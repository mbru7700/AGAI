<?php
/**
 * Endpoint AJAX : Archivage documentaire des actes de supervision
 * Route : /api/archivage
 *
 * Rassemble, pour chaque acte, les huit pieces attendues :
 *   1. Fiche de mandat            (generee a la demande, toujours disponible)
 *   2. Revue documentaire         (storage/revues)
 *   3. Lettre de notification     (storage/notifications)
 *   4. Rapport d'acte             (storage/rapports)
 *   5. Listes de verification     (storage/rapports)
 *   6. Formulaire QRE             (donnees en base)
 *   7. Fiches de non-conformite   (storage/uploads/fnc)
 *   8. Preuves et autres pieces   (storage/uploads/fnc)
 *
 * Actions : list, detail, serve
 *
 * Securite :
 *   - Rbac::guardApi('archivage') et CSRF sur les actions de lecture de donnees.
 *   - 'serve' est exempte de CSRF (lecture seule, ouverture en iframe) mais
 *     conserve session, habilitation et controle d'acces a l'acte.
 *   - Aucun chemin fourni par le client : le nom du fichier provient de la base
 *     et passe par basename(), le repertoire est choisi par une liste blanche.
 *   - Toutes les requetes sont preparees.
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }

Rbac::guardApi('archivage');

$actionBrute = (string)($_POST['action'] ?? $_GET['action'] ?? '');
if ($actionBrute !== 'serve' && !Security::validateCSRF($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'CSRF invalide.']); exit;
}

$db     = Database::getInstance();
$role   = Rbac::role();
$uid    = (int)($_SESSION['user_id'] ?? 0);
$isCI   = in_array($role, ['admin', 'chef_inspecteur', 'consultant'], true);
$action = trim($actionBrute);

$ok   = function ($x = []) { echo json_encode(['success' => true] + $x, JSON_UNESCAPED_UNICODE); };
$fail = function ($m)       { echo json_encode(['success' => false, 'message' => $m], JSON_UNESCAPED_UNICODE); };

/* Inspecteur rattache au compte connecte */
$myInsp = null;
$stI = $db->prepare("SELECT idinspecteur FROM inspecteur WHERE iduser = ? LIMIT 1");
$stI->execute([$uid]);
if ($r = $stI->fetch()) { $myInsp = (int)$r['idinspecteur']; }

/* Restriction de visibilite : un inspecteur ne voit que ses actes */
function arch_where(bool $isCI, ?int $myInsp): array
{
    if ($isCI) { return ['', []]; }
    if ($myInsp === null) { return [' AND 1=0', []]; }
    return [
        " AND (a.idresponsable_audit = ? OR a.idaudit IN (SELECT ae.idaudit FROM audit_equipe ae WHERE ae.idinspecteur = ?))",
        [$myInsp, $myInsp],
    ];
}

/* Repertoires autorises, choisis par liste blanche (jamais par le client) */
function arch_dir(string $type): ?string
{
    $map = [
        'revue'        => STORAGE_PATH . '/revues',
        'notification' => STORAGE_PATH . '/notifications',
        'rapport'      => STORAGE_PATH . '/rapports',
        'checklist'    => STORAGE_PATH . '/rapports',
        'fnc'          => STORAGE_PATH . '/uploads/fnc',
        'preuve'       => STORAGE_PATH . '/uploads/fnc',
        'autres'       => STORAGE_PATH . '/uploads/fnc',
    ];
    return $map[$type] ?? null;
}

try { switch ($action) {

// ----------------------------------------------------------------
// LISTE : un acte par ligne, avec l'etat de chaque piece
// ----------------------------------------------------------------
case 'list':
    [$wh, $pr] = arch_where($isCI, $myInsp);

    $rows = $db->execute(
        "SELECT a.idaudit, a.num_audit, a.type_activite, a.cadre, a.statut,
                a.date_previsionnelle, a.date_realisation, a.date_delivrance_rapport,
                a.site_inspection, a.lettre_notification, a.rapport_audit, a.checklist_signee,
                a.type_activite_operateur, a.est_ferme, a.ncns,
                YEAR(COALESCE(a.date_realisation, a.date_previsionnelle)) AS annee,
                o.nomorga, o.trigrorganisme,
                s.indicateur_oaci, s.nomsite, s.ville,
                TRIM(CONCAT(COALESCE(r.preninspect,''),' ',COALESCE(r.nominspecteur,''))) AS responsable,
                (SELECT COUNT(*) FROM audit_equipe ae WHERE ae.idaudit = a.idaudit)          AS nb_equipe,
                (SELECT COUNT(*) FROM revue_documentaire rd WHERE rd.idaudit = a.idaudit)    AS nb_revues,
                (SELECT COUNT(*) FROM revue_documentaire rd WHERE rd.idaudit = a.idaudit
                    AND (rd.fichier_joint IS NOT NULL AND rd.fichier_joint <> ''))           AS nb_revues_pdf,
                (SELECT COUNT(*) FROM qre q WHERE q.idaudit = a.idaudit)                     AS nb_qre,
                (SELECT COUNT(*) FROM fiche_non_conformite f WHERE f.idaudit = a.idaudit)    AS nb_fnc,
                (SELECT COUNT(*) FROM fiche_non_conformite f WHERE f.idaudit = a.idaudit
                    AND f.fichier_fnc IS NOT NULL AND f.fichier_fnc <> '')                   AS nb_fnc_signees,
                (SELECT COUNT(*) FROM fiche_non_conformite f WHERE f.idaudit = a.idaudit
                    AND ((f.preuve_suivi IS NOT NULL AND f.preuve_suivi <> '')
                      OR (f.autres_documents IS NOT NULL AND f.autres_documents <> '')))     AS nb_fnc_annexes,
                a.idresponsable_audit AS ra_id,
                -- Le RA a-t-il traite sa revue (saisie ou PDF joint) ?
                (SELECT COUNT(*) FROM revue_documentaire rra
                  WHERE rra.idaudit = a.idaudit
                    AND rra.idinspecteur = a.idresponsable_audit
                    AND (rra.contexte_objectif IS NOT NULL OR rra.fichier_joint IS NOT NULL)) AS ra_a_traite,
                -- Rapport saisi en ligne : donnees de synthese renseignees dans l'audit
                (CASE WHEN (a.ncr IS NOT NULL AND a.ncr > 0)
                          OR (a.date_delivrance_rapport IS NOT NULL AND a.date_delivrance_rapport <> '0000-00-00')
                        THEN 1 ELSE 0 END) AS rapport_saisi
         FROM audit a
         LEFT JOIN organisme  o ON o.idorga       = a.idorga
         LEFT JOIN site       s ON s.idsite       = a.idsite
         LEFT JOIN inspecteur r ON r.idinspecteur = a.idresponsable_audit
         WHERE 1=1 $wh
         ORDER BY
            CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(a.num_audit, '/', 2), '/', -1) AS UNSIGNED) DESC,
            CAST(SUBSTRING_INDEX(a.num_audit, '/', 1) AS UNSIGNED) DESC,
            a.idaudit DESC",
        $pr
    )->fetchAll();

    $ok(['data' => $rows]);
    break;

// ----------------------------------------------------------------
// DETAIL : acte, equipe et inventaire documentaire
// ----------------------------------------------------------------
case 'detail':
    $idaudit = (int)($_POST['idaudit'] ?? 0);
    if ($idaudit <= 0) { $fail('Acte introuvable.'); break; }

    [$wh, $pr] = arch_where($isCI, $myInsp);
    $params = array_merge([$idaudit], $pr);
    $a = $db->execute(
        "SELECT a.*, YEAR(COALESCE(a.date_realisation, a.date_previsionnelle)) AS annee,
                o.nomorga, o.trigrorganisme, o.emailorga, o.telorga,
                s.indicateur_oaci, s.nomsite, s.ville,
                TRIM(CONCAT(COALESCE(r.preninspect,''),' ',COALESCE(r.nominspecteur,''))) AS responsable,
                r.mailinspect AS mail_responsable, r.trigr_inspecteur AS trig_responsable,
                a.idresponsable_audit AS ra_id,
                (SELECT COUNT(*) FROM revue_documentaire rra
                  WHERE rra.idaudit = a.idaudit
                    AND rra.idinspecteur = a.idresponsable_audit
                    AND (rra.contexte_objectif IS NOT NULL OR rra.fichier_joint IS NOT NULL)) AS ra_a_traite,
                (CASE WHEN (a.ncr IS NOT NULL AND a.ncr > 0)
                          OR (a.date_delivrance_rapport IS NOT NULL AND a.date_delivrance_rapport <> '0000-00-00')
                        THEN 1 ELSE 0 END) AS rapport_saisi
         FROM audit a
         LEFT JOIN organisme  o ON o.idorga       = a.idorga
         LEFT JOIN site       s ON s.idsite       = a.idsite
         LEFT JOIN inspecteur r ON r.idinspecteur = a.idresponsable_audit
         WHERE a.idaudit = ? $wh LIMIT 1",
        $params
    )->fetch();
    if (!$a) { $fail('Acte introuvable ou acces refuse.'); break; }

    /* Equipe d'inspection */
    $equipe = $db->execute(
        "SELECT DISTINCT ae.idinspecteur,
                TRIM(CONCAT(COALESCE(i.preninspect,''),' ',COALESCE(i.nominspecteur,''))) AS nom,
                i.trigr_inspecteur, i.mailinspect, i.categorie,
                d.nomdomaine,
                (SELECT COUNT(*) FROM revue_documentaire rd
                  WHERE rd.idaudit = ae.idaudit AND rd.idinspecteur = ae.idinspecteur) AS a_revue
         FROM audit_equipe ae
         LEFT JOIN inspecteur i ON i.idinspecteur = ae.idinspecteur
         LEFT JOIN domaine    d ON d.iddomaine    = ae.iddomaine
         WHERE ae.idaudit = ?
         ORDER BY nom", [$idaudit]
    )->fetchAll();

    /* Revues documentaires */
    $revues = $db->execute(
        "SELECT rd.idrevue, rd.idinspecteur, rd.fichier_joint,
                rd.est_consolide, rd.created_at,
                TRIM(CONCAT(COALESCE(i.preninspect,''),' ',COALESCE(i.nominspecteur,''))) AS nom
         FROM revue_documentaire rd
         LEFT JOIN inspecteur i ON i.idinspecteur = rd.idinspecteur
         WHERE rd.idaudit = ? ORDER BY nom", [$idaudit]
    )->fetchAll();

    /* Formulaires QRE */
    $qres = $db->execute(
        "SELECT q.idqre, q.date_qre, q.activites_auditees, q.fichier_joint, o.nomorga
         FROM qre q LEFT JOIN organisme o ON o.idorga = q.idorga
         WHERE q.idaudit = ? ORDER BY q.date_qre DESC", [$idaudit]
    )->fetchAll();

    /* Fiches de non-conformite et leurs pieces */
    $fncs = $db->execute(
        "SELECT f.idfnc, f.num_fnc, f.categorie, f.statut, f.date_emission,
                f.fichier_fnc, f.preuve_suivi, f.autres_documents,
                d.nomdomaine
         FROM fiche_non_conformite f
         LEFT JOIN domaine d ON d.iddomaine = f.iddomaine
         WHERE f.idaudit = ? ORDER BY f.idfnc", [$idaudit]
    )->fetchAll();

    /* Reglements vises par l'acte (pour la fiche de mandat) */
    $reglements = $db->execute(
        "SELECT DISTINCT r.idreglement, r.code_reglement, r.libelle_reglement
         FROM audit_reglement ar
         JOIN reglement r ON r.idreglement = ar.idreglement
         WHERE ar.idaudit = ?
         ORDER BY r.code_reglement", [$idaudit]
    )->fetchAll();

    /* Reglements par inspecteur : audit_reglement porte l'identifiant d'equipe */
    $regEquipe = $db->execute(
        "SELECT ae.idinspecteur, r.code_reglement
         FROM audit_reglement ar
         JOIN audit_equipe ae ON ae.idequipe = ar.idequipe
         JOIN reglement    r  ON r.idreglement = ar.idreglement
         WHERE ar.idaudit = ?", [$idaudit]
    )->fetchAll();

    $ok([
        'audit'      => $a,
        'reglements' => $reglements,
        'reg_equipe' => $regEquipe,
        'equipe'  => $equipe,
        'revues'  => $revues,
        'qre'     => $qres,
        'fnc'     => $fncs,
    ]);
    break;

// ----------------------------------------------------------------
// Operateurs ayant des audits (surveillance active) - lecture seule
case 'surveilles':
    $idorga = (int) ($_POST['idorga'] ?? 0);
    $where  = $idorga > 0 ? 'AND a.idorga = ?' : '';
    $params = $idorga > 0 ? [$idorga] : [];
    $stS = $db->execute(
        "SELECT a.idaudit, a.num_audit, a.type_activite, a.statut, a.date_previsionnelle, a.est_ferme,
                o.nomorga, o.trigrorganisme,
                TRIM(CONCAT(COALESCE(r.preninspect,''),' ',COALESCE(r.nominspecteur,''))) AS responsable
         FROM audit a
         LEFT JOIN organisme  o ON o.idorga      = a.idorga
         LEFT JOIN inspecteur r ON r.idinspecteur = a.idresponsable_audit
         WHERE a.est_ferme = 0 $where
         ORDER BY o.nomorga, a.date_previsionnelle DESC",
        $params
    );
    $ok(['data' => $stS->fetchAll()]);
    break;

// ----------------------------------------------------------------
// SERVE : lecture d'une piece (lecture seule, en fenetre modale)
// ----------------------------------------------------------------
case 'serve':
    $type    = (string)($_GET['type'] ?? $_POST['type'] ?? '');
    $idaudit = (int)($_GET['idaudit'] ?? $_POST['idaudit'] ?? 0);
    $idfnc   = (int)($_GET['idfnc']   ?? $_POST['idfnc']   ?? 0);
    $idrevue = (int)($_GET['idrevue'] ?? $_POST['idrevue'] ?? 0);

    $dir = arch_dir($type);
    if ($dir === null) { $fail('Type de document inconnu.'); break; }

    $fichier = null; $libelle = 'document';

    if ($type === 'notification' || $type === 'rapport' || $type === 'checklist') {
        $col = ['notification' => 'lettre_notification', 'rapport' => 'rapport_audit', 'checklist' => 'checklist_signee'][$type];
        [$wh, $pr] = arch_where($isCI, $myInsp);
        $st = $db->prepare("SELECT a.$col AS f, a.num_audit FROM audit a WHERE a.idaudit = ? $wh LIMIT 1");
        $st->execute(array_merge([$idaudit], $pr));
        $row = $st->fetch();
        if ($row) { $fichier = (string)$row['f']; $libelle = $type . '_' . (string)$row['num_audit']; }

    } elseif ($type === 'revue') {
        [$wh, $pr] = arch_where($isCI, $myInsp);
        $st = $db->prepare(
            "SELECT rd.fichier_joint AS f, a.num_audit
             FROM revue_documentaire rd
             JOIN audit a ON a.idaudit = rd.idaudit
             WHERE rd.idrevue = ? $wh LIMIT 1");
        $st->execute(array_merge([$idrevue], $pr));
        $row = $st->fetch();
        if ($row) { $fichier = (string)$row['f']; $libelle = 'revue_' . (string)$row['num_audit']; }

    } elseif ($type === 'fnc' || $type === 'preuve' || $type === 'autres') {
        $col = ['fnc' => 'fichier_fnc', 'preuve' => 'preuve_suivi', 'autres' => 'autres_documents'][$type];
        [$wh, $pr] = arch_where($isCI, $myInsp);
        $st = $db->prepare(
            "SELECT f.$col AS f, f.num_fnc
             FROM fiche_non_conformite f
             JOIN audit a ON a.idaudit = f.idaudit
             WHERE f.idfnc = ? $wh LIMIT 1");
        $st->execute(array_merge([$idfnc], $pr));
        $row = $st->fetch();
        if ($row) { $fichier = (string)$row['f']; $libelle = $type . '_' . (string)$row['num_fnc']; }
    }

    $fichier = trim((string)$fichier);
    if ($fichier === '') { $fail('Aucun document disponible.'); break; }

    $chemin = $dir . '/' . basename($fichier);
    if (!is_file($chemin)) { $fail('Fichier introuvable sur le serveur.'); break; }

    Audit::log('download', 'archivage', "Consultation piece [$type] acte #$idaudit");

    $ext  = strtolower(pathinfo($chemin, PATHINFO_EXTENSION));
    $mime = ['pdf' => 'application/pdf', 'doc' => 'application/msword',
             'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'][$ext] ?? 'application/octet-stream';

    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $libelle) . '.' . $ext . '"');
    header('Content-Length: ' . filesize($chemin));
    header('X-Content-Type-Options: nosniff');
    readfile($chemin);
    exit;

default:
    $fail('Action inconnue.');
}} catch (Throwable $e) {
    error_log('archivage: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
    $fail('Erreur technique. Operation non realisee.');
}