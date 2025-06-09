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
    private $durationToken = 60; //1200;
    private $durationTokenRefresh = 604800;
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
            'login' => Validator::withMessage(Validator::requiredString(), "Identifiant requis (email ou pseudo)"),
            'password' => Validator::withMessage(Validator::requiredString(), "Mot de passe requis")
        ];

        $errors = Validator::validate($data, $rules);
        if ($errors) Response::error($errors, 400);

        $login = $data['login'];
        $password = $data['password'];

        $user = $this->getUser();

        // Déterminer si c'est un email ou un username
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            // C'est un email
            $user->email = $login;
            $found = $user->findByEmail();
        } else {
            // C'est un username
            $user->username = $login;
            $found = $user->findByUsername();
        }

        if (!$found) {
            Response::error("Email ou pseudo ou mot de passe incorrect", 401);
        }

        if ($user->locked_until && strtotime($user->locked_until) > time()) {
            $wait = ceil((strtotime($user->locked_until) - time()) / 60);
            Response::error("Compte bloqué. Réessayez dans $wait minute(s)", 403);
        }

        if (!$user->is_active) {
            Response::error("Compte non activé", 403);
        }

        if (!password_verify($password, $user->password)) {
            $user->incrementFailedAttempts();
            if ($user->failed_attempts >= 3) {
                $user->lockAccount();
                Response::error("Trop de tentatives. Compte bloqué 15 min", 403);
            }
            Response::error("Email ou pseudo ou mot de passe incorrect", 401);
        }

        $user->resetFailedAttempts();
        $user->updateLastLogin();

        $jwt = new JWT($this->getConfig());

        $access_token = $jwt->generer([
            'id_user' => $user->id_user,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role
        ], $this->durationToken);

        $refresh_token = $jwt->generer(['id_user' => $user->id_user], $this->durationTokenRefresh);

        $rt = new RefreshToken($this->getDB());
        $rt->id_user = $user->id_user;

        if ($rt->existsForUser()) {
            $rt->token = $refresh_token;
            $rt->expires_at = date("Y-m-d H:i:s", time() + $this->durationTokenRefresh);
            $rt->update();
        } else {
            $rt->token = $refresh_token;
            $rt->expires_at = date("Y-m-d H:i:s", time() + $this->durationTokenRefresh);
            $rt->store();
        }

        Response::success("Connexion réussie", [
            'id_user' => $user->id_user,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'token' => $access_token,
            'token_expires_at' => time() + $this->durationToken,
            'refresh_token' => $refresh_token,
            'refresh_token_expires_at' => time() + $this->durationTokenRefresh,
        ]);
    }

    public function refreshToken()
    {
        $data = (array) json_decode(file_get_contents("php://input"), true);

        if (empty($data['refreshToken'])) {
            Response::error("Refresh token manquant", 400);
        }

        $refreshToken = $data['refreshToken'];

        $rt = new RefreshToken($this->getDB());
        $tokenData = $rt->findByToken($refreshToken);

        if (!$tokenData) {
            Response::error("Refresh token invalide", 401);
        }

        if ($tokenData['revoked']) {
            Response::error("Refresh token révoqué", 401);
        }

        if (strtotime($tokenData['expires_at']) < time()) {
            Response::error("Refresh token expiré", 401);
        }

        $user = $this->getUser();
        $user->id_user = $tokenData['id_user'];

        if (!$user->getById()) {
            Response::error("Utilisateur introuvable", 404);
        }

        $jwt = new JWT($this->getConfig());

        // ✅ Nouveau token d'accès
        $access_token = $jwt->generer([
            'id' => $user->id_user,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role
        ], $this->durationToken);

        // ✅ Nouveau refresh_token (sécurité renforcée : rotation)
        $new_refresh_token = $jwt->generer(['id_user' => $user->id_user], $this->durationTokenRefresh);

        // ✅ Mettre à jour le token existant
        $rt->id_user = $user->id_user;
        $rt->token = $new_refresh_token;
        $rt->expires_at = date("Y-m-d H:i:s", time() + $this->durationTokenRefresh);
        $rt->update();

        Response::success("Nouveau token généré", [
            'token' => $access_token,
            'token_expires_at' => time() + $this->durationToken,
            'refresh_token' => $new_refresh_token,
            'refresh_token_expires_at' => time() + $this->durationTokenRefresh,
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
