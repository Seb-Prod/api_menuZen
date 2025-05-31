<?php
require_once __DIR__ . '/../../controllers/v1/RecipeController.php';

$recipeController = new RecipeController();

$router->get('/api_MenuZen/public/v1/recipes', [$recipeController, 'index']);