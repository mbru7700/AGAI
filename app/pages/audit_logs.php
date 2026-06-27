<?php
/**
 * Page : Journal des evenements AGAI - Tableau de bord Power BI
 * Acces : admin + chef_inspecteur (module cybersecurite)
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardPage('cybersecurite');
$csrf      = Security::generateCSRF();
$pageTitle = 'Journal des evenements';
$active    = 'audit_logs';
$pageIcon  = 'bi-shield-lock';
require_once INCLUDES_PATH . '/layout_head.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
/* KPI */
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:10px;margin-bottom:16px;}
.kpi-card{background:#fff;border:1px solid #eef1f6;border-radius:12px;padding:13px 14px;box-shadow:0 1px 3px rgba(16,30,54,.04);position:relative;overflow:hidden;}
.kpi-card::before{content:'';position:absolute;top:0;left:0;width:4px;height:100%;}
.kc-blue::before{background:#23408F;} .kc-green::before{background:#1E9C4B;}
.kc-red::before{background:#D32F2F;}   .kc-gold::before{background:#F3C300;}
.kc-purple::before{background:#7c3aed;} .kc-dark::before{background:#2C3E50;}
.kpi-num{font-size:1.65rem;font-weight:800;line-height:1;color:#2C3E50;}
.kpi-lbl{font-size:.72rem;color:#7b8aa0;margin-top:3px;}
.kpi-delta{font-size:.72rem;font-weight:700;margin-top:4px;}
.delta-up{color:#1E9C4B;} .delta-down{color:#D32F2F;} .delta-eq{color:#7b8aa0;}
/* Comparaison aujourd'hui/hier */
.compare-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px;}
.compare-card{background:#fff;border:1px solid #eef1f6;border-radius:12px;padding:12px 14px;box-shadow:0 1px 3px rgba(16,30,54,.04);}
.compare-label{font-size:.72rem;color:#7b8aa0;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;}
.compare-vals{display:flex;justify-content:space-between;align-items:flex-end;gap:10px;}
.compare-today{font-size:1.5rem;font-weight:800;color:#23408F;}
.compare-yest{font-size:.88rem;color:#9aa7bd;font-weight:600;}
.compare-badge{font-size:.72rem;font-weight:700;padding:2px 7px;border-radius:20px;}
.badge-up{background:#d1fae5;color:#065f46;} .badge-down{background:#fee2e2;color:#991b1b;} .badge-eq{background:#f1f5f9;color:#64748b;}
/* Charts */
.chart-card{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:14px;box-shadow:0 1px 3px rgba(16,30,54,.04);}
.chart-title{font-size:.79rem;font-weight:700;color:#23408F;display:flex;align-items:center;gap:6px;margin-bottom:10px;text-transform:uppercase;letter-spacing:.04em;}
/* Filtres */
.filter-bar{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:12px 16px;margin-bottom:14px;box-shadow:0 1px 3px rgba(16,30,54,.04);}
/* Tableau */
table.tbl{width:100%;border-collapse:separate;border-spacing:0;}
table.tbl thead th{background:#23408F;color:#fff;font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;padding:9px 12px;border:none;font-weight:600;white-space:nowrap;}
table.tbl tbody td{padding:9px 12px;border-bottom:1px solid #f1f4f9;vertical-align:middle;font-size:.84rem;}
table.tbl tbody tr:hover{background:#fafcff;}
.act-badge{display:inline-block;padding:.18rem .55rem;border-radius:20px;font-size:.7rem;font-weight:700;white-space:nowrap;}
.act-login{background:#d1fae5;color:#065f46;} .act-logout{background:#e2e3e5;color:#383d41;}
.act-create{background:#dbeafe;color:#1e40af;} .act-update{background:#e0f2fe;color:#0369a1;}
.act-delete{background:#fee2e2;color:#991b1b;} .act-login_attempt,.act-access_denied{background:#fee2e2;color:#991b1b;}
.act-upload{background:#f3e8ff;color:#6b21a8;} .act-mail{background:#fce7f3;color:#9d174d;}
.act-toggle_2fa,.act-password_reset{background:#fef3c7;color:#92400e;}
/* Heatmap heure */
.hour-grid{display:grid;grid-template-columns:repeat(12,1fr);gap:4px;}
.hour-cell{border-radius:4px;height:32px;display:flex;align-items:center;justify-content:center;font-size:.68rem;font-weight:700;cursor:help;transition:transform .1s;}
.hour-cell:hover{transform:scale(1.1);}
/* Top users */
.user-row{display:flex;align-items:center;gap:10px;padding:7px 10px;border-radius:8px;margin-bottom:4px;background:#fafcff;border:0.5px solid #f0f4ff;}
.user-avatar{width:32px;height:32px;border-radius:50%;background:#e8f0fe;color:#23408F;display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:800;flex:0 0 auto;}
.bar-mini{flex:1;height:6px;background:#eef1f6;border-radius:3px;overflow:hidden;}
.bar-mini-fill{height:100%;border-radius:3px;background:#23408F;}
/* IP suspectes */
.ip-row{display:flex;align-items:center;justify-content:space-between;padding:6px 10px;border-radius:8px;background:#fff5f5;border:0.5px solid #fecaca;margin-bottom:4px;font-size:.82rem;}
/* Pagination */
.pagi{display:flex;gap:4px;justify-content:center;margin-top:12px;flex-wrap:wrap;}
.pagi-btn{padding:5px 11px;border:1px solid #e2e8f0;border-radius:6px;background:#fff;font-size:.82rem;cursor:pointer;transition:all .15s;}
.pagi-btn:hover,.pagi-btn.active{background:#23408F;color:#fff;border-color:#23408F;}
.empty{padding:30px;text-align:center;color:#9aa7bd;}
</style>

<div class="page-head">
  <div>
    <h1><i class="bi bi-shield-lock me-2" style="color:#23408F"></i>Journal des evenements AGAI</h1>
    <div class="sub">Tableau de bord analytique de l'activite et de la securite du systeme.</div>
  </div>
  <div class="d-flex gap-2 align-items-center">
    <span class="badge" style="background:#e8f0fe;color:#23408F;font-size:.78rem" id="lastRefresh"></span>
    <button class="btn btn-sm btn-outline-secondary" id="btnRefresh"><i class="bi bi-arrow-clockwise me-1"></i>Actualiser</button>
  </div>
</div>

<!-- ===== FILTRES ===== -->
<div class="filter-bar mb-3">
  <div class="d-flex align-items-center gap-2 mb-2">
    <i class="bi bi-funnel-fill" style="color:#23408F"></i>
    <strong style="color:#23408F;font-size:.82rem">Filtres</strong>
    <span class="badge ms-1" id="filterBadge" style="background:#e8f0fe;color:#23408F;font-size:.72rem">Mois courant</span>
    <button class="btn btn-xs btn-outline-secondary ms-auto" id="btnResetFilters" style="font-size:.72rem;padding:2px 8px"><i class="bi bi-x-lg me-1"></i>Reset</button>
  </div>
  <div class="row g-2">
    <div class="col-6 col-md-2">
      <div style="font-size:.72rem;font-weight:700;color:#5b6b85;text-transform:uppercase;margin-bottom:3px">Annee</div>
      <select id="f_annee" style="width:100%"><option value="">Toutes</option></select>
    </div>
    <div class="col-6 col-md-2">
      <div style="font-size:.72rem;font-weight:700;color:#5b6b85;text-transform:uppercase;margin-bottom:3px">Mois</div>
      <select id="f_mois" class="form-select form-select-sm">
        <option value="">Tous</option>
        <?php $mn=['01'=>'Janvier','02'=>'Fevrier','03'=>'Mars','04'=>'Avril','05'=>'Mai','06'=>'Juin','07'=>'Juillet','08'=>'Aout','09'=>'Septembre','10'=>'Octobre','11'=>'Novembre','12'=>'Decembre'];
        foreach($mn as $k=>$v): ?><option value="<?php echo $k; ?>"><?php echo $v; ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <div style="font-size:.72rem;font-weight:700;color:#5b6b85;text-transform:uppercase;margin-bottom:3px">Date precise</div>
      <input type="date" class="form-control form-control-sm" id="f_date">
    </div>
    <div class="col-6 col-md-3">
      <div style="font-size:.72rem;font-weight:700;color:#5b6b85;text-transform:uppercase;margin-bottom:3px">Utilisateur</div>
      <select id="f_user" style="width:100%"><option value="">Tous</option></select>
    </div>
    <div class="col-6 col-md-3">
      <div style="font-size:.72rem;font-weight:700;color:#5b6b85;text-transform:uppercase;margin-bottom:3px">Action</div>
      <select id="f_action" style="width:100%">
        <option value="">Toutes</option>
        <option value="login">Connexion reussie</option>
        <option value="login_attempt">Tentative echec</option>
        <option value="logout">Deconnexion</option>
        <option value="access_denied">Acces refuse</option>
        <option value="create">Creation</option>
        <option value="update">Modification</option>
        <option value="delete">Suppression</option>
        <option value="upload">Upload</option>
        <option value="mail">Email</option>
        <option value="toggle_2fa">2FA</option>
      </select>
    </div>
  </div>
</div>

<!-- ===== KPI PRINCIPAUX ===== -->
<div class="kpi-grid" id="kpiRow">
  <div class="kpi-card kc-blue" title="Nombre total d'evenements dans la periode selectionnee">
    <div class="kpi-num" id="k_total">-</div><div class="kpi-lbl">Total evenements</div>
  </div>
  <div class="kpi-card kc-green" title="Connexions reussies dans la periode">
    <div class="kpi-num" id="k_logins">-</div><div class="kpi-lbl">Connexions OK</div>
    <div class="kpi-delta" id="d_logins"></div>
  </div>
  <div class="kpi-card kc-red" title="Tentatives de connexion echouees (brute force potentiel)">
    <div class="kpi-num" id="k_fails">-</div><div class="kpi-lbl">Echecs connexion</div>
    <div class="kpi-delta" id="d_fails"></div>
  </div>
  <div class="kpi-card kc-red" title="Acces refuses (RBAC - tentatives acces non autorise)">
    <div class="kpi-num" id="k_denied">-</div><div class="kpi-lbl">Acces refuses</div>
  </div>
  <div class="kpi-card kc-blue" title="Operations CRUD (creation, modification, suppression)">
    <div class="kpi-num" id="k_crud">-</div><div class="kpi-lbl">Ops CRUD</div>
  </div>
  <div class="kpi-card kc-purple" title="Utilisateurs uniques actifs dans la periode">
    <div class="kpi-num" id="k_users">-</div><div class="kpi-lbl">Users actifs</div>
  </div>
  <div class="kpi-card kc-gold" title="Uploads et envois de mails dans la periode">
    <div class="kpi-num" id="k_uploads">-</div><div class="kpi-lbl">Uploads / Mails</div>
  </div>
  <div class="kpi-card kc-dark" title="Adresses IP distinctes ayant genere des evenements">
    <div class="kpi-num" id="k_ips">-</div><div class="kpi-lbl">IPs uniques</div>
  </div>
</div>

<!-- ===== COMPARAISON AUJOURD'HUI / HIER ===== -->
<div class="compare-grid mb-3">
  <div class="compare-card">
    <div class="compare-label"><i class="bi bi-calendar-day me-1"></i>Activite totale</div>
    <div class="compare-vals">
      <div><div class="compare-today" id="c_today_total">-</div><div style="font-size:.72rem;color:#7b8aa0">Aujourd'hui</div></div>
      <div><div class="compare-yest" id="c_yest_total">-</div><div style="font-size:.72rem;color:#7b8aa0">Hier</div></div>
      <div class="compare-badge" id="c_total_badge">-</div>
    </div>
  </div>
  <div class="compare-card">
    <div class="compare-label"><i class="bi bi-box-arrow-in-right me-1" style="color:#1E9C4B"></i>Connexions</div>
    <div class="compare-vals">
      <div><div class="compare-today" id="c_today_login" style="color:#1E9C4B">-</div><div style="font-size:.72rem;color:#7b8aa0">Aujourd'hui</div></div>
      <div><div class="compare-yest" id="c_yest_login">-</div><div style="font-size:.72rem;color:#7b8aa0">Hier</div></div>
      <div class="compare-badge" id="c_login_badge">-</div>
    </div>
  </div>
  <div class="compare-card">
    <div class="compare-label"><i class="bi bi-shield-exclamation me-1" style="color:#D32F2F"></i>Echecs connexion</div>
    <div class="compare-vals">
      <div><div class="compare-today" id="c_today_fail" style="color:#D32F2F">-</div><div style="font-size:.72rem;color:#7b8aa0">Aujourd'hui</div></div>
      <div><div class="compare-yest" id="c_yest_fail">-</div><div style="font-size:.72rem;color:#7b8aa0">Hier</div></div>
      <div class="compare-badge" id="c_fail_badge">-</div>
    </div>
  </div>
</div>

<!-- ===== LIGNE 1 : Courbe activite + Repartition actions ===== -->
<div class="row g-3 mb-3">
  <div class="col-md-7">
    <div class="chart-card">
      <div class="chart-title"><i class="bi bi-graph-up"></i>Evolution de l'activite (connexions OK vs echecs)</div>
      <div style="height:220px;position:relative"><canvas id="chartCourbe" role="img" aria-label="Courbe activite AGAI">Evolution quotidienne</canvas></div>
    </div>
  </div>
  <div class="col-md-5">
    <div class="chart-card">
      <div class="chart-title"><i class="bi bi-pie-chart-fill"></i>Repartition par type d'action</div>
      <div style="height:220px;position:relative"><canvas id="chartActions" role="img" aria-label="Repartition actions">Actions</canvas></div>
    </div>
  </div>
</div>

<!-- ===== LIGNE 2 : Par module + Heatmap heure ===== -->
<div class="row g-3 mb-3">
  <div class="col-md-5">
    <div class="chart-card">
      <div class="chart-title"><i class="bi bi-bar-chart-fill"></i>Activite par module</div>
      <div style="height:220px;position:relative"><canvas id="chartModules" role="img" aria-label="Activite par module">Modules</canvas></div>
    </div>
  </div>
  <div class="col-md-7">
    <div class="chart-card">
      <div class="chart-title"><i class="bi bi-clock-history"></i>Heures de connexion (repartition sur 24h)</div>
      <div id="heatmapHeure"></div>
      <div class="d-flex justify-content-between mt-2" style="font-size:.7rem;color:#9aa7bd">
        <span>00h</span><span>06h</span><span>12h</span><span>18h</span><span>23h</span>
      </div>
    </div>
  </div>
</div>

<!-- ===== LIGNE 3 : Top users + IPs suspectes ===== -->
<div class="row g-3 mb-3">
  <div class="col-md-6">
    <div class="chart-card" style="max-height:280px;overflow-y:auto">
      <div class="chart-title"><i class="bi bi-people-fill"></i>Top utilisateurs actifs</div>
      <div id="topUsersBox"></div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="chart-card" style="max-height:280px;overflow-y:auto">
      <div class="chart-title" style="color:#D32F2F"><i class="bi bi-exclamation-triangle-fill text-danger"></i>IPs avec echecs de connexion repetes</div>
      <div id="topIpsBox"></div>
    </div>
  </div>
</div>

<!-- ===== JOURNAL DETAILLE ===== -->
<div style="background:#fff;border:1px solid #eef1f6;border-radius:14px;overflow:hidden;box-shadow:0 1px 3px rgba(16,30,54,.04)">
  <div class="d-flex align-items-center justify-content-between px-3 py-2" style="border-bottom:1px solid #eef1f6">
    <div style="font-size:.8rem;font-weight:700;color:#23408F;text-transform:uppercase;letter-spacing:.04em">
      <i class="bi bi-list-ul me-1"></i>Journal detaille
    </div>
    <div class="d-flex gap-2 align-items-center">
      <input type="text" id="f_search" class="form-control form-control-sm" placeholder="Rechercher..." style="width:180px;font-size:.8rem">
      <select id="f_action_tbl" class="form-select form-select-sm" style="width:160px;font-size:.8rem">
        <option value="">Toutes les actions</option>
        <option value="login">Connexion OK</option>
        <option value="login_attempt">Echec connexion</option>
        <option value="logout">Deconnexion</option>
        <option value="access_denied">Acces refuse</option>
        <option value="create">Creation</option>
        <option value="update">Modification</option>
        <option value="delete">Suppression</option>
        <option value="upload">Upload</option>
        <option value="mail">Email</option>
      </select>
      <span class="badge" style="background:#e8f0fe;color:#23408F;font-size:.72rem" id="totalBadge">-</span>
    </div>
  </div>
  <div style="overflow-x:auto">
    <table class="tbl">
      <thead><tr>
        <th>Date / Heure</th><th>Utilisateur</th><th>Action</th>
        <th>Module</th><th>Description</th><th>IP</th>
      </tr></thead>
      <tbody id="tbody">
        <tr><td colspan="6" class="empty"><span class="spinner-border spinner-border-sm me-2"></span>Chargement...</td></tr>
      </tbody>
    </table>
  </div>
  <div id="pagiBox" class="pagi py-2 px-3"></div>
</div>

<!-- ===== MODALE : Connexions du jour ===== -->
<div class="modal fade" id="loginModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,#23408F,#1b3576)">
        <h5 class="modal-title text-white"><i class="bi bi-people-fill me-2" style="color:#F3C300"></i><span id="modalTitle">Connexions</span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <div id="modalContent" style="max-height:480px;overflow-y:auto">
          <div class="text-center py-4"><span class="spinner-border text-primary"></span></div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>
<script>
const CSRF  = '<?php echo Security::escape($csrf); ?>';
const API   = AGAI_BASE + '/api/audit-logs';
let chartCourbe=null, chartActions=null, chartModules=null;
let currentPage=1, totalPages=1;

function apiPost(d){ return $.post(API, Object.assign({csrf_token:CSRF}, d), null, 'json'); }
function fmtDT(s){ if(!s) return '-'; const d=new Date(s); return d.toLocaleDateString('fr-FR')+' '+d.toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit',second:'2-digit'}); }
function fmtD(s){ if(!s) return '-'; const d=new Date(s); return d.toLocaleDateString('fr-FR'); }
function destroyC(c){ if(c){try{c.destroy();}catch(e){}} return null; }

// Select2
$('#f_annee,#f_user,#f_action').select2({theme:'bootstrap-5',width:'100%',allowClear:true,placeholder:'Tous'});

const ACT_COLORS={
  login:'#1E9C4B',login_attempt:'#D32F2F',logout:'#9aa7bd',
  access_denied:'#D32F2F',create:'#2a78d6',update:'#1baf7a',
  delete:'#e34948',upload:'#7c3aed',mail:'#d55181',
  toggle_2fa:'#b58a00',password_reset:'#b58a00',default:'#888'
};

function actBadge(a){
  const c=ACT_COLORS[a]||ACT_COLORS.default;
  const labels={login:'Connexion',login_attempt:'Echec',logout:'Deconnexion',
    access_denied:'Refuse',create:'Creation',update:'Modif',delete:'Suppression',
    upload:'Upload',mail:'Mail',toggle_2fa:'2FA',password_reset:'Reset MDP'};
  const bg=a==='login'?'#d1fae5':a==='login_attempt'||a==='access_denied'?'#fee2e2':
    a==='logout'?'#e2e3e5':a==='create'?'#dbeafe':a==='update'?'#e0f2fe':
    a==='delete'?'#fee2e2':a==='upload'||a==='mail'?'#f3e8ff':'#f1f5f9';
  const tc=a==='login'?'#065f46':a==='login_attempt'||a==='access_denied'||a==='delete'?'#991b1b':
    a==='logout'?'#383d41':a==='create'?'#1e40af':a==='update'?'#0369a1':
    a==='upload'||a==='mail'?'#6b21a8':'#374151';
  return '<span class="act-badge" style="background:'+bg+';color:'+tc+'">'+esc(labels[a]||a)+'</span>';
}

function deltaBadge(now, yest, inverseGood){
  if(!yest) return '<span class="compare-badge badge-eq">-</span>';
  const pct=Math.round((now-yest)/yest*100);
  const isGood=inverseGood?(pct<=0):(pct>=0);
  const icon=pct>0?'<i class="bi bi-arrow-up-short"></i>':pct<0?'<i class="bi bi-arrow-down-short"></i>':'';
  const cls=pct>0?(isGood?'badge-up':'badge-down'):pct<0?(isGood?'badge-up':'badge-down'):'badge-eq';
  return '<span class="compare-badge '+cls+'">'+icon+(pct>=0?'+':'')+pct+'%</span>';
}

// ===== FILTRES =====
function getFilters(){
  return {
    f_annee:$('#f_annee').val()||'',
    f_mois:$('#f_mois').val()||'',
    f_date:$('#f_date').val()||'',
  };
}
$('#f_annee,#f_user,#f_action').on('change',refreshAll);
$('#f_mois,#f_date').on('change',refreshAll);
$('#btnRefresh').on('click',refreshAll);
$('#btnResetFilters').on('click',function(){
  $('#f_annee,#f_user,#f_action').val('').trigger('change');
  $('#f_mois,#f_date').val('');
  refreshAll();
});
$('#f_search,#f_action_tbl').on('input change',function(){ currentPage=1; loadTable(); });

function updateFilterBadge(){
  const fA=$('#f_annee').val(), fM=$('#f_mois').val(), fD=$('#f_date').val();
  const moisNoms=['','Janvier','Fevrier','Mars','Avril','Mai','Juin','Juillet','Aout','Septembre','Octobre','Novembre','Decembre'];
  const moisActuel=new Date().getMonth()+1;
  const anneeActuelle=new Date().getFullYear();
  let lbl='Mois courant ('+moisNoms[moisActuel]+' '+anneeActuelle+')';
  if(fD) lbl='Date : '+fD;
  else if(fA&&fM) lbl='Annee '+fA+' - '+moisNoms[parseInt(fM)];
  else if(fA) lbl='Annee '+fA;
  else if(fM) lbl=moisNoms[parseInt(fM)]+' (toutes annees)';
  $('#filterBadge').text(lbl);
}

// ===== DASHBOARD =====
function loadDashboard(){
  apiPost(Object.assign({action:'dashboard'},getFilters())).done(function(res){
    if(!res.success) return;
    const k=res.kpi||{};
    // KPI
    $('#k_total').text(k.total_events||0);
    $('#k_logins').text(k.logins_ok||0);
    $('#k_fails').text(k.login_fail||0);
    $('#k_denied').text(k.access_denied||0);
    $('#k_crud').text(k.crud_ops||0);
    $('#k_users').text(k.users_actifs||0);
    $('#k_uploads').text(k.uploads||0);
    $('#k_ips').text(k.ips_uniques||0);
    // Comparaison aujourd'hui/hier
    const t=res.today||0, y=res.yesterday||0;
    const lt=res.loginToday||0, ly=res.loginYest||0;
    const ft=res.failToday||0, fy=res.failYest||0;
    $('#c_today_total').text(t); $('#c_yest_total').text(y); $('#c_total_badge').html(deltaBadge(t,y,false));
    $('#c_today_login').text(lt);$('#c_yest_login').text(ly); $('#c_login_badge').html(deltaBadge(lt,ly,true));
    $('#c_today_fail').text(ft); $('#c_yest_fail').text(fy);  $('#c_fail_badge').html(deltaBadge(ft,fy,true));
    // Deltas sous KPI
    function kpiDelta(el,now,prev,inv){
      if(!prev){$(el).html('');return;}
      const p=Math.round((now-prev)/prev*100);
      const good=inv?(p<=0):(p>=0);
      $(el).html('<i class="bi bi-arrow-'+(p>=0?'up':'down')+'-short"></i>'+(p>=0?'+':'')+p+'% vs hier')
           .css('color',good?'#1E9C4B':'#D32F2F');
    }
    kpiDelta('#d_logins',lt,ly,true);
    kpiDelta('#d_fails',ft,fy,true);
    // Courbe
    renderCourbe(res.courbe||[]);
    // Repartition actions
    renderActions(res.by_action||[]);
    // Modules
    renderModules(res.by_module||[]);
    // Heatmap heure
    renderHeatmap(res.par_heure||[]);
    // Top users
    renderTopUsers(res.top_users||[]);
    // IPs suspectes
    renderTopIps(res.top_ips||[]);
    // Remplir filtre annees
    if(res.annees&&res.annees.length){
      const cur=$('#f_annee').val();
      let opts='<option value="">Toutes</option>';
      res.annees.forEach(function(y){ opts+='<option value="'+y+'">'+y+'</option>'; });
      $('#f_annee').html(opts);
      if(cur) $('#f_annee').val(cur);
      $('#f_annee').trigger('change.select2');
    }
    // Timestamp
    $('#lastRefresh').text('Actualise le '+new Date().toLocaleTimeString('fr-FR'));
  });
}

function renderCourbe(data){
  chartCourbe=destroyC(chartCourbe);
  if(!data.length) return;
  chartCourbe=new Chart(document.getElementById('chartCourbe'),{type:'line',data:{
    labels:data.map(function(r){return new Date(r.jour).toLocaleDateString('fr-FR',{day:'2-digit',month:'short'});}),
    datasets:[
      {label:'Connexions OK',data:data.map(function(r){return r.logins||0;}),borderColor:'#1E9C4B',backgroundColor:'rgba(30,156,75,.08)',fill:true,tension:.3,pointRadius:3,borderWidth:2},
      {label:'Echecs',data:data.map(function(r){return r.echecs||0;}),borderColor:'#D32F2F',backgroundColor:'rgba(211,47,47,.06)',fill:true,tension:.3,pointRadius:3,borderWidth:2,borderDash:[4,3]},
      {label:'Total events',data:data.map(function(r){return r.total||0;}),borderColor:'#23408F',backgroundColor:'transparent',tension:.3,pointRadius:2,borderWidth:1.5},
    ]
  },options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},
    plugins:{legend:{position:'bottom',labels:{font:{size:9},boxWidth:10}}},
    scales:{x:{ticks:{font:{size:8},maxRotation:30}},y:{beginAtZero:true,ticks:{font:{size:9},stepSize:1}}}}});
}

function renderActions(data){
  chartActions=destroyC(chartActions);
  if(!data.length) return;
  const colors=data.map(function(r){ return ACT_COLORS[r.action]||ACT_COLORS.default; });
  const total=data.reduce(function(s,r){return s+(r.nb||0);},0);
  const labels=data.map(function(r){
    const pct=total?Math.round((r.nb||0)/total*100):0;
    return (r.action||'-')+' ('+pct+'%)';
  });
  chartActions=new Chart(document.getElementById('chartActions'),{type:'doughnut',data:{
    labels:labels,
    datasets:[{data:data.map(function(r){return r.nb||0;}),backgroundColor:colors,borderColor:'#fff',borderWidth:2}]
  },options:{responsive:true,maintainAspectRatio:false,cutout:'50%',
    plugins:{legend:{position:'bottom',labels:{font:{size:8},boxWidth:9,padding:4}},
    tooltip:{callbacks:{label:function(c){const t=c.chart.data.datasets[0].data.reduce(function(a,b){return a+b;},0);return ' '+c.parsed+' ('+Math.round(c.parsed/t*100)+'%)';}}}}}});
}

function renderModules(data){
  chartModules=destroyC(chartModules);
  if(!data.length) return;
  const MODCOLORS=['rgba(35,64,143,.8)','rgba(30,156,75,.8)','rgba(243,195,0,.8)','rgba(211,47,47,.8)','rgba(90,24,154,.8)','rgba(56,61,65,.7)','rgba(30,156,75,.5)','rgba(35,64,143,.5)'];
  chartModules=new Chart(document.getElementById('chartModules'),{type:'bar',data:{
    labels:data.map(function(r){return r.module||'-';}),
    datasets:[{data:data.map(function(r){return r.nb||0;}),backgroundColor:MODCOLORS,borderRadius:4,borderWidth:0}]
  },options:{responsive:true,maintainAspectRatio:false,indexAxis:'y',
    plugins:{legend:{display:false},tooltip:{callbacks:{label:function(c){return ' '+c.parsed.x+' evenements';}}}},
    scales:{x:{beginAtZero:true,ticks:{font:{size:9}}},y:{ticks:{font:{size:8}}}}}});
}

function renderHeatmap(data){
  const byHr={}; for(let h=0;h<24;h++) byHr[h]=0;
  data.forEach(function(r){ byHr[r.heure]=(r.nb||0); });
  const maxVal=Math.max(...Object.values(byHr))||1;
  let html='<div class="hour-grid">';
  for(let h=0;h<24;h++){
    const v=byHr[h];
    const pct=Math.round(v/maxVal*100);
    const col=pct>80?'#D32F2F':pct>50?'#F3C300':pct>20?'#23408F':pct>5?'#93c5fd':'#eef1f6';
    const tc=pct>20?'#fff':'#7b8aa0';
    html+='<div class="hour-cell" style="background:'+col+';color:'+tc+'" title="'+h+'h : '+v+' connexion(s)">'+(v>0?v:'')+'</div>';
  }
  html+='</div>';
  $('#heatmapHeure').html(html);
}

function renderTopUsers(data){
  if(!data.length){ $('#topUsersBox').html('<div class="empty small">Aucune donnee</div>'); return; }
  const max=data[0].nb||1;
  const ROLE_LABELS={admin:'Admin',chef_inspecteur:'CI',inspecteur:'Insp.',operateur:'Oper.',consultant:'Cons.'};
  let h='';
  data.forEach(function(u,i){
    const initials=((u.prenom||'?').charAt(0)+(u.nom||'?').charAt(0)).toUpperCase();
    const pct=Math.round((u.nb||0)/max*100);
    const roleLabel=ROLE_LABELS[u.role_user||u.role]||u.role_user||'';
    h+='<div class="user-row" style="cursor:pointer" onclick="openLoginModal(\''+esc(u.email||'')+'\',\''+esc((u.prenom||'')+' '+(u.nom||''))+'\',\'login\')">'
      +'<div class="user-avatar">'+esc(initials)+'</div>'
      +'<div style="flex:1;min-width:0">'
        +'<div style="font-size:.8rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+esc((u.prenom||'')+' '+(u.nom||''))+'</div>'
        +'<div style="font-size:.72rem;color:#9aa7bd">'+esc(u.email||'')+(roleLabel?' &middot; <span style="color:#23408F">'+roleLabel+'</span>':'')+'</div>'
        +'<div class="bar-mini mt-1"><div class="bar-mini-fill" style="width:'+pct+'%"></div></div>'
      +'</div>'
      +'<div style="font-size:.82rem;font-weight:800;color:#23408F;flex:0 0 32px;text-align:right">'+u.nb+'</div>'
      +'</div>';
  });
  $('#topUsersBox').html(h);
}

function renderTopIps(data){
  if(!data.length){ $('#topIpsBox').html('<div class="empty small" style="background:inherit;border:none"><i class="bi bi-check-circle-fill text-success me-1"></i>Aucune IP suspecte detectee</div>'); return; }
  let h='';
  data.forEach(function(ip){
    const threat=ip.nb_echecs>=10?'Critique':ip.nb_echecs>=5?'Eleve':'Moyen';
    const tc=ip.nb_echecs>=10?'#D32F2F':ip.nb_echecs>=5?'#b58a00':'#555';
    h+='<div class="ip-row">'
      +'<div><i class="bi bi-geo-alt-fill me-1" style="color:#D32F2F;font-size:.9rem"></i>'
      +'<strong style="font-family:monospace;font-size:.85rem">'+esc(ip.ip_address||'')+'</strong>'
      +'<div style="font-size:.7rem;color:#9aa7bd">'+fmtD(ip.premier)+' - '+fmtD(ip.dernier)+'</div></div>'
      +'<div><span style="font-weight:800;color:'+tc+';font-size:.88rem">'+ip.nb_echecs+' echecs</span>'
      +'<span style="background:#fee2e2;color:#991b1b;border-radius:4px;padding:1px 5px;font-size:.68rem;margin-left:6px">'+threat+'</span></div>'
      +'</div>';
  });
  $('#topIpsBox').html(h);
}

// ===== MODALE CONNEXIONS =====
function openLoginModal(email, nom, type){
  const typeLabel=type==='login'?'Connexions reussies':'Tentatives echec';
  $('#modalTitle').text(typeLabel+(nom?' - '+nom:''));
  $('#modalContent').html('<div class="text-center py-4"><span class="spinner-border text-primary"></span></div>');
  new bootstrap.Modal('#loginModal').show();
  apiPost(Object.assign({action:'logins_detail',type_filter:type,date_filter:$('#f_date').val()||''},
    email?{user_email:email}:{}
  )).done(function(res){
    if(!res.success||!res.data.length){
      $('#modalContent').html('<div class="empty">Aucune donnee trouvee</div>'); return;
    }
    let h='<table class="tbl" style="font-size:.82rem"><thead><tr>'
      +'<th>Date / Heure</th><th>Utilisateur</th><th>IP</th><th>Description</th>'
      +'</tr></thead><tbody>';
    res.data.forEach(function(r){
      const isOk=(r.action||'')!=='login_attempt';
      const rowStyle=isOk?'':'background:#fff5f5';
      h+='<tr style="'+rowStyle+'">'
        +'<td style="white-space:nowrap">'+fmtDT(r.created_at)+'</td>'
        +'<td><div style="font-weight:600">'+esc(r.nom_user||'-')+'</div><div style="font-size:.72rem;color:#9aa7bd">'+esc(r.email_user||'')+'</div></td>'
        +'<td style="font-family:monospace;font-size:.82rem">'+esc(r.ip_address||'-')+'</td>'
        +'<td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="'+esc(r.description||'')+'">'+esc(r.description||'-')+'</td>'
        +'</tr>';
    });
    h+='</tbody></table>';
    $('#modalContent').html(h);
  });
}

// Clic sur courbe pour ouvrir modale du jour
$('#chartCourbe').on('click',function(evt){
  if(!chartCourbe) return;
  const pts=chartCourbe.getElementsAtEventForMode(evt,'index',{intersect:false},false);
  if(!pts.length) return;
  const label=chartCourbe.data.labels[pts[0].index];
  $('#modalTitle').text('Connexions du '+label);
  openLoginModal('','','login');
});

// Clic sur KPI connexions OK -> modale
$('#k_logins').css('cursor','pointer').on('click',function(){ openLoginModal('','','login'); });
$('#k_fails').css('cursor','pointer').on('click',function(){ openLoginModal('','','login_attempt'); });
$('#c_today_total,#c_today_login').css('cursor','pointer').on('click',function(){ openLoginModal('','','login'); });
$('#c_today_fail').css('cursor','pointer').on('click',function(){ openLoginModal('','','login_attempt'); });

// ===== JOURNAL TABLEAU =====
function loadTable(){
  $('#tbody').html('<tr><td colspan="6" class="empty"><span class="spinner-border spinner-border-sm me-1"></span>Chargement...</td></tr>');
  const params=Object.assign({
    action:'list',page:currentPage,per:30,
    action_filter:$('#f_action_tbl').val()||'',
    search:$('#f_search').val()||'',
    user_filter:$('#f_user').val()||'',
  },getFilters());
  apiPost(params).done(function(res){
    if(!res.success){ $('#tbody').html('<tr><td colspan="6" class="empty">Erreur chargement</td></tr>'); return; }
    totalPages=res.pages||1;
    $('#totalBadge').text((res.total||0)+' evenements');
    if(!res.data||!res.data.length){ $('#tbody').html('<tr><td colspan="6" class="empty"><i class="bi bi-inbox me-2"></i>Aucun evenement</td></tr>'); renderPagi(); return; }
    $('#tbody').html(res.data.map(function(r){
      return '<tr>'
        +'<td style="white-space:nowrap;font-size:.78rem">'+fmtDT(r.created_at)+'</td>'
        +'<td><div style="font-weight:600;font-size:.82rem">'+esc(r.nom_user||'Systeme')+'</div>'
          +'<div style="font-size:.71rem;color:#9aa7bd">'+esc(r.email_user||'')+(r.role_user?' &middot; '+esc(r.role_user):'')+'</div></td>'
        +'<td>'+actBadge(r.action||'')+'</td>'
        +'<td style="font-size:.78rem;color:#5b6b85">'+esc(r.module||'-')+'</td>'
        +'<td style="max-width:280px;font-size:.8rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="'+esc(r.description||'')+'">'+esc(r.description||'-')+'</td>'
        +'<td style="font-family:monospace;font-size:.78rem;color:#5b6b85">'+esc(r.ip_address||'-')+'</td>'
        +'</tr>';
    }).join(''));
    renderPagi();
  });
}

function renderPagi(){
  if(totalPages<=1){ $('#pagiBox').html(''); return; }
  let h='';
  const start=Math.max(1,currentPage-2), end=Math.min(totalPages,currentPage+2);
  if(start>1) h+='<button class="pagi-btn" onclick="goPage(1)">1</button>';
  if(start>2) h+='<span class="pagi-btn" style="cursor:default;border:none">…</span>';
  for(let i=start;i<=end;i++) h+='<button class="pagi-btn'+(i===currentPage?' active':'') +'" onclick="goPage('+i+')">'+i+'</button>';
  if(end<totalPages-1) h+='<span class="pagi-btn" style="cursor:default;border:none">…</span>';
  if(end<totalPages) h+='<button class="pagi-btn" onclick="goPage('+totalPages+')">'+totalPages+'</button>';
  $('#pagiBox').html(h);
}
function goPage(p){ currentPage=p; loadTable(); }

// ===== USERS SELECT2 =====
function loadUsersFilter(){
  apiPost({action:'users_list'}).done(function(res){
    if(!res.success) return;
    let opts='<option value="">Tous</option>';
    (res.data||[]).forEach(function(u){ opts+='<option value="'+u.iduser+'">'+esc(u.nom||'')+'</option>'; });
    $('#f_user').html(opts).trigger('change.select2');
  });
}

// ===== REFRESH ALL =====
function refreshAll(){ updateFilterBadge(); loadDashboard(); currentPage=1; loadTable(); }

// ===== INIT =====
refreshAll();
loadUsersFilter();
setInterval(function(){ refreshAll(); }, 60000); // Auto-refresh 60s
</script>