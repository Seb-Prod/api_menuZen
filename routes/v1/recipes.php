<?php
require_once __DIR__ . '/../../controllers/v1/RecipeController.php';

$recipeController = new RecipeConroller();

if ($_SERVER['REQUEST_URI'] === '/recipes' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $recipeController->index();
}