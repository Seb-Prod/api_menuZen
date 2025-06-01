<?php
require_once __DIR__ . '/../../../models/User.php';
require_once __DIR__ . '/../../../config/Database.php';
require_once __DIR__ . '/../../../core/Response.php';
require_once __DIR__ . '/../../../helpers/Validator.php';
require_once __DIR__ . '/../../../helpers/Mailer.php';
require_once __DIR__ . '/../../../helpers/JWT.php';

class AuthController
{
    /**
     * Instancie un objet User avec une connexion à la base de données
     */
    private function getUserInstance(): User
    {
        $database = new Database();
        $db = $database->getConnexion();

        if ($db === null) {
            Response::error('Impossible de se connecter à la base de données', 500);
        }

        return new User($db);
    }

    /**
     * Inscription d’un nouvel utilisateur + envoi d’un e-mail de vérification
     */
    public function register()
    {
        $user = $this->getUserInstance();
        $data = (array) json_decode(file_get_contents("php://input"), true);

        // Règles de validation
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
                "Le mot de passe doit comporter 8 caractères, une majuscule, une minuscule et un chiffre"
            )
        ];

        // Validation des données
        $errors = Validator::validate($data, $rules);
        if (!empty($errors)) {
            Response::error($errors, 400);
        }

        // Hydratation de l'objet User
        foreach (array_keys($rules) as $key) {
            if (isset($data[$key])) {
                $user->$key = $data[$key];
            }
        }

        // Vérifie si l’utilisateur existe déjà
        if ($user->exists()) {
            Response::error("Nom d'utilisateur ou email déjà utilisé", 409);
        }

        // Enregistre l’utilisateur
        if ($user->store()) {
            $config = require __DIR__ . '/../../../config/config.php';
            $adress_api = $config['adress_api'];
            $verificationLink = $adress_api . "verify-email?token=" . urlencode($user->verification_token);

            // Envoie de l’email de vérification
            if (!sendVerificationEmail($user->email, $user->username, $verificationLink)) {
                Response::error("Utilisateur créé mais l'email de vérification n'a pas pu être envoyé", 500);
            }

            Response::success("Utilisateur enregistré. Un e-mail de vérification a été envoyé.", [
                'email' => $user->email
            ], 201);
        } else {
            Response::error("Une erreur est survenue lors de l'enregistrement de l'utilisateur", 500);
        }
    }

    /**
     * Connexion d’un utilisateur + génération d’un token JWT
     */
    public function login()
    {
        $data = (array) json_decode(file_get_contents("php://input"), true);

        // Règles de validation
        $rules = [
            'email' => Validator::withMessage(
                Validator::email(),
                "Un email valide est obligatoire"
            ),
            'password' => Validator::withMessage(
                Validator::requiredString(),
                "Le mot de passe est requis"
            )
        ];

        $errors = Validator::validate($data, $rules);
        if (!empty($errors)) {
            Response::error($errors, 400);
        }

        $user = $this->getUserInstance();
        $user->email = $data['email'];

        // Recherche utilisateur
        if (!$user->findByEmail()) {
            Response::error("Email ou mot de passe incorrect", 401);
        }

        // Vérifie si le compte est temporairement bloqué
        if ($user->locked_until && strtotime($user->locked_until) > time()) {
            $minutesLeft = ceil((strtotime($user->locked_until) - time()) / 60);
            Response::error("Compte temporairement bloqué. Réessayez dans $minutesLeft minute(s).", 403);
        }

        // Vérifie si le compte est activé
        if (!$user->is_active) {
            Response::error("Veuillez vérifier votre adresse e-mail avant de vous connecter", 403);
        }

        // Vérifie le mot de passe
        if (!password_verify($data['password'], $user->password)) {
            $user->incrementFailedAttempts();

            if ($user->failed_attempts >= 3) {
                $user->lockAccount();
                Response::error("Trop de tentatives. Compte bloqué pour 15 minutes.", 403);
            }

            Response::error("Email ou mot de passe incorrect", 401);
        }

        // Connexion réussie : reset des tentatives + mise à jour login
        $user->resetFailedAttempts();
        $user->updateLastLogin();

        // Génération du JWT
        $config = require __DIR__ . '/../../../config/config.php';
        $jwt = new JWT($config);
        $token = $jwt->generer([
            'id_user' => $user->id_user,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role
        ]);

        Response::success("Connexion réussie", [
            'id_user' => $user->id_user,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'token' => $token
        ]);
    }

    /**
     * Vérifie et active un compte utilisateur via un token
     */
    public function verifyEmail()
    {
        $token = $_GET['token'] ?? null;

        if (!$token) {
            Response::error("Le token est manquant", 400);
        }

        $user = $this->getUserInstance();

        if ($user->verifyEmailToken($token)) {
            Response::success("Compte activé avec succès");
        } else {
            Response::error("Token invalide ou compte déjà activé", 400);
        }
    }

    /**
     * Réenvoie un mail pour activer le compte
     *
     * @return void
     */
    public function resendVerificationEmail()
    {
        $data = (array) json_decode(file_get_contents("php://input"), true);

        // Validation des données
        $rules = [
            'email' => Validator::withMessage(
                Validator::email(),
                "Un email valide est requis"
            )
        ];

        $errors = Validator::validate($data, $rules);
        if (!empty($errors)) {
            Response::error($errors, 400);
        }

        // Instancier User
        $user = $this->getUserInstance();
        $user->email = $data['email'];

        if (!$user->findByEmail()) {
            Response::error("Aucun compte associé à cet email", 404);
        }

        if ($user->is_active) {
            Response::error("Le compte est déjà activé", 400);
        }

        // Génère un nouveau token
        $user->generateVerificationToken();
        if (!$user->updateVerificationToken()) {
            Response::error("Impossible de générer un nouveau token", 500);
        }

        // Envoi e-mail
        $config = require __DIR__ . '/../../../config/config.php';
        $adress_api = $config['adress_api'];
        $verificationLink = $adress_api . "verify-email?token=" . urlencode($user->verification_token);

        if (!sendVerificationEmail($user->email, $user->username, $verificationLink)) {
            Response::error("Erreur lors de l'envoi de l'e-mail", 500);
        }

        Response::success("Un nouvel e-mail de vérification a été envoyé");
    }
}
