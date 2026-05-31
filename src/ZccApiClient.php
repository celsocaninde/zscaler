<?php

namespace GlpiPlugin\Zscaler;

/**
 * Cliente da API do Zscaler Client Connector (ZCC / Mobile Admin Portal).
 *
 * Autenticacao: POST /papi/auth/v1/login com {apiKey, secretKey} -> {jwtToken}.
 * As chamadas seguintes usam o header `auth-token: <jwtToken>`.
 */
class ZccApiClient
{
   private const MODE = 'zcc';

   private string $base;
   private string $apiKey;
   private string $secretKey;
   private int $timeout;
   private ?string $jwt = null;

   public function __construct(array $config)
   {
      $this->base = rtrim(trim((string)($config['zcc_api_base'] ?? '')), '/') ?: 'https://api-mobile.zscaler.net';
      $this->apiKey = trim((string)($config['zcc_api_key'] ?? ''));
      $this->secretKey = (string)($config['zcc_secret_key'] ?? '');
      $this->timeout = max(5, min(120, (int)($config['timeout'] ?? 30)));
   }

   public static function fromConfig(?array $config = null): self
   {
      return new self($config ?? Config::getConfig());
   }

   public function testConnection(): array
   {
      return $this->request('GET', '/papi/public/v1/getDevices', ['page' => 1, 'pageSize' => 1]);
   }

   /**
    * Lista dispositivos inscritos (paginado).
    *
    * @return array<int, array<string, mixed>>
    */
   public function getDevices(int $maxPages = 10, int $pageSize = 500): array
   {
      $items = [];
      $maxPages = max(1, $maxPages);
      $pageSize = max(1, min(1000, $pageSize));

      for ($page = 1; $page <= $maxPages; $page++) {
         $response = $this->request('GET', '/papi/public/v1/getDevices', [
            'page'     => $page,
            'pageSize' => $pageSize,
         ]);
         $batch = $response['_items'] ?? [];
         if (!is_array($batch) || $batch === []) {
            break;
         }
         $items = array_merge($items, $batch);
         if (count($batch) < $pageSize) {
            break;
         }
      }

      return $items;
   }

   /**
    * @return array<string, mixed>
    */
   public function request(string $method, string $path, array $query = []): array
   {
      $this->ensureAuthenticated();

      $result = $this->send($method, $path, $query);
      if ($result['status'] === 401) {
         TokenCache::clear(self::MODE);
         $this->jwt = null;
         $this->ensureAuthenticated();
         $result = $this->send($method, $path, $query);
      }

      return Http::decode($result['body'], $result['status'], 'Zscaler ZCC');
   }

   private function send(string $method, string $path, array $query): array
   {
      $url = $this->base . '/' . ltrim($path, '/');
      if ($query !== []) {
         $url .= '?' . http_build_query($query);
      }

      return Http::exec($method, $url, [
         'Accept: application/json',
         'auth-token: ' . $this->jwt,
      ], null, $this->timeout);
   }

   private function ensureAuthenticated(): void
   {
      if ($this->jwt !== null) {
         return;
      }

      $cached = TokenCache::load(self::MODE);
      if ($cached !== null) {
         $this->jwt = $cached;
         return;
      }

      if ($this->apiKey === '' || $this->secretKey === '') {
         throw new \RuntimeException('Credenciais ZCC incompletas (apiKey, secretKey).');
      }

      $body = json_encode(['apiKey' => $this->apiKey, 'secretKey' => $this->secretKey], JSON_UNESCAPED_SLASHES);
      $result = Http::exec('POST', $this->base . '/papi/auth/v1/login', [
         'Accept: application/json',
         'Content-Type: application/json',
      ], $body, $this->timeout);

      $json = Http::decode($result['body'], $result['status'], 'Zscaler ZCC');
      $jwt = (string)($json['jwtToken'] ?? $json['jwt_token'] ?? '');
      if ($jwt === '') {
         throw new \RuntimeException('Login ZCC nao retornou jwtToken.');
      }

      // O JWT do ZCC dura ~1h; cacheamos por 50 min.
      TokenCache::store(self::MODE, $jwt, time() + (50 * 60));
      $this->jwt = $jwt;
   }
}
