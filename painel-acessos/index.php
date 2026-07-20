<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/backend/analytics-lib.php';
analyticsLoadEnv(dirname(__DIR__) . '/.env');

header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
header('Cache-Control: no-store, private');

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
session_name('trafo_analytics');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/painel-acessos/',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

$sessionNow = time();
$sessionExpired = !empty($_SESSION['authenticated']) && (
    ($sessionNow - (int)($_SESSION['last_activity'] ?? 0) > 1800)
    || ($sessionNow - (int)($_SESSION['created_at'] ?? 0) > 28800)
);
if ($sessionExpired) {
    $_SESSION = [];
    session_regenerate_id(true);
}
if (!empty($_SESSION['authenticated'])) {
    $_SESSION['last_activity'] = $sessionNow;
}

if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(24));
}

function panelRateFile(): string
{
    $directory = analyticsDataDirectory() . '/login-rate';
    if (!is_dir($directory)) {
        @mkdir($directory, 0750, true);
    }

    $salt = (string)(getenv('ANALYTICS_SALT') ?: 'trafo-panel');
    return $directory . '/' . hash_hmac('sha256', analyticsClientIp() ?: 'unknown', $salt) . '.json';
}

/** @return list<int> */
function panelLoginAttempts(): array
{
    $file = panelRateFile();
    $handle = fopen($file, 'c+');
    if ($handle === false || !flock($handle, LOCK_SH)) {
        if (is_resource($handle)) fclose($handle);
        return array_fill(0, 5, time());
    }
    $attempts = json_decode((string)stream_get_contents($handle), true);
    flock($handle, LOCK_UN);
    fclose($handle);
    if (!is_array($attempts)) {
        return [];
    }

    $cutoff = time() - 900;
    return array_values(array_filter($attempts, static fn(mixed $value): bool => is_int($value) && $value > $cutoff));
}

function panelSaveLoginAttempts(array $attempts): void
{
    $handle = fopen(panelRateFile(), 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) fclose($handle);
        return;
    }
    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, json_encode(array_values($attempts), JSON_THROW_ON_ERROR));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

function panelCsrfIsValid(): bool
{
    $received = (string)($_POST['csrf'] ?? '');
    $expected = (string)($_SESSION['csrf'] ?? '');
    return $received !== '' && $expected !== '' && hash_equals($expected, $received);
}

$configuredUser = (string)(getenv('ANALYTICS_USERNAME') ?: 'admin');
$passwordHash = (string)(getenv('ANALYTICS_PASSWORD_HASH') ?: '');
$salt = (string)(getenv('ANALYTICS_SALT') ?: '');
$setupReady = $passwordHash !== '' && strlen($salt) >= 32;
$loginError = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!panelCsrfIsValid()) {
        $loginError = 'A sessão expirou. Atualize a página e tente novamente.';
    } elseif (isset($_POST['logout'])) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
        header('Location: ./');
        exit;
    } elseif ($setupReady && isset($_POST['login'])) {
        $attempts = panelLoginAttempts();
        if (count($attempts) >= 5) {
            $loginError = 'Muitas tentativas. Aguarde 15 minutos antes de tentar novamente.';
        } else {
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $userMatches = hash_equals($configuredUser, $username);
            $passwordMatches = password_verify($password, $passwordHash);

            if ($userMatches && $passwordMatches) {
                session_regenerate_id(true);
                $_SESSION['authenticated'] = true;
                $_SESSION['created_at'] = time();
                $_SESSION['last_activity'] = time();
                $_SESSION['csrf'] = bin2hex(random_bytes(24));
                panelSaveLoginAttempts([]);
                header('Location: ./');
                exit;
            }

            $attempts[] = time();
            panelSaveLoginAttempts($attempts);
            $loginError = 'Usuário ou senha inválidos.';
        }
    }
}

$authenticated = !empty($_SESSION['authenticated']);

if (!$authenticated):
?><!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow,noarchive">
  <title>Painel de acessos | Trafo Engenharia</title>
  <link rel="icon" href="../assets/favicon.ico" sizes="any">
  <link rel="stylesheet" href="painel.css?v=20260717-security-1">
</head>
<body class="login-page">
  <main class="login-card">
    <img src="../assets/logo-trafo.svg" alt="Trafo Engenharia" width="190" height="58">
    <p class="eyebrow">Área restrita</p>
    <h1>Painel de acessos</h1>
    <?php if (!$setupReady): ?>
      <div class="notice warning">
        <strong>Configuração pendente</strong>
        <p>Defina <code>ANALYTICS_PASSWORD_HASH</code> e <code>ANALYTICS_SALT</code> no arquivo <code>.env</code> do servidor. Consulte o guia incluído no projeto.</p>
      </div>
    <?php else: ?>
      <?php if ($loginError !== ''): ?><p class="login-error" role="alert"><?= analyticsEscape($loginError) ?></p><?php endif; ?>
      <form method="post" autocomplete="on">
        <input type="hidden" name="csrf" value="<?= analyticsEscape($_SESSION['csrf']) ?>">
        <label>Usuário<input type="text" name="username" autocomplete="username" required autofocus></label>
        <label>Senha<input type="password" name="password" autocomplete="current-password" required></label>
        <button type="submit" name="login" value="1">Entrar</button>
      </form>
    <?php endif; ?>
  </main>
</body>
</html><?php
exit;
endif;

$allowedRanges = [7, 30, 90];
$range = (int)($_GET['dias'] ?? 30);
if (!in_array($range, $allowedRanges, true)) {
    $range = 30;
}
$since = time() - ($range * 86400);
$databaseError = '';
$metrics = ['views' => 0, 'visitors' => 0, 'countries' => 0, 'cities' => 0];
$sources = $countries = $cities = $pages = $devices = $browsers = $recent = [];
$dailyCounts = [];

try {
    $database = analyticsDatabase();
    $metricQuery = $database->prepare(
        "SELECT COUNT(*) AS views,
                COUNT(DISTINCT visitor_hash) AS visitors,
                COUNT(DISTINCT NULLIF(country_code, '')) AS countries,
                COUNT(DISTINCT NULLIF(city_name, '')) AS cities
         FROM visits WHERE occurred_ts >= :since"
    );
    $metricQuery->execute([':since' => $since]);
    $metrics = $metricQuery->fetch() ?: $metrics;

    $groupedQuery = static function (PDO $database, string $sql, int $since): array {
        $statement = $database->prepare($sql);
        $statement->execute([':since' => $since]);
        return $statement->fetchAll();
    };

    $sources = $groupedQuery($database,
        "SELECT source AS label, COUNT(*) AS total
         FROM visits WHERE occurred_ts >= :since AND source != 'Navegação interna'
         GROUP BY source ORDER BY total DESC, label ASC LIMIT 12", $since);
    $countries = $groupedQuery($database,
        "SELECT CASE WHEN country_name = '' THEN 'Não identificado' ELSE country_name END AS label,
                country_code AS code, COUNT(*) AS total
         FROM visits WHERE occurred_ts >= :since
         GROUP BY country_name, country_code ORDER BY total DESC, label ASC LIMIT 12", $since);
    $cities = $groupedQuery($database,
        "SELECT CASE WHEN city_name = '' THEN 'Não identificada' ELSE city_name END AS label,
                country_code AS code, COUNT(*) AS total
         FROM visits WHERE occurred_ts >= :since
         GROUP BY city_name, country_code ORDER BY total DESC, label ASC LIMIT 12", $since);
    $pages = $groupedQuery($database,
        'SELECT path AS label, COUNT(*) AS total FROM visits WHERE occurred_ts >= :since
         GROUP BY path ORDER BY total DESC, label ASC LIMIT 12', $since);
    $devices = $groupedQuery($database,
        'SELECT device AS label, COUNT(*) AS total FROM visits WHERE occurred_ts >= :since
         GROUP BY device ORDER BY total DESC, label ASC', $since);
    $browsers = $groupedQuery($database,
        'SELECT browser AS label, COUNT(*) AS total FROM visits WHERE occurred_ts >= :since
         GROUP BY browser ORDER BY total DESC, label ASC', $since);

    $recentQuery = $database->prepare(
        'SELECT occurred_ts, path, source, city_name, country_name, country_code, device, browser
         FROM visits WHERE occurred_ts >= :since ORDER BY occurred_ts DESC LIMIT 50'
    );
    $recentQuery->execute([':since' => $since]);
    $recent = $recentQuery->fetchAll();

    $dailyQuery = $database->prepare(
        'SELECT occurred_ts FROM visits WHERE occurred_ts >= :since ORDER BY occurred_ts ASC'
    );
    $dailyQuery->execute([':since' => $since]);
    foreach ($dailyQuery->fetchAll(PDO::FETCH_COLUMN) as $timestamp) {
        $date = (new DateTimeImmutable('@' . (int)$timestamp))->setTimezone(analyticsTimezone())->format('Y-m-d');
        $dailyCounts[$date] = ($dailyCounts[$date] ?? 0) + 1;
    }
} catch (Throwable $exception) {
    $databaseError = 'O banco de dados não pôde ser aberto. Confirme a extensão pdo_sqlite e as permissões da pasta backend/data.';
    error_log('Falha no painel de acessos Trafo: ' . $exception->getMessage());
}

$maxDaily = max([1, ...array_values($dailyCounts)]);
?><!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow,noarchive">
  <title>Painel de acessos | Trafo Engenharia</title>
  <link rel="icon" href="../assets/favicon.ico" sizes="any">
  <link rel="stylesheet" href="painel.css?v=20260717-security-1">
</head>
<body>
  <header class="panel-header">
    <div class="panel-container header-inner">
      <a href="../index.html" aria-label="Abrir o site"><img src="../assets/logo-trafo.svg" alt="Trafo Engenharia" width="190" height="58"></a>
      <div>
        <span>Área restrita</span>
        <h1>Painel de acessos</h1>
      </div>
      <form method="post" class="logout-form">
        <input type="hidden" name="csrf" value="<?= analyticsEscape($_SESSION['csrf']) ?>">
        <button type="submit" name="logout" value="1">Sair</button>
      </form>
    </div>
  </header>

  <main class="panel-container panel-main">
    <div class="panel-toolbar">
      <div>
        <h2>Visão geral</h2>
        <p>Localização aproximada. IP bruto não é armazenado.</p>
      </div>
      <nav aria-label="Período">
        <?php foreach ($allowedRanges as $days): ?>
          <a href="?dias=<?= $days ?>"<?= $range === $days ? ' class="active" aria-current="page"' : '' ?>><?= $days ?> dias</a>
        <?php endforeach; ?>
      </nav>
    </div>

    <?php if (!analyticsEnvBool('ANALYTICS_ENABLED')): ?>
      <div class="notice warning"><strong>Coleta desativada</strong><p>Defina <code>ANALYTICS_ENABLED=true</code> no arquivo <code>.env</code>.</p></div>
    <?php endif; ?>
    <?php if (!is_file(analyticsGeoIpDatabasePath())): ?>
      <div class="notice warning"><strong>Cidade e país ainda não serão identificados</strong><p>Adicione a base <code>GeoLite2-City.mmdb</code> conforme o guia de configuração.</p></div>
    <?php endif; ?>
    <?php if ($databaseError !== ''): ?><div class="notice error" role="alert"><?= analyticsEscape($databaseError) ?></div><?php endif; ?>

    <section class="metrics" aria-label="Indicadores">
      <article><span>Visualizações</span><strong><?= number_format((int)$metrics['views'], 0, ',', '.') ?></strong></article>
      <article><span>Visitantes aproximados</span><strong><?= number_format((int)$metrics['visitors'], 0, ',', '.') ?></strong></article>
      <article><span>Países identificados</span><strong><?= number_format((int)$metrics['countries'], 0, ',', '.') ?></strong></article>
      <article><span>Cidades identificadas</span><strong><?= number_format((int)$metrics['cities'], 0, ',', '.') ?></strong></article>
    </section>

    <section class="panel-card chart-card">
      <div class="card-heading"><h2>Acessos por dia</h2><span>Últimos <?= $range ?> dias</span></div>
      <?php if ($dailyCounts === []): ?>
        <p class="empty">Ainda não há acessos registrados neste período.</p>
      <?php else: ?>
        <div class="bar-chart" aria-label="Gráfico de acessos por dia">
          <?php foreach ($dailyCounts as $date => $total): ?>
            <?php $heightClass = max(5, min(100, (int)(ceil((((int)$total / $maxDaily) * 100) / 5) * 5))); ?>
            <div class="bar-item" title="<?= analyticsEscape((new DateTimeImmutable($date))->format('d/m/Y')) ?>: <?= (int)$total ?>">
              <span class="bar size-<?= $heightClass ?>"></span>
              <span class="sr-only"><?= analyticsEscape($date) ?>: <?= (int)$total ?> acessos</span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <?php
    $renderRanking = static function (string $title, array $rows, bool $showCode = false): void {
        echo '<section class="panel-card"><div class="card-heading"><h2>' . analyticsEscape($title) . '</h2></div>';
        if ($rows === []) {
            echo '<p class="empty">Sem dados neste período.</p></section>';
            return;
        }
        $maximum = max(1, ...array_map(static fn(array $row): int => (int)$row['total'], $rows));
        echo '<ol class="ranking">';
        foreach ($rows as $row) {
            $label = (string)$row['label'];
            $code = $showCode && !empty($row['code']) ? ' <small>' . analyticsEscape($row['code']) . '</small>' : '';
            $widthClass = max(5, min(100, (int)(ceil((((int)$row['total'] / $maximum) * 100) / 5) * 5)));
            echo '<li><div><span>' . analyticsEscape($label) . $code . '</span><strong>' . number_format((int)$row['total'], 0, ',', '.') . '</strong></div><i class="size-' . $widthClass . '"></i></li>';
        }
        echo '</ol></section>';
    };
    ?>
    <div class="panel-grid">
      <?php $renderRanking('Origem dos acessos', $sources); ?>
      <?php $renderRanking('Países', $countries, true); ?>
      <?php $renderRanking('Cidades', $cities, true); ?>
      <?php $renderRanking('Páginas mais acessadas', $pages); ?>
      <?php $renderRanking('Dispositivos', $devices); ?>
      <?php $renderRanking('Navegadores', $browsers); ?>
    </div>

    <section class="panel-card recent-card">
      <div class="card-heading"><h2>Acessos recentes</h2><span>Até 50 registros</span></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Data</th><th>Página</th><th>Origem</th><th>Cidade / país</th><th>Dispositivo</th></tr></thead>
          <tbody>
          <?php if ($recent === []): ?>
            <tr><td colspan="5" class="empty">Ainda não há acessos registrados neste período.</td></tr>
          <?php else: foreach ($recent as $visit): ?>
            <tr>
              <td><?= analyticsEscape(analyticsFormatDateTime((int)$visit['occurred_ts'])) ?></td>
              <td><?= analyticsEscape($visit['path']) ?></td>
              <td><?= analyticsEscape($visit['source']) ?></td>
              <td><?= analyticsEscape($visit['city_name'] ?: 'Não identificada') ?> / <?= analyticsEscape($visit['country_name'] ?: $visit['country_code'] ?: 'Não identificado') ?></td>
              <td><?= analyticsEscape($visit['device']) ?> · <?= analyticsEscape($visit['browser']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <p class="panel-footnote">Os dados são mantidos por <?= analyticsRetentionDays() ?> dias. A localização por IP é aproximada e pode ser afetada por VPN, proxy ou rede móvel.</p>
  </main>
</body>
</html>
