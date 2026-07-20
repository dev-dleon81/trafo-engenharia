<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

fwrite(STDOUT, 'Digite uma senha forte para o painel: ');
$canHideInput = DIRECTORY_SEPARATOR === '/' && function_exists('shell_exec');
if ($canHideInput) {
    shell_exec('stty -echo');
}
$password = trim((string)fgets(STDIN));
if ($canHideInput) {
    shell_exec('stty echo');
    fwrite(STDOUT, PHP_EOL);
}
if (mb_strlen($password) < 12) {
    fwrite(STDERR, "A senha precisa ter pelo menos 12 caracteres.\n");
    exit(1);
}

echo 'ANALYTICS_PASSWORD_HASH=' . password_hash($password, PASSWORD_DEFAULT) . PHP_EOL;
echo 'ANALYTICS_SALT=' . bin2hex(random_bytes(32)) . PHP_EOL;
