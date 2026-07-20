<?php
declare(strict_types=1);

function analyticsLoadEnv(string $file): void
{
    if (!is_file($file)) {
        return;
    }

    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        if ($key !== '' && getenv($key) === false) {
            putenv("{$key}={$value}");
        }
    }
}

function analyticsEnvBool(string $key, bool $default = false): bool
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }

    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
}

function analyticsDataDirectory(): string
{
    $configured = trim((string)(getenv('ANALYTICS_DATA_DIR') ?: ''));
    if ($configured !== '') {
        return str_starts_with($configured, '/')
            ? rtrim($configured, '/')
            : dirname(__DIR__) . '/' . trim($configured, '/');
    }

    return __DIR__ . '/data';
}

function analyticsDatabase(): PDO
{
    $dataDirectory = analyticsDataDirectory();
    if (!is_dir($dataDirectory) && !mkdir($dataDirectory, 0750, true) && !is_dir($dataDirectory)) {
        throw new RuntimeException('Não foi possível criar o diretório de dados do painel.');
    }

    $database = new PDO('sqlite:' . $dataDirectory . '/analytics.sqlite');
    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $database->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $database->exec('PRAGMA journal_mode = WAL');
    $database->exec('PRAGMA busy_timeout = 5000');
    $database->exec(
        'CREATE TABLE IF NOT EXISTS visits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            occurred_ts INTEGER NOT NULL,
            visitor_hash TEXT NOT NULL,
            path TEXT NOT NULL,
            source TEXT NOT NULL,
            referrer_host TEXT NOT NULL DEFAULT \'\',
            utm_medium TEXT NOT NULL DEFAULT \'\',
            utm_campaign TEXT NOT NULL DEFAULT \'\',
            country_code TEXT NOT NULL DEFAULT \'\',
            country_name TEXT NOT NULL DEFAULT \'\',
            city_name TEXT NOT NULL DEFAULT \'\',
            device TEXT NOT NULL,
            browser TEXT NOT NULL
        )'
    );
    $database->exec('CREATE INDEX IF NOT EXISTS idx_visits_occurred_ts ON visits (occurred_ts)');
    $database->exec('CREATE INDEX IF NOT EXISTS idx_visits_visitor ON visits (visitor_hash, occurred_ts)');
    $database->exec('CREATE INDEX IF NOT EXISTS idx_visits_source ON visits (source, occurred_ts)');
    $database->exec('CREATE INDEX IF NOT EXISTS idx_visits_location ON visits (country_code, city_name, occurred_ts)');

    return $database;
}

function analyticsCleanText(mixed $value, int $maxLength): string
{
    $text = trim((string)$value);
    $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text) ?? '';
    return mb_substr($text, 0, $maxLength);
}

function analyticsRateLimitExceeded(string $bucket, string $key, int $limit, int $windowSeconds): bool
{
    $directory = analyticsDataDirectory() . '/request-rate';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        return true;
    }

    $safeBucket = preg_replace('/[^a-z0-9_-]/i', '', $bucket) ?: 'default';
    $file = $directory . '/' . $safeBucket . '-' . hash('sha256', $key) . '.json';
    $handle = fopen($file, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        return true;
    }

    $now = time();
    $attempts = json_decode((string)stream_get_contents($handle), true);
    $attempts = is_array($attempts) ? $attempts : [];
    $attempts = array_values(array_filter(
        $attempts,
        static fn(mixed $timestamp): bool => is_int($timestamp) && $timestamp > $now - $windowSeconds
    ));
    $exceeded = count($attempts) >= $limit;

    if (!$exceeded) {
        $attempts[] = $now;
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($attempts, JSON_THROW_ON_ERROR));
        fflush($handle);
    }

    flock($handle, LOCK_UN);
    fclose($handle);
    return $exceeded;
}

function analyticsClientIp(): string
{
    if (analyticsEnvBool('TRUST_CLOUDFLARE') && isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $cloudflareIp = trim((string)$_SERVER['HTTP_CF_CONNECTING_IP']);
        if (filter_var($cloudflareIp, FILTER_VALIDATE_IP)) {
            return $cloudflareIp;
        }
    }

    $remoteIp = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    return filter_var($remoteIp, FILTER_VALIDATE_IP) ? $remoteIp : '';
}

function analyticsIsBot(string $userAgent): bool
{
    if ($userAgent === '') {
        return true;
    }

    return (bool)preg_match(
        '/bot|crawler|spider|slurp|bingpreview|facebookexternalhit|whatsapp|telegrambot|headless|lighthouse|pagespeed|uptimerobot|monitoring/i',
        $userAgent
    );
}

function analyticsDevice(string $userAgent): string
{
    if (preg_match('/ipad|tablet|kindle|silk/i', $userAgent)) {
        return 'Tablet';
    }
    if (preg_match('/mobile|iphone|ipod|android.*mobile|windows phone/i', $userAgent)) {
        return 'Celular';
    }

    return 'Computador';
}

function analyticsBrowser(string $userAgent): string
{
    if (preg_match('/Edg\//i', $userAgent)) {
        return 'Edge';
    }
    if (preg_match('/OPR\/|Opera/i', $userAgent)) {
        return 'Opera';
    }
    if (preg_match('/CriOS\//i', $userAgent)) {
        return 'Chrome iOS';
    }
    if (preg_match('/Chrome\//i', $userAgent)) {
        return 'Chrome';
    }
    if (preg_match('/FxiOS\//i', $userAgent)) {
        return 'Firefox iOS';
    }
    if (preg_match('/Firefox\//i', $userAgent)) {
        return 'Firefox';
    }
    if (preg_match('/Safari\//i', $userAgent)) {
        return 'Safari';
    }

    return 'Outro';
}

function analyticsGeoIpDatabasePath(): string
{
    $configured = trim((string)(getenv('GEOIP_DATABASE') ?: 'backend/data/GeoLite2-City.mmdb'));
    return str_starts_with($configured, '/')
        ? $configured
        : dirname(__DIR__) . '/' . ltrim($configured, '/');
}

/** @return array{country_code: string, country_name: string, city_name: string} */
function analyticsLocation(string $ip): array
{
    $empty = ['country_code' => '', 'country_name' => '', 'city_name' => ''];
    if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return $empty;
    }

    $databasePath = analyticsGeoIpDatabasePath();
    $autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($databasePath) || !is_file($autoloadPath)) {
        return $empty;
    }

    try {
        require_once $autoloadPath;
        if (!class_exists(GeoIp2\Database\Reader::class)) {
            return $empty;
        }

        $reader = new GeoIp2\Database\Reader($databasePath);
        $record = $reader->city($ip);
        $countryNames = $record->country->names ?? [];
        $cityNames = $record->city->names ?? [];
        $reader->close();

        return [
            'country_code' => analyticsCleanText($record->country->isoCode ?? '', 2),
            'country_name' => analyticsCleanText($countryNames['pt-BR'] ?? $countryNames['pt'] ?? $countryNames['en'] ?? '', 100),
            'city_name' => analyticsCleanText($cityNames['pt-BR'] ?? $cityNames['pt'] ?? $cityNames['en'] ?? '', 120),
        ];
    } catch (Throwable $exception) {
        error_log('Falha na geolocalização do painel Trafo: ' . $exception->getMessage());
        return $empty;
    }
}

/** @return list<string> */
function analyticsAllowedOrigins(): array
{
    $raw = (string)(getenv('ALLOWED_ORIGINS') ?: getenv('ALLOWED_ORIGIN') ?: '');
    return array_values(array_filter(array_map(
        static fn(string $value): string => rtrim(trim($value), '/'),
        explode(',', $raw)
    )));
}

function analyticsOriginIsAllowed(): bool
{
    $fetchSite = strtolower((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
    if ($fetchSite === 'cross-site') {
        return false;
    }

    $origin = rtrim((string)($_SERVER['HTTP_ORIGIN'] ?? ''), '/');
    $allowedOrigins = analyticsAllowedOrigins();
    if ($origin === '' || $allowedOrigins === []) {
        return true;
    }

    foreach ($allowedOrigins as $allowedOrigin) {
        if (hash_equals($allowedOrigin, $origin)) {
            return true;
        }
    }

    return false;
}

function analyticsSource(string $referrerHost, string $utmSource): string
{
    if ($utmSource !== '') {
        return $utmSource;
    }

    if ($referrerHost === '') {
        return 'Direto';
    }

    $normalizeHost = static fn(string $host): string => preg_replace('/^www\./i', '', strtolower($host)) ?? '';
    $currentHost = preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')) ?? '';
    if ($currentHost !== '' && hash_equals($normalizeHost($currentHost), $normalizeHost($referrerHost))) {
        return 'Navegação interna';
    }

    return $referrerHost;
}

function analyticsRetentionDays(): int
{
    $days = (int)(getenv('ANALYTICS_RETENTION_DAYS') ?: 90);
    return max(7, min(730, $days));
}

function analyticsTimezone(): DateTimeZone
{
    $name = (string)(getenv('ANALYTICS_TIMEZONE') ?: 'America/Sao_Paulo');
    try {
        return new DateTimeZone($name);
    } catch (Throwable) {
        return new DateTimeZone('America/Sao_Paulo');
    }
}

function analyticsFormatDateTime(int $timestamp): string
{
    return (new DateTimeImmutable('@' . $timestamp))
        ->setTimezone(analyticsTimezone())
        ->format('d/m/Y H:i');
}

function analyticsEscape(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
