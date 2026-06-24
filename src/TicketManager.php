<?php

namespace GlpiPlugin\Zscaler;

class TicketManager
{
   private const ZS_BLUE = '#0648A8';
   private const ZS_CYAN = '#00B2E3';
   private const ZS_DARK = '#0A1E3F';

   /**
    * Cria um ticket de trilha de auditoria para uma acao de escrita.
    *
    * @param string[] $urls
    */
   public static function createForAction(string $action, array $urls, ?array $config = null, array $source = []): int
   {
      $config ??= Config::getConfig();
      $ticket = new \Ticket();
      $type = defined('Ticket::INCIDENT_TYPE') ? \Ticket::INCIDENT_TYPE : 1;

      $input = [
         'name'        => self::actionTitle($action, $urls),
         'content'     => self::actionContent($action, $urls, $config),
         'entities_id' => (int)($config['entity_id'] ?? 0),
         'type'        => $type,
         'urgency'     => self::scale($config['ticket_urgency'] ?? 4, 1, 5),
         'impact'      => self::scale($config['ticket_impact'] ?? 4, 1, 5),
         'priority'    => self::scale($config['ticket_priority'] ?? 4, 1, 6),
      ];

      $categoryId = (int)($config['ticket_category_id'] ?? 0);
      if ($categoryId > 0) {
         $input['itilcategories_id'] = $categoryId;
      }

      self::applyRequester($input, $config);

      $ticketId = $ticket->add($input);
      if (!$ticketId) {
         throw new \RuntimeException('Nao foi possivel criar ticket GLPI para a acao Zscaler.');
      }

      $itemtype = (string)($source['itemtype'] ?? '');
      $itemsId = (int)($source['items_id'] ?? 0);
      if ($itemtype !== '' && $itemsId > 0) {
         self::linkItem((int)$ticketId, $itemtype, $itemsId);
      }

      return (int)$ticketId;
   }

   /**
    * Acrescenta um acompanhamento ao ticket de origem quando a acao foi
    * disparada a partir de um chamado existente.
    *
    * @param string[] $urls
    */
   public static function addActionFollowup(int $ticketId, string $action, array $urls, string $summary): void
   {
      if ($ticketId <= 0 || !class_exists(\ITILFollowup::class)) {
         return;
      }

      $followup = new \ITILFollowup();
      $followup->add([
         'itemtype' => 'Ticket',
         'items_id' => $ticketId,
         'content'  => self::actionContent($action, $urls, Config::getConfig(), $summary),
      ]);
   }

   /**
    * Cria um ticket a partir de um veredito malicioso da sandbox.
    */
   public static function createForSandbox(string $md5, array $report, ?array $config = null): int
   {
      $config ??= Config::getConfig();
      $ticket = new \Ticket();
      $type = defined('Ticket::INCIDENT_TYPE') ? \Ticket::INCIDENT_TYPE : 1;

      $input = [
         'name'        => '[Zscaler] Arquivo malicioso detectado (sandbox): ' . self::short($md5, 40),
         'content'     => self::sandboxContent($md5, $report),
         'entities_id' => (int)($config['entity_id'] ?? 0),
         'type'        => $type,
         'urgency'     => self::scale($config['ticket_urgency'] ?? 4, 1, 5),
         'impact'      => self::scale($config['ticket_impact'] ?? 4, 1, 5),
         'priority'    => self::scale($config['ticket_priority'] ?? 4, 1, 6),
      ];

      $categoryId = (int)($config['ticket_category_id'] ?? 0);
      if ($categoryId > 0) {
         $input['itilcategories_id'] = $categoryId;
      }

      self::applyRequester($input, $config);

      $ticketId = $ticket->add($input);
      if (!$ticketId) {
         throw new \RuntimeException('Nao foi possivel criar ticket GLPI para o veredito de sandbox Zscaler.');
      }

      return (int)$ticketId;
   }

   /**
    * Cria um ticket a partir de um alerta do ZDX.
    *
    * @param array<string,mixed> $alert
    * @param array<string,mixed> $raw
    */
   public static function createForZdxAlert(array $alert, array $raw, ?array $config = null): int
   {
      $config ??= Config::getConfig();
      $ticket = new \Ticket();
      $type = defined('Ticket::INCIDENT_TYPE') ? \Ticket::INCIDENT_TYPE : 1;

      $rule = self::value($alert['rule_name'] ?? null);
      $app = self::value($alert['application'] ?? null);

      $input = [
         'name'        => '[Zscaler ZDX] ' . self::short($rule, 60) . ' - ' . self::short($app, 40),
         'content'     => self::zdxContent($alert),
         'entities_id' => (int)($config['entity_id'] ?? 0),
         'type'        => $type,
         'urgency'     => self::scale($config['ticket_urgency'] ?? 4, 1, 5),
         'impact'      => self::scale($config['ticket_impact'] ?? 4, 1, 5),
         'priority'    => self::scale($config['ticket_priority'] ?? 4, 1, 6),
      ];

      $categoryId = (int)($config['ticket_category_id'] ?? 0);
      if ($categoryId > 0) {
         $input['itilcategories_id'] = $categoryId;
      }

      self::applyRequester($input, $config);

      $ticketId = $ticket->add($input);
      if (!$ticketId) {
         throw new \RuntimeException('Nao foi possivel criar ticket GLPI para o alerta ZDX.');
      }

      return (int)$ticketId;
   }

   /**
    * Cria um ticket para uma aplicacao de nuvem de risco (Shadow IT).
    *
    * @param array<string,mixed> $raw
    */
   public static function createForCloudApp(string $appName, array $raw, ?array $config = null): int
   {
      $config ??= Config::getConfig();
      $ticket = new \Ticket();
      $type = defined('Ticket::INCIDENT_TYPE') ? \Ticket::INCIDENT_TYPE : 1;

      $input = [
         'name'        => '[Zscaler Shadow IT] App de risco detectado: ' . self::short($appName, 60),
         'content'     => self::cloudAppContent($appName, $raw),
         'entities_id' => (int)($config['entity_id'] ?? 0),
         'type'        => $type,
         'urgency'     => self::scale($config['ticket_urgency'] ?? 4, 1, 5),
         'impact'      => self::scale($config['ticket_impact'] ?? 4, 1, 5),
         'priority'    => self::scale($config['ticket_priority'] ?? 4, 1, 6),
      ];

      $categoryId = (int)($config['ticket_category_id'] ?? 0);
      if ($categoryId > 0) {
         $input['itilcategories_id'] = $categoryId;
      }

      self::applyRequester($input, $config);

      $ticketId = $ticket->add($input);
      if (!$ticketId) {
         throw new \RuntimeException('Nao foi possivel criar ticket GLPI para a aplicacao de nuvem.');
      }

      return (int)$ticketId;
   }

   /**
    * @param array<string,mixed> $raw
    */
   private static function cloudAppContent(string $appName, array $raw): string
   {
      $risk = self::value($raw['riskIndex'] ?? $raw['risk'] ?? $raw['riskLevel'] ?? null);
      $accent = self::severityColor($risk);

      $body = self::badge('Shadow IT', self::ZS_CYAN);
      $body .= self::badge('Risco: ' . $risk, $accent);
      $body .= self::kvTable([
         ['Aplicacao', $appName],
         ['Categoria', $raw['category'] ?? $raw['appCategory'] ?? null],
         ['Indice de risco', $risk],
         ['Sancionada', $raw['sanctioned'] ?? $raw['sanctionedState'] ?? null],
         ['Data', date('Y-m-d H:i:s')],
      ]);
      $body .= self::footerNote("\u{1F916} Ticket automatico do plugin Zscaler (Shadow IT / Cloud App Control).");

      return self::htmlCard('Zscaler Shadow IT', self::value($appName), 'Aplicacao de nuvem de risco', $body, $accent);
   }

   private static function zdxContent(array $alert): string
   {
      $severity = (string)($alert['severity'] ?? '');
      $accent = self::severityColor($severity);

      $body = self::badge('Alerta ZDX', self::ZS_CYAN);
      if ($severity !== '') {
         $body .= self::badge('Severidade: ' . ucfirst($severity), $accent);
      }
      $body .= self::kvTable([
         ['Regra', $alert['rule_name'] ?? null],
         ['Aplicacao', $alert['application'] ?? null],
         ['Dispositivos impactados', (string)(int)($alert['num_devices'] ?? 0)],
         ['Inicio', $alert['started_on'] ?? null],
      ]);
      $body .= self::footerNote("\u{1F916} Ticket automatico do plugin Zscaler (alerta ZDX).");

      return self::htmlCard('Zscaler ZDX', self::value($alert['rule_name'] ?? null), self::value($alert['application'] ?? null), $body, $accent);
   }

   private static function severityColor(string $severity): string
   {
      return match (strtolower(trim($severity))) {
         'critical', 'critica' => '#c81e1e',
         'high', 'alta'        => '#e8590c',
         'medium', 'media'     => '#f08c00',
         default               => self::ZS_BLUE,
      };
   }

   // ---------------------------------------------------------------------

   private static function actionTitle(string $action, array $urls): string
   {
      $label = match ($action) {
         'denylist_add'      => 'URL bloqueada',
         'denylist_remove'   => 'URL desbloqueada',
         'category_add_urls' => 'URL recategorizada',
         default             => 'Acao executada',
      };

      return '[Zscaler] ' . $label . ': ' . self::short(self::summarize($urls), 70);
   }

   private static function actionContent(string $action, array $urls, array $config, ?string $summary = null): string
   {
      $label = match ($action) {
         'denylist_add'      => 'Bloqueio de URL (denylist)',
         'denylist_remove'   => 'Remocao de URL da denylist',
         'category_add_urls' => 'Adicao de URL a categoria customizada',
         default             => 'Acao Zscaler',
      };

      $urlList = '';
      foreach (array_slice($urls, 0, 50) as $url) {
         $urlList .= "<li style=\"margin:0 0 4px\">\u{1F517} " . self::esc($url) . "</li>";
      }
      if ($urlList === '') {
         $urlList = "<li>-</li>";
      }

      $user = self::currentUserName();
      $body = self::badge($label, self::ZS_BLUE);
      $body .= self::badge('Cloud: ' . self::value($config['cloud'] ?? ''), self::ZS_DARK);
      if ($summary !== null && $summary !== '') {
         $body .= "<p style=\"margin:14px 0 6px;color:" . self::ZS_DARK . ";font-weight:600\">" . self::esc($summary) . "</p>";
      }
      $body .= "<p style=\"margin:14px 0 6px;font-weight:600;color:" . self::ZS_DARK . "\">\u{1F310} URLs</p>";
      $body .= "<ul style=\"margin:0;padding-left:20px;font-size:14px\">{$urlList}</ul>";
      $body .= self::kvTable([
         ['Executado por', $user],
         ['Quantidade', (string)count($urls)],
         ['Data', date('Y-m-d H:i:s')],
      ]);
      $body .= self::footerNote("\u{1F916} Registro automatico do plugin Zscaler (trilha de auditoria de acoes).");

      return self::htmlCard('Zscaler', $label, self::summarize($urls), $body);
   }

   private static function sandboxContent(string $md5, array $report): string
   {
      $details = $report['Full Details'] ?? $report['fullDetails'] ?? $report;
      $type = self::value($details['Classification']['Type'] ?? $details['classification']['type'] ?? null);
      $score = self::value($details['Classification']['Score'] ?? $details['classification']['score'] ?? null);

      $body = self::badge('Veredito de sandbox', '#c81e1e');
      $body .= self::kvTable([
         ['MD5', $md5],
         ['Classificacao', $type],
         ['Score', $score],
         ['Data', date('Y-m-d H:i:s')],
      ]);
      $body .= self::footerNote("\u{1F916} Ticket automatico do plugin Zscaler (sandbox).");

      return self::htmlCard('Zscaler', 'Arquivo malicioso', $md5, $body, '#c81e1e');
   }

   private static function linkItem(int $ticketId, string $itemtype, int $itemsId): void
   {
      if (!class_exists(\Item_Ticket::class)) {
         return;
      }

      try {
         $link = new \Item_Ticket();
         $link->add([
            'tickets_id'    => $ticketId,
            'itemtype'      => $itemtype,
            'items_id'      => $itemsId,
            '_disablenotif' => true,
         ]);
      } catch (\Throwable $error) {
         // vinculo e opcional
      }
   }

   private static function applyRequester(array &$input, array $config): void
   {
      $requesterId = (int)($config['ticket_requester_id'] ?? 0);
      if ($requesterId <= 0) {
         return;
      }

      $input['_users_id_requester'] = $requesterId;
      $input['users_id_recipient']  = $requesterId;
   }

   private static function currentUserName(): string
   {
      $name = trim((string)($_SESSION['glpiname'] ?? ''));

      return $name !== '' ? $name : 'sistema';
   }

   private static function summarize(array $urls): string
   {
      $first = (string)($urls[0] ?? '');
      $extra = count($urls) - 1;

      return $extra > 0 ? ($first . ' (+' . $extra . ')') : $first;
   }

   private static function scale($value, int $min, int $max): int
   {
      return max($min, min($max, (int)$value));
   }

   private static function short(string $value, int $length): string
   {
      if (mb_strlen($value) <= $length) {
         return $value;
      }

      return mb_substr($value, 0, $length - 3) . '...';
   }

   private static function value($value): string
   {
      $value = trim((string)$value);

      return $value !== '' ? $value : '-';
   }

   private static function esc(string $value): string
   {
      return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
   }

   private static function htmlCard(string $eyebrow, string $title, $subtitle, string $bodyHtml, string $accent = self::ZS_BLUE): string
   {
      $header = "<div style=\"background:{$accent};background:linear-gradient(120deg," . self::ZS_DARK . " 0%,{$accent} 60%," . self::ZS_CYAN . " 100%);color:#ffffff;padding:16px 20px\">"
         . "<div style=\"font-size:12px;letter-spacing:.10em;text-transform:uppercase;opacity:.85\">" . self::esc($eyebrow) . "</div>"
         . "<div style=\"font-size:18px;font-weight:700;margin-top:2px\">" . self::esc($title) . "</div>"
         . "<div style=\"font-size:14px;margin-top:4px;opacity:.95;word-break:break-all\">" . self::esc(self::value($subtitle)) . "</div>"
         . "</div>";

      $body = "<div style=\"padding:16px 20px;background:#ffffff;color:#1c2330\">{$bodyHtml}</div>";

      return "<div style=\"font-family:'Segoe UI',Roboto,Arial,sans-serif;max-width:680px;border:1px solid #d9e2f2;border-radius:12px;overflow:hidden\">{$header}{$body}</div>";
   }

   private static function badge(string $text, string $bg): string
   {
      return "<span style=\"display:inline-block;padding:4px 12px;border-radius:999px;background:{$bg};color:#ffffff;font-size:12px;font-weight:700;margin:0 6px 6px 0\">"
         . self::esc($text) . "</span>";
   }

   /**
    * @param array<int, array{0:string,1:mixed}> $rows
    */
   private static function kvTable(array $rows): string
   {
      $html = "<table style=\"width:100%;border-collapse:collapse;margin-top:12px;font-size:14px\"><tbody>";
      foreach ($rows as $row) {
         $label = (string)($row[0] ?? '');
         $value = $row[1] ?? null;
         $html .= "<tr>"
            . "<td style=\"padding:8px 10px 8px 0;border-bottom:1px solid #eef2f8;color:#6b7280;width:42%;vertical-align:top\">" . self::esc($label) . "</td>"
            . "<td style=\"padding:8px 0;border-bottom:1px solid #eef2f8;font-weight:600;word-break:break-word\">" . self::esc(self::value($value)) . "</td>"
            . "</tr>";
      }
      $html .= "</tbody></table>";

      return $html;
   }

   private static function footerNote(string $innerHtml): string
   {
      return "<div style=\"margin-top:16px;padding:12px 14px;background:#eef5ff;border-radius:8px;color:" . self::ZS_BLUE . ";font-size:13px;line-height:1.5\">{$innerHtml}</div>";
   }
}
