<?php

require_once 'src/controllers/SecurityController.php';
require_once 'src/controllers/DashboardController.php';
require_once 'src/controllers/AssetController.php';
require_once 'src/controllers/ImportController.php';
require_once 'src/controllers/BondController.php';
require_once 'src/controllers/PortfolioController.php';
require_once 'src/controllers/TransactionController.php';
require_once 'src/Middleware/checkRequestAllowed.php';

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
        'assets/delete' => [
            'controller' => 'AssetController',
            'action' => 'delete'
        ],
        'transactions' => [
            'controller' => 'TransactionController',
            'action' => 'index'
        ],
        'transactions/delete' => [
            'controller' => 'TransactionController',
            'action' => 'delete'
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
        'bonds/add' => [
            'controller' => 'BondController',
            'action' => 'add'
        ],
        'portfolio/create' => [
            'controller' => 'PortfolioController',
            'action' => 'create'
        ],
        'portfolio/delete' => [
            'controller' => 'PortfolioController',
            'action' => 'delete'
        ],
        'api/holdings' => [
            'controller' => 'DashboardController',
            'action' => 'getHoldings'
        ],
        'api/stats' => [
            'controller' => 'DashboardController',
            'action' => 'getStats'
        ],
        // Fetch API endpoints
        'api/portfolio/create' => [
            'controller' => 'PortfolioController',
            'action' => 'createApi'
        ],
        'api/portfolio/delete' => [
            'controller' => 'PortfolioController',
            'action' => 'deleteApi'
        ],
        'api/assets/delete' => [
            'controller' => 'AssetController',
            'action' => 'deleteApi'
        ],
        'api/transactions/delete' => [
            'controller' => 'TransactionController',
            'action' => 'deleteApi'
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
            checkRequestAllowed($controller, $action);
            $controller->$action();
        } else {
            http_response_code(404);
            include 'public/views/404.html';
        }
    }
}