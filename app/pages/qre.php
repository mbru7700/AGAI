<?php
/**
 * Page : QRE - Questionnaire de Retour d'Experience
 * IX-GEN-R3-F-I-011 - Fevrier 2024 Version 02
 * Dashboard analytique + CRUD complet
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardPage('qre');
$csrf      = Security::generateCSRF();
$role      = Rbac::role();
$isCI      = in_array($role, ['admin','chef_inspecteur'], true);
$pageTitle = 'Formulaire QRE';
$active    = 'qre';
$pageIcon  = 'bi-ui-checks-grid';
$banierUrl = ASSETS_URL . '/images/banierenteanac.png';
require_once INCLUDES_PATH . '/layout_head.php';
?>
<style>
/* Dashboard */
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:16px;}
.kpi-card{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:14px 16px;box-shadow:0 1px 2px rgba(16,30,54,.04);text-align:center;}
.kpi-num{font-size:1.9rem;font-weight:800;line-height:1;} .kpi-lbl{font-size:.76rem;color:#7b8aa0;margin-top:4px;}
.kpi-sat .kpi-num{color:#1E9C4B;} .kpi-score .kpi-num{color:#23408F;}
.kpi-total .kpi-num{color:#2C3E50;} .kpi-tb .kpi-num{color:#1E9C4B;} .kpi-b .kpi-num{color:#23408F;}
.kpi-m .kpi-num{color:#b58a00;} .kpi-tm .kpi-num{color:#D32F2F;}
.chart-box{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:16px;box-shadow:0 1px 2px rgba(16,30,54,.04);}
.chart-title{font-size:.82rem;font-weight:700;color:#23408F;text-transform:uppercase;letter-spacing:.04em;margin-bottom:10px;}
/* Barre satisfaction */
.sat-bar{display:flex;height:16px;border-radius:8px;overflow:hidden;gap:2px;}
.sat-tb{background:#1E9C4B;} .sat-b{background:#23408F;} .sat-m{background:#F3C300;} .sat-tm{background:#D32F2F;}
/* Podium */
.champ-row{display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid #f1f4f9;}
.champ-row:last-child{border-bottom:none;}
.champ-bar{flex:1;height:8px;border-radius:4px;background:#eef1f6;overflow:hidden;}
.champ-fill{height:100%;border-radius:4px;background:linear-gradient(90deg,#1E9C4B,#23408F);}
/* Filtre */
.filter-bar{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:13px 16px;box-shadow:0 1px 2px rgba(16,30,54,.04);}
/* Tableau */
table.tbl{width:100%;border-collapse:separate;border-spacing:0;}
table.tbl thead th{background:#23408F;color:#fff;font-size:.71rem;text-transform:uppercase;letter-spacing:.04em;padding:10px 12px;border:none;font-weight:600;white-space:nowrap;}
table.tbl tbody td{padding:9px 12px;border-bottom:1px solid #f1f4f9;vertical-align:middle;font-size:.85rem;}
table.tbl tbody tr:hover{background:#fafcff;}
.empty{padding:36px;text-align:center;color:#9aa7bd;}
.s-badge{display:inline-block;padding:.2rem .55rem;border-radius:20px;font-size:.72rem;font-weight:700;}
.s1{background:#e8f0fe;color:#23408F;} .s2{background:#fff3cd;color:#856404;} .s3{background:#d1e7dd;color:#0a5c36;}
.s4{background:#f8d7da;color:#842029;} .s6{background:#e2e3e5;color:#383d41;} .s7{background:#cfe2ff;color:#084298;}
.tag-ok{background:#d1e7dd;color:#0a5c36;border-radius:8px;padding:3px 9px;font-size:.76rem;font-weight:700;display:inline-flex;align-items:center;gap:4px;}
.tag-non{background:#fff3cd;color:#856404;border-radius:8px;padding:3px 9px;font-size:.76rem;font-weight:600;display:inline-flex;align-items:center;gap:4px;}
/* Boutons */
.btn-qre-fill{background:linear-gradient(135deg,#23408F,#1b3576);color:#fff;border:none;border-radius:8px;padding:5px 12px;font-size:.79rem;font-weight:700;display:inline-flex;align-items:center;gap:5px;cursor:pointer;transition:all .15s;}
.btn-qre-fill:hover{background:linear-gradient(135deg,#1b3576,#0f2357);transform:translateY(-1px);}
.btn-qre-view{background:#eef1f6;color:#23408F;border:1px solid #d0d7e3;border-radius:8px;padding:5px 10px;font-size:.78rem;font-weight:600;display:inline-flex;align-items:center;gap:4px;cursor:pointer;}
.btn-qre-view:hover{background:#e0e6f5;}
/* Notes */
.note-tb{background:#d1fae5;color:#065f46;border-radius:4px;padding:2px 7px;font-size:.75rem;font-weight:700;}
.note-b{background:#dbeafe;color:#1e40af;border-radius:4px;padding:2px 7px;font-size:.75rem;font-weight:700;}
.note-m{background:#fef3c7;color:#92400e;border-radius:4px;padding:2px 7px;font-size:.75rem;font-weight:700;}
.note-tm{background:#fee2e2;color:#991b1b;border-radius:4px;padding:2px 7px;font-size:.75rem;font-weight:700;}
/* Formulaire QRE */
.qre-wrap{max-width:820px;margin:0 auto;font-family:'Candara',Arial,sans-serif;color:#2C3E50;}
.qre-ref{text-align:right;font-size:.7rem;color:#666;margin-bottom:4px;}
.qre-title{text-align:center;font-size:1.15rem;font-weight:800;text-transform:uppercase;color:#23408F;border:2px solid #23408F;padding:7px 14px;letter-spacing:.04em;margin:8px 0;}
.qre-intro{background:#f0f4ff;border-left:4px solid #23408F;padding:8px 12px;font-size:.82rem;font-style:italic;margin-bottom:10px;border-radius:0 6px 6px 0;}
.qre-note{background:#fffbeb;border:1px solid #F3C300;padding:8px 12px;font-size:.82rem;margin-bottom:12px;border-radius:6px;}
.qre-section{background:#23408F;color:#fff;padding:6px 12px;font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;margin:12px 0 0;border-radius:6px 6px 0 0;}
.qre-table{width:100%;border-collapse:collapse;}
.qre-table tbody tr{border-bottom:1px solid #e2e8f0;}
.qre-table tbody tr:hover{background:#f8fafc;}
.qre-table td{padding:7px 10px;font-size:.84rem;vertical-align:middle;}
.qre-table td.q-cell{border-right:1px solid #e2e8f0;}
.qre-table td.r-cell{width:10%;text-align:center;border-right:1px solid #e2e8f0;padding:5px;}
.qre-table thead th{background:#f0f4ff;color:#23408F;font-size:.74rem;text-align:center;padding:5px 4px;border:1px solid #c5d4f5;font-weight:700;}
.qre-table thead th.q-head{text-align:left;padding-left:10px;}
.radio-box{display:flex;justify-content:center;align-items:center;}
.radio-box input[type=radio]{width:18px;height:18px;cursor:pointer;accent-color:#23408F;}
/* Choix du mode de soumission QRE */
.qre-choix-card{border:2px solid #eef1f6;border-radius:14px;padding:20px 14px;text-align:center;cursor:pointer;transition:all .18s;height:100%;background:#fff;}
.qre-choix-card:hover{border-color:#23408F;box-shadow:0 8px 20px rgba(35,64,143,.16);transform:translateY(-3px);}
.qre-choix-ic{width:54px;height:54px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.5rem;margin:0 auto 12px;}
.qre-choix-title{font-weight:800;color:#2C3E50;font-size:.95rem;margin-bottom:5px;}
.qre-choix-desc{font-size:.78rem;color:#7b8aa0;line-height:1.4;}
/* Impression */
@media print{
  .no-print{display:none!important;}
  .modal-dialog{max-width:100%!important;}
  .qre-wrap{max-width:100%;}
}
</style>

<div class="page-head">
  <div>
    <h1><i class="bi bi-ui-checks-grid me-2" style="color:var(--anac-primary)"></i>Questionnaire de Retour d'Experience (QRE)</h1>
    <div class="sub">IX-GEN-R3-F-I-011 - Fevrier 2024 Version 02 | Tableau de bord analytique et CRUD complet</div>
  </div>
</div>

<!-- ===================== DASHBOARD ANALYTIQUE ===================== -->
<div id="dashPanel">

  <!-- Filtres dashboard -->
  <div class="filter-bar mb-3" id="dashFilters">
    <div class="d-flex align-items-center gap-2 mb-2">
      <i class="bi bi-funnel-fill" style="color:#23408F"></i>
      <span style="font-size:.8rem;font-weight:700;color:#23408F;text-transform:uppercase;letter-spacing:.04em">Filtres du tableau de bord</span>
      <button class="btn btn-xs btn-outline-secondary ms-auto" id="btnResetDash" style="font-size:.74rem;padding:2px 8px"><i class="bi bi-x-lg me-1"></i>Reset</button>
    </div>
    <div class="row g-2">
      <div class="col-md-3">
        <label class="form-label small fw-bold mb-1" style="color:#5b6b85;font-size:.72rem;text-transform:uppercase">N Audit</label>
        <select id="df_audit" style="width:100%"><option value="">Tous les audits</option></select>
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-bold mb-1" style="color:#5b6b85;font-size:.72rem;text-transform:uppercase">Operateur</label>
        <select id="df_orga" style="width:100%"><option value="">Tous</option></select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-bold mb-1" style="color:#5b6b85;font-size:.72rem;text-transform:uppercase">Annee</label>
        <select id="df_annee" style="width:100%"><option value="">Toutes</option></select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-bold mb-1" style="color:#5b6b85;font-size:.72rem;text-transform:uppercase">Mois</label>
        <select id="df_mois" class="form-select form-select-sm">
          <option value="">Tous</option>
          <?php for($m=1;$m<=12;$m++): ?><option value="<?php echo str_pad($m,2,'0',STR_PAD_LEFT); ?>"><?php echo DateTime::createFromFormat('!m',$m)->format('F'); ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-bold mb-1" style="color:#5b6b85;font-size:.72rem;text-transform:uppercase">Nature</label>
        <select id="df_type" style="width:100%">
          <option value="">Toutes</option>
          <option value="audit">Audit</option>
          <option value="inspection_programmee">Insp. prog.</option>
          <option value="inspection_non_programmee">Insp. non prog.</option>
          <option value="demonstration">Demo</option>
          <option value="test">Test</option>
          <option value="investigation">Investigation</option>
        </select>
      </div>
    </div>
  </div>
  <!-- KPI principaux avec tooltips -->
  <div class="kpi-grid mb-2" id="kpiRow">
    <div class="kpi-card kpi-total" title="Nombre total de QRE recus sur tous les audits eligibles">
      <div class="kpi-num" id="k_total">-</div><div class="kpi-lbl">QRE recus <i class="bi bi-info-circle" style="font-size:.7rem;color:#9aa7bd"></i></div>
    </div>
    <div class="kpi-card kpi-sat" title="Taux de satisfaction = (Tres Bonnes + Bonnes) / Total appreciations x 100. Indique le niveau general de satisfaction de l'operateur.">
      <div class="kpi-num" id="k_sat">- %</div><div class="kpi-lbl">Taux satisfaction <i class="bi bi-info-circle" style="font-size:.7rem;color:#9aa7bd"></i></div>
    </div>
    <div class="kpi-card kpi-score" title="Score moyen sur 4 : TB=4pts, B=3pts, M=2pts, TM=1pt. Score calcule sur toutes les questions et tous les QRE recus.">
      <div class="kpi-num" id="k_score">- / 4</div><div class="kpi-lbl">Score moyen /4 <i class="bi bi-info-circle" style="font-size:.7rem;color:#9aa7bd"></i></div>
    </div>
    <div class="kpi-card kpi-tb" title="Tres Bonne : pourcentage de reponses Tres Bonne sur l'ensemble des appreciations recues.">
      <div class="kpi-num" id="k_tb">-</div><div class="kpi-lbl">Tres Bonne (%) <i class="bi bi-info-circle" style="font-size:.7rem;color:#9aa7bd"></i></div>
    </div>
    <div class="kpi-card kpi-b" title="Bonne : pourcentage de reponses Bonne sur l'ensemble des appreciations recues.">
      <div class="kpi-num" id="k_b">-</div><div class="kpi-lbl">Bonne (%) <i class="bi bi-info-circle" style="font-size:.7rem;color:#9aa7bd"></i></div>
    </div>
    <div class="kpi-card kpi-m" title="Mauvaise : pourcentage de reponses Mauvaise. A surveiller si > 15%.">
      <div class="kpi-num" id="k_m">-</div><div class="kpi-lbl">Mauvaise (%) <i class="bi bi-info-circle" style="font-size:.7rem;color:#9aa7bd"></i></div>
    </div>
    <div class="kpi-card kpi-tm" title="Tres Mauvaise : pourcentage de reponses Tres Mauvaise. Necessite une action corrective immediate si > 5%.">
      <div class="kpi-num" id="k_tm">-</div><div class="kpi-lbl">Tres Mauvaise (%) <i class="bi bi-info-circle" style="font-size:.7rem;color:#9aa7bd"></i></div>
    </div>
  </div>

  <!-- Courbe taux de satisfaction par annee -->
  <div class="chart-box mb-3">
    <div class="chart-title"><i class="bi bi-graph-up me-1" style="color:#1E9C4B"></i>Tendance du taux de satisfaction par annee</div>
    <div style="height:180px"><canvas id="chartSatAnnee"></canvas></div>
  </div>

  <!-- Barre de satisfaction globale -->
  <div class="chart-box mb-3">
    <div class="chart-title"><i class="bi bi-bar-chart-fill me-1"></i>Repartition globale des appreciations</div>
    <div class="sat-bar mb-2" id="satBar" style="height:22px;border-radius:10px">
      <div class="sat-tb" id="bar_tb" style="width:25%"></div>
      <div class="sat-b"  id="bar_b"  style="width:25%"></div>
      <div class="sat-m"  id="bar_m"  style="width:25%"></div>
      <div class="sat-tm" id="bar_tm" style="width:25%"></div>
    </div>
    <div class="d-flex gap-3 flex-wrap" style="font-size:.78rem">
      <span><span style="color:#1E9C4B;font-weight:700">&#9632;</span> Tres Bonne (<span id="lg_tb">-</span>%)</span>
      <span><span style="color:#23408F;font-weight:700">&#9632;</span> Bonne (<span id="lg_b">-</span>%)</span>
      <span><span style="color:#F3C300;font-weight:700">&#9632;</span> Mauvaise (<span id="lg_m">-</span>%)</span>
      <span><span style="color:#D32F2F;font-weight:700">&#9632;</span> Tres Mauvaise (<span id="lg_tm">-</span>%)</span>
    </div>
  </div>

  <!-- Graphiques : par annee + par type + par champ -->
  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <div class="chart-box" style="height:270px">
        <div class="chart-title"><i class="bi bi-calendar3 me-1"></i>Evolution annuelle</div>
        <canvas id="chartAnnee"></canvas>
      </div>
    </div>
    <div class="col-md-4">
      <div class="chart-box" style="height:270px">
        <div class="chart-title"><i class="bi bi-pie-chart me-1"></i>Par type d'audit</div>
        <canvas id="chartType"></canvas>
      </div>
    </div>
    <div class="col-md-4">
      <div class="chart-box" style="height:270px;overflow-y:auto">
        <div class="chart-title"><i class="bi bi-trophy me-1"></i>Score par axe d'evaluation</div>
        <div id="champChart"></div>
      </div>
    </div>
  </div>

  <!-- Alertes / Insights -->
  <div class="row g-3 mb-3" id="insightRow">
    <div class="col-md-4">
      <div class="chart-box" style="border-left:4px solid #1E9C4B">
        <div class="chart-title text-success"><i class="bi bi-star-fill me-1"></i>Point fort</div>
        <div id="pointFort" class="text-muted small">-</div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="chart-box" style="border-left:4px solid #D32F2F">
        <div class="chart-title text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Point d'amelioration</div>
        <div id="pointFaible" class="text-muted small">-</div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="chart-box" style="border-left:4px solid #23408F">
        <div class="chart-title"><i class="bi bi-lightbulb me-1"></i>Indice de qualite</div>
        <div id="indiceQualite" class="text-muted small">-</div>
      </div>
    </div>
  </div>
</div>

<!-- Toggle stats -->
<div class="d-flex justify-content-between align-items-center mb-2">
  <button class="btn btn-sm btn-outline-secondary" id="btnToggleDash">
    <i class="bi bi-graph-up me-1"></i><span id="dashLbl">Masquer le tableau de bord</span>
  </button>
  <span class="small text-muted" id="resCount"></span>
</div>

<!-- Filtres -->
<div class="filter-bar mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label small fw-bold mb-1" style="color:#5b6b85;text-transform:uppercase;font-size:.72rem">N Audit</label>
      <select id="fAudit" style="width:100%"><option value="">Tous les audits</option></select>
    </div>
    <div class="col-md-3">
      <label class="form-label small fw-bold mb-1" style="color:#5b6b85;text-transform:uppercase;font-size:.72rem">Operateur</label>
      <select id="fOrga" style="width:100%"><option value="">Tous les operateurs</option></select>
    </div>
    <div class="col-md-2">
      <label class="form-label small fw-bold mb-1" style="color:#5b6b85;text-transform:uppercase;font-size:.72rem">Statut QRE</label>
      <select id="fQre" class="form-select form-select-sm">
        <option value="">Tous</option>
        <option value="1">QRE rempli</option>
        <option value="0">Non soumis</option>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small fw-bold mb-1" style="color:#5b6b85;text-transform:uppercase;font-size:.72rem">Nature</label>
      <select id="fType" style="width:100%">
        <option value="">Toutes</option>
        <option value="audit">Audit</option>
        <option value="inspection_programmee">Insp. prog.</option>
        <option value="inspection_non_programmee">Insp. non prog.</option>
        <option value="demonstration">Demo</option>
        <option value="test">Test</option>
        <option value="investigation">Investigation</option>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small fw-bold mb-1" style="visibility:hidden">-</label>
      <button class="btn btn-sm btn-outline-secondary w-100" id="btnReset"><i class="bi bi-x-lg me-1"></i>Reset</button>
    </div>
  </div>
</div>

<!-- Tableau -->
<div style="background:#fff;border:1px solid #eef1f6;border-radius:14px;overflow:hidden;box-shadow:0 1px 2px rgba(16,30,54,.04)">
  <table class="tbl">
    <thead>
      <tr>
        <th>N Audit</th><th>Nature</th><th>Operateur</th><th>Site inspection</th>
        <th>Date prev.</th><th>Statut</th><th>QRE</th>
        <th style="text-align:right" class="no-print">Actions</th>
      </tr>
    </thead>
    <tbody id="tbody">
      <tr><td colspan="8" class="empty"><span class="spinner-border spinner-border-sm me-2"></span>Chargement...</td></tr>
    </tbody>
  </table>
</div>

<!-- ===================== MODALE : Choix du mode de soumission ===================== -->
<div class="modal fade" id="qreChoixModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,#23408F,#1b3576)">
        <h5 class="modal-title text-white"><i class="bi bi-ui-checks-grid me-2" style="color:#F3C300"></i>Questionnaire de Retour d'Experience</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <div class="mb-3 text-center text-muted" style="font-size:.86rem">Comment souhaitez-vous soumettre le QRE pour l'audit <strong id="qc_num" style="color:#23408F"></strong> ?</div>
        <div class="row g-3">
          <div class="col-md-6">
            <div class="qre-choix-card" id="qcSaisie">
              <div class="qre-choix-ic" style="background:#23408F"><i class="bi bi-pencil-square"></i></div>
              <div class="qre-choix-title">Saisir en ligne</div>
              <div class="qre-choix-desc">Remplir le questionnaire directement dans l'application, question par question.</div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="qre-choix-card" id="qcFichier">
              <div class="qre-choix-ic" style="background:#1E9C4B"><i class="bi bi-upload"></i></div>
              <div class="qre-choix-title">Joindre le formulaire</div>
              <div class="qre-choix-desc">Le formulaire a deja ete rempli a la main : televersez le document scanne ou photographie.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ===================== MODALE : Joindre un formulaire deja rempli ===================== -->
<div class="modal fade" id="qreFichierModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="qreFichierForm">
      <div class="modal-header" style="background:linear-gradient(135deg,#1E9C4B,#146633)">
        <h5 class="modal-title text-white"><i class="bi bi-upload me-2"></i>Joindre le formulaire QRE</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-3">
        <input type="hidden" id="qf_idaudit">
        <input type="hidden" id="qf_idorga">
        <div class="mb-2">
          <label class="form-label fw-bold" style="font-size:.81rem">Operateur</label>
          <input type="text" class="form-control form-control-sm" id="qf_nomorga" readonly style="background:#f8fafc;font-weight:700;color:#23408F">
        </div>
        <div class="mb-2">
          <label class="form-label fw-bold" style="font-size:.81rem">Activite(s) auditee(s) <span class="text-danger">*</span></label>
          <textarea class="form-control form-control-sm" id="qf_activites" rows="2" maxlength="500" placeholder="Decrivez les activites auditees..." required></textarea>
        </div>
        <div class="row g-2 mb-2">
          <div class="col-md-6">
            <label class="form-label fw-bold" style="font-size:.81rem">Date</label>
            <input type="date" class="form-control form-control-sm" id="qf_date">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-bold" style="font-size:.81rem">Fichier <span class="text-danger">*</span></label>
            <input type="file" class="form-control form-control-sm" id="qf_fichier" accept=".pdf,.jpg,.jpeg,.png" required>
          </div>
        </div>
        <div class="small text-muted" style="font-size:.74rem"><i class="bi bi-info-circle me-1"></i>Formats acceptes : PDF, JPG, PNG - 10 Mo maximum.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" class="btn" style="background:#1E9C4B;color:#fff" id="qf_submit"><i class="bi bi-upload me-1"></i>Televerser et enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- ===================== MODALE : Formulaire QRE ===================== -->
<div class="modal fade" id="qreModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" style="max-width:870px">
    <form class="modal-content" id="qreForm">
      <div class="modal-header py-2 no-print" style="background:linear-gradient(135deg,#23408F,#1b3576)">
        <h5 class="modal-title text-white"><i class="bi bi-ui-checks-grid me-2" style="color:#F3C300"></i>Questionnaire de Retour d'Experience</h5>
        <div class="ms-auto me-2 d-flex gap-2">
          <button type="button" class="btn btn-sm btn-outline-light" id="btnPrintForm"><i class="bi bi-printer me-1"></i>Imprimer</button>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-3">
        <input type="hidden" id="q_idaudit">
        <input type="hidden" id="q_idorga">

        <div class="qre-wrap" id="qreFormContent">
          <!-- Banniere officielle ANAC -->
          <div class="qre-ref">IX-GEN-R3-F-I-011 Fevrier 2024 Version 02</div>
          <div style="margin:0 0 10px;text-align:center;background:#fff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;padding:4px">
            <img src="<?php echo $banierUrl; ?>" alt="ANAC Gabon" style="max-width:100%;max-height:110px;width:auto;display:block;margin:0 auto;object-fit:contain">
          </div>

          <div class="qre-title">Questionnaire de Retour d'Experience</div>
          <div class="qre-intro">
            <em>(Le questionnaire de retour d'experience a pour objectif de tirer les enseignements positifs et negatifs de la realisation de l'audit. Il vise exclusivement l'amelioration du systeme de supervision par l'ANAC).</em>
          </div>
          <div class="qre-note">Votre organisme a ete audite par les inspecteurs de l'ANAC, nous vous remercions de nous faire part de votre appreciation sur le deroulement de l'activite.</div>

          <!-- Infos generales -->
          <div style="border:1px solid #23408F;border-radius:8px;overflow:hidden;margin-bottom:14px">
            <div style="background:#23408F;color:#fff;padding:6px 12px;font-weight:700;font-size:.8rem;text-transform:uppercase;text-align:center">INFORMATIONS GENERALES SUR L'AUDITE</div>
            <div style="padding:10px 14px">
              <div class="row g-2 mb-2">
                <div class="col-md-6">
                  <label class="form-label fw-bold" style="font-size:.81rem">Operateur <span class="text-danger">*</span></label>
                  <input type="text" class="form-control form-control-sm" id="q_nomorga" readonly style="background:#f8fafc;font-weight:700;color:#23408F">
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold" style="font-size:.81rem">Activite(s) auditee(s) <span class="text-danger">*</span></label>
                  <textarea class="form-control form-control-sm" id="q_activites" rows="2" maxlength="500" placeholder="Decrivez les activites auditees..." required></textarea>
                </div>
              </div>
              <div class="row g-2">
                <div class="col-md-6">
                  <label class="form-label fw-bold" style="font-size:.81rem">Lieu (Site d'inspection)</label>
                  <input type="text" class="form-control form-control-sm" id="q_lieu" readonly style="background:#f8fafc">
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold" style="font-size:.81rem">Date</label>
                  <input type="date" class="form-control form-control-sm" id="q_date">
                </div>
              </div>
            </div>
          </div>

          <div class="text-center mb-2" style="font-style:italic;font-size:.83rem;color:#555">
            <em>Veuillez cocher la case correspondant a votre niveau d'appreciation du deroulement de l'audit.</em>
          </div>

          <!-- SECTION 1 -->
          <div class="qre-section">Preparation de l'Audit</div>
          <table class="qre-table" style="border:1px solid #c5d4f5;border-top:none">
            <thead><tr><th class="q-head" style="width:60%"></th><th>Tres Bonne</th><th>Bonne</th><th>Mauvaise</th><th>Tres Mauvaise</th></tr></thead>
            <tbody>
              <?php foreach ([
                'prep_notification' => "Quelle est votre appreciation des informations fournies par la notification, notamment sur le mandat et les attentes de l'auditeur envers vous ?",
                'prep_plan'         => "Quelle est votre appreciation de la contribution du plan d'audit dans la preparation et le deroulement de l'audit ?",
              ] as $name => $label): ?>
              <tr><td class="q-cell"><?php echo htmlspecialchars($label,ENT_QUOTES,'UTF-8'); ?></td>
              <?php foreach (['TB','B','M','TM'] as $v): ?>
              <td class="r-cell"><div class="radio-box"><input type="radio" name="<?php echo $name; ?>" value="<?php echo $v; ?>" required></div></td>
              <?php endforeach; ?></tr>
              <?php endforeach; ?>
            </tbody>
          </table>

          <!-- SECTION 2 -->
          <div class="qre-section" style="margin-top:12px">Conduite de l'Audit</div>
          <table class="qre-table" style="border:1px solid #c5d4f5;border-top:none">
            <thead><tr><th class="q-head" style="width:60%"></th><th>Tres Bonne</th><th>Bonne</th><th>Mauvaise</th><th>Tres Mauvaise</th></tr></thead>
            <tbody>
              <?php foreach ([
                'cond_ouverture'     => "Quelle est votre appreciation du deroulement de la reunion d'ouverture ?",
                'cond_entretiens'    => "Quelle est votre appreciation de la qualite des entretiens, notamment en ce qui concerne la coherence et la clarte des questions posees ?",
                'cond_procedures'    => "Quelle est votre appreciation de la connaissance de vos procedures par l'inspecteur ?",
                'cond_qualites'      => "Quelle est votre appreciation des qualites generales (professionnalisme, neutralite, impartialite, ecoute, etc) chez l'inspecteur ?",
                'cond_communication' => "Quelle est votre appreciation de la qualite de la communication durant l'audit ?",
                'cond_classification'=> "Quelle est votre appreciation du mode de classification des constats d'audit ?",
                'cond_pertinence'    => "Quelle est votre appreciation de la pertinence des observations relevees ?",
                'cond_cloture'       => "Quelle est votre appreciation du deroulement de la reunion de cloture, notamment la restitution des resultats de l'audit ?",
              ] as $name => $label): ?>
              <tr><td class="q-cell"><?php echo htmlspecialchars($label,ENT_QUOTES,'UTF-8'); ?></td>
              <?php foreach (['TB','B','M','TM'] as $v): ?>
              <td class="r-cell"><div class="radio-box"><input type="radio" name="<?php echo $name; ?>" value="<?php echo $v; ?>" required></div></td>
              <?php endforeach; ?></tr>
              <?php endforeach; ?>
            </tbody>
          </table>

          <!-- Autres appreciations -->
          <div class="qre-section" style="margin-top:12px">Autres Appreciations</div>
          <div style="border:1px solid #c5d4f5;border-top:none;border-radius:0 0 6px 6px;padding:10px">
            <textarea class="form-control form-control-sm" id="q_autres" rows="3" placeholder="Commentaires libres, suggestions d'amelioration..."></textarea>
          </div>

          <!-- Envoi mail -->
          <div class="mt-3 p-3 no-print" style="background:#f0f4ff;border-radius:8px;border:1px solid #c5d4f5">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="q_mail">
              <label class="form-check-label fw-bold" for="q_mail" style="font-size:.85rem">
                <i class="bi bi-envelope me-1"></i>Envoyer par mail a l'ANAC (qmanac@anac-gabon.com)
              </label>
            </div>
          </div>
          <div class="mt-2 text-center" style="font-size:.76rem;color:#555;border-top:1px solid #eee;padding-top:8px">
            <em>Nous vous remercions pour la cooperation et vous prions de retourner ce questionnaire a l'adresse suivante : <strong>qmanac@anac-gabon.com</strong></em><br>
            <em style="font-size:.7rem">BP 2212 Libreville (GABON) - Tel.: (241) 01 44 54 00 - Email : anac@anac-gabon.com - www.anacgabon.org</em>
          </div>
        </div>
      </div>
      <div class="modal-footer no-print">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" class="btn btn-anac" id="q_submit">
          <i class="bi bi-check-lg me-1"></i>Enregistrer mon questionnaire
        </button>
      </div>
    </form>
  </div>
</div>

<!-- MODALE : Voir QRE -->
<div class="modal fade" id="viewQreModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" style="max-width:800px">
    <div class="modal-content">
      <div class="modal-header py-2 no-print">
        <h5 class="modal-title"><i class="bi bi-ui-checks-grid me-2" style="color:#23408F"></i>Consultation QRE</h5>
        <div class="ms-auto me-2">
          <button type="button" class="btn btn-sm btn-outline-primary" id="btnPrintView">
            <i class="bi bi-printer me-1"></i>Imprimer / PDF
          </button>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-3" id="viewQreBody">
        <div class="text-center py-4"><span class="spinner-border text-primary"></span></div>
      </div>
    </div>
  </div>
</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const CSRF  = '<?php echo Security::escape($csrf); ?>';
const API   = AGAI_BASE + '/api/qre';
const IS_CI = <?php echo $isCI ? 'true' : 'false'; ?>;
const BANER = '<?php echo Security::escape($banierUrl); ?>';
let ALL = [], chartAnnee = null, chartType = null, chartSatAnnee = null;

function apiPost(data){ return $.post(API, Object.assign({csrf_token:CSRF}, data), null, 'json'); }
function fmtDate(s){ if(!s||s==='0000-00-00') return '-'; const p=String(s).substring(0,10).split('-'); return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:s; }

const TYPES={audit:'Audit',inspection_programmee:'Insp. prog.',inspection_non_programmee:'Insp. non prog.',demonstration:'Demo',test:'Test',investigation:'Investigation'};
const STATUT={1:{t:'Planifie',c:'s1'},2:{t:'Reporte',c:'s2'},3:{t:'Effectue',c:'s3'},4:{t:'Suspendu',c:'s4'},6:{t:'Annule',c:'s6'},7:{t:'Inopine',c:'s7'}};
const NOTE_LABEL={TB:'Tres Bonne',B:'Bonne',M:'Mauvaise',TM:'Tres Mauvaise'};
const NOTE_CLS  ={TB:'note-tb',B:'note-b',M:'note-m',TM:'note-tm'};
const QUESTIONS={
  prep_notification:"Appreciation de la notification",
  prep_plan:"Appreciation du plan d'audit",
  cond_ouverture:"Reunion d'ouverture",
  cond_entretiens:"Qualite des entretiens",
  cond_procedures:"Connaissance des procedures",
  cond_qualites:"Qualites generales inspecteur",
  cond_communication:"Qualite de la communication",
  cond_classification:"Classification des constats",
  cond_pertinence:"Pertinence des observations",
  cond_cloture:"Reunion de cloture",
};

/* ===== TOGGLE DASHBOARD ===== */
let dashVisible = true;
$('#btnToggleDash').on('click',function(){
  dashVisible=!dashVisible;
  $('#dashPanel').toggle(dashVisible);
  $('#dashLbl').text(dashVisible?'Masquer le tableau de bord':'Afficher le tableau de bord');
});

/* ===== DASHBOARD ===== */
let ALL_QRE = []; // toutes les reponses chargees une fois pour filtres dynamiques

function loadStats(){
  apiPost({action:'stats'}).done(function(res){
    if(!res.success) return;
    ALL_QRE = res.allQRE || [];
    // Remplir filtres dashboard
    const seenA={},seenO={},seenY={};
    let optA='<option value="">Tous les audits</option>';
    let optO='<option value="">Tous les operateurs</option>';
    let optY='<option value="">Toutes les annees</option>';
    ALL_QRE.forEach(function(q){
      if(!seenA[q.idaudit]){ seenA[q.idaudit]=1; optA+='<option value="'+q.idaudit+'">'+esc(q.num_audit||q.idaudit)+'</option>'; }
      if(!seenO[q.nomorga]) { seenO[q.nomorga]=1; optO+='<option value="'+esc(q.nomorga)+'">'+esc(q.nomorga)+'</option>'; }
      const yr=(q.date_qre||'').substring(0,4);
      if(yr&&yr>='2020'&&!seenY[yr]){ seenY[yr]=1; optY+='<option value="'+yr+'">'+yr+'</option>'; }
    });
    $('#df_audit').html(optA).trigger('change.select2');
    $('#df_orga').html(optO).trigger('change.select2');
    $('#df_annee').html(optY).trigger('change.select2');
    renderStats(res.stats, res.par_annee||[], res.par_type||[], res.par_champ||[]);
  });
}

function getQreFiltered(){
  const fA=$('#df_audit').val(), fO=$('#df_orga').val(), fY=$('#df_annee').val(), fM=$('#df_mois').val(), fT=$('#df_type').val();
  return ALL_QRE.filter(function(q){
    if(fA && String(q.idaudit)!==String(fA)) return false;
    if(fO && q.nomorga!==fO) return false;
    const yr=(q.date_qre||'').substring(0,4), mo=(q.date_qre||'').substring(5,7);
    if(fY && yr!==fY) return false;
    if(fM && mo!==fM) return false;
    if(fT && q.type_activite!==fT) return false;
    return true;
  });
}

function computeAndRender(list){
  const FIELDS=['prep_notification','prep_plan','cond_ouverture','cond_entretiens','cond_procedures',
                'cond_qualites','cond_communication','cond_classification','cond_pertinence','cond_cloture'];
  const cnt={TB:0,B:0,M:0,TM:0}; let totalNotes=0;
  list.forEach(function(q){ FIELDS.forEach(function(f){ const v=q[f]||''; if(cnt[v]!==undefined){cnt[v]++;totalNotes++;} }); });
  const pct=function(n){ return totalNotes?Math.round(n/totalNotes*1000)/10:0; };
  const SC={TB:4,B:3,M:2,TM:1};
  const totalScore=list.reduce(function(a,q){ return a+FIELDS.reduce(function(s,f){return s+(SC[q[f]]||0);},0);},0);
  const scoreMoyen=totalNotes?Math.round(totalScore/totalNotes*100)/100:0;
  const tauxSat=totalNotes?Math.round((cnt.TB+cnt.B)/totalNotes*1000)/10:0;
  // Par annee
  const byYr={};
  list.forEach(function(q){
    const yr=(q.date_qre||'').substring(0,4); if(!yr||yr<'2020') return;
    if(!byYr[yr]) byYr[yr]={annee:yr,TB:0,B:0,M:0,TM:0,nb_qre:0};
    byYr[yr].nb_qre++;
    FIELDS.forEach(function(f){ const v=q[f]||''; if(byYr[yr][v]!==undefined) byYr[yr][v]++; });
  });
  // Par type
  const byType={};
  list.forEach(function(q){
    const t=q.type_activite||'autre';
    if(!byType[t]) byType[t]={type:t,nb_qre:0};
    byType[t].nb_qre++;
  });
  // Par champ
  const parChamp=FIELDS.map(function(f){
    let ftb=0,fb=0,fm=0,ftm=0;
    list.forEach(function(q){ const v=q[f]||''; if(v==='TB')ftb++;else if(v==='B')fb++;else if(v==='M')fm++;else if(v==='TM')ftm++; });
    const tot=ftb+fb+fm+ftm;
    return {label:QUESTIONS[f]||f,TB:ftb,B:fb,M:fm,TM:ftm,score:tot?Math.round((ftb*4+fb*3+fm*2+ftm*1)/tot*100)/100:0,sat:tot?Math.round((ftb+fb)/tot*1000)/10:0};
  });
  parChamp.sort(function(a,b){return b.score-a.score;});
  const s={total:list.length,score_moyen:scoreMoyen,taux_sat:tauxSat,TB:cnt.TB,B:cnt.B,M:cnt.M,TM:cnt.TM,
    pct_tb:pct(cnt.TB),pct_b:pct(cnt.B),pct_m:pct(cnt.M),pct_tm:pct(cnt.TM)};
  renderStats(s, Object.values(byYr), Object.values(byType), parChamp);
}

// Filtres dashboard connectes
$('#df_audit,#df_orga,#df_annee,#df_type').select2({theme:'bootstrap-5',width:'100%',allowClear:true,placeholder:'Tous'});
$('#df_audit,#df_orga,#df_annee,#df_mois,#df_type').on('change',function(){
  if(!ALL_QRE.length) return;
  computeAndRender(getQreFiltered());
});
$('#btnResetDash').on('click',function(){
  $('#df_audit,#df_orga,#df_annee,#df_type').val('').trigger('change');
  $('#df_mois').val('');
  if(ALL_QRE.length) computeAndRender(ALL_QRE);
});

function renderStats(s, pa, pt, pc){
  if(!s||!s.total){ $('#k_total,#k_sat,#k_score,#k_tb,#k_b,#k_m,#k_tm').text('-'); return; }
  $('#k_total').text(s.total);
  $('#k_sat').text(s.taux_sat+'%');
  $('#k_score').text(s.score_moyen+' / 4');
  $('#k_tb').text(s.pct_tb+'%'); $('#k_b').text(s.pct_b+'%');
  $('#k_m').text(s.pct_m+'%');   $('#k_tm').text(s.pct_tm+'%');
  $('#bar_tb').css('width',s.pct_tb+'%'); $('#bar_b').css('width',s.pct_b+'%');
  $('#bar_m').css('width',s.pct_m+'%');   $('#bar_tm').css('width',s.pct_tm+'%');
  $('#lg_tb').text(s.pct_tb); $('#lg_b').text(s.pct_b);
  $('#lg_m').text(s.pct_m);   $('#lg_tm').text(s.pct_tm);
  // Courbe taux de satisfaction par annee
  if(chartSatAnnee) chartSatAnnee.destroy();
  const paWithSat=pa.filter(function(r){ return r.nb_qre>0; });
  if(paWithSat.length){
    const satByYr=paWithSat.map(function(r){
      const tot=(r.TB||0)+(r.B||0)+(r.M||0)+(r.TM||0);
      return tot>0?Math.round(((r.TB||0)+(r.B||0))/tot*100):0;
    });
    chartSatAnnee=new Chart(document.getElementById('chartSatAnnee').getContext('2d'),{type:'line',data:{
      labels:paWithSat.map(function(r){return r.annee;}),
      datasets:[{
        label:'Taux de satisfaction (%)',
        data:satByYr,
        borderColor:'#1E9C4B',
        backgroundColor:'rgba(30,156,75,.08)',
        fill:true, tension:0.35,
        pointRadius:6, pointBackgroundColor:'#1E9C4B',
        pointHoverRadius:9, pointBorderColor:'#fff', pointBorderWidth:2,
        borderWidth:2.5,
      }]
    },options:{
      responsive:true, maintainAspectRatio:false,
      plugins:{
        legend:{display:false},
        tooltip:{callbacks:{
          label:function(c){ return ' '+c.parsed.y+'% de satisfaction'; },
          afterBody:function(c){
            const r=paWithSat[c[0].dataIndex];
            return 'Base sur '+r.nb_qre+' QRE(s)';
          }
        }}
      },
      scales:{
        y:{beginAtZero:true,max:100,ticks:{font:{size:9},callback:function(v){return v+'%';}},
          grid:{color:'rgba(0,0,0,.05)'}},
        x:{ticks:{font:{size:9}},grid:{display:false}}
      },
      animation:{onComplete:function(){
        const chart=this; const ctx=chart.ctx; const meta=chart.getDatasetMeta(0);
        meta.data.forEach(function(pt,i){
          const val=satByYr[i]; if(val===undefined) return;
          ctx.save(); ctx.fillStyle='#1E9C4B'; ctx.font='bold 9px Candara,Arial,sans-serif';
          ctx.textAlign='center'; ctx.textBaseline='bottom';
          ctx.fillText(val+'%',pt.x,pt.y-6); ctx.restore();
        });
      }}
    }});
  }
  // Chart annuel (barres empilees)
  if(pa.length){
    chartAnnee=new Chart(document.getElementById('chartAnnee').getContext('2d'),{type:'bar',data:{
      labels:pa.map(function(r){return r.annee;}),
      datasets:[
        {label:'Tres Bonne',data:pa.map(function(r){return r.TB||0;}),backgroundColor:'rgba(30,156,75,.8)',borderRadius:3},
        {label:'Bonne',     data:pa.map(function(r){return r.B||0;}), backgroundColor:'rgba(35,64,143,.8)',borderRadius:3},
        {label:'Mauvaise',  data:pa.map(function(r){return r.M||0;}), backgroundColor:'rgba(243,195,0,.8)',borderRadius:3},
        {label:'Tres Mais.',data:pa.map(function(r){return r.TM||0;}),backgroundColor:'rgba(211,47,47,.8)',borderRadius:3},
      ]
    },options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{font:{size:9},boxWidth:9}}},
      scales:{x:{stacked:true,ticks:{font:{size:9}}},y:{stacked:true,beginAtZero:true,ticks:{font:{size:9},stepSize:1}}}}});
  }
  // Chart donut type
  if(chartType) chartType.destroy();
  if(pt.length){
    const TL={audit:'Audit',inspection_programmee:'Insp. prog.',inspection_non_programmee:'Insp. non prog.',demonstration:'Demo',test:'Test',investigation:'Investigation'};
    chartType=new Chart(document.getElementById('chartType').getContext('2d'),{type:'doughnut',data:{
      labels:pt.map(function(r){return TL[r.type]||r.type;}),
      datasets:[{data:pt.map(function(r){return r.nb_qre;}),
        backgroundColor:['rgba(35,64,143,.8)','rgba(30,156,75,.8)','rgba(243,195,0,.8)','rgba(211,47,47,.8)','rgba(90,24,154,.8)','rgba(56,61,65,.8)'],
        borderWidth:2,borderColor:'#fff'}]
    },options:{responsive:true,maintainAspectRatio:false,cutout:'55%',plugins:{legend:{position:'bottom',labels:{font:{size:9},boxWidth:9}}}}});
  }
  // Barres par champ
  let hc='';
  pc.forEach(function(c,i){
    const pct=Math.round(c.sat);
    const medal=i===0?'<i class="bi bi-trophy-fill text-warning me-1"></i>':i===pc.length-1?'<i class="bi bi-exclamation-triangle-fill text-danger me-1"></i>':'';
    const fc=pct>=80?'#1E9C4B':pct>=60?'#23408F':pct>=40?'#F3C300':'#D32F2F';
    hc+='<div class="champ-row"><div style="width:115px;font-size:.72rem;color:#2C3E50;flex-shrink:0">'+medal+esc(c.label)+'</div>'
      +'<div class="champ-bar"><div class="champ-fill" style="width:'+pct+'%;background:'+fc+'"></div></div>'
      +'<div style="width:38px;text-align:right;font-size:.74rem;font-weight:700;color:'+fc+'">'+pct+'%</div></div>';
  });
  $('#champChart').html(hc);
  if(pc.length){
    const best=pc[0],worst=pc[pc.length-1];
    $('#pointFort').html('<strong>'+esc(best.label)+'</strong><br><span class="text-success fw-bold">Score '+best.score+'/4</span> | Sat. <strong>'+best.sat+'%</strong>');
    $('#pointFaible').html('<strong>'+esc(worst.label)+'</strong><br><span class="text-danger fw-bold">Score '+worst.score+'/4</span> | Sat. <strong>'+worst.sat+'%</strong>');
  }
  const indice=s.score_moyen>=3.5?'<span class="text-success fw-bold">Excellent</span>':s.score_moyen>=3?'<span style="color:#23408F;font-weight:700">Bon</span>':s.score_moyen>=2?'<span style="color:#b58a00;font-weight:700">Moyen</span>':'<span class="text-danger fw-bold">A ameliorer</span>';
  $('#indiceQualite').html(indice+' ('+s.score_moyen+'/4)<br>Satisfaction : <strong>'+s.taux_sat+'%</strong><br><span class="text-muted small">'+s.total+' QRE / '+((s.TB||0)+(s.B||0)+(s.M||0)+(s.TM||0))+' reponses</span>');
}

/* ===== TOOLTIPS KPI ===== */
$(function(){
  const tips={
    'k_total':   'Nombre total de QRE soumis parmi les audits eligibles (ayant une lettre de notification jointe).',
    'k_sat':     'Taux de satisfaction = (Tres Bonne + Bonne) / Total reponses x 100. Une reponse positive couvre les 10 questions.',
    'k_score':   'Score moyen : Tres Bonne=4pts, Bonne=3pts, Mauvaise=2pts, Tres Mauvaise=1pt. Calcule sur toutes les reponses de toutes les questions.',
    'k_tb':      'Pourcentage de reponses "Tres Bonne" sur l\'ensemble des 10 questions et de tous les QRE.',
    'k_b':       'Pourcentage de reponses "Bonne" sur l\'ensemble des 10 questions et de tous les QRE.',
    'k_m':       'Pourcentage de reponses "Mauvaise" sur l\'ensemble des 10 questions et de tous les QRE.',
    'k_tm':      'Pourcentage de reponses "Tres Mauvaise" sur l\'ensemble des 10 questions et de tous les QRE.',
  };
  Object.keys(tips).forEach(function(id){
    $('#'+id).closest('.kpi-card').attr('title',tips[id]).css('cursor','help');
  });
});

/* ===== FILTRES ===== */
function fillFilters(){
  const seenAudit={}, seenOrga={}, curA=$('#fAudit').val(), curO=$('#fOrga').val();
  let optsA='<option value="">Tous les audits</option>', optsO='<option value="">Tous les operateurs</option>';
  ALL.forEach(function(a){
    if(!seenAudit[a.idaudit]){ seenAudit[a.idaudit]=1; optsA+='<option value="'+esc(a.idaudit)+'">'+esc(a.num_audit)+'</option>'; }
    if(!seenOrga[a.nomorga])  { seenOrga[a.nomorga]=1;  optsO+='<option value="'+esc(a.nomorga)+'">'+esc(a.nomorga)+'</option>'; }
  });
  $('#fAudit').html(optsA); if(curA) $('#fAudit').val(curA); $('#fAudit').trigger('change.select2');
  $('#fOrga').html(optsO);  if(curO) $('#fOrga').val(curO);  $('#fOrga').trigger('change.select2');
}
function getFiltered(){
  const aud=$('#fAudit').val(), org=$('#fOrga').val(), qre=$('#fQre').val(), typ=$('#fType').val();
  return ALL.filter(function(a){
    if(aud && String(a.idaudit)!==String(aud)) return false;
    if(org && a.nomorga!==org) return false;
    if(typ && a.type_activite!==typ) return false;
    if(qre==='1'&&!a.idqre) return false;
    if(qre==='0'&& a.idqre) return false;
    return true;
  });
}
$('#fAudit,#fOrga').select2({theme:'bootstrap-5',width:'100%',allowClear:true,placeholder:'Tous'});
$('#fType').select2({theme:'bootstrap-5',width:'100%',allowClear:true,placeholder:'Toutes'});
$('#fAudit,#fOrga,#fQre,#fType').on('change',function(){render();});
$('#btnReset').on('click',function(){ $('#fAudit,#fOrga,#fType').val('').trigger('change'); $('#fQre').val(''); render(); });

/* ===== RENDU TABLEAU ===== */
function qreTag(a){
  if(!a.idqre) return '<span class="tag-non"><i class="bi bi-hourglass-split"></i>Non soumis</span>';
  if(a.qre_fichier) return '<span class="tag-ok" style="background:#e8f0fe;color:#23408F"><i class="bi bi-file-earmark-check"></i>'+fmtDate(a.qre_date)+' (fichier)</span>';
  return '<span class="tag-ok"><i class="bi bi-check-circle"></i>'+fmtDate(a.qre_date)+'</span>';
}
function actionsHtml(a){
  let html='';
  if(a.idqre){
    html+='<button class="btn-qre-view me-1 btn-voir-qre" data-idqre="'+esc(a.idqre)+'"><i class="bi bi-eye"></i></button>';
    if(IS_CI) html+='<button class="btn btn-xs btn-outline-danger ms-1 btn-del-qre" data-idqre="'+esc(a.idqre)+'" style="padding:3px 7px"><i class="bi bi-trash"></i></button>';
  } else {
    html+='<button class="btn-qre-fill btn-fill-qre" data-id="'+esc(a.idaudit)+'" data-num="'+esc(a.num_audit)+'" data-orga="'+esc(a.nomorga)+'" data-idorga="'+esc(a.idorga)+'" data-site="'+esc(a.site_inspection||'')+'"><i class="bi bi-pencil-square"></i> Remplir</button>';
  }
  return '<div style="text-align:right;white-space:nowrap">'+html+'</div>';
}
function render(){
  const list=getFiltered();
  $('#resCount').html('<i class="bi bi-ui-checks-grid me-1"></i>'+list.length+' audit(s)');
  if(!list.length){
    $('#tbody').html('<tr><td colspan="8" class="empty"><i class="bi bi-inbox me-2"></i>Aucun audit eligible.</td></tr>'); return;
  }
  $('#tbody').html(list.map(function(a){
    const st=STATUT[a.statut]||{t:'-',c:'s1'};
    return '<tr>'
      +'<td><b style="color:#23408F;font-size:.88rem">'+esc(a.num_audit||'')+'</b></td>'
      +'<td style="font-size:.82rem">'+esc(TYPES[a.type_activite]||'')+'</td>'
      +'<td style="font-weight:600;font-size:.82rem">'+esc(a.nomorga||'-')+'</td>'
      +'<td style="font-size:.82rem;color:#555">'+esc(a.site_inspection||'-')+'</td>'
      +'<td style="font-size:.82rem">'+fmtDate(a.date_previsionnelle)+'</td>'
      +'<td><span class="s-badge '+st.c+'">'+esc(st.t)+'</span></td>'
      +'<td>'+qreTag(a)+'</td>'
      +'<td class="no-print">'+actionsHtml(a)+'</td>'
      +'</tr>';
  }).join(''));
}
function loadList(){
  apiPost({action:'list'}).done(function(res){
    if(!res.success){ $('#tbody').html('<tr><td colspan="8" class="empty">'+esc(res.message||'Erreur')+'</td></tr>'); return; }
    ALL=res.data||[]; fillFilters(); render();
  }).fail(function(){ $('#tbody').html('<tr><td colspan="8" class="empty">Echec.</td></tr>'); });
}

/* ===== CHOIX DU MODE DE SOUMISSION ===== */
let QRE_PENDING = null;
$(document).on('click','.btn-fill-qre',function(){
  QRE_PENDING = {
    id: $(this).data('id'), num: $(this).data('num'),
    orga: $(this).data('orga'), idorga: $(this).data('idorga'), site: $(this).data('site')
  };
  $('#qc_num').text(QRE_PENDING.num || '');
  new bootstrap.Modal('#qreChoixModal').show();
});

/* ===== OUVRIR FORMULAIRE (saisie en ligne) ===== */
$('#qcSaisie').on('click', function(){
  const p = QRE_PENDING; if(!p) return;
  bootstrap.Modal.getInstance(document.getElementById('qreChoixModal')).hide();
  $('#q_idaudit').val(p.id); $('#q_idorga').val(p.idorga);
  $('#q_nomorga').val(p.orga); $('#q_lieu').val(p.site);
  $('#q_date').val(new Date().toISOString().substring(0,10));
  $('#q_activites').val(''); $('#q_autres').val(''); $('#q_mail').prop('checked',false);
  $('#qreForm input[type=radio]').prop('checked',false);
  setTimeout(function(){
    new bootstrap.Modal('#qreModal').show();
    setTimeout(function(){ $('#q_activites').focus(); },400);
  }, 300);
});

/* ===== OUVRIR JOINDRE LE FORMULAIRE (fichier deja rempli a la main) ===== */
$('#qcFichier').on('click', function(){
  const p = QRE_PENDING; if(!p) return;
  bootstrap.Modal.getInstance(document.getElementById('qreChoixModal')).hide();
  $('#qf_idaudit').val(p.id); $('#qf_idorga').val(p.idorga);
  $('#qf_nomorga').val(p.orga);
  $('#qf_activites').val(''); $('#qf_date').val(new Date().toISOString().substring(0,10));
  $('#qf_fichier').val('');
  setTimeout(function(){ new bootstrap.Modal('#qreFichierModal').show(); }, 300);
});

/* ===== SOUMETTRE (fichier joint) ===== */
$('#qreFichierForm').on('submit', function(e){
  e.preventDefault();
  const fInput = document.getElementById('qf_fichier');
  if(!fInput.files.length){ Swal.fire({icon:'warning',title:'Fichier requis',confirmButtonColor:'#23408F'}); return; }
  const file = fInput.files[0];
  const maxSize = 10*1024*1024;
  if(file.size > maxSize){ Swal.fire({icon:'warning',title:'Fichier trop volumineux',text:'10 Mo maximum.',confirmButtonColor:'#23408F'}); return; }
  const ext = file.name.split('.').pop().toLowerCase();
  if(['pdf','jpg','jpeg','png'].indexOf(ext)<0){ Swal.fire({icon:'warning',title:'Format non autorise',text:'Utilisez PDF, JPG ou PNG.',confirmButtonColor:'#23408F'}); return; }
  if(!$('#qf_activites').val().trim()){ Swal.fire({icon:'warning',title:'Activites requises',confirmButtonColor:'#23408F'}); return; }

  const fd = new FormData();
  fd.append('action','save_fichier');
  fd.append('csrf_token', CSRF);
  fd.append('idaudit', $('#qf_idaudit').val());
  fd.append('idorga', $('#qf_idorga').val());
  fd.append('activites', $('#qf_activites').val().trim());
  fd.append('date_qre', $('#qf_date').val());
  fd.append('fichier', file);

  const btn=$('#qf_submit'), html=btn.html();
  btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm me-1"></span>Televersement...');
  $.ajax({ url: API, type:'POST', data: fd, processData:false, contentType:false, dataType:'json' })
    .done(function(res){
      btn.prop('disabled',false).html(html);
      if(res.success){
        bootstrap.Modal.getInstance(document.getElementById('qreFichierModal')).hide();
        Swal.fire({icon:'success',title:'QRE enregistre',text:res.message,confirmButtonColor:'#23408F',timer:2200,timerProgressBar:true});
        loadList(); loadStats();
      } else {
        Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'});
      }
    })
    .fail(function(){
      btn.prop('disabled',false).html(html);
      Swal.fire({icon:'error',title:'Erreur',text:'Echec technique lors du televersement.',confirmButtonColor:'#23408F'});
    });
});

/* ===== SOUMETTRE ===== */
$('#qreForm').on('submit',function(e){
  e.preventDefault();
  const names=['prep_notification','prep_plan','cond_ouverture','cond_entretiens','cond_procedures','cond_qualites','cond_communication','cond_classification','cond_pertinence','cond_cloture'];
  for(let i=0;i<names.length;i++){
    if(!$('input[name="'+names[i]+'"]:checked').val()){
      Swal.fire({icon:'warning',title:'Reponse manquante',text:'Cochez une appreciation pour toutes les questions.',confirmButtonColor:'#23408F'});
      $('input[name="'+names[i]+'"]').first().closest('tr')[0].scrollIntoView({behavior:'smooth',block:'center'});
      return;
    }
  }
  if(!$('#q_activites').val().trim()){ Swal.fire({icon:'warning',title:'Activites requises',confirmButtonColor:'#23408F'}); return; }
  const data={action:'save',idaudit:$('#q_idaudit').val(),idorga:$('#q_idorga').val(),
    activites:$('#q_activites').val().trim(),date_qre:$('#q_date').val(),
    autres:$('#q_autres').val().trim(),envoyer_mail:$('#q_mail').is(':checked')?1:0};
  names.forEach(function(n){ data[n]=$('input[name="'+n+'"]:checked').val(); });
  const btn=$('#q_submit'), html=btn.html();
  btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
  apiPost(data).done(function(res){
    btn.prop('disabled',false).html(html);
    if(res.success){
      bootstrap.Modal.getInstance(document.getElementById('qreModal')).hide();
      Swal.fire({icon:'success',title:'QRE enregistre',text:res.message,confirmButtonColor:'#23408F',timer:2500,timerProgressBar:true});
      loadList(); loadStats();
    } else { Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'}); }
  }).fail(function(){ btn.prop('disabled',false).html(html); Swal.fire({icon:'error',title:'Erreur reseau',confirmButtonColor:'#23408F'}); });
});

/* ===== VOIR QRE ===== */
$(document).on('click','.btn-voir-qre',function(){
  const idqre=$(this).data('idqre');
  $('#viewQreBody').html('<div class="text-center py-4"><span class="spinner-border text-primary"></span></div>');
  new bootstrap.Modal('#viewQreModal').show();
  apiPost({action:'get',idqre:idqre}).done(function(res){
    if(!res.success){ $('#viewQreBody').html('<div class="alert alert-danger">'+esc(res.message)+'</div>'); return; }
    const q=res.data;

    // QRE soumis par fichier joint (formulaire deja rempli a la main) : vue dediee
    if(q.fichier_joint){
      const url=API+'?action=serve&idqre='+encodeURIComponent(idqre);
      const ext=String(q.fichier_joint).split('.').pop().toLowerCase();
      const isImg=['jpg','jpeg','png'].indexOf(ext)>=0;
      let hf='<div style="text-align:center;margin-bottom:14px">'
        +'<span class="tag-ok" style="font-size:.82rem;padding:5px 14px"><i class="bi bi-file-earmark-check me-1"></i>Formulaire joint (deja rempli a la main)</span>'
        +'</div>'
        +'<div style="border:1px solid #eef1f6;border-radius:10px;padding:10px 14px;margin-bottom:14px;background:#f8fafc">'
        +'<div class="row g-2">'
        +'<div class="col-md-6"><div class="small text-muted">Operateur</div><div style="font-weight:700;color:#23408F">'+esc(q.nomorga||'-')+'</div></div>'
        +'<div class="col-md-6"><div class="small text-muted">N Audit</div><div style="font-weight:700;color:#23408F">'+esc(q.num_audit||'-')+'</div></div>'
        +'<div class="col-md-6"><div class="small text-muted">Activite(s) auditee(s)</div><div>'+esc(q.activites_auditees||'-')+'</div></div>'
        +'<div class="col-md-6"><div class="small text-muted">Date</div><div>'+fmtDate(q.date_qre)+'</div></div>'
        +'</div></div>';
      hf += isImg
        ? '<div style="text-align:center"><img src="'+url+'" style="max-width:100%;border:1px solid #eef1f6;border-radius:8px" alt="Formulaire QRE"></div>'
        : '<iframe src="'+url+'" style="width:100%;height:60vh;border:1px solid #eef1f6;border-radius:8px"></iframe>';
      hf += '<div class="text-center mt-3"><a href="'+url+'" class="btn btn-sm btn-outline-primary" download><i class="bi bi-download me-1"></i>Telecharger</a></div>';
      $('#viewQreBody').html(hf);
      return;
    }
    const NOTE_COLORS={TB:'#d1fae5|#065f46',B:'#dbeafe|#1e40af',M:'#fef3c7|#92400e',TM:'#fee2e2|#991b1b'};
    function noteCell(v){
      if(!v) return '<span class="text-muted">-</span>';
      const parts=(NOTE_COLORS[v]||'#eee|#555').split('|');
      return '<span style="background:'+parts[0]+';color:'+parts[1]+';border-radius:4px;padding:2px 10px;font-size:.8rem;font-weight:700;display:inline-block">'+esc(NOTE_LABEL[v]||v)+'</span>';
    }
    function sectionHdr(txt){
      return '<tr><td colspan="5" style="background:#23408F;color:#fff;padding:7px 12px;font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em">'+txt+'</td></tr>';
    }
    function colHdrs(){
      return '<tr style="background:#f0f4ff">'
        +'<td style="padding:7px 12px;font-size:.72rem;font-weight:700;color:#23408F;border:1px solid #c5d4f5;width:52%">Question</td>'
        +'<td style="padding:7px 10px;font-size:.72rem;font-weight:700;color:#1E9C4B;border:1px solid #c5d4f5;text-align:center">Tres Bonne</td>'
        +'<td style="padding:7px 10px;font-size:.72rem;font-weight:700;color:#23408F;border:1px solid #c5d4f5;text-align:center">Bonne</td>'
        +'<td style="padding:7px 10px;font-size:.72rem;font-weight:700;color:#b58a00;border:1px solid #c5d4f5;text-align:center">Mauvaise</td>'
        +'<td style="padding:7px 10px;font-size:.72rem;font-weight:700;color:#D32F2F;border:1px solid #c5d4f5;text-align:center">Tres Mauvaise</td>'
        +'</tr>';
    }
    function qRow(key, rowStyle){
      const v=q[key]||'';
      const bg=rowStyle||'';
      function chk(target){
        return v===target
          ?'<span style="display:inline-block;width:16px;height:16px;background:#23408F;border-radius:2px;vertical-align:middle;position:relative">'
          +'<span style="position:absolute;left:2px;top:0;color:#fff;font-size:11px;font-weight:900">&#10003;</span></span>'
          :'<span style="display:inline-block;width:16px;height:16px;border:1.5px solid #999;border-radius:2px;vertical-align:middle"></span>';
      }
      return '<tr style="'+bg+'">'
        +'<td style="padding:7px 12px;font-size:.83rem;border:1px solid #e2e8f0;line-height:1.4">'+esc(QUESTIONS[key])+'</td>'
        +'<td style="text-align:center;border:1px solid #e2e8f0;padding:7px">'+chk('TB')+'</td>'
        +'<td style="text-align:center;border:1px solid #e2e8f0;padding:7px">'+chk('B')+'</td>'
        +'<td style="text-align:center;border:1px solid #e2e8f0;padding:7px">'+chk('M')+'</td>'
        +'<td style="text-align:center;border:1px solid #e2e8f0;padding:7px">'+chk('TM')+'</td>'
        +'</tr>';
    }
    let html='<div id="qreViewPrint" style="font-family:Candara,Arial,sans-serif;color:#2C3E50;font-size:.88rem">';
    // --- Reference
    html+='<div style="text-align:right;font-size:.7rem;color:#888;margin-bottom:4px">IX-GEN-R3-F-I-011 &ndash; Fevrier 2024 Version 02</div>';
    // --- Banniere (contient deja ANAC + Republique Gabonaise)
    html+='<div style="text-align:center;background:#fff;border:1px solid #d1d5db;border-radius:6px;padding:4px 8px;margin-bottom:10px">'
      +'<img src="'+esc(BANER)+'" alt="ANAC Gabon" style="max-width:100%;max-height:90px;object-fit:contain;display:block;margin:0 auto"></div>';
    // --- Titre
    html+='<div style="text-align:center;font-size:1.05rem;font-weight:900;text-transform:uppercase;color:#23408F;'
      +'border:2px solid #23408F;padding:8px 14px;margin:0 0 8px;letter-spacing:.06em">Questionnaire de Retour d\'Experience</div>';
    // --- Intro
    html+='<div style="font-size:.78rem;font-style:italic;color:#555;margin-bottom:8px;padding:0 4px">'
      +'(Le questionnaire de retour d\'experience a pour objectif de tirer les enseignements positifs et negatifs de la realisation de l\'audit. '
      +'Il vise exclusivement l\'amelioration du systeme de supervision par l\'ANAC).</div>';
    html+='<div style="border:1px solid #d1d5db;padding:8px 12px;font-size:.82rem;margin-bottom:12px;border-radius:4px;background:#f9fafb">'
      +'Votre organisme a ete audite par les inspecteurs de l\'ANAC, nous vous remercions de nous faire part de votre appreciation sur le deroulement de l\'activite.</div>';
    // --- Tableau infos generales
    html+='<table style="width:100%;border-collapse:collapse;margin-bottom:10px;border:1px solid #23408F">'
      +'<thead><tr><td colspan="4" style="background:#23408F;color:#fff;padding:6px 10px;font-size:.78rem;font-weight:700;text-transform:uppercase;text-align:center;letter-spacing:.04em">'
      +'INFORMATIONS GENERALES SUR L\'AUDITE</td></tr></thead><tbody>'
      +'<tr>'
      +'<td style="padding:6px 10px;font-weight:700;font-size:.78rem;border:1px solid #d1d5db;background:#f5f7fa;width:18%">Operateur :</td>'
      +'<td style="padding:6px 10px;font-size:.85rem;border:1px solid #d1d5db;font-weight:700;color:#23408F">'+esc(q.nomorga||'-')+'</td>'
      +'<td style="padding:6px 10px;font-weight:700;font-size:.78rem;border:1px solid #d1d5db;background:#f5f7fa;width:22%">Activite(s) auditee(s) :</td>'
      +'<td style="padding:6px 10px;font-size:.85rem;border:1px solid #d1d5db">'+esc(q.activites_auditees||'-')+'</td>'
      +'</tr><tr>'
      +'<td style="padding:6px 10px;font-weight:700;font-size:.78rem;border:1px solid #d1d5db;background:#f5f7fa">N Audit :</td>'
      +'<td style="padding:6px 10px;font-size:.85rem;border:1px solid #d1d5db;font-weight:700;color:#23408F">'+esc(q.num_audit||'-')+'</td>'
      +'<td style="padding:6px 10px;font-weight:700;font-size:.78rem;border:1px solid #d1d5db;background:#f5f7fa">Site / Lieu :</td>'
      +'<td style="padding:6px 10px;font-size:.85rem;border:1px solid #d1d5db">'+esc(q.site_inspection||'-')+'</td>'
      +'</tr><tr>'
      +'<td style="padding:6px 10px;font-weight:700;font-size:.78rem;border:1px solid #d1d5db;background:#f5f7fa">Date :</td>'
      +'<td style="padding:6px 10px;font-size:.85rem;border:1px solid #d1d5db" colspan="3">'+fmtDate(q.date_qre)+'</td>'
      +'</tr></tbody></table>';
    // --- Instruction
    html+='<div style="text-align:center;font-style:italic;font-size:.8rem;color:#444;margin:6px 0 10px">'
      +'<em>Veuillez cocher la case correspondant a votre niveau d\'appreciation du deroulement de l\'audit.</em></div>';
    // --- Fonctions helpers cases a cocher + sections
    function chkBoxV(v,target){
      if(v===target) return '<span style="display:inline-flex;align-items:center;justify-content:center;width:15px;height:15px;background:#23408F;border-radius:2px;border:1.5px solid #23408F"><svg width="9" height="7" viewBox="0 0 9 7"><polyline points="1,3.5 3.5,6 8,1" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>';
      return '<span style="display:inline-flex;align-items:center;justify-content:center;width:15px;height:15px;background:#fff;border-radius:2px;border:1.5px solid #9ca3af"></span>';
    }
    function buildSect(titre, fields){
      let t='<table style="width:100%;border-collapse:collapse;margin-bottom:10px;border:1px solid #23408F">'
        +'<thead><tr><td colspan="5" style="background:#23408F;color:#fff;padding:6px 10px;font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em">'+titre+'</td></tr>'
        +'<tr style="background:#e8edf8">'
        +'<td style="padding:5px 10px;font-size:.72rem;font-weight:700;color:#23408F;border:1px solid #c5d4f5;width:52%">Question</td>'
        +'<td style="padding:5px 8px;font-size:.72rem;font-weight:700;color:#1E9C4B;border:1px solid #c5d4f5;text-align:center">Tres<br>Bonne</td>'
        +'<td style="padding:5px 8px;font-size:.72rem;font-weight:700;color:#23408F;border:1px solid #c5d4f5;text-align:center">Bonne</td>'
        +'<td style="padding:5px 8px;font-size:.72rem;font-weight:700;color:#b58a00;border:1px solid #c5d4f5;text-align:center">Mauvaise</td>'
        +'<td style="padding:5px 8px;font-size:.72rem;font-weight:700;color:#D32F2F;border:1px solid #c5d4f5;text-align:center">Tres<br>Mauvaise</td>'
        +'</tr></thead><tbody>';
      fields.forEach(function(key,idx){
        const v=q[key]||'';
        const bg=idx%2===0?'background:#f9fafb':'background:#fff';
        t+='<tr style="'+bg+'">'
          +'<td style="padding:6px 10px;font-size:.82rem;border:1px solid #e5e7eb;line-height:1.4;vertical-align:middle">'+esc(QUESTIONS[key]||key)+'</td>'
          +'<td style="text-align:center;border:1px solid #e5e7eb;padding:6px">'+chkBoxV(v,'TB')+'</td>'
          +'<td style="text-align:center;border:1px solid #e5e7eb;padding:6px">'+chkBoxV(v,'B')+'</td>'
          +'<td style="text-align:center;border:1px solid #e5e7eb;padding:6px">'+chkBoxV(v,'M')+'</td>'
          +'<td style="text-align:center;border:1px solid #e5e7eb;padding:6px">'+chkBoxV(v,'TM')+'</td>'
          +'</tr>';
      });
      return t+'</tbody></table>';
    }
    html+=buildSect('PREPARATION DE L\'AUDIT',['prep_notification','prep_plan']);
    html+=buildSect('CONDUITE DE L\'AUDIT',['cond_ouverture','cond_entretiens','cond_procedures','cond_qualites','cond_communication','cond_classification','cond_pertinence','cond_cloture']);
    // --- Autres appreciations
    html+='<table style="width:100%;border-collapse:collapse;margin-bottom:12px">'
      +'<thead><tr><td style="background:#f0f4ff;border:1px solid #23408F;padding:6px 10px;font-size:.78rem;font-weight:700;text-transform:uppercase;color:#23408F">AUTRES APPRECIATIONS</td></tr></thead>'
      +'<tbody><tr><td style="border:1px solid #d1d5db;padding:10px 12px;min-height:50px;font-size:.84rem;color:#374151">'
      +(q.autres_appreciations?esc(q.autres_appreciations):'<span style="color:#9ca3af">-</span>')+'</td></tr></tbody></table>';
    // --- Pied
    html+='<div style="border-top:1px solid #e5e7eb;padding-top:8px;margin-top:4px">'
      +'<div style="font-size:.76rem;font-style:italic;color:#555;margin-bottom:3px">'
      +'Nous vous remercions pour la cooperation et vous prions de retourner ce questionnaire a l\'adresse suivante : '
      +'<a href="mailto:qmanac@anac-gabon.com" style="color:#23408F;font-weight:700">qmanac@anac-gabon.com</a></div>'
      +'<div style="font-size:.76rem;font-style:italic;color:#555;margin-bottom:5px">'
      +'Les reponses recues feront l\'objet d\'analyse periodique afin de determiner les opportunites d\'amelioration de l\'activite d\'audit des processus de certification et de surveillance des operateurs du secteur aerien.</div>'
      +'<div style="font-size:.71rem;color:#9ca3af;text-align:center">Soumis le '+fmtDate(q.created_at)+(q.envoye_mail?' &middot; Envoye par mail':'')+'</div></div>';
    html+='</div>';

    $('#viewQreBody').html(html);
    document.getElementById('qreViewPrint')._qreData = q;
  });
});
/* ===== CSS IMPRESSION PARTAGEE ===== */
const PDF_STYLE = `
  @import url('https://fonts.googleapis.com/css2?family=Candara&display=swap');
  * { box-sizing:border-box; margin:0; padding:0; }
  body { font-family:'Candara',Arial,sans-serif; color:#2C3E50; font-size:10pt; background:#fff; }
  .page { width:210mm; min-height:297mm; padding:12mm 14mm; margin:0 auto; }
  /* En-tete */
  .ref-line { text-align:right; font-size:7.5pt; color:#444; margin-bottom:6px; }
  .hdr { display:flex; align-items:center; justify-content:space-between; border-bottom:2px solid #23408F; padding-bottom:8px; margin-bottom:10px; }
  .hdr-left { font-size:8pt; font-weight:700; text-transform:uppercase; line-height:1.4; color:#2C3E50; }
  .hdr-left span { display:block; width:50px; height:2px; background:#23408F; margin-top:3px; }
  .hdr-right { font-size:7.5pt; text-align:right; font-style:italic; color:#2C3E50; }
  .hdr-right span { display:block; width:50px; height:2px; background:#23408F; margin-top:3px; margin-left:auto; }
  .hdr-logo { text-align:center; }
  .hdr-logo img { height:65px; object-fit:contain; }
  /* Titre */
  .qre-title { text-align:center; font-size:13pt; font-weight:900; text-transform:uppercase; color:#23408F; border:2.5px solid #23408F; padding:7px 14px; margin:8px 0 6px; letter-spacing:.05em; }
  .qre-italic { font-size:7.5pt; font-style:italic; color:#444; margin-bottom:8px; }
  /* Note intro */
  .note-box { border:1px solid #ccc; padding:7px 10px; font-size:8.5pt; margin-bottom:10px; }
  /* Section infos */
  .info-box { border:1px solid #23408F; margin-bottom:10px; }
  .info-title { background:#23408F; color:#fff; font-size:8pt; font-weight:700; text-transform:uppercase; text-align:center; padding:5px; letter-spacing:.05em; }
  .info-grid { display:grid; grid-template-columns:110px 1fr 140px 1fr; gap:0; }
  .info-cell { padding:5px 8px; font-size:8.5pt; border:1px solid #e0e0e0; }
  .info-label { font-weight:700; background:#f5f5f5; }
  .info-val { border-bottom:1px solid #ccc; min-height:18px; }
  /* Instruction */
  .instruction { text-align:center; font-style:italic; font-size:8pt; margin:6px 0 0; color:#333; }
  /* Sections questions */
  .section-hdr { display:grid; grid-template-columns:1fr 60px 60px 60px 60px; border:1px solid #23408F; margin-top:8px; }
  .section-hdr-label { background:#23408F; color:#fff; font-size:8pt; font-weight:700; padding:5px 8px; text-transform:uppercase; }
  .section-hdr-col { background:#e8edf8; font-size:7pt; font-weight:700; text-align:center; padding:4px 2px; border-left:1px solid #ccc; color:#23408F; line-height:1.2; }
  .q-row { display:grid; grid-template-columns:1fr 60px 60px 60px 60px; border:1px solid #ddd; border-top:none; }
  .q-row:nth-child(even) { background:#f9fafb; }
  .q-text { padding:5px 8px; font-size:8pt; border-right:1px solid #ddd; }
  .q-cell { text-align:center; padding:5px 2px; border-left:1px solid #eee; }
  .check-box { display:inline-block; width:14px; height:14px; border:1.5px solid #555; vertical-align:middle; }
  .check-filled { background:#23408F; border-color:#23408F; position:relative; }
  .check-filled::after { content:''; position:absolute; left:2px; top:1px; width:8px; height:5px; border-left:2px solid #fff; border-bottom:2px solid #fff; transform:rotate(-45deg); }
  /* Autres */
  .autres-hdr { background:#f0f4ff; border:1px solid #ddd; border-top:none; padding:5px 8px; font-size:8pt; font-weight:700; text-transform:uppercase; color:#23408F; margin-top:0; }
  .autres-box { border:1px solid #ddd; border-top:none; min-height:50px; padding:6px 8px; font-size:8.5pt; }
  /* Pied */
  .footer { margin-top:14px; border-top:1px solid #ccc; padding-top:8px; font-size:7.5pt; font-style:italic; color:#333; }
  .footer a { color:#23408F; font-weight:700; }
  .footer-addr { margin-top:14px; padding-top:6px; border-top:1px solid #23408F; font-size:7pt; color:#555; text-align:center; }
  @page { size:A4; margin:0; }
  @media print { body { margin:0; } .page { padding:8mm 10mm; } }
`;

function buildQrePdf(q, baner){
  const NL={'TB':'Tres Bonne','B':'Bonne','M':'Mauvaise','TM':'Tres Mauvaise'};
  function checked(v, target){
    return v===target
      ? '<span class="check-box check-filled"></span>'
      : '<span class="check-box"></span>';
  }
  function qRow(label, field){
    const v=q[field]||'';
    return '<div class="q-row">'
      +'<div class="q-text">'+esc(label)+'</div>'
      +'<div class="q-cell">'+checked(v,'TB')+'</div>'
      +'<div class="q-cell">'+checked(v,'B')+'</div>'
      +'<div class="q-cell">'+checked(v,'M')+'</div>'
      +'<div class="q-cell">'+checked(v,'TM')+'</div>'
      +'</div>';
  }
  const datePrev = q.date_previsionnelle ? formatDate(q.date_previsionnelle) : '-';
  const dateReal = q.date_realisation ? formatDate(q.date_realisation) : 'Non renseignee';
  const dateQre  = q.date_qre ? formatDate(q.date_qre) : '-';
  return `<!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><title>QRE - ${esc(q.num_audit||'')}</title>
<style>${PDF_STYLE}</style></head><body>
<div class="page">
  <div class="ref-line">IX-GEN-R3-F-I-011 Fevrier 2024 Version 02</div>
  <div class="hdr" style="justify-content:center;border-bottom:2px solid #23408F;padding-bottom:8px;margin-bottom:10px">
    <img src="${baner}" alt="ANAC Gabon" style="max-height:80px;max-width:100%;object-fit:contain;display:block;margin:0 auto">
  </div>
  <div class="qre-title">Questionnaire de Retour d'Experience</div>
  <div class="qre-italic">(Le questionnaire de retour d'experience a pour objectif de tirer les enseignements positifs et negatifs de la realisation de l'audit. Il vise exclusivement l'amelioration du systeme de supervision par l'ANAC).</div>
  <div class="note-box">Votre organisme a ete audite par les inspecteurs de l'ANAC, nous vous remercions de nous faire part de votre appreciation sur le deroulement de l'activite.</div>

  <div class="info-box">
    <div class="info-title">Informations Generales sur l'Audite</div>
    <div class="info-grid">
      <div class="info-cell info-label">Operateur :</div>
      <div class="info-cell info-val">${esc(q.nomorga||'')}</div>
      <div class="info-cell info-label">Activite(s) auditee(s) :</div>
      <div class="info-cell info-val">${esc(q.activites_auditees||'')}</div>
      <div class="info-cell info-label">N Audit :</div>
      <div class="info-cell info-val" style="font-weight:700;color:#23408F">${esc(q.num_audit||'')}</div>
      <div class="info-cell info-label">Site / Lieu :</div>
      <div class="info-cell info-val">${esc(q.site_inspection||'')}</div>
      <div class="info-cell info-label">Date prev. :</div>
      <div class="info-cell info-val">${datePrev}</div>
      <div class="info-cell info-label">Date realisation :</div>
      <div class="info-cell info-val">${dateReal}</div>
      <div class="info-cell info-label">Date QRE :</div>
      <div class="info-cell info-val">${dateQre}</div>
      <div class="info-cell" style="grid-column:span 2"></div>
    </div>
  </div>

  <div class="instruction">Veuillez cocher la case correspondant a votre niveau d'appreciation du deroulement de l'audit.</div>

  <div class="section-hdr">
    <div class="section-hdr-label">Preparation de l'Audit</div>
    <div class="section-hdr-col">Tres<br>Bonne</div>
    <div class="section-hdr-col">Bonne</div>
    <div class="section-hdr-col">Mauvaise</div>
    <div class="section-hdr-col">Tres<br>Mauvaise</div>
  </div>
  ${qRow("Quelle est votre appreciation des informations fournies par la notification, notamment sur le mandat et les attentes de l'auditeur envers vous ?", 'prep_notification')}
  ${qRow("Quelle est votre appreciation de la contribution du plan d'audit dans la preparation et le deroulement de l'audit ?", 'prep_plan')}

  <div class="section-hdr" style="margin-top:6px">
    <div class="section-hdr-label">Conduite de l'Audit</div>
    <div class="section-hdr-col">Tres<br>Bonne</div>
    <div class="section-hdr-col">Bonne</div>
    <div class="section-hdr-col">Mauvaise</div>
    <div class="section-hdr-col">Tres<br>Mauvaise</div>
  </div>
  ${qRow("Quelle est votre appreciation du deroulement la reunion d'ouverture ?", 'cond_ouverture')}
  ${qRow("Quelle est votre appreciation de la qualite des entretiens, notamment en ce qui concerne la coherence et la clarte des questions posees ?", 'cond_entretiens')}
  ${qRow("Quelle est votre appreciation de la connaissance de vos procedures par l'inspecteur ?", 'cond_procedures')}
  ${qRow("Quelle est votre appreciation des qualites generales (professionnalisme, neutralite, impartialite, ecoute, etc) chez l'inspecteur ?", 'cond_qualites')}
  ${qRow("Quelle est votre appreciation de la qualite de la communication durant l'audit ?", 'cond_communication')}
  ${qRow("Quelle est votre appreciation du mode de classification des constats d'audit ?", 'cond_classification')}
  ${qRow("Quelle est votre appreciation de la pertinence des observations relevees ?", 'cond_pertinence')}
  ${qRow("Quelle est votre appreciation du deroulement de la reunion de cloture, notamment la restitution des resultats de l'audit ?", 'cond_cloture')}

  <div class="autres-hdr" style="margin-top:6px">Autres Appreciations</div>
  <div class="autres-box">${esc(q.autres_appreciations||'')}</div>

  <div class="footer">
    <p>Nous vous remercions pour la cooperation et vous prions de retourner ce questionnaire a l'adresse suivante : <a href="mailto:qmanac@anac-gabon.com">qmanac@anac-gabon.com</a></p>
    <p style="margin-top:4px">Les reponses recues feront l'objet d'analyse periodique afin de determiner les opportunites d'amelioration de l'activite d'audit des processus de certification et de surveillance des operateurs du secteur aerien.</p>
  </div>
  <div class="footer-addr">BP 2212 Libreville (GABON) - Tel.: (241) 01 44 54 00 - Fax: (241) 01 44 54 01 - Email: anac@anac-gabon.com - www.anacgabon.org</div>
</div>
</body></html>`;
}

function formatDate(s){ if(!s||s==='0000-00-00') return '-'; const p=String(s).substring(0,10).split('-'); return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:s; }

function openPdfWindow(html){
  const w=window.open('','_blank','width=900,height=750');
  w.document.write(html); w.document.close();
  w.focus(); setTimeout(function(){ w.print(); }, 800);
}

$('#btnPrintView').on('click',function(){
  const content=document.getElementById('qreViewPrint');
  if(!content||!content._qreData){ Swal.fire({icon:'info',text:'Chargez d\'abord un QRE.',confirmButtonColor:'#23408F'}); return; }
  openPdfWindow(buildQrePdf(content._qreData, BANER));
});

$('#btnPrintForm').on('click',function(){
  // Impression formulaire vierge fidelement au modele
  const fakeQ={
    nomorga:    $('#q_nomorga').val(),
    num_audit:  ALL.find(function(a){return String(a.idaudit)===$('#q_idaudit').val();})?.num_audit || '',
    activites_auditees: $('#q_activites').val(),
    site_inspection: $('#q_lieu').val(),
    date_previsionnelle: null, date_realisation: null,
    date_qre: $('#q_date').val(),
    prep_notification:'', prep_plan:'',
    cond_ouverture:'', cond_entretiens:'', cond_procedures:'',
    cond_qualites:'', cond_communication:'', cond_classification:'',
    cond_pertinence:'', cond_cloture:'',
    autres_appreciations: $('#q_autres').val(),
  };
  // Recuperer les radios coches
  ['prep_notification','prep_plan','cond_ouverture','cond_entretiens','cond_procedures','cond_qualites','cond_communication','cond_classification','cond_pertinence','cond_cloture'].forEach(function(n){
    fakeQ[n]=$('input[name="'+n+'"]:checked').val()||'';
  });
  openPdfWindow(buildQrePdf(fakeQ, BANER));
});

/* ===== SUPPRIMER ===== */
$(document).on('click','.btn-del-qre',function(){
  const idqre=$(this).data('idqre');
  Swal.fire({icon:'warning',title:'Supprimer ce QRE ?',showCancelButton:true,confirmButtonText:'Supprimer',cancelButtonText:'Annuler',confirmButtonColor:'#D32F2F'}).then(function(r){
    if(!r.isConfirmed) return;
    apiPost({action:'delete',idqre:idqre}).done(function(res){
      if(res.success){ Swal.fire({icon:'success',timer:1400,showConfirmButton:false}); loadList(); loadStats(); }
      else Swal.fire({icon:'error',text:res.message,confirmButtonColor:'#23408F'});
    });
  });
});

/* ===== DEMARRAGE ===== */
loadList(); loadStats();
</script>