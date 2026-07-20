# Requisitos técnicos — formulário e contador de acessos

Revisão: 17/07/2026

## Resposta direta sobre a versão do PHP

O backend foi escrito com compatibilidade inicial para **PHP 8.1 ou superior** e não depende de uma versão exclusiva do PHP. Para esta nova publicação, o requisito mínimo foi atualizado para **PHP 8.2**, porque o PHP 8.1 encerrou seu suporte oficial em 31/12/2025.

Para a hospedagem da Trafo Engenharia, a recomendação é usar **PHP 8.4 na versão de manutenção mais recente oferecida pelo provedor**. O PHP 8.4 permanece oficialmente suportado e oferece uma janela de segurança maior. Consulte a [tabela oficial de versões suportadas do PHP](https://www.php.net/supported-versions.php).

O PHP não é enviado dentro do site: ele é fornecido e atualizado pela hospedagem. Este pacote passou por análise estática, validação sintática estrutural e testes de navegador. O envio SMTP, a gravação SQLite e a geolocalização devem ser testados novamente no servidor final, com as credenciais reais.

## Requisitos obrigatórios

- PHP **8.2 ou superior**; recomendado: **PHP 8.4 atualizado**;
- Composer 2;
- extensões PHP `mbstring`, `pdo`, `pdo_sqlite`, `openssl`, `json`, `filter`, `hash` e `session`;
- HTTPS com certificado válido;
- servidor Apache 2.4 com `.htaccess` habilitado ou regras equivalentes no Nginx;
- permissão de leitura e gravação do processo PHP em `backend/data` e `backend/rate-limit`;
- acesso de saída ao servidor SMTP do Google Workspace;
- pasta `vendor` gerada pelo Composer.

## Dependências PHP do projeto

As versões são controladas por `composer.json`:

- `phpmailer/phpmailer` `^6.10`: envio autenticado do formulário por SMTP;
- `geoip2/geoip2` `^3.3`: leitura da base GeoLite2 para cidade e país;
- `ext-mbstring`: tratamento seguro de textos Unicode;
- `ext-pdo`: acesso ao banco;
- `pdo_sqlite`: armazenamento das estatísticas em SQLite.

Em projetos PHP, o arquivo equivalente a um “requirements” é o **`composer.json`**. Este documento explica os requisitos operacionais que o Composer sozinho não descreve.

## Configuração do formulário

O formulário usa `backend/send-email.php` e PHPMailer. No `.env` da hospedagem, configurar:

```dotenv
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USERNAME=contato@trafoengenharia.com.br
SMTP_PASSWORD=SENHA_DE_APP_DO_GOOGLE
MAIL_FROM=contato@trafoengenharia.com.br
MAIL_TO=contato@trafoengenharia.com.br
ALLOWED_ORIGINS=https://www.trafoengenharia.com.br,https://trafoengenharia.com.br
```

A conta Google Workspace deve ter verificação em duas etapas e uma senha de app exclusiva para o site. A senha real nunca deve ser gravada no código, no ZIP ou em repositório.

## Configuração do contador e painel

O contador usa `backend/track-visit.php`, PDO e SQLite. No `.env`, configurar:

```dotenv
ANALYTICS_ENABLED=true
ANALYTICS_USERNAME=admin
ANALYTICS_PASSWORD_HASH=HASH_GERADO
ANALYTICS_SALT=SALT_GERADO
ANALYTICS_RETENTION_DAYS=90
ANALYTICS_TIMEZONE=America/Sao_Paulo
ANALYTICS_DATA_DIR=backend/data
GEOIP_DATABASE=backend/data/GeoLite2-City.mmdb
```

A base `GeoLite2-City.mmdb` é necessária somente para preencher cidade e país. Sem ela, o contador continua registrando visualizações, origem, página, dispositivo e navegador, mas a localização fica vazia.

## Instalação e validação na hospedagem

Executar na raiz do site:

```bash
php -v
php -m
composer install --no-dev --optimize-autoloader
composer check-platform-reqs --no-dev
composer audit --locked --no-dev
php backend/generate-analytics-secrets.php
```

O comando `composer check-platform-reqs` confere a versão real do PHP e as extensões instaladas no servidor, conforme a [documentação oficial do Composer](https://getcomposer.org/doc/03-cli.md#check-platform-reqs). Não publicar se qualquer verificação obrigatória falhar.

## Checklist final

1. Selecionar PHP 8.4 no painel da hospedagem.
2. Ativar `mbstring`, `pdo`, `pdo_sqlite` e `openssl`.
3. Instalar as dependências e enviar a pasta `vendor`.
4. Criar o `.env` com credenciais reais e mantê-lo bloqueado pela web.
5. Garantir escrita somente nas pastas de dados e rate limit.
6. Instalar e manter atualizada a base GeoLite2 City.
7. Testar um envio real para `contato@trafoengenharia.com.br`.
8. Gerar uma visita e confirmar sua exibição em `/painel-acessos/`.
9. Confirmar que `.env`, SQLite, GeoLite2, Composer e documentos internos retornam 403 ou 404 pela internet.
10. Manter PHP e dependências atualizados e executar auditoria periódica.
