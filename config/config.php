<?php
/**
 * Configuration générale AGAI
 * Version corrigée pour URLs propres
 */

// ============================================
// 1. DÉFINITION DES CHEMINS
// ============================================

define('BASE_PATH', dirname(__DIR__));
define('CLASSES_PATH', BASE_PATH . '/classes');
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('CONFIG_PATH', BASE_PATH . '/config');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('UPLOAD_PATH', PUBLIC_PATH . '/uploads');
define('STORAGE_PATH', BASE_PATH . '/storage');  // Hors public : lettres, rapports
define('LOG_PATH', BASE_PATH . '/logs');
define('MAIL_PATH', BASE_PATH . '/mail');

// ============================================
// 2. FONCTION LOGGER
// ============================================

if (!function_exists('logger')) {
    function logger($message, $level = 'info', $context = []) {
        $logFile = LOG_PATH . '/app.log';
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logEntry = "[$timestamp] [$level] $message$contextStr" . PHP_EOL;
        @file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
}

// Créer le dossier logs
if (!is_dir(LOG_PATH)) {
    @mkdir(LOG_PATH, 0755, true);
}

// ============================================
// 3. CHARGEMENT AUTOMATIQUE DES CLASSES
// ============================================

spl_autoload_register(function ($class) {
    $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    $className = basename($class);
    
    $paths = [
        CLASSES_PATH . '/' . $className . '.php',
        CLASSES_PATH . '/' . $class . '.php',
        INCLUDES_PATH . '/' . $className . '.php',
        INCLUDES_PATH . '/' . $class . '.php',
    ];
    
    foreach ($paths as $file) {
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
    }
    return false;
});

// ============================================
// 4. CHARGEMENT DU FICHIER .ENV
// ============================================

class EnvLoader
{
    private static $loaded = false;
    private static $variables = [];

    public static function load($path = null)
    {
        if (self::$loaded) {
            return;
        }

        $path = $path ?: BASE_PATH . '/.env';
        
        if (!file_exists($path)) {
            self::$loaded = true;
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (strpos($line, '#') === 0 || empty($line)) {
                continue;
            }
            
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                $value = trim($value, '"\'');
                
                if ($value === 'true') $value = true;
                if ($value === 'false') $value = false;
                if ($value === 'null') $value = null;
                
                self::$variables[$key] = $value;
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
        
        self::$loaded = true;
    }

    public static function get($key, $default = null)
    {
        if (isset(self::$variables[$key])) {
            return self::$variables[$key];
        }
        
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        
        return $default;
    }
}

EnvLoader::load();

if (!function_exists('env')) {
    function env($key, $default = null) {
        return EnvLoader::get($key, $default);
    }
}

// ============================================
// 5. DÉTECTION DE L'ENVIRONNEMENT
// ============================================

$protocol = 'http';
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $protocol = 'https';
} elseif (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
    $protocol = 'https';
}

// $host GARDE le port (ex: 192.168.5.3:7700) : indispensable pour construire
// des URLs correctes quand le serveur n'ecoute pas sur le port 80/443 par defaut.
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$host = str_replace([':80', ':443'], '', $host); // ne retire QUE les ports standards

// $hostNoPort NE GARDE PAS le port : utilise uniquement pour detecter si on est
// en local (localhost / 127.0.0.1 / IP privee), peu importe le port utilise.
$hostNoPort = preg_replace('/:\d+$/', '', $host);

// Liste des adresses locales
$localHosts = ['localhost', '127.0.0.1', '::1'];
$isLocal = in_array($hostNoPort, $localHosts) || preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $hostNoPort) === 1;

// ============================================
// 6. CONSTRUCTION DES URLS
// ============================================

$projectFolder = '/AGAI';

// Construction de l'URL de base (avec le port s'il y en a un, ex: :7700)
if ($isLocal) {
    $baseUrl = $protocol . '://' . $host . $projectFolder;
} else {
    $baseUrl = env('APP_URL', $protocol . '://' . $host . $projectFolder);
}

// Supprimer les doublons
$baseUrl = str_replace('//', '/', $baseUrl);
$baseUrl = str_replace('http:/', 'http://', $baseUrl);
$baseUrl = str_replace('https:/', 'https://', $baseUrl);

// CONSTANTES
define('PROTOCOL', $protocol);
define('HOST_NAME', $host);
define('IS_LOCAL', $isLocal);

// SITE_URL - sans slash à la fin
define('SITE_URL', rtrim($baseUrl, '/'));
define('BASE_URL', rtrim($baseUrl, '/'));
define('ASSETS_URL', rtrim($baseUrl, '/') . '/public');

// ============================================
// 7. CONSTANTES PRINCIPALES
// ============================================

$appDebug = env('APP_DEBUG', $isLocal ? true : false);
$appEnv = env('APP_ENV', $isLocal ? 'development' : 'production');

define('APP_NAME', env('APP_NAME', 'AGAI - Système de Surveillance Continue'));
define('APP_SHORT_NAME', env('APP_SHORT_NAME', 'AGAI'));
define('APP_VERSION', env('APP_VERSION', '1.0.0'));
define('APP_ENV', $appEnv);
define('APP_DEBUG', $appDebug);
define('APP_MAINTENANCE', env('APP_MAINTENANCE', false));

// ============================================
// 8. GESTION DES ERREURS
// ============================================

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_PATH . '/errors.log');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_PATH . '/errors.log');
}

// ============================================
// 9. SESSIONS
// ============================================

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', $isLocal ? 0 : 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.gc_maxlifetime', (int) env('SESSION_TIMEOUT', 3600));

session_name('AGAI_SESSION');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// 10. BASE DE DONNÉES
// ============================================

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'siganac'));
define('DB_USER', env('DB_USER', 'user_siganac'));
define('DB_PASSWORD', env('DB_PASSWORD', 'Eth@n@2018'));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));
define('DB_PORT', (int) env('DB_PORT', 3306));

define('DB_OPTIONS', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_PERSISTENT => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
]);
    
// ============================================
// 11. SÉCURITÉ
// ============================================

define('SECURITY_SALT', env('SECURITY_SALT', 'AGAI_SECURE_SALT_2026_ANAC_GABON'));
define('MAX_LOGIN_ATTEMPTS', (int) env('MAX_LOGIN_ATTEMPTS', 5));
// Seuil de blocage par ADRESSE IP (protection brute-force / enumeration comptes)
define('MAX_LOGIN_ATTEMPTS_IP', (int) env('MAX_LOGIN_ATTEMPTS_IP', 20));
define('LOCKOUT_TIME', (int) env('LOCKOUT_TIME', 900));
define('SESSION_TIMEOUT', (int) env('SESSION_TIMEOUT', 3600));
define('TWO_FA_ENABLED', env('TWO_FA_ENABLED', true));
define('CSRF_TOKEN_NAME', 'csrf_token');

// ------------------------------------------------------------
// ROTATION / RETENTION DES JOURNAUX
// ------------------------------------------------------------
// Journaux fichiers (logs/app.log, logs/errors.log) et table login_attempts
define('LOG_RETENTION_DAYS', (int) env('LOG_RETENTION_DAYS', 30));
// Taille (Mo) au-dela de laquelle un fichier de log est force a pivoter
define('LOG_MAX_SIZE_MB', (int) env('LOG_MAX_SIZE_MB', 5));
// Retention plus longue pour la table audit_logs (traçabilite/conformite) :
// archivage (CSV compresse) avant purge, jamais de suppression sans archive
define('AUDIT_LOG_RETENTION_DAYS', (int) env('AUDIT_LOG_RETENTION_DAYS', 730));

// ============================================
// 12. EMAIL
// ============================================

define('MAIL_HOST', env('MAIL_HOST', 'smtp.gmail.com'));
define('MAIL_PORT', (int) env('MAIL_PORT', 465));
define('MAIL_USERNAME', env('MAIL_USERNAME', 'siganac.anacgabon@gmail.com'));
define('MAIL_PASSWORD', env('MAIL_PASSWORD', 'phffcbxvhxasnxkz'));
define('MAIL_ENCRYPTION', env('MAIL_ENCRYPTION', 'ssl'));
define('MAIL_FROM', env('MAIL_FROM', 'siganac.anacgabon@gmail.com'));
define('MAIL_FROM_NAME', env('MAIL_FROM_NAME', 'AGAI - ANAC Gabon'));
define('MAIL_ENABLED', env('MAIL_ENABLED', true));

// ============================================
// 13. FONCTIONS UTILITAIRES
// ============================================

if (!function_exists('dd')) {
    function dd(...$vars) {
        if (!APP_DEBUG) return;
        echo '<pre style="background:#1a1a2e;color:#fff;padding:20px;border-radius:8px;margin:10px;font-family:monospace;font-size:14px;max-height:80vh;overflow:auto;border-left:4px solid #23408F;">';
        foreach ($vars as $var) {
            var_dump($var);
            echo PHP_EOL . str_repeat('─', 80) . PHP_EOL;
        }
        echo '</pre>';
        die;
    }
}

if (!function_exists('e')) {
    function e($data) {
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
}

// ============================================
// 14. REDIRECTION - VERS URLs PROPRES (sans .php)
// ============================================

if (!function_exists('redirect')) {
    function redirect($url, $statusCode = 302) {
        if (headers_sent()) {
            echo "<script>window.location.href='$url';</script>";
            exit;
        }
        
        // Si l'URL commence par http, on la garde
        if (strpos($url, 'http') === 0) {
            header('Location: ' . $url, true, $statusCode);
        } else {
            // Retirer .php pour les URLs propres
            $url = str_replace('.php', '', $url);
            $fullUrl = SITE_URL . '/' . ltrim($url, '/');
            header('Location: ' . $fullUrl, true, $statusCode);
        }
        exit;
    }
}

// ============================================
// 15. CRÉATION DES DOSSIERS
// ============================================

$directories = [LOG_PATH, UPLOAD_PATH, STORAGE_PATH, LOG_PATH . '/archive', STORAGE_PATH . '/archives'];
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// ============================================
// 16. LOG DU DÉMARRAGE
// ============================================

if (APP_DEBUG) {
    logger('AGAI - Configuration chargée', 'info', [
        'url' => SITE_URL,
        'local' => IS_LOCAL,
        'host' => HOST_NAME
    ]);
}

// ============================================
// 17. VÉRIFICATION DE LA BASE DE DONNÉES
// ============================================

if (APP_DEBUG) {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, DB_OPTIONS);
        $pdo->query("SELECT 1");
        $pdo = null;
        define('DB_CONNECTED', true);
    } catch (PDOException $e) {
        logger('Erreur de connexion BDD', 'critical', [
            'message' => $e->getMessage(),
            'code' => $e->getCode()
        ]);
        define('DB_CONNECTED', false);
    }
}

// ============================================
// 18. ROTATION AUTOMATIQUE DES JOURNAUX (filet de securite)
// ------------------------------------------------------------
// Le mecanisme principal est la tache planifiee cron/maintenance_logs.php
// (cron Linux en production / Planificateur de taches Windows en local XAMPP).
// Ce declenchement probabiliste et non bloquant garantit que les fichiers
// app.log / errors.log ne grossissent pas indefiniment meme si la tache
// planifiee n'a pas encore ete configuree sur le serveur.
// La purge de login_attempts et l'archivage de audit_logs (operations BDD
// plus couteuses) restent reserves au cron et au bouton de maintenance manuel
// (page "Tentatives de connexion", reserve aux administrateurs).
// ============================================

if (random_int(1, 100) <= 2) {
    try {
        LogRotator::rotateFileLogs();
    } catch (Throwable $e) {
        error_log('Rotation automatique (filet de securite) : ' . $e->getMessage());
    }
}

// ============================================
// 19. FIN DE LA CONFIGURATION
// ============================================

// Fin du fichier