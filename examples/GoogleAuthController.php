<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Appsbd\Auth\Exceptions\OAuthException;
use Appsbd\Auth\Facades\GoogleOAuth;
use Illuminate\Http\RedirectResponse;

/**
 * Example controller — the package ships no routes; copy this into your app.
 * Pair with examples/routes.php and examples/HandleGoogleLogin.php.
 */
class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return GoogleOAuth::redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            GoogleOAuth::callback(); // fires GoogleLoginSucceeded — see HandleGoogleLogin listener
        } catch (OAuthException) {
            return redirect('/login')->withErrors(['google' => 'Google sign-in failed. Please try again.']);
        }

        return redirect()->intended('/dashboard');
    }
}
