<?php
require_once __DIR__ . '/../../controllers/v1/UserController.php';

$userController = new UserController();

$router->get('/api_menuzen/public/v1/users', [$userController, 'index']);
$router->get('/api_menuzen/public/verify-email', [$userController, 'verifyEmail']);
$router->post('/api_menuzen/public/v1/users', [$userController, 'store']);
