<?php

require_once 'src/controllers/SecurityController.php';
require_once 'src/controllers/DashboardController.php';
require_once 'src/controllers/AssetController.php';
require_once 'src/controllers/ImportController.php';

class Routing {

    public static array $routes = [
        '' => [
            'controller' => 'DashboardController',
            'action' => 'index'
        ],
        'login' => [
            'controller' => 'SecurityController',
            'action' => 'login'
        ],
        'register' => [
            'controller' => 'SecurityController',
            'action' => 'register'
        ],
        'logout' => [
            'controller' => 'SecurityController',
            'action' => 'logout'
        ],
        'dashboard' => [
            'controller' => 'DashboardController',
            'action' => 'index'
        ],
        'dashboard/refresh' => [
            'controller' => 'DashboardController',
            'action' => 'refreshPrices'
        ],
        'assets' => [
            'controller' => 'AssetController',
            'action' => 'index'
        ],
        'import' => [
            'controller' => 'ImportController',
            'action' => 'index'
        ],
        'import/upload' => [
            'controller' => 'ImportController',
            'action' => 'upload'
        ],
        'import/confirm' => [
            'controller' => 'ImportController',
            'action' => 'confirm'
        ],
        'import/cancel' => [
            'controller' => 'ImportController',
            'action' => 'cancel'
        ],
        'import/manual' => [
            'controller' => 'ImportController',
            'action' => 'manual'
        ],
        'api/holdings' => [
            'controller' => 'DashboardController',
            'action' => 'getHoldings'
        ],
        'api/stats' => [
            'controller' => 'DashboardController',
            'action' => 'getStats'
        ]
    ];

    public static function run(string $path): void
    {
        $path = strtok($path, '?');
        
        if (array_key_exists($path, self::$routes)) {
            $route = self::$routes[$path];
            $controllerName = $route['controller'];
            $action = $route['action'];

            $controller = new $controllerName();
            $controller->$action();
        } else {
            http_response_code(404);
            include 'public/views/404.html';
        }
    }
}