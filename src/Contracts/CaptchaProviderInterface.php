<?php

namespace Appsbd\Auth\Contracts;

use Appsbd\Auth\Support\CaptchaResponse;

interface CaptchaProviderInterface
{
    public function verify(string $token, ?string $ip = null): CaptchaResponse;

    public function verifyOrFail(string $token, ?string $ip = null): CaptchaResponse;
}
