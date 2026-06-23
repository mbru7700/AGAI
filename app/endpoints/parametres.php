<?php
/**
 * Endpoint AJAX : Parametres de l'application
 * Route : /api/parametres
 * Actions : list, update
 * Acces : admin seulement
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardApi('parametres');

if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success'=>false,'message'=>'Jeton CSRF invalide.']); exit;
}

$db     = Database::getInstance();
$action = $_POST['action'] ?? '';
$ok     = fn($x=[]) => print(json_encode(['success'=>true]+$x));
$fail   = fn($m)     => print(json_encode(['success'=>false,'message'=>$m]));

try {
    switch ($action) {

        case 'list':
            $rows = $db->query("SELECT idparam, nom_param, valeur_param, type_param, description FROM parametres ORDER BY idparam")->fetchAll();
            $ok(['data' => $rows]);
            break;

        case 'update':
            $nom = trim((string) ($_POST['nom_param'] ?? ''));
            $val = trim((string) ($_POST['valeur_param'] ?? ''));
            if ($nom === '') { $fail('Nom du parametre requis.'); break; }

            // Recuperer le type actuel
            $stT = $db->prepare("SELECT type_param FROM parametres WHERE nom_param = ?");
            $stT->execute([$nom]);
            $row = $stT->fetch();
            if (!$row) { $fail('Parametre introuvable.'); break; }

            // Validation selon le type
            $type = $row['type_param'];
            if ($type === 'integer' && !ctype_digit($val)) { $fail('Valeur numerique requise.'); break; }
            if ($type === 'boolean' && !in_array($val, ['0','1'], true)) { $val = $val ? '1' : '0'; }
            if ($type === 'email' && !Security::validateEmail($val)) { $fail('Adresse email invalide.'); break; }
            if ($type === 'url' && $val !== '' && !filter_var($val, FILTER_VALIDATE_URL)) { $fail('URL invalide.'); break; }
            if (mb_strlen($val) > 2000) { $fail('Valeur trop longue.'); break; }

            $db->prepare("UPDATE parametres SET valeur_param = ? WHERE nom_param = ?")->execute([$val, $nom]);
            Audit::log('update', 'parametres', "Modification parametre '$nom' = '$val'");
            $ok(['message' => 'Parametre mis a jour.']);
            break;

        default: $fail('Action inconnue.');
    }
} catch (Throwable $e) {
    error_log('parametres endpoint : ' . $e->getMessage());
    $fail('Erreur technique.');
}