<?php
namespace App\Http\Controllers;

use App\Mail\EmailVerification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use App\Helpers\ApiResponseHelper;

class VerificationController
{
    public function verificationLink($id, Request $request)
    {
        if (!$request->hasValidSignature()) {
            return response()->json(['msg' => 'Invalid or expired verification link.'], 401);
        }

        $user = User::findOrFail($id);

        if (!$user->email_verified && is_null($user->email_verified_at)) {
            $user->update([
                'email_verified' => true,
                'email_verified_at' => now(),
            ]);
        }

        return ApiResponseHelper::respond(
            [$user],
            'Email verified successfully!',
            200
        );
    }

    public function sendVerificationLink(User $user)
    {
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            ['id' => $user->id]
        );

        Mail::to($user)->send(new EmailVerification($verificationUrl));
    }
    public function resendVerificationLink(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['msg' => 'Unauthorized.'], 401);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['msg' => 'Email already verified.'], 400);
        }

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            ['id' => $user->id]
        );

        Mail::to($user)->send(new EmailVerification($verificationUrl));

        return response()->json(['msg' => 'Verification email resent.']);

    }
    protected function sendEmail(User $user)
    {
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            ['id' => $user->id]
        );

        Mail::send('emails.verify', ['url' => $verificationUrl, 'user' => $user], function ($message) use ($user) {
            $message->to($user->email);
            $message->subject('Verify Your Email Address');
        });
    }
}
