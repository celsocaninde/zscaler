<?php

namespace GlpiPlugin\Zscaler;

class UrlCategory extends \CommonDBTM
{
   public static $rightname = 'plugin_zscaler_read';

   public static function getTypeName($nb = 0): string
   {
      return $nb > 1 ? 'Categorias de URL Zscaler' : 'Categoria de URL Zscaler';
   }

   public static function getIcon(): string
   {
      return 'ti ti-category';
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
         'field'    => 'name',
         'name'     => 'Nome',
         'datatype' => 'string',
      ];
      $tab[] = [
         'id'       => 2,
         'table'    => self::getTable(),
         'field'    => 'zscaler_id',
         'name'     => 'ID Zscaler',
         'datatype' => 'string',
      ];
      $tab[] = [
         'id'       => 3,
         'table'    => self::getTable(),
         'field'    => 'type',
         'name'     => 'Tipo',
         'datatype' => 'string',
      ];
      $tab[] = [
         'id'       => 4,
         'table'    => self::getTable(),
         'field'    => 'super_category',
         'name'     => 'Super categoria',
         'datatype' => 'string',
      ];
      $tab[] = [
         'id'       => 5,
         'table'    => self::getTable(),
         'field'    => 'urls_count',
         'name'     => 'URLs customizadas',
         'datatype' => 'number',
      ];
      $tab[] = [
         'id'       => 6,
         'table'    => self::getTable(),
         'field'    => 'db_categorized_urls',
         'name'     => 'URLs do banco Zscaler',
         'datatype' => 'number',
      ];
      $tab[] = [
         'id'       => 7,
         'table'    => self::getTable(),
         'field'    => 'date_mod',
         'name'     => 'Ultima sincronizacao',
         'datatype' => 'datetime',
      ];

      return $tab;
   }
}
