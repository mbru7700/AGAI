<?php
/**
 * Classe Mailer - Envoi d'emails via PHPMailer (résilient + personnalisé)
 * ------------------------------------------------------------
 * - Délai d'attente court, réessai 465 <-> 587, journalisation
 * - Email OTP personnalisé (nom de l'utilisateur) au design ANAC
 *
 * @package AGAI
 * @author  ANAC Gabon
 */

class Mailer
{
    private $lastError = '';

    public function __construct()
    {
        require_once MAIL_PATH . '/PHPMailer/src/PHPMailer.php';
        require_once MAIL_PATH . '/PHPMailer/src/SMTP.php';
        require_once MAIL_PATH . '/PHPMailer/src/Exception.php';
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    private function build(int $port, string $secure)
    {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = $secure;
        $mail->Port       = $port;
        $mail->Timeout    = (int) (function_exists('env') ? env('MAIL_TIMEOUT', 15) : 15);
        $mail->CharSet    = 'UTF-8';
        $mail->Encoding   = 'base64';
        $mail->isHTML(true);
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);

        if (function_exists('env') && env('MAIL_DEBUG', false)) {
            $mail->SMTPDebug   = 2;
            $mail->Debugoutput = function ($str, $level) {
                error_log("SMTP[$level]: $str");
            };
        }
        return $mail;
    }

    private function send(string $to, string $subject, string $html, string $alt): bool
    {
        $primaryPort   = (int) MAIL_PORT;
        $primarySecure = $primaryPort === 587
            ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
            : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;

        if ($primaryPort === 465) {
            $altPort = 587; $altSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $altPort = 465; $altSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        }

        foreach ([[$primaryPort, $primarySecure], [$altPort, $altSecure]] as [$port, $secure]) {
            try {
                $mail = $this->build($port, $secure);
                $mail->clearAddresses();
                $mail->addAddress($to);
                $mail->Subject = $subject;
                $mail->Body    = $html;
                $mail->AltBody = $alt;
                if ($mail->send()) {
                    return true;
                }
                $this->lastError = "Port $port : " . $mail->ErrorInfo;
            } catch (Throwable $e) {
                $this->lastError = "Port $port : " . $e->getMessage();
            }
            error_log("Mailer echec ($port) : " . $this->lastError);
        }
        return false;
    }

    /**
     * Envoie le code OTP, en s'adressant à l'utilisateur par son nom.
     *
     * @param string $email Destinataire
     * @param string $otp   Code à usage unique
     * @param string $name  Nom complet (prénom + nom) — optionnel
     */
    public function sendOTP($email, $otp, $name = ''): bool
    {
        $altGreeting = $name !== '' ? "Bonjour $name,\n\n" : '';
        return $this->send(
            $email,
            'Code de sécurité AGAI - ' . date('d/m/Y'),
            $this->getOTPTemplate($otp, $name),
            $altGreeting . "Votre code de sécurité AGAI est : $otp (valable 10 minutes). Ne le partagez avec personne."
        );
    }

    /**
     * Envoie les identifiants d'accès à un nouvel utilisateur (ou après reset).
     */
    public function sendCredentials($email, $name, $login, $password, $url): bool
    {
        $alt = ($name !== '' ? "Bonjour $name,\n\n" : '')
            . "Votre compte AGAI a été créé.\n"
            . "Adresse de connexion : $url\n"
            . "Identifiant : $login\n"
            . "Mot de passe provisoire : $password\n\n"
            . "Pour votre sécurité, ne partagez jamais ces informations.";

        return $this->send(
            $email,
            'Vos identifiants AGAI - ANAC Gabon',
            $this->getCredentialsTemplate($name, $login, $password, $url),
            $alt
        );
    }

    private function getCredentialsTemplate($name, $login, $password, $url)
    {
        $year     = date('Y');
        $greeting = $name !== ''
            ? 'Bonjour ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ','
            : 'Bonjour,';
        $login    = htmlspecialchars((string) $login, ENT_QUOTES, 'UTF-8');
        $password = htmlspecialchars((string) $password, ENT_QUOTES, 'UTF-8');
        $url      = htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8');

        $html  = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f7fa;margin:0;padding:24px 0;font-family:Segoe UI,Candara,Arial,sans-serif;">';
        $html .= '<tr><td align="center"><table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 4px 18px rgba(35,64,143,0.10);">';
        $html .= '<tr><td style="height:5px;line-height:5px;font-size:0;background:#1E9C4B;">&nbsp;</td></tr>';
        $html .= '<tr><td style="background:#23408F;padding:28px 32px;text-align:center;color:#ffffff;">';
        $html .= '<div style="font-size:24px;font-weight:bold;letter-spacing:2px;">AGAI</div>';
        $html .= '<div style="font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#F3C300;margin-top:4px;">ANAC Gabon</div>';
        $html .= '<div style="font-size:14px;color:#dce4f5;margin-top:12px;">Création de votre compte</div></td></tr>';
        $html .= '<tr><td style="padding:32px;color:#2C3E50;font-size:15px;line-height:1.6;">';
        $html .= '<p style="margin:0 0 14px;">' . $greeting . '</p>';
        $html .= '<p style="margin:0 0 18px;">Un compte vous a été créé sur la plateforme <strong>AGAI</strong>. Voici vos identifiants de connexion :</p>';
        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef3fb;border:1px solid #d8e2f3;border-radius:10px;">';
        $html .= '<tr><td style="padding:16px 20px;">';
        $html .= '<p style="margin:0 0 8px;"><strong>Adresse :</strong> <a href="' . $url . '" style="color:#23408F;">' . $url . '</a></p>';
        $html .= '<p style="margin:0 0 8px;"><strong>Identifiant :</strong> ' . $login . '</p>';
        $html .= '<p style="margin:0;"><strong>Mot de passe provisoire :</strong> <span style="font-family:Consolas,monospace;color:#23408F;">' . $password . '</span></p>';
        $html .= '</td></tr></table>';
        $html .= '<p style="margin:18px 0 8px;font-size:13px;color:#6b7a90;">À la première connexion, un code de sécurité (2FA) vous sera envoyé par email si l&rsquo;option est activée.</p>';
        $html .= '<p style="margin:0;font-size:13px;color:#6b7a90;">Ne partagez jamais vos identifiants. En cas de doute, contactez l&rsquo;administrateur.</p>';
        $html .= '</td></tr>';
        $html .= '<tr><td style="background:#f0f3f8;padding:18px 32px;text-align:center;color:#8a97a8;font-size:11px;border-top:1px solid #e6ebf2;">';
        $html .= '<strong style="color:#23408F;">ANAC Gabon</strong> &middot; AGAI &middot; &copy; ' . $year . '</td></tr>';
        $html .= '</table></td></tr></table>';
        return $html;
    }

    private function getOTPTemplate($otp, $name = '')
    {
        $year     = date('Y');
        $greeting = $name !== ''
            ? 'Bonjour ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ','
            : 'Bonjour,';
        $code = htmlspecialchars((string) $otp, ENT_QUOTES, 'UTF-8');

        $html  = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f7fa;margin:0;padding:24px 0;font-family:Segoe UI,Candara,Arial,sans-serif;">';
        $html .= '<tr><td align="center">';
        $html .= '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 4px 18px rgba(35,64,143,0.10);">';

        // Bandeau drapeau (vert Gabon)
        $html .= '<tr><td style="height:5px;line-height:5px;font-size:0;background:#1E9C4B;">&nbsp;</td></tr>';

        // En-tête
        $html .= '<tr><td style="background:#23408F;padding:28px 32px;text-align:center;color:#ffffff;">';
        $html .= '<div style="font-size:24px;font-weight:bold;letter-spacing:2px;">AGAI</div>';
        $html .= '<div style="font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#F3C300;margin-top:4px;">ANAC Gabon</div>';
        $html .= '<div style="font-size:14px;color:#dce4f5;margin-top:12px;">Authentification à deux facteurs</div>';
        $html .= '</td></tr>';

        // Corps
        $html .= '<tr><td style="padding:32px;color:#2C3E50;font-size:15px;line-height:1.6;">';
        $html .= '<p style="margin:0 0 14px;">' . $greeting . '</p>';
        $html .= '<p style="margin:0 0 22px;">Voici votre code OTP de sécurité  pour vous connecter à la plateforme <strong>AGAI</strong> :</p>';
        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>';
        $html .= '<td style="background:#eef3fb;border:1px solid #d8e2f3;border-radius:10px;text-align:center;padding:22px;">';
        $html .= '<span style="font-size:34px;font-weight:bold;color:#23408F;letter-spacing:10px;">' . $code . '</span>';
        $html .= '</td></tr></table>';
        $html .= '<p style="margin:22px 0 8px;font-size:13px;color:#6b7a90;">Ce code est valable <strong style="color:#2C3E50;">10 minutes</strong>. Ne le partagez avec personne.</p>';
        $html .= '<p style="margin:0;font-size:13px;color:#6b7a90;">Si vous n&rsquo;êtes pas à l&rsquo;origine de cette connexion, ignorez cet email et prévenez l&rsquo;administrateur.</p>';
        $html .= '</td></tr>';

        // Pied
        $html .= '<tr><td style="background:#f0f3f8;padding:18px 32px;text-align:center;color:#8a97a8;font-size:11px;border-top:1px solid #e6ebf2;">';
        $html .= '<strong style="color:#23408F;">ANAC Gabon</strong> &middot; Surveillance Continue de la Sécurité Aérienne<br>';
        $html .= '&copy; ' . $year . ' &mdash; Tous droits réservés &middot; Message automatique, merci de ne pas répondre';
        $html .= '</td></tr>';

        $html .= '</table></td></tr></table>';
        return $html;
    }
}
