<?php

namespace Bijon\LaravelAuth\Http\Middleware;

class VerifyTurnstile extends VerifyCaptcha
{
    protected function inputName(): string
    {
        return config('laravel-auth.turnstile.input_name', 'cf-turnstile-response');
    }
}
