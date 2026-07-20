<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

function analyticsFinish(): never
{
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    analyticsFinish();
}

$contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''), 2)[0]));
if ($contentType !== 'application/json') {
    analyticsFinish();
}

require_once __DIR__ . '/analytics-lib.php';
analyticsLoadEnv(dirname(__DIR__) . '/.env');

if (!analyticsEnvBool('ANALYTICS_ENABLED') || !analyticsOriginIsAllowed()) {
    analyticsFinish();
}

$salt = (string)(getenv('ANALYTICS_SALT') ?: '');
$ip = analyticsClientIp();
$userAgent = analyticsCleanText($_SERVER['HTTP_USER_AGENT'] ?? '', 500);
if (strlen($salt) < 32 || $ip === '' || analyticsIsBot($userAgent)) {
    analyticsFinish();
}
if (analyticsRateLimitExceeded('analytics', hash_hmac('sha256', $ip, $salt), 120, 600)) {
    analyticsFinish();
}

$rawBody = file_get_contents('php://input', false, null, 0, 8193);
if ($rawBody === false || strlen($rawBody) > 8192) {
    analyticsFinish();
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    analyticsFinish();
}

$path = analyticsCleanText($payload['path'] ?? '', 255);
if ($path === '' || !str_starts_with($path, '/') || str_contains($path, '..')) {
    analyticsFinish();
}

$referrer = analyticsCleanText($payload['referrer'] ?? '', 1000);
$referrerHost = '';
if ($referrer !== '') {
    $parsedHost = parse_url($referrer, PHP_URL_HOST);
    if (is_string($parsedHost)) {
        $referrerHost = analyticsCleanText(strtolower($parsedHost), 190);
    }
}

$utmSource = analyticsCleanText($payload['utm_source'] ?? '', 120);
$utmMedium = analyticsCleanText($payload['utm_medium'] ?? '', 120);
$utmCampaign = analyticsCleanText($payload['utm_campaign'] ?? '', 160);
$source = analyticsSource($referrerHost, $utmSource);
$location = analyticsLocation($ip);
$visitorHash = hash_hmac('sha256', $ip . '|' . $userAgent, $salt);
$now = time();

try {
    $database = analyticsDatabase();
    $database->beginTransaction();

    $deduplicate = $database->prepare(
        'SELECT id FROM visits
         WHERE visitor_hash = :visitor_hash AND path = :path AND occurred_ts >= :since
         LIMIT 1'
    );
    $deduplicate->execute([
        ':visitor_hash' => $visitorHash,
        ':path' => $path,
        ':since' => $now - 5,
    ]);

    if ($deduplicate->fetchColumn() === false) {
        $insert = $database->prepare(
            'INSERT INTO visits (
                occurred_ts, visitor_hash, path, source, referrer_host, utm_medium,
                utm_campaign, country_code, country_name, city_name, device, browser
            ) VALUES (
                :occurred_ts, :visitor_hash, :path, :source, :referrer_host, :utm_medium,
                :utm_campaign, :country_code, :country_name, :city_name, :device, :browser
            )'
        );
        $insert->execute([
            ':occurred_ts' => $now,
            ':visitor_hash' => $visitorHash,
            ':path' => $path,
            ':source' => $source,
            ':referrer_host' => $referrerHost,
            ':utm_medium' => $utmMedium,
            ':utm_campaign' => $utmCampaign,
            ':country_code' => $location['country_code'],
            ':country_name' => $location['country_name'],
            ':city_name' => $location['city_name'],
            ':device' => analyticsDevice($userAgent),
            ':browser' => analyticsBrowser($userAgent),
        ]);
    }

    $cleanup = $database->prepare('DELETE FROM visits WHERE occurred_ts < :cutoff');
    $cleanup->execute([':cutoff' => $now - (analyticsRetentionDays() * 86400)]);
    $database->commit();
} catch (Throwable $exception) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    error_log('Falha no contador de acessos Trafo: ' . $exception->getMessage());
}

analyticsFinish();
