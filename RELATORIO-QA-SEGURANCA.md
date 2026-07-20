# Relatório de QA e segurança — Trafo Engenharia

Data da revisão: 17/07/2026

## Escopo

- Sete páginas públicas em HTML, CSS e JavaScript.
- Navegação parcial entre os três conteúdos técnicos.
- Formulário de contato e envio por SMTP.
- Contador próprio de acessos e banco SQLite.
- Painel restrito de estatísticas.
- Regras de publicação para Apache/cPanel.

## Resultado funcional e de UX

- A troca entre artigos preserva no DOM o cabeçalho, a faixa de retorno, o índice lateral e o rodapé.
- Apenas o conteúdo central é substituído a partir do registro local dos artigos, sem nova requisição de página.
- A URL, o título da página, o breadcrumb e o item ativo são atualizados.
- O histórico do navegador funciona ao avançar e voltar.
- Se JavaScript não estiver disponível, os links continuam funcionando com navegação convencional.
- Testes realizados via servidor em viewport desktop (1440 × 900) e celular (390 × 844), além da abertura local por arquivo.
- O contador de navegações permaneceu em 1 e não houve nova requisição de documento ao alternar entre artigos.
- O foco semântico é enviado ao novo título para tecnologias assistivas, sem exibir contorno visual transitório no título.
- O card do transformador no hero foi validado sem a moldura interna duplicada, com escala proporcional, equipamento completo, identificação “CALC_TR 2.0”, textos preservados e comportamento responsivo.
- Os cards CAD/SolidWorks foram validados com diferença de 0 px entre topo, base e altura, sem transformação angular e sem rolagem horizontal.
- As imagens CAD de 1200 × 1600 px foram validadas com linhas reforçadas de forma determinística e cores técnicas preservadas; os originais permanecem no pacote.
- A ampliação dos cards foi validada em desktop e celular, sem nova navegação, sem rolagem horizontal, com legenda e texto alternativo traduzidos e fechamento pela tecla Esc.
- O hero foi medido em 1021, 1280, 1440, 1552 e 1920 px nos três idiomas. O card mantém a mesma largura em cada viewport e nenhum limite de palavra ou caixa do título alcança a imagem. Na resolução de 1552 × 781 do relato, o card mede 560 px, o título fica limitado a 72 px e a palavra longa `transformadas` termina com 86 px de distância do card.
- As duas figuras da proposta internacional foram verificadas em português, inglês e espanhol: imagem e legenda preenchem toda a coluna, com resíduo vertical de 0 px mesmo quando a tradução aumenta a altura do bloco.
- O recorte da imagem do hero foi conferido sem o canto interno da moldura original no encontro com os indicadores inferiores, mantendo o transformador completo e a identificação “CALC_TR 2.0”.
- A identificação superior do hero foi validada em inglês, espanhol e português nas larguras de 1021, 1280, 1440, 1552 e 1920 px. As 15 combinações permaneceram em uma única linha, dentro da coluna de texto e sem alcançar o card do transformador.
- A assinatura “Trafo Engenharia” do rodapé foi validada como um único texto em Montserrat 700. Logo e nome compartilham a mesma área clicável e direcionam ao topo da página inicial; o estado de foco permanece visível para navegação por teclado.
- Os seletores de idioma das sete páginas foram protegidos contra tradução automática com os mecanismos HTML reconhecidos pelos navegadores. As siglas `ES`, `EN` e `BR` permanecem intactas, e a largura dos botões continua uniforme mesmo sob mutação simulada do texto.
- Foram exercitadas as sete páginas em três idiomas e dois viewports, totalizando 42 combinações sem rolagem horizontal, imagens quebradas, traduções vazias ou erros JavaScript.
- O arquivo da bandeira brasileira foi padronizado como `assets/flags/flag-br.png` e todas as sete páginas apontam para o novo nome.
- A publicação passou a exigir PHP 8.2 ou superior e recomenda PHP 8.4 atualizado; os detalhes operacionais estão em `REQUISITOS-TECNICOS.md`.
- Não foram detectados erros JavaScript, referências locais ausentes, IDs duplicados ou rolagem horizontal.
- A navegação foi exercitada sob a política CSP final, sem violações.

## Resultado da revisão de segurança

O verificador incluído em `qa/verificar-seguranca.mjs` executou 54 verificações e aprovou todas.

Controles verificados:

- CSP sem permissão para scripts ou estilos inline.
- Proteção contra clickjacking, MIME sniffing e abertura indevida de contexto.
- HSTS condicionado ao uso de HTTPS.
- Política de referenciador e de recursos sensíveis do navegador.
- Listagem de diretórios desativada.
- Bloqueio web de `.env`, arquivos internos do backend, banco, GeoIP, documentação, logs e configuração do Composer.
- Ausência de JavaScript inline, handlers HTML inline e URLs `javascript:`.
- Links externos com isolamento de `window.opener`.
- Formulário restrito a POST e tipos de conteúdo esperados.
- Limite de tamanho, limites por campo, validação de e-mail e limpeza de caracteres de controle.
- Verificação de origem, honeypot e rate limit atômico por IP.
- Codificação do conteúdo enviado por e-mail e SMTP autenticado.
- Analytics com corpo limitado, origem validada, rate limit, HMAC e consultas preparadas.
- IP bruto não incluído no banco de estatísticas.
- Painel com senha em hash, CSRF, comparação constante, renovação de sessão e saída codificada.
- Cookie de sessão `HttpOnly`, `SameSite=Strict` e `Secure` quando publicado em HTTPS.
- Expiração de sessão por inatividade e duração máxima.
- Nenhum segredo real incluído no pacote.

Não foram identificadas vulnerabilidades críticas ou altas no código revisado. Isso não representa garantia de invulnerabilidade: segurança também depende da hospedagem, do Apache, do PHP, das dependências instaladas, das credenciais e da manutenção posterior.

## Portões obrigatórios na hospedagem

Antes de abrir o domínio ao público:

1. Publicar exclusivamente em HTTPS.
2. Executar `composer install --no-dev --optimize-autoloader`.
3. Executar `composer audit --locked --no-dev` e não publicar caso existam alertas não tratados.
4. Criar o `.env` fora de qualquer repositório, com senha de app e credenciais exclusivas.
5. Gerar senha forte e salt do painel conforme `GUIA-PAINEL-ACESSOS.md`.
6. Confirmar permissões mínimas de escrita somente em `backend/data` e `backend/rate-limit`.
7. Verificar que `.env`, `composer.json`, arquivos `.md`, SQLite e GeoIP retornem 403 ou 404 pela web.
8. Confirmar os cabeçalhos de segurança no domínio final.
9. Testar um envio real do formulário e o bloqueio após cinco tentativas no intervalo configurado.
10. Manter backups, atualizações do PHP e auditoria periódica das dependências.

Exemplo de verificação após a publicação:

```bash
curl -I https://trafoengenharia.com.br/
curl -I https://trafoengenharia.com.br/.env
curl -I https://trafoengenharia.com.br/composer.json
composer audit --locked --no-dev
```

Os cabeçalhos esperados incluem `Content-Security-Policy`, `Strict-Transport-Security`, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` e `Permissions-Policy`.
