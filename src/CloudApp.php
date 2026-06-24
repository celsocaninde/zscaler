<?php

namespace GlpiPlugin\Zscaler;

/**
 * Aplicacao de nuvem descoberta/conhecida pelo Cloud App Control (Shadow IT).
 */
class CloudApp extends \CommonDBTM
{
   public static $rightname = 'plugin_zscaler_read';

   public static function getTypeName($nb = 0): string
   {
      return $nb > 1 ? 'Apps de nuvem (Shadow IT)' : 'App de nuvem';
   }

   public static function getIcon(): string
   {
      return 'ti ti-cloud-data-connection';
   }

   public function rawSearchOptions(): array
   {
      $tab = [];

      $tab[] = ['id' => 'common', 'name' => self::getTypeName(2)];
      $tab[] = ['id' => 1, 'table' => self::getTable(), 'field' => 'name', 'name' => 'Aplicacao', 'datatype' => 'string'];
      $tab[] = ['id' => 2, 'table' => self::getTable(), 'field' => 'category', 'name' => 'Categoria', 'datatype' => 'string'];
      $tab[] = ['id' => 3, 'table' => self::getTable(), 'field' => 'risk_index', 'name' => 'Indice de risco', 'datatype' => 'string'];
      $tab[] = ['id' => 4, 'table' => self::getTable(), 'field' => 'sanctioned', 'name' => 'Sancionada', 'datatype' => 'string'];
      $tab[] = ['id' => 5, 'table' => self::getTable(), 'field' => 'tickets_id', 'name' => 'Ticket', 'datatype' => 'number'];
      $tab[] = ['id' => 6, 'table' => self::getTable(), 'field' => 'date_mod', 'name' => 'Atualizado', 'datatype' => 'datetime'];

      return $tab;
   }

   /**
    * Normaliza o indice de risco textual para uma classe de badge.
    *
    * @return array{0:string,1:string}
    */
   public static function riskBadge(?string $risk): array
   {
      $risk = strtolower(trim((string)$risk));

      return match ($risk) {
         'high', 'alto', 'critical', 'critica' => [ucfirst($risk), 'zs-badge--error'],
         'medium', 'media', 'moderate'         => [ucfirst($risk), 'zs-badge--warn'],
         'low', 'baixo', ''                    => [$risk !== '' ? ucfirst($risk) : '-', 'zs-badge--ok'],
         default                               => [ucfirst($risk), 'zs-badge--ok'],
      };
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
    * Lista apps priorizando os de risco mais alto.
    *
    * @return array<int, array<string, mixed>>
    */
   public static function getRecent(int $limit = 200): array
   {
      global $DB;

      $rows = [];
      if (!$DB->tableExists(self::getTable())) {
         return $rows;
      }

      foreach ($DB->request([
         'FROM'  => self::getTable(),
         'ORDER' => ['risk_index DESC', 'name ASC'],
         'LIMIT' => max(1, min(500, $limit)),
      ]) as $row) {
         $rows[] = $row;
      }

      return $rows;
   }

   public static function countRisky(): int
   {
      global $DB;

      if (!$DB->tableExists(self::getTable())) {
         return 0;
      }

      $row = $DB->request([
         'COUNT' => 'cpt',
         'FROM'  => self::getTable(),
         'WHERE' => ['risk_index' => ['HIGH', 'high', 'High', 'CRITICAL', 'critical', 'Critical']],
      ])->current();

      return (int)($row['cpt'] ?? 0);
   }
}
