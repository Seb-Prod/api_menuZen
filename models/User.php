<?php

class User
{
    private $connexion;
    public $id;
    public $username;
    public $email;
    public $password;
    public $role;
    public $is_active;
    public $verification_token;

    public function __construct($db)
    {
        $this->connexion = $db;
    }

    public function store()
    {
        $sql = "INSERT INTO users (username, email, password, verification_token, created_at, updated_at)
        VALUES (:username, :email, :password, :verification_token, NOW(), NOW())";

        $query = $this->connexion->prepare($sql);

        $this->username = htmlspecialchars(strip_tags($this->username));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->password = password_hash($this->password, PASSWORD_DEFAULT);
        $this->verification_token = $this->generateToken();

        $query->bindParam(":username", $this->username);
        $query->bindParam(":email", $this->email);
        $query->bindParam(":password", $this->password);
        $query->bindParam(":verification_token", $this->verification_token);

        if ($query->execute()) {
            return true;
        }

        return false;
    }

    public function exists(): bool
    {
        $sql = "SELECT id FROM users WHERE email = :email OR username = :username";
        $query = $this->connexion->prepare($sql);
        $query->bindParam(":email", $this->email);
        $query->bindParam(":username", $this->username);
        $query->execute();

        return $query->rowCount() > 0;
    }

    public function verifyEmailToken(string $token): bool
    {
        $sql = "SELECT id, is_active FROM users WHERE verification_token = :token LIMIT 1";
        $stmt = $this->connexion->prepare($sql);
        $stmt->bindParam(':token', $token);
        $stmt->execute();

        if ($stmt->rowCount() === 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user['is_active'] == 1) {
                return false; // déjà activé
            }

            $update = "UPDATE users SET is_active = 1, verification_token = NULL, updated_at = NOW() WHERE id = :id";
            $stmtUpdate = $this->connexion->prepare($update);
            $stmtUpdate->bindParam(':id', $user['id']);
            return $stmtUpdate->execute();
        }

        return false;
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
