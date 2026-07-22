<?php

namespace Appsbd\Auth\Support;

final readonly class OAuthTokens
{
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken,
        public ?int $expiresIn,
        public ?string $idToken,
        public string $tokenType = 'Bearer',
    ) {
    }
}
