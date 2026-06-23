<?php
/**
 * Module : Domaines (page) - Donnees de structures
 * ------------------------------------------------------------
 * CRUD complet de la table `domaine` : liste avec recherche dynamique,
 * cartes de statistiques, creation / edition en fenetre modale,
 * suppression confirmee. Memes patterns de securite que les autres
 * modules (CSRF, requetes preparees cote endpoint, journalisation).
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }

Rbac::guardPage('domaines');
$csrf      = Security::generateCSRF();
$pageTitle = 'Domaines';
$active    = 'domaines';
$pageIcon  = 'bi-grid-3x3-gap';

require_once INCLUDES_PATH . '/layout_head.php';
?>

<div class="page-head">
  <div>
    <h1><i class="bi bi-grid-3x3-gap me-2" style="color:var(--anac-primary)"></i>Domaines</h1>
    <div class="sub">Donnees de structures &middot; domaines de surveillance utilises par les habilitations, audits et reglements.</div>
  </div>
  <button class="btn btn-anac" id="btnNew"><i class="bi bi-plus-lg me-2"></i>Nouveau domaine</button>
</div>

<style>
  .filter-bar{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:14px 16px;box-shadow:0 1px 2px rgba(16,30,54,.04);}
  .stat-card{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:14px 16px;display:flex;align-items:center;gap:12px;box-shadow:0 1px 2px rgba(16,30,54,.04);height:100%;}
  .stat-ic{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex:0 0 auto;}
  .ic-blue{background:rgba(35,64,143,.10);color:#23408F;} .ic-green{background:rgba(30,156,75,.12);color:#1E9C4B;} .ic-gold{background:rgba(243,195,0,.18);color:#b58a00;}
  .stat-num{font-size:1.5rem;font-weight:700;color:#2C3E50;line-height:1;} .stat-lbl{font-size:.78rem;color:#7b8aa0;}
  table.tbl{width:100%;border-collapse:separate;border-spacing:0;}
  table.tbl thead th{background:#f7f9fc;color:#5b6b85;font-size:.74rem;text-transform:uppercase;letter-spacing:.04em;padding:11px 14px;border-bottom:1px solid #eef1f6;text-align:left;}
  table.tbl tbody td{padding:12px 14px;border-bottom:1px solid #f1f4f9;vertical-align:middle;}
  table.tbl tbody tr:hover{background:#fafcff;}
  .b-dom{display:inline-block;padding:.2rem .55rem;border-radius:20px;font-size:.72rem;font-weight:600;background:#eef2f9;color:#23408F;margin:1px 2px;}
  .b-muted{background:#f1f4f9;color:#7b8aa0;}
  .dom-code{font-size:.8rem;color:#8a97ab;}
  .empty{padding:38px 14px;text-align:center;color:#9aa7bd;}
</style>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-4"><div class="stat-card"><div class="stat-ic ic-blue"><i class="bi bi-grid-3x3-gap-fill"></i></div><div><div class="stat-num" id="st_total">0</div><div class="stat-lbl">Domaines</div></div></div></div>
  <div class="col-6 col-md-4"><div class="stat-card"><div class="stat-ic ic-green"><i class="bi bi-diagram-2-fill"></i></div><div><div class="stat-num" id="st_sous">0</div><div class="stat-lbl">Sous-domaines</div></div></div></div>
  <div class="col-6 col-md-4"><div class="stat-card"><div class="stat-ic ic-gold"><i class="bi bi-journal-text"></i></div><div><div class="stat-num" id="st_regs">0</div><div class="stat-lbl">Reglements</div></div></div></div>
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
          <th style="width:26%">Nom du domaine</th>
          <th style="width:36%">Libelle</th>
          <th style="width:24%">Utilisation</th>
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
<div class="modal fade" id="domModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="domForm" autocomplete="off">
      <div class="modal-header">
        <h5 class="modal-title" id="domModalTitle">Nouveau domaine</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="d_id">
        <div class="mb-3">
          <label class="form-label">Nom du domaine <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="d_nom" maxlength="255" required placeholder="ex : AGA, PEL, OPS, AIR...">
          <div class="form-text" id="d_dup" style="display:none;color:#D32F2F;">Un domaine portant ce nom existe deja.</div>
        </div>
        <div class="mb-1">
          <label class="form-label">Libelle du domaine <span class="text-muted">(facultatif)</span></label>
          <input type="text" class="form-control" id="d_libel" maxlength="255">
          <div class="form-text">Description complete. Si vide, le nom est repris automatiquement.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" class="btn btn-anac" id="domSubmit"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>

<script>
const CSRF = '<?php echo Security::escape($csrf); ?>';
const API  = AGAI_BASE + '/api/domaines';
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
    $('#st_sous').text(res.stats.sous || 0);
    $('#st_regs').text(res.stats.regs || 0);
  });
}

/* ---------- Liste + recherche ---------- */
function badge(n, label, cls){
  if(!n) return '<span class="b-dom b-muted">0 '+label+'</span>';
  return '<span class="b-dom '+(cls||'')+'">'+n+' '+label+'</span>';
}
function rowHtml(d){
  const used = (Number(d.nb_sd)+Number(d.nb_reg)+Number(d.nb_hab)) > 0;
  return '<tr>'
    + '<td><div style="font-weight:700;color:#23408F">'+esc(d.nomdomaine)+'</div></td>'
    + '<td><div style="color:#2C3E50">'+esc(d.libel_domaine||'-')+'</div></td>'
    + '<td>'+badge(d.nb_sd,'sous-dom.')+badge(d.nb_reg,'regl.')+badge(d.nb_hab,'habil.')+'</td>'
    + '<td style="text-align:right;white-space:nowrap">'
    +   '<button class="btn btn-sm btn-outline-primary me-1 act-edit" data-id="'+esc(d.iddomaine)+'" title="Modifier"><i class="bi bi-pencil"></i></button>'
    +   '<button class="btn btn-sm btn-outline-danger act-del" data-id="'+esc(d.iddomaine)+'" data-lib="'+esc(d.nomdomaine)+'" data-used="'+(used?1:0)+'" title="Supprimer"><i class="bi bi-trash"></i></button>'
    + '</td>'
    + '</tr>';
}
function render(){
  const sel = $('#filterDom').val();
  const list = sel ? ROWS.filter(d => String(d.iddomaine) === String(sel)) : ROWS;
  const tb = $('#body');
  if(!list.length){ tb.html('<tr><td colspan="4" class="empty"><i class="bi bi-inbox me-2"></i>Aucun domaine.</td></tr>'); return; }
  tb.html(list.map(rowHtml).join(''));
}
function fillFilter(){
  const sel = $('#filterDom');
  const cur = sel.val();
  let opts = '<option value="">Tous les domaines</option>';
  ROWS.forEach(d => { opts += '<option value="'+esc(d.iddomaine)+'">'+esc(d.nomdomaine)+(d.libel_domaine && d.libel_domaine!==d.nomdomaine ? ' - '+esc(d.libel_domaine) : '')+'</option>'; });
  sel.html(opts);
  if(cur && ROWS.some(d => String(d.iddomaine) === String(cur))){ sel.val(cur); }
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
$('#filterDom').select2({theme:'bootstrap-5', placeholder:'Tous les domaines', allowClear:true, width:'100%'});
$('#filterDom').on('change', render);

/* ---------- Nouveau / edition ---------- */
$('#btnNew').on('click', function(){
  $('#domModalTitle').text('Nouveau domaine');
  $('#d_id').val(''); $('#d_libel').val(''); $('#d_nom').val(''); $('#d_dup').hide();
  new bootstrap.Modal('#domModal').show();
});
$(document).on('click', '.act-edit', function(){
  const id = $(this).data('id');
  apiPost({action:'get', iddomaine:id}).done(res => {
    if(!res.success){ Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'}); return; }
    const d = res.data;
    $('#domModalTitle').text('Modifier le domaine');
    $('#d_id').val(d.iddomaine); $('#d_libel').val(d.libel_domaine); $('#d_nom').val(d.nomdomaine); $('#d_dup').hide();
    new bootstrap.Modal('#domModal').show();
  });
});

/* Controle de doublon en direct (sur le nom) */
let dupTimer = null;
$('#d_nom').on('input', function(){
  clearTimeout(dupTimer);
  const nom = $(this).val().trim();
  if(!nom){ $('#d_dup').hide(); return; }
  dupTimer = setTimeout(function(){
    apiPost({action:'check_nom', nomdomaine:nom, iddomaine:$('#d_id').val()||0}).done(res => {
      $('#d_dup').toggle(!!(res.success && res.exists));
    });
  }, 350);
});

/* ---------- Enregistrement ---------- */
$('#domForm').on('submit', function(e){
  e.preventDefault();
  const id  = $('#d_id').val();
  const nom = $('#d_nom').val().trim();
  if(!nom){ Swal.fire({icon:'warning',title:'Nom requis',text:'Indiquez le nom du domaine (ex : AGA, PEL).',confirmButtonColor:'#23408F'}); return; }
  const data = { action: id ? 'update' : 'create', iddomaine: id, nomdomaine: nom, libel_domaine: $('#d_libel').val().trim() };
  const btn = $('#domSubmit'); const html = btn.html();
  btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
  apiPost(data).done(res => {
    btn.prop('disabled', false).html(html);
    if(res.success){
      bootstrap.Modal.getInstance(document.getElementById('domModal')).hide();
      Swal.fire({icon:'success',title:'Enregistre',text:res.message,timer:1600,showConfirmButton:false,timerProgressBar:true});
      loadList(); loadStats();
    } else { Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'}); }
  }).fail(()=>{ btn.prop('disabled', false).html(html); Swal.fire({icon:'error',title:'Erreur',text:'Echec de la requete.',confirmButtonColor:'#23408F'}); });
});

/* ---------- Suppression ---------- */
$(document).on('click', '.act-del', function(){
  const id = $(this).data('id');
  const lib = $(this).data('lib');
  const used = String($(this).data('used')) === '1';
  Swal.fire({
    icon: used ? 'warning' : 'question',
    title: 'Supprimer ce domaine ?',
    html: '<b>'+esc(lib)+'</b>' + (used ? '<br><br><span style="color:#D32F2F">Ce domaine est utilise ailleurs. La suppression sera refusee tant qu\'il est rattache a des sous-domaines, reglements ou habilitations.</span>' : ''),
    showCancelButton: true,
    confirmButtonText: 'Supprimer',
    cancelButtonText: 'Annuler',
    confirmButtonColor: '#D32F2F',
    cancelButtonColor: '#6c757d'
  }).then(r => {
    if(!r.isConfirmed) return;
    apiPost({action:'delete', iddomaine:id}).done(res => {
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