<?php
/**
 * Endpoint AJAX : Reglements (table `reglement`). Route : /api/reglements
 * ------------------------------------------------------------
 * Module "Donnees de structures". CRUD complet : list, domaines, stats,
 * get, check_code, create, update, delete.
 *
 * Securite : session + role autorise (guardApi 'structures') + CSRF,
 * requetes preparees, validation serveur (domaine parent existant),
 * journalisation, et refus de suppression si le reglement est associe a
 * des audits (table de liaison audit_reglement).
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }

Rbac::guardApi('reglements');

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

try {
    switch ($action) {

        // ----------------------------------------------------------------
        case 'list':
            $rows = $db->query(
                "SELECT r.idreglement, r.iddomaine, r.code_reglement, r.libelle_reglement, r.description,
                        d.nomdomaine, d.libel_domaine,
                        (SELECT COUNT(*) FROM audit_reglement a WHERE a.idreglement = r.idreglement) AS nb_aud
                 FROM reglement r
                 LEFT JOIN domaine d ON d.iddomaine = r.iddomaine
                 ORDER BY r.idreglement DESC"
            )->fetchAll();
            $ok(['data' => $rows]);
            break;

        // ----------------------------------------------------------------
        case 'domaines':
            $rows = $db->query("SELECT iddomaine, nomdomaine, libel_domaine FROM domaine ORDER BY nomdomaine")->fetchAll();
            $ok(['data' => $rows]);
            break;

        // ----------------------------------------------------------------
        case 'stats':
            $st = $db->query(
                "SELECT
                    (SELECT COUNT(*) FROM reglement)                                               AS total,
                    (SELECT COUNT(DISTINCT iddomaine) FROM reglement)                              AS dom_couverts,
                    (SELECT COUNT(DISTINCT idreglement) FROM audit_reglement)                      AS nb_aud,
                    (SELECT COUNT(*) FROM audit_reglement)                                         AS nb_cite,
                    (SELECT COUNT(*) FROM reglement WHERE description IS NOT NULL AND TRIM(description) <> '') AS avec_desc,
                    (SELECT COUNT(*) FROM reglement r WHERE NOT EXISTS
                        (SELECT 1 FROM audit_reglement ar WHERE ar.idreglement = r.idreglement))   AS jamais"
            )->fetch();
            foreach ($st as $k => $v) { $st[$k] = (int) $v; }
            $ok(['stats' => $st]);
            break;

        // ----------------------------------------------------------------
        // Synthese pour les modales des KPI :
        //  - domaines couverts (avec nb de reglements)
        //  - reglements utilises en audits (avec nb de citations)
        //  - reglements avec description
        //  - reglements jamais utilises
        case 'synthese':
            // Domaines couverts : chaque domaine ayant au moins un reglement
            $domaines = $db->query(
                "SELECT d.iddomaine, d.nomdomaine, d.libel_domaine,
                        COUNT(r.idreglement) AS nb_reg
                 FROM domaine d
                 JOIN reglement r ON r.iddomaine = d.iddomaine
                 GROUP BY d.iddomaine
                 ORDER BY nb_reg DESC, d.nomdomaine"
            )->fetchAll();

            // Reglements utilises en audits (avec nombre de citations)
            $utilises = $db->query(
                "SELECT r.idreglement, r.code_reglement, r.libelle_reglement,
                        d.nomdomaine,
                        COUNT(ar.idaudit_reglement) AS nb_cite,
                        COUNT(DISTINCT ar.idaudit)  AS nb_audits
                 FROM reglement r
                 JOIN audit_reglement ar ON ar.idreglement = r.idreglement
                 LEFT JOIN domaine d ON d.iddomaine = r.iddomaine
                 GROUP BY r.idreglement
                 ORDER BY nb_cite DESC, r.code_reglement"
            )->fetchAll();

            // Reglements avec description
            $avecDesc = $db->query(
                "SELECT r.idreglement, r.code_reglement, r.libelle_reglement, r.description, d.nomdomaine
                 FROM reglement r
                 LEFT JOIN domaine d ON d.iddomaine = r.iddomaine
                 WHERE r.description IS NOT NULL AND TRIM(r.description) <> ''
                 ORDER BY r.code_reglement"
            )->fetchAll();

            // Reglements jamais utilises
            $jamais = $db->query(
                "SELECT r.idreglement, r.code_reglement, r.libelle_reglement, d.nomdomaine
                 FROM reglement r
                 LEFT JOIN domaine d ON d.iddomaine = r.iddomaine
                 WHERE NOT EXISTS (SELECT 1 FROM audit_reglement ar WHERE ar.idreglement = r.idreglement)
                 ORDER BY r.code_reglement"
            )->fetchAll();

            $ok([
                'domaines'  => $domaines,
                'utilises'  => $utilises,
                'avec_desc' => $avecDesc,
                'jamais'    => $jamais,
            ]);
            break;

        // ----------------------------------------------------------------
        // Detail complet : infos + audits ou ce reglement est vise
        case 'detail':
            $id = (int) ($_POST['idreglement'] ?? 0);
            if ($id <= 0) { $fail('Reglement introuvable.'); break; }
            $stR = $db->prepare(
                "SELECT r.idreglement, r.iddomaine, r.code_reglement, r.libelle_reglement, r.description,
                        d.nomdomaine, d.libel_domaine
                 FROM reglement r
                 LEFT JOIN domaine d ON d.iddomaine = r.iddomaine
                 WHERE r.idreglement = ?"
            );
            $stR->execute([$id]);
            $reg = $stR->fetch();
            if (!$reg) { $fail('Reglement introuvable.'); break; }
            // Audits ou ce reglement est vise (via audit_reglement) ORDER DESC
            $stAud = $db->prepare(
                "SELECT DISTINCT a.idaudit, a.num_audit, a.type_activite, a.statut,
                        a.date_previsionnelle, o.nomorga,
                        TRIM(CONCAT(COALESCE(insp.preninspect,''),' ',COALESCE(insp.nominspecteur,''))) AS nom_inspecteur
                 FROM audit_reglement ar
                 JOIN audit a ON a.idaudit = ar.idaudit
                 LEFT JOIN organisme  o    ON o.idorga      = a.idorga
                 LEFT JOIN inspecteur insp ON insp.idinspecteur = a.idresponsable_audit
                 WHERE ar.idreglement = ?
                 ORDER BY a.idaudit DESC
                 LIMIT 30"
            );
            $stAud->execute([$id]);
            $auds = $stAud->fetchAll();
            $ok(['data' => $reg, 'audits' => $auds]);
            break;

        // ----------------------------------------------------------------
        case 'get':
            $id = (int) ($_POST['idreglement'] ?? 0);
            $st = $db->prepare("SELECT idreglement, iddomaine, code_reglement, libelle_reglement, description FROM reglement WHERE idreglement = ?");
            $st->execute([$id]);
            $r = $st->fetch();
            if (!$r) { $fail('Reglement introuvable.'); break; }
            $ok(['data' => $r]);
            break;

        // ----------------------------------------------------------------
        // Doublon sur le code (identifiant du reglement), insensible a la casse
        case 'check_code':
            $code = clean_label($_POST['code_reglement'] ?? '');
            $exc  = (int) ($_POST['idreglement'] ?? 0);
            if ($code === '') { $ok(['exists' => false]); break; }
            $st = $db->prepare("SELECT idreglement FROM reglement WHERE LOWER(code_reglement) = LOWER(?) AND idreglement <> ?");
            $st->execute([$code, $exc]);
            $ok(['exists' => (bool) $st->fetch()]);
            break;

        // ----------------------------------------------------------------
        case 'create_batch':
            // Insertion de plusieurs reglements en une seule requete (depuis le declenchement).
            // Recoit : iddomaine, codes[] et libelles[] en tableaux paralleles.
            $dom    = (int) ($_POST['iddomaine'] ?? 0);
            $codes  = $_POST['codes'] ?? [];
            $libs   = $_POST['libelles'] ?? [];
            if (!is_array($codes)) { $codes = []; }
            if (!is_array($libs))  { $libs = []; }

            if ($dom <= 0) { $fail('Domaine invalide.'); break; }
            $stD = $db->prepare("SELECT iddomaine FROM domaine WHERE iddomaine = ?");
            $stD->execute([$dom]);
            if (!$stD->fetch()) { $fail('Domaine inconnu.'); break; }

            $inserted = [];
            $errors   = [];
            $st  = $db->prepare("INSERT INTO reglement (iddomaine, code_reglement, libelle_reglement, description) VALUES (?, ?, ?, NULL)");
            $dup = $db->prepare("SELECT idreglement FROM reglement WHERE LOWER(code_reglement) = LOWER(?)");

            foreach ($codes as $k => $rawCode) {
                $code = clean_label($rawCode);
                $lib  = clean_label($libs[$k] ?? '');
                if ($code === '' || $lib === '') { continue; }
                if (mb_strlen($code) > 50)  { $errors[] = $code . ' (code trop long)'; continue; }
                if (mb_strlen($lib) > 255)  { $errors[] = $code . ' (libelle trop long)'; continue; }
                $dup->execute([$code]);
                if ($dup->fetch()) { $errors[] = $code . ' (code deja existant)'; continue; }
                $st->execute([$dom, $code, $lib]);
                $newId = (int) $db->lastInsertId();
                Audit::log('create', 'structures', 'Creation reglement #' . $newId . ' (' . $code . ')');
                $inserted[] = ['idreglement' => $newId, 'code_reglement' => $code, 'libelle_reglement' => $lib];
            }

            if (empty($inserted) && !empty($errors)) {
                $fail('Aucun reglement insere. Problemes : ' . implode(', ', $errors));
                break;
            }
            $ok(['inserted' => $inserted, 'errors' => $errors,
                 'message' => count($inserted) . ' reglement(s) insere(s).' . (count($errors) ? ' Ignores : ' . implode(', ', $errors) : '')]);
            break;

        // ----------------------------------------------------------------
        case 'create':
        case 'update':
            $isUpdate = ($action === 'update');
            $id       = (int) ($_POST['idreglement'] ?? 0);
            $dom      = (int) ($_POST['iddomaine'] ?? 0);
            $code     = clean_label($_POST['code_reglement'] ?? '');
            $libelle  = clean_label($_POST['libelle_reglement'] ?? '');
            $desc     = trim((string) ($_POST['description'] ?? ''));

            if ($dom <= 0) { $fail('Veuillez choisir le domaine.'); break; }
            if ($code === '' || mb_strlen($code) > 50) { $fail('Le code du reglement est requis (50 caracteres maximum).'); break; }
            if ($libelle === '' || mb_strlen($libelle) > 255) { $fail('Le libelle du reglement est requis (255 caracteres maximum).'); break; }

            $stD = $db->prepare("SELECT iddomaine FROM domaine WHERE iddomaine = ?");
            $stD->execute([$dom]);
            if (!$stD->fetch()) { $fail('Domaine inconnu. Merci de le re-selectionner.'); break; }

            $stDup = $db->prepare("SELECT idreglement FROM reglement WHERE LOWER(code_reglement) = LOWER(?) AND idreglement <> ?");
            $stDup->execute([$code, $id]);
            if ($stDup->fetch()) { $fail('Un reglement portant ce code existe deja.'); break; }

            if (!$isUpdate) {
                $st = $db->prepare("INSERT INTO reglement (iddomaine, code_reglement, libelle_reglement, description) VALUES (?, ?, ?, ?)");
                $st->execute([$dom, $code, $libelle, ($desc !== '' ? $desc : null)]);
                $newId = (int) $db->lastInsertId();
                Audit::log('create', 'structures', 'Creation reglement #' . $newId . ' (' . $code . ')');
                $ok(['message' => 'Reglement enregistre.', 'idreglement' => $newId]);
            } else {
                if ($id <= 0) { $fail('Reglement introuvable.'); break; }
                $stG = $db->prepare("SELECT idreglement FROM reglement WHERE idreglement = ?");
                $stG->execute([$id]);
                if (!$stG->fetch()) { $fail('Reglement introuvable.'); break; }
                $st = $db->prepare("UPDATE reglement SET iddomaine = ?, code_reglement = ?, libelle_reglement = ?, description = ? WHERE idreglement = ?");
                $st->execute([$dom, $code, $libelle, ($desc !== '' ? $desc : null), $id]);
                Audit::log('update', 'structures', 'Modification reglement #' . $id . ' (' . $code . ')');
                $ok(['message' => 'Reglement mis a jour.', 'idreglement' => $id]);
            }
            break;

        // ----------------------------------------------------------------
        case 'delete':
            $id = (int) ($_POST['idreglement'] ?? 0);
            if ($id <= 0) { $fail('Reglement introuvable.'); break; }

            $st = $db->prepare("SELECT COUNT(*) FROM audit_reglement WHERE idreglement = ?");
            $st->execute([$id]);
            $n = (int) $st->fetchColumn();
            if ($n > 0) {
                $fail('Suppression impossible : ce reglement est associe a ' . $n . ' audit(s). Detachez-le d\'abord.');
                break;
            }

            $db->prepare("DELETE FROM reglement WHERE idreglement = ?")->execute([$id]);
            Audit::log('delete', 'structures', 'Suppression reglement #' . $id);
            $ok(['message' => 'Reglement supprime.']);
            break;

        // ----------------------------------------------------------------
        default:
            $fail('Action inconnue.');
    }
} catch (Throwable $e) {
    error_log('reglements endpoint : ' . $e->getMessage());
    $fail('Erreur technique.');
}