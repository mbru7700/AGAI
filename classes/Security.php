<?php
/**
 * Classe Security - Gestion de la securite
 * Methodes statiques pour compatibilite
 *
 * @package AGAI
 * @author ANAC Gabon
 */

class Security
{
    public static function generateCSRF()
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateCSRF($token)
    {
        if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
            return false;
        }
        return true;
    }

    public static function cleanInput($data)
    {
        if (is_array($data)) {
            return array_map([self::class, 'cleanInput'], $data);
        }
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }

    public static function escape($data)
    {
        if (is_array($data)) {
            return array_map([self::class, 'escape'], $data);
        }
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }

    public static function hashPassword($password)
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }

    public static function generateStrongPassword($length = 12)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }

    /**
     * Politique de mot de passe de l'application.
     * Sert a la fois a l'affichage (cote page) et a la validation (cote serveur).
     */
    public static function passwordPolicy()
    {
        return [
            'min'       => 10,   // longueur minimale
            'max'       => 72,   // limite technique de bcrypt
            'uppercase' => true, // au moins une majuscule
            'lowercase' => true, // au moins une minuscule
            'digit'     => true, // au moins un chiffre
            'special'   => true, // au moins un caractere special
        ];
    }

    /**
     * Valide la robustesse d'un mot de passe choisi manuellement.
     * Retourne un tableau : ['valid' => bool, 'errors' => [messages a corriger]].
     * Cette verification serveur est OBLIGATOIRE meme si le navigateur a deja verifie.
     */
    public static function validatePasswordStrength($password)
    {
        $policy = self::passwordPolicy();
        $p      = (string) $password;
        $errors = [];

        if (mb_strlen($p) < $policy['min']) {
            $errors[] = 'au moins ' . $policy['min'] . ' caracteres';
        }
        if (mb_strlen($p) > $policy['max']) {
            $errors[] = 'pas plus de ' . $policy['max'] . ' caracteres';
        }
        if ($policy['uppercase'] && !preg_match('/[A-Z]/', $p)) {
            $errors[] = 'une lettre majuscule';
        }
        if ($policy['lowercase'] && !preg_match('/[a-z]/', $p)) {
            $errors[] = 'une lettre minuscule';
        }
        if ($policy['digit'] && !preg_match('/[0-9]/', $p)) {
            $errors[] = 'un chiffre';
        }
        if ($policy['special'] && !preg_match('/[^A-Za-z0-9]/', $p)) {
            $errors[] = 'un caractere special';
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    public static function validateEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function sanitizeFileName($filename)
    {
        $filename = basename($filename);
        $filename = preg_replace('/[^a-zA-Z0-9_.-]/', '', $filename);
        return $filename;
    }
}