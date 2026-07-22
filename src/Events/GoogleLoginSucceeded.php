<?php

namespace Appsbd\Auth\Events;

use Appsbd\Auth\Support\OAuthTokens;
use Appsbd\Auth\Support\OAuthUser;

final class GoogleLoginSucceeded
{
    public function __construct(
        public readonly OAuthUser $user,
        public readonly OAuthTokens $tokens,
    ) {
    }
}
