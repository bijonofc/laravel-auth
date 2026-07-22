<?php

namespace Appsbd\Auth\Services;

use Appsbd\Auth\Contracts\OAuthProviderInterface;
use Appsbd\Auth\Events\GoogleLoginFailed;
use Appsbd\Auth\Events\GoogleLoginSucceeded;
use Appsbd\Auth\Exceptions\ConfigurationException;
use Appsbd\Auth\Exceptions\OAuthException;
use Appsbd\Auth\Support\OAuthTokens;
use Appsbd\Auth\Support\OAuthUser;
use Illuminate\Http\Client\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
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

    public function redirect(?string $state = null, array $scopes = []): RedirectResponse
    {
        return new RedirectResponse($this->generateAuthorizationUrl($state, $scopes));
    }

    /**
     * @return array{user: OAuthUser, tokens: OAuthTokens}
     */
    public function callback(?string $code = null, ?string $state = null): array
    {
        try {
            $request = request();
            $code ??= $request->query('code');
            $returnedState = $state ?? $request->query('state');
            $sessionState = session()->pull(self::STATE_SESSION_KEY);

            if ($sessionState !== null) {
                if (! is_string($returnedState) || ! hash_equals($sessionState, $returnedState)) {
                    throw new OAuthException('OAuth state mismatch: the state returned by Google does not match the one stored in the session.');
                }
            } elseif ($state === null) {
                throw new OAuthException('No OAuth state available: nothing stored in the session and none passed to callback().');
            }

            if (! is_string($code) || $code === '') {
                throw new OAuthException('No authorization code was provided to callback().');
            }

            $tokens = $this->getTokensFromCode($code);
            $user = $this->getUserFromAccessToken($tokens->accessToken);
        } catch (\Throwable $e) {
            event(new GoogleLoginFailed($e->getMessage(), $e));

            throw $e;
        }

        event(new GoogleLoginSucceeded($user, $tokens));

        return ['user' => $user, 'tokens' => $tokens];
    }

    public function getTokensFromCode(string $code): OAuthTokens
    {
        $response = $this->googleRequest(fn () => Http::asForm()->post(self::TOKEN_URL, [
            'client_id'     => $this->requireConfig('client_id'),
            'client_secret' => $this->requireConfig('client_secret'),
            'redirect_uri'  => $this->requireConfig('redirect'),
            'grant_type'    => 'authorization_code',
            'code'          => $code,
        ]), 'exchange authorization code');

        return $this->mapTokens($response->json() ?? []);
    }

    public function getUserFromAccessToken(string $accessToken): OAuthUser
    {
        $response = $this->googleRequest(
            fn () => Http::withToken($accessToken)->get(self::USERINFO_URL),
            'fetch user profile'
        );

        $data = $response->json() ?? [];

        if (! isset($data['sub'])) {
            throw new OAuthException('Google userinfo response did not include a subject identifier.');
        }

        return new OAuthUser(
            id: (string) $data['sub'],
            email: $data['email'] ?? null,
            name: $data['name'] ?? null,
            avatarUrl: $data['picture'] ?? null,
            raw: $data,
        );
    }

    public function refreshToken(string $refreshToken): OAuthTokens
    {
        $response = $this->googleRequest(fn () => Http::asForm()->post(self::TOKEN_URL, [
            'client_id'     => $this->requireConfig('client_id'),
            'client_secret' => $this->requireConfig('client_secret'),
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]), 'refresh access token');

        return $this->mapTokens($response->json() ?? [], $refreshToken);
    }

    public function revokeToken(string $token): bool
    {
        try {
            return Http::asForm()->post(self::REVOKE_URL, ['token' => $token])->successful();
        } catch (\Throwable $e) {
            throw new OAuthException("Could not reach Google to revoke token: {$e->getMessage()}", previous: $e);
        }
    }

    protected function googleRequest(\Closure $call, string $context): Response
    {
        try {
            $response = $call();
        } catch (\Throwable $e) {
            throw new OAuthException("Could not reach Google to {$context}: {$e->getMessage()}", previous: $e);
        }

        if ($response->failed()) {
            $message = $response->json('error_description')
                ?? $response->json('error')
                ?? "HTTP {$response->status()}";

            throw new OAuthException("Google refused to {$context}: {$message}");
        }

        return $response;
    }

    protected function mapTokens(array $data, ?string $fallbackRefreshToken = null): OAuthTokens
    {
        if (empty($data['access_token'])) {
            throw new OAuthException('Google token response did not include an access_token.');
        }

        return new OAuthTokens(
            accessToken: (string) $data['access_token'],
            refreshToken: $data['refresh_token'] ?? $fallbackRefreshToken,
            expiresIn: isset($data['expires_in']) ? (int) $data['expires_in'] : null,
            idToken: $data['id_token'] ?? null,
            tokenType: $data['token_type'] ?? 'Bearer',
        );
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
