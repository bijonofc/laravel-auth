<?php

namespace Bijon\LaravelAuth\Contracts;

use Bijon\LaravelAuth\Support\CaptchaResponse;

interface CaptchaProviderInterface
{
    public function verify(string $token, ?string $ip = null): CaptchaResponse;

    public function verifyOrFail(string $token, ?string $ip = null): CaptchaResponse;
}
