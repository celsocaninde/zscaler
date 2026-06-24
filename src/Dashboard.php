<?php

namespace GlpiPlugin\Zscaler;

/**
 * Cards de KPI do Zscaler para o dashboard nativo do GLPI (hook dashboard_cards).
 */
class Dashboard
{
   private const GROUP = 'Zscaler';

   public static function getCards($cards = null): array
   {
      if (!is_array($cards)) {
         $cards = [];
      }

      $defs = [
         'plugin_zscaler_categories_custom' => 'cardCategoriesCustom',
         'plugin_zscaler_denylist_total'    => 'cardDenylistTotal',
         'plugin_zscaler_actions_total'     => 'cardActionsTotal',
         'plugin_zscaler_actions_error'     => 'cardActionsError',
         'plugin_zscaler_zcc_unprotected'   => 'cardZccUnprotected',
         'plugin_zscaler_zdx_ongoing'       => 'cardZdxOngoing',
         'plugin_zscaler_cloudapps_risky'   => 'cardCloudAppsRisky',
         'plugin_zscaler_audit_total'       => 'cardAuditTotal',
      ];

      $labels = [
         'plugin_zscaler_categories_custom' => 'Zscaler - Categorias customizadas',
         'plugin_zscaler_denylist_total'    => 'Zscaler - URLs bloqueadas',
         'plugin_zscaler_actions_total'     => 'Zscaler - Acoes executadas',
         'plugin_zscaler_actions_error'     => 'Zscaler - Acoes com erro',
         'plugin_zscaler_zcc_unprotected'   => 'Zscaler - Computadores sem ZCC',
         'plugin_zscaler_zdx_ongoing'       => 'Zscaler - Alertas ZDX abertos',
         'plugin_zscaler_cloudapps_risky'   => 'Zscaler - Apps de risco (Shadow IT)',
         'plugin_zscaler_audit_total'       => 'Zscaler - Registros de auditoria',
      ];

      foreach ($defs as $key => $method) {
         $cards[$key] = [
            'widgettype' => ['bigNumber'],
            'group'      => self::GROUP,
            'label'      => $labels[$key],
            'provider'   => self::class . '::' . $method,
         ];
      }

      return $cards;
   }

   public static function cardCategoriesCustom(array $params = []): array
   {
      return self::bigNumber(
         self::count(UrlCategory::getTable(), ['type' => 'custom']),
         'Categorias customizadas',
         'ti ti-category',
         self::frontUrl('overview.php')
      );
   }

   public static function cardDenylistTotal(array $params = []): array
   {
      return self::bigNumber(
         self::count(DenylistEntry::getTable()),
         'URLs bloqueadas',
         'ti ti-ban',
         self::frontUrl('overview.php')
      );
   }

   public static function cardActionsTotal(array $params = []): array
   {
      return self::bigNumber(
         self::count(ActionLog::getTable()),
         'Acoes executadas',
         'ti ti-bolt',
         self::frontUrl('overview.php')
      );
   }

   public static function cardActionsError(array $params = []): array
   {
      return self::bigNumber(
         self::count(ActionLog::getTable(), ['status' => 'error']),
         'Acoes com erro',
         'ti ti-alert-triangle',
         self::frontUrl('overview.php')
      );
   }

   public static function cardZccUnprotected(array $params = []): array
   {
      $value = Profile::hasReadRight() ? ZccDevice::countUnprotectedComputers() : 0;

      return [
         'number' => $value,
         'label'  => 'Computadores sem ZCC',
         'icon'   => 'ti ti-shield-off',
         'url'    => self::frontUrl('unprotected.php'),
      ];
   }

   public static function cardZdxOngoing(array $params = []): array
   {
      return self::bigNumber(
         self::count(ZdxAlert::getTable(), ['status' => 'ongoing']),
         'Alertas ZDX abertos',
         'ti ti-activity-heartbeat',
         self::frontUrl('zdxalert.php')
      );
   }

   public static function cardCloudAppsRisky(array $params = []): array
   {
      $value = Profile::hasReadRight() ? CloudApp::countRisky() : 0;

      return [
         'number' => $value,
         'label'  => 'Apps de risco (Shadow IT)',
         'icon'   => 'ti ti-cloud-data-connection',
         'url'    => self::frontUrl('cloudapps.php'),
      ];
   }

   public static function cardAuditTotal(array $params = []): array
   {
      return self::bigNumber(
         self::count(AuditEntry::getTable()),
         'Registros de auditoria',
         'ti ti-clipboard-list',
         self::frontUrl('auditlog_zia.php')
      );
   }

   private static function bigNumber(int $number, string $label, string $icon, string $url): array
   {
      return [
         'number' => $number,
         'label'  => $label,
         'icon'   => $icon,
         'url'    => $url,
      ];
   }

   private static function count(string $table, array $where = []): int
   {
      global $DB;

      if (!Profile::hasReadRight() || !$DB->tableExists($table)) {
         return 0;
      }

      $criteria = ['COUNT' => 'cpt', 'FROM' => $table];
      if ($where !== []) {
         $criteria['WHERE'] = $where;
      }

      $row = $DB->request($criteria)->current();

      return (int)($row['cpt'] ?? 0);
   }

   private static function frontUrl(string $file): string
   {
      global $CFG_GLPI;

      $rootDoc = (string)($CFG_GLPI['root_doc'] ?? '');

      return $rootDoc . '/plugins/zscaler/front/' . ltrim($file, '/');
   }
}
