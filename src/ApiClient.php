<?php

namespace GlpiPlugin\Zscaler;

/**
 * Cliente HTTP para a API do Zscaler Internet Access (ZIA).
 *
 * Suporta os dois esquemas de autenticacao:
 *  - OneAPI (Zidentity / OAuth2 client_credentials) -> Bearer token.
 *  - Legado (API key ofuscada + usuario/senha)       -> cookie JSESSIONID.
 *
 * O token/cookie e cacheado na tabela glpi_plugin_zscaler_tokens para evitar
 * reautenticar a cada chamada e para funcionar no cron (sem $_SESSION).
 */
class ApiClient
{
   public const MODE_ONEAPI = 'oneapi';
   public const MODE_LEGACY = 'legacy';

   private string $authMode;
   private string $cloud;
   private int $timeout;

   // OneAPI
   private string $vanityDomain;
   private string $clientId;
   private string $clientSecret;
   private string $oneapiApiBase;

   // Legado
   private string $apiKey;
   private string $username;
   private string $password;

   // Sandbox submission (token dedicado)
   private string $sandboxToken;

   private ?string $sessionCredential = null;

   public function __construct(array $config)
   {
      $this->authMode = ((string)($config['auth_mode'] ?? self::MODE_ONEAPI) === self::MODE_LEGACY)
         ? self::MODE_LEGACY
         : self::MODE_ONEAPI;
      $this->cloud = self::cleanCloud((string)($config['cloud'] ?? 'zscaler.net'));
      $this->timeout = max(5, min(120, (int)($config['timeout'] ?? 30)));

      $this->vanityDomain = trim((string)($config['vanity_domain'] ?? ''));
      $this->clientId = trim((string)($config['client_id'] ?? ''));
      $this->clientSecret = (string)($config['client_secret'] ?? '');
      $this->oneapiApiBase = rtrim(trim((string)($config['oneapi_api_base'] ?? '')), '/') ?: 'https://api.zsapi.net';

      $this->apiKey = (string)($config['api_key'] ?? '');
      $this->username = trim((string)($config['username'] ?? ''));
      $this->password = (string)($config['password'] ?? '');

      $this->sandboxToken = trim((string)($config['sandbox_token'] ?? ''));
   }

   public static function fromConfig(?array $config = null): self
   {
      return new self($config ?? Config::getConfig());
   }

   public function getAuthMode(): string
   {
      return $this->authMode;
   }

   // ---------------------------------------------------------------------
   // Endpoints ZIA
   // ---------------------------------------------------------------------

   public function testConnection(): array
   {
      // Chamada leve que valida credenciais e conectividade.
      return $this->request('GET', '/urlCategories', ['customOnly' => 'true']);
   }

   /**
    * @return array<int, array<string, mixed>>
    */
   public function getUrlCategories(bool $customOnly = false): array
   {
      $query = $customOnly ? ['customOnly' => 'true'] : [];
      $response = $this->request('GET', '/urlCategories', $query);

      return $this->asList($response);
   }

   /**
    * Consulta a categoria/classificacao de ate 100 URLs.
    *
    * @param string[] $urls
    * @return array<int, array<string, mixed>>
    */
   public function urlLookup(array $urls): array
   {
      $urls = array_values(array_filter(array_map('trim', $urls), static fn($u): bool => $u !== ''));
      if ($urls === []) {
         return [];
      }

      $response = $this->request('POST', '/urlLookup', [], array_slice($urls, 0, 100));

      return $this->asList($response);
   }

   /**
    * @return string[] URLs presentes na denylist (security/advanced).
    */
   public function getDenylist(): array
   {
      $response = $this->request('GET', '/security/advanced');
      $urls = $response['blacklistUrls'] ?? [];

      return is_array($urls) ? array_values(array_map('strval', $urls)) : [];
   }

   /**
    * @param string[] $urls
    */
   public function addToDenylist(array $urls): array
   {
      return $this->modifyDenylist($urls, 'ADD_TO_LIST');
   }

   /**
    * @param string[] $urls
    */
   public function removeFromDenylist(array $urls): array
   {
      return $this->modifyDenylist($urls, 'REMOVE_FROM_LIST');
   }

   /**
    * @param string[] $urls
    */
   public function addUrlsToCategory(string $categoryId, array $urls): array
   {
      $urls = array_values(array_filter(array_map('trim', $urls), static fn($u): bool => $u !== ''));
      if ($categoryId === '' || $urls === []) {
         throw new \RuntimeException('Categoria e URLs sao obrigatorias.');
      }

      return $this->request(
         'PUT',
         '/urlCategories/' . rawurlencode($categoryId),
         ['action' => 'ADD_TO_LIST'],
         ['urls' => $urls]
      );
   }

   public function sandboxReport(string $md5): array
   {
      $md5 = trim($md5);
      if ($md5 === '') {
         throw new \RuntimeException('Hash MD5 obrigatorio.');
      }

      return $this->request('GET', '/sandbox/report/' . rawurlencode($md5), ['details' => 'full']);
   }

   /**
    * Quota diaria de submissoes ao sandbox.
    */
   public function sandboxQuota(): array
   {
      return $this->request('GET', '/sandbox/report/quota');
   }

   /**
    * Submete um arquivo ao Cloud Sandbox (host csbapi dedicado, token proprio).
    * O veredito completo e obtido depois via sandboxReport(md5).
    *
    * @return array<string, mixed>
    */
   public function sandboxSubmit(string $filename, string $content, bool $inspect = true): array
   {
      if ($this->sandboxToken === '') {
         throw new \RuntimeException('Token de submissao do Sandbox nao configurado.');
      }
      if ($content === '') {
         throw new \RuntimeException('Arquivo vazio.');
      }

      $endpoint = $inspect ? 'submit' : 'discan';
      $url = 'https://csbapi.' . $this->cloud . '/zscsb/' . $endpoint
         . '?api_token=' . rawurlencode($this->sandboxToken);

      $result = Http::exec('POST', $url, [
         'Content-Type: application/octet-stream',
         'Accept: application/json',
      ], $content, $this->timeout);

      $out = Http::decode($result['body'], $result['status'], 'Zscaler Sandbox');
      $out['md5'] = strtolower(md5($content));
      $out['filename'] = $filename;

      return $out;
   }

   /**
    * @return array<int, array<string, mixed>>
    */
   public function getUrlFilteringRules(): array
   {
      return $this->asList($this->request('GET', '/urlFilteringRules'));
   }

   /**
    * @return array<int, array<string, mixed>>
    */
   public function getFirewallRules(): array
   {
      return $this->asList($this->request('GET', '/firewallFilteringRules'));
   }

   public function getUrlFilteringRule(int $id): array
   {
      return $this->request('GET', '/urlFilteringRules/' . $id);
   }

   /**
    * Liga/desliga uma regra de URL Filtering (envia a regra completa com o novo state).
    */
   public function setUrlFilteringRuleState(int $id, bool $enabled): array
   {
      $rule = $this->getUrlFilteringRule($id);
      unset($rule['_http_status']);
      $rule['state'] = $enabled ? 'ENABLED' : 'DISABLED';

      return $this->request('PUT', '/urlFilteringRules/' . $id, [], $rule);
   }

   public function getUsers(int $page = 1, int $pageSize = 100): array
   {
      return $this->asList($this->request('GET', '/users', [
         'page'     => max(1, $page),
         'pageSize' => max(1, min(1000, $pageSize)),
      ]));
   }

   public function getLocations(int $page = 1, int $pageSize = 100): array
   {
      return $this->asList($this->request('GET', '/locations', [
         'page'     => max(1, $page),
         'pageSize' => max(1, min(1000, $pageSize)),
      ]));
   }

   /**
    * Ativa as mudancas pendentes na console. Obrigatorio apos qualquer escrita.
    */
   public function activateChanges(): array
   {
      return $this->request('POST', '/status/activate');
   }

   // ---------------------------------------------------------------------
   // Nucleo HTTP / autenticacao
   // ---------------------------------------------------------------------

   /**
    * @param array<string, mixed>      $query
    * @param array<mixed>|null         $body
    * @return array<string, mixed>
    */
   public function request(string $method, string $path, array $query = [], ?array $body = null): array
   {
      $this->ensureAuthenticated();

      $result = $this->send($method, $path, $query, $body);

      // Sessao/token expirado: reautentica uma vez e repete.
      if ($result['status'] === 401) {
         $this->clearCachedCredential();
         $this->sessionCredential = null;
         $this->ensureAuthenticated();
         $result = $this->send($method, $path, $query, $body);
      }

      return $this->decode($result['body'], $result['status']);
   }

   private function modifyDenylist(array $urls, string $action): array
   {
      $urls = array_values(array_filter(array_map('trim', $urls), static fn($u): bool => $u !== ''));
      if ($urls === []) {
         throw new \RuntimeException('Informe ao menos uma URL.');
      }

      return $this->request(
         'PUT',
         '/security/advanced/blacklistUrls',
         ['action' => $action],
         ['blacklistUrls' => $urls]
      );
   }

   private function ensureAuthenticated(): void
   {
      if ($this->sessionCredential !== null) {
         return;
      }

      $cached = $this->loadCachedCredential();
      if ($cached !== null) {
         $this->sessionCredential = $cached;
         return;
      }

      $this->sessionCredential = $this->authMode === self::MODE_LEGACY
         ? $this->authenticateLegacy()
         : $this->authenticateOneApi();
   }

   private function authenticateOneApi(): string
   {
      if ($this->vanityDomain === '' || $this->clientId === '' || $this->clientSecret === '') {
         throw new \RuntimeException('Credenciais OneAPI incompletas (vanity domain, client_id, client_secret).');
      }

      $url = 'https://' . $this->vanityDomain . '.zslogin.net/oauth2/v1/token';
      $payload = http_build_query([
         'grant_type'    => 'client_credentials',
         'client_id'     => $this->clientId,
         'client_secret' => $this->clientSecret,
         'audience'      => 'https://api.zscaler.com',
      ]);

      $result = $this->httpExec('POST', $url, [
         'Accept: application/json',
         'Content-Type: application/x-www-form-urlencoded',
      ], $payload);

      $json = $this->decode($result['body'], $result['status']);
      $token = (string)($json['access_token'] ?? '');
      if ($token === '') {
         throw new \RuntimeException('OneAPI nao retornou access_token.');
      }

      $expiresIn = (int)($json['expires_in'] ?? 3600);
      $this->storeCachedCredential($token, time() + max(60, $expiresIn) - 60);

      return $token;
   }

   private function authenticateLegacy(): string
   {
      if ($this->apiKey === '' || $this->username === '' || $this->password === '') {
         throw new \RuntimeException('Credenciais legadas incompletas (API key, usuario, senha).');
      }

      [$obfuscated, $timestamp] = self::obfuscateApiKey($this->apiKey);

      $url = $this->legacyBase() . '/authenticatedSession';
      $body = json_encode([
         'apiKey'    => $obfuscated,
         'username'  => $this->username,
         'password'  => $this->password,
         'timestamp' => $timestamp,
      ], JSON_UNESCAPED_SLASHES);

      $result = $this->httpExec('POST', $url, [
         'Accept: application/json',
         'Content-Type: application/json',
         'Cache-Control: no-cache',
      ], $body, true);

      if ($result['status'] >= 400) {
         $this->decode($result['body'], $result['status']); // lanca excecao com a mensagem da API
      }

      $jsession = self::extractJSessionId($result['headers']);
      if ($jsession === '') {
         throw new \RuntimeException('Login legado nao retornou cookie JSESSIONID.');
      }

      // Sessao legada expira por inatividade (~30 min). Cacheamos por 25 min.
      $this->storeCachedCredential($jsession, time() + (25 * 60));

      return $jsession;
   }

   /**
    * Algoritmo oficial de ofuscacao da API key legada do ZIA.
    *
    * @return array{0:string,1:int} [chave ofuscada, timestamp em ms]
    */
   public static function obfuscateApiKey(string $seed): array
   {
      $now = (int)round(microtime(true) * 1000);
      $n = substr((string)$now, -6);
      $r = str_pad((string)((int)$n >> 1), 6, '0', STR_PAD_LEFT);

      $key = '';
      $len = strlen($seed);
      for ($i = 0, $c = strlen($n); $i < $c; $i++) {
         $idx = (int)$n[$i];
         $key .= $idx < $len ? $seed[$idx] : '';
      }
      for ($j = 0, $c = strlen($r); $j < $c; $j++) {
         $idx = (int)$r[$j] + 2;
         $key .= $idx < $len ? $seed[$idx] : '';
      }

      return [$key, $now];
   }

   /**
    * Executa a chamada de recurso ja autenticada.
    *
    * @return array{status:int, body:string, headers:array<int,string>}
    */
   private function send(string $method, string $path, array $query, ?array $body): array
   {
      $url = $this->resourceBase() . '/' . ltrim($path, '/');
      if ($query !== []) {
         $url .= '?' . http_build_query($query);
      }

      $headers = [
         'Accept: application/json',
         'Content-Type: application/json',
      ];

      if ($this->authMode === self::MODE_LEGACY) {
         $headers[] = 'Cookie: JSESSIONID=' . $this->sessionCredential;
      } else {
         $headers[] = 'Authorization: Bearer ' . $this->sessionCredential;
      }

      $payload = $body !== null ? json_encode($body, JSON_UNESCAPED_SLASHES) : null;

      return $this->httpExec($method, $url, $headers, $payload);
   }

   /**
    * @param string[] $headers
    * @return array{status:int, body:string, headers:array<int,string>}
    */
   private function httpExec(string $method, string $url, array $headers, ?string $body, bool $returnHeaders = false): array
   {
      if (function_exists('curl_init')) {
         return $this->execCurl($method, $url, $headers, $body, $returnHeaders);
      }

      return $this->execStream($method, $url, $headers, $body);
   }

   /**
    * @param string[] $headers
    * @return array{status:int, body:string, headers:array<int,string>}
    */
   private function execCurl(string $method, string $url, array $headers, ?string $body, bool $returnHeaders): array
   {
      $curl = curl_init($url);
      curl_setopt_array($curl, [
         CURLOPT_CUSTOMREQUEST  => strtoupper($method),
         CURLOPT_HTTPHEADER     => $headers,
         CURLOPT_RETURNTRANSFER => true,
         CURLOPT_HEADER         => $returnHeaders,
         CURLOPT_TIMEOUT        => $this->timeout,
         CURLOPT_SSL_VERIFYPEER => true,
         CURLOPT_SSL_VERIFYHOST => 2,
      ]);

      if ($body !== null) {
         curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
      }

      $raw = curl_exec($curl);
      $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
      $headerSize = (int)curl_getinfo($curl, CURLINFO_HEADER_SIZE);
      $error = curl_error($curl);
      curl_close($curl);

      if ($raw === false) {
         throw new \RuntimeException('Falha HTTP Zscaler: ' . $error);
      }

      $raw = (string)$raw;
      $headerLines = [];
      $responseBody = $raw;

      if ($returnHeaders) {
         $rawHeaders = substr($raw, 0, $headerSize);
         $responseBody = substr($raw, $headerSize);
         $headerLines = preg_split('/\r\n/', $rawHeaders) ?: [];
      }

      return [
         'status'  => $status,
         'body'    => $responseBody,
         'headers' => $headerLines,
      ];
   }

   /**
    * @param string[] $headers
    * @return array{status:int, body:string, headers:array<int,string>}
    */
   private function execStream(string $method, string $url, array $headers, ?string $body): array
   {
      $context = stream_context_create([
         'http' => [
            'method'        => strtoupper($method),
            'header'        => implode("\r\n", $headers),
            'content'       => $body ?? '',
            'timeout'       => $this->timeout,
            'ignore_errors' => true,
         ],
      ]);

      $raw = @file_get_contents($url, false, $context);
      $status = 0;
      $responseHeaders = $http_response_header ?? [];

      foreach ($responseHeaders as $header) {
         if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
            $status = (int)$matches[1];
            break;
         }
      }

      if ($raw === false) {
         throw new \RuntimeException('Falha HTTP Zscaler usando stream wrapper.');
      }

      return [
         'status'  => $status,
         'body'    => (string)$raw,
         'headers' => $responseHeaders,
      ];
   }

   /**
    * @param array<string, mixed> $response
    * @return array<int, array<string, mixed>>
    */
   private function asList(array $response): array
   {
      if (isset($response['_items']) && is_array($response['_items'])) {
         return $response['_items'];
      }

      return [];
   }

   /**
    * Decodifica a resposta JSON e normaliza listas no formato {_items, _http_status}.
    *
    * @return array<string, mixed>
    */
   private function decode(string $raw, int $status): array
   {
      $raw = trim($raw);
      $json = $raw === '' ? [] : json_decode($raw, true);

      if (!is_array($json)) {
         if ($status >= 400) {
            throw new \RuntimeException('Erro Zscaler HTTP ' . $status . '.');
         }
         // Resposta nao-JSON em sucesso (ex.: 204 do activate).
         $json = [];
      }

      if ($status >= 400) {
         $message = $json['message']
            ?? $json['error']
            ?? $json['errors'][0]['message']
            ?? ('Erro HTTP ' . $status);
         throw new \RuntimeException('Erro Zscaler: ' . (is_string($message) ? $message : ('HTTP ' . $status)));
      }

      $out = array_is_list($json) ? ['_items' => $json] : $json;
      $out['_http_status'] = $status;

      return $out;
   }

   // ---------------------------------------------------------------------
   // Bases de URL
   // ---------------------------------------------------------------------

   private function resourceBase(): string
   {
      return $this->authMode === self::MODE_LEGACY
         ? $this->legacyBase()
         : $this->oneapiApiBase . '/zia/api/v1';
   }

   private function legacyBase(): string
   {
      return 'https://zsapi.' . $this->cloud . '/api/v1';
   }

   private static function cleanCloud(string $cloud): string
   {
      $cloud = strtolower(trim($cloud));
      $cloud = preg_replace('#^https?://#', '', $cloud) ?? $cloud;
      $cloud = preg_replace('#^(zsapi|admin)\.#', '', $cloud) ?? $cloud;

      return trim($cloud, '/ ') ?: 'zscaler.net';
   }

   /**
    * @param array<int,string> $headers
    */
   private static function extractJSessionId(array $headers): string
   {
      foreach ($headers as $header) {
         if (stripos($header, 'set-cookie:') === 0 && preg_match('/JSESSIONID=([^;]+)/i', $header, $m)) {
            return trim($m[1]);
         }
      }

      return '';
   }

   // ---------------------------------------------------------------------
   // Cache de credencial (tabela glpi_plugin_zscaler_tokens)
   // ---------------------------------------------------------------------

   private function loadCachedCredential(): ?string
   {
      global $DB;

      $table = 'glpi_plugin_zscaler_tokens';
      if (!isset($DB) || !$DB->tableExists($table)) {
         return null;
      }

      foreach ($DB->request([
         'FROM'  => $table,
         'WHERE' => ['auth_mode' => $this->authMode],
         'LIMIT' => 1,
      ]) as $row) {
         if ((int)$row['expires_at'] > time() && trim((string)$row['token']) !== '') {
            return (string)$row['token'];
         }
      }

      return null;
   }

   private function storeCachedCredential(string $token, int $expiresAt): void
   {
      global $DB;

      $table = 'glpi_plugin_zscaler_tokens';
      if (!isset($DB) || !$DB->tableExists($table)) {
         return;
      }

      $existing = $DB->request([
         'SELECT' => ['id'],
         'FROM'   => $table,
         'WHERE'  => ['auth_mode' => $this->authMode],
         'LIMIT'  => 1,
      ])->current();

      $data = [
         'token'      => $token,
         'expires_at' => $expiresAt,
         'date_mod'   => date('Y-m-d H:i:s'),
      ];

      if ($existing) {
         $DB->update($table, $data, ['id' => (int)$existing['id']]);
      } else {
         $DB->insert($table, $data + ['auth_mode' => $this->authMode]);
      }
   }

   private function clearCachedCredential(): void
   {
      global $DB;

      $table = 'glpi_plugin_zscaler_tokens';
      if (isset($DB) && $DB->tableExists($table)) {
         $DB->delete($table, ['auth_mode' => $this->authMode]);
      }
   }
}
