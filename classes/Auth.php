<?php
/**
 * Classe Auth - Gestion de l'authentification
 * 
 * @package AGAI
 * @author ANAC Gabon
 */

class Auth
{
    private static $db = null;
    
    private static function getDB()
    {
        if (self::$db === null) {
            self::$db = Database::getInstance();
        }
        return self::$db;
    }
    
    /**
     * Vérifier si l'utilisateur est connecté (avec session)
     */
    public static function isLoggedIn()
    {
        return isset($_SESSION['user_id']) && !isset($_SESSION['2fa_required']);
    }
    
    /**
     * Vérification complète de la session (avec timeout)
     */
    public static function checkLogin()
    {
        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        // Vérifier si la 2FA est requise
        if (isset($_SESSION['2fa_required'])) {
            return false;
        }
        
        // Vérifier le timeout de session
        $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 3600;
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
            self::logout();
            return false;
        }
        
        // Mettre à jour le timestamp d'activité
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    /**
     * Connexion utilisateur
     */
    public static function login($email, $password)
    {
        try {
            $db = self::getDB();
            $email = Security::cleanInput($email);
            
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                self::logAttempt($email, false, 'Utilisateur non trouvé');
                return ['success' => false, 'message' => 'Email ou mot de passe incorrect'];
            }
            
            if (self::isLocked($user)) {
                return ['success' => false, 'message' => 'Compte verrouillé. Réessayez plus tard.'];
            }
            
            if (!Security::verifyPassword($password, $user['password_hash'])) {
                self::incrementAttempts($user['iduser']);
                return ['success' => false, 'message' => 'Email ou mot de passe incorrect'];
            }
            
            self::resetAttempts($user['iduser']);
            
            $_SESSION['user_id'] = $user['iduser'];
            $_SESSION['user'] = [
                'id'         => $user['iduser'],
                'email'      => $user['email'],
                'nom'        => $user['nom'],
                'prenom'     => $user['prenom'],
                'role'       => $user['role'],
                'matricule'  => $user['matricule'],
                'last_login' => $user['last_login'] ?? null,
            ];
            $_SESSION['last_activity'] = time();
            
            if ($user['is_2fa_enabled']) {
                $otp = self::generateOTP($user['iduser']);
                $_SESSION['2fa_required'] = true;
                
                require_once CLASSES_PATH . '/Mailer.php';
                $mailer = new Mailer();
                $mailer->sendOTP($user['email'], $otp);
                
                return ['success' => true, 'requires_2fa' => true, 'message' => 'Code de sécurité envoyé par email'];
            }
            
            session_regenerate_id(true);
            self::logLogin($user['iduser']);
            
            return ['success' => true, 'requires_2fa' => false, 'message' => 'Connexion réussie'];
            
        } catch (Exception $e) {
            error_log("Erreur login: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur technique'];
        }
    }
    
    /**
     * Générer un code OTP
     */
    public static function generateOTP($userId)
    {
        $db = self::getDB();
        $otp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $stmt = $db->prepare("UPDATE users SET 2fa_secret = ?, updated_at = NOW() WHERE iduser = ?");
        $stmt->execute([$otp, $userId]);
        return $otp;
    }
    
    /**
     * Vérifier le code OTP
     */
    public static function verifyOTP($code)
    {
        try {
            if (!isset($_SESSION['user_id'])) {
                return ['success' => false, 'message' => 'Session invalide'];
            }
            
            $db = self::getDB();
            $userId = $_SESSION['user_id'];
            $code = Security::cleanInput($code);
            
            $stmt = $db->prepare("
                SELECT * FROM users 
                WHERE iduser = ? 
                AND 2fa_secret = ? 
                AND updated_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
                LIMIT 1
            ");
            $stmt->execute([$userId, $code]);
            $valid = $stmt->fetch();
            
            if (!$valid) {
                return ['success' => false, 'message' => 'Code invalide ou expiré'];
            }
            
            $stmt = $db->prepare("UPDATE users SET 2fa_secret = NULL WHERE iduser = ?");
            $stmt->execute([$userId]);

            unset($_SESSION['2fa_required']);
            session_regenerate_id(true);
            $_SESSION['last_activity'] = time();

            // Mettre a jour last_login dans la session
            $stLl = $db->prepare("SELECT last_login FROM users WHERE iduser = ?");
            $stLl->execute([$userId]);
            $rowLl = $stLl->fetch();
            if (isset($_SESSION['user']) && $rowLl) {
                $_SESSION['user']['last_login'] = $rowLl['last_login'];
            }

            self::logLogin($userId);
            
            return ['success' => true, 'message' => 'Vérification réussie'];
            
        } catch (Exception $e) {
            error_log("Erreur 2FA: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur technique'];
        }
    }
    
    /**
     * Journaliser une tentative
     */
    private static function logAttempt($email, $success, $message, $userId = null)
    {
        $db = self::getDB();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt = $db->prepare("
            INSERT INTO audit_logs (iduser, action, module, description, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, 'login_attempt', 'auth', $message . ' - Email: ' . $email, $ip, $userAgent]);
    }
    
    /**
     * Journaliser une connexion
     */
    private static function logLogin($userId)
    {
        $db = self::getDB();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt = $db->prepare("
            INSERT INTO audit_logs (iduser, action, module, description, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, 'login', 'auth', 'Connexion réussie', $ip, $userAgent]);
        
        $stmt = $db->prepare("UPDATE users SET last_login = NOW(), last_ip = ? WHERE iduser = ?");
        $stmt->execute([$ip, $userId]);
    }
    
    /**
     * Vérifier si le compte est verrouillé
     */
    private static function isLocked($user)
    {
        if ($user['login_attempts'] >= 5) {
            $lockedUntil = strtotime($user['locked_until'] ?? '1970-01-01');
            if (time() < $lockedUntil) {
                return true;
            }
            self::resetAttempts($user['iduser']);
        }
        return false;
    }
    
    /**
     * Incrémenter les tentatives
     */
    private static function incrementAttempts($userId)
    {
        $db = self::getDB();
        $stmt = $db->prepare("
            UPDATE users 
            SET login_attempts = login_attempts + 1,
                locked_until = IF(login_attempts >= 4, DATE_ADD(NOW(), INTERVAL 15 MINUTE), NULL)
            WHERE iduser = ?
        ");
        $stmt->execute([$userId]);
    }
    
    /**
     * Réinitialiser les tentatives
     */
    private static function resetAttempts($userId)
    {
        $db = self::getDB();
        $stmt = $db->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE iduser = ?");
        $stmt->execute([$userId]);
    }
    
    /**
     * Déconnexion
     */
    public static function logout()
    {
        $db = self::getDB();
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $stmt = $db->prepare("
                INSERT INTO audit_logs (iduser, action, module, description, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, 'logout', 'auth', 'Déconnexion', $ip, $_SERVER['HTTP_USER_AGENT'] ?? '']);
        }
        $_SESSION = [];
        session_unset();
        session_destroy();
    }


    /**
     * Activer ou desactiver le 2FA pour un utilisateur
     * Accessible par admin et chef_inspecteur
     */
    public static function set2FA(int $iduser, bool $enabled, ?string $reason = null): array
    {
        try {
            $callerRole = $_SESSION['user']['role'] ?? '';
            $callerId   = (int) ($_SESSION['user_id'] ?? 0);

            if (!in_array($callerRole, ['admin', 'chef_inspecteur'], true)) {
                return ['success' => false, 'message' => 'Action reservee aux administrateurs et chefs inspecteurs.'];
            }

            $db = self::getDB();

            $st = $db->prepare("SELECT iduser, role, nom, prenom, is_2fa_enabled FROM users WHERE iduser = ?");
            $st->execute([$iduser]);
            $target = $st->fetch();
            if (!$target) {
                return ['success' => false, 'message' => 'Utilisateur introuvable.'];
            }

            // Chef_inspecteur ne peut pas modifier le 2FA d'un admin
            if ($callerRole === 'chef_inspecteur' && $target['role'] === 'admin') {
                return ['success' => false, 'message' => "Un chef inspecteur ne peut pas modifier le 2FA d'un administrateur."];
            }

            if (!$enabled) {
                // Desactivation : effacer aussi le secret OTP
                $db->prepare("UPDATE users SET is_2fa_enabled = 0, 2fa_secret = NULL, otp_hash = NULL, otp_expires_at = NULL, updated_at = NOW() WHERE iduser = ?")
                   ->execute([$iduser]);
            } else {
                $db->prepare("UPDATE users SET is_2fa_enabled = 1, updated_at = NOW() WHERE iduser = ?")
                   ->execute([$iduser]);
            }

            $action    = $enabled ? 'activation' : 'desactivation';
            $targetNom = trim(($target['prenom'] ?? '') . ' ' . ($target['nom'] ?? ''));
            $desc      = "2FA $action pour l'utilisateur #$iduser ($targetNom)" . ($reason ? " - Raison : $reason" : '');
            Audit::log('toggle_2fa', 'users', $desc);

            return ['success' => true, 'message' => '2FA ' . ($enabled ? 'active' : 'desactive') . ' pour ' . htmlspecialchars($targetNom) . '.'];

        } catch (Throwable $e) {
            error_log('Auth::set2FA error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
        }
    }

}