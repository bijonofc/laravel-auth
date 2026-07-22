<?php

namespace Bijon\LaravelAuth\Services;

use Bijon\LaravelAuth\Concerns\SendsSecureHttpRequests;
use Bijon\LaravelAuth\Contracts\CaptchaProviderInterface;
use Bijon\LaravelAuth\Events\TurnstileFailed;
use Bijon\LaravelAuth\Events\TurnstileVerified;
use Bijon\LaravelAuth\Exceptions\ConfigurationException;
use Bijon\LaravelAuth\Exceptions\TurnstileException;
use Bijon\LaravelAuth\Support\CaptchaResponse;

class TurnstileService implements CaptchaProviderInterface
{
    use SendsSecureHttpRequests;

    protected const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(protected array $config)
    {
    }

    public function verify(string $token, ?string $ip = null): CaptchaResponse
    {
        $secret = $this->config['secret'] ?? null;

        if ($secret === null || $secret === '') {
            throw ConfigurationException::missing('laravel-auth.turnstile.secret');
        }

        try {
            $response = $this->http()
                ->timeout((int) ($this->config['timeout'] ?? 10))
                ->asForm()
                ->post(self::VERIFY_URL, [
                    'secret'   => $secret,
                    'response' => $token,
                    'remoteip' => $ip,
                ]);

            if ($response->failed()) {
                $result = new CaptchaResponse(success: false, errorCodes: ['internal-error']);
            } else {
                $data = $response->json() ?? [];
                $result = new CaptchaResponse(
                    success: (bool) ($data['success'] ?? false),
                    errorCodes: $data['error-codes'] ?? [],
                    hostname: $data['hostname'] ?? null,
                    challengedAt: $data['challenge_ts'] ?? null,
                    action: $data['action'] ?? null,
                    cdata: $data['cdata'] ?? null,
                );
            }
        } catch (\Throwable $e) {
            report($e);
            $result = new CaptchaResponse(success: false, errorCodes: ['network-error']);
        }

        event($result->success ? new TurnstileVerified($result) : new TurnstileFailed($result));

        return $result;
    }

    public function verifyOrFail(string $token, ?string $ip = null): CaptchaResponse
    {
        $result = $this->verify($token, $ip);

        if ($result->failed()) {
            throw new TurnstileException($result);
        }

        return $result;
    }
}
