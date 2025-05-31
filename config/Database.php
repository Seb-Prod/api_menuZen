<?php

/**
 * Classe pour établir une connexion à la base de données MySQL en utilisant PDO.
 *
 * Cette classe gère la connexion à une base de données MySQL en utilisant l'extension PDO (PHP Data Objects).
 * Elle lit les informations de connexion à partir d'un fichier de configuration externe ('config.php')
 * pour une meilleure séparation de la configuration et du code.
 */

class Database
{
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $connexion;

    public function __construct()
    {
        // Chemin vers le fichier de configuration
        $configFile = __DIR__ . '/config.php';

        // Vérifier si le fichier de configuration existe
        if (!file_exists($configFile)) {
            throw new Exception("Le fichier de configuration 'config.php' est introuvable.");
        }

        // Charger la configuration sous forme de tableau associatif
        $config = require($configFile);

        // Vérifier si les clés de configuration nécessaires existent
        if (!isset($config['host'], $config['db_name'], $config['username'], $config['password'])) {
            throw new Exception("Le fichier de configuration 'config.php' est incomplet. Veuillez vérifier les clés 'host', 'db_name', 'username' et 'password'.");
        }

        // Initialiser les propriétés à partir de la configuration
        $this->host = $config['host'];
        $this->db_name = $config['db_name'];
        $this->username = $config['username'];
        $this->password = $config['password'];
    }

    public function getConnexion()
    {
        // Initialiser la connexion à null avant de tenter d'en établir une nouvelle
        $this->connexion = null;

        try {
            // Créer une nouvelle instance de PDO pour établir la connexion
            $this->connexion = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name};charset=utf8",
                $this->username,
                $this->password
            );
            // Configuration des erreurs PDO en mode exception pour une meilleure gestion
            $this->connexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            // En cas d'erreur de connexion, afficher le message d'erreur
            //echo "Erreur de connexion à la base de données";
        }
        // Retourner l'objet de connexion (ou null en cas d'échec)
        return $this->connexion;
    }
}
