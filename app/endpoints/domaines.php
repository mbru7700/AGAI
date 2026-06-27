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
                        (SELECT COUNT(*) FROM habilitation h WHERE h.iddomaine = d.iddomaine) AS nb_hab,
                        (SELECT COUNT(DISTINCT ae.idaudit) FROM audit_equipe ae WHERE ae.iddomaine = d.iddomaine) AS nb_aud
                 FROM domaine d
                 ORDER BY d.iddomaine DESC"
            )->fetchAll();
            $ok(['data' => $rows]);
            break;

        // ----------------------------------------------------------------
        case 'stats':
            $st = $db->query(
                "SELECT
                    (SELECT COUNT(*) FROM domaine)                                                        AS total,
                    (SELECT COUNT(*) FROM sous_domaine)                                                    AS sous,
                    (SELECT COUNT(*) FROM reglement)                                                       AS regs,
                    (SELECT COUNT(*) FROM habilitation)                                                    AS habs,
                    (SELECT COUNT(DISTINCT iddomaine) FROM audit_equipe)                                   AS audits,
                    (SELECT COUNT(DISTINCT idinspecteur) FROM habilitation)                                AS inspecteurs"
            )->fetch();
            foreach ($st as $k => $v) { $st[$k] = (int) $v; }
            $ok(['stats' => $st]);
            break;

        // ----------------------------------------------------------------
        // Detail complet : sous-domaines, reglements, habilitations/inspecteurs, audits
        case 'detail':
            $id = (int) ($_POST['iddomaine'] ?? 0);
            if ($id <= 0) { $fail('Domaine introuvable.'); break; }
            $stD = $db->prepare("SELECT iddomaine, nomdomaine, libel_domaine FROM domaine WHERE iddomaine = ?");
            $stD->execute([$id]);
            $d = $stD->fetch();
            if (!$d) { $fail('Domaine introuvable.'); break; }
            // Sous-domaines ORDER DESC
            $stSd = $db->prepare(
                "SELECT idsousdomaine, nom_sousdomaine AS nomsd FROM sous_domaine WHERE iddomaine = ? ORDER BY idsousdomaine DESC"
            );
            $stSd->execute([$id]);
            $sous = $stSd->fetchAll();
            // Reglements ORDER DESC
            $stReg = $db->prepare(
                "SELECT idreglement, code_reglement, libelle_reglement FROM reglement WHERE iddomaine = ? ORDER BY idreglement DESC"
            );
            $stReg->execute([$id]);
            $regs = $stReg->fetchAll();
            // Habilitations + inspecteurs
            $stHab = $db->prepare(
                "SELECT h.idhabilitation, h.idinspecteur, h.numero_habilitation,
                        h.date_habilitation, h.date_expiration,
                        i.nominspecteur, i.preninspect, i.trigr_inspecteur
                 FROM habilitation h
                 JOIN inspecteur i ON i.idinspecteur = h.idinspecteur
                 WHERE h.iddomaine = ?
                 ORDER BY h.date_expiration ASC, i.nominspecteur"
            );
            $stHab->execute([$id]);
            $habs = $stHab->fetchAll();
            // Audits recents associes ORDER DESC
            $stAud = $db->prepare(
                "SELECT DISTINCT a.idaudit, a.num_audit, a.type_activite, a.statut, a.date_previsionnelle,
                        o.nomorga
                 FROM audit_equipe ae
                 JOIN audit a ON a.idaudit = ae.idaudit
                 LEFT JOIN organisme o ON o.idorga = a.idorga
                 WHERE ae.iddomaine = ?
                 ORDER BY a.idaudit DESC
                 LIMIT 20"
            );
            $stAud->execute([$id]);
            $auds = $stAud->fetchAll();
            $ok(['data' => $d, 'sous_domaines' => $sous, 'reglements' => $regs, 'habilitations' => $habs, 'audits' => $auds]);
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