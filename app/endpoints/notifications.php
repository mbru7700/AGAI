<?php
/**
 * Endpoint AJAX : Notifications d'audit
 * Route : /api/notifications
 * - list     : audits avec revue complete (eligible notification)
 * - upload   : joindre la lettre (RA/Admin/CI uniquement)
 *              -> met a jour audit.lettre_notification + date_notification
 * - serve    : servir le fichier (GET)
 * - check_email  : verifier email operateur
 * - update_email : maj email operateur
 * - send_mail    : envoyer la lettre par mail avec PJ
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardApi('notifications');

$db   = Database::getInstance();
$role = Rbac::role();
$uid  = (int) ($_SESSION['user_id'] ?? 0);

// ---- Dossier de stockage des lettres (hors public) ----
$notifDir = STORAGE_PATH . '/notifications';
if (!is_dir($notifDir)) { @mkdir($notifDir, 0755, true); }

// ---- Action GET : servir le fichier (lecture seule, pas de CSRF) ----
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['serve'])) {
    $idaudit = (int) ($_GET['idaudit'] ?? 0);
    $st = $db->prepare(
        "SELECT lettre_notification, lettre_notification_original, idresponsable_audit, idorga
         FROM audit WHERE idaudit = ? AND lettre_notification IS NOT NULL"
    );
    $st->execute([$idaudit]);
    $row = $st->fetch();
    if (!$row) { http_response_code(404); exit('Fichier introuvable.'); }
    // Verifier acces
    $myInsp = null;
    $ri = $db->prepare("SELECT idinspecteur FROM inspecteur WHERE iduser = ? LIMIT 1");
    $ri->execute([$uid]);
    $ri = $ri->fetch();
    if ($ri) $myInsp = (int) $ri['idinspecteur'];
    if (!in_array($role, ['admin','chef_inspecteur','consultant','operateur'], true)) {
        if ($myInsp !== null) {
            $stA = $db->prepare(
                "SELECT 1 FROM audit WHERE idaudit=? AND (idresponsable_audit=? OR idchef_inspecteur=?)
                 UNION SELECT 1 FROM audit_equipe WHERE idaudit=? AND idinspecteur=?"
            );
            $stA->execute([$idaudit,$myInsp,$myInsp,$idaudit,$myInsp]);
            if (!$stA->fetch()) { http_response_code(403); exit('Acces refuse.'); }
        }
    }
    $path = $notifDir . '/' . basename((string) $row['lettre_notification']);
    if (!file_exists($path)) { http_response_code(404); exit('Fichier absent.'); }
    $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mime = match($ext) {
        'pdf'  => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'doc'  => 'application/msword',
        default => 'application/octet-stream',
    };
    $orig = $row['lettre_notification_original'] ?? basename($path);
    $dl   = isset($_GET['dl']);
    header('Content-Type: ' . $mime);
    header('Content-Disposition: ' . ($dl ? 'attachment' : 'inline') . '; filename="' . rawurlencode($orig) . '"');
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

// Inspecteur de l'utilisateur connecte
$myInsp = null;
$stI = $db->prepare("SELECT idinspecteur FROM inspecteur WHERE iduser = ? LIMIT 1");
$stI->execute([$uid]);
$ri = $stI->fetch();
if ($ri) $myInsp = (int) $ri['idinspecteur'];

// Operateur connecte (idorga depuis users)
$idorgaUser = null;
$stOrg = $db->prepare("SELECT idorga FROM users WHERE iduser = ? LIMIT 1");
$stOrg->execute([$uid]);
$orgRow = $stOrg->fetch();
if ($orgRow && !empty($orgRow['idorga'])) $idorgaUser = (int) $orgRow['idorga'];

// Sous-requete nb_revues (meme logique que mes_audits)
$nb_sql = "(CASE
    WHEN (SELECT COUNT(*) FROM revue_documentaire rd_ra
          WHERE rd_ra.idaudit=a.idaudit
            AND rd_ra.idinspecteur=a.idresponsable_audit
            AND (rd_ra.contexte_objectif IS NOT NULL OR rd_ra.fichier_joint IS NOT NULL)) > 0
    THEN (SELECT COUNT(DISTINCT ae3.idinspecteur) FROM audit_equipe ae3 WHERE ae3.idaudit=a.idaudit)
    ELSE (SELECT COUNT(*) FROM revue_documentaire rd_ind
          WHERE rd_ind.idaudit=a.idaudit
            AND (rd_ind.contexte_objectif IS NOT NULL OR rd_ind.fichier_joint IS NOT NULL))
    END)";

try { switch ($action) {

// ----------------------------------------------------------------
case 'list':
    $isCI   = in_array($role, ['admin','chef_inspecteur','consultant'], true);
    $isOper = ($role === 'operateur');
    $where  = ''; $params = [$myInsp ?? 0];

    if ($isCI) {
        // Admin/CI/Consultant : voit tout
        $where = ''; $params = [$myInsp ?? 0];
    } elseif ($isOper && $idorgaUser !== null) {
        // Operateur : voit uniquement les audits de son organisme
        $where  = 'AND a.idorga = ?';
        $params = [0, $idorgaUser]; // 0 = myInsp fictif (est_ra sera 0)
    } elseif ($myInsp !== null) {
        // RA / Inspecteur : voit ses propres audits (ou il est RA ou membre equipe)
        $where  = "AND (a.idresponsable_audit=? OR a.idaudit IN
                  (SELECT ae.idaudit FROM audit_equipe ae WHERE ae.idinspecteur=?))";
        $params = [$myInsp ?? 0, $myInsp, $myInsp];
    } else {
        $where = 'AND 1=0'; $params = [0];
    }
    $rows = $db->execute(
        "SELECT a.idaudit, a.num_audit, a.type_activite, a.cadre, a.statut,
                a.date_previsionnelle, a.date_notification,
                a.lettre_notification, a.lettre_notification_original,
                a.lettre_notif_envoi_mail,
                o.idorga, o.nomorga, o.emailorga,
                TRIM(CONCAT(COALESCE(ir.preninspect,''),' ',COALESCE(ir.nominspecteur,''))) AS ra_nom,
                (a.idresponsable_audit = COALESCE(?,0)) AS est_ra,
                $nb_sql AS nb_revues,
                (SELECT COUNT(DISTINCT ae2.idinspecteur) FROM audit_equipe ae2 WHERE ae2.idaudit=a.idaudit) AS nb_equipe
         FROM audit a
         LEFT JOIN organisme  o  ON o.idorga       = a.idorga
         LEFT JOIN inspecteur ir ON ir.idinspecteur = a.idresponsable_audit
         WHERE 1=1 $where
         ORDER BY a.idaudit DESC",
        $params
    )->fetchAll();
    // Garder seulement revues completes
    $rows = array_values(array_filter($rows, function($r) {
        $ne = (int)($r['nb_equipe'] ?? 0);
        $nr = (int)($r['nb_revues'] ?? 0);
        return $ne > 0 && $nr >= $ne;
    }));
    $ok(['data' => $rows]);
    break;

// ----------------------------------------------------------------
case 'upload':
    // Verifier droit : RA de l'audit OU admin/CI
    $idaudit = (int) ($_POST['idaudit'] ?? 0);
    if ($idaudit <= 0) { $fail('Audit invalide.'); break; }
    $canUpload = in_array($role, ['admin','chef_inspecteur'], true);
    if (!$canUpload && $myInsp !== null) {
        $stRa = $db->prepare("SELECT 1 FROM audit WHERE idaudit=? AND idresponsable_audit=?");
        $stRa->execute([$idaudit, $myInsp]);
        $canUpload = (bool) $stRa->fetch();
    }
    if (!$canUpload) { $fail('Seul le RA, un CI ou un Admin peut joindre la lettre.'); break; }
    if (empty($_FILES['fichier_notif']) || $_FILES['fichier_notif']['error'] !== UPLOAD_ERR_OK) {
        $fail('Aucun fichier selectionne ou erreur de telechargement.'); break;
    }
    $f    = $_FILES['fichier_notif'];
    $orig = basename($f['name']);
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf','doc','docx'], true)) {
        $fail('Format non autorise. Utilisez PDF, DOC ou DOCX.'); break;
    }
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
    // Supprimer l'ancien fichier si present
    $stOld = $db->prepare("SELECT lettre_notification FROM audit WHERE idaudit=?");
    $stOld->execute([$idaudit]);
    $old = $stOld->fetch();
    if ($old && $old['lettre_notification']) {
        $oldPath = $notifDir . '/' . basename($old['lettre_notification']);
        if (file_exists($oldPath)) @unlink($oldPath);
    }
    // Stocker le nouveau fichier
    $stored = 'notif_' . $idaudit . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest   = $notifDir . '/' . $stored;
    if (!move_uploaded_file($f['tmp_name'], $dest)) { $fail('Echec de l\'enregistrement.'); break; }
    // Mettre a jour la table audit
    $db->prepare(
        "UPDATE audit SET lettre_notification=?, lettre_notification_original=?,
                          lettre_notif_envoi_mail=0, date_notification=CURDATE()
         WHERE idaudit=?"
    )->execute([$stored, $orig, $idaudit]);
    Audit::log('upload', 'notifications', "Lettre notification audit #$idaudit - $orig");
    $ok(['message' => 'Lettre enregistree. Date de notification mise a jour.', 'fichier' => $stored]);
    break;

// ----------------------------------------------------------------
case 'check_email':
    $idorga = (int) ($_POST['idorga'] ?? 0);
    $st = $db->prepare("SELECT idorga, nomorga, emailorga FROM organisme WHERE idorga=?");
    $st->execute([$idorga]);
    $org = $st->fetch();
    if (!$org) { $fail('Operateur introuvable.'); break; }
    $email = trim($org['emailorga'] ?? '');
    $ok([
        'nomorga'   => $org['nomorga'],
        'emailorga' => $email,
        'has_email' => ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)),
    ]);
    break;

// ----------------------------------------------------------------
case 'update_email':
    $idorga = (int) ($_POST['idorga'] ?? 0);
    $email  = trim((string) ($_POST['emailorga'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $fail('Adresse email invalide.'); break; }
    $db->prepare("UPDATE organisme SET emailorga=? WHERE idorga=?")->execute([$email, $idorga]);
    Audit::log('update', 'notifications', "Email operateur #$idorga mis a jour : $email");
    $ok(['message' => 'Email mis a jour.']);
    break;

// ----------------------------------------------------------------
case 'send_mail':
    $idaudit = (int) ($_POST['idaudit'] ?? 0);
    $email   = trim((string) ($_POST['email_dest'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $fail('Adresse email invalide.'); break; }

    // Infos audit + operateur + RA
    $stA = $db->prepare(
        "SELECT a.idaudit, a.num_audit, a.type_activite, a.date_previsionnelle,
                a.lettre_notification, a.lettre_notification_original,
                o.nomorga, o.idorga,
                TRIM(CONCAT(COALESCE(ir.preninspect,''),' ',COALESCE(ir.nominspecteur,''))) AS ra_nom
         FROM audit a
         LEFT JOIN organisme  o  ON o.idorga       = a.idorga
         LEFT JOIN inspecteur ir ON ir.idinspecteur = a.idresponsable_audit
         WHERE a.idaudit = ? AND a.lettre_notification IS NOT NULL"
    );
    $stA->execute([$idaudit]);
    $aud = $stA->fetch();
    if (!$aud) { $fail('Audit ou lettre introuvable.'); break; }
    $filePath = $notifDir . '/' . basename($aud['lettre_notification']);
    if (!file_exists($filePath)) { $fail('Fichier absent du serveur.'); break; }

    // Membres de l equipe (RA en premier, puis autres)
    $stEq = $db->prepare(
        "SELECT DISTINCT
                TRIM(CONCAT(COALESCE(i.preninspect,''),' ',COALESCE(i.nominspecteur,''))) AS nom,
                i.trigr_inspecteur AS trigr,
                d.nomdomaine,
                (ae.idinspecteur = a.idresponsable_audit) AS est_ra
         FROM audit_equipe ae
         JOIN audit a ON a.idaudit = ae.idaudit
         JOIN inspecteur i ON i.idinspecteur = ae.idinspecteur
         LEFT JOIN domaine d ON d.iddomaine = ae.iddomaine
         WHERE ae.idaudit = ?
         ORDER BY est_ra DESC, i.nominspecteur"
    );
    $stEq->execute([$idaudit]);
    $membres = $stEq->fetchAll();

    // Tableau equipe HTML
    $lignesEquipe = '';
    foreach ($membres as $m) {
        $raStyle = $m['est_ra'] ? 'color:#D32F2F;font-weight:700' : 'color:#2C3E50';
        $raTag   = $m['est_ra'] ? ' <span style="background:#f8d7da;color:#842029;border-radius:4px;padding:1px 6px;font-size:.78em">R.A</span>' : '';
        $nom     = htmlspecialchars(trim($m['nom']), ENT_QUOTES, 'UTF-8');
        $trigr   = htmlspecialchars($m['trigr'] ?? '', ENT_QUOTES, 'UTF-8');
        $dom     = htmlspecialchars($m['nomdomaine'] ?? '-', ENT_QUOTES, 'UTF-8');
        $lignesEquipe .= "<tr>
          <td style='padding:8px 12px;border:1px solid #e2e8f0;{$raStyle}'>{$nom}{$raTag}</td>
          <td style='padding:8px 12px;border:1px solid #e2e8f0;color:#555;font-family:monospace;font-size:.88em'>{$trigr}</td>
          <td style='padding:8px 12px;border:1px solid #e2e8f0;color:#23408F'>{$dom}</td>
        </tr>";
    }
    $tableauEquipe = $lignesEquipe
        ? "<p style='margin:16px 0 6px;font-weight:700;color:#2C3E50'>Membres de l'equipe d'audit :</p>
           <table style='width:100%;border-collapse:collapse;margin-bottom:16px;font-size:.9rem'>
             <thead><tr>
               <th style='background:#23408F;color:#fff;padding:8px 12px;text-align:left;border:1px solid #1b3576'>Inspecteur</th>
               <th style='background:#23408F;color:#fff;padding:8px 12px;text-align:left;border:1px solid #1b3576'>Trigramme</th>
               <th style='background:#23408F;color:#fff;padding:8px 12px;text-align:left;border:1px solid #1b3576'>Domaine</th>
             </tr></thead>
             <tbody>{$lignesEquipe}</tbody>
           </table>"
        : '';

    $typeLabel = [
        'audit' => 'Audit', 'inspection_programmee' => 'Inspection programmee',
        'inspection_non_programmee' => 'Inspection non programmee',
        'demonstration' => 'Demonstration', 'test' => 'Test', 'investigation' => 'Investigation',
    ][$aud['type_activite']] ?? $aud['type_activite'];
    $datePrev = $aud['date_previsionnelle'] ? date('d/m/Y', strtotime($aud['date_previsionnelle'])) : '-';
    $subject  = '[AGAI] Lettre de notification - ' . $aud['num_audit'];

    $bodyHtml = "
<div style='font-family:Candara,Arial,sans-serif;font-size:11pt;color:#1e293b;max-width:660px;margin:0 auto'>
  <div style='margin:0;padding:0;line-height:0'>
    <img src='https://anacgabon.org/wp-content/uploads/2024/10/banierenteanac.png' alt='ANAC Gabon' style='width:100%;max-width:660px;display:block;border:none'>
  </div>
  <div style='background:linear-gradient(135deg,#23408F,#1b3576);padding:18px 28px;text-align:center'>
    <h2 style='color:#fff;margin:0;font-size:1.1rem;letter-spacing:.03em'>AGENCE NATIONALE DE L'AVIATION CIVILE</h2>
    <p style='color:rgba(255,255,255,.8);margin:4px 0 0;font-size:.85rem'>Systeme de Surveillance Continue de la Securite Aerienne - AGAI</p>
  </div>
  <div style='background:#fff;padding:28px;border:1px solid #e2e8f0;border-top:none'>
    <p style='margin-bottom:12px'>Monsieur / Madame,</p>
    <p>Nous avons l'honneur de vous notifier, par le present courrier electronique dont la lettre officielle est jointe en piece jointe, l'acte de supervision aeronautique suivant :</p>
    <table style='width:100%;border-collapse:collapse;margin:18px 0;font-size:.93rem'>
      <tr style='background:#f0f4ff'>
        <td style='padding:9px 14px;font-weight:700;border:1px solid #e2e8f0;width:40%'>Reference</td>
        <td style='padding:9px 14px;border:1px solid #e2e8f0;font-weight:800;color:#23408F'>{$aud['num_audit']}</td>
      </tr>
      <tr>
        <td style='padding:9px 14px;font-weight:700;border:1px solid #e2e8f0;background:#f8fafc'>Nature</td>
        <td style='padding:9px 14px;border:1px solid #e2e8f0'>$typeLabel</td>
      </tr>
      <tr style='background:#f0f4ff'>
        <td style='padding:9px 14px;font-weight:700;border:1px solid #e2e8f0'>Date</td>
        <td style='padding:9px 14px;border:1px solid #e2e8f0'>$datePrev</td>
      </tr>
      <tr>
        <td style='padding:9px 14px;font-weight:700;border:1px solid #e2e8f0;background:#f8fafc'>Operateur concerne</td>
        <td style='padding:9px 14px;border:1px solid #e2e8f0'>{$aud['nomorga']}</td>
      </tr>
    </table>
    {$tableauEquipe}
    <div style='background:#fff8e0;border-left:4px solid #F3C300;padding:12px 16px;border-radius:0 8px 8px 0;margin:16px 0;font-size:.9rem'>
      <strong>Note importante :</strong> Le document physique original vous sera achemine par voie officielle par les agents coursiers de l'ANAC Gabon. Nous vous prions de bien vouloir en accuser reception.
    </div>
    <p>Nous vous saurions gre de prendre toutes les dispositions necessaires en vue de cet acte de supervision et de vous assurer que vos dossiers techniques et operationnels sont a jour.</p>
    <p>Pour toute question ou information complementaire, veuillez contacter la Cellule Inspection.</p>
    <p style='margin-top:24px'>Recevez, Monsieur / Madame, l'assurance de notre consideration distinguee.</p>
    <div style='margin-top:24px;padding-top:16px;border-top:3px solid #23408F'>
      <p style='margin:0;font-weight:700;color:#23408F;font-size:1rem'>Direction Generale</p>
      <p style='margin:3px 0;color:#555'>Agence Nationale de l'Aviation Civile - ANAC Gabon</p>
    </div>
  </div>
  <div style='background:#f7f9fc;padding:12px 28px;text-align:center;font-size:.76rem;color:#94a3b8;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 10px 10px'>
    <strong>Ceci est un message automatique - merci de ne pas repondre directement a cet email.</strong><br>
    Message genere le " . date('d/m/Y') . " par le Systeme AGAI - ANAC Gabon
  </div>
</div>";

    $altBody  = 'ANAC Gabon - Lettre de notification : ' . $aud['num_audit'] . ' - ' . $typeLabel . ' - ' . $datePrev . ' - ' . $aud['nomorga'] . '. Ceci est un message automatique.';
    $fileName = $aud['lettre_notification_original'] ?? basename($filePath);
    $mailer   = new Mailer();
    $sent     = $mailer->sendWithAttachment($email, $subject, $bodyHtml, $altBody, $filePath, $fileName);
    if (!$sent) { $fail("Echec de l'envoi. Verifiez la configuration SMTP."); break; }
    $db->prepare("UPDATE audit SET lettre_notif_envoi_mail=1 WHERE idaudit=?")->execute([$idaudit]);
    Audit::log('mail', 'notifications', "Notif audit #{$idaudit} envoyee a $email");
    $ok(['message' => 'Lettre envoyee avec succes a ' . $email . '.']);
    break;

default: $fail('Action inconnue.');
}} catch (Throwable $e) {
    error_log('notifications: ' . $e->getMessage());
    $fail('Erreur technique : ' . $e->getMessage());
}