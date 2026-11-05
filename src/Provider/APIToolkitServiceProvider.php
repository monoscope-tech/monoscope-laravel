<?php

namespace APIToolkit\Provider;

// The service provider which seems correct
// use APIToolkit\Service\APIToolkitService;
use Illuminate\Support\ServiceProvider;

class APIToolkitServiceProvider extends ServiceProvider
{
  public function register()
  {
    $this->mergeConfigFrom(__DIR__ . '/../../config/apitoolkit.php', 'apitoolkit');
  }

  public function boot()
  {
    $this->publishes([__DIR__ . '/../../config/apitoolkit.php' => config_path('apitoolkit.php')], 'config');
  }
}

function generateRandomString($length = 10)
{
  return substr(str_shuffle(str_repeat($x = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length / strlen($x)))), 1, $length);
}
