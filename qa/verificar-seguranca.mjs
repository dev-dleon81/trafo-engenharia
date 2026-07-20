import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname), '..');
const read = relativePath => fs.readFileSync(path.join(root, relativePath), 'utf8');
const failures = [];
const checks = [];

function check(name, condition) {
  checks.push({ name, passed: Boolean(condition) });
  if (!condition) failures.push(name);
}

const htaccess = read('.htaccess');
const backendHtaccess = read('backend/.htaccess');
const sendEmail = read('backend/send-email.php');
const analytics = read('backend/analytics-lib.php') + read('backend/track-visit.php');
const panel = read('painel-acessos/index.php');
const composer = JSON.parse(read('composer.json'));
const htmlFiles = fs.readdirSync(root).filter(file => file.endsWith('.html'));
const publicPhp = ['backend/send-email.php', 'backend/track-visit.php', 'backend/analytics-lib.php', 'painel-acessos/index.php'];

check('CSP restritiva configurada', htaccess.includes("script-src 'self'") && htaccess.includes("frame-ancestors 'none'"));
check('CSP não permite script ou estilo inline', !htaccess.includes("'unsafe-inline'") && !panel.includes('style="'));
check('HSTS condicionado a HTTPS', htaccess.includes('Strict-Transport-Security') && htaccess.includes("%{HTTPS} == 'on'"));
check('Proteção contra MIME sniffing e clickjacking', htaccess.includes('X-Content-Type-Options') && htaccess.includes('X-Frame-Options'));
check('Política de permissões configurada', htaccess.includes('Permissions-Policy'));
check('Listagem de diretórios desativada', htaccess.includes('Options -Indexes'));
check('Arquivos internos do backend bloqueados', backendHtaccess.includes('send-email\\.php') && backendHtaccess.includes('track-visit\\.php'));
check('Arquivos sensíveis e documentação bloqueados', htaccess.includes('composer\\.') && htaccess.includes('md|log|ini'));

check('Formulário limita tamanho total da requisição', sendEmail.includes("$contentLength > 20_000"));
check('Formulário restringe método e tipo de conteúdo', sendEmail.includes("REQUEST_METHOD") && sendEmail.includes('application/x-www-form-urlencoded'));
check('Formulário valida origem e contexto de navegação', sendEmail.includes('HTTP_ORIGIN') && sendEmail.includes("$fetchSite === 'cross-site'"));
check('Formulário possui honeypot e rate limit com lock', sendEmail.includes("field('website'") && sendEmail.includes('flock($rateHandle, LOCK_EX)'));
check('E-mail validado no servidor', sendEmail.includes('FILTER_VALIDATE_EMAIL'));
check('Conteúdo do e-mail codificado', sendEmail.includes('htmlspecialchars') && sendEmail.includes('ENT_SUBSTITUTE'));
check('Envio usa SMTP autenticado', sendEmail.includes('$mail->isSMTP()') && sendEmail.includes('$mail->SMTPAuth = true'));

check('Analytics limita corpo e frequência', analytics.includes('8193') && analytics.includes("analyticsRateLimitExceeded('analytics'"));
check('Analytics usa HMAC e não grava IP bruto', analytics.includes("hash_hmac('sha256'") && !analytics.includes('INSERT INTO visits (ip'));
check('Consultas ao banco são preparadas', analytics.includes('$database->prepare('));
check('Saída do painel é codificada', panel.includes('analyticsEscape('));
check('Painel usa CSRF e comparação constante', panel.includes('panelCsrfIsValid') && panel.includes('hash_equals'));
check('Painel usa hash de senha e renovação de sessão', panel.includes('password_verify') && panel.includes('session_regenerate_id(true)'));
check('Cookie do painel é HttpOnly, Secure quando HTTPS e SameSite Strict', panel.includes("'httponly' => true") && panel.includes("'secure' => $https") && panel.includes("'samesite' => 'Strict'"));
check('Sessão possui expiração por inatividade', panel.includes("$_SESSION['last_activity']") && panel.includes('> 1800'));

const dangerousPattern = /(?<!->)\b(?:eval|assert|system|passthru|exec|shell_exec|popen|proc_open)\s*\(/;
check('PHP público sem execução de comandos', publicPhp.every(file => !dangerousPattern.test(read(file))));
check('Projeto não contém arquivo .env', !fs.existsSync(path.join(root, '.env')));
check('Dependência PHPMailer exige versão moderna', String(composer.require?.['phpmailer/phpmailer'] || '').startsWith('^6.10'));

for (const file of htmlFiles) {
  const html = read(file);
  check(`${file}: sem JavaScript inline`, !/<script(?![^>]*\bsrc=)[^>]*>/i.test(html));
  check(`${file}: sem handlers HTML inline`, !/\son[a-z]+\s*=/i.test(html));
  check(`${file}: sem URL javascript:`, !/javascript\s*:/i.test(html));
  const unsafeBlank = [...html.matchAll(/<a\b[^>]*target=["']_blank["'][^>]*>/gi)]
    .some(match => !/rel=["'][^"']*noopener/i.test(match[0]));
  check(`${file}: links externos isolam window.opener`, !unsafeBlank);
}

console.log(`Verificações executadas: ${checks.length}`);
console.log(`Aprovadas: ${checks.filter(item => item.passed).length}`);
console.log(`Falhas: ${failures.length}`);
if (failures.length) {
  for (const failure of failures) console.error(`FALHA: ${failure}`);
  process.exit(1);
}
