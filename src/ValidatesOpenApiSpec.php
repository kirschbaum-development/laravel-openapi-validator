<?php

namespace Kirschbaum\OpenApiValidator;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Kirschbaum\OpenApiValidator\Exceptions\UnknownParserForFileTypeException;
use Kirschbaum\OpenApiValidator\Exceptions\UnknownSpecFileTypeException;
use League\OpenAPIValidation\PSR7\Exception\Validation\AddressValidationFailed;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase as PHPunit;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

trait ValidatesOpenApiSpec
{
    use MakesHttpRequests;

    protected $openApiValidatorBuilder;

    private $psr7Factory;

    private $skipRequestValidation = false;

    private $skipResponseValidation = false;

    /**
     * Gets the open api validator builder.
     *
     * @return ValidatorBuilder
     */
    public function getOpenApiValidatorBuilder(): ValidatorBuilder
    {
        if (! isset($this->openApiValidatorBuilder)) {
            $specType = $this->getSpecFileType();

            if ($specType === 'json') {
                $this->openApiValidatorBuilder = (new ValidatorBuilder())->fromJsonFile($this->getOpenApiSpecPath());
            } elseif ($specType === 'yaml') {
                $this->openApiValidatorBuilder = (new ValidatorBuilder())->fromYamlFile($this->getOpenApiSpecPath());
            } else {
                throw new UnknownParserForFileTypeException("Unknown parser for file type {$specType}");
            }
        }

        return $this->openApiValidatorBuilder;
    }

    /**
     * Call the given URI and return the Response.
     *
     * @param  string  $method
     * @param  string  $uri
     * @param  array  $parameters
     * @param  array  $cookies
     * @param  array  $files
     * @param  array  $server
     * @param  string|null  $content
     *
     * @return \Illuminate\Testing\TestResponse
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $kernel = $this->app->make(HttpKernel::class);

        $files = array_merge($files, $this->extractFilesFromDataArray($parameters));

        $symfonyRequest = SymfonyRequest::create(
            $this->prepareUrlForRequest($uri),
            $method,
            $parameters,
            $cookies,
            $files,
            array_replace($this->serverVariables, $server),
            $content
        );

        $request = Request::createFromBase($symfonyRequest);

        $address = $this->validateRequest($request);

        $response = $kernel->handle($request);

        if ($this->followRedirects) {
            $response = $this->followRedirects($response);
        }

        $kernel->terminate($request, $response);

        $testResponse = $this->createTestResponse($response, $request);

        if ($address) {
            $this->validateResponse($address, $testResponse->baseResponse);
        }

        return $testResponse;
    }

    /**
     * Skips validating both the request and response on the next request.
     */
    public function withoutValidation(): self
    {
        $this->skipRequestValidation = true;
        $this->skipResponseValidation = true;

        return $this;
    }

    /**
     * Skips validating the request on the next request.
     */
    public function withoutRequestValidation(): self
    {
        $this->skipRequestValidation = true;

        return $this;
    }

    /**
     * Skips validating the response on the next request.
     */
    public function withoutResponseValidation(): self
    {
        $this->skipResponseValidation = true;

        return $this;
    }

    /**
     * Skips the given response codes from response validation.
     * @param mixed $responseCodes
     * @return self
     */
    public function skipResponseCode(...$responseCodes)
    {
        $this->responseCodesToSkip = array_merge($this->responseCodesToSkip ?? [], $responseCodes);

        return $this;
    }

    /**
     * Gets the regex to check which response codes to skip validation on.
     */
    protected function getResponseCodesToSkipRegex(): string
    {
        $codes = $this->responseCodesToSkip ?? ['5\d\d'];

        return '/' . implode('|', array_map(function ($code) {
            return "({$code})";
        }, $codes)) . '/';
    }

    /**
     * Checks if we should skip validating this response (it's a 500, etc)
     */
    protected function shouldSkipResponseValidation(SymfonyResponse $response): bool
    {
        if ($this->skipResponseValidation) {
            $this->skipResponseValidation = false;

            return true;
        }

        return preg_match($this->getResponseCodesToSkipRegex(), $response->getStatusCode()) === 1;
    }

    /**
     * Checks if we should skip validating this request.
     */
    protected function shouldSkipRequestValidation(): bool
    {
        if ($this->skipRequestValidation) {
            $this->skipRequestValidation = false;

            return true;
        }

        return false;
    }

    /**
     * Gets the openapi.yaml path.
     */
    protected function getOpenApiSpecPath(): string
    {
        return config('openapi_validator.spec_path');
    }

    /**
     * Gets the spec file type (json/yaml).
     *
     * @throws UnknownSpecFileTypeException
     */
    protected function getSpecFileType(): string
    {
        $type = strtolower(Str::afterLast($this->getOpenApiSpecPath(), '.'));

        if (! $type || ! in_array($type, ['json', 'yaml'])) {
            throw new UnknownSpecFileTypeException("Expected json or yaml type OpenAPI spec, got {$type}");
        }

        return $type;
    }

    /**
     * Gets the authenticated request (for spoofing auth).
     *
     * NOTE: Override this to support spoofing other authentication mechanisms, if necessary.
     */
    protected function getAuthenticatedRequest(SymfonyRequest $request): SymfonyRequest
    {
        if ($request->headers->has('Authorization')) {
            return $request;
        }

        // Spoofing when authentication headers are not present.
        $authenticatedRequest = clone $request;
        $authenticatedRequest->headers->set('Authorization', 'Bearer token');
        
        return $authenticatedRequest;
    }

    /**
     * Validates an HTTP Request
     */
    protected function validateRequest(SymfonyRequest $request): ?OperationAddress
    {
        if ($this->shouldSkipRequestValidation()) {
            return null;
        }

        $authenticatedRequest = $this->getAuthenticatedRequest($request);

        try {
            return $this->getOpenApiValidatorBuilder()
                ->getRequestValidator()
                ->validate($this->getPsr7Factory()->createRequest($authenticatedRequest));
        } catch (AddressValidationFailed $e) {
            $this->handleAddressValidationFailed($e, $request->getContent());
        }
    }

    /**
     * Validates an HTTP Response
     */
    protected function validateResponse(OperationAddress $address, SymfonyResponse $response): void
    {
        if ($this->shouldSkipResponseValidation($response)) {
            return;
        }

        try {
            $this->getOpenApiValidatorBuilder()
                ->getResponseValidator()
                ->validate(
                    $address,
                    $this->getPsr7Factory()->createResponse($response)
                );
        } catch (AddressValidationFailed $e) {
            $this->handleAddressValidationFailed($e, $response->getContent());
        }
    }

    /**
     * Handles pretty-printing a validation error.
     *
     * @param AddressValidationFailed $exception
     * @param mixed $content
     *
     * @return void
     */
    private function handleAddressValidationFailed(AddressValidationFailed $exception, $content = null): void
    {
        $previous = $exception->getPrevious();

        if ($previous && $previous instanceof \League\OpenAPIValidation\Schema\Exception\KeywordMismatch) {
            print_r(PHP_EOL . json_encode(is_string($content) ? json_decode($content) : $content, JSON_PRETTY_PRINT) . PHP_EOL);
            print_r($previous->getMessage() . PHP_EOL);
            print_r('Key: ' . implode(' -> ', $previous->dataBreadCrumb()->buildChain()));
        }
        PHPUnit::fail($exception->getMessage());
    }

    /**
     * Gets the PSR 7 factory.
     */
    private function getPsr7Factory(): PsrHttpFactory
    {
        if (! isset($this->psr7Factory)) {
            $psr17Factory = new Psr17Factory();
            $this->psr7Factory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
        }

        return $this->psr7Factory;
    }
}
