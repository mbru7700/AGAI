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
            // Perimetre AGAI : operateur gere_agai='AGAI' OU ayant au moins un audit.
            $perimOrgList =
                "(o.gere_agai = 'AGAI' OR EXISTS (SELECT 1 FROM audit ax WHERE ax.idorga = o.idorga))";
            $rows = $db->query(
                "SELECT t.idtypeorga, t.nomtypeorg, t.datesaizi,
                        (SELECT COUNT(*) FROM organisme o
                          WHERE o.typeorga = t.idtypeorga
                            AND (o.gere_agai = 'AGAI' OR EXISTS (SELECT 1 FROM audit ax WHERE ax.idorga = o.idorga))
                        ) AS nb_org,
                        (SELECT COUNT(DISTINCT a.idaudit)
                           FROM organisme o2
                           JOIN audit a ON a.idorga = o2.idorga
                          WHERE o2.typeorga = t.idtypeorga
                            AND (o2.gere_agai = 'AGAI' OR EXISTS (SELECT 1 FROM audit ay WHERE ay.idorga = o2.idorga))
                        ) AS nb_aud,
                        (SELECT COUNT(DISTINCT h.idhabilitation)
                           FROM habilitation h
                           JOIN audit_equipe ae ON ae.idinspecteur = h.idinspecteur AND ae.iddomaine = h.iddomaine
                           JOIN audit a2 ON a2.idaudit = ae.idaudit
                           JOIN organisme o3 ON o3.idorga = a2.idorga
                          WHERE o3.typeorga = t.idtypeorga
                            AND (o3.gere_agai = 'AGAI' OR EXISTS (SELECT 1 FROM audit az WHERE az.idorga = o3.idorga))
                        ) AS nb_hab
                 FROM type_organisme t
                 ORDER BY t.idtypeorga DESC"
            )->fetchAll();
            $ok(['data' => $rows]);
            break;

        // ----------------------------------------------------------------
        case 'stats':
            // "Operateurs classes" et les comptages de types utilises/non utilises
            // se limitent au PERIMETRE AGAI (operateurs gere_agai='AGAI' ou audites),
            // et non a l'integralite du referentiel partage SIGANAC.
            // Sous-requete reutilisable : les organismes du perimetre AGAI.
            $perimOrg =
                "SELECT o.idorga FROM organisme o
                 WHERE o.gere_agai = 'AGAI'
                    OR EXISTS (SELECT 1 FROM audit a WHERE a.idorga = o.idorga)";
            $st = $db->query(
                "SELECT
                    (SELECT COUNT(*) FROM type_organisme) AS total,
                    (SELECT COUNT(*) FROM organisme o WHERE o.typeorga > 0 AND o.idorga IN ($perimOrg)) AS orgs,
                    (SELECT COUNT(DISTINCT a.idaudit)
                       FROM audit a
                      WHERE a.idorga IN ($perimOrg)) AS audits,
                    (SELECT COUNT(DISTINCT h.idhabilitation)
                       FROM habilitation h
                       JOIN audit_equipe ae ON ae.idinspecteur = h.idinspecteur AND ae.iddomaine = h.iddomaine
                       JOIN audit a ON a.idaudit = ae.idaudit AND a.idorga IN ($perimOrg)
                    ) AS habs,
                    (SELECT COUNT(DISTINCT o.typeorga)
                       FROM organisme o
                       JOIN type_organisme t ON t.idtypeorga = o.typeorga AND TRIM(t.nomtypeorg) <> ''
                      WHERE o.idorga IN ($perimOrg)) AS avec_orgs,
                    (SELECT COUNT(*) FROM type_organisme t
                       WHERE NOT EXISTS (
                          SELECT 1 FROM organisme o
                           WHERE o.typeorga = t.idtypeorga AND o.idorga IN ($perimOrg)
                       )) AS sans_orgs"
            )->fetch();
            foreach ($st as $k => $v) { $st[$k] = (int) $v; }
            $ok(['stats' => $st]);
            break;

        // ----------------------------------------------------------------
        // Synthese pour les modales des KPI (perimetre AGAI) :
        //  - types utilises (avec nb d'operateurs et nb d'audits)
        //  - types non utilises
        //  - audits associes (detail par type)
        //  - habilitations liees (inspecteurs habilites intervenant sur ces types)
        case 'synthese':
            $perimOrg =
                "(o.gere_agai = 'AGAI' OR EXISTS (SELECT 1 FROM audit ax WHERE ax.idorga = o.idorga))";

            // Types utilises : au moins un operateur AGAI de ce type.
            // On exclut les types au libelle vide (non significatifs).
            $utilises = $db->query(
                "SELECT t.idtypeorga, t.nomtypeorg,
                        COUNT(DISTINCT o.idorga) AS nb_org,
                        (SELECT COUNT(DISTINCT a.idaudit)
                           FROM organisme o2 JOIN audit a ON a.idorga = o2.idorga
                          WHERE o2.typeorga = t.idtypeorga
                            AND (o2.gere_agai = 'AGAI' OR EXISTS (SELECT 1 FROM audit ay WHERE ay.idorga = o2.idorga))
                        ) AS nb_aud
                 FROM type_organisme t
                 JOIN organisme o ON o.typeorga = t.idtypeorga AND $perimOrg
                 WHERE TRIM(t.nomtypeorg) <> ''
                 GROUP BY t.idtypeorga
                 ORDER BY nb_org DESC, t.nomtypeorg"
            )->fetchAll();

            // Types non utilises : aucun operateur AGAI de ce type
            $nonUtilises = $db->query(
                "SELECT t.idtypeorga, t.nomtypeorg, t.datesaizi
                 FROM type_organisme t
                 WHERE NOT EXISTS (
                    SELECT 1 FROM organisme o
                     WHERE o.typeorga = t.idtypeorga
                       AND (o.gere_agai = 'AGAI' OR EXISTS (SELECT 1 FROM audit ax WHERE ax.idorga = o.idorga))
                 )
                 ORDER BY t.nomtypeorg"
            )->fetchAll();

            // Audits associes du perimetre AGAI, regroupes par type d'operateur.
            // On part des AUDITS (pas des types) pour inclure les operateurs sans
            // type valide sous un libelle "Sans type" unique. On groupe sur
            // l'EXPRESSION complete (pas l'alias) pour que tous les cas sans type
            // (libelle vide id 66 + typeorga orphelins sans ligne type_organisme)
            // fusionnent en une seule ligne, et que le total colle au KPI.
            $audits = $db->query(
                "SELECT COALESCE(NULLIF(TRIM(t.nomtypeorg), ''), 'Sans type') AS nomtypeorg,
                        COUNT(DISTINCT a.idaudit) AS nb_aud
                 FROM audit a
                 JOIN organisme o ON o.idorga = a.idorga AND $perimOrg
                 LEFT JOIN type_organisme t ON t.idtypeorga = o.typeorga
                 GROUP BY COALESCE(NULLIF(TRIM(t.nomtypeorg), ''), 'Sans type')
                 ORDER BY nb_aud DESC, nomtypeorg"
            )->fetchAll();

            // Habilitations liees : inspecteurs habilites qui interviennent sur des
            // audits d'operateurs AGAI, regroupes par type d'operateur (Sans type
            // fusionne via l'expression de regroupement).
            $habs = $db->query(
                "SELECT COALESCE(NULLIF(TRIM(t.nomtypeorg), ''), 'Sans type') AS nomtypeorg,
                        COUNT(DISTINCT h.idhabilitation) AS nb_hab,
                        COUNT(DISTINCT h.idinspecteur)   AS nb_insp
                 FROM audit a
                 JOIN organisme o     ON o.idorga = a.idorga AND $perimOrg
                 LEFT JOIN type_organisme t ON t.idtypeorga = o.typeorga
                 JOIN audit_equipe ae ON ae.idaudit = a.idaudit
                 JOIN habilitation h  ON h.idinspecteur = ae.idinspecteur AND h.iddomaine = ae.iddomaine
                 GROUP BY COALESCE(NULLIF(TRIM(t.nomtypeorg), ''), 'Sans type')
                 ORDER BY nb_hab DESC, nomtypeorg"
            )->fetchAll();

            $ok([
                'utilises'     => $utilises,
                'non_utilises' => $nonUtilises,
                'audits'       => $audits,
                'habilitations'=> $habs,
            ]);
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
            // Operateurs AGAI de ce type uniquement (gere_agai='AGAI' ou audites)
            $stOrg = $db->prepare(
                "SELECT o.idorga, o.nomorga, o.trigrorganisme, o.statutorga, o.ville_org
                 FROM organisme o
                 WHERE o.typeorga = ?
                   AND (o.gere_agai = 'AGAI' OR EXISTS (SELECT 1 FROM audit ax WHERE ax.idorga = o.idorga))
                 ORDER BY o.idorga DESC"
            );
            $stOrg->execute([$id]);
            $orgs = $stOrg->fetchAll();
            // Audits associes via les operateurs AGAI de ce type (ORDER DESC)
            $stAud = $db->prepare(
                "SELECT a.num_audit, a.type_activite, a.statut, a.date_previsionnelle, o.nomorga
                 FROM audit a
                 JOIN organisme o ON o.idorga = a.idorga
                 WHERE o.typeorga = ?
                   AND (o.gere_agai = 'AGAI' OR EXISTS (SELECT 1 FROM audit ay WHERE ay.idorga = o.idorga))
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