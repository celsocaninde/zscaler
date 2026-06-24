# ☁️🛡️ Zscaler GLPI Plugin

> 🇧🇷 **PT-BR:** Plugin para GLPI 11 que integra Zscaler ZIA, ZCC e ZDX ao service desk, com sincronização de categorias, denylist, URL lookup, Cloud Sandbox, ações controladas, tickets automáticos e visibilidade de endpoints sem proteção.
>
> 🇺🇸 **English:** A GLPI 11 plugin that integrates Zscaler ZIA, ZCC, and ZDX into service desk workflows, with category sync, denylist management, URL lookup, Cloud Sandbox, controlled actions, automatic tickets, and visibility into unprotected endpoints.

🚀 Repositório: https://github.com/celsocaninde/zscaler

🏷️ Versão atual: `0.4.0` · GLPI `11.0.x` · PHP `>= 8.2`

🎨 Identidade visual: azul Zscaler `#0648A8`, ciano `#00B2E3` e azul escuro `#0A1E3F`.

---

## 🇧🇷 Português (Brasil)

### ✨ Visão Geral

O **Zscaler GLPI Plugin** conecta a operação de segurança web ao inventário e ao fluxo de tickets do GLPI. Ele centraliza dados do **Zscaler Internet Access (ZIA)**, **Zscaler Client Connector (ZCC)** e **Zscaler Digital Experience (ZDX)** em telas próprias, dashboard operacional, ações auditáveis e automação de chamados.

🎯 Ideal para equipes de TI, segurança, SOC e service desk que precisam investigar URLs, bloquear domínios, acompanhar cobertura do Zscaler nos endpoints e transformar eventos relevantes em tickets rastreáveis no GLPI.

### 🚀 Capacidades Principais

- 🧭 **Dashboard Zscaler** com KPIs, categorias, denylist, ações recentes, endpoints sem ZCC e alertas ZDX.
- 🌐 **ZIA Internet Access** com sincronização de categorias de URL, denylist, URL lookup e Cloud Sandbox.
- 🔎 **URL Lookup** para consultar classificação e reputação de endereços diretamente no GLPI.
- 🚫 **Denylist controlada** para adicionar/remover URLs bloqueadas com trilha de auditoria.
- 🧩 **Categorias customizadas** com inclusão de URLs em categorias do tenant.
- 🧪 **Cloud Sandbox** com consulta por hash MD5, submissão de arquivos e ticket automático em veredicto malicioso.
- 💻 **ZCC Client Connector** com inventário de dispositivos e relatório de computadores GLPI sem Zscaler.
- 📈 **ZDX Digital Experience** com sincronização de alertas abertos e abertura de tickets por severidade.
- 🔥 **Cloud Firewall / DNS / IPS** com listagem e liga/desliga de regras (mesma trava dupla das ações).
- 🛡️ **Segurança ATP** com denylist de URLs maliciosas e allowlist (bypass) gerenciáveis com auditoria.
- 🌩️ **Shadow IT / Cloud Apps** com descoberta de aplicações de nuvem e ticket automático para apps de risco.
- 📋 **Admin Audit Log** importando "quem mudou o quê" na console ZIA para um painel no GLPI.
- 🎫 **Tickets automáticos** para ações manuais, sandbox malicioso, alertas ZDX e apps de risco.
- 🔐 **Permissões por perfil** para leitura, configuração e ações de escrita.
- 🛡️ **Trava dupla de segurança** para qualquer ação que escreva na console Zscaler.
- 🧾 **Logs de ação e sincronização** para auditoria operacional.

### ☁️ Módulos Integrados

#### 🌐 ZIA - Zscaler Internet Access

- 📚 Sincroniza categorias de URL customizadas e predefinidas.
- 🚫 Sincroniza e exibe URLs bloqueadas na denylist.
- 🔎 Consulta URLs pelo lookup do ZIA.
- 🧪 Consulta Cloud Sandbox por hash MD5.
- 📤 Submete arquivos para análise no Sandbox usando token dedicado.
- ⚡ Executa `/status/activate` após alterações, quando habilitado.

#### 💻 ZCC - Zscaler Client Connector

- 🔄 Sincroniza dispositivos inscritos no Client Connector.
- 🔗 Relaciona dispositivos ZCC com computadores GLPI por hostname.
- 🧭 Exibe computadores ativos do GLPI sem agente Zscaler.
- 📊 Mostra cobertura no dashboard para ajudar a fechar lacunas.

#### 📈 ZDX - Zscaler Digital Experience

- 🚨 Sincroniza alertas de experiência em andamento.
- 🎯 Filtra tickets por severidade mínima configurada.
- 🎫 Abre chamados GLPI para incidentes relevantes.
- 🧾 Mantém histórico local dos alertas sincronizados.

### 🔐 Autenticação

O plugin suporta dois modos para o ZIA:

- 🔑 **OneAPI (Zidentity / OAuth2)**: `client_id` + `client_secret` + vanity domain. O plugin obtém token Bearer em `https://<vanity>.zslogin.net/oauth2/v1/token` e chama `https://api.zsapi.net/zia/api/v1/...`.
- 🧩 **Legado (API key)**: `API key` + usuário + senha. O plugin aplica o algoritmo oficial de ofuscação da chave, autentica em `/api/v1/authenticatedSession` e reutiliza o cookie `JSESSIONID`.

Credenciais adicionais:

- 💻 **ZCC**: `apiKey` + `secretKey` em `https://api-mobile.zscaler.net`.
- 📈 **ZDX**: `key_id` + `key_secret` em `https://api.zdxcloud.net`.
- 🧪 **Sandbox**: token dedicado para submissão de arquivos no host `csbapi`.

🔒 Segredos como `client_secret`, `api_key`, `password`, `sandbox_token`, `zcc_secret_key` e `zdx_key_secret` são cifrados com `GLPIKey`. Tokens e cookies ficam em cache na tabela `glpi_plugin_zscaler_tokens` para reduzir autenticações repetidas e permitir execução via cron.

### 🛡️ Travas de Segurança

Uma ação de escrita só é executada quando **todas** as condições abaixo são verdadeiras:

- 👁️ Modo somente leitura desligado (`readonly_mode = 0`).
- ✅ Permitir ações ligado (`allow_actions = 1`).
- 🔐 Usuário com direito `plugin_zscaler_action`.

⚡ Com **Ativar mudanças automaticamente** ligado, cada escrita chama `/status/activate` para publicar a mudança na console Zscaler.

### 🔐 Permissões

- 👁️ `plugin_zscaler_read`: visualizar telas, listas e dashboard.
- ⚡ `plugin_zscaler_action`: executar ações de escrita na Zscaler.
- ⚙️ `plugin_zscaler_config`: ver e editar a configuração do plugin.

✅ `Super-Admin` e `Admin` recebem acesso completo na instalação. Ajuste por perfil em **Administração > Perfis > Zscaler**.

### 📦 Instalação

1. 📁 Copie a pasta `zscaler` para `plugins/zscaler/` no GLPI.
2. 🧩 Em **Configurar > Plugins**, instale e ative o plugin **Zscaler**.
3. ⚙️ Em **Configuração > Zscaler**, escolha o modo de autenticação e informe as credenciais.
4. 🧪 Clique em **Testar conexão**.
5. 🔄 Use **Sincronizar** na visão geral ou habilite a ação automática `syncziadata`.

### ✅ Validação

No ambiente Docker/Nginx deste projeto:

```bash
docker compose exec glpi-fpm sh -lc "find /var/www/glpi/plugins/zscaler -name '*.php' -print0 | xargs -0 -n1 php -l"
docker compose exec glpi-fpm php /var/www/glpi/bin/console plugin:list
docker compose exec glpi-fpm php /var/www/glpi/bin/console plugin:install zscaler
docker compose exec glpi-fpm php /var/www/glpi/bin/console plugin:activate zscaler
```

### 🔌 Endpoints ZIA Utilizados

- 📚 `/urlCategories`
- 🔎 `/urlLookup`
- 🚫 `/security/advanced` + `/blacklistUrls` (denylist ATP)
- 🛡️ `/security` + `whitelistUrls` (allowlist / bypass)
- 🧩 `/urlCategories/{id}` com `ADD_TO_LIST`
- 🧪 `/sandbox/report/{md5}`
- 🔥 `/firewallFilteringRules`, `/firewallDnsRules`, `/firewallIpsRules` (GET + PUT estado)
- 🌩️ `/cloudApplications/lite` (Shadow IT)
- 📋 `/auditlogEntryReport` (+ `/download`) — Admin Audit Log
- 👥 `/users`
- 📍 `/locations`
- ⚡ `/status/activate`

### 🧭 Arquivos Principais

- ⚙️ `setup.php`: metadados, hooks, menu e registro de classes.
- 🪝 `hook.php`: instalação, uninstall, tabelas e tarefas automáticas.
- ⚙️ `src/Config.php`: formulário de configuração e armazenamento seguro.
- 🔌 `src/ApiClient.php`: cliente ZIA com OneAPI e modo legado.
- 💻 `src/ZccApiClient.php`: cliente do Zscaler Client Connector.
- 📈 `src/ZdxApiClient.php`: cliente do Zscaler Digital Experience.
- 🔄 `src/Sync.php`: sincronização de ZIA, ZCC, ZDX e estatísticas.
- 🚫 `src/DenylistEntry.php`: lista de URLs bloqueadas.
- 📚 `src/UrlCategory.php`: categorias de URL.
- ⚡ `src/Actions.php`: ações de escrita controladas.
- 🎫 `src/TicketManager.php`: abertura de tickets GLPI.
- 🧾 `src/ActionLog.php`: auditoria de ações.
- 🖥️ `front/overview.php`: dashboard principal.
- 🔎 `front/urllookup.php`: consulta de URLs.
- 🧪 `front/sandbox.php`: consulta e submissão ao Cloud Sandbox.
- 💻 `front/unprotected.php`: computadores GLPI sem Zscaler.
- 🎨 `public/css/zscaler.css`: tema visual do plugin.

### ⚠️ Limitações e Próximos Passos

- 🧾 A API de configuração do ZIA não é um stream de eventos de segurança; logs web em tempo real exigem NSS/Log Streaming.
- 🔐 ZPA ainda não está no escopo deste plugin.
- 🧩 Campos de ZCC/ZDX são tratados de forma defensiva com múltiplos aliases; alguns tenants podem exigir ajuste fino.
- 🧪 Próximas melhorias: teste de conexão por módulo, painel de usuários/localidades ao vivo e regras avançadas de ticket por classificação de URL.

---

## 🇺🇸 English

### ✨ Overview

The **Zscaler GLPI Plugin** brings web security operations into GLPI by connecting **Zscaler Internet Access (ZIA)**, **Zscaler Client Connector (ZCC)**, and **Zscaler Digital Experience (ZDX)** with inventory, tickets, dashboards, controlled actions, and audit logs.

🎯 It is built for IT, security, SOC, and service desk teams that need to investigate URLs, block domains, track endpoint coverage, and turn relevant Zscaler events into traceable GLPI tickets.

### 🚀 Key Features

- 🧭 **Zscaler dashboard** with KPIs, categories, denylist, recent actions, unprotected endpoints, and ZDX alerts.
- 🌐 **ZIA Internet Access** category sync, denylist sync, URL lookup, and Cloud Sandbox support.
- 🔎 **URL Lookup** directly from GLPI.
- 🚫 **Controlled denylist actions** to add or remove blocked URLs with audit history.
- 🧩 **Custom category actions** for adding URLs to tenant categories.
- 🧪 **Cloud Sandbox** hash lookup, file submission, and automatic tickets for malicious verdicts.
- 💻 **ZCC Client Connector** device inventory and GLPI computers without Zscaler coverage.
- 📈 **ZDX Digital Experience** ongoing alert sync and severity-based ticket creation.
- 🔥 **Cloud Firewall / DNS / IPS** rule listing and enable/disable (same double safety lock).
- 🛡️ **ATP security** with manageable malicious-URL denylist and allowlist (bypass), all audited.
- 🌩️ **Shadow IT / Cloud Apps** discovery with automatic tickets for risky applications.
- 📋 **Admin Audit Log** importing "who changed what" from the ZIA console into a GLPI panel.
- 🎫 **Automatic tickets** for manual actions, malicious sandbox results, ZDX alerts, and risky apps.
- 🔐 **Profile permissions** for read, configuration, and write actions.
- 🛡️ **Double safety lock** before any write operation reaches the Zscaler console.
- 🧾 **Action and sync logs** for operational auditing.

### ☁️ Integrated Modules

#### 🌐 ZIA - Zscaler Internet Access

- 📚 Sync custom and predefined URL categories.
- 🚫 Sync and display denylisted URLs.
- 🔎 Run ZIA URL lookups.
- 🧪 Query Cloud Sandbox reports by MD5 hash.
- 📤 Submit files to Cloud Sandbox with a dedicated token.
- ⚡ Run `/status/activate` after changes when enabled.

#### 💻 ZCC - Zscaler Client Connector

- 🔄 Sync enrolled Client Connector devices.
- 🔗 Match ZCC devices to GLPI computers by hostname.
- 🧭 Display active GLPI computers without Zscaler.
- 📊 Show coverage metrics on the dashboard.

#### 📈 ZDX - Zscaler Digital Experience

- 🚨 Sync ongoing experience alerts.
- 🎯 Filter ticket creation by minimum severity.
- 🎫 Open GLPI tickets for relevant incidents.
- 🧾 Keep a local history of synchronized alerts.

### 🔐 Authentication

The plugin supports two ZIA authentication modes:

- 🔑 **OneAPI (Zidentity / OAuth2)**: `client_id` + `client_secret` + vanity domain. The plugin gets a Bearer token from `https://<vanity>.zslogin.net/oauth2/v1/token` and calls `https://api.zsapi.net/zia/api/v1/...`.
- 🧩 **Legacy API key**: `API key` + username + password. The plugin applies Zscaler's key obfuscation algorithm, authenticates at `/api/v1/authenticatedSession`, and reuses the `JSESSIONID` cookie.

Additional credentials:

- 💻 **ZCC**: `apiKey` + `secretKey` at `https://api-mobile.zscaler.net`.
- 📈 **ZDX**: `key_id` + `key_secret` at `https://api.zdxcloud.net`.
- 🧪 **Sandbox**: dedicated token for file submission on the `csbapi` host.

🔒 Sensitive values such as `client_secret`, `api_key`, `password`, `sandbox_token`, `zcc_secret_key`, and `zdx_key_secret` are encrypted with `GLPIKey`. Tokens and cookies are cached in `glpi_plugin_zscaler_tokens` to reduce repeated authentication and support cron execution.

### 🛡️ Safety Locks

A write action is executed only when **all** conditions are true:

- 👁️ Read-only mode is disabled (`readonly_mode = 0`).
- ✅ Actions are allowed (`allow_actions = 1`).
- 🔐 The user has the `plugin_zscaler_action` right.

⚡ When **Auto activate changes** is enabled, each write operation calls `/status/activate` to publish the change in the Zscaler console.

### 🔐 Permissions

- 👁️ `plugin_zscaler_read`: view pages, lists, and dashboard.
- ⚡ `plugin_zscaler_action`: execute write actions in Zscaler.
- ⚙️ `plugin_zscaler_config`: view and edit plugin configuration.

✅ `Super-Admin` and `Admin` receive full access during installation. Adjust profile rights in **Administration > Profiles > Zscaler**.

### 📦 Installation

1. 📁 Copy the `zscaler` folder to `plugins/zscaler/` in GLPI.
2. 🧩 Go to **Setup > Plugins**, then install and enable **Zscaler**.
3. ⚙️ Go to **Configuration > Zscaler**, choose the authentication mode, and enter credentials.
4. 🧪 Click **Test connection**.
5. 🔄 Use **Sync** from the overview page or enable the `syncziadata` automatic action.

### ✅ Validation

In this Docker/Nginx environment:

```bash
docker compose exec glpi-fpm sh -lc "find /var/www/glpi/plugins/zscaler -name '*.php' -print0 | xargs -0 -n1 php -l"
docker compose exec glpi-fpm php /var/www/glpi/bin/console plugin:list
docker compose exec glpi-fpm php /var/www/glpi/bin/console plugin:install zscaler
docker compose exec glpi-fpm php /var/www/glpi/bin/console plugin:activate zscaler
```

### 🔌 ZIA Endpoints Used

- 📚 `/urlCategories`
- 🔎 `/urlLookup`
- 🚫 `/security/advanced` + `/blacklistUrls` (ATP denylist)
- 🛡️ `/security` + `whitelistUrls` (allowlist / bypass)
- 🧩 `/urlCategories/{id}` with `ADD_TO_LIST`
- 🧪 `/sandbox/report/{md5}`
- 🔥 `/firewallFilteringRules`, `/firewallDnsRules`, `/firewallIpsRules` (GET + PUT state)
- 🌩️ `/cloudApplications/lite` (Shadow IT)
- 📋 `/auditlogEntryReport` (+ `/download`) — Admin Audit Log
- 👥 `/users`
- 📍 `/locations`
- ⚡ `/status/activate`

### ⚠️ Limitations and Next Steps

- 🧾 The ZIA configuration API is not a real-time security event stream; web traffic logs require NSS/Log Streaming.
- 🔐 ZPA is not covered yet.
- 🧩 ZCC/ZDX fields are parsed defensively with multiple aliases; some tenants may require fine tuning.
- 🧪 Future ideas: connection tests per module, live users/locations panels, and advanced ticket rules by URL classification.
