<?php

namespace APIToolkit\Tests\Http\Middleware;

use Orchestra\Testbench\TestCase;
use APIToolkit\Http\Middleware\APIToolkit;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mockery;
use Ramsey\Uuid\Uuid;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;

/**
 * @covers APIToolkit\Http\Middleware
 */
class APIToolkitTest extends TestCase
{
  // Define environment setup
  protected function getEnvironmentSetUp($app)
  {
    $app['config']->set('logging.default', 'stderr');
    $app['router']->aliasMiddleware('apitoolkit', APIToolkit::class);
  }

  protected function tearDown(): void
  {
    Mockery::close();  // Close Mockery session after each test
  }

  public function testMiddleware()
  {
    $expectedJSON = '
    { "store": {
        "book": [
          { "category": "[CLIENT_REDACTED]",
            "author": "Nigel Rees"
          },
          { "category": "[CLIENT_REDACTED]",
            "author": "Evelyn Waugh"
          }
        ]
      }
    }
    ';
    // Define a route for testing
    $this->app['router']->get('/test-route/{id}/{name}', function (Request $request) {
      $testJSON = '
    { "store": {
        "book": [
          { "category": "reference",
            "author": "Nigel Rees"
          },
          { "category": "fiction",
            "author": "Evelyn Waugh"
          }
        ]
      }
    }
    ';
      return response()->json(json_decode($testJSON), 200);
    })->middleware('apitoolkit');

    $response = $this->get('/test-route/id22/nameVal');
    $testJSON = '
    { "store": {
        "book": [
          { "category": "reference",
            "author": "Nigel Rees"
          },
          { "category": "fiction",
            "author": "Evelyn Waugh"
          }
        ]
      }
    }
    ';
    $response->assertStatus(200)
      ->assertJson(json_decode($testJSON, true));
  }

  // Register package service providers
  protected function getPackageProviders($app)
  {
    return [\APIToolkit\Provider\APIToolkitServiceProvider::class];
  }

  public function testLog()
  {
    putenv('APITOOLKIT_CAPTURE_RESPONSE_BODY=1');

    $request = Request::create('/example', 'GET', [
      'query_param1' => 'value1',
      'query_param2' => 'value2',
    ], [], [], [
      'HTTP_Custom-Header' => 'CustomValue',
      'HTTP_Authorization' => 'Bearer blabla',
    ]);
    $response = new Response('Body content here', 200, [
      'Content-Type' => 'application/json',
      'Custom-Header' => 'CustomValue',
    ]);

    $exporter = new InMemoryExporter();
    $tracerProvider = new TracerProvider(new SimpleSpanProcessor($exporter));
    $span = $tracerProvider->getTracer('test')->spanBuilder('apitoolkit-http-span')->startSpan();

    $apiToolkit = new APIToolkit();
    $apiToolkit->debug = false;
    $newUuid = Uuid::uuid4();
    $msg_id = $newUuid->toString();

    $apiToolkit->log($request, $response, $msg_id, $span);

    $spans = $exporter->getSpans();
    $this->assertCount(1, $spans);
    $attrs = $spans[0]->getAttributes();
    $this->assertEquals(200, $attrs->get('http.response.status_code'));
    $this->assertEquals('GET', $attrs->get('http.request.method'));
    $this->assertEquals('/example?query_param1=value1&query_param2=value2', $attrs->get('http.target'));
    $query = json_decode($attrs->get('http.request.query_params'));
    $this->assertEquals('value1', $query->query_param1);
    $this->assertEquals('value2', $query->query_param2);
    $this->assertEquals(base64_encode('Body content here'), $attrs->get('http.response.body'));

    putenv('APITOOLKIT_CAPTURE_RESPONSE_BODY');
  }
}
