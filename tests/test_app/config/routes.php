<?php

use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;
use Cake\Routing\Router;

Router::defaultRouteClass(DashedRoute::class);

Router::scope('/', function (RouteBuilder $routes) {

    // Login.
    $routes->connect(
        '/test',
        ['controller' => 'TestApp', 'action' => 'test'],
        ['_name' => 'test']
    );
});
