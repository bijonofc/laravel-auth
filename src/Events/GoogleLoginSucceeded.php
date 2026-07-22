<?php

namespace Bijon\LaravelAuth\Events;

use Bijon\LaravelAuth\Support\OAuthTokens;
use Bijon\LaravelAuth\Support\OAuthUser;

final class GoogleLoginSucceeded
{
    public function __construct(
        public readonly OAuthUser $user,
        public readonly OAuthTokens $tokens,
    ) {
    }
}
