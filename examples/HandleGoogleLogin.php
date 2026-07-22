<?php

namespace App\Listeners;

use App\Models\User;
use Bijon\LaravelAuth\Events\GoogleLoginSucceeded;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Example listener: find-or-create the user, then log in via the session guard
 * (Sanctum SPA cookie auth uses the same web guard).
 *
 * Register in a service provider:
 *   Event::listen(GoogleLoginSucceeded::class, HandleGoogleLogin::class);
 */
class HandleGoogleLogin
{
    public function handle(GoogleLoginSucceeded $event): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => $event->user->email],
            [
                'name'     => $event->user->name ?? 'Google User',
                'password' => Str::password(32),
            ],
        );

        Auth::login($user, remember: true);
    }
}
