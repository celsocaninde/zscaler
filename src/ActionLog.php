<?php

namespace GlpiPlugin\Zscaler;

/**
 * Trilha de auditoria das acoes de escrita executadas na console Zscaler.
 */
class ActionLog extends \CommonDBTM
{
   public static $rightname = 'plugin_zscaler_read';

   public static function getTypeName($nb = 0): string
   {
      return $nb > 1 ? 'Acoes Zscaler' : 'Acao Zscaler';
   }

   public static function getIcon(): string
   {
      return 'ti ti-history';
   }

   /**
    * Registra uma acao executada (ou tentada) e devolve o id criado.
    *
    * @param array{action:string,target?:?string,status:string,message?:?string,users_id?:?int,tickets_id?:?int,source_itemtype?:?string,source_items_id?:?int} $data
    */
   public static function record(array $data): int
   {
      global $DB;

      $table = self::getTable();
      if (!$DB->tableExists($table)) {
         return 0;
      }

      $DB->insert($table, [
         'action'          => (string)($data['action'] ?? 'unknown'),
         'target'          => self::nullable($data['target'] ?? null),
         'status'          => (string)($data['status'] ?? 'ok'),
         'message'         => self::nullable($data['message'] ?? null),
         'users_id'        => self::nullableInt($data['users_id'] ?? null),
         'tickets_id'      => self::nullableInt($data['tickets_id'] ?? null),
         'source_itemtype' => self::nullable($data['source_itemtype'] ?? null),
         'source_items_id' => self::nullableInt($data['source_items_id'] ?? null),
         'date_creation'   => date('Y-m-d H:i:s'),
      ]);

      return (int)$DB->insertId();
   }

   public static function getRecent(int $limit = 10): array
   {
      global $DB;

      $rows = [];
      $limit = max(1, min(50, $limit));

      if (!$DB->tableExists(self::getTable())) {
         return $rows;
      }

      foreach ($DB->request([
         'FROM'  => self::getTable(),
         'ORDER' => ['id DESC'],
         'LIMIT' => $limit,
      ]) as $row) {
         $rows[] = $row;
      }

      return $rows;
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
         'field'    => 'action',
         'name'     => 'Acao',
         'datatype' => 'string',
      ];
      $tab[] = [
         'id'       => 2,
         'table'    => self::getTable(),
         'field'    => 'target',
         'name'     => 'Alvo',
         'datatype' => 'string',
      ];
      $tab[] = [
         'id'       => 3,
         'table'    => self::getTable(),
         'field'    => 'status',
         'name'     => 'Status',
         'datatype' => 'string',
      ];
      $tab[] = [
         'id'       => 4,
         'table'    => self::getTable(),
         'field'    => 'tickets_id',
         'name'     => 'Ticket',
         'datatype' => 'number',
      ];
      $tab[] = [
         'id'       => 5,
         'table'    => self::getTable(),
         'field'    => 'date_creation',
         'name'     => 'Data',
         'datatype' => 'datetime',
      ];

      return $tab;
   }

   private static function nullable($value): ?string
   {
      if ($value === null) {
         return null;
      }
      $value = trim((string)$value);

      return $value === '' ? null : $value;
   }

   private static function nullableInt($value): ?int
   {
      if ($value === null || $value === '' || (int)$value <= 0) {
         return null;
      }

      return (int)$value;
   }
}
