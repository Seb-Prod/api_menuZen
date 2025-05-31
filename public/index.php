<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../core/Router.php';

// Démarre le routeur
$router = new Router();

// Charge les routes
require_once __DIR__ . '/../routes/v1/recipes.php';

// Lance la recherche de route
$router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);