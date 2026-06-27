<?php
/**
 * Endpoint AJAX : QRE - Questionnaire de Retour d'Experience
 * Route : /api/qre
 * Actions : list (audits eligibles), save (soumettre QRE), get (lire QRE), delete
 * Eligibilite : audit doit avoir lettre_notification jointe
 * Acces : role=operateur => voit ses propres audits uniquement
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardApi('qre');

$db   = Database::getInstance();
$role = Rbac::role();
$uid  = (int) ($_SESSION['user_id'] ?? 0);

if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success'=>false,'message'=>'CSRF invalide.']); exit;
}

$action = trim((string) ($_POST['action'] ?? ''));
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
                q.idqre, q.created_at AS qre_date
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
    // Toutes les reponses QRE (selon droits)
    $isCI   = in_array($role, ['admin','chef_inspecteur','consultant'], true);
    $where  = ''; $params = [];
    if (!$isCI && $idorgaUser !== null) {
        $where = "WHERE q.idorga = ?"; $params = [$idorgaUser];
    } elseif (!$isCI && $myInsp !== null) {
        $where = "WHERE q.idaudit IN (SELECT idaudit FROM audit_equipe WHERE idinspecteur=? UNION SELECT idaudit FROM audit WHERE idresponsable_audit=?)";
        $params = [$myInsp, $myInsp];
    } elseif (!$isCI) {
        $ok(['stats'=>[], 'par_annee'=>[], 'par_type'=>[], 'par_note'=>[]]); break;
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
// delete : supprimer un QRE (admin seulement)
case 'delete':
    if (!in_array($role, ['admin','chef_inspecteur'], true)) { $fail('Non autorise.'); break; }
    $idqre = (int) ($_POST['idqre'] ?? 0);
    $db->prepare("DELETE FROM qre WHERE idqre=?")->execute([$idqre]);
    Audit::log('delete','qre',"QRE #$idqre supprime");
    $ok(['message'=>'QRE supprime.']);
    break;

default: $fail('Action inconnue.');
}} catch (Throwable $e) {
    error_log('qre: ' . $e->getMessage());
    $fail('Erreur technique : ' . $e->getMessage());
}