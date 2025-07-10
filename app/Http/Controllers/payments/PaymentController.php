<?php

namespace App\Http\Controllers\payments;

use App\Http\Requests\CreatePaymentRequest;
use App\Http\Requests\CreateTopUpRequest;
use App\Models\Payment;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentController
{
  public function CreatePayment(CreatePaymentRequest $request)
  {
    DB::beginTransaction();
    $validated = $request->validated();

    try {
      $paymentId = Str::uuid();
      $userId = Auth::id();
      $payment = Payment::create([
        'id' => $paymentId,
        'payment_type' => 'pay_freelancer',
        'payment_method' => $validated['payment_method'],
        'amount' => $validated['amount'],
        'date' => now(),
        'status' => 'pending',
        'client_id' => $userId,
        'freelancer_id' => $validated['freelancer_id'],
      ]);

      $controller = app(PaymentMethodController::class);
      $paymentInfo = null;
      $userEmail = User::where('id', $userId)->value('email');

      switch ($validated['payment_method']) {
        // card
        case 'card':
          $result = $controller->HandleCardMethod($request, $paymentId, $userId);
          break;
        // e-money
        case 'qris':
          $result = $controller->HandleQrisMethod($request, $paymentId);
          break;

        case 'gopay':
          $result = $controller->HandleGopayMethod($request, $paymentId);
          break;

        case 'shopeepay':
          $result = $controller->HandleShoopePayMethod($request, $paymentId, $userEmail, "pay_freelancer");
          break;
        // bank transfer
        case 'bri':
        case 'bca':
        case 'bni':
          $result = $controller->HandleBankTransferMethod($request, $paymentId, $userEmail, "pay_freelancer");
          break;

        case 'mandiri':
          $result = $controller->HandleMandiriBill($request, $paymentId, $userEmail, "pay_freelancer");
          break;
        // over the counter(OTC)
        case 'alfamart':
          $result = $controller->HandleAlfamartMethod($request, $paymentId);
          break;

        case 'indomaret':
          $result = $controller->HandleIndomaretMethod($request, $paymentId);
          break;

        default:
          throw new \InvalidArgumentException("Unsupported payment method: {$validated['payment_method']}");
      }
      $paymentInfo = $result['payment_info'] ?? null;
      if ($result['success'] === false) {
        throw new \Exception($result['message']);
      }

      $paymentInfo = $result['payment_info'] ?? null;

      DB::commit();

      return response()->json([
        'status' => 'success',
        'message' => 'Payment is being processed.',
        'data' => [
          'payment' => $payment,
          'payment_info' => $paymentInfo,
        ],
      ]);
    } catch (\Exception $e) {
      DB::rollBack();

      return response()->json([
        'status' => 'error',
        'message' => 'Failed to create top up.',
        'error' => $e->getMessage(),
      ], 500);
    }
  }
  public function CreateTopUp(CreateTopUpRequest $request)
  {
    DB::beginTransaction();
    $validated = $request->validated();

    try {
      $paymentId = Str::uuid();
      $userId = Auth::id();
      $payment = Payment::create([
        'id' => $paymentId,
        'payment_type' => 'top_up',
        'payment_method' => $validated['payment_method'],
        'amount' => $validated['amount'],
        'date' => now(),
        'status' => 'pending',
        'client_id' => $userId,
      ]);

      $controller = app(PaymentMethodController::class);
      $paymentInfo = null;
      $userEmail = User::where('id', $userId)->value('email');

      switch ($validated['payment_method']) {
        // card
        case 'card':
          $result = $controller->HandleCardMethod($request, $paymentId, $userId);
          break;
        // e-money
        case 'qris':
          $result = $controller->HandleQrisMethod($request, $paymentId);
          break;

        case 'gopay':
          $result = $controller->HandleGopayMethod($request, $paymentId);
          break;

        case 'shopeepay':
          $result = $controller->HandleShoopePayMethod($request, $paymentId, $userEmail, "pay_freelancer");
          break;
        // bank transfer
        case 'bri':
        case 'bca':
        case 'bni':
          $result = $controller->HandleBankTransferMethod($request, $paymentId, $userEmail, "pay_freelancer");
          break;

        case 'mandiri':
          $result = $controller->HandleMandiriBill($request, $paymentId, $userEmail, "pay_freelancer");
          break;
        // over the counter(OTC)
        case 'alfamart':
          $result = $controller->HandleAlfamartMethod($request, $paymentId);
          break;

        case 'indomaret':
          $result = $controller->HandleIndomaretMethod($request, $paymentId);
          break;

        default:
          throw new \InvalidArgumentException("Unsupported payment method: {$validated['payment_method']}");
      }
      $paymentInfo = $result['payment_info'] ?? null;
      if ($result['success'] === false) {
        throw new \Exception($result['message']);
      }

      $paymentInfo = $result['payment_info'] ?? null;

      DB::commit();

      return response()->json([
        'status' => 'success',
        'message' => 'top up is being processed.',
        'data' => [
          'payment' => $payment,
          'payment_info' => $paymentInfo,
        ],
      ]);
    } catch (\Exception $e) {
      DB::rollBack();

      return response()->json([
        'status' => 'error',
        'message' => 'Failed to create top up.',
        'error' => $e->getMessage(),
      ], 500);
    }
  }
  public function Callback(Request $request)
  {
    $payload = $request->all();

    $orderId = $payload['order_id'] ?? null;
    $transactionStatus = $payload['transaction_status'] ?? null;

    if (!$orderId || !$transactionStatus) {
      return response()->json([
        'status' => 'error',
        'message' => 'Missing order_id or transaction_status'
      ], 400);
    }

    $payment = Payment::find($orderId);

    if (!$payment) {
      return response()->json([
        'status' => 'error',
        'message' => 'Payment not found'
      ], 404);
    }
    $payment->status = match ($transactionStatus) {
      'authorize', 'capture', 'settlement', 'pending', 'deny', 'cancel',
      'refund', 'partial_refund', 'chargeback', 'partial_chargeback',
      'expire', 'failure' => $transactionStatus,
      default => 'unknown',
    };

    if (
      $payment->payment_type === 'top_up' &&
      in_array($transactionStatus, ['authorize', 'capture', 'settlement'])
    ) {
      $wallet = Wallet::where('user_id', $payment->client_id)->first();

      if ($wallet) {
        $wallet->balance += $payment->amount;
        $wallet->save();
      }
    }


    $payment->save();

    return response()->json([
      'status' => 'success',
      'message' => 'Payment status updated',
      'order_id' => $orderId,
      'transaction_status' => $transactionStatus,
    ]);
  }
}
