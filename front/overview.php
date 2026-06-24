<?php

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
$actionsOn = Config::actionsEnabled($config);
$stats = Sync::stats();

\Html::header('Zscaler', $_SERVER['PHP_SELF'], 'plugins', 'zscaler');
echo "<style>.container-xl,.container-lg{max-width:100%!important}</style>";

echo "<div class='zscaler-dashboard'>";

// ----- Hero (identidade da marca) -----
echo "<div class='zscaler-dashboard__hero'>";
echo "<div class='zs-hero__brand'>";
echo "<span class='zs-logo'><span class='ti ti-cloud-lock'></span></span>";
echo "<div>";
echo "<div class='zscaler-dashboard__eyebrow'>Zscaler &middot; Internet Access</div>";
echo "<h2>Visao geral</h2>";
echo "<p>Categorias de URL, denylist e acoes executadas pelo plugin.</p>";
echo "</div>";
echo "</div>";
echo "<div class='zscaler-hero__actions'>";
echo "<a class='btn btn-light' href='" . $h($root . '/plugins/zscaler/front/urllookup.php') . "'><span class='ti ti-search'></span> URL lookup</a>";
if (Profile::hasActionRight() || Profile::hasConfigReadRight()) {
   echo "<a class='btn btn-light' href='" . $h($root . '/plugins/zscaler/front/sync.php') . "'><span class='ti ti-refresh'></span> Sincronizar</a>";
}
echo "</div>";
echo "</div>";

// ----- Estado da integracao -----
if (!$configured) {
   echo "<div class='zscaler-banner zscaler-banner--warn'>";
   echo "<span class='ti ti-alert-triangle'></span>";
   echo "<div><strong>Integracao pendente.</strong> Configure as credenciais em <a href='" . $h($root . '/plugins/zscaler/front/config.form.php') . "'>Configuracao &gt; Zscaler</a> para sincronizar e executar acoes.</div>";
   echo "</div>";
} elseif (!$actionsOn) {
   echo "<div class='zscaler-banner zscaler-banner--info'>";
   echo "<span class='ti ti-eye'></span>";
   echo "<div><strong>Modo somente leitura.</strong> As acoes de escrita estao desligadas. Para bloquear/recategorizar URLs, ajuste as travas em <a href='" . $h($root . '/plugins/zscaler/front/config.form.php') . "'>Configuracao</a>.</div>";
   echo "</div>";
}

// ----- Cards de KPI -----
$cards = [
   ['ti-category', $stats['categories_total'], 'Categorias de URL', $root . '/plugins/zscaler/front/urlcategory.php'],
   ['ti-ban', $stats['denylist_total'], 'URLs bloqueadas', $root . '/plugins/zscaler/front/denylist.php'],
   ['ti-bolt', $stats['actions_total'], 'Acoes executadas', $root . '/plugins/zscaler/front/actionlog.php'],
   ['ti-shield-off', $stats['zcc_unprotected'], 'Computadores sem ZCC', $root . '/plugins/zscaler/front/unprotected.php'],
   ['ti-activity-heartbeat', $stats['zdx_ongoing'], 'Alertas ZDX abertos', $root . '/plugins/zscaler/front/zdxalert.php'],
];

echo "<div class='zscaler-cards'>";
foreach ($cards as [$icon, $value, $label, $url]) {
   echo "<a class='zscaler-card' href='" . $h($url) . "'>";
   echo "<span class='zscaler-card__icon ti " . $h($icon) . "'></span>";
   echo "<span class='zscaler-card__value'>" . $h((string)(int)$value) . "</span>";
   echo "<span class='zscaler-card__label'>" . $h($label) . "</span>";
   echo "</a>";
}
echo "</div>";

// ----- Duas colunas: acoes recentes + categorias customizadas -----
echo "<div class='zscaler-grid-2'>";

echo "<section class='zscaler-panel'>";
echo "<div class='zscaler-panel__head'><div class='zscaler-panel__title'><span class='zscaler-panel__icon ti ti-history'></span><div><h3>Acoes recentes</h3></div></div></div>";
echo "<div class='zscaler-panel__body'>";
if (!empty($stats['recent_actions'])) {
   echo "<div class='table-responsive'><table class='table table-vcenter table-hover mb-0'>";
   echo "<thead><tr><th>Acao</th><th>Alvo</th><th>Status</th><th>Data</th></tr></thead><tbody>";
   foreach ($stats['recent_actions'] as $row) {
      $badge = (string)$row['status'] === 'error' ? 'zs-badge--error' : 'zs-badge--ok';
      echo "<tr>";
      echo "<td>" . $h((string)$row['action']) . "</td>";
      echo "<td class='zscaler-break'>" . $h((string)($row['target'] ?? '-')) . "</td>";
      echo "<td><span class='zs-badge {$badge}'>" . $h((string)$row['status']) . "</span></td>";
      echo "<td>" . $h((string)($row['date_creation'] ?? '')) . "</td>";
      echo "</tr>";
   }
   echo "</tbody></table></div>";
} else {
   echo "<p class='text-muted mb-0'>Nenhuma acao registrada ainda.</p>";
}
echo "</div></section>";

echo "<section class='zscaler-panel'>";
echo "<div class='zscaler-panel__head'><div class='zscaler-panel__title'><span class='zscaler-panel__icon ti ti-category'></span><div><h3>Categorias customizadas</h3></div></div></div>";
echo "<div class='zscaler-panel__body'>";
if (!empty($stats['recent_categories'])) {
   echo "<div class='table-responsive'><table class='table table-vcenter table-hover mb-0'>";
   echo "<thead><tr><th>Nome</th><th>URLs</th><th>Atualizada</th></tr></thead><tbody>";
   foreach ($stats['recent_categories'] as $row) {
      echo "<tr>";
      echo "<td>" . $h((string)($row['name'] ?? $row['zscaler_id'])) . "</td>";
      echo "<td>" . $h((string)(int)($row['urls_count'] ?? 0)) . "</td>";
      echo "<td>" . $h((string)($row['date_mod'] ?? '')) . "</td>";
      echo "</tr>";
   }
   echo "</tbody></table></div>";
} else {
   echo "<p class='text-muted mb-0'>Nenhuma categoria sincronizada. Use <strong>Sincronizar</strong>.</p>";
}
echo "</div></section>";

echo "</div>"; // grid-2

// ----- Rodape: ultima sincronizacao -----
$lastSync = $stats['last_sync'] ?? null;
echo "<div class='zscaler-footnote'>";
echo "<span class='ti ti-clock'></span> ";
if (is_array($lastSync)) {
   echo "Ultima sincronizacao: <strong>" . $h((string)($lastSync['date_creation'] ?? '-')) . "</strong> &middot; " . $h((string)($lastSync['status'] ?? '')) . " &middot; " . $h((string)($lastSync['message'] ?? ''));
} else {
   echo "Nenhuma sincronizacao executada ainda.";
}
echo "</div>";

echo "</div>"; // dashboard

\Html::footer();
