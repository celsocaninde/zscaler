<?php

namespace GlpiPlugin\Zscaler;

/**
 * Aba "Zscaler" no Usuario do GLPI: casa o usuario com a identidade ZIA
 * (departamento, grupos) consultando a API ao vivo pelo email/login.
 */
class UserPanel extends \CommonGLPI
{
   public static $rightname = 'plugin_zscaler_read';

   public static function getTypeName($nb = 0): string
   {
      return 'Zscaler';
   }

   public function getTabNameForItem(\CommonGLPI $item, $withtemplate = 0): string
   {
      if ($item instanceof \User && Profile::hasReadRight()) {
         return 'Zscaler';
      }

      return '';
   }

   public static function displayTabContentForItem(\CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
   {
      if (!$item instanceof \User) {
         return false;
      }

      $h = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      $config = Config::getConfig();

      echo "<div class='zscaler-itemaction'>";
      echo "<div class='zscaler-itemaction__head'>";
      echo "<span class='zs-logo zs-logo--solid'><span class='ti ti-cloud-lock'></span></span>";
      echo "<div><h3>Zscaler &middot; Identidade do usuario</h3><p>Correspondencia com o Zscaler Internet Access (ZIA).</p></div>";
      echo "</div>";

      if (!Config::isConfigured($config)) {
         echo "<div class='zscaler-banner zscaler-banner--warn'><span class='ti ti-alert-triangle'></span><div>Integracao Zscaler nao configurada.</div></div>";
         echo "</div>";
         return true;
      }

      $email = '';
      if (method_exists($item, 'getDefaultEmail')) {
         $email = (string)$item->getDefaultEmail();
      }
      $login = (string)($item->fields['name'] ?? '');
      $needle = $email !== '' ? $email : $login;

      $zuser = null;
      $error = null;
      try {
         $client = ApiClient::fromConfig($config);
         $zuser = $client->findUser($needle);
      } catch (\Throwable $e) {
         $error = $e->getMessage();
      }

      if ($error !== null) {
         echo "<div class='zscaler-banner zscaler-banner--error'><span class='ti ti-alert-triangle'></span><div>" . $h($error) . "</div></div>";
      } elseif ($zuser === null) {
         echo "<div class='zscaler-banner zscaler-banner--info'><span class='ti ti-user-question'></span><div>Nenhum usuario Zscaler encontrado para <strong>" . $h($needle) . "</strong>.</div></div>";
      } else {
         $dept = $zuser['department'] ?? null;
         $deptName = is_array($dept) ? (string)($dept['name'] ?? '') : (string)$dept;

         $groups = [];
         foreach ((array)($zuser['groups'] ?? []) as $g) {
            $groups[] = is_array($g) ? (string)($g['name'] ?? '') : (string)$g;
         }
         $groups = array_filter($groups, static fn($g): bool => $g !== '');

         $rows = [
            ['Nome (ZIA)', (string)($zuser['name'] ?? '-')],
            ['Email', (string)($zuser['email'] ?? '-')],
            ['Login', (string)($zuser['loginName'] ?? $zuser['login'] ?? '-')],
            ['Departamento', $deptName !== '' ? $deptName : '-'],
            ['Grupos', $groups !== [] ? implode(', ', $groups) : '-'],
         ];

         echo "<div class='table-responsive'><table class='table table-vcenter mb-0'><tbody>";
         foreach ($rows as [$label, $value]) {
            echo "<tr><th style='width:220px'>" . $h($label) . "</th><td class='zscaler-break'>" . $h($value) . "</td></tr>";
         }
         echo "</tbody></table></div>";
      }

      echo "</div>";

      return true;
   }
}
