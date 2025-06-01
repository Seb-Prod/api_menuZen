<?php

class User
{
    private $conn; // Connexion à la base de données

    // Propriétés publiques représentant les colonnes de la table "users"
    public $id_user;
    public $username;
    public $email;
    public $password;
    public $role;
    public $is_active;
    public $locked_until;
    public $failed_attempts;
    public $verification_token;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Enregistre un nouvel utilisateur dans la base de données.
     * - Hash du mot de passe
     * - Génération d’un token de vérification
     */
    public function store(): bool
    {
        $sql = "INSERT INTO users (username, email, password, verification_token, created_at, updated_at)
                VALUES (:username, :email, :password, :verification_token, NOW(), NOW())";

        $query = $this->conn->prepare($sql);

        $this->username = htmlspecialchars(strip_tags($this->username));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->password = password_hash($this->password, PASSWORD_DEFAULT);
        $this->verification_token = $this->generateVerificationToken();

        $query->bindParam(":username", $this->username);
        $query->bindParam(":email", $this->email);
        $query->bindParam(":password", $this->password);
        $query->bindParam(":verification_token", $this->verification_token);

        return $query->execute();
    }

    /**
     * Récupère un utilisateur par email et remplit les propriétés de l'objet.
     */
    public function findByEmail(): bool
    {
        $sql = "SELECT * FROM users WHERE email = :email LIMIT 1";
        $query = $this->conn->prepare($sql);

        $this->email = htmlspecialchars(strip_tags($this->email));
        $query->bindParam(':email', $this->email);
        $query->execute();

        if ($query->rowCount() > 0) {
            $row = $query->fetch(PDO::FETCH_ASSOC);
            $this->id_user         = $row['id'];
            $this->username        = $row['username'];
            $this->password        = $row['password'];
            $this->role            = $row['role'];
            $this->is_active       = $row['is_active'];
            $this->locked_until    = $row['locked_until'];
            $this->failed_attempts = $row['failed_attempts'];
            return true;
        }

        return false;
    }

    /**
     * Vérifie si un utilisateur existe déjà par email ou nom d'utilisateur.
     */
    public function exists(): bool
    {
        $sql = "SELECT id FROM users WHERE email = :email OR username = :username";
        $query = $this->conn->prepare($sql);
        $query->bindParam(":email", $this->email);
        $query->bindParam(":username", $this->username);
        $query->execute();

        return $query->rowCount() > 0;
    }

    /**
     * Vérifie le token de validation d'email et active le compte.
     */
    public function verifyEmailToken(string $token): bool
    {
        $sql = "SELECT id, is_active FROM users WHERE verification_token = :token LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':token', $token);
        $stmt->execute();

        if ($stmt->rowCount() === 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user['is_active'] == 1) {
                return false; // Déjà activé
            }

            $update = "UPDATE users SET is_active = 1, verification_token = NULL, updated_at = NOW() WHERE id = :id";
            $stmtUpdate = $this->conn->prepare($update);
            $stmtUpdate->bindParam(':id', $user['id']);
            return $stmtUpdate->execute();
        }

        return false;
    }

    /**
     * Génère un token sécurisé (pour email ou reset).
     */
    public function generateVerificationToken()
    {
        $this->verification_token = bin2hex(random_bytes(16));
    }

    /**
     * Incrémente le nombre d'échecs de connexion.
     */
    public function incrementFailedAttempts(): void
    {
        $sql = "UPDATE users SET failed_attempts = failed_attempts + 1 WHERE email = :email";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':email', $this->email);
        $stmt->execute();
    }

    /**
     * Réinitialise le compteur d’échecs et déverrouille le compte.
     */
    public function resetFailedAttempts(): void
    {
        $sql = "UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE email = :email";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':email', $this->email);
        $stmt->execute();
    }

    /**
     * Verrouille temporairement le compte (15 minutes).
     */
    public function lockAccount(): void
    {
        $lockedUntil = date("Y-m-d H:i:s", strtotime("+15 minutes"));
        $sql = "UPDATE users SET locked_until = :locked_until WHERE email = :email";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':locked_until', $lockedUntil);
        $stmt->bindParam(':email', $this->email);
        $stmt->execute();
    }

    /**
     * Met à jour la date de dernière connexion.
     */
    public function updateLastLogin(): void
    {
        $now = date("Y-m-d H:i:s");
        $sql = "UPDATE users SET last_login = :now WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':now', $now);
        $stmt->bindParam(':id', $this->id_user);
        $stmt->execute();
    }

    /** 
     * Met à jour le token d'activation du compte 
    */
    public function updateVerificationToken()
    {
        $query = "UPDATE users SET verification_token = :token WHERE email = :email";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':token', $this->verification_token);
        $stmt->bindParam(':email', $this->email);
        return $stmt->execute();
    }
}
