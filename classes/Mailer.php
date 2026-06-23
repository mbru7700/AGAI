<?php
/**
 * Classe Mailer - Gestion des emails avec PHPMailer
 * 
 * @package AGAI
 * @author ANAC Gabon
 */

class Mailer
{
    private $mail;
    
    public function __construct()
    {
        // Charger PHPMailer
        require_once MAIL_PATH . '/PHPMailer/src/PHPMailer.php';
        require_once MAIL_PATH . '/PHPMailer/src/SMTP.php';
        require_once MAIL_PATH . '/PHPMailer/src/Exception.php';
        
        $this->mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            $this->mail->isSMTP();
            $this->mail->Host = MAIL_HOST;
            $this->mail->SMTPAuth = true;
            $this->mail->Username = MAIL_USERNAME;
            $this->mail->Password = MAIL_PASSWORD;
            $this->mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $this->mail->Port = MAIL_PORT;
            
            $this->mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
            $this->mail->CharSet = 'UTF-8';
            $this->mail->Encoding = 'base64';
            $this->mail->isHTML(true);
            
        } catch (Exception $e) {
            error_log("Erreur mail: " . $e->getMessage());
        }
    }
    
    public function sendOTP($email, $otp)
    {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($email);
            
            $this->mail->Subject = '🔐 Code de sécurité AGAI - ' . date('d/m/Y');
            $this->mail->Body = $this->getOTPTemplate($otp);
            $this->mail->AltBody = "Votre code de sécurité est : $otp";
            
            return $this->mail->send();
            
        } catch (Exception $e) {
            error_log("Erreur envoi OTP: " . $e->getMessage());
            return false;
        }
    }
    
    private function getOTPTemplate($otp)
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Candara, Arial, sans-serif; background: #f5f7fa; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,0.1); overflow: hidden; }
                .header { background: #23408F; color: white; padding: 30px; text-align: center; }
                .header h2 { margin: 0; font-weight: 700; }
                .header p { margin: 5px 0 0; opacity: 0.8; }
                .body { padding: 30px; }
                .code { background: #e8f0fe; padding: 20px; text-align: center; border-radius: 8px; margin: 20px 0; }
                .code span { font-size: 36px; font-weight: 700; color: #23408F; letter-spacing: 8px; }
                .footer { text-align: center; padding: 20px; border-top: 1px solid #eee; color: #666; font-size: 12px; }
                .footer .logo { height: 40px; margin-bottom: 10px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🔐 AGAI - Sécurité</h2>
                    <p>Code d'authentification à deux facteurs</p>
                </div>
                <div class='body'>
                    <p>Bonjour,</p>
                    <p>Utilisez le code ci-dessous pour vous connecter à l'application AGAI :</p>
                    <div class='code'>
                        <span>$otp</span>
                    </div>
                    <p><small>⚠️ Ce code est valable <strong>10 minutes</strong>. Ne le partagez avec personne.</small></p>
                    <p><small>Si vous n'êtes pas à l'origine de cette demande, ignorez cet email.</small></p>
                </div>
                <div class='footer'>
                    <p><strong>AGAI - ANAC Gabon</strong></p>
                    <p>Système de Surveillance Continue de la Sécurité Aérienne</p>
                    <p>&copy; " . date('Y') . " - Tous droits réservés</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Envoie la notification de planification d'un acte de supervision
     * a un inspecteur designe.
     *
     * @param string $toEmail   Adresse mail de l'inspecteur (mailinspect)
     * @param string $toName    Nom complet de l'inspecteur
     * @param array  $params    Donnees du mail (voir sendNotifAuditTemplate)
     * @return bool
     */
    public function sendNotifAudit(string $toEmail, string $toName, array $params): bool
    {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($toEmail, $toName);
            $this->mail->Subject = 'Designation - Acte de supervision ' . ($params['num_audit'] ?? '');
            $this->mail->Body    = $this->getNotifAuditTemplate($params);
            $this->mail->AltBody = 'Bonjour ' . $toName . ', vous etes designe pour un acte de supervision. Connectez-vous a AGAI : ' . ($params['agai_url'] ?? '');
            return $this->mail->send();
        } catch (\Exception $e) {
            error_log('Erreur sendNotifAudit vers ' . $toEmail . ' : ' . $e->getMessage());
            return false;
        }
    }

    private function getNotifAuditTemplate(array $p): string
    {
        $num      = htmlspecialchars($p['num_audit']     ?? '', ENT_QUOTES, 'UTF-8');
        $type     = htmlspecialchars($p['type_activite'] ?? '', ENT_QUOTES, 'UTF-8');
        $cadre    = htmlspecialchars($p['cadre']         ?? '', ENT_QUOTES, 'UTF-8');
        $mois     = htmlspecialchars($p['mois_annee']    ?? '', ENT_QUOTES, 'UTF-8');
        $oper     = htmlspecialchars($p['operateur']     ?? '', ENT_QUOTES, 'UTF-8');
        $site     = htmlspecialchars($p['site']          ?? '', ENT_QUOTES, 'UTF-8');
        $ciNom    = htmlspecialchars($p['ci_nom']        ?? '', ENT_QUOTES, 'UTF-8');
        $raNom    = htmlspecialchars($p['ra_nom']        ?? '', ENT_QUOTES, 'UTF-8');
        $destNom  = htmlspecialchars($p['dest_nom']      ?? '', ENT_QUOTES, 'UTF-8');
        $agaiUrl  = htmlspecialchars($p['agai_url']      ?? '', ENT_QUOTES, 'UTF-8');
        $equipe   = $p['equipe'] ?? [];

        // Tableau de l'equipe
        $lignes = '';
        foreach ($equipe as $m) {
            $style = !empty($m['est_resp'])
                ? 'color:#D32F2F;font-weight:700'
                : 'color:#2C3E50';
            $role  = !empty($m['est_resp']) ? ' <em>(R.A / Chef de mission)</em>' : '';
            $doms  = htmlspecialchars(implode(', ', $m['domaines'] ?? []), ENT_QUOTES, 'UTF-8');
            $nom   = htmlspecialchars(trim(($m['prenom'] ?? '') . ' ' . ($m['nom'] ?? '')), ENT_QUOTES, 'UTF-8');
            $lignes .= '<tr>'
                . '<td style="padding:8px 12px;border-bottom:1px solid #eee;' . $style . '">'
                . $nom . $role . '</td>'
                . '<td style="padding:8px 12px;border-bottom:1px solid #eee;color:#2C3E50">'
                . $doms . '</td>'
                . '</tr>';
        }

        $raLine = $raNom
            ? '<p style="margin:4px 0 12px;color:#D32F2F;font-weight:600">Responsable d\'Audit (R.A) : ' . $raNom . '</p>'
            : '';

        $lienAGAI = $agaiUrl
            ? '<p style="text-align:center;margin:18px 0">
                 <a href="' . $agaiUrl . '" style="background:#23408F;color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;font-weight:700;font-size:.95rem">
                   &#128274; Se connecter a AGAI
                 </a>
               </p>
               <p style="text-align:center;font-size:.8rem;color:#8a97ab">' . $agaiUrl . '</p>'
            : '';

        return '<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>
body{font-family:Candara,Arial,sans-serif;background:#f5f7fa;margin:0;padding:0}
.wrap{max-width:640px;margin:24px auto;background:#fff;border-radius:14px;box-shadow:0 4px 18px rgba(0,0,0,.09);overflow:hidden}
.hdr{background:#23408F;color:#fff;padding:28px 30px}
.hdr h2{margin:0 0 4px;font-size:1.2rem;font-weight:700}
.hdr p{margin:0;opacity:.85;font-size:.9rem}
.body{padding:28px 30px}
.body p{line-height:1.6;color:#2C3E50;margin:0 0 12px}
.kv{background:#f7f9fc;border-left:4px solid #23408F;border-radius:8px;padding:10px 14px;margin:14px 0}
.kv div{margin-bottom:4px;font-size:.92rem;color:#2C3E50}
.kv span{font-weight:700;color:#23408F}
table.eq{width:100%;border-collapse:collapse;margin:14px 0}
table.eq thead th{background:#23408F;color:#fff;padding:9px 12px;text-align:left;font-size:.85rem}
ul.ml{color:#2C3E50;line-height:1.8;padding-left:18px}
.sign{margin-top:24px;padding-top:16px;border-top:1px solid #eef1f6;color:#2C3E50}
.ftr{background:#f5f7fa;padding:14px 30px;text-align:center;font-size:.8rem;color:#8a97ab;border-top:1px solid #eef1f6}
</style></head><body>
<div class="wrap">
  <div class="hdr">
    <h2>&#9992; AGAI - Acte de supervision</h2>
    <p>Agence Nationale de l\'Aviation Civile - Gabon</p>
  </div>
  <div class="body">
    <p>Bonjour <strong>' . $destNom . '</strong>,</p>
    <p>Dans le cadre des activites de la supervision de la securite/surete du mois de
    <strong>' . $mois . '</strong>, vous etes designe pour mener les activites
    d\'inspection/audit selon vos domaines respectifs et conformement au tableau ci-dessous.</p>
    <div class="kv">
      <div><span>Reference :</span> ' . $num . '</div>
      <div><span>Nature :</span> ' . $type . '</div>
      <div><span>Cadre :</span> ' . $cadre . '</div>
      <div><span>Operateur :</span> ' . $oper . '</div>
      <div><span>Site :</span> ' . $site . '</div>
    </div>
    ' . $raLine . '
    <table class="eq">
      <thead><tr><th>Inspecteur</th><th>Domaine(s)</th></tr></thead>
      <tbody>' . $lignes . '</tbody>
    </table>
    <p style="font-size:.82rem;color:#8a97ab"><em>En rouge : le Responsable d\'Audit (R.A) / Chef de mission.</em><br>
    Votre designation reste en vigueur tant qu\'une instruction contraire ne vous est pas communiquee.</p>
    <p>Vos missions consisteront en des activites d\'audit, d\'inspection, de test et de
    demonstration, visant a verifier la conformite des operateurs, conformement aux exigences
    du Reglement de l\'Aviation Civile Gabonaise (RAG) et aux procedures operationnelles
    des operateurs supervises.</p>
    <p>Lors de ces missions, vous veillerez a :</p>
    <ul class="ml">
      <li>Appliquer vos competences conformement a vos habilitations ;</li>
      <li>Maintenir votre independance vis-a-vis des entites auditees afin de garantir
          l\'impartialite et l\'objectivite des evaluations ;</li>
      <li>Fonctionner efficacement en equipe, en mobilisant les competences
          complementaires de chaque membre.</li>
    </ul>
    <p>Le responsable de l\'activite est prie de se rapprocher de l\'operateur afin de
    verifier la faisabilite de ces missions.</p>
    <p>Nous restons dans l\'attente des dates convenues, afin de transmettre le besoin a
    la Direction Financiere (DF) pour l\'etablissement de vos O.D.M, si necessaire.</p>
    <p>Nous vous prions de faire parvenir, dans les meilleurs delais, le formulaire
    d\'etude documentaire ainsi que le plan d\'audit/inspection, incluant : les objectifs
    des missions, le perimetre couvert, le referentiel applicable, la duree et les dates
    prevues.</p>
    <p>Toute activite reportee devra etre formalisee par un courrier officiel ou par email,
    en precisant le motif du report.</p>
    <p>A titre de rappel, la completude des dossiers lies aux activites d\'inspection est
    prise en compte dans l\'evaluation des inspecteurs.</p>
    ' . $lienAGAI . '
    <div class="sign">
      <p style="margin:0">Cordialement,</p>
      <p style="margin:6px 0 0;font-weight:700;color:#23408F">
        Le Chef Inspecteur,<br>' . $ciNom . '</p>
    </div>
  </div>
  <div class="ftr">
    AGAI - Systeme de Surveillance Continue de la Securite Aerienne
    &copy; ' . date('Y') . '
  </div>
</div>
</body></html>';
    }
}