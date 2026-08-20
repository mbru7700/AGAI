<?php
/**
 * Classe Audit - Journalisation centralisée (audit_logs)
 * ------------------------------------------------------------
 * Réutilisable par tous les modules : qui, quoi, quand, IP, agent.
 *
 * @package AGAI
 * @author  ANAC Gabon
 */

class Audit
{
    public static function log(string $action, string $module, string $description = ''): void
    {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare(
                "INSERT INTO audit_logs (iduser, action, module, description, ip_address, user_agent)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $params = [
                $_SESSION['user_id'] ?? null,
                $action,
                $module,
                $description,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
            ];

            try {
                $stmt->execute($params);
            } catch (PDOException $ePdo) {
                // Contrainte de cle etrangere : l'utilisateur en session n'existe plus
                // (compte supprime alors que la session etait encore ouverte).
                // L'evenement doit malgre tout etre journalise : on l'enregistre en anonyme.
                if ($ePdo->getCode() === '23000') {
                    $params[0] = null;
                    $params[3] = trim($description . ' [utilisateur supprime]');
                    $stmt->execute($params);
                } else {
                    throw $ePdo;
                }
            }
        } catch (Throwable $e) {
            error_log('Audit::log : ' . $e->getMessage());
        }
    }
}