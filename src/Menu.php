<?php

namespace GlpiPlugin\Zscaler;

/**
 * Entrada de menu do plugin (Plugins > Zscaler), apontando para a visao geral.
 */
class Menu extends \CommonGLPI
{
   public static $rightname = 'plugin_zscaler_read';

   public static function getMenuName(): string
   {
      return 'Zscaler';
   }

   public static function getIcon(): string
   {
      return 'ti ti-cloud-lock';
   }

   public static function getTypeName($nb = 0): string
   {
      return 'Zscaler';
   }

   /**
    * @return array<string, mixed>
    */
   public static function getMenuContent()
   {
      $rootDoc = self::rootDoc();

      $menu = [
         'title' => self::getMenuName(),
         'page'  => $rootDoc . '/plugins/zscaler/front/overview.php',
         'icon'  => self::getIcon(),
         'links' => [],
      ];

      $menu['links']['search'] = $rootDoc . '/plugins/zscaler/front/urlcategory.php';
      $menu['links']["<i class='ti ti-dashboard' title='Visao geral'></i>"] = $rootDoc . '/plugins/zscaler/front/overview.php';
      $menu['links']["<i class='ti ti-search' title='URL lookup / bloquear'></i>"] = $rootDoc . '/plugins/zscaler/front/urllookup.php';
      $menu['links']["<i class='ti ti-shield-cog' title='Politicas (URL Filtering)'></i>"] = $rootDoc . '/plugins/zscaler/front/policies.php';
      $menu['links']["<i class='ti ti-flask' title='Cloud Sandbox'></i>"] = $rootDoc . '/plugins/zscaler/front/sandbox.php';
      $menu['links']["<i class='ti ti-device-laptop' title='Dispositivos ZCC'></i>"] = $rootDoc . '/plugins/zscaler/front/zccdevice.php';
      $menu['links']["<i class='ti ti-activity-heartbeat' title='Alertas ZDX'></i>"] = $rootDoc . '/plugins/zscaler/front/zdxalert.php';
      $menu['links']["<i class='ti ti-history' title='Historico de acoes'></i>"] = $rootDoc . '/plugins/zscaler/front/actionlog.php';

      return $menu;
   }

   public static function rootDoc(): string
   {
      global $CFG_GLPI;

      return (string)($CFG_GLPI['root_doc'] ?? '');
   }
}
