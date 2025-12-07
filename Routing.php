<?php

require_once 'src/controllers/SecurityController.php';
require_once 'src/controllers/DashboardController.php';

class Routing {

    public static array $routes = [
        // Auth
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
        // Dashboard
        'dashboard' => [
            'controller' => 'DashboardController',
            'action' => 'index'
        ],
        'dashboard/refresh' => [
            'controller' => 'DashboardController',
            'action' => 'refreshPrices'
        ],
        // API
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
        // Usuń query string z path
        $path = strtok($path, '?');
        
        // Sprawdź czy route istnieje
        if (array_key_exists($path, self::$routes)) {
            $route = self::$routes[$path];
            $controllerName = $route['controller'];
            $action = $route['action'];

            $controller = new $controllerName();
            $controller->$action();
        } else {
            // 404 - nie znaleziono route
            http_response_code(404);
            include 'public/views/404.html';
        }
    }
}