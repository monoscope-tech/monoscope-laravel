<?php

namespace APIToolkit\Tests;

use Orchestra\Testbench\TestCase;
use APIToolkit\APIToolkitLaravel;
use APIToolkit\Http\Middleware\APIToolkit;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use ReflectionClass;

/**
 * @covers \APIToolkit\APIToolkitLaravel
 */
class APIToolkitLaravelTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [\APIToolkit\Provider\APIToolkitServiceProvider::class];
    }

    public function test_observe_guzzle_returns_guzzle_client(): void
    {
        $request = $this->requestWithData(['msg_id' => 'test-msg-id', 'client' => new APIToolkit()]);

        $client = APIToolkitLaravel::observeGuzzle($request, ['pathPattern' => '/example']);

        $this->assertInstanceOf(Client::class, $client);
    }

    public function test_report_error_adds_error_to_client(): void
    {
        $apiToolkit = new APIToolkit();
        $request = $this->requestWithData(['msg_id' => 'test-msg-id', 'client' => $apiToolkit]);

        $error = new \RuntimeException('something broke');
        APIToolkitLaravel::reportError($error, $request);

        $errors = $this->clientErrors($apiToolkit);
        $this->assertCount(1, $errors);
        $this->assertEquals('something broke', $errors[0]['message']);
        $this->assertEquals(\RuntimeException::class, $errors[0]['error_type']);
        $this->assertEquals(\RuntimeException::class, $errors[0]['root_error_type']);
        $this->assertArrayHasKey('stack_trace', $errors[0]);
    }

    public function test_report_error_accumulates_multiple_errors(): void
    {
        $apiToolkit = new APIToolkit();
        $request = $this->requestWithData(['msg_id' => 'test-msg-id', 'client' => $apiToolkit]);

        APIToolkitLaravel::reportError(new \RuntimeException('first'), $request);
        APIToolkitLaravel::reportError(new \LogicException('second'), $request);

        $errors = $this->clientErrors($apiToolkit);
        $this->assertCount(2, $errors);
        $this->assertEquals('first', $errors[0]['message']);
        $this->assertEquals('second', $errors[1]['message']);
    }

    private function requestWithData(array $data): Request
    {
        $request = Request::create('/example', 'GET');
        $request->merge(['apitoolkitData' => $data]);
        return $request;
    }

    private function clientErrors(APIToolkit $client): array
    {
        $prop = (new ReflectionClass($client))->getProperty('errors');
        $prop->setAccessible(true);
        return $prop->getValue($client);
    }
}
