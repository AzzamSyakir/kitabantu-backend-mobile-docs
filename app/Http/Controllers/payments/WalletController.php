<?php

namespace App\Http\Controllers\payments;

use App\Helpers\ApiResponseHelper;
use App\Models\Wallet;
use App\Http\Requests\CreateWalletRequest;
use Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;
use App\Http\Requests\UpdatePinRequest;

class WalletController
{

    public function CreateWallet(CreateWalletRequest $request)
    {
        $validated = $request->validated();
        DB::beginTransaction();
        $userId = Auth::id();

        try {
            $existingWallet = Wallet::where('user_id', $userId)->first();

            if ($existingWallet) {
                return ApiResponseHelper::respond(
                    null,
                    'Wallet already exists for this user.',
                    409
                );
            }

            $wallet = Wallet::create([
                'user_id' => $userId,
                'pin' => Hash::make($validated['pin']),
            ]);

            DB::commit();

            return ApiResponseHelper::respond(
                ['wallet_id' => $wallet->id],
                'Wallet created successfully.',
                201
            );
        } catch (Throwable $e) {
            DB::rollBack();

            return ApiResponseHelper::respond(
                null,
                'Failed to create Wallet: ' . $e->getMessage(),
                500
            );
        }
    }


    public function UpdatePin(UpdatePinRequest $request)
    {
        $data = $request->validated();

        DB::beginTransaction();

        try {
            $user = auth()->user();

            if (!$user) {
                return ApiResponseHelper::respond(
                    data: null,
                    message: 'Unauthorized.',
                    status: 401
                );
            }

            $wallet = Wallet::where('user_id', $user->id)->first();

            if (!$wallet) {
                return ApiResponseHelper::respond(
                    data: null,
                    message: 'Wallet not found for this user.',
                    status: 404
                );
            }

            if (!Hash::check($data['old_pin'], $wallet->pin)) {
                return ApiResponseHelper::respond(
                    data: null,
                    message: 'Old PIN is incorrect.',
                    status: 403
                );
            }

            if (Hash::check($data['new_pin'], $wallet->pin)) {
                return ApiResponseHelper::respond(
                    data: null,
                    message: 'New PIN must be different from the old PIN.',
                    status: 422
                );
            }

            $wallet->pin = Hash::make($data['new_pin']);
            $wallet->save();

            DB::commit();

            return ApiResponseHelper::respond(
                data: ['wallet_id' => $wallet->id],
                message: 'PIN updated successfully.',
                status: 200
            );
        } catch (Throwable $e) {
            DB::rollBack();

            return ApiResponseHelper::respond(
                data: null,
                message: 'Failed to update PIN: ' . $e->getMessage(),
                status: 500
            );
        }
    }

}
