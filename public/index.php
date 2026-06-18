<?php
/**
 * ============================================================
 * AGAI - ANAC Gabon
 * FRONT CONTROLLER  (public/index.php)
 * ------------------------------------------------------------
 * Seule porte d'entrée web de l'application.
 * Toutes les requêtes (/AGAI/, /AGAI/dashboard, /AGAI/auth ...)
 * sont routées ici par les .htaccess.
 *
 * Les pages et les dossiers sensibles vivent HORS de /public.
 * ============================================================
 */

// 1) Bootstrap : charge config, classes, session, .env ...
$configFile = dirname(__DIR__) . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Configuration introuvable. Fichier attendu : ' . $configFile
        . ' — vérifiez que le dossier config/ et ses fichiers sont bien présents.');
}
require_once $configFile;

// 2) Récupération + nettoyage de la route
$route = $_GET['route'] ?? '';
$route = parse_url($route, PHP_URL_PATH) ?? '';
$route = trim($route, '/');

// Anti-traversal : on ne garde que des caractères sûrs
$route = preg_replace('/[^A-Za-z0-9_\-\/]/', '', $route);

// Une URL en .php est redirigée vers sa version propre (301)
if (substr(strtolower($route), -4) === '.php') {
    $clean = substr($route, 0, -4);
    header('Location: ' . SITE_URL . '/' . ltrim($clean, '/'), true, 301);
    exit;
}

$route = strtolower($route);
if ($route === '' || $route === 'index') {
    $route = 'home';
}

// 3) Routes "actions" (pas une page HTML)
switch ($route) {

    // Déconnexion propre
    case 'logout':
        Auth::logout();
        redirect('/');               // -> SITE_URL . '/'
        break;

    // Endpoint AJAX d'authentification (login, 2FA, resend, refresh...)
    case 'auth':
        require dirname(__DIR__) . '/app/endpoints/auth.php';
        exit;
}

// 3bis) Endpoints AJAX des modules : /api/<nom>
if (strpos($route, 'api/') === 0) {
    $name       = substr($route, 4);
    $allowedApi = ['users', 'organisme', 'personnel', 'profile'];
    if (in_array($name, $allowedApi, true)) {
        require dirname(__DIR__) . '/app/endpoints/' . $name . '.php';
        exit;
    }
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Endpoint inconnu']);
    exit;
}

// 4) Table de routage des pages (liste blanche)
$pages = [
    'home'         => 'home.php',
    'verification' => 'verification.php',
    'dashboard'    => 'dashboard.php',
    'users'        => 'users.php',
    'profile'      => 'profile.php',
];

if (isset($pages[$route])) {
    $file = dirname(__DIR__) . '/app/pages/' . $pages[$route];
    if (is_file($file)) {
        require $file;
        exit;
    }
}

// 5) 404 -> page d'accueil
http_response_code(404);
$home = dirname(__DIR__) . '/app/pages/home.php';
if (is_file($home)) {
    require $home;
} else {
    echo 'Page introuvable.';
}
exit;