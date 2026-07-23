<?php

namespace Bijon\LaravelAuth\Services;

use Bijon\LaravelAuth\Concerns\SendsSecureHttpRequests;
use Bijon\LaravelAuth\Contracts\CaptchaProviderInterface;
use Bijon\LaravelAuth\Events\CaptchaFailed;
use Bijon\LaravelAuth\Events\CaptchaVerified;
use Bijon\LaravelAuth\Exceptions\CaptchaException;
use Bijon\LaravelAuth\Exceptions\ConfigurationException;
use Bijon\LaravelAuth\Support\CaptchaResponse;

abstract class AbstractCaptchaService implements CaptchaProviderInterface
{
    use SendsSecureHttpRequests;

    public function __construct(protected array $config)
    {
    }

    /** Provider identifier, also the config key under `laravel-auth.` (e.g. `turnstile`). */
    abstract public function name(): string;

    /** The provider's siteverify endpoint. */
    abstract protected function verifyUrl(): string;

    /** Map the provider's siteverify JSON body to the unified response. */
    abstract protected function mapResponse(array $data): CaptchaResponse;

    /** The request input the provider's frontend submits the token under. */
    public function inputName(): string
    {
        return $this->config['input_name'] ?? 'captcha-token';
    }

    /** The JS script the frontend must load to render this provider's widget. */
    public function scriptUrl(): ?string
    {
        return $this->config['script_url'] ?? $this->defaultScriptUrl();
    }

    /** Default script URL when `script_url` is not configured. */
    protected function defaultScriptUrl(): ?string
    {
        return null;
    }

    /** Provider-specific extras the frontend needs (e.g. reCAPTCHA's action). */
    public function frontendParams(): array
    {
        return [];
    }

    public function verify(string $token, ?string $ip = null): CaptchaResponse
    {
        $secret = $this->config['secret'] ?? null;

        if ($secret === null || $secret === '') {
            throw ConfigurationException::missing("laravel-auth.{$this->name()}.secret");
        }

        try {
            $response = $this->http()
                ->timeout((int) ($this->config['timeout'] ?? 10))
                ->asForm()
                ->post($this->verifyUrl(), $this->payload($secret, $token, $ip));

            if ($response->failed()) {
                $result = $this->failure(['internal-error']);
            } else {
                $result = $this->mapResponse($response->json() ?? []);
            }
        } catch (\Throwable $e) {
            report($e);
            $result = $this->failure(['network-error']);
        }

        $this->dispatchEvents($result);

        return $result;
    }

    public function verifyOrFail(string $token, ?string $ip = null): CaptchaResponse
    {
        $result = $this->verify($token, $ip);

        if ($result->failed()) {
            throw $this->failureException($result);
        }

        return $result;
    }

    /** Form body sent to the siteverify endpoint. All supported providers share this shape. */
    protected function payload(string $secret, string $token, ?string $ip): array
    {
        return [
            'secret'   => $secret,
            'response' => $token,
            'remoteip' => $ip,
        ];
    }

    protected function failure(array $errorCodes): CaptchaResponse
    {
        return new CaptchaResponse(success: false, errorCodes: $errorCodes, provider: $this->name());
    }

    protected function dispatchEvents(CaptchaResponse $result): void
    {
        event($result->success ? new CaptchaVerified($result) : new CaptchaFailed($result));
    }

    protected function failureException(CaptchaResponse $result): CaptchaException
    {
        return new CaptchaException($result);
    }
}
