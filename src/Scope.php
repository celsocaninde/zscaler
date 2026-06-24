<?php

namespace GlpiPlugin\Zscaler;

/**
 * Escopo do Zscaler: o Client Connector roda apenas em workstations.
 *
 * Esta classe decide quais Computadores do GLPI estao no escopo (workstations)
 * e quais sao VMs (fora de escopo). A deteccao COMBINA Tipo e Modelo: a maquina
 * e tratada como VM se o nome do Tipo OU do Modelo bater em uma palavra-chave
 * virtual. As listas de palavras-chave sao configuraveis (Configuracao > Zscaler).
 */
class Scope
{
   public const DEFAULT_TYPE_KEYWORDS  = 'virtual,vm,maquina virtual,máquina virtual';
   public const DEFAULT_MODEL_KEYWORDS = 'virtual,vmware,virtualbox,hyper-v,hyperv,kvm,qemu,xen,parallels';

   /** @return string[] palavras-chave (minusculas) que marcam um Tipo como VM */
   public static function typeKeywords(?array $config = null): array
   {
      $config ??= Config::getConfig();
      return self::splitKeywords((string)($config['vm_type_keywords'] ?? self::DEFAULT_TYPE_KEYWORDS));
   }

   /** @return string[] palavras-chave (minusculas) que marcam um Modelo como VM */
   public static function modelKeywords(?array $config = null): array
   {
      $config ??= Config::getConfig();
      return self::splitKeywords((string)($config['vm_model_keywords'] ?? self::DEFAULT_MODEL_KEYWORDS));
   }

   /** @return string[] */
   private static function splitKeywords(string $raw): array
   {
      $parts = array_map(
         static fn(string $s): string => self::lower(trim($s)),
         explode(',', $raw)
      );
      $parts = array_filter($parts, static fn(string $s): bool => $s !== '');

      return array_values(array_unique($parts));
   }

   private static function lower(string $value): string
   {
      return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
   }

   private static function contains(string $haystack, string $needle): bool
   {
      if ($needle === '') {
         return false;
      }
      $haystack = self::lower($haystack);

      return function_exists('mb_strpos')
         ? mb_strpos($haystack, $needle) !== false
         : strpos($haystack, $needle) !== false;
   }

   /**
    * Postura de escopo de um Computador: se e VM, e por que.
    *
    * @return array{is_virtual:bool, type:?string, model:?string, matched_on:?string, matched_keyword:?string}
    */
   public static function computerScopeInfo(int $computersId): array
   {
      $info = [
         'is_virtual'      => false,
         'type'            => null,
         'model'           => null,
         'matched_on'      => null,
         'matched_keyword' => null,
      ];

      if ($computersId <= 0) {
         return $info;
      }

      $computer = new \Computer();
      if (!$computer->getFromDB($computersId)) {
         return $info;
      }

      $typeId  = (int)($computer->fields['computertypes_id'] ?? 0);
      $modelId = (int)($computer->fields['computermodels_id'] ?? 0);

      $typeName  = $typeId > 0 ? self::dropdownName('glpi_computertypes', $typeId) : null;
      $modelName = $modelId > 0 ? self::dropdownName('glpi_computermodels', $modelId) : null;

      $info['type']  = $typeName;
      $info['model'] = $modelName;

      if ($typeName !== null) {
         foreach (self::typeKeywords() as $kw) {
            if (self::contains($typeName, $kw)) {
               return ['is_virtual' => true, 'type' => $typeName, 'model' => $modelName, 'matched_on' => 'type', 'matched_keyword' => $kw];
            }
         }
      }

      if ($modelName !== null) {
         foreach (self::modelKeywords() as $kw) {
            if (self::contains($modelName, $kw)) {
               return ['is_virtual' => true, 'type' => $typeName, 'model' => $modelName, 'matched_on' => 'model', 'matched_keyword' => $kw];
            }
         }
      }

      return $info;
   }

   /** Conveniencia: o Computador e uma VM (fora do escopo do Zscaler)? */
   public static function isVirtual(int $computersId): bool
   {
      return self::computerScopeInfo($computersId)['is_virtual'];
   }

   /**
    * IDs de Tipos de computador considerados VM. @return array<int,true>
    */
   public static function virtualTypeIds(?array $config = null): array
   {
      return self::idsMatchingKeywords('glpi_computertypes', self::typeKeywords($config));
   }

   /**
    * IDs de Modelos de computador considerados VM. @return array<int,true>
    */
   public static function virtualModelIds(?array $config = null): array
   {
      return self::idsMatchingKeywords('glpi_computermodels', self::modelKeywords($config));
   }

   /**
    * Avalia uma linha de glpi_computers (com computertypes_id/computermodels_id)
    * contra os conjuntos pre-computados de IDs virtuais.
    *
    * @param array<int,true> $virtualTypeIds
    * @param array<int,true> $virtualModelIds
    */
   public static function isVirtualRow(array $computerRow, array $virtualTypeIds, array $virtualModelIds): bool
   {
      $typeId  = (int)($computerRow['computertypes_id'] ?? 0);
      $modelId = (int)($computerRow['computermodels_id'] ?? 0);

      return ($typeId > 0 && isset($virtualTypeIds[$typeId]))
         || ($modelId > 0 && isset($virtualModelIds[$modelId]));
   }

   /**
    * @param string[] $keywords
    * @return array<int,true>
    */
   private static function idsMatchingKeywords(string $table, array $keywords): array
   {
      global $DB;

      $ids = [];
      if ($keywords === [] || !$DB->tableExists($table)) {
         return $ids;
      }

      $or = [];
      foreach ($keywords as $kw) {
         $or[] = ['name' => ['LIKE', '%' . $kw . '%']];
      }

      foreach ($DB->request([
         'SELECT' => ['id'],
         'FROM'   => $table,
         'WHERE'  => count($or) === 1 ? $or[0] : ['OR' => $or],
      ]) as $row) {
         $ids[(int)$row['id']] = true;
      }

      return $ids;
   }

   private static function dropdownName(string $table, int $id): ?string
   {
      $name = \Dropdown::getDropdownName($table, $id);
      $name = trim(str_replace('&nbsp;', '', (string)$name));

      return $name !== '' ? $name : null;
   }
}
