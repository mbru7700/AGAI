<?php
/**
 * Module : Sites d'inspection (page) - Donnees de structures
 * ------------------------------------------------------------
 * CRUD complet de la table `site` (indicateurs OACI). Memes patterns que
 * les autres referentiels : filtre par liste deroulante Select2, derniers
 * enregistrements en tete, modale d'ajout/edition (pays en liste deroulante),
 * controle de doublon sur l'indicateur OACI, suppression confirmee et protegee.
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }

Rbac::guardPage('sites');
$csrf      = Security::generateCSRF();
$pageTitle = 'Sites d\'inspection';
$active    = 'sites';
$pageIcon  = 'bi-geo-alt';

require_once INCLUDES_PATH . '/layout_head.php';
?>

<div class="page-head">
  <div>
    <h1><i class="bi bi-geo-alt me-2" style="color:var(--anac-primary)"></i>Sites d'inspection</h1>
    <div class="sub">Donnees de structures &middot; sites identifies par leur indicateur OACI (ex : FOOL).</div>
  </div>
  <button class="btn btn-anac" id="btnNew"><i class="bi bi-plus-lg me-2"></i>Nouveau site</button>
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
  .b-oaci{display:inline-block;padding:.2rem .6rem;border-radius:8px;font-size:.8rem;font-weight:700;background:rgba(35,64,143,.10);color:#23408F;letter-spacing:.05em;}
  .b-dom{display:inline-block;padding:.2rem .55rem;border-radius:20px;font-size:.72rem;font-weight:600;background:#eef2f9;color:#23408F;}
  .b-muted{background:#f1f4f9;color:#7b8aa0;}
  .muted{font-size:.85rem;color:#8a97ab;}
  .empty{padding:38px 14px;text-align:center;color:#9aa7bd;}
</style>

<div class="row g-3 mb-3">
  <div class="col-6"><div class="stat-card"><div class="stat-ic ic-blue"><i class="bi bi-geo-alt-fill"></i></div><div><div class="stat-num" id="st_total">0</div><div class="stat-lbl">Sites</div></div></div></div>
  <div class="col-6"><div class="stat-card"><div class="stat-ic ic-green"><i class="bi bi-globe-americas"></i></div><div><div class="stat-num" id="st_pays">0</div><div class="stat-lbl">Pays couverts</div></div></div></div>
</div>

<div class="filter-bar mb-3">
  <label class="form-label mb-1 small text-muted"><i class="bi bi-funnel me-1"></i>Filtrer par site</label>
  <select id="filterSite" style="width:100%"></select>
</div>

<div class="card" style="border:1px solid #eef1f6;border-radius:14px;overflow:hidden;">
  <div class="table-responsive">
    <table class="tbl">
      <thead>
        <tr>
          <th style="width:16%">Indicateur OACI</th>
          <th style="width:34%">Nom du site</th>
          <th style="width:18%">Ville</th>
          <th style="width:18%">Pays</th>
          <th style="width:14%;text-align:right">Actions</th>
        </tr>
      </thead>
      <tbody id="body">
        <tr><td colspan="5" class="empty"><span class="spinner-border spinner-border-sm me-2"></span>Chargement...</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- ===== MODALE AJOUT / EDITION ===== -->
<div class="modal fade" id="siteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="siteForm" autocomplete="off">
      <div class="modal-header">
        <h5 class="modal-title" id="siteModalTitle">Nouveau site</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="s_id">
        <div class="row g-3">
          <div class="col-md-5">
            <label class="form-label">Indicateur OACI <span class="text-danger">*</span></label>
            <input type="text" class="form-control text-uppercase" id="s_oaci" maxlength="10" required placeholder="ex : FOOL">
            <div class="form-text" id="s_dup" style="display:none;color:#D32F2F;">Cet indicateur OACI existe deja.</div>
          </div>
          <div class="col-md-7">
            <label class="form-label">Nom du site <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="s_nom" maxlength="150" required placeholder="ex : Aeroport de Libreville">
          </div>
          <div class="col-md-6">
            <label class="form-label">Pays</label>
            <select id="s_pays" style="width:100%"></select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Ville</label>
            <input type="text" class="form-control" id="s_ville" maxlength="150" placeholder="ex : Libreville">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" class="btn btn-anac" id="siteSubmit"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>

<script>
const CSRF = '<?php echo Security::escape($csrf); ?>';
const API  = AGAI_BASE + '/api/sites';
let ROWS = [];
let PAYS = [];

function apiPost(data){
  data = Object.assign({csrf_token: CSRF}, data);
  return $.post(API, data, null, 'json');
}

/* ---------- Statistiques ---------- */
function loadStats(){
  apiPost({action:'stats'}).done(res => {
    if(!res.success || !res.stats) return;
    $('#st_total').text(res.stats.total || 0);
    $('#st_pays').text(res.stats.pays_couverts || 0);
  });
}

/* ---------- Pays (modale) ---------- */
function loadPays(){
  return apiPost({action:'pays'}).done(res => {
    if(!res.success) return;
    PAYS = res.data || [];
    let opts = '<option value="">Aucun / non precise</option>';
    PAYS.forEach(p => { opts += '<option value="'+esc(p.idpays)+'">'+esc(p.nompays)+'</option>'; });
    $('#s_pays').html(opts);
  });
}

/* ---------- Liste + filtre ---------- */
function rowHtml(s){
  const used = Number(s.nb_aud) > 0;
  return '<tr>'
    + '<td><span class="b-oaci">'+esc(s.indicateur_oaci)+'</span></td>'
    + '<td><div style="font-weight:600;color:#2C3E50">'+esc(s.nomsite)+'</div>'
    +     (used ? '<div class="muted">'+s.nb_aud+' audit'+(s.nb_aud>1?'s':'')+'</div>' : '')+'</td>'
    + '<td>'+esc(s.ville||'-')+'</td>'
    + '<td>'+(s.nompays ? '<span class="b-dom">'+esc(s.nompays)+'</span>' : '<span class="b-dom b-muted">-</span>')+'</td>'
    + '<td style="text-align:right;white-space:nowrap">'
    +   '<button class="btn btn-sm btn-outline-primary me-1 act-edit" data-id="'+esc(s.idsite)+'" title="Modifier"><i class="bi bi-pencil"></i></button>'
    +   '<button class="btn btn-sm btn-outline-danger act-del" data-id="'+esc(s.idsite)+'" data-lib="'+esc(s.indicateur_oaci)+'" data-used="'+(used?1:0)+'" title="Supprimer"><i class="bi bi-trash"></i></button>'
    + '</td>'
    + '</tr>';
}
function render(){
  const sel = $('#filterSite').val();
  const list = sel ? ROWS.filter(s => String(s.idsite) === String(sel)) : ROWS;
  const tb = $('#body');
  if(!list.length){ tb.html('<tr><td colspan="5" class="empty"><i class="bi bi-inbox me-2"></i>Aucun site.</td></tr>'); return; }
  tb.html(list.map(rowHtml).join(''));
}
function fillFilter(){
  const sel = $('#filterSite');
  const cur = sel.val();
  let opts = '<option value="">Tous les sites</option>';
  ROWS.forEach(s => { opts += '<option value="'+esc(s.idsite)+'">'+esc(s.indicateur_oaci)+' - '+esc(s.nomsite)+'</option>'; });
  sel.html(opts);
  if(cur && ROWS.some(s => String(s.idsite) === String(cur))){ sel.val(cur); }
  if(sel.hasClass('select2-hidden-accessible')) sel.trigger('change.select2');
}
function loadList(){
  apiPost({action:'list'}).done(res => {
    if(!res.success){ $('#body').html('<tr><td colspan="5" class="empty">'+esc(res.message||'Erreur')+'</td></tr>'); return; }
    ROWS = res.data || [];
    fillFilter();
    render();
  }).fail(()=>{ $('#body').html('<tr><td colspan="5" class="empty">Echec du chargement.</td></tr>'); });
}
$('#filterSite').select2({theme:'bootstrap-5', placeholder:'Tous les sites', allowClear:true, width:'100%'});
$('#filterSite').on('change', render);

/* ---------- Nouveau / edition ---------- */
function initPaysSelect(){
  if($('#s_pays').hasClass('select2-hidden-accessible')) $('#s_pays').select2('destroy');
  $('#s_pays').select2({theme:'bootstrap-5', dropdownParent:$('#siteModal'), placeholder:'Aucun / non precise', allowClear:true, width:'100%'});
}
$('#btnNew').on('click', function(){
  $('#siteModalTitle').text('Nouveau site');
  $('#s_id').val(''); $('#s_oaci').val(''); $('#s_nom').val(''); $('#s_ville').val(''); $('#s_dup').hide();
  initPaysSelect(); $('#s_pays').val('').trigger('change');
  new bootstrap.Modal('#siteModal').show();
});
$(document).on('click', '.act-edit', function(){
  const id = $(this).data('id');
  apiPost({action:'get', idsite:id}).done(res => {
    if(!res.success){ Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'}); return; }
    const s = res.data;
    $('#siteModalTitle').text('Modifier le site');
    $('#s_id').val(s.idsite); $('#s_oaci').val(s.indicateur_oaci); $('#s_nom').val(s.nomsite); $('#s_ville').val(s.ville||''); $('#s_dup').hide();
    initPaysSelect();
    // On retrouve le pays par son nom (la liste est dedoublonnee par nom)
    let paysVal = '';
    if(s.nompays){ const m = PAYS.find(p => String(p.nompays) === String(s.nompays)); if(m){ paysVal = String(m.idpays); } }
    $('#s_pays').val(paysVal).trigger('change');
    new bootstrap.Modal('#siteModal').show();
  });
});

/* Controle de doublon en direct (indicateur OACI) */
let dupTimer = null;
$('#s_oaci').on('input', function(){
  clearTimeout(dupTimer);
  const oaci = $(this).val().trim();
  if(!oaci){ $('#s_dup').hide(); return; }
  dupTimer = setTimeout(function(){
    apiPost({action:'check_oaci', indicateur_oaci:oaci, idsite:$('#s_id').val()||0}).done(res => {
      $('#s_dup').toggle(!!(res.success && res.exists));
    });
  }, 350);
});

/* ---------- Enregistrement ---------- */
$('#siteForm').on('submit', function(e){
  e.preventDefault();
  const id   = $('#s_id').val();
  const oaci = $('#s_oaci').val().trim();
  const nom  = $('#s_nom').val().trim();
  if(!oaci){ Swal.fire({icon:'warning',title:'Indicateur requis',text:'Indiquez l\'indicateur OACI (ex : FOOL).',confirmButtonColor:'#23408F'}); return; }
  if(!nom){ Swal.fire({icon:'warning',title:'Nom requis',text:'Indiquez le nom du site.',confirmButtonColor:'#23408F'}); return; }
  const data = { action: id ? 'update' : 'create', idsite: id, indicateur_oaci: oaci, nomsite: nom, idpays: $('#s_pays').val() || 0, ville: $('#s_ville').val().trim() };
  const btn = $('#siteSubmit'); const html = btn.html();
  btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
  apiPost(data).done(res => {
    btn.prop('disabled', false).html(html);
    if(res.success){
      bootstrap.Modal.getInstance(document.getElementById('siteModal')).hide();
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
    title: 'Supprimer ce site ?',
    html: '<b>'+esc(lib)+'</b>' + (used ? '<br><br><span style="color:#D32F2F">Ce site est rattache a des audits. La suppression sera refusee.</span>' : ''),
    showCancelButton: true,
    confirmButtonText: 'Supprimer',
    cancelButtonText: 'Annuler',
    confirmButtonColor: '#D32F2F',
    cancelButtonColor: '#6c757d'
  }).then(r => {
    if(!r.isConfirmed) return;
    apiPost({action:'delete', idsite:id}).done(res => {
      if(res.success){
        Swal.fire({icon:'success',title:'Supprime',timer:1400,showConfirmButton:false});
        loadList(); loadStats();
      } else { Swal.fire({icon:'error',title:'Suppression impossible',text:res.message,confirmButtonColor:'#23408F'}); }
    }).fail(()=>{ Swal.fire({icon:'error',title:'Erreur',text:'Echec de la requete.',confirmButtonColor:'#23408F'}); });
  });
});

/* ---------- Demarrage ---------- */
loadStats();
loadPays();
loadList();
</script>