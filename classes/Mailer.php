<?php
/**
 * Classe Mailer - Gestion des emails avec PHPMailer
 * @package AGAI
 * @author  ANAC Gabon
 */
class Mailer
{
    private $mail;

    public function __construct()
    {
        require_once MAIL_PATH . '/PHPMailer/src/PHPMailer.php';
        require_once MAIL_PATH . '/PHPMailer/src/SMTP.php';
        require_once MAIL_PATH . '/PHPMailer/src/Exception.php';

        $this->mail = new PHPMailer\PHPMailer\PHPMailer(true);

        try {
            $this->mail->isSMTP();
            $this->mail->Host       = MAIL_HOST;
            $this->mail->SMTPAuth   = true;
            $this->mail->Username   = MAIL_USERNAME;
            $this->mail->Password   = MAIL_PASSWORD;
            $this->mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $this->mail->Port       = MAIL_PORT;
            $this->mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
            $this->mail->CharSet    = 'UTF-8';
            $this->mail->Encoding   = 'base64';
            $this->mail->isHTML(true);
        } catch (Exception $e) {
            error_log('Mailer construct : ' . $e->getMessage());
        }
    }

    /* ------------------------------------------------------------------ */
    /* Banniere commune en haut de tous les mails                         */
    /* ------------------------------------------------------------------ */
    private function bannerHtml(): string
    {
        return "<div style='margin:0;padding:0;line-height:0'>"
             . "<img src='https://anacgabon.org/wp-content/uploads/2024/10/banierenteanac.png' "
             . "alt='ANAC Gabon' style='width:100%;max-width:660px;display:block;border:none'>"
             . "</div>";
    }

    /* ------------------------------------------------------------------ */
    /* Pied de page commun                                                 */
    /* ------------------------------------------------------------------ */
    private function footerHtml(): string
    {
        return "<div style='background:#f7f9fc;padding:10px 28px;text-align:center;font-size:.76rem;"
             . "color:#94a3b8;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 10px 10px'>"
             . "<strong>Ceci est un message automatique - merci de ne pas repondre directement a cet email.</strong><br>"
             . "Message genere le " . date('d/m/Y') . " par le Systeme AGAI - ANAC Gabon"
             . "</div>";
    }

    /* ------------------------------------------------------------------ */
    /* OTP / 2FA                                                           */
    /* ------------------------------------------------------------------ */
    public function sendOTP($email, $otp)
    {
        try {
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();
            $this->mail->addAddress($email);
            $this->mail->Subject = 'Code de securite AGAI - ' . date('d/m/Y');
            $this->mail->Body    = $this->getOTPTemplate($otp);
            $this->mail->AltBody = "Votre code de securite est : $otp";
            return $this->mail->send();
        } catch (\Throwable $e) {
            error_log('Erreur envoi OTP : ' . $e->getMessage());
            return false;
        }
    }

    private function getOTPTemplate($otp)
    {
        $banner = $this->bannerHtml();
        $footer = $this->footerHtml();
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>
        body{font-family:Candara,Arial,sans-serif;background:#f5f7fa;margin:0;padding:0}
        .container{max-width:660px;margin:20px auto;background:#fff;border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,.1);overflow:hidden}
        .header{background:#23408F;color:#fff;padding:24px 28px;text-align:center}
        .header h2{margin:0;font-weight:700;font-size:1.1rem}
        .header p{margin:5px 0 0;opacity:.8;font-size:.88rem}
        .body{padding:28px}
        .code{background:#e8f0fe;padding:20px;text-align:center;border-radius:8px;margin:20px 0}
        .code span{font-size:36px;font-weight:700;color:#23408F;letter-spacing:8px}
        </style></head><body>
        <div class='container'>
          $banner
          <div class='header'><h2>AGAI - Securite</h2><p>Code d'authentification a deux facteurs</p></div>
          <div class='body'>
            <p>Bonjour,</p>
            <p>Utilisez le code ci-dessous pour vous connecter a l'application AGAI :</p>
            <div class='code'><span>$otp</span></div>
            <p><small>Ce code est valable <strong>10 minutes</strong>. Ne le partagez avec personne.</small></p>
            <p><small>Si vous n'etes pas a l'origine de cette demande, ignorez cet email.</small></p>
          </div>
          $footer
        </div></body></html>";
    }

    /* ------------------------------------------------------------------ */
    /* Designation inspection / audit                                      */
    /* ------------------------------------------------------------------ */
    public function sendNotifAudit(string $toEmail, string $toName, array $params): bool
    {
        try {
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();
            $this->mail->addAddress($toEmail, $toName);
            $this->mail->Subject = 'Designation - Acte de supervision ' . ($params['num_audit'] ?? '');
            $this->mail->Body    = $this->getNotifAuditTemplate($params);
            $this->mail->AltBody = 'Bonjour ' . $toName . ', vous etes designe pour un acte de supervision. Connectez-vous a AGAI : ' . ($params['agai_url'] ?? '');
            return $this->mail->send();
        } catch (\Throwable $e) {
            error_log('Erreur sendNotifAudit vers ' . $toEmail . ' : ' . $e->getMessage());
            return false;
        }
    }

    /* ------------------------------------------------------------------ */
    /* Notification au directeur fonctionnel d'un inspecteur en mission    */
    /* ------------------------------------------------------------------ */
    public function sendNotifDirecteur(string $toEmail, string $toName, array $params): bool
    {
        try {
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();
            $this->mail->addAddress($toEmail, $toName !== '' ? $toName : $toEmail);
            $this->mail->Subject = 'Information - Designation d\'un inspecteur pour une activite de supervision '
                . ($params['num_audit'] ?? '');
            $this->mail->Body    = $this->getNotifDirecteurTemplate($params);
            $insp = $params['inspecteur'] ?? 'un inspecteur';
            $this->mail->AltBody = 'Cher collaborateur, ' . $insp
                . ' a ete designe pour une activite de supervision le ' . ($params['date_prev'] ?? '') . '.';
            return $this->mail->send();
        } catch (\Throwable $e) {
            error_log('Erreur sendNotifDirecteur vers ' . $toEmail . ' : ' . $e->getMessage());
            return false;
        }
    }

    private function getNotifDirecteurTemplate(array $p): string
    {
        $banner    = $this->bannerHtml();
        $footer    = $this->footerHtml();
        $directeur = htmlspecialchars($p['directeur']  ?? '', ENT_QUOTES, 'UTF-8');
        $insp      = htmlspecialchars($p['inspecteur'] ?? '', ENT_QUOTES, 'UTF-8');
        $domaine   = htmlspecialchars($p['domaine']    ?? '', ENT_QUOTES, 'UTF-8');
        $datePrev  = htmlspecialchars($p['date_prev']  ?? '', ENT_QUOTES, 'UTF-8');
        $oper      = htmlspecialchars($p['operateur']  ?? '', ENT_QUOTES, 'UTF-8');
        $site      = htmlspecialchars($p['site']       ?? '', ENT_QUOTES, 'UTF-8');
        $num       = htmlspecialchars($p['num_audit']  ?? '', ENT_QUOTES, 'UTF-8');
        $ciNom     = htmlspecialchars($p['ci_nom']     ?? '', ENT_QUOTES, 'UTF-8');

        $domLigne  = $domaine !== '' ? ' dans le(s) domaine(s) <strong>' . $domaine . '</strong>' : '';

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>'
            . '<body style="font-family:Candara,Arial,sans-serif;background:#f5f7fa;margin:0">'
            . '<div style="max-width:660px;margin:24px auto;background:#fff;border-radius:14px;box-shadow:0 4px 18px rgba(0,0,0,.09);overflow:hidden">'
            . $banner
            . '<div style="background:#23408F;color:#fff;padding:22px 28px">'
            .   '<h2 style="margin:0 0 4px;font-size:1.1rem">Information - Activite de supervision</h2>'
            .   '<p style="margin:0;opacity:.85;font-size:.88rem">Agence Nationale de l\'Aviation Civile - Gabon</p>'
            . '</div>'
            . '<div style="padding:28px;color:#2C3E50">'
            .   '<p>Cher Collaborateur, ' . ($directeur !== '' ? '<strong>' . $directeur . '</strong>' : 'Directeur') . ',</p>'
            .   '<p>Monsieur/Madame <strong>' . $insp . '</strong>' . $domLigne
            .   ' a ete designe(e) pour une activite de supervision prevue le <strong>' . $datePrev . '</strong>.</p>'
            .   '<div style="background:#f7f9fc;border-left:4px solid #23408F;border-radius:8px;padding:12px 16px;margin:16px 0">'
            .     ($num !== ''      ? '<div><strong>Reference :</strong> ' . $num . '</div>' : '')
            .     '<div><strong>Inspecteur designe :</strong> ' . $insp . '</div>'
            .     ($domaine !== ''  ? '<div><strong>Domaine(s) :</strong> ' . $domaine . '</div>' : '')
            .     ($datePrev !== '' ? '<div><strong>Date previsionnelle :</strong> ' . $datePrev . '</div>' : '')
            .     ($oper !== ''     ? '<div><strong>Operateur :</strong> ' . $oper . '</div>' : '')
            .     ($site !== ''     ? '<div><strong>Site / Lieu d\'inspection :</strong> ' . $site . '</div>' : '')
            .   '</div>'
            .   '<p>Cette information vous est communiquee au titre de votre fonction d\'encadrement, afin que vous soyez '
            .   'informe(e) de la participation de votre collaborateur a cette activite de supervision.</p>'
            .   '<div style="margin-top:24px;padding-top:16px;border-top:1px solid #eef1f6">'
            .     '<p style="margin:0">Cordialement,</p>'
            .     '<p style="margin:6px 0 0;font-weight:700;color:#23408F">Le Chef Inspecteur,<br>' . $ciNom . '</p>'
            .   '</div>'
            . '</div>'
            . $footer
            . '</div></body></html>';
    }

    private function getNotifAuditTemplate(array $p): string
    {
        $banner  = $this->bannerHtml();
        $footer  = $this->footerHtml();
        $num     = htmlspecialchars($p['num_audit']     ?? '', ENT_QUOTES, 'UTF-8');
        $type    = htmlspecialchars($p['type_activite'] ?? '', ENT_QUOTES, 'UTF-8');
        $cadre   = htmlspecialchars($p['cadre']         ?? '', ENT_QUOTES, 'UTF-8');
        $mois    = htmlspecialchars($p['mois_annee']    ?? '', ENT_QUOTES, 'UTF-8');
        $oper    = htmlspecialchars($p['operateur']     ?? '', ENT_QUOTES, 'UTF-8');
        $site    = htmlspecialchars($p['site']          ?? '', ENT_QUOTES, 'UTF-8');
        $ciNom   = htmlspecialchars($p['ci_nom']        ?? '', ENT_QUOTES, 'UTF-8');
        $raNom   = htmlspecialchars($p['ra_nom']        ?? '', ENT_QUOTES, 'UTF-8');
        $destNom = htmlspecialchars($p['dest_nom']      ?? '', ENT_QUOTES, 'UTF-8');
        $agaiUrl = htmlspecialchars($p['agai_url']      ?? '', ENT_QUOTES, 'UTF-8');
        $equipe  = $p['equipe'] ?? [];
        $lignes  = '';
        foreach ($equipe as $m) {
            $style = !empty($m['est_resp']) ? 'color:#D32F2F;font-weight:700' : 'color:#2C3E50';
            $role  = !empty($m['est_resp']) ? ' <em>(R.A)</em>' : '';
            $doms  = htmlspecialchars(implode(', ', $m['domaines'] ?? []), ENT_QUOTES, 'UTF-8');
            $nom   = htmlspecialchars(trim(($m['prenom'] ?? '') . ' ' . ($m['nom'] ?? '')), ENT_QUOTES, 'UTF-8');
            $lignes .= '<tr><td style="padding:8px 12px;border-bottom:1px solid #eee;' . $style . '">'
                . $nom . $role . '</td>'
                . '<td style="padding:8px 12px;border-bottom:1px solid #eee">' . $doms . '</td></tr>';
        }
        $raLine  = $raNom ? '<p style="color:#D32F2F;font-weight:600">R.A : ' . $raNom . '</p>' : '';
        $lienBtn = $agaiUrl ? '<p style="text-align:center;margin:18px 0"><a href="' . $agaiUrl
            . '" style="background:#23408F;color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;font-weight:700">Se connecter a AGAI</a></p>' : '';
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>'
            . '<body style="font-family:Candara,Arial,sans-serif;background:#f5f7fa;margin:0">'
            . '<div style="max-width:660px;margin:24px auto;background:#fff;border-radius:14px;box-shadow:0 4px 18px rgba(0,0,0,.09);overflow:hidden">'
            . $banner
            . '<div style="background:#23408F;color:#fff;padding:22px 28px">'
            .   '<h2 style="margin:0 0 4px;font-size:1.1rem">Acte de supervision - AGAI</h2>'
            .   '<p style="margin:0;opacity:.85;font-size:.88rem">Agence Nationale de l\'Aviation Civile - Gabon</p>'
            . '</div>'
            . '<div style="padding:28px">'
            .   '<p>Bonjour <strong>' . $destNom . '</strong>,</p>'
            .   '<p>Vous etes designe pour mener les activites de supervision du mois de <strong>' . $mois . '</strong>.</p>'
            .   '<div style="background:#f7f9fc;border-left:4px solid #23408F;border-radius:8px;padding:10px 14px;margin:14px 0">'
            .     '<div><strong>Reference :</strong> ' . $num . '</div>'
            .     '<div><strong>Nature :</strong> ' . $type . '</div>'
            .     '<div><strong>Cadre :</strong> ' . $cadre . '</div>'
            .     '<div><strong>Operateur :</strong> ' . $oper . '</div>'
            .     '<div><strong>Site :</strong> ' . $site . '</div>'
            .   '</div>'
            .   $raLine
            .   '<table style="width:100%;border-collapse:collapse;margin:14px 0">'
            .     '<thead><tr>'
            .       '<th style="background:#23408F;color:#fff;padding:9px 12px;text-align:left">Inspecteur</th>'
            .       '<th style="background:#23408F;color:#fff;padding:9px 12px;text-align:left">Domaine(s)</th>'
            .     '</tr></thead>'
            .     '<tbody>' . $lignes . '</tbody>'
            .   '</table>'
            .   $lienBtn
            .   '<div style="margin-top:24px;padding-top:16px;border-top:1px solid #eef1f6">'
            .     '<p style="margin:0">Cordialement,</p>'
            .     '<p style="margin:6px 0 0;font-weight:700;color:#23408F">Le Chef Inspecteur,<br>' . $ciNom . '</p>'
            .   '</div>'
            . '</div>'
            . $footer
            . '</div></body></html>';
    }

    /* ------------------------------------------------------------------ */
    /* Lettre de notification avec piece jointe                            */
    /* ------------------------------------------------------------------ */
    public function sendWithAttachment(
        string $toEmail,
        string $subject,
        string $bodyHtml,
        string $altBody   = '',
        string $filePath  = '',
        string $fileName  = ''
    ): bool {
        $sent = false;
        try {
            $this->mail->SMTPKeepAlive = true; // Evite timeout sur gros fichiers
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();
            $this->mail->addAddress($toEmail);
            $this->mail->Subject = $subject;
            $this->mail->Body    = $bodyHtml;
            $this->mail->AltBody = $altBody ?: strip_tags($bodyHtml);
            if ($filePath && file_exists($filePath)) {
                $this->mail->addAttachment($filePath, $fileName ?: basename($filePath));
            }
            $sent = $this->mail->send();
        } catch (\Throwable $e) {
            error_log('Mailer sendWithAttachment : ' . $e->getMessage());
            $sent = false;
        } finally {
            try { $this->mail->smtpClose(); } catch (\Throwable $e) {}
        }
        return $sent;
    }

    /* ------------------------------------------------------------------ */
    /* Relance d'echeance d'une (ou plusieurs) fiche(s) de non-conformite  */
    /* Adressee a l'inspecteur en charge du suivi.                         */
    /* Signature attendue par l'endpoint nonconformites (action relance).  */
    /* ------------------------------------------------------------------ */
    public function sendRelanceFnc(string $toEmail, string $toName, array $fiches, string $urlApp = ''): bool
    {
        try {
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();
            $this->mail->addAddress($toEmail, $toName !== '' ? $toName : $toEmail);
            $nb = count($fiches);
            $this->mail->Subject = 'Relance - Echeance de ' . $nb . ' fiche(s) de non-conformite';
            $this->mail->Body    = $this->getRelanceFncTemplate($toName, $fiches, $urlApp);
            $this->mail->AltBody = 'Bonjour ' . $toName . ', vous avez ' . $nb
                . ' fiche(s) de non-conformite dont l\'echeance approche ou est depassee. Connectez-vous a AGAI : ' . $urlApp;
            return $this->mail->send();
        } catch (\Throwable $e) {
            error_log('Erreur sendRelanceFnc vers ' . $toEmail . ' : ' . $e->getMessage());
            return false;
        }
    }

    private function getRelanceFncTemplate(string $nom, array $fiches, string $urlApp): string
    {
        $banner = $this->bannerHtml();
        $footer = $this->footerHtml();
        $nomE   = htmlspecialchars($nom, ENT_QUOTES, 'UTF-8');
        $urlE   = htmlspecialchars($urlApp, ENT_QUOTES, 'UTF-8');

        $rows = '';
        foreach ($fiches as $f) {
            $numFnc = htmlspecialchars((string)($f['num_fnc'] ?? ''), ENT_QUOTES, 'UTF-8');
            $numAud = htmlspecialchars((string)($f['num_audit'] ?? ''), ENT_QUOTES, 'UTF-8');
            $oper   = htmlspecialchars((string)($f['operateur'] ?? ''), ENT_QUOTES, 'UTF-8');
            $ech    = htmlspecialchars((string)($f['echeance'] ?? '-'), ENT_QUOTES, 'UTF-8');
            $cat    = htmlspecialchars((string)($f['categorie'] ?? ''), ENT_QUOTES, 'UTF-8');
            $jours  = $f['jours'] ?? null;
            $etat   = is_numeric($jours)
                ? ((int)$jours < 0 ? 'En retard de ' . abs((int)$jours) . ' jour(s)'
                    : ((int)$jours === 0 ? 'Echeance aujourd\'hui' : 'Dans ' . (int)$jours . ' jour(s)'))
                : '-';
            $couleur = is_numeric($jours) && (int)$jours < 0 ? '#D32F2F' : ((int)$jours <= 7 ? '#E8890C' : '#1E9C4B');
            $rows .= "<tr>"
                . "<td style='padding:8px 10px;border:1px solid #e2e8f0;font-family:monospace;color:#23408F;font-weight:bold'>$numFnc</td>"
                . "<td style='padding:8px 10px;border:1px solid #e2e8f0'>$numAud</td>"
                . "<td style='padding:8px 10px;border:1px solid #e2e8f0'>$oper</td>"
                . "<td style='padding:8px 10px;border:1px solid #e2e8f0;text-transform:capitalize'>$cat</td>"
                . "<td style='padding:8px 10px;border:1px solid #e2e8f0'>$ech</td>"
                . "<td style='padding:8px 10px;border:1px solid #e2e8f0;color:$couleur;font-weight:bold'>$etat</td>"
                . "</tr>";
        }

        $bouton = $urlE !== ''
            ? "<div style='text-align:center;margin:22px 0'>"
              . "<a href='$urlE' style='background:#23408F;color:#fff;text-decoration:none;padding:11px 26px;"
              . "border-radius:8px;font-weight:bold;font-size:.9rem;display:inline-block'>"
              . "Acceder au module de suivi</a></div>"
            : '';

        return "<div style='max-width:680px;margin:0 auto;font-family:Candara,Segoe UI,Arial,sans-serif;color:#2C3E50'>"
            . $banner
            . "<div style='padding:22px 28px;border:1px solid #e2e8f0;border-top:none'>"
            . "<h2 style='color:#23408F;font-size:1.15rem;margin:0 0 6px'>Relance : echeances de mise en conformite</h2>"
            . "<p style='font-size:.9rem;line-height:1.5'>Bonjour <strong>$nomE</strong>,</p>"
            . "<p style='font-size:.9rem;line-height:1.5'>En votre qualite d'inspecteur en charge du suivi, "
            . "vous trouverez ci-dessous la ou les fiche(s) de non-conformite dont l'echeance de reponse "
            . "ou de mise en conformite approche ou est deja depassee. Nous vous invitons a prendre les "
            . "dispositions necessaires dans les meilleurs delais.</p>"
            . "<table style='width:100%;border-collapse:collapse;font-size:.82rem;margin-top:12px'>"
            . "<thead><tr style='background:#23408F;color:#fff'>"
            . "<th style='padding:8px 10px;text-align:left'>N FNC</th>"
            . "<th style='padding:8px 10px;text-align:left'>Audit</th>"
            . "<th style='padding:8px 10px;text-align:left'>Operateur</th>"
            . "<th style='padding:8px 10px;text-align:left'>Categorie</th>"
            . "<th style='padding:8px 10px;text-align:left'>Echeance</th>"
            . "<th style='padding:8px 10px;text-align:left'>Etat</th>"
            . "</tr></thead><tbody>$rows</tbody></table>"
            . $bouton
            . "<p style='font-size:.82rem;color:#6b7a90;margin-top:16px'>Cordialement,<br>"
            . "Le Systeme AGAI - Agence Nationale de l'Aviation Civile du Gabon</p>"
            . "</div>"
            . $footer
            . "</div>";
    }
}