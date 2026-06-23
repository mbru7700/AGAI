<?php
/**
 * Endpoint AJAX : Domaines (table `domaine`). Route : /api/domaines
 * ------------------------------------------------------------
 * Module "Donnees de structures". CRUD complet : list, get, create,
 * update, delete, plus check_label (controle de doublon en direct).
 *
 * Securite : session + role autorise (guardApi 'structures') + CSRF
 * sur toute action mutatrice, requetes preparees partout, validation
 * serveur, journalisation, et garde-fou a la suppression (refus si le
 * domaine est rattache a des sous-domaines, reglements, habilitations,
 * audits ou fiches).
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }

Rbac::guardApi('domaines');

if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Jeton CSRF invalide. Rechargez la page.']);
    exit;
}

$db     = Database::getInstance();
$action = $_POST['action'] ?? '';

$ok   = function ($extra = []) { echo json_encode(['success' => true] + $extra); };
$fail = function ($msg) { echo json_encode(['success' => false, 'message' => $msg]); };

/* Nettoie un libelle (espaces, retours a la ligne, tabulations parasites). */
function clean_label($s): string
{
    return trim(preg_replace('/\s+/', ' ', (string) $s));
}

try {
    switch ($action) {

        // ----------------------------------------------------------------
        case 'list':
            $rows = $db->query(
                "SELECT d.iddomaine, d.nomdomaine, d.libel_domaine,
                        (SELECT COUNT(*) FROM sous_domaine s WHERE s.iddomaine = d.iddomaine) AS nb_sd,
                        (SELECT COUNT(*) FROM reglement r   WHERE r.iddomaine = d.iddomaine) AS nb_reg,
                        (SELECT COUNT(*) FROM habilitation h WHERE h.iddomaine = d.iddomaine) AS nb_hab
                 FROM domaine d
                 ORDER BY d.iddomaine DESC"
            )->fetchAll();
            $ok(['data' => $rows]);
            break;

        // ----------------------------------------------------------------
        case 'stats':
            $st = $db->query(
                "SELECT
                    (SELECT COUNT(*) FROM domaine)      AS total,
                    (SELECT COUNT(*) FROM sous_domaine) AS sous,
                    (SELECT COUNT(*) FROM reglement)    AS regs"
            )->fetch();
            $ok(['stats' => $st]);
            break;

        // ----------------------------------------------------------------
        case 'get':
            $id = (int) ($_POST['iddomaine'] ?? 0);
            $st = $db->prepare("SELECT iddomaine, nomdomaine, libel_domaine FROM domaine WHERE iddomaine = ?");
            $st->execute([$id]);
            $d = $st->fetch();
            if (!$d) { $fail('Domaine introuvable.'); break; }
            $ok(['data' => $d]);
            break;

        // ----------------------------------------------------------------
        // Controle de doublon en direct (sur le nom du domaine), hors id courant
        case 'check_nom':
            $nom = clean_label($_POST['nomdomaine'] ?? '');
            $exc = (int) ($_POST['iddomaine'] ?? 0);
            if ($nom === '') { $ok(['exists' => false]); break; }
            $st = $db->prepare("SELECT iddomaine FROM domaine WHERE LOWER(nomdomaine) = LOWER(?) AND iddomaine <> ?");
            $st->execute([$nom, $exc]);
            $ok(['exists' => (bool) $st->fetch()]);
            break;

        // ----------------------------------------------------------------
        case 'create':
        case 'update':
            $isUpdate = ($action === 'update');
            $id       = (int) ($_POST['iddomaine'] ?? 0);
            $nom      = clean_label($_POST['nomdomaine'] ?? '');
            $libel    = clean_label($_POST['libel_domaine'] ?? '');

            if ($nom === '' || mb_strlen($nom) > 255) { $fail('Le nom du domaine est requis (255 caracteres maximum).'); break; }
            if ($libel !== '' && mb_strlen($libel) > 255) { $fail('Le libelle du domaine est trop long (255 caracteres maximum).'); break; }
            // libel_domaine est NOT NULL en base : si vide, on reprend le nom
            if ($libel === '') { $libel = $nom; }

            // Doublon de nom (insensible a la casse), hors enregistrement courant
            $stDup = $db->prepare("SELECT iddomaine FROM domaine WHERE LOWER(nomdomaine) = LOWER(?) AND iddomaine <> ?");
            $stDup->execute([$nom, $id]);
            if ($stDup->fetch()) { $fail('Un domaine portant ce nom existe deja.'); break; }

            if (!$isUpdate) {
                $st = $db->prepare("INSERT INTO domaine (nomdomaine, libel_domaine) VALUES (?, ?)");
                $st->execute([$nom, $libel]);
                $newId = (int) $db->lastInsertId();
                Audit::log('create', 'structures', 'Creation domaine #' . $newId . ' (' . $nom . ')');
                $ok(['message' => 'Domaine enregistre.', 'iddomaine' => $newId]);
            } else {
                if ($id <= 0) { $fail('Domaine introuvable.'); break; }
                $stG = $db->prepare("SELECT iddomaine FROM domaine WHERE iddomaine = ?");
                $stG->execute([$id]);
                if (!$stG->fetch()) { $fail('Domaine introuvable.'); break; }
                $st = $db->prepare("UPDATE domaine SET nomdomaine = ?, libel_domaine = ? WHERE iddomaine = ?");
                $st->execute([$nom, $libel, $id]);
                Audit::log('update', 'structures', 'Modification domaine #' . $id . ' (' . $nom . ')');
                $ok(['message' => 'Domaine mis a jour.', 'iddomaine' => $id]);
            }
            break;

        // ----------------------------------------------------------------
        case 'delete':
            $id = (int) ($_POST['iddomaine'] ?? 0);
            if ($id <= 0) { $fail('Domaine introuvable.'); break; }

            // Garde-fou : on refuse la suppression si le domaine est utilise,
            // pour eviter toute suppression en cascade involontaire.
            $refs = [];
            $checks = [
                'sous_domaine'        => 'sous-domaine(s)',
                'reglement'           => 'reglement(s)',
                'habilitation'        => 'habilitation(s)',
                'audit_equipe'        => 'affectation(s) d\'audit',
                'fiche_non_conformite'=> 'fiche(s) de non-conformite',
            ];
            foreach ($checks as $table => $label) {
                $st = $db->prepare("SELECT COUNT(*) FROM `$table` WHERE iddomaine = ?");
                $st->execute([$id]);
                $n = (int) $st->fetchColumn();
                if ($n > 0) { $refs[] = $n . ' ' . $label; }
            }
            if ($refs) {
                $fail('Suppression impossible : ce domaine est rattache a ' . implode(', ', $refs) . '. Detachez ces elements d\'abord.');
                break;
            }

            $db->prepare("DELETE FROM domaine WHERE iddomaine = ?")->execute([$id]);
            Audit::log('delete', 'structures', 'Suppression domaine #' . $id);
            $ok(['message' => 'Domaine supprime.']);
            break;

        // ----------------------------------------------------------------
        default:
            $fail('Action inconnue.');
    }
} catch (Throwable $e) {
    error_log('domaines endpoint : ' . $e->getMessage());
    $fail('Erreur technique.');
}