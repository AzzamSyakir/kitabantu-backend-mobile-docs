<?php
namespace App\Helpers;

class ApiResponseHelper
{
  public static function respond($data, $message, $status)
  {
    $response = response()
      ->json([
        'message' => $message,
        'data' => $data,
        'status_code' => $status,
      ], $status)->header('Content-Type', 'application/json');

    $response->setEncodingOptions(
      $response->getEncodingOptions() | JSON_UNESCAPED_SLASHES
    );

    return $response;
  }

}
