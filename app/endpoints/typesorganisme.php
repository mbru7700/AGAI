<?php
/**
 * Endpoint AJAX : Types d'organisme (table `type_organisme`).
 * Route : /api/typesorganisme
 * ------------------------------------------------------------
 * Module "Donnees de structures". CRUD complet : list, stats, get,
 * check_nom, create, update, delete.
 *
 * Securite : session + role autorise (guardApi 'structures') + CSRF,
 * requetes preparees, validation serveur, journalisation, et refus de
 * suppression si le type est utilise par un exploitant (organisme).
 *
 * Champs herites obligatoires : datesaizi (date d'enregistrement, fixee
 * a la creation) et numat (matricule de l'agent qui enregistre).
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }

Rbac::guardApi('typesorganisme');

if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Jeton CSRF invalide. Rechargez la page.']);
    exit;
}

$db     = Database::getInstance();
$action = $_POST['action'] ?? '';

$ok   = function ($extra = []) { echo json_encode(['success' => true] + $extra); };
$fail = function ($msg) { echo json_encode(['success' => false, 'message' => $msg]); };

function clean_label($s): string
{
    return trim(preg_replace('/\s+/', ' ', (string) $s));
}

/* Matricule de l'agent connecte (pour le champ numat), repli sur l'id. */
function current_agent(): int
{
    $mat = (int) ($_SESSION['user']['matricule'] ?? 0);
    if ($mat > 0) { return $mat; }
    return (int) ($_SESSION['user']['id'] ?? 0);
}

try {
    switch ($action) {

        // ----------------------------------------------------------------
        case 'list':
            $rows = $db->query(
                "SELECT t.idtypeorga, t.nomtypeorg, t.datesaizi,
                        (SELECT COUNT(*) FROM organisme o WHERE o.typeorga = t.idtypeorga) AS nb_org
                 FROM type_organisme t
                 ORDER BY t.idtypeorga DESC"
            )->fetchAll();
            $ok(['data' => $rows]);
            break;

        // ----------------------------------------------------------------
        case 'stats':
            $st = $db->query(
                "SELECT
                    (SELECT COUNT(*) FROM type_organisme) AS total,
                    (SELECT COUNT(*) FROM organisme)       AS orgs"
            )->fetch();
            $ok(['stats' => $st]);
            break;

        // ----------------------------------------------------------------
        case 'get':
            $id = (int) ($_POST['idtypeorga'] ?? 0);
            $st = $db->prepare("SELECT idtypeorga, nomtypeorg, datesaizi FROM type_organisme WHERE idtypeorga = ?");
            $st->execute([$id]);
            $t = $st->fetch();
            if (!$t) { $fail('Type d\'organisme introuvable.'); break; }
            $ok(['data' => $t]);
            break;

        // ----------------------------------------------------------------
        case 'check_nom':
            $nom = clean_label($_POST['nomtypeorg'] ?? '');
            $exc = (int) ($_POST['idtypeorga'] ?? 0);
            if ($nom === '') { $ok(['exists' => false]); break; }
            $st = $db->prepare("SELECT idtypeorga FROM type_organisme WHERE LOWER(nomtypeorg) = LOWER(?) AND idtypeorga <> ?");
            $st->execute([$nom, $exc]);
            $ok(['exists' => (bool) $st->fetch()]);
            break;

        // ----------------------------------------------------------------
        case 'create':
        case 'update':
            $isUpdate = ($action === 'update');
            $id       = (int) ($_POST['idtypeorga'] ?? 0);
            $nom      = clean_label($_POST['nomtypeorg'] ?? '');

            if ($nom === '' || mb_strlen($nom) > 255) { $fail('Le nom du type d\'organisme est requis (255 caracteres maximum).'); break; }

            $stDup = $db->prepare("SELECT idtypeorga FROM type_organisme WHERE LOWER(nomtypeorg) = LOWER(?) AND idtypeorga <> ?");
            $stDup->execute([$nom, $id]);
            if ($stDup->fetch()) { $fail('Un type d\'organisme portant ce nom existe deja.'); break; }

            if (!$isUpdate) {
                // datesaizi et numat sont renseignes a la creation (champs herites NOT NULL)
                $st = $db->prepare("INSERT INTO type_organisme (nomtypeorg, datesaizi, numat) VALUES (?, ?, ?)");
                $st->execute([$nom, date('Y-m-d'), current_agent()]);
                $newId = (int) $db->lastInsertId();
                Audit::log('create', 'structures', 'Creation type organisme #' . $newId . ' (' . $nom . ')');
                $ok(['message' => 'Type d\'organisme enregistre.', 'idtypeorga' => $newId]);
            } else {
                if ($id <= 0) { $fail('Type d\'organisme introuvable.'); break; }
                $stG = $db->prepare("SELECT idtypeorga FROM type_organisme WHERE idtypeorga = ?");
                $stG->execute([$id]);
                if (!$stG->fetch()) { $fail('Type d\'organisme introuvable.'); break; }
                // On ne modifie que le nom : la date d'enregistrement et l'agent d'origine sont conserves
                $st = $db->prepare("UPDATE type_organisme SET nomtypeorg = ? WHERE idtypeorga = ?");
                $st->execute([$nom, $id]);
                Audit::log('update', 'structures', 'Modification type organisme #' . $id . ' (' . $nom . ')');
                $ok(['message' => 'Type d\'organisme mis a jour.', 'idtypeorga' => $id]);
            }
            break;

        // ----------------------------------------------------------------
        case 'delete':
            $id = (int) ($_POST['idtypeorga'] ?? 0);
            if ($id <= 0) { $fail('Type d\'organisme introuvable.'); break; }

            $st = $db->prepare("SELECT COUNT(*) FROM organisme WHERE typeorga = ?");
            $st->execute([$id]);
            $n = (int) $st->fetchColumn();
            if ($n > 0) {
                $fail('Suppression impossible : ce type est rattache a ' . $n . ' exploitant(s). Reclassez-les d\'abord.');
                break;
            }

            $db->prepare("DELETE FROM type_organisme WHERE idtypeorga = ?")->execute([$id]);
            Audit::log('delete', 'structures', 'Suppression type organisme #' . $id);
            $ok(['message' => 'Type d\'organisme supprime.']);
            break;

        // ----------------------------------------------------------------
        default:
            $fail('Action inconnue.');
    }
} catch (Throwable $e) {
    error_log('typesorganisme endpoint : ' . $e->getMessage());
    $fail('Erreur technique.');
}