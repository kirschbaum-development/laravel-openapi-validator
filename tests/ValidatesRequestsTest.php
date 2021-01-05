<?php

namespace Kirschbaum\OpenApiValidator\Tests;

use Exception;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Kirschbaum\OpenApiValidator\ValidatesOpenApiSpec;
use League\OpenAPIValidation\PSR7\Exception\NoPath;
use League\OpenAPIValidation\PSR7\OperationAddress;
use Orchestra\Testbench\TestCase;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class ValidatesRequestsTest extends TestCase
{
    use ValidatesOpenApiSpec;
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('openapi_validator.spec_path', __DIR__.'/fixtures/OpenAPI.yaml');
    }

    /**
     * @test
     * @dataProvider provideValidationScenarios
     */
    public function testValidatesRequests(array $requestData, bool $expectSuccess, ?string $expectedException = null)
    {
        extract($requestData);

        $request = $this->makeRequest($method, $uri, $parameters ?? [], $cookies ?? [], $files ?? [], $server ?? [], $content ?? null);

        try {
            $result = $this->validateRequest($request);
        } catch (Exception $exception) {
            if (is_null($expectedException)) {
                $this->fail('Validation failed with unexpected exception ' . get_class($exception) . PHP_EOL . $exception->getMessage());
            }
            $this->assertInstanceOf($expectedException, $exception, "Expected an exception of class [${expectedException}] to be thrown, got " . get_class($exception));

            $this->assertFalse($expectSuccess);
            // End the test here
            return;
        }

        $this->assertTrue($expectSuccess, 'Not expecting a successful validation, but here we are...');

        $this->assertInstanceOf(OperationAddress::class, $result);
        $this->assertTrue(OperationAddress::isPathMatchesSpec($result->path(), Str::start($uri, '/')), 'Spec path does not match given path.');
        $this->assertEqualsIgnoringCase($method, $result->method());
    }

    /**
     * Provides a handful of scenarios to test the validator is hooked up correctly.
     * We'll defer the actual testing to the league's validator itself.
     */
    public function provideValidationScenarios()
    {
        yield 'Gets test OK' => [
            [
                'method' => 'GET',
                'uri' => 'test',
            ],
            true,
        ];

        yield 'Gets params with param' => [
            [
                'method' => 'GET',
                'uri' => 'params/parameter1',
            ],
            true,
        ];

        yield 'Fails to gets params without param' => [
            [
                'method' => 'GET',
                'uri' => 'params',
            ],
            false,
            NoPath::class,
        ];

        yield 'Gets query with query params' => [
            [
                'method' => 'GET',
                'uri' => 'query-params',
                'parameters' => ['parameter' => 'forks'],
            ],
            true,
        ];

        yield 'Fails to get query with query params in path' => [
            [
                'method' => 'GET',
                'uri' => 'query-params/forks',
            ],
            false,
            NoPath::class,
        ];

        yield 'Posts with form' => [
            [
                'method' => 'POST',
                'uri' => 'form',
                'content' => json_encode([
                    'formInputInteger' => 14,
                    'formInputString' => 'yarn',
                ]),
                'server' => ['CONTENT_TYPE' => 'application/json'],
            ],
            true,
        ];
    }

    private function makeRequest($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $symfonyRequest = SymfonyRequest::create(
            $this->prepareUrlForRequest($uri),
            $method,
            $parameters,
            $cookies,
            $files,
            array_replace($this->serverVariables, $server),
            $content
        );

        return Request::createFromBase($symfonyRequest);
    }

    /**
     * NOTE: overriding this method for testing
     */
    public function shouldSkipRequestValidation()
    {
        return false;
    }
}
