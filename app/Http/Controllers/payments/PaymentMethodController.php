<?php

namespace App\Http\Controllers\payments;

use App\Helpers\MidtransHelper;
use App\Helpers\PaymentResponseHelper;
use App\Models\Card;
use Illuminate\Http\Request;
use App\Http\Requests\CreatePaymentRequest;

class PaymentMethodController
{
    // card(debit/credit)
    public function HandleCardMethod(Request $request, string $paymentId, string $userId): array
    {
        $cardData = $request->input('card');
        try {

            $card = new Card([
                'id' => $paymentId,
                'card_number' => $cardData['card_number'],
                'card_holder_name' => $cardData['card_holder_name'],
                'user_id' => $userId,
                'card_address' => $cardData['card_address'],
            ]);

            if (!empty($cardData['save_card']) && $cardData['save_card'] === true) {
                $card->save();
            }
            [$expMonth, $expYear] = explode('/', $cardData['card_exp']);

            $queryParams = [
                'client_key' => env('MIDTRANS_CLIENT_KEY'),
                'card_number' => $cardData['card_number'],
                'card_cvv' => $cardData['card_cvv'],
                'card_exp_month' => $expMonth,
                'card_exp_year' => $expYear,
            ];

            $tokenResult = MidtransHelper::cardToken($queryParams);
            if ($tokenResult['success'] == false) {
                return PaymentResponseHelper::response(false, $tokenResult['message'], null);
            }
            $payload = [
                'payment_type' => 'credit_card',
                'transaction_details' => [
                    'order_id' => $paymentId,
                    'gross_amount' => $request->amount,
                ],
                'credit_card' => [
                    'token_id' => $tokenResult['token_id'],
                    'authentication' => true,
                    'callback_type' => 'js_event',
                ]
            ];

            $chargeResult = MidtransHelper::chargeTransaction($payload);
            if ($chargeResult['success'] == false) {
                return PaymentResponseHelper::response(false, $tokenResult['message'], null);
            }
            return PaymentResponseHelper::response(true, null, [
                'redirect_url' => $chargeResult['data']['redirect_url'] ?? null,
            ]);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'payment_info' => null,
            ];
        }
    }
    // e-wallet
    public function HandleQrisMethod(Request $request, string $paymentId): array
    {
        try {
            $payload = [
                'payment_type' => 'qris',
                'transaction_details' => [
                    'order_id' => $paymentId,
                    'gross_amount' => $request->amount,
                ],
                'qris' => [
                    "acquirer" => "gopay"
                ]
            ];

            $chargeResult = MidtransHelper::chargeTransaction($payload);
            if ($chargeResult['success'] == false) {
                return PaymentResponseHelper::response(false, $chargeResult['message']);
            }

            $data = $chargeResult['data'] ?? [];
            $qrUrl = collect($data['actions'] ?? [])
                ->firstWhere('name', 'generate-qr-code')['url'] ?? null;

            return PaymentResponseHelper::response(true, null, [
                'qr_url' => $qrUrl,
            ]);
        } catch (\Exception $e) {
            return PaymentResponseHelper::response(false, $e->getMessage());
        }
    }
    public function HandleGopayMethod(Request $request, string $paymentId): array
    {
        try {
            $payload = [
                'payment_type' => 'gopay',
                'transaction_details' => [
                    'order_id' => $paymentId,
                    'gross_amount' => $request->amount,
                ],
                'gopay' => [
                    "enable_callback" => true,
                ]
            ];

            $chargeResult = MidtransHelper::chargeTransaction($payload);
            if ($chargeResult['success'] == false) {
                return PaymentResponseHelper::response(false, $chargeResult['message']);
            }

            $data = $chargeResult['data'] ?? [];
            $deeplinkUrl = collect($data['actions'] ?? [])
                ->firstWhere('name', 'deeplink-redirect')['url'] ?? null;

            return PaymentResponseHelper::response(true, null, [
                'redirect_url' => $deeplinkUrl,
            ]);
        } catch (\Exception $e) {
            return PaymentResponseHelper::response(false, $e->getMessage());
        }
    }
    public function HandleShoopePayMethod(Request $request, string $paymentId, string $userEmail, string $paymentType): array
    {
        try {
            $payload = [
                'payment_type' => 'shopeepay',
                'transaction_details' => [
                    'order_id' => $paymentId,
                    'gross_amount' => $request->amount,
                ],
                'item_details' => [
                    [
                        'price' => $request->amount,
                        'quantity' => 1,
                        'name' => $paymentType
                    ]
                ],
                'customer_details' => [
                    'email' => $userEmail,
                ],
                'shopeepay' => [
                    "callback_url" => "https://midtrans.com/"
                ]
            ];

            $chargeResult = MidtransHelper::chargeTransaction($payload);
            if ($chargeResult['success'] == false) {
                return PaymentResponseHelper::response(false, $chargeResult['message']);
            }

            $data = $chargeResult['data'] ?? [];
            $deeplinkUrl = collect($data['actions'] ?? [])
                ->firstWhere('name', 'deeplink-redirect')['url'] ?? null;

            return PaymentResponseHelper::response(true, null, $deeplinkUrl);
        } catch (\Exception $e) {
            return PaymentResponseHelper::response(false, $e->getMessage());
        }
    }
    // bank transfer
    public function HandleBankTransferMethod(Request $request, string $paymentId, string $userEmail, string $paymentType): array
    {
        try {
            $payload = [
                'payment_type' => 'bank_transfer',
                'transaction_details' => [
                    'order_id' => $paymentId,
                    'gross_amount' => $request->amount,
                ],
                'item_details' => [
                    [
                        'id' => 'item-001',
                        'price' => $request->amount,
                        'quantity' => 1,
                        'name' => $paymentType
                    ]
                ],
                'customer_details' => [
                    'email' => $userEmail,
                ],
                'bank_transfer' => [
                    'bank' => $request->payment_method,
                ]
            ];
            $chargeResult = MidtransHelper::chargeTransaction($payload);
            if ($chargeResult['success'] == false) {
                return PaymentResponseHelper::response(false, $chargeResult['message']);
            }

            $data = $chargeResult['data'] ?? [];

            $vaNumber = collect($data['va_numbers'] ?? [])->first()['va_number'] ?? null;

            return PaymentResponseHelper::response(true, null, [
                'va_number' => $vaNumber,
            ]);
        } catch (\Exception $e) {
            return PaymentResponseHelper::response(false, $e->getMessage());
        }
    }

    public function HandleMandiriBill(Request $request, string $paymentId, string $userEmail, string $paymentType): array
    {
        try {
            $payload = [
                'payment_type' => 'echannel',
                'transaction_details' => [
                    'order_id' => $paymentId,
                    'gross_amount' => $request->amount,
                ],
                'echannel' => [
                    'bill_info1' => 'Payment for',
                    'bill_info2' => $paymentType,
                ]
            ];

            $chargeResult = MidtransHelper::chargeTransaction($payload);
            if ($chargeResult['success'] === false) {
                return PaymentResponseHelper::response(false, $chargeResult['message']);
            }

            $data = $chargeResult['data'] ?? [];

            return PaymentResponseHelper::response(true, null, [
                'bill_key' => $data['bill_key'],
                'biller_code' => $data['biller_code'],
            ]);
        } catch (\Exception $e) {
            return PaymentResponseHelper::response(false, $e->getMessage());
        }
    }
    // over the counter(OTC)
    public function HandleAlfamartMethod(Request $request, string $paymentId): array
    {
        try {
            $payload = [
                'payment_type' => 'cstore',
                'transaction_details' => [
                    'order_id' => $paymentId,
                    'gross_amount' => $request->input('amount'),
                ],
                'cstore' => [
                    'store' => 'alfamart',
                    'message' => 'Silakan tunjukkan kode pembayaran ini ke kasir Alfamart.'
                ],
                'customer_details' => [
                    'first_name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'phone' => $request->user()->phone
                ]
            ];

            $chargeResult = MidtransHelper::chargeTransaction($payload);

            if ($chargeResult['success'] === false) {
                return PaymentResponseHelper::response(false, $chargeResult['message']);
            }
            return PaymentResponseHelper::response(
                true,
                null,
                [
                    'payment_code' => $chargeResult['data']['payment_code']
                ]
            );
        } catch (\Exception $e) {
            return PaymentResponseHelper::response(false, $chargeResult['message']);
        }
    }
    public function HandleIndomaretMethod(Request $request, string $paymentId): array
    {
        try {
            $payload = [
                'payment_type' => 'cstore',
                'transaction_details' => [
                    'order_id' => $paymentId,
                    'gross_amount' => $request->input('amount'),
                ],
                'cstore' => [
                    'store' => 'indomaret',
                    'message' => 'Silakan tunjukkan kode pembayaran ini ke kasir Alfamart.'
                ],
                'customer_details' => [
                    'first_name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'phone' => $request->user()->phone
                ]
            ];

            $chargeResult = MidtransHelper::chargeTransaction($payload);

            if ($chargeResult['success'] === false) {
                return PaymentResponseHelper::response(false, $chargeResult['message']);
            }
            return PaymentResponseHelper::response(
                true,
                null,
                [
                    'payment_code' => $chargeResult['data']['payment_code']
                ]
            );
        } catch (\Exception $e) {
            return PaymentResponseHelper::response(false, $chargeResult['message']);
        }
    }
}
