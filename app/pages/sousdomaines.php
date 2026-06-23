<?php
/**
 * Module : Sous-domaines (page) - Donnees de structures
 * ------------------------------------------------------------
 * CRUD complet de la table `sous_domaine`, rattachee a un domaine parent.
 * Memes patterns que les autres modules : filtre par liste deroulante
 * Select2 (par domaine parent), tri du plus recent en tete, modale
 * d'ajout/edition avec selection du domaine, suppression confirmee et
 * protegee (refus si le sous-domaine est utilise par une fiche).
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }

Rbac::guardPage('sousdomaines');
$csrf      = Security::generateCSRF();
$pageTitle = 'Sous-domaines';
$active    = 'sousdomaines';
$pageIcon  = 'bi-diagram-2';

require_once INCLUDES_PATH . '/layout_head.php';
?>

<div class="page-head">
  <div>
    <h1><i class="bi bi-diagram-2 me-2" style="color:var(--anac-primary)"></i>Sous-domaines</h1>
    <div class="sub">Donnees de structures &middot; subdivisions d'un domaine de surveillance.</div>
  </div>
  <button class="btn btn-anac" id="btnNew"><i class="bi bi-plus-lg me-2"></i>Nouveau sous-domaine</button>
</div>

<style>
  .filter-bar{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:14px 16px;box-shadow:0 1px 2px rgba(16,30,54,.04);}
  .stat-card{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:14px 16px;display:flex;align-items:center;gap:12px;box-shadow:0 1px 2px rgba(16,30,54,.04);height:100%;}
  .stat-ic{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex:0 0 auto;}
  .ic-blue{background:rgba(35,64,143,.10);color:#23408F;} .ic-green{background:rgba(30,156,75,.12);color:#1E9C4B;}
  .stat-num{font-size:1.5rem;font-weight:700;color:#2C3E50;line-height:1;} .stat-lbl{font-size:.78rem;color:#7b8aa0;}
  table.tbl{width:100%;border-collapse:separate;border-spacing:0;}
  table.tbl thead th{background:#f7f9fc;color:#5b6b85;font-size:.74rem;text-transform:uppercase;letter-spacing:.04em;padding:11px 14px;border-bottom:1px solid #eef1f6;text-align:left;}
  table.tbl tbody td{padding:12px 14px;border-bottom:1px solid #f1f4f9;vertical-align:middle;}
  table.tbl tbody tr:hover{background:#fafcff;}
  .b-dom{display:inline-block;padding:.2rem .55rem;border-radius:20px;font-size:.72rem;font-weight:600;background:#eef2f9;color:#23408F;}
  .b-muted{background:#f1f4f9;color:#7b8aa0;}
  .empty{padding:38px 14px;text-align:center;color:#9aa7bd;}
</style>

<div class="row g-3 mb-3">
  <div class="col-6"><div class="stat-card"><div class="stat-ic ic-blue"><i class="bi bi-diagram-2-fill"></i></div><div><div class="stat-num" id="st_total">0</div><div class="stat-lbl">Sous-domaines</div></div></div></div>
  <div class="col-6"><div class="stat-card"><div class="stat-ic ic-green"><i class="bi bi-grid-3x3-gap-fill"></i></div><div><div class="stat-num" id="st_dom">0</div><div class="stat-lbl">Domaines couverts</div></div></div></div>
</div>

<div class="filter-bar mb-3">
  <label class="form-label mb-1 small text-muted"><i class="bi bi-funnel me-1"></i>Filtrer par domaine parent</label>
  <select id="filterDom" style="width:100%"></select>
</div>

<div class="card" style="border:1px solid #eef1f6;border-radius:14px;overflow:hidden;">
  <div class="table-responsive">
    <table class="tbl">
      <thead>
        <tr>
          <th style="width:40%">Sous-domaine</th>
          <th style="width:30%">Domaine parent</th>
          <th style="width:16%">Utilisation</th>
          <th style="width:14%;text-align:right">Actions</th>
        </tr>
      </thead>
      <tbody id="body">
        <tr><td colspan="4" class="empty"><span class="spinner-border spinner-border-sm me-2"></span>Chargement...</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- ===== MODALE AJOUT / EDITION ===== -->
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
          <label class="form-label">Domaine parent <span class="text-danger">*</span></label>
          <select id="s_dom" style="width:100%"></select>
        </div>
        <div class="mb-1">
          <label class="form-label">Nom du sous-domaine <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="s_nom" maxlength="255" required>
          <div class="form-text" id="s_dup" style="display:none;color:#D32F2F;">Ce sous-domaine existe deja pour ce domaine.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" class="btn btn-anac" id="sdSubmit"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>

<script>
const CSRF = '<?php echo Security::escape($csrf); ?>';
const API  = AGAI_BASE + '/api/sousdomaines';
let ROWS = [];
let DOMAINES = [];

function apiPost(data){
  data = Object.assign({csrf_token: CSRF}, data);
  return $.post(API, data, null, 'json');
}

function domLabel(d){ return d.nomdomaine + (d.libel_domaine && d.libel_domaine !== d.nomdomaine ? ' - ' + d.libel_domaine : ''); }

/* ---------- Statistiques ---------- */
function loadStats(){
  apiPost({action:'stats'}).done(res => {
    if(!res.success || !res.stats) return;
    $('#st_total').text(res.stats.total || 0);
    $('#st_dom').text(res.stats.dom_couverts || 0);
  });
}

/* ---------- Domaines parents (filtre + modale) ---------- */
function loadDomaines(){
  return apiPost({action:'domaines'}).done(res => {
    if(!res.success) return;
    DOMAINES = res.data || [];
    // Filtre
    let fopts = '<option value="">Tous les domaines</option>';
    DOMAINES.forEach(d => { fopts += '<option value="'+esc(d.iddomaine)+'">'+esc(domLabel(d))+'</option>'; });
    const fcur = $('#filterDom').val();
    $('#filterDom').html(fopts);
    if(fcur) $('#filterDom').val(fcur);
    if($('#filterDom').hasClass('select2-hidden-accessible')) $('#filterDom').trigger('change.select2');
    // Modale
    let mopts = '<option value="">Choisir un domaine...</option>';
    DOMAINES.forEach(d => { mopts += '<option value="'+esc(d.iddomaine)+'">'+esc(domLabel(d))+'</option>'; });
    $('#s_dom').html(mopts);
  });
}

/* ---------- Liste + filtre ---------- */
function usageBadge(n){
  if(!n) return '<span class="b-dom b-muted">0 fiche</span>';
  return '<span class="b-dom">'+n+' fiche'+(n>1?'s':'')+'</span>';
}
function rowHtml(sd){
  const used = Number(sd.nb_fnc) > 0;
  const parent = sd.nomdomaine ? esc(sd.nomdomaine) : '<span style="color:#D32F2F">domaine supprime</span>';
  return '<tr>'
    + '<td><div style="font-weight:600;color:#2C3E50">'+esc(sd.nom_sousdomaine)+'</div></td>'
    + '<td><span class="b-dom">'+parent+'</span></td>'
    + '<td>'+usageBadge(sd.nb_fnc)+'</td>'
    + '<td style="text-align:right;white-space:nowrap">'
    +   '<button class="btn btn-sm btn-outline-primary me-1 act-edit" data-id="'+esc(sd.idsousdomaine)+'" title="Modifier"><i class="bi bi-pencil"></i></button>'
    +   '<button class="btn btn-sm btn-outline-danger act-del" data-id="'+esc(sd.idsousdomaine)+'" data-lib="'+esc(sd.nom_sousdomaine)+'" data-used="'+(used?1:0)+'" title="Supprimer"><i class="bi bi-trash"></i></button>'
    + '</td>'
    + '</tr>';
}
function render(){
  const sel = $('#filterDom').val();
  const list = sel ? ROWS.filter(sd => String(sd.iddomaine) === String(sel)) : ROWS;
  const tb = $('#body');
  if(!list.length){ tb.html('<tr><td colspan="4" class="empty"><i class="bi bi-inbox me-2"></i>Aucun sous-domaine'+(sel?' pour ce domaine':'')+'.</td></tr>'); return; }
  tb.html(list.map(rowHtml).join(''));
}
function loadList(){
  apiPost({action:'list'}).done(res => {
    if(!res.success){ $('#body').html('<tr><td colspan="4" class="empty">'+esc(res.message||'Erreur')+'</td></tr>'); return; }
    ROWS = res.data || [];
    render();
  }).fail(()=>{ $('#body').html('<tr><td colspan="4" class="empty">Echec du chargement.</td></tr>'); });
}
$('#filterDom').select2({theme:'bootstrap-5', placeholder:'Tous les domaines', allowClear:true, width:'100%'});
$('#filterDom').on('change', render);

/* ---------- Nouveau / edition ---------- */
function initDomSelect(){
  if($('#s_dom').hasClass('select2-hidden-accessible')) $('#s_dom').select2('destroy');
  $('#s_dom').select2({theme:'bootstrap-5', dropdownParent:$('#sdModal'), placeholder:'Choisir un domaine...', width:'100%'});
}
$('#btnNew').on('click', function(){
  $('#sdModalTitle').text('Nouveau sous-domaine');
  $('#s_id').val(''); $('#s_nom').val(''); $('#s_dup').hide();
  initDomSelect(); $('#s_dom').val('').trigger('change');
  new bootstrap.Modal('#sdModal').show();
});
$(document).on('click', '.act-edit', function(){
  const id = $(this).data('id');
  apiPost({action:'get', idsousdomaine:id}).done(res => {
    if(!res.success){ Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'}); return; }
    const sd = res.data;
    $('#sdModalTitle').text('Modifier le sous-domaine');
    $('#s_id').val(sd.idsousdomaine); $('#s_nom').val(sd.nom_sousdomaine); $('#s_dup').hide();
    initDomSelect(); $('#s_dom').val(String(sd.iddomaine)).trigger('change');
    new bootstrap.Modal('#sdModal').show();
  });
});

/* Controle de doublon en direct (dans le domaine choisi) */
let dupTimer = null;
function checkDup(){
  clearTimeout(dupTimer);
  const nom = $('#s_nom').val().trim();
  const dom = $('#s_dom').val();
  if(!nom || !dom){ $('#s_dup').hide(); return; }
  dupTimer = setTimeout(function(){
    apiPost({action:'check_nom', nom_sousdomaine:nom, iddomaine:dom, idsousdomaine:$('#s_id').val()||0}).done(res => {
      $('#s_dup').toggle(!!(res.success && res.exists));
    });
  }, 350);
}
$('#s_nom').on('input', checkDup);
$(document).on('change', '#s_dom', checkDup);

/* ---------- Enregistrement ---------- */
$('#sdForm').on('submit', function(e){
  e.preventDefault();
  const id  = $('#s_id').val();
  const dom = $('#s_dom').val();
  const nom = $('#s_nom').val().trim();
  if(!dom){ Swal.fire({icon:'warning',title:'Domaine requis',text:'Choisissez le domaine parent.',confirmButtonColor:'#23408F'}); return; }
  if(!nom){ Swal.fire({icon:'warning',title:'Nom requis',text:'Indiquez le nom du sous-domaine.',confirmButtonColor:'#23408F'}); return; }
  const data = { action: id ? 'update' : 'create', idsousdomaine: id, iddomaine: dom, nom_sousdomaine: nom };
  const btn = $('#sdSubmit'); const html = btn.html();
  btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
  apiPost(data).done(res => {
    btn.prop('disabled', false).html(html);
    if(res.success){
      bootstrap.Modal.getInstance(document.getElementById('sdModal')).hide();
      Swal.fire({icon:'success',title:'Enregistre',text:res.message,timer:1600,showConfirmButton:false,timerProgressBar:true});
      loadList(); loadStats(); loadDomaines();
    } else { Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'}); }
  }).fail(()=>{ btn.prop('disabled', false).html(html); Swal.fire({icon:'error',title:'Erreur',text:'Echec de la requete.',confirmButtonColor:'#23408F'}); });
});

/* ---------- Suppression ---------- */
$(document).on('click', '.act-del', function(){
  const id  = $(this).data('id');
  const lib = $(this).data('lib');
  const used = String($(this).data('used')) === '1';
  Swal.fire({
    icon: used ? 'warning' : 'question',
    title: 'Supprimer ce sous-domaine ?',
    html: '<b>'+esc(lib)+'</b>' + (used ? '<br><br><span style="color:#D32F2F">Il est utilise par des fiches de non-conformite. La suppression sera refusee tant qu\'elles y sont rattachees.</span>' : ''),
    showCancelButton: true,
    confirmButtonText: 'Supprimer',
    cancelButtonText: 'Annuler',
    confirmButtonColor: '#D32F2F',
    cancelButtonColor: '#6c757d'
  }).then(r => {
    if(!r.isConfirmed) return;
    apiPost({action:'delete', idsousdomaine:id}).done(res => {
      if(res.success){
        Swal.fire({icon:'success',title:'Supprime',timer:1400,showConfirmButton:false});
        loadList(); loadStats(); loadDomaines();
      } else { Swal.fire({icon:'error',title:'Suppression impossible',text:res.message,confirmButtonColor:'#23408F'}); }
    }).fail(()=>{ Swal.fire({icon:'error',title:'Erreur',text:'Echec de la requete.',confirmButtonColor:'#23408F'}); });
  });
});

/* ---------- Demarrage ---------- */
loadStats();
loadDomaines().always(loadList);
</script>