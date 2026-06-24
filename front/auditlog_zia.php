<?php

use GlpiPlugin\Zscaler\AuditEntry;
use GlpiPlugin\Zscaler\Config;
use GlpiPlugin\Zscaler\Menu;
use GlpiPlugin\Zscaler\Profile;
use GlpiPlugin\Zscaler\Sync;

include('../../../inc/includes.php');

\Session::checkRight(Profile::RIGHT_READ, READ);

$h = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$root = Menu::rootDoc();
$config = Config::getConfig();
$configured = Config::isConfigured($config);

$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['do'] ?? '') === 'sync') {
   $days = (int)($_POST['days'] ?? 0);
   try {
      $result = Sync::syncAuditLog($days > 0 ? $days : null);
      $flash = ['type' => 'ok', 'text' => $result['processed'] . ' registro(s) lido(s), ' . $result['inserted'] . ' novo(s) importado(s), ' . ($result['tickets'] ?? 0) . ' ticket(s) criado(s).'];
   } catch (\Throwable $error) {
      $flash = ['type' => 'error', 'text' => $error->getMessage()];
   }
}

$entries = AuditEntry::getRecent(200);
$total = AuditEntry::countAll();
$days = max(1, min(180, (int)($config['audit_days'] ?? 7)));

\Html::header('Zscaler - Auditoria', $_SERVER['PHP_SELF'], 'plugins', 'zscaler');
echo "<style>.container-xl,.container-lg{max-width:100%!important}</style>";

echo "<div class='zscaler-dashboard'>";
echo "<div class='zscaler-dashboard__hero'>";
echo "<div class='zs-hero__brand'>";
echo "<span class='zs-logo'><span class='ti ti-clipboard-list'></span></span>";
echo "<div>";
echo "<div class='zscaler-dashboard__eyebrow'>Zscaler &middot; Admin Audit Log</div>";
echo "<h2>Auditoria da console Zscaler</h2>";
echo "<p>Quem mudou o que na console ZIA. Importado do relatorio de auditoria (ultimos " . $h((string)$days) . " dias).</p>";
echo "</div>";
echo "</div>";
echo "<div class='zscaler-hero__actions'>";
if ($configured) {
   echo "<form method='post' action='" . $h($_SERVER['PHP_SELF']) . "' style='display:inline'>";
   echo \Html::hidden('days', ['value' => $days]);
   echo "<button class='btn btn-light' type='submit' name='do' value='sync'><span class='ti ti-refresh'></span> Atualizar agora</button>";
   \Html::closeForm();
}
echo "<a class='btn btn-light' href='" . $h($root . '/plugins/zscaler/front/overview.php') . "'><span class='ti ti-dashboard'></span> Visao geral</a>";
echo "</div>";
echo "</div>";

if ($flash !== null) {
   $cls = ['ok' => 'zscaler-banner--ok', 'error' => 'zscaler-banner--error'][$flash['type']] ?? 'zscaler-banner--info';
   $icon = ['ok' => 'ti-circle-check', 'error' => 'ti-alert-triangle'][$flash['type']] ?? 'ti-info-circle';
   echo "<div class='zscaler-banner {$cls}'><span class='ti {$icon}'></span><div>" . $h($flash['text']) . "</div></div>";
}
if (!$configured) {
   echo "<div class='zscaler-banner zscaler-banner--warn'><span class='ti ti-alert-triangle'></span><div>Integracao ZIA nao configurada.</div></div>";
} else {
   echo "<div class='zscaler-banner zscaler-banner--info'><span class='ti ti-info-circle'></span><div>O relatorio de auditoria e gerado sob demanda pela Zscaler; \"Atualizar agora\" pode levar alguns segundos.</div></div>";
}

echo "<section class='zscaler-panel zscaler-panel--wide'>";
echo "<div class='zscaler-panel__head'><div class='zscaler-panel__title'><span class='zscaler-panel__icon ti ti-clipboard-list'></span><div><h3>Registros de auditoria (" . $h((string)$total) . ")</h3></div></div></div>";
echo "<div class='zscaler-panel__body'>";

if ($entries !== []) {
   echo "<div class='table-responsive'><table class='table table-vcenter table-hover mb-0'>";
   echo "<thead><tr><th>Data</th><th>Administrador</th><th>Acao</th><th>Recurso</th><th>Resultado</th><th>IP</th><th>Ticket</th></tr></thead><tbody>";
   foreach ($entries as $row) {
      $result = strtolower((string)($row['result'] ?? ''));
      $badge = str_contains($result, 'fail') || str_contains($result, 'error') || str_contains($result, 'denied')
         ? 'zs-badge--error'
         : 'zs-badge--ok';
      $ticketId = (int)($row['tickets_id'] ?? 0);
      echo "<tr>";
      echo "<td>" . $h((string)($row['recorded_at'] ?? '-')) . "</td>";
      echo "<td>" . $h((string)($row['admin'] ?? '-')) . "</td>";
      echo "<td>" . $h((string)($row['action'] ?? '-')) . "</td>";
      echo "<td>" . $h((string)($row['resource'] ?? '-')) . "</td>";
      echo "<td><span class='zs-badge {$badge}'>" . $h((string)($row['result'] ?? '-')) . "</span></td>";
      echo "<td>" . $h((string)($row['client_ip'] ?? '-')) . "</td>";
      if ($ticketId > 0) {
         echo "<td><a href='" . $h($root . '/front/ticket.form.php?id=' . $ticketId) . "'>#" . $h((string)$ticketId) . "</a></td>";
      } else {
         echo "<td class='text-muted'>-</td>";
      }
      echo "</tr>";
   }
   echo "</tbody></table></div>";
} else {
   echo "<p class='text-muted mb-0'>Nenhum registro de auditoria importado ainda. Clique em \"Atualizar agora\".</p>";
}

echo "</div></section>";
echo "</div>";

\Html::footer();
