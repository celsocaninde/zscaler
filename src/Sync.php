<?php

namespace GlpiPlugin\Zscaler;

class Sync
{
   public static function getTypeName($nb = 0): string
   {
      return 'Sincronizacao Zscaler';
   }

   public static function cronInfo(string $name): array
   {
      $info = [
         'syncziadata' => [
            'description' => 'Sincroniza categorias de URL e a denylist do Zscaler Internet Access',
         ],
         'synczccdevices' => [
            'description' => 'Sincroniza dispositivos do Zscaler Client Connector com Computadores do GLPI',
         ],
         'synczdxalerts' => [
            'description' => 'Sincroniza alertas do Zscaler Digital Experience e cria tickets opcionais',
         ],
         'syncziaaudit' => [
            'description' => 'Importa o Admin Audit Log do Zscaler (quem mudou o que na console)',
         ],
         'synczcloudapps' => [
            'description' => 'Sincroniza apps de nuvem (Shadow IT) e abre tickets para apps de risco',
         ],
      ];

      return $info[strtolower($name)] ?? [];
   }

   public static function cronSyncziadata(?\CronTask $task = null): int
   {
      try {
         $result = self::syncAll();

         if ($task !== null) {
            $task->addVolume($result['processed']);
         }

         return 1;
      } catch (\Throwable $error) {
         Log::record('syncziadata', 'error', $error->getMessage());
         return 0;
      }
   }

   public static function cronSynczccdevices(?\CronTask $task = null): int
   {
      try {
         $result = self::syncZccDevices();
         if ($task !== null) {
            $task->addVolume($result['processed']);
         }
         return 1;
      } catch (\Throwable $error) {
         Log::record('synczccdevices', 'error', $error->getMessage());
         return 0;
      }
   }

   public static function cronSynczdxalerts(?\CronTask $task = null): int
   {
      try {
         $result = self::syncZdxAlerts();
         if ($task !== null) {
            $task->addVolume($result['processed']);
         }
         return 1;
      } catch (\Throwable $error) {
         Log::record('synczdxalerts', 'error', $error->getMessage());
         return 0;
      }
   }

   public static function cronSyncziaaudit(?\CronTask $task = null): int
   {
      try {
         $result = self::syncAuditLog();
         if ($task !== null) {
            $task->addVolume($result['inserted']);
         }
         return 1;
      } catch (\Throwable $error) {
         Log::record('syncziaaudit', 'error', $error->getMessage());
         return 0;
      }
   }

   public static function cronSynczcloudapps(?\CronTask $task = null): int
   {
      try {
         $result = self::syncCloudApps();
         if ($task !== null) {
            $task->addVolume($result['processed']);
         }
         return 1;
      } catch (\Throwable $error) {
         Log::record('synczcloudapps', 'error', $error->getMessage());
         return 0;
      }
   }

   /**
    * @return array{processed:int, categories:int, denylist:int}
    */
   public static function syncAll(): array
   {
      $config = Config::getConfig();

      if (!Config::isConfigured($config)) {
         Log::record('syncziadata', 'skipped', 'Integracao Zscaler nao configurada.');
         return ['processed' => 0, 'categories' => 0, 'denylist' => 0];
      }

      $client = ApiClient::fromConfig($config);

      $categories = self::syncCategories($client);
      $denylist = self::syncDenylist($client);

      $processed = $categories + $denylist;
      Log::record('syncziadata', 'ok', 'Sincronizacao ZIA concluida.', $processed);

      return ['processed' => $processed, 'categories' => $categories, 'denylist' => $denylist];
   }

   private static function syncCategories(ApiClient $client): int
   {
      global $DB;

      $categories = $client->getUrlCategories(false);
      $now = date('Y-m-d H:i:s');
      $table = UrlCategory::getTable();

      foreach ($categories as $raw) {
         if (!is_array($raw)) {
            continue;
         }

         $zscalerId = (string)($raw['id'] ?? $raw['configuredName'] ?? '');
         if ($zscalerId === '') {
            continue;
         }

         $isCustom = (bool)($raw['customCategory'] ?? false);
         $urls = is_array($raw['urls'] ?? null) ? $raw['urls'] : [];
         $dbUrls = is_array($raw['dbCategorizedUrls'] ?? null) ? $raw['dbCategorizedUrls'] : [];

         $data = [
            'name'                => (string)($raw['configuredName'] ?? $zscalerId),
            'type'                => $isCustom ? 'custom' : 'predefined',
            'super_category'      => self::nullable($raw['superCategory'] ?? null),
            'urls_count'          => count($urls),
            'db_categorized_urls' => count($dbUrls),
            'custom_urls'         => $urls !== [] ? json_encode(array_values($urls), JSON_UNESCAPED_SLASHES) : null,
            'raw_json'            => json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'date_mod'            => $now,
         ];

         $existingId = self::findId($table, 'zscaler_id', $zscalerId);
         if ($existingId !== null) {
            $DB->update($table, $data, ['id' => $existingId]);
         } else {
            $DB->insert($table, $data + ['zscaler_id' => $zscalerId, 'date_creation' => $now]);
         }
      }

      return count($categories);
   }

   private static function syncDenylist(ApiClient $client): int
   {
      global $DB;

      $urls = $client->getDenylist();
      $table = DenylistEntry::getTable();
      $now = date('Y-m-d H:i:s');

      // Substitui o cache local pela foto atual da console.
      $DB->delete($table, [1]);

      foreach ($urls as $url) {
         $url = trim((string)$url);
         if ($url === '') {
            continue;
         }

         $DB->insert($table, [
            'url'           => mb_substr($url, 0, 255),
            'source'        => 'zia',
            'date_creation' => $now,
            'date_mod'      => $now,
         ]);
      }

      return count($urls);
   }

   // ---------------------------------------------------------------------
   // ZCC - dispositivos do Client Connector
   // ---------------------------------------------------------------------

   /**
    * @return array{processed:int, matched:int}
    */
   public static function syncZccDevices(): array
   {
      global $DB;

      $config = Config::getConfig();
      if (!Config::zccConfigured($config)) {
         Log::record('synczccdevices', 'skipped', 'Modulo ZCC nao configurado.');
         return ['processed' => 0, 'matched' => 0];
      }

      $client = ZccApiClient::fromConfig($config);
      $devices = $client->getDevices((int)$config['zcc_sync_pages']);
      $table = ZccDevice::getTable();
      $now = date('Y-m-d H:i:s');
      $matched = 0;

      foreach ($devices as $raw) {
         if (!is_array($raw)) {
            continue;
         }

         $udid = (string)self::pick($raw, ['udid', 'machineToken', 'deviceToken', 'id']);
         $hostname = (string)self::pick($raw, ['machineHostname', 'hostname', 'deviceName', 'name']);
         if ($udid === '' && $hostname === '') {
            continue;
         }
         if ($udid === '') {
            $udid = 'host:' . $hostname;
         }

         $computersId = self::matchComputer($hostname);
         if ($computersId !== null) {
            $matched++;
         }

         $data = [
            'user'               => self::nullable(self::pick($raw, ['user', 'userName', 'owner'])),
            'machine_hostname'   => self::nullable($hostname),
            'os_version'         => self::nullable(self::pick($raw, ['osVersion', 'os', 'operatingSystem'])),
            'agent_version'      => self::nullable(self::pick($raw, ['agentVersion', 'zccVersion', 'version'])),
            'registration_state' => self::nullable(self::pick($raw, ['registrationState', 'state', 'deviceState'])),
            'policy_name'        => self::nullable(self::pick($raw, ['policyName', 'policy'])),
            'computers_id'       => $computersId,
            'last_seen'          => self::epochOrDate(self::pick($raw, ['lastSeenTime', 'lastSeen', 'registrationTime', 'modifiedTime'])),
            'raw_json'           => json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'date_mod'           => $now,
         ];

         $existingId = self::findId($table, 'udid', $udid);
         if ($existingId !== null) {
            $DB->update($table, $data, ['id' => $existingId]);
         } else {
            $DB->insert($table, $data + ['udid' => $udid, 'date_creation' => $now]);
         }
      }

      Log::record('synczccdevices', 'ok', 'Sincronizacao de dispositivos ZCC concluida.', count($devices));

      return ['processed' => count($devices), 'matched' => $matched];
   }

   // ---------------------------------------------------------------------
   // ZDX - alertas de experiencia
   // ---------------------------------------------------------------------

   /**
    * @return array{processed:int, tickets:int}
    */
   public static function syncZdxAlerts(): array
   {
      global $DB;

      $config = Config::getConfig();
      if (!Config::zdxConfigured($config)) {
         Log::record('synczdxalerts', 'skipped', 'Modulo ZDX nao configurado.');
         return ['processed' => 0, 'tickets' => 0];
      }

      $client = ZdxApiClient::fromConfig($config);
      $alerts = $client->getOngoingAlerts();
      $table = ZdxAlert::getTable();
      $now = date('Y-m-d H:i:s');
      $tickets = 0;

      foreach ($alerts as $raw) {
         if (!is_array($raw)) {
            continue;
         }

         $alertId = (string)self::pick($raw, ['id', 'alert_id', 'alertId']);
         if ($alertId === '') {
            $alertId = hash('sha256', json_encode($raw, JSON_UNESCAPED_SLASHES));
         }

         $existing = self::getRowByField($table, 'zdx_alert_id', $alertId);

         $data = [
            'rule_name'   => self::nullable(self::pick($raw, ['rule_name', 'ruleName', 'name'])),
            'severity'    => self::nullable(self::pick($raw, ['severity', 'impact'])),
            'status'      => 'ongoing',
            'application' => self::nullable(self::pickApp($raw)),
            'num_devices' => (int)self::pick($raw, ['num_devices', 'numDevices', 'impacted_devices', 'devices']),
            'started_on'  => self::epochOrDate(self::pick($raw, ['started_on', 'startedOn', 'start_time'])),
            'ended_on'    => self::epochOrDate(self::pick($raw, ['ended_on', 'endedOn', 'end_time'])),
            'raw_json'    => json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'date_mod'    => $now,
         ];

         if ($existing !== null) {
            if (!empty($existing['tickets_id'])) {
               $data['tickets_id'] = (int)$existing['tickets_id'];
            }
            $DB->update($table, $data, ['id' => (int)$existing['id']]);
            continue;
         }

         if (self::shouldTicketZdx($data, $config)) {
            try {
               $data['tickets_id'] = TicketManager::createForZdxAlert($data, $raw, $config);
               $tickets++;
            } catch (\Throwable $error) {
               Log::record('synczdxalerts', 'warning', 'Falha ao criar ticket de alerta ZDX: ' . $error->getMessage());
            }
         }

         $DB->insert($table, $data + ['zdx_alert_id' => $alertId, 'date_creation' => $now]);
      }

      Log::record('synczdxalerts', 'ok', 'Sincronizacao de alertas ZDX concluida.', count($alerts));

      return ['processed' => count($alerts), 'tickets' => $tickets];
   }

   private static function shouldTicketZdx(array $alert, array $config): bool
   {
      if ((string)($config['create_tickets'] ?? '0') !== '1'
         || (string)($config['ticket_on_zdx_alert'] ?? '0') !== '1') {
         return false;
      }

      $min = strtolower((string)($config['zdx_alert_min_severity'] ?? ''));
      $severity = strtolower((string)($alert['severity'] ?? ''));

      if ($min === 'critical') {
         return $severity === 'critical';
      }
      if ($min === 'high') {
         return in_array($severity, ['high', 'critical'], true);
      }

      return true;
   }

   // ---------------------------------------------------------------------
   // Admin Audit Log (relatorio assincrono CSV)
   // ---------------------------------------------------------------------

   /**
    * @return array{processed:int, inserted:int}
    */
   public static function syncAuditLog(?int $daysBack = null): array
   {
      global $DB;

      $config = Config::getConfig();
      if (!Config::isConfigured($config)) {
         Log::record('syncziaaudit', 'skipped', 'Integracao Zscaler nao configurada.');
         return ['processed' => 0, 'inserted' => 0];
      }

      $days = $daysBack ?? max(1, min(180, (int)($config['audit_days'] ?? 7)));
      $client = ApiClient::fromConfig($config);
      $entries = $client->fetchAuditLog($days);

      $table = AuditEntry::getTable();
      $now = date('Y-m-d H:i:s');
      $inserted = 0;

      foreach ($entries as $raw) {
         if (!is_array($raw) || $raw === []) {
            continue;
         }

         $admin    = self::csvPick($raw, ['admin login id', 'admin', 'login id', 'user']);
         $action   = self::csvPick($raw, ['action', 'operation']);
         $resource = self::csvPick($raw, ['resource', 'category', 'subcategory', 'object', 'resource type']);
         $result   = self::csvPick($raw, ['result', 'status', 'response']);
         $clientIp = self::csvPick($raw, ['client ip', 'source ip', 'admin ip', 'ip']);
         $when     = self::csvPick($raw, ['recorded time', 'time', 'timestamp', 'date']);
         $recordedAt = self::epochOrDate($when) ?? $now;

         $hash = hash('sha256', $recordedAt . '|' . $admin . '|' . $action . '|' . $resource . '|' . $clientIp);

         if (self::findId($table, 'entry_hash', $hash) !== null) {
            continue;
         }

         $DB->insert($table, [
            'entry_hash'  => $hash,
            'admin'       => self::nullable($admin),
            'action'      => self::nullable($action),
            'resource'    => self::nullable($resource),
            'result'      => self::nullable($result),
            'client_ip'   => self::nullable($clientIp),
            'recorded_at' => $recordedAt,
            'raw_json'    => json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'date_creation' => $now,
         ]);
         $inserted++;
      }

      Log::record('syncziaaudit', 'ok', 'Auditoria ZIA sincronizada.', count($entries));

      return ['processed' => count($entries), 'inserted' => $inserted];
   }

   // ---------------------------------------------------------------------
   // Shadow IT / Cloud Applications
   // ---------------------------------------------------------------------

   /**
    * @return array{processed:int, tickets:int}
    */
   public static function syncCloudApps(): array
   {
      global $DB;

      $config = Config::getConfig();
      if (!Config::isConfigured($config)) {
         Log::record('synczcloudapps', 'skipped', 'Integracao Zscaler nao configurada.');
         return ['processed' => 0, 'tickets' => 0];
      }

      $client = ApiClient::fromConfig($config);
      $apps = $client->getCloudApplications();
      $table = CloudApp::getTable();
      $now = date('Y-m-d H:i:s');
      $tickets = 0;

      foreach ($apps as $raw) {
         if (!is_array($raw)) {
            continue;
         }

         $appId = (string)self::pick($raw, ['id', 'appId', 'app_id', 'val']);
         $name  = (string)self::pick($raw, ['name', 'appName', 'app', 'application']);
         if ($appId === '' && $name === '') {
            continue;
         }
         if ($appId === '') {
            $appId = 'name:' . $name;
         }

         $risk = self::nullable(self::pick($raw, ['riskIndex', 'risk', 'riskLevel', 'risk_index']));
         $data = [
            'name'       => self::nullable($name),
            'category'   => self::nullable(self::pick($raw, ['category', 'appCategory', 'parentCategory'])),
            'risk_index' => $risk,
            'sanctioned' => self::nullable(self::pick($raw, ['sanctioned', 'sanctionedState', 'status'])),
            'raw_json'   => json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'date_mod'   => $now,
         ];

         $existing = self::getRowByField($table, 'app_id', $appId);
         if ($existing !== null) {
            if (!empty($existing['tickets_id'])) {
               $data['tickets_id'] = (int)$existing['tickets_id'];
            }
            $DB->update($table, $data, ['id' => (int)$existing['id']]);
            continue;
         }

         if (self::shouldTicketApp($risk, $config)) {
            try {
               $data['tickets_id'] = TicketManager::createForCloudApp($name, $raw, $config);
               $tickets++;
            } catch (\Throwable $error) {
               Log::record('synczcloudapps', 'warning', 'Falha ao criar ticket de app de risco: ' . $error->getMessage());
            }
         }

         $DB->insert($table, $data + ['app_id' => $appId, 'date_creation' => $now]);
      }

      Log::record('synczcloudapps', 'ok', 'Apps de nuvem (Shadow IT) sincronizados.', count($apps));

      return ['processed' => count($apps), 'tickets' => $tickets];
   }

   private static function shouldTicketApp(?string $risk, array $config): bool
   {
      if ((string)($config['create_tickets'] ?? '0') !== '1'
         || (string)($config['ticket_on_risky_app'] ?? '0') !== '1') {
         return false;
      }

      $risk = strtolower(trim((string)$risk));
      if ($risk === '') {
         return false;
      }

      $min = strtolower((string)($config['risky_app_min_risk'] ?? 'high'));
      if ($min === 'medium') {
         return in_array($risk, ['medium', 'media', 'moderate', 'high', 'alto', 'critical', 'critica'], true);
      }

      return in_array($risk, ['high', 'alto', 'critical', 'critica'], true);
   }

   /**
    * Acesso a coluna do CSV de auditoria por nome aproximado (case-insensitive).
    *
    * @param array<string,string> $row
    * @param string[]             $names
    */
   private static function csvPick(array $row, array $names): string
   {
      $lower = [];
      foreach ($row as $key => $value) {
         $lower[strtolower(trim((string)$key))] = (string)$value;
      }
      foreach ($names as $name) {
         $name = strtolower($name);
         if (isset($lower[$name]) && trim($lower[$name]) !== '') {
            return trim($lower[$name]);
         }
      }
      // Match por substring (cabecalhos variam entre tenants).
      foreach ($names as $name) {
         $name = strtolower($name);
         foreach ($lower as $key => $value) {
            if (trim($value) !== '' && str_contains($key, $name)) {
               return trim($value);
            }
         }
      }

      return '';
   }

   private static function matchComputer(string $hostname): ?int
   {
      $hostname = trim($hostname);
      if ($hostname === '') {
         return null;
      }

      $id = self::findComputerByName($hostname);
      if ($id !== null) {
         return $id;
      }

      $short = preg_replace('/\..*$/', '', $hostname);
      if ($short !== null && $short !== $hostname) {
         return self::findComputerByName($short);
      }

      return null;
   }

   private static function findComputerByName(string $name): ?int
   {
      global $DB;

      foreach ($DB->request([
         'SELECT' => ['id'],
         'FROM'   => 'glpi_computers',
         'WHERE'  => ['name' => $name, 'is_deleted' => 0],
         'LIMIT'  => 1,
      ]) as $row) {
         return (int)$row['id'];
      }

      return null;
   }

   private static function getRowByField(string $table, string $field, string $value): ?array
   {
      global $DB;

      foreach ($DB->request([
         'FROM'  => $table,
         'WHERE' => [$field => $value],
         'LIMIT' => 1,
      ]) as $row) {
         return $row;
      }

      return null;
   }

   private static function pick(array $row, array $keys)
   {
      foreach ($keys as $key) {
         if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '' && !is_array($row[$key])) {
            return $row[$key];
         }
      }

      return null;
   }

   private static function pickApp(array $row): ?string
   {
      $app = $row['application'] ?? $row['app'] ?? null;
      if (is_array($app)) {
         return self::nullable($app['name'] ?? $app['app_name'] ?? null);
      }

      return self::nullable($app);
   }

   private static function epochOrDate($value): ?string
   {
      if ($value === null || $value === '') {
         return null;
      }
      if (is_numeric($value)) {
         $ts = (int)$value;
         if ($ts > 20000000000) { // milissegundos
            $ts = (int)($ts / 1000);
         }
         return date('Y-m-d H:i:s', $ts);
      }
      try {
         return (new \DateTimeImmutable((string)$value))->format('Y-m-d H:i:s');
      } catch (\Throwable $error) {
         return null;
      }
   }

   /**
    * Contagens para dashboard e tela de visao geral.
    */
   public static function stats(): array
   {
      return [
         'categories_total'  => self::countRows(UrlCategory::getTable()),
         'categories_custom' => self::countRows(UrlCategory::getTable(), ['type' => 'custom']),
         'denylist_total'    => self::countRows(DenylistEntry::getTable()),
         'actions_total'     => self::countRows(ActionLog::getTable()),
         'actions_error'     => self::countRows(ActionLog::getTable(), ['status' => 'error']),
         'zcc_total'         => self::countRows(ZccDevice::getTable()),
         'zcc_unprotected'   => ZccDevice::countUnprotectedComputers(),
         'zdx_total'         => self::countRows(ZdxAlert::getTable()),
         'zdx_ongoing'       => self::countRows(ZdxAlert::getTable(), ['status' => 'ongoing']),
         'audit_total'       => self::countRows(AuditEntry::getTable()),
         'cloudapps_total'   => self::countRows(CloudApp::getTable()),
         'cloudapps_risky'   => CloudApp::countRisky(),
         'last_sync'         => self::getLastLog('syncziadata'),
         'recent_actions'    => ActionLog::getRecent(8),
         'recent_categories' => self::getRows(UrlCategory::getTable(), ['type' => 'custom'], ['date_mod DESC'], 8),
         'logs'              => Log::getRecent(8),
      ];
   }

   private static function countRows(string $table, array $where = []): int
   {
      global $DB;

      if (!$DB->tableExists($table)) {
         return 0;
      }

      $criteria = ['COUNT' => 'cpt', 'FROM' => $table];
      if ($where !== []) {
         $criteria['WHERE'] = $where;
      }

      $row = $DB->request($criteria)->current();

      return (int)($row['cpt'] ?? 0);
   }

   private static function getRows(string $table, array $where, array $order, int $limit): array
   {
      global $DB;

      $rows = [];
      if (!$DB->tableExists($table)) {
         return $rows;
      }

      $criteria = ['FROM' => $table, 'ORDER' => $order, 'LIMIT' => max(1, min(50, $limit))];
      if ($where !== []) {
         $criteria['WHERE'] = $where;
      }

      foreach ($DB->request($criteria) as $row) {
         $rows[] = $row;
      }

      return $rows;
   }

   private static function getLastLog(string $action): ?array
   {
      global $DB;

      if (!$DB->tableExists(Log::getTable())) {
         return null;
      }

      foreach ($DB->request([
         'FROM'  => Log::getTable(),
         'WHERE' => ['action' => $action],
         'ORDER' => ['id DESC'],
         'LIMIT' => 1,
      ]) as $row) {
         return $row;
      }

      return null;
   }

   private static function findId(string $table, string $field, string $value): ?int
   {
      global $DB;

      foreach ($DB->request([
         'SELECT' => ['id'],
         'FROM'   => $table,
         'WHERE'  => [$field => $value],
         'LIMIT'  => 1,
      ]) as $row) {
         return (int)$row['id'];
      }

      return null;
   }

   private static function nullable($value): ?string
   {
      if ($value === null) {
         return null;
      }
      $value = trim((string)$value);

      return $value === '' ? null : $value;
   }
}
