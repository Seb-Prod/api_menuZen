<?php
require_once __DIR__ . '/../../models/User.php';
include_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../helpers/Validator.php';

class UserController
{
    public function store()
    {
        // Connection à la basse de données
        $database = new Database();
        $db = $database->getConnexion();

        if ($db === null) {
            Response::error('Impossible de se connecter à la base de données', 500);
        }

        // Création d'un instance de la classe Users.
        $user = new User($db);

        // Récupération des données JSON reçues dans le corps de la requeête.
        $data = (array) json_decode(file_get_contents("php://input"), true);

        // Définition des règles de validation pour les données reçues.
        $rules = [
            'username' => Validator::withMessage(
                Validator::requiredStringMax(50),
                "Un nom d'utilisateur est obligatoire et ne doit pas dépasser 50 caractères"
            ),
            'email' => Validator::withMessage(
                Validator::email(),
                "Un email valide est obligatoire"
            ),
            'password' => Validator::withMessage(
                Validator::password(),
                "Le mot de passe doit être de 8 caractères, une majuscule, une minuscile et un chiffre"
            )
        ];

        // Validation des données selon les règles définies.
        $errors = Validator::validate($data, $rules);

        // Si des erreurs de validation sont présentes, envoie d'une réponse d'erreur.
        if (!empty($errors)) {
            Response::error($errors, 400);
        }

        // Assignation des valeurs des données reçus aux propirétés de l'objet 'user'
        foreach (array_keys($rules) as $key) {
            if (isset($data[$key])) {
                $user->$key = $data[$key];
            }
        }

        // Vérification existence utilisateur
        if ($user->exists()) {
            Response::error("Nom d'utilisateur ou email déjà utilisé", 409);
        }

        // Tentative de création d'un user
        if ($user->store()) {
            Response::success("Utilisateur enregistré avec succès", [
                'username' => $user->username,
                'email' => $user->email
            ], 201);
        } else {
            Response::error("Une erreur est survenue lors de l'enregistrement de l'utilisateur", 500);
        }
    }
}
