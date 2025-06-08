<?php

require_once __DIR__ . '/../../../models/User.php';
require_once __DIR__ . '/../../../models/RefreshToken.php';
require_once __DIR__ . '/../../../config/Database.php';
require_once __DIR__ . '/../../../core/Response.php';
require_once __DIR__ . '/../../../helpers/Validator.php';
require_once __DIR__ . '/../../../helpers/Mailer.php';
require_once __DIR__ . '/../../../helpers/JWT.php';

class AuthController
{
    private function getDB()
    {
        $database = new Database();
        $db = $database->getConnexion();
        if ($db === null) {
            Response::error('Connexion à la base de données impossible', 500);
        }
        return $db;
    }

    private function getUser(): User
    {
        return new User($this->getDB());
    }

    private function getConfig(): array
    {
        return require __DIR__ . '/../../../config/config.php';
    }

    public function register()
    {
        $data = (array) json_decode(file_get_contents("php://input"), true);

        $rules = [
            'username' => Validator::withMessage(Validator::requiredStringMax(50), "Nom d'utilisateur requis (max 50 caractères)"),
            'email' => Validator::withMessage(Validator::email(), "Email valide requis"),
            'password' => Validator::withMessage(Validator::password(), "Mot de passe faible (8 caractères, majuscule, minuscule, chiffre)")
        ];

        $errors = Validator::validate($data, $rules);
        if ($errors) Response::error($errors, 400);

        $user = $this->getUser();
        foreach (['username', 'email', 'password'] as $field) {
            $user->$field = $data[$field];
        }

        if ($user->exists()) {
            Response::error("Nom d'utilisateur ou email déjà utilisé", 409);
        }

        if (!$user->store()) {
            Response::error("Erreur lors de l'enregistrement", 500);
        }

        $link = $this->getConfig()['adress_api'] . "verify-email?token=" . urlencode($user->verification_token);
        if (!sendVerificationEmail($user->email, $user->username, $link)) {
            Response::error("Utilisateur créé, mais e-mail non envoyé", 500);
        }

        Response::success("Utilisateur enregistré. Vérification par e-mail envoyée", ['email' => $user->email], 201);
    }

    public function login()
    {
        $data = (array) json_decode(file_get_contents("php://input"), true);

        $rules = [
            'email' => Validator::withMessage(Validator::email(), "Email valide requis"),
            'password' => Validator::withMessage(Validator::requiredString(), "Mot de passe requis")
        ];

        $errors = Validator::validate($data, $rules);
        if ($errors) Response::error($errors, 400);

        $user = $this->getUser();
        $user->email = $data['email'];

        if (!$user->findByEmail()) {
            Response::error("Email ou mot de passe incorrect", 401);
        }

        if ($user->locked_until && strtotime($user->locked_until) > time()) {
            $wait = ceil((strtotime($user->locked_until) - time()) / 60);
            Response::error("Compte bloqué. Réessayez dans $wait minute(s)", 403);
        }

        if (!$user->is_active) {
            Response::error("Compte non activé", 403);
        }

        if (!password_verify($data['password'], $user->password)) {
            $user->incrementFailedAttempts();
            if ($user->failed_attempts >= 3) {
                $user->lockAccount();
                Response::error("Trop de tentatives. Compte bloqué 15 min", 403);
            }
            Response::error("Email ou mot de passe incorrect", 401);
        }

        $user->resetFailedAttempts();
        $user->updateLastLogin();

        $jwt = new JWT($this->getConfig());

        $access_token = $jwt->generer([
            'id_user' => $user->id_user,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role
        ], 3600);

        $refresh_token = $jwt->generer(['id_user' => $user->id_user], 604800);

        $rt = new RefreshToken($this->getDB());
        $rt->id_user = $user->id_user;

        if ($rt->existsForUser()) {
            $rt->token = $refresh_token;
            $rt->expires_at = date("Y-m-d H:i:s", time() + 604800);
            $rt->update(); // méthode à créer dans le modèle
        } else {
            $rt->token = $refresh_token;
            $rt->expires_at = date("Y-m-d H:i:s", time() + 604800);
            $rt->store();
        }

        Response::success("Connexion réussie", [
            'id_user' => $user->id_user,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'token' => $access_token,
            'token_expires_in' => 3600,
            'refresh_token' => $refresh_token,
            'refresh_token_expires_in' => 604800
        ]);
    }

    public function verifyEmail()
    {
        $token = $_GET['token'] ?? null;
        if (!$token) {
            Response::error("Token manquant", 400);
        }

        $user = $this->getUser();
        if ($user->verifyEmailToken($token)) {
            Response::success("Email vérifié, compte activé");
        } else {
            Response::error("Token invalide ou compte déjà activé", 400);
        }
    }

    public function resendVerificationEmail()
    {
        $data = (array) json_decode(file_get_contents("php://input"), true);

        $rules = [
            'email' => Validator::withMessage(Validator::email(), "Email requis")
        ];
        $errors = Validator::validate($data, $rules);
        if ($errors) Response::error($errors, 400);

        $user = $this->getUser();
        $user->email = $data['email'];

        if (!$user->findByEmail()) {
            Response::error("Aucun compte avec cet email", 404);
        }

        if ($user->is_active) {
            Response::error("Le compte est déjà activé", 400);
        }

        $user->generateVerificationToken();
        if (!$user->updateVerificationToken()) {
            Response::error("Erreur de génération de token", 500);
        }

        $link = $this->getConfig()['adress_api'] . "verify-email?token=" . urlencode($user->verification_token);

        if (!sendVerificationEmail($user->email, $user->username, $link)) {
            Response::error("Erreur lors de l'envoi de l'e-mail", 500);
        }

        Response::success("Nouveau mail de vérification envoyé");
    }
}
