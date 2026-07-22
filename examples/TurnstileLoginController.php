<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Example login controller for a Vue SPA posting credentials + turnstile token.
 * Uses the 'turnstile' validation rule; alternatively route through ->middleware('turnstile')
 * and drop the rule (never both — tokens are single-use).
 */
class TurnstileLoginController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'                 => ['required', 'email'],
            'password'              => ['required'],
            'cf-turnstile-response' => ['required', 'turnstile'],
        ]);

        if (! Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password']])) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $request->session()->regenerate();

        return response()->json(['user' => $request->user()]);
    }
}
