<?php

use GlpiPlugin\Zscaler\Menu;
use GlpiPlugin\Zscaler\Profile;
use GlpiPlugin\Zscaler\ZccDevice;

include('../../../inc/includes.php');

\Session::checkRight(Profile::RIGHT_READ, READ);

global $DB;
$h = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$root = Menu::rootDoc();

// Conjunto de computadores com dispositivo ZCC vinculado
$matched = [];
if ($DB->tableExists(ZccDevice::getTable())) {
   foreach ($DB->request([
      'SELECT'   => ['computers_id'],
      'DISTINCT' => true,
      'FROM'     => ZccDevice::getTable(),
      'WHERE'    => ['NOT' => ['computers_id' => null]],
   ]) as $row) {
      $matched[(int)$row['computers_id']] = true;
   }
}

$rows = [];
foreach ($DB->request([
   'SELECT' => ['id', 'name', 'serial', 'entities_id'],
   'FROM'   => 'glpi_computers',
   'WHERE'  => ['is_deleted' => 0],
   'ORDER'  => ['name ASC'],
   'LIMIT'  => 500,
]) as $row) {
   if (!isset($matched[(int)$row['id']])) {
      $rows[] = $row;
   }
}

\Html::header('Computadores sem ZCC', $_SERVER['PHP_SELF'], 'plugins', 'zscaler');

echo "<div class='zscaler-dashboard'>";
echo "<div class='zscaler-dashboard__hero'>";
echo "<div class='zs-hero__brand'>";
echo "<span class='zs-logo'><span class='ti ti-shield-off'></span></span>";
echo "<div>";
echo "<div class='zscaler-dashboard__eyebrow'>Zscaler &middot; Client Connector</div>";
echo "<h2>Computadores sem o Zscaler</h2>";
echo "<p>Ativos do GLPI sem um dispositivo do Client Connector vinculado.</p>";
echo "</div>";
echo "</div>";
echo "<div class='zscaler-hero__actions'>";
echo "<a class='btn btn-light' href='" . $h($root . '/plugins/zscaler/front/zccdevice.php') . "'><span class='ti ti-device-laptop'></span> Dispositivos ZCC</a>";
echo "</div>";
echo "</div>";

if (!ZccDevice::countUnprotectedComputers() && $rows === []) {
   echo "<div class='zscaler-banner zscaler-banner--ok'><span class='ti ti-circle-check'></span><div>Todos os computadores possuem ZCC vinculado (ou ainda nao ha sincronizacao).</div></div>";
}

echo "<section class='zscaler-panel zscaler-panel--wide'>";
echo "<div class='zscaler-panel__head'><div class='zscaler-panel__title'><span class='zscaler-panel__icon ti ti-shield-off'></span><div><h3>" . count($rows) . " computador(es) sem ZCC</h3><p>Limitado aos 500 primeiros.</p></div></div></div>";
echo "<div class='zscaler-panel__body'>";
if ($rows !== []) {
   echo "<div class='table-responsive'><table class='table table-vcenter table-hover mb-0'>";
   echo "<thead><tr><th>Computador</th><th>Serial</th><th></th></tr></thead><tbody>";
   foreach ($rows as $row) {
      $name = (string)($row['name'] ?? '') !== '' ? (string)$row['name'] : ('#' . (int)$row['id']);
      echo "<tr>";
      echo "<td><a href='" . $h($root . '/front/computer.form.php?id=' . (int)$row['id']) . "'>" . $h($name) . "</a></td>";
      echo "<td>" . $h((string)($row['serial'] ?? '-')) . "</td>";
      echo "<td><a class='btn btn-sm btn-outline-secondary' href='" . $h($root . '/front/computer.form.php?id=' . (int)$row['id'] . '&forcetab=' . urlencode('GlpiPlugin\\Zscaler\\ItemAction$1')) . "'>Zscaler</a></td>";
      echo "</tr>";
   }
   echo "</tbody></table></div>";
} else {
   echo "<p class='text-muted mb-0'>Nenhum computador sem ZCC encontrado.</p>";
}
echo "</div></section>";
echo "</div>";

\Html::footer();
