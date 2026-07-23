# Validation Rule

Two equivalent forms are registered/available out of the box:

- **String rule:** `'captcha'`
- **Rule object:** `new \Bijon\LaravelAuth\Validation\CaptchaRule`

Both resolve the [auto-detected captcha provider](Captcha.md#automatic-provider-detection) from the container and verify the value with the current request's IP. The legacy `'turnstile'` string rule and `TurnstileRule` object (now a `CaptchaRule` subclass) remain registered and behave identically — they're kept for backward compatibility.

## In a FormRequest

```php
use Bijon\LaravelAuth\Validation\CaptchaRule;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email'                 => ['required', 'email'],
            'password'              => ['required'],
            'cf-turnstile-response' => ['required', 'captcha'],
            // or: ['required', new CaptchaRule],
        ];
    }

    public function messages(): array
    {
        return [
            'cf-turnstile-response.captcha' => 'Please complete the captcha and try again.',
        ];
    }
}
```

(Use the input name matching your provider: `cf-turnstile-response` for Turnstile, `g-recaptcha-response` for reCAPTCHA v3.)

## Inline validation

```php
$request->validate([
    'cf-turnstile-response' => ['required', 'captcha'],
]);
```

The default error message is: `The :attribute field failed captcha verification.`

## Rule vs middleware

| | `captcha` rule | `captcha` middleware |
|---|---|---|
| Runs | with the rest of validation | before the controller/validation |
| Errors | merged with other field errors in one 422 | immediate 422 / redirect-back |
| Use when | the captcha is part of a form | you want a hard gate on the route |

Don't use both on the same request — tokens are single-use, so the second verification always fails.
