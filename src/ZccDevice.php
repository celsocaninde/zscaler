<?php

namespace GlpiPlugin\Zscaler;

class ZccDevice extends \CommonDBTM
{
   public static $rightname = 'plugin_zscaler_read';

   public static function getTypeName($nb = 0): string
   {
      return $nb > 1 ? 'Dispositivos ZCC' : 'Dispositivo ZCC';
   }

   public static function getIcon(): string
   {
      return 'ti ti-device-laptop';
   }

   /**
    * Conta computadores do GLPI que nao tem um dispositivo ZCC vinculado.
    */
   public static function countUnprotectedComputers(): int
   {
      global $DB;

      if (!$DB->tableExists('glpi_computers') || !$DB->tableExists(self::getTable())) {
         return 0;
      }

      $matched = [];
      foreach ($DB->request([
         'SELECT'   => ['computers_id'],
         'DISTINCT' => true,
         'FROM'     => self::getTable(),
         'WHERE'    => ['NOT' => ['computers_id' => null]],
      ]) as $row) {
         $matched[(int)$row['computers_id']] = true;
      }

      $total = 0;
      foreach ($DB->request([
         'SELECT' => ['id'],
         'FROM'   => 'glpi_computers',
         'WHERE'  => ['is_deleted' => 0],
      ]) as $row) {
         if (!isset($matched[(int)$row['id']])) {
            $total++;
         }
      }

      return $total;
   }

   public function rawSearchOptions(): array
   {
      $tab = [];

      $tab[] = ['id' => 'common', 'name' => self::getTypeName(2)];
      $tab[] = ['id' => 1, 'table' => self::getTable(), 'field' => 'machine_hostname', 'name' => 'Host', 'datatype' => 'string'];
      $tab[] = ['id' => 2, 'table' => self::getTable(), 'field' => 'user', 'name' => 'Usuario', 'datatype' => 'string'];
      $tab[] = ['id' => 3, 'table' => self::getTable(), 'field' => 'os_version', 'name' => 'SO', 'datatype' => 'string'];
      $tab[] = ['id' => 4, 'table' => self::getTable(), 'field' => 'agent_version', 'name' => 'Versao ZCC', 'datatype' => 'string'];
      $tab[] = ['id' => 5, 'table' => self::getTable(), 'field' => 'registration_state', 'name' => 'Registro', 'datatype' => 'string'];
      $tab[] = ['id' => 6, 'table' => self::getTable(), 'field' => 'computers_id', 'name' => 'Computador GLPI', 'datatype' => 'number'];
      $tab[] = ['id' => 7, 'table' => self::getTable(), 'field' => 'last_seen', 'name' => 'Ultimo contato', 'datatype' => 'datetime'];

      return $tab;
   }
}
