<?php

namespace GlpiPlugin\Zscaler;

/**
 * Cliente da API do Zscaler Digital Experience (ZDX).
 *
 * Autenticacao: POST /v1/oauth/token com {key_id, key_secret} -> {token} (JWT).
 * As chamadas seguintes usam `Authorization: Bearer <token>`.
 */
class ZdxApiClient
{
   private const MODE = 'zdx';

   private string $base;
   private string $keyId;
   private string $keySecret;
   private int $timeout;
   private ?string $token = null;

   public function __construct(array $config)
   {
      $this->base = rtrim(trim((string)($config['zdx_api_base'] ?? '')), '/') ?: 'https://api.zdxcloud.net';
      $this->keyId = trim((string)($config['zdx_key_id'] ?? ''));
      $this->keySecret = (string)($config['zdx_key_secret'] ?? '');
      $this->timeout = max(5, min(120, (int)($config['timeout'] ?? 30)));
   }

   public static function fromConfig(?array $config = null): self
   {
      return new self($config ?? Config::getConfig());
   }

   public function testConnection(): array
   {
      return $this->request('GET', '/v1/alerts/ongoing');
   }

   /**
    * Alertas em andamento (degradacao de experiencia).
    *
    * @return array<int, array<string, mixed>>
    */
   public function getOngoingAlerts(): array
   {
      return $this->extractAlerts($this->request('GET', '/v1/alerts/ongoing'));
   }

   /**
    * @return array<int, array<string, mixed>>
    */
   public function getHistoricalAlerts(?int $from = null, ?int $to = null): array
   {
      $query = [];
      if ($from !== null) {
         $query['from'] = $from;
      }
      if ($to !== null) {
         $query['to'] = $to;
      }

      return $this->extractAlerts($this->request('GET', '/v1/alerts/historical', $query));
   }

   public function getAlert(string $alertId): array
   {
      return $this->request('GET', '/v1/alerts/' . rawurlencode($alertId));
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
         $this->token = null;
         $this->ensureAuthenticated();
         $result = $this->send($method, $path, $query);
      }

      return Http::decode($result['body'], $result['status'], 'Zscaler ZDX');
   }

   private function send(string $method, string $path, array $query): array
   {
      $url = $this->base . '/' . ltrim($path, '/');
      if ($query !== []) {
         $url .= '?' . http_build_query($query);
      }

      return Http::exec($method, $url, [
         'Accept: application/json',
         'Authorization: Bearer ' . $this->token,
      ], null, $this->timeout);
   }

   private function ensureAuthenticated(): void
   {
      if ($this->token !== null) {
         return;
      }

      $cached = TokenCache::load(self::MODE);
      if ($cached !== null) {
         $this->token = $cached;
         return;
      }

      if ($this->keyId === '' || $this->keySecret === '') {
         throw new \RuntimeException('Credenciais ZDX incompletas (key_id, key_secret).');
      }

      $body = json_encode(['key_id' => $this->keyId, 'key_secret' => $this->keySecret], JSON_UNESCAPED_SLASHES);
      $result = Http::exec('POST', $this->base . '/v1/oauth/token', [
         'Accept: application/json',
         'Content-Type: application/json',
      ], $body, $this->timeout);

      $json = Http::decode($result['body'], $result['status'], 'Zscaler ZDX');
      $token = (string)($json['token'] ?? $json['access_token'] ?? '');
      if ($token === '') {
         throw new \RuntimeException('ZDX nao retornou token.');
      }

      $expiresIn = (int)($json['expires_in'] ?? 3600);
      TokenCache::store(self::MODE, $token, time() + max(60, $expiresIn) - 60);
      $this->token = $token;
   }

   /**
    * @param array<string, mixed> $response
    * @return array<int, array<string, mixed>>
    */
   private function extractAlerts(array $response): array
   {
      foreach (['alerts', '_items', 'data'] as $key) {
         if (isset($response[$key]) && is_array($response[$key])) {
            return array_values(array_filter($response[$key], 'is_array'));
         }
      }

      return [];
   }
}
