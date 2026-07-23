<?php

namespace Bijon\LaravelAuth\Support;

final readonly class CaptchaResponse
{
    public function __construct(
        public bool $success,
        public array $errorCodes = [],
        public ?string $hostname = null,
        public ?string $challengedAt = null,
        public ?string $action = null,
        public ?string $cdata = null,
        public ?string $provider = null,
        public ?float $score = null,
        public array $raw = [],
    ) {
    }

    public function failed(): bool
    {
        return ! $this->success;
    }
}
