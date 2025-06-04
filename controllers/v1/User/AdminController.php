<?php

require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../core/Response.php';

class AdminController
{
    private function getUserInstance(): User
    {
        $database = new Database();
        $db = $database->getConnexion();
        if ($db === null) {
            Response::error("Connexion à la base de données échouée", 500);
        }
        return new User($db);
    }

    // Liste tous les utilisateurs
    public function listUsers()
    {
        $user = $this->getUserInstance();
        $users = $user->getAll();
        Response::success("Liste des utilisateurs", $users);
    }

    // Supprimer un utilisateur par ID
    public function deleteUser($id)
    {
        $user = $this->getUserInstance();
        if (!$user->deleteById($id)) {
            Response::error("Suppression impossible. Utilisateur introuvable", 404);
        }
        Response::success("Utilisateur supprimé avec succès");
    }

    // Activer ou désactiver un utilisateur
    public function toggleActive($id)
    {
        $user = $this->getUserInstance();
        if (!$user->toggleActiveById($id)) {
            Response::error("Utilisateur introuvable ou erreur lors de l'opération", 404);
        }
        Response::success("Statut du compte mis à jour");
    }

    // Modifier le rôle d’un utilisateur
    public function changeUserRole($id)
    {
        $data = (array) json_decode(file_get_contents("php://input"), true);

        if (empty($data['role'])) {
            Response::error("Le champ 'role' est requis", 400);
        }

        $user = $this->getUserInstance();
        if (!$user->updateRole($id, $data['role'])) {
            Response::error("Impossible de changer le rôle de l'utilisateur", 400);
        }

        Response::success("Rôle mis à jour avec succès");
    }
}