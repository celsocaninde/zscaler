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

/** @var array{type:string,text:string}|null $flash */
$flash = null;
/** @var array<int,array<string,mixed>> $lookupResults */
$lookupResults = [];
$submittedUrls = '';

/**
 * @param string $raw
 * @return string[]
 */
$parseUrls = static function (string $raw): array {
   $parts = preg_split('/[\r\n,;\s]+/', $raw) ?: [];
   $clean = [];
   foreach ($parts as $part) {
      $part = trim($part);
      if ($part !== '') {
         $clean[strtolower($part)] = $part;
      }
   }
   return array_values($clean);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   $do = (string)($_POST['do'] ?? 'lookup');
   $submittedUrls = (string)($_POST['urls'] ?? '');
   $urls = $parseUrls($submittedUrls);

   try {
      if ($urls === []) {
         throw new \RuntimeException('Informe ao menos uma URL.');
      }

      if ($do === 'block') {
         $result = Actions::blockUrls($urls);
         $msg = $result['message'];
         if (!empty($result['ticket_id'])) {
            $msg .= ' Ticket #' . (int)$result['ticket_id'] . ' criado.';
         }
         $flash = ['type' => 'ok', 'text' => $msg];
      } elseif ($do === 'unblock') {
         $result = Actions::unblockUrls($urls);
         $flash = ['type' => 'ok', 'text' => $result['message']];
      } else {
         if (!$configured) {
            throw new \RuntimeException('Integracao Zscaler nao esta configurada.');
         }
         $lookupResults = ApiClient::fromConfig($config)->urlLookup($urls);
         if ($lookupResults === []) {
            $flash = ['type' => 'info', 'text' => 'A consulta nao retornou classificacoes.'];
         }
      }
   } catch (\Throwable $error) {
      $flash = ['type' => 'error', 'text' => $error->getMessage()];
   }
}

\Html::header('Zscaler - URL lookup', $_SERVER['PHP_SELF'], 'plugins', 'zscaler');

echo "<div class='zscaler-dashboard'>";

// Hero
echo "<div class='zscaler-dashboard__hero'>";
echo "<div class='zs-hero__brand'>";
echo "<span class='zs-logo'><span class='ti ti-search'></span></span>";
echo "<div>";
echo "<div class='zscaler-dashboard__eyebrow'>Zscaler &middot; Internet Access</div>";
echo "<h2>URL lookup &amp; bloqueio</h2>";
echo "<p>Consulte a categoria de uma URL e bloqueie/desbloqueie na denylist.</p>";
echo "</div>";
echo "</div>";
echo "<div class='zscaler-hero__actions'>";
echo "<a class='btn btn-light' href='" . $h($root . '/plugins/zscaler/front/overview.php') . "'><span class='ti ti-dashboard'></span> Visao geral</a>";
echo "</div>";
echo "</div>";

if ($flash !== null) {
   $cls = ['ok' => 'zscaler-banner--ok', 'error' => 'zscaler-banner--error', 'info' => 'zscaler-banner--info'][$flash['type']] ?? 'zscaler-banner--info';
   $icon = ['ok' => 'ti-circle-check', 'error' => 'ti-alert-triangle', 'info' => 'ti-info-circle'][$flash['type']] ?? 'ti-info-circle';
   echo "<div class='zscaler-banner {$cls}'><span class='ti {$icon}'></span><div>" . $h($flash['text']) . "</div></div>";
}

if (!$actionsOn) {
   echo "<div class='zscaler-banner zscaler-banner--info'><span class='ti ti-eye'></span><div><strong>Modo somente leitura.</strong> A consulta esta liberada; o bloqueio exige liberar as acoes na configuracao e o direito \"Acoes\".</div></div>";
}

// Form
echo "<section class='zscaler-panel zscaler-panel--wide'>";
echo "<div class='zscaler-panel__head'><div class='zscaler-panel__title'><span class='zscaler-panel__icon ti ti-world-search'></span><div><h3>Consultar URLs</h3><p>Uma URL por linha (ate 100). Ex.: exemplo.com, sub.exemplo.com/path</p></div></div></div>";
echo "<div class='zscaler-panel__body'>";
echo "<form method='post' action='" . $h($_SERVER['PHP_SELF']) . "'>";
echo "<textarea class='form-control' name='urls' rows='5' placeholder='exemplo.com&#10;malicioso.net'>" . $h($submittedUrls) . "</textarea>";
echo "<div class='zscaler-toolbar'>";
echo "<button class='btn btn-primary' type='submit' name='do' value='lookup'><span class='ti ti-search'></span> Consultar categoria</button>";
$disabled = $actionsOn ? '' : ' disabled';
echo "<button class='btn btn-danger' type='submit' name='do' value='block'{$disabled} onclick=\"return confirm('Bloquear as URLs informadas na denylist do Zscaler?');\"><span class='ti ti-ban'></span> Bloquear na denylist</button>";
echo "<button class='btn btn-outline-secondary' type='submit' name='do' value='unblock'{$disabled} onclick=\"return confirm('Remover as URLs informadas da denylist?');\"><span class='ti ti-circle-minus'></span> Desbloquear</button>";
echo "</div>";
\Html::closeForm();
echo "</div></section>";

// Resultados
if ($lookupResults !== []) {
   echo "<section class='zscaler-panel zscaler-panel--wide'>";
   echo "<div class='zscaler-panel__head'><div class='zscaler-panel__title'><span class='zscaler-panel__icon ti ti-list-check'></span><div><h3>Resultado da consulta</h3></div></div></div>";
   echo "<div class='zscaler-panel__body'>";
   echo "<div class='table-responsive'><table class='table table-vcenter table-hover mb-0'>";
   echo "<thead><tr><th>URL</th><th>Classificacoes</th><th>Alerta de seguranca</th></tr></thead><tbody>";
   foreach ($lookupResults as $row) {
      $url = (string)($row['url'] ?? '');
      $classes = $row['urlClassifications'] ?? [];
      $secClasses = $row['urlClassificationsWithSecurityAlert'] ?? [];
      $classText = is_array($classes) ? implode(', ', array_map('strval', $classes)) : (string)$classes;
      $secText = is_array($secClasses) ? implode(', ', array_map('strval', $secClasses)) : (string)$secClasses;
      $hasSec = trim($secText) !== '';
      echo "<tr>";
      echo "<td class='zscaler-break'>" . $h($url) . "</td>";
      echo "<td>" . $h($classText !== '' ? $classText : '-') . "</td>";
      if ($hasSec) {
         echo "<td><span class='zs-badge zs-badge--error'>" . $h($secText) . "</span></td>";
      } else {
         echo "<td><span class='text-muted'>-</span></td>";
      }
      echo "</tr>";
   }
   echo "</tbody></table></div>";
   echo "</div></section>";
}

echo "</div>";

\Html::footer();
