<?php

namespace Appsbd\Auth\Facades;

use Appsbd\Auth\Services\GoogleOAuthService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static string generateAuthorizationUrl(?string $state = null, array $scopes = [])
 * @method static \Illuminate\Http\RedirectResponse redirect(?string $state = null, array $scopes = [])
 * @method static array{user: \Appsbd\Auth\Support\OAuthUser, tokens: \Appsbd\Auth\Support\OAuthTokens} callback(?string $code = null, ?string $state = null)
 * @method static \Appsbd\Auth\Support\OAuthTokens getTokensFromCode(string $code)
 * @method static \Appsbd\Auth\Support\OAuthUser getUserFromAccessToken(string $accessToken)
 * @method static \Appsbd\Auth\Support\OAuthTokens refreshToken(string $refreshToken)
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
