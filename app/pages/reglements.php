<?php
/**
 * Module : Reglements (page) - Donnees de structures
 * ------------------------------------------------------------
 * CRUD complet de la table `reglement`, rattachee a un domaine.
 * Memes patterns : filtre par liste deroulante Select2 (par domaine),
 * tri du plus recent en tete, modale d'ajout/edition (code, libelle,
 * description), suppression confirmee et protegee (refus si le reglement
 * est associe a des audits).
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }

Rbac::guardPage('reglements');
$csrf      = Security::generateCSRF();
$pageTitle = 'Reglements';
$active    = 'reglements';
$pageIcon  = 'bi-journal-text';

require_once INCLUDES_PATH . '/layout_head.php';
?>

<div class="page-head">
  <div>
    <h1><i class="bi bi-journal-text me-2" style="color:var(--anac-primary)"></i>Reglements</h1>
    <div class="sub">Donnees de structures &middot; references reglementaires par domaine de surveillance.</div>
  </div>
  <button class="btn btn-anac" id="btnNew"><i class="bi bi-plus-lg me-2"></i>Nouveau reglement</button>
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
  .b-code{display:inline-block;padding:.2rem .55rem;border-radius:8px;font-size:.78rem;font-weight:700;background:rgba(35,64,143,.08);color:#23408F;font-family:Consolas,monospace;}
  .desc{font-size:.8rem;color:#8a97ab;margin-top:2px;max-width:520px;}
  .empty{padding:38px 14px;text-align:center;color:#9aa7bd;}
</style>

<div class="row g-3 mb-3">
  <div class="col-6"><div class="stat-card"><div class="stat-ic ic-blue"><i class="bi bi-journal-text"></i></div><div><div class="stat-num" id="st_total">0</div><div class="stat-lbl">Reglements</div></div></div></div>
  <div class="col-6"><div class="stat-card"><div class="stat-ic ic-green"><i class="bi bi-grid-3x3-gap-fill"></i></div><div><div class="stat-num" id="st_dom">0</div><div class="stat-lbl">Domaines couverts</div></div></div></div>
</div>

<div class="filter-bar mb-3">
  <label class="form-label mb-1 small text-muted"><i class="bi bi-funnel me-1"></i>Filtrer par domaine</label>
  <select id="filterDom" style="width:100%"></select>
</div>

<div class="card" style="border:1px solid #eef1f6;border-radius:14px;overflow:hidden;">
  <div class="table-responsive">
    <table class="tbl">
      <thead>
        <tr>
          <th style="width:16%">Code</th>
          <th style="width:42%">Libelle</th>
          <th style="width:18%">Domaine</th>
          <th style="width:12%">Audits</th>
          <th style="width:12%;text-align:right">Actions</th>
        </tr>
      </thead>
      <tbody id="body">
        <tr><td colspan="5" class="empty"><span class="spinner-border spinner-border-sm me-2"></span>Chargement...</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- ===== MODALE AJOUT / EDITION ===== -->
<div class="modal fade" id="regModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="regForm" autocomplete="off">
      <div class="modal-header">
        <h5 class="modal-title" id="regModalTitle">Nouveau reglement</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="r_id">
        <div class="mb-3">
          <label class="form-label">Domaine <span class="text-danger">*</span></label>
          <select id="r_dom" style="width:100%"></select>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-md-4">
            <label class="form-label">Code <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="r_code" maxlength="50" required placeholder="ex : RAG 06">
            <div class="form-text" id="r_dup" style="display:none;color:#D32F2F;">Ce code existe deja.</div>
          </div>
          <div class="col-md-8">
            <label class="form-label">Libelle <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="r_lib" maxlength="255" required>
          </div>
        </div>
        <div class="mb-1">
          <label class="form-label">Description <span class="text-muted">(facultatif)</span></label>
          <textarea class="form-control" id="r_desc" rows="3" placeholder="Objet ou portee du reglement..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" class="btn btn-anac" id="regSubmit"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>

<script>
const CSRF = '<?php echo Security::escape($csrf); ?>';
const API  = AGAI_BASE + '/api/reglements';
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

/* ---------- Domaines (filtre + modale) ---------- */
function loadDomaines(){
  return apiPost({action:'domaines'}).done(res => {
    if(!res.success) return;
    DOMAINES = res.data || [];
    let fopts = '<option value="">Tous les domaines</option>';
    DOMAINES.forEach(d => { fopts += '<option value="'+esc(d.iddomaine)+'">'+esc(domLabel(d))+'</option>'; });
    const fcur = $('#filterDom').val();
    $('#filterDom').html(fopts);
    if(fcur) $('#filterDom').val(fcur);
    if($('#filterDom').hasClass('select2-hidden-accessible')) $('#filterDom').trigger('change.select2');
    let mopts = '<option value="">Choisir un domaine...</option>';
    DOMAINES.forEach(d => { mopts += '<option value="'+esc(d.iddomaine)+'">'+esc(domLabel(d))+'</option>'; });
    $('#r_dom').html(mopts);
  });
}

/* ---------- Liste + filtre ---------- */
function usageBadge(n){
  if(!n) return '<span class="b-dom b-muted">0 audit</span>';
  return '<span class="b-dom">'+n+' audit'+(n>1?'s':'')+'</span>';
}
function rowHtml(r){
  const used = Number(r.nb_aud) > 0;
  const parent = r.nomdomaine ? esc(r.nomdomaine) : '<span style="color:#D32F2F">domaine supprime</span>';
  const desc = r.description ? '<div class="desc">'+esc(r.description)+'</div>' : '';
  return '<tr>'
    + '<td><span class="b-code">'+esc(r.code_reglement)+'</span></td>'
    + '<td><div style="font-weight:600;color:#2C3E50">'+esc(r.libelle_reglement)+'</div>'+desc+'</td>'
    + '<td><span class="b-dom">'+parent+'</span></td>'
    + '<td>'+usageBadge(r.nb_aud)+'</td>'
    + '<td style="text-align:right;white-space:nowrap">'
    +   '<button class="btn btn-sm btn-outline-primary me-1 act-edit" data-id="'+esc(r.idreglement)+'" title="Modifier"><i class="bi bi-pencil"></i></button>'
    +   '<button class="btn btn-sm btn-outline-danger act-del" data-id="'+esc(r.idreglement)+'" data-lib="'+esc(r.code_reglement)+'" data-used="'+(used?1:0)+'" title="Supprimer"><i class="bi bi-trash"></i></button>'
    + '</td>'
    + '</tr>';
}
function render(){
  const sel = $('#filterDom').val();
  const list = sel ? ROWS.filter(r => String(r.iddomaine) === String(sel)) : ROWS;
  const tb = $('#body');
  if(!list.length){ tb.html('<tr><td colspan="5" class="empty"><i class="bi bi-inbox me-2"></i>Aucun reglement'+(sel?' pour ce domaine':'')+'.</td></tr>'); return; }
  tb.html(list.map(rowHtml).join(''));
}
function loadList(){
  apiPost({action:'list'}).done(res => {
    if(!res.success){ $('#body').html('<tr><td colspan="5" class="empty">'+esc(res.message||'Erreur')+'</td></tr>'); return; }
    ROWS = res.data || [];
    render();
  }).fail(()=>{ $('#body').html('<tr><td colspan="5" class="empty">Echec du chargement.</td></tr>'); });
}
$('#filterDom').select2({theme:'bootstrap-5', placeholder:'Tous les domaines', allowClear:true, width:'100%'});
$('#filterDom').on('change', render);

/* ---------- Nouveau / edition ---------- */
function initDomSelect(){
  if($('#r_dom').hasClass('select2-hidden-accessible')) $('#r_dom').select2('destroy');
  $('#r_dom').select2({theme:'bootstrap-5', dropdownParent:$('#regModal'), placeholder:'Choisir un domaine...', width:'100%'});
}
$('#btnNew').on('click', function(){
  $('#regModalTitle').text('Nouveau reglement');
  $('#r_id').val(''); $('#r_code').val(''); $('#r_lib').val(''); $('#r_desc').val(''); $('#r_dup').hide();
  initDomSelect(); $('#r_dom').val('').trigger('change');
  new bootstrap.Modal('#regModal').show();
});
$(document).on('click', '.act-edit', function(){
  const id = $(this).data('id');
  apiPost({action:'get', idreglement:id}).done(res => {
    if(!res.success){ Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'}); return; }
    const r = res.data;
    $('#regModalTitle').text('Modifier le reglement');
    $('#r_id').val(r.idreglement); $('#r_code').val(r.code_reglement); $('#r_lib').val(r.libelle_reglement); $('#r_desc').val(r.description||''); $('#r_dup').hide();
    initDomSelect(); $('#r_dom').val(String(r.iddomaine)).trigger('change');
    new bootstrap.Modal('#regModal').show();
  });
});

/* Controle de doublon en direct (code dans le domaine choisi) */
let dupTimer = null;
function checkDup(){
  clearTimeout(dupTimer);
  const code = $('#r_code').val().trim();
  const dom  = $('#r_dom').val();
  if(!code || !dom){ $('#r_dup').hide(); return; }
  dupTimer = setTimeout(function(){
    apiPost({action:'check_code', code_reglement:code, iddomaine:dom, idreglement:$('#r_id').val()||0}).done(res => {
      $('#r_dup').toggle(!!(res.success && res.exists));
    });
  }, 350);
}
$('#r_code').on('input', checkDup);
$(document).on('change', '#r_dom', checkDup);

/* ---------- Enregistrement ---------- */
$('#regForm').on('submit', function(e){
  e.preventDefault();
  const id   = $('#r_id').val();
  const dom  = $('#r_dom').val();
  const code = $('#r_code').val().trim();
  const lib  = $('#r_lib').val().trim();
  if(!dom){ Swal.fire({icon:'warning',title:'Domaine requis',text:'Choisissez le domaine.',confirmButtonColor:'#23408F'}); return; }
  if(!code){ Swal.fire({icon:'warning',title:'Code requis',text:'Indiquez le code du reglement.',confirmButtonColor:'#23408F'}); return; }
  if(!lib){ Swal.fire({icon:'warning',title:'Libelle requis',text:'Indiquez le libelle du reglement.',confirmButtonColor:'#23408F'}); return; }
  const data = { action: id ? 'update' : 'create', idreglement: id, iddomaine: dom, code_reglement: code, libelle_reglement: lib, description: $('#r_desc').val().trim() };
  const btn = $('#regSubmit'); const html = btn.html();
  btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
  apiPost(data).done(res => {
    btn.prop('disabled', false).html(html);
    if(res.success){
      bootstrap.Modal.getInstance(document.getElementById('regModal')).hide();
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
    title: 'Supprimer ce reglement ?',
    html: '<b>'+esc(lib)+'</b>' + (used ? '<br><br><span style="color:#D32F2F">Il est associe a des audits. La suppression sera refusee tant qu\'il y est rattache.</span>' : ''),
    showCancelButton: true,
    confirmButtonText: 'Supprimer',
    cancelButtonText: 'Annuler',
    confirmButtonColor: '#D32F2F',
    cancelButtonColor: '#6c757d'
  }).then(r => {
    if(!r.isConfirmed) return;
    apiPost({action:'delete', idreglement:id}).done(res => {
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