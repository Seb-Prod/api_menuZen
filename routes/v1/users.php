<?php

require_once __DIR__ . '/../../controllers/v1/User/AuthController.php';
//require_once __DIR__ . '/../../controllers/v1/User/ProfileController.php';
//require_once __DIR__ . '/../../controllers/v1/User/PasswordController.php';
//require_once __DIR__ . '/../../controllers/v1/User/AdminController.php';

// 🔐 Authentification
$router->post('/api_menuzen/public/register', [AuthController::class, 'register']);
$router->post('/api_menuzen/public/login', [AuthController::class, 'login']);
$router->post('/api_menuzen/public/refresh-token', [AuthController::class, 'refreshToken']);
$router->get('/api_menuzen/public/verify-email', [AuthController::class, 'verifyEmail']);
$router->post('/api_menuzen/public/resend-email', [AuthController::class, 'resendVerificationEmail']);

// 👤 Profil utilisateur (authentifié)
$router->get('/me', [ProfileController::class, 'getProfile']);
$router->put('/me', [ProfileController::class, 'updateProfile']);
$router->put('/change-password', [ProfileController::class, 'changePassword']);

// 🔁 Réinitialisation du mot de passe
$router->post('/reset-password-request', [PasswordController::class, 'requestReset']);
$router->post('/reset-password', [PasswordController::class, 'resetPassword']);

// 🛠️ Administration (droits admin requis)
$router->get('/admin/users', [AdminController::class, 'listUsers']);
$router->delete('/admin/users/{id}', [AdminController::class, 'deleteUser']);