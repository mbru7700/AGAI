<?php
/**
 * Authentification - Point d'entrée pour les requêtes AJAX
 */

// Charger la configuration
require_once dirname(__DIR__) . '/config/config.php';

// Traitement des requêtes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Vérification CSRF
    if (!Security::validateCSRF($csrf_token)) {
        echo json_encode(['success' => false, 'message' => 'Token CSRF invalide']);
        exit;
    }
    
    switch ($action) {
        case 'login':
            $email = Security::cleanInput($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $result = Auth::login($email, $password);
            echo json_encode($result);
            break;
            
        case 'verify_2fa':
            $code = Security::cleanInput($_POST['otp_code'] ?? '');
            $result = Auth::verifyOTP($code);
            echo json_encode($result);
            break;
            
        case 'resend_otp':
            if (isset($_SESSION['user_id'])) {
                $userId = $_SESSION['user_id'];
                $db = Database::getInstance();
                $stmt = $db->prepare("SELECT email FROM users WHERE iduser = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                
                if ($user) {
                    $otp = Auth::generateOTP($userId);
                    require_once CLASSES_PATH . '/Mailer.php';
                    $mailer = new Mailer();
                    $mailer->sendOTP($user['email'], $otp);
                    echo json_encode(['success' => true, 'message' => 'Nouveau code envoyé']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Session invalide']);
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
            
        default:
            echo json_encode(['success' => false, 'message' => 'Action invalide']);
    }
    exit;
}

// Si le fichier est appelé directement sans POST (déconnexion)
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    Auth::logout();
    header('Location: ' . SITE_URL . '/index');
    exit;
}

// Si on appelle logout sans action
if (basename($_SERVER['PHP_SELF']) === 'logout.php') {
    Auth::logout();
    header('Location: ' . SITE_URL . '/index');
    exit;
}