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
   $urls = preg_split('/[\s,;]+/', (string)($_POST['urls'] ?? '')) ?: [];
   $urls = array_values(array_filter(array_map('trim', $urls), static fn($u): bool => $u !== ''));
   try {
      $result = match ($do) {
         'deny_add'    => Actions::blockUrls($urls),
         'deny_remove' => Actions::unblockUrls($urls),
         'allow_add'   => Actions::allowlistUrls($urls),
         'allow_remove' => Actions::removeAllowlistUrls($urls),
         default       => throw new \RuntimeException('Acao desconhecida.'),
      };
      $flash = ['type' => 'ok', 'text' => $result['message']];
   } catch (\Throwable $error) {
      $flash = ['type' => 'error', 'text' => $error->getMessage()];
   }
}

$denylist = [];
$allowlist = [];
$loadError = null;
if ($configured) {
   try {
      $client = ApiClient::fromConfig($config);
      $denylist = $client->getDenylist();
      $allowlist = $client->getSecurityAllowlist();
   } catch (\Throwable $error) {
      $loadError = $error->getMessage();
   }
}

\Html::header('Zscaler - Seguranca', $_SERVER['PHP_SELF'], 'plugins', 'zscaler');
echo "<style>.container-xl,.container-lg{max-width:100%!important}</style>";

echo "<div class='zscaler-dashboard'>";
echo "<div class='zscaler-dashboard__hero'>";
echo "<div class='zs-hero__brand'>";
echo "<span class='zs-logo'><span class='ti ti-shield-lock'></span></span>";
echo "<div>";
echo "<div class='zscaler-dashboard__eyebrow'>Zscaler &middot; Advanced Threat Protection</div>";
echo "<h2>Seguranca: denylist (ATP) e allowlist</h2>";
echo "<p>Bloqueie URLs maliciosas na protecao avancada ou libere URLs confiaveis (bypass).</p>";
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
   echo "<div class='zscaler-banner zscaler-banner--info'><span class='ti ti-eye'></span><div><strong>Modo somente leitura.</strong> Voce pode ver as listas; adicionar/remover exige liberar as acoes na configuracao.</div></div>";
}
if ($loadError !== null) {
   echo "<div class='zscaler-banner zscaler-banner--error'><span class='ti ti-alert-triangle'></span><div>" . $h($loadError) . "</div></div>";
}

// ---- Denylist ATP (URLs maliciosas) ----
$panels = [
   [
      'title'   => 'Denylist ATP (URLs maliciosas)',
      'icon'    => 'ti-ban',
      'desc'    => 'URLs bloqueadas na Advanced Threat Protection. Adicione dominios maliciosos identificados em incidentes.',
      'list'    => $denylist,
      'add'     => 'deny_add',
      'remove'  => 'deny_remove',
      'addlbl'  => 'Bloquear URLs',
      'empty'   => 'Nenhuma URL na denylist ATP.',
      'badge'   => 'zs-badge--error',
   ],
   [
      'title'   => 'Allowlist (bypass)',
      'icon'    => 'ti-shield-check',
      'desc'    => 'URLs confiaveis que ignoram a politica de seguranca. Use com cautela.',
      'list'    => $allowlist,
      'add'     => 'allow_add',
      'remove'  => 'allow_remove',
      'addlbl'  => 'Liberar URLs',
      'empty'   => 'Nenhuma URL na allowlist.',
      'badge'   => 'zs-badge--ok',
   ],
];

foreach ($panels as $panel) {
   echo "<section class='zscaler-panel zscaler-panel--wide'>";
   echo "<div class='zscaler-panel__head'><div class='zscaler-panel__title'><span class='zscaler-panel__icon ti " . $h($panel['icon']) . "'></span><div><h3>" . $h($panel['title']) . "</h3><p>" . $h($panel['desc']) . "</p></div></div></div>";
   echo "<div class='zscaler-panel__body'>";

   if ($actionsOn) {
      echo "<form method='post' action='" . $h($_SERVER['PHP_SELF']) . "' class='mb-3'>";
      echo "<textarea class='form-control mb-2' name='urls' rows='2' placeholder='Uma URL por linha (ou separadas por virgula)'></textarea>";
      echo "<button class='btn btn-sm btn-primary' type='submit' name='do' value='" . $h($panel['add']) . "'><span class='ti ti-plus'></span> " . $h($panel['addlbl']) . "</button>";
      \Html::closeForm();
   }

   if ($panel['list'] !== []) {
      echo "<div class='table-responsive'><table class='table table-vcenter table-hover mb-0'>";
      echo "<thead><tr><th>URL</th><th></th></tr></thead><tbody>";
      foreach ($panel['list'] as $url) {
         $url = (string)$url;
         echo "<tr>";
         echo "<td><span class='zs-badge {$panel['badge']}'></span> " . $h($url) . "</td>";
         echo "<td class='text-end'>";
         if ($actionsOn) {
            echo "<form method='post' action='" . $h($_SERVER['PHP_SELF']) . "' style='display:inline'>";
            echo \Html::hidden('urls', ['value' => $url]);
            echo "<button class='btn btn-sm btn-outline-danger' type='submit' name='do' value='" . $h($panel['remove']) . "' onclick=\"return confirm('Remover esta URL da lista?');\"><span class='ti ti-trash'></span></button>";
            \Html::closeForm();
         } else {
            echo "<span class='text-muted'>-</span>";
         }
         echo "</td>";
         echo "</tr>";
      }
      echo "</tbody></table></div>";
   } elseif ($configured && $loadError === null) {
      echo "<p class='text-muted mb-0'>" . $h($panel['empty']) . "</p>";
   }

   echo "</div></section>";
}

echo "</div>";

\Html::footer();
