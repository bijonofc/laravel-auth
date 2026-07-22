<?php

namespace Appsbd\Auth\Events;

use Appsbd\Auth\Support\CaptchaResponse;

final class TurnstileVerified
{
    public function __construct(public readonly CaptchaResponse $response)
    {
    }
}
