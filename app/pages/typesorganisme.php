<?php
/**
 * Module : Types d'organisme (page) - Donnees de structures
 * ------------------------------------------------------------
 * CRUD complet de la table `type_organisme`. Memes patterns que le
 * module Domaines : filtre par liste deroulante Select2, tri du plus
 * recent en tete, modale d'ajout/edition, suppression confirmee et
 * protegee (refus si le type est utilise par un exploitant).
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }

Rbac::guardPage('typesorganisme');
$csrf      = Security::generateCSRF();
$pageTitle = 'Types d\'organisme';
$active    = 'typesorganisme';
$pageIcon  = 'bi-tags';

require_once INCLUDES_PATH . '/layout_head.php';
?>

<div class="page-head">
  <div>
    <h1><i class="bi bi-tags me-2" style="color:var(--anac-primary)"></i>Types d'organisme</h1>
    <div class="sub">Donnees de structures &middot; categories des exploitants (operateurs et compagnies).</div>
  </div>
  <button class="btn btn-anac" id="btnNew"><i class="bi bi-plus-lg me-2"></i>Nouveau type</button>
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
  .muted{font-size:.82rem;color:#8a97ab;}
  .empty{padding:38px 14px;text-align:center;color:#9aa7bd;}
</style>

<div class="row g-3 mb-3">
  <div class="col-6"><div class="stat-card"><div class="stat-ic ic-blue"><i class="bi bi-tags-fill"></i></div><div><div class="stat-num" id="st_total">0</div><div class="stat-lbl">Types d'organisme</div></div></div></div>
  <div class="col-6"><div class="stat-card"><div class="stat-ic ic-green"><i class="bi bi-buildings-fill"></i></div><div><div class="stat-num" id="st_orgs">0</div><div class="stat-lbl">Exploitants classes</div></div></div></div>
</div>

<div class="filter-bar mb-3">
  <label class="form-label mb-1 small text-muted"><i class="bi bi-funnel me-1"></i>Filtrer par type</label>
  <select id="filterType" style="width:100%"></select>
</div>

<div class="card" style="border:1px solid #eef1f6;border-radius:14px;overflow:hidden;">
  <div class="table-responsive">
    <table class="tbl">
      <thead>
        <tr>
          <th style="width:46%">Nom du type</th>
          <th style="width:22%">Enregistre le</th>
          <th style="width:18%">Utilisation</th>
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
<div class="modal fade" id="typeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="typeForm" autocomplete="off">
      <div class="modal-header">
        <h5 class="modal-title" id="typeModalTitle">Nouveau type</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="t_id">
        <div class="mb-1">
          <label class="form-label">Nom du type d'organisme <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="t_nom" maxlength="255" required placeholder="ex : Compagnie aerienne, Aeroclub, Organisme de maintenance...">
          <div class="form-text" id="t_dup" style="display:none;color:#D32F2F;">Un type portant ce nom existe deja.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" class="btn btn-anac" id="typeSubmit"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>

<script>
const CSRF = '<?php echo Security::escape($csrf); ?>';
const API  = AGAI_BASE + '/api/typesorganisme';
let ROWS = [];

function apiPost(data){
  data = Object.assign({csrf_token: CSRF}, data);
  return $.post(API, data, null, 'json');
}

/* ---------- Statistiques ---------- */
function loadStats(){
  apiPost({action:'stats'}).done(res => {
    if(!res.success || !res.stats) return;
    $('#st_total').text(res.stats.total || 0);
    $('#st_orgs').text(res.stats.orgs || 0);
  });
}

/* ---------- Liste + filtre ---------- */
function usageBadge(n){
  if(!n) return '<span class="b-dom b-muted">0 exploitant</span>';
  return '<span class="b-dom">'+n+' exploitant'+(n>1?'s':'')+'</span>';
}
function fmtDate(s){
  if(!s) return '-';
  const d = String(s).substring(0,10);
  return d || '-';
}
function rowHtml(t){
  const used = Number(t.nb_org) > 0;
  return '<tr>'
    + '<td><div style="font-weight:600;color:#2C3E50">'+esc(t.nomtypeorg)+'</div></td>'
    + '<td><span class="muted">'+esc(fmtDate(t.datesaizi))+'</span></td>'
    + '<td>'+usageBadge(t.nb_org)+'</td>'
    + '<td style="text-align:right;white-space:nowrap">'
    +   '<button class="btn btn-sm btn-outline-primary me-1 act-edit" data-id="'+esc(t.idtypeorga)+'" title="Modifier"><i class="bi bi-pencil"></i></button>'
    +   '<button class="btn btn-sm btn-outline-danger act-del" data-id="'+esc(t.idtypeorga)+'" data-lib="'+esc(t.nomtypeorg)+'" data-used="'+(used?1:0)+'" title="Supprimer"><i class="bi bi-trash"></i></button>'
    + '</td>'
    + '</tr>';
}
function render(){
  const sel = $('#filterType').val();
  const list = sel ? ROWS.filter(t => String(t.idtypeorga) === String(sel)) : ROWS;
  const tb = $('#body');
  if(!list.length){ tb.html('<tr><td colspan="4" class="empty"><i class="bi bi-inbox me-2"></i>Aucun type.</td></tr>'); return; }
  tb.html(list.map(rowHtml).join(''));
}
function fillFilter(){
  const sel = $('#filterType');
  const cur = sel.val();
  let opts = '<option value="">Tous les types</option>';
  ROWS.forEach(t => { opts += '<option value="'+esc(t.idtypeorga)+'">'+esc(t.nomtypeorg)+'</option>'; });
  sel.html(opts);
  if(cur && ROWS.some(t => String(t.idtypeorga) === String(cur))){ sel.val(cur); }
  if(sel.hasClass('select2-hidden-accessible')) sel.trigger('change.select2');
}
function loadList(){
  apiPost({action:'list'}).done(res => {
    if(!res.success){ $('#body').html('<tr><td colspan="4" class="empty">'+esc(res.message||'Erreur')+'</td></tr>'); return; }
    ROWS = res.data || [];
    fillFilter();
    render();
  }).fail(()=>{ $('#body').html('<tr><td colspan="4" class="empty">Echec du chargement.</td></tr>'); });
}
$('#filterType').select2({theme:'bootstrap-5', placeholder:'Tous les types', allowClear:true, width:'100%'});
$('#filterType').on('change', render);

/* ---------- Nouveau / edition ---------- */
$('#btnNew').on('click', function(){
  $('#typeModalTitle').text('Nouveau type');
  $('#t_id').val(''); $('#t_nom').val(''); $('#t_dup').hide();
  new bootstrap.Modal('#typeModal').show();
});
$(document).on('click', '.act-edit', function(){
  const id = $(this).data('id');
  apiPost({action:'get', idtypeorga:id}).done(res => {
    if(!res.success){ Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'}); return; }
    const t = res.data;
    $('#typeModalTitle').text('Modifier le type');
    $('#t_id').val(t.idtypeorga); $('#t_nom').val(t.nomtypeorg); $('#t_dup').hide();
    new bootstrap.Modal('#typeModal').show();
  });
});

/* Controle de doublon en direct */
let dupTimer = null;
$('#t_nom').on('input', function(){
  clearTimeout(dupTimer);
  const nom = $(this).val().trim();
  if(!nom){ $('#t_dup').hide(); return; }
  dupTimer = setTimeout(function(){
    apiPost({action:'check_nom', nomtypeorg:nom, idtypeorga:$('#t_id').val()||0}).done(res => {
      $('#t_dup').toggle(!!(res.success && res.exists));
    });
  }, 350);
});

/* ---------- Enregistrement ---------- */
$('#typeForm').on('submit', function(e){
  e.preventDefault();
  const id  = $('#t_id').val();
  const nom = $('#t_nom').val().trim();
  if(!nom){ Swal.fire({icon:'warning',title:'Nom requis',text:'Indiquez le nom du type d\'organisme.',confirmButtonColor:'#23408F'}); return; }
  const data = { action: id ? 'update' : 'create', idtypeorga: id, nomtypeorg: nom };
  const btn = $('#typeSubmit'); const html = btn.html();
  btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
  apiPost(data).done(res => {
    btn.prop('disabled', false).html(html);
    if(res.success){
      bootstrap.Modal.getInstance(document.getElementById('typeModal')).hide();
      Swal.fire({icon:'success',title:'Enregistre',text:res.message,timer:1600,showConfirmButton:false,timerProgressBar:true});
      loadList(); loadStats();
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
    title: 'Supprimer ce type ?',
    html: '<b>'+esc(lib)+'</b>' + (used ? '<br><br><span style="color:#D32F2F">Ce type est utilise par des exploitants. La suppression sera refusee tant qu\'ils y sont rattaches.</span>' : ''),
    showCancelButton: true,
    confirmButtonText: 'Supprimer',
    cancelButtonText: 'Annuler',
    confirmButtonColor: '#D32F2F',
    cancelButtonColor: '#6c757d'
  }).then(r => {
    if(!r.isConfirmed) return;
    apiPost({action:'delete', idtypeorga:id}).done(res => {
      if(res.success){
        Swal.fire({icon:'success',title:'Supprime',timer:1400,showConfirmButton:false});
        loadList(); loadStats();
      } else { Swal.fire({icon:'error',title:'Suppression impossible',text:res.message,confirmButtonColor:'#23408F'}); }
    }).fail(()=>{ Swal.fire({icon:'error',title:'Erreur',text:'Echec de la requete.',confirmButtonColor:'#23408F'}); });
  });
});

/* ---------- Demarrage ---------- */
loadStats();
loadList();
</script>