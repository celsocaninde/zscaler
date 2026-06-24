<?php

namespace GlpiPlugin\Zscaler;

/**
 * Fluxo self-service de bloqueio de URL com aprovacao GLPI.
 *
 * Um tecnico solicita o bloqueio a partir da aba Zscaler de um Ticket; o pedido
 * fica em 'pending_approval' ate o aprovador responder a solicitacao de validacao
 * (TicketValidation). Aceito -> executa o bloqueio na console; recusado -> cancela.
 * A aprovacao GLPI e a autorizacao: a execucao nao depende do direito "Acoes" do
 * aprovador, mas respeita o modo somente leitura da integracao.
 */
class BlockRequest
{
   public const TABLE = 'glpi_plugin_zscaler_blockrequests';

   /**
    * Cria o pedido e abre a solicitacao de aprovacao no ticket.
    *
    * @param string[] $urls
    * @return array{ok:bool, request_id:int, message:string}
    */
   public static function create(array $urls, int $ticketId, int $approverId): array
   {
      global $DB;

      $config = Config::getConfig();
      if ((string)($config['selfservice_enabled'] ?? '0') !== '1') {
         throw new \RuntimeException('O fluxo self-service de bloqueio esta desativado na configuracao.');
      }
      if (!Config::isConfigured($config)) {
         throw new \RuntimeException('Integracao Zscaler nao configurada.');
      }

      $urls = self::normalizeUrls($urls);
      if ($urls === []) {
         throw new \RuntimeException('Informe ao menos uma URL valida.');
      }
      if ($ticketId <= 0) {
         throw new \RuntimeException('Pedido precisa estar vinculado a um ticket.');
      }
      if ($approverId <= 0) {
         throw new \RuntimeException('Selecione um aprovador.');
      }
      if (!$DB->tableExists(self::TABLE)) {
         throw new \RuntimeException('Tabela de pedidos indisponivel.');
      }

      $now = date('Y-m-d H:i:s');
      $DB->insert(self::TABLE, [
         'urls'          => json_encode($urls, JSON_UNESCAPED_SLASHES),
         'tickets_id'    => $ticketId,
         'status'        => 'pending_approval',
         'requested_by'  => self::currentUserId(),
         'message'       => count($urls) . ' URL(s) aguardando aprovacao.',
         'date_creation' => $now,
         'date_mod'      => $now,
      ]);
      $requestId = (int)$DB->insertId();

      self::openValidation($ticketId, $approverId, $urls);

      self::followup(
         $ticketId,
         'info',
         '⏳ Bloqueio Zscaler aguardando aprovacao',
         'Foi solicitado o bloqueio de ' . count($urls) . ' URL(s) na denylist do Zscaler. '
         . 'A execucao ocorrera automaticamente apos a <strong>aprovacao</strong> da solicitacao de validacao deste chamado.',
         self::urlListHtml($urls)
      );

      return ['ok' => true, 'request_id' => $requestId, 'message' => 'Pedido de bloqueio criado e aguardando aprovacao.'];
   }

   /**
    * Hook de TicketValidation (add/update). Executa ou cancela o pedido pendente.
    */
   public static function onValidationUpdate($validation): void
   {
      global $DB;

      if (!($validation instanceof \TicketValidation)) {
         return;
      }

      $ticketId = (int)($validation->fields['tickets_id'] ?? 0);
      $status   = (int)($validation->fields['status'] ?? 0);
      if ($ticketId <= 0 || !$DB->tableExists(self::TABLE)) {
         return;
      }

      if (!in_array($status, [\CommonITILValidation::ACCEPTED, \CommonITILValidation::REFUSED], true)) {
         return;
      }

      $request = null;
      foreach ($DB->request([
         'FROM'  => self::TABLE,
         'WHERE' => ['tickets_id' => $ticketId, 'status' => 'pending_approval'],
         'ORDER' => ['id ASC'],
         'LIMIT' => 1,
      ]) as $row) {
         $request = $row;
      }
      if ($request === null) {
         return;
      }

      if ($status === \CommonITILValidation::ACCEPTED) {
         $approver = (int)($validation->fields['users_id_validate'] ?? 0);
         if ($approver <= 0) {
            $approver = (int)($validation->fields['items_id_target'] ?? 0);
         }
         self::execute((int)$request['id'], $approver);
      } else {
         self::reject((int)$request['id'], (string)($validation->fields['comment_validation'] ?? ''));
      }
   }

   /**
    * Executa o bloqueio aprovado direto na console (sem depender do direito do aprovador).
    */
   public static function execute(int $requestId, int $approverId): array
   {
      global $DB;

      $request = self::load($requestId);
      if ($request === null || $request['status'] !== 'pending_approval') {
         return ['ok' => false, 'message' => 'Pedido ja processado ou inexistente.'];
      }

      $ticketId = (int)($request['tickets_id'] ?? 0);
      $urls = self::decodeUrls($request['urls'] ?? '');
      $config = Config::getConfig();

      if (!Config::isConfigured($config) || (string)($config['readonly_mode'] ?? '1') === '1') {
         self::setStatus($requestId, 'failed', 'Integracao nao configurada ou em modo somente leitura.');
         self::followup($ticketId, 'danger', '❌ Bloqueio nao executado',
            'A aprovacao foi aceita, mas a integracao Zscaler esta em modo somente leitura ou nao configurada. Nenhum bloqueio foi aplicado.');
         return ['ok' => false, 'message' => 'Integracao em somente leitura.'];
      }

      try {
         $client = ApiClient::fromConfig($config);
         $client->addToDenylist($urls);
         if ((string)($config['auto_activate'] ?? '1') === '1') {
            $client->activateChanges();
         }
         self::cacheDenylist($urls);
      } catch (\Throwable $error) {
         self::setStatus($requestId, 'failed', $error->getMessage());
         ActionLog::record([
            'action'          => 'denylist_add',
            'target'          => self::summarize($urls),
            'status'          => 'error',
            'message'         => 'Self-service aprovado, mas falhou: ' . $error->getMessage(),
            'users_id'        => $approverId ?: null,
            'tickets_id'      => $ticketId ?: null,
            'source_itemtype' => 'Ticket',
            'source_items_id' => $ticketId ?: null,
         ]);
         Log::record('denylist_add', 'error', $error->getMessage());
         self::followup($ticketId, 'danger', '❌ Falha ao aplicar bloqueio',
            'A aprovacao foi aceita, mas a chamada a API Zscaler falhou.', self::esc($error->getMessage()));
         return ['ok' => false, 'message' => $error->getMessage()];
      }

      $message = count($urls) . ' URL(s) bloqueada(s) na console Zscaler apos aprovacao.';
      self::setStatus($requestId, 'approved', $message, $approverId);

      ActionLog::record([
         'action'          => 'denylist_add',
         'target'          => self::summarize($urls),
         'status'          => 'ok',
         'message'         => $message,
         'users_id'        => $approverId ?: null,
         'tickets_id'      => $ticketId ?: null,
         'source_itemtype' => 'Ticket',
         'source_items_id' => $ticketId ?: null,
      ]);
      Log::record('denylist_add', 'ok', $message, count($urls));

      self::followup($ticketId, 'success', '✅ Bloqueio aplicado na Zscaler',
         'A aprovacao foi aceita e o bloqueio foi aplicado na denylist do Zscaler Internet Access.',
         self::urlListHtml($urls));

      return ['ok' => true, 'message' => $message];
   }

   private static function reject(int $requestId, string $comment): void
   {
      $request = self::load($requestId);
      if ($request === null || $request['status'] !== 'pending_approval') {
         return;
      }

      self::setStatus($requestId, 'rejected', 'Aprovacao recusada.');

      $ticketId = (int)($request['tickets_id'] ?? 0);
      $extra = trim($comment) !== '' ? ('<strong>Motivo:</strong> ' . self::esc($comment)) : '';
      self::followup($ticketId, 'danger', '❌ Bloqueio Zscaler recusado',
         'A solicitacao de aprovacao foi recusada. Nenhuma URL foi bloqueada.', $extra);
   }

   // ---------------------------------------------------------------------

   private static function openValidation(int $ticketId, int $approverId, array $urls): void
   {
      global $DB;

      if (!class_exists(\TicketValidation::class)) {
         throw new \RuntimeException('Recurso de validacao (aprovacao) indisponivel neste GLPI.');
      }

      $comment = 'Solicitacao de bloqueio Zscaler: ' . self::summarize($urls);
      $input = [
         'tickets_id'         => $ticketId,
         'comment_submission' => $comment,
         'status'             => \CommonITILValidation::WAITING,
      ];

      // Compatibilidade: GLPI 11 usa itemtype_target/items_id_target; versoes
      // anteriores usam users_id_validate.
      if ($DB->fieldExists('glpi_ticketvalidations', 'items_id_target')) {
         $input['itemtype_target'] = 'User';
         $input['items_id_target'] = $approverId;
      } else {
         $input['users_id_validate'] = $approverId;
      }

      $validation = new \TicketValidation();
      if (!$validation->add($input)) {
         throw new \RuntimeException('Nao foi possivel abrir a solicitacao de aprovacao no ticket.');
      }
   }

   private static function load(int $requestId): ?array
   {
      global $DB;

      if ($requestId <= 0 || !$DB->tableExists(self::TABLE)) {
         return null;
      }

      foreach ($DB->request(['FROM' => self::TABLE, 'WHERE' => ['id' => $requestId], 'LIMIT' => 1]) as $row) {
         return $row;
      }

      return null;
   }

   private static function setStatus(int $requestId, string $status, string $message, int $approverId = 0): void
   {
      global $DB;

      $data = ['status' => $status, 'message' => $message, 'date_mod' => date('Y-m-d H:i:s')];
      if ($approverId > 0) {
         $data['approved_by'] = $approverId;
      }
      $DB->update(self::TABLE, $data, ['id' => $requestId]);
   }

   private static function cacheDenylist(array $urls): void
   {
      global $DB;

      $table = DenylistEntry::getTable();
      if (!$DB->tableExists($table)) {
         return;
      }

      $now = date('Y-m-d H:i:s');
      foreach ($urls as $url) {
         $url = mb_substr($url, 0, 255);
         $exists = $DB->request([
            'SELECT' => ['id'], 'FROM' => $table, 'WHERE' => ['url' => $url], 'LIMIT' => 1,
         ])->current();
         if (!$exists) {
            $DB->insert($table, ['url' => $url, 'source' => 'zia', 'date_creation' => $now, 'date_mod' => $now]);
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
    * @return string[]
    */
   private static function decodeUrls(string $json): array
   {
      $decoded = json_decode($json, true);

      return is_array($decoded) ? array_values(array_map('strval', $decoded)) : [];
   }

   private static function summarize(array $urls): string
   {
      $first = (string)($urls[0] ?? '');
      $extra = count($urls) - 1;

      return $extra > 0 ? ($first . ' (+' . $extra . ')') : $first;
   }

   private static function urlListHtml(array $urls): string
   {
      $items = '';
      foreach (array_slice($urls, 0, 50) as $url) {
         $items .= '<li>' . self::esc($url) . '</li>';
      }

      return $items !== '' ? '<ul style="margin:0;padding-left:18px">' . $items . '</ul>' : '';
   }

   private static function followup(int $ticketId, string $type, string $title, string $body, string $extra = ''): void
   {
      if ($ticketId <= 0 || !class_exists(\ITILFollowup::class)) {
         return;
      }

      $styles = [
         'success' => ['#38a169', '#f0fff4', '#276749'],
         'danger'  => ['#e53e3e', '#fff5f5', '#c53030'],
         'info'    => ['#0648A8', '#eef5ff', '#0a1e3f'],
      ];
      [$border, $bg, $titleColor] = $styles[$type] ?? $styles['info'];
      $now = date('d/m/Y H:i');

      $html = "<div style='border-left:4px solid {$border};background:{$bg};padding:16px 20px;border-radius:8px;font-family:-apple-system,sans-serif'>"
         . "<div style='font-weight:700;color:{$titleColor};margin-bottom:8px'>" . $title . "</div>"
         . "<div style='color:#4a5568;font-size:.9rem;line-height:1.6'>" . $body . "</div>"
         . ($extra !== '' ? "<div style='margin-top:10px;padding:8px 12px;background:rgba(0,0,0,.04);border-radius:6px;font-size:.85rem;color:#4a5568'>" . $extra . "</div>" : '')
         . "<div style='margin-top:10px;font-size:.75rem;color:#a0aec0'>🤖 Plugin Zscaler &middot; {$now}</div>"
         . "</div>";

      $followup = new \ITILFollowup();
      $followup->add([
         'itemtype'   => 'Ticket',
         'items_id'   => $ticketId,
         'content'    => $html,
         'is_private' => 0,
      ]);
   }

   private static function currentUserId(): ?int
   {
      $id = (int)($_SESSION['glpiID'] ?? 0);

      return $id > 0 ? $id : null;
   }

   private static function esc(string $value): string
   {
      return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
   }
}
