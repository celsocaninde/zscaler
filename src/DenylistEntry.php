<?php

namespace GlpiPlugin\Zscaler;

class DenylistEntry extends \CommonDBTM
{
   public static $rightname = 'plugin_zscaler_read';

   public static function getTypeName($nb = 0): string
   {
      return $nb > 1 ? 'URLs bloqueadas Zscaler' : 'URL bloqueada Zscaler';
   }

   public static function getIcon(): string
   {
      return 'ti ti-ban';
   }

   public function rawSearchOptions(): array
   {
      $tab = [];

      $tab[] = [
         'id'   => 'common',
         'name' => self::getTypeName(2),
      ];
      $tab[] = [
         'id'       => 1,
         'table'    => self::getTable(),
         'field'    => 'url',
         'name'     => 'URL',
         'datatype' => 'string',
      ];
      $tab[] = [
         'id'       => 2,
         'table'    => self::getTable(),
         'field'    => 'source',
         'name'     => 'Origem',
         'datatype' => 'string',
      ];
      $tab[] = [
         'id'       => 3,
         'table'    => self::getTable(),
         'field'    => 'date_creation',
         'name'     => 'Registrada em',
         'datatype' => 'datetime',
      ];

      return $tab;
   }
}
