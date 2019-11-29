<?php

/**
 * @var \Laravel\Lumen\Routing\Router $router
 */

$router->get('/', function () {
    return response(
        \Illuminate\Support\Facades\Storage::get('vivinofeed.xml')
    )->header('Content-Type', 'application/xml');
});
