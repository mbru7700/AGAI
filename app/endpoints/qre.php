<?php
/**
 * Endpoint AJAX : QRE - Questionnaire de Retour d'Experience
 * Route : /api/qre
 * Actions : list (audits eligibles), save (soumettre en ligne), save_fichier
 *           (joindre un formulaire deja rempli), get (lire QRE), serve
 *           (lecture du fichier joint), imprimer (vue imprimable d'un QRE
 *           saisi en ligne), delete
 * Eligibilite : audit doit avoir lettre_notification jointe
 * Acces : role=operateur => voit ses propres audits uniquement
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardApi('qre');

$db   = Database::getInstance();
$role = Rbac::role();
$uid  = (int) ($_SESSION['user_id'] ?? 0);

// 'serve' est une lecture seule (ouverture en iframe/lien direct) : pas de jeton
// CSRF disponible dans ce contexte, comme pour l'action equivalente d'archivage.php.
$actionBrute = trim((string) ($_POST['action'] ?? $_GET['action'] ?? ''));
if (!in_array($actionBrute, ['serve', 'imprimer'], true) && !Security::validateCSRF($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success'=>false,'message'=>'CSRF invalide.']); exit;
}

$action = $actionBrute;
$ok   = function($x=[]) { echo json_encode(['success'=>true]+$x); };
$fail = function($m)     { echo json_encode(['success'=>false,'message'=>$m]); };

// Recuperer idorga de l utilisateur (operateur)
$idorgaUser = null;
$stOrg = $db->prepare("SELECT idorga FROM users WHERE iduser = ?");
$stOrg->execute([$uid]);
$uRow = $stOrg->fetch();
if ($uRow) $idorgaUser = (int) ($uRow['idorga'] ?? 0) ?: null;

// Inspecteur eventuellement
$myInsp = null;
$stI = $db->prepare("SELECT idinspecteur FROM inspecteur WHERE iduser = ? LIMIT 1");
$stI->execute([$uid]); $ri = $stI->fetch();
if ($ri) $myInsp = (int) $ri['idinspecteur'];

// Valeurs autorisees pour les radios
$VALID_NOTES = ['TB','B','M','TM'];

// Un operateur ne peut agir que sur son propre organisme ; admin/CI/consultant : tout
// Seul un OPERATEUR est restreint a son propre organisme. Tous les autres
// roles (inspecteur, chef_inspecteur, admin, consultant) peuvent enregistrer
// ou consulter un QRE pour n'importe quel organisme : ce sont eux qui
// saisissent les questionnaires sur le terrain. On raisonne donc par
// "est-ce un operateur ?" plutot que par liste blanche de roles (plus sûr :
// un nouveau role n'est jamais bloque par oubli).
$isOperateur = ($role === 'operateur');
$isCiGlobal  = !$isOperateur;

try { switch ($action) {

// ----------------------------------------------------------------
// list : audits avec lettre_notification jointe, filtres par role
case 'list':
    $isCI    = in_array($role, ['admin','chef_inspecteur','consultant'], true);
    $isOper  = ($role === 'operateur');
    $where   = ''; $params = [];

    if ($isOper && $idorgaUser !== null) {
        // Operateur : voit ses propres audits (son organisme)
        $where = "AND a.idorga = ?";
        $params = [$idorgaUser];
    } elseif (!$isCI && $myInsp !== null) {
        // Inspecteur : voit ses audits
        $where = "AND (a.idresponsable_audit=? OR a.idaudit IN
                  (SELECT ae.idaudit FROM audit_equipe ae WHERE ae.idinspecteur=?))";
        $params = [$myInsp, $myInsp];
    } elseif (!$isCI && !$isOper) {
        $where = 'AND 1=0';
    }

    $rows = $db->execute(
        "SELECT a.idaudit, a.num_audit, a.type_activite, a.statut,
                a.date_previsionnelle, a.date_realisation,
                a.site_inspection, a.lettre_notification,
                o.idorga, o.nomorga,
                TRIM(CONCAT(COALESCE(ir.preninspect,''),' ',COALESCE(ir.nominspecteur,''))) AS ra_nom,
                q.idqre, q.created_at AS qre_date, q.fichier_joint AS qre_fichier
         FROM audit a
         LEFT JOIN organisme  o  ON o.idorga       = a.idorga
         LEFT JOIN inspecteur ir ON ir.idinspecteur = a.idresponsable_audit
         LEFT JOIN qre q ON q.idaudit = a.idaudit AND q.idorga = o.idorga
         WHERE a.lettre_notification IS NOT NULL
           AND TRIM(a.lettre_notification) <> '' $where
         ORDER BY a.idaudit DESC",
        $params
    )->fetchAll();
    $ok(['data' => $rows]);
    break;

// ----------------------------------------------------------------
// stats : dashboard analytique complet
case 'stats':
    // Tableau de bord QRE = analyse globale de la qualite des audits (amelioration
    // du systeme de supervision). Visible dans son ensemble par les inspecteurs,
    // chefs inspecteurs, admins et consultants. Seul l'OPERATEUR est restreint a
    // ses propres QRE (il ne voit que les audits de son organisme).
    $where  = ''; $params = [];
    if ($role === 'operateur') {
        if ($idorgaUser !== null) {
            $where = "WHERE q.idorga = ?"; $params = [$idorgaUser];
        } else {
            // Operateur sans organisme rattache : aucune donnee
            $ok(['stats'=>['total'=>0,'taux_tb'=>0,'taux_b'=>0,'taux_m'=>0,'taux_tm'=>0], 'par_annee'=>[], 'par_type'=>[], 'par_champ'=>[], 'allQ'=>[]]); break;
        }
    }
    $fields = ['prep_notification','prep_plan','cond_ouverture','cond_entretiens','cond_procedures',
               'cond_qualites','cond_communication','cond_classification','cond_pertinence','cond_cloture'];
    // Stats globales - inclure num_audit et nomorga pour filtres JS
    $allQ = $db->execute(
        "SELECT q.*, a.num_audit, a.type_activite, a.date_previsionnelle,
                o.nomorga
         FROM qre q
         JOIN audit     a ON a.idaudit = q.idaudit
         JOIN organisme o ON o.idorga  = q.idorga
         $where",
        $params
    )->fetchAll();
    $total = count($allQ);
    if (!$total) { $ok(['stats'=>['total'=>0,'taux_tb'=>0,'taux_b'=>0,'taux_m'=>0,'taux_tm'=>0], 'par_annee'=>[], 'par_type'=>[], 'par_champ'=>[], 'allQ'=>[]]); break; }
    // Comptage notes toutes questions confondues
    $cnt = ['TB'=>0,'B'=>0,'M'=>0,'TM'=>0];
    $totalNotes = 0;
    foreach ($allQ as $r) {
        foreach ($fields as $f) {
            $v = $r[$f] ?? '';
            if (isset($cnt[$v])) { $cnt[$v]++; $totalNotes++; }
        }
    }
    $pct = function(int $n) use ($totalNotes): float { return $totalNotes ? round($n/$totalNotes*100,1) : 0; };
    // Score moyen (TB=4, B=3, M=2, TM=1) => taux satisfaction positive (TB+B)
    $score = ['TB'=>4,'B'=>3,'M'=>2,'TM'=>1];
    $totalScore = array_sum(array_map(function($v) use ($score) { return $score[$v] ?? 0; }, array_merge(...array_map(function($r) use ($fields) { return array_map(function($f) use ($r) { return $r[$f]??''; }, $fields); }, $allQ))));
    $scoreMoyen = $totalNotes ? round($totalScore/$totalNotes, 2) : 0;
    $tauxSat    = round(($cnt['TB']+$cnt['B'])/$totalNotes*100, 1);
    // Par annee
    $parAnnee = [];
    foreach ($allQ as $r) {
        $yr = substr($r['date_qre']??$r['created_at']??'',0,4);
        if (!$yr || $yr < '2020') continue;
        if (!isset($parAnnee[$yr])) $parAnnee[$yr] = ['annee'=>$yr,'TB'=>0,'B'=>0,'M'=>0,'TM'=>0,'total'=>0,'nb_qre'=>0];
        $parAnnee[$yr]['nb_qre']++;
        foreach ($fields as $f) {
            $v = $r[$f]??'';
            if (isset($parAnnee[$yr][$v])) $parAnnee[$yr][$v]++;
            $parAnnee[$yr]['total']++;
        }
    }
    // Par type d activite
    $parType = [];
    foreach ($allQ as $r) {
        $t = $r['type_activite'] ?? 'autre';
        if (!isset($parType[$t])) $parType[$t] = ['type'=>$t,'TB'=>0,'B'=>0,'M'=>0,'TM'=>0,'total'=>0,'nb_qre'=>0];
        $parType[$t]['nb_qre']++;
        foreach ($fields as $f) {
            $v = $r[$f]??'';
            if (isset($parType[$t][$v])) $parType[$t][$v]++;
            $parType[$t]['total']++;
        }
    }
    // Par champ/question (score moyen)
    $LABELS = ['prep_notification'=>'Notification','prep_plan'=>'Plan d\'audit','cond_ouverture'=>'Reunion d\'ouverture',
               'cond_entretiens'=>'Qualite entretiens','cond_procedures'=>'Connaissance procedures',
               'cond_qualites'=>'Qualites inspecteur','cond_communication'=>'Communication',
               'cond_classification'=>'Classification constats','cond_pertinence'=>'Pertinence obs.',
               'cond_cloture'=>'Reunion de cloture'];
    $parChamp = [];
    foreach ($fields as $f) {
        $ftb=0;$fb=0;$fm=0;$ftm=0;
        foreach ($allQ as $r) {
            $v=$r[$f]??'';
            if($v==='TB')$ftb++;elseif($v==='B')$fb++;elseif($v==='M')$fm++;elseif($v==='TM')$ftm++;
        }
        $tot=$ftb+$fb+$fm+$ftm;
        $sc=$tot?round(($ftb*4+$fb*3+$fm*2+$ftm*1)/$tot,2):0;
        $sat=$tot?round(($ftb+$fb)/$tot*100,1):0;
        $parChamp[] = ['champ'=>$f,'label'=>$LABELS[$f]??$f,'TB'=>$ftb,'B'=>$fb,'M'=>$fm,'TM'=>$ftm,'score'=>$sc,'sat'=>$sat];
    }
    // Trier par score decroissant pour le podium
    usort($parChamp, function($a,$b){ return $b['score'] <=> $a['score']; });
    $ok([
        'stats' => ['total'=>$total,'score_moyen'=>$scoreMoyen,'taux_sat'=>$tauxSat,
                    'TB'=>$cnt['TB'],'B'=>$cnt['B'],'M'=>$cnt['M'],'TM'=>$cnt['TM'],
                    'pct_tb'=>$pct($cnt['TB']),'pct_b'=>$pct($cnt['B']),
                    'pct_m'=>$pct($cnt['M']),'pct_tm'=>$pct($cnt['TM'])],
        'par_annee' => array_values($parAnnee),
        'par_type'  => array_values($parType),
        'par_champ' => $parChamp,
        'allQRE'    => $allQ,  // Pour filtres dynamiques cote JS (inclut num_audit, nomorga)
    ]);
    break;

// ----------------------------------------------------------------
// get : lire un QRE existant
case 'get':
    $idqre = (int) ($_POST['idqre'] ?? 0);
    $st = $db->prepare(
        "SELECT q.*, o.nomorga, a.num_audit, a.type_activite,
                a.site_inspection, a.date_previsionnelle, a.date_realisation
         FROM qre q
         JOIN organisme o ON o.idorga = q.idorga
         JOIN audit     a ON a.idaudit = q.idaudit
         WHERE q.idqre = ?"
    );
    $st->execute([$idqre]);
    $qre = $st->fetch();
    if (!$qre) { $fail('QRE introuvable.'); break; }

    // Un operateur ne peut lire que le QRE de son propre organisme (anti-IDOR)
    if (!$isCiGlobal && $idorgaUser !== (int) $qre['idorga']) {
        $fail('Acces non autorise a ce questionnaire.'); break;
    }

    $ok(['data' => $qre]);
    break;

// ----------------------------------------------------------------
// save : soumettre / modifier un QRE
case 'save':
    $idaudit    = (int)  ($_POST['idaudit']            ?? 0);
    $idorga     = (int)  ($_POST['idorga']             ?? 0);
    $activites  = trim((string) ($_POST['activites']   ?? ''));
    $dateQre    = trim((string) ($_POST['date_qre']    ?? date('Y-m-d')));
    $autres     = trim((string) ($_POST['autres']      ?? ''));
    $envoyerMail= (int) ($_POST['envoyer_mail']        ?? 0);

    if ($idaudit <= 0 || $idorga <= 0) { $fail('Audit ou operateur invalide.'); break; }
    if (!$activites) { $fail('Activites auditees requises.'); break; }

    // Un operateur ne peut soumettre un QRE que pour son propre organisme (anti-IDOR)
    if (!$isCiGlobal && $idorgaUser !== $idorga) {
        $fail('Action non autorisee pour cet operateur.'); break;
    }

    // Valider les 10 notes
    $champs = [
        'prep_notification','prep_plan',
        'cond_ouverture','cond_entretiens','cond_procedures',
        'cond_qualites','cond_communication','cond_classification',
        'cond_pertinence','cond_cloture',
    ];
    $notes = [];
    foreach ($champs as $c) {
        $v = strtoupper(trim((string) ($_POST[$c] ?? '')));
        if (!in_array($v, $VALID_NOTES, true)) {
            $fail("Champ \"{$c}\" : valeur invalide. Choisissez TB, B, M ou TM."); break 2;
        }
        $notes[$c] = $v;
    }

    // Verifier que l audit a bien une lettre de notification
    $stA = $db->prepare("SELECT lettre_notification FROM audit WHERE idaudit=? AND lettre_notification IS NOT NULL");
    $stA->execute([$idaudit]);
    if (!$stA->fetch()) { $fail('Cet audit n\'est pas eligible (lettre de notification manquante).'); break; }

    // Upsert
    $stU = $db->prepare(
        "INSERT INTO qre (idaudit, idorga, iduser, activites_auditees, date_qre,
            prep_notification, prep_plan, cond_ouverture, cond_entretiens, cond_procedures,
            cond_qualites, cond_communication, cond_classification, cond_pertinence, cond_cloture,
            autres_appreciations, envoye_mail, created_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
         ON DUPLICATE KEY UPDATE
            iduser=VALUES(iduser), activites_auditees=VALUES(activites_auditees),
            date_qre=VALUES(date_qre),
            prep_notification=VALUES(prep_notification), prep_plan=VALUES(prep_plan),
            cond_ouverture=VALUES(cond_ouverture), cond_entretiens=VALUES(cond_entretiens),
            cond_procedures=VALUES(cond_procedures), cond_qualites=VALUES(cond_qualites),
            cond_communication=VALUES(cond_communication), cond_classification=VALUES(cond_classification),
            cond_pertinence=VALUES(cond_pertinence), cond_cloture=VALUES(cond_cloture),
            autres_appreciations=VALUES(autres_appreciations),
            envoye_mail=VALUES(envoye_mail), created_at=NOW()"
    );
    $stU->execute([
        $idaudit, $idorga, $uid, $activites, $dateQre,
        $notes['prep_notification'], $notes['prep_plan'],
        $notes['cond_ouverture'], $notes['cond_entretiens'], $notes['cond_procedures'],
        $notes['cond_qualites'], $notes['cond_communication'], $notes['cond_classification'],
        $notes['cond_pertinence'], $notes['cond_cloture'],
        $autres, $envoyerMail
    ]);

    // Envoi mail si demande
    if ($envoyerMail) {
        $stOrga = $db->prepare("SELECT nomorga FROM organisme WHERE idorga=?");
        $stOrga->execute([$idorga]);
        $org = $stOrga->fetch();
        $stAud = $db->prepare(
            "SELECT num_audit, type_activite, site_inspection, date_previsionnelle, date_realisation
             FROM audit WHERE idaudit=?"
        );
        $stAud->execute([$idaudit]);
        $aud = $stAud->fetch();
        $labelNote = ['TB'=>'Tres Bonne','B'=>'Bonne','M'=>'Mauvaise','TM'=>'Tres Mauvaise'];
        $typeLabel  = [
            'audit'=>'Audit','inspection_programmee'=>'Inspection programmee',
            'inspection_non_programmee'=>'Inspection non programmee',
            'demonstration'=>'Demonstration','test'=>'Test','investigation'=>'Investigation',
        ];
        $nl = $labelNote;
        $nomorga   = htmlspecialchars($org['nomorga'] ?? '', ENT_QUOTES, 'UTF-8');
        $numAudit  = htmlspecialchars($aud['num_audit'] ?? '', ENT_QUOTES, 'UTF-8');
        $typAudit  = htmlspecialchars($typeLabel[$aud['type_activite'] ?? ''] ?? '', ENT_QUOTES, 'UTF-8');
        $siteInsp  = htmlspecialchars($aud['site_inspection'] ?? '', ENT_QUOTES, 'UTF-8');
        $datePrev  = $aud['date_previsionnelle'] && $aud['date_previsionnelle']!=='0000-00-00'
                     ? date('d/m/Y', strtotime($aud['date_previsionnelle'])) : '-';
        $dateReal  = $aud['date_realisation'] && $aud['date_realisation']!=='0000-00-00'
                     ? date('d/m/Y', strtotime($aud['date_realisation'])) : 'Non renseignee';
        $dateQreFmt= date('d/m/Y', strtotime($dateQre));
        $activitesH= htmlspecialchars($activites, ENT_QUOTES, 'UTF-8');
        $autresH   = $autres ? htmlspecialchars($autres, ENT_QUOTES, 'UTF-8') : '';
        $noteSpan  = function(string $v) use ($labelNote): string {
            $colors=['TB'=>'#1E9C4B','B'=>'#23408F','M'=>'#b58a00','TM'=>'#D32F2F'];
            $c=$colors[$v]??'#555'; $t=$labelNote[$v]??$v;
            return "<span style='font-family:Candara,Arial,sans-serif;background:{$c};color:#fff;border-radius:5px;padding:2px 10px;font-weight:700;font-size:.88em'>{$t}</span>";
        };
        $bodyHtml = "
<div style='font-family:Candara,Arial,sans-serif;font-size:11pt;color:#1e293b;max-width:660px;margin:0 auto'>
  <div style='margin:0;padding:0;line-height:0'>
    <img src='https://anacgabon.org/wp-content/uploads/2024/10/banierenteanac.png' alt='ANAC Gabon' style='width:100%;max-width:660px;display:block'>
  </div>
  <div style='background:#23408F;padding:14px 22px;text-align:center'>
    <h2 style='font-family:Candara,Arial,sans-serif;color:#fff;margin:0;font-size:1rem;letter-spacing:.04em'>QUESTIONNAIRE DE RETOUR D\'EXPERIENCE</h2>
    <p style='font-family:Candara,Arial,sans-serif;color:rgba(255,255,255,.82);margin:3px 0 0;font-size:.8rem'>IX-GEN-R3-F-I-011 - Fevrier 2024 Version 02</p>
  </div>
  <div style='background:#fff;padding:24px;border:1px solid #e2e8f0;border-top:none'>
    <p style='font-family:Candara,Arial,sans-serif;font-size:.95rem;color:#2C3E50;margin-bottom:18px'>
      L\'operateur <strong style='color:#23408F'>{$nomorga}</strong> vient de remplir son Questionnaire de Retour d\'Experience (QRE) concernant l\'acte de supervision
      <strong style='color:#23408F'>{$numAudit}</strong> ({$typAudit}). Vous trouverez ci-dessous le detail complet des reponses soumises.
    </p>
    <table style='font-family:Candara,Arial,sans-serif;width:100%;border-collapse:collapse;margin-bottom:16px;font-size:.9rem'>
      <tr style='background:#f0f4ff'>
        <td style='padding:7px 12px;font-weight:700;border:1px solid #e2e8f0;width:38%'>Operateur</td>
        <td style='padding:7px 12px;border:1px solid #e2e8f0;font-weight:700;color:#23408F'>{$nomorga}</td>
      </tr>
      <tr>
        <td style='padding:7px 12px;font-weight:700;border:1px solid #e2e8f0;background:#f8fafc'>N Audit</td>
        <td style='padding:7px 12px;border:1px solid #e2e8f0;font-weight:800;color:#23408F'>{$numAudit}</td>
      </tr>
      <tr style='background:#f0f4ff'>
        <td style='padding:7px 12px;font-weight:700;border:1px solid #e2e8f0'>Nature</td>
        <td style='padding:7px 12px;border:1px solid #e2e8f0'>{$typAudit}</td>
      </tr>
      <tr>
        <td style='padding:7px 12px;font-weight:700;border:1px solid #e2e8f0;background:#f8fafc'>Site d\'inspection</td>
        <td style='padding:7px 12px;border:1px solid #e2e8f0'>{$siteInsp}</td>
      </tr>
      <tr style='background:#f0f4ff'>
        <td style='padding:7px 12px;font-weight:700;border:1px solid #e2e8f0'>Date previsionnelle</td>
        <td style='padding:7px 12px;border:1px solid #e2e8f0'>{$datePrev}</td>
      </tr>
      <tr>
        <td style='padding:7px 12px;font-weight:700;border:1px solid #e2e8f0;background:#f8fafc'>Date de realisation</td>
        <td style='padding:7px 12px;border:1px solid #e2e8f0'>{$dateReal}</td>
      </tr>
      <tr style='background:#f0f4ff'>
        <td style='padding:7px 12px;font-weight:700;border:1px solid #e2e8f0'>Activite(s) auditee(s)</td>
        <td style='padding:7px 12px;border:1px solid #e2e8f0'>{$activitesH}</td>
      </tr>
      <tr>
        <td style='padding:7px 12px;font-weight:700;border:1px solid #e2e8f0;background:#f8fafc'>Date QRE</td>
        <td style='padding:7px 12px;border:1px solid #e2e8f0'>{$dateQreFmt}</td>
      </tr>
    </table>

    <div style='font-family:Candara,Arial,sans-serif;background:#23408F;color:#fff;padding:8px 14px;border-radius:6px 6px 0 0;font-weight:700;font-size:.85rem;text-transform:uppercase;letter-spacing:.04em'>Preparation de l\'Audit</div>
    <table style='font-family:Candara,Arial,sans-serif;width:100%;border-collapse:collapse;border:1px solid #c5d4f5;border-top:none;margin-bottom:14px;font-size:.88rem'>
      <tr><td style='padding:7px 12px;border-bottom:1px solid #e2e8f0'>Appreciation de la notification</td><td style='padding:7px 12px;border-bottom:1px solid #e2e8f0;text-align:right'>" . $noteSpan($notes['prep_notification']) . "</td></tr>
      <tr style='background:#f8fafc'><td style='padding:7px 12px'>Appreciation du plan d\'audit</td><td style='padding:7px 12px;text-align:right'>" . $noteSpan($notes['prep_plan']) . "</td></tr>
    </table>

    <div style='font-family:Candara,Arial,sans-serif;background:#23408F;color:#fff;padding:8px 14px;border-radius:6px 6px 0 0;font-weight:700;font-size:.85rem;text-transform:uppercase;letter-spacing:.04em'>Conduite de l\'Audit</div>
    <table style='font-family:Candara,Arial,sans-serif;width:100%;border-collapse:collapse;border:1px solid #c5d4f5;border-top:none;margin-bottom:14px;font-size:.88rem'>
      <tr><td style='padding:7px 12px;border-bottom:1px solid #e2e8f0'>Reunion d\'ouverture</td><td style='padding:7px 12px;border-bottom:1px solid #e2e8f0;text-align:right'>" . $noteSpan($notes['cond_ouverture']) . "</td></tr>
      <tr style='background:#f8fafc'><td style='padding:7px 12px;border-bottom:1px solid #e2e8f0'>Qualite des entretiens</td><td style='padding:7px 12px;border-bottom:1px solid #e2e8f0;text-align:right'>" . $noteSpan($notes['cond_entretiens']) . "</td></tr>
      <tr><td style='padding:7px 12px;border-bottom:1px solid #e2e8f0'>Connaissance des procedures</td><td style='padding:7px 12px;border-bottom:1px solid #e2e8f0;text-align:right'>" . $noteSpan($notes['cond_procedures']) . "</td></tr>
      <tr style='background:#f8fafc'><td style='padding:7px 12px;border-bottom:1px solid #e2e8f0'>Qualites generales inspecteur</td><td style='padding:7px 12px;border-bottom:1px solid #e2e8f0;text-align:right'>" . $noteSpan($notes['cond_qualites']) . "</td></tr>
      <tr><td style='padding:7px 12px;border-bottom:1px solid #e2e8f0'>Qualite de la communication</td><td style='padding:7px 12px;border-bottom:1px solid #e2e8f0;text-align:right'>" . $noteSpan($notes['cond_communication']) . "</td></tr>
      <tr style='background:#f8fafc'><td style='padding:7px 12px;border-bottom:1px solid #e2e8f0'>Classification des constats</td><td style='padding:7px 12px;border-bottom:1px solid #e2e8f0;text-align:right'>" . $noteSpan($notes['cond_classification']) . "</td></tr>
      <tr><td style='padding:7px 12px;border-bottom:1px solid #e2e8f0'>Pertinence des observations</td><td style='padding:7px 12px;border-bottom:1px solid #e2e8f0;text-align:right'>" . $noteSpan($notes['cond_pertinence']) . "</td></tr>
      <tr style='background:#f8fafc'><td style='padding:7px 12px'>Reunion de cloture</td><td style='padding:7px 12px;text-align:right'>" . $noteSpan($notes['cond_cloture']) . "</td></tr>
    </table>
    " . ($autresH ? "<div style='font-family:Candara,Arial,sans-serif;background:#f0f4ff;padding:10px 14px;border-radius:6px;font-size:.88rem;margin-bottom:8px'><strong>Autres appreciations :</strong><br>" . $autresH . "</div>" : '') . "
    <div style='font-family:Candara,Arial,sans-serif;margin-top:20px;padding-top:14px;border-top:2px solid #23408F'>
      <p style='margin:0;color:#555;font-size:.88rem'>Pour toute question, veuillez contacter la Cellule Inspection.</p>
      <p style='margin:4px 0 0;font-weight:700;color:#23408F'>Direction Generale - ANAC Gabon</p>
    </div>
  </div>
  <div style='font-family:Candara,Arial,sans-serif;background:#f7f9fc;padding:10px 22px;text-align:center;font-size:.74rem;color:#94a3b8;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 10px 10px'>
    <strong>Ceci est un message automatique - merci de ne pas repondre directement a cet email.</strong><br>
    Message genere le " . date('d/m/Y H:i') . " par le Systeme AGAI - ANAC Gabon
  </div>
</div>";
        $mailer = new Mailer();
        $dest = 'rufin.mbadinga@anac-gabon.com'; // test - production: qmanac@anac-gabon.com
        $mailer->sendWithAttachment(
            $dest,
            "[AGAI] QRE - {$nomorga} - Audit {$numAudit}",
            $bodyHtml,
            "L'operateur {$nomorga} vient de remplir son QRE pour l'audit {$numAudit}.",
            '', ''
        );
        Audit::log('mail', 'qre', "QRE audit #{$idaudit} envoye a $dest");
    }

    Audit::log('create', 'qre', "QRE soumis audit #$idaudit organisme #$idorga");
    $ok(['message' => 'Questionnaire enregistre avec succes.']);
    break;

// ----------------------------------------------------------------
// save_fichier : joindre un formulaire QRE deja rempli a la main (scan/photo)
// Alternative a la saisie en ligne : aucune note TB/B/M/TM individuelle
// n'est demandee, seul le document est archive.
case 'save_fichier':
    $idaudit   = (int) ($_POST['idaudit'] ?? 0);
    $idorga    = (int) ($_POST['idorga'] ?? 0);
    $dateQre   = trim((string) ($_POST['date_qre'] ?? date('Y-m-d')));
    $activites = trim((string) ($_POST['activites'] ?? ''));

    if ($idaudit <= 0 || $idorga <= 0) { $fail('Audit ou operateur invalide.'); break; }
    if (!$activites) { $fail('Activites auditees requises.'); break; }

    // Un operateur ne peut joindre un QRE que pour son propre organisme (anti-IDOR)
    if (!$isCiGlobal && $idorgaUser !== $idorga) {
        $fail('Action non autorisee pour cet operateur.'); break;
    }

    // Verifier que l audit a bien une lettre de notification (meme eligibilite que la saisie en ligne)
    $stA = $db->prepare("SELECT lettre_notification FROM audit WHERE idaudit=? AND lettre_notification IS NOT NULL");
    $stA->execute([$idaudit]);
    if (!$stA->fetch()) { $fail('Cet audit n\'est pas eligible (lettre de notification manquante).'); break; }

    if (empty($_FILES['fichier']) || ($_FILES['fichier']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $fail('Aucun fichier valide recu.'); break;
    }
    $file = $_FILES['fichier'];

    $maxSize = 10 * 1024 * 1024; // 10 Mo
    if ($file['size'] <= 0 || $file['size'] > $maxSize) {
        $fail('Fichier invalide ou trop volumineux (10 Mo maximum).'); break;
    }

    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $allowedMime = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
    if (!isset($allowedMime[$ext])) {
        $fail('Format non autorise. Utilisez PDF, JPG ou PNG.'); break;
    }
    // Verification du contenu reel du fichier (pas seulement l'extension declaree par le client)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeReel = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
    if ($finfo) { finfo_close($finfo); }
    if ($mimeReel !== $allowedMime[$ext]) {
        $fail('Le contenu du fichier ne correspond pas au format attendu.'); break;
    }

    $dirQre = STORAGE_PATH . '/qre';
    if (!is_dir($dirQre)) { @mkdir($dirQre, 0755, true); }

    // Nom de fichier genere par le serveur : jamais le nom fourni par le client
    $nomFichier = 'qre_' . $idaudit . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $cheminDest = $dirQre . '/' . $nomFichier;

    if (!is_uploaded_file($file['tmp_name']) || !move_uploaded_file($file['tmp_name'], $cheminDest)) {
        $fail('Echec de l\'enregistrement du fichier.'); break;
    }

    // Recuperer l'ancien fichier (le cas echeant) pour le supprimer apres succes de l'upsert
    $stOld = $db->prepare("SELECT fichier_joint FROM qre WHERE idaudit = ? AND idorga = ?");
    $stOld->execute([$idaudit, $idorga]);
    $ancien = $stOld->fetchColumn();

    $stU = $db->prepare(
        "INSERT INTO qre (idaudit, idorga, iduser, activites_auditees, date_qre, fichier_joint, created_at)
         VALUES (?,?,?,?,?,?,NOW())
         ON DUPLICATE KEY UPDATE
            iduser = VALUES(iduser), activites_auditees = VALUES(activites_auditees),
            date_qre = VALUES(date_qre), fichier_joint = VALUES(fichier_joint),
            created_at = NOW()"
    );
    $stU->execute([$idaudit, $idorga, $uid, $activites, $dateQre, $nomFichier]);

    // Nettoyage de l'ancien fichier seulement apres succes de l'enregistrement en base
    if ($ancien && is_file($dirQre . '/' . basename((string) $ancien))) {
        @unlink($dirQre . '/' . basename((string) $ancien));
    }

    Audit::log('upload', 'qre', "QRE joint (fichier scanne) audit #$idaudit organisme #$idorga - $nomFichier");
    $ok(['message' => 'Formulaire QRE joint avec succes.']);
    break;

// ----------------------------------------------------------------
// imprimer : vue HTML imprimable d'un QRE saisi EN LIGNE (pas de fichier joint)
// Reproduit fidelement la modale "Consultation QRE" du module /qre (meme
// structure, memes couleurs) pour que l'affichage soit identique, que l'on
// consulte depuis /qre ou depuis /archivage.
case 'imprimer':
    $idqre = (int) ($_GET['idqre'] ?? $_POST['idqre'] ?? 0);
    if ($idqre <= 0) { http_response_code(400); exit('Requete invalide.'); }

    $st = $db->prepare(
        "SELECT q.*, o.nomorga, a.num_audit, a.site_inspection
         FROM qre q
         JOIN organisme o ON o.idorga = q.idorga
         JOIN audit     a ON a.idaudit = q.idaudit
         WHERE q.idqre = ?"
    );
    $st->execute([$idqre]);
    $q = $st->fetch();
    if (!$q) { http_response_code(404); exit('Questionnaire introuvable.'); }

    // Meme regle d'acces que get/serve : un operateur ne consulte que son organisme
    if (!$isCiGlobal && $idorgaUser !== (int) $q['idorga']) {
        http_response_code(403); exit('Acces refuse.');
    }

    Audit::log('download', 'qre', "Consultation QRE saisi en ligne #$idqre");

    $QUESTIONS = [
        'prep_notification'   => "Appreciation de la notification",
        'prep_plan'            => "Appreciation du plan d'audit",
        'cond_ouverture'       => "Reunion d'ouverture",
        'cond_entretiens'      => "Qualite des entretiens",
        'cond_procedures'      => "Connaissance des procedures",
        'cond_qualites'        => "Qualites generales inspecteur",
        'cond_communication'   => "Qualite de la communication",
        'cond_classification'  => "Classification des constats",
        'cond_pertinence'      => "Pertinence des observations",
        'cond_cloture'         => "Reunion de cloture",
    ];
    $e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
    $fmt = function ($s) {
        $s = substr((string) $s, 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) { return '-'; }
        [$y, $m, $d] = explode('-', $s);
        return "$d/$m/$y";
    };
    $chkBoxV = function (string $v, string $target) {
        if ($v === $target) {
            return '<span style="display:inline-flex;align-items:center;justify-content:center;width:15px;height:15px;'
                 . 'background:#23408F;border-radius:2px;border:1.5px solid #23408F">'
                 . '<svg width="9" height="7" viewBox="0 0 9 7"><polyline points="1,3.5 3.5,6 8,1" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>';
        }
        return '<span style="display:inline-flex;align-items:center;justify-content:center;width:15px;height:15px;background:#fff;border-radius:2px;border:1.5px solid #9ca3af"></span>';
    };
    $buildSect = function (string $titre, array $fields) use ($q, $QUESTIONS, $e, $chkBoxV) {
        $t = '<table style="width:100%;border-collapse:collapse;margin-bottom:10px;border:1px solid #23408F">'
           . '<thead><tr><td colspan="5" style="background:#23408F;color:#fff;padding:6px 10px;font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em">' . $e($titre) . '</td></tr>'
           . '<tr style="background:#e8edf8">'
           . '<td style="padding:5px 10px;font-size:.72rem;font-weight:700;color:#23408F;border:1px solid #c5d4f5;width:52%">Question</td>'
           . '<td style="padding:5px 8px;font-size:.72rem;font-weight:700;color:#1E9C4B;border:1px solid #c5d4f5;text-align:center">Tres<br>Bonne</td>'
           . '<td style="padding:5px 8px;font-size:.72rem;font-weight:700;color:#23408F;border:1px solid #c5d4f5;text-align:center">Bonne</td>'
           . '<td style="padding:5px 8px;font-size:.72rem;font-weight:700;color:#b58a00;border:1px solid #c5d4f5;text-align:center">Mauvaise</td>'
           . '<td style="padding:5px 8px;font-size:.72rem;font-weight:700;color:#D32F2F;border:1px solid #c5d4f5;text-align:center">Tres<br>Mauvaise</td>'
           . '</tr></thead><tbody>';
        foreach (array_values($fields) as $idx => $key) {
            $v  = (string) ($q[$key] ?? '');
            $bg = ($idx % 2 === 0) ? 'background:#f9fafb' : 'background:#fff';
            $t .= '<tr style="' . $bg . '">'
                . '<td style="padding:6px 10px;font-size:.82rem;border:1px solid #e5e7eb;line-height:1.4;vertical-align:middle">' . $e($QUESTIONS[$key] ?? $key) . '</td>'
                . '<td style="text-align:center;border:1px solid #e5e7eb;padding:6px">' . $chkBoxV($v, 'TB') . '</td>'
                . '<td style="text-align:center;border:1px solid #e5e7eb;padding:6px">' . $chkBoxV($v, 'B') . '</td>'
                . '<td style="text-align:center;border:1px solid #e5e7eb;padding:6px">' . $chkBoxV($v, 'M') . '</td>'
                . '<td style="text-align:center;border:1px solid #e5e7eb;padding:6px">' . $chkBoxV($v, 'TM') . '</td>'
                . '</tr>';
        }
        return $t . '</tbody></table>';
    };
    $baner = defined('ASSETS_URL') ? ASSETS_URL . '/images/banierenteanac.png' : '';

    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>QRE ' . $e($q['num_audit'] ?? '') . '</title>'
       . '<style>@page{size:A4;margin:12mm}body{font-family:Candara,Arial,sans-serif;color:#2C3E50;font-size:.88rem;margin:0;padding:10px}</style></head><body>'
       // --- Reference
       . '<div style="text-align:right;font-size:.7rem;color:#888;margin-bottom:4px">IX-GEN-R3-F-I-011 &ndash; Fevrier 2024 Version 02</div>'
       // --- Banniere
       . '<div style="text-align:center;background:#fff;border:1px solid #d1d5db;border-radius:6px;padding:4px 8px;margin-bottom:10px">'
       . '<img src="' . $e($baner) . '" alt="ANAC Gabon" style="max-width:100%;max-height:90px;object-fit:contain;display:block;margin:0 auto"></div>'
       // --- Titre
       . '<div style="text-align:center;font-size:1.05rem;font-weight:900;text-transform:uppercase;color:#23408F;'
       . 'border:2px solid #23408F;padding:8px 14px;margin:0 0 8px;letter-spacing:.06em">Questionnaire de Retour d\'Experience</div>'
       // --- Intro
       . '<div style="font-size:.78rem;font-style:italic;color:#555;margin-bottom:8px;padding:0 4px">'
       . '(Le questionnaire de retour d\'experience a pour objectif de tirer les enseignements positifs et negatifs de la realisation de l\'audit. '
       . 'Il vise exclusivement l\'amelioration du systeme de supervision par l\'ANAC).</div>'
       . '<div style="border:1px solid #d1d5db;padding:8px 12px;font-size:.82rem;margin-bottom:12px;border-radius:4px;background:#f9fafb">'
       . 'Votre organisme a ete audite par les inspecteurs de l\'ANAC, nous vous remercions de nous faire part de votre appreciation sur le deroulement de l\'activite.</div>'
       // --- Tableau infos generales (avec en-tete bleu, identique au module QRE)
       . '<table style="width:100%;border-collapse:collapse;margin-bottom:10px;border:1px solid #23408F">'
       . '<thead><tr><td colspan="4" style="background:#23408F;color:#fff;padding:6px 10px;font-size:.78rem;font-weight:700;text-transform:uppercase;text-align:center;letter-spacing:.04em">'
       . 'INFORMATIONS GENERALES SUR L\'AUDITE</td></tr></thead><tbody>'
       . '<tr>'
       . '<td style="padding:6px 10px;font-weight:700;font-size:.78rem;border:1px solid #d1d5db;background:#f5f7fa;width:18%">Operateur :</td>'
       . '<td style="padding:6px 10px;font-size:.85rem;border:1px solid #d1d5db;font-weight:700;color:#23408F">' . $e($q['nomorga'] ?? '-') . '</td>'
       . '<td style="padding:6px 10px;font-weight:700;font-size:.78rem;border:1px solid #d1d5db;background:#f5f7fa;width:22%">Activite(s) auditee(s) :</td>'
       . '<td style="padding:6px 10px;font-size:.85rem;border:1px solid #d1d5db">' . $e($q['activites_auditees'] ?? '-') . '</td>'
       . '</tr><tr>'
       . '<td style="padding:6px 10px;font-weight:700;font-size:.78rem;border:1px solid #d1d5db;background:#f5f7fa">N Audit :</td>'
       . '<td style="padding:6px 10px;font-size:.85rem;border:1px solid #d1d5db;font-weight:700;color:#23408F">' . $e($q['num_audit'] ?? '-') . '</td>'
       . '<td style="padding:6px 10px;font-weight:700;font-size:.78rem;border:1px solid #d1d5db;background:#f5f7fa">Site / Lieu :</td>'
       . '<td style="padding:6px 10px;font-size:.85rem;border:1px solid #d1d5db">' . $e($q['site_inspection'] ?? '-') . '</td>'
       . '</tr><tr>'
       . '<td style="padding:6px 10px;font-weight:700;font-size:.78rem;border:1px solid #d1d5db;background:#f5f7fa">Date :</td>'
       . '<td style="padding:6px 10px;font-size:.85rem;border:1px solid #d1d5db" colspan="3">' . $e($fmt($q['date_qre'] ?? '')) . '</td>'
       . '</tr></tbody></table>'
       // --- Instruction
       . '<div style="text-align:center;font-style:italic;font-size:.8rem;color:#444;margin:6px 0 10px">'
       . '<em>Veuillez cocher la case correspondant a votre niveau d\'appreciation du deroulement de l\'audit.</em></div>'
       . $buildSect("PREPARATION DE L'AUDIT", ['prep_notification', 'prep_plan'])
       . $buildSect("CONDUITE DE L'AUDIT", ['cond_ouverture', 'cond_entretiens', 'cond_procedures', 'cond_qualites', 'cond_communication', 'cond_classification', 'cond_pertinence', 'cond_cloture'])
       // --- Autres appreciations
       . '<table style="width:100%;border-collapse:collapse;margin-bottom:12px">'
       . '<thead><tr><td style="background:#f0f4ff;border:1px solid #23408F;padding:6px 10px;font-size:.78rem;font-weight:700;text-transform:uppercase;color:#23408F">AUTRES APPRECIATIONS</td></tr></thead>'
       . '<tbody><tr><td style="border:1px solid #d1d5db;padding:10px 12px;min-height:50px;font-size:.84rem;color:#374151">'
       . (!empty($q['autres_appreciations']) ? $e($q['autres_appreciations']) : '<span style="color:#9ca3af">-</span>') . '</td></tr></tbody></table>'
       // --- Pied
       . '<div style="border-top:1px solid #e5e7eb;padding-top:8px;margin-top:4px">'
       . '<div style="font-size:.76rem;font-style:italic;color:#555;margin-bottom:3px">'
       . 'Nous vous remercions pour la cooperation et vous prions de retourner ce questionnaire a l\'adresse suivante : '
       . '<a href="mailto:qmanac@anac-gabon.com" style="color:#23408F;font-weight:700">qmanac@anac-gabon.com</a></div>'
       . '<div style="font-size:.76rem;font-style:italic;color:#555;margin-bottom:5px">'
       . 'Les reponses recues feront l\'objet d\'analyse periodique afin de determiner les opportunites d\'amelioration de l\'activite d\'audit des processus de certification et de surveillance des operateurs du secteur aerien.</div>'
       . '<div style="font-size:.71rem;color:#9ca3af;text-align:center">Soumis le ' . $e(!empty($q['created_at']) ? $fmt($q['created_at']) : '-') . (!empty($q['envoye_mail']) ? ' &middot; Envoye par mail' : '') . '</div></div>'
       . '</body></html>';
    exit;

// ----------------------------------------------------------------
// serve : lecture du fichier QRE joint (lecture seule, en fenetre/onglet)
case 'serve':
    $idqre = (int) ($_GET['idqre'] ?? $_POST['idqre'] ?? 0);
    if ($idqre <= 0) { http_response_code(400); exit('Requete invalide.'); }

    $st = $db->prepare("SELECT q.fichier_joint, q.idorga, a.num_audit FROM qre q JOIN audit a ON a.idaudit = q.idaudit WHERE q.idqre = ?");
    $st->execute([$idqre]);
    $row = $st->fetch();
    if (!$row) { http_response_code(404); exit('Document introuvable.'); }

    // Meme regle d'acces que pour les autres actions : un operateur ne consulte que son organisme
    if (!$isCiGlobal && $idorgaUser !== (int) $row['idorga']) {
        http_response_code(403); exit('Acces refuse.');
    }

    $fichier = trim((string) ($row['fichier_joint'] ?? ''));
    if ($fichier === '') { http_response_code(404); exit('Aucun fichier joint.'); }

    $chemin = STORAGE_PATH . '/qre/' . basename($fichier);
    if (!is_file($chemin)) { http_response_code(404); exit('Fichier introuvable sur le serveur.'); }

    Audit::log('download', 'qre', "Consultation QRE joint #$idqre");

    $ext  = strtolower(pathinfo($chemin, PATHINFO_EXTENSION));
    $mime = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'][$ext] ?? 'application/octet-stream';

    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="qre_' . preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $row['num_audit']) . '.' . $ext . '"');
    header('Content-Length: ' . filesize($chemin));
    header('X-Content-Type-Options: nosniff');
    readfile($chemin);
    exit;

// ----------------------------------------------------------------
// delete : supprimer un QRE (admin seulement)
case 'delete':
    if (!in_array($role, ['admin','chef_inspecteur'], true)) { $fail('Non autorise.'); break; }
    $idqre = (int) ($_POST['idqre'] ?? 0);
    $stF = $db->prepare("SELECT fichier_joint FROM qre WHERE idqre = ?");
    $stF->execute([$idqre]);
    $fJoint = $stF->fetchColumn();
    $db->prepare("DELETE FROM qre WHERE idqre=?")->execute([$idqre]);
    if ($fJoint && is_file(STORAGE_PATH . '/qre/' . basename((string) $fJoint))) {
        @unlink(STORAGE_PATH . '/qre/' . basename((string) $fJoint));
    }
    Audit::log('delete','qre',"QRE #$idqre supprime");
    $ok(['message'=>'QRE supprime.']);
    break;

default: $fail('Action inconnue.');
}} catch (Throwable $e) {
    error_log('qre: ' . $e->getMessage());
    $fail('Erreur technique : ' . $e->getMessage());
}