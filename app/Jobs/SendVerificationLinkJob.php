<?php

namespace App\Jobs;

use App\Models\User;
use App\Http\Controllers\VerificationController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendVerificationLinkJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    protected User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function handle()
    {
        (new VerificationController)->sendVerificationLink($this->user);
    }
}