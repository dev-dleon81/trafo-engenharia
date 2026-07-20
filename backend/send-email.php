<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

function respond(int $status, bool $success, string $message): never {
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function loadEnv(string $file): void {
    if (!is_file($file)) return;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        if ($key !== '' && getenv($key) === false) putenv("{$key}={$value}");
    }
}

function field(string $name, int $maxLength): string {
    $raw = $_POST[$name] ?? '';
    if (!is_string($raw) && !is_numeric($raw)) return '';
    $value = trim((string)$raw);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    return mb_substr($value, 0, $maxLength);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') respond(405, false, 'Método não permitido.');

$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength <= 0 || $contentLength > 20_000) respond(413, false, 'Solicitação inválida ou muito grande.');
$contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''), 2)[0]));
if (!in_array($contentType, ['application/x-www-form-urlencoded', 'multipart/form-data'], true)) {
    respond(415, false, 'Formato de solicitação não aceito.');
}

loadEnv(dirname(__DIR__) . '/.env');

$allowedOriginsRaw = (string)(getenv('ALLOWED_ORIGINS') ?: getenv('ALLOWED_ORIGIN') ?: '');
$allowedOrigins = array_values(array_filter(array_map(
    static fn(string $value): string => rtrim(trim($value), '/'),
    explode(',', $allowedOriginsRaw)
)));
$origin = rtrim((string)($_SERVER['HTTP_ORIGIN'] ?? ''), '/');
$fetchSite = strtolower((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
if ($fetchSite === 'cross-site') respond(403, false, 'Origem da solicitação não autorizada.');
if ($allowedOrigins !== []) {
    $originAllowed = $origin === '' && $fetchSite === 'same-origin';
    foreach ($allowedOrigins as $allowedOrigin) {
        if ($origin !== '' && hash_equals($allowedOrigin, $origin)) {
            $originAllowed = true;
            break;
        }
    }
    if (!$originAllowed) respond(403, false, 'Origem da solicitação não autorizada.');
}

// Honeypot: bots costumam preencher este campo invisível.
if (field('website', 200) !== '') respond(200, true, 'Solicitação recebida.');

// Limite atômico: até 5 tentativas por IP a cada 15 minutos.
$rateDir = __DIR__ . '/rate-limit';
if (!is_dir($rateDir) && !mkdir($rateDir, 0750, true) && !is_dir($rateDir)) {
    respond(503, false, 'Serviço temporariamente indisponível.');
}
$ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$rateFile = $rateDir . '/' . hash('sha256', $ip) . '.json';
$now = time();
$window = 900;
$rateHandle = fopen($rateFile, 'c+');
if ($rateHandle === false || !flock($rateHandle, LOCK_EX)) {
    if (is_resource($rateHandle)) fclose($rateHandle);
    respond(503, false, 'Serviço temporariamente indisponível.');
}
$attempts = json_decode((string)stream_get_contents($rateHandle), true);
$attempts = is_array($attempts) ? $attempts : [];
$attempts = array_values(array_filter($attempts, static fn($time) => is_int($time) && $time > $now - $window));
if (count($attempts) >= 5) {
    flock($rateHandle, LOCK_UN);
    fclose($rateHandle);
    respond(429, false, 'Muitas tentativas. Aguarde alguns minutos e tente novamente.');
}
$attempts[] = $now;
rewind($rateHandle);
ftruncate($rateHandle, 0);
fwrite($rateHandle, json_encode($attempts, JSON_THROW_ON_ERROR));
fflush($rateHandle);
flock($rateHandle, LOCK_UN);
fclose($rateHandle);

$name = field('name', 120);
$company = field('company', 160);
$email = field('email', 180);
$phone = field('phone', 60);
$subject = field('subject', 180);
$message = field('message', 5000);

if ($name === '' || $email === '' || $subject === '' || $message === '') {
    respond(422, false, 'Preencha todos os campos obrigatórios.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) respond(422, false, 'Informe um endereço de e-mail válido.');

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) respond(500, false, 'Dependências do servidor não instaladas. Execute composer install.');
require $autoload;

$requiredEnv = ['SMTP_HOST', 'SMTP_PORT', 'SMTP_USERNAME', 'SMTP_PASSWORD', 'MAIL_FROM', 'MAIL_TO'];
foreach ($requiredEnv as $key) {
    if ((string)(getenv($key) ?: '') === '') {
        error_log("Configuração ausente no formulário Trafo: {$key}");
        respond(500, false, 'Serviço de envio temporariamente indisponível.');
    }
}

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = (string)getenv('SMTP_HOST');
    $mail->Port = (int)getenv('SMTP_PORT');
    $mail->SMTPAuth = true;
    $mail->Username = (string)getenv('SMTP_USERNAME');
    $mail->Password = (string)getenv('SMTP_PASSWORD');

    $encryption = strtolower((string)(getenv('SMTP_ENCRYPTION') ?: 'tls'));
    $mail->SMTPSecure = $encryption === 'ssl'
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;

    $mail->CharSet = PHPMailer::CHARSET_UTF8;
    $mail->setFrom((string)getenv('MAIL_FROM'), (string)(getenv('MAIL_FROM_NAME') ?: 'Site Trafo Engenharia'));
    $mail->addAddress((string)getenv('MAIL_TO'), (string)(getenv('MAIL_TO_NAME') ?: 'Trafo Engenharia'));
    $mail->addReplyTo($email, $name);
    $mail->Subject = '[Site Trafo Engenharia] ' . $subject;

    $safe = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $mail->isHTML(true);
    $mail->Body = '<h2>Nova solicitação pelo site</h2>'
        . '<p><strong>Nome:</strong> ' . $safe($name) . '</p>'
        . '<p><strong>Empresa:</strong> ' . $safe($company ?: 'Não informada') . '</p>'
        . '<p><strong>E-mail:</strong> ' . $safe($email) . '</p>'
        . '<p><strong>Telefone:</strong> ' . $safe($phone ?: 'Não informado') . '</p>'
        . '<p><strong>Assunto:</strong> ' . $safe($subject) . '</p>'
        . '<p><strong>Mensagem:</strong><br>' . nl2br($safe($message)) . '</p>';
    $mail->AltBody = "Nova solicitação pelo site\n\nNome: {$name}\nEmpresa: " . ($company ?: 'Não informada')
        . "\nE-mail: {$email}\nTelefone: " . ($phone ?: 'Não informado')
        . "\nAssunto: {$subject}\n\nMensagem:\n{$message}";

    $mail->send();
    respond(200, true, 'Solicitação enviada com sucesso. Em breve entraremos em contato.');
} catch (Exception $exception) {
    error_log('Falha no formulário Trafo: ' . $exception->getMessage());
    respond(500, false, 'Não foi possível enviar a solicitação agora. Tente novamente mais tarde.');
}
