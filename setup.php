<?php

use Glpi\Plugin\Hooks;
use GlpiPlugin\Zscaler\ActionLog;
use GlpiPlugin\Zscaler\Config as ZscalerConfig;
use GlpiPlugin\Zscaler\Dashboard as ZscalerDashboard;
use GlpiPlugin\Zscaler\DenylistEntry;
use GlpiPlugin\Zscaler\ItemAction;
use GlpiPlugin\Zscaler\Menu as ZscalerMenu;
use GlpiPlugin\Zscaler\Profile as ZscalerProfile;
use GlpiPlugin\Zscaler\UrlCategory;
use GlpiPlugin\Zscaler\ZccDevice;
use GlpiPlugin\Zscaler\ZdxAlert;

define('PLUGIN_ZSCALER_VERSION', '0.2.0');
define('PLUGIN_ZSCALER_MIN_GLPI_VERSION', '11.0.0');
define('PLUGIN_ZSCALER_MAX_GLPI_VERSION', '11.0.99');

function plugin_init_zscaler(): void
{
   global $PLUGIN_HOOKS;

   ZscalerProfile::syncCurrentProfileRights();

   $PLUGIN_HOOKS['csrf_compliant']['zscaler'] = true;
   $PLUGIN_HOOKS['config_page']['zscaler'] = 'front/config.form.php';
   $PLUGIN_HOOKS[Hooks::ADD_CSS]['zscaler'] = ['public/css/zscaler.css'];
   $PLUGIN_HOOKS[Hooks::DASHBOARD_CARDS]['zscaler'] = [ZscalerDashboard::class, 'getCards'];

   Plugin::registerClass(ZscalerProfile::class, [
      'addtabon' => [\Profile::class],
   ]);

   Plugin::registerClass(ZscalerConfig::class, [
      'addtabon' => \Config::class,
   ]);

   Plugin::registerClass(UrlCategory::class);
   Plugin::registerClass(DenylistEntry::class);
   Plugin::registerClass(ActionLog::class);
   Plugin::registerClass(ZccDevice::class);
   Plugin::registerClass(ZdxAlert::class);
   Plugin::registerClass(ZscalerMenu::class);

   Plugin::registerClass(ItemAction::class, [
      'addtabon' => [\Ticket::class, \Computer::class],
   ]);

   $PLUGIN_HOOKS[Hooks::MENU_TOADD]['zscaler'] = [
      'plugins' => ZscalerMenu::class,
   ];
}

function plugin_version_zscaler(): array
{
   return [
      'name'           => 'Zscaler',
      'version'        => PLUGIN_ZSCALER_VERSION,
      'author'         => 'Celso / Claude',
      'license'        => 'GPLv3+',
      'homepage'       => 'https://github.com/celsocaninde/zscaler',
      'requirements'   => [
         'glpi' => [
            'min' => PLUGIN_ZSCALER_MIN_GLPI_VERSION,
            'max' => PLUGIN_ZSCALER_MAX_GLPI_VERSION,
         ],
         'php' => [
            'min' => '8.2',
         ],
      ],
   ];
}

function plugin_zscaler_check_prerequisites(): bool
{
   if (version_compare(GLPI_VERSION, PLUGIN_ZSCALER_MIN_GLPI_VERSION, 'lt')
      || version_compare(GLPI_VERSION, PLUGIN_ZSCALER_MAX_GLPI_VERSION, 'gt')) {
      echo sprintf(
         'This plugin requires GLPI >= %s and <= %s.',
         PLUGIN_ZSCALER_MIN_GLPI_VERSION,
         PLUGIN_ZSCALER_MAX_GLPI_VERSION
      );
      return false;
   }

   if (version_compare(PHP_VERSION, '8.2.0', 'lt')) {
      echo 'This plugin requires PHP >= 8.2.';
      return false;
   }

   return true;
}

function plugin_zscaler_check_config(bool $verbose = false): bool
{
   return true;
}
