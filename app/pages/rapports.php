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
    : 'Établissez le rapport IX-GEN-R3-F-I-009 en le saisissant en ligne ou en joignant un fichier.';
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
.rap-choix-tile{border:2px solid #e2e8f0;border-radius:14px;padding:20px 14px;text-align:center;cursor:pointer;transition:all .18s;height:100%;display:flex;flex-direction:column;align-items:center;gap:8px}
.rap-choix-tile:hover{border-color:#23408F;background:#f7f9fc;transform:translateY(-3px);box-shadow:0 8px 20px rgba(35,64,143,.12)}
.rap-choix-tile:focus{outline:3px solid rgba(35,64,143,.3)}
.rap-choix-tile.tile-locked{opacity:.45;cursor:not-allowed;filter:grayscale(1);pointer-events:auto}
.rap-choix-tile.tile-locked:hover{border-color:#e2e8f0;background:#fff;transform:none;box-shadow:none}
.badge-approuve{display:inline-flex;align-items:center;gap:4px;background:#e7f6ee;color:#1E7A3E;border:1px solid #bfe6cc;border-radius:20px;padding:3px 10px;font-size:.76rem;font-weight:700}
.rct-ico{width:56px;height:56px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.6rem}
.rct-title{font-weight:800;color:#2C3E50;font-size:1rem}
.rct-desc{font-size:.78rem;color:#6b7a90;line-height:1.3}
/* Editeur du rapport */
.rap-sec{background:#fff;border-radius:12px;padding:16px 18px;margin-bottom:14px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
.rap-sec-h{font-weight:800;color:#23408F;font-size:.95rem;margin-bottom:10px;padding-bottom:6px;border-bottom:2px solid #eef3fb}
.rap-sec-sub{font-weight:600;color:#2C3E50;margin:10px 0 6px}
.rap-checks{display:flex;flex-wrap:wrap;gap:8px 16px}
.rap-check{font-size:.82rem;color:#6b7a90;display:inline-flex;align-items:center;gap:4px}
.rap-check.on{color:#23408F;font-weight:700}
.rap-check.on i{color:#1E9C4B}
.rap-note{margin-top:10px;font-size:.78rem;color:#6b7a90;background:#f7f9fc;border-left:3px solid #23408F;padding:6px 10px;border-radius:6px}
.rap-lbl{font-weight:600;color:#2C3E50;font-size:.84rem;margin-bottom:3px}
.rap-info-todo{background:#fff8e6;border:1px solid #f3e2a8;border-radius:10px;padding:12px 14px;font-size:.84rem;color:#8a6d00}
.btn-voir-chk{background:linear-gradient(135deg,#1E9C4B,#157a3a);color:#fff;border:none;border-radius:8px;padding:5px 9px;font-size:.78rem;font-weight:700;display:inline-flex;align-items:center;gap:4px;cursor:pointer;transition:all .15s;text-decoration:none;}
.btn-voir-chk:hover{background:linear-gradient(135deg,#157a3a,#0f5c2b);color:#fff;transform:translateY(-1px);}
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
    <div class="howto-step"><div class="step-num">1</div><div><strong>Éligibilité.</strong> Seuls les audits dont la lettre de notification est jointe apparaissent. Complétez d'abord le module Notifications si un audit manque.</div></div>
    <div class="howto-step"><div class="step-num">2</div><div><strong>Choix de la méthode (irréversible).</strong> Cliquez sur "Rapport", puis choisissez <strong>Saisir en ligne</strong> (remplir le formulaire dans l'application) ou <strong>Joindre un fichier</strong> (téléverser un PDF/Word déjà rédigé). Une fois un choix fait pour un audit, l'autre option est verrouillée : les inspecteurs doivent s'accorder au préalable.</div></div>
    <div class="howto-step"><div class="step-num">3</div><div><strong>Critères par domaine.</strong> Chaque inspecteur saisit les critères (NCE, NCS, NCNS, NCNE, NCNA) <strong>de son propre domaine</strong>. L'en-tête du rapport (destinataires, référentiels, périmètre...) est commune : chacun peut la compléter. Le total NCR et les taux se calculent automatiquement.</div></div>
    <div class="howto-step"><div class="step-num">4</div><div><strong>Automatisations.</strong> À la première sauvegarde, le statut de l'audit passe à <strong>Effectué</strong>, la date de réalisation est enregistrée et le délai d'exécution est calculé. Les NCNS alimentent l'ouverture des fiches de non-conformité.</div></div>
    <div class="howto-step"><div class="step-num">5</div><div><strong>Approbation du chef inspecteur.</strong> Quand le rapport est complet, le <strong>chef inspecteur</strong> l'approuve via le bouton dédié. Une fois approuvé, le rapport est verrouillé : plus aucune modification n'est possible, seule la consultation du PDF reste disponible.</div></div>
  </div>
</div>
<?php endif; ?>

<!-- Toggle dashboard -->
<div class="d-flex justify-content-between align-items-center mb-2">
  <button class="btn btn-sm" id="btnToggleDash" style="background:linear-gradient(135deg,#23408F,#1b3576);color:#fff;font-weight:600;box-shadow:0 1px 3px rgba(35,64,143,.25)">
    <i class="bi bi-graph-up-arrow me-1"></i><span id="dashLbl">Afficher les statistiques</span>
    <i class="bi bi-chevron-down ms-1" id="dashChevron" style="transition:transform .2s"></i>
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
  <div class="row g-3 mb-4">
    <div class="col-md-7">
      <!-- Hauteur augmentee : les libellés de l'axe des abscisses etaient masques
           par la rangee de cartes situee juste en dessous. -->
      <div class="chart-box" style="height:340px;padding-bottom:26px">
        <div class="chart-title"><i class="bi bi-bar-chart-steps me-1"></i>Repartition des criteres - Diagramme en batons (NCE/NCS/NCNS/NCNE/NCNA)</div>
        <div style="height:calc(100% - 34px)"><canvas id="chartCriteresBar"></canvas></div>
      </div>
    </div>
    <div class="col-md-5">
      <div class="chart-box" style="height:340px;padding-bottom:26px">
        <div class="chart-title"><i class="bi bi-pie-chart me-1"></i>Repartition des criteres - Camembert (%) NCE/NCS/NCNS/NCNE/NCNA</div>
        <div style="height:calc(100% - 34px)"><canvas id="chartCriteresPie"></canvas></div>
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
      <th title="Detail des criteres et taux" style="min-width:190px">Criteres (NCE/NCS/NCNS/NCNE/NCNA)</th><th>Rapport</th>
      <th style="text-align:right" class="col-actions">Actions</th>
    </tr></thead>
    <tbody id="tbody"><tr><td colspan="11" class="empty"><span class="spinner-border spinner-border-sm me-2"></span>Chargement...</td></tr></tbody>
  </table>
</div>

<!-- MODALE : Choix du mode de rapport (Joindre un fichier / Saisir en ligne) -->
<div class="modal fade" id="rapChoixModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,#23408F,#1b3576)">
        <h5 class="modal-title text-white"><i class="bi bi-file-earmark-text me-2" style="color:#F3C300"></i>Rapport d'acte de supervision</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <p class="text-muted mb-4" style="font-size:.9rem">Comment souhaitez-vous etablir le rapport pour l'audit <b id="rapChoixNum" style="color:#23408F"></b> ?</p>
        <div class="row g-3">
          <div class="col-6">
            <div class="rap-choix-tile" id="rapChoixSaisir" role="button" tabindex="0">
              <div class="rct-ico" style="background:#e8f0fe"><i class="bi bi-pencil-square" style="color:#23408F"></i></div>
              <div class="rct-title">Saisir en ligne</div>
              <div class="rct-desc">Remplir le formulaire directement dans l'application, avec mise en forme.</div>
            </div>
          </div>
          <div class="col-6">
            <div class="rap-choix-tile" id="rapChoixJoindre" role="button" tabindex="0">
              <div class="rct-ico" style="background:#eafaf0"><i class="bi bi-paperclip" style="color:#1E9C4B"></i></div>
              <div class="rct-title">Joindre un fichier</div>
              <div class="rct-desc">Televerser un rapport deja redige (PDF ou Word).</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- MODALE EDITEUR RAPPORT (saisie en ligne) -->
<div class="modal fade" id="rapEditModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,#23408F,#1b3576)">
        <h5 class="modal-title text-white"><i class="bi bi-file-earmark-text me-2" style="color:#F3C300"></i>Rapport d'acte de supervision <span id="re_num" class="ms-2" style="font-weight:600;opacity:.85"></span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="re_body" style="background:#f5f7fa">
        <div class="text-center text-muted p-4"><span class="spinner-border spinner-border-sm me-2"></span>Chargement...</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fermer</button>
        <button type="button" class="btn btn-anac" id="re_save"><i class="bi bi-save me-1"></i>Enregistrer</button>
      </div>
    </div>
  </div>
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
        <!-- DOCUMENTS A JOINDRE -->
        <div style="border:1px solid #23408F;border-radius:10px;overflow:hidden;margin-bottom:16px">
          <div style="background:#23408F;color:#fff;padding:8px 14px;font-weight:700;font-size:.85rem">
            <i class="bi bi-paperclip me-2" style="color:#F3C300"></i>Documents a joindre
          </div>
          <div style="padding:14px">

            <div class="mb-2 p-2" style="background:#eef3fb;border-left:4px solid #23408F;border-radius:8px;font-size:.79rem;color:#33507f">
              <i class="bi bi-lightbulb-fill me-1" style="color:#23408F"></i>
              Deux documents distincts sont attendus : le <strong>rapport d'acte de supervision</strong>,
              signe par le Directeur General de l'ANAC, et les <strong>listes de verification</strong>,
              signees par chaque inspecteur de l'equipe.
            </div>

            <!-- 1. Rapport -->
            <div class="mb-3 p-3" style="border:1px solid #e2e8f0;border-radius:10px;background:#fff">
              <div class="d-flex align-items-center gap-2 mb-1">
                <span style="background:#23408F;color:#fff;width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.74rem;font-weight:800">1</span>
                <label class="form-label fw-bold mb-0">Rapport d'acte de supervision <span class="text-danger">*</span></label>
              </div>
              <div style="font-size:.76rem;color:#6b7a90;margin:0 0 7px 30px">
                Document signe par le <strong>Directeur General de l'ANAC</strong>.
              </div>
              <input type="file" class="form-control" id="rap_fichier" name="fichier_rapport" accept=".pdf,.doc,.docx" required>
              <div class="form-text"><i class="bi bi-info-circle me-1 text-success"></i>PDF, DOC ou DOCX - Pas de limite de taille.</div>
            </div>

            <!-- 2. Listes de verification -->
            <div class="p-3" style="border:1px solid #e2e8f0;border-radius:10px;background:#fff">
              <div class="d-flex align-items-center gap-2 mb-1">
                <span style="background:#1E9C4B;color:#fff;width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.74rem;font-weight:800">2</span>
                <label class="form-label fw-bold mb-0">Listes de verification signees (checklists)</label>
              </div>
              <div style="font-size:.76rem;color:#6b7a90;margin:0 0 7px 30px">
                En tant que responsable d'audit, rassemblez les listes de verification
                <strong>signees par chaque inspecteur</strong> ayant participe a l'acte,
                scannez-les en <strong>un seul document</strong> et deposez-le ici.
              </div>
              <input type="file" class="form-control" id="rap_checklist" name="fichier_checklist" accept="application/pdf">
              <div class="form-text">
                <i class="bi bi-shield-check me-1 text-success"></i>
                <strong>PDF uniquement</strong>, ce sont des documents signes - Pas de limite de taille.
                Un nouveau depot remplace le precedent.
              </div>
              <div id="rap_checklist_actuel" class="mt-2" style="display:none">
                <span class="badge" style="background:#e8f5ec;color:#157a3a;font-weight:600">
                  <i class="bi bi-check-circle me-1"></i>Un document est deja joint</span>
                <button type="button" class="btn btn-sm btn-outline-primary ms-1" id="btnVoirChecklist">
                  <i class="bi bi-eye me-1"></i>Consulter</button>
              </div>
            </div>

          </div>
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
                <div style="font-size:.72rem;color:#555">( NCS / (NCS + NCNS) ) x 100</div>
              </div>
              <div class="taux-item taux-nonconf">
                <div style="font-size:.78rem;font-weight:700;color:#D32F2F;margin-bottom:4px">Taux de non-conformite</div>
                <div id="tnc_val" style="font-size:1.5rem;font-weight:800;color:#D32F2F">- %</div>
                <div style="font-size:.72rem;color:#555">( NCNS / (NCS + NCNS) ) x 100</div>
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
<?php if ($isOper): ?>
// Masquer la colonne Actions pour le role operateur (lecture seule)
document.write('<style>.col-actions{display:none !important;}</style>');
<?php endif; ?>
let ALL=[], ALL_R=[], chartBarOrga=null, chartPie=null, chartAnnee=null, chartType=null;

function apiPost(data){ return $.post(API, Object.assign({csrf_token:CSRF}, data), null, 'json'); }
function fmtDate(s){ if(!s||s==='0000-00-00'||s===null) return '-'; const p=String(s).substring(0,10).split('-'); return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:s; }
const TYPES={audit:'Audit',inspection_programmee:'Insp. prog.',inspection_non_programmee:'Insp. non prog.',demonstration:'Demo',test:'Test',investigation:'Investigation'};
const STATUT={1:{t:'Planifie',c:'s1'},3:{t:'Effectue',c:'s3'},2:{t:'Reporte',c:''},4:{t:'Suspendu',c:''}};

/* ===== RAPPORT : referentiels d'affichage ===== */
// Libelles complets des types d'acte et cadres (pour les cases a cocher du rapport)
const TYPES_FULL={audit:'Audit',inspection_programmee:'Inspection programmee',inspection_non_programmee:'Inspection non programmee',demonstration:'Demonstration',test:'Test',investigation:'Investigation'};
const CADRES_LBL={certification:'Certification',homologation:'Homologation',reconnaissance:'Reconnaissance',renouvellement:'Renouvellement',surveillance_continue:'Surveillance continue',traitement_evenement:'Traitement d\'un evenement',fermeture_provisoire:'Fermeture provisoire',fermeture_definitive:'Fermeture definitive',delivrance_autorisation:'Delivrance d\'une autorisation'};
// Liste deroulante des codes d'ampliation ANAC (fournie par le CI)
const AMPLIATION_ANAC=['DG','DG-DD','DG-DC','DG-CE','DG-DZ','DG-DA','DG-YY','DG-IX','DG-XD','DG-XA','DG-XZ','IX-OPS','IX-AIR','IX-AVS','IX-FAC','IX-ANS','IX-AGA','IX-PEL','IX-OCV','IX-MDA','DG-QM','DG-QD','DG-QA','DG-QZ','QM-QUA','QM-SEC','DG-CD','DG-CZ','CD-COM','CD-REP','CD-DOB','DG-PE','DG-ED','DG-EZ','DG-IQ','DD-COU','DD-DR','DG-RD','DG-RZ','DR-DN','DR-DS','DR-DE','DR-DO','DE-ED','DE-EZ','DE-EL','EL-PEL','EL-FOR','DE-EM','DE-EX','EX-OPS','EX-MDA','DE','DNA','DN-AD','DN-AZ','NA-ATS','NA-CNS','NA-AIS','NA-PAN','NA-SAR-MET','DN','DN-ND','DN-NZ','DN-NN','NN-AIR','NN-IMA','DN-NM','DU','DU-UD','DU-UZ','DU-US','DU-UF','DA','DA-AD','DA-AZ','DA-AP','AP-EGA','AP-GPB','AP-EIS','DA-AG','DA-AE','DJ','DJ-JD','DJ-JZ','DJ-JR','DJ-JS','DJ-JJ','DTA','DT-TD','DT-TZ','DRH','DH-HD','DH-HZ','DF','DF-FD','DF-FZ','DF-FA','FA-ADM','FA-MGX','DF-FH','FH-GRH','FH-ADP','DF-FC','FC-FIN','FC-CPT'];

/* ===== GUIDE ===== */
let guideVisible=true;
$('#btnToggleGuide').on('click',function(){ guideVisible=!guideVisible; $('#guideBody').toggle(guideVisible); $('#guideLbl').text(guideVisible?'Masquer':'Afficher'); });

/* ===== TOGGLE DASHBOARD ===== */
let dashVisible=false;
function setDash(show){
  dashVisible=show; $('#dashPanel').toggle(show);
  $('#dashLbl').text(show?'Masquer les statistiques':'Afficher les statistiques');
  $('#dashChevron').css('transform', show?'rotate(180deg)':'rotate(0deg)');
  try{localStorage.setItem('agai_dash_rapports',show?'1':'0');}catch(e){}
  if(show&&ALL_R.length) renderDash(getFiltered_R());
}

/* Le bouton n'etait relie a aucun gestionnaire : le tableau de bord ne pouvait
   ni s'ouvrir ni se fermer, quel que soit le role. */
$('#btnToggleDash').on('click', function(){ setDash(!dashVisible); });

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
    // Aide au diagnostic : des rapports peuvent etre joints sans criteres NCE/NCS/NCNS
    // renseignes ; le tableau de bord est alors vide alors que la liste est fournie.
    if(dashVisible && !ALL_R.length){
      $('#dashPanel .dash-empty-note').remove();
      $('#dashPanel').prepend(
        '<div class="alert dash-empty-note" style="background:#fff8e1;border-left:4px solid #F3C300;border-radius:8px;font-size:.85rem">'
        +'<i class="bi bi-info-circle-fill me-1" style="color:#9a7d00"></i>'
        +'Aucun rapport ne comporte encore de criteres evalues (NCE / NCS / NCNS). '
        +'Les indicateurs et graphiques se calculent a partir de ces criteres : renseignez-les depuis la fiche d\'un rapport.</div>'
      );
    } else { $('#dashPanel .dash-empty-note').remove(); }
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
/* Colonne fusionnee : detail des criteres (NCE/NCS/NCNS/NCNE/NCNA) + taux.
   Les 5 valeurs sont presentees en pastilles, suivies des deux taux calcules. */
function criteresCell(a){
  const nce=Number(a.nce||0), ncs=Number(a.ncs||0), ncns=Number(a.ncns||0),
        ncne=Number(a.ncne||0), ncna=Number(a.ncna||0), ncr=Number(a.ncr||0);
  if(ncr===0 && nce===0 && ncs===0 && ncns===0){
    return '<span class="text-muted small">Non renseigne</span>';
  }
  // Taux : on privilegie les valeurs stockees, sinon recalcul NCS/(NCS+NCNS).
  const base=ncs+ncns;
  let tc = (a.taux_conformite!=null && a.taux_conformite!=='') ? parseFloat(a.taux_conformite) : (base>0?(ncs/base*100):null);
  let tnc= (a.taux_non_conformite!=null && a.taux_non_conformite!=='') ? parseFloat(a.taux_non_conformite) : (base>0?(ncns/base*100):null);
  const pill=function(lbl,val,color,bg){
    return '<span title="'+lbl+'" style="display:inline-block;background:'+bg+';color:'+color+';border-radius:5px;padding:1px 6px;font-size:.7rem;font-weight:700;margin:1px">'+lbl+' '+val+'</span>';
  };
  let h='<div style="display:flex;flex-wrap:wrap;gap:1px;margin-bottom:3px">'
    +pill('NCE',nce,'#23408F','#e8f0fe')
    +pill('NCS',ncs,'#1E9C4B','#d1fae5')
    +pill('NCNS',ncns,'#D32F2F','#fee2e2')
    +pill('NCNE',ncne,'#b58a00','#fef3c7')
    +pill('NCNA',ncna,'#6b7a90','#eef1f6')
    +'</div>';
  h+='<div style="font-size:.68rem;color:#5b6b85">'
    +'NCR : <b>'+ncr+'</b> &nbsp;|&nbsp; '
    +'Conf. : '+(tc!=null?('<b style="color:#1E9C4B">'+tc.toFixed(1)+'%</b>'):'-')+' &nbsp; '
    +'Non-conf. : '+(tnc!=null?('<b style="color:#D32F2F">'+tnc.toFixed(1)+'%</b>'):'-')
    +'</div>';
  return h;
}
function rapTag(a){
  const has=a.rapport_audit&&String(a.rapport_audit).trim();
  const saisi=(a.rapport_methode||'')==='saisie';
  if(has){
    return '<span class="tag-ok"><i class="bi bi-file-earmark-check"></i>Joint</span>'+(a.date_delivrance_rapport?'<div class="text-muted small mt-1">'+fmtDate(a.date_delivrance_rapport)+'</div>':'');
  }
  if(saisi){
    // Rapport saisi en ligne : lien direct vers l'apercu PDF
    return '<a href="'+AGAI_BASE+'/rapport-pdf?audit='+encodeURIComponent(a.idaudit)+'" target="_blank" class="tag-ok" style="text-decoration:none" title="Voir le rapport (PDF)"><i class="bi bi-file-earmark-text"></i>Saisi</a>'
      +(a.date_realisation?'<div class="text-muted small mt-1">'+fmtDate(a.date_realisation)+'</div>':'');
  }
  return '<span class="tag-non"><i class="bi bi-hourglass-split"></i>En attente</span>';
}
function actionsHtml(a){
  const has=a.rapport_audit&&String(a.rapport_audit).trim();
  const methode=(a.rapport_methode||'');            // '', 'saisie' ou 'joindre'
  const approuve=String(a.rapport_approuve||'0')==='1';
  let h='';
  if(has){
    h+='<a href="javascript:void(0)" class="btn-voir-rap me-1 btn-pdf-rap" data-audit="'+esc(a.idaudit)+'" data-num="'+esc(a.num_audit)+'" title="Consulter le rapport signe par le DG"><i class="bi bi-file-earmark-text"></i></a>';
  }
  if(a.checklist_signee && String(a.checklist_signee).trim()){
    h+='<a href="javascript:void(0)" class="btn-voir-chk me-1 btn-chk-rap" data-audit="'+esc(a.idaudit)+'" data-num="'+esc(a.num_audit)+'" title="Consulter les listes de verification signees"><i class="bi bi-list-check"></i></a>';
  }
  if(approuve){
    // Rapport approuve par le CI : plus de modification possible.
    // Seuls restent l'apercu (PDF) et un badge d'approbation.
    h+='<span class="badge-approuve me-1" title="Rapport approuve par le chef inspecteur"><i class="bi bi-patch-check-fill"></i> Approuve</span>';
  } else {
    // Bouton "Rapport" : la modale propose Saisir / Joindre.
    // La methode deja utilisee verrouille l'autre option (choix irreversible).
    h+='<button class="'+(has?'btn-remplacer-rap':'btn-joindre-rap')+' btn-choix-rap me-1" data-id="'+esc(a.idaudit)+'" data-num="'+esc(a.num_audit)+'" data-orga="'+esc(a.nomorga)+'" data-has="'+(has?'1':'0')+'" data-methode="'+esc(methode)+'" title="Etablir ou joindre le rapport d\'acte de supervision">'
      +'<i class="bi bi-file-earmark-text"></i>Rapport</button>';
    // Bouton "Approuver" reserve au CI/admin, visible seulement si un rapport existe (saisie ou joint)
    if(IS_CI && (has || methode==='saisie')){
      h+='<button class="btn-approuver-rap me-1" data-id="'+esc(a.idaudit)+'" data-num="'+esc(a.num_audit)+'" title="Approuver definitivement le rapport (verrouillage)" style="background:#1E9C4B;color:#fff;border:none;border-radius:8px;padding:4px 9px;font-size:.78rem;font-weight:600;display:inline-flex;align-items:center;gap:4px;cursor:pointer">'
        +'<i class="bi bi-patch-check"></i>Approuver</button>';
    }
  }
  // Bouton PDF (Imprimer le rapport PDF) : visible UNIQUEMENT si l'option
  // "Saisir" a ete choisie. Si le rapport a ete joint, ce bouton disparait.
  if(methode==='saisie'){
    h+='<button class="btn-pdf-online" data-id="'+esc(a.idaudit)+'" title="Imprimer le rapport saisi (PDF)" style="background:#D32F2F;color:#fff;border:none;border-radius:8px;padding:4px 9px;font-size:.78rem;font-weight:600;display:inline-flex;align-items:center;gap:4px;cursor:pointer">'
      +'<i class="bi bi-file-earmark-pdf"></i>PDF</button>';
  }
  return '<div style="text-align:right;white-space:nowrap;display:flex;align-items:center;gap:3px;justify-content:flex-end">'+h+'</div>';
}
let MODE_REMPLACE = false;   // true : les documents deviennent facultatifs

/* Delai d'execution : valeur signee en jours.
   Negatif = realise en avance, positif = en retard, zero = a la date prevue. */
function fmtDelai(v){
  if(v===null || v===undefined || v==='') return '<span class="text-muted">-</span>';
  const j = parseInt(v,10);
  if(isNaN(j)) return '<span style="font-size:.75rem;color:#7b8aa0">'+esc(String(v))+'</span>';
  if(j === 0) return '<span style="font-size:.74rem;font-weight:700;color:#1E9C4B">Dans les temps</span>';
  if(j < 0)   return '<span style="font-size:.74rem;font-weight:700;color:#1E9C4B" title="Realise avant la date prevue">'+Math.abs(j)+' j d\'avance</span>';
  return '<span style="font-size:.74rem;font-weight:700;color:#D32F2F" title="Realise apres la date prevue">'+j+' j de retard</span>';
}

function render(){
  const list=getFiltered();
  $('#resCount').html('<i class="bi bi-file-earmark-text me-1"></i>'+list.length+' audit(s)'
    + '<span style="margin-left:14px;font-size:.74rem;color:#6b7a90">'
    +   '<span style="display:inline-flex;align-items:center;gap:4px;margin-right:10px">'
    +     '<span style="width:11px;height:11px;border-radius:3px;background:linear-gradient(135deg,#23408F,#1b3576);display:inline-block"></span>Rapport signe (DG)</span>'
    +   '<span style="display:inline-flex;align-items:center;gap:4px;margin-right:10px">'
    +     '<span style="width:11px;height:11px;border-radius:3px;background:linear-gradient(135deg,#1E9C4B,#157a3a);display:inline-block"></span>Listes de verification signees</span>'
    +   '<span style="display:inline-flex;align-items:center;gap:4px">'
    +     '<span style="width:11px;height:11px;border-radius:3px;background:linear-gradient(135deg,#b58a00,#9a7500);display:inline-block"></span>Remplacer / corriger</span>'
    + '</span>');
  if(!list.length){
    $('#tbody').html('<tr><td colspan="11" class="empty"><i class="bi bi-inbox me-2"></i>Aucun audit eligible.'+(ALL.length?'<br><small class="text-muted">Modifiez les filtres.</small>':'<br><small class="text-muted">Les audits apparaissent apres que la lettre de notification ait ete jointe.</small>')+'</td></tr>');
    return;
  }
  $('#tbody').html(list.map(function(a){
    const st=STATUT[a.statut]||{t:'-',c:''};
    const del=fmtDelai(a.delai_execution);
    return '<tr>'
      +'<td><b style="color:#23408F;font-size:.87rem">'+esc(a.num_audit||'')+'</b></td>'
      +'<td style="font-size:.82rem">'+esc(TYPES[a.type_activite]||'')+'</td>'
      +'<td style="font-weight:600;font-size:.82rem">'+esc(a.nomorga||'-')+'</td>'
      +'<td style="font-size:.82rem;color:#D32F2F;font-weight:600">'+esc(a.ra_nom||'-')+'</td>'
      +'<td style="font-size:.82rem">'+fmtDate(a.date_previsionnelle)+'</td>'
      +'<td style="font-size:.82rem">'+fmtDate(a.date_realisation)+'</td>'
      +'<td>'+del+'</td>'
      +'<td><span class="s-badge '+(st.c||'')+'">'+esc(st.t)+'</span></td>'
      +'<td>'+criteresCell(a)+'</td>'
      +'<td>'+rapTag(a)+'</td>'
      +'<td class="col-actions">'+actionsHtml(a)+'</td>'
      +'</tr>';
  }).join(''));
}
function loadList(){
  apiPost({action:'list'}).done(function(res){
    if(!res.success){ $('#tbody').html('<tr><td colspan="11" class="empty">'+esc(res.message||'Erreur')+'</td></tr>'); return; }
    ALL=res.data||[]; fillTableFilters(); render();
  }).fail(function(){ $('#tbody').html('<tr><td colspan="11" class="empty">Echec.</td></tr>'); });
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

// Bouton PDF (liste) : ouvre la vue impression du rapport
$(document).on('click','.btn-pdf-online',function(){
  const id=$(this).attr('data-id');
  window.open(AGAI_BASE + '/rapport-pdf?audit=' + encodeURIComponent(id), '_blank');
});

/* ===== MODALE CHOIX (Joindre / Saisir) ===== */
let RAP_CHOIX = {id:'', num:'', orga:'', has:false};
$(document).on('click','.btn-choix-rap',function(){
  // .attr() plutot que .data() : evite le cache jQuery qui pouvait renvoyer
  // une valeur d'une ligne precedente.
  RAP_CHOIX = {
    id:$(this).attr('data-id'), num:$(this).attr('data-num'),
    orga:$(this).attr('data-orga'), has:String($(this).attr('data-has'))==='1',
    methode:$(this).attr('data-methode')||''
  };
  $('#rapChoixNum').text(RAP_CHOIX.num||'');

  // Verrouillage du choix : si une methode a deja ete utilisee pour cet audit,
  // l'autre option est grisee et un message rouge l'explique (choix irreversible).
  const $tSaisir=$('#rapChoixSaisir'), $tJoindre=$('#rapChoixJoindre');
  $tSaisir.removeClass('tile-locked'); $tJoindre.removeClass('tile-locked');
  $('#rapChoixLockMsg').remove();
  if(RAP_CHOIX.methode==='saisie'){
    $tJoindre.addClass('tile-locked');
    $('#rapChoixModal .modal-body').append('<div id="rapChoixLockMsg" class="mt-2 p-2" style="background:#fdecea;border:1px solid #f5c6cb;border-radius:8px;color:#D32F2F;font-size:.82rem"><i class="bi bi-lock-fill me-1"></i>Cet audit a ete etabli en <b>saisie en ligne</b>. L\'option Joindre est desormais indisponible (choix irreversible).</div>');
  } else if(RAP_CHOIX.methode==='joindre'){
    $tSaisir.addClass('tile-locked');
    $('#rapChoixModal .modal-body').append('<div id="rapChoixLockMsg" class="mt-2 p-2" style="background:#fdecea;border:1px solid #f5c6cb;border-radius:8px;color:#D32F2F;font-size:.82rem"><i class="bi bi-lock-fill me-1"></i>Cet audit a ete etabli par <b>rapport joint</b>. L\'option Saisir en ligne est desormais indisponible (choix irreversible).</div>');
  }

  new bootstrap.Modal('#rapChoixModal').show();
});
// Choix : Joindre un fichier -> ouvre le formulaire d'upload existant
$('#rapChoixJoindre').on('click',function(){
  if($(this).hasClass('tile-locked')){
    Swal.fire({icon:'info',title:'Option indisponible',text:'Cet audit a ete etabli en saisie en ligne. Vous ne pouvez plus joindre un rapport.',confirmButtonColor:'#23408F'});
    return;
  }
  bootstrap.Modal.getInstance(document.getElementById('rapChoixModal')).hide();
  const c = RAP_CHOIX;
  setTimeout(function(){ openUploadRap(c.id, c.num, c.orga, c.has); }, 300);
});
// Choix : Saisir en ligne -> page dediee (comme la revue documentaire)
$('#rapChoixSaisir').on('click',function(){
  if($(this).hasClass('tile-locked')){
    Swal.fire({icon:'info',title:'Option indisponible',text:'Cet audit a ete etabli par rapport joint. Vous ne pouvez plus le saisir en ligne.',confirmButtonColor:'#23408F'});
    return;
  }
  bootstrap.Modal.getInstance(document.getElementById('rapChoixModal')).hide();
  window.location = AGAI_BASE + '/rapport-saisie?audit=' + encodeURIComponent(RAP_CHOIX.id);
});
$('#rapChoixSaisir,#rapChoixJoindre').on('keypress',function(e){ if(e.which===13||e.which===32){ e.preventDefault(); $(this).trigger('click'); } });

// ===== APPROBATION DU RAPPORT (reservee CI/admin) =====
$(document).on('click','.btn-approuver-rap',function(){
  const id=$(this).attr('data-id'), num=$(this).attr('data-num')||'';
  Swal.fire({
    icon:'warning',
    title:'Approuver le rapport ?',
    html:'<div style="text-align:left;font-size:.9rem">Vous allez <b>approuver definitivement</b> le rapport de l\'audit <b>'+esc(num)+'</b>.<br><br>'
        +'<span style="color:#D32F2F"><i class="bi bi-exclamation-triangle-fill me-1"></i>Cette action est irreversible.</span> '
        +'Une fois approuve, plus aucune saisie ni modification ne sera possible. Seul l\'apercu PDF restera accessible.</div>',
    showCancelButton:true,
    confirmButtonText:'<i class="bi bi-patch-check me-1"></i>Approuver',
    cancelButtonText:'Annuler',
    confirmButtonColor:'#1E9C4B',
    cancelButtonColor:'#6b7a90'
  }).then(function(res){
    if(!res.isConfirmed) return;
    apiPost({action:'approuver_rapport', idaudit:id})
      .done(function(r){
        if(r && r.success){
          Swal.fire({icon:'success',title:'Rapport approuve',timer:1400,showConfirmButton:false});
          loadList(); if(typeof loadStats==='function'){ loadStats(); }
        } else {
          Swal.fire({icon:'error',title:'Erreur',text:(r&&r.message)||'Echec de l\'approbation.',confirmButtonColor:'#D32F2F'});
        }
      })
      .fail(function(){ Swal.fire({icon:'error',title:'Erreur',text:'Echec de l\'approbation.',confirmButtonColor:'#D32F2F'}); });
  });
});

/* ===== EDITEUR DU RAPPORT (saisie en ligne) ===== */
let RAP_EDIT_AUDIT = null;   // audit courant
let RAP_EDIT_DATA  = null;   // donnees du rapport (entete) chargees

function openRapEditor(idaudit, num, orga){
  $('#re_num').text(num ? ('- '+num) : '');
  $('#re_body').html('<div class="text-center text-muted p-4"><span class="spinner-border spinner-border-sm me-2"></span>Chargement du rapport...</div>');
  new bootstrap.Modal('#rapEditModal').show();
  // On recupere l'audit (depuis ALL) + les donnees deja saisies via l'API
  const aud = ALL.find(function(x){ return String(x.idaudit)===String(idaudit); });
  RAP_EDIT_AUDIT = aud || {idaudit:idaudit, num_audit:num, nomorga:orga};
  apiPost({action:'get_rapport', idaudit:idaudit}).done(function(res){
    RAP_EDIT_DATA = (res && res.success) ? (res.rapport||{}) : {};
    const meta = (res && res.success) ? (res.meta||{}) : {};
    buildRapForm(RAP_EDIT_AUDIT, RAP_EDIT_DATA, meta);
  }).fail(function(){
    // L'endpoint n'a peut-etre pas encore l'action : on construit quand meme le socle
    RAP_EDIT_DATA = {};
    buildRapForm(RAP_EDIT_AUDIT, {}, {});
  });
}

/* Construit le formulaire du rapport (socle : en-tetes auto + champs de base) */
function buildRapForm(aud, rap, meta){
  rap = rap||{}; meta = meta||{};
  const typeActe = aud.type_activite||'';
  const cadre    = aud.cadre||'';
  const operateur= aud.nomorga||meta.nomorga||'';
  const activite = meta.activite_operateur||aud.type_activite_operateur||'';
  const dateReal = aud.date_realisation||aud.date_previsionnelle||'';
  const periodeDefaut = rap.periode_texte || (dateReal? ('le '+fmtDate(dateReal)) : '');

  // Cases a cocher : type d'acte
  let typeChecks='';
  Object.keys(TYPES_FULL).forEach(function(k){
    const on=(k===typeActe);
    typeChecks+='<span class="rap-check '+(on?'on':'')+'"><i class="bi '+(on?'bi-check-square-fill':'bi-square')+'"></i> '+esc(TYPES_FULL[k])+'</span>';
  });
  // Cases a cocher : cadre
  let cadreChecks='';
  Object.keys(CADRES_LBL).forEach(function(k){
    const on=(k===cadre);
    cadreChecks+='<span class="rap-check '+(on?'on':'')+'"><i class="bi '+(on?'bi-check-square-fill':'bi-square')+'"></i> '+esc(CADRES_LBL[k])+'</span>';
  });

  // Ampliation ANAC : lignes ajoutables (liste deroulante)
  const amplExist = (rap.ampliation_anac||'').split('\n').map(function(s){return s.trim();}).filter(Boolean);
  const amplLines = amplExist.length? amplExist : [''];

  const html =
   '<div class="rap-sec">'
   +  '<div class="rap-sec-h"><i class="bi bi-flag-fill me-1"></i>Nature de l\'acte</div>'
   +  '<div class="rap-checks">'+typeChecks+'</div>'
   +  '<div class="rap-sec-sub">Dans le cadre :</div>'
   +  '<div class="rap-checks">'+cadreChecks+'</div>'
   +  '<div class="rap-note"><i class="bi bi-info-circle me-1"></i>La nature et le cadre sont repris automatiquement de l\'audit (declenchement PSC).</div>'
   +'</div>'

   +'<div class="rap-sec">'
   +  '<div class="row g-3">'
   +    '<div class="col-md-6"><label class="rap-lbl">Periode</label>'
   +      '<input type="text" class="form-control" id="re_periode" maxlength="255" value="'+esc(periodeDefaut)+'" placeholder="le 25 au 26 Aout 2025">'
   +      '<div class="form-text">Modifiable. Par defaut : date de realisation de l\'audit.</div></div>'
   +    '<div class="col-md-6"><label class="rap-lbl">Operateur</label>'
   +      '<input type="text" class="form-control" value="'+esc(operateur)+'" readonly style="background:#eef3fb"></div>'
   +    '<div class="col-md-6"><label class="rap-lbl">Activite de l\'operateur</label>'
   +      '<input type="text" class="form-control" value="'+esc(activite)+'" readonly style="background:#eef3fb"></div>'
   +  '</div>'
   +'</div>'

   +'<div class="rap-sec">'
   +  '<div class="rap-sec-h"><i class="bi bi-people me-1"></i>Redaction et validation</div>'
   +  '<div class="row g-3">'
   +    '<div class="col-md-4"><label class="rap-lbl">Redacteur</label><select id="re_redacteur" class="form-select">'+inspOptionsRap(meta, rap.id_redacteur)+'</select></div>'
   +    '<div class="col-md-4"><label class="rap-lbl">Verificateur</label><select id="re_verificateur" class="form-select">'+inspOptionsRap(meta, rap.id_verificateur)+'</select></div>'
   +    '<div class="col-md-4"><label class="rap-lbl">Validation</label><input type="text" class="form-control" id="re_validation" value="'+esc(meta.ci_nom||rap.validation_nom||'Chef Inspecteur')+'" readonly style="background:#eef3fb"></div>'
   +    '<div class="col-md-6"><label class="rap-lbl">Fonction</label><input type="text" class="form-control" id="re_fonction" maxlength="255" value="'+esc(rap.fonction_libre||'')+'" placeholder="Saisir la fonction"></div>'
   +  '</div>'
   +'</div>'

   +'<div class="rap-sec">'
   +  '<div class="rap-sec-h"><i class="bi bi-envelope me-1"></i>Destinataires et ampliation</div>'
   +  '<div class="row g-3">'
   +    '<div class="col-md-6"><label class="rap-lbl">Destinataire(s)</label>'
   +      '<textarea class="form-control" id="re_destinataires" rows="4" placeholder="Saisir les destinataires (une entree par ligne)">'+esc(rap.destinataires||'')+'</textarea></div>'
   +    '<div class="col-md-6"><label class="rap-lbl">Ampliation ANAC</label>'
   +      '<div id="re_amplList">'+amplLines.map(amplRowHtml).join('')+'</div>'
   +      '<button type="button" class="btn btn-sm btn-outline-primary mt-1" id="re_amplAdd"><i class="bi bi-plus-lg me-1"></i>Ajouter une ligne</button></div>'
   +  '</div>'
   +'</div>'

   +'<div class="rap-sec">'
   +  '<div class="rap-sec-h"><i class="bi bi-bullseye me-1"></i>Objectifs et perimetre</div>'
   +  rapTextarea('re_objectifs','Objectif(s) vise(s)', rap.objectifs)
   +  rapTextarea('re_sites','Le(s) site(s) geographique(s)', rap.sites_geographiques || (meta.nomsite? meta.nomsite : ''))
   +  rapTextarea('re_unites','Le(s) unite(s) organisationnelle(s)', rap.unites_organisation)
   +  rapTextarea('re_activites','Activites / processus / produits a prendre en consideration', rap.activites_processus)
   +  rapTextarea('re_referentiels','Referentiel(s) opposables a l\'exploitant', rap.referentiels)
   +'</div>'

   +'<div class="rap-sec">'
   +  '<div class="rap-sec-h"><i class="bi bi-clipboard-check me-1"></i>Deroulement</div>'
   +  rapTextarea('re_responsables','Responsables rencontres (Operateur)', rap.responsables_operateur)
   +  rapTextarea('re_plan','Plan effectivement realise', rap.plan_realise)
   +'</div>'

   +'<div class="rap-info-todo"><i class="bi bi-hourglass-split me-1"></i>Les sections <b>Criteres par domaine</b>, <b>graphiques</b>, <b>releve des non-conformites</b> et <b>conclusion automatique</b> seront ajoutees prochainement.</div>';

  $('#re_body').html(html);
}

/* Une ligne d'ampliation : liste deroulante des codes ANAC + suppression */
function amplRowHtml(val){
  let opts='<option value="">-- Choisir --</option>';
  AMPLIATION_ANAC.forEach(function(c){ opts+='<option value="'+esc(c)+'"'+(String(c)===String(val)?' selected':'')+'>'+esc(c)+'</option>'; });
  return '<div class="input-group input-group-sm mb-1 ampl-row"><select class="form-select ampl-sel">'+opts+'</select>'
    +'<button type="button" class="btn btn-outline-danger ampl-del"><i class="bi bi-x-lg"></i></button></div>';
}
$(document).on('click','#re_amplAdd',function(){ $('#re_amplList').append(amplRowHtml('')); });
$(document).on('click','.ampl-del',function(){ if($('#re_amplList .ampl-row').length>1){ $(this).closest('.ampl-row').remove(); } else { $(this).closest('.ampl-row').find('.ampl-sel').val(''); } });

/* Liste deroulante des inspecteurs planifies sur l'audit (pour redacteur / verificateur) */
function inspOptionsRap(meta, selected){
  const list = (meta && meta.inspecteurs) ? meta.inspecteurs : [];
  let opts='<option value="">-- Choisir --</option>';
  list.forEach(function(i){
    const nom=(i.nom||'').trim()||('Inspecteur '+i.idinspecteur);
    opts+='<option value="'+esc(i.idinspecteur)+'"'+(String(i.idinspecteur)===String(selected)?' selected':'')+'>'+esc(nom)+(i.trigr?(' ('+esc(i.trigr)+')'):'')+'</option>';
  });
  return opts;
}

/* Bloc textarea de section (contenu simple pour le socle ; l'editeur riche
   viendra a la session suivante, sur le modele de la revue documentaire) */
function rapTextarea(id, label, val){
  return '<div class="mb-3"><label class="rap-lbl">'+esc(label)+'</label>'
    +'<textarea class="form-control rap-rich" id="'+id+'" rows="3">'+esc(stripHtml(val||''))+'</textarea></div>';
}
function stripHtml(s){ return String(s||'').replace(/<[^>]*>/g,'').replace(/&nbsp;/g,' ').trim(); }

/* Enregistrement du rapport saisi */
$('#re_save').on('click',function(){
  if(!RAP_EDIT_AUDIT) return;
  const ampl=[]; $('#re_amplList .ampl-sel').each(function(){ const v=$(this).val(); if(v) ampl.push(v); });
  const payload={
    action:'save_rapport',
    idaudit:RAP_EDIT_AUDIT.idaudit,
    periode_texte:$('#re_periode').val()||'',
    id_redacteur:$('#re_redacteur').val()||'',
    id_verificateur:$('#re_verificateur').val()||'',
    fonction_libre:$('#re_fonction').val()||'',
    destinataires:$('#re_destinataires').val()||'',
    ampliation_anac:ampl.join('\n'),
    objectifs:$('#re_objectifs').val()||'',
    sites_geographiques:$('#re_sites').val()||'',
    unites_organisation:$('#re_unites').val()||'',
    activites_processus:$('#re_activites').val()||'',
    referentiels:$('#re_referentiels').val()||'',
    responsables_operateur:$('#re_responsables').val()||'',
    plan_realise:$('#re_plan').val()||''
  };
  const btn=$('#re_save'); const html=btn.html();
  btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
  apiPost(payload).done(function(res){
    btn.prop('disabled',false).html(html);
    if(res&&res.success){
      Swal.fire({icon:'success',title:'Rapport enregistre',timer:1200,showConfirmButton:false});
      bootstrap.Modal.getInstance(document.getElementById('rapEditModal')).hide();
      loadList();
    } else {
      Swal.fire({icon:'error',title:'Erreur',text:(res&&res.message)||'Echec de l\'enregistrement.',confirmButtonColor:'#23408F'});
    }
  }).fail(function(){
    btn.prop('disabled',false).html(html);
    Swal.fire({icon:'warning',title:'Action indisponible',text:'L\'enregistrement du rapport en ligne necessite la mise a jour de l\'API (action save_rapport). Cette partie sera activee a la prochaine etape.',confirmButtonColor:'#23408F'});
  });
});

/* ===== MODALE UPLOAD ===== */
function openUploadRap(id, num, orga, remplace){
  MODE_REMPLACE = remplace;
  $('#rap_fichier').prop('required', !remplace);
  $('#rapModalTitle').html((remplace?'<i class="bi bi-arrow-repeat me-1"></i>Remplacer':'<i class="bi bi-file-earmark-plus me-1"></i>Joindre')+' le rapport');
  $('#rap_idaudit').val(id); $('#rap_audit_info').text('Audit : '+num); $('#rap_orga_info').text('Operateur : '+orga);
  $('#rap_fichier').val(''); $('#rap_checklist').val('');
  $('#rap_date_real').val(new Date().toISOString().substring(0,10));
  const aud = ALL.find(function(x){ return String(x.idaudit)===String(id); });
  if(aud && aud.checklist_signee){
    $('#rap_checklist_actuel').show().data('id', id);
  } else {
    $('#rap_checklist_actuel').hide();
  }
  if(remplace){
    const a=ALL.find(function(x){ return String(x.idaudit)===String(id); });
    if(a){ $('#c_nce').val(a.nce||0); $('#c_ncs').val(a.ncs||0); $('#c_ncns').val(a.ncns||0); $('#c_ncne').val(a.ncne||0); $('#c_ncna').val(a.ncna||0); }
  } else { ['c_nce','c_ncs','c_ncns','c_ncne','c_ncna'].forEach(function(id){$('#'+id).val(0);}); }
  recalcCriteres();
  new bootstrap.Modal('#rapModal').show();
}
$(document).on('click','.btn-upload-rap',function(){
  const id=$(this).data('id'), num=$(this).data('num'), orga=$(this).data('orga');
  openUploadRap(id, num, orga, $(this).hasClass('btn-remplacer-rap'));
});

/* Consultation des listes de verification deja deposees */
$(document).on('click','#btnVoirChecklist',function(){
  const id=$('#rap_checklist_actuel').data('id') || $('#rap_idaudit').val();
  if(!id) return;
  window.open(API+'?serve=1&doc=checklist&idaudit='+encodeURIComponent(id), '_blank');
});

$('#rapForm').on('submit',function(e){
  e.preventDefault();
  const idaudit=$('#rap_idaudit').val(), dateReal=$('#rap_date_real').val();
  // Au premier depot le rapport est obligatoire ; ensuite tout est facultatif :
  // on peut corriger les criteres sans redeposer de document.
  if(!MODE_REMPLACE && !$('#rap_fichier')[0].files.length){
    Swal.fire({icon:'warning',title:'Rapport requis',
      text:'Le rapport d\'acte de supervision est obligatoire au premier depot.',
      confirmButtonColor:'#1E9C4B'});
    return;
  }
  if(!dateReal){ Swal.fire({icon:'warning',title:'Date requise',confirmButtonColor:'#1E9C4B'}); return; }
  const btn=$('#rap_submit'), btnHtml=btn.html();
  btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm me-1"></span>Enregistrement...');
  // Les listes de verification sont des documents signes : PDF exclusivement
  const fChk = $('#rap_checklist')[0].files[0] || null;
  if(fChk && !/\.pdf$/i.test(fChk.name)){
    Swal.fire({icon:'error',title:'Format invalide',
      text:'Les listes de verification signees doivent etre fournies en un seul fichier PDF.',
      confirmButtonColor:'#D32F2F'});
    return;
  }
  const fd=new FormData();
  fd.append('csrf_token',CSRF); fd.append('action','upload'); fd.append('idaudit',idaudit);
  fd.append('date_realisation',dateReal);
  if($('#rap_fichier')[0].files.length){ fd.append('fichier_rapport',$('#rap_fichier')[0].files[0]); }
  if(fChk){ fd.append('fichier_checklist', fChk); }
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
  $('#pdfTitle').html('<span style="color:#23408F">Rapport d\'acte de supervision</span> - '+esc(num));
  $('#pdfFrame').attr('src',url);
  $('#pdfDl').attr('href',url+'&dl=1');
  $('#pdfPrint').off('click').on('click',function(){ document.getElementById('pdfFrame').contentWindow.print(); });
  new bootstrap.Modal('#pdfModal').show();
});
/* Listes de verification signees : meme lecteur, en-tete differencie */
$(document).on('click','.btn-chk-rap',function(){
  const idaudit=$(this).data('audit'), num=$(this).data('num')||'';
  const url=AGAI_BASE+'/api/rapports?serve=1&doc=checklist&idaudit='+idaudit;
  $('#pdfTitle').html('<span style="color:#1E9C4B">Listes de verification signees</span> - '+esc(num));
  $('#pdfFrame').attr('src',url);
  $('#pdfDl').attr('href',url+'&dl=1');
  $('#pdfPrint').off('click').on('click',function(){ document.getElementById('pdfFrame').contentWindow.print(); });
  new bootstrap.Modal('#pdfModal').show();
});
$('#pdfModal').on('hidden.bs.modal',function(){ $('#pdfFrame').attr('src',''); });

/* ===== DEMARRAGE ===== */
loadList(); loadStats();
/* Le tableau de bord est TOUJOURS replie au chargement : le tableau des rapports
   s'affiche directement, sans scroll. L'utilisateur l'ouvre a la demande. */
(function(){ setDash(false); })();
</script>