<?php

namespace GlpiPlugin\Zscaler;

/**
 * Cache simples de token/cookie por modo de autenticacao
 * (tabela glpi_plugin_zscaler_tokens), compartilhado pelos clientes ZCC/ZDX.
 */
class TokenCache
{
   private const TABLE = 'glpi_plugin_zscaler_tokens';

   public static function load(string $mode): ?string
   {
      global $DB;

      if (!isset($DB) || !$DB->tableExists(self::TABLE)) {
         return null;
      }

      foreach ($DB->request([
         'FROM'  => self::TABLE,
         'WHERE' => ['auth_mode' => $mode],
         'LIMIT' => 1,
      ]) as $row) {
         if ((int)$row['expires_at'] > time() && trim((string)$row['token']) !== '') {
            return (string)$row['token'];
         }
      }

      return null;
   }

   public static function store(string $mode, string $token, int $expiresAt): void
   {
      global $DB;

      if (!isset($DB) || !$DB->tableExists(self::TABLE)) {
         return;
      }

      $existing = $DB->request([
         'SELECT' => ['id'],
         'FROM'   => self::TABLE,
         'WHERE'  => ['auth_mode' => $mode],
         'LIMIT'  => 1,
      ])->current();

      $data = [
         'token'      => $token,
         'expires_at' => $expiresAt,
         'date_mod'   => date('Y-m-d H:i:s'),
      ];

      if ($existing) {
         $DB->update(self::TABLE, $data, ['id' => (int)$existing['id']]);
      } else {
         $DB->insert(self::TABLE, $data + ['auth_mode' => $mode]);
      }
   }

   public static function clear(string $mode): void
   {
      global $DB;

      if (isset($DB) && $DB->tableExists(self::TABLE)) {
         $DB->delete(self::TABLE, ['auth_mode' => $mode]);
      }
   }
}
