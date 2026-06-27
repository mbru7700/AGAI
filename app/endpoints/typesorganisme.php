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
                        (SELECT COUNT(*) FROM organisme  o  WHERE o.typeorga  = t.idtypeorga) AS nb_org,
                        (SELECT COUNT(*) FROM habilitation h WHERE h.iddomaine IN
                            (SELECT iddomaine FROM domaine)) AS nb_hab_global,
                        (SELECT COUNT(DISTINCT a.idaudit)
                           FROM organisme o2
                           JOIN audit a ON a.idorga = o2.idorga
                          WHERE o2.typeorga = t.idtypeorga) AS nb_aud,
                        (SELECT COUNT(*) FROM habilitation h2
                           JOIN inspecteur i ON i.idinspecteur = h2.idinspecteur
                          WHERE i.idinspecteur IN
                              (SELECT ae.idinspecteur FROM audit_equipe ae
                               JOIN audit a2 ON a2.idaudit = ae.idaudit
                               JOIN organisme o3 ON o3.idorga = a2.idorga
                               WHERE o3.typeorga = t.idtypeorga)) AS nb_hab
                 FROM type_organisme t
                 ORDER BY t.idtypeorga DESC"
            )->fetchAll();
            $ok(['data' => $rows]);
            break;

        // ----------------------------------------------------------------
        case 'stats':
            $st = $db->query(
                "SELECT
                    (SELECT COUNT(*) FROM type_organisme)                                              AS total,
                    (SELECT COUNT(*) FROM organisme WHERE typeorga > 0)                                AS orgs,
                    (SELECT COUNT(DISTINCT a.idaudit) FROM audit a JOIN organisme o ON o.idorga=a.idorga WHERE o.typeorga > 0) AS audits,
                    (SELECT COUNT(*) FROM habilitation)                                                AS habs,
                    (SELECT COUNT(DISTINCT t.idtypeorga) FROM type_organisme t
                       JOIN organisme o ON o.typeorga = t.idtypeorga)                                  AS avec_orgs,
                    (SELECT COUNT(*) FROM type_organisme t
                       WHERE NOT EXISTS (SELECT 1 FROM organisme o WHERE o.typeorga = t.idtypeorga))   AS sans_orgs"
            )->fetch();
            foreach ($st as $k => $v) { $st[$k] = (int) $v; }
            $ok(['stats' => $st]);
            break;

        // ----------------------------------------------------------------
        // Detail : operateurs et audits associes
        case 'detail':
            $id = (int) ($_POST['idtypeorga'] ?? 0);
            if ($id <= 0) { $fail("Type d'organisme introuvable."); break; }
            $stT = $db->prepare("SELECT idtypeorga, nomtypeorg, datesaizi FROM type_organisme WHERE idtypeorga = ?");
            $stT->execute([$id]);
            $t = $stT->fetch();
            if (!$t) { $fail("Type d'organisme introuvable."); break; }
            // Operateurs de ce type (ORDER DESC)
            $stOrg = $db->prepare(
                "SELECT idorga, nomorga, trigrorganisme, statutorga, ville_org
                 FROM organisme WHERE typeorga = ? ORDER BY idorga DESC"
            );
            $stOrg->execute([$id]);
            $orgs = $stOrg->fetchAll();
            // Audits associes via les operateurs de ce type (ORDER DESC)
            $stAud = $db->prepare(
                "SELECT a.num_audit, a.type_activite, a.statut, a.date_previsionnelle, o.nomorga
                 FROM audit a
                 JOIN organisme o ON o.idorga = a.idorga
                 WHERE o.typeorga = ?
                 ORDER BY a.idaudit DESC
                 LIMIT 30"
            );
            $stAud->execute([$id]);
            $auds = $stAud->fetchAll();
            $ok(['data' => $t, 'operateurs' => $orgs, 'audits' => $auds]);
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