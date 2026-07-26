<?php

namespace APIToolkit;

use Apitoolkit\Common\Shared;
use GuzzleHttp\Client;

class APIToolkitLaravel
{
    public static function observeGuzzle($request, $options): Client
    {
        $apitoolkit = $request->apitoolkitData;
        $msgId = $apitoolkit['msg_id'];
        return Shared::observeGuzzle($options, $msgId);
    }

    public static function reportError($error, $request): void
    {
        $apitoolkit = $request->apitoolkitData;
        $client = $apitoolkit['client'];
        Shared::reportError($error, $client);
    }
}
