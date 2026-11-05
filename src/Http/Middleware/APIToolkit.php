<?php

namespace APIToolkit\Http\Middleware;

use Illuminate\Support\Facades\Log;
use OpenTelemetry\SDK\Trace\TracerProvider;
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
  private TracerProvider $tracerProvider;

  public function __construct()
  {
    $list = fn($key) => array_values(array_filter(array_map('trim', explode(",", env($key, "")))));
    $this->tags = $list('APITOOLKIT_TAGS');
    $this->serviceVersion = env('APITOOLKIT_SERVICE_VERSION', null);
    $this->serviceName = env('APITOOLKIT_SERVICE_NAME', "");
    $this->redactHeaders = $list('APITOOLKIT_REDACT_HEADERS');
    $this->redactRequestBody = $list('APITOOLKIT_REDACT_REQUEST_BODY');
    $this->redactResponseBody = $list('APITOOLKIT_REDACT_RESPONSE_BODY');
    $this->captureRequestBody = env('APITOOLKIT_CAPTURE_REQUEST_BODY', false);
    $this->captureResponseBody = env('APITOOLKIT_CAPTURE_RESPONSE_BODY', false);
    $this->debug = env('APITOOLKIT_DEBUG', false);
    $this->config = [
      'redactHeaders' => $this->redactHeaders,
      'redactRequestBody' => $this->redactRequestBody,
      'redactResponseBody' => $this->redactResponseBody,
      'serviceVersion' => $this->serviceVersion,
      'tags' => $this->tags,
      'captureRequestBody' => $this->captureRequestBody,
      'captureResponseBody' => $this->captureResponseBody,
      'debug' => $this->debug,
      'serviceName' => $this->serviceName,
    ];

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
