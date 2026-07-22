<?php

namespace Bijon\LaravelAuth\Concerns;

use Composer\CaBundle\CaBundle;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

trait SendsSecureHttpRequests
{
    /**
     * Base HTTP client with an explicit CA bundle, so TLS verification works
     * even on hosts without a configured certificate store (e.g. WAMP/XAMPP).
     */
    protected function http(): PendingRequest
    {
        return Http::withOptions(['verify' => CaBundle::getSystemCaRootBundlePath()]);
    }
}
