<?php

namespace GlpiPlugin\Zscaler;

class ZdxAlert extends \CommonDBTM
{
   public static $rightname = 'plugin_zscaler_read';

   public static function getTypeName($nb = 0): string
   {
      return $nb > 1 ? 'Alertas ZDX' : 'Alerta ZDX';
   }

   public static function getIcon(): string
   {
      return 'ti ti-activity-heartbeat';
   }

   public function rawSearchOptions(): array
   {
      $tab = [];

      $tab[] = ['id' => 'common', 'name' => self::getTypeName(2)];
      $tab[] = ['id' => 1, 'table' => self::getTable(), 'field' => 'rule_name', 'name' => 'Regra', 'datatype' => 'string'];
      $tab[] = ['id' => 2, 'table' => self::getTable(), 'field' => 'severity', 'name' => 'Severidade', 'datatype' => 'string'];
      $tab[] = ['id' => 3, 'table' => self::getTable(), 'field' => 'status', 'name' => 'Status', 'datatype' => 'string'];
      $tab[] = ['id' => 4, 'table' => self::getTable(), 'field' => 'application', 'name' => 'Aplicacao', 'datatype' => 'string'];
      $tab[] = ['id' => 5, 'table' => self::getTable(), 'field' => 'num_devices', 'name' => 'Dispositivos', 'datatype' => 'number'];
      $tab[] = ['id' => 6, 'table' => self::getTable(), 'field' => 'tickets_id', 'name' => 'Ticket', 'datatype' => 'number'];
      $tab[] = ['id' => 7, 'table' => self::getTable(), 'field' => 'started_on', 'name' => 'Inicio', 'datatype' => 'datetime'];

      return $tab;
   }

   /**
    * Severidade legivel + classe de badge.
    *
    * @return array{0:string,1:string}
    */
   public static function severityBadge(string $severity): array
   {
      return match (strtolower(trim($severity))) {
         'critical', 'high' => [ucfirst($severity), 'zs-badge--error'],
         default            => [$severity !== '' ? ucfirst($severity) : '-', 'zs-badge--ok'],
      };
   }
}
