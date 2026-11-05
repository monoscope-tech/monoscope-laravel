<?php

namespace APIToolkit\Http\Middleware;

use Orchestra\Testbench\TestCase;
use APIToolkit\Http\Middleware\APIToolkit;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mockery;
use OpenTelemetry\API\Instrumentation\Configurator;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Ramsey\Uuid\Uuid;

/**
 * @covers APIToolkit\Http\Middleware
 * Currently the tests result in hitting apitoolkit servers to get credentials. 
 * This expectes the APITOOLKIT_KEY environment variable to have been set.
 * TODO: the tests should not affect or hit any 3rd party servers except explicitly required 
 *
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
  private $testJSON = '
    { "store": {
        "book": [
          { "category": "reference",
            "author": "Nigel Rees",
            "title": "Sayings of the Century",
            "price": 8.95,
            "available": true
          },
          { "category": "fiction",
            "author": "Evelyn Waugh",
            "title": "Sword of Honour",
            "price": 12.99,
            "available": false
          }
        ],
        "bicycle": {
          "color": "red",
          "price": 19.95,
          "available": true
        }
      },
      "authors": [
        "Nigel Rees",
        "Herman Melville",
        "J. R. R. Tolkien"
      ]
    }
    ';

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

  public function test_redacts_headers_and_bodies_from_env_lists(): void
  {
    // The documented .env format is a comma-separated string.
    putenv('APITOOLKIT_REDACT_HEADERS=X-Api-Key, X-Other');
    putenv('APITOOLKIT_REDACT_REQUEST_BODY=$.password, $.pin');
    putenv('APITOOLKIT_CAPTURE_REQUEST_BODY=true');
    $exporter = new InMemoryExporter();
    $scope = Configurator::create()->withTracerProvider(new TracerProvider(new SimpleSpanProcessor($exporter)))->activate();
    try {
      $this->app['router']->post('/login', fn() => response('ok')->cookie('sid', 'secret'))->middleware('apitoolkit');
      $this->call('POST', '/login', [], [], [], [
        'HTTP_AUTHORIZATION' => 'Bearer abc',
        'HTTP_X_API_KEY' => 'key',
        'HTTP_ACCEPT' => 'text/plain',
        'CONTENT_TYPE' => 'application/json',
      ], '{"password":"p","pin":"1","user":"u"}')->assertOk();
      $attrs = $exporter->getSpans()[0]->getAttributes()->toArray();
    } finally {
      $scope->detach();
      putenv('APITOOLKIT_REDACT_HEADERS');
      putenv('APITOOLKIT_REDACT_REQUEST_BODY');
      putenv('APITOOLKIT_CAPTURE_REQUEST_BODY');
    }
    foreach (['request.header.authorization', 'request.header.x-api-key', 'response.header.set-cookie'] as $h) {
      $this->assertSame('[CLIENT_REDACTED]', $attrs["http.$h"], $h);
    }
    $this->assertSame('text/plain', $attrs['http.request.header.accept']);
    $this->assertJsonStringEqualsJsonString('{"password":"[CLIENT_REDACTED]","pin":"[CLIENT_REDACTED]","user":"u"}', base64_decode($attrs['http.request.body']));
  }





}
