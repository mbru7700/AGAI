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
    $st = $db->prepare("SELECT rapport_audit, idresponsable_audit FROM audit WHERE idaudit = ? AND rapport_audit IS NOT NULL");
    $st->execute([$idaudit]);
    $row = $st->fetch();
    if (!$row) { http_response_code(404); exit('Fichier introuvable.'); }
    if (!in_array($role, ['admin','chef_inspecteur','consultant','operateur'], true) && $myInsp !== null) {
        $stA = $db->prepare("SELECT 1 FROM audit WHERE idaudit=? AND (idresponsable_audit=? OR idchef_inspecteur=?)
                             UNION SELECT 1 FROM audit_equipe WHERE idaudit=? AND idinspecteur=?");
        $stA->execute([$idaudit,$myInsp,$myInsp,$idaudit,$myInsp]);
        if (!$stA->fetch()) { http_response_code(403); exit('Acces refuse.'); }
    }
    $path = $rapportDir . '/' . basename((string) $row['rapport_audit']);
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

    $rows = $db->execute(
        "SELECT a.idaudit, a.num_audit, a.type_activite, a.cadre, a.statut,
                a.date_previsionnelle, a.date_realisation, a.date_delivrance_rapport,
                a.delai_execution, a.rapport_audit, a.lettre_notification,
                a.nce, a.ncs, a.ncns, a.ncne, a.ncna, a.ncr,
                a.taux_conformite, a.taux_non_conformite,
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

    if (empty($_FILES['fichier_rapport']) || $_FILES['fichier_rapport']['error'] !== UPLOAD_ERR_OK) {
        $fail('Aucun fichier selectionne.'); break;
    }
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

    $stAud = $db->prepare("SELECT date_previsionnelle, rapport_audit FROM audit WHERE idaudit=?");
    $stAud->execute([$idaudit]); $aud = $stAud->fetch();
    if (!$aud) { $fail('Audit introuvable.'); break; }

    if ($aud['rapport_audit']) {
        $oldPath = $rapportDir . '/' . basename($aud['rapport_audit']);
        if (file_exists($oldPath)) @unlink($oldPath);
    }

    $stored = 'rapport_' . $idaudit . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest   = $rapportDir . '/' . $stored;
    if (!move_uploaded_file($f['tmp_name'], $dest)) { $fail("Echec de l'enregistrement."); break; }

    $dateRealFinal = (!empty($dateReal) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateReal))
        ? $dateReal : date('Y-m-d');

    $delai = null;
    if ($aud['date_previsionnelle'] && $aud['date_previsionnelle'] !== '0000-00-00') {
        $d1 = new DateTime($aud['date_previsionnelle']);
        $d2 = new DateTime($dateRealFinal);
        $diff = $d1->diff($d2);
        $delai = $diff->days . ' jour' . ($diff->days > 1 ? 's' : '') . ($diff->invert ? ' (avance)' : ' (retard)');
    }

    $db->prepare(
        "UPDATE audit SET
            rapport_audit=?, statut=3,
            date_realisation=?, date_delivrance_rapport=CURDATE(), delai_execution=?,
            nce=?, ncs=?, ncns=?, ncne=?, ncna=?, ncr=?,
            taux_conformite=?, taux_non_conformite=?
         WHERE idaudit=?"
    )->execute([$stored, $dateRealFinal, $delai,
                $nce, $ncs, $ncns, $ncne, $ncna, $ncr,
                $tauxConf, $tauxNonConf, $idaudit]);

    Audit::log('upload','rapports',"Rapport audit #$idaudit - NCR:$ncr, Taux:$tauxConf%");
    $ok(['message'=>'Rapport enregistre. Statut passe a Effectue.','fichier'=>$stored,
         'delai'=>$delai,'ncr'=>$ncr,'taux_conformite'=>$tauxConf,'taux_non_conformite'=>$tauxNonConf]);
    break;

// ----------------------------------------------------------------
case 'stats':
    // Dashboard Power BI : stats globales + par operateur + par annee + par type
    $isCI   = in_array($role, ['admin','chef_inspecteur','consultant'], true);
    $where  = ''; $params = [];
    if (!$isCI && $idorgaUser !== null) {
        $where = 'AND a.idorga = ?'; $params = [$idorgaUser];
    } elseif (!$isCI && $myInsp !== null) {
        $where = "AND (a.idresponsable_audit=? OR a.idaudit IN (SELECT ae.idaudit FROM audit_equipe ae WHERE ae.idinspecteur=?))";
        $params = [$myInsp, $myInsp];
    } elseif (!$isCI) {
        $ok(['stats'=>[],'par_operateur'=>[],'par_annee'=>[],'par_type'=>[]]); break;
    }

    // Tous les rapports joints (avec OU sans criteres)
    $allRjoints = $db->execute(
        "SELECT a.idaudit, a.num_audit, a.type_activite, a.date_realisation,
                a.nce, a.ncs, a.ncns, a.ncne, a.ncna, a.ncr,
                a.taux_conformite, a.taux_non_conformite,
                o.nomorga, o.idorga
         FROM audit a
         LEFT JOIN organisme o ON o.idorga = a.idorga
         WHERE a.rapport_audit IS NOT NULL
           AND TRIM(a.rapport_audit) <> '' $where
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

default: $fail('Action inconnue.');
}} catch (Throwable $e) {
    error_log('rapports: ' . $e->getMessage());
    $fail('Erreur technique : ' . $e->getMessage());
}