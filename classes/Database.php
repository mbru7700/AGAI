<?php
/**
 * Classe Database - Gestion de la base de données avec PDO
 *
 * @package AGAI
 * @author ANAC Gabon
 */

class Database
{
    private static $instance = null;
    private $pdo = null;

    private function __construct()
    {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

            $this->pdo = new PDO($dsn, DB_USER, DB_PASSWORD, DB_OPTIONS);
        } catch (PDOException $e) {
            error_log("Erreur de connexion à la base de données: " . $e->getMessage());
            throw new Exception("Erreur de connexion à la base de données.");
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPDO()
    {
        return $this->pdo;
    }

    public function prepare($sql)
    {
        return $this->pdo->prepare($sql);
    }

    /**
     * Exécute directement une requête SANS paramètres (SELECT statiques, COUNT...).
     * Retourne un PDOStatement (utilisons ->fetch(), ->fetchAll(), ->fetchColumn()).
     * Pour toute requête avec des données utilisateur, utilisons prepare()/execute().
     */
    public function query($sql)
    {
        return $this->pdo->query($sql);
    }

    public function execute($sql, $params = [])
    {
        $stmt = $this->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }

    public function beginTransaction()
    {
        return $this->pdo->beginTransaction();
    }

    public function commit()
    {
        return $this->pdo->commit();
    }

    public function rollBack()
    {
        return $this->pdo->rollBack();
    }
}