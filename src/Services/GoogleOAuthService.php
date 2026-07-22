<?php

namespace Appsbd\Auth\Services;

use Appsbd\Auth\Contracts\OAuthProviderInterface;
use Appsbd\Auth\Exceptions\ConfigurationException;
use Appsbd\Auth\Support\OAuthTokens;
use Appsbd\Auth\Support\OAuthUser;
use Illuminate\Support\Str;

class GoogleOAuthService implements OAuthProviderInterface
{
    public const STATE_SESSION_KEY = 'appsbd-auth.google.state';

    protected const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    protected const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    protected const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';
    protected const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    public function __construct(protected array $config)
    {
    }

    public function generateAuthorizationUrl(?string $state = null, array $scopes = []): string
    {
        $clientId = $this->requireConfig('client_id');
        $redirect = $this->requireConfig('redirect');

        if ($state === null) {
            $state = Str::random(40);
            session()->put(self::STATE_SESSION_KEY, $state);
        }

        $query = http_build_query([
            'client_id'     => $clientId,
            'redirect_uri'  => $redirect,
            'response_type' => 'code',
            'scope'         => implode(' ', $scopes ?: ($this->config['scopes'] ?? ['openid', 'email', 'profile'])),
            'state'         => $state,
            'access_type'   => 'offline',
            'prompt'        => 'consent',
        ]);

        return self::AUTHORIZE_URL.'?'.$query;
    }

    public function getTokensFromCode(string $code): OAuthTokens
    {
        throw new \BadMethodCallException('Not implemented yet.');
    }

    public function getUserFromAccessToken(string $accessToken): OAuthUser
    {
        throw new \BadMethodCallException('Not implemented yet.');
    }

    public function refreshToken(string $refreshToken): OAuthTokens
    {
        throw new \BadMethodCallException('Not implemented yet.');
    }

    public function revokeToken(string $token): bool
    {
        throw new \BadMethodCallException('Not implemented yet.');
    }

    protected function requireConfig(string $key): string
    {
        $value = $this->config[$key] ?? null;

        if ($value === null || $value === '') {
            throw ConfigurationException::missing("appsbd-auth.google.{$key}");
        }

        return (string) $value;
    }
}
