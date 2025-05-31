<?php

class Recette{
    private $connexion;
    public function __construct($db)
    {
        $this->connexion = $db;
    }

    public function getAll(){
        $sql = "SELECT * FROM recettes";
        $query = $this->connexion->prepare($sql);

        try{
            $query->execute();
            return $query;
        } catch (PDOException $e){
            return false;
        }

    }
}