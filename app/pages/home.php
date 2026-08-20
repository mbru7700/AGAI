<?php
/**
 * ============================================================
 * AGAI - ANAC Gabon
 * Page d'accueil & connexion (redessinée, responsive, charte ANAC)
 * ------------------------------------------------------------
 * - Menu "Connexion" corrigé (ancre #connexion réelle)
 * - Formulaire de login visible sur TOUS les écrans (RWD)
 * - URLs propres (sans .php)
 * - Logique CSRF / AJAX / 2FA préservée
 * ============================================================
 *
 * NOTE : le bootstrap (config, classes, session) est assuré
 * par le front controller public/index.php. Ne pas re-require config ici.
 * ------------------------------------------------------------
 */

if (!defined('SITE_URL')) {                 // sécurité : accès hors routeur
    require_once dirname(__DIR__, 2) . '/config/config.php';
}

/* ------------------------------------------------------------
 * Rediriger si déjà connecté (URLs propres, sans .php)
 * ---------------------------------------------------------- */
if (Auth::isLoggedIn()) {
    if (isset($_SESSION['2fa_required']) && $_SESSION['2fa_required']) {
        header('Location: ' . SITE_URL . '/verification');
        exit;
    }
    header('Location: ' . SITE_URL . '/dashboard');
    exit;
}

/* ------------------------------------------------------------
 * Traitement du formulaire de connexion (AJAX -> JSON)
 * ---------------------------------------------------------- */
$response = ['success' => false, 'message' => '', 'requires_2fa' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email      = Security::cleanInput($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!Security::validateCSRF($csrf_token)) {
        $response['message'] = 'Token de sécurité invalide. Rechargez la page.';
    } elseif (empty($email) || empty($password)) {
        $response['message'] = 'Email et mot de passe requis.';
    } elseif (!Security::validateEmail($email)) {
        $response['message'] = 'Adresse email invalide.';
    } else {
        $result = Auth::login($email, $password);

        if (!empty($result['success'])) {
            $requires2fa = !empty($result['requires_2fa']);
            $response = [
                'success'      => true,
                'requires_2fa' => $requires2fa,
                'message'      => $result['message'],
                'redirect'     => SITE_URL . ($requires2fa ? '/verification' : '/dashboard'),
            ];
        } else {
            $response['message'] = $result['message'] ?? 'Échec de la connexion.';
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

/* ------------------------------------------------------------
 * Jeton CSRF
 * ---------------------------------------------------------- */
$csrf_token = Security::generateCSRF();

$logo = ASSETS_URL . '/images/Logo-ANAC-CERTIFICATION.png';
?>
<!DOCTYPE html>
<html lang="fr" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#23408F">
    <meta name="description" content="AGAI - Plateforme nationale de suivi de l'exécution des activités de Supervision de la sécurité et la sûreté de l'Aviation Civile du Gabon - ANAC Gabon.">
    <title>AGAI · Supervision de la Sécurité et la Sûreté de l'Aviation Civile · ANAC Gabon</title>

    <link rel="icon" href="<?php echo ASSETS_URL; ?>/images/faviconLOGOANAC.ico" type="image/x-icon">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <style>
        /* ===================== CHARTE ANAC ===================== */
        :root {
            --anac-primary:    #23408F;   /* Bleu ANAC */
            --anac-primary-d:  #1a3270;
            --anac-primary-dd: #14264f;
            --anac-secondary:  #1E9C4B;   /* Vert Gabon */
            --anac-gold:       #F3C300;   /* Jaune Gabon */
            --anac-danger:     #D32F2F;   /* Rouge */
            --anac-bg:         #F5F7FA;   /* Fond général */
            --anac-card:       #FFFFFF;   /* Cartes */
            --anac-text:       #2C3E50;   /* Texte principal */
            --anac-muted:      #6b7a90;

            --radius: 16px;
            --shadow-sm: 0 2px 14px rgba(35,64,143,.08);
            --shadow-md: 0 12px 40px rgba(35,64,143,.14);
            --shadow-lg: 0 24px 70px rgba(20,38,79,.28);
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; scroll-padding-top: 90px; }

        body {
            font-family: 'Candara','Segoe UI',system-ui,-apple-system,sans-serif;
            color: var(--anac-text);
            background: var(--anac-bg);
            margin: 0;
            overflow-x: hidden;
        }
        h1,h2,h3,h4,h5,h6 { font-family: 'Candara','Segoe UI',system-ui,sans-serif; }

        /* ===================== BANDEAU DRAPEAU ===================== */
        .flag-bar {
            height: 5px;
            background: linear-gradient(90deg,
                var(--anac-secondary) 0 33.33%,
                var(--anac-gold) 33.33% 66.66%,
                var(--anac-primary) 66.66% 100%);
        }

        /* ===================== NAVBAR ===================== */
        #mainNav {
            background: transparent;
            transition: all .35s ease;
            padding: 14px 0;
        }
        #mainNav.scrolled {
            background: rgba(20,38,79,.97);
            backdrop-filter: blur(10px);
            box-shadow: 0 6px 24px rgba(20,38,79,.25);
            padding: 8px 0;
        }
        .navbar-brand { display:flex; align-items:center; gap:10px; }
        .navbar-brand img { height: 42px; width:auto; }
        .navbar-brand .brand-text {
            color:#fff; font-weight:700; font-size:1.35rem; letter-spacing:.5px;
            line-height:1;
        }
        .navbar-brand .brand-sub {
            display:block; color:var(--anac-gold); font-size:.62rem; font-weight:600;
            letter-spacing:1px; text-transform:uppercase;
        }
        #mainNav .nav-link {
            color: rgba(255,255,255,.88) !important;
            font-weight:500; font-size:.95rem; border-radius:8px;
            padding:.5rem .9rem !important; transition:all .2s;
        }
        #mainNav .nav-link:hover { color:#fff !important; background:rgba(255,255,255,.12); }
        .btn-connexion {
            border:2px solid var(--anac-gold) !important;
            color:var(--anac-gold) !important; font-weight:600;
        }
        .btn-connexion:hover {
            background:var(--anac-gold) !important; color:var(--anac-primary-dd) !important;
        }
        .navbar-toggler { border-color: rgba(255,255,255,.4); }

        /* ===================== HERO ===================== */
        .hero {
            position: relative;
            background:
                radial-gradient(900px 500px at 80% -10%, rgba(243,195,0,.18), transparent 60%),
                linear-gradient(135deg, var(--anac-primary) 0%, var(--anac-primary-d) 55%, var(--anac-primary-dd) 100%);
            color:#fff; overflow:hidden;
            padding: 150px 0 90px;
        }
        /* Radar / ondes décoratives */
        .hero::before {
            content:""; position:absolute; top:-120px; right:-120px;
            width:520px; height:520px; border-radius:50%;
            background: repeating-radial-gradient(circle, rgba(255,255,255,.06) 0 1px, transparent 1px 46px);
            animation: spin 60s linear infinite; pointer-events:none;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .hero .floating {
            position:absolute; border-radius:50%;
            background:rgba(255,255,255,.05); pointer-events:none;
            animation: floaty 9s ease-in-out infinite;
        }
        .hero .f1 { width:120px;height:120px; top:18%; left:6%; }
        .hero .f2 { width:70px; height:70px; top:62%; left:18%; animation-delay:1.5s; }
        .hero .f3 { width:180px;height:180px; bottom:-40px; left:42%; animation-delay:3s; }
        @keyframes floaty { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-24px)} }

        .hero-content { position:relative; z-index:2; }
        .hero-badge {
            display:inline-flex; align-items:center; gap:8px;
            background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.22);
            padding:7px 16px; border-radius:50px; font-size:.82rem; font-weight:600;
            margin-bottom:22px; backdrop-filter: blur(4px);
        }
        .hero-badge i { color:var(--anac-gold); }
        .hero h1 {
            font-weight:700; font-size: clamp(2rem, 5vw, 3.4rem);
            line-height:1.12; margin-bottom:18px;
        }
        .hero h1 .accent { color:var(--anac-gold); }
        .hero .lead {
            color:rgba(255,255,255,.85); font-size:1.08rem; max-width:560px;
            margin-bottom:26px;
        }
        .hero .checks { display:flex; flex-wrap:wrap; gap:14px 22px; margin-bottom:30px; font-size:.95rem; }
        .hero .checks span i { color:var(--anac-gold); margin-right:7px; }

        .btn-anac-gold {
            background:var(--anac-gold); color:var(--anac-primary-dd);
            font-weight:700; border:none; border-radius:50px; padding:13px 34px;
            transition:all .25s; box-shadow:0 10px 30px rgba(243,195,0,.3);
        }
        .btn-anac-gold:hover { transform:translateY(-3px); box-shadow:0 16px 40px rgba(243,195,0,.45); color:var(--anac-primary-dd); }
        .btn-ghost {
            background:transparent; color:#fff; border:2px solid rgba(255,255,255,.5);
            border-radius:50px; padding:11px 26px; font-weight:600; transition:all .25s;
        }
        .btn-ghost:hover { background:rgba(255,255,255,.12); color:#fff; border-color:#fff; }

        /* ===================== CARTE LOGIN ===================== */
        .login-card {
            background:var(--anac-card); border-radius:22px; overflow:hidden;
            box-shadow:var(--shadow-lg); transition: transform .25s ease;
            border:1px solid rgba(255,255,255,.4);
        }
        .login-head {
            background: linear-gradient(135deg, var(--anac-primary) 0%, var(--anac-primary-d) 100%);
            color:#fff; padding:26px 28px 22px; text-align:center; position:relative;
        }
        .login-head::after {
            content:""; position:absolute; left:0; right:0; bottom:0; height:4px;
            background: linear-gradient(90deg, var(--anac-secondary), var(--anac-gold), var(--anac-primary));
        }
        .login-head img { height:46px; margin-bottom:10px; }
        .login-head h4 { font-weight:700; margin:0; font-size:1.25rem; }
        .login-head p { margin:4px 0 0; opacity:.8; font-size:.85rem; }
        .login-body { padding:30px 28px; }
        .login-body .form-label { font-weight:600; font-size:.85rem; color:var(--anac-text); }
        .login-body .input-group-text { background:var(--anac-bg); border:1px solid #dfe6ef; color:var(--anac-primary); }
        .login-body .form-control { border:1px solid #dfe6ef; padding:.7rem .9rem; }
        .login-body .form-control:focus {
            border-color:var(--anac-primary); box-shadow:0 0 0 .2rem rgba(35,64,143,.15);
        }
        .btn-login {
            width:100%; background:linear-gradient(135deg, var(--anac-primary), var(--anac-primary-d));
            color:#fff; font-weight:700; border:none; border-radius:12px; padding:13px;
            transition:all .25s; position:relative; overflow:hidden;
        }
        .btn-login:hover { transform:translateY(-2px); box-shadow:0 12px 30px rgba(35,64,143,.35); color:#fff; }
        .btn-login:disabled { opacity:.75; }

        /* ===================== STAT STRIP ===================== */
        .stat-strip { background:#fff; box-shadow:var(--shadow-sm); position:relative; z-index:3; }
        .stat-box { padding:26px 12px; text-align:center; border-right:1px solid #eef2f7; }
        .stat-box:last-child { border-right:none; }
        .stat-box .num { font-size:2rem; font-weight:700; color:var(--anac-primary); line-height:1; }
        .stat-box .lbl { color:var(--anac-muted); font-size:.85rem; margin-top:6px; }
        .stat-box i { color:var(--anac-secondary); }

        /* ===================== SECTIONS ===================== */
        section { scroll-margin-top: 80px; }
        .section-title { font-weight:700; font-size:clamp(1.7rem,3.5vw,2.4rem); color:var(--anac-text); }
        .section-title .hl { color:var(--anac-primary); }
        .section-sub { color:var(--anac-muted); max-width:620px; margin:10px auto 0; }
        .bg-soft { background:var(--anac-bg); }

        .feature-card {
            background:var(--anac-card); border-radius:var(--radius); padding:30px 24px;
            height:100%; box-shadow:var(--shadow-sm); transition:all .3s;
            border:1px solid #eef2f7; border-top:4px solid transparent;
        }
        .feature-card:hover { transform:translateY(-8px); box-shadow:var(--shadow-md); border-top-color:var(--anac-gold); }
        .feature-icon {
            width:64px; height:64px; border-radius:16px; display:flex; align-items:center; justify-content:center;
            font-size:1.7rem; color:#fff; margin-bottom:18px;
            background:linear-gradient(135deg,var(--anac-primary),var(--anac-primary-d));
        }

        .objectif-item {
            background:var(--anac-card); border-radius:var(--radius); padding:22px;
            box-shadow:var(--shadow-sm); border-left:4px solid var(--anac-secondary);
        }
        .obj-icon { color:var(--anac-secondary); }

        .securite-card {
            background:var(--anac-card); border-radius:var(--radius); padding:28px;
            height:100%; box-shadow:var(--shadow-sm); border:1px solid #eef2f7;
        }
        .sec-icon { width:60px;height:60px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.6rem;margin-bottom:16px; }

        .domaine-card {
            background:var(--anac-card); border-radius:14px; padding:22px 14px; text-align:center;
            box-shadow:var(--shadow-sm); transition:all .25s; height:100%;
            border:1px solid #eef2f7;
        }
        .domaine-card:hover { transform:translateY(-5px); box-shadow:var(--shadow-md); }
        .domaine-icon {
            display:inline-flex; align-items:center; justify-content:center;
            width:64px; height:64px; border-radius:50%; margin-bottom:12px;
            font-size:1.6rem; color:#fff;
        }
        .domaine-code {
            font-weight:800; font-size:.95rem; letter-spacing:1px; color:var(--anac-text); margin-bottom:2px;
        }
        .domaine-name { font-size:.82rem; color:var(--anac-muted); font-weight:600; line-height:1.25; }

        /* ===================== FAQ ===================== */
        .faq-item { background:var(--anac-card); border-radius:12px; margin-bottom:14px; box-shadow:var(--shadow-sm); overflow:hidden; border:1px solid #eef2f7; }
        .faq-question { padding:18px 22px; display:flex; justify-content:space-between; align-items:center; cursor:pointer; font-weight:600; color:var(--anac-text); }
        .faq-question i { color:var(--anac-primary); transition:transform .3s; }
        .faq-answer { max-height:0; overflow:hidden; transition:max-height .35s ease, padding .35s ease; padding:0 22px; color:var(--anac-muted); }
        .faq-answer.show { max-height:300px; padding:0 22px 20px; }

        /* ===================== FOOTER ===================== */
        .footer { background:var(--anac-primary-dd); color:#fff; padding:60px 0 0; }
        .footer-logo { height:54px; margin-bottom:16px; }
        .footer h5 { font-weight:700; margin-bottom:18px; font-size:1rem; }
        .footer ul { list-style:none; padding:0; }
        .footer ul li { margin-bottom:10px; color:rgba(255,255,255,.7); font-size:.92rem; }
        .footer ul li a { color:rgba(255,255,255,.7); text-decoration:none; transition:color .2s; }
        .footer ul li a:hover { color:var(--anac-gold); }
        .footer ul li i { color:var(--anac-gold); margin-right:8px; }
        .social-links a {
            width:38px;height:38px;border-radius:10px; display:inline-flex; align-items:center; justify-content:center;
            background:rgba(255,255,255,.1); color:#fff; transition:all .25s;
        }
        .social-links a:hover { background:var(--anac-gold); color:var(--anac-primary-dd); }
        .copyright { border-top:1px solid rgba(255,255,255,.12); margin-top:40px; padding:22px 0; text-align:center; color:rgba(255,255,255,.6); font-size:.85rem; }

        /* ===================== RESPONSIVE ===================== */
        @media (max-width: 991.98px) {
            #mainNav { background: rgba(20,38,79,.97); }
            .navbar-collapse { background:rgba(20,38,79,.98); border-radius:12px; padding:14px; margin-top:10px; }
            .hero { padding:120px 0 60px; text-align:center; }
            .hero .checks { justify-content:center; }
            .hero .lead { margin-left:auto; margin-right:auto; }
            .login-card { margin-top:36px; max-width:440px; margin-left:auto; margin-right:auto; }
            .stat-box { border-right:none; border-bottom:1px solid #eef2f7; }
        }
        @media (max-width: 575.98px) {
            .hero h1 { font-size:1.9rem; }
            .login-body { padding:24px 20px; }
            .btn-anac-gold, .btn-ghost { width:100%; }
        }
    </style>
</head>
<body>

    <div class="flag-bar"></div>

    <!-- ================= NAVIGATION ================= -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top" id="mainNav">
        <div class="container">
            <a class="navbar-brand" href="#accueil">
                <img src="<?php echo $logo; ?>" alt="ANAC Gabon" onerror="this.style.display='none'">
                <span class="brand-text">AGAI<span class="brand-sub">ANAC Gabon</span></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-1">
                    <li class="nav-item"><a class="nav-link" href="#accueil">Accueil</a></li>
                    <li class="nav-item"><a class="nav-link" href="#fonctionnalites">Fonctionnalités</a></li>
                    <li class="nav-item"><a class="nav-link" href="#objectifs">Objectifs</a></li>
                    <li class="nav-item"><a class="nav-link" href="#securite">Sécurité</a></li>
                    <li class="nav-item"><a class="nav-link" href="#domaines">Domaines OACI</a></li>
                    <li class="nav-item"><a class="nav-link" href="#faq">FAQ</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-connexion ms-lg-2 px-4" href="#connexion">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Connexion
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- ================= HERO + LOGIN ================= -->
    <section id="accueil" class="hero">
        <span class="floating f1"></span>
        <span class="floating f2"></span>
        <span class="floating f3"></span>
        <div class="container hero-content">
            <div class="row align-items-center g-5">
                <div class="col-lg-6" data-aos="fade-right">
                    <span class="hero-badge"><i class="bi bi-shield-check"></i> Système Sécurité : ANAC</span>
                    <h1>Supervision de la sécurité et<br>la sûreté de <span class="accent">l'Aviation Civile</span></h1>
                    <p class="lead">
                        Plateforme nationale de suivi de l'exécution des activités de Supervision
                        de la sécurité et la sûreté de l'Aviation Civile du Gabon en temps réel.
                    </p>
                    <div class="checks">
                        <span><i class="bi bi-check-circle-fill"></i>Audits &amp; inspections</span>
                        <span><i class="bi bi-check-circle-fill"></i>Contrôle</span>
                        <span><i class="bi bi-check-circle-fill"></i>Gestion des rapports</span>
                        <span><i class="bi bi-check-circle-fill"></i>Non-conformités</span>
                        <span><i class="bi bi-check-circle-fill"></i>Suivi temps réel</span>
                    </div>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="#connexion" class="btn btn-anac-gold"><i class="bi bi-box-arrow-in-right me-2"></i>Accéder à mon espace</a>
                        <a href="#fonctionnalites" class="btn btn-ghost"><i class="bi bi-info-circle me-2"></i>En savoir plus</a>
                    </div>
                </div>

                <!-- Carte de connexion : visible sur TOUS les écrans, ancre #connexion -->
                <div class="col-lg-5 offset-lg-1" id="connexion" data-aos="fade-left">
                    <div class="login-card" id="loginCard">
                        <div class="login-head">
                            <img src="<?php echo $logo; ?>" alt="ANAC" onerror="this.style.display='none'">
                            <h4><i class="bi bi-shield-lock me-2"></i>Connexion sécurisée</h4>
                            <p>Espace réservé aux utilisateurs habilités</p>
                        </div>
                        <div class="login-body">
                            <form id="loginForm" method="POST" action="" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo Security::escape($csrf_token); ?>">

                                <div class="mb-3">
                                    <label class="form-label" for="email">Email professionnel</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-envelope-fill"></i></span>
                                        <input type="email" class="form-control" name="email" id="email"
                                               placeholder="prenom.nom@anac-gabon.com" autocomplete="username" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="password">Mot de passe</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                        <input type="password" class="form-control" name="password" id="password"
                                               placeholder="••••••••" autocomplete="current-password" required>
                                        <button type="button" class="btn btn-outline-secondary" id="togglePassword" aria-label="Afficher le mot de passe">
                                            <i class="bi bi-eye-fill"></i>
                                        </button>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-login" id="loginButton">
                                    <i class="bi bi-box-arrow-in-right me-2"></i> Se connecter
                                </button>

                                <p class="text-center mt-3 mb-0">
                                    <small class="text-muted">
                                        <i class="bi bi-shield-check me-1"></i>Un code OTP vous sera envoyé par email (2FA)
                                    </small>
                                </p>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= STAT STRIP ================= -->
    <div class="stat-strip">
        <div class="container">
            <div class="row">
                <div class="col-6 col-md-3"><div class="stat-box"><i class="bi bi-diagram-3 fs-3"></i><div class="num">07</div><div class="lbl">Domaines critiques OACI</div></div></div>
                <div class="col-6 col-md-3"><div class="stat-box"><i class="bi bi-clipboard-check fs-3"></i><div class="num">100%</div><div class="lbl">Traçabilité des actions</div></div></div>
                <div class="col-6 col-md-3"><div class="stat-box"><i class="bi bi-shield-lock fs-3"></i><div class="num">2FA</div><div class="lbl">Double authentification</div></div></div>
                <div class="col-6 col-md-3"><div class="stat-box"><i class="bi bi-clock-history fs-3"></i><div class="num">24/7</div><div class="lbl">Surveillance continue</div></div></div>
            </div>
        </div>
    </div>

    <!-- ================= FONCTIONNALITÉS ================= -->
    <section id="fonctionnalites" class="py-5" style="background:#fff;">
        <div class="container py-4">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="section-title">Fonctionnalités <span class="hl">principales</span></h2>
                <p class="section-sub">Un système complet pour la gestion des activités de surveillance continue.</p>
            </div>
            <div class="row g-4">
                <?php
                $features = [
                    ['bi-clipboard-check', "Programmation d'audits/Inspections", "Planification et suivi des audits et inspections selon les exigences OACI."],
                    ['bi-exclamation-triangle', "Gestion des non-conformités", "Suivi complet des écarts avec catégorisation Critique · Majeur · Mineur."],
                    ['bi-people', "Gestion des inspecteurs", "Habilitations, domaines de compétence et suivi des qualifications."],
                    ['bi-clock-history', "Traçabilité complète", "Historique des modifications et journalisation de toutes les actions."],
                    ['bi-bell', "Notifications temps réel", "Alertes par email pour les nouvelles missions et rapports."],
                    ['bi-file-earmark-pdf', "Rapports & statistiques", "Génération de rapports PDF et tableaux de bord analytiques."],
                ];
                $d = 0;
                foreach ($features as $f):
                    $d += 100;
                ?>
                <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="<?php echo $d; ?>">
                    <div class="feature-card text-center">
                        <div class="feature-icon mx-auto"><i class="bi <?php echo $f[0]; ?>"></i></div>
                        <h5 class="fw-bold"><?php echo $f[1]; ?></h5>
                        <p class="text-muted small mb-0"><?php echo $f[2]; ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ================= OBJECTIFS ================= -->
    <section id="objectifs" class="py-5 bg-soft">
        <div class="container py-4">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="section-title">Nos <span class="hl">objectifs</span></h2>
                <p class="section-sub">AGAI répond aux besoins spécifiques de la supervision de la sécurité et de la sûreté de l'aviation civile.</p>
            </div>
            <div class="row g-4">
                <?php
                $objectifs = [
                    ['Surveillance continue', "Assurer un suivi permanent des activités des opérateurs aériens conformément aux exigences OACI.", 'fade-right'],
                    ['Conformité réglementaire', "Respect des normes et recommandations de l'Organisation de l'Aviation Civile Internationale.", 'fade-right'],
                    ['Digitalisation', "Modernisation des processus de supervision et de contrôle pour une meilleure efficacité.", 'fade-left'],
                    ['Transparence', "Traçabilité complète des actions et décisions de supervision pour une gouvernance exemplaire.", 'fade-left'],
                ];
                foreach ($objectifs as $o):
                ?>
                <div class="col-md-6" data-aos="<?php echo $o[2]; ?>">
                    <div class="objectif-item">
                        <div class="d-flex align-items-start gap-3">
                            <div class="obj-icon"><i class="bi bi-check-circle-fill fs-4"></i></div>
                            <div>
                                <h6 class="fw-bold mb-1"><?php echo $o[0]; ?></h6>
                                <p class="text-muted small mb-0"><?php echo $o[1]; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ================= SÉCURITÉ ================= -->
    <section id="securite" class="py-5" style="background:#fff;">
        <div class="container py-4">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="section-title">Sécurité <span class="hl">renforcée</span></h2>
                <p class="section-sub">Protection maximale des données sensibles, conforme aux bonnes pratiques OWASP.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-6" data-aos="flip-left">
                    <div class="securite-card">
                        <div class="sec-icon" style="background:rgba(35,64,143,.08); color:var(--anac-primary);"><i class="bi bi-person-check"></i></div>
                        <h5 class="fw-bold">Authentification 2FA</h5>
                        <p class="text-muted small">Code OTP envoyé par email pour une connexion sécurisée et une protection contre les accès non autorisés.</p>
                        <ul class="small text-muted ps-3 mb-0">
                            <li><i class="bi bi-check-circle-fill text-success me-2"></i>Codes à usage unique</li>
                            <li><i class="bi bi-check-circle-fill text-success me-2"></i>Validité limitée à 10 minutes</li>
                            <li><i class="bi bi-check-circle-fill text-success me-2"></i>Notifications par email</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6" data-aos="flip-right">
                    <div class="securite-card">
                        <div class="sec-icon" style="background:rgba(30,156,75,.08); color:var(--anac-secondary);"><i class="bi bi-shield-lock"></i></div>
                        <h5 class="fw-bold">Contrôle d'accès RBAC</h5>
                        <p class="text-muted small">Gestion fine des permissions selon le profil utilisateur :</p>
                        <div class="row g-2 mt-1">
                            <div class="col-6"><div class="p-2 rounded" style="background:rgba(35,64,143,.05);border-left:3px solid var(--anac-primary);"><small class="fw-bold" style="color:var(--anac-primary);">Administrateur</small><div class="small text-muted">Accès complet</div></div></div>
                            <div class="col-6"><div class="p-2 rounded" style="background:rgba(35,64,143,.05);border-left:3px solid var(--anac-primary-d);"><small class="fw-bold" style="color:var(--anac-primary-d);">Inspecteur</small><div class="small text-muted">Audits &amp; inspections</div></div></div>
                            <div class="col-6"><div class="p-2 rounded" style="background:rgba(30,156,75,.05);border-left:3px solid var(--anac-secondary);"><small class="fw-bold" style="color:var(--anac-secondary);">Opérateur</small><div class="small text-muted">Consultation &amp; réponse</div></div></div>
                            <div class="col-6"><div class="p-2 rounded" style="background:rgba(243,195,0,.08);border-left:3px solid var(--anac-gold);"><small class="fw-bold" style="color:#b89600;">Consultant</small><div class="small text-muted">Lecture seule</div></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= DOMAINES OACI ================= -->
    <section id="domaines" class="py-5 bg-soft">
        <div class="container py-4">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="section-title">Domaines <span class="hl">OACI</span></h2>
                <p class="section-sub">Les domaines critiques de supervision selon les normes de l'OACI.</p>
            </div>
            <div class="row g-3 justify-content-center" data-aos="fade-up">
                <?php
                // Liste fixe des 7 domaines critiques affiches sur la page publique
                // (independante de la table `domaine` utilisee dans le module de gestion interne).
                $domainesOaci = [
                    ['AGA',   'Aérodromes',                       'bi-buildings',       'var(--anac-primary)'],
                    ['ANS',   'Services de Navigation Aérienne',  'bi-diagram-3',       'var(--anac-secondary)'],
                    ['AVSEC', 'Sûreté de l\'Aviation',            'bi-shield-lock-fill','var(--anac-primary-d)'],
                    ['FAL',   'Facilitation',                     'bi-globe',           'var(--anac-gold)'],
                    ['AIR',   'Navigabilité',                     'bi-tools',           'var(--anac-primary)'],
                    ['OPS',   'Opérations Aériennes',             'bi-airplane-fill',   'var(--anac-secondary)'],
                    ['PEL',   'Licences du Personnel',            'bi-person-badge',    'var(--anac-primary-d)'],
                ];
                foreach ($domainesOaci as $dom):
                ?>
                <div class="col-lg-3 col-md-4 col-6">
                    <div class="domaine-card">
                        <div class="domaine-icon" style="background:<?php echo $dom[3]; ?>;"><i class="bi <?php echo $dom[2]; ?>"></i></div>
                        <div class="domaine-code"><?php echo $dom[0]; ?></div>
                        <div class="domaine-name"><?php echo $dom[1]; ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ================= FAQ ================= -->
    <section id="faq" class="py-5" style="background:#fff;">
        <div class="container py-4">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="section-title">Foire aux <span class="hl">questions</span></h2>
                <p class="section-sub">Les réponses aux questions les plus fréquentes.</p>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <?php
                    $faqs = [
                        ["Comment accéder au système AGAI ?", "Utilisez vos identifiants ANAC (email professionnel et mot de passe). Un code OTP vous sera envoyé par email pour la double authentification."],
                        ["Qu'est-ce que la double authentification ?", "C'est une sécurité supplémentaire qui vous protège contre les accès non autorisés. Après votre mot de passe, un code à 6 chiffres vous est envoyé par email."],
                        ["Que faire si je ne reçois pas le code OTP ?", "Vérifiez vos spams. Si le code n'arrive pas, utilisez la fonction « Renvoyer le code » sur la page de vérification."],
                        ["Les opérateurs ont-ils accès au système ?", "Oui, chaque opérateur dispose de sa propre session. Ils reçoivent les notifications par email et peuvent consulter leurs audits et répondre aux actions correctives."],
                        ["Comment signaler une non-conformité ?", "Après connexion, allez dans le menu « Non-conformités » puis « Nouvelle fiche » et renseignez les champs requis."],
                        ["Que se passe-t-il après plusieurs échecs de connexion ?", "Après 5 tentatives échouées, le compte est verrouillé par mesure de sécurité. Seul un administrateur ou le chef inspecteur peut le réactiver manuellement."],
                        ["Mon compte a été désactivé, que faire ?", "Contactez un administrateur ou le chef inspecteur ANAC : ils peuvent vous indiquer le motif et réactiver votre accès si nécessaire."],
                        ["J'ai oublié mon mot de passe, comment faire ?", "Contactez un administrateur : il peut générer un nouveau mot de passe temporaire, envoyé automatiquement sur votre email professionnel."],
                        ["Quels sont les différents profils utilisateurs ?", "AGAI distingue cinq profils : Administrateur, Chef inspecteur, Inspecteur, Opérateur et Consultant, chacun avec des droits d'accès adaptés à son rôle."],
                        ["AGAI fonctionne-t-il sur tablette et mobile ?", "Oui, l'interface est entièrement responsive et s'adapte automatiquement aux ordinateurs, tablettes et smartphones."],
                        ["Que signifient les domaines AVSEC et FAL ?", "AVSEC désigne la sûreté de l'aviation (protection contre les actes d'intervention illicite) et FAL la facilitation du transport aérien : deux domaines complémentaires à la sécurité (safety) au sens strict."],
                    ];
                    foreach ($faqs as $faq):
                    ?>
                    <div class="faq-item" data-aos="fade-up">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <span><?php echo $faq[0]; ?></span>
                            <i class="bi bi-chevron-down"></i>
                        </div>
                        <div class="faq-answer"><?php echo $faq[1]; ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= FOOTER ================= -->
    <footer id="contact" class="footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <img src="<?php echo $logo; ?>" alt="ANAC Gabon" class="footer-logo" onerror="this.style.display='none'">
                    <p style="color:rgba(255,255,255,.7);">Agence Nationale de l'Aviation Civile du Gabon<br>
                        <span style="color:rgba(255,255,255,.45);">Supervision de la sécurité et la sûreté de l'Aviation Civile</span></p>
                    <div class="social-links d-flex gap-2 mt-3">
                        <a href="#" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                        <a href="#" aria-label="X"><i class="bi bi-twitter-x"></i></a>
                        <a href="#" aria-label="LinkedIn"><i class="bi bi-linkedin"></i></a>
                        <a href="#" aria-label="YouTube"><i class="bi bi-youtube"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6">
                    <h5>Navigation</h5>
                    <ul>
                        <li><a href="#accueil">Accueil</a></li>
                        <li><a href="#fonctionnalites">Fonctionnalités</a></li>
                        <li><a href="#objectifs">Objectifs</a></li>
                        <li><a href="#securite">Sécurité</a></li>
                        <li><a href="#domaines">Domaines OACI</a></li>
                        <li><a href="#faq">FAQ</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6">
                    <h5>Contact</h5>
                    <ul>
                        <li><i class="bi bi-geo-alt"></i> ANAC Gabon, Libreville</li>
                        <li><i class="bi bi-telephone"></i> (+241) 11 44 56 54 / 58</li>
                        <li><i class="bi bi-envelope"></i> contact@anac-gabon.com</li>
                        <li><i class="bi bi-clock"></i> Lun – Ven : 7h30 – 15h30</li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6">
                    <h5>Ressources</h5>
                    <ul>
                        <li><a href="#" onclick="showComingSoon();return false;">Guide utilisateur</a></li>
                        <li><a href="#" onclick="showComingSoon();return false;">Manuel de procédures</a></li>
                        <li><a href="#" onclick="showComingSoon();return false;">Politique de sécurité</a></li>
                        <li><a href="#" onclick="showComingSoon();return false;">Mentions légales</a></li>
                    </ul>
                </div>
            </div>
            <div class="copyright">
                &copy; <?php echo date('Y'); ?> ANAC Gabon · AGAI v<?php echo defined('APP_VERSION') ? APP_VERSION : '1.0.0'; ?> · Tous droits réservés
            </div>
        </div>
    </footer>

    <!-- ================= SCRIPTS ================= -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ duration: 800, once: true, offset: 80 });

        // Navbar : effet au scroll
        const nav = document.getElementById('mainNav');
        window.addEventListener('scroll', () => nav.classList.toggle('scrolled', window.scrollY > 50));

        // Défilement fluide pour toutes les ancres (#connexion inclus -> menu Connexion OK)
        document.querySelectorAll('a[href^="#"]').forEach(a => {
            a.addEventListener('click', function (e) {
                const id = this.getAttribute('href');
                if (id.length <= 1) return;
                const el = document.querySelector(id);
                if (!el) return;
                e.preventDefault();
                const top = el.getBoundingClientRect().top + window.pageYOffset - 80;
                window.scrollTo({ top, behavior: 'smooth' });
                // Refermer le menu mobile
                const collapse = document.getElementById('navbarNav');
                if (collapse.classList.contains('show')) {
                    bootstrap.Collapse.getInstance(collapse)?.hide();
                }
            });
        });

        // FAQ
        window.toggleFaq = function (el) {
            const ans = el.nextElementSibling;
            const icon = el.querySelector('i');
            const open = ans.classList.contains('show');
            document.querySelectorAll('.faq-answer').forEach(a => {
                a.classList.remove('show');
                a.previousElementSibling.querySelector('i').className = 'bi bi-chevron-down';
            });
            if (!open) { ans.classList.add('show'); icon.className = 'bi bi-chevron-up'; }
        };

        // Afficher / masquer le mot de passe
        document.getElementById('togglePassword').addEventListener('click', function () {
            const inp = document.getElementById('password');
            const icon = this.querySelector('i');
            const show = inp.type === 'password';
            inp.type = show ? 'text' : 'password';
            icon.className = show ? 'bi bi-eye-slash-fill' : 'bi bi-eye-fill';
        });

        // Soumission du formulaire (AJAX)
        document.getElementById('loginForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = document.getElementById('loginButton');
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Connexion…';
            try {
                const res = await fetch('', { method: 'POST', body: new FormData(this), headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                if (data.success) {
                    if (data.requires_2fa) {
                        await Swal.fire({ icon: 'success', title: 'Code envoyé', text: data.message, timer: 1800, showConfirmButton: false, timerProgressBar: true });
                    }
                    window.location.href = data.redirect;
                } else {
                    Swal.fire({ icon: 'error', title: 'Échec de la connexion', text: data.message, confirmButtonColor: '#23408F' });
                    btn.disabled = false; btn.innerHTML = original;
                }
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Erreur technique', text: 'Une erreur est survenue. Veuillez réessayer.', confirmButtonColor: '#23408F' });
                btn.disabled = false; btn.innerHTML = original;
            }
        });

        window.showComingSoon = function () {
            Swal.fire({ icon: 'info', title: 'Bientôt disponible', text: 'Cette fonctionnalité sera disponible prochainement.', confirmButtonColor: '#23408F', timer: 2600, timerProgressBar: true });
        };

        // Léger effet de relief sur la carte (souris uniquement, pas sur mobile)
        const card = document.getElementById('loginCard');
        if (card && window.matchMedia('(hover:hover) and (pointer:fine)').matches) {
            card.addEventListener('mousemove', e => {
                const r = card.getBoundingClientRect();
                const rx = (e.clientY - r.top - r.height/2) / 30;
                const ry = (r.width/2 - (e.clientX - r.left)) / 30;
                card.style.transform = `perspective(1000px) rotateX(${rx}deg) rotateY(${ry}deg)`;
            });
            card.addEventListener('mouseleave', () => card.style.transform = 'perspective(1000px) rotateX(0) rotateY(0)');
        }
    </script>
</body>
</html>