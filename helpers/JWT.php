<?php

/**
 * @file
 * Classe pour gérer les tokens JWT (JSON Web Tokens).
 */

class JWT
{
    
    private $cle_secrete;
    private $duree_validite = 3600;

    public function __construct($config)
    {
        // Récupérer la clé depuis la configuration
        if (isset($config['jwt_secret'])) {
            $this->cle_secrete = $config['jwt_secret'];
        } else {
            // Lancer une exception si la clé n'est pas fournie
            throw new Exception("Erreur: clé JWT non définie dans la configuration");
        }
    }

    public function generer($donnees)
    {
        // En-tête du JWT
        $header = $this->encoderBase64(json_encode([
            'typ' => 'JWT',
            'alg' => 'HS256'
        ]));

        // Charge utile (payload) du JWT
        $payload = $this->encoderBase64(json_encode([
            'iat' => time(), // Timestamp de la création du token
            'exp' => time() + $this->duree_validite, // Timestamp de l'expiration du token
            'data' => $donnees // Les données à inclure dans le token
        ]));

        // Signature du JWT
        $signature = $this->encoderBase64(
            hash_hmac('sha256', "$header.$payload", $this->cle_secrete, true)
        );

        // Assembler le token JWT
        return "$header.$payload.$signature";
    }

    public function verifier($token)
    {
        // Séparer les trois parties du token en utilisant le point comme délimiteur
        $parties = explode('.', $token);
        // Un token JWT valide doit contenir exactement trois parties
        if (count($parties) != 3) {
            return false;
        }

        // Assigner chaque partie à une variable distincte pour une meilleure lisibilité
        list($header, $payload, $signature) = $parties;

        // Calculer la signature attendue en utilisant l'en-tête, la charge utile et la clé secrète
        $signature_calculee = $this->encoderBase64(
            hash_hmac('sha256', "$header.$payload", $this->cle_secrete, true)
        );

        // Comparer la signature calculée avec la signature fournie dans le token
        if ($signature_calculee !== $signature) {
            // Si les signatures ne correspondent pas, le token est invalide (altéré)
            return false;
        }

        // Décoder la charge utile (payload) du token de la base64 au format JSON, puis en tableau associatif
        $payload_decode = json_decode($this->decoderBase64($payload), true);

        // Vérifier si la clé 'exp' (expiration) existe dans la charge utile décodée
        if (!isset($payload_decode['exp'])) {
            return false; // Le token est malformé s'il n'a pas d'expiration
        }

        // Vérifier si la date d'expiration du token est dans le passé
        if ($payload_decode['exp'] < time()) {
            // Si la date d'expiration est antérieure à l'heure actuelle, le token a expiré
            return false;
        }

        // Si toutes les vérifications ont réussi, retourner les données de la charge utile
        return $payload_decode['data'];
    }

    private function encoderBase64($donnees)
    {
        return rtrim(strtr(base64_encode($donnees), '+/', '-_'), '=');
    }

    private function decoderBase64($donnees)
    {
        return base64_decode(strtr($donnees, '-_', '+/'));
    }
}

?>