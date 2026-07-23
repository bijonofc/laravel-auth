<?php

namespace Bijon\LaravelAuth\Services;

use Bijon\LaravelAuth\Events\TurnstileFailed;
use Bijon\LaravelAuth\Events\TurnstileVerified;
use Bijon\LaravelAuth\Exceptions\CaptchaException;
use Bijon\LaravelAuth\Exceptions\TurnstileException;
use Bijon\LaravelAuth\Support\CaptchaResponse;

class TurnstileService extends AbstractCaptchaService
{
    protected const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function name(): string
    {
        return 'turnstile';
    }

    protected function verifyUrl(): string
    {
        return self::VERIFY_URL;
    }

    public function inputName(): string
    {
        return $this->config['input_name'] ?? 'cf-turnstile-response';
    }

    protected function mapResponse(array $data): CaptchaResponse
    {
        return new CaptchaResponse(
            success: (bool) ($data['success'] ?? false),
            errorCodes: $data['error-codes'] ?? [],
            hostname: $data['hostname'] ?? null,
            challengedAt: $data['challenge_ts'] ?? null,
            action: $data['action'] ?? null,
            cdata: $data['cdata'] ?? null,
            provider: $this->name(),
            raw: $data,
        );
    }

    protected function dispatchEvents(CaptchaResponse $result): void
    {
        parent::dispatchEvents($result);

        event($result->success ? new TurnstileVerified($result) : new TurnstileFailed($result));
    }

    protected function failureException(CaptchaResponse $result): CaptchaException
    {
        return new TurnstileException($result);
    }
}
