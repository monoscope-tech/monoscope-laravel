<?php

namespace APIToolkit\Http\Middleware;

use Illuminate\Support\Facades\Log;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\API\Globals;
use Illuminate\Http\Request;
use Closure;
use Exception;
use Ramsey\Uuid\Uuid;
use  Apitoolkit\Common\Shared;

class APIToolkit
{
  public bool $debug;
  private array $redactHeaders = [];
  private array $redactRequestBody = [];
  private array $redactResponseBody = [];
  private array $errors = [];
  private ?string $serviceVersion;
  private ?string $serviceName;
  private array $tags = [];
  private bool $captureRequestBody = false;
  private bool $captureResponseBody = false;
  private array $config = [];
  private TracerProviderInterface $tracerProvider;

  public function __construct()
  {
    // Read via config() so values survive `php artisan config:cache`; fall back to the defaults if the provider isn't loaded.
    $this->config = config('apitoolkit') ?? require __DIR__ . '/../../../config/apitoolkit.php';
    $this->tags = $this->config['tags'];
    $this->serviceVersion = $this->config['serviceVersion'];
    $this->serviceName = $this->config['serviceName'];
    $this->redactHeaders = $this->config['redactHeaders'];
    $this->redactRequestBody = $this->config['redactRequestBody'];
    $this->redactResponseBody = $this->config['redactResponseBody'];
    $this->captureRequestBody = $this->config['captureRequestBody'];
    $this->captureResponseBody = $this->config['captureResponseBody'];
    $this->debug = $this->config['debug'];

    $this->tracerProvider = Globals::tracerProvider();

    if ($this->debug) {
      Log::debug('APIToolkit: Credentials loaded from server correctly');
    }
  }

  public function addError($error)
  {
    $this->errors[] = $error;
  }

  public function handle(Request $request, Closure $next)
  {
    $tracer = $this->tracerProvider->getTracer("apitoolkit-http-tracer");
    $span = $tracer->spanBuilder('apitoolkit-http-span')->startSpan();
    $newUuid = Uuid::uuid4();
    $msg_id = $newUuid->toString();
    $request = $request->merge([
      'apitoolkitData' => [
        'msg_id' => $msg_id,
        'client' => $this,
      ]
    ]);
    $response = $next($request);
    $this->log($request, $response, $msg_id, $span);
    return $response;
  }
  public function log(Request $request, $response, $msg_id, $span)
  {
    if ($this->debug) {
      Log::debug("APIToolkit: sending payload");
    }
    $errors = $this->errors;
    $query = $request->query();
    $requestBody = "";
    if ($this->captureRequestBody) {
      $requestBody = $request->getContent();
    }
    $responseBody = "";
    if ($this->captureResponseBody) {
      $responseBody = $response->getContent();
    }
    unset($query['apitoolkitData']);
    Shared::setAttributes(
      $span,
      $request->getHttpHost(),
      $response->getStatusCode(),
      $query,
      $request->route() ? $request->route()->parameters() : [],
      $request->headers->all(),
      $response->headers->all(),
      $request->getMethod(),
      $request->getRequestUri(),
      $msg_id,
      $request->route() ? $request->route()->uri : $request->getRequestUri(),
      $requestBody,
      $responseBody,
      $errors,
      $this->config,
      'PhpLaravel',
      null
    );
    if ($this->debug) {
      Log::debug("APIToolkit: payload sent");
    }
  }
}

class InvalidClientMetadataException extends Exception
{
}
