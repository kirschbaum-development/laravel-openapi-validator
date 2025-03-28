# Laravel OpenAPI Validator

![Laravel Supported Versions](https://img.shields.io/badge/laravel-6.x/7.x/8.x/9.x/10.x/11.x/12.x-green.svg)
[![MIT Licensed](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)

Using an OpenAPI spec is a great way to create and share a contract to which your API adheres. This package will automatically verify both the request and response used in your integration and feature tests wherever the Laravel HTTP testing methods (`->get('/uri')`, etc) are used.

Behind the scenes this package connects the [Laravel HTTP helpers](https://laravel.com/docs/8.x/http-tests) to [The PHP League's OpenAPI Validator](https://github.com/thephpleague/openapi-psr7-validator).

## Installation

You can install the package via composer:

```bash
composer require kirschbaum-development/laravel-openapi-validator
```

## Setup

In any feature/integration test (such as those that extend the framework's `Tests\TestCase` base class), add the `ValidatesOpenApiSpec` trait:

```php
use Kirschbaum\OpenApiValidator\ValidatesOpenApiSpec;

class HttpTest extends TestCase
{
    use ValidatesOpenApiSpec;
}
```

In many situations, the defaults should handle configuration. If you need to customize your configuration (namely the location of the `openapi.yaml` or `openapi.json` file), publish the config with:

```bash
php artisan vendor:publish --provider="Kirschbaum\OpenApiValidator\OpenApiValidatorServiceProvider"
```

and configure the path to the OpenAPI spec in `config/openapi_validator.php` to fit your needs.

## Usage

After applying the trait to your test class, anytime you interact with an HTTP test method (`get`, `post`, `put`, `delete`, `postJson`, `call`, etc), the validator will validate both the request and the response.

### Skipping Validation

Especially when initially writing tests (such as in TDD), it can be helpful to turn off the request or response validation until the tests are closer to complete. You can do so as follows:

```php
public function testEndpointInProgress()
{
    $response = $this->withoutRequestValidation()->get('/'); // Skips request validation, still validates response
    // or
    $response = $this->withoutResponseValidation()->get('/'); // Validates the request, but skips response
    // or
    $response = $this->withoutValidation()->get('/'); // No validation
}
```

You are free to chain these methods as shown above, or call them on their own:

```php
public function testEndpointInProgress()
{
    $this->withoutRequestValidation();
    $response = $this->get('/');
}
```

Keep in mind that `withoutRequestValidation()`, `withoutResponseValidation()`, and `withoutValidation()` only apply to the _next_ request/response and will reset afterwards.

#### Skipping Responses Based on Response Code

We assume, by default, that any `5xx` status code should not be validated. You may change this by setting the protected `responseCodesToSkip` property on your test class, or by using the `skipResponseCode` method to add response codes (single, array, or a regex pattern):

```php
use Kirschbaum\OpenApiValidator\ValidatesOpenApiSpec;

class HttpTest extends TestCase
{
    use ValidatesOpenApiSpec;

    protected $responseCodesToSkip = [200]; // Will validate every response EXCEPT 200

    public function testNoRedirects()
    {
        $this->skipResponseCode(300); // Will skip 200 and 300
        $this->skipResponseCode(301, 302); // Will skip 200, 300, 301, 302
        $this->skipResponseCode('3[1-2]1'); // Will skip 200, 300, 301, 302, 311, and 321
        // ...
    }
}
```

### Authentication/Authorization

In most tests, you're likely using Laravel's helpers such as `actingAs($user)` to handle auth. This package, by default, assumes you're using bearer token as an authorization header, _and that this is specified in your OpenAPI spec_. The validator will expect the authorization to be part of the request, even though Laravel does not send them. If you are using security other than a bearer token, you should override the `getAuthenticatedRequest` method and add the appropriate headers. Note that they do not need to be valid (unless your code will check them), they just need to be present to satisfy the validator.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email security@kirschbaumdevelopment.com instead of using the issue tracker.

## Credits

- [Zack Teska](https://github.com/zerodahero)

## Sponsorship

Development of this package is sponsored by Kirschbaum Development Group, a developer driven company focused on problem solving, team building, and community. Learn more [about us](https://kirschbaumdevelopment.com) or [join us](https://careers.kirschbaumdevelopment.com)!

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
