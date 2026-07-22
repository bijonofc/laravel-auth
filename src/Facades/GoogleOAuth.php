<?php

namespace Bijon\LaravelAuth\Facades;

use Bijon\LaravelAuth\Services\GoogleOAuthService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static string generateAuthorizationUrl(?string $state = null, array $scopes = [])
 * @method static \Illuminate\Http\RedirectResponse redirect(?string $state = null, array $scopes = [])
 * @method static array{user: \Bijon\LaravelAuth\Support\OAuthUser, tokens: \Bijon\LaravelAuth\Support\OAuthTokens} callback(?string $code = null, ?string $state = null)
 * @method static \Bijon\LaravelAuth\Support\OAuthTokens getTokensFromCode(string $code)
 * @method static \Bijon\LaravelAuth\Support\OAuthUser getUserFromAccessToken(string $accessToken)
 * @method static \Bijon\LaravelAuth\Support\OAuthTokens refreshToken(string $refreshToken)
 * @method static bool revokeToken(string $token)
 *
 * @see GoogleOAuthService
 */
class GoogleOAuth extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return GoogleOAuthService::class;
    }
}
