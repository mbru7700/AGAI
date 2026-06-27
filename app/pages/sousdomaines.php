<?php
/**
 * Module : Sous-domaines - Donnees de structures
 * Meme design que domaines, inspecteurs, operateurs :
 * - KPI + panneau stats masquable
 * - En-tetes tableau bleu ANAC
 * - Bouton Voir : modale detail (domaine parent, fiches NC associees)
 * - Filtres Select2 enrichis
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardPage('sousdomaines');
$csrf      = Security::generateCSRF();
$pageTitle = 'Sous-domaines';
$active    = 'sousdomaines';
$pageIcon  = 'bi-diagram-2';
require_once INCLUDES_PATH . '/layout_head.php';
?>
<style>
.filter-bar{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:14px 16px;box-shadow:0 1px 2px rgba(16,30,54,.04);}
.stat-card{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:14px 16px;display:flex;align-items:center;gap:12px;box-shadow:0 1px 2px rgba(16,30,54,.04);height:100%;}
.stat-ic{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex:0 0 auto;}
.ic-blue{background:rgba(35,64,143,.10);color:#23408F;} .ic-green{background:rgba(30,156,75,.12);color:#1E9C4B;}
.ic-gold{background:rgba(243,195,0,.18);color:#b58a00;} .ic-purple{background:rgba(90,24,154,.10);color:#5a189a;}
.stat-num{font-size:1.5rem;font-weight:700;color:#2C3E50;line-height:1;} .stat-lbl{font-size:.78rem;color:#7b8aa0;}
/* Tableau */
table.tbl{width:100%;border-collapse:separate;border-spacing:0;}
table.tbl thead th{background:#23408F;color:#fff;font-size:.71rem;text-transform:uppercase;letter-spacing:.04em;padding:10px 12px;border:none;font-weight:600;}
table.tbl tbody td{padding:10px 12px;border-bottom:1px solid #f1f4f9;vertical-align:middle;font-size:.86rem;}
table.tbl tbody tr:hover{background:#fafcff;}
.empty{padding:36px;text-align:center;color:#9aa7bd;}
/* Badges */
.b-tag{display:inline-block;padding:.18rem .55rem;border-radius:20px;font-size:.72rem;font-weight:700;white-space:nowrap;margin:.1rem;}
.b-blue{background:#e8f0fe;color:#23408F;} .b-green{background:#d1e7dd;color:#0a5c36;}
.b-gold{background:#fff3cd;color:#856404;} .b-muted{background:#f1f4f9;color:#7b8aa0;}
.b-purple{background:#f0e6ff;color:#5a189a;}
.dom-code{font-family:monospace;font-size:.88rem;font-weight:800;color:#23408F;background:#e8f0fe;padding:.15rem .5rem;border-radius:6px;}
/* Detail modale */
.det-card{border:1px solid #eef1f6;border-radius:12px;overflow:hidden;margin-bottom:10px;}
.det-card-head{background:linear-gradient(135deg,#23408F,#1b3576);color:#fff;padding:9px 15px;font-weight:700;font-size:.83rem;}
.det-card-body{padding:12px 15px;}
.det-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:8px 16px;}
.dl{font-size:.67rem;text-transform:uppercase;color:#7b8aa0;font-weight:700;letter-spacing:.04em;margin-bottom:1px;}
.dv{font-size:.88rem;color:#2C3E50;font-weight:600;border-bottom:1px solid #f1f4f9;padding-bottom:3px;}
.item-row{display:flex;align-items:center;gap:8px;padding:6px 10px;border-radius:8px;border:1px solid #eef1f6;margin-bottom:4px;font-size:.84rem;}
</style>

<div class="page-head">
  <div>
    <h1><i class="bi bi-diagram-2 me-2" style="color:var(--anac-primary)"></i>Sous-domaines</h1>
    <div class="sub">Subdivisions des domaines de surveillance utilises dans les fiches de non-conformite.</div>
  </div>
  <button class="btn btn-anac" id="btnNew"><i class="bi bi-plus-lg me-2"></i>Nouveau sous-domaine</button>
</div>

<!-- Toggle stats -->
<div class="d-flex justify-content-end mb-2">
  <button class="btn btn-sm btn-outline-secondary" id="btnToggleStats">
    <i class="bi bi-bar-chart-line-fill me-1"></i><span id="statsLbl">Afficher les statistiques</span>
  </button>
</div>

<!-- Panneau stats -->
<div id="statsPanel" class="mb-3" style="display:none">
  <div class="row g-3">
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-ic ic-blue"><i class="bi bi-diagram-2-fill"></i></div><div><div class="stat-num" id="st_total">0</div><div class="stat-lbl">Sous-domaines</div></div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-ic ic-green"><i class="bi bi-grid-3x3-gap-fill"></i></div><div><div class="stat-num" id="st_dom">0</div><div class="stat-lbl">Domaines couverts</div></div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-ic ic-gold"><i class="bi bi-exclamation-triangle-fill"></i></div><div><div class="stat-num" id="st_fnc">0</div><div class="stat-lbl">Fiches NC associees</div></div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-ic ic-purple"><i class="bi bi-bar-chart-steps"></i></div><div><div class="stat-num" id="st_avg">0</div><div class="stat-lbl">Moy. SD par domaine</div></div></div></div>
  </div>
</div>

<!-- Filtres -->
<div class="filter-bar mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-md-7">
      <label class="form-label small fw-bold mb-1" style="color:#5b6b85;text-transform:uppercase;font-size:.72rem;letter-spacing:.04em">Domaine parent</label>
      <select id="filterDom" style="width:100%"></select>
    </div>
    <div class="col-md-5">
      <label class="form-label small fw-bold mb-1" style="color:#5b6b85;text-transform:uppercase;font-size:.72rem;letter-spacing:.04em">Recherche rapide</label>
      <input type="text" class="form-control form-control-sm" id="filterSearch" placeholder="Rechercher un sous-domaine...">
    </div>
  </div>
  <div class="mt-2 small text-muted" id="resCount"></div>
</div>

<!-- Tableau -->
<div style="background:#fff;border:1px solid #eef1f6;border-radius:14px;overflow:hidden;box-shadow:0 1px 2px rgba(16,30,54,.04)">
  <table class="tbl">
    <thead>
      <tr>
        <th style="width:42%">Sous-domaine</th>
        <th style="width:26%">Domaine parent</th>
        <th style="width:14%">Fiches NC</th>
        <th style="text-align:right">Actions</th>
      </tr>
    </thead>
    <tbody id="body">
      <tr><td colspan="4" class="empty"><span class="spinner-border spinner-border-sm me-2"></span>Chargement...</td></tr>
    </tbody>
  </table>
</div>

<!-- MODALE : Nouveau / Edition -->
<div class="modal fade" id="sdModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="sdForm" autocomplete="off">
      <div class="modal-header">
        <h5 class="modal-title" id="sdModalTitle">Nouveau sous-domaine</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="s_id">
        <div class="mb-3">
          <label class="form-label fw-bold">Domaine parent <span class="text-danger">*</span></label>
          <select id="s_dom" style="width:100%"></select>
        </div>
        <div class="mb-1">
          <label class="form-label fw-bold">Nom du sous-domaine <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="s_nom" maxlength="255" required placeholder="ex : A-Organisation et infrastructure">
          <div class="form-text" id="s_dup" style="display:none;color:#D32F2F"><i class="bi bi-exclamation-triangle me-1"></i>Ce sous-domaine existe deja pour ce domaine.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" class="btn btn-anac" id="sdSubmit"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- MODALE : Voir detail -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h5 class="modal-title"><i class="bi bi-diagram-2 me-2" style="color:#23408F"></i><span id="viewTitle"></span></h5>
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
const API  = AGAI_BASE + '/api/sousdomaines';
let ROWS=[], DOMAINES=[];
function apiPost(data){ data=Object.assign({csrf_token:CSRF},data); return $.post(API,data,null,'json'); }
function domLabel(d){ return esc(d.nomdomaine)+(d.libel_domaine&&d.libel_domaine.trim()&&d.libel_domaine.trim()!==d.nomdomaine?' - '+esc(d.libel_domaine.trim().substring(0,50)):''); }

/* ===== TOGGLE STATS ===== */
function setStatsVisible(show){
  $('#statsPanel').toggle(show);
  $('#statsLbl').text(show?'Masquer les statistiques':'Afficher les statistiques');
  try{localStorage.setItem('agai_stats_sousdomaines',show?'1':'0');}catch(e){}
  if(show) loadStats();
}
$('#btnToggleStats').on('click',function(){ setStatsVisible($('#statsPanel').is(':hidden')); });

/* ===== STATS ===== */
function loadStats(){
  apiPost({action:'stats'}).done(res => {
    if(!res.success||!res.stats) return;
    const s=res.stats;
    $('#st_total').text(s.total||0);
    $('#st_dom').text(s.dom_couverts||0);
    $('#st_fnc').text(s.nb_fnc||0);
    const avg=Number(s.dom_couverts)>0?Math.round(Number(s.total)/Number(s.dom_couverts)*10)/10:0;
    $('#st_avg').text(avg);
  });
}

/* ===== DOMAINES (filtre + modale) ===== */
function loadDomaines(){
  return apiPost({action:'domaines'}).done(res => {
    if(!res.success) return;
    DOMAINES=res.data||[];
    let fopts='<option value="">Tous les domaines</option>';
    DOMAINES.forEach(d => { fopts+='<option value="'+esc(d.iddomaine)+'">'+domLabel(d)+'</option>'; });
    const fcur=$('#filterDom').val();
    $('#filterDom').html(fopts);
    if(fcur) $('#filterDom').val(fcur);
    if($('#filterDom').hasClass('select2-hidden-accessible')) $('#filterDom').trigger('change.select2');
    let mopts='<option value="">Choisir un domaine...</option>';
    DOMAINES.forEach(d => { mopts+='<option value="'+esc(d.iddomaine)+'">'+domLabel(d)+'</option>'; });
    $('#s_dom').html(mopts);
  });
}

/* ===== LISTE / FILTRE / RENDU ===== */
function rowHtml(sd){
  const used=Number(sd.nb_fnc)>0;
  const parent=sd.nomdomaine?('<span class="b-tag b-blue">'+esc(sd.nomdomaine)+'</span>'):'<span class="b-tag b-muted" style="color:#D32F2F">domaine supprime</span>';
  const fncBadge=used?('<span class="b-tag b-gold">'+sd.nb_fnc+' fiche'+(sd.nb_fnc>1?'s':'')+'</span>'):'<span class="b-tag b-muted">0</span>';
  return '<tr>'
    +'<td><div style="font-weight:600;color:#2C3E50">'+esc(sd.nom_sousdomaine)+'</div></td>'
    +'<td>'+parent+'</td>'
    +'<td>'+fncBadge+'</td>'
    +'<td style="text-align:right;white-space:nowrap">'
    +'<button class="btn btn-xs btn-outline-info me-1 act-view" data-id="'+esc(sd.idsousdomaine)+'" style="padding:3px 7px" title="Voir le detail"><i class="bi bi-eye"></i></button>'
    +'<button class="btn btn-xs btn-outline-primary me-1 act-edit" data-id="'+esc(sd.idsousdomaine)+'" style="padding:3px 7px" title="Modifier"><i class="bi bi-pencil"></i></button>'
    +'<button class="btn btn-xs btn-outline-danger act-del" data-id="'+esc(sd.idsousdomaine)+'" data-lib="'+esc(sd.nom_sousdomaine)+'" data-used="'+(used?1:0)+'" style="padding:3px 7px" title="Supprimer"><i class="bi bi-trash"></i></button>'
    +'</td></tr>';
}
function getFiltered(){
  const dom=$('#filterDom').val();
  const q=($('#filterSearch').val()||'').toLowerCase().trim();
  return ROWS.filter(sd => {
    if(dom&&String(sd.iddomaine)!==String(dom)) return false;
    if(q&&!sd.nom_sousdomaine.toLowerCase().includes(q)) return false;
    return true;
  });
}
function render(){
  const list=getFiltered(); const tb=$('#body');
  if(!list.length){ tb.html('<tr><td colspan="4" class="empty"><i class="bi bi-inbox me-2"></i>Aucun sous-domaine.</td></tr>'); }
  else { tb.html(list.map(rowHtml).join('')); }
  const dom=$('#filterDom').val();
  const domName=dom?(DOMAINES.find(d=>String(d.iddomaine)===String(dom))||{}).nomdomaine:'';
  $('#resCount').html('<i class="bi bi-diagram-2 me-1"></i>'+list.length+' sous-domaine(s)'+(domName?' dans <b>'+esc(domName)+'</b>':' sur '+ROWS.length));
}
function loadList(){
  apiPost({action:'list'}).done(res => {
    if(!res.success){ $('#body').html('<tr><td colspan="4" class="empty">'+esc(res.message||'Erreur')+'</td></tr>'); return; }
    ROWS=res.data||[]; render();
  }).fail(()=>{ $('#body').html('<tr><td colspan="4" class="empty">Echec.</td></tr>'); });
}
$('#filterDom').select2({theme:'bootstrap-5',placeholder:'Tous les domaines',allowClear:true,width:'100%'});
$('#filterDom').on('change',render);
$('#filterSearch').on('input',render);

/* ===== MODALE VOIR DETAIL ===== */
$(document).on('click','.act-view',function(){
  const id=$(this).data('id');
  const row=ROWS.find(sd=>String(sd.idsousdomaine)===String(id));
  $('#viewTitle').text(row?row.nom_sousdomaine:'...');
  $('#viewBody').html('<div class="text-center py-4"><span class="spinner-border text-primary"></span></div>');
  new bootstrap.Modal('#viewModal').show();
  apiPost({action:'detail',idsousdomaine:id}).done(res => {
    if(!res.success){ $('#viewBody').html('<div class="alert alert-danger">'+esc(res.message||'Erreur')+'</div>'); return; }
    const sd=res.data||{}, fncs=res.fncs||[];
    function di(l,v){ return '<div><div class="dl">'+l+'</div><div class="dv">'+(v||'<span style="color:#aab4c0;font-style:italic">-</span>')+'</div></div>'; }
    let html='';
    // Identification
    html+='<div class="det-card"><div class="det-card-head"><i class="bi bi-diagram-2 me-2"></i>Identification</div><div class="det-card-body"><div class="det-row">';
    html+=di('Sous-domaine','<span style="font-weight:700;color:#2C3E50">'+esc(sd.nom_sousdomaine||'')+'</span>');
    html+=di('Domaine parent','<span class="b-tag b-blue">'+esc(sd.nomdomaine||'-')+'</span>'+(sd.libel_domaine&&sd.libel_domaine.trim()?'<div class="text-muted small mt-1">'+esc(sd.libel_domaine.trim().substring(0,80))+'</div>':''));
    html+=di('Fiches NC associees','<span class="b-tag '+(fncs.length>0?'b-gold':'b-muted')+'">'+fncs.length+'</span>');
    html+='</div></div></div>';
    // Fiches NC
    html+='<div class="det-card"><div class="det-card-head"><i class="bi bi-exclamation-triangle me-2"></i>Fiches de non-conformite ('+fncs.length+')</div><div class="det-card-body">';
    if(!fncs.length){
      html+='<div class="text-muted small text-center py-2"><i class="bi bi-check-circle text-success me-1"></i>Aucune fiche de non-conformite associee.</div>';
    } else {
      html+='<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">'
        +'<thead><tr><th style="font-size:.72rem;text-transform:uppercase;color:#5b6b85">Reference</th><th style="font-size:.72rem;text-transform:uppercase;color:#5b6b85">Criticite</th><th style="font-size:.72rem;text-transform:uppercase;color:#5b6b85">Responsable</th><th style="font-size:.72rem;text-transform:uppercase;color:#5b6b85">Statut</th></tr></thead><tbody>';
      fncs.forEach(f => {
        const crit={'majeure':'<span class="b-tag b-gold">Majeure</span>','mineure':'<span class="b-tag b-green">Mineure</span>','critique':'<span class="b-tag" style="background:#f8d7da;color:#842029">Critique</span>'}[String(f.criticite||'').toLowerCase()]||esc(f.criticite||'-');
        const stat=f.statut_fnc?'<span class="b-tag b-blue">'+esc(f.statut_fnc)+'</span>':'-';
        html+='<tr><td style="font-weight:700;font-size:.83rem">'+esc(f.reference_fnc||'-')+'</td><td>'+crit+'</td><td style="font-size:.83rem">'+esc(f.responsable||'-')+'</td><td>'+stat+'</td></tr>';
      });
      html+='</tbody></table></div>';
    }
    html+='</div></div>';
    $('#viewBody').html(html);
  });
});

/* ===== CRUD ===== */
function initDomSelect(){
  if($('#s_dom').hasClass('select2-hidden-accessible')) $('#s_dom').select2('destroy');
  $('#s_dom').select2({theme:'bootstrap-5',dropdownParent:$('#sdModal'),placeholder:'Choisir un domaine...',width:'100%'});
}
$('#btnNew').on('click',function(){
  $('#sdModalTitle').text('Nouveau sous-domaine'); $('#s_id').val(''); $('#s_nom').val(''); $('#s_dup').hide();
  initDomSelect(); $('#s_dom').val('').trigger('change');
  new bootstrap.Modal('#sdModal').show();
});
$(document).on('click','.act-edit',function(){
  const id=$(this).data('id');
  apiPost({action:'get',idsousdomaine:id}).done(res => {
    if(!res.success){ Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'}); return; }
    const sd=res.data;
    $('#sdModalTitle').text('Modifier le sous-domaine'); $('#s_id').val(sd.idsousdomaine);
    $('#s_nom').val(sd.nom_sousdomaine); $('#s_dup').hide();
    initDomSelect(); $('#s_dom').val(String(sd.iddomaine)).trigger('change');
    new bootstrap.Modal('#sdModal').show();
  });
});
let dupTimer=null;
function checkDup(){
  clearTimeout(dupTimer);
  const nom=$('#s_nom').val().trim(), dom=$('#s_dom').val();
  if(!nom||!dom){ $('#s_dup').hide(); return; }
  dupTimer=setTimeout(function(){
    apiPost({action:'check_nom',nom_sousdomaine:nom,iddomaine:dom,idsousdomaine:$('#s_id').val()||0}).done(res=>{ $('#s_dup').toggle(!!(res.success&&res.exists)); });
  },350);
}
$('#s_nom').on('input',checkDup); $(document).on('change','#s_dom',checkDup);
$('#sdForm').on('submit',function(e){
  e.preventDefault();
  const id=$('#s_id').val(), dom=$('#s_dom').val(), nom=$('#s_nom').val().trim();
  if(!dom){ Swal.fire({icon:'warning',title:'Domaine requis',text:'Choisissez le domaine parent.',confirmButtonColor:'#23408F'}); return; }
  if(!nom){ Swal.fire({icon:'warning',title:'Nom requis',text:'Indiquez le nom du sous-domaine.',confirmButtonColor:'#23408F'}); return; }
  const data={action:id?'update':'create',idsousdomaine:id,iddomaine:dom,nom_sousdomaine:nom};
  const btn=$('#sdSubmit'), html=btn.html();
  btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
  apiPost(data).done(res => {
    btn.prop('disabled',false).html(html);
    if(res.success){
      bootstrap.Modal.getInstance(document.getElementById('sdModal')).hide();
      Swal.fire({icon:'success',title:'Enregistre',text:res.message,timer:1600,showConfirmButton:false,timerProgressBar:true});
      loadList(); loadStats(); loadDomaines();
    } else { Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'}); }
  }).fail(()=>{ btn.prop('disabled',false).html(html); Swal.fire({icon:'error',title:'Erreur',text:'Echec.',confirmButtonColor:'#23408F'}); });
});
$(document).on('click','.act-del',function(){
  const id=$(this).data('id'), lib=$(this).data('lib'), used=String($(this).data('used'))==='1';
  Swal.fire({
    icon:used?'warning':'question', title:'Supprimer ce sous-domaine ?',
    html:'<b>'+esc(lib)+'</b>'+(used?'<br><br><span style="color:#D32F2F">Il est utilise par des fiches NC. La suppression sera refusee tant qu\'elles y sont rattachees.</span>':''),
    showCancelButton:true,confirmButtonText:'Supprimer',cancelButtonText:'Annuler',
    confirmButtonColor:'#D32F2F',cancelButtonColor:'#6c757d'
  }).then(r => {
    if(!r.isConfirmed) return;
    apiPost({action:'delete',idsousdomaine:id}).done(res => {
      if(res.success){ Swal.fire({icon:'success',timer:1400,showConfirmButton:false}); loadList(); loadStats(); loadDomaines(); }
      else { Swal.fire({icon:'error',title:'Impossible',text:res.message,confirmButtonColor:'#23408F'}); }
    });
  });
});

/* ===== DEMARRAGE ===== */
loadStats();
loadDomaines().always(loadList);
(function(){ let v='0'; try{v=localStorage.getItem('agai_stats_sousdomaines')||'0';}catch(e){} if(v==='1') setStatsVisible(true); })();
</script>