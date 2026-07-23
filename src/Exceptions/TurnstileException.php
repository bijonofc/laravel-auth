<?php

namespace Bijon\LaravelAuth\Exceptions;

use Bijon\LaravelAuth\Support\CaptchaResponse;

class TurnstileException extends CaptchaException
{
    public function __construct(
        CaptchaResponse $response,
        string $message = 'Turnstile verification failed.',
    ) {
        parent::__construct($response, $message);
    }
}
