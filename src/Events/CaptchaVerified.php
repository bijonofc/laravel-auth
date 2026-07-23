<?php

namespace Bijon\LaravelAuth\Events;

use Bijon\LaravelAuth\Support\CaptchaResponse;

class CaptchaVerified
{
    public function __construct(public readonly CaptchaResponse $response)
    {
    }
}
