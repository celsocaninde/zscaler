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

      // Postura do Client Connector + escopo (apenas para Computadores).
      if ($item instanceof \Computer) {
         self::renderComputerPosture($itemsId, $h);
      }

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

   /**
    * Postura Zscaler de um Computador: escopo (workstation x VM) e estado do
    * Client Connector vinculado. Nao depende da API (usa dados ja sincronizados).
    */
   private static function renderComputerPosture(int $computersId, callable $h): void
   {
      $scope = Scope::computerScopeInfo($computersId);

      echo "<div class='zscaler-posture'>";
      echo "<div class='zscaler-posture__head'><span class='ti ti-device-laptop'></span><h4>Postura do Client Connector</h4></div>";

      // VMs ficam fora do escopo: nada de cobrar instalacao do ZCC.
      if ($scope['is_virtual']) {
         $detail = [];
         if ($scope['type'] !== null) {
            $detail[] = 'Tipo: ' . $h((string)$scope['type']);
         }
         if ($scope['model'] !== null) {
            $detail[] = 'Modelo: ' . $h((string)$scope['model']);
         }
         $detailStr = $detail !== [] ? ' (' . implode(' &middot; ', $detail) . ')' : '';
         echo "<div class='zscaler-banner zscaler-banner--info'><span class='ti ti-server-cog'></span><div>";
         echo "<strong>Fora de escopo (VM).</strong> O Zscaler Client Connector roda apenas em workstations" . $detailStr . ".";
         echo "</div></div>";
         echo "</div>";
         return;
      }

      $device = self::findZccDeviceForComputer($computersId);

      if ($device === null) {
         echo "<div class='zscaler-banner zscaler-banner--warn'><span class='ti ti-shield-off'></span><div>";
         echo "<strong>Workstation sem ZCC vinculado.</strong> Nenhum dispositivo do Client Connector casa com este computador. Verifique o enrolamento do agente ou rode a sincronizacao do ZCC.";
         echo "</div></div>";
         echo "</div>";
         return;
      }

      $state = trim((string)($device['registration_state'] ?? ''));
      $isRegistered = $state !== '' && preg_match('/regist|active|enrol/i', $state) === 1;
      $stateBadge = $isRegistered ? 'zs-badge--ok' : 'zs-badge--warn';
      $stateLabel = $state !== '' ? $state : 'desconhecido';

      echo "<div class='zscaler-banner zscaler-banner--ok'><span class='ti ti-shield-check'></span><div>";
      echo "<strong>Protegido pelo Client Connector.</strong> Dispositivo ZCC vinculado a este ativo.";
      echo "</div></div>";

      $rows = [
         ['Registro', "<span class='zs-badge {$stateBadge}'>" . $h($stateLabel) . "</span>"],
         ['Versao ZCC', $h((string)($device['agent_version'] ?? '-'))],
         ['Sistema operacional', $h((string)($device['os_version'] ?? '-'))],
         ['Politica', $h((string)($device['policy_name'] ?? '-'))],
         ['Usuario', $h((string)($device['user'] ?? '-'))],
         ['Ultimo contato', $h((string)($device['last_seen'] ?? '-'))],
      ];

      echo "<div class='table-responsive mt-2'><table class='table table-vcenter mb-0'><tbody>";
      foreach ($rows as [$label, $value]) {
         $value = ($value === '' || $value === '-') ? '<span class="text-muted">-</span>' : $value;
         echo "<tr><th style='width:200px'>" . $h($label) . "</th><td class='zscaler-break'>" . $value . "</td></tr>";
      }
      echo "</tbody></table></div>";

      echo "</div>";
   }

   /**
    * @return array<string,mixed>|null
    */
   private static function findZccDeviceForComputer(int $computersId): ?array
   {
      global $DB;

      $table = ZccDevice::getTable();
      if ($computersId <= 0 || !$DB->tableExists($table)) {
         return null;
      }

      foreach ($DB->request([
         'FROM'  => $table,
         'WHERE' => ['computers_id' => $computersId],
         'ORDER' => ['last_seen DESC', 'id DESC'],
         'LIMIT' => 1,
      ]) as $row) {
         return $row;
      }

      return null;
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
