<?php
/**
 * Endpoint AJAX : Revue documentaire (table `revue_documentaire`).
 * Route : /api/revue
 *
 * Actions :
 *   mes_audits   - liste des audits visibles selon le role
 *   get_revue    - lire la revue d'un inspecteur pour un audit
 *   save_revue   - creer ou mettre a jour une revue
 *   get_audit    - detail d'un audit (pour l'en-tete du formulaire)
 *   list_revues  - toutes les revues d'un audit (RA et CI/admin)
 *   consolider   - marquer la revue comme consolidee (RA uniquement)
 *   upload_revue - joindre un PDF a une revue
 *
 * Visibilite :
 *   admin / chef_inspecteur : tous les audits
 *   inspecteur (RA)         : audits ou il est idresponsable_audit
 *   inspecteur              : audits ou il figure dans audit_equipe
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }

Rbac::guardApi('audits');

// Action serve : requete GET (pas de CSRF, auth session suffisante)
if (($_GET['serve'] ?? '') === '1') {
    $_POST['action'] = 'serve';
}

if (!Security::validateCSRF($_POST['csrf_token'] ?? '') && ($_POST['action'] ?? '') !== 'serve') {
    echo json_encode(['success' => false, 'message' => 'Jeton CSRF invalide. Rechargez la page.']);
    exit;
}

$db     = Database::getInstance();
$action = $_POST['action'] ?? '';
$uid    = (int) ($_SESSION['user_id'] ?? 0);
$role   = Rbac::role();

$ok   = function ($extra = []) { echo json_encode(['success' => true]  + $extra); };
$fail = function ($msg)        { echo json_encode(['success' => false, 'message' => $msg]); };

/* Resoudre l'idinspecteur de l'utilisateur connecte (null si pas inspecteur). */
function get_my_idinspecteur($db, int $uid): ?int
{
    $st = $db->prepare("SELECT idinspecteur FROM inspecteur WHERE iduser = ? LIMIT 1");
    $st->execute([$uid]);
    $r = $st->fetch();
    return $r ? (int) $r['idinspecteur'] : null;
}

/* Verifier qu'un utilisateur a acces a un audit donne. */
function can_access_audit($db, int $idaudit, int $uid, string $role, ?int $idinsp): bool
{
    if (in_array($role, ['admin', 'chef_inspecteur'], true)) { return true; }
    if ($idinsp === null) { return false; }
    // RA
    $st = $db->prepare("SELECT idaudit FROM audit WHERE idaudit = ? AND idresponsable_audit = ?");
    $st->execute([$idaudit, $idinsp]);
    if ($st->fetch()) { return true; }
    // Membre de l'equipe
    $st = $db->prepare("SELECT idequipe FROM audit_equipe WHERE idaudit = ? AND idinspecteur = ?");
    $st->execute([$idaudit, $idinsp]);
    return (bool) $st->fetch();
}

const STATUT_LABELS = [1=>'Planifiee',2=>'Reportee',3=>'Effectuee',4=>'Suspendue',5=>'A surveiller'];
const TYPE_LABELS   = [
    'audit'=>'Audit','inspection_programmee'=>'Inspection programmee',
    'inspection_non_programmee'=>'Inspection non programmee',
    'demonstration'=>'Demonstration','test'=>'Test','investigation'=>'Investigation',
];

try {
    $myInsp = get_my_idinspecteur($db, $uid);

    switch ($action) {

        // ----------------------------------------------------------------
        // Servir un PDF joint (auth-gated)
        case 'serve':
            // Cet endpoint est appele en GET via URL directe (pas AJAX POST)
            // On ne verifie pas le CSRF ici car c'est une requete GET
            $idaudit = (int) ($_GET['idaudit'] ?? 0);
            $idinsp  = (int) ($_GET['idinsp']  ?? 0);
            if (!can_access_audit($db, $idaudit, $uid, $role, $myInsp)) {
                http_response_code(403); echo 'Acces refuse.'; exit;
            }
            // Si idinsp=0 on prend le premier PDF disponible pour cet audit
            if ($idinsp > 0) {
                $stP = $db->prepare("SELECT fichier_joint FROM revue_documentaire WHERE idaudit = ? AND idinspecteur = ? LIMIT 1");
                $stP->execute([$idaudit, $idinsp]);
            } else {
                $stP = $db->prepare("SELECT fichier_joint FROM revue_documentaire WHERE idaudit = ? AND fichier_joint IS NOT NULL LIMIT 1");
                $stP->execute([$idaudit]);
            }
            $rowP = $stP->fetch();
            if (!$rowP || empty($rowP['fichier_joint'])) {
                http_response_code(404); echo 'Aucun PDF joint.'; exit;
            }
            $path = BASE_PATH . '/storage/revues/' . $rowP['fichier_joint'];
            if (!file_exists($path)) { http_response_code(404); echo 'Fichier introuvable.'; exit; }
            $dl = !empty($_GET['dl']);
            header('Content-Type: application/pdf');
            header('Content-Length: ' . filesize($path));
            header('Content-Disposition: ' . ($dl ? 'attachment' : 'inline') . '; filename="revue_' . $idaudit . '.pdf"');
            header('X-Content-Type-Options: nosniff');
            readfile($path);
            exit;

        // ----------------------------------------------------------------
        // Retourner l'idinspecteur de l'utilisateur connecte
        case 'my_insp':
            $st = $db->prepare("SELECT idinspecteur FROM inspecteur WHERE iduser = ? LIMIT 1");
            $st->execute([$uid]);
            $r = $st->fetch();
            if ($r) { $ok(['idinspecteur' => (int) $r['idinspecteur']]); }
            else     { $ok(['idinspecteur' => null]); }
            break;

        // ----------------------------------------------------------------
        // Liste des audits visibles par l'utilisateur connecte
        case 'mes_audits':
            // Sous-requete commune : nb_revues tient compte du RA
            // Si le RA a soumis sa revue -> nb_revues = nb_equipe (couvre tout le monde)
            // Sinon : compte des revues individuelles saisies
            $nb_revues_sql = "CASE
              WHEN (SELECT COUNT(*) FROM revue_documentaire rd_ra
                    WHERE rd_ra.idaudit = a.idaudit
                      AND rd_ra.idinspecteur = a.idresponsable_audit
                      AND (rd_ra.contexte_objectif IS NOT NULL OR rd_ra.fichier_joint IS NOT NULL)) > 0
              THEN (SELECT COUNT(DISTINCT ae3.idinspecteur) FROM audit_equipe ae3 WHERE ae3.idaudit = a.idaudit)
              ELSE (SELECT COUNT(*) FROM revue_documentaire rd_ind
                    WHERE rd_ind.idaudit = a.idaudit
                      AND (rd_ind.contexte_objectif IS NOT NULL OR rd_ind.fichier_joint IS NOT NULL))
            END";

            if (in_array($role, ['admin', 'chef_inspecteur', 'consultant'], true)) {
                $rows = $db->query(
                    "SELECT a.idaudit, a.num_audit, a.type_activite, a.cadre, a.statut,
                            a.date_previsionnelle,
                            o.nomorga AS operateur,
                            TRIM(CONCAT(COALESCE(ir.preninspect,''),' ',COALESCE(ir.nominspecteur,''))) AS ra_nom,
                            1 AS est_ra,
                            $nb_revues_sql AS nb_revues,
                            (SELECT COUNT(DISTINCT ae2.idinspecteur) FROM audit_equipe ae2 WHERE ae2.idaudit = a.idaudit) AS nb_equipe,
                            (SELECT rd2.fichier_joint FROM revue_documentaire rd2
                             WHERE rd2.idaudit = a.idaudit AND rd2.fichier_joint IS NOT NULL LIMIT 1) AS mon_pdf,
                            (SELECT rd3.idinspecteur FROM revue_documentaire rd3
                             WHERE rd3.idaudit = a.idaudit AND rd3.fichier_joint IS NOT NULL LIMIT 1) AS pdf_idinspecteur
                     FROM audit a
                     LEFT JOIN organisme  o  ON o.idorga       = a.idorga
                     LEFT JOIN inspecteur ir ON ir.idinspecteur = a.idresponsable_audit
                     ORDER BY a.idaudit DESC"
                )->fetchAll();
            } elseif ($myInsp !== null) {
                $rows = $db->query(
                    "SELECT DISTINCT a.idaudit, a.num_audit, a.type_activite, a.cadre, a.statut,
                            a.date_previsionnelle,
                            o.nomorga AS operateur,
                            TRIM(CONCAT(COALESCE(ir.preninspect,''),' ',COALESCE(ir.nominspecteur,''))) AS ra_nom,
                            (a.idresponsable_audit = " . (int) $myInsp . ") AS est_ra,
                            (SELECT COUNT(DISTINCT ae2.idinspecteur) FROM audit_equipe ae2 WHERE ae2.idaudit = a.idaudit) AS nb_equipe,
                            -- Si le RA a soumis : nb_revues = nb_equipe (sa revue couvre tous)
                            -- Sinon : nombre de revues individuelles saisies
                            CASE
                              WHEN (SELECT COUNT(*) FROM revue_documentaire rd_ra
                                    WHERE rd_ra.idaudit = a.idaudit
                                      AND rd_ra.idinspecteur = a.idresponsable_audit
                                      AND (rd_ra.contexte_objectif IS NOT NULL OR rd_ra.fichier_joint IS NOT NULL)) > 0
                              THEN (SELECT COUNT(DISTINCT ae3.idinspecteur) FROM audit_equipe ae3 WHERE ae3.idaudit = a.idaudit)
                              ELSE (SELECT COUNT(*) FROM revue_documentaire rd_ind
                                    WHERE rd_ind.idaudit = a.idaudit
                                      AND (rd_ind.contexte_objectif IS NOT NULL OR rd_ind.fichier_joint IS NOT NULL))
                            END AS nb_revues,
                            -- PDF a afficher : d'abord le PDF du RA s'il existe, sinon le propre PDF de l'inspecteur
                            COALESCE(
                              (SELECT rd_ra2.fichier_joint FROM revue_documentaire rd_ra2
                               WHERE rd_ra2.idaudit = a.idaudit
                                 AND rd_ra2.idinspecteur = a.idresponsable_audit
                                 AND rd_ra2.fichier_joint IS NOT NULL LIMIT 1),
                              (SELECT rd2.fichier_joint FROM revue_documentaire rd2
                               WHERE rd2.idaudit = a.idaudit
                                 AND rd2.idinspecteur = " . (int) $myInsp . "
                                 AND rd2.fichier_joint IS NOT NULL LIMIT 1)
                            ) AS mon_pdf,
                            -- Savoir si c'est le PDF du RA (pour l'afficher a tous)
                            CASE
                              WHEN (SELECT rd_ra3.fichier_joint FROM revue_documentaire rd_ra3
                                    WHERE rd_ra3.idaudit = a.idaudit
                                      AND rd_ra3.idinspecteur = a.idresponsable_audit
                                      AND rd_ra3.fichier_joint IS NOT NULL LIMIT 1) IS NOT NULL
                              THEN a.idresponsable_audit
                              ELSE " . (int) $myInsp . "
                            END AS pdf_idinspecteur
                     FROM audit a
                     LEFT JOIN organisme  o  ON o.idorga       = a.idorga
                     LEFT JOIN inspecteur ir ON ir.idinspecteur = a.idresponsable_audit
                     WHERE a.idaudit IN (
                         SELECT idaudit FROM audit_equipe WHERE idinspecteur = " . (int) $myInsp . "
                         UNION
                         SELECT idaudit FROM audit WHERE idresponsable_audit = " . (int) $myInsp . "
                     )
                     ORDER BY a.idaudit DESC"
                )->fetchAll();
            } else {
                $rows = [];
            }
            $ok(['data' => $rows]);
            break;

        // ----------------------------------------------------------------
        // Detail d'un audit (en-tete du formulaire de revue)
        case 'get_audit':
            $id = (int) ($_POST['idaudit'] ?? 0);
            if (!can_access_audit($db, $id, $uid, $role, $myInsp)) { $fail('Acces refuse.'); break; }
            $st = $db->prepare(
                "SELECT a.idaudit, a.num_audit, a.type_activite, a.cadre, a.statut,
                        a.date_previsionnelle, a.site_inspection,
                        o.nomorga AS operateur,
                        TRIM(CONCAT(COALESCE(ir.preninspect,''),' ',COALESCE(ir.nominspecteur,''))) AS ra_nom,
                        ir.idinspecteur AS ra_id
                 FROM audit a
                 LEFT JOIN organisme  o  ON o.idorga       = a.idorga
                 LEFT JOIN inspecteur ir ON ir.idinspecteur = a.idresponsable_audit
                 WHERE a.idaudit = ?"
            );
            $st->execute([$id]);
            $a = $st->fetch();
            if (!$a) { $fail('Audit introuvable.'); break; }
            // Equipe complete pour l'affichage
            $stEq = $db->prepare(
                "SELECT i.idinspecteur, TRIM(CONCAT(COALESCE(i.preninspect,''),' ',COALESCE(i.nominspecteur,''))) AS nom,
                        d.nomdomaine, e.est_responsable
                 FROM audit_equipe e
                 JOIN inspecteur i ON i.idinspecteur = e.idinspecteur
                 JOIN domaine d    ON d.iddomaine    = e.iddomaine
                 WHERE e.idaudit = ?
                 ORDER BY e.est_responsable DESC, i.nominspecteur"
            );
            $stEq->execute([$id]);
            $ok(['audit' => $a, 'equipe' => $stEq->fetchAll()]);
            break;

        // ----------------------------------------------------------------
        // Lire la revue d'un inspecteur pour un audit
        case 'get_revue':
            $id    = (int) ($_POST['idaudit']       ?? 0);
            $idinsp = (int) ($_POST['idinspecteur'] ?? ($myInsp ?? 0));
            if (!can_access_audit($db, $id, $uid, $role, $myInsp)) { $fail('Acces refuse.'); break; }
            $st = $db->prepare("SELECT * FROM revue_documentaire WHERE idaudit = ? AND idinspecteur = ? LIMIT 1");
            $st->execute([$id, $idinsp]);
            $r = $st->fetch();
            $ok(['revue' => $r ?: null]);
            break;

        // ----------------------------------------------------------------
        // Creer ou mettre a jour une revue (inspecteur ou RA)
        case 'save_revue':
            $idaudit = (int) ($_POST['idaudit'] ?? 0);

            // L'inspecteur ne peut ecrire que sur sa propre revue.
            // Le CI et l'admin peuvent ecrire sur n'importe quelle revue.
            if (in_array($role, ['admin', 'chef_inspecteur'], true)) {
                $idinsp = (int) ($_POST['idinspecteur'] ?? 0);
                if ($idinsp <= 0) { $fail('Inspecteur non specifie.'); break; }
            } else {
                // Inspecteur : on ignore l'idinspecteur du POST, on prend le sien
                if ($myInsp === null) { $fail('Votre compte n\'est pas lie a un inspecteur.'); break; }
                $idinsp = $myInsp;
            }

            if (!can_access_audit($db, $idaudit, $uid, $role, $myInsp)) { $fail('Acces refuse.'); break; }
            if ($idaudit <= 0 || $idinsp <= 0) { $fail('Parametres invalides.'); break; }

            $contexte  = trim((string) ($_POST['contexte_objectif']       ?? ''));
            $perimetre = trim((string) ($_POST['perimetre_activite']       ?? ''));
            $refs      = trim((string) ($_POST['references_reglementaires']?? ''));
            $criteres  = trim((string) ($_POST['criteres_audit']           ?? ''));
            $docs      = trim((string) ($_POST['liste_documentation']      ?? ''));
            $points    = trim((string) ($_POST['points_attention']         ?? ''));
            $conclusion= trim((string) ($_POST['conclusion']               ?? ''));

            // Verifier si une revue existe deja pour cet inspecteur + audit
            $st = $db->prepare("SELECT idrevue FROM revue_documentaire WHERE idaudit = ? AND idinspecteur = ?");
            $st->execute([$idaudit, $idinsp]);
            $existing = $st->fetch();

            if ($existing) {
                $st = $db->prepare(
                    "UPDATE revue_documentaire SET
                        contexte_objectif = ?, perimetre_activite = ?, references_reglementaires = ?,
                        criteres_audit = ?, liste_documentation = ?, points_attention = ?, conclusion = ?
                     WHERE idaudit = ? AND idinspecteur = ?"
                );
                $st->execute([$contexte, $perimetre, $refs, $criteres, $docs, $points, $conclusion, $idaudit, $idinsp]);
                $idrevue = (int) $existing['idrevue'];
                Audit::log('update', 'audits', 'Mise a jour revue doc audit #' . $idaudit . ' insp #' . $idinsp);
            } else {
                $st = $db->prepare(
                    "INSERT INTO revue_documentaire
                        (idaudit, idinspecteur, contexte_objectif, perimetre_activite,
                         references_reglementaires, criteres_audit, liste_documentation,
                         points_attention, conclusion)
                     VALUES (?,?,?,?,?,?,?,?,?)"
                );
                $st->execute([$idaudit, $idinsp, $contexte, $perimetre, $refs, $criteres, $docs, $points, $conclusion]);
                $idrevue = (int) $db->lastInsertId();
                Audit::log('create', 'audits', 'Creation revue doc audit #' . $idaudit . ' insp #' . $idinsp);
            }
            $ok(['message' => 'Revue documentaire enregistree.', 'idrevue' => $idrevue]);
            break;

        // ----------------------------------------------------------------
        // Toutes les revues d'un audit (RA / CI / admin)
        case 'list_revues':
            $idaudit = (int) ($_POST['idaudit'] ?? 0);
            if (!can_access_audit($db, $idaudit, $uid, $role, $myInsp)) { $fail('Acces refuse.'); break; }
            $st = $db->prepare(
                "SELECT rd.*,
                        TRIM(CONCAT(COALESCE(i.preninspect,''),' ',COALESCE(i.nominspecteur,''))) AS nom_inspecteur,
                        i.trigr_inspecteur
                 FROM revue_documentaire rd
                 JOIN inspecteur i ON i.idinspecteur = rd.idinspecteur
                 WHERE rd.idaudit = ?
                 ORDER BY rd.est_consolide DESC, rd.updated_at DESC"
            );
            $st->execute([$idaudit]);
            $ok(['data' => $st->fetchAll()]);
            break;

        // ----------------------------------------------------------------
        // Consolider la revue (RA uniquement)
        case 'consolider':
            $idaudit = (int) ($_POST['idaudit'] ?? 0);
            $idinsp  = (int) ($_POST['idinspecteur'] ?? 0);
            if (!can_access_audit($db, $idaudit, $uid, $role, $myInsp)) { $fail('Acces refuse.'); break; }
            // Seul le RA ou un admin / CI peut consolider
            $isRA = false;
            if (in_array($role, ['admin', 'chef_inspecteur'], true)) { $isRA = true; }
            elseif ($myInsp !== null) {
                $st = $db->prepare("SELECT idaudit FROM audit WHERE idaudit = ? AND idresponsable_audit = ?");
                $st->execute([$idaudit, $myInsp]);
                $isRA = (bool) $st->fetch();
            }
            if (!$isRA) { $fail('Seul le Responsable d\'Audit peut consolider la revue.'); break; }
            $st = $db->prepare(
                "UPDATE revue_documentaire SET est_consolide = 1, date_consolidation = NOW(), iduser_consolidation = ?
                 WHERE idaudit = ? AND idinspecteur = ?"
            );
            $st->execute([$uid, $idaudit, $idinsp]);
            Audit::log('update', 'audits', 'Consolidation revue doc audit #' . $idaudit . ' insp #' . $idinsp);
            $ok(['message' => 'Revue consolidee.']);
            break;

        // ----------------------------------------------------------------
        // Supprimer le PDF joint (pour revenir en mode saisie texte)
        case 'del_pdf':
            $idaudit = (int) ($_POST['idaudit'] ?? 0);
            if (in_array($role, ['admin', 'chef_inspecteur'], true)) {
                $idinsp = (int) ($_POST['idinspecteur'] ?? 0);
            } else {
                if ($myInsp === null) { $fail('Compte non lie a un inspecteur.'); break; }
                $idinsp = $myInsp;
                // Verifier que c'est sa propre revue ou qu'il est RA
                $stRA = $db->prepare("SELECT idaudit FROM audit WHERE idaudit = ? AND idresponsable_audit = ?");
                $stRA->execute([$idaudit, $myInsp]);
                if (!$stRA->fetch() && $idinsp !== $myInsp) { $fail('Acces refuse.'); break; }
            }
            $stF = $db->prepare("SELECT fichier_joint FROM revue_documentaire WHERE idaudit = ? AND idinspecteur = ?");
            $stF->execute([$idaudit, $idinsp]);
            $row = $stF->fetch();
            if ($row && $row['fichier_joint']) {
                $path = BASE_PATH . '/storage/revues/' . $row['fichier_joint'];
                if (file_exists($path)) { unlink($path); }
            }
            $db->prepare("UPDATE revue_documentaire SET fichier_joint = NULL WHERE idaudit = ? AND idinspecteur = ?")
               ->execute([$idaudit, $idinsp]);
            Audit::log('delete', 'audits', 'Suppression PDF revue audit #' . $idaudit . ' insp #' . $idinsp);
            $ok(['message' => 'PDF supprime.']);
            break;

        // ----------------------------------------------------------------
        // Upload PDF joint a une revue
        case 'upload_revue':
            $idaudit = (int) ($_POST['idaudit'] ?? 0);
            if (in_array($role, ['admin', 'chef_inspecteur'], true)) {
                $idinsp = (int) ($_POST['idinspecteur'] ?? 0);
                if ($idinsp <= 0) { $fail('Inspecteur non specifie.'); break; }
            } else {
                if ($myInsp === null) { $fail('Votre compte n\'est pas lie a un inspecteur.'); break; }
                $idinsp = $myInsp;
            }
            if (!can_access_audit($db, $idaudit, $uid, $role, $myInsp)) { $fail('Acces refuse.'); break; }
            if (!isset($_FILES['fichier']) || $_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
                $fail('Aucun fichier recu ou erreur d\'upload.'); break;
            }
            $allowed = ['application/pdf'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($_FILES['fichier']['tmp_name']);
            if (!in_array($mime, $allowed, true)) { $fail('Seuls les fichiers PDF sont acceptes.'); break; }
            if ($_FILES['fichier']['size'] > 10 * 1024 * 1024) { $fail('Fichier trop volumineux (10 Mo maximum).'); break; }

            $dir = BASE_PATH . '/storage/revues/';
            if (!is_dir($dir)) { mkdir($dir, 0755, true); }
            $ext  = 'pdf';
            $name = 'revue_' . $idaudit . '_' . $idinsp . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            if (!move_uploaded_file($_FILES['fichier']['tmp_name'], $dir . $name)) {
                $fail('Erreur lors de l\'enregistrement du fichier.'); break;
            }
            // Upsert : creer la ligne si elle n'existe pas encore avant d'y stocker le fichier
            $stCheck = $db->prepare("SELECT idrevue, fichier_joint FROM revue_documentaire WHERE idaudit = ? AND idinspecteur = ?");
            $stCheck->execute([$idaudit, $idinsp]);
            $existing = $stCheck->fetch();

            if (!$existing) {
                // Creer une ligne vide pour pouvoir stocker le PDF
                $db->prepare("INSERT INTO revue_documentaire (idaudit, idinspecteur) VALUES (?, ?)")
                   ->execute([$idaudit, $idinsp]);
            } elseif (!empty($existing['fichier_joint'])) {
                // Supprimer l'ancien fichier physique
                $oldPath = $dir . $existing['fichier_joint'];
                if (file_exists($oldPath)) { unlink($oldPath); }
            }

            $st = $db->prepare("UPDATE revue_documentaire SET fichier_joint = ? WHERE idaudit = ? AND idinspecteur = ?");
            $st->execute([$name, $idaudit, $idinsp]);
            Audit::log('upload', 'audits', 'PDF revue doc audit #' . $idaudit . ' insp #' . $idinsp);
            $ok(['message' => 'Fichier enregistre.', 'fichier' => $name]);
            break;

        // ----------------------------------------------------------------
        default:
            $fail('Action inconnue.');
    }
} catch (Throwable $e) {
    error_log('revue endpoint : ' . $e->getMessage());
    $fail('Erreur technique.');
}