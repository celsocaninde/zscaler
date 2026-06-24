<?php

namespace GlpiPlugin\Zscaler;

/**
 * Servico de acoes de escrita na console Zscaler.
 *
 * Centraliza as travas de seguranca (modo somente leitura, acoes liberadas,
 * direito do usuario), a ativacao das mudancas, a trilha de auditoria e a
 * automacao de tickets. Reutilizado pela ferramenta de URL lookup e pelo
 * botao de acao manual em tickets/ativos.
 */
class Actions
{
   /**
    * Bloqueia uma ou mais URLs na denylist do ZIA.
    *
    * @param string[]                                                            $urls
    * @param array{itemtype?:?string,items_id?:?int,tickets_id?:?int}            $source
    * @return array{ok:bool, count:int, ticket_id:int, message:string}
    */
   public static function blockUrls(array $urls, array $source = []): array
   {
      return self::denylistOperation('block', $urls, $source);
   }

   /**
    * Remove uma ou mais URLs da denylist do ZIA.
    *
    * @param string[]                                                 $urls
    * @param array{itemtype?:?string,items_id?:?int,tickets_id?:?int} $source
    * @return array{ok:bool, count:int, ticket_id:int, message:string}
    */
   public static function unblockUrls(array $urls, array $source = []): array
   {
      return self::denylistOperation('unblock', $urls, $source);
   }

   /**
    * @param string[]                                                 $urls
    * @param array{itemtype?:?string,items_id?:?int,tickets_id?:?int} $source
    * @return array{ok:bool, count:int, ticket_id:int, message:string}
    */
   private static function denylistOperation(string $op, array $urls, array $source): array
   {
      $config = self::guard();

      $urls = self::normalizeUrls($urls);
      if ($urls === []) {
         throw new \RuntimeException('Informe ao menos uma URL valida.');
      }

      $client = ApiClient::fromConfig($config);
      $action = $op === 'unblock' ? 'denylist_remove' : 'denylist_add';
      $target = self::summarizeTarget($urls);

      try {
         if ($op === 'unblock') {
            $client->removeFromDenylist($urls);
         } else {
            $client->addToDenylist($urls);
         }

         self::maybeActivate($client, $config);
         self::syncDenylistCache($op, $urls);
      } catch (\Throwable $error) {
         ActionLog::record([
            'action'          => $action,
            'target'          => $target,
            'status'          => 'error',
            'message'         => $error->getMessage(),
            'users_id'        => self::currentUserId(),
            'source_itemtype' => $source['itemtype'] ?? null,
            'source_items_id' => $source['items_id'] ?? null,
            'tickets_id'      => $source['tickets_id'] ?? null,
         ]);
         Log::record($action, 'error', $error->getMessage());
         throw $error;
      }

      $verb = $op === 'unblock' ? 'desbloqueada(s)' : 'bloqueada(s)';
      $message = count($urls) . ' URL(s) ' . $verb . ' com sucesso na console Zscaler.';

      $ticketId = self::handleTicket($action, $urls, $config, $source, $message);

      ActionLog::record([
         'action'          => $action,
         'target'          => $target,
         'status'          => 'ok',
         'message'         => $message,
         'users_id'        => self::currentUserId(),
         'tickets_id'      => $ticketId ?: ($source['tickets_id'] ?? null),
         'source_itemtype' => $source['itemtype'] ?? null,
         'source_items_id' => $source['items_id'] ?? null,
      ]);
      Log::record($action, 'ok', $message, count($urls));

      return [
         'ok'        => true,
         'count'     => count($urls),
         'ticket_id' => $ticketId,
         'message'   => $message,
      ];
   }

   /**
    * Adiciona URLs a uma categoria customizada (recategorizacao).
    *
    * @param string[] $urls
    * @return array{ok:bool, count:int, ticket_id:int, message:string}
    */
   public static function addUrlsToCategory(string $categoryId, array $urls, array $source = []): array
   {
      $config = self::guard();
      $urls = self::normalizeUrls($urls);
      if ($categoryId === '' || $urls === []) {
         throw new \RuntimeException('Categoria e ao menos uma URL sao obrigatorias.');
      }

      $client = ApiClient::fromConfig($config);
      $target = $categoryId . ': ' . self::summarizeTarget($urls);

      try {
         $client->addUrlsToCategory($categoryId, $urls);
         self::maybeActivate($client, $config);
      } catch (\Throwable $error) {
         ActionLog::record([
            'action'   => 'category_add_urls',
            'target'   => $target,
            'status'   => 'error',
            'message'  => $error->getMessage(),
            'users_id' => self::currentUserId(),
         ]);
         Log::record('category_add_urls', 'error', $error->getMessage());
         throw $error;
      }

      $message = count($urls) . ' URL(s) adicionada(s) a categoria ' . $categoryId . '.';
      $ticketId = self::handleTicket('category_add_urls', $urls, $config, $source, $message);

      ActionLog::record([
         'action'     => 'category_add_urls',
         'target'     => $target,
         'status'     => 'ok',
         'message'    => $message,
         'users_id'   => self::currentUserId(),
         'tickets_id' => $ticketId ?: ($source['tickets_id'] ?? null),
      ]);
      Log::record('category_add_urls', 'ok', $message, count($urls));

      return ['ok' => true, 'count' => count($urls), 'ticket_id' => $ticketId, 'message' => $message];
   }

   /**
    * Liga/desliga uma regra de URL Filtering.
    *
    * @return array{ok:bool, message:string}
    */
   public static function setUrlFilteringRuleState(int $ruleId, bool $enabled, string $ruleName = ''): array
   {
      $config = self::guard();
      if ($ruleId <= 0) {
         throw new \RuntimeException('Regra invalida.');
      }

      $client = ApiClient::fromConfig($config);
      $label = $ruleName !== '' ? $ruleName : ('#' . $ruleId);
      $state = $enabled ? 'ENABLED' : 'DISABLED';

      try {
         $client->setUrlFilteringRuleState($ruleId, $enabled);
         self::maybeActivate($client, $config);
      } catch (\Throwable $error) {
         ActionLog::record([
            'action'   => 'urlfilter_state',
            'target'   => $label . ' -> ' . $state,
            'status'   => 'error',
            'message'  => $error->getMessage(),
            'users_id' => self::currentUserId(),
         ]);
         Log::record('urlfilter_state', 'error', $error->getMessage());
         throw $error;
      }

      $message = 'Regra "' . $label . '" ' . ($enabled ? 'ativada' : 'desativada') . ' na console Zscaler.';
      ActionLog::record([
         'action'   => 'urlfilter_state',
         'target'   => $label . ' -> ' . $state,
         'status'   => 'ok',
         'message'  => $message,
         'users_id' => self::currentUserId(),
      ]);
      Log::record('urlfilter_state', 'ok', $message);

      return ['ok' => true, 'message' => $message];
   }

   /**
    * Liga/desliga uma regra de Cloud Firewall, DNS ou IPS.
    *
    * @param string $type 'filtering' | 'dns' | 'ips'
    * @return array{ok:bool, message:string}
    */
   public static function setFirewallRuleState(string $type, int $ruleId, bool $enabled, string $ruleName = ''): array
   {
      $config = self::guard();
      if ($ruleId <= 0) {
         throw new \RuntimeException('Regra invalida.');
      }

      $type = in_array(strtolower($type), ['dns', 'ips', 'filtering'], true) ? strtolower($type) : 'filtering';
      $typeLabel = ['dns' => 'DNS', 'ips' => 'IPS', 'filtering' => 'Firewall'][$type];
      $client = ApiClient::fromConfig($config);
      $label = $ruleName !== '' ? $ruleName : ('#' . $ruleId);
      $state = $enabled ? 'ENABLED' : 'DISABLED';

      try {
         $client->setFirewallRuleState($type, $ruleId, $enabled);
         self::maybeActivate($client, $config);
      } catch (\Throwable $error) {
         ActionLog::record([
            'action'   => 'firewall_state',
            'target'   => $typeLabel . ' ' . $label . ' -> ' . $state,
            'status'   => 'error',
            'message'  => $error->getMessage(),
            'users_id' => self::currentUserId(),
         ]);
         Log::record('firewall_state', 'error', $error->getMessage());
         throw $error;
      }

      $message = 'Regra ' . $typeLabel . ' "' . $label . '" ' . ($enabled ? 'ativada' : 'desativada') . ' na console Zscaler.';
      ActionLog::record([
         'action'   => 'firewall_state',
         'target'   => $typeLabel . ' ' . $label . ' -> ' . $state,
         'status'   => 'ok',
         'message'  => $message,
         'users_id' => self::currentUserId(),
      ]);
      Log::record('firewall_state', 'ok', $message);

      return ['ok' => true, 'message' => $message];
   }

   /**
    * Adiciona URLs a allowlist (bypass) da politica de seguranca.
    *
    * @param string[] $urls
    * @return array{ok:bool, count:int, ticket_id:int, message:string}
    */
   public static function allowlistUrls(array $urls, array $source = []): array
   {
      return self::allowlistOperation('add', $urls, $source);
   }

   /**
    * Remove URLs da allowlist (bypass) da politica de seguranca.
    *
    * @param string[] $urls
    * @return array{ok:bool, count:int, ticket_id:int, message:string}
    */
   public static function removeAllowlistUrls(array $urls, array $source = []): array
   {
      return self::allowlistOperation('remove', $urls, $source);
   }

   /**
    * @param string[] $urls
    * @return array{ok:bool, count:int, ticket_id:int, message:string}
    */
   private static function allowlistOperation(string $op, array $urls, array $source): array
   {
      $config = self::guard();

      $urls = self::normalizeUrls($urls);
      if ($urls === []) {
         throw new \RuntimeException('Informe ao menos uma URL valida.');
      }

      $client = ApiClient::fromConfig($config);
      $action = $op === 'remove' ? 'allowlist_remove' : 'allowlist_add';
      $target = self::summarizeTarget($urls);

      try {
         if ($op === 'remove') {
            $client->removeFromAllowlist($urls);
         } else {
            $client->addToAllowlist($urls);
         }
         self::maybeActivate($client, $config);
      } catch (\Throwable $error) {
         ActionLog::record([
            'action'   => $action,
            'target'   => $target,
            'status'   => 'error',
            'message'  => $error->getMessage(),
            'users_id' => self::currentUserId(),
         ]);
         Log::record($action, 'error', $error->getMessage());
         throw $error;
      }

      $verb = $op === 'remove' ? 'removida(s) da' : 'adicionada(s) a';
      $message = count($urls) . ' URL(s) ' . $verb . ' allowlist de seguranca na console Zscaler.';
      $ticketId = self::handleTicket($action, $urls, $config, $source, $message);

      ActionLog::record([
         'action'     => $action,
         'target'     => $target,
         'status'     => 'ok',
         'message'    => $message,
         'users_id'   => self::currentUserId(),
         'tickets_id' => $ticketId ?: ($source['tickets_id'] ?? null),
      ]);
      Log::record($action, 'ok', $message, count($urls));

      return ['ok' => true, 'count' => count($urls), 'ticket_id' => $ticketId, 'message' => $message];
   }

   private static function guard(): array
   {
      $config = Config::getConfig();

      if (!Config::isConfigured($config)) {
         throw new \RuntimeException('Integracao Zscaler nao esta configurada.');
      }

      if (!Profile::hasActionRight()) {
         throw new \RuntimeException('Voce nao tem permissao para executar acoes na Zscaler.');
      }

      if (!Config::actionsEnabled($config)) {
         throw new \RuntimeException('Acoes de escrita desativadas. Desligue o "modo somente leitura" e ative "permitir acoes" na configuracao.');
      }

      return $config;
   }

   private static function maybeActivate(ApiClient $client, array $config): void
   {
      if ((string)($config['auto_activate'] ?? '1') === '1') {
         $client->activateChanges();
      }
   }

   /**
    * @param string[] $urls
    */
   private static function handleTicket(string $action, array $urls, array $config, array $source, string $summary): int
   {
      $sourceTicket = (int)($source['tickets_id'] ?? 0);

      try {
         if ($sourceTicket > 0) {
            TicketManager::addActionFollowup($sourceTicket, $action, $urls, $summary);
            return $sourceTicket;
         }

         if ((string)($config['create_tickets'] ?? '0') === '1'
            && (string)($config['ticket_on_action'] ?? '0') === '1') {
            return TicketManager::createForAction($action, $urls, $config, $source);
         }
      } catch (\Throwable $error) {
         Log::record($action, 'warning', 'Acao executada, mas falhou ao registrar ticket: ' . $error->getMessage());
      }

      return 0;
   }

   /**
    * Atualiza o cache local da denylist apos uma escrita.
    *
    * @param string[] $urls
    */
   private static function syncDenylistCache(string $op, array $urls): void
   {
      global $DB;

      $table = DenylistEntry::getTable();
      if (!$DB->tableExists($table)) {
         return;
      }

      $now = date('Y-m-d H:i:s');
      foreach ($urls as $url) {
         $url = mb_substr($url, 0, 255);
         if ($op === 'unblock') {
            $DB->delete($table, ['url' => $url]);
            continue;
         }

         $exists = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => $table,
            'WHERE'  => ['url' => $url],
            'LIMIT'  => 1,
         ])->current();

         if (!$exists) {
            $DB->insert($table, [
               'url'           => $url,
               'source'        => 'zia',
               'date_creation' => $now,
               'date_mod'      => $now,
            ]);
         }
      }
   }

   /**
    * @param string[] $urls
    * @return string[]
    */
   private static function normalizeUrls(array $urls): array
   {
      $clean = [];
      foreach ($urls as $url) {
         $url = trim((string)$url);
         $url = preg_replace('#^https?://#i', '', $url) ?? $url;
         $url = rtrim($url, '/');
         if ($url !== '') {
            $clean[strtolower($url)] = $url;
         }
      }

      return array_values($clean);
   }

   /**
    * @param string[] $urls
    */
   private static function summarizeTarget(array $urls): string
   {
      $first = $urls[0] ?? '';
      $extra = count($urls) - 1;

      return $extra > 0 ? ($first . ' (+' . $extra . ')') : $first;
   }

   private static function currentUserId(): ?int
   {
      $id = (int)($_SESSION['glpiID'] ?? 0);

      return $id > 0 ? $id : null;
   }
}
