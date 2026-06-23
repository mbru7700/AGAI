<?php
/**
 * Module : Declenchement d'un acte de supervision (page) - Phase 1
 * ------------------------------------------------------------
 * Formulaire pleine page atteint depuis le tableau de bord (nature + cadre
 * deja choisis, affiches en grise et modifiables). On y choisit le
 * responsable (non stagiaire), l'operateur, le type d'organisme et le site
 * (listes deroulantes avec bouton + d'ajout rapide), le statut et la date
 * previsionnelle. Le numero d'audit est genere automatiquement.
 *
 * Reserve a l'administrateur et au chef inspecteur.
 * L'equipe d'inspecteurs et les notifications par mail viennent en tranche 1C.
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }

Rbac::guardPage('audits');
if (!in_array(Rbac::role(), ['admin', 'chef_inspecteur'], true)) {
    header('Location: ' . SITE_URL . '/dashboard');
    exit;
}

$csrf = Security::generateCSRF();

$TYPES  = ['audit','inspection_programmee','inspection_non_programmee','demonstration','test','investigation'];
$CADRES = ['certification','homologation','reconnaissance','renouvellement','surveillance_continue','traitement_evenement','fermeture_provisoire','fermeture_definitive','delivrance_autorisation'];

$type  = $_GET['type'] ?? '';
$cadre = $_GET['cadre'] ?? '';
if (!in_array($type, $TYPES, true) || !in_array($cadre, $CADRES, true)) {
    // Nature/cadre manquants ou invalides : on repart du tableau de bord
    header('Location: ' . SITE_URL . '/dashboard');
    exit;
}

$pageTitle = 'Declenchement';
$active    = 'audits';
$pageIcon  = 'bi-flag';

require_once INCLUDES_PATH . '/layout_head.php';
?>

<div class="page-head">
  <div>
    <h1><i class="bi bi-flag me-2" style="color:var(--anac-primary)"></i>Declenchement d'un acte de supervision</h1>
    <div class="sub">Phase 1 - Designation et planification.</div>
  </div>
  <a href="<?php echo SITE_URL; ?>/audits" class="btn btn-light"><i class="bi bi-list-ul me-1"></i>Liste des audits</a>
</div>

<style>
  .form-card{background:#fff;border:1px solid #eef1f6;border-radius:16px;padding:22px 24px;box-shadow:0 1px 2px rgba(16,30,54,.04);}
  .form-section{font-size:.74rem;text-transform:uppercase;letter-spacing:.05em;color:#23408F;font-weight:700;border-bottom:1px solid #eef1f6;padding-bottom:5px;margin:4px 0 14px;}
  .grey-box{background:#f5f7fa;border:1px dashed #cdd7e6;border-radius:12px;padding:14px 16px;display:flex;align-items:center;gap:18px;flex-wrap:wrap;}
  .grey-box .gi{font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:#8a97ab;}
  .grey-box .gv{font-weight:700;color:#2C3E50;font-size:1.02rem;}
  .num-badge{font-family:monospace;font-weight:700;color:#23408F;background:rgba(35,64,143,.08);padding:.35rem .7rem;border-radius:8px;display:inline-block;}
  .add-btn{flex:0 0 auto;}
  .eq-card{border:1px solid #e8edf5;border-radius:12px;padding:14px 16px;margin-bottom:12px;background:#fcfdff;}
  .eq-card .eq-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;}
  .eq-card .eq-name{font-weight:700;color:#23408F;}
  .eq-dom{border:1px solid #eef1f6;border-radius:10px;padding:9px 12px;margin-bottom:8px;}
  .eq-dom.expired{background:#fdecec;border-color:#f4c9c9;}
  .eq-dom .dom-line{display:flex;align-items:center;gap:9px;}
  .eq-dom .dom-code{font-weight:600;color:#2C3E50;}
  .eq-dom .exp-tag{font-size:.75rem;color:#D32F2F;font-weight:600;margin-left:auto;}
  .eq-dom .ok-tag{font-size:.75rem;color:#1E9C4B;font-weight:600;margin-left:auto;}
  .reg-list{margin-top:8px;padding-top:8px;border-top:1px dashed #e3e9f2;display:none;}
  .reg-list .form-check{margin-bottom:3px;}
  .reg-empty{font-size:.82rem;color:#9aa7bd;}
  .ra-badge{display:inline-block;font-size:.7rem;font-weight:700;color:#fff;background:#D32F2F;border-radius:20px;padding:.1rem .5rem;margin-left:8px;}
  .help-box{background:linear-gradient(135deg,#eef3fb,#f7faff);border:1px solid #dde7f5;border-radius:14px;padding:14px 18px;margin-bottom:16px;}
  .help-box .ht{display:flex;align-items:center;gap:8px;color:#23408F;font-weight:700;margin-bottom:10px;}
  .help-box .ht i{font-size:1.15rem;color:#23408F;}
  .help-steps{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin:0;}
  .help-step{display:flex;gap:10px;align-items:flex-start;font-size:.85rem;color:#3d4a5c;}
  .help-step .hn{flex:0 0 auto;width:24px;height:24px;border-radius:50%;background:#23408F;color:#fff;font-weight:700;font-size:.78rem;display:flex;align-items:center;justify-content:center;}
  .help-step i{color:#1E9C4B;}
  .reg-add{margin-top:6px;}
  .dom-chk:disabled{opacity:.45;}
</style>

<div class="form-card">
  <form id="decForm" autocomplete="off">

    <div class="help-box">
      <div class="ht"><i class="bi bi-info-circle-fill"></i>Comment remplir ce formulaire</div>
      <div class="help-steps">
        <div class="help-step"><span class="hn">1</span><div><i class="bi bi-flag me-1"></i>La <b>nature</b> et le <b>cadre</b> sont deja choisis (modifiables en haut). Le <b>numero d'audit</b> est genere automatiquement.</div></div>
        <div class="help-step"><span class="hn">2</span><div><i class="bi bi-person-badge me-1"></i>Designez le <b>responsable</b> (inspecteur non stagiaire) et l'<b>operateur</b> concerne.</div></div>
        <div class="help-step"><span class="hn">3</span><div><i class="bi bi-geo-alt me-1"></i>Precisez le <b>type d'activite</b>, le <b>site</b>, le <b>statut</b> et la <b>date previsionnelle</b>. Les boutons <i class="bi bi-plus-lg"></i> permettent d'ajouter au vol.</div></div>
        <div class="help-step"><span class="hn">4</span><div><i class="bi bi-people me-1"></i>Composez l'<b>equipe</b> : ajoutez les inspecteurs, cochez leurs <b>domaines habilites</b> (les expires sont en rouge) et les <b>reglements</b> vises.</div></div>
      </div>
    </div>

    <!-- Nature + cadre choisis (grises, modifiables) -->
    <div class="grey-box mb-4">
      <div>
        <div class="gi">Nature de la supervision</div>
        <div class="gv" id="natLabel">-</div>
      </div>
      <div style="border-left:1px solid #d7dfea;height:38px;"></div>
      <div>
        <div class="gi">Cadre</div>
        <div class="gv" id="cadreLabel">-</div>
      </div>
      <div style="border-left:1px solid #d7dfea;height:38px;"></div>
      <div>
        <div class="gi">Numero d'audit (auto)</div>
        <div class="gv"><span class="num-badge" id="numPreview">...</span></div>
      </div>
      <a href="<?php echo SITE_URL; ?>/dashboard" class="btn btn-sm btn-outline-secondary ms-auto"><i class="bi bi-arrow-left me-1"></i>Changer la nature / le cadre</a>
    </div>

    <div class="form-section">Designation</div>
    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <label class="form-label">Responsable de l'audit <span class="text-danger">*</span></label>
        <select id="d_resp" style="width:100%"></select>
        <div class="form-text">Seuls les inspecteurs non stagiaires peuvent etre responsables.</div>
      </div>
      <div class="col-md-6">
        <label class="form-label">Statut</label>
        <select class="form-select" id="d_statut">
          <option value="1">Planifiee</option>
          <option value="2">Reportee</option>
          <option value="3">Effectuee</option>
          <option value="4">Suspendue</option>
          <option value="5">A surveiller</option>
        </select>
      </div>
    </div>

    <div class="form-section">Operateur concerne</div>
    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <label class="form-label">Operateur <span class="text-danger">*</span></label>
        <div class="d-flex gap-2 align-items-start">
          <div style="flex:1 1 auto"><select id="d_orga" style="width:100%"></select></div>
          <button type="button" class="btn btn-outline-primary add-btn" id="addOrga" title="Ajouter un operateur"><i class="bi bi-plus-lg"></i></button>
        </div>
      </div>
      <div class="col-md-6">
        <label class="form-label">Type d'activite de l'operateur
          <i class="bi bi-info-circle text-muted" title="Categorie de l'operateur (type d'organisme)"></i>
        </label>
        <div class="d-flex gap-2 align-items-start">
          <div style="flex:1 1 auto"><select id="d_typeorga" style="width:100%"></select></div>
          <button type="button" class="btn btn-outline-primary add-btn" id="addType" title="Ajouter un type d'organisme"><i class="bi bi-plus-lg"></i></button>
        </div>
      </div>
    </div>

    <div class="form-section">Lieu et planification</div>
    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <label class="form-label">Site d'inspection <span class="text-danger">*</span>
          <i class="bi bi-info-circle text-muted" title="Site identifie par son indicateur OACI (ex: FOOL)"></i>
        </label>
        <div class="d-flex gap-2 align-items-start">
          <div style="flex:1 1 auto"><select id="d_site" style="width:100%"></select></div>
          <button type="button" class="btn btn-outline-primary add-btn" id="addSite" title="Ajouter un site"><i class="bi bi-plus-lg"></i></button>
        </div>
      </div>
      <div class="col-md-4">
        <label class="form-label">Date previsionnelle</label>
        <input type="date" class="form-control" id="d_dprev">
        <div class="form-text">Facultatif.</div>
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="d_notif" checked>
          <label class="form-check-label small" for="d_notif">Notifier par mail</label>
        </div>
      </div>
    </div>

    <div class="form-section">Equipe d'audit</div>
    <div class="form-text mb-2">Ajoutez un ou plusieurs inspecteurs. Pour chacun, choisissez-le dans la liste : ses domaines habilites apparaissent aussitot. Cochez le ou les domaines, puis les reglements vises. Les habilitations expirees sont en rouge et non selectionnables.</div>
    <div id="eqList"></div>
    <button type="button" class="btn btn-outline-primary btn-sm mt-1" id="eqAddRow"><i class="bi bi-person-plus me-1"></i>Ajouter un inspecteur</button>

    <div class="d-flex justify-content-end gap-2 mt-3">
      <a href="<?php echo SITE_URL; ?>/dashboard" class="btn btn-light">Annuler</a>
      <button type="submit" class="btn btn-anac" id="decSubmit" disabled><i class="bi bi-check-lg me-1"></i>Enregistrer le declenchement</button>
    </div>
    <div id="mailProgress" style="display:none;margin-top:12px">
      <div class="d-flex align-items-center gap-2 mb-1">
        <span class="spinner-border spinner-border-sm text-primary"></span>
        <span class="small text-muted" id="mailProgressTxt">Enregistrement en cours...</span>
      </div>
      <div class="progress" style="height:6px;border-radius:3px">
        <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" style="width:100%"></div>
      </div>
    </div>
  </form>
</div>

<!-- ===== MODALE : ajouter un operateur ===== -->
<div class="modal fade" id="orgaModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="orgaForm" autocomplete="off">
      <div class="modal-header"><h5 class="modal-title">Nouvel operateur</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2"><label class="form-label">Raison sociale <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="o_nom" maxlength="255" required>
          <div class="form-text" id="o_dup" style="display:none;color:#D32F2F;">Cet operateur existe deja.</div></div>
        <div class="mb-1"><label class="form-label">Sigle</label><input type="text" class="form-control" id="o_sigle" maxlength="70"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-anac" id="o_submit">Ajouter</button></div>
    </form>
  </div>
</div>

<!-- ===== MODALE : ajouter un type d'organisme ===== -->
<div class="modal fade" id="typeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="typeForm" autocomplete="off">
      <div class="modal-header"><h5 class="modal-title">Nouveau type d'organisme</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <label class="form-label">Nom du type <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="t_nom" maxlength="255" required placeholder="ex : Compagnie aerienne">
        <div class="form-text" id="t_dup" style="display:none;color:#D32F2F;">Ce type existe deja.</div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-anac" id="t_submit">Ajouter</button></div>
    </form>
  </div>
</div>

<!-- ===== MODALE : ajouter un site ===== -->
<div class="modal fade" id="siteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="siteForm" autocomplete="off">
      <div class="modal-header"><h5 class="modal-title">Nouveau site</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="row g-2">
          <div class="col-5"><label class="form-label">Indicateur OACI <span class="text-danger">*</span></label>
            <input type="text" class="form-control text-uppercase" id="si_oaci" maxlength="10" placeholder="FOOL">
            <div class="form-text" id="si_dup" style="display:none;color:#D32F2F;">Existe deja.</div></div>
          <div class="col-7"><label class="form-label">Nom du site <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="si_nom" maxlength="150"></div>
          <div class="col-12"><label class="form-label">Ville</label><input type="text" class="form-control" id="si_ville" maxlength="150"></div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-anac" id="si_submit">Ajouter</button></div>
    </form>
  </div>
</div>

<!-- ===== MODALE : ajouter des reglements a un domaine ===== -->
<div class="modal fade" id="regModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <form class="modal-content" id="regForm" autocomplete="off">
      <div class="modal-header"><h5 class="modal-title">Ajouter des reglements - domaine <span id="regDomName" style="color:var(--anac-primary)"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" id="reg_dom">
        <div class="form-text mb-2">Saisissez un ou plusieurs reglements. Ils seront crees pour ce domaine et coches automatiquement.</div>
        <div id="regRows"></div>
        <button type="button" class="btn btn-sm btn-outline-primary" id="regAddRow"><i class="bi bi-plus-lg me-1"></i>Ajouter une ligne</button>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-anac" id="reg_submit">Enregistrer</button></div>
    </form>
  </div>
</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>

<script>
const CSRF       = '<?php echo Security::escape($csrf); ?>';
const API_AUDITS = AGAI_BASE + '/api/audits';
const API_EXP    = AGAI_BASE + '/api/exploitants';
const API_TYPE   = AGAI_BASE + '/api/typesorganisme';
const API_SITE   = AGAI_BASE + '/api/sites';
const API_REG    = AGAI_BASE + '/api/reglements';
const SEL_TYPE   = '<?php echo Security::escape($type); ?>';
const SEL_CADRE  = '<?php echo Security::escape($cadre); ?>';

const TYPE_LABELS = {audit:'Audit', inspection_programmee:'Inspection programmee', inspection_non_programmee:'Inspection non programmee', demonstration:'Demonstration', test:'Test', investigation:'Investigation'};
const CADRE_LABELS = {certification:'Certification', homologation:'Homologation', reconnaissance:'Reconnaissance', renouvellement:'Renouvellement', surveillance_continue:'Surveillance continue', traitement_evenement:"Traitement d'un evenement", fermeture_provisoire:'Fermeture provisoire', fermeture_definitive:'Fermeture definitive', delivrance_autorisation:"Delivrance d'une autorisation"};

function post(url, data){ data = Object.assign({csrf_token: CSRF}, data); return $.post(url, data, null, 'json'); }

$('#natLabel').text(TYPE_LABELS[SEL_TYPE] || SEL_TYPE);
$('#cadreLabel').text(CADRE_LABELS[SEL_CADRE] || SEL_CADRE);

/* Apercu du numero d'audit auto */
post(API_AUDITS, {action:'next_num', type_activite:SEL_TYPE}).done(res => {
  if(res.success) $('#numPreview').text(res.num_audit);
});

/* ---------- Chargement des listes ---------- */
let RESP_READY = false;
function fillResp(inspecteurs){
  const list = (inspecteurs || []).filter(i => String(i.categorie) !== 'stagiaire');
  let opts = '<option value="">Choisir un responsable...</option>';
  list.forEach(i => { opts += '<option value="'+esc(i.idinspecteur)+'">'+esc(i.nom)+(i.trigr_inspecteur?' ('+esc(i.trigr_inspecteur)+')':'')+'</option>'; });
  $('#d_resp').html(opts);
}
function fillOrga(exploitants, keep){
  let opts = '<option value="">Choisir un operateur...</option>';
  (exploitants||[]).forEach(o => { opts += '<option value="'+esc(o.idorga)+'">'+esc(o.nomorga)+(o.trigrorganisme?' ('+esc(o.trigrorganisme)+')':'')+'</option>'; });
  $('#d_orga').html(opts); if(keep) $('#d_orga').val(String(keep));
}
function fillType(types, keep){
  let opts = '<option value="">Non precise</option>';
  (types||[]).forEach(t => { opts += '<option value="'+esc(t.idtypeorga)+'">'+esc(t.nomtypeorg)+'</option>'; });
  $('#d_typeorga').html(opts); if(keep) $('#d_typeorga').val(String(keep));
}
function fillSite(sites, keep){
  let opts = '<option value="">Choisir un site...</option>';
  (sites||[]).forEach(s => { opts += '<option value="'+esc(s.idsite)+'">'+esc(s.indicateur_oaci)+' - '+esc(s.nomsite)+'</option>'; });
  $('#d_site').html(opts); if(keep) $('#d_site').val(String(keep));
}
function s2(id){ $(id).select2({theme:'bootstrap-5', width:'100%'}); }

function loadLists(){
  return post(API_AUDITS, {action:'lists'}).done(res => {
    if(!res.success) return;
    fillResp(res.inspecteurs);
    fillOrga(res.exploitants);
    fillType(res.types_orga);
    fillSite(res.sites);
    s2('#d_resp'); s2('#d_orga'); s2('#d_typeorga'); s2('#d_site');
    $('#d_resp').on('change', function(){ refreshRaBadges(); validateForm(); });
    $('#d_orga, #d_site').on('change', validateForm);
    // Equipe (1C)
    INSP_ALL = res.inspecteurs || [];
    REG_BY_DOM = {};
    (res.reglements || []).forEach(r => { (REG_BY_DOM[r.iddomaine] = REG_BY_DOM[r.iddomaine] || []).push(r); });
    RESP_READY = true;
    validateForm();
  });
}
loadLists();

/* ===== Equipe d'audit (1C) : inspecteurs par domaine habilite ===== */
let REG_BY_DOM = {};
let INSP_ALL = [];
let eqSeq = 0;
let regTargetEl = null;

function fmtDate(s){ if(!s) return ''; const p = String(s).split('-'); return p.length === 3 ? (p[2]+'/'+p[1]+'/'+p[0]) : s; }

function inspOptions(){
  let opts = '<option value="">Choisir un inspecteur...</option>';
  INSP_ALL.forEach(i => { opts += '<option value="'+esc(i.idinspecteur)+'">'+esc(i.nom)+(i.trigr_inspecteur?' ('+esc(i.trigr_inspecteur)+')':'')+'</option>'; });
  return opts;
}

/* Ajout d'une carte inspecteur (chaque carte a sa propre liste deroulante) */
$('#eqAddRow').on('click', function(){
  const idx = ++eqSeq;
  const card = $(
    '<div class="eq-card" data-idx="'+idx+'" data-insp="">'
    + '<div class="eq-head" style="gap:10px">'
    +   '<div style="flex:1 1 auto"><select class="insp-sel" id="inspSel'+idx+'" style="width:100%"></select></div>'
    +   '<span class="ra-tag"></span>'
    +   '<button type="button" class="btn btn-sm btn-outline-danger eq-remove" title="Retirer cet inspecteur"><i class="bi bi-x-lg"></i></button>'
    + '</div>'
    + '<div class="eq-body"><div class="reg-empty">Choisissez un inspecteur pour afficher ses domaines habilites.</div></div>'
    + '</div>'
  );
  $('#eqList').append(card);
  const sel = card.find('.insp-sel');
  sel.html(inspOptions());
  sel.select2({theme:'bootstrap-5', width:'100%', placeholder:'Choisir un inspecteur...'});
  validateForm();
});

/* Choix de l'inspecteur dans une carte : ses domaines s'affichent aussitot */
$(document).on('change', '.insp-sel', function(){
  const card = $(this).closest('.eq-card');
  const id = $(this).val();
  if(id){
    let dup = false;
    $('#eqList .eq-card').each(function(){ if(!$(this).is(card) && String($(this).attr('data-insp')) === String(id)) dup = true; });
    if(dup){
      Swal.fire({icon:'info',title:'Deja dans l\'equipe',text:'Cet inspecteur est deja present dans une autre ligne.',confirmButtonColor:'#23408F'});
      $(this).val('').trigger('change.select2');
      card.attr('data-insp','');
      card.find('.eq-body').html('<div class="reg-empty">Choisissez un inspecteur pour afficher ses domaines habilites.</div>');
      refreshRaBadges(); validateForm();
      return;
    }
  }
  card.attr('data-insp', id || '');
  const body = card.find('.eq-body');
  if(!id){ body.html('<div class="reg-empty">Choisissez un inspecteur pour afficher ses domaines habilites.</div>'); refreshRaBadges(); validateForm(); return; }
  body.html('<div class="text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Chargement des domaines habilites...</div>');
  post(API_AUDITS, {action:'insp_domaines', idinspecteur:id}).done(res => {
    if(!res.success){ body.html('<div class="text-danger small">Erreur de chargement des domaines.</div>'); return; }
    const doms = res.data || [];
    if(!doms.length){ body.html('<div class="reg-empty">Aucun domaine habilite pour cet inspecteur.</div>'); validateForm(); return; }
    body.html(doms.map(domRow).join(''));
    validateForm();
  }).fail(()=>{ body.html('<div class="text-danger small">Echec de chargement.</div>'); });
  refreshRaBadges();
});

function domRow(d){
  const expired = Number(d.expired) === 1;
  const code = esc(d.nomdomaine) + (d.libel_domaine ? ' - ' + esc(d.libel_domaine) : '');
  const tag = expired
    ? '<span class="exp-tag">Habilitation expiree le '+esc(fmtDate(d.date_expiration))+'</span>'
    : '<span class="ok-tag">Valide jusqu\'au '+esc(fmtDate(d.date_expiration))+'</span>';
  return '<div class="eq-dom'+(expired?' expired':'')+'" data-dom="'+esc(d.iddomaine)+'" data-domname="'+esc(d.nomdomaine)+'">'
    + '<div class="dom-line">'
    +   '<input class="form-check-input dom-chk" type="checkbox"'+(expired?' disabled':'')+'>'
    +   '<span class="dom-code">'+code+'</span>'+tag
    + '</div>'
    + '<div class="reg-list">'
    +   regListHtml(d.iddomaine)
    +   '<button type="button" class="btn btn-sm btn-outline-secondary reg-add mt-1"><i class="bi bi-plus-lg me-1"></i>Ajouter des reglements</button>'
    + '</div>'
    + '</div>';
}
function regListHtml(dom){
  const regs = REG_BY_DOM[dom] || [];
  if(!regs.length) return '<div class="reg-holder"><div class="reg-empty">Aucun reglement enregistre pour ce domaine. Utilisez le bouton ci-dessous.</div></div>';
  return '<div class="reg-holder">' + regs.map(r => regCheck(dom, r)).join('') + '</div>';
}
function regCheck(dom, r){
  const rid = 'rg'+dom+'_'+r.idreglement;
  return '<div class="form-check"><input class="form-check-input reg-chk" type="checkbox" value="'+esc(r.idreglement)+'" id="'+rid+'"><label class="form-check-label small" for="'+rid+'">'+esc(r.code_reglement)+' - '+esc(r.libelle_reglement)+'</label></div>';
}

$(document).on('change', '.dom-chk', function(){
  $(this).closest('.eq-dom').find('.reg-list').toggle($(this).is(':checked'));
  validateForm();
});
$(document).on('click', '.eq-remove', function(){ $(this).closest('.eq-card').remove(); refreshRaBadges(); validateForm(); });

function refreshRaBadges(){
  const ra = $('#d_resp').val();
  $('#eqList .eq-card').each(function(){
    const isRa = ra && String($(this).attr('data-insp')) === String(ra);
    $(this).find('.ra-tag').html(isRa ? '<span class="ra-badge">Responsable</span>' : '');
  });
}

/* ----- Bouton + : ajouter des reglements a un domaine (sans rechargement) ----- */
function regRowHtml(){
  return '<div class="reg-row row g-2 mb-2 align-items-center">'
    + '<div class="col-4"><input type="text" class="form-control form-control-sm rr-code" placeholder="Code (ex: RAG-OPS-1)"></div>'
    + '<div class="col-7"><input type="text" class="form-control form-control-sm rr-lib" placeholder="Libelle du reglement"></div>'
    + '<div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger rr-del"><i class="bi bi-x"></i></button></div>'
    + '</div>';
}
$(document).on('click', '.reg-add', function(){
  regTargetEl = $(this).closest('.eq-dom');
  $('#reg_dom').val(regTargetEl.data('dom'));
  $('#regDomName').text(regTargetEl.data('domname') || '');
  $('#regRows').html(regRowHtml());
  new bootstrap.Modal('#regModal').show();
});
$('#regAddRow').on('click', function(){ $('#regRows').append(regRowHtml()); });
$(document).on('click', '.rr-del', function(){ if($('#regRows .reg-row').length > 1){ $(this).closest('.reg-row').remove(); } });

$('#regForm').on('submit', function(e){
  e.preventDefault();
  const dom = $('#reg_dom').val();
  const codes = [], libs = [];
  $('#regRows .reg-row').each(function(){
    const code = $(this).find('.rr-code').val().trim();
    const lib  = $(this).find('.rr-lib').val().trim();
    if(code && lib){ codes.push(code); libs.push(lib); }
  });
  if(!codes.length){ Swal.fire({icon:'warning',title:'Rien a ajouter',text:'Saisissez au moins un code et un libelle.',confirmButtonColor:'#23408F'}); return; }
  const btn = $('#reg_submit'); btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
  // Envoi en une seule requete (un seul token CSRF) via create_batch
  post(API_REG, {action:'create_batch', iddomaine:dom, 'codes[]':codes, 'libelles[]':libs}).done(resp => {
    btn.prop('disabled', false).html('Enregistrer');
    if(resp.success){
      bootstrap.Modal.getInstance(document.getElementById('regModal')).hide();
      finishReg(dom, resp.inserted || []);
      if(resp.errors && resp.errors.length){
        Swal.fire({icon:'warning',title:resp.inserted.length+' insere(s)',html:'Problemes : <b>'+esc(resp.errors.join(', '))+'</b>',confirmButtonColor:'#23408F'});
      }
    } else { Swal.fire({icon:'error',title:'Erreur',text:resp.message,confirmButtonColor:'#23408F'}); }
  }).fail(()=>{ btn.prop('disabled', false).html('Enregistrer'); Swal.fire({icon:'error',title:'Erreur',text:'Echec de la requete.',confirmButtonColor:'#23408F'}); });
});
function finishReg(dom, created){
  if(!created.length){ Swal.fire({icon:'error',title:'Echec',text:'Aucun reglement n\'a pu etre ajoute (doublon de code ?).',confirmButtonColor:'#23408F'}); return; }
  REG_BY_DOM[dom] = REG_BY_DOM[dom] || [];
  created.forEach(c => REG_BY_DOM[dom].push(c));
  if(regTargetEl){
    let holder = regTargetEl.find('.reg-holder');
    holder.find('.reg-empty').remove();
    created.forEach(c => { holder.append(regCheck(dom, c)); regTargetEl.find('#rg'+dom+'_'+c.idreglement).prop('checked', true); });
  }
  validateForm();
}

function gatherTeam(){
  const eqInsp = [], eqDom = [], eqRegs = {}; let idx = 0;
  $('#eqList .eq-card').each(function(){
    const insp = $(this).attr('data-insp');
    if(!insp) return;
    $(this).find('.eq-dom').each(function(){
      const chk = $(this).find('.dom-chk');
      if(chk.is(':checked') && !chk.is(':disabled')){
        eqInsp.push(insp); eqDom.push($(this).data('dom'));
        const rids = []; $(this).find('.reg-chk:checked').each(function(){ rids.push($(this).val()); });
        eqRegs[idx] = rids; idx++;
      }
    });
  });
  return {eqInsp, eqDom, eqRegs};
}

/* Bouton Enregistrer grise dynamiquement tant que le formulaire n'est pas valide */
function hasExpiredSelected(){
  // Bloque si une carte a un inspecteur selectionne ET au moins un domaine expire visible
  let found = false;
  $('#eqList .eq-card').each(function(){
    if(!$(this).attr('data-insp')) return;
    // Un domaine expire dans cette carte = blocage
    if($(this).find('.eq-dom.expired').length){ found = true; }
  });
  return found;
}
function validateForm(){
  const ok = !!$('#d_resp').val() && !!$('#d_orga').val() && !!$('#d_site').val() && !hasExpiredSelected();
  $('#decSubmit').prop('disabled', !ok);
  if(hasExpiredSelected()){
    $('#decSubmit').attr('title', 'Un inspecteur a une habilitation expiree. Retirez-le ou choisissez un autre.');
  } else {
    $('#decSubmit').removeAttr('title');
  }
}

/* ---------- Bouton + : ajouter un operateur ---------- */
$('#addOrga').on('click', function(){ $('#o_nom').val(''); $('#o_sigle').val(''); $('#o_dup').hide(); new bootstrap.Modal('#orgaModal').show(); });
let oDup=null;
$('#o_nom').on('input', function(){
  clearTimeout(oDup); const nom=$(this).val().trim(); if(!nom){ $('#o_dup').hide(); return; }
  oDup=setTimeout(()=>{ post(API_EXP, {action:'check', nomorga:nom, idorga:0}).done(r=>{ $('#o_dup').toggle(!!(r.success&&r.exists)); }); }, 350);
});
$('#orgaForm').on('submit', function(e){
  e.preventDefault();
  const nom=$('#o_nom').val().trim(); if(!nom){ return; }
  const btn=$('#o_submit'); btn.prop('disabled',true);
  post(API_EXP, {action:'create', nomorga:nom, trigrorganisme:$('#o_sigle').val().trim()}).done(r=>{
    btn.prop('disabled',false);
    if(r.success){
      bootstrap.Modal.getInstance(document.getElementById('orgaModal')).hide();
      $('#d_orga').append('<option value="'+esc(r.idorga)+'">'+esc(nom)+'</option>').val(String(r.idorga)).trigger('change');
    } else { Swal.fire({icon:'error',title:'Erreur',text:r.message,confirmButtonColor:'#23408F'}); }
  }).fail(()=>{ btn.prop('disabled',false); Swal.fire({icon:'error',title:'Erreur',text:'Echec.',confirmButtonColor:'#23408F'}); });
});

/* ---------- Bouton + : ajouter un type d'organisme ---------- */
$('#addType').on('click', function(){ $('#t_nom').val(''); $('#t_dup').hide(); new bootstrap.Modal('#typeModal').show(); });
let tDup=null;
$('#t_nom').on('input', function(){
  clearTimeout(tDup); const nom=$(this).val().trim(); if(!nom){ $('#t_dup').hide(); return; }
  tDup=setTimeout(()=>{ post(API_TYPE, {action:'check_nom', nomtypeorg:nom, idtypeorga:0}).done(r=>{ $('#t_dup').toggle(!!(r.success&&r.exists)); }); }, 350);
});
$('#typeForm').on('submit', function(e){
  e.preventDefault();
  const nom=$('#t_nom').val().trim(); if(!nom){ return; }
  const btn=$('#t_submit'); btn.prop('disabled',true);
  post(API_TYPE, {action:'create', nomtypeorg:nom}).done(r=>{
    btn.prop('disabled',false);
    if(r.success){
      bootstrap.Modal.getInstance(document.getElementById('typeModal')).hide();
      $('#d_typeorga').append('<option value="'+esc(r.idtypeorga)+'">'+esc(nom)+'</option>').val(String(r.idtypeorga)).trigger('change');
    } else { Swal.fire({icon:'error',title:'Erreur',text:r.message,confirmButtonColor:'#23408F'}); }
  }).fail(()=>{ btn.prop('disabled',false); Swal.fire({icon:'error',title:'Erreur',text:'Echec.',confirmButtonColor:'#23408F'}); });
});

/* ---------- Bouton + : ajouter un site ---------- */
$('#addSite').on('click', function(){ $('#si_oaci').val(''); $('#si_nom').val(''); $('#si_ville').val(''); $('#si_dup').hide(); new bootstrap.Modal('#siteModal').show(); });
let siDup=null;
$('#si_oaci').on('input', function(){
  clearTimeout(siDup); const oaci=$(this).val().trim(); if(!oaci){ $('#si_dup').hide(); return; }
  siDup=setTimeout(()=>{ post(API_SITE, {action:'check_oaci', indicateur_oaci:oaci, idsite:0}).done(r=>{ $('#si_dup').toggle(!!(r.success&&r.exists)); }); }, 350);
});
$('#siteForm').on('submit', function(e){
  e.preventDefault();
  const oaci=$('#si_oaci').val().trim(), nom=$('#si_nom').val().trim();
  if(!oaci || !nom){ Swal.fire({icon:'warning',title:'Champs requis',text:'Indicateur OACI et nom du site.',confirmButtonColor:'#23408F'}); return; }
  const btn=$('#si_submit'); btn.prop('disabled',true);
  post(API_SITE, {action:'create', indicateur_oaci:oaci, nomsite:nom, ville:$('#si_ville').val().trim(), idpays:0}).done(r=>{
    btn.prop('disabled',false);
    if(r.success){
      bootstrap.Modal.getInstance(document.getElementById('siteModal')).hide();
      $('#d_site').append('<option value="'+esc(r.idsite)+'">'+esc(oaci.toUpperCase())+' - '+esc(nom)+'</option>').val(String(r.idsite)).trigger('change');
    } else { Swal.fire({icon:'error',title:'Erreur',text:r.message,confirmButtonColor:'#23408F'}); }
  }).fail(()=>{ btn.prop('disabled',false); Swal.fire({icon:'error',title:'Erreur',text:'Echec.',confirmButtonColor:'#23408F'}); });
});

/* ---------- Enregistrement du declenchement ---------- */
$('#decForm').on('submit', function(e){
  e.preventDefault();
  if(!$('#d_resp').val()){ Swal.fire({icon:'warning',title:'Responsable requis',text:'Choisissez le responsable de l\'audit.',confirmButtonColor:'#23408F'}); return; }
  if(!$('#d_orga').val()){ Swal.fire({icon:'warning',title:'Operateur requis',text:'Choisissez l\'operateur concerne.',confirmButtonColor:'#23408F'}); return; }
  if(!$('#d_site').val()){ Swal.fire({icon:'warning',title:'Site requis',text:'Choisissez le site d\'inspection.',confirmButtonColor:'#23408F'}); return; }
  const team = gatherTeam();
  const data = {
    action:'create', auto_num:1,
    type_activite:SEL_TYPE, cadre:SEL_CADRE,
    idresponsable_audit:$('#d_resp').val(), idorga:$('#d_orga').val(),
    idtypeorga:$('#d_typeorga').val() || 0, idsite:$('#d_site').val(),
    statut:$('#d_statut').val() || 1, date_previsionnelle:$('#d_dprev').val(),
    notif_mail:$('#d_notif').is(':checked') ? 1 : 0,
    eq_inspecteur: team.eqInsp, eq_domaine: team.eqDom, eq_regs_json: JSON.stringify(team.eqRegs)
  };
  const notifActive = $('#d_notif').is(':checked');
  const btn=$('#decSubmit'); const html=btn.html();
  btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
  if(notifActive){
    $('#mailProgressTxt').text('Enregistrement et envoi des notifications en cours...');
    $('#mailProgress').show();
  }
  post(API_AUDITS, data).done(res => {
    $('#mailProgress').hide();
    if(res.success){
      let htm = 'Numero : <b>'+esc(res.num_audit || '')+'</b>';
      if(res.equipe_msg){ htm += '<br><small style="color:#D32F2F">'+esc(res.equipe_msg)+'</small>'; }
      if(res.notif_msg){
        const col = res.notif_msg.indexOf('Erreur') >= 0 || res.notif_msg.indexOf('echec') >= 0 ? '#D32F2F' : '#1E9C4B';
        htm += '<br><small style="color:'+col+'"><i class="bi bi-envelope me-1"></i>'+esc(res.notif_msg)+'</small>';
      }
      const ico = (res.equipe_msg && res.equipe_msg.indexOf('Attention') >= 0) ? 'warning' : 'success';
      Swal.fire({icon:ico, title:'Declenchement enregistre', html:htm, confirmButtonColor:'#23408F'})
        .then(()=>{ window.location = AGAI_BASE + '/audits'; });
    } else { btn.prop('disabled',false).html(html); Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'}); }
  }).fail(()=>{ $('#mailProgress').hide(); btn.prop('disabled',false).html(html); Swal.fire({icon:'error',title:'Erreur',text:'Echec de la requete.',confirmButtonColor:'#23408F'}); });
});
</script>