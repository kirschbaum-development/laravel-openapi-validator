<?php

namespace Kirschbaum\OpenApiValidator\Tests;

use Exception;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Kirschbaum\OpenApiValidator\ValidatesOpenApiSpec;
use League\OpenAPIValidation\PSR7\Exception\NoResponseCode;
use League\OpenAPIValidation\PSR7\OperationAddress;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\AssertionFailedError;

class ValidatesResponsesTest extends TestCase
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
    public function testValidatesResponses(OperationAddress $address, array $responseData, bool $expectSuccess, ?string $expectedException = null)
    {
        extract($responseData);
        $response = new Response($data, $status ?? 200, $headers ?? []);

        try {
            $this->validateResponse($address, $response);
        } catch (Exception $exception) {
            if (is_null($expectedException)) {
                $this->fail('Validation failed with unexpected exception ' . get_class($exception) . PHP_EOL . $exception->getMessage());
            }
            $this->assertInstanceOf($expectedException, $exception, "Expected an exception of class [{$expectedException}] to be thrown, got " . get_class($exception));

            $this->assertFalse($expectSuccess);
            // End the test here
            return;
        }

        $this->assertTrue($expectSuccess, 'Not expecting a successful validation, but here we are...');
    }

    /**
     * Provides a handful of scenarios to test the validator is hooked up correctly.
     * We'll defer the actual testing to the league's validator itself.
     */
    public function provideValidationScenarios()
    {
        yield 'Empty 200 on /test is OK' => [
            new OperationAddress('/test', 'get'),
            [
                'data' => '',
            ],
            true,
        ];

        yield 'Empty 204 on /test is not OK' => [
            new OperationAddress('/test', 'get'),
            [
                'data' => [],
                'status' => 204,
            ],
            false,
            NoResponseCode::class,
        ];
    }

    /**
     * NOTE: overriding this method for testing
     */
    public function shouldSkipResponseValidation($response)
    {
        return false;
    }

    /**
     * @test
     */
    public function testHandlesKeywordMismatch()
    {
        // This request has the wrong type on the parameter
        $response = new JsonResponse(['message' => 12], 201);

        try {
            $result = $this->validateResponse(new OperationAddress('/test', 'get'), $response);
        } catch (AssertionFailedError $exception) {
            ob_clean();
            // This validator fails the test for us.
            // Double check it came from our handler.
            // Using contains to ensure backwards compatibility in case the trace changes due to phpunit or otherwise.
            $this->assertContains('handleAddressValidationFailed', array_column($exception->getSerializableTrace(), 'function'));
        }
    }
}
