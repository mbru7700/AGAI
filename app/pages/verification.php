<?php
/**
 * Page de vérification 2FA - AGAI ANAC Gabon
 * ------------------------------------------------------------
 * Bootstrap assuré par le front controller public/index.php.
 */

if (!defined('SITE_URL')) {                 // sécurité : accès hors routeur
    require_once dirname(__DIR__, 2) . '/config/config.php';
}

// Vérifier la session - SI PAS DE SESSION, REDIRIGER VERS L'ACCUEIL
if (!isset($_SESSION['user_id']) || !isset($_SESSION['2fa_required'])) {
    header('Location: ' . SITE_URL . '/index');
    exit;
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $code = Security::cleanInput($_POST['otp_code'] ?? '');
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!Security::validateCSRF($csrf_token)) {
        echo json_encode(['success' => false, 'message' => 'Token CSRF invalide']);
        exit;
    }

    $result = Auth::verifyOTP($code);

    if ($result['success']) {
        echo json_encode(['success' => true, 'redirect' => SITE_URL . '/dashboard']);
    } else {
        echo json_encode(['success' => false, 'message' => $result['message']]);
    }
    exit;
}

$csrf_token = Security::generateCSRF();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification 2FA - AGAI | ANAC Gabon</title>
    <link rel="icon" href="<?php echo ASSETS_URL; ?>/images/faviconLOGOANAC.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        * { font-family: 'Candara','Segoe UI',system-ui,sans-serif !important; }
        body {
            background: linear-gradient(135deg, #23408F, #1a3270);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            margin: 0;
        }
        .verification-container { width: 100%; max-width: 480px; }
        .verification-card {
            background: rgba(255,255,255,0.97);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 28px;
            box-shadow: 0 30px 80px rgba(0,0,0,0.35);
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .verification-card:hover { box-shadow: 0 40px 100px rgba(0,0,0,0.4); }
        .verification-header {
            background: linear-gradient(135deg, #23408F, #1a3270);
            color: white;
            padding: 35px 30px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .verification-header::before {
            content: '';
            position: absolute;
            top: -50%; left: -50%;
            width: 200%; height: 200%;
            background: radial-gradient(ellipse at center, rgba(243,195,0,0.08) 0%, transparent 70%);
            animation: rotateSlow 20s linear infinite;
        }
        @keyframes rotateSlow { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .verification-header .logo {
            height: 55px; margin-bottom: 12px; position: relative; z-index: 1;
            filter: drop-shadow(0 2px 10px rgba(0,0,0,0.2));
        }
        .verification-header h3 { font-weight: 700; margin-bottom: 5px; position: relative; z-index: 1; }
        .verification-header p { opacity: 0.8; margin: 0; position: relative; z-index: 1; }
        .verification-body { padding: 40px; }
        .icon-container {
            width: 80px; height: 80px; border-radius: 50%;
            background: linear-gradient(135deg, rgba(35,64,143,0.08), rgba(26,50,112,0.08));
            display: flex; align-items: center; justify-content: center;
            font-size: 2.5rem; color: #23408F; margin: -60px auto 20px;
            position: relative; z-index: 2; border: 4px solid white;
            box-shadow: 0 8px 30px rgba(35,64,143,0.15);
        }
        .otp-input-group { max-width: 220px; margin: 0 auto; }
        .otp-input-group input {
            font-size: 2rem; letter-spacing: 10px; font-weight: 700; text-align: center;
            border-radius: 14px; padding: 18px 15px; border: 2px solid #e0e0e0; background: white; transition: all 0.3s;
        }
        .otp-input-group input:focus { border-color: #23408F; box-shadow: 0 0 0 6px rgba(35,64,143,0.08); }
        .btn-verify {
            background: linear-gradient(135deg, #23408F, #1a3270);
            color: white; border: none; padding: 16px; border-radius: 14px;
            font-weight: 600; font-size: 17px; width: 100%; transition: all 0.3s; position: relative; overflow: hidden;
        }
        .btn-verify::before {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent); transition: left 0.6s;
        }
        .btn-verify:hover::before { left: 100%; }
        .btn-verify:hover { transform: translateY(-3px); box-shadow: 0 8px 30px rgba(35,64,143,0.3); color: white; }
        .btn-link { color: #23408F; text-decoration: none; font-weight: 500; transition: all 0.3s; }
        .btn-link:hover { color: #1a3270; text-decoration: underline; }
        .timer { font-weight: 600; color: #23408F; background: rgba(35,64,143,0.06); padding: 2px 12px; border-radius: 20px; }
        .footer-text { text-align: center; margin-top: 20px; color: #6c757d; font-size: 0.85rem; padding-top: 20px; border-top: 1px solid #f0f0f0; }
        .footer-text .anac-text { color: #23408F; font-weight: 600; }
        @media (max-width: 576px) {
            .verification-body { padding: 25px; }
            .otp-input-group { max-width: 180px; }
            .otp-input-group input { font-size: 1.5rem; letter-spacing: 6px; padding: 14px; }
            .verification-header { padding: 25px 20px; }
            .icon-container { width: 65px; height: 65px; font-size: 2rem; margin-top: -50px; }
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <div class="verification-card">
            <div class="verification-header">
                <img src="<?php echo ASSETS_URL; ?>/images/Logo-ANAC-CERTIFICATION.png" alt="ANAC Gabon" class="logo" onerror="this.style.display='none'">
                <h3><i class="bi bi-shield-check me-2"></i>Vérification 2FA</h3>
                <p>Entrez le code de sécurité envoyé à votre email</p>
            </div>
            <div class="verification-body">
                <div class="icon-container"><i class="bi bi-envelope-check"></i></div>

                <form id="verifyForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo Security::escape($csrf_token); ?>">

                    <div class="mb-4">
                        <label class="form-label fw-bold text-center d-block">Code à 6 chiffres</label>
                        <div class="otp-input-group">
                            <input type="text" class="form-control" id="otp_code" name="otp_code"
                                   placeholder="000000" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" required autofocus>
                        </div>
                        <div class="text-center mt-2">
                            <small class="text-muted">
                                <i class="bi bi-clock me-1"></i>
                                Valable <span class="timer" id="timer">10:00</span>
                            </small>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-verify" id="verifyButton">
                        <i class="bi bi-check-circle me-2"></i> Vérifier
                    </button>

                    <div class="text-center mt-3">
                        <button type="button" class="btn btn-link" id="resendCode">
                            <i class="bi bi-arrow-repeat me-1"></i> Renvoyer le code
                        </button>
                    </div>
                </form>

                <div class="footer-text">
                    <i class="bi bi-info-circle me-1"></i>
                    Si vous ne recevez pas le code, vérifiez vos <strong>spams</strong><br>
                    <small class="text-muted">ANAC Gabon - <span class="anac-text">AGAI</span> &copy; <?php echo date('Y'); ?></small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    $(document).ready(function() {
        let timeLeft = 600;
        const timerElement = document.getElementById('timer');

        function updateTimer() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerElement.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                timerElement.textContent = '00:00';
                document.getElementById('verifyButton').disabled = true;
            }
            timeLeft--;
        }

        const timerInterval = setInterval(updateTimer, 1000);
        updateTimer();

        $('#verifyForm').on('submit', function(e) {
            e.preventDefault();
            const button = $('#verifyButton');
            const originalText = button.html();
            button.prop('disabled', true);
            button.html('<span class="spinner-border spinner-border-sm me-2"></span>Vérification...');

            $.ajax({
                url: '',
                method: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Vérification réussie',
                            text: 'Redirection vers le tableau de bord...',
                            timer: 1500,
                            showConfirmButton: false,
                            timerProgressBar: true,
                            willClose: function() { window.location.href = response.redirect; }
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Code invalide',
                            text: response.message || 'Le code est incorrect ou a expiré.',
                            confirmButtonColor: '#23408F',
                            confirmButtonText: 'Réessayer'
                        });
                        button.prop('disabled', false);
                        button.html(originalText);
                        $('#otp_code').val('').focus();
                    }
                },
                error: function() {
                    Swal.fire({ icon: 'error', title: 'Erreur', text: 'Une erreur est survenue. Veuillez réessayer.', confirmButtonColor: '#23408F' });
                    button.prop('disabled', false);
                    button.html(originalText);
                }
            });
        });

        $('#resendCode').on('click', function() {
            const button = $(this);
            button.prop('disabled', true);
            button.html('<span class="spinner-border spinner-border-sm me-2"></span>Envoi...');

            $.ajax({
                url: '<?php echo SITE_URL; ?>/auth',
                method: 'POST',
                data: { action: 'resend_otp', csrf_token: $('input[name="csrf_token"]').val() },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        timeLeft = 600;
                        document.getElementById('verifyButton').disabled = false;
                        Swal.fire({ icon: 'success', title: 'Code envoyé', text: 'Un nouveau code vous a été envoyé par email.', timer: 3000, showConfirmButton: false, timerProgressBar: true });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Erreur', text: response.message || "Impossible d'envoyer le code.", confirmButtonColor: '#23408F' });
                    }
                    button.prop('disabled', false);
                    button.html('<i class="bi bi-arrow-repeat me-1"></i> Renvoyer le code');
                },
                error: function() {
                    Swal.fire({ icon: 'error', title: 'Erreur', text: 'Une erreur est survenue.', confirmButtonColor: '#23408F' });
                    button.prop('disabled', false);
                    button.html('<i class="bi bi-arrow-repeat me-1"></i> Renvoyer le code');
                }
            });
        });
    });
    </script>
</body>
</html>
