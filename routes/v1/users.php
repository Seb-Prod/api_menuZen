<?php
require_once __DIR__ . '/../../controllers/v1/UserController.php';

$userController = new UserController();

$router->get('/api_MenuZen/public/v1/users', [$userController, 'index']);
$router->post('/api_MenuZen/public/v1/users', [$userController, 'store']);