<?php
/**
 * Endpoint AJAX : Organismes (liste, verification de doublon, ajout rapide)
 * Accas : administrateurs. Route : /api/organisme
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
            $st = $db->query(
                "SELECT MIN(idorga) AS idorga, nomorga, MAX(trigrorganisme) AS trigrorganisme
                 FROM organisme
                 WHERE idorga > 0 AND TRIM(nomorga) <> ''
                 GROUP BY nomorga
                 ORDER BY nomorga"
            );
            echo json_encode(['success' => true, 'data' => $st->fetchAll()]);
            break;

        // Verification temps reel d'un doublon de nom
        case 'check':
            $nom = trim($_POST['nomorga'] ?? '');
            if ($nom === '') { echo json_encode(['exists' => false]); break; }
            $st = $db->prepare("SELECT idorga FROM organisme WHERE LOWER(TRIM(nomorga)) = LOWER(TRIM(?)) LIMIT 1");
            $st->execute([$nom]);
            $row = $st->fetch();
            echo json_encode(['exists' => (bool) $row, 'idorga' => $row['idorga'] ?? null]);
            break;

        case 'create':
            $nom = trim($_POST['nomorga'] ?? '');
            if ($nom === '' || mb_strlen($nom) > 255) {
                echo json_encode(['success' => false, 'message' => "Le nom de l'organisme est requis."]);
                break;
            }
            // Garde serveur anti-doublon (en plus du controle client)
            $st = $db->prepare("SELECT idorga FROM organisme WHERE LOWER(TRIM(nomorga)) = LOWER(TRIM(?)) LIMIT 1");
            $st->execute([$nom]);
            if ($st->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Cet organisme existe deja.']);
                break;
            }

            $sigle = trim($_POST['trigrorganisme'] ?? '');
            $ville = trim($_POST['ville_org'] ?? '');
            $tel   = trim($_POST['telorga'] ?? '');
            $email = trim($_POST['emailorga'] ?? '');
            if ($email !== '' && !Security::validateEmail($email)) {
                echo json_encode(['success' => false, 'message' => "Email de l'organisme invalide."]);
                break;
            }

            // La table organisme a de nombreuses colonnes NOT NULL : valeurs par defaut sures.
            $st = $db->prepare(
                "INSERT INTO organisme
                 (nomorga, typeorga, lieuorga, adresorga, telorga, emailorga, faxorga,
                  statutorga, trigrorganisme, createur, datexpire, siteactivite, cateoperater,
                  nom_commercial_org, ville_org, idpays, boite_postal_org)
                 VALUES (?, 0, '', '', ?, ?, '', '', ?, ?, CURDATE(), '', '', '', ?, 0, '')"
            );
            $st->execute([$nom, $tel, $email, $sigle, (int) ($_SESSION['user_id'] ?? 0), $ville]);
            $newId = (int) $db->lastInsertId();

            Audit::log('create', 'organisme', "Ajout organisme « $nom » (#$newId)");
            echo json_encode(['success' => true, 'idorga' => $newId, 'message' => 'Organisme ajoute.']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Action inconnue.']);
    }
} catch (Throwable $e) {
    error_log('organisme endpoint : ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur technique.']);
}