<?php

namespace App\Helpers;

class MidtransHelper
{
  protected static $serverKey;
  protected static $baseUrl;
  protected static $timeout = 10;
  protected static $maxRetry = 1;

  protected static function init()
  {
    self::$serverKey = config('midtrans.server_key');
    $isProduction = config('midtrans.is_production');

    self::$baseUrl = $isProduction
      ? 'https://api.midtrans.com/v2'
      : 'https://api.sandbox.midtrans.com/v2';
  }

  protected static function handleResponse(string $response, string $type = 'json'): array
  {
    $data = $type === 'json' ? json_decode($response, true) : $response;

    if (!is_array($data)) {
      return [
        'success' => false,
        'message' => 'Invalid response from Midtrans.',
        'raw' => $response,
      ];
    }

    if (!isset($data['status_code']) || !in_array($data['status_code'], ['200', '201'])) {
      return [
        'success' => false,
        'message' => $data['status_message'] ?? 'Unknown error from Midtrans.',
        'raw' => $data,
      ];
    }

    return [
      'success' => true,
      'data' => $data,
    ];
  }

  protected static function sendCurlRequest(array $options): array
  {
    $attempt = 0;
    do {
      $ch = curl_init();
      curl_setopt_array($ch, $options);
      curl_setopt($ch, CURLOPT_TIMEOUT, self::$timeout);
      $response = curl_exec($ch);
      $error = curl_error($ch);
      curl_close($ch);

      if ($error) {
        $attempt++;
        if ($attempt > self::$maxRetry) {
          return [
            'success' => false,
            'message' => 'Midtrans cURL Error: ' . $error,
          ];
        }
        sleep(1);
      } else {
        break;
      }
    } while ($attempt <= self::$maxRetry);

    return self::handleResponse($response);
  }

  protected static function request(string $endpoint, array $payload): array
  {
    self::init();

    $url = self::$baseUrl . $endpoint;
    $headers = [
      'Content-Type: application/json',
      'Accept: application/json',
      'Authorization: Basic ' . base64_encode(self::$serverKey . ':'),
    ];

    $options = [
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => json_encode($payload),
      CURLOPT_HTTPHEADER => $headers,
    ];

    return self::sendCurlRequest($options);
  }

  public static function ChargeTransaction(array $payload): array
  {
    return self::request('/charge', $payload);
  }

  public static function cardToken(array $queryParams): array
  {
    try {
      self::init();

      $url = self::$baseUrl . '/token?' . http_build_query($queryParams);
      $headers = ['Accept: application/json'];

      $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => 'GET',
      ];

      $result = self::sendCurlRequest($options);
      if (!$result['success']) {
        return $result;
      }

      $data = $result['data'];

      if (!isset($data['token_id'])) {
        return [
          'success' => false,
          'message' => 'Unable to retrieve token_id from Midtrans.',
          'raw' => $data,
        ];
      }

      return [
        'success' => true,
        'token_id' => $data['token_id'],
      ];
    } catch (\Exception $e) {
      return [
        'success' => false,
        'message' => 'Exception: ' . $e->getMessage(),
      ];
    }
  }
}
