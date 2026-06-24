<?php

namespace GlpiPlugin\Zscaler;

/**
 * Entrada do Admin Audit Log do Zscaler (quem mudou o que na console ZIA).
 * Importada via relatorio assincrono /auditlogEntryReport e cacheada localmente.
 */
class AuditEntry extends \CommonDBTM
{
   public static $rightname = 'plugin_zscaler_read';

   public static function getTypeName($nb = 0): string
   {
      return $nb > 1 ? 'Auditoria Zscaler' : 'Auditoria Zscaler';
   }

   public static function getIcon(): string
   {
      return 'ti ti-clipboard-list';
   }

   public function rawSearchOptions(): array
   {
      $tab = [];

      $tab[] = ['id' => 'common', 'name' => self::getTypeName(2)];
      $tab[] = ['id' => 1, 'table' => self::getTable(), 'field' => 'admin', 'name' => 'Administrador', 'datatype' => 'string'];
      $tab[] = ['id' => 2, 'table' => self::getTable(), 'field' => 'action', 'name' => 'Acao', 'datatype' => 'string'];
      $tab[] = ['id' => 3, 'table' => self::getTable(), 'field' => 'resource', 'name' => 'Recurso', 'datatype' => 'string'];
      $tab[] = ['id' => 4, 'table' => self::getTable(), 'field' => 'result', 'name' => 'Resultado', 'datatype' => 'string'];
      $tab[] = ['id' => 5, 'table' => self::getTable(), 'field' => 'client_ip', 'name' => 'IP de origem', 'datatype' => 'string'];
      $tab[] = ['id' => 6, 'table' => self::getTable(), 'field' => 'recorded_at', 'name' => 'Data', 'datatype' => 'datetime'];

      return $tab;
   }

   public static function countAll(): int
   {
      global $DB;

      if (!$DB->tableExists(self::getTable())) {
         return 0;
      }

      $row = $DB->request(['COUNT' => 'cpt', 'FROM' => self::getTable()])->current();

      return (int)($row['cpt'] ?? 0);
   }

   /**
    * @return array<int, array<string, mixed>>
    */
   public static function getRecent(int $limit = 50): array
   {
      global $DB;

      $rows = [];
      if (!$DB->tableExists(self::getTable())) {
         return $rows;
      }

      foreach ($DB->request([
         'FROM'  => self::getTable(),
         'ORDER' => ['recorded_at DESC', 'id DESC'],
         'LIMIT' => max(1, min(500, $limit)),
      ]) as $row) {
         $rows[] = $row;
      }

      return $rows;
   }
}
