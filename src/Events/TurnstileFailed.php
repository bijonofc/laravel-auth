<?php

namespace Appsbd\Auth\Events;

use Appsbd\Auth\Support\CaptchaResponse;

final class TurnstileFailed
{
    public function __construct(public readonly CaptchaResponse $response)
    {
    }
}
