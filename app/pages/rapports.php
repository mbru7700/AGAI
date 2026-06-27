<?php
/**
 * Page : Rapports d'actes de supervision
 * Dashboard Power BI + Modale avec section criteres NCE/NCS/NCNS/NCNE/NCNA
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardPage('rapports');
$csrf      = Security::generateCSRF();
$role      = Rbac::role();
$isCI      = in_array($role, ['admin','chef_inspecteur'], true);
$isOper    = ($role === 'operateur');
$pageTitle = 'Rapports';
$active    = 'rapports';
$pageIcon  = 'bi-file-earmark-text';
$sousTitre = $isOper
    ? 'Consultez les rapports d\'actes de supervision vous concernant.'
    : 'Joignez le rapport IX-GEN-R3-FI-009 et saisissez les conclusions des activites realisees.';
require_once INCLUDES_PATH . '/layout_head.php';
?>
<style>
/* Dashboard */
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:14px;}
.kpi-card{background:#fff;border:1px solid #eef1f6;border-radius:12px;padding:13px 14px;box-shadow:0 1px 2px rgba(16,30,54,.04);text-align:center;cursor:help;}
.kpi-num{font-size:1.75rem;font-weight:800;line-height:1;} .kpi-lbl{font-size:.74rem;color:#7b8aa0;margin-top:4px;}
.chart-box{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:14px;box-shadow:0 1px 2px rgba(16,30,54,.04);}
.chart-title{font-size:.8rem;font-weight:700;color:#23408F;text-transform:uppercase;letter-spacing:.04em;margin-bottom:10px;display:flex;align-items:center;gap:6px;}
.filter-bar{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:12px 16px;box-shadow:0 1px 2px rgba(16,30,54,.04);}
/* Tableau */
table.tbl{width:100%;border-collapse:separate;border-spacing:0;}
table.tbl thead th{background:#23408F;color:#fff;font-size:.71rem;text-transform:uppercase;letter-spacing:.04em;padding:9px 10px;border:none;font-weight:600;white-space:nowrap;}
table.tbl tbody td{padding:8px 10px;border-bottom:1px solid #f1f4f9;vertical-align:middle;font-size:.84rem;}
table.tbl tbody tr:hover{background:#fafcff;}
.empty{padding:36px;text-align:center;color:#9aa7bd;}
.s-badge{display:inline-block;padding:.2rem .5rem;border-radius:20px;font-size:.71rem;font-weight:700;}
.s1{background:#e8f0fe;color:#23408F;} .s3{background:#d1e7dd;color:#0a5c36;} .s4{background:#f8d7da;color:#842029;}
.tag-ok{background:#d1e7dd;color:#0a5c36;border-radius:8px;padding:3px 9px;font-size:.75rem;font-weight:700;display:inline-flex;align-items:center;gap:4px;}
.tag-non{background:#fff3cd;color:#856404;border-radius:8px;padding:3px 9px;font-size:.75rem;font-weight:600;display:inline-flex;align-items:center;gap:4px;}
.tag-ra{background:#f8d7da;color:#842029;border-radius:8px;padding:3px 9px;font-size:.75rem;font-weight:600;display:inline-flex;align-items:center;gap:4px;}
/* Boutons */
.btn-joindre-rap{background:linear-gradient(135deg,#1E9C4B,#157a3a);color:#fff;border:none;border-radius:8px;padding:5px 11px;font-size:.78rem;font-weight:700;display:inline-flex;align-items:center;gap:4px;cursor:pointer;transition:all .15s;}
.btn-joindre-rap:hover{background:linear-gradient(135deg,#157a3a,#0e5228);transform:translateY(-1px);}
.btn-remplacer-rap{background:linear-gradient(135deg,#b58a00,#9a7500);color:#fff;border:none;border-radius:8px;padding:5px 11px;font-size:.78rem;font-weight:700;display:inline-flex;align-items:center;gap:4px;cursor:pointer;transition:all .15s;}
.btn-remplacer-rap:hover{background:linear-gradient(135deg,#9a7500,#7d5f00);transform:translateY(-1px);}
.btn-voir-rap{background:#eef1f6;color:#23408F;border:1px solid #d0d7e3;border-radius:8px;padding:5px 10px;font-size:.77rem;font-weight:600;display:inline-flex;align-items:center;gap:4px;cursor:pointer;text-decoration:none;}
.btn-voir-rap:hover{background:#e0e6f5;color:#23408F;}
/* Section criteres */
.critere-row{display:grid;grid-template-columns:1fr auto;gap:8px;align-items:center;padding:5px 0;border-bottom:1px solid #f0f4ff;}
.critere-row:last-child{border-bottom:none;}
.critere-label{font-size:.84rem;color:#2C3E50;}
.critere-badge{font-size:.72rem;font-weight:700;color:#23408F;background:#e8f0fe;border-radius:20px;padding:2px 8px;}
.ncr-total{background:#23408F;color:#fff;border-radius:10px;padding:10px 14px;display:flex;justify-content:space-between;align-items:center;margin-top:10px;}
.taux-box{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px;}
.taux-item{border-radius:10px;padding:10px 14px;text-align:center;}
.taux-conf{background:rgba(30,156,75,.1);border:1px solid rgba(30,156,75,.3);}
.taux-nonconf{background:rgba(211,47,47,.08);border:1px solid rgba(211,47,47,.2);}
/* Classement operateurs */
.rank-row{display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;margin-bottom:4px;}
.rank-1{background:linear-gradient(90deg,rgba(243,195,0,.15),transparent);}
.rank-2{background:linear-gradient(90deg,rgba(192,192,192,.12),transparent);}
.rank-3{background:linear-gradient(90deg,rgba(205,127,50,.1),transparent);}
.rank-bar{flex:1;height:10px;background:#eef1f6;border-radius:5px;overflow:hidden;}
.rank-fill{height:100%;border-radius:5px;transition:width .4s;}
.howto-card{background:linear-gradient(135deg,rgba(30,156,75,.04),rgba(35,64,143,.03));border:1px solid rgba(30,156,75,.15);border-radius:14px;padding:16px 20px;}
.howto-step{display:flex;align-items:flex-start;gap:12px;padding:8px 0;border-bottom:1px solid rgba(30,156,75,.08);}
.howto-step:last-child{border-bottom:none;}
.step-num{width:30px;height:30px;border-radius:50%;background:#1E9C4B;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.85rem;flex:0 0 auto;}
</style>

<div class="page-head">
  <div>
    <h1><i class="bi bi-file-earmark-text me-2" style="color:var(--anac-primary)"></i>Rapports d'actes de supervision</h1>
    <div class="sub"><?php echo Security::escape($sousTitre); ?></div>
  </div>
</div>

<?php if ($isOper): ?>
<div class="alert mb-3" style="background:#f0fdf4;border:1px solid #a7f3d0;border-left:4px solid #1E9C4B;border-radius:10px;padding:12px 16px">
  <i class="bi bi-info-circle-fill me-2" style="color:#1E9C4B"></i>
  <strong style="color:#1E9C4B">Espace operateur.</strong>
  Consultez vos rapports d'actes de supervision. Cliquez sur <strong>Voir</strong> pour consulter ou telecharger.
</div>
<?php else: ?>
<!-- Guide -->
<div class="howto-card mb-3" id="guideBox">
  <div class="d-flex align-items-center gap-2 mb-2">
    <i class="bi bi-info-circle-fill" style="color:#1E9C4B;font-size:1.1rem"></i>
    <strong style="color:#1E9C4B;font-size:.92rem">Comment fonctionne ce module ?</strong>
    <button class="btn btn-sm btn-outline-secondary ms-auto" id="btnToggleGuide" style="font-size:.75rem"><span id="guideLbl">Masquer</span></button>
  </div>
  <div id="guideBody">
    <div class="howto-step"><div class="step-num">1</div><div><strong>Eligibilite.</strong> Seuls les audits avec lettre de notification jointe apparaissent. Completez d'abord le module Notifications.</div></div>
    <div class="howto-step"><div class="step-num">2</div><div><strong>Joindre le rapport.</strong> Cliquez sur "Joindre" (vert), selectionnez le fichier PDF ou Word, saisissez la date de realisation et completez la section criteres.</div></div>
    <div class="howto-step"><div class="step-num">3</div><div><strong>Section criteres.</strong> Saisissez NCE, NCS, NCNS, NCNE, NCNA. Le total NCR et les taux de conformite se calculent automatiquement.</div></div>
    <div class="howto-step"><div class="step-num">4</div><div><strong>Automatisations.</strong> Le statut passe a Effectue, la date de delivrance est enregistree, le delai d'execution est calcule.</div></div>
  </div>
</div>
<?php endif; ?>

<!-- Toggle dashboard -->
<div class="d-flex justify-content-between align-items-center mb-2">
  <button class="btn btn-sm btn-outline-secondary" id="btnToggleDash">
    <i class="bi bi-graph-up me-1"></i><span id="dashLbl">Afficher le tableau de bord</span>
  </button>
  <span class="small text-muted" id="resCount"></span>
</div>

<!-- ======= DASHBOARD POWER BI ======= -->
<div id="dashPanel" style="display:none">

  <!-- Filtres dashboard -->
  <div class="filter-bar mb-3">
    <div class="d-flex align-items-center gap-2 mb-2">
      <i class="bi bi-funnel-fill" style="color:#23408F"></i>
      <span style="font-size:.78rem;font-weight:700;color:#23408F;text-transform:uppercase">Filtres du tableau de bord</span>
      <button class="btn btn-xs btn-outline-secondary ms-auto" id="btnResetDash" style="font-size:.72rem;padding:2px 8px"><i class="bi bi-x-lg me-1"></i>Reset</button>
    </div>
    <div class="row g-2">
      <div class="col-md-3"><label class="form-label small fw-bold mb-1" style="color:#5b6b85;font-size:.72rem;text-transform:uppercase">N Audit</label><select id="df_audit" style="width:100%"><option value="">Tous</option></select></div>
      <div class="col-md-3"><label class="form-label small fw-bold mb-1" style="color:#5b6b85;font-size:.72rem;text-transform:uppercase">Operateur</label><select id="df_orga" style="width:100%"><option value="">Tous</option></select></div>
      <div class="col-md-2"><label class="form-label small fw-bold mb-1" style="color:#5b6b85;font-size:.72rem;text-transform:uppercase">Annee</label><select id="df_annee" style="width:100%"><option value="">Toutes</option></select></div>
      <div class="col-md-2"><label class="form-label small fw-bold mb-1" style="color:#5b6b85;font-size:.72rem;text-transform:uppercase">Mois</label><select id="df_mois" class="form-select form-select-sm">
        <option value="">Tous</option>
        <?php for($m=1;$m<=12;$m++): ?><option value="<?php echo str_pad($m,2,'0',STR_PAD_LEFT); ?>"><?php echo DateTime::createFromFormat('!m',$m)->format('F'); ?></option><?php endfor; ?>
      </select></div>
      <div class="col-md-2"><label class="form-label small fw-bold mb-1" style="color:#5b6b85;font-size:.72rem;text-transform:uppercase">Nature</label>
        <select id="df_type" style="width:100%"><option value="">Toutes</option>
          <option value="audit">Audit</option><option value="inspection_programmee">Insp. prog.</option>
          <option value="inspection_non_programmee">Insp. non prog.</option>
          <option value="demonstration">Demo</option><option value="test">Test</option>
        </select>
      </div>
    </div>
  </div>

  <!-- KPI -->
  <div class="kpi-grid mb-3">
    <div class="kpi-card" title="Nombre total de rapports joints (PDF ou Word). Les rapports analyses incluent uniquement ceux avec criteres NCR renseignes.">
      <div class="kpi-num" id="k_total" style="color:#23408F">-</div>
      <div class="kpi-lbl">Rapports joints</div>
      <div style="font-size:.7rem;color:#7b8aa0" id="k_analyses_lbl"></div>
    </div>
    <div class="kpi-card" title="Taux de conformite moyen = Moy(NCS/(NCS+NCNS)*100)"><div class="kpi-num" id="k_tc" style="color:#1E9C4B">- %</div><div class="kpi-lbl">Taux conformite moy.</div></div>
    <div class="kpi-card" title="Taux de non-conformite moyen = Moy(NCNS/(NCS+NCNS)*100)"><div class="kpi-num" id="k_tnc" style="color:#D32F2F">- %</div><div class="kpi-lbl">Taux non-conf. moy.</div></div>
    <div class="kpi-card" title="Total cumule de tous les criteres retenus (NCR)"><div class="kpi-num" id="k_ncr" style="color:#23408F">-</div><div class="kpi-lbl">Total criteres NCR</div></div>
    <div class="kpi-card" title="Total cumule de criteres satisfaisants (NCS)"><div class="kpi-num" id="k_ncs" style="color:#1E9C4B">-</div><div class="kpi-lbl">Total NCS</div></div>
    <div class="kpi-card" title="Total cumule de criteres non satisfaisants (NCNS) - font l'objet de FNC"><div class="kpi-num" id="k_ncns" style="color:#D32F2F">-</div><div class="kpi-lbl">Total NCNS (FNC)</div></div>
  </div>

  <!-- Graphiques ligne 1 -->
  <div class="row g-3 mb-3">
    <div class="col-md-5">
      <div class="chart-box" style="height:280px">
        <div class="chart-title"><i class="bi bi-bar-chart-fill"></i>Taux de conformite par operateur (baton)</div>
        <canvas id="chartBarOrga"></canvas>
      </div>
    </div>
    <div class="col-md-4">
      <div class="chart-box" style="height:280px">
        <div class="chart-title"><i class="bi bi-pie-chart-fill"></i>Repartition NCS/NCNS (camembert)</div>
        <canvas id="chartPie"></canvas>
      </div>
    </div>
    <div class="col-md-3">
      <div class="chart-box" style="height:280px;overflow-y:auto">
        <div class="chart-title"><i class="bi bi-trophy-fill text-warning"></i>Classement operateurs</div>
        <div id="rankList"></div>
      </div>
    </div>
  </div>

  <!-- Graphiques ligne 2 -->
  <div class="row g-3 mb-3">
    <div class="col-md-6">
      <div class="chart-box" style="height:220px">
        <div class="chart-title"><i class="bi bi-calendar3"></i>Evolution annuelle du taux de conformite</div>
        <canvas id="chartAnnee"></canvas>
      </div>
    </div>
    <div class="col-md-6">
      <div class="chart-box" style="height:220px">
        <div class="chart-title"><i class="bi bi-clipboard-data"></i>Taux de conformite par nature d'audit</div>
        <canvas id="chartType"></canvas>
      </div>
    </div>
  </div>

  <!-- Graphiques ligne 3 : NCE/NCS/NCNS/NCNE/NCNA -->
  <div class="row g-3 mb-3">
    <div class="col-md-7">
      <div class="chart-box" style="height:250px">
        <div class="chart-title"><i class="bi bi-bar-chart-steps me-1"></i>Repartition des criteres - Diagramme en batons (NCE/NCS/NCNS/NCNE/NCNA)</div>
        <canvas id="chartCriteresBar"></canvas>
      </div>
    </div>
    <div class="col-md-5">
      <div class="chart-box" style="height:250px">
        <div class="chart-title"><i class="bi bi-pie-chart me-1"></i>Repartition des criteres - Camembert (%) NCE/NCS/NCNS/NCNE/NCNA</div>
        <canvas id="chartCriteresPie"></canvas>
      </div>
    </div>
  </div>

  <!-- Insights -->
  <div class="row g-3 mb-3">
    <div class="col-md-4"><div class="chart-box" style="border-left:4px solid #1E9C4B"><div class="chart-title text-success"><i class="bi bi-star-fill me-1"></i>Meilleur operateur</div><div id="bestOrga" class="small text-muted">-</div></div></div>
    <div class="col-md-4"><div class="chart-box" style="border-left:4px solid #D32F2F"><div class="chart-title text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>Operateur a accompagner</div><div id="worstOrga" class="small text-muted">-</div></div></div>
    <div class="col-md-4"><div class="chart-box" style="border-left:4px solid #23408F"><div class="chart-title"><i class="bi bi-lightbulb-fill me-1"></i>Indice qualite global</div><div id="indiceGlobal" class="small text-muted">-</div></div></div>
  </div>
</div>

<!-- Filtres tableau -->
<div class="filter-bar mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label small fw-bold mb-1" style="color:#5b6b85;font-size:.72rem;text-transform:uppercase">Operateur</label><select id="fOrga" style="width:100%"><option value="">Tous</option></select></div>
    <div class="col-md-3"><label class="form-label small fw-bold mb-1" style="color:#5b6b85;font-size:.72rem;text-transform:uppercase">Statut</label><select id="fStatut" style="width:100%"><option value="">Tous</option><option value="1">Planifie</option><option value="3">Effectue</option><option value="2">Reporte</option></select></div>
    <div class="col-md-3"><label class="form-label small fw-bold mb-1" style="color:#5b6b85;font-size:.72rem;text-transform:uppercase">Etat rapport</label><select id="fEtat" class="form-select form-select-sm"><option value="">Tous</option><option value="1">Rapport joint</option><option value="0">Sans rapport</option></select></div>
    <div class="col-md-2"><label class="form-label small fw-bold mb-1" style="color:#5b6b85;font-size:.72rem;text-transform:uppercase">Recherche</label><input type="text" id="fSearch" class="form-control form-control-sm" placeholder="N audit, RA..."></div>
    <div class="col-md-1"><label style="visibility:hidden" class="form-label small mb-1">-</label><button class="btn btn-sm btn-outline-secondary w-100" id="btnReset"><i class="bi bi-x-lg"></i></button></div>
  </div>
</div>

<!-- Tableau -->
<div style="background:#fff;border:1px solid #eef1f6;border-radius:14px;overflow:hidden;box-shadow:0 1px 2px rgba(16,30,54,.04)">
  <table class="tbl">
    <thead><tr>
      <th>N Audit</th><th>Nature</th><th>Operateur</th><th>RA</th>
      <th>Date prev.</th><th>Date real.</th><th>Delai</th><th>Statut</th>
      <th>NCR</th><th title="Taux de conformite">Conf. %</th><th>Rapport</th>
      <th style="text-align:right">Actions</th>
    </tr></thead>
    <tbody id="tbody"><tr><td colspan="12" class="empty"><span class="spinner-border spinner-border-sm me-2"></span>Chargement...</td></tr></tbody>
  </table>
</div>

<!-- MODALE : Joindre le rapport -->
<div class="modal fade" id="rapModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <form class="modal-content" id="rapForm" enctype="multipart/form-data">
      <div class="modal-header" style="background:linear-gradient(135deg,#1E9C4B,#157a3a)">
        <h5 class="modal-title text-white"><i class="bi bi-file-earmark-plus me-2" style="color:#F3C300"></i><span id="rapModalTitle">Joindre le rapport</span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="rap_idaudit">
        <div class="mb-3 p-3" style="background:#f0fdf4;border-radius:10px;border-left:4px solid #1E9C4B">
          <div id="rap_audit_info" style="font-weight:700;color:#1E9C4B;font-size:.92rem"></div>
          <div id="rap_orga_info"  style="font-size:.84rem;color:#555;margin-top:3px"></div>
        </div>
        <div class="mb-3 p-2" style="background:#e8f0fe;border-radius:8px;font-size:.82rem;color:#23408F">
          <i class="bi bi-bookmark-fill me-1"></i><strong>Formulaire IX-GEN-R3-FI-009</strong> - Rapport d'acte de supervision
        </div>
        <!-- Fichier -->
        <div class="mb-3">
          <label class="form-label fw-bold">Fichier rapport (PDF ou Word) <span class="text-danger">*</span></label>
          <input type="file" class="form-control" id="rap_fichier" name="fichier_rapport" accept=".pdf,.doc,.docx" required>
          <div class="form-text"><i class="bi bi-info-circle me-1 text-success"></i>PDF, DOC ou DOCX - Pas de limite de taille.</div>
        </div>
        <!-- Date realisation -->
        <div class="mb-3">
          <label class="form-label fw-bold">Date de realisation <span class="text-danger">*</span></label>
          <input type="date" class="form-control" id="rap_date_real">
          <div class="form-text">Le delai d'execution sera calcule automatiquement.</div>
        </div>

        <!-- SECTION CRITERES -->
        <div style="border:1px solid #23408F;border-radius:10px;overflow:hidden;margin-bottom:0">
          <div style="background:#23408F;color:#fff;padding:8px 14px;font-weight:700;font-size:.85rem">
            <i class="bi bi-list-check me-2" style="color:#F3C300"></i>Conclusion des activites realisees
          </div>
          <div style="padding:14px">
            <div class="row g-3">
              <!-- NCE -->
              <div class="col-md-6">
                <label class="form-label fw-bold" style="font-size:.82rem">Nombre de criteres evalues <span class="critere-badge">NCE</span></label>
                <input type="number" class="form-control form-control-sm crit-input" id="c_nce" name="nce" min="0" value="0" placeholder="0">
              </div>
              <!-- NCS -->
              <div class="col-md-6">
                <label class="form-label fw-bold" style="font-size:.82rem">Criteres jugés satisfaisants <span class="critere-badge" style="background:#d1fae5;color:#065f46">NCS</span></label>
                <input type="number" class="form-control form-control-sm crit-input" id="c_ncs" name="ncs" min="0" value="0">
              </div>
              <!-- NCNS -->
              <div class="col-md-6">
                <label class="form-label fw-bold" style="font-size:.82rem">Criteres jugés non satisfaisants <span class="critere-badge" style="background:#fee2e2;color:#991b1b">NCNS</span></label>
                <input type="number" class="form-control form-control-sm crit-input" id="c_ncns" name="ncns" min="0" value="0">
                <div class="form-text text-warning" style="font-size:.74rem"><i class="bi bi-exclamation-triangle me-1"></i>Font l'objet d'emission de FNC attachees a ce rapport.</div>
              </div>
              <!-- NCNE -->
              <div class="col-md-3">
                <label class="form-label fw-bold" style="font-size:.82rem">Non evalues <span class="critere-badge" style="background:#f0e6ff;color:#5a189a">NCNE</span></label>
                <input type="number" class="form-control form-control-sm crit-input" id="c_ncne" name="ncne" min="0" value="0">
              </div>
              <!-- NCNA -->
              <div class="col-md-3">
                <label class="form-label fw-bold" style="font-size:.82rem">Non applicables <span class="critere-badge" style="background:#f5f5f5;color:#383d41">NCNA</span></label>
                <input type="number" class="form-control form-control-sm crit-input" id="c_ncna" name="ncna" min="0" value="0">
              </div>
            </div>
            <!-- NCR total -->
            <div class="ncr-total mt-3">
              <span style="font-size:.88rem;font-weight:700">Total des criteres retenus (NCR) = NCE + NCS + NCNS + NCNE + NCNA</span>
              <span id="ncr_val" style="font-size:1.4rem;font-weight:900">0</span>
            </div>
            <!-- Taux calcules -->
            <div class="taux-box">
              <div class="taux-item taux-conf">
                <div style="font-size:.78rem;font-weight:700;color:#1E9C4B;margin-bottom:4px">Taux de conformite</div>
                <div id="tc_val" style="font-size:1.5rem;font-weight:800;color:#1E9C4B">- %</div>
                <div style="font-size:.72rem;color:#555">NCS / (NCS + NCNS) x 100</div>
              </div>
              <div class="taux-item taux-nonconf">
                <div style="font-size:.78rem;font-weight:700;color:#D32F2F;margin-bottom:4px">Taux de non-conformite</div>
                <div id="tnc_val" style="font-size:1.5rem;font-weight:800;color:#D32F2F">- %</div>
                <div style="font-size:.72rem;color:#555">NCNS / (NCS + NCNS) x 100</div>
              </div>
            </div>
          </div>
        </div>

        <div class="mt-3 p-2" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;font-size:.8rem;color:#555">
          <i class="bi bi-magic me-1 text-success"></i>
          A l'enregistrement : statut passe a <strong>Effectue</strong>, date de delivrance = aujourd'hui, delai calcule.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" class="btn" id="rap_submit" style="background:#1E9C4B;color:#fff;font-weight:600">
          <i class="bi bi-check-lg me-1"></i>Enregistrer le rapport
        </button>
      </div>
    </form>
  </div>
</div>

<!-- MODALE : Voir PDF -->
<div class="modal fade" id="pdfModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width:90vw">
    <div class="modal-content" style="height:88vh;display:flex;flex-direction:column">
      <div class="modal-header py-2">
        <h5 class="modal-title"><i class="bi bi-file-earmark-text me-2 text-success"></i><span id="pdfTitle"></span></h5>
        <div class="ms-auto d-flex gap-2 me-2">
          <a id="pdfDl" href="#" class="btn btn-sm btn-outline-primary"><i class="bi bi-download me-1"></i>Telecharger</a>
          <button class="btn btn-sm btn-outline-secondary" id="pdfPrint"><i class="bi bi-printer me-1"></i>Imprimer</button>
        </div>
        <button type="button" class="btn-close ms-2" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0" style="flex:1;overflow:hidden">
        <iframe id="pdfFrame" src="" style="width:100%;height:100%;border:none"></iframe>
      </div>
    </div>
  </div>
</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const CSRF   = '<?php echo Security::escape($csrf); ?>';
const API    = AGAI_BASE + '/api/rapports';
const IS_CI  = <?php echo $isCI ? 'true' : 'false'; ?>;
const IS_OPER= <?php echo $isOper ? 'true' : 'false'; ?>;
let ALL=[], ALL_R=[], chartBarOrga=null, chartPie=null, chartAnnee=null, chartType=null;

function apiPost(data){ return $.post(API, Object.assign({csrf_token:CSRF}, data), null, 'json'); }
function fmtDate(s){ if(!s||s==='0000-00-00'||s===null) return '-'; const p=String(s).substring(0,10).split('-'); return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:s; }
const TYPES={audit:'Audit',inspection_programmee:'Insp. prog.',inspection_non_programmee:'Insp. non prog.',demonstration:'Demo',test:'Test',investigation:'Investigation'};
const STATUT={1:{t:'Planifie',c:'s1'},3:{t:'Effectue',c:'s3'},2:{t:'Reporte',c:''},4:{t:'Suspendu',c:''}};

/* ===== GUIDE ===== */
let guideVisible=true;
$('#btnToggleGuide').on('click',function(){ guideVisible=!guideVisible; $('#guideBody').toggle(guideVisible); $('#guideLbl').text(guideVisible?'Masquer':'Afficher'); });

/* ===== TOGGLE DASHBOARD ===== */
let dashVisible=false;
function setDash(show){
  dashVisible=show; $('#dashPanel').toggle(show);
  $('#dashLbl').text(show?'Masquer le tableau de bord':'Afficher le tableau de bord');
  try{localStorage.setItem('agai_dash_rapports',show?'1':'0');}catch(e){}
  if(show&&ALL_R.length) renderDash(getFiltered_R());
}

/* ===== CALCUL DASHBOARD ===== */
function computeDash(list){
  const n=list.length; if(!n) return null;
  let sumTC=0,sumTNC=0,cntTC=0,sumNCR=0,sumNCS=0,sumNCNS=0,sumNCE=0,sumNCNE=0,sumNCNA=0;
  const byOrga={}, byAn={}, byType={};
  list.forEach(function(r){
    sumNCR +=(parseInt(r.ncr) ||0); sumNCE +=(parseInt(r.nce) ||0);
    sumNCS +=(parseInt(r.ncs) ||0); sumNCNS+=(parseInt(r.ncns)||0);
    sumNCNE+=(parseInt(r.ncne)||0); sumNCNA+=(parseInt(r.ncna)||0);
    if(r.taux_conformite!==null&&r.taux_conformite!==''){ sumTC+=parseFloat(r.taux_conformite||0); cntTC++; }
    sumTNC+=parseFloat(r.taux_non_conformite||0);
    // Par orga
    const ok=r.nomorga||'Inconnu';
    if(!byOrga[ok]) byOrga[ok]={nomorga:ok,nb:0,sumTC:0,sumNCS:0,sumNCNS:0,sumNCE:0,sumNCNE:0,sumNCNA:0};
    byOrga[ok].nb++; byOrga[ok].sumTC+=parseFloat(r.taux_conformite||0);
    byOrga[ok].sumNCS +=(parseInt(r.ncs)||0); byOrga[ok].sumNCNS+=(parseInt(r.ncns)||0);
    byOrga[ok].sumNCE +=(parseInt(r.nce)||0); byOrga[ok].sumNCNE+=(parseInt(r.ncne)||0);
    byOrga[ok].sumNCNA+=(parseInt(r.ncna)||0);
    // Par annee
    const yr=(r.date_realisation||'').substring(0,4);
    if(yr&&yr>='2020'){
      if(!byAn[yr]) byAn[yr]={annee:yr,nb:0,sumTC:0};
      byAn[yr].nb++; byAn[yr].sumTC+=parseFloat(r.taux_conformite||0);
    }
    // Par type
    const t=r.type_activite||'autre';
    if(!byType[t]) byType[t]={label:TYPES[t]||t,nb:0,sumTC:0};
    byType[t].nb++; byType[t].sumTC+=parseFloat(r.taux_conformite||0);
  });
  Object.values(byOrga).forEach(function(g){ g.taux_moy=g.nb?Math.round(g.sumTC/g.nb*10)/10:0; });
  const orgaArr=Object.values(byOrga).sort(function(a,b){return b.taux_moy-a.taux_moy;});
  Object.values(byAn).forEach(function(g){ g.taux_moy=g.nb?Math.round(g.sumTC/g.nb*10)/10:0; });
  Object.values(byType).forEach(function(g){ g.taux_moy=g.nb?Math.round(g.sumTC/g.nb*10)/10:0; });
  return {n,avgTC:cntTC?Math.round(sumTC/cntTC*10)/10:0,avgTNC:cntTC?Math.round(sumTNC/cntTC*10)/10:0,
    sumNCR,sumNCS,sumNCNS,sumNCE,sumNCNE,sumNCNA,
    byOrga:orgaArr,
    byAn:Object.values(byAn).sort(function(a,b){return a.annee>b.annee?1:-1;}),
    byType:Object.values(byType)};
}

// Plugin tooltip camembert avec nb + pourcentage
const PIE_LABEL_PLUGIN = {
  id:'pieLabels',
  afterDatasetDraw(chart){
    const {ctx,data}=chart;
    const total=data.datasets[0].data.reduce(function(a,b){return a+b;},0);
    if(!total) return;
    chart.getDatasetMeta(0).data.forEach(function(arc,i){
      const val=data.datasets[0].data[i];
      if(!val) return;
      const pct=Math.round(val/total*1000)/10;
      const midAngle=arc.startAngle+(arc.endAngle-arc.startAngle)/2;
      const r=arc.outerRadius*0.65;
      const x=arc.x+Math.cos(midAngle)*r, y=arc.y+Math.sin(midAngle)*r;
      ctx.save();
      ctx.fillStyle='#fff'; ctx.font='bold 9px Candara,Arial,sans-serif';
      ctx.textAlign='center'; ctx.textBaseline='middle';
      ctx.fillText(val+' ('+pct+'%)',x,y);
      ctx.restore();
    });
  }
};
Chart.register(PIE_LABEL_PLUGIN);

function renderDash(list){
  const d=computeDash(list);
  // Toujours mettre a jour les KPI et insights, meme si dashboard ferme
  if(!d){
    ['k_total','k_tc','k_tnc','k_ncr','k_ncs','k_ncns'].forEach(function(i){$('#'+i).text('-');});
    $('#bestOrga,#worstOrga,#indiceGlobal').html('<span class="text-muted">Aucune donnee</span>');
    ['chartBarOrga','chartPie','chartAnnee','chartType','chartCriteresBar','chartCriteresPie'].forEach(destroyChart);
    return;
  }
  // KPI
  $('#k_total').text(d.n); $('#k_tc').text(d.avgTC+'%'); $('#k_tnc').text(d.avgTNC+'%');
  $('#k_ncr').text(d.sumNCR); $('#k_ncs').text(d.sumNCS); $('#k_ncns').text(d.sumNCNS);

  // 1. Graphique barres par operateur (top 10)
  destroyChart('chartBarOrga');
  if(d.byOrga.length){
    const top=d.byOrga.slice(0,10);
    const colors=top.map(function(g){return g.taux_moy>=80?'rgba(30,156,75,.85)':g.taux_moy>=60?'rgba(35,64,143,.85)':g.taux_moy>=40?'rgba(243,195,0,.85)':'rgba(211,47,47,.85)';});
    new Chart(document.getElementById('chartBarOrga'),{type:'bar',data:{
      labels:top.map(function(g){return g.nomorga.length>16?g.nomorga.substring(0,14)+'..':g.nomorga;}),
      datasets:[{label:'Taux conformite (%)',data:top.map(function(g){return g.taux_moy;}),backgroundColor:colors,borderRadius:5,borderWidth:0}]
    },options:{responsive:true,maintainAspectRatio:false,animation:{onComplete:function(){
      const chart=this; const ctx=chart.ctx; const meta=chart.getDatasetMeta(0);
      meta.data.forEach(function(bar,i){
        const val=chart.data.datasets[0].data[i]; if(!val) return;
        const barH=bar.base-bar.y;
        ctx.save(); ctx.textAlign='center'; ctx.textBaseline='middle'; ctx.font='bold 9px Candara,Arial,sans-serif';
        if(barH>22){ ctx.fillStyle='#fff'; ctx.fillText(val+'%', bar.x, bar.y+barH*0.5); }
        else { ctx.fillStyle='#2C3E50'; ctx.fillText(val+'%', bar.x, bar.y-7); }
        ctx.restore();
      });
    }},plugins:{legend:{display:false},tooltip:{callbacks:{label:function(c){return ' '+c.parsed.y+'%';}}}},
      scales:{y:{beginAtZero:true,max:100,ticks:{font:{size:9},callback:function(v){return v+'%';}}},x:{ticks:{font:{size:8}}}}}});
  }

  // 2. Camembert NCS/NCNS avec nb + % lisibles
  destroyChart('chartPie');
  if(d.sumNCS||d.sumNCNS){
    const totalPie=d.sumNCS+d.sumNCNS;
    new Chart(document.getElementById('chartPie'),{type:'pie',data:{
      labels:[
        'NCS - Satisfaisants ('+d.sumNCS+' | '+Math.round(d.sumNCS/totalPie*100)+'%)',
        'NCNS - Non satisfaisants ('+d.sumNCNS+' | '+Math.round(d.sumNCNS/totalPie*100)+'%)'
      ],
      datasets:[{data:[d.sumNCS,d.sumNCNS],backgroundColor:['rgba(30,156,75,.85)','rgba(211,47,47,.85)'],borderColor:['#fff','#fff'],borderWidth:3}]
    },options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{font:{size:8},boxWidth:10,padding:6}},
      tooltip:{callbacks:{label:function(c){const t=c.chart.data.datasets[0].data.reduce(function(a,b){return a+b;},0);return ' '+c.parsed+' ('+Math.round(c.parsed/t*100)+'%)';}}}}}}); 
  }

  // 3. Classement operateurs
  let rankHtml='';
  d.byOrga.forEach(function(g,i){
    const medal=i===0?'<i class="bi bi-trophy-fill text-warning"></i>':i===1?'<span style="color:#aaa;font-weight:800">2</span>':i===2?'<span style="color:#cd7f32;font-weight:800">3</span>':'<span class="text-muted" style="font-size:.76rem">'+(i+1)+'</span>';
    const cls=i===0?'rank-1':i===1?'rank-2':i===2?'rank-3':'';
    const fc=g.taux_moy>=80?'#1E9C4B':g.taux_moy>=60?'#23408F':g.taux_moy>=40?'#b58a00':'#D32F2F';
    rankHtml+='<div class="rank-row '+cls+'" title="NCS:'+g.sumNCS+' NCNS:'+g.sumNCNS+' sur '+g.nb+' rapport(s)">'
      +'<span style="font-size:.78rem;flex:0 0 18px;text-align:center">'+medal+'</span>'
      +'<div style="flex:1;min-width:0"><div style="font-size:.76rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+esc(g.nomorga)+'</div>'
      +'<div class="rank-bar"><div class="rank-fill" style="width:'+g.taux_moy+'%;background:'+fc+'"></div></div></div>'
      +'<span style="font-size:.8rem;font-weight:800;color:'+fc+';flex:0 0 42px;text-align:right">'+g.taux_moy+'%</span>'
      +'</div>';
  });
  $('#rankList').html(rankHtml||'<div class="text-muted small text-center py-2">Aucune donnee</div>');

  // 4. Evolution annuelle (ligne)
  destroyChart('chartAnnee');
  if(d.byAn.length){
    new Chart(document.getElementById('chartAnnee'),{type:'line',data:{
      labels:d.byAn.map(function(g){return g.annee;}),
      datasets:[{label:'Taux conformite (%)',data:d.byAn.map(function(g){return g.taux_moy;}),
        borderColor:'#23408F',backgroundColor:'rgba(35,64,143,.08)',fill:true,tension:.3,
        pointRadius:5,pointBackgroundColor:'#23408F',pointHoverRadius:7}]
    },options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},
      tooltip:{callbacks:{label:function(c){return ' '+c.parsed.y+'%  ('+d.byAn[c.dataIndex].nb+' rapp.)';}}}},
      scales:{y:{beginAtZero:true,max:100,ticks:{font:{size:9},callback:function(v){return v+'%';}}},x:{ticks:{font:{size:9}}}}}});
  }

  // 5. Par nature (barres horizontales)
  destroyChart('chartType');
  if(d.byType.length){
    const fc2=d.byType.map(function(g){return g.taux_moy>=80?'rgba(30,156,75,.8)':g.taux_moy>=60?'rgba(35,64,143,.8)':g.taux_moy>=40?'rgba(243,195,0,.8)':'rgba(211,47,47,.8)';});
    new Chart(document.getElementById('chartType'),{type:'bar',data:{
      labels:d.byType.map(function(g){return g.label;}),
      datasets:[{data:d.byType.map(function(g){return g.taux_moy;}),backgroundColor:fc2,borderRadius:5}]
    },options:{responsive:true,maintainAspectRatio:false,indexAxis:'y',
      plugins:{legend:{display:false},tooltip:{callbacks:{label:function(c){return ' '+c.parsed.x+'%';}}},},
      scales:{x:{beginAtZero:true,max:100,ticks:{font:{size:9},callback:function(v){return v+'%';}}},y:{ticks:{font:{size:9}}}}}});
  }

  // 6. Nouveaux blocs : NCE/NCS/NCNS/NCNE/NCNA - Diagramme en batons
  destroyChart('chartCriteresBar');
  const labsCrit=['NCE (Evalues)','NCS (Satisfaisants)','NCNS (Non Sat.)','NCNE (Non eval.)','NCNA (Non appl.)'];
  const valsCrit=[d.sumNCE,d.sumNCS,d.sumNCNS,d.sumNCNE,d.sumNCNA];
  const colorsCrit=['rgba(35,64,143,.8)','rgba(30,156,75,.85)','rgba(211,47,47,.85)','rgba(90,24,154,.75)','rgba(56,61,65,.7)'];
  if(valsCrit.some(function(v){return v>0;})){
    new Chart(document.getElementById('chartCriteresBar'),{type:'bar',data:{
      labels:labsCrit,
      datasets:[{label:'Nombre de criteres',data:valsCrit,backgroundColor:colorsCrit,borderRadius:6,borderWidth:0}]
    },options:{responsive:true,maintainAspectRatio:false,animation:{onComplete:function(){
      const chart=this; const ctx=chart.ctx; const meta=chart.getDatasetMeta(0);
      meta.data.forEach(function(bar,i){
        const val=valsCrit[i]; if(!val) return;
        const barH=bar.base-bar.y;
        ctx.save(); ctx.textAlign='center'; ctx.textBaseline='middle'; ctx.font='bold 10px Candara,Arial,sans-serif';
        if(barH>22){ ctx.fillStyle='#fff'; ctx.fillText(val, bar.x, bar.y+barH*0.5); }
        else { ctx.fillStyle='#2C3E50'; ctx.fillText(val, bar.x, bar.y-7); }
        ctx.restore();
      });
    }},plugins:{legend:{display:false},tooltip:{callbacks:{label:function(c){return ' '+c.parsed.y+' critere(s)';}}},},
      scales:{y:{beginAtZero:true,ticks:{font:{size:9}}},x:{ticks:{font:{size:8},maxRotation:0}}}}});
  }

  // 7. Nouveaux blocs : NCE/NCS/NCNS/NCNE/NCNA - Camembert en % avec nb + %
  destroyChart('chartCriteresPie');
  const totalCrit=valsCrit.reduce(function(a,b){return a+b;},0);
  if(totalCrit>0){
    const labelsCritPie=labsCrit.map(function(l,i){
      const pct=Math.round(valsCrit[i]/totalCrit*100); return l+' - '+valsCrit[i]+' ('+pct+'%)';
    });
    new Chart(document.getElementById('chartCriteresPie'),{type:'doughnut',data:{
      labels:labelsCritPie,
      datasets:[{data:valsCrit,backgroundColor:colorsCrit,borderColor:'#fff',borderWidth:3}]
    },options:{responsive:true,maintainAspectRatio:false,cutout:'42%',
      plugins:{legend:{position:'bottom',labels:{font:{size:8},boxWidth:9,padding:5}},
      tooltip:{callbacks:{label:function(c){const pct=Math.round(c.parsed/totalCrit*100);return ' '+c.parsed+' critere(s) ('+pct+'%)';}}}}}});
  }

  // Insights (toujours mis a jour)
  if(d.byOrga.length){
    const best=d.byOrga[0], worst=d.byOrga[d.byOrga.length-1];
    $('#bestOrga').html('<strong style="font-size:.9rem">'+esc(best.nomorga)+'</strong><br>'
      +'Taux conf. : <strong style="color:#1E9C4B">'+best.taux_moy+'%</strong><br>'
      +'<span class="text-muted" style="font-size:.75rem">NCS:'+best.sumNCS+' NCNS:'+best.sumNCNS+' sur '+best.nb+' rapport(s)</span>');
    $('#worstOrga').html('<strong style="font-size:.9rem">'+esc(worst.nomorga)+'</strong><br>'
      +'Taux conf. : <strong style="color:#D32F2F">'+worst.taux_moy+'%</strong><br>'
      +'<span class="text-muted" style="font-size:.75rem">NCS:'+worst.sumNCS+' NCNS:'+worst.sumNCNS+' sur '+worst.nb+' rapport(s)</span>');
  }
  const ql=d.avgTC>=80?'Excellent':d.avgTC>=60?'Satisfaisant':d.avgTC>=40?'A ameliorer':'Insuffisant';
  const qc=d.avgTC>=80?'#1E9C4B':d.avgTC>=60?'#23408F':d.avgTC>=40?'#b58a00':'#D32F2F';
  $('#indiceGlobal').html('Niveau : <strong style="color:'+qc+'">'+ql+'</strong><br>'
    +'Taux conf. moy. : <strong>'+d.avgTC+'%</strong><br>'
    +'NCR total : <strong>'+d.sumNCR+'</strong> | NCS : <strong style="color:#1E9C4B">'+d.sumNCS+'</strong> | NCNS : <strong style="color:#D32F2F">'+d.sumNCNS+'</strong><br>'
    +'<span class="text-muted" style="font-size:.74rem">Base sur '+d.n+' rapport(s) avec criteres</span>');
}

function destroyChart(id){ const el=document.getElementById(id); if(!el) return; const c=Chart.getChart(el); if(c) c.destroy(); }

/* ===== FILTRES DASHBOARD ===== */
function getFiltered_R(){
  const fA=$('#df_audit').val(), fO=$('#df_orga').val(), fY=$('#df_annee').val(), fM=$('#df_mois').val(), fT=$('#df_type').val();
  return ALL_R.filter(function(r){
    if(fA && String(r.idaudit)!==String(fA)) return false;
    if(fO && r.nomorga!==fO) return false;
    const yr=(r.date_realisation||'').substring(0,4), mo=(r.date_realisation||'').substring(5,7);
    if(fY && yr!==fY) return false;
    if(fM && mo!==fM) return false;
    if(fT && r.type_activite!==fT) return false;
    return true;
  });
}
$('#df_audit,#df_orga,#df_annee,#df_type').select2({theme:'bootstrap-5',width:'100%',allowClear:true,placeholder:'Tous'});
$('#df_audit,#df_orga,#df_annee,#df_mois,#df_type').on('change',function(){ renderDash(getFiltered_R()); });
$('#btnResetDash').on('click',function(){ $('#df_audit,#df_orga,#df_annee,#df_type').val('').trigger('change'); $('#df_mois').val('').trigger('change'); renderDash(ALL_R); });

function fillDashFilters(){
  const sA={},sO={},sY={};
  let oA='<option value="">Tous</option>',oO='<option value="">Tous</option>',oY='<option value="">Toutes</option>';
  ALL_R.forEach(function(r){
    if(!sA[r.idaudit]){sA[r.idaudit]=1;oA+='<option value="'+r.idaudit+'">'+esc(r.num_audit||r.idaudit)+'</option>';}
    if(!sO[r.nomorga]){sO[r.nomorga]=1;oO+='<option value="'+esc(r.nomorga)+'">'+esc(r.nomorga)+'</option>';}
    const yr=(r.date_realisation||'').substring(0,4);
    if(yr&&yr>='2020'&&!sY[yr]){sY[yr]=1;oY+='<option value="'+yr+'">'+yr+'</option>';}
  });
  $('#df_audit').html(oA).trigger('change.select2');
  $('#df_orga').html(oO).trigger('change.select2');
  $('#df_annee').html(oY).trigger('change.select2');
}

/* ===== STATS CHARGEMENT ===== */
function loadStats(){
  apiPost({action:'stats'}).done(function(res){
    if(!res.success) return;
    ALL_R = res.allR || [];
    // Mettre a jour les KPI principaux depuis les stats serveur
    const s=res.stats||{};
    $('#k_total').text(s.total||0);    // Tous rapports joints
    const analyses=res.allR?res.allR.length:0;
    $('#k_analyses_lbl').text(analyses>0?'dont '+analyses+' analyses':'Aucun critere saisi');
    if(s.avg_tc!==undefined&&s.avg_tc!==null){ $('#k_tc').text(s.avg_tc+'%'); }
    if(s.avg_tnc!==undefined&&s.avg_tnc!==null){ $('#k_tnc').text(s.avg_tnc+'%'); }
    if(s.sumNCR){ $('#k_ncr').text(s.sumNCR); }
    if(s.sumNCS){ $('#k_ncs').text(s.sumNCS); }
    if(s.sumNCNS){ $('#k_ncns').text(s.sumNCNS); }
    fillDashFilters();
    if(dashVisible) renderDash(ALL_R);
  });
}

/* ===== FILTRES TABLEAU ===== */
$('#fOrga').select2({theme:'bootstrap-5',width:'100%',allowClear:true,placeholder:'Tous les operateurs'});
$('#fStatut').select2({theme:'bootstrap-5',width:'100%',allowClear:true,placeholder:'Tous'});
function fillTableFilters(){
  const seen={}, cur=$('#fOrga').val();
  let opts='<option value="">Tous les operateurs</option>';
  ALL.forEach(function(a){ if(!seen[a.nomorga]){seen[a.nomorga]=1;opts+='<option value="'+esc(a.nomorga)+'">'+esc(a.nomorga)+'</option>';} });
  $('#fOrga').html(opts); if(cur) $('#fOrga').val(cur); $('#fOrga').trigger('change.select2');
}
function getFiltered(){
  const org=$('#fOrga').val(),st=$('#fStatut').val(),et=$('#fEtat').val(),q=$('#fSearch').val().toLowerCase().trim();
  return ALL.filter(function(a){
    if(org&&a.nomorga!==org) return false;
    if(st&&String(a.statut)!==st) return false;
    const has=a.rapport_audit&&String(a.rapport_audit).trim();
    if(et==='1'&&!has) return false; if(et==='0'&&has) return false;
    if(q&&!((a.num_audit||'').toLowerCase().includes(q)||(a.nomorga||'').toLowerCase().includes(q)||(a.ra_nom||'').toLowerCase().includes(q))) return false;
    return true;
  });
}
$('#fOrga,#fStatut,#fEtat').on('change',render); $('#fSearch').on('input',render);
$('#btnReset').on('click',function(){ $('#fOrga,#fStatut').val('').trigger('change'); $('#fEtat').val(''); $('#fSearch').val(''); render(); });

/* ===== RENDU TABLEAU ===== */
function tcBadge(tc){
  if(tc===null||tc===''||tc===undefined) return '<span class="text-muted small">-</span>';
  const v=parseFloat(tc);
  const c=v>=80?'#1E9C4B':v>=60?'#23408F':v>=40?'#b58a00':'#D32F2F';
  const bg=v>=80?'#d1fae5':v>=60?'#e8f0fe':v>=40?'#fef3c7':'#fee2e2';
  return '<span style="background:'+bg+';color:'+c+';border-radius:20px;padding:2px 8px;font-size:.76rem;font-weight:700">'+v+'%</span>';
}
function rapTag(a){ const has=a.rapport_audit&&String(a.rapport_audit).trim();
  return has?'<span class="tag-ok"><i class="bi bi-file-earmark-check"></i>Joint</span>'+(a.date_delivrance_rapport?'<div class="text-muted small mt-1">'+fmtDate(a.date_delivrance_rapport)+'</div>':'')
            :'<span class="tag-non"><i class="bi bi-hourglass-split"></i>En attente</span>'; }
function actionsHtml(a){
  const has=a.rapport_audit&&String(a.rapport_audit).trim();
  const estRA=String(a.est_ra)==='1', peutJoindre=IS_CI||estRA;
  let h='';
  if(has){
    const url=AGAI_BASE+'/api/rapports?serve=1&idaudit='+esc(a.idaudit);
    h+='<a href="javascript:void(0)" class="btn-voir-rap me-1 btn-pdf-rap" data-audit="'+esc(a.idaudit)+'" data-num="'+esc(a.num_audit)+'"><i class="bi bi-eye"></i></a>';
    h+='<a href="'+url+'&dl=1" class="btn-voir-rap me-1" title="Telecharger"><i class="bi bi-download"></i></a>';
  }
  if(peutJoindre){
    h+=has
      ?'<button class="btn-remplacer-rap btn-upload-rap" data-id="'+esc(a.idaudit)+'" data-num="'+esc(a.num_audit)+'" data-orga="'+esc(a.nomorga)+'" title="Remplacer"><i class="bi bi-arrow-repeat"></i>Remplacer</button>'
      :'<button class="btn-joindre-rap btn-upload-rap" data-id="'+esc(a.idaudit)+'" data-num="'+esc(a.num_audit)+'" data-orga="'+esc(a.nomorga)+'" title="Joindre"><i class="bi bi-file-earmark-plus"></i>Joindre</button>';
  } else if(!has) h+='<span class="tag-ra"><i class="bi bi-lock"></i>RA uniquement</span>';
  return '<div style="text-align:right;white-space:nowrap;display:flex;align-items:center;gap:3px;justify-content:flex-end">'+h+'</div>';
}
function render(){
  const list=getFiltered();
  $('#resCount').html('<i class="bi bi-file-earmark-text me-1"></i>'+list.length+' audit(s)');
  if(!list.length){
    $('#tbody').html('<tr><td colspan="12" class="empty"><i class="bi bi-inbox me-2"></i>Aucun audit eligible.'+(ALL.length?'<br><small class="text-muted">Modifiez les filtres.</small>':'<br><small class="text-muted">Les audits apparaissent apres que la lettre de notification ait ete jointe.</small>')+'</td></tr>');
    return;
  }
  $('#tbody').html(list.map(function(a){
    const st=STATUT[a.statut]||{t:'-',c:''};
    const del=a.delai_execution?('<span style="font-size:.75rem;color:#7b8aa0">'+esc(a.delai_execution)+'</span>'):'<span class="text-muted">-</span>';
    const ncrCell=a.ncr>0?('<span style="font-weight:700;color:#23408F">'+a.ncr+'</span>'):'<span class="text-muted">-</span>';
    return '<tr>'
      +'<td><b style="color:#23408F;font-size:.87rem">'+esc(a.num_audit||'')+'</b></td>'
      +'<td style="font-size:.82rem">'+esc(TYPES[a.type_activite]||'')+'</td>'
      +'<td style="font-weight:600;font-size:.82rem">'+esc(a.nomorga||'-')+'</td>'
      +'<td style="font-size:.82rem;color:#D32F2F;font-weight:600">'+esc(a.ra_nom||'-')+'</td>'
      +'<td style="font-size:.82rem">'+fmtDate(a.date_previsionnelle)+'</td>'
      +'<td style="font-size:.82rem">'+fmtDate(a.date_realisation)+'</td>'
      +'<td>'+del+'</td>'
      +'<td><span class="s-badge '+(st.c||'')+'">'+esc(st.t)+'</span></td>'
      +'<td style="text-align:center">'+ncrCell+'</td>'
      +'<td style="text-align:center">'+tcBadge(a.taux_conformite)+'</td>'
      +'<td>'+rapTag(a)+'</td>'
      +'<td>'+actionsHtml(a)+'</td>'
      +'</tr>';
  }).join(''));
}
function loadList(){
  apiPost({action:'list'}).done(function(res){
    if(!res.success){ $('#tbody').html('<tr><td colspan="12" class="empty">'+esc(res.message||'Erreur')+'</td></tr>'); return; }
    ALL=res.data||[]; fillTableFilters(); render();
  }).fail(function(){ $('#tbody').html('<tr><td colspan="12" class="empty">Echec.</td></tr>'); });
}

/* ===== CALCUL CRITERES DANS MODALE ===== */
function recalcCriteres(){
  const nce=parseInt($('#c_nce').val())||0, ncs=parseInt($('#c_ncs').val())||0;
  const ncns=parseInt($('#c_ncns').val())||0, ncne=parseInt($('#c_ncne').val())||0, ncna=parseInt($('#c_ncna').val())||0;
  const ncr=nce+ncs+ncns+ncne+ncna;
  $('#ncr_val').text(ncr);
  const base=ncs+ncns;
  if(base>0){
    const tc=Math.round(ncs/base*100*10)/10, tnc=Math.round(ncns/base*100*10)/10;
    $('#tc_val').text(tc+'%'); $('#tnc_val').text(tnc+'%');
  } else { $('#tc_val').text('- %'); $('#tnc_val').text('- %'); }
}
$(document).on('input','.crit-input',recalcCriteres);

/* ===== MODALE UPLOAD ===== */
$(document).on('click','.btn-upload-rap',function(){
  const id=$(this).data('id'), num=$(this).data('num'), orga=$(this).data('orga');
  const remplace=$(this).hasClass('btn-remplacer-rap');
  $('#rapModalTitle').html((remplace?'<i class="bi bi-arrow-repeat me-1"></i>Remplacer':'<i class="bi bi-file-earmark-plus me-1"></i>Joindre')+' le rapport');
  $('#rap_idaudit').val(id); $('#rap_audit_info').text('Audit : '+num); $('#rap_orga_info').text('Operateur : '+orga);
  $('#rap_fichier').val(''); $('#rap_date_real').val(new Date().toISOString().substring(0,10));
  // Recuperer criteres existants si remplace
  if(remplace){
    const a=ALL.find(function(x){ return String(x.idaudit)===String(id); });
    if(a){ $('#c_nce').val(a.nce||0); $('#c_ncs').val(a.ncs||0); $('#c_ncns').val(a.ncns||0); $('#c_ncne').val(a.ncne||0); $('#c_ncna').val(a.ncna||0); }
  } else { ['c_nce','c_ncs','c_ncns','c_ncne','c_ncna'].forEach(function(id){$('#'+id).val(0);}); }
  recalcCriteres();
  new bootstrap.Modal('#rapModal').show();
});

$('#rapForm').on('submit',function(e){
  e.preventDefault();
  const idaudit=$('#rap_idaudit').val(), dateReal=$('#rap_date_real').val();
  if(!$('#rap_fichier')[0].files.length){ Swal.fire({icon:'warning',title:'Fichier requis',confirmButtonColor:'#1E9C4B'}); return; }
  if(!dateReal){ Swal.fire({icon:'warning',title:'Date requise',confirmButtonColor:'#1E9C4B'}); return; }
  const btn=$('#rap_submit'), btnHtml=btn.html();
  btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm me-1"></span>Enregistrement...');
  const fd=new FormData();
  fd.append('csrf_token',CSRF); fd.append('action','upload'); fd.append('idaudit',idaudit);
  fd.append('date_realisation',dateReal); fd.append('fichier_rapport',$('#rap_fichier')[0].files[0]);
  fd.append('nce',$('#c_nce').val()||0); fd.append('ncs',$('#c_ncs').val()||0);
  fd.append('ncns',$('#c_ncns').val()||0); fd.append('ncne',$('#c_ncne').val()||0); fd.append('ncna',$('#c_ncna').val()||0);
  $.ajax({url:API,type:'POST',data:fd,processData:false,contentType:false,dataType:'json'})
  .done(function(res){
    btn.prop('disabled',false).html(btnHtml);
    if(res.success){
      bootstrap.Modal.getInstance(document.getElementById('rapModal')).hide();
      Swal.fire({icon:'success',title:'Rapport enregistre',
        html:'Statut : <strong>Effectue</strong><br>NCR : <strong>'+res.ncr+'</strong>'
          +(res.taux_conformite!==null?'<br>Taux conformite : <strong>'+res.taux_conformite+'%</strong>':''),
        confirmButtonColor:'#1E9C4B',timer:3500,timerProgressBar:true});
      loadList(); loadStats();
    } else { Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#1E9C4B'}); }
  })
  .fail(function(jqXHR){ btn.prop('disabled',false).html(btnHtml); Swal.fire({icon:'error',title:'Erreur',text:(jqXHR.responseJSON?.message||'Echec.'),confirmButtonColor:'#1E9C4B'}); });
});

/* ===== PDF ===== */
$(document).on('click','.btn-pdf-rap',function(){
  const idaudit=$(this).data('audit'), num=$(this).data('num')||'';
  const url=AGAI_BASE+'/api/rapports?serve=1&idaudit='+idaudit;
  $('#pdfTitle').text('Rapport - '+num); $('#pdfFrame').attr('src',url);
  $('#pdfDl').attr('href',url+'&dl=1');
  $('#pdfPrint').off('click').on('click',function(){ document.getElementById('pdfFrame').contentWindow.print(); });
  new bootstrap.Modal('#pdfModal').show();
});
$('#pdfModal').on('hidden.bs.modal',function(){ $('#pdfFrame').attr('src',''); });

/* ===== DEMARRAGE ===== */
loadList(); loadStats();
(function(){ try{ if(localStorage.getItem('agai_dash_rapports')==='1') setDash(true); }catch(e){} })();
</script>