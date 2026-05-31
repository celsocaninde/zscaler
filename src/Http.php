<?php

namespace GlpiPlugin\Zscaler;

/**
 * Helper HTTP minimo e compartilhado pelos clientes ZDX/ZCC (e chamadas ZIA
 * fora da base padrao, como o sandbox submission). Usa curl com fallback para
 * stream wrapper. Nao decodifica JSON (cada cliente decide).
 */
class Http
{
   /**
    * @param string[]    $headers
    * @param string|null $body
    * @return array{status:int, body:string, headers:array<int,string>}
    */
   public static function exec(
      string $method,
      string $url,
      array $headers,
      ?string $body = null,
      int $timeout = 30,
      bool $returnHeaders = false
   ): array {
      $timeout = max(5, min(120, $timeout));

      if (function_exists('curl_init')) {
         return self::curl($method, $url, $headers, $body, $timeout, $returnHeaders);
      }

      return self::stream($method, $url, $headers, $body, $timeout);
   }

   /**
    * Decodifica JSON e lanca excecao amigavel em erro HTTP.
    *
    * @return array<string, mixed>
    */
   public static function decode(string $raw, int $status, string $vendor = 'Zscaler'): array
   {
      $raw = trim($raw);
      $json = $raw === '' ? [] : json_decode($raw, true);

      if (!is_array($json)) {
         if ($status >= 400) {
            throw new \RuntimeException("Erro {$vendor} HTTP {$status}.");
         }
         $json = [];
      }

      if ($status >= 400) {
         $message = $json['message']
            ?? $json['error']
            ?? $json['error_description']
            ?? $json['errors'][0]['message']
            ?? ('HTTP ' . $status);
         throw new \RuntimeException("Erro {$vendor}: " . (is_string($message) ? $message : ('HTTP ' . $status)));
      }

      $out = array_is_list($json) ? ['_items' => $json] : $json;
      $out['_http_status'] = $status;

      return $out;
   }

   /**
    * @param string[]    $headers
    * @return array{status:int, body:string, headers:array<int,string>}
    */
   private static function curl(string $method, string $url, array $headers, ?string $body, int $timeout, bool $returnHeaders): array
   {
      $curl = curl_init($url);
      curl_setopt_array($curl, [
         CURLOPT_CUSTOMREQUEST  => strtoupper($method),
         CURLOPT_HTTPHEADER     => $headers,
         CURLOPT_RETURNTRANSFER => true,
         CURLOPT_HEADER         => $returnHeaders,
         CURLOPT_TIMEOUT        => $timeout,
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
         throw new \RuntimeException('Falha HTTP: ' . $error);
      }

      $raw = (string)$raw;
      $headerLines = [];
      $responseBody = $raw;

      if ($returnHeaders) {
         $responseBody = substr($raw, $headerSize);
         $headerLines = preg_split('/\r\n/', substr($raw, 0, $headerSize)) ?: [];
      }

      return ['status' => $status, 'body' => $responseBody, 'headers' => $headerLines];
   }

   /**
    * @param string[]    $headers
    * @return array{status:int, body:string, headers:array<int,string>}
    */
   private static function stream(string $method, string $url, array $headers, ?string $body, int $timeout): array
   {
      $context = stream_context_create([
         'http' => [
            'method'        => strtoupper($method),
            'header'        => implode("\r\n", $headers),
            'content'       => $body ?? '',
            'timeout'       => $timeout,
            'ignore_errors' => true,
         ],
      ]);

      $raw = @file_get_contents($url, false, $context);
      $responseHeaders = $http_response_header ?? [];
      $status = 0;
      foreach ($responseHeaders as $header) {
         if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $m)) {
            $status = (int)$m[1];
            break;
         }
      }

      if ($raw === false) {
         throw new \RuntimeException('Falha HTTP usando stream wrapper.');
      }

      return ['status' => $status, 'body' => (string)$raw, 'headers' => $responseHeaders];
   }
}
