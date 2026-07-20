# Site Trafo Engenharia — formulário e painel de acessos

Site institucional responsivo e trilíngue em HTML, CSS e JavaScript, com backend PHP para envio do formulário por SMTP e estatísticas próprias de acesso.

## Campos obrigatórios

Somente estes campos estão marcados com asterisco vermelho e validados no navegador e no servidor:

- Nome
- E-mail
- Assunto
- Mensagem

Empresa e Telefone são opcionais.

## Requisitos do servidor

- PHP 8.2 ou superior; PHP 8.4 recomendado para a nova publicação
- Extensões PHP: `mbstring`, `openssl`, `json`, `pdo` e `pdo_sqlite`
- Composer
- Conta SMTP do domínio ou de um provedor de e-mail
- Hospedagem com PHP; GitHub Pages não executa o backend

## Configuração

1. No terminal, entre na pasta do site e instale as dependências:

   ```bash
   composer install --no-dev --optimize-autoloader
   ```

2. Copie `.env.example` para `.env`:

   ```bash
   cp .env.example .env
   ```

3. Edite `.env` com os dados SMTP fornecidos pela hospedagem ou pelo serviço de e-mail.

4. Envie todos os arquivos para a hospedagem, incluindo a pasta `vendor` criada pelo Composer. Não publique o arquivo `.env` em repositórios públicos.

5. Abra o site por uma URL HTTP/HTTPS. O formulário não deve ser testado abrindo apenas o `index.html` com `file://`.

## Dados SMTP normalmente necessários

- servidor SMTP, como `smtp.seudominio.com.br`
- porta `587` com `tls` ou porta `465` com `ssl`
- usuário, geralmente o endereço completo de e-mail
- senha da conta ou senha específica de aplicativo
- endereço que receberá as mensagens em `MAIL_TO`
- domínios públicos corretos em `ALLOWED_ORIGINS`

## Segurança incluída

- validação dos campos obrigatórios também no servidor
- validação do endereço de e-mail
- conteúdo HTML escapado antes de montar a mensagem
- campo honeypot contra robôs
- limite de cinco tentativas por IP a cada quinze minutos
- verificação opcional da origem da solicitação
- credenciais SMTP armazenadas fora do código, no arquivo `.env`
- mensagens técnicas detalhadas registradas somente no log do servidor

## Teste local

Depois de configurar `.env` e executar `composer install`:

```bash
php -S localhost:8000
```

Acesse `http://localhost:8000` no navegador.

## Google Workspace configurado

O projeto está preparado para enviar pelo servidor `smtp.gmail.com`, porta `587`, com TLS e autenticação da conta `contato@trafoengenharia.com.br`. O destinatário também está definido como `contato@trafoengenharia.com.br`.

A senha comum da conta não deve ser usada. Ative a verificação em duas etapas, gere uma senha de app exclusiva para o site e grave-a somente no arquivo `.env` do servidor. Consulte `CONFIGURACAO-GOOGLE-WORKSPACE.md` para o procedimento completo e para a alternativa com SMTP relay.

## Painel privado de acessos

O contador não aparece no website. Depois de configurado, o painel é acessado diretamente em:

`https://www.trafoengenharia.com.br/painel-acessos/`

Ele apresenta visualizações, visitantes aproximados, origem do tráfego, cidade, país, páginas, dispositivos e navegadores. Não usa cookies e não grava o endereço IP original. Consulte `GUIA-PAINEL-ACESSOS.md` para criar a senha, instalar a base de geolocalização e validar a publicação.


## Idioma inicial

Na primeira visita, o site abre em espanhol. Quando o visitante escolhe ES, EN ou BR, a preferência é salva no navegador e reutilizada nas próximas visitas e recarregamentos.


## Atualização CAD e 3D

- Removidas as referências a inteligência artificial/IA do conteúdo público.
- Integração visual reorganizada em triângulo com CAD, 3D e BOM.
- Incluída a marca TJ Transformadores Jundiaí na grade institucional.


## Proposta internacional

- O card "Parcerias tecnológicas" foi substituído por "Nova proposta internacional".
- A nova seção apresenta obstáculos comuns, atuação técnica, proposta de kit completo e solução logística internacional.
- Os três botões redundantes de idioma da seção internacional foram removidos.
- Os números do WhatsApp ficam totalmente brancos no estado normal e verdes apenas ao passar o mouse ou navegar por teclado.


## Página da proposta internacional

A proposta internacional foi movida para `proposta-internacional.html`. O card na página inicial abre essa página, que preserva o cabeçalho, o seletor de idiomas, o rodapé e oferece links para retornar à página principal ou ao formulário de contato.

## Alinhamento da proposta internacional

As colunas “Obstáculos comuns” e “Nossa atuação” usam a mesma estrutura de linhas, altura de títulos e espaçamento vertical. A seta permanece centralizada entre as listas no desktop e muda para o fluxo vertical em telas menores.


## Navegação da proposta

Na página `proposta-internacional.html`, o breadcrumb e o link “Voltar para a página inicial” permanecem acessíveis em uma barra fixa logo abaixo do cabeçalho.

## Revisão final para publicação

- Alinhados os títulos “Obstáculos comuns” e “Nossa atuação” ao início de suas respectivas listas.
- As imagens da proposta preservam a proporção original e exibem a legenda “Imagem meramente ilustrativa” nos três idiomas.
- Corrigido o contraste de “Empresa” e do submenu no menu móvel.
- Eliminada a rolagem horizontal em telas estreitas, com suporte a partir de 320 px.
- Adicionados fallbacks para preferência de movimento reduzido, armazenamento local bloqueado e ausência de `IntersectionObserver`.
- As mensagens do formulário acompanham o idioma ativo, inclusive no limite de tentativas.
- Atualizada a versão de cache do CSS e do JavaScript para a nova publicação.
- Incluído contador próprio e oculto, painel restrito por senha e política de privacidade trilíngue.
- O retorno da proposta internacional permanece acessível durante a rolagem, em uma barra fixa abaixo do cabeçalho.
- A ilustração esquemática do hero foi substituída pelo transformador real fornecido, mantendo os textos e indicadores responsivos e traduzíveis.
- A seção de integração com SolidWorks agora utiliza dois modelos CAD fornecidos como evidência visual de modelagem externa e detalhamento interno; a terceira imagem não foi utilizada por ter resolução insuficiente para o padrão visual do site.
- `conteudo-tecnico.html` funciona como biblioteca central, enquanto cada um dos três materiais possui uma página própria e uma URL individual; o antigo “Estudo de caso” foi corrigido para “Análise técnica”, evitando resultados fictícios sem dados de um projeto documentado.
- A página de conteúdo técnico possui retorno persistente para a página inicial, seguindo o mesmo padrão visual e de navegação da Proposta Internacional.
- A troca entre artigos usa um registro local de conteúdo e History API: cabeçalho, retorno, índice e rodapé permanecem montados, enquanto somente o artigo, a URL, o título e o item ativo são atualizados. Não há nova requisição ao trocar de artigo; os links continuam funcionando normalmente sem JavaScript.
- A troca mantém o foco semântico no novo título para leitores de tela, sem desenhar um retângulo de foco temporário sobre o título.
- O hero da página inicial mantém somente a moldura externa do card; o transformador foi reenquadrado com margens proporcionais, pés completos e a identificação original “CALC_TR 2.0”, sem remover descrições ou indicadores.
- Os dois cards de integração CAD/SolidWorks foram alinhados pelo topo e pela base, com a mesma altura e sem rotação, reforçando precisão visual e comparação direta.
- As imagens CAD originais foram preservadas e receberam versões derivadas de 1200 × 1600 px com engrossamento determinístico das linhas e contraste reforçado, sem geração nem alteração da geometria técnica.
- Cada card CAD pode ser ampliado por clique ou toque em uma janela acessível, com imagem completa, legenda traduzida, fechamento por botão, fundo ou tecla Esc e fallback para nova aba.
- O card do transformador no hero mantém exatamente a mesma largura nos três idiomas. A escala do título foi limitada à própria coluna para que palavras longas em inglês, espanhol ou português nunca avancem sobre a imagem.
- As imagens da proposta internacional agora ocupam integralmente a altura disponível antes da legenda, eliminando a faixa residual criada pelos textos mais longos em inglês e espanhol sem deformar a proporção da imagem.
- O recorte do transformador no hero foi refinado para ocultar o canto interno da arte original que aparecia como uma pequena falha de preenchimento; equipamento, indicadores e identificação “CALC_TR 2.0” foram preservados.
- A identificação superior do hero mantém “Engenharia de transformadores • Tecnologia • Conhecimento” em uma única linha no desktop, com escala responsiva por largura e quebra permitida somente em telas pequenas.
- No rodapé, “Trafo Engenharia” usa uma única família e um único peso tipográfico. Logo e nome formam um único link acessível para o topo da página inicial, com retorno suave na home e navegação explícita a partir das páginas internas.
- O seletor `ES / EN / BR` está marcado com `translate="no"` e `notranslate`, impedindo que o tradutor automático do navegador altere as siglas; os três controles também possuem largura estável como proteção visual adicional.
- A bandeira brasileira foi renomeada de `flag-pt.png` para `flag-br.png`, com todas as referências atualizadas.
- Os requisitos do formulário, contador e painel estão documentados em `REQUISITOS-TECNICOS.md`; o mínimo de publicação é PHP 8.2 e a versão recomendada é PHP 8.4 atualizada.
- A publicação inclui CSP restritiva, HSTS em HTTPS, cabeçalhos defensivos, bloqueio de arquivos internos, limites de requisição, rate limits atômicos e sessões do painel com expiração.
- O relatório completo está em `RELATORIO-QA-SEGURANCA.md`; o verificador local pode ser executado com `node qa/verificar-seguranca.mjs`.

## Checklist para nova publicação

- Execute `composer install --no-dev --optimize-autoloader` antes de enviar o projeto.
- Execute `composer audit --locked --no-dev` depois da instalação e trate qualquer alerta antes de publicar.
- Crie o arquivo `.env` no servidor com os dados reais de SMTP e mantenha-o fora de qualquer repositório público.
- Envie as pastas `assets`, `backend` e `vendor`, além dos arquivos da raiz.
- Confirme que o PHP pode criar e gravar em `backend/rate-limit`.
- Confirme que o PHP pode criar e gravar em `backend/data` e que `pdo_sqlite` está ativo.
- Configure o painel e a base GeoLite2 conforme `GUIA-PAINEL-ACESSOS.md`.
- Publique em HTTPS e atualize `ALLOWED_ORIGINS` com os endereços finais, separados por vírgula e sem barra no fim.
- Após a publicação, teste os três idiomas, o menu em celular, os links da proposta e um envio real do formulário.
