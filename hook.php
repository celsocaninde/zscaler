<?php

use GlpiPlugin\Zscaler\Config as ZscalerConfig;
use GlpiPlugin\Zscaler\Profile;
use GlpiPlugin\Zscaler\Sync;

function plugin_zscaler_install(): bool
{
   global $DB;

   $migration = new Migration(PLUGIN_ZSCALER_VERSION);
   $charset = DBConnection::getDefaultCharset();
   $collation = DBConnection::getDefaultCollation();

   $tables = [
      'glpi_plugin_zscaler_urlcategories' => "
         CREATE TABLE `glpi_plugin_zscaler_urlcategories` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `zscaler_id` varchar(255) NOT NULL,
            `name` varchar(255) DEFAULT NULL,
            `type` varchar(50) DEFAULT NULL,
            `super_category` varchar(255) DEFAULT NULL,
            `urls_count` int unsigned NOT NULL DEFAULT 0,
            `db_categorized_urls` int unsigned NOT NULL DEFAULT 0,
            `custom_urls` longtext DEFAULT NULL,
            `raw_json` longtext DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_zscaler_category` (`zscaler_id`),
            KEY `idx_type` (`type`),
            KEY `idx_name` (`name`)
         ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}",
      'glpi_plugin_zscaler_denylistentries' => "
         CREATE TABLE `glpi_plugin_zscaler_denylistentries` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `url` varchar(255) NOT NULL,
            `source` varchar(50) NOT NULL DEFAULT 'zia',
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_zscaler_denylist` (`url`)
         ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}",
      'glpi_plugin_zscaler_actionlogs' => "
         CREATE TABLE `glpi_plugin_zscaler_actionlogs` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `action` varchar(100) NOT NULL,
            `target` varchar(255) DEFAULT NULL,
            `status` varchar(50) NOT NULL,
            `message` text DEFAULT NULL,
            `users_id` int unsigned DEFAULT NULL,
            `tickets_id` int unsigned DEFAULT NULL,
            `source_itemtype` varchar(100) DEFAULT NULL,
            `source_items_id` int unsigned DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_action` (`action`),
            KEY `idx_status` (`status`)
         ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}",
      'glpi_plugin_zscaler_logs' => "
         CREATE TABLE `glpi_plugin_zscaler_logs` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `action` varchar(255) NOT NULL,
            `status` varchar(50) NOT NULL,
            `message` text DEFAULT NULL,
            `items_count` int unsigned NOT NULL DEFAULT 0,
            `date_creation` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_action` (`action`),
            KEY `idx_status` (`status`)
         ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}",
      'glpi_plugin_zscaler_tokens' => "
         CREATE TABLE `glpi_plugin_zscaler_tokens` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `auth_mode` varchar(20) NOT NULL,
            `token` longtext DEFAULT NULL,
            `expires_at` int unsigned NOT NULL DEFAULT 0,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_zscaler_token_mode` (`auth_mode`)
         ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}",
      'glpi_plugin_zscaler_zccdevices' => "
         CREATE TABLE `glpi_plugin_zscaler_zccdevices` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `udid` varchar(255) NOT NULL,
            `user` varchar(255) DEFAULT NULL,
            `machine_hostname` varchar(255) DEFAULT NULL,
            `os_version` varchar(255) DEFAULT NULL,
            `agent_version` varchar(255) DEFAULT NULL,
            `registration_state` varchar(100) DEFAULT NULL,
            `policy_name` varchar(255) DEFAULT NULL,
            `computers_id` int unsigned DEFAULT NULL,
            `last_seen` timestamp NULL DEFAULT NULL,
            `raw_json` longtext DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_zscaler_zcc_udid` (`udid`),
            KEY `idx_computers_id` (`computers_id`),
            KEY `idx_hostname` (`machine_hostname`)
         ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}",
      'glpi_plugin_zscaler_zdxalerts' => "
         CREATE TABLE `glpi_plugin_zscaler_zdxalerts` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `zdx_alert_id` varchar(255) NOT NULL,
            `rule_name` varchar(255) DEFAULT NULL,
            `severity` varchar(50) DEFAULT NULL,
            `status` varchar(50) DEFAULT NULL,
            `application` varchar(255) DEFAULT NULL,
            `num_devices` int unsigned NOT NULL DEFAULT 0,
            `tickets_id` int unsigned DEFAULT NULL,
            `started_on` timestamp NULL DEFAULT NULL,
            `ended_on` timestamp NULL DEFAULT NULL,
            `raw_json` longtext DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_zscaler_zdx_alert` (`zdx_alert_id`),
            KEY `idx_status` (`status`),
            KEY `idx_tickets_id` (`tickets_id`)
         ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}",
      'glpi_plugin_zscaler_auditentries' => "
         CREATE TABLE `glpi_plugin_zscaler_auditentries` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `entry_hash` varchar(64) NOT NULL,
            `admin` varchar(255) DEFAULT NULL,
            `action` varchar(255) DEFAULT NULL,
            `resource` varchar(255) DEFAULT NULL,
            `result` varchar(100) DEFAULT NULL,
            `client_ip` varchar(64) DEFAULT NULL,
            `tickets_id` int unsigned DEFAULT NULL,
            `recorded_at` timestamp NULL DEFAULT NULL,
            `raw_json` longtext DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_zscaler_audit_hash` (`entry_hash`),
            KEY `idx_admin` (`admin`),
            KEY `idx_recorded_at` (`recorded_at`)
         ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}",
      'glpi_plugin_zscaler_blockrequests' => "
         CREATE TABLE `glpi_plugin_zscaler_blockrequests` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `urls` text NOT NULL,
            `tickets_id` int unsigned DEFAULT NULL,
            `status` varchar(30) NOT NULL DEFAULT 'pending_approval',
            `requested_by` int unsigned DEFAULT NULL,
            `approved_by` int unsigned DEFAULT NULL,
            `message` text DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_tickets_id` (`tickets_id`),
            KEY `idx_status` (`status`)
         ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}",
      'glpi_plugin_zscaler_cloudapps' => "
         CREATE TABLE `glpi_plugin_zscaler_cloudapps` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `app_id` varchar(255) NOT NULL,
            `name` varchar(255) DEFAULT NULL,
            `category` varchar(255) DEFAULT NULL,
            `risk_index` varchar(50) DEFAULT NULL,
            `sanctioned` varchar(50) DEFAULT NULL,
            `tickets_id` int unsigned DEFAULT NULL,
            `raw_json` longtext DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_zscaler_cloudapp` (`app_id`),
            KEY `idx_risk_index` (`risk_index`),
            KEY `idx_name` (`name`)
         ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}",
   ];

   foreach ($tables as $table => $sql) {
      if (!$DB->tableExists($table)) {
         $DB->doQueryOrDie($sql, "Error creating {$table}");
      }
   }

   // Migracoes incrementais para instalacoes ja existentes.
   if ($DB->tableExists('glpi_plugin_zscaler_auditentries')
      && !$DB->fieldExists('glpi_plugin_zscaler_auditentries', 'tickets_id')) {
      $DB->doQuery("ALTER TABLE `glpi_plugin_zscaler_auditentries` ADD COLUMN `tickets_id` int unsigned DEFAULT NULL AFTER `client_ip`");
   }

   ZscalerConfig::installDefaults();
   Profile::ensureProfileRights();

   foreach (['syncziadata', 'synczccdevices', 'synczdxalerts', 'syncziaaudit', 'synczcloudapps'] as $cron) {
      CronTask::register(Sync::class, $cron, HOUR_TIMESTAMP, [
         'mode'      => CronTask::MODE_EXTERNAL,
         'allowmode' => CronTask::MODE_EXTERNAL,
         'state'     => CronTask::STATE_DISABLE,
      ]);
   }

   $migration->executeMigration();

   return true;
}

function plugin_zscaler_uninstall(): bool
{
   global $DB;

   $tables = [
      'glpi_plugin_zscaler_blockrequests',
      'glpi_plugin_zscaler_cloudapps',
      'glpi_plugin_zscaler_auditentries',
      'glpi_plugin_zscaler_zdxalerts',
      'glpi_plugin_zscaler_zccdevices',
      'glpi_plugin_zscaler_tokens',
      'glpi_plugin_zscaler_logs',
      'glpi_plugin_zscaler_actionlogs',
      'glpi_plugin_zscaler_denylistentries',
      'glpi_plugin_zscaler_urlcategories',
   ];

   foreach ($tables as $table) {
      if ($DB->tableExists($table)) {
         $DB->doQueryOrDie("DROP TABLE `{$table}`", "Error dropping {$table}");
      }
   }

   $config = new \Config();
   $config->deleteByCriteria(['context' => ZscalerConfig::CONTEXT]);

   $cron = new CronTask();
   $cron->deleteByCriteria(['itemtype' => Sync::class]);

   $rights = array_column(Profile::getAllRights(), 'field');
   if ($rights !== []) {
      ProfileRight::deleteProfileRights($rights);
   }

   return true;
}
