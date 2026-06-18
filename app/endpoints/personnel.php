<?php
/**
 * Endpoint AJAX : Personnel ANAC (pour le choix d'un agent non-operateur)
 * Accas : administrateurs. Route : /api/personnel
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }

Rbac::guardApi('users');

if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Jeton CSRF invalide.']);
    exit;
}

$db     = Database::getInstance();
$action = $_POST['action'] ?? '';

try {
    switch ($action) {

        case 'list':
            // Agents ANAC reels (on exclut le matricule technique '000')
            $st = $db->query(
                "SELECT idpersonnel, numat, nomag, prenag, email_anac
                 FROM personnel_anac
                 WHERE numat <> '000'
                 ORDER BY nomag, prenag"
            );
            echo json_encode(['success' => true, 'data' => $st->fetchAll()]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Action inconnue.']);
    }
} catch (Throwable $e) {
    error_log('personnel endpoint : ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur technique (table personnel_anac introuvable ?).']);
}
