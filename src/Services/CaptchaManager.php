<?php

namespace Bijon\LaravelAuth\Services;

use Bijon\LaravelAuth\Contracts\CaptchaProviderInterface;
use Bijon\LaravelAuth\Exceptions\ConfigurationException;
use Bijon\LaravelAuth\Support\CaptchaResponse;
use Closure;
use Illuminate\Contracts\Container\Container;

class CaptchaManager implements CaptchaProviderInterface
{
    /**
     * Registered providers, keyed by name. Registration order is the
     * auto-detection priority; values are container classes or factories.
     *
     * @var array<string, class-string<CaptchaProviderInterface>|Closure>
     */
    protected array $providers = [];

    public function __construct(protected Container $app)
    {
    }

    /** Register a provider. New providers only need this — the manager never changes. */
    public function extend(string $name, string|Closure $provider): static
    {
        $this->providers[$name] = $provider;

        return $this;
    }

    /** @return list<string> registered provider names, in detection priority order */
    public function providers(): array
    {
        return array_keys($this->providers);
    }

    /**
     * The active provider name: the forced `captcha.provider` config value if set,
     * otherwise the first registered provider with both site_key and secret configured,
     * otherwise the first registered provider (whose verify() will fail loudly).
     */
    public function detect(): string
    {
        $forced = $this->config('captcha')['provider'] ?? null;

        if ($forced !== null && $forced !== '') {
            if (! isset($this->providers[$forced])) {
                throw new ConfigurationException(sprintf(
                    'Unknown captcha provider [%s]. Registered providers: %s.',
                    $forced,
                    implode(', ', $this->providers()) ?: 'none',
                ));
            }

            return $forced;
        }

        foreach ($this->providers() as $name) {
            if ($this->isConfigured($name)) {
                return $name;
            }
        }

        return array_key_first($this->providers)
            ?? throw new ConfigurationException('No captcha providers are registered.');
    }

    public function isConfigured(string $name): bool
    {
        $config = $this->config($name);

        return ($config['site_key'] ?? '') !== '' && ($config['site_key'] ?? null) !== null
            && ($config['secret'] ?? '') !== '' && ($config['secret'] ?? null) !== null;
    }

    /** Resolve a provider by name, or the detected one when no name is given. */
    public function provider(?string $name = null): CaptchaProviderInterface
    {
        $name ??= $this->detect();

        $provider = $this->providers[$name] ?? throw new ConfigurationException(
            "Unknown captcha provider [{$name}]."
        );

        return $provider instanceof Closure ? $provider($this->app) : $this->app->make($provider);
    }

    public function verify(string $token, ?string $ip = null): CaptchaResponse
    {
        return $this->provider()->verify($token, $ip);
    }

    public function verifyOrFail(string $token, ?string $ip = null): CaptchaResponse
    {
        return $this->provider()->verifyOrFail($token, $ip);
    }

    /** The active provider's public site key, for the frontend widget/script. */
    public function siteKey(): ?string
    {
        return $this->config($this->detect())['site_key'] ?? null;
    }

    /** The request input the active provider's frontend submits the token under. */
    public function inputName(): string
    {
        $provider = $this->provider();

        return method_exists($provider, 'inputName') ? $provider->inputName() : 'captcha-token';
    }

    /**
     * Everything a frontend needs to render the active captcha widget, or null
     * when no provider is configured — so a SPA can just check for null.
     *
     * @return array{provider: string, site_key: ?string, input: string, script: ?string, params: array}|null
     */
    public function frontendConfig(): ?array
    {
        $name = $this->detect();

        if (! $this->isConfigured($name)) {
            return null;
        }

        $provider = $this->provider($name);

        return [
            'provider' => $name,
            'site_key' => $this->siteKey(),
            'input'    => method_exists($provider, 'inputName') ? $provider->inputName() : 'captcha-token',
            'script'   => method_exists($provider, 'scriptUrl') ? $provider->scriptUrl() : null,
            'params'   => method_exists($provider, 'frontendParams') ? $provider->frontendParams() : [],
        ];
    }

    protected function config(string $key): array
    {
        return $this->app->make('config')->get("laravel-auth.{$key}", []) ?? [];
    }
}
