<?php

namespace GlpiPlugin\Zscaler;

class Config extends \CommonGLPI
{
   public const CONTEXT = 'plugin:zscaler';
   private const SECRET_PREFIX = 'glpikey:';
   private const CONNECTION_FLASH_KEY = 'plugin_zscaler_connection_test';

   /** Campos sensiveis (cifrados com GLPIKey). */
   private const SECRET_FIELDS = ['client_secret', 'api_key', 'password', 'sandbox_token', 'zcc_secret_key', 'zdx_key_secret'];

   public static $rightname = 'plugin_zscaler_config';

   public static function getTypeName($nb = 0): string
   {
      return 'Zscaler';
   }

   public function getTabNameForItem(\CommonGLPI $item, $withtemplate = 0): string
   {
      if ($item instanceof \Config && Profile::hasConfigReadRight()) {
         return 'Zscaler';
      }

      return '';
   }

   public static function displayTabContentForItem(\CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
   {
      self::showForm();
      return true;
   }

   public static function defaults(): array
   {
      return [
         'enabled'         => '0',
         'auth_mode'       => ApiClient::MODE_ONEAPI,
         'cloud'           => 'zscaler.net',
         'timeout'         => '30',
         // OneAPI
         'vanity_domain'   => '',
         'oneapi_api_base' => 'https://api.zsapi.net',
         'client_id'       => '',
         'client_secret'   => '',
         // Legado
         'api_key'         => '',
         'username'        => '',
         'password'        => '',
         // Acoes
         'readonly_mode'   => '1',
         'allow_actions'   => '0',
         'auto_activate'   => '1',
         // Sincronizacao
         'sync_limit'      => '100',
         'max_pages'       => '10',
         'entity_id'       => '0',
         'sync_users'      => '0',
         'sync_locations'  => '0',
         // Tickets
         'create_tickets'              => '0',
         'ticket_category_id'          => '0',
         'ticket_requester_id'         => '0',
         'ticket_urgency'              => '4',
         'ticket_impact'               => '4',
         'ticket_priority'             => '4',
         'ticket_on_action'            => '1',
         'ticket_on_sandbox_malicious' => '0',
         'ticket_on_zdx_alert'         => '0',
         'ticket_on_risky_app'         => '0',
         'risky_app_min_risk'          => 'high',
         // Admin Audit Log (ZIA)
         'audit_days'                  => '7',
         'ticket_on_sensitive_audit'   => '0',
         'audit_sensitive_keywords'    => 'delete,deactivate,disable,remove,credential,allowlist,denylist,security',
         // Self-service (bloqueio com aprovacao)
         'selfservice_enabled'         => '0',
         // Sandbox (ZIA)
         'sandbox_token'   => '',
         // ZCC - Client Connector
         'zcc_enabled'     => '0',
         'zcc_api_base'    => 'https://api-mobile.zscaler.net',
         'zcc_api_key'     => '',
         'zcc_secret_key'  => '',
         'zcc_sync_pages'  => '10',
         // Escopo (o Client Connector roda so em workstations; VMs ficam fora)
         'vm_type_keywords'  => Scope::DEFAULT_TYPE_KEYWORDS,
         'vm_model_keywords' => Scope::DEFAULT_MODEL_KEYWORDS,
         // ZDX - Digital Experience
         'zdx_enabled'        => '0',
         'zdx_api_base'       => 'https://api.zdxcloud.net',
         'zdx_key_id'         => '',
         'zdx_key_secret'     => '',
         'zdx_alert_min_severity' => '',
      ];
   }

   public static function cloudPresets(): array
   {
      return [
         'zscaler.net'      => 'zscaler.net',
         'zscalerone.net'   => 'zscalerone.net',
         'zscalertwo.net'   => 'zscalertwo.net',
         'zscalerthree.net' => 'zscalerthree.net',
         'zscloud.net'      => 'zscloud.net',
         'zscalerbeta.net'  => 'zscalerbeta.net (beta)',
      ];
   }

   public static function installDefaults(): void
   {
      $existing = \Config::getConfigurationValues(self::CONTEXT, array_keys(self::defaults()));

      if ($existing === []) {
         \Config::setConfigurationValues(self::CONTEXT, self::defaults());
      }
   }

   public static function getConfig(): array
   {
      $defaults = self::defaults();
      $values = \Config::getConfigurationValues(self::CONTEXT, array_keys($defaults));

      $config = array_merge($defaults, $values ?: []);
      foreach (self::SECRET_FIELDS as $field) {
         $config[$field] = self::unprotectSecret((string)($config[$field] ?? ''));
      }

      return $config;
   }

   public static function buildConfigFromInput(array $input, bool $keepExistingSecrets = true): array
   {
      $config = self::getConfig();

      $config['enabled'] = self::boolInput($input, 'enabled');
      $config['auth_mode'] = ((string)($input['auth_mode'] ?? $config['auth_mode']) === ApiClient::MODE_LEGACY)
         ? ApiClient::MODE_LEGACY
         : ApiClient::MODE_ONEAPI;
      $config['cloud'] = self::resolvePreset($input, 'cloud', array_keys(self::cloudPresets()), 'zscaler.net');
      $config['timeout'] = (string)max(5, min(120, (int)($input['timeout'] ?? 30)));

      $config['vanity_domain'] = self::cleanVanity((string)($input['vanity_domain'] ?? ''));
      $config['oneapi_api_base'] = rtrim(trim((string)($input['oneapi_api_base'] ?? 'https://api.zsapi.net')), '/') ?: 'https://api.zsapi.net';
      $config['client_id'] = trim((string)($input['client_id'] ?? ''));
      $config['username'] = trim((string)($input['username'] ?? ''));

      $config['readonly_mode'] = self::boolInput($input, 'readonly_mode');
      $config['allow_actions'] = self::boolInput($input, 'allow_actions');
      $config['auto_activate'] = self::boolInput($input, 'auto_activate');

      $config['sync_limit'] = (string)max(1, min(1000, (int)($input['sync_limit'] ?? 100)));
      $config['max_pages'] = (string)max(1, min(500, (int)($input['max_pages'] ?? 10)));
      $config['entity_id'] = (string)max(0, (int)($input['entity_id'] ?? 0));
      $config['sync_users'] = self::boolInput($input, 'sync_users');
      $config['sync_locations'] = self::boolInput($input, 'sync_locations');

      $config['create_tickets'] = self::boolInput($input, 'create_tickets');
      $config['ticket_category_id'] = (string)max(0, (int)($input['ticket_category_id'] ?? 0));
      $config['ticket_requester_id'] = (string)max(0, (int)($input['ticket_requester_id'] ?? 0));
      $config['ticket_urgency'] = (string)max(1, min(5, (int)($input['ticket_urgency'] ?? 4)));
      $config['ticket_impact'] = (string)max(1, min(5, (int)($input['ticket_impact'] ?? 4)));
      $config['ticket_priority'] = (string)max(1, min(6, (int)($input['ticket_priority'] ?? 4)));
      $config['ticket_on_action'] = self::boolInput($input, 'ticket_on_action');
      $config['ticket_on_sandbox_malicious'] = self::boolInput($input, 'ticket_on_sandbox_malicious');
      $config['ticket_on_zdx_alert'] = self::boolInput($input, 'ticket_on_zdx_alert');
      $config['ticket_on_risky_app'] = self::boolInput($input, 'ticket_on_risky_app');
      $config['risky_app_min_risk'] = in_array((string)($input['risky_app_min_risk'] ?? 'high'), ['medium', 'high'], true)
         ? (string)($input['risky_app_min_risk'] ?? 'high')
         : 'high';
      $config['audit_days'] = (string)max(1, min(180, (int)($input['audit_days'] ?? 7)));
      $config['ticket_on_sensitive_audit'] = self::boolInput($input, 'ticket_on_sensitive_audit');
      $config['audit_sensitive_keywords'] = self::cleanKeywords(
         (string)($input['audit_sensitive_keywords'] ?? ''),
         'delete,deactivate,disable,remove,credential,allowlist,denylist,security'
      );
      $config['selfservice_enabled'] = self::boolInput($input, 'selfservice_enabled');

      $config['zcc_enabled'] = self::boolInput($input, 'zcc_enabled');
      $config['zcc_api_base'] = rtrim(trim((string)($input['zcc_api_base'] ?? 'https://api-mobile.zscaler.net')), '/') ?: 'https://api-mobile.zscaler.net';
      $config['zcc_api_key'] = trim((string)($input['zcc_api_key'] ?? ''));
      $config['zcc_sync_pages'] = (string)max(1, min(200, (int)($input['zcc_sync_pages'] ?? 10)));

      $config['vm_type_keywords'] = self::cleanKeywords((string)($input['vm_type_keywords'] ?? ''), Scope::DEFAULT_TYPE_KEYWORDS);
      $config['vm_model_keywords'] = self::cleanKeywords((string)($input['vm_model_keywords'] ?? ''), Scope::DEFAULT_MODEL_KEYWORDS);

      $config['zdx_enabled'] = self::boolInput($input, 'zdx_enabled');
      $config['zdx_api_base'] = rtrim(trim((string)($input['zdx_api_base'] ?? 'https://api.zdxcloud.net')), '/') ?: 'https://api.zdxcloud.net';
      $config['zdx_key_id'] = trim((string)($input['zdx_key_id'] ?? ''));
      $config['zdx_alert_min_severity'] = in_array((string)($input['zdx_alert_min_severity'] ?? ''), ['', 'high', 'critical'], true)
         ? (string)($input['zdx_alert_min_severity'] ?? '')
         : '';

      foreach (self::SECRET_FIELDS as $field) {
         $value = trim((string)($input[$field] ?? ''));
         if ($value !== '' || !$keepExistingSecrets) {
            $config[$field] = $value;
         }
      }

      return $config;
   }

   public static function saveFromInput(array $input): void
   {
      $config = self::buildConfigFromInput($input);
      foreach (self::SECRET_FIELDS as $field) {
         $config[$field] = self::protectSecret((string)$config[$field]);
      }

      \Config::setConfigurationValues(self::CONTEXT, $config);

      // Troca de credenciais invalida o cache de sessao/token.
      self::flushTokenCache();
   }

   /** Normaliza uma lista de palavras-chave separadas por virgula (cai no default se vazia). */
   private static function cleanKeywords(string $raw, string $fallback): string
   {
      $parts = array_map('trim', explode(',', $raw));
      $parts = array_values(array_filter($parts, static fn(string $s): bool => $s !== ''));

      return $parts === [] ? $fallback : implode(',', $parts);
   }

   public static function isConfigured(?array $config = null): bool
   {
      $config ??= self::getConfig();

      if ((string)$config['enabled'] !== '1') {
         return false;
      }

      if ((string)$config['auth_mode'] === ApiClient::MODE_LEGACY) {
         return trim((string)$config['cloud']) !== ''
            && trim((string)$config['api_key']) !== ''
            && trim((string)$config['username']) !== ''
            && trim((string)$config['password']) !== '';
      }

      return trim((string)$config['vanity_domain']) !== ''
         && trim((string)$config['client_id']) !== ''
         && trim((string)$config['client_secret']) !== '';
   }

   /** Acoes de escrita so sao permitidas com modo leitura desligado e acoes liberadas. */
   public static function actionsEnabled(?array $config = null): bool
   {
      $config ??= self::getConfig();

      return self::isConfigured($config)
         && (string)$config['readonly_mode'] !== '1'
         && (string)$config['allow_actions'] === '1';
   }

   public static function zccConfigured(?array $config = null): bool
   {
      $config ??= self::getConfig();

      return (string)($config['zcc_enabled'] ?? '0') === '1'
         && trim((string)($config['zcc_api_key'] ?? '')) !== ''
         && trim((string)($config['zcc_secret_key'] ?? '')) !== '';
   }

   public static function zdxConfigured(?array $config = null): bool
   {
      $config ??= self::getConfig();

      return (string)($config['zdx_enabled'] ?? '0') === '1'
         && trim((string)($config['zdx_key_id'] ?? '')) !== ''
         && trim((string)($config['zdx_key_secret'] ?? '')) !== '';
   }

   public static function setConnectionTestFlash(array $payload): void
   {
      $_SESSION[self::CONNECTION_FLASH_KEY] = [
         'ok'          => (bool)($payload['ok'] ?? false),
         'duration_ms' => (int)($payload['duration_ms'] ?? 0),
         'status'      => (int)($payload['status'] ?? 0),
         'message'     => (string)($payload['message'] ?? ''),
      ];
   }

   public static function showForm(): void
   {
      $config = self::getConfig();
      $formUrl = self::getPluginFormUrl();
      $canUpdate = Profile::hasConfigUpdateRight();
      $configured = self::isConfigured($config);
      $isLegacy = (string)$config['auth_mode'] === ApiClient::MODE_LEGACY;
      $flash = self::pullConnectionTestFlash();

      echo "<div class='zscaler-config'>";
      echo "<form method='post' action='" . self::h($formUrl) . "' id='zscaler-config-form'>";

      // Hero com identidade da marca
      echo "<div class='zscaler-config__hero'>";
      echo "<div class='zs-hero__brand'>";
      echo "<span class='zs-logo'><span class='ti ti-cloud-lock'></span></span>";
      echo "<div>";
      echo "<div class='zscaler-config__eyebrow'>Zscaler &middot; Internet Access</div>";
      echo "<h2>Integracao da API</h2>";
      echo "<p>Credenciais, sincronizacao, acoes e automacao de tickets do plugin.</p>";
      echo "</div>";
      echo "</div>";
      echo "<div class='zscaler-config__status " . ($configured ? 'is-ok' : 'is-warn') . "'>";
      echo "<span class='ti " . ($configured ? 'ti-circle-check' : 'ti-alert-triangle') . "'></span>";
      echo $configured ? 'Configurada' : 'Pendente';
      echo "</div>";
      echo "</div>";

      if ($flash !== null) {
         self::renderConnectionTestFlash($flash);
      }
      echo "<div id='zscaler-test-result' class='zscaler-test-result zscaler-test-result--hidden' aria-live='polite'></div>";

      echo "<div class='zscaler-config__grid'>";

      // ----- Conexao -----
      echo "<section class='zscaler-panel zscaler-panel--wide'>";
      self::panelHead('Conexao', 'Esquema de autenticacao e credenciais da API ZIA.', 'ti-plug-connected');
      echo "<div class='zscaler-panel__body zscaler-fields'>";
      self::renderYesNo('enabled', 'Integracao ativa', (string)$config['enabled'] === '1', $canUpdate, 'Interruptor geral: sincronizacoes e acoes so rodam com isto em Sim.');
      self::renderSelectFromArray('auth_mode', 'Esquema de autenticacao', [
         ApiClient::MODE_ONEAPI => 'OneAPI (Zidentity / OAuth2) - recomendado',
         ApiClient::MODE_LEGACY => 'Legado (API key + usuario/senha)',
      ], $config['auth_mode'], $canUpdate, 'OneAPI usa client_id/secret; legado usa API key ofuscada. Os campos abaixo mudam conforme a escolha.', 'data-zs-authmode');
      self::renderPreset('cloud', 'Cloud Zscaler', self::cloudPresets(), (string)$config['cloud'], 'A nuvem do seu tenant (aparece na URL da console). Usada no modo legado e nos links.', $canUpdate, 'ex.: zscalertwo.net');
      self::renderNumber('timeout', 'Timeout HTTP em segundos', (int)$config['timeout'], 5, 120, $canUpdate, 'Tempo maximo de espera por resposta da API.');

      // OneAPI
      echo "<div class='zscaler-auth zscaler-auth--oneapi" . ($isLegacy ? ' zscaler-field--hidden' : '') . "'>";
      self::renderText('vanity_domain', 'Vanity domain (Zidentity)', (string)$config['vanity_domain'], 'ex.: minhaempresa', $canUpdate, false, 'Subdominio do seu Zidentity: https://<vanity>.zslogin.net. Informe apenas o nome.');
      self::renderText('client_id', 'Client ID', (string)$config['client_id'], '', $canUpdate, false, 'Client ID da aplicacao de API no Zidentity.');
      self::renderPassword('client_secret', 'Client Secret', trim((string)$config['client_secret']) !== '' ? 'Secret cadastrado' : 'Secret nao cadastrado', $canUpdate);
      self::renderText('oneapi_api_base', 'Base da API OneAPI (avancado)', (string)$config['oneapi_api_base'], 'https://api.zsapi.net', $canUpdate, false, 'Padrao para producao. Altere apenas para clouds alternativas (beta/alpha).');
      echo "</div>";

      // Legado
      echo "<div class='zscaler-auth zscaler-auth--legacy" . (!$isLegacy ? ' zscaler-field--hidden' : '') . "'>";
      self::renderPassword('api_key', 'API key', trim((string)$config['api_key']) !== '' ? 'API key cadastrada' : 'API key nao cadastrada', $canUpdate);
      self::renderText('username', 'Usuario administrador da API', (string)$config['username'], 'ex.: api-glpi@empresa.com', $canUpdate, false, 'Usuario admin dedicado a API (com papel de API).');
      self::renderPassword('password', 'Senha', trim((string)$config['password']) !== '' ? 'Senha cadastrada' : 'Senha nao cadastrada', $canUpdate);
      echo "</div>";

      echo "</div>";
      echo "</section>";

      // ----- Acoes -----
      echo "<section class='zscaler-panel'>";
      self::panelHead('Acoes na console', 'Travas de seguranca para escrita na Zscaler.', 'ti-bolt');
      echo "<div class='zscaler-panel__body zscaler-fields'>";
      self::renderYesNo('readonly_mode', 'Modo somente leitura', (string)$config['readonly_mode'] === '1', $canUpdate, 'Ligado: o plugin nunca escreve na console (so consulta). Desligue para liberar acoes.');
      self::renderYesNo('allow_actions', 'Permitir acoes de escrita', (string)$config['allow_actions'] === '1', $canUpdate, 'Habilita bloquear/recategorizar URLs. Exige modo somente leitura desligado e o direito "Acoes".');
      self::renderYesNo('auto_activate', 'Ativar mudancas automaticamente', (string)$config['auto_activate'] === '1', $canUpdate, 'Apos cada escrita, chama /status/activate para publicar na console.');
      echo "</div>";
      echo "</section>";

      // ----- Sincronizacao -----
      echo "<section class='zscaler-panel'>";
      self::panelHead('Sincronizacao', 'O que o plugin importa do ZIA.', 'ti-refresh');
      echo "<div class='zscaler-panel__body zscaler-fields'>";
      self::renderNumber('sync_limit', 'Itens por pagina', (int)$config['sync_limit'], 1, 1000, $canUpdate, 'Quantos itens pedir por pagina a API.');
      self::renderNumber('max_pages', 'Maximo de paginas', (int)$config['max_pages'], 1, 500, $canUpdate, 'Limite de paginas por execucao.');
      self::renderEntityDropdown('entity_id', 'Entidade padrao GLPI', (int)$config['entity_id'], $canUpdate);
      self::renderYesNo('sync_users', 'Sincronizar usuarios', (string)$config['sync_users'] === '1', $canUpdate, 'Importa a lista de usuarios do ZIA (somente leitura).');
      self::renderYesNo('sync_locations', 'Sincronizar localidades', (string)$config['sync_locations'] === '1', $canUpdate, 'Importa a lista de localidades do ZIA (somente leitura).');
      self::renderNumber('audit_days', 'Auditoria: dias por importacao', (int)$config['audit_days'], 1, 180, $canUpdate, 'Janela de tempo do Admin Audit Log puxada a cada sincronizacao.');
      self::renderYesNo('ticket_on_sensitive_audit', 'Ticket em mudanca sensivel (auditoria)', (string)$config['ticket_on_sensitive_audit'] === '1', $canUpdate, 'Abre ticket quando um novo registro de auditoria casa com as palavras-chave sensiveis (exige automacao de tickets ligada).');
      self::renderText('audit_sensitive_keywords', 'Palavras-chave sensiveis (auditoria)', (string)$config['audit_sensitive_keywords'], 'delete,disable,credential...', $canUpdate, true, 'Comparadas com acao/recurso do registro de auditoria (sem distincao de maiusculas). Separe por virgula.');
      self::renderYesNo('selfservice_enabled', 'Self-service: bloqueio com aprovacao', (string)$config['selfservice_enabled'] === '1', $canUpdate, 'Habilita, na aba Zscaler do Ticket, o pedido de bloqueio de URL que so executa apos aprovacao GLPI.');
      echo "</div>";
      echo "</section>";

      // ----- Tickets -----
      echo "<section class='zscaler-panel zscaler-panel--wide'>";
      self::panelHead('Automacao de tickets', 'Abrir chamados GLPI a partir de acoes e veredictos de sandbox.', 'ti-ticket');
      echo "<div class='zscaler-panel__body zscaler-fields'>";
      self::renderYesNo('create_tickets', 'Criar tickets', (string)$config['create_tickets'] === '1', $canUpdate, 'Interruptor geral da automacao de tickets.');
      self::renderYesNo('ticket_on_action', 'Ticket ao bloquear/recategorizar', (string)$config['ticket_on_action'] === '1', $canUpdate, 'Abre um ticket de trilha de auditoria sempre que uma acao de escrita e executada.');
      self::renderYesNo('ticket_on_sandbox_malicious', 'Ticket em veredito malicioso (sandbox)', (string)$config['ticket_on_sandbox_malicious'] === '1', $canUpdate, 'Abre ticket quando uma consulta de sandbox retorna veredito malicioso.');
      self::renderTicketCategoryDropdown('ticket_category_id', 'Categoria GLPI do ticket', (int)$config['ticket_category_id'], $canUpdate);
      self::renderUserDropdown('ticket_requester_id', 'Usuario solicitante (integracao)', (int)$config['ticket_requester_id'], $canUpdate, 'Crie um usuario dedicado (ex.: "integracao") para identificar os chamados do plugin.');
      self::renderSelectFromArray('ticket_urgency', 'Urgencia', self::ticketScaleOptions(false), (int)$config['ticket_urgency'], $canUpdate);
      self::renderSelectFromArray('ticket_impact', 'Impacto', self::ticketScaleOptions(false), (int)$config['ticket_impact'], $canUpdate);
      self::renderSelectFromArray('ticket_priority', 'Prioridade', self::ticketScaleOptions(true), (int)$config['ticket_priority'], $canUpdate);
      self::renderYesNo('ticket_on_zdx_alert', 'Ticket em alerta ZDX', (string)$config['ticket_on_zdx_alert'] === '1', $canUpdate, 'Abre ticket quando um alerta do ZDX e sincronizado (respeita a severidade minima do ZDX).');
      self::renderYesNo('ticket_on_risky_app', 'Ticket em app de risco (Shadow IT)', (string)$config['ticket_on_risky_app'] === '1', $canUpdate, 'Abre ticket quando um novo app de nuvem de risco e descoberto na sincronizacao.');
      self::renderSelectFromArray('risky_app_min_risk', 'Risco minimo p/ ticket de app', [
         'medium' => 'Medio ou maior',
         'high'   => 'Alto ou critico',
      ], (string)$config['risky_app_min_risk'], $canUpdate, 'Define quais apps de nuvem viram ticket (quando o ticket de app de risco esta ligado).');
      echo "</div>";
      echo "</section>";

      // ----- Sandbox (ZIA) -----
      echo "<section class='zscaler-panel'>";
      self::panelHead('Cloud Sandbox (ZIA)', 'Submissao de arquivos e veredictos.', 'ti-flask');
      echo "<div class='zscaler-panel__body zscaler-fields'>";
      self::renderPassword('sandbox_token', 'Token de submissao do Sandbox', trim((string)$config['sandbox_token']) !== '' ? 'Token cadastrado' : 'Token nao cadastrado', $canUpdate);
      echo "<div class='zscaler-field zscaler-field--wide'><small>Token dedicado da API de submissao (Administration &rarr; Sandbox). Usa o host <code>csbapi.&lt;cloud&gt;</code>. A consulta por hash usa as credenciais ZIA normais.</small></div>";
      echo "</div>";
      echo "</section>";

      // ----- ZCC -----
      echo "<section class='zscaler-panel'>";
      self::panelHead('ZCC - Client Connector', 'Inventario de dispositivos para casar com Computadores do GLPI.', 'ti-device-laptop');
      echo "<div class='zscaler-panel__body zscaler-fields'>";
      self::renderYesNo('zcc_enabled', 'Modulo ZCC ativo', (string)$config['zcc_enabled'] === '1', $canUpdate, 'Liga a sincronizacao de dispositivos do Client Connector.');
      self::renderText('zcc_api_base', 'Base da API ZCC', (string)$config['zcc_api_base'], 'https://api-mobile.zscaler.net', $canUpdate, false, 'Host do Mobile Admin Portal. Para clouds especificas use mobileadmin.<cloud>.');
      self::renderText('zcc_api_key', 'API Key (Client ID)', (string)$config['zcc_api_key'], '', $canUpdate, false, 'Gerada em Mobile Admin Portal &rarr; Administration &rarr; API.');
      self::renderPassword('zcc_secret_key', 'Secret Key', trim((string)$config['zcc_secret_key']) !== '' ? 'Secret cadastrado' : 'Secret nao cadastrado', $canUpdate);
      self::renderNumber('zcc_sync_pages', 'Maximo de paginas por sync', (int)$config['zcc_sync_pages'], 1, 200, $canUpdate, 'Limite de paginas de dispositivos por execucao.');
      echo "</div>";
      echo "</section>";

      // ----- Escopo (workstations) -----
      echo "<section class='zscaler-panel'>";
      self::panelHead('Escopo (workstations)', 'O Client Connector roda so em workstations. VMs ficam fora.', 'ti-device-desktop');
      echo "<div class='zscaler-panel__body zscaler-fields'>";
      echo "<div class='zscaler-field zscaler-field--wide'><small>Uma maquina e tratada como <strong>VM (fora de escopo)</strong> se o nome do <strong>Tipo</strong> OU do <strong>Modelo</strong> do computador contiver uma das palavras-chave abaixo (sem distincao de maiusculas). VMs nao entram na lista de \"sem ZCC\" e aparecem como fora de escopo na aba do Computador.</small></div>";
      self::renderText('vm_type_keywords', 'Palavras-chave de Tipo (VM)', (string)$config['vm_type_keywords'], Scope::DEFAULT_TYPE_KEYWORDS, $canUpdate, true, 'Comparadas com o nome do Tipo de computador. Separe por virgula.');
      self::renderText('vm_model_keywords', 'Palavras-chave de Modelo (VM)', (string)$config['vm_model_keywords'], Scope::DEFAULT_MODEL_KEYWORDS, $canUpdate, true, 'Comparadas com o nome do Modelo (preenchido pelo inventario: VMware, VirtualBox, KVM...). Separe por virgula.');
      echo "</div>";
      echo "</section>";

      // ----- ZDX -----
      echo "<section class='zscaler-panel'>";
      self::panelHead('ZDX - Digital Experience', 'Alertas de experiencia para abrir tickets.', 'ti-activity-heartbeat');
      echo "<div class='zscaler-panel__body zscaler-fields'>";
      self::renderYesNo('zdx_enabled', 'Modulo ZDX ativo', (string)$config['zdx_enabled'] === '1', $canUpdate, 'Liga a sincronizacao de alertas do ZDX.');
      self::renderText('zdx_api_base', 'Base da API ZDX', (string)$config['zdx_api_base'], 'https://api.zdxcloud.net', $canUpdate, false, 'Padrao para a cloud zdxcloud.');
      self::renderText('zdx_key_id', 'Key ID', (string)$config['zdx_key_id'], '', $canUpdate, false, 'Gerada em ZDX Admin &rarr; Administration &rarr; API Management.');
      self::renderPassword('zdx_key_secret', 'Key Secret', trim((string)$config['zdx_key_secret']) !== '' ? 'Secret cadastrado' : 'Secret nao cadastrado', $canUpdate);
      self::renderSelectFromArray('zdx_alert_min_severity', 'Severidade minima p/ ticket', [
         ''         => 'Qualquer severidade',
         'high'     => 'Alta ou maior',
         'critical' => 'Somente critica',
      ], (string)$config['zdx_alert_min_severity'], $canUpdate, 'Filtra quais alertas viram ticket (quando o ticket de alerta ZDX esta ligado).');
      echo "</div>";
      echo "</section>";

      echo "</div>"; // grid

      echo "<div class='zscaler-config__actions'>";
      echo "<button class='btn btn-primary' type='submit' name='update' value='1'" . (!$canUpdate ? ' disabled' : '') . "><span class='ti ti-device-floppy'></span>Salvar</button>";
      echo "<button class='btn btn-outline-primary' type='submit' name='test' value='1' data-zscaler-test" . (!$canUpdate ? ' disabled' : '') . "><span class='ti ti-plug-connected'></span>Testar conexao</button>";
      echo "</div>";

      \Html::closeForm();
      self::renderScript();
      echo "</div>";
   }

   public static function getPluginFormUrl(): string
   {
      global $CFG_GLPI;

      return $CFG_GLPI['root_doc'] . '/plugins/zscaler/front/config.form.php';
   }

   // ---------------------------------------------------------------------
   // Helpers de formulario (mesma identidade visual do hero)
   // ---------------------------------------------------------------------

   private static function renderText(string $name, string $label, string $value, string $placeholder = '', bool $enabled = true, bool $wide = false, ?string $help = null): void
   {
      echo "<label class='zscaler-field" . ($wide ? ' zscaler-field--wide' : '') . "'>";
      echo "<span>" . self::h($label) . "</span>";
      echo "<input class='form-control' type='text' name='" . self::h($name) . "' value='" . self::h($value) . "' placeholder='" . self::h($placeholder) . "'" . self::disabled(!$enabled) . ">";
      self::renderHelp($help);
      echo "</label>";
   }

   private static function renderPassword(string $name, string $label, string $help, bool $enabled = true): void
   {
      echo "<label class='zscaler-field'>";
      echo "<span>" . self::h($label) . "</span>";
      echo "<input class='form-control' type='password' name='" . self::h($name) . "' autocomplete='new-password' placeholder='" . self::h($help) . "'" . self::disabled(!$enabled) . ">";
      echo "<small>Deixe em branco para manter o valor atual.</small>";
      echo "</label>";
   }

   private static function renderNumber(string $name, string $label, int $value, int $min, int $max, bool $enabled = true, ?string $help = null): void
   {
      echo "<label class='zscaler-field'>";
      echo "<span>" . self::h($label) . "</span>";
      echo "<input class='form-control' type='number' min='" . self::h((string)$min) . "' max='" . self::h((string)$max) . "' name='" . self::h($name) . "' value='" . self::h((string)$value) . "'" . self::disabled(!$enabled) . ">";
      self::renderHelp($help);
      echo "</label>";
   }

   private static function renderYesNo(string $name, string $label, bool $selected, bool $enabled = true, ?string $help = null): void
   {
      echo "<label class='zscaler-field'>";
      echo "<span>" . self::h($label) . "</span>";
      echo "<select class='form-select' name='" . self::h($name) . "'" . self::disabled(!$enabled) . ">";
      echo "<option value='0'" . (!$selected ? ' selected' : '') . ">Nao</option>";
      echo "<option value='1'" . ($selected ? ' selected' : '') . ">Sim</option>";
      echo "</select>";
      self::renderHelp($help);
      echo "</label>";
   }

   private static function renderSelectFromArray(string $name, string $label, array $options, $value, bool $enabled = true, ?string $help = null, string $extraAttr = ''): void
   {
      echo "<label class='zscaler-field'>";
      echo "<span>" . self::h($label) . "</span>";
      echo "<select class='form-select' name='" . self::h($name) . "' " . $extraAttr . self::disabled(!$enabled) . ">";
      foreach ($options as $optionValue => $optionLabel) {
         $selected = (string)$optionValue === (string)$value ? ' selected' : '';
         echo "<option value='" . self::h((string)$optionValue) . "'{$selected}>" . self::h((string)$optionLabel) . "</option>";
      }
      echo "</select>";
      self::renderHelp($help);
      echo "</label>";
   }

   private static function renderPreset(string $name, string $label, array $presets, string $value, string $help, bool $enabled, string $customPlaceholder): void
   {
      $value = trim($value);
      $isCustom = $value !== '' && !array_key_exists($value, $presets);

      echo "<label class='zscaler-field'>";
      echo "<span>" . self::h($label) . "</span>";
      echo "<select class='form-select' name='" . self::h($name) . "_preset' data-zs-preset='" . self::h($name) . "'" . self::disabled(!$enabled) . ">";
      foreach ($presets as $optionValue => $optionLabel) {
         $selected = (!$isCustom && (string)$optionValue === $value) ? ' selected' : '';
         echo "<option value='" . self::h((string)$optionValue) . "'{$selected}>" . self::h((string)$optionLabel) . "</option>";
      }
      echo "<option value='__custom__'" . ($isCustom ? ' selected' : '') . ">Personalizado...</option>";
      echo "</select>";
      echo "<input class='form-control" . ($isCustom ? '' : ' zscaler-field--hidden') . "' type='text' name='" . self::h($name) . "_custom' data-zs-preset-custom='" . self::h($name) . "' value='" . self::h($isCustom ? $value : '') . "' placeholder='" . self::h($customPlaceholder) . "'" . self::disabled(!$enabled) . ">";
      if ($help !== '') {
         echo "<small>" . self::h($help) . "</small>";
      }
      echo "</label>";
   }

   private static function renderEntityDropdown(string $name, string $label, int $value, bool $enabled = true): void
   {
      $entities = $_SESSION['glpiactiveentities'] ?? -1;

      echo "<label class='zscaler-field zscaler-field--entity'>";
      echo "<span>" . self::h($label) . "</span>";
      \Entity::dropdown([
         'name'                 => $name,
         'value'                => $value,
         'entity'               => $entities,
         'comments'             => false,
         'width'                => '100%',
         'addicon'              => false,
         'display_emptychoice'  => false,
         'permit_select_parent' => true,
         'readonly'             => !$enabled,
      ]);
      echo "<small>Usada para gravar dados e tickets criados pela integracao.</small>";
      echo "</label>";
   }

   private static function renderTicketCategoryDropdown(string $name, string $label, int $value, bool $enabled = true): void
   {
      $entities = $_SESSION['glpiactiveentities'] ?? -1;

      echo "<label class='zscaler-field'>";
      echo "<span>" . self::h($label) . "</span>";
      \ITILCategory::dropdown([
         'name'                => $name,
         'value'               => $value,
         'entity'              => $entities,
         'comments'            => false,
         'width'               => '100%',
         'addicon'             => false,
         'display_emptychoice' => true,
         'emptylabel'          => 'Sem categoria',
         'readonly'            => !$enabled,
      ]);
      echo "</label>";
   }

   private static function renderUserDropdown(string $name, string $label, int $value, bool $enabled = true, ?string $help = null): void
   {
      $entities = $_SESSION['glpiactiveentities'] ?? -1;

      echo "<label class='zscaler-field'>";
      echo "<span>" . self::h($label) . "</span>";
      \User::dropdown([
         'name'                => $name,
         'value'               => $value,
         'right'               => 'all',
         'entity'              => $entities,
         'width'               => '100%',
         'comments'            => false,
         'display_emptychoice' => true,
         'emptylabel'          => 'Sem usuario (padrao do GLPI)',
         'readonly'            => !$enabled,
      ]);
      self::renderHelp($help);
      echo "</label>";
   }

   private static function ticketScaleOptions(bool $includeMajor): array
   {
      $options = [
         1 => '1 - Muito baixa',
         2 => '2 - Baixa',
         3 => '3 - Media',
         4 => '4 - Alta',
         5 => '5 - Muito alta',
      ];

      if ($includeMajor) {
         $options[6] = '6 - Critica';
      }

      return $options;
   }

   private static function panelHead(string $title, string $subtitle, string $icon, string $rightHtml = ''): void
   {
      echo "<div class='zscaler-panel__head'>";
      echo "<div class='zscaler-panel__title'>";
      if ($icon !== '') {
         echo "<span class='zscaler-panel__icon ti " . self::h($icon) . "'></span>";
      }
      echo "<div>";
      echo "<h3>" . self::h($title) . "</h3>";
      if ($subtitle !== '') {
         echo "<p>" . self::h($subtitle) . "</p>";
      }
      echo "</div>";
      echo "</div>";
      if ($rightHtml !== '') {
         echo $rightHtml;
      }
      echo "</div>";
   }

   private static function renderHelp(?string $help): void
   {
      if ($help !== null && $help !== '') {
         echo "<small>" . self::h($help) . "</small>";
      }
   }

   private static function renderScript(): void
   {
      echo <<<'HTML'
<script>
(function () {
   const form = document.getElementById('zscaler-config-form');
   if (!form) { return; }

   // Alterna grupos de campos OneAPI x Legado
   const authSelect = form.querySelector('[data-zs-authmode]');
   const oneapi = form.querySelector('.zscaler-auth--oneapi');
   const legacy = form.querySelector('.zscaler-auth--legacy');
   function applyAuth() {
      if (!authSelect || !oneapi || !legacy) { return; }
      const isLegacy = authSelect.value === 'legacy';
      legacy.classList.toggle('zscaler-field--hidden', !isLegacy);
      oneapi.classList.toggle('zscaler-field--hidden', isLegacy);
   }
   if (authSelect) { authSelect.addEventListener('change', applyAuth); applyAuth(); }

   // Presets com opcao "Personalizado..."
   form.querySelectorAll('[data-zs-preset]').forEach(function (select) {
      const key = select.getAttribute('data-zs-preset');
      const custom = form.querySelector('[data-zs-preset-custom="' + key + '"]');
      if (!custom) { return; }
      function apply(focus) {
         const isCustom = select.value === '__custom__';
         custom.classList.toggle('zscaler-field--hidden', !isCustom);
         if (isCustom && focus) { custom.focus(); }
      }
      select.addEventListener('change', function () { apply(true); });
      apply(false);
   });

   // Teste de conexao via AJAX
   const button = form.querySelector('[data-zscaler-test]');
   const result = document.getElementById('zscaler-test-result');
   if (!button || !result) { return; }
   const originalButton = button.innerHTML;

   function showResult(ok, message, status) {
      result.className = 'zscaler-test-result ' + (ok ? 'zscaler-test-result--ok' : 'zscaler-test-result--error');
      result.innerHTML = '';
      const title = document.createElement('strong');
      title.textContent = ok ? 'Conexao OK' : 'Falha na conexao';
      const detail = document.createElement('span');
      let text = message || (ok ? 'Conexao validada.' : 'Nao foi possivel validar a conexao.');
      if (status && status > 0) { text += ' HTTP ' + status + '.'; }
      detail.textContent = text;
      result.appendChild(title);
      result.appendChild(detail);
   }

   button.addEventListener('click', function (event) {
      event.preventDefault();
      if (button.disabled) { return; }
      const token = form.querySelector('input[name="_glpi_csrf_token"]');
      const data = new FormData(form);
      data.set('ajax_test', '1');
      data.set('test', '1');
      button.disabled = true;
      button.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Testando';
      result.className = 'zscaler-test-result zscaler-test-result--loading';
      result.innerHTML = '<strong>Testando</strong><span>Chamando a API Zscaler...</span>';
      fetch(form.action, {
         method: 'POST',
         body: data,
         credentials: 'same-origin',
         headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-Glpi-Csrf-Token': token ? token.value : '' }
      })
         .then(function (r) { return r.json(); })
         .then(function (p) { showResult(Boolean(p.ok), p.message, Number(p.status || 0)); })
         .catch(function () { showResult(false, 'Falha inesperada ao testar a conexao.', 0); })
         .finally(function () { button.disabled = false; button.innerHTML = originalButton; });
   });
})();
</script>
HTML;
   }

   // ---------------------------------------------------------------------
   // Utilidades
   // ---------------------------------------------------------------------

   private static function pullConnectionTestFlash(): ?array
   {
      $flash = $_SESSION[self::CONNECTION_FLASH_KEY] ?? null;
      unset($_SESSION[self::CONNECTION_FLASH_KEY]);

      return is_array($flash) ? $flash : null;
   }

   private static function renderConnectionTestFlash(array $flash): void
   {
      $ok = (bool)($flash['ok'] ?? false);
      $status = (int)($flash['status'] ?? 0);
      $message = (string)($flash['message'] ?? '');
      $class = $ok ? 'zscaler-test-result--ok' : 'zscaler-test-result--error';
      $title = $ok ? 'Conexao OK' : 'Falha na conexao';

      echo "<div class='zscaler-test-result {$class}'>";
      echo "<strong>" . self::h($title) . "</strong>";
      echo "<span>" . self::h($message);
      if ($status > 0) {
         echo " HTTP " . self::h((string)$status) . ".";
      }
      echo "</span>";
      echo "</div>";
   }

   private static function resolvePreset(array $input, string $name, array $presetKeys, string $default): string
   {
      $preset = trim((string)($input[$name . '_preset'] ?? ''));

      if ($preset === '__custom__') {
         $custom = trim((string)($input[$name . '_custom'] ?? ''));
         return $custom !== '' ? $custom : $default;
      }

      if ($preset !== '' && in_array($preset, $presetKeys, true)) {
         return $preset;
      }

      $direct = trim((string)($input[$name] ?? ''));

      return $direct !== '' ? $direct : $default;
   }

   private static function cleanVanity(string $value): string
   {
      $value = strtolower(trim($value));
      $value = preg_replace('#^https?://#', '', $value) ?? $value;
      $value = preg_replace('#\.zslogin\.net.*$#', '', $value) ?? $value;

      return trim($value, '/ ');
   }

   private static function flushTokenCache(): void
   {
      global $DB;

      if (isset($DB) && $DB->tableExists('glpi_plugin_zscaler_tokens')) {
         $DB->delete('glpi_plugin_zscaler_tokens', [1]);
      }
   }

   private static function boolInput(array $input, string $key): string
   {
      return isset($input[$key]) && (string)$input[$key] === '1' ? '1' : '0';
   }

   private static function protectSecret(string $secret): string
   {
      if ($secret === '' || str_starts_with($secret, self::SECRET_PREFIX)) {
         return $secret;
      }

      if (class_exists(\GLPIKey::class)) {
         $key = new \GLPIKey();
         if (method_exists($key, 'encrypt')) {
            return self::SECRET_PREFIX . $key->encrypt($secret);
         }
      }

      return $secret;
   }

   private static function unprotectSecret(string $secret): string
   {
      if ($secret === '' || !str_starts_with($secret, self::SECRET_PREFIX)) {
         return $secret;
      }

      if (!class_exists(\GLPIKey::class)) {
         return '';
      }

      $key = new \GLPIKey();
      if (!method_exists($key, 'decrypt')) {
         return '';
      }

      try {
         return (string)$key->decrypt(substr($secret, strlen(self::SECRET_PREFIX)));
      } catch (\Throwable $error) {
         return '';
      }
   }

   private static function disabled(bool $disabled): string
   {
      return $disabled ? ' disabled' : '';
   }

   private static function h(string $value): string
   {
      return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
   }
}
