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
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $email = Security::cleanInput($email);

            // Protection brute-force AU NIVEAU IP : verifiee AVANT toute requete
            // sur les comptes. Complementaire au verrouillage par compte (isLocked) :
            // protege aussi contre l'enumeration d'utilisateurs et les attaques
            // distribuees sur plusieurs comptes depuis une meme source.
            $throttle = self::checkIpThrottle($ip);
            if ($throttle['blocked']) {
                self::recordAttempt($email, $ip, false);
                Audit::log('login_blocked_ip', 'auth', "IP temporairement bloquee ($ip) - seuil de tentatives depasse");
                return [
                    'success' => false,
                    'message' => 'Trop de tentatives depuis cette adresse. Reessayez dans ' . $throttle['retry_minutes'] . ' minute(s).',
                ];
            }

            $db = self::getDB();

            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                self::logAttempt($email, false, 'Utilisateur non trouvé');
                self::recordAttempt($email, $ip, false);
                return ['success' => false, 'message' => 'Email ou mot de passe incorrect'];
            }

            // Compte desactive MANUELLEMENT par un admin/chef inspecteur (conge,
            // depart, suspension...). Distinct du verrouillage automatique par
            // echecs (isLocked, juste apres). On ne revele le motif qu'APRES
            // verification du mot de passe : un tiers qui ne connait pas les
            // identifiants ne doit pas apprendre que ce compte existe/est desactive.
            if ((int) $user['is_active'] !== 1) {
                self::recordAttempt($email, $ip, false);
                if (Security::verifyPassword($password, $user['password_hash'])) {
                    self::logAttempt($email, false, 'Connexion refusee - compte desactive', $user['iduser']);
                    $motif = trim((string) ($user['motif_desactivation'] ?? ''));
                    return [
                        'success' => false,
                        'message' => 'Votre compte a ete desactive' . ($motif !== '' ? " : $motif" : '.')
                            . ' Contactez un administrateur ou le chef inspecteur pour plus d\'informations.',
                    ];
                }
                self::logAttempt($email, false, 'Utilisateur non trouvé');
                return ['success' => false, 'message' => 'Email ou mot de passe incorrect'];
            }
            
            if (self::isLocked($user)) {
                self::logAttempt($email, false, 'Tentative sur un compte verrouille', $user['iduser']);
                self::recordAttempt($email, $ip, false);
                return [
                    'success' => false,
                    'message' => 'Compte verrouille suite a plusieurs echecs de connexion. Seul un administrateur ou un chef inspecteur peut le reactiver.',
                ];
            }
            
            if (!Security::verifyPassword($password, $user['password_hash'])) {
                $justLocked = self::incrementAttempts($user['iduser']);
                self::recordAttempt($email, $ip, false);

                if ($justLocked) {
                    self::logAttempt($email, false, 'Compte verrouille definitivement apres trop d\'echecs', $user['iduser']);
                    Audit::log(
                        'account_locked',
                        'auth',
                        "Compte verrouille de facon permanente (email : $email) apres " . MAX_LOGIN_ATTEMPTS . " echecs consecutifs - reactivation manuelle requise (admin/chef inspecteur)."
                    );
                    return [
                        'success' => false,
                        'message' => 'Compte verrouille suite a plusieurs echecs de connexion. Seul un administrateur ou un chef inspecteur peut le reactiver.',
                    ];
                }

                self::logAttempt($email, false, 'Mot de passe incorrect', $user['iduser']);
                return ['success' => false, 'message' => 'Email ou mot de passe incorrect'];
            }
            
            self::resetAttempts($user['iduser']);
            self::recordAttempt($email, $ip, true);
            
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
     * Enregistrer chaque tentative (succes ou echec) dans la table dediee
     * login_attempts. Independante de audit_logs : sert exclusivement a la
     * detection brute-force par adresse IP (fenetre glissante).
     */
    private static function recordAttempt(?string $email, string $ip, bool $success): void
    {
        try {
            $db = self::getDB();
            $stmt = $db->prepare(
                "INSERT INTO login_attempts (email, ip_address, success, attempted_at) VALUES (?, ?, ?, NOW())"
            );
            $stmt->execute([($email !== null && $email !== '') ? $email : null, $ip, $success ? 1 : 0]);
        } catch (Throwable $e) {
            error_log('Auth::recordAttempt : ' . $e->getMessage());
        }
    }

    /**
     * Verifie si une adresse IP doit etre bloquee : trop d'echecs enregistres
     * dans la fenetre glissante LOCKOUT_TIME (secondes). Seuil : MAX_LOGIN_ATTEMPTS_IP.
     * En cas d'erreur technique on ne bloque PAS (evite un deni de service auto-inflige).
     */
    private static function checkIpThrottle(string $ip): array
    {
        try {
            $db = self::getDB();
            $maxAttempts = defined('MAX_LOGIN_ATTEMPTS_IP') ? MAX_LOGIN_ATTEMPTS_IP : 20;
            $window      = defined('LOCKOUT_TIME') ? LOCKOUT_TIME : 900;

            $stmt = $db->prepare(
                "SELECT COUNT(*) AS nb, MAX(attempted_at) AS dernier
                 FROM login_attempts
                 WHERE ip_address = ? AND success = 0
                   AND attempted_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)"
            );
            $stmt->execute([$ip, $window]);
            $row = $stmt->fetch();

            $nb = (int) ($row['nb'] ?? 0);
            if ($nb >= $maxAttempts) {
                $dernier   = $row['dernier'] ? strtotime($row['dernier']) : time();
                $retryAt   = $dernier + $window;
                $remaining = max(1, (int) ceil(($retryAt - time()) / 60));
                return ['blocked' => true, 'retry_minutes' => $remaining];
            }
            return ['blocked' => false, 'retry_minutes' => 0];
        } catch (Throwable $e) {
            error_log('Auth::checkIpThrottle : ' . $e->getMessage());
            return ['blocked' => false, 'retry_minutes' => 0];
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
     * Vérifier si le compte est verrouillé.
     * Depuis la mise en place du verrouillage permanent, locked_until ne
     * contient plus que NULL ou la sentinelle '9999-12-31 23:59:59' (voir
     * incrementAttempts). La branche "expire" ci-dessous reste en place
     * uniquement pour nettoyer d'anciens verrouillages temporaires (15 min)
     * qui pourraient encore exister en base avant cette mise a jour.
     */
    private static function isLocked($user): bool
    {
        if (empty($user['locked_until'])) {
            return false;
        }
        $lockedUntil = strtotime($user['locked_until']);
        if ($lockedUntil !== false && time() < $lockedUntil) {
            return true;
        }
        if ($lockedUntil !== false) {
            // Verrouillage temporaire (legacy) expire : on nettoie pour permettre une nouvelle tentative
            self::resetAttempts($user['iduser']);
        }
        return false;
    }
    
    /**
     * Incrémenter les tentatives échouées d'un compte.
     * Au seuil MAX_LOGIN_ATTEMPTS, le compte est verrouillé de façon
     * PERMANENTE (comme une carte bancaire avalée par un distributeur) :
     * seul un administrateur ou un chef inspecteur peut le débloquer
     * manuellement (page Cybersécurité > Tentatives de connexion > Débloquer).
     * Il n'y a plus de déverrouillage automatique après un délai.
     *
     * @return bool true si cet appel vient de déclencher le verrouillage
     */
    private static function incrementAttempts($userId): bool
    {
        $db  = self::getDB();
        $max = defined('MAX_LOGIN_ATTEMPTS') ? MAX_LOGIN_ATTEMPTS : 5;

        $db->prepare("UPDATE users SET login_attempts = login_attempts + 1 WHERE iduser = ?")
           ->execute([$userId]);

        $stmt = $db->prepare("SELECT login_attempts FROM users WHERE iduser = ?");
        $stmt->execute([$userId]);
        $attempts = (int) ($stmt->fetch()['login_attempts'] ?? 0);

        if ($attempts >= $max) {
            // Sentinelle "permanent" : date maximale acceptee par le type DATETIME MySQL.
            // Seule l'action 'debloquer' (app/endpoints/login-attempts.php) peut l'effacer.
            $db->prepare("UPDATE users SET locked_until = '9999-12-31 23:59:59' WHERE iduser = ?")
               ->execute([$userId]);
            return true;
        }
        return false;
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