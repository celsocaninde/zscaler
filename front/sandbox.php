<?php

use GlpiPlugin\Zscaler\ApiClient;
use GlpiPlugin\Zscaler\Config;
use GlpiPlugin\Zscaler\Menu;
use GlpiPlugin\Zscaler\Profile;
use GlpiPlugin\Zscaler\TicketManager;

include('../../../inc/includes.php');

\Session::checkRight(Profile::RIGHT_READ, READ);

$h = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$root = Menu::rootDoc();
$config = Config::getConfig();
$configured = Config::isConfigured($config);
$hasSubmitToken = trim((string)$config['sandbox_token']) !== '';

$flash = null;
$report = null;
$reportMd5 = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   $do = (string)($_POST['do'] ?? '');
   try {
      if (!$configured) {
         throw new \RuntimeException('Integracao ZIA nao configurada.');
      }
      $client = ApiClient::fromConfig($config);

      if ($do === 'report') {
         $reportMd5 = trim((string)($_POST['md5'] ?? ''));
         if ($reportMd5 === '') {
            throw new \RuntimeException('Informe um hash MD5.');
         }
         $report = $client->sandboxReport($reportMd5);

         $verdict = strtoupper((string)(
            $report['Full Details']['Classification']['Type']
            ?? $report['fullDetails']['classification']['type']
            ?? ''
         ));
         if ($verdict === 'MALICIOUS'
            && (string)$config['create_tickets'] === '1'
            && (string)$config['ticket_on_sandbox_malicious'] === '1') {
            $ticketId = TicketManager::createForSandbox($reportMd5, $report, $config);
            $flash = ['type' => 'ok', 'text' => 'Veredito MALICIOSO: ticket #' . $ticketId . ' criado.'];
         } else {
            $flash = ['type' => 'info', 'text' => 'Relatorio obtido' . ($verdict !== '' ? (' (' . $verdict . ')') : '') . '.'];
         }
      } elseif ($do === 'submit') {
         if (!Profile::hasActionRight()) {
            throw new \RuntimeException('Voce nao tem permissao para submeter arquivos.');
         }
         if (!$hasSubmitToken) {
            throw new \RuntimeException('Token de submissao do Sandbox nao configurado.');
         }
         if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            throw new \RuntimeException('Selecione um arquivo para submeter.');
         }
         $content = (string)file_get_contents($_FILES['file']['tmp_name']);
         $name = (string)($_FILES['file']['name'] ?? 'arquivo');
         $submit = $client->sandboxSubmit($name, $content, true);
         $flash = ['type' => 'ok', 'text' => 'Arquivo submetido ao sandbox. MD5: ' . ($submit['md5'] ?? '-') . '. O veredito completo pode levar alguns minutos; consulte pelo hash.'];
      }
   } catch (\Throwable $error) {
      $flash = ['type' => 'error', 'text' => $error->getMessage()];
   }
}

// Quota (best-effort)
$quota = null;
if ($configured) {
   try {
      $quota = ApiClient::fromConfig($config)->sandboxQuota();
   } catch (\Throwable $error) {
      $quota = null;
   }
}

\Html::header('Zscaler - Sandbox', $_SERVER['PHP_SELF'], 'plugins', 'zscaler');

echo "<div class='zscaler-dashboard'>";
echo "<div class='zscaler-dashboard__hero'>";
echo "<div class='zs-hero__brand'>";
echo "<span class='zs-logo'><span class='ti ti-flask'></span></span>";
echo "<div>";
echo "<div class='zscaler-dashboard__eyebrow'>Zscaler &middot; Cloud Sandbox</div>";
echo "<h2>Sandbox</h2>";
echo "<p>Consulte veredictos por hash e submeta arquivos para analise.</p>";
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
if (!$configured) {
   echo "<div class='zscaler-banner zscaler-banner--warn'><span class='ti ti-alert-triangle'></span><div>Integracao ZIA nao configurada.</div></div>";
}

if (is_array($quota)) {
   $allowed = (int)($quota['allowed'] ?? $quota['Allowed'] ?? 0);
   $used = (int)($quota['used'] ?? $quota['Used'] ?? 0);
   if ($allowed > 0 || $used > 0) {
      echo "<div class='zscaler-footnote'><span class='ti ti-gauge'></span> Quota de submissao: <strong>" . $h((string)$used) . "</strong> usada de <strong>" . $h((string)$allowed) . "</strong>.</div>";
   }
}

// Consulta por hash
echo "<section class='zscaler-panel'>";
echo "<div class='zscaler-panel__head'><div class='zscaler-panel__title'><span class='zscaler-panel__icon ti ti-file-search'></span><div><h3>Consultar por hash (MD5)</h3></div></div></div>";
echo "<div class='zscaler-panel__body'>";
echo "<form method='post' action='" . $h($_SERVER['PHP_SELF']) . "'>";
echo "<div class='zscaler-fields'><label class='zscaler-field zscaler-field--wide'><span>Hash MD5</span><input class='form-control' type='text' name='md5' value='" . $h($reportMd5) . "' placeholder='ex.: 44d88612fea8a8f36de82e1278abb02f'></label></div>";
echo "<div class='zscaler-toolbar'><button class='btn btn-primary' type='submit' name='do' value='report'><span class='ti ti-search'></span> Buscar relatorio</button></div>";
\Html::closeForm();
echo "</div></section>";

// Submissao de arquivo
echo "<section class='zscaler-panel'>";
echo "<div class='zscaler-panel__head'><div class='zscaler-panel__title'><span class='zscaler-panel__icon ti ti-upload'></span><div><h3>Submeter arquivo</h3><p>Requer token de submissao e o direito \"Acoes\".</p></div></div></div>";
echo "<div class='zscaler-panel__body'>";
if (!$hasSubmitToken) {
   echo "<div class='zscaler-banner zscaler-banner--info'><span class='ti ti-info-circle'></span><div>Configure o <strong>Token de submissao do Sandbox</strong> na configuracao para habilitar.</div></div>";
}
$canSubmit = $hasSubmitToken && Profile::hasActionRight();
echo "<form method='post' action='" . $h($_SERVER['PHP_SELF']) . "' enctype='multipart/form-data'>";
echo "<div class='zscaler-fields'><label class='zscaler-field zscaler-field--wide'><span>Arquivo</span><input class='form-control' type='file' name='file'" . ($canSubmit ? '' : ' disabled') . "></label></div>";
echo "<div class='zscaler-toolbar'><button class='btn btn-danger' type='submit' name='do' value='submit'" . ($canSubmit ? '' : ' disabled') . " onclick=\"return confirm('Submeter este arquivo ao Cloud Sandbox?');\"><span class='ti ti-flask'></span> Submeter ao sandbox</button></div>";
\Html::closeForm();
echo "</div></section>";

// Resultado do relatorio
if (is_array($report)) {
   $details = $report['Full Details'] ?? $report['fullDetails'] ?? $report;
   $class = $details['Classification'] ?? $details['classification'] ?? [];
   $type = strtoupper((string)($class['Type'] ?? $class['type'] ?? '-'));
   $score = (string)($class['Score'] ?? $class['score'] ?? '-');
   $category = (string)($class['Category'] ?? $class['category'] ?? '-');
   $badge = $type === 'MALICIOUS' ? 'zs-badge--error' : 'zs-badge--ok';

   echo "<section class='zscaler-panel zscaler-panel--wide'>";
   echo "<div class='zscaler-panel__head'><div class='zscaler-panel__title'><span class='zscaler-panel__icon ti ti-report'></span><div><h3>Relatorio do sandbox</h3></div></div></div>";
   echo "<div class='zscaler-panel__body'>";
   echo "<table class='table table-vcenter mb-0'><tbody>";
   echo "<tr><td style='width:30%'>Hash</td><td class='zscaler-break'>" . $h($reportMd5) . "</td></tr>";
   echo "<tr><td>Classificacao</td><td><span class='zs-badge {$badge}'>" . $h($type) . "</span></td></tr>";
   echo "<tr><td>Score</td><td>" . $h($score) . "</td></tr>";
   echo "<tr><td>Categoria</td><td>" . $h($category) . "</td></tr>";
   echo "</tbody></table>";
   echo "</div></section>";
}

echo "</div>";

\Html::footer();
