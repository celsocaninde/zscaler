<?php

use GlpiPlugin\Zscaler\Actions;
use GlpiPlugin\Zscaler\BlockRequest;
use GlpiPlugin\Zscaler\Profile;

include('../../../inc/includes.php');

\Session::checkRight(Profile::RIGHT_READ, READ);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
   \Html::back();
}

$do = (string)($_POST['do'] ?? 'block');
$rawUrls = (string)($_POST['urls'] ?? '');
$urls = array_values(array_filter(array_map('trim', preg_split('/[\r\n,;\s]+/', $rawUrls) ?: []), static fn($u): bool => $u !== ''));

$source = [
   'itemtype'   => trim((string)($_POST['source_itemtype'] ?? '')) ?: null,
   'items_id'   => (int)($_POST['source_items_id'] ?? 0) ?: null,
   'tickets_id' => (int)($_POST['source_tickets_id'] ?? 0) ?: null,
];

try {
   if ($urls === []) {
      throw new \RuntimeException('Informe ao menos uma URL.');
   }

   if ($do === 'request_block') {
      // Self-service: cria pedido + solicitacao de aprovacao (nao executa agora).
      $ticketId = (int)($_POST['source_tickets_id'] ?? 0);
      $approver = (int)($_POST['approver_id'] ?? 0);
      $result = BlockRequest::create($urls, $ticketId, $approver);
      \Session::addMessageAfterRedirect($result['message']);
   } else {
      $result = $do === 'unblock'
         ? Actions::unblockUrls($urls, $source)
         : Actions::blockUrls($urls, $source);

      $msg = $result['message'];
      if (!empty($result['ticket_id'])) {
         $msg .= ' Ticket #' . (int)$result['ticket_id'] . '.';
      }

      \Session::addMessageAfterRedirect($msg);
   }
} catch (\Throwable $error) {
   \Session::addMessageAfterRedirect('Erro na acao Zscaler: ' . $error->getMessage(), false, ERROR);
}

\Html::back();
