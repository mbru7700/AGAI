<?php
/**
 * Module : Inspecteurs (page) - Tranche 2a
 * ------------------------------------------------------------
 * - Liste avec filtres dynamiques Select2 (inspecteur, domaine, direction),
 *   rafraichissement automatique, tri du plus recent, sans DataTables.
 * - Creation / edition / suppression d'un inspecteur (rattache a un
 *   utilisateur de role inspecteur ou chef inspecteur) avec habilitations
 *   dynamiques par domaine selon la categorie.
 * - Uploads (photo, decisions PDF) : tranche 2b.
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }

Rbac::guardPage('inspecteurs');
$csrf      = Security::generateCSRF();
$pageTitle = 'Inspecteurs';
$active    = 'inspecteurs';
$pageIcon  = 'bi-person-badge';
$myRole    = Rbac::role();   // pour n'afficher la creation d'utilisateur qu'aux admins

require_once INCLUDES_PATH . '/layout_head.php';
?>

<div class="page-head">
  <div>
    <h1><i class="bi bi-person-badge me-2" style="color:var(--anac-primary)"></i>Inspecteurs</h1>
    <div class="sub">Inspecteurs, categories et habilitations par domaine.</div>
  </div>
  <button class="btn btn-anac" id="btnNew"><i class="bi bi-plus-lg me-2"></i>Nouvel inspecteur</button>
</div>

<style>
  .filter-bar{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:14px 16px;box-shadow:0 1px 2px rgba(16,30,54,.04);}
  .insp-avatar{width:40px;height:40px;border-radius:50%;background:rgba(35,64,143,.10);color:#23408F;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;flex:0 0 auto;}
  .badge-soft{display:inline-block;padding:.25rem .6rem;border-radius:20px;font-size:.74rem;font-weight:600;white-space:nowrap;}
  .b-green{background:rgba(30,156,75,.12);color:#1E9C4B;}
  .b-gold{background:rgba(243,195,0,.18);color:#b58a00;}
  .b-blue{background:rgba(35,64,143,.10);color:#23408F;}
  .b-dom{background:#eef2f9;color:#23408F;margin:1px 2px;}
  .res-count{font-size:.85rem;color:#6b7a90;}
  .hab-row{background:#f7f9fc;border:1px solid #e8edf5;border-radius:10px;padding:10px;margin-bottom:8px;}
  #habRows.stagiaire .hab-formal{display:none !important;}
  #inspModal .modal-body{padding:1.25rem 1.5rem;}
  @media (min-width:992px){ #inspModal .modal-lg{max-width:900px;} }
  .stat-card{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:14px 16px;display:flex;align-items:center;gap:12px;box-shadow:0 1px 2px rgba(16,30,54,.04);height:100%;}
  .stat-ic{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex:0 0 auto;}
  .stat-num{font-size:1.5rem;font-weight:700;line-height:1;color:#2C3E50;}
  .stat-lbl{font-size:.78rem;color:#6b7a90;margin-top:3px;}
  .ic-blue{background:rgba(35,64,143,.10);color:#23408F;}
  .ic-green{background:rgba(30,156,75,.12);color:#1E9C4B;}
  .ic-gold{background:rgba(243,195,0,.18);color:#b58a00;}
  .ic-dark{background:rgba(44,62,80,.10);color:#2C3E50;}
</style>

<div class="d-flex justify-content-end mb-2">
  <button class="btn btn-sm btn-outline-secondary" id="btnToggleStats" type="button">
    <i class="bi bi-bar-chart-line-fill me-1"></i><span id="statsToggleLabel">Afficher les statistiques</span>
  </button>
</div>
<div id="statsPanel" class="row g-3 mb-3" style="display:none;">
  <div class="col-6 col-md-4 col-xl-2"><div class="stat-card"><div class="stat-ic ic-blue"><i class="bi bi-person-badge-fill"></i></div><div><div class="stat-num" id="st_total">0</div><div class="stat-lbl">Total inspecteurs</div></div></div></div>
  <div class="col-6 col-md-4 col-xl-2"><div class="stat-card"><div class="stat-ic ic-gold"><i class="bi bi-mortarboard-fill"></i></div><div><div class="stat-num" id="st_stagiaires">0</div><div class="stat-lbl">Stagiaires</div></div></div></div>
  <div class="col-6 col-md-4 col-xl-2"><div class="stat-card"><div class="stat-ic ic-green"><i class="bi bi-patch-check-fill"></i></div><div><div class="stat-num" id="st_titulaires">0</div><div class="stat-lbl">Titulaires</div></div></div></div>
  <div class="col-6 col-md-4 col-xl-2"><div class="stat-card"><div class="stat-ic ic-blue"><i class="bi bi-star-fill"></i></div><div><div class="stat-num" id="st_exceptionnels">0</div><div class="stat-lbl">Exceptionnels</div></div></div></div>
  <div class="col-6 col-md-4 col-xl-2"><div class="stat-card"><div class="stat-ic ic-dark"><i class="bi bi-shield-fill-check"></i></div><div><div class="stat-num" id="st_habilitations">0</div><div class="stat-lbl">Habilitations</div></div></div></div>
  <div class="col-6 col-md-4 col-xl-2"><div class="stat-card"><div class="stat-ic ic-green"><i class="bi bi-diagram-3-fill"></i></div><div><div class="stat-num" id="st_domaines">0</div><div class="stat-lbl">Domaines couverts</div></div></div></div>
</div>

<!-- Barre de filtres dynamiques -->
<div class="filter-bar mb-3">
  <div class="row g-3 align-items-end">
    <div class="col-12 col-md-4">
      <label class="form-label mb-1"><i class="bi bi-person me-1"></i>Inspecteur</label>
      <select id="f_inspecteur" class="form-select" multiple></select>
    </div>
    <div class="col-12 col-md-4">
      <label class="form-label mb-1"><i class="bi bi-shield-shaded me-1"></i>Domaine</label>
      <select id="f_domaine" class="form-select" multiple></select>
    </div>
    <div class="col-12 col-md-3">
      <label class="form-label mb-1"><i class="bi bi-diagram-3 me-1"></i>Direction</label>
      <select id="f_direction" class="form-select" multiple></select>
    </div>
    <div class="col-12 col-md-1 d-grid">
      <button class="btn btn-outline-secondary" id="btnResetFilters" title="Reinitialiser les filtres"><i class="bi bi-arrow-counterclockwise"></i></button>
    </div>
  </div>
  <div class="res-count mt-2" id="resCount"></div>
</div>

<div class="card-anac p-3 p-md-4">
  <div class="table-responsive">
    <table class="table table-hover align-middle" style="width:100%">
      <thead>
        <tr>
          <th></th><th>Matricule</th><th>Nom &amp; prenom</th><th>Categorie</th>
          <th>Direction</th><th>Domaines</th><th>Telephone</th><th>Email</th><th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody id="inspBody">
        <tr><td colspan="9" class="text-center text-muted py-4">Chargement...</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- ===== MODALE INSPECTEUR ===== -->
<div class="modal fade" id="inspModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <form id="inspForm" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="inspModalTitle">Nouvel inspecteur</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="i_idinspecteur" value="">

        <!-- Selection de l'utilisateur (creation) -->
        <div class="mb-3" id="userPick">
          <label class="form-label">Utilisateur inspecteur <span class="text-danger">*</span></label>
          <div class="d-flex gap-2 align-items-start">
            <div class="flex-grow-1"><select id="i_iduser" class="form-select" style="width:100%"></select></div>
            <?php if ($myRole === 'admin'): ?>
            <button type="button" class="btn btn-success" id="btnNewUser" title="Creer un nouvel utilisateur inspecteur"><i class="bi bi-plus-lg"></i></button>
            <?php endif; ?>
          </div>
          <div class="form-text">Si l'inspecteur n'existe pas encore, cliquez sur + pour le creer d'abord comme utilisateur.</div>
          <div class="form-text text-danger" id="err_user" style="display:none;"></div>
        </div>
        <!-- En edition : utilisateur fige -->
        <div class="mb-3" id="userFixed" style="display:none;">
          <label class="form-label">Utilisateur inspecteur</label>
          <input type="text" class="form-control" id="i_userlabel" readonly style="background:#eef2f7;">
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Nom</label>
            <input type="text" class="form-control" id="i_nom" readonly style="background:#eef2f7;">
          </div>
          <div class="col-md-6">
            <label class="form-label">Prenom</label>
            <input type="text" class="form-control" id="i_prenom" readonly style="background:#eef2f7;">
          </div>
          <div class="col-md-6">
            <label class="form-label">Matricule</label>
            <input type="text" class="form-control" id="i_matricule" readonly style="background:#eef2f7;">
          </div>
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="text" class="form-control" id="i_email" readonly style="background:#eef2f7;">
          </div>
        </div>

        <hr class="my-3">

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Categorie <span class="text-danger">*</span></label>
            <select id="i_categorie" class="form-select">
              <option value="stagiaire">Stagiaire</option>
              <option value="titulaire">Titulaire</option>
              <option value="exceptionnel">Exceptionnel</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Trigramme <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="i_trigr" maxlength="41" placeholder="ex : MBR">
          </div>
          <div class="col-md-4" id="dateWrap">
            <label class="form-label">Date de nomination <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="i_datenomine">
          </div>
          <div class="col-md-6">
            <label class="form-label">Direction <span class="text-danger">*</span></label>
            <select id="i_codedirec" class="form-select" style="width:100%"></select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Telephone <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="i_tele" maxlength="100" placeholder="ex : +241 ...">
          </div>
        </div>

        <hr class="my-3">

        <div class="d-flex justify-content-between align-items-center mb-2">
          <label class="form-label mb-0"><i class="bi bi-shield-shaded me-1"></i>Habilitations par domaine <span class="text-danger">*</span></label>
          <button type="button" class="btn btn-sm btn-outline-success" id="btnAddHab"><i class="bi bi-plus-lg me-1"></i>Ajouter un domaine</button>
        </div>
        <div class="form-text mb-2" id="habHint"></div>
        <div id="habRows"></div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" class="btn btn-anac" id="inspSubmit"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- ===== MODALE NOUVEAU DOMAINE ===== -->
<div class="modal fade" id="domModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form id="domForm" class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Nouveau domaine</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Nom du domaine <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="d_nom" maxlength="255" placeholder="ex : ANS">
          <div class="form-text text-danger" id="err_dom" style="display:none;"></div>
        </div>
        <div class="mb-2">
          <label class="form-label">Libelle</label>
          <input type="text" class="form-control" id="d_lib" maxlength="255" placeholder="ex : Navigation aerienne">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" class="btn btn-anac" id="domSubmit"><i class="bi bi-check-lg me-1"></i>Ajouter</button>
      </div>
    </form>
  </div>
</div>

<!-- ===== MODALE NOUVEL UTILISATEUR INSPECTEUR ===== -->
<div class="modal fade" id="newUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <form id="newUserForm" class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Nouvel utilisateur inspecteur</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Role <span class="text-danger">*</span></label>
          <select id="nu_role" class="form-select">
            <option value="inspecteur">Inspecteur</option>
            <option value="chef_inspecteur">Chef inspecteur</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Personnel ANAC <span class="text-danger">*</span></label>
          <select id="nu_personnel" class="form-select" style="width:100%"></select>
          <div class="form-text">Selectionnez l'agent dans le personnel ANAC (un stagiaire est aussi un utilisateur).</div>
        </div>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Prenom</label><input type="text" class="form-control" id="nu_prenom" readonly style="background:#eef2f7;"></div>
          <div class="col-md-6"><label class="form-label">Nom</label><input type="text" class="form-control" id="nu_nom" readonly style="background:#eef2f7;"></div>
          <div class="col-md-6"><label class="form-label">Matricule</label><input type="text" class="form-control" id="nu_matricule" readonly style="background:#eef2f7;"></div>
          <div class="col-md-6"><label class="form-label">Email <span class="text-danger">*</span></label><input type="email" class="form-control" id="nu_email"><div class="form-text text-danger" id="err_nu_email" style="display:none;"></div></div>
        </div>
        <div class="mt-3 p-3" style="background:#f4f7fb;border-radius:10px;">
          <div class="form-text mb-2">Un mot de passe fort est genere automatiquement. Comment le transmettre ?</div>
          <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="nu_send" id="nu_send1" value="1" checked><label class="form-check-label" for="nu_send1">Envoyer par email</label></div>
          <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="nu_send" id="nu_send0" value="0"><label class="form-check-label" for="nu_send0">Ne pas envoyer (afficher)</label></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" class="btn btn-anac" id="newUserSubmit"><i class="bi bi-check-lg me-1"></i>Creer l'utilisateur</button>
      </div>
    </form>
  </div>
</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>

<script>
const CSRF = '<?php echo Security::escape($csrf); ?>';
const API_INSP = AGAI_BASE + '/api/inspecteurs';

function apiPost(url, data){
  data = Object.assign({csrf_token: CSRF}, data);
  return $.post(url, data, null, 'json');
}

const CAT = {
  stagiaire:   {t:'Stagiaire',   c:'b-gold'},
  titulaire:   {t:'Titulaire',   c:'b-green'},
  exceptionnel:{t:'Exceptionnel',c:'b-blue'}
};

let DOMAINES = [];     // [{iddomaine, nomdomaine}]
let DIRECTIONS = [];   // [{codedirec, libdirec}]
let USERMAP = {};      // iduser -> {nom,prenom,matricule,email}

/* ---------- Filtres ---------- */
function initFilters(){
  $('#f_inspecteur').select2({theme:'bootstrap-5', placeholder:'Tous les inspecteurs', width:'100%', closeOnSelect:false});
  $('#f_domaine').select2({theme:'bootstrap-5', placeholder:'Tous les domaines', width:'100%', closeOnSelect:false});
  $('#f_direction').select2({theme:'bootstrap-5', placeholder:'Toutes les directions', width:'100%', closeOnSelect:false});
}
function loadFilters(){
  return apiPost(API_INSP, {action:'filters'}).done(res => {
    if(!res.success) return;
    DOMAINES = res.domaines || [];
    DIRECTIONS = res.directions || [];
    $('#f_inspecteur').empty();
    res.inspecteurs.forEach(i => $('#f_inspecteur').append(new Option(i.libelle, i.idinspecteur)));
    $('#f_domaine').empty();
    DOMAINES.forEach(d => $('#f_domaine').append(new Option(d.nomdomaine, d.iddomaine)));
    $('#f_direction').empty();
    DIRECTIONS.forEach(d => $('#f_direction').append(new Option(d.libdirec, d.codedirec)));
    $('#f_inspecteur, #f_domaine, #f_direction').trigger('change.select2');
  });
}

/* ---------- Liste ---------- */
function avatar(insp){
  const ini = (String(insp.preninspect||'').charAt(0) + String(insp.nominspecteur||'').charAt(0)).toUpperCase();
  return '<span class="insp-avatar">'+esc(ini || 'IN')+'</span>';
}
function renderRows(data){
  const tb = $('#inspBody'); tb.empty();
  if(!data.length){ tb.append('<tr><td colspan="9" class="text-center text-muted py-4">Aucun inspecteur a afficher</td></tr>'); return; }
  data.forEach(i => {
    const cat = CAT[i.categorie] || {t:i.categorie, c:'b-blue'};
    const doms = (i.domaines_list && i.domaines_list.length)
      ? i.domaines_list.map(d => '<span class="badge-soft b-dom">'+esc(d)+'</span>').join(' ')
      : '<span class="text-muted">-</span>';
    const actions = '<div class="btn-group btn-group-sm">'
      + '<button class="btn btn-outline-primary act-edit" data-id="'+i.idinspecteur+'" title="Modifier"><i class="bi bi-pencil"></i></button>'
      + '<button class="btn btn-outline-danger act-del" data-id="'+i.idinspecteur+'" data-name="'+esc(((i.preninspect||'')+' '+(i.nominspecteur||'')).trim())+'" title="Supprimer"><i class="bi bi-trash"></i></button>'
      + '</div>';
    tb.append(
      '<tr>'
      + '<td>'+avatar(i)+'</td>'
      + '<td>'+esc(i.numatinspecteur||'-')+'</td>'
      + '<td>'+esc(((i.preninspect||'')+' '+(i.nominspecteur||'')).trim())+'</td>'
      + '<td><span class="badge-soft '+cat.c+'">'+esc(cat.t)+'</span></td>'
      + '<td>'+(i.libdirec ? esc(i.libdirec) : '<span class="text-muted">-</span>')+'</td>'
      + '<td>'+doms+'</td>'
      + '<td>'+(i.teleinspecter ? esc(i.teleinspecter) : '<span class="text-muted">-</span>')+'</td>'
      + '<td>'+(i.mailinspect ? esc(i.mailinspect) : '<span class="text-muted">-</span>')+'</td>'
      + '<td class="text-end">'+actions+'</td>'
      + '</tr>'
    );
  });
}
function loadList(){
  const data = {
    action:'list',
    inspecteurs: $('#f_inspecteur').val() || [],
    domaines:    $('#f_domaine').val() || [],
    directions:  $('#f_direction').val() || []
  };
  $('#resCount').text('Chargement...');
  apiPost(API_INSP, data)
    .done(res => {
      if(!res.success){ $('#resCount').text(''); Swal.fire({icon:'error',title:'Erreur',text:res.message||'Chargement impossible',confirmButtonColor:'#23408F'}); return; }
      renderRows(res.data);
      $('#resCount').html('<i class="bi bi-people me-1"></i>' + res.count + ' inspecteur(s)');
      if($('#statsPanel').is(':visible')){ loadStats(); }
    })
    .fail(() => { $('#resCount').text(''); Swal.fire({icon:'error',title:'Connexion',text:'Impossible de joindre le serveur.',confirmButtonColor:'#23408F'}); });
}
$('#f_inspecteur, #f_domaine, #f_direction').on('change', function(){ loadList(); });
$('#btnResetFilters').on('click', function(){ $('#f_inspecteur, #f_domaine, #f_direction').val(null).trigger('change.select2'); loadList(); });

/* ---------- Formulaire : direction + habilitations ---------- */
function fillDirectionSelect(selected){
  const sel = $('#i_codedirec'); sel.empty().append(new Option('', ''));
  DIRECTIONS.forEach(d => sel.append(new Option(d.libdirec, d.codedirec)));
  sel.val(selected || '').trigger('change.select2');
}
function domaineOptionsHtml(selected){
  let h = '<option value="">Choisir un domaine</option>';
  DOMAINES.forEach(d => { h += '<option value="'+d.iddomaine+'"'+(String(selected)===String(d.iddomaine)?' selected':'')+'>'+esc(d.nomdomaine)+'</option>'; });
  return h;
}
function addHabRow(h){
  h = h || {};
  const row = $(
    '<div class="hab-row">'
    + '<div class="row g-2 align-items-end">'
    +   '<div class="col-md-4"><label class="form-label mb-1 small">Domaine</label><div class="d-flex gap-1"><div class="flex-grow-1"><select class="form-select form-select-sm hab-dom" style="width:100%">'+domaineOptionsHtml(h.iddomaine)+'</select></div><button type="button" class="btn btn-sm btn-success hab-adddom" title="Ajouter un domaine absent de la liste"><i class="bi bi-plus-lg"></i></button></div></div>'
    +   '<div class="col-md-3 hab-formal"><label class="form-label mb-1 small">N habilitation</label><input type="text" class="form-control form-control-sm hab-num" maxlength="50" value="'+esc(h.numero_habilitation||'')+'"></div>'
    +   '<div class="col-md-2 hab-formal"><label class="form-label mb-1 small">Debut</label><input type="date" class="form-control form-control-sm hab-deb" value="'+esc((h.date_habilitation||'').substring(0,10))+'"></div>'
    +   '<div class="col-md-2 hab-formal"><label class="form-label mb-1 small">Expiration</label><input type="date" class="form-control form-control-sm hab-fin" value="'+esc((h.date_expiration||'').substring(0,10))+'"></div>'
    +   '<div class="col-md-1 d-grid"><button type="button" class="btn btn-sm btn-outline-danger hab-del" title="Retirer"><i class="bi bi-x-lg"></i></button></div>'
    +   '<div class="col-12"><input type="text" class="form-control form-control-sm hab-obs" placeholder="Observation (facultatif)" value="'+esc(h.observation||'')+'"></div>'
    + '</div></div>'
  );
  $('#habRows').append(row);
  row.find('.hab-dom').select2({theme:'bootstrap-5', dropdownParent:$('#inspModal'), placeholder:'Choisir un domaine', width:'100%'});
  row.find('.hab-del').on('click', function(){ row.remove(); });
  row.find('.hab-adddom').on('click', function(){ openDomModal(row.find('.hab-dom')); });
}
function applyCategoryUI(){
  const cat = $('#i_categorie').val();
  if(cat === 'stagiaire'){
    $('#habRows').addClass('stagiaire');
    $('#dateWrap').hide();
    $('#habHint').html('<i class="bi bi-info-circle me-1"></i>Categorie stagiaire : on enregistre seulement les domaines (sans date de nomination ni numero ni dates d\'habilitation).');
  } else {
    $('#habRows').removeClass('stagiaire');
    $('#dateWrap').show();
    $('#habHint').html('<i class="bi bi-info-circle me-1"></i>Categorie ' + cat + ' : numero et dates d\'habilitation obligatoires pour chaque domaine.');
  }
}
$('#i_categorie').on('change', applyCategoryUI);
$('#btnAddHab').on('click', function(){ addHabRow(); });

/* ---------- Nouvel inspecteur ---------- */
function CAT_ROLE(r){ return r === 'chef_inspecteur' ? 'Chef inspecteur' : 'Inspecteur'; }

function refreshUserSelect(selectByEmail){
  return apiPost(API_INSP, {action:'users_available'}).done(res => {
    if(!res.success) return;
    USERMAP = {};
    const sel = $('#i_iduser');
    if(sel.hasClass('select2-hidden-accessible')) sel.select2('destroy');
    sel.empty().append(new Option('', ''));
    let toSelect = '';
    res.data.forEach(u => {
      USERMAP[u.iduser] = u;
      sel.append(new Option((u.prenom+' '+u.nom).trim()+' ('+CAT_ROLE(u.role)+')', u.iduser));
      if(selectByEmail && u.email === selectByEmail){ toSelect = String(u.iduser); }
    });
    sel.select2({theme:'bootstrap-5', dropdownParent:$('#inspModal'), placeholder:'Rechercher un utilisateur...', width:'100%'});
    sel.val(toSelect).trigger('change');
  });
}

$('#btnNew').on('click', function(){
  refreshUserSelect().always(function(){
    $('#inspModalTitle').text('Nouvel inspecteur');
    $('#i_idinspecteur').val('');
    $('#userPick').show(); $('#userFixed').hide();
    $('#err_user').hide();
    $('#i_nom,#i_prenom,#i_matricule,#i_email,#i_trigr,#i_tele').val('');
    $('#i_categorie').val('titulaire');
    $('#i_datenomine').val('');
    fillDirectionSelect('');
    if($('#i_codedirec').hasClass('select2-hidden-accessible')) $('#i_codedirec').select2('destroy');
    $('#i_codedirec').select2({theme:'bootstrap-5', dropdownParent:$('#inspModal'), placeholder:'Choisir une direction', width:'100%'});
    $('#habRows').empty(); addHabRow();
    applyCategoryUI();
    new bootstrap.Modal('#inspModal').show();
  });
});

/* Auto-remplissage grise depuis l'utilisateur choisi */
$('#i_iduser').on('change', function(){
  const u = USERMAP[$(this).val()];
  if(u){ $('#i_nom').val(u.nom); $('#i_prenom').val(u.prenom); $('#i_matricule').val(u.matricule); $('#i_email').val(u.email); }
  else { $('#i_nom,#i_prenom,#i_matricule,#i_email').val(''); }
});

/* ---------- Edition ---------- */
$(document).on('click', '.act-edit', function(){
  const id = $(this).data('id');
  apiPost(API_INSP, {action:'get', idinspecteur:id}).done(res => {
    if(!res.success){ Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'}); return; }
    const i = res.data;
    $('#inspModalTitle').text('Modifier l\'inspecteur');
    $('#i_idinspecteur').val(i.idinspecteur);
    $('#userPick').hide(); $('#userFixed').show();
    $('#i_userlabel').val(((i.preninspect||'')+' '+(i.nominspecteur||'')).trim());
    $('#i_nom').val(i.nominspecteur); $('#i_prenom').val(i.preninspect);
    $('#i_matricule').val(i.numatinspecteur); $('#i_email').val(i.mailinspect);
    $('#i_categorie').val(i.categorie);
    $('#i_trigr').val(i.trigr_inspecteur);
    $('#i_datenomine').val((i.datenomine||'').substring(0,10));
    $('#i_tele').val(i.teleinspecter);
    fillDirectionSelect(i.codedirec);
    if($('#i_codedirec').hasClass('select2-hidden-accessible')) $('#i_codedirec').select2('destroy');
    $('#i_codedirec').select2({theme:'bootstrap-5', dropdownParent:$('#inspModal'), placeholder:'Choisir une direction', width:'100%'});
    $('#habRows').empty();
    (res.habilitations||[]).forEach(h => addHabRow(h));
    if(!(res.habilitations||[]).length) addHabRow();
    applyCategoryUI();
    new bootstrap.Modal('#inspModal').show();
  });
});

/* ---------- Suppression ---------- */
$(document).on('click', '.act-del', function(){
  const id = $(this).data('id'); const name = $(this).data('name');
  Swal.fire({title:'Supprimer ?', html:'Inspecteur <strong>'+esc(name)+'</strong>', icon:'warning', showCancelButton:true,
    confirmButtonColor:'#D32F2F', cancelButtonColor:'#6c757d', confirmButtonText:'Supprimer', cancelButtonText:'Annuler'})
  .then(r => {
    if(!r.isConfirmed) return;
    apiPost(API_INSP, {action:'delete', idinspecteur:id}).done(res => {
      if(res.success){ Swal.fire({icon:'success',title:'Supprime',timer:1500,showConfirmButton:false}); loadList(); loadFilters(); }
      else { Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'}); }
    });
  });
});

/* ---------- Soumission ---------- */
$('#inspForm').on('submit', function(e){
  e.preventDefault();
  const isUpdate = $('#i_idinspecteur').val() !== '';
  // Collecte des habilitations (tableaux paralleles, meme ordre)
  const hab_domaine=[], hab_numero=[], hab_debut=[], hab_fin=[], hab_obs=[];
  $('#habRows .hab-row').each(function(){
    const d = $(this).find('.hab-dom').val();
    if(!d) return;
    hab_domaine.push(d);
    hab_numero.push($(this).find('.hab-num').val()||'');
    hab_debut.push($(this).find('.hab-deb').val()||'');
    hab_fin.push($(this).find('.hab-fin').val()||'');
    hab_obs.push($(this).find('.hab-obs').val()||'');
  });
  if(hab_domaine.length === 0){ Swal.fire({icon:'warning',title:'Domaine',text:'Ajoutez au moins un domaine.',confirmButtonColor:'#23408F'}); return; }

  const data = {
    action: isUpdate ? 'update' : 'create',
    idinspecteur: $('#i_idinspecteur').val(),
    iduser: $('#i_iduser').val() || 0,
    categorie: $('#i_categorie').val(),
    trigr_inspecteur: $('#i_trigr').val(),
    codedirec: $('#i_codedirec').val() || 0,
    datenomine: $('#i_datenomine').val(),
    teleinspecter: $('#i_tele').val(),
    hab_domaine, hab_numero, hab_debut, hab_fin, hab_obs
  };
  const btn = $('#inspSubmit'); const html = btn.html();
  btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
  apiPost(API_INSP, data).done(res => {
    btn.prop('disabled', false).html(html);
    if(res.success){
      bootstrap.Modal.getInstance(document.getElementById('inspModal')).hide();
      Swal.fire({icon:'success',title:'Enregistre',text:res.message,timer:1800,showConfirmButton:false,timerProgressBar:true});
      loadList(); loadFilters();
    } else { Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'}); }
  }).fail(()=>{ btn.prop('disabled', false).html(html); Swal.fire({icon:'error',title:'Erreur',text:'Echec de la requete.',confirmButtonColor:'#23408F'}); });
});

/* ---------- Statistiques (panneau repliable, choix memorise) ---------- */
function loadStats(){
  apiPost(API_INSP, {action:'stats'}).done(res => {
    if(!res.success || !res.stats) return;
    const s = res.stats;
    $('#st_total').text(s.total);                 $('#st_stagiaires').text(s.stagiaires);
    $('#st_titulaires').text(s.titulaires);       $('#st_exceptionnels').text(s.exceptionnels);
    $('#st_habilitations').text(s.habilitations); $('#st_domaines').text(s.domaines_couverts);
  });
}
function setStatsVisible(show){
  $('#statsPanel').toggle(show);
  $('#statsToggleLabel').text(show ? 'Masquer les statistiques' : 'Afficher les statistiques');
  try { localStorage.setItem('agai_stats_inspecteurs', show ? '1' : '0'); } catch(e){}
  if(show){ loadStats(); }
}
$('#btnToggleStats').on('click', function(){ setStatsVisible($('#statsPanel').is(':hidden')); });

/* ---------- Ajout rapide d'un domaine (bouton + dans chaque ligne) ---------- */
let domTarget = null;     // le select .hab-dom qui a demande l'ajout
let domTimer  = null;
function openDomModal(targetSelect){
  domTarget = targetSelect || null;
  $('#d_nom').val(''); $('#d_lib').val(''); $('#err_dom').hide(); $('#domSubmit').prop('disabled', false);
  new bootstrap.Modal('#domModal').show();
}
$('#d_nom').on('input', function(){
  const nom = $(this).val().trim();
  clearTimeout(domTimer);
  if(nom === ''){ $('#err_dom').hide(); $('#domSubmit').prop('disabled', false); return; }
  domTimer = setTimeout(function(){
    apiPost(API_INSP, {action:'check_domaine', nomdomaine:nom}).done(r => {
      if(r.success && r.exists){ $('#err_dom').text('Ce domaine existe deja.').show(); $('#domSubmit').prop('disabled', true); }
      else { $('#err_dom').hide(); $('#domSubmit').prop('disabled', false); }
    });
  }, 350);
});
$('#domForm').on('submit', function(e){
  e.preventDefault();
  const nom = $('#d_nom').val().trim(), lib = $('#d_lib').val().trim();
  if(nom === ''){ $('#err_dom').text('Le nom du domaine est requis.').show(); return; }
  const btn = $('#domSubmit'); const html = btn.html();
  btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
  apiPost(API_INSP, {action:'create_domaine', nomdomaine:nom, libel_domaine:lib}).done(res => {
    btn.prop('disabled', false).html(html);
    if(res.success){
      DOMAINES.push({iddomaine:res.iddomaine, nomdomaine:res.nomdomaine});
      // Ajouter l'option a toutes les listes de domaines deja affichees
      $('.hab-dom').each(function(){ $(this).append(new Option(res.nomdomaine, res.iddomaine)); $(this).trigger('change.select2'); });
      $('#f_domaine').append(new Option(res.nomdomaine, res.iddomaine)).trigger('change.select2');
      if(domTarget){ domTarget.val(res.iddomaine).trigger('change'); }
      bootstrap.Modal.getInstance(document.getElementById('domModal')).hide();
      Swal.fire({icon:'success',title:'Domaine ajoute',timer:1400,showConfirmButton:false});
    } else { $('#err_dom').text(res.message).show(); }
  }).fail(()=>{ btn.prop('disabled', false).html(html); Swal.fire({icon:'error',title:'Erreur',text:'Echec de la requete.',confirmButtonColor:'#23408F'}); });
});

/* ---------- Creation d'un nouvel utilisateur inspecteur (admin) ---------- */
let NU_PERS = {};
$('#btnNewUser').on('click', function(){
  apiPost(AGAI_BASE + '/api/personnel', {action:'list'}).done(res => {
    const sel = $('#nu_personnel');
    if(sel.hasClass('select2-hidden-accessible')) sel.select2('destroy');
    sel.empty().append(new Option('', ''));
    NU_PERS = {};
    if(res.success){
      res.data.forEach(p => { NU_PERS[p.idpersonnel] = p; sel.append(new Option((p.prenag+' '+p.nomag).trim()+' ('+p.numat+')', p.idpersonnel)); });
    }
    sel.val('').select2({theme:'bootstrap-5', dropdownParent:$('#newUserModal'), placeholder:'Rechercher un agent ANAC...', width:'100%'});
    $('#nu_role').val('inspecteur');
    $('#nu_prenom,#nu_nom,#nu_matricule,#nu_email').val('');
    $('#nu_send1').prop('checked', true);
    $('#err_nu_email').hide(); $('#newUserSubmit').prop('disabled', false);
    new bootstrap.Modal('#newUserModal').show();
  }).fail(()=> Swal.fire({icon:'error',title:'Acces',text:'Impossible de charger le personnel (action reservee aux administrateurs).',confirmButtonColor:'#23408F'}));
});
$('#nu_personnel').on('change', function(){
  const p = NU_PERS[$(this).val()];
  if(p){ $('#nu_prenom').val(p.prenag); $('#nu_nom').val(p.nomag); $('#nu_matricule').val(p.numat); $('#nu_email').val(p.email_anac||''); }
  else { $('#nu_prenom,#nu_nom,#nu_matricule,#nu_email').val(''); }
  checkNuEmail();
});

/* Controle de doublon email en temps reel (reutilise le controle du module utilisateurs) */
let nuEmailTimer = null;
function checkNuEmail(){
  const email = ($('#nu_email').val()||'').trim();
  clearTimeout(nuEmailTimer);
  if(email === ''){ $('#err_nu_email').hide(); $('#newUserSubmit').prop('disabled', false); return; }
  nuEmailTimer = setTimeout(function(){
    apiPost(AGAI_BASE + '/api/users', {action:'check_email', email:email}).done(r => {
      if(r && r.exists){ $('#err_nu_email').text('Cet email est deja utilise par un autre compte.').show(); $('#newUserSubmit').prop('disabled', true); }
      else { $('#err_nu_email').hide(); $('#newUserSubmit').prop('disabled', false); }
    });
  }, 350);
}
$('#nu_email').on('input', checkNuEmail);
$('#newUserForm').on('submit', function(e){
  e.preventDefault();
  const idp = $('#nu_personnel').val();
  if(!idp){ Swal.fire({icon:'warning',title:'Personnel',text:'Choisissez un agent ANAC.',confirmButtonColor:'#23408F'}); return; }
  const email = $('#nu_email').val().trim();
  if(email === ''){ Swal.fire({icon:'warning',title:'Email',text:'L\'email est requis.',confirmButtonColor:'#23408F'}); return; }
  if($('#err_nu_email').is(':visible')){ Swal.fire({icon:'warning',title:'Email en doublon',text:'Cet email est deja utilise. Veuillez le corriger.',confirmButtonColor:'#23408F'}); return; }
  const data = {
    action:'create', role:$('#nu_role').val(), idpersonnel:idp, email:email,
    is_2fa_enabled:1, email_notifications:1, pwd_mode:'auto',
    send_email:$('input[name=nu_send]:checked').val() || '1'
  };
  const btn = $('#newUserSubmit'); const html = btn.html();
  btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
  apiPost(AGAI_BASE + '/api/users', data).done(res => {
    btn.prop('disabled', false).html(html);
    if(res.success){
      bootstrap.Modal.getInstance(document.getElementById('newUserModal')).hide();
      if(res.password){
        Swal.fire({icon:'success', title:'Utilisateur cree',
          html:'Mot de passe genere :<br><code style="font-size:1.05rem">'+esc(res.password)+'</code><br><small>Copiez-le, il ne sera plus affiche.</small>',
          confirmButtonColor:'#23408F'});
      } else {
        Swal.fire({icon:'success',title:'Utilisateur cree',text:res.message,timer:2200,showConfirmButton:false});
      }
      // On rafraichit la liste et on selectionne automatiquement le nouvel utilisateur
      refreshUserSelect(email);
    } else { Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'}); }
  }).fail(()=>{ btn.prop('disabled', false).html(html); Swal.fire({icon:'error',title:'Erreur',text:'Creation impossible (action reservee aux administrateurs ?).',confirmButtonColor:'#23408F'}); });
});

/* ---------- Demarrage ---------- */
initFilters();
loadFilters().always(loadList);
(function(){ let v='0'; try{ v = localStorage.getItem('agai_stats_inspecteurs') || '0'; }catch(e){} if(v==='1'){ setStatsVisible(true); } })();
</script>