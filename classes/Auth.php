<?php
/**
 * Classe Auth - Gestion de l'authentification
 * ------------------------------------------------------------
 * Protection brute-force par FENÊTRE GLISSANTE (table login_attempts) :
 *   - limite par COMPTE (email)
 *   - limite par ADRESSE IP (tous comptes confondus)
 * Anti-énumération : réponse générique + vérification factice du hash.
 *
 * @package AGAI
 * @author  ANAC Gabon
 */

class Auth
{
    /** Hash factice pour égaliser le temps de réponse quand l'email n'existe pas */
    private const DUMMY_HASH = '$2y$12$dMz4gDOdCTcezdU0woNjv.KnIKbuX7fhFOWrXbRIH02lZ9jxya7Ei';

    private static $db = null;

    private static function getDB()
    {
        if (self::$db === null) {
            self::$db = Database::getInstance();
        }
        return self::$db;
    }

    /* ============================================================
     * SESSION
     * ============================================================ */

    public static function isLoggedIn()
    {
        return isset($_SESSION['user_id']) && !isset($_SESSION['2fa_required']);
    }

    public static function checkLogin()
    {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        if (isset($_SESSION['2fa_required'])) {
            return false;
        }

        $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 3600;
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
            self::logout();
            return false;
        }

        $_SESSION['last_activity'] = time();
        return true;
    }

    /* ============================================================
     * PARAMÈTRES DE THROTTLING
     * ============================================================ */

    private static function windowSeconds(): int
    {
        return defined('LOCKOUT_TIME') ? (int) LOCKOUT_TIME : 900; // 15 min
    }

    private static function accountMax(): int
    {
        return defined('MAX_LOGIN_ATTEMPTS') ? (int) MAX_LOGIN_ATTEMPTS : 5;
    }

    private static function ipMax(): int
    {
        // Plus large que par compte : un poste partagé peut servir plusieurs agents
        return (int) (function_exists('env') ? env('MAX_LOGIN_ATTEMPTS_IP', 15) : 15);
    }

    private static function windowMinutes(): int
    {
        return max(1, (int) round(self::windowSeconds() / 60));
    }

    private static function clientIp(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /* ============================================================
     * CONNEXION
     * ============================================================ */

    public static function login($email, $password)
    {
        try {
            $db    = self::getDB();
            $ip    = self::clientIp();
            $email = Security::cleanInput($email);

            // 1) Limite par IP (toutes tentatives échouées récentes)
            if (self::countRecentFailures(null, $ip) >= self::ipMax()) {
                self::recordAttempt($email, $ip, false);
                self::logAttempt($email, false, 'Blocage IP (brute-force)');
                return ['success' => false, 'message' => 'Trop de tentatives depuis cette adresse. Réessayez dans ' . self::windowMinutes() . ' minutes.'];
            }

            // 2) Limite par compte (email)
            if ($email !== '' && self::countRecentFailures($email, null) >= self::accountMax()) {
                self::recordAttempt($email, $ip, false);
                self::logAttempt($email, false, 'Blocage compte (brute-force)');
                return ['success' => false, 'message' => 'Compte temporairement verrouillé suite à trop de tentatives. Réessayez dans ' . self::windowMinutes() . ' minutes.'];
            }

            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Anti-énumération : on vérifie toujours un hash (réel ou factice)
            $hash       = $user['password_hash'] ?? self::DUMMY_HASH;
            $passwordOk = Security::verifyPassword($password, $hash);

            if (!$user || !$passwordOk) {
                self::recordAttempt($email, $ip, false);
                self::logAttempt($email, false, $user ? 'Mot de passe incorrect' : 'Utilisateur inconnu', $user['iduser'] ?? null);
                // Message identique dans les deux cas
                return ['success' => false, 'message' => 'Email ou mot de passe incorrect'];
            }

            // Succès du mot de passe : on consigne et on purge les échecs récents
            self::recordAttempt($email, $ip, true);
            self::clearFailures($email, $ip);

            $_SESSION['user_id'] = $user['iduser'];
            $_SESSION['user'] = [
                'id'        => $user['iduser'],
                'email'     => $user['email'],
                'nom'       => $user['nom'],
                'prenom'    => $user['prenom'],
                'role'      => $user['role'],
                'matricule' => $user['matricule'],
            ];
            $_SESSION['last_activity'] = time();

            if ($user['is_2fa_enabled']) {
                $otp = self::generateOTP($user['iduser']);

                require_once CLASSES_PATH . '/Mailer.php';
                $mailer   = new Mailer();
                $fullName = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
                $sent     = $mailer->sendOTP($user['email'], $otp, $fullName);

                if (!$sent) {
                    // L'email n'est pas parti : on annule proprement, pas de
                    // session à moitié ouverte, et on informe clairement.
                    self::clearOTP($user['iduser']);
                    unset($_SESSION['user_id'], $_SESSION['user'], $_SESSION['2fa_required']);
                    error_log("OTP non envoyé à {$user['email']} : " . $mailer->getLastError());
                    return [
                        'success' => false,
                        'message' => "Impossible d'envoyer le code de sécurité (problème d'envoi d'email). Réessayez dans un instant ; si cela persiste, contactez l'administrateur."
                    ];
                }

                $_SESSION['2fa_required'] = true;
                return ['success' => true, 'requires_2fa' => true, 'message' => 'Code de sécurité envoyé par email'];
            }

            session_regenerate_id(true);
            self::logLogin($user['iduser']);

            return ['success' => true, 'requires_2fa' => false, 'message' => 'Connexion réussie'];

        } catch (Throwable $e) {
            error_log("Erreur login: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur technique'];
        }
    }

    /* ============================================================
     * THROTTLING - FENÊTRE GLISSANTE (table login_attempts)
     * ============================================================ */

    private static function recordAttempt(string $email, string $ip, bool $success): void
    {
        try {
            $db   = self::getDB();
            $stmt = $db->prepare("INSERT INTO login_attempts (email, ip_address, success) VALUES (?, ?, ?)");
            $stmt->execute([$email !== '' ? $email : null, $ip, $success ? 1 : 0]);

            // Nettoyage léger et occasionnel des vieilles lignes (> 24 h)
            if (random_int(1, 50) === 1) {
                $db->prepare("DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)")->execute();
            }
        } catch (Throwable $e) {
            error_log("recordAttempt: " . $e->getMessage());
        }
    }

    /**
     * Compte les échecs récents dans la fenêtre.
     * Passer $email (et $ip = null) pour la limite par compte,
     * ou $ip (et $email = null) pour la limite par IP.
     */
    private static function countRecentFailures(?string $email, ?string $ip): int
    {
        try {
            $db = self::getDB();

            // Fenêtre calculée CÔTÉ MySQL (NOW()) pour éviter tout décalage
            // de fuseau horaire entre PHP et la base. $window est un entier
            // sûr, inséré directement dans la requête (aucune injection possible).
            $window = (int) self::windowSeconds();

            if ($email !== null) {
                $stmt = $db->prepare(
                    "SELECT COUNT(*) FROM login_attempts
                     WHERE email = ? AND success = 0
                       AND attempted_at > (NOW() - INTERVAL $window SECOND)"
                );
                $stmt->execute([$email]);
            } else {
                $stmt = $db->prepare(
                    "SELECT COUNT(*) FROM login_attempts
                     WHERE ip_address = ? AND success = 0
                       AND attempted_at > (NOW() - INTERVAL $window SECOND)"
                );
                $stmt->execute([$ip]);
            }
            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log("countRecentFailures: " . $e->getMessage());
            return 0; // en cas d'erreur, ne pas bloquer un utilisateur légitime
        }
    }

    private static function clearFailures(string $email, string $ip): void
    {
        try {
            $db   = self::getDB();
            $stmt = $db->prepare("DELETE FROM login_attempts WHERE success = 0 AND (email = ? OR ip_address = ?)");
            $stmt->execute([$email, $ip]);
        } catch (Throwable $e) {
            error_log("clearFailures: " . $e->getMessage());
        }
    }

    /* ============================================================
     * 2FA (OTP)  — inchangé
     * ============================================================ */

    private static function otpLength(): int
    {
        $n = (int) (function_exists('env') ? env('TWO_FA_CODE_LENGTH', 6) : 6);
        return max(4, min(8, $n));
    }

    private static function otpTtlSeconds(): int
    {
        return (int) (function_exists('env') ? env('TWO_FA_EXPIRY', 600) : 600);
    }

    private static function otpMaxAttempts(): int
    {
        return (int) (function_exists('env') ? env('MAX_OTP_ATTEMPTS', 5) : 5);
    }

    /**
     * Génère un code OTP, stocke son HACHÉ (jamais le clair) avec une
     * expiration dédiée, et renvoie le code en clair pour l'envoi par email.
     */
    public static function generateOTP($userId)
    {
        $db     = self::getDB();
        $length = self::otpLength();
        $max    = (10 ** $length) - 1;
        $otp    = str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);

        $hash = password_hash($otp, PASSWORD_BCRYPT, ['cost' => 12]);
        $ttl  = self::otpTtlSeconds(); // entier sûr inséré en SQL

        $stmt = $db->prepare(
            "UPDATE users
             SET otp_hash = ?, otp_expires_at = (NOW() + INTERVAL $ttl SECOND),
                 otp_attempts = 0, `2fa_secret` = NULL, updated_at = NOW()
             WHERE iduser = ?"
        );
        $stmt->execute([$hash, $userId]);

        return $otp; // clair : uniquement pour l'envoi email, jamais stocké
    }

    /**
     * Vérifie l'OTP : haché, non expiré, dans la limite d'essais.
     */
    public static function verifyOTP($code)
    {
        try {
            if (!isset($_SESSION['user_id'])) {
                return ['success' => false, 'message' => 'Session invalide'];
            }

            $db     = self::getDB();
            $userId = $_SESSION['user_id'];
            // On ne garde que les chiffres saisis
            $code   = preg_replace('/\D/', '', (string) $code);

            // otp_hash, nombre d'essais et flag d'expiration (NOW() côté SQL)
            $stmt = $db->prepare(
                "SELECT otp_hash, otp_attempts,
                        (otp_expires_at IS NULL OR otp_expires_at <= NOW()) AS expired
                 FROM users WHERE iduser = ? LIMIT 1"
            );
            $stmt->execute([$userId]);
            $row = $stmt->fetch();

            if (!$row || empty($row['otp_hash'])) {
                return ['success' => false, 'message' => 'Aucun code en attente. Demandez un nouveau code.'];
            }

            if ((int) $row['expired'] === 1) {
                self::clearOTP($userId);
                return ['success' => false, 'message' => 'Code OTP expiré. Demandez un nouveau code.'];
            }

            $maxAttempts = self::otpMaxAttempts();
            if ((int) $row['otp_attempts'] >= $maxAttempts) {
                self::clearOTP($userId);
                return ['success' => false, 'message' => 'Trop d\'essais. Un nouveau code OTP est requis.'];
            }

            if (!password_verify($code, $row['otp_hash'])) {
                $db->prepare("UPDATE users SET otp_attempts = otp_attempts + 1 WHERE iduser = ?")
                   ->execute([$userId]);
                $remaining = max(0, $maxAttempts - ((int) $row['otp_attempts'] + 1));
                $msg = $remaining > 0
                    ? "Code invalide. Il vous reste $remaining essai(s)."
                    : 'Code invalide. Demandez un nouveau code.';
                if ($remaining === 0) {
                    self::clearOTP($userId);
                }
                return ['success' => false, 'message' => $msg];
            }

            // Succès : on efface l'OTP, on régénère la session
            self::clearOTP($userId);
            unset($_SESSION['2fa_required']);
            session_regenerate_id(true);
            $_SESSION['last_activity'] = time();
            self::logLogin($userId);

            return ['success' => true, 'message' => 'Vérification réussie'];

        } catch (Throwable $e) {
            error_log("Erreur 2FA: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur technique'];
        }
    }

    private static function clearOTP($userId): void
    {
        try {
            self::getDB()
                ->prepare("UPDATE users SET otp_hash = NULL, otp_expires_at = NULL, otp_attempts = 0 WHERE iduser = ?")
                ->execute([$userId]);
        } catch (Throwable $e) {
            error_log("clearOTP: " . $e->getMessage());
        }
    }

    /**
     * BYPASS ADMIN AUDITÉ
     * Active / désactive temporairement la 2FA d'un utilisateur (ex. email HS).
     * Réservé aux administrateurs connectés ; chaque action est journalisée.
     */
    public static function set2FA(int $targetUserId, bool $enabled, ?string $reason = null): array
    {
        try {
            $admin = $_SESSION['user'] ?? null;
            if (!self::checkLogin() || !$admin || ($admin['role'] ?? '') !== 'admin') {
                return ['success' => false, 'message' => 'Action réservée aux administrateurs.'];
            }

            $db = self::getDB();
            $db->prepare("UPDATE users SET is_2fa_enabled = ? WHERE iduser = ?")
               ->execute([$enabled ? 1 : 0, $targetUserId]);

            // Si on désactive, on purge tout OTP en attente pour ce compte
            if (!$enabled) {
                self::clearOTP($targetUserId);
            }

            $action = $enabled ? '2fa_enabled' : '2fa_disabled';
            $desc   = ($enabled ? 'Réactivation' : 'Désactivation temporaire')
                    . " de la 2FA pour l'utilisateur #$targetUserId"
                    . ($reason ? ' - Motif: ' . $reason : '');

            $db->prepare(
                "INSERT INTO audit_logs (iduser, action, module, description, ip_address, user_agent)
                 VALUES (?, ?, ?, ?, ?, ?)"
            )->execute([
                $admin['id'], $action, 'security', $desc,
                self::clientIp(), $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);

            return [
                'success' => true,
                'message' => $enabled
                    ? '2FA réactivée pour cet utilisateur.'
                    : '2FA désactivée temporairement (action journalisée dans l\'audit).'
            ];
        } catch (Throwable $e) {
            error_log("set2FA: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur technique'];
        }
    }

    /* ============================================================
     * JOURNALISATION
     * ============================================================ */

    private static function logAttempt($email, $success, $message, $userId = null)
    {
        try {
            $db = self::getDB();
            $ip = self::clientIp();
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $stmt = $db->prepare("
                INSERT INTO audit_logs (iduser, action, module, description, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, 'login_attempt', 'auth', $message . ' - Email: ' . $email, $ip, $ua]);
        } catch (Throwable $e) {
            error_log("logAttempt: " . $e->getMessage());
        }
    }

    private static function logLogin($userId)
    {
        try {
            $db = self::getDB();
            $ip = self::clientIp();
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

            $stmt = $db->prepare("
                INSERT INTO audit_logs (iduser, action, module, description, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, 'login', 'auth', 'Connexion réussie', $ip, $ua]);

            $stmt = $db->prepare("UPDATE users SET last_login = NOW(), last_ip = ? WHERE iduser = ?");
            $stmt->execute([$ip, $userId]);
        } catch (Throwable $e) {
            error_log("logLogin: " . $e->getMessage());
        }
    }

    /* ============================================================
     * DÉCONNEXION
     * ============================================================ */

    public static function logout()
    {
        try {
            $db     = self::getDB();
            $userId = $_SESSION['user_id'] ?? null;
            if ($userId) {
                $ip = self::clientIp();
                $stmt = $db->prepare("
                    INSERT INTO audit_logs (iduser, action, module, description, ip_address, user_agent)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$userId, 'logout', 'auth', 'Déconnexion', $ip, $_SERVER['HTTP_USER_AGENT'] ?? '']);
            }
        } catch (Throwable $e) {
            error_log("logout: " . $e->getMessage());
        }
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
    }
}