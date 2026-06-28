<?php
/**
 * Endpoint AJAX : Non-Conformites (FNC)
 * Route : /api/nonconformites
 * Actions : audits_eligibles, habilitations_insp, sousdomaines, reglements_audit,
 *           create, list, get, update, delete, stats
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
// Guard : accessible si ouverture_nc OU suivi_nc est accorde
if (!Rbac::canAccess('ouverture_nc') && !Rbac::canAccess('suivi_nc')) {
    echo json_encode(['success'=>false,'message'=>'Acces refuse.']); exit;
}

if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success'=>false,'message'=>'CSRF invalide.']); exit;
}

$db          = Database::getInstance();
$role        = Rbac::role();
$uid         = (int)($_SESSION['user_id'] ?? 0);
$sessionRole = $_SESSION['user']['role'] ?? '';
$action      = trim((string)($_POST['action'] ?? ''));
$ok   = function($x=[]) { echo json_encode(['success'=>true]+$x); };
$fail = function($m)     { echo json_encode(['success'=>false,'message'=>$m]); };

// Inspecteur connecte
$myInsp = null;
$stI = $db->prepare("SELECT idinspecteur FROM inspecteur WHERE iduser = ? LIMIT 1");
$stI->execute([$uid]); $ri = $stI->fetch();
if ($ri) $myInsp = (int)$ri['idinspecteur'];

$isCI   = in_array($sessionRole, ['admin','chef_inspecteur'], true);
$isInsp = ($sessionRole === 'inspecteur');

try { switch ($action) {

// ----------------------------------------------------------------
// Audits eligibles a l'ouverture de NC (NCNS >= 1 et pas encore toutes fiches crees)
// ----------------------------------------------------------------
case 'audits_eligibles':
    $where = "WHERE a.ncns >= 1";
    $params = [];
    // Inspecteur : uniquement ses audits
    if ($isInsp && $myInsp !== null) {
        $where .= " AND (a.idresponsable_audit=? OR a.idaudit IN (SELECT ae.idaudit FROM audit_equipe ae WHERE ae.idinspecteur=?))";
        $params = [$myInsp, $myInsp];
    }
    $rows = $db->execute(
        "SELECT a.idaudit, a.num_audit, a.type_activite, a.cadre,
                a.date_previsionnelle, a.date_delivrance_rapport,
                o.nomorga, s.indicateur_oaci, s.ville,
                a.ncns,
                (SELECT COUNT(*) FROM fiche_non_conformite f WHERE f.idaudit = a.idaudit) AS nb_fnc_crees,
                (a.ncns - (SELECT COUNT(*) FROM fiche_non_conformite f WHERE f.idaudit = a.idaudit)) AS reste_a_creer
         FROM audit a
         LEFT JOIN organisme o ON o.idorga = a.idorga
         LEFT JOIN site      s ON s.idsite  = a.idsite
         $where
         HAVING reste_a_creer > 0
         ORDER BY a.date_previsionnelle DESC",
        $params
    )->fetchAll();
    $ok(['data' => $rows]);
    break;

// ----------------------------------------------------------------
// Habilitations de l'inspecteur connecte (domaines + sous-domaines)
// ----------------------------------------------------------------
case 'habilitations_insp':
    $idInsp = (int)($_POST['idinspecteur'] ?? ($myInsp ?? 0));
    if (!$idInsp) { $fail('Inspecteur non identifie.'); break; }
    // Domaines habilites
    $domaines = $db->execute(
        "SELECT DISTINCT d.iddomaine, d.nomdomaine, d.libel_domaine
         FROM habilitation h
         JOIN domaine d ON d.iddomaine = h.iddomaine
         WHERE h.idinspecteur = ? ORDER BY d.nomdomaine",
        [$idInsp]
    )->fetchAll();
    // Sous-domaines de ces domaines
    $idsDomainesArr = array_column($domaines, 'iddomaine');
    $sousdomaines = [];
    if ($idsDomainesArr) {
        $in = implode(',', array_fill(0, count($idsDomainesArr), '?'));
        $sousdomaines = $db->execute(
            "SELECT sd.idsousdomaine, sd.nom_sousdomaine, sd.iddomaine, d.nomdomaine
             FROM sous_domaine sd JOIN domaine d ON d.iddomaine = sd.iddomaine
             WHERE sd.iddomaine IN ($in) ORDER BY d.nomdomaine, sd.nom_sousdomaine",
            $idsDomainesArr
        )->fetchAll();
    }
    $ok(['domaines' => $domaines, 'sousdomaines' => $sousdomaines]);
    break;

// ----------------------------------------------------------------
// Reglements associes a un audit (depuis audit_reglement)
// ----------------------------------------------------------------
case 'reglements_audit':
    $idaudit = (int)($_POST['idaudit'] ?? 0);
    $rows = $db->execute(
        "SELECT r.idreglement, r.code_reglement, r.libelle_reglement, r.description
         FROM audit_reglement ar
         JOIN reglement r ON r.idreglement = ar.idreglement
         WHERE ar.idaudit = ?
         ORDER BY r.code_reglement",
        [$idaudit]
    )->fetchAll();
    // Si aucun reglement dans audit_reglement, retourner tous les reglements du domaine
    if (!$rows) {
        $rows = $db->execute(
            "SELECT r.idreglement, r.code_reglement, r.libelle_reglement, r.description
             FROM reglement r ORDER BY r.code_reglement LIMIT 50"
        )->fetchAll();
    }
    $ok(['data' => $rows]);
    break;

// ----------------------------------------------------------------
// Prochain numero FNC auto-incremente
// ----------------------------------------------------------------
case 'next_num_fnc':
    $idaudit  = (int)($_POST['idaudit'] ?? 0);
    $iddomaine= (int)($_POST['iddomaine'] ?? 0);
    // Indicateur OACI du site de l'audit
    $stSite = $db->prepare(
        "SELECT s.indicateur_oaci, YEAR(a.date_previsionnelle) AS annee
         FROM audit a LEFT JOIN site s ON s.idsite=a.idsite
         WHERE a.idaudit=? LIMIT 1"
    );
    $stSite->execute([$idaudit]); $site = $stSite->fetch();
    $oaci  = $site['indicateur_oaci'] ?? 'XXXX';
    $annee = $site['annee'] ?? date('Y');
    // Abrev domaine
    $stDom = $db->prepare("SELECT nomdomaine FROM domaine WHERE iddomaine=? LIMIT 1");
    $stDom->execute([$iddomaine]); $dom = $stDom->fetch();
    $abrevDom = strtoupper($dom['nomdomaine'] ?? 'DOM');
    // Compteur global pour cette annee
    $stCount = $db->prepare("SELECT COUNT(*) FROM fiche_non_conformite WHERE YEAR(date_emission)=?");
    $stCount->execute([$annee]);
    $nb = (int)$stCount->fetchColumn() + 1;
    $numFnc = str_pad($nb, 3, '0', STR_PAD_LEFT).'/'.$oaci.'/'.$abrevDom.'/'.$annee;
    $ok(['num_fnc' => $numFnc, 'oaci' => $oaci, 'domaine' => $abrevDom, 'annee' => $annee]);
    break;

// ----------------------------------------------------------------
// Creer un sous-domaine depuis la FNC (inspecteur habilite)
// ----------------------------------------------------------------
case 'create_sousdomaine':
    $nom    = trim(strip_tags($_POST['nom_sousdomaine'] ?? ''));
    $idDom  = (int)($_POST['iddomaine'] ?? 0);
    if (!$nom)   { $fail('Nom du sous-domaine obligatoire.'); break; }
    if (!$idDom) { $fail('Domaine obligatoire.'); break; }
    // Verifier que le domaine existe
    $stD = $db->prepare("SELECT iddomaine FROM domaine WHERE iddomaine=? LIMIT 1");
    $stD->execute([$idDom]); if (!$stD->fetch()) { $fail('Domaine introuvable.'); break; }
    // Verifier doublon
    $stChk = $db->prepare("SELECT idsousdomaine FROM sous_domaine WHERE iddomaine=? AND LOWER(nom_sousdomaine)=LOWER(?) LIMIT 1");
    $stChk->execute([$idDom, $nom]); $exist = $stChk->fetch();
    if ($exist) { $ok(['idsousdomaine' => $exist['idsousdomaine'], 'message' => 'Sous-domaine deja existant - ajoute a la selection.']); break; }
    // Inserer
    $db->prepare("INSERT INTO sous_domaine (iddomaine, nom_sousdomaine) VALUES (?,?)")->execute([$idDom, $nom]);
    $newId = (int)$db->lastInsertId();
    Audit::log('create','nonconformites',"Nouveau sous-domaine cree: $nom (domaine #$idDom)");
    $ok(['idsousdomaine' => $newId, 'nom_sousdomaine' => $nom, 'iddomaine' => $idDom]);
    break;
    if (!$myInsp && !$isCI) { $fail('Seul un inspecteur peut creer une FNC.'); break; }
    $idaudit   = (int)($_POST['idaudit']   ?? 0);
    $iddomaine = (int)($_POST['iddomaine'] ?? 0);
    $categorie = Security::cleanInput($_POST['categorie'] ?? '');
    $num_fnc   = Security::cleanInput($_POST['num_fnc']   ?? '');
    if (!$idaudit || !$iddomaine || !$categorie || !$num_fnc) {
        $fail('Champs obligatoires manquants (audit, domaine, categorie, num_fnc).'); break;
    }
    // Verifier que le num_fnc n'existe pas deja
    $stChk = $db->prepare("SELECT COUNT(*) FROM fiche_non_conformite WHERE num_fnc=?");
    $stChk->execute([$num_fnc]);
    if ((int)$stChk->fetchColumn() > 0) { $fail('Ce numero FNC existe deja.'); break; }
    // Verifier quota NCNS
    $stAudit = $db->prepare("SELECT ncns, ncr, date_delivrance_rapport, idorga FROM audit WHERE idaudit=?");
    $stAudit->execute([$idaudit]); $aud = $stAudit->fetch();
    if (!$aud) { $fail('Audit introuvable.'); break; }
    $stNbFnc = $db->prepare("SELECT COUNT(*) FROM fiche_non_conformite WHERE idaudit=?");
    $stNbFnc->execute([$idaudit]); $nbExist = (int)$stNbFnc->fetchColumn();
    if ($nbExist >= (int)($aud['ncns'] ?? 0)) {
        $fail('Quota atteint : toutes les '.$aud['ncns'].' fiches NC ont ete crees pour cet audit.'); break;
    }
    // Dates automatiques selon categorie
    $dateEmission = Security::cleanInput($_POST['date_emission'] ?? date('Y-m-d'));
    $dateRep      = null; $dateLimite = null;
    $dateRapport  = $aud['date_delivrance_rapport'] ?? null;
    switch ($categorie) {
        case 'critique':
            $dateRep    = $dateEmission;
            $dateLimite = $dateEmission;
            break;
        case 'majeur':
            $dateRep    = $dateRapport ? date('Y-m-d', strtotime($dateRapport.' +30 days'))  : null;
            $dateLimite = $dateRapport ? date('Y-m-d', strtotime($dateRapport.' +3 months')) : null;
            break;
        case 'mineur':
            $dateRep    = $dateRapport ? date('Y-m-d', strtotime($dateRapport.' +30 days'))  : null;
            $dateLimite = $dateRapport ? date('Y-m-d', strtotime($dateRapport.' +6 months')) : null;
            break;
        case 'observation':
            $dateRep = null; $dateLimite = null;
            break;
    }
    // Delai transmission
    $datePrev  = $db->execute("SELECT date_previsionnelle, date_delivrance_rapport FROM audit WHERE idaudit=?",[$idaudit])->fetch();
    $delaiTrans = null;
    if ($datePrev['date_previsionnelle'] && $datePrev['date_delivrance_rapport']) {
        $d1 = new DateTime($datePrev['date_previsionnelle']);
        $d2 = new DateTime($datePrev['date_delivrance_rapport']);
        $delaiTrans = (int)$d1->diff($d2)->days;
    }
    $inspCreateur = $myInsp ?? (int)($_POST['idinspecteur_createur'] ?? 0);
    // Nettoyage texte brut : trim + strip_tags (pas htmlspecialchars qui encode &#039;)
    $clean = function($v){ return trim(strip_tags((string)$v)); };
    $db->prepare(
        "INSERT INTO fiche_non_conformite
         (num_fnc, idaudit, idinspecteur_createur, idorga, iddomaine,
          representant_operateur, titre_representant, manuel, autres,
          source_audit, date_emission, date_transmission_rapport, delais_transmission,
          libelle, description_constatation, etat, categorie, ref_reglement, ref_reglement_iem,
          date_reponse_exigee, date_limite_mise_conformite,
          agent_suivi, statut,
          analyse_causes, actions_correctives, observation,
          pac_pertinent, pac_exhaustif, pac_detaille, pac_specifique, pac_realiste, pac_coherent,
          pac_acceptation, verification_meo, nom_visa_date)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
    )->execute([
        $num_fnc, $idaudit, $inspCreateur, (int)$aud['idorga'], $iddomaine,
        $clean($_POST['representant_operateur'] ?? ''),
        $clean($_POST['titre_representant'] ?? ''),
        $clean($_POST['manuel'] ?? ''),
        $clean($_POST['autres'] ?? ''),
        $clean($_POST['source_audit'] ?? ''),
        $dateEmission, $dateRapport, $delaiTrans,
        $clean($_POST['libelle'] ?? ''),
        $clean($_POST['description_constatation'] ?? ''),
        Security::cleanInput($_POST['etat'] ?? ''),
        $categorie,
        $clean($_POST['ref_reglement'] ?? ''),
        $clean($_POST['ref_reglement_iem'] ?? ''),
        $dateRep, $dateLimite,
        $inspCreateur, 4,
        $clean($_POST['analyse_causes'] ?? ''),
        $clean($_POST['actions_correctives'] ?? ''),
        $clean($_POST['observation'] ?? ''),
        Security::cleanInput($_POST['pac_pertinent']  ?? ''),
        Security::cleanInput($_POST['pac_exhaustif']  ?? ''),
        Security::cleanInput($_POST['pac_detaille']   ?? ''),
        Security::cleanInput($_POST['pac_specifique'] ?? ''),
        Security::cleanInput($_POST['pac_realiste']   ?? ''),
        Security::cleanInput($_POST['pac_coherent']   ?? ''),
        Security::cleanInput($_POST['pac_acceptation'] ?? ''),
        (int)($_POST['verification_meo'] ?? 0),
        $clean($_POST['nom_visa_date'] ?? ''),
    ]);
    $idfnc = (int)$db->lastInsertId();
    // Sous-domaines
    $sdsRaw = $_POST['sousdomaines'] ?? [];
    $sds = is_array($sdsRaw) ? $sdsRaw : explode(',', $sdsRaw);
    foreach ($sds as $sd) {
        $sd = (int)trim($sd);
        if ($sd > 0) {
            $db->prepare("INSERT INTO fnc_sousdomaine (idfnc,idsousdomaine) VALUES (?,?)")->execute([$idfnc,$sd]);
        }
    }
    // Reglements
    $regsRaw = $_POST['reglements'] ?? [];
    $regs = is_array($regsRaw) ? $regsRaw : explode(',', $regsRaw);
    foreach ($regs as $rg) {
        $rg = (int)trim($rg);
        if ($rg > 0) {
            $db->prepare("INSERT INTO fnc_reglement (idfnc,idreglement) VALUES (?,?)")->execute([$idfnc,$rg]);
        }
    }
    Audit::log('create','nonconformites',"Creation FNC $num_fnc pour audit #$idaudit");
    $ok(['idfnc' => $idfnc, 'num_fnc' => $num_fnc, 'message' => "FNC $num_fnc creee avec succes."]);
    break;

// ----------------------------------------------------------------
// Liste des FNC
// ----------------------------------------------------------------
case 'list':
    $where  = 'WHERE 1=1'; $params = [];
    // Filtre par role :
    // - Admin et CI : voient TOUTES les FNC
    // - Inspecteur : voit ses FNC + celles des collegues sur les memes audits
    if ($isInsp && $myInsp !== null) {
        // Audits auxquels l'inspecteur participe (comme responsable ou membre d'equipe)
        $stAudits = $db->prepare(
            "SELECT DISTINCT a.idaudit FROM audit a
             LEFT JOIN audit_equipe ae ON ae.idaudit = a.idaudit
             WHERE a.idresponsable_audit = ? OR ae.idinspecteur = ?"
        );
        $stAudits->execute([$myInsp, $myInsp]);
        $myAuditIds = $stAudits->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($myAuditIds)) {
            $in = implode(',', array_fill(0, count($myAuditIds), '?'));
            // Voir ses propres FNC + toutes les FNC des audits auxquels il participe
            $where .= " AND (f.idinspecteur_createur=? OR f.idaudit IN ($in))";
            $params = array_merge([$myInsp], $myAuditIds);
        } else {
            // Aucun audit : voit uniquement ses propres FNC
            $where .= " AND f.idinspecteur_createur=?";
            $params = [$myInsp];
        }
    }
    // Filtres utilisateur
    $fAudit  = (int)($_POST['f_audit']   ?? 0);
    $fStatut = trim((string)($_POST['f_statut'] ?? ''));
    $fCat    = trim((string)($_POST['f_categorie'] ?? ''));
    if ($fAudit)  { $where .= ' AND f.idaudit=?';    $params[] = $fAudit; }
    if ($fStatut) { $where .= ' AND f.statut=?';     $params[] = (int)$fStatut; }
    if ($fCat)    { $where .= ' AND f.categorie=?';  $params[] = $fCat; }

    $rows = $db->execute(
        "SELECT f.*,
                a.num_audit, a.type_activite, a.cadre, a.date_previsionnelle, a.date_delivrance_rapport,
                a.site_inspection,
                o.nomorga, s.indicateur_oaci, s.ville,
                d.nomdomaine, d.libel_domaine,
                TRIM(CONCAT(COALESCE(i.preninspect,''),' ',COALESCE(i.nominspecteur,''))) AS nom_inspecteur,
                GROUP_CONCAT(DISTINCT sd.nom_sousdomaine ORDER BY sd.nom_sousdomaine SEPARATOR ', ') AS sousdomaines_noms,
                GROUP_CONCAT(DISTINCT r.code_reglement   ORDER BY r.code_reglement   SEPARATOR ', ') AS reglements_codes
         FROM fiche_non_conformite f
         LEFT JOIN audit       a  ON a.idaudit       = f.idaudit
         LEFT JOIN organisme   o  ON o.idorga        = f.idorga
         LEFT JOIN site        s  ON s.idsite         = a.idsite
         LEFT JOIN domaine     d  ON d.iddomaine      = f.iddomaine
         LEFT JOIN inspecteur  i  ON i.idinspecteur   = f.idinspecteur_createur
         LEFT JOIN fnc_sousdomaine fsd ON fsd.idfnc   = f.idfnc
         LEFT JOIN sous_domaine sd ON sd.idsousdomaine = fsd.idsousdomaine
         LEFT JOIN fnc_reglement   frg ON frg.idfnc    = f.idfnc
         LEFT JOIN reglement       r   ON r.idreglement = frg.idreglement
         $where
         GROUP BY f.idfnc
         ORDER BY f.created_at DESC",
        $params
    )->fetchAll();
    $ok(['data' => $rows]);
    break;

// ----------------------------------------------------------------
// Creer un sous-domaine (depuis le formulaire FNC, accessible a l'inspecteur)
// ----------------------------------------------------------------
case 'create_sousdomaine':
    $nom   = trim(strip_tags($_POST['nom_sousdomaine'] ?? ''));
    $domId = (int)($_POST['iddomaine'] ?? 0);
    if ($nom === '' || mb_strlen($nom) > 255) { $fail('Nom du sous-domaine requis (255 car. max).'); break; }
    if ($domId <= 0) { $fail('Domaine parent requis.'); break; }
    // Verifier que le domaine existe
    $stD = $db->prepare("SELECT iddomaine FROM domaine WHERE iddomaine=?");
    $stD->execute([$domId]);
    if (!$stD->fetch()) { $fail('Domaine inconnu.'); break; }
    // Eviter les doublons
    $stDup = $db->prepare("SELECT idsousdomaine FROM sous_domaine WHERE iddomaine=? AND LOWER(nom_sousdomaine)=LOWER(?)");
    $stDup->execute([$domId, $nom]);
    $dup = $stDup->fetch();
    if ($dup) {
        // Retourner l'existant plutot qu'une erreur
        $ok(['idsousdomaine' => (int)$dup['idsousdomaine'], 'message' => 'Sous-domaine deja existant, recupere.']);
        break;
    }
    $db->prepare("INSERT INTO sous_domaine (iddomaine, nom_sousdomaine) VALUES (?,?)")->execute([$domId, $nom]);
    $newSdId = (int)$db->lastInsertId();
    Audit::log('create','nonconformites',"Nouveau sous-domaine #$newSdId ($nom) via FNC");
    $ok(['idsousdomaine' => $newSdId, 'message' => 'Sous-domaine cree.']);
    break;
case 'get':
    $idfnc = (int)($_POST['idfnc'] ?? 0);
    $row = $db->execute(
        "SELECT f.*,
                a.num_audit, a.type_activite, a.cadre, a.date_previsionnelle,
                a.date_delivrance_rapport, a.site_inspection,
                o.nomorga,
                s.indicateur_oaci, s.ville,
                d.nomdomaine, d.libel_domaine,
                TRIM(CONCAT(COALESCE(i.preninspect,''),' ',COALESCE(i.nominspecteur,''))) AS nom_inspecteur
         FROM fiche_non_conformite f
         LEFT JOIN audit       a ON a.idaudit       = f.idaudit
         LEFT JOIN organisme   o ON o.idorga        = f.idorga
         LEFT JOIN site        s ON s.idsite         = a.idsite
         LEFT JOIN domaine     d ON d.iddomaine      = f.iddomaine
         LEFT JOIN inspecteur  i ON i.idinspecteur   = f.idinspecteur_createur
         WHERE f.idfnc = ? LIMIT 1",
        [$idfnc]
    )->fetch();
    if (!$row) { $fail('FNC introuvable.'); break; }
    // Sous-domaines
    $sds = $db->execute(
        "SELECT sd.idsousdomaine, sd.nom_sousdomaine FROM fnc_sousdomaine fsd
         JOIN sous_domaine sd ON sd.idsousdomaine=fsd.idsousdomaine WHERE fsd.idfnc=?",[$idfnc]
    )->fetchAll();
    // Reglements
    $regs = $db->execute(
        "SELECT r.idreglement, r.code_reglement, r.libelle_reglement FROM fnc_reglement frg
         JOIN reglement r ON r.idreglement=frg.idreglement WHERE frg.idfnc=?",[$idfnc]
    )->fetchAll();
    $ok(['data' => $row, 'sousdomaines' => $sds, 'reglements' => $regs]);
    break;

// ----------------------------------------------------------------
// Mettre a jour le statut / suivi FNC
// ----------------------------------------------------------------
case 'update_suivi':
    $idfnc  = (int)($_POST['idfnc'] ?? 0);
    if (!$idfnc) { $fail('FNC manquante.'); break; }
    if (!$isCI)  { $fail('Action reservee au CI et Admin.'); break; }
    $fields = ['statut','date_effective_cloture','efficacite_mise_conformite',
               'statut_delais_efficacite','preuve_suivi','observations_courriers','relance'];
    $sets = []; $vals = [];
    foreach ($fields as $f) {
        if (isset($_POST[$f])) {
            $sets[] = "`$f`=?";
            $vals[] = Security::cleanInput((string)$_POST[$f]);
        }
    }
    if (!$sets) { $fail('Aucune donnee a mettre a jour.'); break; }
    $vals[] = $idfnc;
    $db->prepare("UPDATE fiche_non_conformite SET ".implode(',',$sets).",updated_at=NOW() WHERE idfnc=?")
       ->execute($vals);
    Audit::log('update','nonconformites',"Maj suivi FNC #$idfnc");
    $ok(['message' => 'FNC mise a jour.']);
    break;

// ----------------------------------------------------------------
// Supprimer une FNC (admin/CI seulement)
// ----------------------------------------------------------------
case 'delete':
    $idfnc = (int)($_POST['idfnc'] ?? 0);
    if (!$isCI) { $fail('Action reservee au CI et Admin.'); break; }
    $stF = $db->prepare("SELECT num_fnc FROM fiche_non_conformite WHERE idfnc=?");
    $stF->execute([$idfnc]); $fnc = $stF->fetch();
    if (!$fnc) { $fail('FNC introuvable.'); break; }
    $db->prepare("DELETE FROM fiche_non_conformite WHERE idfnc=?")->execute([$idfnc]);
    Audit::log('delete','nonconformites',"Suppression FNC ".$fnc['num_fnc']);
    $ok(['message' => 'FNC supprimee.']);
    break;

// ----------------------------------------------------------------
// Stats pour tableau de bord suivi
// ----------------------------------------------------------------
case 'stats':
    $where = 'WHERE 1=1'; $params = [];
    if ($isInsp && $myInsp !== null) {
        $where .= " AND (f.idinspecteur_createur=? OR f.agent_suivi=?)";
        $params = [$myInsp, $myInsp];
    }
    $s = $db->execute(
        "SELECT
            COUNT(*) AS total,
            SUM(f.statut=4) AS ouvertes,
            SUM(f.statut=1) AS acceptees_non_verif,
            SUM(f.statut=2) AS rejetees,
            SUM(f.statut=3) AS fermees,
            SUM(f.categorie='critique') AS critiques,
            SUM(f.categorie='majeur')   AS majeures,
            SUM(f.categorie='mineur')   AS mineures,
            SUM(f.categorie='observation') AS observations,
            SUM(f.date_reponse_exigee < CURDATE() AND f.statut IN (1,4)) AS en_retard
         FROM fiche_non_conformite f $where",
        $params
    )->fetch();
    $ok($s ?: []);
    break;

default: $fail('Action inconnue.');
}} catch (Throwable $e) {
    error_log('nonconformites: '.$e->getMessage());
    $fail('Erreur technique : '.$e->getMessage());
}