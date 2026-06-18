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
            $stmt->execute([
                $_SESSION['user_id'] ?? null,
                $action,
                $module,
                $description,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);
        } catch (Throwable $e) {
            error_log('Audit::log : ' . $e->getMessage());
        }
    }
}
