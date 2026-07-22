<?php

namespace Bijon\LaravelAuth\Exceptions;

use Bijon\LaravelAuth\Support\CaptchaResponse;

class TurnstileException extends AuthException
{
    public function __construct(
        public readonly CaptchaResponse $response,
        string $message = 'Turnstile verification failed.',
    ) {
        parent::__construct($message);
    }
}
