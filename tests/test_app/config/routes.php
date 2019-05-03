<?php

use Cake\Routing\RouteBuilder;
use Cake\Routing\Router;
use Cake\Routing\Route\DashedRoute;

Router::defaultRouteClass(DashedRoute::class);

Router::scope('/', function (RouteBuilder $routes) {

    // Login.
    $routes->connect(
        '/test',
        ['controller' => 'TestApp', 'action' => 'test'],
        ['_name' => 'test']
    );
});
