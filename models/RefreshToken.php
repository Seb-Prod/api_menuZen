<?php

class RefreshToken
{
    private $conn;

    public $id;
    public $id_user;
    public $token;
    public $expires_at;
    public $revoked;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function store(): bool
    {
        $sql = "INSERT INTO refresh_tokens (id_user, token, expires_at)
                VALUES (:id_user, :token, :expires_at)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id_user', $this->id_user);
        $stmt->bindParam(':token', $this->token);
        $stmt->bindParam(':expires_at', $this->expires_at);
        return $stmt->execute();
    }

    public function findByToken($token): ?array
    {
        $sql = "SELECT * FROM refresh_tokens WHERE token = :token LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function revoke($token): bool
    {
        $sql = "UPDATE refresh_tokens SET revoked = 1 WHERE token = :token";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':token', $token);
        return $stmt->execute();
    }

    public function deleteExpired(): void
    {
        $sql = "DELETE FROM refresh_tokens WHERE expires_at < NOW() OR revoked = 1";
        $this->conn->query($sql);
    }

    public function existsForUser(): bool
    {
        $sql = "SELECT * FROM refresh_tokens WHERE id_user = :id_user LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id_user', $this->id_user);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function update(): bool
    {
        $sql = "UPDATE refresh_tokens SET token = :token, expires_at = :expires_at WHERE id_user = :id_user";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':token', $this->token);
        $stmt->bindParam(':expires_at', $this->expires_at);
        $stmt->bindParam(':id_user', $this->id_user);
        return $stmt->execute();
    }
}
