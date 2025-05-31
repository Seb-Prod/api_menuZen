<?php
require_once __DIR__ . '/../../models/Recipe.php';
include_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../core/Response.php';

class RecipeController
{
    public function index()
    {


        $database = new Database();
        $db = $database->getConnexion();

        if ($db === null) {
            Response::error('Impossible de se connecter à la base de données',500);
        }

        $recettes = new Recette($db);

        $stmt = $recettes->getAll();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        Response::success("Liste des recettes", $rows);
    }
}
