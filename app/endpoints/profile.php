<?php
/**
 * Endpoint AJAX : Profil de l'utilisateur connecte. Route : /api/profile
 * ------------------------------------------------------------
 * Accessible a TOUT utilisateur connecte (pas seulement admin).
 * Action : change_password (changement de son propre mot de passe).
 *
 * Securite :
 *  - session valide obligatoire (guardAuthApi)
 *  - jeton CSRF obligatoire
 *  - re-authentification : le mot de passe actuel doit etre fourni et correct
 *  - le nouveau mot de passe doit respecter la politique de robustesse (serveur)
 *  - confirmation (saisie deux fois)
 *  - regeneration de l'identifiant de session apres changement
 *  - journalisation de l'action
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }

Rbac::guardAuthApi();   // session valide (tout role connecte)

if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Jeton CSRF invalide. Rechargez la page.']);
    exit;
}

$db     = Database::getInstance();
$action = $_POST['action'] ?? '';
$uid    = (int) ($_SESSION['user_id'] ?? 0);

$ok   = function ($extra = []) { echo json_encode(['success' => true] + $extra); };
$fail = function ($msg) { echo json_encode(['success' => false, 'message' => $msg]); };

try {
    switch ($action) {

        case 'change_password':
            $current = (string) ($_POST['current_password'] ?? '');
            $new     = (string) ($_POST['new_password'] ?? '');
            $confirm = (string) ($_POST['confirm_password'] ?? '');

            if ($uid <= 0) { $fail('Session invalide.'); break; }
            if ($current === '' || $new === '' || $confirm === '') {
                $fail('Veuillez remplir tous les champs.'); break;
            }

            // 1) Recuperer le hash actuel de l'utilisateur connecte
            $st = $db->prepare("SELECT password_hash FROM users WHERE iduser = ? AND is_active = 1 LIMIT 1");
            $st->execute([$uid]);
            $row = $st->fetch();
            if (!$row) { $fail('Compte introuvable.'); break; }

            // 2) Re-authentification : le mot de passe actuel doit etre correct
            if (!Security::verifyPassword($current, $row['password_hash'])) {
                Audit::log('password_change_failed', 'profile', 'Mot de passe actuel incorrect');
                $fail('Le mot de passe actuel est incorrect.'); break;
            }

            // 3) Confirmation
            if ($new !== $confirm) { $fail('Le nouveau mot de passe et sa confirmation ne correspondent pas.'); break; }

            // 4) Le nouveau doit etre different de l'ancien
            if (Security::verifyPassword($new, $row['password_hash'])) {
                $fail('Le nouveau mot de passe doit etre different de l\'ancien.'); break;
            }

            // 5) Politique de robustesse (verification serveur obligatoire)
            $check = Security::validatePasswordStrength($new);
            if (!$check['valid']) {
                $fail('Mot de passe trop faible. Il doit contenir : ' . implode(', ', $check['errors']) . '.'); break;
            }

            // 6) Mise a jour + invalidation d'un eventuel OTP en cours
            $db->prepare(
                "UPDATE users
                 SET password_hash = ?, otp_hash = NULL, otp_expires_at = NULL, otp_attempts = 0, updated_at = NOW()
                 WHERE iduser = ?"
            )->execute([Security::hashPassword($new), $uid]);

            Audit::log('password_change', 'profile', 'Changement de mot de passe par l\'utilisateur');

            // 7) Anti-fixation : on regenere l'identifiant de session
            session_regenerate_id(true);

            $ok(['message' => 'Votre mot de passe a ete change avec succes.']);
            break;

        default:
            $fail('Action inconnue.');
    }
} catch (Throwable $e) {
    error_log('profile endpoint : ' . $e->getMessage());
    $fail('Erreur technique.');
}