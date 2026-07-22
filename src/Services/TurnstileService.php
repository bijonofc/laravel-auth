<?php

namespace Appsbd\Auth\Services;

use Appsbd\Auth\Contracts\CaptchaProviderInterface;
use Appsbd\Auth\Events\TurnstileFailed;
use Appsbd\Auth\Events\TurnstileVerified;
use Appsbd\Auth\Exceptions\ConfigurationException;
use Appsbd\Auth\Exceptions\TurnstileException;
use Appsbd\Auth\Support\CaptchaResponse;
use Illuminate\Support\Facades\Http;

class TurnstileService implements CaptchaProviderInterface
{
    protected const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(protected array $config)
    {
    }

    public function verify(string $token, ?string $ip = null): CaptchaResponse
    {
        $secret = $this->config['secret'] ?? null;

        if ($secret === null || $secret === '') {
            throw ConfigurationException::missing('appsbd-auth.turnstile.secret');
        }

        try {
            $response = Http::timeout((int) ($this->config['timeout'] ?? 10))
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
        } catch (\Throwable) {
            $result = new CaptchaResponse(success: false, errorCodes: ['internal-error']);
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
