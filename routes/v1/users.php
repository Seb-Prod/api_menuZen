<?php
require_once __DIR__ . '/../../controllers/v1/User/AuthController.php';

$router->post('/api_menuzen/public/register', [AuthController::class, 'register']);
$router->post('/api_menuzen/public/login', [AuthController::class, 'login']);
$router->get('/api_menuzen/public/verify-email', [AuthController::class, 'verifyEmail']);
$router->post('/api_menuzen/public/resend-email', [AuthController::class, 'resendVerificationEmail']);


$router->get('/me', [ProfileController::class, 'getProfile']);
$router->put('/me', [ProfileController::class, 'updateProfile']);
$router->put('/change-password', [ProfileController::class, 'changePassword']);

$router->post('/reset-password-request', [PasswordController::class, 'requestReset']);
$router->post('/reset-password', [PasswordController::class, 'resetPassword']);

$router->get('/admin/users', [AdminController::class, 'listUsers']);
$router->delete('/admin/users/{id}', [AdminController::class, 'deleteUser']);
