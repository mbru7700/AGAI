<?php
/**
 * Module : Types d'organisme - Donnees de structures
 * Design uniforme inspecteurs / operateurs / domaines / sous-domaines :
 * - KPI + panneau stats masquable
 * - En-tetes tableau bleu ANAC, ordre decroissant
 * - Bouton Voir : modale detail (operateurs rattaches, audits associes)
 * - CRUD inchange (modale creation/edition)
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardPage('typesorganisme');
$csrf      = Security::generateCSRF();
$pageTitle = 'Types d\'organisme';
$active    = 'typesorganisme';
$pageIcon  = 'bi-tags';
require_once INCLUDES_PATH . '/layout_head.php';
?>
<style>
.filter-bar{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:14px 16px;box-shadow:0 1px 2px rgba(16,30,54,.04);}
.stat-card{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:14px 16px;display:flex;align-items:center;gap:12px;box-shadow:0 1px 2px rgba(16,30,54,.04);height:100%;}
.stat-ic{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex:0 0 auto;}
.ic-blue{background:rgba(35,64,143,.10);color:#23408F;} .ic-green{background:rgba(30,156,75,.12);color:#1E9C4B;}
.ic-gold{background:rgba(243,195,0,.18);color:#b58a00;} .ic-purple{background:rgba(90,24,154,.10);color:#5a189a;}
.ic-dark{background:rgba(44,62,80,.09);color:#2C3E50;} .ic-red{background:rgba(211,47,47,.10);color:#D32F2F;}
.stat-num{font-size:1.5rem;font-weight:700;color:#2C3E50;line-height:1;} .stat-lbl{font-size:.78rem;color:#7b8aa0;}
/* Tableau */
table.tbl{width:100%;border-collapse:separate;border-spacing:0;}
table.tbl thead th{background:#23408F;color:#fff;font-size:.71rem;text-transform:uppercase;letter-spacing:.04em;padding:10px 12px;border:none;font-weight:600;white-space:nowrap;}
table.tbl tbody td{padding:10px 12px;border-bottom:1px solid #f1f4f9;vertical-align:middle;font-size:.86rem;}
table.tbl tbody tr:hover{background:#fafcff;}
.empty{padding:36px;text-align:center;color:#9aa7bd;}
/* Badges */
.b-tag{display:inline-block;padding:.18rem .55rem;border-radius:20px;font-size:.72rem;font-weight:700;white-space:nowrap;margin:.1rem;}
.b-blue{background:#e8f0fe;color:#23408F;} .b-green{background:#d1e7dd;color:#0a5c36;}
.b-gold{background:#fff3cd;color:#856404;} .b-muted{background:#f1f4f9;color:#7b8aa0;}
.b-red{background:#f8d7da;color:#842029;} .b-purple{background:#f0e6ff;color:#5a189a;}
/* Detail modale */
.det-card{border:1px solid #eef1f6;border-radius:12px;overflow:hidden;margin-bottom:10px;}
.det-card-head{background:linear-gradient(135deg,#23408F,#1b3576);color:#fff;padding:9px 15px;font-weight:700;font-size:.83rem;}
.det-card-body{padding:12px 15px;}
.det-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:8px 16px;}
.dl{font-size:.67rem;text-transform:uppercase;color:#7b8aa0;font-weight:700;letter-spacing:.04em;margin-bottom:1px;}
.dv{font-size:.88rem;color:#2C3E50;font-weight:600;border-bottom:1px solid #f1f4f9;padding-bottom:3px;}
.item-row{display:flex;align-items:center;gap:8px;padding:6px 10px;border-radius:8px;border:1px solid #eef1f6;margin-bottom:4px;font-size:.84rem;flex-wrap:wrap;}
.item-row:hover{background:#fafcff;}
</style>

<div class="page-head">
  <div>
    <h1><i class="bi bi-tags me-2" style="color:var(--anac-primary)"></i>Types d'organisme</h1>
    <div class="sub">Classification des operateurs soumis a la surveillance continue de l'ANAC Gabon.</div>
  </div>
  <button class="btn btn-anac" id="btnNew"><i class="bi bi-plus-lg me-2"></i>Nouveau type</button>
</div>

<!-- Toggle stats -->
<div class="d-flex justify-content-end mb-2">
  <button class="btn btn-sm btn-outline-secondary" id="btnToggleStats">
    <i class="bi bi-bar-chart-line-fill me-1"></i><span id="statsLbl">Afficher les statistiques</span>
  </button>
</div>

<!-- Panneau stats masquable -->
<div id="statsPanel" class="mb-3" style="display:none">
  <div class="row g-3">
    <div class="col-6 col-md-2"><div class="stat-card"><div class="stat-ic ic-blue"><i class="bi bi-tags-fill"></i></div><div><div class="stat-num" id="st_total">0</div><div class="stat-lbl">Types d'organisme</div></div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card"><div class="stat-ic ic-green"><i class="bi bi-buildings-fill"></i></div><div><div class="stat-num" id="st_orgs">0</div><div class="stat-lbl">Operateurs classes</div></div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card"><div class="stat-ic ic-gold"><i class="bi bi-clipboard-check-fill"></i></div><div><div class="stat-num" id="st_audits">0</div><div class="stat-lbl">Audits associes</div></div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card"><div class="stat-ic ic-purple"><i class="bi bi-shield-check-fill"></i></div><div><div class="stat-num" id="st_habs">0</div><div class="stat-lbl">Habilitations liees</div></div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card"><div class="stat-ic ic-dark"><i class="bi bi-grid-1x2-fill"></i></div><div><div class="stat-num" id="st_avec">0</div><div class="stat-lbl">Types utilises</div></div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card"><div class="stat-ic ic-red"><i class="bi bi-slash-circle-fill"></i></div><div><div class="stat-num" id="st_sans">0</div><div class="stat-lbl">Types non utilises</div></div></div></div>
  </div>
</div>

<!-- Filtre -->
<div class="filter-bar mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-md-7">
      <label class="form-label small fw-bold mb-1" style="color:#5b6b85;text-transform:uppercase;font-size:.72rem;letter-spacing:.04em">Rechercher un type</label>
      <select id="filterType" style="width:100%"></select>
    </div>
    <div class="col-md-3">
      <label class="form-label small fw-bold mb-1" style="color:#5b6b85;text-transform:uppercase;font-size:.72rem;letter-spacing:.04em">Utilisation</label>
      <select id="filterUsage" class="form-select form-select-sm">
        <option value="">Tous</option>
        <option value="1">Avec operateurs</option>
        <option value="0">Non utilise</option>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small fw-bold mb-1" style="color:#5b6b85;text-transform:uppercase;font-size:.72rem;letter-spacing:.04em">Reset</label>
      <button class="btn btn-sm btn-outline-secondary w-100" id="btnReset"><i class="bi bi-x-lg me-1"></i>Reinit.</button>
    </div>
  </div>
  <div class="mt-2 small text-muted" id="resCount"></div>
</div>

<!-- Tableau -->
<div style="background:#fff;border:1px solid #eef1f6;border-radius:14px;overflow:hidden;box-shadow:0 1px 2px rgba(16,30,54,.04)">
  <table class="tbl">
    <thead>
      <tr>
        <th style="width:50%">Libelle du type</th>
        <th style="width:15%">Operateurs</th>
        <th style="width:15%">Audits</th>
        <th style="width:8%">Habilit.</th>
        <th style="text-align:right">Actions</th>
      </tr>
    </thead>
    <tbody id="body">
      <tr><td colspan="5" class="empty"><span class="spinner-border spinner-border-sm me-2"></span>Chargement...</td></tr>
    </tbody>
  </table>
</div>

<!-- MODALE : Nouveau / Edition -->
<div class="modal fade" id="typeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="typeForm" autocomplete="off">
      <div class="modal-header">
        <h5 class="modal-title" id="typeModalTitle">Nouveau type d'organisme</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="t_id">
        <div class="mb-2">
          <label class="form-label fw-bold">Libelle du type <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="t_nom" maxlength="255" required
                 placeholder="ex : EXPLOITANT AERODROME">
          <div class="form-text" id="t_dup" style="display:none;color:#D32F2F">
            <i class="bi bi-exclamation-triangle me-1"></i>Ce type existe deja.
          </div>
          <div class="form-text">Libelle en majuscules, unique dans le systeme.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" class="btn btn-anac" id="typeSubmit">
          <i class="bi bi-check-lg me-1"></i>Enregistrer
        </button>
      </div>
    </form>
  </div>
</div>

<!-- MODALE : Voir detail -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" style="max-width:86vw">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h5 class="modal-title">
          <i class="bi bi-tags me-2" style="color:#23408F"></i><span id="viewTitle"></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-3" id="viewBody">
        <div class="text-center py-4"><span class="spinner-border text-primary"></span></div>
      </div>
    </div>
  </div>
</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>
<script>
const CSRF = '<?php echo Security::escape($csrf); ?>';
const API  = AGAI_BASE + '/api/typesorganisme';
let ROWS = [];

function apiPost(data){ data=Object.assign({csrf_token:CSRF},data); return $.post(API,data,null,'json'); }
function fmtDate(s){
  if(!s) return '-';
  const d = String(s).substring(0,10);
  if(d === '0000-00-00' || d === '') return '-';
  const p = d.split('-'); return p.length===3 ? p[2]+'/'+p[1]+'/'+p[0] : s;
}
function nbBadge(n, icon, cls){
  const c = Number(n)||0;
  return '<span class="b-tag '+(c>0?cls:'b-muted')+'"><i class="bi bi-'+icon+' me-1"></i>'+c+'</span>';
}

/* ===== TOGGLE STATS ===== */
function setStatsVisible(show){
  $('#statsPanel').toggle(show);
  $('#statsLbl').text(show ? 'Masquer les statistiques' : 'Afficher les statistiques');
  try{ localStorage.setItem('agai_stats_typesorganisme', show?'1':'0'); } catch(e){}
  if(show) loadStats();
}
$('#btnToggleStats').on('click', function(){ setStatsVisible($('#statsPanel').is(':hidden')); });

/* ===== STATS ===== */
function loadStats(){
  apiPost({action:'stats'}).done(res => {
    if(!res.success||!res.stats) return;
    const s = res.stats;
    $('#st_total').text(s.total||0);
    $('#st_orgs').text(s.orgs||0);
    $('#st_audits').text(s.audits||0);
    $('#st_habs').text(s.habs||0);
    $('#st_avec').text(s.avec_orgs||0);
    $('#st_sans').text(s.sans_orgs||0);
  });
}

/* ===== LISTE / FILTRE / RENDU ===== */
function rowHtml(t){
  const used = Number(t.nb_org) > 0;
  const nb_aud = Number(t.nb_aud||0);
  const nb_hab = Number(t.nb_hab||0);
  return '<tr>'
    +'<td><div style="font-weight:700;font-size:.9rem;color:#2C3E50">'+esc(t.nomtypeorg)+'</div>'
    +(t.datesaizi&&t.datesaizi.trim()&&t.datesaizi.trim()!=='0'?'<div class="text-muted" style="font-size:.73rem">Enregistre le '+esc(t.datesaizi.substring(0,10))+'</div>':'')
    +'</td>'
    +'<td>'+nbBadge(t.nb_org,'buildings','b-blue')+'</td>'
    +'<td>'+nbBadge(nb_aud,'clipboard-check','b-gold')+'</td>'
    +'<td>'+nbBadge(nb_hab,'shield-check','b-purple')+'</td>'
    +'<td style="text-align:right;white-space:nowrap">'
    +'<button class="btn btn-xs btn-outline-info me-1 act-view" data-id="'+esc(t.idtypeorga)+'" style="padding:3px 7px" title="Voir le detail"><i class="bi bi-eye"></i></button>'
    +'<button class="btn btn-xs btn-outline-primary me-1 act-edit" data-id="'+esc(t.idtypeorga)+'" style="padding:3px 7px" title="Modifier"><i class="bi bi-pencil"></i></button>'
    +'<button class="btn btn-xs btn-outline-danger act-del" data-id="'+esc(t.idtypeorga)+'" data-lib="'+esc(t.nomtypeorg)+'" data-used="'+(used?1:0)+'" style="padding:3px 7px" title="Supprimer"><i class="bi bi-trash"></i></button>'
    +'</td></tr>';
}

function getFiltered(){
  const sel  = $('#filterType').val();
  const usage= $('#filterUsage').val();
  return ROWS.filter(t => {
    if(sel   && String(t.idtypeorga) !== String(sel))  return false;
    if(usage === '1' && Number(t.nb_org) === 0)        return false;
    if(usage === '0' && Number(t.nb_org) > 0)          return false;
    return true;
  });
}

function render(){
  const list = getFiltered(); const tb = $('#body');
  if(!list.length){ tb.html('<tr><td colspan="5" class="empty"><i class="bi bi-inbox me-2"></i>Aucun type d\'organisme.</td></tr>'); }
  else { tb.html(list.map(rowHtml).join('')); }
  $('#resCount').html('<i class="bi bi-tags me-1"></i>'+list.length+' type(s) affiche(s) sur '+ROWS.length);
}

function fillFilter(){
  const sel  = $('#filterType'), cur = sel.val();
  let opts = '<option value="">Tous les types</option>';
  ROWS.forEach(t => { opts += '<option value="'+esc(t.idtypeorga)+'">'+esc(t.nomtypeorg)+'</option>'; });
  sel.html(opts);
  if(cur && ROWS.some(t => String(t.idtypeorga) === String(cur))) sel.val(cur);
  if(sel.hasClass('select2-hidden-accessible')) sel.trigger('change.select2');
}

function loadList(){
  apiPost({action:'list'}).done(res => {
    if(!res.success){ $('#body').html('<tr><td colspan="5" class="empty">'+esc(res.message||'Erreur')+'</td></tr>'); return; }
    ROWS = res.data||[]; fillFilter(); render();
  }).fail(()=>{ $('#body').html('<tr><td colspan="5" class="empty">Echec.</td></tr>'); });
}

$('#filterType').select2({theme:'bootstrap-5', placeholder:'Tous les types', allowClear:true, width:'100%'});
$('#filterType, #filterUsage').on('change', render);
$('#btnReset').on('click', function(){
  $('#filterType').val('').trigger('change');
  $('#filterUsage').val('');
  render();
});

/* ===== MODALE VOIR DETAIL ===== */
$(document).on('click', '.act-view', function(){
  const id = $(this).data('id');
  const row = ROWS.find(t => String(t.idtypeorga) === String(id));
  $('#viewTitle').text(row ? row.nomtypeorg : '...');
  $('#viewBody').html('<div class="text-center py-4"><span class="spinner-border text-primary"></span></div>');
  new bootstrap.Modal('#viewModal').show();
  apiPost({action:'detail', idtypeorga:id}).done(res => {
    if(!res.success){ $('#viewBody').html('<div class="alert alert-danger">'+esc(res.message||'Erreur')+'</div>'); return; }
    const t = res.data||{}, orgs = res.operateurs||[], auds = res.audits||[];

    function di(l,v){ return '<div><div class="dl">'+l+'</div><div class="dv">'+(v||'<span style="color:#aab4c0;font-style:italic">-</span>')+'</div></div>'; }

    let html = '';

    // Identification
    html += '<div class="det-card"><div class="det-card-head"><i class="bi bi-tags me-2"></i>Identification</div>'
      +'<div class="det-card-body"><div class="det-row">'
      +di('Libelle','<span style="font-weight:700">'+esc(t.nomtypeorg||'')+'</span>')
      +di('Operateurs classes','<span class="b-tag b-blue">'+esc(orgs.length)+'</span>')
      +di('Audits associes','<span class="b-tag b-gold">'+esc(auds.length)+'</span>')
      +di('Date enregistrement', t.datesaizi&&t.datesaizi.trim()&&t.datesaizi.trim()!=='0'?esc(t.datesaizi.substring(0,10)):'Non renseignee')
      +'</div></div></div>';

    // Operateurs
    html += '<div class="det-card"><div class="det-card-head"><i class="bi bi-buildings me-2"></i>Operateurs de ce type ('+orgs.length+')</div><div class="det-card-body">';
    if(!orgs.length){
      html += '<div class="text-muted small text-center py-2"><i class="bi bi-info-circle me-1"></i>Aucun operateur classe sous ce type.</div>';
    } else {
      orgs.forEach(o => {
        const statCls = (o.statutorga||'').toLowerCase() === 'actif' ? 'b-green'
          : (o.statutorga||'').toLowerCase() === 'suspendu' ? 'b-gold'
          : (o.statutorga||'').toLowerCase() === 'retire'   ? 'b-red' : 'b-muted';
        html += '<div class="item-row">'
          +'<span style="font-weight:700;flex:1">'+esc(o.nomorga)+'</span>'
          +(o.trigrorganisme?'<span class="b-tag b-muted" style="font-size:.7rem">'+esc(o.trigrorganisme)+'</span>':'')
          +(o.statutorga?'<span class="b-tag '+statCls+'" style="font-size:.7rem">'+esc(o.statutorga)+'</span>':'')
          +'</div>';
      });
    }
    html += '</div></div>';

    // Audits recents
    html += '<div class="det-card"><div class="det-card-head"><i class="bi bi-clipboard-check me-2"></i>Audits associes ('+auds.length+')</div><div class="det-card-body">';
    if(!auds.length){
      html += '<div class="text-muted small text-center py-2"><i class="bi bi-info-circle me-1"></i>Aucun audit pour ce type d\'organisme.</div>';
    } else {
      const STATUT={1:'Planifie',2:'Reporte',3:'Effectue',4:'Suspendu',6:'Annule',7:'Inopine'};
      const STATUT_CLS={1:'b-blue',2:'b-gold',3:'b-green',4:'b-red',6:'b-muted',7:'b-purple'};
      auds.slice(0, 15).forEach(a => {
        const s = STATUT[a.statut]||('-'); const sc = STATUT_CLS[a.statut]||'b-muted';
        html += '<div class="item-row">'
          +'<span style="font-family:monospace;font-size:.82rem;font-weight:700;color:#23408F">'+esc(a.num_audit||'-')+'</span>'
          +'<span class="b-tag b-blue" style="font-size:.7rem">'+esc(a.type_activite||'')+'</span>'
          +'<span class="text-muted small">'+fmtDate(a.date_previsionnelle)+'</span>'
          +'<span class="b-tag '+sc+'" style="font-size:.7rem">'+s+'</span>'
          +'<span class="text-muted small ms-auto">'+esc(a.nomorga||'')+'</span>'
          +'</div>';
      });
      if(auds.length > 15) html += '<div class="text-muted small mt-2">... et '+(auds.length-15)+' autres audits.</div>';
    }
    html += '</div></div>';

    $('#viewBody').html(html);
  });
});

/* ===== CRUD ===== */
$('#btnNew').on('click', function(){
  $('#typeModalTitle').text("Nouveau type d'organisme");
  $('#t_id').val(''); $('#t_nom').val(''); $('#t_dup').hide();
  new bootstrap.Modal('#typeModal').show();
  setTimeout(()=>$('#t_nom').focus(), 300);
});

$(document).on('click', '.act-edit', function(){
  const id = $(this).data('id');
  apiPost({action:'get', idtypeorga:id}).done(res => {
    if(!res.success){ Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'}); return; }
    const t = res.data;
    $('#typeModalTitle').text("Modifier le type d'organisme");
    $('#t_id').val(t.idtypeorga); $('#t_nom').val(t.nomtypeorg); $('#t_dup').hide();
    new bootstrap.Modal('#typeModal').show();
    setTimeout(()=>$('#t_nom').select(), 300);
  });
});

let dupTimer = null;
$('#t_nom').on('input', function(){
  clearTimeout(dupTimer);
  const nom = $(this).val().trim();
  if(!nom){ $('#t_dup').hide(); return; }
  dupTimer = setTimeout(function(){
    apiPost({action:'check_nom', nomtypeorg:nom, idtypeorga:$('#t_id').val()||0})
      .done(res => { $('#t_dup').toggle(!!(res.success && res.exists)); });
  }, 350);
});

$('#typeForm').on('submit', function(e){
  e.preventDefault();
  const id = $('#t_id').val(), nom = $('#t_nom').val().trim().toUpperCase();
  if(!nom){ Swal.fire({icon:'warning',title:'Libelle requis',confirmButtonColor:'#23408F'}); return; }
  const data = {action: id?'update':'create', idtypeorga:id, nomtypeorg:nom};
  const btn = $('#typeSubmit'), html = btn.html();
  btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
  apiPost(data).done(res => {
    btn.prop('disabled',false).html(html);
    if(res.success){
      bootstrap.Modal.getInstance(document.getElementById('typeModal')).hide();
      Swal.fire({icon:'success',title:'Enregistre',text:res.message,timer:1600,showConfirmButton:false,timerProgressBar:true});
      loadList(); loadStats();
    } else { Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'}); }
  }).fail(()=>{ btn.prop('disabled',false).html(html); Swal.fire({icon:'error',text:'Echec.',confirmButtonColor:'#23408F'}); });
});

$(document).on('click', '.act-del', function(){
  const id=$(this).data('id'), lib=$(this).data('lib'), used=String($(this).data('used'))==='1';
  Swal.fire({
    icon: used?'warning':'question', title:"Supprimer ce type ?",
    html:'<b>'+esc(lib)+'</b>'+(used?'<br><br><span style="color:#D32F2F">Rattache a des operateurs. La suppression sera refusee.</span>':''),
    showCancelButton:true, confirmButtonText:'Supprimer', cancelButtonText:'Annuler',
    confirmButtonColor:'#D32F2F', cancelButtonColor:'#6c757d'
  }).then(r => {
    if(!r.isConfirmed) return;
    apiPost({action:'delete', idtypeorga:id}).done(res => {
      if(res.success){ Swal.fire({icon:'success',timer:1400,showConfirmButton:false}); loadList(); loadStats(); }
      else { Swal.fire({icon:'error',title:'Impossible',text:res.message,confirmButtonColor:'#23408F'}); }
    });
  });
});

/* ===== DEMARRAGE ===== */
loadStats(); loadList();
(function(){
  let v='0'; try{v=localStorage.getItem('agai_stats_typesorganisme')||'0';}catch(e){}
  if(v==='1') setStatsVisible(true);
})();
</script>