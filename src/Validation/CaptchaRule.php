<?php

namespace Bijon\LaravelAuth\Validation;

use Bijon\LaravelAuth\Contracts\CaptchaProviderInterface;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CaptchaRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $result = app(CaptchaProviderInterface::class)->verify((string) $value, request()->ip());

        if ($result->failed()) {
            $fail('The :attribute field failed captcha verification.');
        }
    }
}
