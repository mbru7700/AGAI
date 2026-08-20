<?php
/**
 * Endpoint AJAX : Rapports d'actes de supervision
 * Route : /api/rapports
 * Actions : list, upload, serve, stats
 * Colonnes criteres : nce, ncs, ncns, ncne, ncna, ncr, taux_conformite, taux_non_conformite
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardApi('rapports');

$db   = Database::getInstance();
$role = Rbac::role();
$uid  = (int) ($_SESSION['user_id'] ?? 0);

$rapportDir = STORAGE_PATH . '/rapports';
if (!is_dir($rapportDir)) { @mkdir($rapportDir, 0755, true); }

// ---- Inspecteur connecte ----
$myInsp = null;
$stI = $db->prepare("SELECT idinspecteur FROM inspecteur WHERE iduser = ? LIMIT 1");
$stI->execute([$uid]); $ri = $stI->fetch();
if ($ri) $myInsp = (int) $ri['idinspecteur'];

// ---- Operateur connecte ----
$idorgaUser = null;
$stOrg = $db->prepare("SELECT idorga FROM users WHERE iduser = ? LIMIT 1");
$stOrg->execute([$uid]); $orgRow = $stOrg->fetch();
if ($orgRow && !empty($orgRow['idorga'])) $idorgaUser = (int) $orgRow['idorga'];

/* ---- Servir le fichier rapport (GET) ---- */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['serve'])) {
    $idaudit = (int) ($_GET['idaudit'] ?? 0);
    // Deux documents distincts : le rapport signe par le DG, et les listes de
    // verification signees par les inspecteurs (un seul PDF scanne).
    $quoi = ($_GET['doc'] ?? 'rapport') === 'checklist' ? 'checklist' : 'rapport';
    $colonne = $quoi === 'checklist' ? 'checklist_signee' : 'rapport_audit';
    $st = $db->prepare("SELECT $colonne AS fichier, idresponsable_audit FROM audit WHERE idaudit = ? AND $colonne IS NOT NULL");
    $st->execute([$idaudit]);
    $row = $st->fetch();
    if (!$row) { http_response_code(404); exit('Document indisponible.'); }
    /* ------------------------------------------------------------------
     * Controle d'acces au document (OWASP A01).
     *   - admin / chef inspecteur / consultant : acces a tous les rapports
     *   - operateur : uniquement les audits de SON organisme
     *   - inspecteur : uniquement les audits ou il intervient
     * Le role operateur etait auparavant exempte de tout controle : il pouvait
     * consulter les rapports de n'importe quel autre operateur.
     * ------------------------------------------------------------------ */
    $roleGlobal = in_array($role, ['admin','chef_inspecteur','consultant'], true);
    if (!$roleGlobal) {
        $autorise = false;

        if ($role === 'operateur' && $idorgaUser !== null) {
            $stO = $db->prepare("SELECT 1 FROM audit WHERE idaudit = ? AND idorga = ?");
            $stO->execute([$idaudit, $idorgaUser]);
            $autorise = (bool) $stO->fetch();

        } elseif ($myInsp !== null) {
            $stA = $db->prepare(
                "SELECT 1 FROM audit WHERE idaudit=? AND (idresponsable_audit=? OR idchef_inspecteur=?)
                 UNION SELECT 1 FROM audit_equipe WHERE idaudit=? AND idinspecteur=?");
            $stA->execute([$idaudit, $myInsp, $myInsp, $idaudit, $myInsp]);
            $autorise = (bool) $stA->fetch();
        }

        if (!$autorise) {
            Audit::log('access_denied','rapports',"Tentative de consultation du document [$quoi] audit #$idaudit");
            http_response_code(403);
            exit('Acces refuse.');
        }
    }
    $path = $rapportDir . '/' . basename((string) $row['fichier']);
    if (!file_exists($path)) { http_response_code(404); exit('Fichier absent.'); }
    $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mime = match($ext) {
        'pdf'  => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'doc'  => 'application/msword',
        default => 'application/octet-stream',
    };
    header('Content-Type: ' . $mime);
    header('Content-Disposition: ' . (isset($_GET['dl']) ? 'attachment' : 'inline') . '; filename="' . rawurlencode(basename($path)) . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, max-age=3600');
    readfile($path);
    exit;
}

if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success'=>false,'message'=>'CSRF invalide.']); exit;
}

$action = trim((string) ($_POST['action'] ?? ''));
$ok   = function($x=[]) { echo json_encode(['success'=>true]+$x); };
$fail = function($m)     { echo json_encode(['success'=>false,'message'=>$m]); };

// Construire le filtre par role
function buildWhere(string $role, ?int $myInsp, ?int $idorgaUser): array {
    $isCI   = in_array($role, ['admin','chef_inspecteur','consultant'], true);
    $isOper = ($role === 'operateur');
    if ($isCI) return ['','[]'];
    if ($isOper && $idorgaUser !== null)
        return ['AND a.idorga = ?', json_encode([$idorgaUser])];
    if ($myInsp !== null)
        return ["AND (a.idresponsable_audit=? OR a.idaudit IN (SELECT ae.idaudit FROM audit_equipe ae WHERE ae.idinspecteur=?))",
                json_encode([$myInsp, $myInsp])];
    return ['AND 1=0','[]'];
}

/* Un inspecteur ne peut saisir/consulter le rapport que d'un audit ou il est
   autorise : role global (CI/admin/consultant), ou responsable de l'audit,
   ou membre de l'equipe d'audit. Les operateurs n'ont pas acces en ecriture. */
function peutSaisirRapport(Database $db, string $role, ?int $myInsp, int $idaudit): bool {
    if (in_array($role, ['admin','chef_inspecteur','consultant'], true)) return true;
    if ($myInsp === null) return false;
    $st = $db->prepare(
        "SELECT 1 FROM audit WHERE idaudit=? AND idresponsable_audit=?
         UNION SELECT 1 FROM audit_equipe WHERE idaudit=? AND idinspecteur=? LIMIT 1"
    );
    $st->execute([$idaudit, $myInsp, $idaudit, $myInsp]);
    return (bool) $st->fetch();
}

try { switch ($action) {

// ----------------------------------------------------------------
case 'list':
    $isCI   = in_array($role, ['admin','chef_inspecteur','consultant'], true);
    $isOper = ($role === 'operateur');
    $where  = ''; $params = [];

    if ($isCI) {
        $where = ''; $params = [];
    } elseif ($isOper && $idorgaUser !== null) {
        $where = 'AND a.idorga = ?'; $params = [$idorgaUser];
    } elseif ($myInsp !== null) {
        $where = "AND (a.idresponsable_audit=? OR a.idaudit IN (SELECT ae.idaudit FROM audit_equipe ae WHERE ae.idinspecteur=?))";
        $params = [$myInsp, $myInsp];
    } else {
        $where = 'AND 1=0'; $params = [];
    }

    // Detection des colonnes du cycle de vie (migration peut ne pas etre appliquee)
    $cycleCols = '';
    try {
        $c1 = $db->execute("SHOW COLUMNS FROM audit LIKE 'rapport_methode'")->fetch();
        if ($c1) { $cycleCols = ', a.rapport_methode, a.rapport_approuve, a.rapport_approuve_par, a.rapport_date_approbation'; }
    } catch (\Throwable $e) { $cycleCols = ''; }

    $rows = $db->execute(
        "SELECT a.idaudit, a.num_audit, a.type_activite, a.cadre, a.statut,
                a.date_previsionnelle, a.date_realisation, a.date_delivrance_rapport,
                a.delai_execution, a.rapport_audit, a.checklist_signee, a.checklist_depot, a.lettre_notification,
                a.nce, a.ncs, a.ncns, a.ncne, a.ncna, a.ncr,
                a.taux_conformite, a.taux_non_conformite $cycleCols,
                o.nomorga,
                TRIM(CONCAT(COALESCE(ir.preninspect,''),' ',COALESCE(ir.nominspecteur,''))) AS ra_nom,
                (a.idresponsable_audit = COALESCE(0,0)) AS est_ra
         FROM audit a
         LEFT JOIN organisme  o  ON o.idorga       = a.idorga
         LEFT JOIN inspecteur ir ON ir.idinspecteur = a.idresponsable_audit
         WHERE a.lettre_notification IS NOT NULL
           AND TRIM(a.lettre_notification) <> '' $where
         ORDER BY a.idaudit DESC",
        $params
    )->fetchAll();

    // est_ra : verifier si l'utilisateur est le RA
    if ($myInsp !== null) {
        foreach ($rows as &$r) {
            $stRa = $db->prepare("SELECT 1 FROM audit WHERE idaudit=? AND idresponsable_audit=?");
            $stRa->execute([$r['idaudit'], $myInsp]);
            $r['est_ra'] = $stRa->fetch() ? 1 : 0;
        }
        unset($r);
    }
    $ok(['data' => $rows]);
    break;

// ----------------------------------------------------------------
case 'upload':
    $idaudit  = (int) ($_POST['idaudit'] ?? 0);
    $dateReal = trim((string) ($_POST['date_realisation'] ?? ''));
    // Criteres
    $nce  = max(0, (int) ($_POST['nce']  ?? 0));
    $ncs  = max(0, (int) ($_POST['ncs']  ?? 0));
    $ncns = max(0, (int) ($_POST['ncns'] ?? 0));
    $ncne = max(0, (int) ($_POST['ncne'] ?? 0));
    $ncna = max(0, (int) ($_POST['ncna'] ?? 0));
    $ncr  = $nce + $ncs + $ncns + $ncne + $ncna;
    // Taux
    $base = $ncs + $ncns;
    $tauxConf    = $base > 0 ? round($ncs  / $base * 100, 2) : null;
    $tauxNonConf = $base > 0 ? round($ncns / $base * 100, 2) : null;

    if ($idaudit <= 0) { $fail('Audit invalide.'); break; }

    // Droit : RA ou admin/CI
    $canUpload = in_array($role, ['admin','chef_inspecteur'], true);
    if (!$canUpload && $myInsp !== null) {
        $stRa = $db->prepare("SELECT 1 FROM audit WHERE idaudit=? AND idresponsable_audit=?");
        $stRa->execute([$idaudit, $myInsp]);
        $canUpload = (bool) $stRa->fetch();
    }
    if (!$canUpload) { $fail('Seul le RA, un CI ou un Admin peut joindre le rapport.'); break; }

    // Le rapport n'est obligatoire qu'au premier depot. Ensuite, on peut corriger
    // les criteres ou remplacer un seul des deux documents sans tout redeposer.
    $stDejaR = $db->prepare("SELECT rapport_audit FROM audit WHERE idaudit=?");
    $stDejaR->execute([$idaudit]);
    $rapportExistant = (string) ($stDejaR->fetchColumn() ?: '');
    $nouveauRapport  = (!empty($_FILES['fichier_rapport']) && (int)$_FILES['fichier_rapport']['error'] === UPLOAD_ERR_OK);

    if (!$nouveauRapport && $rapportExistant === '') {
        $fail('Le rapport d\'acte de supervision est obligatoire au premier depot.'); break;
    }

    $stored = $rapportExistant;   // conserve par defaut
    if ($nouveauRapport) {
    $f    = $_FILES['fichier_rapport'];
    $orig = basename($f['name']);
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf','doc','docx'], true)) { $fail('Format non autorise. Utilisez PDF, DOC ou DOCX.'); break; }
    // Verification MIME reel (finfo) - protection contre renommage malveillant
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeReal = $finfo->file($f['tmp_name']);
    $mimeOk   = ['application/pdf',
                  'application/msword',
                  'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    if (!in_array($mimeReal, $mimeOk, true)) {
        $fail('Type de fichier non autorise (MIME : ' . htmlspecialchars($mimeReal, ENT_QUOTES, 'UTF-8') . '). Seuls PDF, DOC et DOCX sont acceptes.'); break;
    }
    if (!is_uploaded_file($f['tmp_name'])) { $fail('Telechargement invalide.'); break; }

        // Le nouveau rapport remplace l'ancien, efface du disque
        if ($rapportExistant !== '') {
            $oldPath = $rapportDir . '/' . basename($rapportExistant);
            if (file_exists($oldPath)) { @unlink($oldPath); }
        }

        $stored = 'rapport_' . $idaudit . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest   = $rapportDir . '/' . $stored;
        if (!move_uploaded_file($f['tmp_name'], $dest)) { $fail("Echec de l'enregistrement."); break; }
    }   // fin du depot d'un nouveau rapport

    // Donnees de l'audit necessaires au calcul du delai
    $stAud = $db->prepare("SELECT date_previsionnelle, rapport_audit FROM audit WHERE idaudit=?");
    $stAud->execute([$idaudit]); $aud = $stAud->fetch();
    if (!$aud) { $fail('Audit introuvable.'); break; }

    /* ------------------------------------------------------------------
     * Listes de verification signees par les inspecteurs, scannees en un
     * seul document. Document signe : le PDF est le seul format admis.
     * Champ facultatif : le rapport peut etre depose seul.
     * ------------------------------------------------------------------ */
    $checklistStored = null;
    if (!empty($_FILES['fichier_checklist']) && (int)$_FILES['fichier_checklist']['error'] === UPLOAD_ERR_OK) {
        $fc = $_FILES['fichier_checklist'];
        if (!is_uploaded_file($fc['tmp_name'])) { $fail('Telechargement de la checklist invalide.'); break; }
        $extC = strtolower(pathinfo(basename($fc['name']), PATHINFO_EXTENSION));
        if ($extC !== 'pdf') {
            $fail('Les listes de verification doivent etre fournies en PDF : ce sont des documents signes.'); break;
        }
        $finfoC = new finfo(FILEINFO_MIME_TYPE);
        if ($finfoC->file($fc['tmp_name']) !== 'application/pdf') {
            $fail('Le fichier des listes de verification n\'est pas un PDF valide.'); break;
        }
        $checklistStored = 'checklist_' . $idaudit . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.pdf';
        if (!move_uploaded_file($fc['tmp_name'], $rapportDir . '/' . $checklistStored)) {
            $fail("Echec de l'enregistrement des listes de verification."); break;
        }
        // Le nouveau document remplace l'ancien, efface du disque
        $stChk = $db->prepare("SELECT checklist_signee FROM audit WHERE idaudit=?");
        $stChk->execute([$idaudit]);
        $ancienChk = (string)($stChk->fetchColumn() ?: '');
        if ($ancienChk !== '' && $ancienChk !== $checklistStored) {
            $oldC = $rapportDir . '/' . basename($ancienChk);
            if (is_file($oldC)) { @unlink($oldC); }
        }
    }

    $dateRealFinal = (!empty($dateReal) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateReal))
        ? $dateReal : date('Y-m-d');

    /* Delai d'execution, en jours ENTIERS et signes :
     *   valeur positive  = retard (realise apres la date prevue)
     *   valeur negative  = avance (realise avant la date prevue)
     *   zero             = realise a la date prevue
     * La colonne delai_execution est un entier : y ecrire une chaine du type
     * "1276 jours (avance)" la tronquait a 1276 et faisait perdre le sens. */
    $delai = null;
    if (!empty($aud['date_previsionnelle']) && $aud['date_previsionnelle'] !== '0000-00-00') {
        $d1 = new DateTime($aud['date_previsionnelle']);
        $d2 = new DateTime($dateRealFinal);
        $diff = $d1->diff($d2);
        $delai = (int) $diff->days * ($diff->invert ? -1 : 1);
    }

    // Detecter la colonne rapport_methode pour verrouiller le choix Saisir/Joindre
    $hasMethodeCol = false;
    try {
        $chkM = $db->execute("SHOW COLUMNS FROM audit LIKE 'rapport_methode'")->fetch();
        $hasMethodeCol = (bool)$chkM;
    } catch (\Throwable $e) { $hasMethodeCol = false; }
    $setMethode = $hasMethodeCol ? ", rapport_methode = COALESCE(rapport_methode,'joindre')" : '';

    $db->prepare(
        "UPDATE audit SET
            rapport_audit=?, statut=3,
            checklist_signee = COALESCE(?, checklist_signee),
            checklist_depot  = CASE WHEN ? IS NULL THEN checklist_depot ELSE NOW() END,
            date_realisation=?, date_delivrance_rapport=CURDATE(), delai_execution=?,
            nce=?, ncs=?, ncns=?, ncne=?, ncna=?, ncr=?,
            taux_conformite=?, taux_non_conformite=? $setMethode
         WHERE idaudit=?"
    )->execute([$stored, $checklistStored, $checklistStored, $dateRealFinal, $delai,
                $nce, $ncs, $ncns, $ncne, $ncna, $ncr,
                $tauxConf, $tauxNonConf, $idaudit]);

    Audit::log('upload','rapports',"Rapport audit #$idaudit - NCR:$ncr, Taux:$tauxConf%"
        . ($checklistStored ? ' (listes de verification jointes)' : ''));
    $ok(['message'=>'Rapport enregistre. Statut passe a Effectue.'
            . ($checklistStored ? ' Listes de verification signees jointes.' : ''),
         'fichier'=>$stored, 'checklist'=>$checklistStored,
         'delai'=>$delai,'ncr'=>$ncr,'taux_conformite'=>$tauxConf,'taux_non_conformite'=>$tauxNonConf]);
    break;

// ----------------------------------------------------------------
case 'stats':
    // Dashboard Power BI : stats globales + par operateur + par annee + par type
    $isCI   = in_array($role, ['admin','chef_inspecteur','consultant'], true);
    $isOper = ($role === 'operateur');
    $where  = ''; $params = [];

    // Ordre de priorite identique a l'action 'list' :
    // 1) CI / admin / consultant : tout
    // 2) OPERATEUR : uniquement ses propres audits
    // 3) INSPECTEUR : les audits dont il est RA ou membre d'equipe
    //    (un inspecteur peut avoir un idorga renseigne : il ne doit pas
    //     etre traite comme un operateur, sinon son tableau reste vide)
    if ($isCI) {
        $where = ''; $params = [];
    } elseif ($isOper && $idorgaUser !== null) {
        $where = 'AND a.idorga = ?'; $params = [$idorgaUser];
    } elseif ($myInsp !== null) {
        $where = "AND (a.idresponsable_audit=? OR a.idaudit IN (SELECT ae.idaudit FROM audit_equipe ae WHERE ae.idinspecteur=?))";
        $params = [$myInsp, $myInsp];
    } else {
        $ok(['stats'=>['total'=>0],'par_operateur'=>[],'par_annee'=>[],'par_type'=>[],'allR'=>[]]); break;
    }

    // Tous les rapports joints (avec OU sans criteres)
    // Compatibilite : la colonne rapport_methode peut ne pas exister si la
    // migration cycle de vie n'a pas encore ete appliquee. On la detecte.
    $hasMethode = false;
    try {
        $chkCol = $db->execute("SHOW COLUMNS FROM audit LIKE 'rapport_methode'")->fetch();
        $hasMethode = (bool)$chkCol;
    } catch (\Throwable $e) { $hasMethode = false; }
    $condMethode = $hasMethode ? "OR a.rapport_methode = 'saisie'" : '';

    // Un "rapport" compte pour les stats des lors que l'audit :
    //   - a un PDF joint (mode joindre), OU
    //   - a ete saisi en ligne (rapport_methode='saisie'), OU
    //   - possede des criteres renseignes (NCR>0), quel que soit le mode.
    $allRjoints = $db->execute(
        "SELECT a.idaudit, a.num_audit, a.type_activite, a.date_realisation,
                a.nce, a.ncs, a.ncns, a.ncne, a.ncna, a.ncr,
                a.taux_conformite, a.taux_non_conformite,
                o.nomorga, o.idorga
         FROM audit a
         LEFT JOIN organisme o ON o.idorga = a.idorga
         WHERE (
                 (a.rapport_audit IS NOT NULL AND TRIM(a.rapport_audit) <> '')
                 $condMethode
              OR COALESCE(a.ncr,0) > 0
               ) $where
         ORDER BY a.date_realisation DESC",
        $params
    )->fetchAll();

    $totalJoints = count($allRjoints);

    // Sous-ensemble avec criteres renseignes (NCR > 0) pour les calculs de taux
    $allR = array_values(array_filter($allRjoints, function($r) {
        return (int)($r['ncr'] ?? 0) > 0;
    }));

    $total = count($allR);
    if (!$totalJoints) { $ok(['stats'=>['total'=>0],'par_operateur'=>[],'par_annee'=>[],'par_type'=>[],'allR'=>[]]); break; }

    $sumTC=0; $sumTNC=0; $cnt=0; $sumNCR=0; $sumNCS=0; $sumNCNS=0;
    foreach ($allR as $r) {
        if ($r['taux_conformite'] !== null) { $sumTC += (float)$r['taux_conformite']; $cnt++; }
        $sumTNC += (float)($r['taux_non_conformite'] ?? 0);
        $sumNCR  += (int)$r['ncr']; $sumNCS += (int)$r['ncs']; $sumNCNS += (int)$r['ncns'];
    }
    $avgTC  = $cnt ? round($sumTC/$cnt,1) : 0;
    $avgTNC = $cnt ? round($sumTNC/$cnt,1) : 0;

    // Par operateur (taux moy, meilleur, pire)
    $byOrga = [];
    foreach ($allR as $r) {
        $k = $r['nomorga']??'Inconnu';
        if (!isset($byOrga[$k])) $byOrga[$k]=['nomorga'=>$k,'idorga'=>$r['idorga'],'nb'=>0,'sumTC'=>0,'sumNCS'=>0,'sumNCNS'=>0,'sumNCR'=>0];
        $byOrga[$k]['nb']++;
        $byOrga[$k]['sumTC']  += (float)($r['taux_conformite']??0);
        $byOrga[$k]['sumNCS'] += (int)$r['ncs'];
        $byOrga[$k]['sumNCNS']+= (int)$r['ncns'];
        $byOrga[$k]['sumNCR'] += (int)$r['ncr'];
    }
    foreach ($byOrga as &$g) {
        $g['taux_moy'] = $g['nb'] ? round($g['sumTC']/$g['nb'],1) : 0;
        $g['taux_non'] = $g['sumNCS']+$g['sumNCNS'] > 0 ? round($g['sumNCNS']/($g['sumNCS']+$g['sumNCNS'])*100,1) : 0;
    }
    unset($g);
    usort($byOrga, fn($a,$b) => $b['taux_moy'] <=> $a['taux_moy']);

    // Par annee
    $byAnnee = [];
    foreach ($allR as $r) {
        $yr = substr($r['date_realisation']??'',0,4); if(!$yr||$yr<'2020') continue;
        if(!isset($byAnnee[$yr])) $byAnnee[$yr]=['annee'=>$yr,'nb'=>0,'sumTC'=>0,'sumNCS'=>0,'sumNCNS'=>0];
        $byAnnee[$yr]['nb']++;
        $byAnnee[$yr]['sumTC']  += (float)($r['taux_conformite']??0);
        $byAnnee[$yr]['sumNCS'] += (int)$r['ncs'];
        $byAnnee[$yr]['sumNCNS']+= (int)$r['ncns'];
    }
    foreach ($byAnnee as &$g) { $g['taux_moy']=round($g['sumTC']/max($g['nb'],1),1); }
    ksort($byAnnee);

    // Par type
    $byType = [];
    $tl=['audit'=>'Audit','inspection_programmee'=>'Insp. prog.','inspection_non_programmee'=>'Insp. non prog.',
         'demonstration'=>'Demo','test'=>'Test','investigation'=>'Investigation'];
    foreach ($allR as $r) {
        $t=$r['type_activite']??'autre'; $tLabel=$tl[$t]??$t;
        if(!isset($byType[$t])) $byType[$t]=['type'=>$t,'label'=>$tLabel,'nb'=>0,'sumTC'=>0];
        $byType[$t]['nb']++;
        $byType[$t]['sumTC']+=(float)($r['taux_conformite']??0);
    }
    foreach ($byType as &$g) { $g['taux_moy']=round($g['sumTC']/max($g['nb'],1),1); }

    $ok([
        'stats'         => ['total'=>$totalJoints,'analyses'=>$total,'avg_tc'=>$avgTC,'avg_tnc'=>$avgTNC,'sumNCR'=>$sumNCR,'sumNCS'=>$sumNCS,'sumNCNS'=>$sumNCNS],
        'par_operateur' => array_values($byOrga),
        'par_annee'     => array_values($byAnnee),
        'par_type'      => array_values($byType),
        'allR'          => $allR,  // Seulement ceux avec NCR pour les filtres dynamiques
    ]);
    break;

// ----------------------------------------------------------------
// GET_RAPPORT : entete du rapport + meta (activite, site, equipe, CI)
// ----------------------------------------------------------------
case 'get_rapport':
    $idaudit = (int) ($_POST['idaudit'] ?? 0);
    if ($idaudit <= 0) { $fail('Audit invalide.'); break; }
    if (!peutSaisirRapport($db, $role, $myInsp, $idaudit)) {
        Audit::log('access_denied','rapports',"get_rapport audit #$idaudit");
        $fail('Acces refuse a ce rapport.'); break;
    }
    $st = $db->prepare("SELECT * FROM rapport_entete WHERE idaudit = ? LIMIT 1");
    $st->execute([$idaudit]);
    $rap = $st->fetch() ?: [];

    $meta = [];
    $stA = $db->prepare(
        "SELECT a.type_activite_operateur, a.idsite, a.site_inspection,
                t.nomtypeorg, s.nomsite
         FROM audit a
         LEFT JOIN type_organisme t ON t.idtypeorga = a.idtypeorga
         LEFT JOIN site s           ON s.idsite      = a.idsite
         WHERE a.idaudit = ? LIMIT 1"
    );
    $stA->execute([$idaudit]);
    $arow = $stA->fetch() ?: [];
    $meta['activite_operateur'] = $arow['nomtypeorg'] ?? ($arow['type_activite_operateur'] ?? '');
    // nomsite : privilegier la table site, sinon le champ texte site_inspection de l'audit
    $nomsite = trim((string)($arow['nomsite'] ?? ''));
    if ($nomsite === '') { $nomsite = trim((string)($arow['site_inspection'] ?? '')); }
    $meta['nomsite'] = $nomsite;

    // Validation = le vrai Chef Inspecteur (compte utilisateur role='chef_inspecteur' actif),
    // et NON le champ idchef_inspecteur de l'audit (qui contient l'ID du RA).
    $stCIrole = $db->prepare(
        "SELECT TRIM(CONCAT(COALESCE(i.preninspect,''),' ',COALESCE(i.nominspecteur,''))) AS ci_nom
         FROM users u
         JOIN inspecteur i ON i.iduser = u.iduser
         WHERE u.role = 'chef_inspecteur' AND u.is_active = 1
         ORDER BY i.idinspecteur LIMIT 1"
    );
    $stCIrole->execute();
    $ciNom = trim((string)($stCIrole->fetchColumn() ?: ''));
    $meta['ci_nom'] = $ciNom !== '' ? $ciNom : 'Chef Inspecteur';

    // Referentiels opposables : reglements rattaches a l'audit (pre-remplissage).
    $stReg = $db->prepare(
        "SELECT DISTINCT r.code_reglement, r.libelle_reglement
         FROM audit_reglement ar
         JOIN reglement r ON r.idreglement = ar.idreglement
         WHERE ar.idaudit = ?
         ORDER BY r.code_reglement"
    );
    $stReg->execute([$idaudit]);
    $regs = $stReg->fetchAll();
    if ($regs) {
        $lignes = '';
        foreach ($regs as $r) {
            $code = trim((string)($r['code_reglement'] ?? ''));
            $lib  = trim((string)($r['libelle_reglement'] ?? ''));
            $txt  = $code . ($code && $lib ? ' - ' : '') . $lib;
            $lignes .= '<p>' . htmlspecialchars($txt, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        $meta['referentiels'] = $lignes;
    } else {
        $meta['referentiels'] = '';
    }

    $stI = $db->prepare(
        "SELECT DISTINCT i.idinspecteur,
                TRIM(CONCAT(COALESCE(i.preninspect,''),' ',COALESCE(i.nominspecteur,''))) AS nom,
                i.trigr_inspecteur AS trigr
         FROM inspecteur i
         WHERE i.idinspecteur IN (
                SELECT idresponsable_audit FROM audit WHERE idaudit = ?
                UNION SELECT idinspecteur FROM audit_equipe WHERE idaudit = ?
         )
         ORDER BY nom"
    );
    $stI->execute([$idaudit, $idaudit]);
    $meta['inspecteurs'] = $stI->fetchAll();

    // Donnees de l'audit necessaires a l'affichage (nature, cadre, dates, operateur)
    $stAud = $db->prepare(
        "SELECT a.idaudit, a.num_audit, a.type_activite, a.cadre,
                a.date_realisation, a.date_previsionnelle, a.type_activite_operateur,
                a.idresponsable_audit, a.checklist_signee, o.nomorga
         FROM audit a LEFT JOIN organisme o ON o.idorga = a.idorga
         WHERE a.idaudit = ? LIMIT 1"
    );
    $stAud->execute([$idaudit]);
    $audit = $stAud->fetch() ?: [];

    // Droit d'ecriture : CI/admin, responsable, ou membre de l'equipe
    $canEdit = peutSaisirRapport($db, $role, $myInsp, $idaudit);

    // Domaines de l'audit (via audit_equipe) avec l'inspecteur assigne et les criteres deja saisis.
    // Chaque inspecteur ne saisit QUE le domaine qui lui est assigne.
    $stDom = $db->prepare(
        "SELECT ae.iddomaine, ae.idinspecteur,
                d.nomdomaine, d.libel_domaine,
                TRIM(CONCAT(COALESCE(i.preninspect,''),' ',COALESCE(i.nominspecteur,''))) AS insp_nom,
                rd.nce, rd.ncs, rd.ncns, rd.ncne, rd.ncna, rd.observations
         FROM audit_equipe ae
         JOIN domaine d       ON d.iddomaine = ae.iddomaine
         LEFT JOIN inspecteur i ON i.idinspecteur = ae.idinspecteur
         LEFT JOIN rapport_domaine rd
                ON rd.idaudit = ae.idaudit AND rd.iddomaine = ae.iddomaine AND rd.idinspecteur = ae.idinspecteur
         WHERE ae.idaudit = ?
         GROUP BY ae.iddomaine, ae.idinspecteur
         ORDER BY d.nomdomaine"
    );
    $stDom->execute([$idaudit]);
    $domaines = [];
    foreach ($stDom->fetchAll() as $d) {
        $domaines[] = [
            'iddomaine'    => (int) $d['iddomaine'],
            'idinspecteur' => (int) $d['idinspecteur'],
            'nomdomaine'   => $d['nomdomaine'] ?? '',
            'libel_domaine'=> $d['libel_domaine'] ?? '',
            'insp_nom'     => trim((string)($d['insp_nom'] ?? '')),
            'nce'  => (int) ($d['nce']  ?? 0),
            'ncs'  => (int) ($d['ncs']  ?? 0),
            'ncns' => (int) ($d['ncns'] ?? 0),
            'ncne' => (int) ($d['ncne'] ?? 0),
            'ncna' => (int) ($d['ncna'] ?? 0),
            'observations' => $d['observations'] ?? '',
            // L'inspecteur connecte peut-il saisir CE domaine ?
            'can_edit_dom' => ( in_array($role, ['admin','chef_inspecteur','consultant'], true)
                                || ($myInsp !== null && (int)$myInsp === (int)$d['idinspecteur']) )
        ];
    }

    $ok(['rapport' => $rap, 'meta' => $meta, 'audit' => $audit, 'can_edit' => $canEdit, 'domaines' => $domaines]);
    break;

// ----------------------------------------------------------------
// WHOAMI_INSP : renvoie l'idinspecteur de l'utilisateur connecte
// ----------------------------------------------------------------
case 'whoami_insp':
    $ok(['idinspecteur' => $myInsp]);
    break;

// ----------------------------------------------------------------
// RELEVE_NC : releve des non-conformites d'un audit (groupees par domaine)
//   + FNC anterieures encore ouvertes de l'operateur.
//   Mapping : Description <- libelle, Cat <- categorie, Referentiel <- reglements
// ----------------------------------------------------------------
case 'releve_nc':
    $idaudit = (int) ($_POST['idaudit'] ?? 0);
    if ($idaudit <= 0) { $fail('Audit invalide.'); break; }
    if (!peutSaisirRapport($db, $role, $myInsp, $idaudit)) {
        Audit::log('access_denied','rapports',"releve_nc audit #$idaudit");
        $fail('Acces refuse.'); break;
    }

    // Operateur de l'audit
    $stO = $db->prepare("SELECT idorga FROM audit WHERE idaudit = ? LIMIT 1");
    $stO->execute([$idaudit]);
    $idorga = (int) ($stO->fetchColumn() ?: 0);

    // Fonction locale : recupere les reglements d'une FNC (table de liaison, sinon champ texte)
    $reglementsFnc = function(int $idfnc, string $refTexte) use ($db): string {
        $st = $db->prepare(
            "SELECT r.code_reglement, r.libelle_reglement
             FROM fnc_reglement fr JOIN reglement r ON r.idreglement = fr.idreglement
             WHERE fr.idfnc = ? ORDER BY r.code_reglement"
        );
        $st->execute([$idfnc]);
        $rows = $st->fetchAll();
        if ($rows) {
            $parts = [];
            foreach ($rows as $r) {
                $code = trim((string)($r['code_reglement'] ?? ''));
                $lib  = trim((string)($r['libelle_reglement'] ?? ''));
                $parts[] = $code . ($code && $lib ? ' - ' : '') . $lib;
            }
            return implode('; ', $parts);
        }
        return trim($refTexte);
    };

    // FNC de l'audit, groupees par domaine
    $stF = $db->prepare(
        "SELECT f.idfnc, f.num_fnc, f.libelle, f.categorie, f.ref_reglement, f.statut,
                f.iddomaine, d.nomdomaine, d.libel_domaine
         FROM fiche_non_conformite f
         LEFT JOIN domaine d ON d.iddomaine = f.iddomaine
         WHERE f.idaudit = ?
         ORDER BY d.nomdomaine, f.num_fnc"
    );
    $stF->execute([$idaudit]);
    $parDomaine = [];   // iddomaine => { nom, libelle, fiches[] }
    $parCategorie = ['critique'=>0,'majeur'=>0,'mineur'=>0,'observation'=>0];
    foreach ($stF->fetchAll() as $f) {
        $iddom = (int) $f['iddomaine'];
        if (!isset($parDomaine[$iddom])) {
            $parDomaine[$iddom] = [
                'iddomaine'  => $iddom,
                'nomdomaine' => $f['nomdomaine'] ?? '',
                'libel_domaine' => $f['libel_domaine'] ?? '',
                'fiches'     => []
            ];
        }
        $cat = $f['categorie'] ?? '';
        if (isset($parCategorie[$cat])) $parCategorie[$cat]++;
        $parDomaine[$iddom]['fiches'][] = [
            'idfnc'       => (int) $f['idfnc'],
            'num_fnc'     => $f['num_fnc'] ?? '',
            'description' => $f['libelle'] ?? '',
            'categorie'   => $cat,
            'referentiel' => $reglementsFnc((int)$f['idfnc'], (string)($f['ref_reglement'] ?? '')),
            'statut'      => (int) $f['statut']
        ];
    }

    // FNC anterieures de l'operateur encore ouvertes (statut != 3 Ferme), hors audit courant
    $anterieures = [];
    if ($idorga > 0) {
        $stA = $db->prepare(
            "SELECT f.idfnc, f.num_fnc, f.libelle, f.categorie, f.statut, f.date_emission,
                    f.date_limite_mise_conformite, f.delais_mise_conformite_exige,
                    a.num_audit, d.nomdomaine
             FROM fiche_non_conformite f
             JOIN audit a     ON a.idaudit = f.idaudit
             LEFT JOIN domaine d ON d.iddomaine = f.iddomaine
             WHERE f.idorga = ? AND f.idaudit <> ? AND f.statut <> 3
             ORDER BY d.nomdomaine, f.date_emission DESC"
        );
        $stA->execute([$idorga, $idaudit]);
        foreach ($stA->fetchAll() as $f) {
            $st = (int) $f['statut'];
            $lbl = $st === 1 ? 'Accepte non verifie' : ($st === 2 ? 'Rejete' : 'Ouvert');
            // Delai : date limite si presente, sinon nombre de jours exige
            $delai = trim((string)($f['date_limite_mise_conformite'] ?? ''));
            if ($delai === '' || $delai === '0000-00-00') {
                $j = $f['delais_mise_conformite_exige'];
                $delai = ($j !== null && $j !== '') ? ($j . ' jours') : '-';
            }
            $anterieures[] = [
                'num_fnc'   => $f['num_fnc'] ?? '',
                'description'=> $f['libelle'] ?? '',
                'categorie' => $f['categorie'] ?? '',
                'num_audit' => $f['num_audit'] ?? '',
                'nomdomaine'=> $f['nomdomaine'] ?? '',
                'date_emission' => $f['date_emission'] ?? '',
                'delai_conformite' => $delai,
                'statut_lbl' => $lbl
            ];
        }
    }

    $ok([
        'par_domaine'   => array_values($parDomaine),
        'par_categorie' => $parCategorie,
        'anterieures'   => $anterieures
    ]);
    break;

// ----------------------------------------------------------------
// SAVE_RAPPORT : upsert de l'en-tete du rapport (saisie en ligne)
// ----------------------------------------------------------------
case 'save_rapport':
    $idaudit = (int) ($_POST['idaudit'] ?? 0);
    if ($idaudit <= 0) { $fail('Audit invalide.'); break; }
    if (!peutSaisirRapport($db, $role, $myInsp, $idaudit)) {
        Audit::log('access_denied','rapports',"save_rapport audit #$idaudit");
        $fail('Acces refuse : vous n\'etes pas autorise a saisir ce rapport.'); break;
    }

    $periode = mb_substr(trim((string)($_POST['periode_texte'] ?? '')), 0, 255);
    // Date de realisation (obligatoire, validee) et methode d'etablissement
    $dateReal = trim((string)($_POST['date_realisation'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateReal)) { $fail('Date de realisation invalide.'); break; }
    $dtR = DateTime::createFromFormat('Y-m-d', $dateReal);
    if (!$dtR || $dtR->format('Y-m-d') !== $dateReal) { $fail('Date de realisation invalide.'); break; }
    if ($dateReal > date('Y-m-d')) { $fail('La date de realisation ne peut pas etre dans le futur.'); break; }
    $methode = ($_POST['rapport_methode'] ?? '') === 'saisie' ? 'saisie' : null;
    $fonction= mb_substr(trim((string)($_POST['fonction_libre'] ?? '')), 0, 255);
    $fctRed  = mb_substr(trim((string)($_POST['fonction_redacteur'] ?? '')), 0, 150);
    $fctVer  = mb_substr(trim((string)($_POST['fonction_verificateur'] ?? '')), 0, 150);
    $idRed   = ctype_digit((string)($_POST['id_redacteur'] ?? ''))    ? (int)$_POST['id_redacteur']    : null;
    $idVer   = ctype_digit((string)($_POST['id_verificateur'] ?? '')) ? (int)$_POST['id_verificateur'] : null;

    $clean = function($k, $max = 20000) {
        return mb_substr((string)($_POST[$k] ?? ''), 0, $max);
    };
    $destinataires = $clean('destinataires', 5000);
    $ampliation    = $clean('ampliation_anac', 5000);
    $objectifs     = $clean('objectifs');
    $sites         = $clean('sites_geographiques');
    $unites        = $clean('unites_organisation');
    $activites     = $clean('activites_processus');
    $referentiels  = $clean('referentiels');
    $responsables  = $clean('responsables_operateur');
    $plan          = $clean('plan_realise');
    $conclusion    = $clean('conclusion');
    $pointsForts   = $clean('points_forts');
    $preuves       = $clean('preuves_documentees');
    // Visa (texte court) et dates (AAAA-MM-JJ ou vide) de redaction/verification/validation
    $reDate = function($k){ $v=trim((string)($_POST[$k] ?? '')); return preg_match('/^\d{4}-\d{2}-\d{2}$/',$v) ? $v : null; };
    $visaRed = mb_substr(trim((string)($_POST['visa_redacteur'] ?? '')), 0, 80);
    $visaVer = mb_substr(trim((string)($_POST['visa_verificateur'] ?? '')), 0, 80);
    $visaVal = mb_substr(trim((string)($_POST['visa_validation'] ?? '')), 0, 80);
    $dateRed = $reDate('date_redacteur');
    $dateVer = $reDate('date_verificateur');
    $dateVal = $reDate('date_validation');

    $stCI = $db->prepare("SELECT idchef_inspecteur FROM audit WHERE idaudit = ? LIMIT 1");
    $stCI->execute([$idaudit]);
    $idValidation = (int) ($stCI->fetchColumn() ?: 0) ?: null;

    $exists = $db->prepare("SELECT idrapport FROM rapport_entete WHERE idaudit = ? LIMIT 1");
    $exists->execute([$idaudit]);
    $idrapport = $exists->fetchColumn();

    if ($idrapport) {
        $up = $db->prepare(
            "UPDATE rapport_entete SET
                periode_texte=?, id_redacteur=?, id_verificateur=?, id_validation=?,
                fonction_libre=?, fonction_redacteur=?, fonction_verificateur=?,
                destinataires=?, ampliation_anac=?,
                objectifs=?, sites_geographiques=?, unites_organisation=?,
                activites_processus=?, referentiels=?, responsables_operateur=?, plan_realise=?,
                points_forts=?, preuves_documentees=?, conclusion=?,
                visa_redacteur=?, date_redacteur=?, visa_verificateur=?, date_verificateur=?,
                visa_validation=?, date_validation=?
             WHERE idaudit=?"
        );
        $up->execute([
            $periode, $idRed, $idVer, $idValidation,
            $fonction, $fctRed, $fctVer,
            $destinataires, $ampliation,
            $objectifs, $sites, $unites,
            $activites, $referentiels, $responsables, $plan,
            $pointsForts, $preuves, $conclusion,
            $visaRed, $dateRed, $visaVer, $dateVer, $visaVal, $dateVal,
            $idaudit
        ]);
    } else {
        $ins = $db->prepare(
            "INSERT INTO rapport_entete
                (idaudit, periode_texte, id_redacteur, id_verificateur, id_validation,
                 fonction_libre, fonction_redacteur, fonction_verificateur,
                 destinataires, ampliation_anac,
                 objectifs, sites_geographiques, unites_organisation,
                 activites_processus, referentiels, responsables_operateur, plan_realise, points_forts, preuves_documentees, conclusion,
                 visa_redacteur, date_redacteur, visa_verificateur, date_verificateur, visa_validation, date_validation)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $ins->execute([
            $idaudit, $periode, $idRed, $idVer, $idValidation,
            $fonction, $fctRed, $fctVer,
            $destinataires, $ampliation,
            $objectifs, $sites, $unites,
            $activites, $referentiels, $responsables, $plan, $pointsForts, $preuves, $conclusion,
            $visaRed, $dateRed, $visaVer, $dateVer, $visaVal, $dateVal
        ]);
        $idrapport = $db->lastInsertId();
    }

    // Mise a jour de l'audit : date de realisation, passage statut=3 (Effectue),
    // et methode d'etablissement = saisie (verrouille le choix Saisir/Joindre).
    // On ne force la methode que si elle n'est pas deja definie a 'joindre'.
    $hasMethodeCol = false;
    try {
        $chk = $db->execute("SHOW COLUMNS FROM audit LIKE 'rapport_methode'")->fetch();
        $hasMethodeCol = (bool)$chk;
    } catch (\Throwable $e) { $hasMethodeCol = false; }

    if ($hasMethodeCol && $methode === 'saisie') {
        $db->prepare(
            "UPDATE audit
                SET date_realisation = ?, statut = 3,
                    rapport_methode = COALESCE(rapport_methode, 'saisie')
              WHERE idaudit = ?"
        )->execute([$dateReal, $idaudit]);
    } else {
        $db->prepare(
            "UPDATE audit SET date_realisation = ?, statut = 3 WHERE idaudit = ?"
        )->execute([$dateReal, $idaudit]);
    }

    Audit::log('update','rapports',"Saisie du rapport (entete) audit #$idaudit, statut=3, date_real=$dateReal");
    $ok(['idrapport' => (int)$idrapport, 'statut' => 3, 'date_realisation' => $dateReal]);
    break;

// ----------------------------------------------------------------
// UPLOAD_CHECKLIST : depot des listes de verification signees (checklists)
//   depuis la SAISIE en ligne. Un seul PDF (a combiner cote client au besoin).
//   N'affecte NI rapport_audit NI rapport_methode : ne bascule pas en "joindre".
// ----------------------------------------------------------------
case 'upload_checklist':
    $idaudit = (int) ($_POST['idaudit'] ?? 0);
    if ($idaudit <= 0) { $fail('Audit invalide.'); break; }
    if (!peutSaisirRapport($db, $role, $myInsp, $idaudit)) {
        Audit::log('access_denied','rapports',"upload_checklist audit #$idaudit");
        $fail('Acces refuse : vous n\'etes pas autorise a deposer les checklists de ce rapport.'); break;
    }
    // Le rapport approuve est verrouille : plus aucun depot.
    try {
        $chkAp = $db->execute("SHOW COLUMNS FROM audit LIKE 'rapport_approuve'")->fetch();
        if ($chkAp) {
            $stAp = $db->prepare("SELECT rapport_approuve FROM audit WHERE idaudit=? LIMIT 1");
            $stAp->execute([$idaudit]);
            if ((int)$stAp->fetchColumn() === 1) { $fail('Rapport approuve : depot verrouille.'); break; }
        }
    } catch (\Throwable $e) { /* colonne absente : on continue */ }

    if (empty($_FILES['fichier_checklist']) || (int)$_FILES['fichier_checklist']['error'] !== UPLOAD_ERR_OK) {
        $fail('Aucun fichier recu.'); break;
    }
    $fc = $_FILES['fichier_checklist'];
    if (!is_uploaded_file($fc['tmp_name'])) { $fail('Telechargement invalide.'); break; }
    // Extension PDF stricte
    if (strtolower(pathinfo(basename($fc['name']), PATHINFO_EXTENSION)) !== 'pdf') {
        $fail('Les listes de verification doivent etre un seul fichier PDF.'); break;
    }
    // MIME reel (protection contre renommage)
    $finfoCk = new finfo(FILEINFO_MIME_TYPE);
    if ($finfoCk->file($fc['tmp_name']) !== 'application/pdf') {
        $fail('Le fichier n\'est pas un PDF valide.'); break;
    }
    if (!is_dir($rapportDir)) { @mkdir($rapportDir, 0775, true); }
    $ckName = 'checklist_' . $idaudit . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.pdf';
    if (!move_uploaded_file($fc['tmp_name'], $rapportDir . '/' . $ckName)) {
        $fail('Echec de l\'enregistrement du document.'); break;
    }

    // Recuperer l'ancien document pour COMBINER (ancien + nouveau) via Ghostscript.
    $stOld = $db->prepare("SELECT checklist_signee FROM audit WHERE idaudit=? LIMIT 1");
    $stOld->execute([$idaudit]);
    $ancien = trim((string)($stOld->fetchColumn() ?: ''));

    $finalName = $ckName;   // par defaut : le nouveau seul
    if ($ancien !== '' && $ancien !== $ckName) {
        $pAncien  = $rapportDir . '/' . basename($ancien);
        $pNouveau = $rapportDir . '/' . $ckName;
        if (is_file($pAncien) && class_exists('PdfMerger')) {
            $fusion  = 'checklist_' . $idaudit . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.pdf';
            $pFusion = $rapportDir . '/' . $fusion;
            // Ordre : ancien document d'abord, puis le nouveau
            if (PdfMerger::fusionner([$pAncien, $pNouveau], $pFusion)) {
                @unlink($pAncien);
                @unlink($pNouveau);
                $finalName = $fusion;
            } else {
                Audit::log('error','rapports',"Fusion checklist impossible audit #$idaudit : ".(class_exists('PdfMerger')?PdfMerger::derniereErreur():'PdfMerger absente'));
                @unlink($pAncien);
                $finalName = $ckName;
            }
        } else {
            if (is_file($pAncien)) { @unlink($pAncien); }
            $finalName = $ckName;
        }
    }

    // Enregistrer UNIQUEMENT la checklist (ne touche pas au rapport ni a la methode)
    $db->prepare(
        "UPDATE audit SET checklist_signee = ?, checklist_depot = NOW() WHERE idaudit = ?"
    )->execute([$finalName, $idaudit]);

    Audit::log('upload','rapports',"Depot checklists signees (saisie) audit #$idaudit"
        . ($finalName !== $ckName ? ' (combine avec le precedent)' : ''));
    $ok(['checklist' => $finalName, 'combine' => ($finalName !== $ckName)]);
    break;

// ----------------------------------------------------------------
// APPROUVER_RAPPORT : approbation finale par le CI (verrouillage).
//   Reservee a chef_inspecteur / admin. Une fois approuve, plus aucune
//   saisie ni modification n'est possible : seul l'apercu PDF reste.
// ----------------------------------------------------------------
case 'approuver_rapport':
    $idaudit = (int) ($_POST['idaudit'] ?? 0);
    if ($idaudit <= 0) { $fail('Audit invalide.'); break; }
    // Seuls le chef inspecteur et l'admin approuvent.
    if (!in_array($role, ['chef_inspecteur','admin'], true)) {
        Audit::log('access_denied','rapports',"approuver_rapport refuse (role $role) audit #$idaudit");
        $fail('Acces refuse : seul le chef inspecteur peut approuver le rapport.'); break;
    }
    // La colonne d'approbation doit exister (migration appliquee).
    try {
        $chkA = $db->execute("SHOW COLUMNS FROM audit LIKE 'rapport_approuve'")->fetch();
        if (!$chkA) { $fail('Fonction indisponible : migration du cycle de vie non appliquee.'); break; }
    } catch (\Throwable $e) { $fail('Fonction indisponible.'); break; }

    // L'audit doit exister et avoir un rapport (saisi ou joint).
    $stA = $db->prepare("SELECT rapport_audit, rapport_methode, rapport_approuve FROM audit WHERE idaudit = ? LIMIT 1");
    $stA->execute([$idaudit]);
    $aRow = $stA->fetch();
    if (!$aRow) { $fail('Audit introuvable.'); break; }
    if ((int)($aRow['rapport_approuve'] ?? 0) === 1) { $fail('Ce rapport est deja approuve.'); break; }
    $aHas = !empty($aRow['rapport_audit']) && trim((string)$aRow['rapport_audit']) !== '';
    $aSaisi = ($aRow['rapport_methode'] ?? '') === 'saisie';
    if (!$aHas && !$aSaisi) { $fail('Aucun rapport a approuver pour cet audit.'); break; }

    $db->prepare(
        "UPDATE audit
            SET rapport_approuve = 1, rapport_approuve_par = ?, rapport_date_approbation = NOW()
          WHERE idaudit = ?"
    )->execute([$uid, $idaudit]);

    // La signature du CI (date de validation du rapport) prend la date du jour,
    // si un rapport saisi en ligne existe (table rapport_entete).
    try {
        $stRe = $db->prepare("SELECT idrapport, date_validation FROM rapport_entete WHERE idaudit=? LIMIT 1");
        $stRe->execute([$idaudit]);
        $re = $stRe->fetch();
        if ($re) {
            // On ne remplit que si la date n'est pas deja fixee
            if (empty($re['date_validation']) || $re['date_validation'] === '0000-00-00') {
                $db->prepare("UPDATE rapport_entete SET date_validation = CURDATE() WHERE idaudit = ?")
                   ->execute([$idaudit]);
            }
        }
    } catch (\Throwable $e) { /* table absente : sans effet */ }

    Audit::log('update','rapports',"Approbation du rapport audit #$idaudit par user #$uid");
    $ok(['approuve' => 1]);
    break;

// ----------------------------------------------------------------
// SAVE_RAPPORT_DOMAINE : criteres (NCE/NCS/NCNS/NCNE/NCNA) + observations
//   d'UN domaine. Chaque inspecteur ne saisit QUE le domaine qui lui est assigne.
// ----------------------------------------------------------------
case 'save_rapport_domaine':
    $idaudit = (int) ($_POST['idaudit'] ?? 0);
    $iddom   = (int) ($_POST['iddomaine'] ?? 0);
    $idinsp  = (int) ($_POST['idinspecteur'] ?? 0);
    if ($idaudit <= 0 || $iddom <= 0 || $idinsp <= 0) { $fail('Parametres invalides.'); break; }

    // Verifier que ce trio (audit, inspecteur, domaine) existe reellement dans l'equipe
    $stChk = $db->prepare("SELECT 1 FROM audit_equipe WHERE idaudit=? AND idinspecteur=? AND iddomaine=? LIMIT 1");
    $stChk->execute([$idaudit, $idinsp, $iddom]);
    if (!$stChk->fetch()) { $fail('Ce domaine n\'est pas assigne a cet inspecteur pour cet audit.'); break; }

    // Controle d'acces : CI/admin, OU l'inspecteur lui-meme (il ne saisit que SON domaine)
    $estCI = in_array($role, ['admin','chef_inspecteur','consultant'], true);
    if (!$estCI && ($myInsp === null || (int)$myInsp !== $idinsp)) {
        Audit::log('access_denied','rapports',"save_rapport_domaine audit #$idaudit dom #$iddom");
        $fail('Vous ne pouvez saisir que le domaine qui vous est assigne.'); break;
    }

    // Validation des criteres : entiers >= 0
    $n = function($k){ $v = $_POST[$k] ?? 0; return (ctype_digit((string)$v)) ? (int)$v : 0; };
    $nce = $n('nce'); $ncs = $n('ncs'); $ncns = $n('ncns'); $ncne = $n('ncne'); $ncna = $n('ncna');
    $obs = mb_substr((string)($_POST['observations'] ?? ''), 0, 20000);

    // Upsert (cle unique idaudit+idinspecteur+iddomaine)
    $ex = $db->prepare("SELECT idrapport_domaine FROM rapport_domaine WHERE idaudit=? AND idinspecteur=? AND iddomaine=? LIMIT 1");
    $ex->execute([$idaudit, $idinsp, $iddom]);
    if ($ex->fetchColumn()) {
        $up = $db->prepare(
            "UPDATE rapport_domaine SET nce=?, ncs=?, ncns=?, ncne=?, ncna=?, observations=?
             WHERE idaudit=? AND idinspecteur=? AND iddomaine=?"
        );
        $up->execute([$nce,$ncs,$ncns,$ncne,$ncna,$obs, $idaudit,$idinsp,$iddom]);
    } else {
        $ins = $db->prepare(
            "INSERT INTO rapport_domaine (idaudit, idinspecteur, iddomaine, nce, ncs, ncns, ncne, ncna, observations)
             VALUES (?,?,?,?,?,?,?,?,?)"
        );
        $ins->execute([$idaudit,$idinsp,$iddom,$nce,$ncs,$ncns,$ncne,$ncna,$obs]);
    }

    // Recalcul des totaux de l'audit = somme de tous les domaines du rapport.
    // Indispensable pour que l'ouverture des FNC voie les NCNS (audit.ncns >= 1).
    $stSum = $db->prepare(
        "SELECT COALESCE(SUM(nce),0) AS nce, COALESCE(SUM(ncs),0) AS ncs,
                COALESCE(SUM(ncns),0) AS ncns, COALESCE(SUM(ncne),0) AS ncne,
                COALESCE(SUM(ncna),0) AS ncna
         FROM rapport_domaine WHERE idaudit = ?"
    );
    $stSum->execute([$idaudit]);
    $sum = $stSum->fetch() ?: ['nce'=>0,'ncs'=>0,'ncns'=>0,'ncne'=>0,'ncna'=>0];
    $tNce=(int)$sum['nce']; $tNcs=(int)$sum['ncs']; $tNcns=(int)$sum['ncns'];
    $tNcne=(int)$sum['ncne']; $tNcna=(int)$sum['ncna'];
    $tNcr = $tNce + $tNcs + $tNcns + $tNcne + $tNcna;
    $den  = $tNcs + $tNcns;
    $tauxConf    = $den ? round($tNcs / $den * 100, 2) : 0;
    $tauxNonConf = $den ? round($tNcns / $den * 100, 2) : 0;

    $stMaj = $db->prepare(
        "UPDATE audit SET nce=?, ncs=?, ncns=?, ncne=?, ncna=?, ncr=?,
                taux_conformite=?, taux_non_conformite=? WHERE idaudit=?"
    );
    $stMaj->execute([$tNce,$tNcs,$tNcns,$tNcne,$tNcna,$tNcr,$tauxConf,$tauxNonConf,$idaudit]);

    Audit::log('update','rapports',"Criteres domaine #$iddom audit #$idaudit (NCNS total=$tNcns)");
    $ok(['saved' => true, 'totaux' => ['nce'=>$tNce,'ncs'=>$tNcs,'ncns'=>$tNcns,'ncne'=>$tNcne,'ncna'=>$tNcna,'ncr'=>$tNcr]]);
    break;

default: $fail('Action inconnue.');
}} catch (Throwable $e) {
    error_log('rapports: ' . $e->getMessage());
    $fail('Erreur technique : ' . $e->getMessage());
}