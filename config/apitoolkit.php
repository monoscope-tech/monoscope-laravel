<?php

$list = fn($key) => array_values(array_filter(array_map('trim', explode(",", env($key, "")))));

return [
  'redactHeaders' => $list('APITOOLKIT_REDACT_HEADERS'),
  'redactRequestBody' => $list('APITOOLKIT_REDACT_REQUEST_BODY'),
  'redactResponseBody' => $list('APITOOLKIT_REDACT_RESPONSE_BODY'),
  'serviceVersion' => env('APITOOLKIT_SERVICE_VERSION', null),
  'tags' => $list('APITOOLKIT_TAGS'),
  'captureRequestBody' => env('APITOOLKIT_CAPTURE_REQUEST_BODY', false),
  'captureResponseBody' => env('APITOOLKIT_CAPTURE_RESPONSE_BODY', false),
  'debug' => env('APITOOLKIT_DEBUG', false),
  'serviceName' => env('APITOOLKIT_SERVICE_NAME', ""),
];
