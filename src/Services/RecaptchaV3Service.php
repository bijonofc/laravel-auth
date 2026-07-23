<?php

namespace Bijon\LaravelAuth\Services;

use Bijon\LaravelAuth\Support\CaptchaResponse;

class RecaptchaV3Service extends AbstractCaptchaService
{
    protected const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public function name(): string
    {
        return 'recaptcha';
    }

    protected function verifyUrl(): string
    {
        return self::VERIFY_URL;
    }

    public function inputName(): string
    {
        return $this->config['input_name'] ?? 'g-recaptcha-response';
    }

    protected function mapResponse(array $data): CaptchaResponse
    {
        $success = (bool) ($data['success'] ?? false);
        $score = isset($data['score']) ? (float) $data['score'] : null;
        $action = $data['action'] ?? null;
        $errorCodes = $data['error-codes'] ?? [];

        if ($success && ($score ?? 0.0) < $this->minScore()) {
            $success = false;
            $errorCodes[] = 'low-score';
        }

        $expectedAction = $this->config['action'] ?? null;

        if ($success && $expectedAction !== null && $action !== $expectedAction) {
            $success = false;
            $errorCodes[] = 'action-mismatch';
        }

        return new CaptchaResponse(
            success: $success,
            errorCodes: $errorCodes,
            hostname: $data['hostname'] ?? null,
            challengedAt: $data['challenge_ts'] ?? null,
            action: $action,
            provider: $this->name(),
            score: $score,
            raw: $data,
        );
    }

    protected function minScore(): float
    {
        return (float) ($this->config['min_score'] ?? 0.5);
    }
}
