<?php

namespace App\Helpers;

class PaymentResponseHelper
{
  public static function response(bool $success, string $message = null, $paymentInfo = null): array
  {
    return [
      'success' => $success,
      'message' => $message,
      'payment_info' => $paymentInfo,
    ];
  }
}
