<?php

namespace Appsbd\Auth\Contracts;

use Appsbd\Auth\Support\OAuthTokens;
use Appsbd\Auth\Support\OAuthUser;

interface OAuthProviderInterface
{
    public function generateAuthorizationUrl(?string $state = null, array $scopes = []): string;

    public function getTokensFromCode(string $code): OAuthTokens;

    public function getUserFromAccessToken(string $accessToken): OAuthUser;

    public function refreshToken(string $refreshToken): OAuthTokens;

    public function revokeToken(string $token): bool;
}
