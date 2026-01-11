<?php

require_once __DIR__ . '/../Attribute/AllowedMethods.php';

function checkRequestAllowed(object $controller, string $methodName): void {
    $reflection = new ReflectionMethod($controller, $methodName);
    $attributes = $reflection->getAttributes(AllowedMethods::class);
    
    if (!empty($attributes)) {
        $instance = $attributes[0]->newInstance();
        $allowed = $instance->methods;
        
        if (!in_array($_SERVER['REQUEST_METHOD'], $allowed)) {
            http_response_code(405);
            include __DIR__ . '/../../public/views/404.html'; // Method Not Allowed
            exit;
        }
    }
}