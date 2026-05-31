<?php

namespace GlpiPlugin\Zscaler;

/**
 * Aba "Zscaler" em Tickets e Computadores com o botao de acao manual
 * ("bloquear dominio na Zscaler"), com a identidade visual do plugin.
 */
class ItemAction extends \CommonGLPI
{
   public static $rightname = 'plugin_zscaler_read';

   public static function getTypeName($nb = 0): string
   {
      return 'Zscaler';
   }

   public function getTabNameForItem(\CommonGLPI $item, $withtemplate = 0): string
   {
      if (($item instanceof \Ticket || $item instanceof \Computer) && Profile::hasReadRight()) {
         return 'Zscaler';
      }

      return '';
   }

   public static function displayTabContentForItem(\CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
   {
      if (!$item instanceof \Ticket && !$item instanceof \Computer) {
         return false;
      }

      global $CFG_GLPI;
      $root = (string)($CFG_GLPI['root_doc'] ?? '');
      $h = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

      $config = Config::getConfig();
      $actionsOn = Config::actionsEnabled($config) && Profile::hasActionRight();
      $itemtype = $item->getType();
      $itemsId = (int)$item->getID();
      $ticketId = $item instanceof \Ticket ? $itemsId : 0;

      echo "<div class='zscaler-itemaction'>";
      echo "<div class='zscaler-itemaction__head'>";
      echo "<span class='zs-logo zs-logo--solid'><span class='ti ti-cloud-lock'></span></span>";
      echo "<div><h3>Zscaler &middot; Acao rapida</h3><p>Bloquear um dominio/URL na denylist do Zscaler Internet Access.</p></div>";
      echo "</div>";

      if (!Config::isConfigured($config)) {
         echo "<div class='zscaler-banner zscaler-banner--warn'><span class='ti ti-alert-triangle'></span><div>Integracao Zscaler nao configurada.</div></div>";
         echo "</div>";
         return true;
      }

      if (!$actionsOn) {
         echo "<div class='zscaler-banner zscaler-banner--info'><span class='ti ti-eye'></span><div><strong>Acoes desativadas.</strong> Para liberar, ajuste as travas em Configuracao &gt; Zscaler e garanta o direito \"Acoes\".</div></div>";
      }

      echo "<form method='post' action='" . $h($root . '/plugins/zscaler/front/action.form.php') . "'>";
      echo \Html::hidden('source_itemtype', ['value' => $itemtype]);
      echo \Html::hidden('source_items_id', ['value' => $itemsId]);
      echo \Html::hidden('source_tickets_id', ['value' => $ticketId]);
      echo "<div class='zscaler-fields'>";
      echo "<label class='zscaler-field zscaler-field--wide'>";
      echo "<span>URL / dominio a bloquear</span>";
      echo "<input class='form-control' type='text' name='urls' placeholder='ex.: malicioso.com' " . ($actionsOn ? '' : 'disabled') . ">";
      echo "<small>Sera adicionado a denylist do ZIA e a mudanca sera ativada na console.</small>";
      echo "</label>";
      echo "</div>";
      echo "<div class='zscaler-toolbar'>";
      echo "<button class='btn btn-danger' type='submit' name='do' value='block'" . ($actionsOn ? '' : ' disabled') . " onclick=\"return confirm('Bloquear esta URL na denylist do Zscaler?');\"><span class='ti ti-ban'></span> Bloquear na Zscaler</button>";
      echo "</div>";
      \Html::closeForm();

      // Acoes recentes vinculadas a este item
      self::renderRecentForItem($itemtype, $itemsId, $h);

      echo "</div>";

      return true;
   }

   private static function renderRecentForItem(string $itemtype, int $itemsId, callable $h): void
   {
      global $DB;

      $table = ActionLog::getTable();
      if (!$DB->tableExists($table)) {
         return;
      }

      $rows = [];
      foreach ($DB->request([
         'FROM'  => $table,
         'WHERE' => ['source_itemtype' => $itemtype, 'source_items_id' => $itemsId],
         'ORDER' => ['id DESC'],
         'LIMIT' => 5,
      ]) as $row) {
         $rows[] = $row;
      }

      if ($rows === []) {
         return;
      }

      echo "<div class='table-responsive mt-3'><table class='table table-vcenter mb-0'>";
      echo "<thead><tr><th>Acao</th><th>Alvo</th><th>Status</th><th>Data</th></tr></thead><tbody>";
      foreach ($rows as $row) {
         $badge = (string)$row['status'] === 'error' ? 'zs-badge--error' : 'zs-badge--ok';
         echo "<tr>";
         echo "<td>" . $h((string)$row['action']) . "</td>";
         echo "<td class='zscaler-break'>" . $h((string)($row['target'] ?? '-')) . "</td>";
         echo "<td><span class='zs-badge {$badge}'>" . $h((string)$row['status']) . "</span></td>";
         echo "<td>" . $h((string)($row['date_creation'] ?? '')) . "</td>";
         echo "</tr>";
      }
      echo "</tbody></table></div>";
   }
}
