<?php
/**
 * Layout admin (entete) - sidebar + topbar reutilisables.
 * Variables attendues : $pageTitle (string), $active (cle menu), $pageIcon (icone bi).
 * A inclure APRES les gardes de session/role de la page.
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__) . '/config/config.php'; }

$pageTitle = $pageTitle ?? 'AGAI';
$active    = $active ?? '';
$pageIcon  = $pageIcon ?? 'bi-grid';
$me        = $_SESSION['user'] ?? ['prenom' => '', 'nom' => '', 'role' => ''];
$initials  = strtoupper(mb_substr($me['prenom'] ?? '', 0, 1) . mb_substr($me['nom'] ?? '', 0, 1));

/* Menu : cle, libelle, icone, route, module RBAC, construit(true/false) */
$menu = [
    ['dashboard',  'Tableau de bord',          'bi-speedometer2',        'dashboard',      'dashboard',      true],
    ['programme',  'Programme de surveillance', 'bi-calendar3',           'programme',      'programme',      false],
    ['audits',     'Audits et inspections',     'bi-clipboard-check',     'audits',         'audits',         false],
    ['ncs',        'Non-conformites',           'bi-exclamation-triangle','nonconformites', 'nonconformites', false],
    ['actions',    'Actions correctives',       'bi-check2-square',       'actions',        'actions',        false],
    ['docs',       'Gestion documentaire',      'bi-folder2-open',        'documents',      'documents',      false],
    ['rapports',   'Rapports et statistiques',  'bi-graph-up',            'rapports',       'rapports',       false],
    ['domaines',   'Domaines critiques OACI',   'bi-shield-shaded',       'domaines',       'domaines',       false],
    ['users',      'Gestion des utilisateurs',  'bi-people',              'users',          'users',          true],
    ['parametres', 'Parametres',                'bi-gear',                'parametres',     'parametres',     false],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo Security::escape($pageTitle); ?> - AGAI</title>
<link rel="icon" href="<?php echo ASSETS_URL; ?>/images/faviconLOGOANAC.ico" type="image/x-icon">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
<style>
:root{
  --anac-primary:#23408F; --anac-primary-d:#1a3270; --anac-primary-dd:#14264f;
  --anac-secondary:#1E9C4B; --anac-gold:#F3C300; --anac-danger:#D32F2F;
  --anac-bg:#F5F7FA; --anac-text:#2C3E50; --anac-muted:#6b7a90;
  --sidebar-w:260px;
}
*{box-sizing:border-box;}
body{font-family:'Candara','Segoe UI',system-ui,sans-serif;background:var(--anac-bg);color:var(--anac-text);margin:0;}
.sidebar{position:fixed;top:0;left:0;width:var(--sidebar-w);height:100vh;background:linear-gradient(180deg,var(--anac-primary) 0%,var(--anac-primary-dd) 100%);color:#fff;display:flex;flex-direction:column;z-index:1040;transition:transform .3s ease;}
.sidebar .brand{padding:20px 22px;display:flex;align-items:center;gap:12px;border-bottom:1px solid rgba(255,255,255,.12);}
.sidebar .brand .logo{width:40px;height:40px;border-radius:10px;background:#fff;display:flex;align-items:center;justify-content:center;overflow:hidden;}
.sidebar .brand .logo img{max-width:100%;max-height:100%;}
.sidebar .brand .t{font-weight:700;font-size:1.25rem;line-height:1;}
.sidebar .brand .s{font-size:.6rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--anac-gold);}
.sidebar .menu{flex:1;overflow-y:auto;padding:14px 0;}
.sidebar .menu a{display:flex;align-items:center;gap:13px;padding:11px 22px;color:rgba(255,255,255,.82);text-decoration:none;font-size:.93rem;border-left:3px solid transparent;transition:all .18s;}
.sidebar .menu a i{font-size:1.1rem;width:20px;text-align:center;}
.sidebar .menu a:hover{background:rgba(255,255,255,.08);color:#fff;}
.sidebar .menu a.active{background:rgba(255,255,255,.12);color:#fff;border-left-color:var(--anac-gold);font-weight:600;}
.sidebar .menu a .soon{margin-left:auto;font-size:.6rem;background:rgba(255,255,255,.15);padding:2px 7px;border-radius:10px;color:#cdd7ea;}
.sidebar .foot{padding:14px 22px;border-top:1px solid rgba(255,255,255,.12);font-size:.72rem;color:rgba(255,255,255,.5);}
.topbar{position:fixed;top:0;left:var(--sidebar-w);right:0;height:72px;background:#fff;border-bottom:1px solid #e7ecf3;display:flex;align-items:center;justify-content:space-between;padding:0 24px;z-index:1030;box-shadow:0 2px 10px rgba(35,64,143,.04);}
.topbar::after{content:"";position:absolute;left:0;bottom:-1px;height:3px;width:100%;background:linear-gradient(90deg,var(--anac-primary) 0%,var(--anac-secondary) 50%,var(--anac-gold) 100%);}
.topbar .left{display:flex;align-items:center;gap:14px;}
.topbar .burger{display:none;border:none;background:transparent;font-size:1.4rem;color:var(--anac-primary);}
.topbar .pagebox{width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,var(--anac-primary),var(--anac-primary-d));color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.3rem;box-shadow:0 4px 12px rgba(35,64,143,.25);}
.topbar .ptitle{font-weight:700;font-size:1.15rem;color:var(--anac-text);line-height:1.15;}
.topbar .pcrumb{font-size:.74rem;color:var(--anac-muted);margin-top:2px;}
.topbar .pcrumb .sep{color:var(--anac-gold);margin:0 5px;}
.topbar .right{display:flex;align-items:center;gap:18px;}
.topbar .ic{position:relative;color:var(--anac-muted);font-size:1.25rem;text-decoration:none;}
.topbar .ic .dot{position:absolute;top:-3px;right:-4px;width:8px;height:8px;border-radius:50%;background:var(--anac-danger);}
.topbar .who{display:flex;align-items:center;gap:10px;cursor:pointer;}
.topbar .avatar{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--anac-primary),var(--anac-primary-d));color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;}
.topbar .who .nm{font-size:.85rem;line-height:1.1;}
.topbar .who .nm small{color:var(--anac-muted);}
.content{margin-left:var(--sidebar-w);padding:96px 24px 40px;min-height:100vh;}
.page-head{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:22px;}
.page-head h1{font-size:1.5rem;font-weight:700;margin:0;color:var(--anac-text);}
.page-head .sub{color:var(--anac-muted);font-size:.9rem;margin-top:2px;}
.card-anac{background:#fff;border:1px solid #eef2f7;border-radius:14px;box-shadow:0 2px 14px rgba(35,64,143,.06);}
.btn-anac{background:var(--anac-primary);border:none;color:#fff;font-weight:600;}
.btn-anac:hover{background:var(--anac-primary-d);color:#fff;}
.badge-soft{padding:.35em .7em;border-radius:50px;font-size:.74rem;font-weight:600;}
.b-green{background:rgba(30,156,75,.12);color:#157a3a;}
.b-gold{background:rgba(243,195,0,.18);color:#9a7d00;}
.b-red{background:rgba(211,47,47,.12);color:#b02525;}
.b-grey{background:#eef2f7;color:#5b6b80;}
.b-blue{background:rgba(35,64,143,.10);color:var(--anac-primary);}
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(20,38,79,.45);z-index:1035;}
table.dataTable thead th{background:#f4f7fb;color:var(--anac-text);font-size:.82rem;text-transform:uppercase;letter-spacing:.4px;}
.modal-header{background:var(--anac-primary);color:#fff;}
.modal-header .btn-close{filter:invert(1) grayscale(100%) brightness(200%);}
.form-label{font-weight:600;font-size:.85rem;}
.btn-add-org{background:var(--anac-secondary);border:none;color:#fff;font-weight:700;display:flex;align-items:center;justify-content:center;animation:pulseAdd 2s infinite;}
.btn-add-org:hover{background:#157a3a;color:#fff;}
@keyframes pulseAdd{0%{box-shadow:0 0 0 0 rgba(30,156,75,.45);}70%{box-shadow:0 0 0 8px rgba(30,156,75,0);}100%{box-shadow:0 0 0 0 rgba(30,156,75,0);}}
.select2-container{width:100% !important;}
.select2-container--bootstrap-5 .select2-selection{min-height:38px;border-color:#ced4da;}
.field-error{color:var(--anac-danger);font-size:.8rem;margin-top:4px;display:none;}
@media (max-width:991.98px){
  .sidebar{transform:translateX(-100%);}
  .sidebar.open{transform:translateX(0);}
  .topbar{left:0;}
  .content{margin-left:0;}
  .topbar .burger{display:inline-block;}
  .topbar .pcrumb{display:none;}
  .sidebar-overlay.show{display:block;}
}
</style>
</head>
<body>

<aside class="sidebar" id="sidebar">
  <div class="brand">
    <span class="logo"><img src="<?php echo ASSETS_URL; ?>/images/LOGOANAC.PNG" alt="ANAC" onerror="this.parentNode.innerHTML='<span style=\'color:#23408F;font-weight:700\'>A</span>'"></span>
    <span><span class="t">AGAI</span><br><span class="s">ANAC Gabon</span></span>
  </div>
  <nav class="menu">
    <?php foreach ($menu as [$key, $label, $icon, $route, $module, $built]):
        if (!Rbac::canAccess($module)) continue;
        $isActive = ($active === $key) ? ' active' : '';
    ?>
      <?php if ($built): ?>
        <a class="<?php echo trim($isActive); ?>" href="<?php echo SITE_URL . '/' . $route; ?>">
          <i class="bi <?php echo $icon; ?>"></i><span><?php echo $label; ?></span>
        </a>
      <?php else: ?>
        <a class="nav-soon<?php echo $isActive; ?>" href="#" data-module="<?php echo Security::escape($label); ?>">
          <i class="bi <?php echo $icon; ?>"></i><span><?php echo $label; ?></span><span class="soon">bientot</span>
        </a>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>
  <div class="foot">AGAI v<?php echo defined('APP_VERSION') ? APP_VERSION : '1.0.0'; ?> &middot; &copy; <?php echo date('Y'); ?></div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<header class="topbar">
  <div class="left">
    <button class="burger" id="burger" aria-label="Menu"><i class="bi bi-list"></i></button>
    <div class="pagebox"><i class="bi <?php echo Security::escape($pageIcon); ?>"></i></div>
    <div>
      <div class="ptitle"><?php echo Security::escape($pageTitle); ?></div>
      <div class="pcrumb"><i class="bi bi-house-door"></i> Accueil <span class="sep">&rsaquo;</span> <?php echo Security::escape($pageTitle); ?></div>
    </div>
  </div>
  <div class="right">
    <a href="#" class="ic" title="Notifications"><i class="bi bi-bell"></i><span class="dot"></span></a>
    <div class="dropdown">
      <div class="who" data-bs-toggle="dropdown" aria-expanded="false">
        <span class="avatar"><?php echo Security::escape($initials ?: 'AG'); ?></span>
        <span class="nm">
          <?php echo Security::escape(trim(($me['prenom'] ?? '') . ' ' . ($me['nom'] ?? ''))); ?><br>
          <small><?php echo Security::escape(Rbac::roleLabel($me['role'] ?? '')); ?></small>
        </span>
        <i class="bi bi-chevron-down text-muted"></i>
      </div>
      <ul class="dropdown-menu dropdown-menu-end shadow">
        <li><span class="dropdown-item-text small text-muted"><?php echo Security::escape($me['email'] ?? ''); ?></span></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/profile"><i class="bi bi-person-circle me-2"></i>Mon profil</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="<?php echo SITE_URL; ?>/logout"><i class="bi bi-box-arrow-right me-2"></i>Deconnexion</a></li>
      </ul>
    </div>
  </div>
</header>

<main class="content">