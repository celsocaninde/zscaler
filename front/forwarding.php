<?php

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

$pick = static function (array $row, array $keys): string {
   foreach ($keys as $key) {
      if (isset($row[$key]) && !is_array($row[$key]) && trim((string)$row[$key]) !== '') {
         return (string)$row[$key];
      }
   }
   return '';
};
$locName = static function ($loc): string {
   if (is_array($loc)) {
      return (string)($loc['name'] ?? $loc['id'] ?? '');
   }
   return (string)$loc;
};

$vpn = [];
$gre = [];
$staticIps = [];
$loadError = null;
if ($configured) {
   try {
      $client = ApiClient::fromConfig($config);
      $vpn = $client->getVpnCredentials();
      $gre = $client->getGreTunnels();
      $staticIps = $client->getStaticIps();
   } catch (\Throwable $error) {
      $loadError = $error->getMessage();
   }
}

$orphans = 0;
foreach ($vpn as $cred) {
   if (is_array($cred) && trim($locName($cred['location'] ?? '')) === '') {
      $orphans++;
   }
}

\Html::header('Zscaler - Forwarding', $_SERVER['PHP_SELF'], 'plugins', 'zscaler');
echo "<style>.container-xl,.container-lg{max-width:100%!important}</style>";

echo "<div class='zscaler-dashboard'>";
echo "<div class='zscaler-dashboard__hero'>";
echo "<div class='zs-hero__brand'>";
echo "<span class='zs-logo'><span class='ti ti-route'></span></span>";
echo "<div>";
echo "<div class='zscaler-dashboard__eyebrow'>Zscaler &middot; Traffic Forwarding</div>";
echo "<h2>VPN, GRE e IPs estaticos</h2>";
echo "<p>Inventario da infraestrutura de encaminhamento de trafego configurada no tenant.</p>";
echo "</div>";
echo "</div>";
echo "<div class='zscaler-hero__actions'>";
echo "<a class='btn btn-light' href='" . $h($root . '/plugins/zscaler/front/overview.php') . "'><span class='ti ti-dashboard'></span> Visao geral</a>";
echo "</div>";
echo "</div>";

if (!$configured) {
   echo "<div class='zscaler-banner zscaler-banner--warn'><span class='ti ti-alert-triangle'></span><div>Integracao ZIA nao configurada.</div></div>";
}
if ($loadError !== null) {
   echo "<div class='zscaler-banner zscaler-banner--error'><span class='ti ti-alert-triangle'></span><div>" . $h($loadError) . "</div></div>";
}
if ($orphans > 0) {
   echo "<div class='zscaler-banner zscaler-banner--warn'><span class='ti ti-alert-triangle'></span><div><strong>" . $h((string)$orphans) . " credencial(is) VPN sem localidade associada.</strong> Credenciais orfas devem ser revisadas (atribua a uma localidade ou remova).</div></div>";
}

// ---- VPN Credentials ----
echo "<section class='zscaler-panel zscaler-panel--wide'>";
echo "<div class='zscaler-panel__head'><div class='zscaler-panel__title'><span class='zscaler-panel__icon ti ti-key'></span><div><h3>Credenciais VPN (" . $h((string)count($vpn)) . ")</h3></div></div></div>";
echo "<div class='zscaler-panel__body'>";
if ($vpn !== []) {
   echo "<div class='table-responsive'><table class='table table-vcenter table-hover mb-0'>";
   echo "<thead><tr><th>Tipo</th><th>FQDN / IP</th><th>Localidade</th><th>Comentario</th></tr></thead><tbody>";
   foreach ($vpn as $cred) {
      if (!is_array($cred)) {
         continue;
      }
      $type = $pick($cred, ['type']);
      $idVal = $pick($cred, ['fqdn', 'ipAddress', 'comments']);
      $loc = trim($locName($cred['location'] ?? ''));
      echo "<tr>";
      echo "<td>" . $h($type !== '' ? $type : '-') . "</td>";
      echo "<td>" . $h($idVal !== '' ? $idVal : '-') . "</td>";
      if ($loc === '') {
         echo "<td><span class='zs-badge zs-badge--warn'>sem localidade</span></td>";
      } else {
         echo "<td>" . $h($loc) . "</td>";
      }
      echo "<td>" . $h($pick($cred, ['comments']) ?: '-') . "</td>";
      echo "</tr>";
   }
   echo "</tbody></table></div>";
} elseif ($configured && $loadError === null) {
   echo "<p class='text-muted mb-0'>Nenhuma credencial VPN encontrada.</p>";
}
echo "</div></section>";

// ---- GRE Tunnels ----
echo "<section class='zscaler-panel zscaler-panel--wide'>";
echo "<div class='zscaler-panel__head'><div class='zscaler-panel__title'><span class='zscaler-panel__icon ti ti-network'></span><div><h3>Tuneis GRE (" . $h((string)count($gre)) . ")</h3></div></div></div>";
echo "<div class='zscaler-panel__body'>";
if ($gre !== []) {
   echo "<div class='table-responsive'><table class='table table-vcenter table-hover mb-0'>";
   echo "<thead><tr><th>IP de origem</th><th>Faixa interna</th><th>VIP primario</th><th>Comentario</th></tr></thead><tbody>";
   foreach ($gre as $tunnel) {
      if (!is_array($tunnel)) {
         continue;
      }
      $primary = $tunnel['primaryDestVip'] ?? null;
      $primaryStr = is_array($primary) ? (string)($primary['virtualIp'] ?? $primary['datacenter'] ?? '') : (string)$primary;
      echo "<tr>";
      echo "<td>" . $h($pick($tunnel, ['sourceIp']) ?: '-') . "</td>";
      echo "<td>" . $h($pick($tunnel, ['internalIpRange']) ?: '-') . "</td>";
      echo "<td>" . $h($primaryStr !== '' ? $primaryStr : '-') . "</td>";
      echo "<td>" . $h($pick($tunnel, ['comment']) ?: '-') . "</td>";
      echo "</tr>";
   }
   echo "</tbody></table></div>";
} elseif ($configured && $loadError === null) {
   echo "<p class='text-muted mb-0'>Nenhum tunel GRE encontrado.</p>";
}
echo "</div></section>";

// ---- Static IPs ----
echo "<section class='zscaler-panel zscaler-panel--wide'>";
echo "<div class='zscaler-panel__head'><div class='zscaler-panel__title'><span class='zscaler-panel__icon ti ti-map-pin'></span><div><h3>IPs estaticos (" . $h((string)count($staticIps)) . ")</h3></div></div></div>";
echo "<div class='zscaler-panel__body'>";
if ($staticIps !== []) {
   echo "<div class='table-responsive'><table class='table table-vcenter table-hover mb-0'>";
   echo "<thead><tr><th>IP</th><th>Roteavel</th><th>Comentario</th></tr></thead><tbody>";
   foreach ($staticIps as $ip) {
      if (!is_array($ip)) {
         continue;
      }
      $routable = isset($ip['routableIP']) ? ((bool)$ip['routableIP'] ? 'Sim' : 'Nao') : '-';
      echo "<tr>";
      echo "<td>" . $h($pick($ip, ['ipAddress']) ?: '-') . "</td>";
      echo "<td>" . $h($routable) . "</td>";
      echo "<td>" . $h($pick($ip, ['comment']) ?: '-') . "</td>";
      echo "</tr>";
   }
   echo "</tbody></table></div>";
} elseif ($configured && $loadError === null) {
   echo "<p class='text-muted mb-0'>Nenhum IP estatico encontrado.</p>";
}
echo "</div></section>";

echo "</div>";

\Html::footer();
