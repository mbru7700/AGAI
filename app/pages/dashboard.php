<?php
/**
 * Tableau de bord AGAI - ANAC Gabon
 * Migré vers le gabarit commun (sidebar + topbar partagés).
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }

Rbac::guardPage('dashboard');          // connecté (tous les rôles ont accès)
$user = $_SESSION['user'];
$csrf = Security::generateCSRF();
$db   = Database::getInstance();

/* ---- Statistiques (mêmes requêtes qu'avant) ---- */
$audits_programmes = (int) $db->query("SELECT COUNT(*) FROM audit WHERE statut = 1")->fetchColumn();
$audits_realises   = (int) $db->query("SELECT COUNT(*) FROM audit WHERE statut = 3")->fetchColumn();
$fnc_ouvertes      = (int) $db->query("SELECT COUNT(*) FROM fiche_non_conformite WHERE statut = 4")->fetchColumn();
$fnc_fermees       = (int) $db->query("SELECT COUNT(*) FROM fiche_non_conformite WHERE statut = 3")->fetchColumn();

$total_fnc       = $fnc_ouvertes + $fnc_fermees;
$taux_conformite = $total_fnc > 0 ? (int) round(($fnc_fermees / $total_fnc) * 100) : 100;
$taux_class      = $taux_conformite >= 80 ? 'b-green' : ($taux_conformite >= 50 ? 'b-gold' : 'b-red');
$bar_color       = $taux_conformite >= 80 ? 'var(--anac-secondary)' : ($taux_conformite >= 50 ? 'var(--anac-gold)' : 'var(--anac-danger)');

$recent_activities = $db->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 10")->fetchAll();
$domaines          = $db->query("SELECT * FROM domaine ORDER BY nomdomaine")->fetchAll();

$pageTitle = 'Tableau de bord';
$active    = 'dashboard';
$pageIcon  = 'bi-speedometer2';
require_once INCLUDES_PATH . '/layout_head.php';
?>

<style>
.kpi{display:flex;align-items:center;gap:16px;padding:20px;height:100%;}
.kpi .ic{width:54px;height:54px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0;}
.kpi .v{font-size:1.9rem;font-weight:700;color:var(--anac-text);line-height:1;}
.kpi .l{color:var(--anac-muted);font-size:.85rem;margin-top:5px;}
.kpi.blue .ic{background:rgba(35,64,143,.12);color:var(--anac-primary);}
.kpi.green .ic{background:rgba(30,156,75,.12);color:var(--anac-secondary);}
.kpi.gold .ic{background:rgba(243,195,0,.20);color:#9a7d00;}
.kpi.red .ic{background:rgba(211,47,47,.12);color:var(--anac-danger);}
.act{display:flex;gap:14px;padding:14px 18px;border-bottom:1px solid #f0f3f8;}
.act:last-child{border-bottom:0;}
.act .ai{width:38px;height:38px;border-radius:50%;background:rgba(35,64,143,.08);color:var(--anac-primary);display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.act p{margin:0;font-size:.92rem;color:var(--anac-text);}
.act .t{font-size:.78rem;color:var(--anac-muted);}
.dbadge{display:inline-block;margin:3px;padding:.4em .8em;border-radius:50px;font-size:.78rem;font-weight:600;background:rgba(35,64,143,.10);color:var(--anac-primary);}
</style>

<div class="page-head">
  <div>
    <h1><i class="bi bi-speedometer2 me-2" style="color:var(--anac-primary)"></i>Tableau de bord</h1>
    <div class="sub">Surveillance continue de la sécurité aérienne - vue d'ensemble.</div>
  </div>
  <span class="badge-soft b-blue"><i class="bi bi-clock me-1"></i><?php echo date('d/m/Y H:i'); ?></span>
</div>

<!-- KPI -->
<div class="row g-3 mb-3">
  <div class="col-xl-3 col-md-6">
    <div class="card-anac kpi blue">
      <div class="ic"><i class="bi bi-calendar-check"></i></div>
      <div><div class="v"><?php echo $audits_programmes; ?></div><div class="l">Audits programmés</div></div>
    </div>
  </div>
  <div class="col-xl-3 col-md-6">
    <div class="card-anac kpi green">
      <div class="ic"><i class="bi bi-check-circle"></i></div>
      <div><div class="v"><?php echo $audits_realises; ?></div><div class="l">Audits réalisés</div></div>
    </div>
  </div>
  <div class="col-xl-3 col-md-6">
    <div class="card-anac kpi gold">
      <div class="ic"><i class="bi bi-exclamation-triangle"></i></div>
      <div><div class="v"><?php echo $fnc_ouvertes; ?></div><div class="l">Non-conformités ouvertes</div></div>
    </div>
  </div>
  <div class="col-xl-3 col-md-6">
    <div class="card-anac kpi green">
      <div class="ic"><i class="bi bi-check2-circle"></i></div>
      <div><div class="v"><?php echo $fnc_fermees; ?></div><div class="l">Non-conformités clôturées</div></div>
    </div>
  </div>
</div>

<!-- Taux de conformité -->
<div class="card-anac p-4 mb-3">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <span class="fw-bold" style="color:var(--anac-text)"><i class="bi bi-graph-up me-2" style="color:var(--anac-primary)"></i>Taux de conformité global</span>
    <span class="badge-soft <?php echo $taux_class; ?>"><?php echo $taux_conformite; ?>%</span>
  </div>
  <div class="progress" style="height:12px;border-radius:50px;">
    <div class="progress-bar" role="progressbar" style="width:<?php echo $taux_conformite; ?>%;background:<?php echo $bar_color; ?>;border-radius:50px;" aria-valuenow="<?php echo $taux_conformite; ?>" aria-valuemin="0" aria-valuemax="100"></div>
  </div>
  <div class="sub mt-2" style="font-size:.82rem;color:var(--anac-muted)">Basé sur <?php echo $total_fnc; ?> non-conformité(s) suivie(s).</div>
</div>

<div class="row g-3">
  <!-- Dernières activités -->
  <div class="col-lg-8">
    <div class="card-anac">
      <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
        <span class="fw-bold" style="color:var(--anac-text)"><i class="bi bi-clock-history me-2" style="color:var(--anac-primary)"></i>Dernières activités</span>
      </div>
      <div>
        <?php if (!empty($recent_activities)): ?>
          <?php
          $icon_map = ['login'=>'bi-box-arrow-in-right','logout'=>'bi-box-arrow-left','create'=>'bi-plus-circle',
                       'update'=>'bi-pencil-square','delete'=>'bi-trash','password_reset'=>'bi-key',
                       'access_denied'=>'bi-shield-x','default'=>'bi-dot'];
          foreach ($recent_activities as $a):
            $icon = $icon_map[$a['action']] ?? $icon_map['default'];
          ?>
          <div class="act">
            <div class="ai"><i class="bi <?php echo $icon; ?>"></i></div>
            <div class="flex-grow-1">
              <p><?php echo Security::escape($a['description'] ?: $a['action']); ?></p>
              <span class="t"><i class="bi bi-clock me-1"></i><?php echo date('d/m/Y H:i', strtotime($a['created_at'])); ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-3 opacity-25"></i>Aucune activité récente</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Domaines OACI + profil -->
  <div class="col-lg-4">
    <div class="card-anac p-3 mb-3">
      <span class="fw-bold d-block mb-2" style="color:var(--anac-text)"><i class="bi bi-shield-shaded me-2" style="color:var(--anac-primary)"></i>Domaines OACI</span>
      <?php if (!empty($domaines)): ?>
        <?php foreach ($domaines as $d): ?>
          <span class="dbadge"><?php echo Security::escape($d['nomdomaine']); ?></span>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="text-muted small mb-0">Aucun domaine configuré.</p>
      <?php endif; ?>
    </div>

    <div class="card-anac p-3">
      <span class="fw-bold d-block mb-3" style="color:var(--anac-text)"><i class="bi bi-person-circle me-2" style="color:var(--anac-primary)"></i>Profil</span>
      <div class="d-flex align-items-center gap-3 mb-3">
        <span style="width:48px;height:48px;border-radius:50%;background:rgba(243,195,0,.18);color:#9a7d00;display:flex;align-items:center;justify-content:center;font-weight:700;">
          <?php echo strtoupper(mb_substr($user['prenom'] ?? 'U', 0, 1)); ?>
        </span>
        <div>
          <div class="fw-bold"><?php echo Security::escape(trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''))); ?></div>
          <div class="text-muted small"><?php echo Security::escape($user['email'] ?? ''); ?></div>
        </div>
      </div>
      <div class="row g-2 small">
        <div class="col-6"><span class="text-muted">Matricule</span><div class="fw-bold"><?php echo Security::escape($user['matricule'] ?? 'N/A'); ?></div></div>
        <div class="col-6"><span class="text-muted">Rôle</span><div class="fw-bold"><?php echo Security::escape(Rbac::roleLabel($user['role'] ?? '')); ?></div></div>
      </div>
    </div>
  </div>
</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>
