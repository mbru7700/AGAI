<?php
/**
 * ============================================================
 * AGAI - ANAC Gabon
 * Endpoint AJAX d'authentification  (app/endpoints/auth.php)
 * ------------------------------------------------------------
 * Atteint via la route propre : <SITE_URL>/auth
 * Le bootstrap (config) est déjà chargé par le front controller.
 * Fallback défensif si appelé dans un autre contexte.
 * ============================================================
 */

if (!defined('SITE_URL')) {
    require_once dirname(__DIR__, 2) . '/config/config.php';
}

// Déconnexion via GET autorisée (lien direct)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (($_GET['action'] ?? '') === 'logout') {
        Auth::logout();
        redirect('/');
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

header('Content-Type: application/json');

$action     = $_POST['action'] ?? '';
$csrf_token = $_POST['csrf_token'] ?? '';

// CSRF obligatoire pour toute action POST
if (!Security::validateCSRF($csrf_token)) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF invalide']);
    exit;
}

switch ($action) {

    case 'login':
        $email    = Security::cleanInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $result   = Auth::login($email, $password);
        if (!empty($result['success'])) {
            $result['redirect'] = SITE_URL . (!empty($result['requires_2fa']) ? '/verification' : '/dashboard');
        }
        echo json_encode($result);
        break;

    case 'verify_2fa':
        $code = Security::cleanInput($_POST['otp_code'] ?? '');
        echo json_encode(Auth::verifyOTP($code));
        break;

    case 'resend_otp':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Session invalide']);
            break;
        }
        $db   = Database::getInstance();
        $stmt = $db->prepare("SELECT email, prenom, nom FROM users WHERE iduser = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if ($user) {
            $otp = Auth::generateOTP($_SESSION['user_id']);
            require_once CLASSES_PATH . '/Mailer.php';
            $mailer   = new Mailer();
            $fullName = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
            if ($mailer->sendOTP($user['email'], $otp, $fullName)) {
                echo json_encode(['success' => true, 'message' => 'Nouveau code envoyé']);
            } else {
                error_log('Renvoi OTP échoué : ' . $mailer->getLastError());
                echo json_encode(['success' => false, 'message' => "Échec de l'envoi de l'email. Réessayez ou contactez l'administrateur."]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Utilisateur introuvable']);
        }
        break;

    case 'refresh_session':
        if (isset($_SESSION['user_id'])) {
            $_SESSION['last_activity'] = time();
            echo json_encode(['success' => true, 'message' => 'Session prolongée']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Session invalide']);
        }
        break;

    case 'logout':
        Auth::logout();
        echo json_encode(['success' => true, 'message' => 'Déconnecté']);
        break;

    // Bypass admin audité : (dés)activer la 2FA d'un utilisateur
    case 'admin_set_2fa':
        $targetId = (int) ($_POST['target_id'] ?? 0);
        $enabled  = (int) ($_POST['enabled'] ?? 0) === 1;
        $reason   = Security::cleanInput($_POST['reason'] ?? '');
        if ($targetId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Utilisateur cible invalide']);
            break;
        }
        echo json_encode(Auth::set2FA($targetId, $enabled, $reason !== '' ? $reason : null));
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Action invalide']);
}
exit;