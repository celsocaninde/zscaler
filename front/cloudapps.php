<?php

use GlpiPlugin\Zscaler\CloudApp;
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
   try {
      $result = Sync::syncCloudApps();
      $flash = ['type' => 'ok', 'text' => $result['processed'] . ' app(s) sincronizado(s), ' . $result['tickets'] . ' ticket(s) criado(s).'];
   } catch (\Throwable $error) {
      $flash = ['type' => 'error', 'text' => $error->getMessage()];
   }
}

$apps = CloudApp::getRecent(200);
$total = CloudApp::countAll();
$risky = CloudApp::countRisky();

\Html::header('Zscaler - Shadow IT', $_SERVER['PHP_SELF'], 'plugins', 'zscaler');
echo "<style>.container-xl,.container-lg{max-width:100%!important}</style>";

echo "<div class='zscaler-dashboard'>";
echo "<div class='zscaler-dashboard__hero'>";
echo "<div class='zs-hero__brand'>";
echo "<span class='zs-logo'><span class='ti ti-cloud-data-connection'></span></span>";
echo "<div>";
echo "<div class='zscaler-dashboard__eyebrow'>Zscaler &middot; Cloud App Control</div>";
echo "<h2>Shadow IT - Aplicacoes de nuvem</h2>";
echo "<p>Aplicacoes descobertas pelo Zscaler. Apps de risco podem abrir tickets automaticamente.</p>";
echo "</div>";
echo "</div>";
echo "<div class='zscaler-hero__actions'>";
if ($configured) {
   echo "<form method='post' action='" . $h($_SERVER['PHP_SELF']) . "' style='display:inline'>";
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
}

echo "<section class='zscaler-panel zscaler-panel--wide'>";
echo "<div class='zscaler-panel__head'><div class='zscaler-panel__title'><span class='zscaler-panel__icon ti ti-cloud-data-connection'></span><div><h3>Aplicacoes (" . $h((string)$total) . ") &middot; de risco: " . $h((string)$risky) . "</h3></div></div></div>";
echo "<div class='zscaler-panel__body'>";

if ($apps !== []) {
   echo "<div class='table-responsive'><table class='table table-vcenter table-hover mb-0'>";
   echo "<thead><tr><th>Aplicacao</th><th>Categoria</th><th>Risco</th><th>Sancionada</th><th>Ticket</th></tr></thead><tbody>";
   foreach ($apps as $app) {
      [$riskLabel, $riskClass] = CloudApp::riskBadge($app['risk_index'] ?? null);
      $ticketId = (int)($app['tickets_id'] ?? 0);
      echo "<tr>";
      echo "<td>" . $h((string)($app['name'] ?? '-')) . "</td>";
      echo "<td>" . $h((string)($app['category'] ?? '-')) . "</td>";
      echo "<td><span class='zs-badge {$riskClass}'>" . $h($riskLabel) . "</span></td>";
      echo "<td>" . $h((string)($app['sanctioned'] ?? '-')) . "</td>";
      if ($ticketId > 0) {
         echo "<td><a href='" . $h($root . '/front/ticket.form.php?id=' . $ticketId) . "'>#" . $h((string)$ticketId) . "</a></td>";
      } else {
         echo "<td class='text-muted'>-</td>";
      }
      echo "</tr>";
   }
   echo "</tbody></table></div>";
} else {
   echo "<p class='text-muted mb-0'>Nenhuma aplicacao sincronizada ainda. Clique em \"Atualizar agora\".</p>";
}

echo "</div></section>";
echo "</div>";

\Html::footer();
