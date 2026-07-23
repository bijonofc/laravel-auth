<?php

namespace Bijon\LaravelAuth\Exceptions;

use Bijon\LaravelAuth\Support\CaptchaResponse;

class CaptchaException extends AuthException
{
    public function __construct(
        public readonly CaptchaResponse $response,
        string $message = 'Captcha verification failed.',
    ) {
        parent::__construct($message);
    }
}
