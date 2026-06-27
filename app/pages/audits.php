<?php
/**
 * Audits et inspections - Page principale refactorisee
 * Stats KPI couleurs ANAC, graphique annee/mois, tableau filtre, modale detail complete, PDF/Excel
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardPage('audits');

$csrf      = Security::generateCSRF();
$role      = Rbac::role();
$pageTitle = 'Audits et inspections';
$active    = 'audits';
$isOper    = ($role === 'operateur');
$canWrite  = in_array($role, ['admin', 'chef_inspecteur'], true);
$canDelete = in_array($role, ['admin', 'chef_inspecteur'], true);

require_once INCLUDES_PATH . '/layout_head.php';
?>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<style>
/* ---- KPI compactes ---- */
.kpi-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:8px;margin-bottom:0;}
.kpi-card{background:#fff;border:1px solid #eef1f6;border-radius:12px;padding:10px 12px;box-shadow:0 1px 2px rgba(16,30,54,.04);display:flex;align-items:center;gap:9px;}
.kpi-ic{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1rem;flex:0 0 auto;}
.kpi-num{font-size:1.3rem;font-weight:800;line-height:1;color:#2C3E50;}
.kpi-lbl{font-size:.68rem;color:#6b7a90;margin-top:1px;}
.kpi-taux{font-size:.95rem;font-weight:700;}
.ic-plan{background:rgba(35,64,143,.12);color:#23408F;}
.ic-rep{background:rgba(243,195,0,.2);color:#b58a00;}
.ic-eff{background:rgba(30,156,75,.13);color:#1E9C4B;}
.ic-sus{background:rgba(211,47,47,.12);color:#D32F2F;}
.ic-total{background:rgba(44,62,80,.09);color:#2C3E50;}
.ic-exec{background:rgba(30,156,75,.13);color:#1E9C4B;}
/* ---- Panneau stats depliable ---- */
.stats-panel{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:12px 14px;box-shadow:0 1px 2px rgba(16,30,54,.04);margin-bottom:12px;}
.stats-toggle{display:flex;align-items:center;justify-content:space-between;cursor:pointer;user-select:none;}
.stats-toggle h6{margin:0;font-size:.88rem;font-weight:700;color:#2C3E50;}
.stats-body{margin-top:12px;}
/* ---- Graphiques 3 colonnes ---- */
.chart-3{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:12px;}
.chart-mini{background:#f7f9fc;border-radius:10px;padding:10px 12px;}
.chart-mini h6{font-size:.78rem;font-weight:700;color:#2C3E50;margin:0 0 6px;display:flex;align-items:center;gap:5px;}
/* ---- Filtres ---- */
.filter-bar{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:10px 14px;margin-bottom:12px;box-shadow:0 1px 2px rgba(16,30,54,.04);}
/* ---- Tableau ---- */
.tbl-wrap{background:#fff;border:1px solid #eef1f6;border-radius:14px;overflow:hidden;box-shadow:0 1px 2px rgba(16,30,54,.04);}
table.tbl{width:100%;border-collapse:separate;border-spacing:0;}
table.tbl thead th{background:#23408F;color:#fff;font-size:.71rem;text-transform:uppercase;letter-spacing:.04em;padding:9px 10px;border-bottom:2px solid #1b3576;white-space:nowrap;font-weight:600;}
table.tbl thead th:first-child{border-radius:0;}
table.tbl tbody td{padding:8px 10px;border-bottom:1px solid #f1f4f9;vertical-align:middle;font-size:.85rem;}
table.tbl tbody tr:hover{filter:brightness(.97);}
/* Couleur de fond par statut */
table.tbl tbody tr.st-1{background:#f0f4ff;}
table.tbl tbody tr.st-2{background:#fffbeb;}
table.tbl tbody tr.st-3{background:#f0fdf4;}
table.tbl tbody tr.st-4{background:#fff5f5;}
table.tbl tbody tr.st-5{background:#faf5ff;}
table.tbl tbody tr.st-6{background:#f8f9fa;}
table.tbl tbody tr.st-7{background:#e8f0fe;}
table.tbl tbody tr.st-ferme{background:#f5f5f5;opacity:.8;}
.empty{padding:30px;text-align:center;color:#9aa7bd;}
.sb{display:inline-block;padding:.18rem .5rem;border-radius:20px;font-size:.69rem;font-weight:700;white-space:nowrap;}
.sb1{background:#e8f0fe;color:#23408F;} .sb2{background:#fff3cd;color:#856404;}
.sb3{background:#d1e7dd;color:#0a5c36;} .sb4{background:#f8d7da;color:#842029;}
.sb5{background:#f0e6ff;color:#5a189a;} .sb0{background:#e2e3e5;color:#383d41;}
.type-b{background:rgba(35,64,143,.10);color:#23408F;font-size:.69rem;font-weight:700;padding:.15rem .45rem;border-radius:20px;}
.nat-tile{background:rgba(255,255,255,.10);border:1px solid rgba(255,255,255,.18);border-radius:12px;padding:13px 9px;text-align:center;cursor:pointer;color:#fff;transition:all .17s;min-width:120px;}
.nat-tile:hover{background:#fff;color:#23408F;transform:translateY(-2px);}
.nat-tile:hover i{color:#23408F!important;}
.nat-tile i{font-size:1.5rem;display:block;margin-bottom:6px;color:#F3C300;}
.nat-tile .nt{font-size:.8rem;font-weight:600;}
.cadre-opt{display:block;border:1px solid #e8edf5;border-radius:9px;padding:9px 14px;margin-bottom:6px;cursor:pointer;transition:all .13s;}
.cadre-opt:hover,.cadre-opt.sel{border-color:#23408F;background:rgba(35,64,143,.06);}
.cadre-opt input{margin-right:7px;}
.det-sec{border:1px solid #e8edf5;border-radius:12px;overflow:hidden;margin-bottom:10px;}
.det-sec-head{background:linear-gradient(135deg,#23408F,#1b3576);color:#fff;padding:9px 15px;font-weight:700;font-size:.83rem;display:flex;align-items:center;gap:7px;}
.det-sec-body{padding:12px 15px;}
.det-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px 16px;}
.det-item .dl{font-size:.67rem;text-transform:uppercase;letter-spacing:.04em;color:#7b8aa0;font-weight:700;margin-bottom:1px;}
.det-item .dv{font-size:.87rem;color:#2C3E50;font-weight:600;border-bottom:1px solid #f1f4f9;padding-bottom:3px;}
.eq-row{display:flex;align-items:center;gap:8px;padding:6px 10px;border-radius:8px;border:1px solid #eef1f6;margin-bottom:4px;font-size:.84rem;}
.eq-row.ra{border-color:#D32F2F;background:#fff8f8;}
.dot-ra{width:8px;height:8px;border-radius:50%;background:#D32F2F;flex:0 0 auto;}
.dot-in{width:8px;height:8px;border-radius:50%;background:#23408F;flex:0 0 auto;}
.reg-chip{display:inline-block;background:#e8f0fe;color:#23408F;font-size:.72rem;font-weight:700;padding:.15rem .5rem;border-radius:20px;margin:.1rem;}
@media(max-width:768px){.chart-3{grid-template-columns:1fr;}}

</style>

<div class="page-head">
  <div>
    <h1><i class="bi bi-clipboard-check me-2" style="color:var(--anac-primary)"></i>Audits et inspections</h1>
    <div class="sub">
      <?php if ($isOper): ?>
        Consultez les actes de supervision vous concernant (lettre de notification recue).
      <?php else: ?>
        Planification, suivi et statut des activites de surveillance.
      <?php endif; ?>
    </div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if ($canWrite): ?>
    <button class="btn btn-outline-success" id="btnExcel"><i class="bi bi-file-earmark-excel me-1"></i>Excel</button>
    <button class="btn btn-anac" id="btnNew"><i class="bi bi-plus-lg me-1"></i>Nouveau declenchement</button>
    <?php else: ?>
    <button class="btn btn-outline-success" id="btnExcel"><i class="bi bi-file-earmark-excel me-1"></i>Excel</button>
    <?php endif; ?>
  </div>
</div>

<?php if ($isOper): ?>
<div class="alert mb-3" style="background:#e8f0fe;border:1px solid #c5d4f5;border-left:4px solid #23408F;border-radius:10px;padding:12px 16px">
  <i class="bi bi-info-circle-fill me-2" style="color:#23408F"></i>
  <strong style="color:#23408F">Espace operateur.</strong>
  Vous visualisez ici uniquement les audits vous concernant pour lesquels une <strong>lettre de notification a ete jointe</strong>.
  Cliquez sur <i class="bi bi-eye"></i> <strong>Voir</strong> pour consulter le detail d'un audit.
</div>
<?php endif; ?>

<!-- Panneau stats depliable -->
<div class="stats-panel mb-3">
  <div class="stats-toggle" id="statsToggle">
    <h6><i class="bi bi-bar-chart-line me-2" style="color:#23408F"></i>Statistiques et graphiques</h6>
    <div class="d-flex align-items-center gap-2">
      <span class="small text-muted" id="statsResume" style="font-size:.78rem"></span>
      <i class="bi bi-chevron-down" id="statsChevron" style="color:#7b8aa0;transition:transform .25s"></i>
    </div>
  </div>
  <div class="stats-body collapse" id="statsBody">
    <!-- KPI avec pourcentages -->
    <div class="kpi-row mb-3">
      <div class="kpi-card" title="Total des audits affiches">
        <div class="kpi-ic ic-total"><i class="bi bi-clipboard-data-fill"></i></div>
        <div><div class="kpi-num" id="kTotal">-</div><div class="kpi-lbl">Total</div></div>
      </div>
      <div class="kpi-card" title="Audits planifies | % du total">
        <div class="kpi-ic ic-plan"><i class="bi bi-calendar-check-fill"></i></div>
        <div><div class="kpi-num" id="kPlan" style="color:#23408F">-</div><div class="kpi-lbl">Planifies</div><div class="kpi-pct" id="pPlan" style="font-size:.7rem;color:#23408F;font-weight:700"></div></div>
      </div>
      <div class="kpi-card" title="Audits reportes | % du total">
        <div class="kpi-ic ic-rep"><i class="bi bi-arrow-clockwise"></i></div>
        <div><div class="kpi-num" id="kRep" style="color:#b58a00">-</div><div class="kpi-lbl">Reportes</div><div class="kpi-pct" id="pRep" style="font-size:.7rem;color:#b58a00;font-weight:700"></div></div>
      </div>
      <div class="kpi-card" title="Audits effectues | % du total">
        <div class="kpi-ic ic-eff"><i class="bi bi-check-circle-fill"></i></div>
        <div><div class="kpi-num" id="kEff" style="color:#1E9C4B">-</div><div class="kpi-lbl">Effectues</div><div class="kpi-pct" id="pEff" style="font-size:.7rem;color:#1E9C4B;font-weight:700"></div></div>
      </div>
      <div class="kpi-card" title="Audits suspendus | % du total">
        <div class="kpi-ic ic-sus"><i class="bi bi-pause-circle-fill"></i></div>
        <div><div class="kpi-num" id="kSus" style="color:#D32F2F">-</div><div class="kpi-lbl">Suspendus</div><div class="kpi-pct" id="pSus" style="font-size:.7rem;color:#D32F2F;font-weight:700"></div></div>
      </div>
      <div class="kpi-card" title="Audits annules | % du total">
        <div class="kpi-ic" style="background:rgba(56,61,65,.1);color:#383d41"><i class="bi bi-x-circle-fill"></i></div>
        <div><div class="kpi-num" id="kAnn" style="color:#383d41">-</div><div class="kpi-lbl">Annules</div><div class="kpi-pct" id="pAnn" style="font-size:.7rem;color:#383d41;font-weight:700"></div></div>
      </div>
      <div class="kpi-card" title="Audits inopines | % du total">
        <div class="kpi-ic" style="background:rgba(35,64,143,.08);color:#23408F"><i class="bi bi-lightning-fill"></i></div>
        <div><div class="kpi-num" id="kInop" style="color:#23408F">-</div><div class="kpi-lbl">Inopines</div><div class="kpi-pct" id="pInop" style="font-size:.7rem;color:#23408F;font-weight:700"></div></div>
      </div>
      <div class="kpi-card" title="Taux d'execution = Effectues / Total * 100">
        <div class="kpi-ic ic-exec"><i class="bi bi-speedometer2"></i></div>
        <div><div class="kpi-taux" id="kTaux" style="color:#1E9C4B">- %</div><div class="kpi-lbl">Taux execution</div></div>
      </div>
    </div>
    <!-- 3 graphiques sur la meme ligne -->
    <div class="chart-3">
      <div class="chart-mini">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;flex-wrap:wrap;gap:4px">
          <h6 style="margin:0;font-size:.8rem;font-weight:700;color:#2C3E50"><i class="bi bi-bar-chart me-1" style="color:#23408F"></i>Par annee</h6>
          <select id="fGraphAnnee" class="form-select form-select-sm" style="width:auto;font-size:.74rem;max-width:100px">
            <option value="">Toutes</option>
          </select>
        </div>
        <canvas id="chartAnnee" height="120"></canvas>
      </div>
      <div class="chart-mini">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;flex-wrap:wrap;gap:4px">
          <h6 style="margin:0;font-size:.8rem;font-weight:700;color:#2C3E50"><i class="bi bi-calendar3 me-1" style="color:#1E9C4B"></i>Par mois</h6>
          <select id="fGraphMois" class="form-select form-select-sm" style="width:auto;font-size:.74rem;max-width:100px">
            <option value="">Tous</option>
            <option value="1">Janv</option><option value="2">Fevr</option><option value="3">Mars</option>
            <option value="4">Avr</option><option value="5">Mai</option><option value="6">Juin</option>
            <option value="7">Juil</option><option value="8">Aout</option><option value="9">Sept</option>
            <option value="10">Oct</option><option value="11">Nov</option><option value="12">Dec</option>
          </select>
        </div>
        <canvas id="chartMois" height="120"></canvas>
      </div>
      <div class="chart-mini">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;flex-wrap:wrap;gap:4px">
          <h6 style="margin:0;font-size:.8rem;font-weight:700;color:#2C3E50"><i class="bi bi-pie-chart me-1" style="color:#b58a00"></i>Repartition</h6>
          <select id="fGraphStatut" class="form-select form-select-sm" style="width:auto;font-size:.74rem;max-width:100px">
            <option value="">Tous</option>
            <option value="1">Planifies</option>
            <option value="2">Reportes</option>
            <option value="3">Effectues</option>
            <option value="4">Suspendus</option>
            <option value="ferme">Fermes</option>
          </select>
        </div>
        <canvas id="chartPie" height="120"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Filtres dynamiques (sans bouton Filtrer - Select2 instantane) -->
<div class="filter-bar mb-2">
  <div class="row g-2 align-items-end">
    <div class="col-6 col-md">
      <label class="form-label small fw-bold mb-1" style="color:#5b6b85;font-size:.72rem;text-transform:uppercase;letter-spacing:.04em">Nature supervision</label>
      <select id="fType" style="width:100%"><option value="">Toutes</option></select>
    </div>
    <div class="col-6 col-md">
      <label class="form-label small fw-bold mb-1" style="color:#5b6b85;font-size:.72rem;text-transform:uppercase;letter-spacing:.04em">Statut</label>
      <select id="fStatut" style="width:100%"><option value="">Tous</option></select>
    </div>
    <div class="col-6 col-md">
      <label class="form-label small fw-bold mb-1" style="color:#5b6b85;font-size:.72rem;text-transform:uppercase;letter-spacing:.04em">Operateur</label>
      <select id="fOrga" style="width:100%"><option value="">Tous</option></select>
    </div>
    <div class="col-6 col-md">
      <label class="form-label small fw-bold mb-1" style="color:#5b6b85;font-size:.72rem;text-transform:uppercase;letter-spacing:.04em">Site d'inspection</label>
      <select id="fSite" style="width:100%"><option value="">Tous</option></select>
    </div>
    <div class="col-6 col-md">
      <label class="form-label small fw-bold mb-1" style="color:#5b6b85;font-size:.72rem;text-transform:uppercase;letter-spacing:.04em">Inspecteur</label>
      <select id="fInsp" style="width:100%"><option value="">Tous</option></select>
    </div>
    <div class="col-auto">
      <label class="form-label small mb-1 d-block" style="visibility:hidden">-</label>
      <button class="btn btn-sm btn-outline-secondary" id="btnReset" title="Reinitialiser tous les filtres"><i class="bi bi-x-lg me-1"></i>Reset</button>
    </div>
  </div>
</div>

<!-- Tableau -->
<div class="tbl-wrap">
  <table class="tbl">
    <thead>
      <tr>
        <th>N Audit</th><th>Nature</th><th>Act. operateur</th><th>Operateur</th>
        <th>Site</th><th>Domaine(s)</th><th>Responsable</th><th>Date prev.</th>
        <th>Statut</th><th>Realisee le</th><th>Inspecteurs</th>
        <th style="text-align:right">Actions</th>
      </tr>
    </thead>
    <tbody id="tbody"><tr><td colspan="12" class="empty"><span class="spinner-border spinner-border-sm me-2"></span>Chargement...</td></tr></tbody>
  </table>
</div>
<div class="d-flex justify-content-between align-items-center mt-2 px-1">
  <span class="small text-muted" id="pgInfo"></span>
  <div class="btn-group btn-group-sm" id="pgBtns"></div>
</div>

<!-- MODALE : Nature -->
<div class="modal fade" id="natModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0">
      <div class="modal-header border-0" style="background:linear-gradient(135deg,#23408F,#1b3576);border-radius:8px 8px 0 0">
        <h5 class="modal-title text-white"><i class="bi bi-plus-circle-fill me-2" style="color:#F3C300"></i>Programmer un acte de supervision</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="background:linear-gradient(135deg,#23408F 60%,#1b3576);border-radius:0 0 8px 8px;padding:20px 22px">
        <p style="color:rgba(255,255,255,.8);font-size:.87rem;margin-bottom:14px">Choisissez la nature de la supervision pour demarrer la planification.</p>
        <div class="d-flex flex-wrap gap-2 justify-content-center">
          <div class="nat-tile" data-type="audit"><i class="bi bi-clipboard-check"></i><div class="nt">Audit</div></div>
          <div class="nat-tile" data-type="inspection_programmee"><i class="bi bi-calendar-check"></i><div class="nt">Inspection prog.</div></div>
          <div class="nat-tile" data-type="inspection_non_programmee"><i class="bi bi-calendar-x"></i><div class="nt">Insp. non prog.</div></div>
          <div class="nat-tile" data-type="demonstration"><i class="bi bi-easel"></i><div class="nt">Demonstration</div></div>
          <div class="nat-tile" data-type="test"><i class="bi bi-bullseye"></i><div class="nt">Test</div></div>
          <div class="nat-tile" data-type="investigation"><i class="bi bi-search"></i><div class="nt">Investigation</div></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- MODALE : Cadre -->
<div class="modal fade" id="cadreModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-question-circle me-2" style="color:#23408F"></i>Selectionnez le cadre</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3">Nature : <b id="natLabel" style="color:#23408F"></b>. Choisissez le cadre de supervision.</p>
        <div id="cadreList">
          <label class="cadre-opt"><input type="radio" name="cadre" value="certification"> Certification</label>
          <label class="cadre-opt"><input type="radio" name="cadre" value="homologation"> Homologation</label>
          <label class="cadre-opt"><input type="radio" name="cadre" value="reconnaissance"> Reconnaissance</label>
          <label class="cadre-opt"><input type="radio" name="cadre" value="renouvellement"> Renouvellement</label>
          <label class="cadre-opt"><input type="radio" name="cadre" value="surveillance_continue"> Surveillance continue</label>
          <label class="cadre-opt"><input type="radio" name="cadre" value="traitement_evenement"> Traitement d'un evenement</label>
          <label class="cadre-opt"><input type="radio" name="cadre" value="fermeture_provisoire"> Fermeture provisoire</label>
          <label class="cadre-opt"><input type="radio" name="cadre" value="fermeture_definitive"> Fermeture definitive</label>
          <label class="cadre-opt"><input type="radio" name="cadre" value="delivrance_autorisation"> Delivrance d'une autorisation</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-anac" id="cadreContinue" disabled><i class="bi bi-arrow-right-circle me-1"></i>Continuer</button>
      </div>
    </div>
  </div>
</div>

<!-- MODALE : Modification audit -->
<div class="modal fade" id="auditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <form class="modal-content" id="auditForm" autocomplete="off">
      <div class="modal-header"><h5 class="modal-title" id="auditModalTitle">Modifier l'audit</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" id="a_id">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Numero</label><input type="text" class="form-control" id="a_num"><div id="a_dup" style="display:none;color:#D32F2F;font-size:.8rem">Numero deja utilise.</div></div>
          <div class="col-md-6"><label class="form-label">Statut</label><select class="form-select" id="a_statut"></select></div>
          <div class="col-md-6"><label class="form-label">Nature</label><select class="form-select" id="a_type"></select></div>
          <div class="col-md-6"><label class="form-label">Cadre</label><select class="form-select" id="a_cadre"></select></div>
          <div class="col-md-6"><label class="form-label">Operateur</label><select id="a_orga" style="width:100%"></select></div>
          <div class="col-md-6"><label class="form-label">Site</label><input type="text" class="form-control" id="a_site"></div>
          <div class="col-md-6"><label class="form-label">Responsable d'audit</label><select id="a_resp" style="width:100%"></select></div>
          <div class="col-md-6"><label class="form-label">Chef inspecteur</label><select id="a_chef" style="width:100%"></select></div>
          <div class="col-md-6"><label class="form-label">Date previsionnelle</label><input type="date" class="form-control" id="a_dprev"></div>
          <div class="col-md-6"><label class="form-label">Date realisation</label><input type="date" class="form-control" id="a_dreal"></div>
          <div class="col-md-6"><label class="form-label">Delai execution (j)</label><input type="number" class="form-control" id="a_delai" min="0"></div>
          <div class="col-md-6"><label class="form-label">Date rapport</label><input type="date" class="form-control" id="a_drap"></div>
          <div class="col-md-6"><label class="form-label">Date notification</label><input type="date" class="form-control" id="a_dnotif"></div>
          <div class="col-12"><div class="form-check"><input type="checkbox" class="form-check-input" id="a_ferme"><label class="form-check-label small">Marquer comme ferme</label></div><div id="wrapFerme" style="display:none;margin-top:6px"><input type="date" class="form-control" id="a_dferm"></div></div>
          <div class="col-12">
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;font-weight:700;color:#23408F;border-bottom:1px solid #eef1f6;padding-bottom:4px;margin-bottom:8px">Equipe d'audit</div>
            <div id="eqRows"></div>
            <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddEq"><i class="bi bi-person-plus me-1"></i>Ajouter</button>
          </div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-anac" id="auditSubmit"><i class="bi bi-check-lg me-1"></i>Enregistrer</button></div>
    </form>
  </div>
</div>

<!-- MODALE : Detail complet -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" style="max-width:90vw">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h5 class="modal-title"><i class="bi bi-eye me-2" style="color:#23408F"></i>Details - <span id="viewNum" style="color:#23408F;font-weight:700"></span></h5>
        <div class="ms-auto d-flex gap-2 me-2">
          <button class="btn btn-sm btn-outline-success" id="viewExcelBtn"><i class="bi bi-file-earmark-excel me-1"></i>Excel</button>
          <button class="btn btn-sm btn-anac" id="viewPrintBtn"><i class="bi bi-printer me-1"></i>Imprimer PDF</button>
        </div>
        <button type="button" class="btn-close ms-1" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-3" id="viewBody"><div class="text-center py-4"><span class="spinner-border text-primary"></span></div></div>
    </div>
  </div>
</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>
<script>
const CSRF       = '<?php echo Security::escape($csrf); ?>';
const API_AUDITS = AGAI_BASE + '/api/audits';
const ROLE       = '<?php echo Security::escape($role); ?>';
const CAN_WRITE  = <?php echo $canWrite ? 'true' : 'false'; ?>;
const CAN_DELETE = <?php echo $canDelete ? 'true' : 'false'; ?>;
const IS_OPER    = <?php echo $isOper ? 'true' : 'false'; ?>;
const IMG_BASE   = AGAI_BASE + '/public/images/';

const TYPE_LABELS={audit:'Audit',inspection_programmee:'Inspection programmee',inspection_non_programmee:'Inspection non programmee',demonstration:'Demonstration',test:'Test',investigation:'Investigation'};
const CADRE_LABELS={certification:'Certification',homologation:'Homologation',reconnaissance:'Reconnaissance',renouvellement:'Renouvellement',surveillance_continue:'Surveillance continue',traitement_evenement:"Traitement d'un evenement",fermeture_provisoire:'Fermeture provisoire',fermeture_definitive:'Fermeture definitive',delivrance_autorisation:"Delivrance d'une autorisation"};
const STATUT={1:{t:'Planifiee',c:'sb1',col:'#23408F'},2:{t:'Reportee',c:'sb2',col:'#b58a00'},3:{t:'Effectuee',c:'sb3',col:'#1E9C4B'},4:{t:'Suspendue',c:'sb4',col:'#D32F2F'},5:{t:'A surveiller',c:'sb5',col:'#5a189a'},6:{t:'Annulee',c:'sb0',col:'#383d41'},7:{t:'Inopinee',c:'sb1',col:'#23408F'}};
const MOIS_FR=['','Jan','Fev','Mar','Avr','Mai','Jun','Jul','Aou','Sep','Oct','Nov','Dec'];

function apiPost(data){data=Object.assign({csrf_token:CSRF},data);return $.post(API_AUDITS,data,null,'json');}
function fmtDate(s){if(!s||String(s)==='0000-00-00')return '-';const p=String(s).substring(0,10).split('-');return p.length===3?(p[2]+'/'+p[1]+'/'+p[0]):s;}
function statBadge(a){if(Number(a.est_ferme)===1)return '<span class="sb sb0">Ferme</span>';const s=STATUT[a.statut]||{t:a.statut||'-',c:'sb1'};return '<span class="sb '+s.c+'">'+esc(s.t)+'</span>';}

/* ======= TOGGLE STATS ======= */
let statsOpen = false;
$('#statsToggle').on('click', function(){
  statsOpen = !statsOpen;
  $('#statsBody').toggleClass('show', statsOpen);
  $('#statsChevron').css('transform', statsOpen ? 'rotate(180deg)' : 'rotate(0deg)');
  // Redraw graphiques avec les donnees filtrees courantes
  if(statsOpen && STATS_DATA){
    setTimeout(function(){
      const list = STATS_DATA._filteredList||ALL;
      buildGraphAnnee(calcParAnnee(list));
      buildGraphMois(calcParMois(list));
      buildGraphPie(calcStats(list));
    }, 50);
  }
});

/* ======= STATS CALCULEES SUR LES DONNES FILTREES ======= */
let chartAnnee=null, chartMois=null, chartPie=null, STATS_DATA=null;

function calcStats(list){
  const s={total:list.length,planifies:0,reportes:0,effectues:0,suspendus:0,
           annules:0,inopines:0,fermes:0};
  list.forEach(function(a){
    if(Number(a.est_ferme)===1){s.fermes++;return;}
    const st=Number(a.statut);
    if(st===1)s.planifies++;
    else if(st===2)s.reportes++;
    else if(st===3)s.effectues++;
    else if(st===4)s.suspendus++;
    else if(st===6)s.annules++;
    else if(st===7)s.inopines++;
  });
  s.taux_execution = s.total ? Math.round(s.effectues/s.total*100) : 0;
  return s;
}

function calcParAnnee(list){
  const map={};
  list.forEach(function(a){
    const yr = a.date_previsionnelle ? String(a.date_previsionnelle).substring(0,4) : null;
    if(!yr||yr==='0000') return;
    if(!map[yr]) map[yr]={annee:yr,planifies:0,reportes:0,effectues:0,suspendus:0,annules:0,inopines:0,total:0};
    map[yr].total++;
    const st=Number(a.statut);
    if(st===1)map[yr].planifies++;
    else if(st===2)map[yr].reportes++;
    else if(st===3)map[yr].effectues++;
    else if(st===4)map[yr].suspendus++;
    else if(st===6)map[yr].annules++;
    else if(st===7)map[yr].inopines++;
  });
  return Object.values(map).sort(function(a,b){return a.annee-b.annee;});
}

function calcParMois(list){
  const now=new Date().getFullYear();
  const map={};
  list.forEach(function(a){
    const d=a.date_previsionnelle;
    if(!d||String(d).substring(0,4)!==String(now)) return;
    const mo=Number(String(d).substring(5,7));
    if(!mo) return;
    if(!map[mo]) map[mo]={mois:mo,planifies:0,reportes:0,effectues:0,annules:0,inopines:0,total:0};
    map[mo].total++;
    const st=Number(a.statut);
    if(st===1)map[mo].planifies++;
    else if(st===2)map[mo].reportes++;
    else if(st===3)map[mo].effectues++;
    else if(st===6)map[mo].annules++;
    else if(st===7)map[mo].inopines++;
  });
  return Object.values(map).sort(function(a,b){return a.mois-b.mois;});
}

function updateStatsDisplay(data){
  const list=data._filteredList||ALL;
  const s=calcStats(list);
  const tot=s.total||1; // eviter division par zero
  function pct(v){ return tot>0?Math.round(v/tot*100):0; }
  // KPI + pourcentages
  $('#kTotal').text(s.total);
  $('#kPlan').text(s.planifies);  $('#pPlan').text(s.planifies>0?pct(s.planifies)+'%':'');
  $('#kRep').text(s.reportes);    $('#pRep').text(s.reportes>0?pct(s.reportes)+'%':'');
  $('#kEff').text(s.effectues);   $('#pEff').text(s.effectues>0?pct(s.effectues)+'%':'');
  $('#kSus').text(s.suspendus);   $('#pSus').text(s.suspendus>0?pct(s.suspendus)+'%':'');
  $('#kAnn').text(s.annules||0);  $('#pAnn').text((s.annules||0)>0?pct(s.annules||0)+'%':'');
  $('#kInop').text(s.inopines||0);$('#pInop').text((s.inopines||0)>0?pct(s.inopines||0)+'%':'');
  $('#kTaux').text(s.taux_execution+'%');
  // Resume en-tete du panneau stats
  $('#statsResume').text(s.total+' actes | '+s.effectues+' effectues ('+s.taux_execution+'%)');
  // Graphiques
  const parAnnee=calcParAnnee(list);
  const parMois=calcParMois(list);
  buildFilterGraphAnnee(parAnnee);
  buildGraphAnnee(parAnnee);
  buildGraphMois(parMois);
  buildGraphPie(s);
}

function buildFilterGraphAnnee(parAnnee){
  const current=$('#fGraphAnnee').val()||'';
  let h='<option value="">Toutes</option>';
  parAnnee.slice().reverse().forEach(function(r){h+='<option value="'+esc(r.annee)+'"'+(String(r.annee)===current?' selected':'')+'>'+esc(r.annee)+'</option>';});
  $('#fGraphAnnee').html(h);
}

// Suppression de l'ancien handler isole fGraphAnnee (maintenant gere par applyFilters via change.graphfilter)

const CHART_OPTS={responsive:true,maintainAspectRatio:true,
  plugins:{legend:{position:'bottom',labels:{font:{size:9},boxWidth:9,padding:4}}},
  scales:{x:{grid:{display:false},ticks:{font:{size:9}}},y:{beginAtZero:true,ticks:{stepSize:1,font:{size:9}}}}};

// Helper : dessiner les valeurs sur les barres empilees apres animation
function drawBarLabels(chart){
  const ctx=chart.ctx;
  chart.data.datasets.forEach(function(dataset,dsIdx){
    const meta=chart.getDatasetMeta(dsIdx);
    if(meta.hidden) return;
    meta.data.forEach(function(bar,i){
      const val=dataset.data[i]; if(!val) return;
      const barH=bar.base-bar.y;
      if(barH<12) return; // Trop petite
      ctx.save();
      ctx.fillStyle='rgba(255,255,255,0.92)';
      ctx.font='bold 8px Candara,Arial,sans-serif';
      ctx.textAlign='center'; ctx.textBaseline='middle';
      ctx.fillText(val, bar.x, bar.y+barH*0.5);
      ctx.restore();
    });
  });
}

// Helper : dessiner nb + % sur les parts du camembert/donut
function drawPieLabels(chart){
  const {ctx,data}=chart;
  const total=data.datasets[0].data.reduce(function(a,b){return a+b;},0);
  if(!total) return;
  chart.getDatasetMeta(0).data.forEach(function(arc,i){
    const val=data.datasets[0].data[i]; if(!val) return;
    const pct=Math.round(val/total*100);
    const arcAngle=arc.endAngle-arc.startAngle;
    if(arcAngle<0.25) return; // Trop petit pour afficher
    const mid=arc.startAngle+arcAngle/2;
    const r=(arc.outerRadius-(arc.outerRadius-arc.innerRadius)*0.5)*0.82;
    const x=arc.x+Math.cos(mid)*r, y=arc.y+Math.sin(mid)*r;
    ctx.save();
    ctx.fillStyle='#fff'; ctx.font='bold 8px Candara,Arial,sans-serif';
    ctx.textAlign='center'; ctx.textBaseline='middle';
    ctx.fillText(val+' ('+pct+'%)', x, y);
    ctx.restore();
  });
}

function buildGraphAnnee(data){
  const ctx=document.getElementById('chartAnnee').getContext('2d');
  if(chartAnnee){chartAnnee.destroy();}
  if(!data.length){chartAnnee=null;return;}
  chartAnnee=new Chart(ctx,{type:'bar',data:{
    labels:data.map(function(r){return String(r.annee);}),
    datasets:[
      {label:'Plan.',data:data.map(function(r){return Number(r.planifies||0);}),backgroundColor:'rgba(35,64,143,.78)',borderRadius:3},
      {label:'Eff.',data:data.map(function(r){return Number(r.effectues||0);}),backgroundColor:'rgba(30,156,75,.82)',borderRadius:3},
      {label:'Rep.',data:data.map(function(r){return Number(r.reportes||0);}),backgroundColor:'rgba(243,195,0,.78)',borderRadius:3},
      {label:'Sus.',data:data.map(function(r){return Number(r.suspendus||0);}),backgroundColor:'rgba(211,47,47,.78)',borderRadius:3},
      {label:'Ann.',data:data.map(function(r){return Number(r.annules||0);}),backgroundColor:'rgba(56,61,65,.72)',borderRadius:3},
      {label:'Inop.',data:data.map(function(r){return Number(r.inopines||0);}),backgroundColor:'rgba(35,64,143,.42)',borderRadius:3},
    ]
  },options:{...CHART_OPTS,
    animation:{onComplete:function(){drawBarLabels(this);}},
    scales:{x:{grid:{display:false},ticks:{font:{size:9}},stacked:false},y:{beginAtZero:true,ticks:{stepSize:1,font:{size:9}}}}
  }});
}

function buildGraphMois(data){
  const ctx=document.getElementById('chartMois').getContext('2d');
  if(chartMois){chartMois.destroy();}
  const MOIS=['','Jan','Fev','Mar','Avr','Mai','Jun','Jul','Aou','Sep','Oct','Nov','Dec'];
  if(!data.length){chartMois=null;return;}
  chartMois=new Chart(ctx,{type:'bar',data:{
    labels:data.map(function(r){return MOIS[r.mois]||r.mois;}),
    datasets:[
      {label:'Plan.',data:data.map(function(r){return r.planifies||0;}),backgroundColor:'rgba(35,64,143,.78)',borderRadius:3},
      {label:'Eff.',data:data.map(function(r){return r.effectues||0;}),backgroundColor:'rgba(30,156,75,.82)',borderRadius:3},
      {label:'Rep.',data:data.map(function(r){return r.reportes||0;}),backgroundColor:'rgba(243,195,0,.78)',borderRadius:3},
      {label:'Ann.',data:data.map(function(r){return r.annules||0;}),backgroundColor:'rgba(56,61,65,.72)',borderRadius:3},
    ]
  },options:{...CHART_OPTS,
    animation:{onComplete:function(){drawBarLabels(this);}},
    scales:{x:{grid:{display:false},ticks:{font:{size:9}}},y:{beginAtZero:true,ticks:{stepSize:1,font:{size:9}}}}
  }});
}

function buildGraphPie(s){
  const ctx=document.getElementById('chartPie').getContext('2d');
  if(chartPie){chartPie.destroy();}
  const total=s.total||0;
  const vals=[s.planifies||0,s.effectues||0,s.reportes||0,s.suspendus||0,s.annules||0,s.inopines||0];
  if(!vals.some(function(v){return v>0;})){chartPie=null;return;}
  // Labels avec pourcentage
  const pctLabels=['Planifie','Effectue','Reporte','Suspendu','Annule','Inopine'].map(function(l,i){
    const pct=total>0?Math.round(vals[i]/total*100):0;
    return l+' '+vals[i]+(pct>0?' ('+pct+'%)':'');
  });
  chartPie=new Chart(ctx,{type:'doughnut',data:{
    labels:pctLabels,
    datasets:[{data:vals,
      backgroundColor:['rgba(35,64,143,.85)','rgba(30,156,75,.85)','rgba(243,195,0,.85)','rgba(211,47,47,.85)','rgba(56,61,65,.75)','rgba(35,64,143,.4)'],
      borderWidth:2,borderColor:'#fff'}]
  },options:{responsive:true,maintainAspectRatio:true,cutout:'55%',
    animation:{onComplete:function(){drawPieLabels(this);}},
    plugins:{legend:{position:'bottom',labels:{font:{size:8},boxWidth:9,padding:3}},
      tooltip:{callbacks:{label:function(c){const t=c.chart.data.datasets[0].data.reduce(function(a,b){return a+b;},0);const pct=t>0?Math.round(c.parsed/t*100):0;return ' '+c.parsed+' ('+pct+'%)';}}}}
  }});
}

function loadStats(){ /* Stats recalculees depuis les donnees filtrees via updateStatsDisplay */ }

/* ======= TABLEAU + FILTRES DYNAMIQUES ======= */
let ALL=[], PAGE=1, PER=20;
let F_TYPE='',F_STATUT='',F_ORGA='',F_SITE='',F_INSP='';
// Filtres graphiques : impactent le tableau, les KPI et les 3 graphiques
let F_GRAPH_ANNEE='',F_GRAPH_MOIS='',F_GRAPH_STATUT='';
let LISTS_LOADED=false, EXPLOITANTS=[], INSPECTEURS=[], DOMAINES=[], SITES=[];

function loadLists(){
  return apiPost({action:'lists'}).done(function(res){
    if(!res.success) return;
    EXPLOITANTS=res.exploitants||[];INSPECTEURS=res.inspecteurs||[];
    DOMAINES=res.domaines||[];SITES=res.sites||[];
    LISTS_LOADED=true;
    if(ALL.length) buildFilters(); // Si table deja chargee, construire les filtres
  });
}

function buildFilters(){
  let h='<option value="">Tous types</option>';
  Object.entries(TYPE_LABELS).forEach(function([k,v]){h+='<option value="'+k+'">'+esc(v)+'</option>';});
  $('#fType').html(h);
  h='<option value="">Tous statuts</option>';
  Object.entries(STATUT).forEach(function([k,v]){h+='<option value="'+k+'">'+esc(v.t)+'</option>';});
  $('#fStatut').html(h);
  // Operateurs : uniquement ceux presents dans les audits visibles
  const orgaIds=new Set(ALL.map(function(a){return String(a.idorga);}));
  h='<option value="">Tous operateurs</option>';
  EXPLOITANTS.filter(function(o){return orgaIds.has(String(o.idorga));})
    .forEach(function(o){h+='<option value="'+esc(o.idorga)+'">'+esc(o.nomorga)+'</option>';});
  $('#fOrga').html(h);
  // Sites
  const siteVals=new Set(ALL.map(function(a){return String(a.idsite||'');}));
  h='<option value="">Tous sites</option>';
  SITES.filter(function(s){return siteVals.has(String(s.idsite));})
    .forEach(function(s){h+='<option value="'+esc(s.idsite)+'">'+esc(s.indicateur_oaci)+' - '+esc(s.nomsite)+'</option>';});
  $('#fSite').html(h);
  // Inspecteurs : uniquement ceux dans les equipes des audits visibles
  const inspIds=new Set();
  ALL.forEach(function(a){(a.inspecteurs||[]).forEach(function(i){inspIds.add(String(i.idinspecteur));});});
  h='<option value="">Tous inspecteurs</option>';
  INSPECTEURS.filter(function(i){return inspIds.has(String(i.idinspecteur));})
    .forEach(function(i){h+='<option value="'+esc(i.idinspecteur)+'">'+esc(i.nom)+'</option>';});
  $('#fInsp').html(h);
  // Initialiser Select2 sur tous les filtres
  ['#fType','#fStatut','#fOrga','#fSite','#fInsp'].forEach(function(id){
    if($(id).hasClass('select2-hidden-accessible'))$(id).select2('destroy');
    $(id).select2({theme:'bootstrap-5',width:'100%',allowClear:true,placeholder:'Tous'});
    // Filtre instantane au changement
    $(id).off('change.filter').on('change.filter',function(){ applyFilters(); });
  });
}

function applyFilters(){
  F_TYPE=$('#fType').val()||'';F_STATUT=$('#fStatut').val()||'';
  F_ORGA=$('#fOrga').val()||'';F_SITE=$('#fSite').val()||'';
  F_INSP=$('#fInsp').val()||'';
  F_GRAPH_ANNEE=$('#fGraphAnnee').val()||'';
  F_GRAPH_MOIS=$('#fGraphMois').val()||'';
  F_GRAPH_STATUT=$('#fGraphStatut').val()||'';
  PAGE=1;
  const list=filtered();
  if(!STATS_DATA) STATS_DATA={};
  STATS_DATA._filteredList=list;
  updateStatsDisplay(STATS_DATA);
  renderRows(list);
}

// Ecouter les filtres graphiques aussi
$(document).off('change.graphfilter').on('change.graphfilter','#fGraphAnnee,#fGraphMois,#fGraphStatut',function(){
  applyFilters();
});

function filtered(){
  return ALL.filter(function(a){
    if(F_TYPE   && a.type_activite!==F_TYPE)    return false;
    if(F_STATUT && String(a.statut)!==F_STATUT) return false;
    if(F_ORGA   && String(a.idorga)!==F_ORGA)   return false;
    if(F_SITE   && String(a.idsite||'')!==F_SITE) return false;
    if(F_INSP){const eq=a.inspecteurs||[];if(!eq.some(function(i){return String(i.idinspecteur)===F_INSP;})) return false;}
    // Filtres graphiques : impactent le tableau et les visuels
    if(F_GRAPH_ANNEE){
      const y=String(a.date_previsionnelle||'').substring(0,4);
      if(y!==F_GRAPH_ANNEE) return false;
    }
    if(F_GRAPH_MOIS){
      const mo=String(a.date_previsionnelle||'').substring(5,7).replace(/^0/,'');
      if(mo!==F_GRAPH_MOIS) return false;
    }
    if(F_GRAPH_STATUT){
      if(F_GRAPH_STATUT==='ferme'){if(Number(a.est_ferme)!==1)return false;}
      else{if(String(a.statut)!==F_GRAPH_STATUT||Number(a.est_ferme)===1)return false;}
    }
    return true;
  });
}

function render(){
  const list=filtered();
  if(STATS_DATA){STATS_DATA._filteredList=list;if(statsOpen)updateStatsDisplay(STATS_DATA);}
  renderRows(list);
}

function renderRows(list){
  const total=list.length;
  const pages=Math.ceil(total/PER)||1; if(PAGE>pages)PAGE=1;
  const chunk=list.slice((PAGE-1)*PER,PAGE*PER);
  const tb=$('#tbody');
  if(!chunk.length){tb.html('<tr><td colspan="12" class="empty"><i class="bi bi-inbox me-2"></i>Aucun audit.</td></tr>');$('#pgInfo').text('');$('#pgBtns').html('');return;}
  tb.html(chunk.map(function(a){
    const doms=[...new Set((a.inspecteurs||[]).map(function(i){return i.nomdomaine||'';}).filter(Boolean))].join(', ')||'-';
    const insps=(function(){
      const seen={};
      return (a.inspecteurs||[]).filter(function(i){if(seen[i.idinspecteur])return false;seen[i.idinspecteur]=true;return true;})
        .map(function(i){const ra=Number(i.est_responsable)===1;return (ra?'<b style="color:#D32F2F">':'<span>')+esc(i.nom||'')+(ra?'</b>':'</span>');}).join(', ')||'-';
    }());
    const effectue = Number(a.statut)===3;
    // Operateur : lecture seule, bouton Voir uniquement
    const hasLettre = a.lettre_notification && String(a.lettre_notification).trim();
    const acts='<div style="text-align:right;white-space:nowrap">'
      +'<button class="btn btn-xs btn-outline-primary me-1 act-view" data-id="'+esc(a.idaudit)+'" style="padding:3px 7px" title="Voir le detail"><i class="bi bi-eye"></i></button>'
      +(!IS_OPER&&CAN_WRITE&&!effectue?'<a href="'+AGAI_BASE+'/modifier-audit?id='+esc(a.idaudit)+'" class="btn btn-xs btn-outline-secondary me-1" title="Modifier" style="padding:3px 7px"><i class="bi bi-pencil"></i></a>':'')
      +(!IS_OPER&&CAN_DELETE&&!effectue?'<button class="btn btn-xs btn-outline-danger act-del" data-id="'+esc(a.idaudit)+'" data-num="'+esc(a.num_audit)+'" style="padding:3px 7px"><i class="bi bi-trash"></i></button>':'')
      +(effectue?'<span class="badge ms-1" style="background:#d1e7dd;color:#0a5c36;font-size:.68rem;padding:4px 7px"><i class="bi bi-check-circle me-1"></i>Effectue</span>':'')
      +(IS_OPER&&hasLettre?'<span class="badge ms-1" style="background:#e8f0fe;color:#23408F;font-size:.68rem;padding:4px 7px"><i class="bi bi-bell me-1"></i>Notifie</span>':'')
      +'</div>';
    const stCls=Number(a.est_ferme)===1?'st-ferme':'st-'+Number(a.statut);
    return '<tr class="'+stCls+'"><td><b style="color:#23408F;font-size:.81rem">'+esc(a.num_audit||'')+'</b></td>'
      +'<td><span class="type-b">'+esc(TYPE_LABELS[a.type_activite]||a.type_activite||'')+'</span></td>'
      +'<td style="font-size:.81rem;max-width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'+esc(a.type_activite_operateur||'-')+'</td>'
      +'<td style="font-size:.81rem">'+esc(a.nomorga||'-')+'</td>'
      +'<td style="font-size:.81rem">'+esc(a.site_inspection||'-')+'</td>'
      +'<td style="font-size:.78rem;max-width:110px">'+esc(doms)+'</td>'
      +'<td style="font-size:.82rem;color:#D32F2F;font-weight:600">'+esc(a.responsable||'-')+'</td>'
      +'<td style="font-size:.82rem">'+fmtDate(a.date_previsionnelle)+'</td>'
      +'<td>'+statBadge(a)+'</td>'
      +'<td style="font-size:.82rem">'+fmtDate(a.date_realisation)+'</td>'
      +'<td style="font-size:.78rem;max-width:130px">'+insps+'</td>'
      +'<td>'+acts+'</td></tr>';
  }).join(''));
  $('#pgInfo').text(total+' audit(s) - Page '+PAGE+'/'+pages);
  let btns='<button class="btn btn-outline-secondary" onclick="changePage('+(PAGE-1)+')" '+(PAGE<=1?'disabled':'')+'>Prec.</button>';
  for(let i=Math.max(1,PAGE-2);i<=Math.min(pages,PAGE+2);i++){btns+='<button class="btn btn-'+(i===PAGE?'primary':'outline-secondary')+'" onclick="changePage('+i+')">'+i+'</button>';}
  btns+='<button class="btn btn-outline-secondary" onclick="changePage('+(PAGE+1)+')" '+(PAGE>=pages?'disabled':'')+'>Suiv.</button>';
  $('#pgBtns').html(btns);
}
function changePage(p){if(p<1)return;PAGE=p;const list=filtered();renderRows(list);}

function loadTable(){
  apiPost({action:'list'}).done(function(res){
    if(!res.success){$('#tbody').html('<tr><td colspan="12" class="empty">'+esc(res.message||'Erreur')+'</td></tr>');return;}
    ALL=res.data||[];
    // Toujours initialiser et afficher les stats des le chargement
    STATS_DATA={_filteredList:ALL};
    updateStatsDisplay(STATS_DATA); // Met a jour KPI + resume + graphiques
    // Construire les filtres dynamiques avec les vraies donnees
    if(LISTS_LOADED) buildFilters();
    render();
  }).fail(function(){$('#tbody').html('<tr><td colspan="12" class="empty">Echec du chargement.</td></tr>');});
}

$('#btnReset').on('click',function(){
  ['#fType','#fStatut','#fOrga','#fSite','#fInsp'].forEach(function(id){
    if($(id).hasClass('select2-hidden-accessible'))$(id).val(null).trigger('change');
    else $(id).val('');
  });
  $('#fGraphAnnee,#fGraphMois,#fGraphStatut').val('');
  F_TYPE=F_STATUT=F_ORGA=F_SITE=F_INSP='';
  F_GRAPH_ANNEE=F_GRAPH_MOIS=F_GRAPH_STATUT='';
  PAGE=1;
  if(!STATS_DATA) STATS_DATA={};
  STATS_DATA._filteredList=ALL;
  updateStatsDisplay(STATS_DATA);
  renderRows(ALL);
});


/* ======= MODALES NATURE + CADRE ======= */
let selType='';
const NAT_LABELS={audit:'Audit',inspection_programmee:'Inspection programmee',inspection_non_programmee:'Inspection non programmee',demonstration:'Demonstration',test:'Test',investigation:'Investigation'};
if(CAN_WRITE){
  const natModal=new bootstrap.Modal('#natModal');
  const cadreModal=new bootstrap.Modal('#cadreModal');
  $('#btnNew').on('click',function(){natModal.show();});
  $('.nat-tile').on('click',function(){
    selType=$(this).data('type');
    $('#natLabel').text(NAT_LABELS[selType]||selType);
    $('input[name="cadre"]').prop('checked',false);$('.cadre-opt').removeClass('sel');
    $('#cadreContinue').prop('disabled',true);
    natModal.hide();cadreModal.show();
  });
  $(document).on('change','input[name="cadre"]',function(){$('.cadre-opt').removeClass('sel');$(this).closest('.cadre-opt').addClass('sel');$('#cadreContinue').prop('disabled',false);});
  $('#cadreContinue').on('click',function(){
    const cadre=$('input[name="cadre"]:checked').val();if(!selType||!cadre)return;
    cadreModal.hide();window.location=AGAI_BASE+'/declenchement?type='+encodeURIComponent(selType)+'&cadre='+encodeURIComponent(cadre);
  });
}

/* ======= MODIFICATION ======= */
function inspOpts(sel){return '<option value="">Inspecteur...</option>'+INSPECTEURS.map(function(o){return '<option value="'+esc(o.idinspecteur)+'"'+(String(sel)===String(o.idinspecteur)?' selected':'')+'>'+esc(o.nom)+(o.trigr_inspecteur?' ('+esc(o.trigr_inspecteur)+')':'')+'</option>';}).join('');}
function domOpts(sel){return '<option value="">Domaine...</option>'+DOMAINES.map(function(o){return '<option value="'+esc(o.iddomaine)+'"'+(String(sel)===String(o.iddomaine)?' selected':'')+'>'+esc(o.nomdomaine)+'</option>';}).join('');}

function addEqRow(e){
  e=e||{};
  const html='<div class="row g-2 mb-2 eq-row align-items-center">'
    +'<div class="col-5"><select class="eq-insp" style="width:100%">'+inspOpts(e.idinspecteur)+'</select></div>'
    +'<div class="col-4"><select class="eq-dom" style="width:100%">'+domOpts(e.iddomaine)+'</select></div>'
    +'<div class="col-2"><div class="form-check"><input class="form-check-input eq-resp" type="checkbox"'+(Number(e.est_responsable)===1?' checked':'')+'><label class="form-check-label small">Resp.</label></div></div>'
    +'<div class="col-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger eq-del"><i class="bi bi-x"></i></button></div>'
    +'</div>';
  $('#eqRows').append(html);
  const $r=$('#eqRows .eq-row').last();
  $r.find('.eq-insp,.eq-dom').select2({theme:'bootstrap-5',dropdownParent:$('#auditModal'),width:'100%'});
}
function clearEqRows(){$('#eqRows .eq-insp,.eq-dom').each(function(){if($(this).hasClass('select2-hidden-accessible'))$(this).select2('destroy');});$('#eqRows').empty();}
$('#btnAddEq').on('click',function(){addEqRow();});
$(document).on('click','.eq-del',function(){const $r=$(this).closest('.eq-row');$r.find('.eq-insp,.eq-dom').each(function(){if($(this).hasClass('select2-hidden-accessible'))$(this).select2('destroy');});$r.remove();});
$('#a_ferme').on('change',function(){$('#wrapFerme').toggle(this.checked);});

function buildModalSelects(){
  function sel(id,opts,ph){let h='<option value="">'+ph+'</option>';opts.forEach(function(o){h+='<option value="'+esc(o.v)+'">'+esc(o.t)+'</option>';});$('#'+id).html(h);}
  sel('a_type',Object.keys(TYPE_LABELS).map(function(k){return {v:k,t:TYPE_LABELS[k]};}), 'Choisir...');
  sel('a_cadre',Object.keys(CADRE_LABELS).map(function(k){return {v:k,t:CADRE_LABELS[k]};}), 'Choisir...');
  sel('a_statut',Object.keys(STATUT).map(function(k){return {v:k,t:STATUT[k].t};}), '');
  $('#a_statut').val('1');
  $('#a_orga').html('<option value="">Exploitant...</option>'+EXPLOITANTS.map(function(o){return '<option value="'+esc(o.idorga)+'">'+esc(o.nomorga)+'</option>';}).join(''));
  const insO='<option value="">Inspecteur...</option>'+INSPECTEURS.map(function(o){return '<option value="'+esc(o.idinspecteur)+'">'+esc(o.nom)+'</option>';}).join('');
  $('#a_resp').html(insO);$('#a_chef').html(insO);
  ['a_orga','a_resp','a_chef'].forEach(function(id){if($('#'+id).hasClass('select2-hidden-accessible'))$('#'+id).select2('destroy');$('#'+id).select2({theme:'bootstrap-5',dropdownParent:$('#auditModal'),width:'100%'});});
}

$(document).on('click','.act-edit',function(){
  if(!CAN_WRITE) return;
  const id=$(this).data('id');
  const fill=function(){
    apiPost({action:'get',idaudit:id}).done(function(res){
      if(!res.success){Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'});return;}
      const a=res.data;
      $('#auditModalTitle').text('Modifier - '+esc(a.num_audit));
      buildModalSelects();
      $('#a_id').val(a.idaudit);$('#a_num').val(a.num_audit);
      $('#a_type').val(a.type_activite);$('#a_cadre').val(a.cadre);
      $('#a_statut').val(String(a.statut||1));$('#a_site').val(a.site_inspection||'');
      $('#a_orga').val(String(a.idorga)).trigger('change');
      $('#a_resp').val(String(a.idresponsable_audit)).trigger('change');
      $('#a_chef').val(String(a.idchef_inspecteur||a.idresponsable_audit)).trigger('change');
      const d=function(s){return s?String(s).substring(0,10):'';};
      $('#a_dprev').val(d(a.date_previsionnelle));$('#a_dreal').val(d(a.date_realisation));
      $('#a_drap').val(d(a.date_delivrance_rapport));$('#a_dnotif').val(d(a.date_notification));
      $('#a_delai').val(a.delai_execution||'');
      $('#a_ferme').prop('checked',Number(a.est_ferme)===1);$('#wrapFerme').toggle(Number(a.est_ferme)===1);
      $('#a_dferm').val(d(a.date_fermeture));$('#a_dup').hide();
      clearEqRows();const eq=res.equipe||[];if(eq.length){eq.forEach(addEqRow);}else{addEqRow();}
      new bootstrap.Modal('#auditModal').show();
    });
  };
  if(!LISTS_LOADED){loadLists().always(fill);}else{fill();}
});

$('#auditForm').on('submit',function(e){
  e.preventDefault();
  const id=$('#a_id').val();
  const data={action:id?'update':'create',idaudit:id,num_audit:$('#a_num').val().trim(),
    type_activite:$('#a_type').val(),cadre:$('#a_cadre').val(),idorga:$('#a_orga').val(),
    site_inspection:$('#a_site').val().trim(),idresponsable_audit:$('#a_resp').val(),
    idchef_inspecteur:$('#a_chef').val()||$('#a_resp').val(),statut:$('#a_statut').val()||1,
    date_previsionnelle:$('#a_dprev').val(),date_realisation:$('#a_dreal').val(),
    date_delivrance_rapport:$('#a_drap').val(),date_notification:$('#a_dnotif').val(),
    delai_execution:$('#a_delai').val(),est_ferme:$('#a_ferme').is(':checked')?1:0,date_fermeture:$('#a_dferm').val()};
  const eqI=[],eqD=[],eqR=[];
  $('#eqRows .eq-row').each(function(){const ins=$(this).find('.eq-insp').val(),dom=$(this).find('.eq-dom').val();if(!ins||!dom)return;eqI.push(ins);eqD.push(dom);eqR.push($(this).find('.eq-resp').is(':checked')?1:0);});
  data.eq_inspecteur=eqI;data.eq_domaine=eqD;data.eq_resp=eqR;
  const btn=$('#auditSubmit');const h=btn.html();
  btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
  apiPost(data).done(function(res){
    btn.prop('disabled',false).html(h);
    if(res.success){bootstrap.Modal.getInstance(document.getElementById('auditModal')).hide();Swal.fire({icon:'success',title:'Enregistre',timer:1400,showConfirmButton:false});loadTable();loadStats();}
    else{Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'});}
  }).fail(function(){btn.prop('disabled',false).html(h);Swal.fire({icon:'error',text:'Echec.',confirmButtonColor:'#23408F'});});
});

/* ======= SUPPRESSION ======= */
$(document).on('click','.act-del',function(){
  if(!CAN_DELETE)return;
  const id=$(this).data('id'),num=$(this).data('num');
  Swal.fire({icon:'question',title:'Supprimer ?',html:'<b>'+esc(num)+'</b>',showCancelButton:true,confirmButtonText:'Supprimer',cancelButtonText:'Annuler',confirmButtonColor:'#D32F2F'}).then(function(r){
    if(!r.isConfirmed)return;
    apiPost({action:'delete',idaudit:id}).done(function(res){
      if(res.success){Swal.fire({icon:'success',timer:1400,showConfirmButton:false});loadTable();loadStats();}
      else{Swal.fire({icon:'error',title:'Suppression impossible',text:res.message,confirmButtonColor:'#23408F'});}
    });
  });
});

/* ======= VUE DETAIL ======= */
let CURRENT_AUDIT=null;
$(document).on('click','.act-view',function(){
  const id=$(this).data('id');
  $('#viewBody').html('<div class="text-center py-4"><span class="spinner-border text-primary"></span></div>');
  $('#viewNum').text('');
  new bootstrap.Modal('#viewModal').show();
  apiPost({action:'get',idaudit:id}).done(function(res){
    if(!res.success){$('#viewBody').html('<div class="alert alert-danger">'+esc(res.message||'Erreur')+'</div>');return;}
    CURRENT_AUDIT=res;
    $('#viewNum').text(res.data.num_audit||'');
    $('#viewBody').html(buildDetailHtml(res));
  });
});

function det(label,valHtml){return '<div class="det-item"><div class="dl">'+label+'</div><div class="dv">'+(valHtml||'<span style="color:#aab4c0;font-style:italic">-</span>')+'</div></div>';}
function detTxt(v){v=String(v||'').trim();return v?('<span style="font-weight:600;color:#2C3E50">'+esc(v)+'</span>'):'<span style="color:#aab4c0;font-style:italic">-</span>';}
function detSec(icon,title,body){return '<div class="det-sec"><div class="det-sec-head"><i class="bi '+icon+' me-2"></i>'+title+'</div><div class="det-sec-body"><div class="det-grid">'+body+'</div></div></div>';}

function buildDetailHtml(res){
  const a=res.data||{},eq=res.equipe||[],regs=res.reglements_detail||[];
  const statHtml=Number(a.est_ferme)===1?'<span class="sb sb0">Ferme</span>':(function(){const s=STATUT[a.statut]||{t:a.statut||'-',c:'sb1'};return '<span class="sb '+s.c+'">'+esc(s.t)+'</span>';}());

  // Equipe : regrouper par inspecteur
  const seenEq={};let eqHtml='<div style="grid-column:1/-1">';
  eq.forEach(function(m){
    if(seenEq[m.idinspecteur]) return; seenEq[m.idinspecteur]=true;
    const ra=Number(m.est_responsable)===1;
    // Reglements de cet inspecteur via idequipe (comparaison souple string/int)
    const myRegs=regs.filter(function(r){return String(r.idequipe)===String(m.idequipe) && r.idequipe!=null;});
    eqHtml+='<div class="eq-row'+(ra?' ra':'')+'"><div class="'+(ra?'dot-ra':'dot-in')+'"></div>'
      +'<div style="flex:1"><div style="font-weight:700;font-size:.86rem">'+(ra?'<span style="color:#D32F2F">':'')+esc(m.nom||'')+(ra?'<span style="font-size:.7rem;font-weight:700;background:#D32F2F;color:#fff;padding:.1rem .4rem;border-radius:10px;margin-left:5px">R.A</span></span>':'')+'</div>'
      +'<div style="font-size:.76rem;color:#7b8aa0">'+esc((m.nomdomaine||'')+(m.libel_domaine?' - '+m.libel_domaine:''))+'</div>'
      +(myRegs.length?'<div style="margin-top:3px">'+myRegs.map(function(r){return '<span class="reg-chip">'+esc(r.code_reglement||'')+'</span>';}).join('')+'</div>':'')
      +'</div></div>';
  });
  if(!eq.length) eqHtml+='<div class="text-muted small">Aucun inspecteur dans l\'equipe.</div>';
  eqHtml+='</div>';

  // Reglements globaux (non lies a une equipe)
  const globalRegs=regs.filter(function(r){return !r.idequipe;});
  let regHtml='<div style="grid-column:1/-1">';
  if(regs.length){
    regs.forEach(function(r){regHtml+='<span class="reg-chip">'+esc(r.code_reglement||'')+(r.libelle_reglement?' - '+esc(r.libelle_reglement.substring(0,40))+'..':'')+'</span>';});
  } else { regHtml+='<span class="text-muted small">Aucun reglement vise</span>'; }
  regHtml+='</div>';

  return detSec('bi-clipboard-data','Informations generales',
      det('N Audit',detTxt(a.num_audit))+det('Nature',detTxt(TYPE_LABELS[a.type_activite]||a.type_activite))
      +det('Cadre',detTxt(CADRE_LABELS[a.cadre]||a.cadre))+det('Statut',statHtml)
      +det('Activite operateur',detTxt(a.type_activite_operateur))+det('Site',detTxt(a.site_inspection)))
    +detSec('bi-buildings','Operateur et responsable',
      det('Operateur',detTxt(a.nomorga))
      +det('Responsable d\'audit','<span style="color:#D32F2F;font-weight:700">'+esc(a.responsable||'-')+'</span>'))
    +detSec('bi-calendar3','Planification et dates',
      det('Date previsionnelle',detTxt(fmtDate(a.date_previsionnelle)))
      +det('Date realisation',detTxt(fmtDate(a.date_realisation)))
      +det("Delai d'execution",a.delai_execution?detTxt(a.delai_execution+' j'):null)
      +det('Date rapport',detTxt(fmtDate(a.date_delivrance_rapport)))
      +det('Date notification',detTxt(fmtDate(a.date_notification)))
      +(Number(a.est_ferme)===1?det('Date fermeture',detTxt(fmtDate(a.date_fermeture))):''))
    +'<div class="det-sec"><div class="det-sec-head"><i class="bi bi-people me-2"></i>Equipe d\'audit et reglements</div><div class="det-sec-body"><div class="det-grid">'+eqHtml+'</div></div></div>'
    +(regs.length?'<div class="det-sec"><div class="det-sec-head"><i class="bi bi-journal-text me-2"></i>Tous les reglements vises</div><div class="det-sec-body"><div class="det-grid">'+regHtml+'</div></div></div>':'');
}

/* ======= PDF ======= */
$('#viewPrintBtn').on('click',function(){if(CURRENT_AUDIT)printPDF(CURRENT_AUDIT);});
function printPDF(res){
  const a=res.data||{},eq=res.equipe||[],regs=res.reglements_detail||[];
  const statLbl=Number(a.est_ferme)===1?'Ferme':((STATUT[a.statut]||{t:'-'}).t);
  const seen={};
  const teamRows=eq.map(function(m){
    if(seen[m.idinspecteur])return '';seen[m.idinspecteur]=true;
    const ra=Number(m.est_responsable)===1;
    const myRegs=regs.filter(function(r){return String(r.idequipe)===String(m.idequipe) && r.idequipe!=null;}).map(function(r){return esc(r.code_reglement||'');}).join(', ')||'-';
    return '<tr><td style="'+(ra?'color:#D32F2F;font-weight:700':'')+'">'+(m.nom||'-')+(ra?' (R.A)':'')+'</td>'
      +'<td>'+esc((m.nomdomaine||'')+(m.libel_domaine?' - '+m.libel_domaine:''))+'</td>'
      +'<td>'+myRegs+'</td></tr>';
  }).join('');
  const allRegs=regs.map(function(r){return esc(r.code_reglement||'')+(r.libelle_reglement?' - '+esc(r.libelle_reglement):'');}).join('<br>')||'-';
  const html='<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Audit '+esc(a.num_audit||'')+'</title>'
    +'<style>*{box-sizing:border-box;margin:0;padding:0}'
    +'body{font-family:Candara,Arial,sans-serif;font-size:10pt;color:#1e293b}'
    +'@page{margin:12mm 12mm 38mm 12mm}'
    +'.page{border:3px solid #23408F;padding:10px;min-height:270mm}'
    +'.hdr{border-bottom:3px solid #23408F;padding-bottom:8px;margin-bottom:12px;text-align:center}'
    +'.hdr img{max-height:60px;width:auto}'
    +'.ref{text-align:right;font-size:8pt;color:#555;margin-bottom:4px}'
    +'h1{text-align:center;font-size:13pt;font-weight:700;text-transform:uppercase;color:#23408F;margin:6px 0 12px;letter-spacing:.03em}'
    +'.sec{margin-bottom:12px;break-inside:avoid}'
    +'.sh{background:#23408F;color:#fff;padding:7px 12px;font-weight:700;font-size:9pt;-webkit-print-color-adjust:exact;print-color-adjust:exact}'
    +'.sb{border:1px solid #dde4f0;border-top:none;padding:10px 12px}'
    +'.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:6px 16px}'
    +'.grid-2{display:grid;grid-template-columns:repeat(2,1fr);gap:6px 16px}'
    +'.dl{font-size:7.5pt;text-transform:uppercase;color:#64748b;font-weight:700;letter-spacing:.03em;margin-bottom:1px}'
    +'.dv{font-size:9.5pt;font-weight:600;color:#1e293b;border-bottom:1px solid #e8edf5;padding-bottom:2px}'
    +'table.t{width:100%;border-collapse:collapse;font-size:9pt}'
    +'table.t th{background:#23408F;color:#fff;padding:5px 8px;text-align:left;-webkit-print-color-adjust:exact;print-color-adjust:exact}'
    +'table.t td{padding:4px 8px;border-bottom:1px solid #dde}'
    +'.fimg{position:fixed;bottom:0;left:0;right:0;text-align:center}'
    +'.fimg img{width:100%;max-height:30mm}'
    +'@media print{.page{border:3px solid #23408F}.fimg{position:fixed;bottom:0}}</style></head><body>'
    +'<div class="page">'
    +'<div class="ref">Audit AGAI - ANAC Gabon - '+new Date().toLocaleDateString('fr-FR')+'</div>'
    +'<div class="hdr"><img src="'+IMG_BASE+'banierenteanac.png" onerror="this.style.display=\'none\'"></div>'
    +'<h1>Fiche de mandat d\'audit</h1>'
    +'<div class="sec"><div class="sh">Informations generales</div><div class="sb"><div class="grid">'
    +'<div><div class="dl">N Audit</div><div class="dv">'+esc(a.num_audit||'-')+'</div></div>'
    +'<div><div class="dl">Nature</div><div class="dv">'+esc(TYPE_LABELS[a.type_activite]||a.type_activite||'-')+'</div></div>'
    +'<div><div class="dl">Cadre</div><div class="dv">'+esc(CADRE_LABELS[a.cadre]||a.cadre||'-')+'</div></div>'
    +'<div><div class="dl">Statut</div><div class="dv" style="'+(Number(a.est_ferme)===1?'color:#383d41':(STATUT[a.statut]?'color:'+STATUT[a.statut].col:''))+'">'+statLbl+'</div></div>'
    +'<div><div class="dl">Activite operateur</div><div class="dv">'+esc(a.type_activite_operateur||'-')+'</div></div>'
    +'<div><div class="dl">Site</div><div class="dv">'+esc(a.site_inspection||'-')+'</div></div>'
    +'</div></div></div>'
    +'<div class="sec"><div class="sh">Operateur et responsable</div><div class="sb"><div class="grid-2">'
    +'<div><div class="dl">Operateur</div><div class="dv">'+esc(a.nomorga||'-')+'</div></div>'
    +'<div><div class="dl">Responsable d\'Audit (R.A)</div><div class="dv" style="color:#D32F2F;font-weight:700">'+esc(a.responsable||'-')+'</div></div>'
    +'</div></div></div>'
    +'<div class="sec"><div class="sh">Planification</div><div class="sb"><div class="grid">'
    +'<div><div class="dl">Date previsionnelle</div><div class="dv">'+fmtDate(a.date_previsionnelle)+'</div></div>'
    +'<div><div class="dl">Date realisation</div><div class="dv">'+fmtDate(a.date_realisation)+'</div></div>'
    +'<div><div class="dl">Delai execution</div><div class="dv">'+(a.delai_execution?esc(a.delai_execution)+' j':'-')+'</div></div>'
    +'<div><div class="dl">Date rapport</div><div class="dv">'+fmtDate(a.date_delivrance_rapport)+'</div></div>'
    +'<div><div class="dl">Date notification</div><div class="dv">'+fmtDate(a.date_notification)+'</div></div>'
    +(Number(a.est_ferme)===1?'<div><div class="dl">Date fermeture</div><div class="dv">'+fmtDate(a.date_fermeture)+'</div></div>':'')
    +'</div></div></div>'
    +'<div class="sec"><div class="sh">Equipe d\'audit</div><div class="sb">'
    +'<table class="t"><thead><tr><th>Inspecteur</th><th>Domaine</th><th>Reglements</th></tr></thead>'
    +'<tbody>'+teamRows+'</tbody></table></div></div>'
    +(regs.length?'<div class="sec"><div class="sh">Reglements vises</div><div class="sb" style="font-size:9pt;line-height:1.6">'+allRegs+'</div></div>':'')
    +'<div style="margin-top:18px;padding-top:10px;border-top:1px solid #ccc;display:flex;justify-content:space-between;font-size:9pt">'
    +'<div><b>Signature du Responsable d\'Audit</b><br><br><br>_______________________</div>'
    +'<div style="text-align:right"><b>Visa du Chef Inspecteur</b><br><br><br>_______________________</div>'
    +'</div>'
    +'</div>'
    +'<div class="fimg"><img src="'+IMG_BASE+'pied_page_anac.jpg" onerror="this.style.display=\'none\'"></div>'
    +'</body></html>';
  const w=window.open('','_blank','width=900,height=700');
  w.document.write(html);w.document.close();
  w.onload=function(){setTimeout(function(){w.print();},300);};
}

/* ======= EXCEL ======= */
function exportExcel(data){
  const hdrs=['N Audit','Nature','Cadre','Activite operateur','Operateur','Site','Domaine(s)','Responsable','Inspecteurs','Date prev.','Date realisation','Delai(j)','Date rapport','Date notif.','Statut'];
  const rows=data.map(function(a){
    const doms=[...new Set((a.inspecteurs||[]).map(function(i){return i.nomdomaine||'';}).filter(Boolean))].join(', ');
    const insps=[...new Set((a.inspecteurs||[]).map(function(i){return (i.nom||'')+(Number(i.est_responsable)===1?' (RA)':'');}).filter(Boolean))].join(', ');
    const stat=Number(a.est_ferme)===1?'Ferme':((STATUT[a.statut]||{t:a.statut||'-'}).t);
    return [a.num_audit||'',TYPE_LABELS[a.type_activite]||a.type_activite||'',CADRE_LABELS[a.cadre]||a.cadre||'',
      a.type_activite_operateur||'',a.nomorga||'',a.site_inspection||'',doms,a.responsable||'',insps,
      fmtDate(a.date_previsionnelle),fmtDate(a.date_realisation),a.delai_execution||'',
      fmtDate(a.date_delivrance_rapport),fmtDate(a.date_notification),stat];
  });
  const now=new Date(),dtStr=now.toLocaleDateString('fr-FR')+' '+now.toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'});
  let tbl='<table border="1" style="border-collapse:collapse;font-family:Candara,Arial;font-size:9pt">';
  tbl+='<tr><th colspan="'+hdrs.length+'" style="background:#23408F;color:#fff;font-size:12pt;padding:12px;text-align:center">LISTE DES AUDITS ET INSPECTIONS - ANAC GABON</th></tr>';
  tbl+='<tr><th colspan="'+hdrs.length+'" style="background:#f5f7fa;font-size:9pt;padding:6px">Total : '+rows.length+' acte(s) | Edite le : '+dtStr+'</th></tr>';
  tbl+='<tr>'+hdrs.map(function(h){return '<th style="background:#23408F;color:#fff;padding:7px 10px">'+h+'</th>';}).join('')+'</tr>';
  rows.forEach(function(r,i){const bg=i%2===0?'#fff':'#f7f9fc';tbl+='<tr>'+r.map(function(c){return '<td style="padding:5px 8px;background:'+bg+'">'+String(c||'')+'</td>';}).join('')+'</tr>';});
  tbl+='<tr><td colspan="'+hdrs.length+'" style="padding:8px;font-style:italic;font-size:8pt;color:#888;text-align:center">AGAI - Systeme de Surveillance Continue de la Securite Aerienne - ANAC GABON</td></tr>';
  tbl+='</table>';
  const blob=new Blob(['<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'+tbl+'</body></html>'],{type:'application/vnd.ms-excel;charset=utf-8'});
  const a=document.createElement('a');a.href=URL.createObjectURL(blob);
  a.download='AUDITS_AGAI_'+now.toISOString().substring(0,10)+'.xls';
  a.click();URL.revokeObjectURL(a.href);
}
$('#btnExcel').on('click',function(){exportExcel(ALL);});
$('#viewExcelBtn').on('click',function(){if(CURRENT_AUDIT)exportExcel([CURRENT_AUDIT.data]);});

/* ======= DEMARRAGE ======= */
// 1. Charger les listes de support (exploitants, inspecteurs, sites...)
// 2. Charger le tableau (qui construira les filtres dynamiques une fois LISTS_LOADED)
loadLists().always(function(){
  loadTable();
});

</script>