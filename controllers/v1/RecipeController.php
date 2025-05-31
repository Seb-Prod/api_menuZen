<?php
require_once __DIR__ . '/../../models/Recipe.php';
include_once __DIR__ . '/../../config/Database.php';

class RecipeConroller
{
    public function index()
    {
        $database = new Database();
        $db = $database->getConnexion();
        $recettes = new Recette($db);
    }
}
