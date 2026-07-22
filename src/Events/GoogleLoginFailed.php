<?php

namespace Bijon\LaravelAuth\Events;

final class GoogleLoginFailed
{
    public function __construct(
        public readonly string $reason,
        public readonly ?\Throwable $exception = null,
    ) {
    }
}
