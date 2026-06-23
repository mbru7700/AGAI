<?php
/**
 * Page : Tentatives de connexion - Consultation uniquement
 * Lit les entrees action='login_attempt' dans audit_logs
 * + champ login_attempts / locked_until dans users
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardPage('parametres');

$csrf      = Security::generateCSRF();
$pageTitle = 'Tentatives de connexion';
$active    = 'login_attempts';
$pageIcon  = 'bi-shield-exclamation';
require_once INCLUDES_PATH . '/layout_head.php';
?>
<style>
.stat-pill{display:inline-flex;align-items:center;gap:6px;padding:.35rem .8rem;border-radius:20px;font-size:.8rem;font-weight:700;}
.pill-ok{background:#d1e7dd;color:#0a5c36;}
.pill-warn{background:#fff3cd;color:#856404;}
.pill-danger{background:#f8d7da;color:#842029;}
.filter-row{background:#fff;border:1px solid #eef1f6;border-radius:12px;padding:12px 16px;margin-bottom:14px;box-shadow:0 1px 2px rgba(16,30,54,.04);}
.tbl-wrap{background:#fff;border:1px solid #eef1f6;border-radius:14px;overflow:hidden;box-shadow:0 1px 2px rgba(16,30,54,.04);}
table.tbl{width:100%;border-collapse:separate;border-spacing:0;}
table.tbl thead th{background:#f7f9fc;color:#5b6b85;font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;padding:10px 13px;border-bottom:1px solid #eef1f6;white-space:nowrap;}
table.tbl tbody td{padding:10px 13px;border-bottom:1px solid #f1f4f9;vertical-align:middle;font-size:.88rem;}
table.tbl tbody tr:hover{background:#fafcff;}
.empty{padding:30px;text-align:center;color:#9aa7bd;}
.ip-code{font-family:monospace;background:#f5f7fa;padding:.15rem .45rem;border-radius:5px;font-size:.82rem;color:#23408F;}
.lock-badge{background:#f8d7da;color:#842029;font-size:.72rem;font-weight:700;padding:.15rem .45rem;border-radius:10px;}
</style>

<div class="page-head">
  <div>
    <h1><i class="bi bi-shield-exclamation me-2" style="color:#D32F2F"></i>Tentatives de connexion</h1>
    <div class="sub">Consultation des echecs et blocages d'authentification. Lecture seule.</div>
  </div>
  <button class="btn btn-outline-secondary btn-sm" id="btnRefresh"><i class="bi bi-arrow-clockwise me-1"></i>Actualiser</button>
</div>

<!-- Stats rapides -->
<div class="row g-3 mb-3" id="statsRow">
  <div class="col-6 col-md-3"><div class="tbl-wrap p-3 text-center"><div class="fs-4 fw-700 text-danger" id="st_total">-</div><div class="small text-muted">Total tentatives (24h)</div></div></div>
  <div class="col-6 col-md-3"><div class="tbl-wrap p-3 text-center"><div class="fs-4 fw-700 text-warning" id="st_uniq">-</div><div class="small text-muted">IP uniques (24h)</div></div></div>
  <div class="col-6 col-md-3"><div class="tbl-wrap p-3 text-center"><div class="fs-4 fw-700" style="color:#23408F" id="st_locked">-</div><div class="small text-muted">Comptes bloques</div></div></div>
  <div class="col-6 col-md-3"><div class="tbl-wrap p-3 text-center"><div class="fs-4 fw-700 text-danger" id="st_7j">-</div><div class="small text-muted">Total tentatives (7j)</div></div></div>
</div>

<!-- Comptes bloques -->
<div id="lockedBlock" style="display:none" class="mb-3">
  <div class="tbl-wrap">
    <div class="p-3 border-bottom d-flex align-items-center gap-2">
      <i class="bi bi-lock-fill text-danger"></i>
      <span class="fw-bold" style="color:#D32F2F">Comptes actuellement bloques</span>
    </div>
    <div id="lockedList"></div>
  </div>
</div>

<!-- Filtres -->
<div class="filter-row d-flex gap-3 flex-wrap align-items-end">
  <div>
    <label class="form-label small text-muted mb-1">Periode</label>
    <select class="form-select form-select-sm" id="fPeriode" style="min-width:140px">
      <option value="1">Dernieres 24h</option>
      <option value="7" selected>7 derniers jours</option>
      <option value="30">30 derniers jours</option>
      <option value="0">Tout</option>
    </select>
  </div>
  <div>
    <label class="form-label small text-muted mb-1">Recherche (email / IP)</label>
    <input type="text" class="form-control form-control-sm" id="fSearch" placeholder="..." style="min-width:200px">
  </div>
  <button class="btn btn-sm btn-outline-primary" id="btnFilter"><i class="bi bi-funnel me-1"></i>Filtrer</button>
</div>

<!-- Tableau -->
<div class="tbl-wrap">
  <table class="tbl">
    <thead><tr>
      <th>Date / Heure</th>
      <th>Email tente</th>
      <th>Adresse IP</th>
      <th>Resultat</th>
      <th>User-Agent</th>
    </tr></thead>
    <tbody id="tbody"><tr><td colspan="5" class="empty"><span class="spinner-border spinner-border-sm me-2"></span>Chargement...</td></tr></tbody>
  </table>
</div>
<div id="pagination" class="d-flex justify-content-between align-items-center mt-2 px-1">
  <span class="small text-muted" id="pgInfo"></span>
  <div class="btn-group btn-group-sm" id="pgBtns"></div>
</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>
<script>
const CSRF = '<?php echo Security::escape($csrf); ?>';
const API  = AGAI_BASE + '/api/login-attempts';
function post(data){ data=Object.assign({csrf_token:CSRF},data); return $.post(API,data,null,'json'); }

let PAGE=1; const PER=25;

function fmtDt(s){ if(!s) return '-'; const d=new Date(s.replace(' ','T')); return d.toLocaleDateString('fr-FR')+' '+d.toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit',second:'2-digit'}); }

function shortUA(ua){ if(!ua) return '-'; const m=ua.match(/\(([^)]+)\)/); return m?m[1].split(';')[0].trim():ua.slice(0,50); }

function resultBadge(desc){
  desc=String(desc||'').toLowerCase();
  if(desc.includes('non trouv') || desc.includes('not found')) return '<span class="stat-pill pill-warn">Email inconnu</span>';
  if(desc.includes('verrou') || desc.includes('lock')) return '<span class="stat-pill pill-danger">Compte verrouille</span>';
  if(desc.includes('mot de passe') || desc.includes('password')) return '<span class="stat-pill pill-danger">Mot de passe errone</span>';
  if(desc.includes('2fa') || desc.includes('code')) return '<span class="stat-pill pill-warn">Code 2FA invalide</span>';
  return '<span class="stat-pill pill-warn">Echec</span>';
}

function loadStats(){
  post({action:'stats'}).done(function(r){
    if(!r.success) return;
    $('#st_total').text(r.total_24h||0);
    $('#st_uniq').text(r.uniq_ip_24h||0);
    $('#st_locked').text(r.comptes_bloques||0);
    $('#st_7j').text(r.total_7j||0);
    if(r.locked && r.locked.length){
      let h='';
      r.locked.forEach(function(u){
        h+='<div class="d-flex align-items-center gap-3 p-3 border-bottom"><i class="bi bi-person-fill-lock text-danger"></i>'
          +'<div style="flex:1"><b>'+esc(u.email)+'</b> <small class="text-muted">('+esc(u.nom)+' '+esc(u.prenom)+')</small></div>'
          +'<span class="lock-badge">'+esc(u.login_attempts)+' tentatives</span>'
          +'<span class="small text-muted">Bloque jusqu\'au '+esc(u.locked_until)+'</span>'
          +'</div>';
      });
      $('#lockedList').html(h);
      $('#lockedBlock').show();
    } else { $('#lockedBlock').hide(); }
  });
}

function load(){
  const data={action:'list',page:PAGE,per:PER,periode:$('#fPeriode').val(),search:$('#fSearch').val().trim()};
  $('#tbody').html('<tr><td colspan="5" class="empty"><span class="spinner-border spinner-border-sm me-2"></span>Chargement...</td></tr>');
  post(data).done(function(r){
    if(!r.success){ $('#tbody').html('<tr><td colspan="5" class="empty">'+esc(r.message||'Erreur')+'</td></tr>'); return; }
    const rows=r.data||[];
    if(!rows.length){ $('#tbody').html('<tr><td colspan="5" class="empty"><i class="bi bi-check-circle me-2 text-success"></i>Aucune tentative dans cette periode.</td></tr>'); return; }
    $('#tbody').html(rows.map(function(row){
      return '<tr>'
        +'<td style="white-space:nowrap;font-size:.82rem">'+esc(fmtDt(row.created_at))+'</td>'
        +'<td>'+esc(row.email||row.description?.match(/Email:\s*([^\s,]+)/i)?.[1]||'-')+'</td>'
        +'<td><span class="ip-code">'+esc(row.ip_address||'-')+'</span></td>'
        +'<td>'+resultBadge(row.description)+'</td>'
        +'<td class="text-muted" style="font-size:.75rem;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="'+esc(row.user_agent||'')+'">'+esc(shortUA(row.user_agent))+'</td>'
        +'</tr>';
    }).join(''));
    // Pagination
    const total=r.total||0; const pages=Math.ceil(total/PER);
    $('#pgInfo').text('Page '+PAGE+'/'+pages+' - '+total+' tentative(s)');
    let btns='';
    btns+='<button class="btn btn-outline-secondary" onclick="changePage('+(PAGE-1)+')" '+(PAGE<=1?'disabled':'')+'>Prec.</button>';
    for(let i=Math.max(1,PAGE-2);i<=Math.min(pages,PAGE+2);i++){
      btns+='<button class="btn btn-'+(i===PAGE?'primary':'outline-secondary')+'" onclick="changePage('+i+')">'+i+'</button>';
    }
    btns+='<button class="btn btn-outline-secondary" onclick="changePage('+(PAGE+1)+')" '+(PAGE>=pages?'disabled':'')+'>Suiv.</button>';
    $('#pgBtns').html(btns);
  }).fail(function(){ $('#tbody').html('<tr><td colspan="5" class="empty">Echec du chargement.</td></tr>'); });
}

function changePage(p){ if(p<1) return; PAGE=p; load(); }
$('#btnRefresh').on('click', function(){ loadStats(); PAGE=1; load(); });
$('#btnFilter').on('click', function(){ PAGE=1; load(); });
$('#fSearch').on('keydown',function(e){ if(e.key==='Enter'){ PAGE=1; load(); } });

loadStats();
load();
</script>