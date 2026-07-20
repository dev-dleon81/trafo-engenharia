# Configuração do formulário — Google Workspace

O código já está preparado para enviar as mensagens do formulário para `contato@trafoengenharia.com.br` usando a própria conta como remetente autenticado.

## Configuração recomendada

Use o servidor SMTP do Gmail com uma senha de app exclusiva:

- servidor: `smtp.gmail.com`
- porta: `587`
- criptografia: `tls`
- usuário: `contato@trafoengenharia.com.br`
- remetente: `contato@trafoengenharia.com.br`
- destinatário: `contato@trafoengenharia.com.br`

## 1. Gerar a senha de app

1. Entre na conta Google `contato@trafoengenharia.com.br`.
2. Ative a verificação em duas etapas, caso ainda não esteja ativa.
3. Abra a página **Senhas de app** da Conta Google.
4. Crie uma senha com o nome `Site Trafo Engenharia`.
5. Guarde a senha exibida. Ela deve ser usada sem espaços.

Não envie essa senha por e-mail, WhatsApp ou chat e não a coloque dentro do arquivo `.zip` do site.

## 2. Criar o arquivo `.env` no servidor

Copie `.env.example` para `.env` e substitua somente o valor indicado:

```dotenv
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USERNAME=contato@trafoengenharia.com.br
SMTP_PASSWORD=COLE_AQUI_A_SENHA_DE_APP_SEM_ESPACOS

MAIL_FROM=contato@trafoengenharia.com.br
MAIL_FROM_NAME=Site Trafo Engenharia
MAIL_TO=contato@trafoengenharia.com.br
MAIL_TO_NAME=Trafo Engenharia

ALLOWED_ORIGINS=https://www.trafoengenharia.com.br,https://trafoengenharia.com.br
```

O arquivo `.env` deve ficar na raiz do site, no mesmo nível de `index.html`, e não deve ficar acessível para download público. O pacote inclui um `.htaccess` que bloqueia esses arquivos em hospedagens Apache/cPanel. Em Nginx, crie a regra equivalente no servidor.

## 3. Instalar o PHPMailer

Na raiz do projeto, execute:

```bash
composer install --no-dev --optimize-autoloader
```

Esse comando cria a pasta `vendor`. Na publicação, envie essa pasta junto com os demais arquivos.

## 4. Teste após a publicação

1. Abra o site pelo endereço HTTPS oficial.
2. Preencha Nome, E-mail, Assunto e Mensagem.
3. Envie o formulário.
4. Confirme a mensagem de sucesso no site.
5. Confira a caixa de entrada e a pasta Spam de `contato@trafoengenharia.com.br`.
6. Responda à mensagem de teste para confirmar que o `Reply-To` aponta para o e-mail informado pelo visitante.

## Se “Senhas de app” não estiver disponível

O administrador do Google Workspace pode ter bloqueado esse recurso. Nesse caso, configure o serviço de SMTP relay no Admin Console e use `smtp-relay.gmail.com`. Essa alternativa normalmente exige regras administrativas e, dependendo da configuração, o IP público fixo da hospedagem.

Não troque para a senha comum da conta: ela não é a credencial correta para este formulário.
