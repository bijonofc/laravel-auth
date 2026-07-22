<?php

namespace Bijon\LaravelAuth\Support;

final readonly class OAuthUser
{
    public function __construct(
        public string $id,
        public ?string $email,
        public ?string $name,
        public ?string $avatarUrl,
        public array $raw = [],
    ) {
    }
}
