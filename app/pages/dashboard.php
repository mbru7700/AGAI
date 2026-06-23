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
    <div class="sub">Surveillance continue de la securite aerienne - vue d'ensemble.</div>
  </div>
  <span class="badge-soft b-blue"><i class="bi bi-clock me-1"></i><?php echo date('d/m/Y H:i'); ?></span>
</div>

<?php if (in_array(Rbac::role(), ['admin', 'chef_inspecteur'], true)): ?>
<!-- ===== LANCEUR : programmer un acte de supervision (admin / chef inspecteur) ===== -->
<style>
  .launcher{background:linear-gradient(135deg,#23408F 0%,#1b3576 100%);border-radius:16px;padding:20px 22px;margin-bottom:20px;box-shadow:0 6px 20px rgba(35,64,143,.18);}
  .launcher .lh{display:flex;align-items:center;gap:10px;color:#fff;margin-bottom:14px;}
  .launcher .lh i{font-size:1.3rem;color:var(--anac-gold);}
  .launcher .lh .lt{font-weight:700;font-size:1.05rem;}
  .launcher .lh .ls{font-size:.82rem;color:#c7d2ec;}
  .nat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;}
  .nat-tile{background:rgba(255,255,255,.10);border:1px solid rgba(255,255,255,.16);border-radius:12px;padding:16px 12px;text-align:center;cursor:pointer;color:#fff;transition:all .18s;}
  .nat-tile:hover{background:#fff;color:var(--anac-primary);transform:translateY(-2px);box-shadow:0 6px 16px rgba(0,0,0,.18);}
  .nat-tile i{font-size:1.7rem;display:block;margin-bottom:8px;color:var(--anac-gold);}
  .nat-tile:hover i{color:var(--anac-primary);}
  .nat-tile .nt{font-size:.84rem;font-weight:600;line-height:1.2;}
  .cadre-opt{display:block;border:1px solid #e8edf5;border-radius:10px;padding:11px 14px;margin-bottom:8px;cursor:pointer;transition:all .15s;}
  .cadre-opt:hover{border-color:var(--anac-primary);background:#f7f9fc;}
  .cadre-opt input{margin-right:9px;}
  .cadre-opt.sel{border-color:var(--anac-primary);background:rgba(35,64,143,.06);}
</style>
<div class="launcher">
  <div class="lh">
    <i class="bi bi-plus-circle-fill"></i>
    <div>
      <div class="lt">Programmer un acte de supervision</div>
      <div class="ls">Choisissez la nature de la supervision pour demarrer la planification.</div>
    </div>
  </div>
  <div class="nat-grid">
    <div class="nat-tile" data-type="audit"><i class="bi bi-clipboard-check"></i><div class="nt">Audit</div></div>
    <div class="nat-tile" data-type="inspection_programmee"><i class="bi bi-calendar-check"></i><div class="nt">Inspection programmee</div></div>
    <div class="nat-tile" data-type="inspection_non_programmee"><i class="bi bi-calendar-x"></i><div class="nt">Inspection non programmee</div></div>
    <div class="nat-tile" data-type="demonstration"><i class="bi bi-easel"></i><div class="nt">Demonstration</div></div>
    <div class="nat-tile" data-type="test"><i class="bi bi-bullseye"></i><div class="nt">Test</div></div>
    <div class="nat-tile" data-type="investigation"><i class="bi bi-search"></i><div class="nt">Investigation</div></div>
  </div>
</div>
<?php endif; ?>

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

<?php if (in_array(Rbac::role(), ['admin', 'chef_inspecteur'], true)): ?>
<!-- ===== MODALE : Dans le cadre ? ===== -->
<div class="modal fade" id="cadreModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-question-circle me-2" style="color:var(--anac-primary)"></i>Dans quel cadre ?</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3">Nature choisie : <b id="cadreNature" style="color:var(--anac-primary)"></b>. Selectionnez le cadre de la supervision.</p>
        <div id="cadreList">
          <label class="cadre-opt"><input type="radio" name="cadre" value="certification">Certification</label>
          <label class="cadre-opt"><input type="radio" name="cadre" value="homologation">Homologation</label>
          <label class="cadre-opt"><input type="radio" name="cadre" value="reconnaissance">Reconnaissance</label>
          <label class="cadre-opt"><input type="radio" name="cadre" value="renouvellement">Renouvellement</label>
          <label class="cadre-opt"><input type="radio" name="cadre" value="surveillance_continue">Surveillance continue</label>
          <label class="cadre-opt"><input type="radio" name="cadre" value="traitement_evenement">Traitement d'un evenement</label>
          <label class="cadre-opt"><input type="radio" name="cadre" value="fermeture_provisoire">Fermeture provisoire</label>
          <label class="cadre-opt"><input type="radio" name="cadre" value="fermeture_definitive">Fermeture definitive</label>
          <label class="cadre-opt"><input type="radio" name="cadre" value="delivrance_autorisation">Delivrance d'une autorisation</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-anac" id="cadreContinue" disabled><i class="bi bi-arrow-right-circle me-1"></i>Continuer</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>

<?php if (in_array(Rbac::role(), ['admin', 'chef_inspecteur'], true)): ?>
<script>
(function(){
  const NAT = {
    audit:'Audit', inspection_programmee:'Inspection programmee', inspection_non_programmee:'Inspection non programmee',
    demonstration:'Demonstration', test:'Test', investigation:'Investigation'
  };
  let selType = '';
  const modal = new bootstrap.Modal('#cadreModal');

  $('.nat-tile').on('click', function(){
    selType = $(this).data('type');
    $('#cadreNature').text(NAT[selType] || selType);
    $('input[name="cadre"]').prop('checked', false);
    $('.cadre-opt').removeClass('sel');
    $('#cadreContinue').prop('disabled', true);
    modal.show();
  });

  $(document).on('change', 'input[name="cadre"]', function(){
    $('.cadre-opt').removeClass('sel');
    $(this).closest('.cadre-opt').addClass('sel');
    $('#cadreContinue').prop('disabled', false);
  });

  $('#cadreContinue').on('click', function(){
    const cadre = $('input[name="cadre"]:checked').val();
    if(!selType || !cadre) return;
    window.location = AGAI_BASE + '/declenchement?type=' + encodeURIComponent(selType) + '&cadre=' + encodeURIComponent(cadre);
  });
})();
</script>
<?php endif; ?>