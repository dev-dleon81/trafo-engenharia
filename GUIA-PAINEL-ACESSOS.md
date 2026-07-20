# Configuração do painel privado de acessos

O site já contém o contador oculto e o painel. A ativação final precisa ser feita na hospedagem porque a senha, a base geográfica e o banco de acessos não devem ser distribuídos dentro do pacote público.

## O que será registrado

- data e página acessada;
- origem direta, domínio de referência ou parâmetros UTM;
- categoria do dispositivo e do navegador;
- cidade e país aproximados;
- identificador pseudônimo para estimar visitantes únicos.

O endereço IP é usado momentaneamente no servidor para consultar a localização e formar o identificador pseudônimo, mas o IP original e o navegador completo não são gravados. Robôs conhecidos, `Do Not Track` e Global Privacy Control são ignorados.

## 1. Requisitos da hospedagem

- PHP 8.2 ou superior; PHP 8.4 recomendado;
- extensões `mbstring`, `pdo` e `pdo_sqlite`;
- Composer;
- HTTPS;
- permissão de gravação do PHP na pasta `backend/data`.

No cPanel, essas extensões normalmente ficam em **Selecionar versão do PHP > Extensões**. Se a hospedagem não oferecer `pdo_sqlite`, solicite a ativação ao suporte.

## 2. Instalar as dependências

Na raiz do site, execute:

```bash
composer install --no-dev --optimize-autoloader
```

Se o Composer for executado no computador antes do envio, publique também a pasta `vendor` gerada.

## 3. Criar usuário, senha e salt

No terminal, dentro da pasta do site, execute:

```bash
php backend/generate-analytics-secrets.php
```

Digite uma senha forte com pelo menos 12 caracteres. O comando exibirá duas linhas. Copie ambas para o arquivo `.env` da hospedagem:

```dotenv
ANALYTICS_ENABLED=true
ANALYTICS_USERNAME=admin
ANALYTICS_PASSWORD_HASH=RESULTADO_GERADO
ANALYTICS_SALT=RESULTADO_GERADO
ANALYTICS_RETENTION_DAYS=90
ANALYTICS_TIMEZONE=America/Sao_Paulo
ANALYTICS_DATA_DIR=backend/data
GEOIP_DATABASE=backend/data/GeoLite2-City.mmdb
```

Para trocar a senha futuramente, execute o gerador outra vez e substitua apenas `ANALYTICS_PASSWORD_HASH`. Não há senha padrão no projeto.

## 4. Instalar a base de cidade e país

1. Crie uma conta gratuita na página oficial do [GeoLite2](https://dev.maxmind.com/geoip/geolite2-free-geolocation-data/).
2. Gere uma chave de licença na conta MaxMind.
3. Baixe a edição **GeoLite2 City** no formato binário MMDB.
4. Extraia o arquivo `GeoLite2-City.mmdb` para `backend/data/GeoLite2-City.mmdb`.

A geolocalização por IP é aproximada: alguns acessos podem mostrar apenas o país ou ficar como não identificados. VPNs, proxies e redes móveis também podem alterar o resultado.

A licença GeoLite exige manter a base atualizada. Automatize a atualização ou substitua o arquivo regularmente; as [orientações oficiais](https://dev.maxmind.com/geoip/updating-databases/) devem ser seguidas.

## 5. Proteção do banco de dados

No Apache/cPanel, os arquivos `.htaccess` incluídos bloqueiam o download de `.env`, `.sqlite` e `.mmdb`.

Se o servidor usar Nginx, peça à hospedagem para bloquear acesso público a `/backend/data/` e a arquivos `.env`. O PHP precisa ler e gravar nessa pasta, mas visitantes não devem conseguir baixá-la.

## 6. Acessar e testar

Abra:

`https://www.trafoengenharia.com.br/painel-acessos/`

Entre com o usuário definido em `ANALYTICS_USERNAME` e a senha usada no gerador. O painel permite consultar os últimos 7, 30 ou 90 dias.

Para validar:

1. abra a página inicial em uma janela normal;
2. abra também a proposta internacional;
3. aguarde alguns segundos e atualize o painel;
4. confirme que as visualizações apareceram;
5. verifique se cidade e país foram preenchidos.

Testes locais em `localhost` não terão cidade e país porque endereços privados não são geolocalizados.

## 7. Privacidade e retenção

O rodapé público contém a página `politica-de-privacidade.html`, com a descrição do tratamento. A retenção padrão é de 90 dias e pode ser alterada em `ANALYTICS_RETENTION_DAYS`, entre 7 e 730 dias. Registros antigos são removidos automaticamente quando novos acessos chegam.

O painel não tem link no menu público, envia cabeçalhos `noindex` e exige autenticação. Ainda assim, mantenha a senha exclusivamente com pessoas autorizadas e conserve o PHP e suas dependências atualizados.
