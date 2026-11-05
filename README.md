<div align="center">

![APItoolkit's Logo](https://github.com/apitoolkit/.github/blob/main/images/logo-white.svg?raw=true#gh-dark-mode-only)
![APItoolkit's Logo](https://github.com/apitoolkit/.github/blob/main/images/logo-black.svg?raw=true#gh-light-mode-only)

## Laravel SDK

[![APItoolkit SDK](https://img.shields.io/badge/APItoolkit-SDK-0068ff?logo=laravel)](https://github.com/topics/apitoolkit-sdk) [![Join Discord Server](https://img.shields.io/badge/Chat-Discord-7289da)](https://apitoolkit.io/discord?utm_campaign=devrel&utm_medium=github&utm_source=sdks_readme) [![APItoolkit Docs](https://img.shields.io/badge/Read-Docs-0068ff)](https://apitoolkit.io/docs/sdks/php/laravel?utm_campaign=devrel&utm_medium=github&utm_source=sdks_readme)

APItoolkit is an end-to-end API and web services management toolkit for engineers and customer support teams. To integrate your Laravel (PHP) application with APItoolkit, you need to use this SDK to monitor incoming traffic, aggregate the requests, and then deliver them to the APItoolkit's servers.

</div>

---

## Table of Contents

- [Installation](#installation)
- [Open Telemetry Configuration](#setup-opentelemetry)
- [APItoolkit Middleware Setup](#setup-apitoolkit-middleware)
- [Contributing and Help](#contributing-and-help)
- [License](#license)

---

## Installation

Kindly run the command below to install the apitoolkit-laravel sdk and required otel packages:

```sh
composer require \
    open-telemetry/sdk \
    open-telemetry/exporter-otlp \
    apitoolkit/apitoolkit-laravel

```

## Setup Opentelemetry

#### Installing opentelemetry extension

After installing the necessary packages, you'll need to install the opentelemetry extention and add it to your `php.ini` file

```sh
pecl install opentelemetry
```

Then add it to your `php.ini` file like so.

```ini
[opentelemetry]
extension=opentelemetry.so
```

export the following opentelemetry variables

```sh
export OTEL_PHP_AUTOLOAD_ENABLED=true
export OTEL_SERVICE_NAME=your-service-name
export OTEL_TRACES_EXPORTER=otlp
export OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
export OTEL_EXPORTER_OTLP_ENDPOINT=http://otelcol.apitoolkit.io:4318
export OTEL_RESOURCE_ATTRIBUTES="at-project-key={ENTER_YOUR_API_KEY_HERE}"
export OTEL_PROPAGATORS=baggage,tracecontext
```

## Setup APItoolkit Middleware

Next, register the middleware in the `app/Http/Kernel.php` file under the correct middleware group (e.g., `api`) or at the root, like so. This creates a customs spans which captures and sends http request info such as headers, requests and repsonse bodies, matched route etc. for each request

```php
<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    protected $middlewareGroups = [
        'api' => [
            // Other middleware here...
            \APIToolkit\Http\Middleware\APIToolkit::class, // Initialize the APItoolkit client
        ],
    ];
}
```

Alternatively, if you want to monitor specific routes, you can register the middleware, like so:

```php
<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    protected $routeMiddleware = [
        // Other middleware here...
        'apitoolkit' => \APIToolkit\Http\Middleware\APIToolkit::class,
    ];
}
```

Then you can use the `apitoolkit` middleware in your routes like so:

```php
Route::get('/', function () {
    return response()->json([
        'message' => 'Welcome to your new application!'
    ]);
})->middleware('apitoolkit');
```

### Configuration

The middleware reads its settings from `config/apitoolkit.php`, so they keep working after `php artisan config:cache`. By default that file reads these environment variables (lists are comma-separated):

```sh
APITOOLKIT_SERVICE_NAME=my-service
APITOOLKIT_SERVICE_VERSION=1.0.0
APITOOLKIT_TAGS=prod,eu
APITOOLKIT_REDACT_HEADERS=X-Api-Key,X-Session
APITOOLKIT_REDACT_REQUEST_BODY=$.password,$.card.number
APITOOLKIT_REDACT_RESPONSE_BODY=$.token
APITOOLKIT_CAPTURE_REQUEST_BODY=false
APITOOLKIT_CAPTURE_RESPONSE_BODY=false
APITOOLKIT_DEBUG=false
```

To change the config file itself, publish it:

```sh
php artisan vendor:publish --provider="APIToolkit\Provider\APIToolkitServiceProvider"
```

> [!NOTE]
>
> The `{ENTER_YOUR_API_KEY_HERE}` demo string should be replaced with the [API key](https://apitoolkit.io/docs/dashboard/settings-pages/api-keys?utm_campaign=devrel&utm_medium=github&utm_source=sdks_readme) generated from the APItoolkit dashboard.

<br />

> [!IMPORTANT]
>
> To learn more configuration options (redacting fields, error reporting, outgoing requests, etc.), please read this [SDK documentation](https://apitoolkit.io/docs/sdks/php/laravel?utm_campaign=devrel&utm_medium=github&utm_source=sdks_readme).

## Contributing and Help

To contribute to the development of this SDK or request help from the community and our team, kindly do any of the following:

- Read our [Contributors Guide](https://github.com/apitoolkit/.github/blob/main/CONTRIBUTING.md).
- Join our community [Discord Server](https://apitoolkit.io/discord?utm_campaign=devrel&utm_medium=github&utm_source=sdks_readme).
- Create a [new issue](https://github.com/apitoolkit/apitoolkit-laravel/issues/new/choose) in this repository.

## License

This repository is published under the [MIT](LICENSE) license.

---

<div align="center">

<a href="https://apitoolkit.io?utm_campaign=devrel&utm_medium=github&utm_source=sdks_readme" target="_blank" rel="noopener noreferrer"><img src="https://github.com/apitoolkit/.github/blob/main/images/icon.png?raw=true" width="40" /></a>

</div>
