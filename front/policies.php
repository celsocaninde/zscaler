<?php

use GlpiPlugin\Zscaler\Actions;
use GlpiPlugin\Zscaler\ApiClient;
use GlpiPlugin\Zscaler\Config;
use GlpiPlugin\Zscaler\Menu;
use GlpiPlugin\Zscaler\Profile;

include('../../../inc/includes.php');

\Session::checkRight(Profile::RIGHT_READ, READ);

$h = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$root = Menu::rootDoc();
$config = Config::getConfig();
$configured = Config::isConfigured($config);
$actionsOn = Config::actionsEnabled($config);

$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   $do = (string)($_POST['do'] ?? '');
   $ruleId = (int)($_POST['rule_id'] ?? 0);
   $ruleName = (string)($_POST['rule_name'] ?? '');
   try {
      if ($do === 'enable' || $do === 'disable') {
         $result = Actions::setUrlFilteringRuleState($ruleId, $do === 'enable', $ruleName);
         $flash = ['type' => 'ok', 'text' => $result['message']];
      }
   } catch (\Throwable $error) {
      $flash = ['type' => 'error', 'text' => $error->getMessage()];
   }
}

$rules = [];
$loadError = null;
if ($configured) {
   try {
      $rules = ApiClient::fromConfig($config)->getUrlFilteringRules();
   } catch (\Throwable $error) {
      $loadError = $error->getMessage();
   }
}

\Html::header('Zscaler - Politicas', $_SERVER['PHP_SELF'], 'plugins', 'zscaler');
echo "<style>.container-xl,.container-lg{max-width:100%!important}</style>";

echo "<div class='zscaler-dashboard'>";
echo "<div class='zscaler-dashboard__hero'>";
echo "<div class='zs-hero__brand'>";
echo "<span class='zs-logo'><span class='ti ti-shield-cog'></span></span>";
echo "<div>";
echo "<div class='zscaler-dashboard__eyebrow'>Zscaler &middot; Internet Access</div>";
echo "<h2>Politicas de URL Filtering</h2>";
echo "<p>Visualize as regras e ligue/desligue diretamente do GLPI.</p>";
echo "</div>";
echo "</div>";
echo "<div class='zscaler-hero__actions'>";
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
} elseif (!$actionsOn) {
   echo "<div class='zscaler-banner zscaler-banner--info'><span class='ti ti-eye'></span><div><strong>Modo somente leitura.</strong> Voce pode ver as regras; ligar/desligar exige liberar as acoes na configuracao.</div></div>";
}
if ($loadError !== null) {
   echo "<div class='zscaler-banner zscaler-banner--error'><span class='ti ti-alert-triangle'></span><div>" . $h($loadError) . "</div></div>";
}

echo "<section class='zscaler-panel zscaler-panel--wide'>";
echo "<div class='zscaler-panel__head'><div class='zscaler-panel__title'><span class='zscaler-panel__icon ti ti-shield-cog'></span><div><h3>Regras de URL Filtering</h3></div></div></div>";
echo "<div class='zscaler-panel__body'>";
if ($rules !== []) {
   echo "<div class='table-responsive'><table class='table table-vcenter table-hover mb-0'>";
   echo "<thead><tr><th>Ordem</th><th>Nome</th><th>Acao</th><th>Estado</th><th></th></tr></thead><tbody>";
   foreach ($rules as $rule) {
      $id = (int)($rule['id'] ?? 0);
      $name = (string)($rule['name'] ?? ('#' . $id));
      $state = strtoupper((string)($rule['state'] ?? ''));
      $ruleAction = (string)($rule['action'] ?? '');
      $order = (string)($rule['order'] ?? $rule['rank'] ?? '');
      $isEnabled = $state === 'ENABLED';
      $badge = $isEnabled ? 'zs-badge--ok' : 'zs-badge--error';
      echo "<tr>";
      echo "<td>" . $h($order !== '' ? $order : '-') . "</td>";
      echo "<td>" . $h($name) . "</td>";
      echo "<td>" . $h($ruleAction !== '' ? $ruleAction : '-') . "</td>";
      echo "<td><span class='zs-badge {$badge}'>" . $h($state !== '' ? $state : '-') . "</span></td>";
      echo "<td>";
      if ($actionsOn && $id > 0) {
         $do = $isEnabled ? 'disable' : 'enable';
         $btnLabel = $isEnabled ? 'Desativar' : 'Ativar';
         $btnClass = $isEnabled ? 'btn-outline-danger' : 'btn-outline-success';
         echo "<form method='post' action='" . $h($_SERVER['PHP_SELF']) . "' style='display:inline'>";
         echo \Html::hidden('rule_id', ['value' => $id]);
         echo \Html::hidden('rule_name', ['value' => $name]);
         echo "<button class='btn btn-sm {$btnClass}' type='submit' name='do' value='" . $h($do) . "' onclick=\"return confirm('Confirmar " . $h($btnLabel) . " a regra?');\">" . $h($btnLabel) . "</button>";
         \Html::closeForm();
      } else {
         echo "<span class='text-muted'>-</span>";
      }
      echo "</td>";
      echo "</tr>";
   }
   echo "</tbody></table></div>";
} elseif ($configured && $loadError === null) {
   echo "<p class='text-muted mb-0'>Nenhuma regra de URL Filtering encontrada.</p>";
}
echo "</div></section>";
echo "</div>";

\Html::footer();
