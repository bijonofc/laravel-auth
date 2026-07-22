# Validation Rule

Two equivalent forms are registered/available out of the box:

- **String rule:** `'turnstile'`
- **Rule object:** `new \Appsbd\Auth\Validation\TurnstileRule`

Both resolve the captcha service from the container and verify the value with the current request's IP.

## In a FormRequest

```php
use Appsbd\Auth\Validation\TurnstileRule;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email'                 => ['required', 'email'],
            'password'              => ['required'],
            'cf-turnstile-response' => ['required', 'turnstile'],
            // or: ['required', new TurnstileRule],
        ];
    }

    public function messages(): array
    {
        return [
            'cf-turnstile-response.turnstile' => 'Please complete the captcha and try again.',
        ];
    }
}
```

## Inline validation

```php
$request->validate([
    'cf-turnstile-response' => ['required', 'turnstile'],
]);
```

The default error message is: `The :attribute field failed captcha verification.`

## Rule vs middleware

| | `turnstile` rule | `turnstile` middleware |
|---|---|---|
| Runs | with the rest of validation | before the controller/validation |
| Errors | merged with other field errors in one 422 | immediate 422 / redirect-back |
| Use when | the captcha is part of a form | you want a hard gate on the route |

Don't use both on the same request — tokens are single-use, so the second verification always fails.
