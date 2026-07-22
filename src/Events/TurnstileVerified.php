<?php

namespace Bijon\LaravelAuth\Events;

use Bijon\LaravelAuth\Support\CaptchaResponse;

final class TurnstileVerified
{
    public function __construct(public readonly CaptchaResponse $response)
    {
    }
}
