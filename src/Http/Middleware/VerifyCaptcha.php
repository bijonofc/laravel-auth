<?php

namespace Bijon\LaravelAuth\Http\Middleware;

use Bijon\LaravelAuth\Contracts\CaptchaProviderInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyCaptcha
{
    public function __construct(protected CaptchaProviderInterface $captcha)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $inputName = $this->inputName();
        $message = 'Captcha verification failed. Please try again.';

        $result = $this->captcha->verify((string) $request->input($inputName, ''), $request->ip());

        if ($result->success) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'errors'  => [$inputName => [$message]],
            ], 422);
        }

        return back()
            ->withInput($request->except($inputName))
            ->withErrors([$inputName => $message]);
    }

    protected function inputName(): string
    {
        return method_exists($this->captcha, 'inputName') ? $this->captcha->inputName() : 'captcha-token';
    }
}
