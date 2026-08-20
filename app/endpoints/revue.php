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

/* Mode adopte par l'equipe pour cet audit : 'pdf', 'texte' ou '' (rien encore).
   Sert a verrouiller le choix : tous les inspecteurs suivent le meme mode. */
function mode_audit($db, int $idaudit): string
{
    $st = $db->prepare("SELECT COUNT(*) FROM revue_documentaire WHERE idaudit=? AND fichier_joint IS NOT NULL");
    $st->execute([$idaudit]);
    if ((int) $st->fetchColumn() > 0) { return 'pdf'; }
    $st = $db->prepare("SELECT COUNT(*) FROM revue_documentaire WHERE idaudit=? AND contexte_objectif IS NOT NULL AND TRIM(contexte_objectif) <> ''");
    $st->execute([$idaudit]);
    if ((int) $st->fetchColumn() > 0) { return 'texte'; }
    return '';
}

/* Le RA a-t-il deja traite sa revue (saisie ou PDF) ? Si oui, la revue de
   l'audit est terminee : plus aucun inspecteur ne peut saisir ou joindre. */
function ra_a_traite($db, int $idaudit): bool
{
    $st = $db->prepare(
        "SELECT COUNT(*) FROM revue_documentaire rd
          JOIN audit a ON a.idaudit = rd.idaudit
         WHERE rd.idaudit = ?
           AND rd.idinspecteur = a.idresponsable_audit
           AND (rd.contexte_objectif IS NOT NULL OR rd.fichier_joint IS NOT NULL)"
    );
    $st->execute([$idaudit]);
    return (int) $st->fetchColumn() > 0;
}

/* Verifie qu'un inspecteur (non RA) peut encore ecrire dans le mode demande.
   Retourne un message d'erreur, ou '' si l'ecriture est autorisee. */
function verifier_mode($db, int $idaudit, int $idinsp, string $modeDemande): string
{
    // Le RA a le dernier mot : s'il a traite, plus personne n'ecrit.
    $stRa = $db->prepare("SELECT idresponsable_audit FROM audit WHERE idaudit=?");
    $stRa->execute([$idaudit]);
    $raId = (int) $stRa->fetchColumn();
    $estRA = ($raId === $idinsp);

    if (!$estRA && ra_a_traite($db, $idaudit)) {
        return 'La revue de cet audit est terminee : le Responsable d\'Audit l\'a deja traitee.';
    }
    // Mode verrouille pour tout l'audit (par les ecritures des AUTRES inspecteurs).
    $st = $db->prepare(
        "SELECT
            (SELECT COUNT(*) FROM revue_documentaire WHERE idaudit=? AND idinspecteur<>? AND fichier_joint IS NOT NULL) AS nb_pdf,
            (SELECT COUNT(*) FROM revue_documentaire WHERE idaudit=? AND idinspecteur<>? AND contexte_objectif IS NOT NULL AND TRIM(contexte_objectif)<>'') AS nb_txt"
    );
    $st->execute([$idaudit, $idinsp, $idaudit, $idinsp]);
    $r = $st->fetch();
    $modeEquipe = ((int)($r['nb_pdf'] ?? 0) > 0) ? 'pdf' : (((int)($r['nb_txt'] ?? 0) > 0) ? 'texte' : '');
    if ($modeEquipe !== '' && $modeDemande !== $modeEquipe) {
        return ($modeEquipe === 'pdf')
            ? 'L\'equipe a adopte le mode PDF joint : vous devez joindre un PDF, pas saisir.'
            : 'L\'equipe a adopte le mode saisie : vous devez saisir les rubriques, pas joindre un PDF.';
    }
    return '';
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
        // Liste des PDF de revue joints pour un audit (par inspecteur), pour
        // permettre a chaque membre de consulter ce qui a deja ete depose.
        case 'pdfs_audit':
            $idaudit = (int) ($_POST['idaudit'] ?? 0);
            if (!can_access_audit($db, $idaudit, $uid, $role, $myInsp)) { $fail('Acces refuse.'); break; }
            $st = $db->prepare(
                "SELECT rd.idinspecteur, rd.fichier_joint, rd.est_consolide,
                        TRIM(CONCAT(COALESCE(i.preninspect,''),' ',COALESCE(i.nominspecteur,''))) AS nom,
                        i.trigr_inspecteur,
                        (rd.idinspecteur = a.idresponsable_audit) AS est_ra
                   FROM revue_documentaire rd
                   JOIN audit a ON a.idaudit = rd.idaudit
                   LEFT JOIN inspecteur i ON i.idinspecteur = rd.idinspecteur
                  WHERE rd.idaudit = ? AND rd.fichier_joint IS NOT NULL
                  ORDER BY est_ra DESC, nom"
            );
            $st->execute([$idaudit]);
            $ok(['pdfs' => $st->fetchAll()]);
            break;

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
                             WHERE rd3.idaudit = a.idaudit AND rd3.fichier_joint IS NOT NULL LIMIT 1) AS pdf_idinspecteur,
                            CASE
                              WHEN (SELECT COUNT(*) FROM revue_documentaire rdm
                                    WHERE rdm.idaudit = a.idaudit AND rdm.fichier_joint IS NOT NULL) > 0
                                   THEN 'pdf'
                              WHEN (SELECT COUNT(*) FROM revue_documentaire rdt
                                    WHERE rdt.idaudit = a.idaudit AND rdt.contexte_objectif IS NOT NULL
                                      AND TRIM(rdt.contexte_objectif) <> '') > 0
                                   THEN 'texte'
                              ELSE ''
                            END AS mode_audit,
                            (SELECT COUNT(*) FROM revue_documentaire rra
                              WHERE rra.idaudit = a.idaudit
                                AND rra.idinspecteur = a.idresponsable_audit
                                AND (rra.contexte_objectif IS NOT NULL OR rra.fichier_joint IS NOT NULL)) AS ra_a_traite,
                            a.idresponsable_audit AS ra_id,
                            -- Numero de la revue documentaire consolidee du RA : idrevue/annee/IX-GEN
                            (SELECT CONCAT(rdn.idrevue,'/',
                                     COALESCE(NULLIF(YEAR(rdn.date_consolidation),0), YEAR(CURDATE())),'/IX-GEN')
                               FROM revue_documentaire rdn
                              WHERE rdn.idaudit = a.idaudit
                                AND rdn.idinspecteur = a.idresponsable_audit
                                AND rdn.est_consolide = 1
                              LIMIT 1) AS num_revue,
                            -- Liste des membres de l'equipe (noms)
                            (SELECT GROUP_CONCAT(DISTINCT TRIM(CONCAT(COALESCE(im.preninspect,''),' ',COALESCE(im.nominspecteur,''))) SEPARATOR ', ')
                               FROM audit_equipe aem
                               JOIN inspecteur im ON im.idinspecteur = aem.idinspecteur
                              WHERE aem.idaudit = a.idaudit) AS membres
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
                            END AS pdf_idinspecteur,
                            -- Mode deja adopte par l'equipe : 'texte' si au moins une revue
                            -- a ete saisie, 'pdf' si au moins une a ete jointe, sinon ''.
                            -- Sert a verrouiller le choix pour tout l'audit.
                            CASE
                              WHEN (SELECT COUNT(*) FROM revue_documentaire rdm
                                    WHERE rdm.idaudit = a.idaudit AND rdm.fichier_joint IS NOT NULL) > 0
                                   THEN 'pdf'
                              WHEN (SELECT COUNT(*) FROM revue_documentaire rdt
                                    WHERE rdt.idaudit = a.idaudit AND rdt.contexte_objectif IS NOT NULL
                                      AND TRIM(rdt.contexte_objectif) <> '') > 0
                                   THEN 'texte'
                              ELSE ''
                            END AS mode_audit,
                            -- Le RA a-t-il traite sa revue (saisie ou PDF) ? Si oui, c'est fini.
                            (SELECT COUNT(*) FROM revue_documentaire rra
                              WHERE rra.idaudit = a.idaudit
                                AND rra.idinspecteur = a.idresponsable_audit
                                AND (rra.contexte_objectif IS NOT NULL OR rra.fichier_joint IS NOT NULL)) AS ra_a_traite,
                            a.idresponsable_audit AS ra_id,
                            (SELECT CONCAT(rdn.idrevue,'/',
                                     COALESCE(NULLIF(YEAR(rdn.date_consolidation),0), YEAR(CURDATE())),'/IX-GEN')
                               FROM revue_documentaire rdn
                              WHERE rdn.idaudit = a.idaudit
                                AND rdn.idinspecteur = a.idresponsable_audit
                                AND rdn.est_consolide = 1
                              LIMIT 1) AS num_revue,
                            (SELECT GROUP_CONCAT(DISTINCT TRIM(CONCAT(COALESCE(im.preninspect,''),' ',COALESCE(im.nominspecteur,''))) SEPARATOR ', ')
                               FROM audit_equipe aem
                               JOIN inspecteur im ON im.idinspecteur = aem.idinspecteur
                              WHERE aem.idaudit = a.idaudit) AS membres
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

            // Une revue documentaire engage son auteur : SEUL l'inspecteur concerne
            // peut la rediger ou la modifier. Le responsable d'audit et le chef
            // inspecteur y accedent en consultation. L'identifiant transmis par le
            // client est ignore : on retient toujours celui de la session.
            if ($myInsp === null) {
                $fail('Seul un inspecteur peut rediger une revue documentaire.'); break;
            }
            $idinsp = $myInsp;

            if (!can_access_audit($db, $idaudit, $uid, $role, $myInsp)) { $fail('Acces refuse.'); break; }
            if ($idaudit <= 0 || $idinsp <= 0) { $fail('Parametres invalides.'); break; }

            // Une revue consolidee est definitive : plus aucune ecriture n'est admise.
            $stC = $db->prepare("SELECT est_consolide FROM revue_documentaire WHERE idaudit=? AND idinspecteur=? LIMIT 1");
            $stC->execute([$idaudit, $idinsp]);
            $ligneC = $stC->fetch();
            if ($ligneC && (int)$ligneC['est_consolide'] === 1) {
                $fail('Cette revue est consolidee : elle ne peut plus etre modifiee.'); break;
            }

            // Verrouillage du mode (OWASP - controle serveur). La saisie texte n'est
            // permise que si l'equipe est en mode texte (ou n'a encore rien fait) et
            // que le RA n'a pas deja cloture la revue.
            $errMode = verifier_mode($db, $idaudit, $idinsp, 'texte');
            if ($errMode !== '') { $fail($errMode); break; }

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
            // Seul l'auteur retire le document de sa propre revue.
            if ($myInsp === null) { $fail('Compte non lie a un inspecteur.'); break; }
            $idinsp = $myInsp;
            $stCD = $db->prepare("SELECT est_consolide FROM revue_documentaire WHERE idaudit=? AND idinspecteur=? LIMIT 1");
            $stCD->execute([$idaudit, $idinsp]);
            $ligCD = $stCD->fetch();
            if ($ligCD && (int)$ligCD['est_consolide'] === 1) {
                $fail('Cette revue est consolidee : le document ne peut plus etre retire.'); break;
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
            // Meme regle que la saisie : seul l'auteur joint le document de sa revue.
            if ($myInsp === null) { $fail('Seul un inspecteur peut joindre une revue documentaire.'); break; }
            $idinsp = $myInsp;
            if (!can_access_audit($db, $idaudit, $uid, $role, $myInsp)) { $fail('Acces refuse.'); break; }
            $stCU = $db->prepare("SELECT est_consolide FROM revue_documentaire WHERE idaudit=? AND idinspecteur=? LIMIT 1");
            $stCU->execute([$idaudit, $idinsp]);
            $ligCU = $stCU->fetch();
            if ($ligCU && (int)$ligCU['est_consolide'] === 1) {
                $fail('Cette revue est consolidee : le document ne peut plus etre remplace.'); break;
            }

            // Verrouillage du mode (OWASP - controle serveur). Joindre un PDF n'est
            // permis que si l'equipe est en mode PDF (ou n'a rien fait) et que le RA
            // n'a pas deja cloture la revue.
            $errModeU = verifier_mode($db, $idaudit, $idinsp, 'pdf');
            if ($errModeU !== '') { $fail($errModeU); break; }
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