<?php

namespace Bijon\LaravelAuth\Events;

use Bijon\LaravelAuth\Support\CaptchaResponse;

final class TurnstileFailed
{
    public function __construct(public readonly CaptchaResponse $response)
    {
    }
}
