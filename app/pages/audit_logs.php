<?php
/**
 * Page : Journal des evenements AGAI (audit_logs)
 * Consultation uniquement - lecture seule
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardPage('parametres');

$csrf      = Security::generateCSRF();
$pageTitle = 'Journal des evenements';
$active    = 'audit_logs';
$pageIcon  = 'bi-journal-text';
require_once INCLUDES_PATH . '/layout_head.php';
?>
<style>
.filter-bar{background:#fff;border:1px solid #eef1f6;border-radius:12px;padding:12px 16px;margin-bottom:14px;box-shadow:0 1px 2px rgba(16,30,54,.04);}
.tbl-wrap{background:#fff;border:1px solid #eef1f6;border-radius:14px;overflow:hidden;box-shadow:0 1px 2px rgba(16,30,54,.04);}
table.tbl{width:100%;border-collapse:separate;border-spacing:0;}
table.tbl thead th{background:#f7f9fc;color:#5b6b85;font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;padding:10px 13px;border-bottom:1px solid #eef1f6;white-space:nowrap;}
table.tbl tbody td{padding:10px 13px;border-bottom:1px solid #f1f4f9;vertical-align:middle;font-size:.88rem;}
table.tbl tbody tr:hover{background:#fafcff;}
.empty{padding:30px;text-align:center;color:#9aa7bd;}
.act-badge{display:inline-block;padding:.18rem .55rem;border-radius:20px;font-size:.7rem;font-weight:700;white-space:nowrap;}
.act-create{background:#d1e7dd;color:#0a5c36;}
.act-update{background:#cfe2ff;color:#0a3278;}
.act-delete{background:#f8d7da;color:#842029;}
.act-login{background:#d1e7dd;color:#0a5c36;}
.act-logout{background:#e2e3e5;color:#383d41;}
.act-error,.act-access_denied{background:#f8d7da;color:#842029;}
.act-upload,.act-mail{background:#f3e8ff;color:#6b21a8;}
.act-default{background:#eef1f6;color:#5b6b85;}
.mod-badge{font-size:.7rem;padding:.15rem .45rem;border-radius:6px;background:#e8f0fe;color:#23408F;font-weight:600;}
.ip-code{font-family:monospace;background:#f5f7fa;padding:.12rem .4rem;border-radius:5px;font-size:.8rem;color:#23408F;}
.kpi-num{font-size:1.4rem;font-weight:700;color:#2C3E50;line-height:1;}
.kpi-lbl{font-size:.75rem;color:#7b8aa0;}
</style>

<div class="page-head">
  <div>
    <h1><i class="bi bi-journal-text me-2" style="color:var(--anac-primary)"></i>Journal des evenements</h1>
    <div class="sub">Historique complet des actions AGAI. Lecture seule - aucune modification possible.</div>
  </div>
  <button class="btn btn-outline-secondary btn-sm" id="btnRefresh"><i class="bi bi-arrow-clockwise me-1"></i>Actualiser</button>
</div>

<!-- KPI -->
<div class="row g-3 mb-3" id="kpiRow">
  <div class="col-6 col-md-3"><div class="tbl-wrap p-3 text-center"><div class="kpi-num" id="kTotal">-</div><div class="kpi-lbl">Evenements (24h)</div></div></div>
  <div class="col-6 col-md-3"><div class="tbl-wrap p-3 text-center"><div class="kpi-num" style="color:#D32F2F" id="kErrors">-</div><div class="kpi-lbl">Erreurs / Refus (24h)</div></div></div>
  <div class="col-6 col-md-3"><div class="tbl-wrap p-3 text-center"><div class="kpi-num" style="color:#1E9C4B" id="kLogins">-</div><div class="kpi-lbl">Connexions reussies (24h)</div></div></div>
  <div class="col-6 col-md-3"><div class="tbl-wrap p-3 text-center"><div class="kpi-num" style="color:#23408F" id="kUsers">-</div><div class="kpi-lbl">Utilisateurs actifs (24h)</div></div></div>
</div>

<!-- Filtres -->
<div class="filter-bar">
  <div class="row g-2 align-items-end">
    <div class="col-6 col-md-2">
      <label class="form-label small text-muted mb-1">Periode</label>
      <select class="form-select form-select-sm" id="fPeriode">
        <option value="1">24h</option>
        <option value="7" selected>7 jours</option>
        <option value="30">30 jours</option>
        <option value="0">Tout</option>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label small text-muted mb-1">Action</label>
      <select class="form-select form-select-sm" id="fAction">
        <option value="">Toutes</option>
        <option value="login">Connexion</option>
        <option value="logout">Deconnexion</option>
        <option value="login_attempt">Tentative connexion</option>
        <option value="create">Creation</option>
        <option value="update">Modification</option>
        <option value="delete">Suppression</option>
        <option value="upload">Upload</option>
        <option value="access_denied">Acces refuse</option>
        <option value="mail">Email</option>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label small text-muted mb-1">Module</label>
      <select class="form-select form-select-sm" id="fModule">
        <option value="">Tous</option>
        <option value="auth">Authentification</option>
        <option value="users">Utilisateurs</option>
        <option value="audits">Audits</option>
        <option value="inspecteurs">Inspecteurs</option>
        <option value="structures">Structures</option>
        <option value="parametres">Parametres</option>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label small text-muted mb-1">Recherche (description / IP)</label>
      <input type="text" class="form-control form-control-sm" id="fSearch" placeholder="...">
    </div>
    <div class="col-12 col-md-3 d-flex gap-2">
      <button class="btn btn-sm btn-outline-primary flex-fill" id="btnFilter"><i class="bi bi-funnel me-1"></i>Filtrer</button>
      <button class="btn btn-sm btn-outline-secondary flex-fill" id="btnReset">Reinit.</button>
    </div>
  </div>
</div>

<!-- Tableau -->
<div class="tbl-wrap">
  <table class="tbl">
    <thead><tr>
      <th style="min-width:140px">Date / Heure</th>
      <th>Utilisateur</th>
      <th>Action</th>
      <th>Module</th>
      <th>Description</th>
      <th>IP</th>
    </tr></thead>
    <tbody id="tbody">
      <tr><td colspan="6" class="empty"><span class="spinner-border spinner-border-sm me-2"></span>Chargement...</td></tr>
    </tbody>
  </table>
</div>
<div class="d-flex justify-content-between align-items-center mt-2 px-1">
  <span class="small text-muted" id="pgInfo"></span>
  <div class="btn-group btn-group-sm" id="pgBtns"></div>
</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>
<script>
const CSRF = '<?php echo Security::escape($csrf); ?>';
const API  = AGAI_BASE + '/api/audit-logs';
function post(data){ data=Object.assign({csrf_token:CSRF},data); return $.post(API,data,null,'json'); }

let PAGE=1; const PER=30;

function fmtDt(s){ if(!s) return '-'; const d=new Date(s.replace(' ','T')); return '<span style="font-size:.8rem">'+d.toLocaleDateString('fr-FR')+'</span><br><span style="font-size:.75rem;color:#7b8aa0">'+d.toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit',second:'2-digit'})+'</span>'; }

function actBadge(a){
  const cls='act-'+(a||'default');
  const labels={login:'Connexion',logout:'Deconnexion',login_attempt:'Tentative',create:'Creation',update:'Modification',delete:'Suppression',upload:'Upload',access_denied:'Acces refuse',mail:'Email',error:'Erreur'};
  return '<span class="act-badge '+cls+'">'+(labels[a]||esc(a||'-'))+'</span>';
}

function loadKpi(){
  post({action:'stats'}).done(function(r){
    if(!r.success) return;
    $('#kTotal').text(r.total_24h||0);
    $('#kErrors').text(r.errors_24h||0);
    $('#kLogins').text(r.logins_24h||0);
    $('#kUsers').text(r.users_24h||0);
  });
}

function load(){
  const data={action:'list',page:PAGE,per:PER,
    periode:$('#fPeriode').val(), action_filter:$('#fAction').val(),
    module_filter:$('#fModule').val(), search:$('#fSearch').val().trim()};
  $('#tbody').html('<tr><td colspan="6" class="empty"><span class="spinner-border spinner-border-sm me-2"></span>Chargement...</td></tr>');
  post(data).done(function(r){
    if(!r.success){ $('#tbody').html('<tr><td colspan="6" class="empty">'+esc(r.message||'Erreur')+'</td></tr>'); return; }
    const rows=r.data||[];
    if(!rows.length){ $('#tbody').html('<tr><td colspan="6" class="empty"><i class="bi bi-inbox me-2"></i>Aucun evenement trouve.</td></tr>'); return; }
    $('#tbody').html(rows.map(function(row){
      return '<tr>'
        +'<td>'+fmtDt(row.created_at)+'</td>'
        +'<td><div style="font-size:.85rem;font-weight:600">'+esc(row.nom_user||'Systeme')+'</div>'
        +'<div style="font-size:.74rem;color:#7b8aa0">'+esc(row.email_user||'')+'</div></td>'
        +'<td>'+actBadge(row.action)+'</td>'
        +'<td><span class="mod-badge">'+esc(row.module||'-')+'</span></td>'
        +'<td style="max-width:280px;font-size:.82rem" title="'+esc(row.description||'')+'">'+esc((row.description||'-').slice(0,100)+(row.description&&row.description.length>100?'...':''))+'</td>'
        +'<td><span class="ip-code">'+esc(row.ip_address||'-')+'</span></td>'
        +'</tr>';
    }).join(''));
    const total=r.total||0; const pages=Math.ceil(total/PER);
    $('#pgInfo').text(total+' evenement(s) - Page '+PAGE+'/'+pages);
    let btns='<button class="btn btn-outline-secondary" onclick="changePage('+(PAGE-1)+')" '+(PAGE<=1?'disabled':'')+'>Prec.</button>';
    const from=Math.max(1,PAGE-2), to=Math.min(pages,PAGE+2);
    for(let i=from;i<=to;i++) btns+='<button class="btn btn-'+(i===PAGE?'primary':'outline-secondary')+'" onclick="changePage('+i+')">'+i+'</button>';
    btns+='<button class="btn btn-outline-secondary" onclick="changePage('+(PAGE+1)+')" '+(PAGE>=pages?'disabled':'')+'>Suiv.</button>';
    $('#pgBtns').html(btns);
  }).fail(function(){ $('#tbody').html('<tr><td colspan="6" class="empty">Echec du chargement.</td></tr>'); });
}

function changePage(p){ if(p<1) return; PAGE=p; load(); }
$('#btnRefresh').on('click',function(){ loadKpi(); PAGE=1; load(); });
$('#btnFilter').on('click',function(){ PAGE=1; load(); });
$('#btnReset').on('click',function(){ $('#fPeriode').val('7'); $('#fAction,#fModule').val(''); $('#fSearch').val(''); PAGE=1; load(); });
$('#fSearch').on('keydown',function(e){ if(e.key==='Enter'){PAGE=1;load();} });

loadKpi(); load();
</script>