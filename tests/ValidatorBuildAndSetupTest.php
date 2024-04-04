<?php

namespace Kirschbaum\OpenApiValidator\Tests;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Kirschbaum\OpenApiValidator\Exceptions\UnknownSpecFileTypeException;
use Kirschbaum\OpenApiValidator\ValidatesOpenApiSpec;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Orchestra\Testbench\TestCase;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class ValidatorBuildAndSetupTest extends TestCase
{
    use ValidatesOpenApiSpec;
    use MockeryPHPUnitIntegration;
    use WithFaker;

    /**
     * @test
     * @dataProvider provideSpecFormats
     */
    public function testGetsOpenApiValidatorBuilder(string $extension)
    {
        $this->app['config']->set('openapi_validator.spec_path', __DIR__."/fixtures/OpenAPI.{$extension}");
        $builder = $this->getOpenApiValidatorBuilder();

        $this->assertInstanceOf(ValidatorBuilder::class, $builder);
    }

    public static function provideSpecFormats()
    {
        return [
            ['json'],
            ['yaml'],
        ];
    }

    /**
     * @test
     * @dataProvider provideSpecUnknownFormats
     */
    public function testThrowsExceptionForUnknownFormat(string $extension)
    {
        $this->app['config']->set('openapi_validator.spec_path', __DIR__ . "/fixtures/OpenAPI.{$extension}");

        $this->expectException(UnknownSpecFileTypeException::class);
        $this->getSpecFileType();
    }

    /**
     * @return array
     */
    public static function provideSpecUnknownFormats(): array
    {
        return [
            ['banana'],
            ['jsonn'],
            ['yamll'],
        ];
    }

    /**
     * @test
     */
    public function testSkipsRequestValidation()
    {
        $this->withoutRequestValidation();

        $this->assertTrue($this->shouldSkipRequestValidation());
    }

    /**
     * @test
     */
    public function testSkipsResponseValidation()
    {
        $this->withoutResponseValidation();

        $this->assertTrue($this->shouldSkipResponseValidation(Mockery::mock(Response::class)));
    }

    /**
     * @test
     */
    public function testSkipsValidation()
    {
        $this->withoutValidation();

        $this->assertTrue($this->shouldSkipRequestValidation());

        $this->assertTrue($this->shouldSkipResponseValidation(Mockery::mock(Response::class)));
    }

    /**
     * @test
     * @dataProvider provideResponseCodes
     */
    public function testSkipsResponseCodes($responseCode, $codesToSkip, bool $expected)
    {
        if (is_callable($responseCode)) {
            $responseCode = $responseCode($this->faker);
        }
        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatusCode')->once()->andReturn($responseCode);

        if ($codesToSkip) {
            is_array($codesToSkip)
                ? $this->skipResponseCode(...$codesToSkip)
                : $this->skipResponseCode($codesToSkip);
        }

        $this->assertEquals($expected, $this->shouldSkipResponseValidation($response));
    }

    public static function provideResponseCodes()
    {
        for ($i = 0; $i <= 8; $i++) {
            yield "Skips 50{$i} by default" => [500 + $i, [], true];
        }

        yield 'Skips other 500s by default (1)' => [
            function ($faker) {
                return $faker->numberBetween(505, 599);
            },
            [],
            true,
        ];

        yield 'Skips other 500s by default (2)' => [
            function ($faker) {
                return $faker->numberBetween(505, 599);
            },
            [],
            true,
        ];

        yield 'Skips other 500s by default (3)' => [
            function ($faker) {
                return $faker->numberBetween(505, 599);
            },
            [],
            true,
        ];

        yield 'Skips single code' => [200, 200, true];

        yield 'Skips array of codes' => [431, [431, 200], true];

        yield 'Skips regex of codes' => [306, '30?', true];

        yield 'Doesn\'t skip valid single' => [201, 204, false];

        yield 'Doesn\'t skip valid array' => [202, [207, 301], false];

        yield 'Doesn\'t skip valid regex' => [206, '2[1-9]6', false];
    }

    /**
     * @test
     */
    public function testBypassesAuthenticationInRequests()
    {
        $originRequest = new SymfonyRequest();
        $request = $this->getAuthenticatedRequest($originRequest);
        $this->assertTrue($request->headers->has('Authorization'));
        $this->assertNotSame($originRequest, $request);
    }

    /**
     * @test
     */
    public function testDontSpoofAuthenticationInRequests()
    {
        $originRequest = new SymfonyRequest();
        $authenticationHeaderValue = 'Basic MTIzNDU2Nzg5MDo=';
        $originRequest->headers->set('Authorization', $authenticationHeaderValue);
        $request = $this->getAuthenticatedRequest($originRequest);
        $this->assertTrue($request->headers->has('Authorization'));
        $this->assertEquals($authenticationHeaderValue, $request->headers->get('Authorization'));
        $this->assertSame($originRequest, $request);
    }
}
