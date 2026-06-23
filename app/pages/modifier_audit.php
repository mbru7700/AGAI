<?php
/**
 * Page : Modification d'un audit (pleine page, design declenchement)
 * Corrections :
 * - Numero grise (readonly)
 * - Activite operateur recuperee (types_orga depuis lists)
 * - Chef inspecteur retire
 * - Case RA retiree (RA = idresponsable_audit de l'audit)
 * - Domaine auto selon inspecteur via insp_domaines
 * - Reglements en modale SweetAlert sans refresh
 * - Verification habilitation assouplie en modification
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardPage('audits');
if (!in_array(Rbac::role(), ['admin', 'chef_inspecteur'], true)) {
    header('Location: ' . SITE_URL . '/audits'); exit;
}
$idaudit = (int) ($_GET['id'] ?? 0);
if ($idaudit <= 0) { header('Location: ' . SITE_URL . '/audits'); exit; }

$csrf      = Security::generateCSRF();
$pageTitle = 'Modifier l\'audit';
$active    = 'audits';
require_once INCLUDES_PATH . '/layout_head.php';
?>
<style>
.form-section{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:20px 22px;margin-bottom:16px;box-shadow:0 1px 2px rgba(16,30,54,.04);}
.sec-title{font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;font-weight:700;color:#23408F;border-bottom:2px solid #23408F;padding-bottom:5px;margin-bottom:14px;display:flex;align-items:center;gap:6px;}
.req::after{content:" *";color:#D32F2F;}
.prog-wrap{height:5px;background:#eef1f6;border-radius:3px;margin-bottom:18px;overflow:hidden;}
.prog-fill{height:100%;border-radius:3px;background:linear-gradient(90deg,#23408F,#1E9C4B);transition:width .3s;}
.insp-card{border:1px solid #eef1f6;border-radius:12px;padding:14px 14px 10px;margin-bottom:10px;background:#fafcff;position:relative;}
.insp-card:hover{border-color:#c5d0e6;}
.rm-btn-abs{position:absolute;top:8px;right:8px;padding:2px 7px;font-size:.78rem;}
.dom-badge{display:inline-flex;align-items:center;background:#e8f0fe;color:#23408F;border-radius:8px;padding:.15rem .55rem;font-size:.78rem;font-weight:600;margin:.15rem;}
.dom-badge.exp{background:#fff3cd;color:#856404;}
.reg-chip{display:inline-flex;align-items:center;gap:5px;background:#d1e7dd;color:#0a5c36;border-radius:8px;padding:.18rem .55rem;font-size:.78rem;font-weight:600;margin:.12rem;}
.reg-chip .rm-r{cursor:pointer;font-size:.8rem;opacity:.7;}
.reg-chip .rm-r:hover{opacity:1;}
.insp-load{display:none;font-size:.78rem;color:#7b8aa0;}
</style>

<div class="page-head">
  <div>
    <h1><i class="bi bi-pencil-square me-2" style="color:var(--anac-primary)"></i>Modifier l'audit</h1>
    <div class="sub" id="auditSubTitle">Chargement en cours...</div>
  </div>
  <a href="<?php echo SITE_URL; ?>/audits" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Retour aux audits</a>
</div>

<div class="prog-wrap"><div class="prog-fill" id="fProg" style="width:0%"></div></div>

<div id="loadZone" class="text-center py-5">
  <span class="spinner-border text-primary me-2"></span>Chargement de l'audit...
</div>

<div id="formZone" style="display:none">
<form id="modifForm" autocomplete="off">
  <input type="hidden" id="m_id" value="<?php echo $idaudit; ?>">
  <input type="hidden" id="m_csrf" value="<?php echo Security::escape($csrf); ?>">

  <!-- S1 : Identification -->
  <div class="form-section">
    <div class="sec-title"><i class="bi bi-clipboard-data"></i>Identification de l'acte</div>
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label fw-bold">Numero d'audit</label>
        <input type="text" class="form-control bg-light" id="m_num" readonly
               style="cursor:not-allowed;color:#5b6b85;font-weight:700">
        <div class="form-text">Le numero est genere automatiquement et non modifiable.</div>
      </div>
      <div class="col-md-4">
        <label class="form-label req">Nature de la supervision</label>
        <select class="form-select" id="m_type" required>
          <option value="">-- Choisir --</option>
          <option value="audit">Audit</option>
          <option value="inspection_programmee">Inspection programmee</option>
          <option value="inspection_non_programmee">Inspection non programmee</option>
          <option value="demonstration">Demonstration</option>
          <option value="test">Test</option>
          <option value="investigation">Investigation</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label req">Cadre</label>
        <select class="form-select" id="m_cadre" required>
          <option value="">-- Choisir --</option>
          <option value="certification">Certification</option>
          <option value="homologation">Homologation</option>
          <option value="reconnaissance">Reconnaissance</option>
          <option value="renouvellement">Renouvellement</option>
          <option value="surveillance_continue">Surveillance continue</option>
          <option value="traitement_evenement">Traitement d'un evenement</option>
          <option value="fermeture_provisoire">Fermeture provisoire</option>
          <option value="fermeture_definitive">Fermeture definitive</option>
          <option value="delivrance_autorisation">Delivrance d'une autorisation</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label req">Statut</label>
        <select class="form-select" id="m_statut" required>
          <option value="1">1 - Planifie</option>
          <option value="2">2 - Reporte</option>
          <option value="3">3 - Effectue</option>
          <option value="4">4 - Suspendu</option>
          <option value="5">5 - A surveiller</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Activite de l'operateur</label>
        <select class="form-select" id="m_typeorga">
          <option value="">-- Choisir --</option>
        </select>
      </div>
    </div>
  </div>

  <!-- S2 : Operateur et lieu -->
  <div class="form-section">
    <div class="sec-title"><i class="bi bi-buildings"></i>Operateur et lieu</div>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label req">Operateur (exploitant)</label>
        <select id="m_orga" style="width:100%"></select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Site d'inspection</label>
        <select id="m_site" style="width:100%"></select>
      </div>
      <div class="col-12">
        <label class="form-label">Libelle libre du site <small class="text-muted">(si le site n'est pas dans la liste)</small></label>
        <input type="text" class="form-control" id="m_site_lib" maxlength="100" placeholder="Ex: Aerodrome de Mouila">
      </div>
    </div>
  </div>

  <!-- S3 : Responsable et dates -->
  <div class="form-section">
    <div class="sec-title"><i class="bi bi-person-badge"></i>Responsable et planification</div>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label req">Responsable d'audit (R.A)</label>
        <select id="m_resp" style="width:100%"></select>
      </div>
      <div class="col-md-3">
        <label class="form-label req">Date previsionnelle</label>
        <input type="date" class="form-control" id="m_dprev" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Date de realisation</label>
        <input type="date" class="form-control" id="m_dreal">
      </div>
      <div class="col-md-3">
        <label class="form-label">Delai d'execution (jours)</label>
        <input type="number" class="form-control" id="m_delai" min="0" max="365">
      </div>
      <div class="col-md-3">
        <label class="form-label">Date delivrance rapport</label>
        <input type="date" class="form-control" id="m_drap">
      </div>
      <div class="col-md-3">
        <label class="form-label">Date notification</label>
        <input type="date" class="form-control" id="m_dnotif">
      </div>
      <div class="col-md-3 d-flex align-items-end pb-1">
        <div class="form-check">
          <input type="checkbox" class="form-check-input" id="m_notif">
          <label class="form-check-label" for="m_notif">Notifier par email</label>
        </div>
      </div>
    </div>
  </div>

  <!-- S4 : Equipe -->
  <div class="form-section">
    <div class="sec-title"><i class="bi bi-people"></i>Equipe d'audit</div>
    <div class="alert alert-info py-2 small mb-3">
      <i class="bi bi-info-circle me-1"></i>Selectionnez un inspecteur : ses domaines habilites s'affichent automatiquement. Ajoutez ensuite les reglements vises.
    </div>
    <div id="equipeCards"></div>
    <button type="button" class="btn btn-outline-primary" id="btnAddInsp">
      <i class="bi bi-person-plus me-1"></i>Ajouter un inspecteur
    </button>
  </div>

  <!-- Boutons -->
  <div class="d-flex justify-content-between align-items-center mt-2 mb-5">
    <a href="<?php echo SITE_URL; ?>/audits" class="btn btn-light btn-lg">
      <i class="bi bi-x-lg me-1"></i>Annuler
    </a>
    <button type="submit" class="btn btn-anac btn-lg" id="modifSubmit">
      <i class="bi bi-check-lg me-1"></i>Enregistrer les modifications
    </button>
  </div>
</form>
</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>
<script>
const CSRF       = '<?php echo Security::escape($csrf); ?>';
const IDAUDIT    = <?php echo $idaudit; ?>;
const API_AUDITS = AGAI_BASE + '/api/audits';

function apiPost(data){ data=Object.assign({csrf_token:CSRF},data); return $.post(API_AUDITS,data,null,'json'); }
function dFmt(s){ return s ? String(s).substring(0,10) : ''; }

let INSPECTEURS=[], REGLEMENTS=[], AUDIT=null, EQUIPE=[], EQUIPE_REGS=[];
let SEQ=0;
// Cache domaines par inspecteur (evite les appels multiples)
let DOM_CACHE={};

/* ======= CHARGEMENT INITIAL ======= */
$.when(
  apiPost({action:'get',    idaudit:IDAUDIT}),
  apiPost({action:'lists'})
).done(function(ra, rb){
  const resA=ra[0], resL=rb[0];
  if(!resA.success){
    Swal.fire({icon:'error',title:'Audit introuvable',text:resA.message,confirmButtonColor:'#23408F'})
      .then(function(){window.location=AGAI_BASE+'/audits';});
    return;
  }
  AUDIT     = resA.data;
  EQUIPE    = resA.equipe||[];
  EQUIPE_REGS = resA.reglements_detail||[];

  if(resL.success){
    INSPECTEURS = resL.inspecteurs||[];
    REGLEMENTS  = resL.reglements||[];
    buildSelects(resL);
  }

  fillForm();
  buildEquipeCards();

  $('#loadZone').hide();
  $('#formZone').fadeIn(200);
  updateProgress();
  $('#auditSubTitle').text(esc(AUDIT.num_audit||'')+' - '+esc(AUDIT.type_activite||''));
}).fail(function(){
  Swal.fire({icon:'error',title:'Erreur de chargement',confirmButtonColor:'#23408F'})
    .then(function(){window.location=AGAI_BASE+'/audits';});
});

/* ======= CONSTRUCTION DES SELECTS ======= */
function buildSelects(lists){
  // Exploitants
  let h='<option value="">-- Exploitant --</option>';
  (lists.exploitants||[]).forEach(function(o){h+='<option value="'+esc(o.idorga)+'">'+esc(o.nomorga)+'</option>';});
  $('#m_orga').html(h);
  $('#m_orga').select2({theme:'bootstrap-5',width:'100%',placeholder:'Choisir un exploitant'});

  // Sites
  h='<option value="">-- Site --</option>';
  (lists.sites||[]).forEach(function(s){h+='<option value="'+esc(s.idsite)+'">'+esc(s.indicateur_oaci)+' - '+esc(s.nomsite)+'</option>';});
  $('#m_site').html(h);
  $('#m_site').select2({theme:'bootstrap-5',width:'100%',placeholder:'Choisir un site',allowClear:true});

  // Responsable
  h='<option value="">-- Responsable --</option>';
  INSPECTEURS.forEach(function(i){h+='<option value="'+esc(i.idinspecteur)+'">'+esc(i.nom)+'</option>';});
  $('#m_resp').html(h);
  $('#m_resp').select2({theme:'bootstrap-5',width:'100%',placeholder:'Choisir le R.A'});

  // Types organisme (activite operateur)
  h='<option value="">-- Type d\'activite --</option>';
  (lists.types_orga||[]).forEach(function(t){h+='<option value="'+esc(t.idtypeorga)+'">'+esc(t.nomtypeorg)+'</option>';});
  $('#m_typeorga').html(h);
}

/* ======= REMPLISSAGE DU FORMULAIRE ======= */
function fillForm(){
  const a=AUDIT;
  $('#m_num').val(a.num_audit||'');
  $('#m_type').val(a.type_activite||'');
  $('#m_cadre').val(a.cadre||'');
  $('#m_statut').val(String(a.statut||1));
  // Activite operateur : priorite idtypeorga, sinon text
  if(a.idtypeorga){ $('#m_typeorga').val(String(a.idtypeorga)); }
  $('#m_orga').val(String(a.idorga||'')).trigger('change');
  $('#m_site').val(String(a.idsite||'')).trigger('change');
  $('#m_site_lib').val(a.site_inspection||'');
  $('#m_resp').val(String(a.idresponsable_audit||'')).trigger('change');
  $('#m_dprev').val(dFmt(a.date_previsionnelle));
  $('#m_dreal').val(dFmt(a.date_realisation));
  $('#m_delai').val(a.delai_execution||'');
  $('#m_drap').val(dFmt(a.date_delivrance_rapport));
  $('#m_dnotif').val(dFmt(a.date_notification));
  $('#m_notif').prop('checked', Number(a.notif_mail)===1);
}


/* ======= BARRE DE PROGRESSION ======= */
function updateProgress(){
  const fields=['m_type','m_cadre','m_statut','m_dprev'];
  const orga=$('#m_orga').val()||'';
  const resp=$('#m_resp').val()||'';
  const filled=fields.filter(function(f){return ($('#'+f).val()||'')!=='';}).length
    + (orga?1:0) + (resp?1:0);
  const nbCards=$('#equipeCards .insp-card').length;
  const pct=Math.min(100,Math.round((filled/6)*70)+(nbCards>0?30:0));
  $('#fProg').css('width',pct+'%');
}
$(document).on('change input','#modifForm input,#modifForm select',updateProgress);

/* ======= EQUIPE : CONSTRUCTION INITIALE ======= */
function buildEquipeCards(){
  if(!EQUIPE.length){ addInspCard(null); return; }
  const seen={};
  EQUIPE.forEach(function(m){
    if(seen[m.idinspecteur]) return;
    seen[m.idinspecteur]=true;
    // Reglements de cet inspecteur (par idequipe)
    const myRegs=EQUIPE_REGS.filter(function(r){return r.idequipe===m.idequipe||r.idequipe===String(m.idequipe);});
    addInspCard(m, myRegs);
  });
}

/* ======= AJOUTER UNE CARTE INSPECTEUR ======= */
function addInspCard(m, regsInit){
  const s=++SEQ;
  m=m||{};
  const iid=String(m.idinspecteur||'');
  const idom=String(m.iddomaine||'');

  let inspOpts='<option value="">-- Inspecteur --</option>';
  INSPECTEURS.forEach(function(i){
    inspOpts+='<option value="'+esc(i.idinspecteur)+'"'+(String(i.idinspecteur)===iid?' selected':'')+'>'+esc(i.nom)+'</option>';
  });

  const html='<div class="insp-card" id="ic_'+s+'">'
    +'<button type="button" class="btn btn-sm btn-outline-danger rm-btn-abs rm-insp" data-seq="'+s+'" title="Retirer"><i class="bi bi-x-lg"></i></button>'
    +'<div class="row g-2 align-items-start">'
    +'<div class="col-md-4">'
    +'<label class="form-label small fw-bold mb-1">Inspecteur</label>'
    +'<select class="eq-insp" id="ei_'+s+'" data-seq="'+s+'" style="width:100%">'+inspOpts+'</select>'
    +'</div>'
    +'<div class="col-md-4">'
    +'<label class="form-label small fw-bold mb-1">Domaine habilite</label>'
    +'<div id="dom-wrap-'+s+'"><div class="text-muted small">Selectionnez un inspecteur...</div></div>'
    +'<input type="hidden" class="eq-dom" id="ed_'+s+'" value="'+esc(idom)+'">'
    +'</div>'
    +'<div class="col-md-4">'
    +'<label class="form-label small fw-bold mb-1">Reglements vises</label>'
    +'<div id="rl_'+s+'" class="mb-1"></div>'
    +'<button type="button" class="btn btn-xs btn-outline-primary btn-add-reg" data-seq="'+s+'" style="font-size:.75rem;padding:3px 9px"><i class="bi bi-plus me-1"></i>Ajouter reglement</button>'
    +'</div>'
    +'</div></div>';
  $('#equipeCards').append(html);

  $('#ei_'+s).select2({theme:'bootstrap-5',dropdownParent:$('body'),width:'100%',placeholder:'Inspecteur'});

  // Si inspecteur deja connu : charger ses domaines et preselectionner les reglements
  if(iid){
    const regIds = (regsInit||[]).map(function(r){ return r.idreglement; });
    loadDomaines(s, iid, idom, regIds);
  } else if(regsInit&&regsInit.length){
    // Pas d'inspecteur mais des reglements : les afficher quand meme
    regsInit.forEach(function(r){addRegChip(s, r.idreglement, r.code_reglement);});
  }
  updateProgress();
}

/* ======= CHARGEMENT DOMAINES PAR INSPECTEUR ======= */
function loadDomaines(seq, iid, selDom, selRegs){
  const $wrap=$('#dom-wrap-'+seq);
  $wrap.html('<span style="font-size:.78rem;color:#7b8aa0"><span class="spinner-border spinner-border-sm me-1" style="width:.9rem;height:.9rem"></span>Chargement...</span>');
  if(DOM_CACHE[iid]){
    renderDomSelect(seq, DOM_CACHE[iid], selDom, selRegs);
    return;
  }
  apiPost({action:'insp_domaines', idinspecteur:iid}).done(function(res){
    const doms=(res.success && res.domaines) ? res.domaines : [];
    DOM_CACHE[iid]=doms;
    renderDomSelect(seq, doms, selDom, selRegs);
  }).fail(function(){
    $wrap.html('<div class="text-danger small"><i class="bi bi-exclamation-circle me-1"></i>Erreur chargement domaines.</div>');
  });
}

function renderDomSelect(seq, doms, selDom, selRegs){
  const $wrap=$('#dom-wrap-'+seq);
  const $hidden=$('#ed_'+seq);
  if(!doms||!doms.length){
    $wrap.html('<div class="text-warning small"><i class="bi bi-exclamation-triangle me-1"></i>Aucun domaine habilite pour cet inspecteur.</div>');
    $hidden.val('');
    return;
  }
  let h='<select class="form-select form-select-sm eq-dom-sel" id="eds_'+seq+'" data-seq="'+seq+'"><option value="">-- Choisir le domaine --</option>';
  doms.forEach(function(d){
    const exp=Number(d.est_expire)===1;
    h+='<option value="'+esc(d.iddomaine)+'"'+(String(d.iddomaine)===String(selDom)?' selected':'')
      +(exp?' style="color:#856404"':'')+'>'
      +esc(d.nomdomaine)+(exp?' (expire)':'')+'</option>';
  });
  h+='</select>';
  // Badge expiration si domaine selectionne est expire
  const selDomObj = doms.find(function(d){return String(d.iddomaine)===String(selDom);});
  if(selDomObj && Number(selDomObj.est_expire)===1){
    h+='<div class="mt-1"><span style="background:#fff3cd;color:#856404;font-size:.74rem;padding:.18rem .5rem;border-radius:6px;font-weight:600"><i class="bi bi-exclamation-triangle me-1"></i>Habilitation expiree - enregistrement bloque</span></div>';
  }
  $wrap.html(h);
  const $sel=$wrap.find('select');
  $hidden.val($sel.val()||selDom||'');
  // Verifier l'expiration et griser le bouton si necessaire
  checkExpiredDomains();
  if($sel.val()){
    loadReglementsDomaine(seq, $sel.val(), selRegs||[]);
  }
  $sel.on('change',function(){
    const domId=$(this).val()||'';
    $hidden.val(domId);
    $('#rl_'+seq).empty();
    // Verifier expiration du domaine choisi
    const domObj=doms.find(function(d){return String(d.iddomaine)===domId;});
    const $badge=$wrap.find('.exp-warn');
    $badge.remove();
    if(domObj && Number(domObj.est_expire)===1){
      $wrap.append('<div class="mt-1 exp-warn"><span style="background:#fff3cd;color:#856404;font-size:.74rem;padding:.18rem .5rem;border-radius:6px;font-weight:600"><i class="bi bi-exclamation-triangle me-1"></i>Habilitation expiree - enregistrement bloque</span></div>');
    }
    checkExpiredDomains();
    if(domId){ loadReglementsDomaine(seq, domId, []); }
  });
}

/* ======= GRISER LE BOUTON SI HABILITATION EXPIREE ======= */
function checkExpiredDomains(){
  let hasExpired = false;
  // Verifier chaque ligne d'equipe
  $('#equipeCards .insp-card').each(function(){
    const $c = $(this);
    const iid = $c.find('.eq-insp').val();
    if(!iid) return;
    const seq = $c.find('.eq-insp').data('seq');
    const domId = $c.find('select.eq-dom-sel').val() || $c.find('input.eq-dom').val() || '';
    if(!domId) return;
    // Verifier dans le cache
    if(DOM_CACHE[iid]){
      const domObj = DOM_CACHE[iid].find(function(d){return String(d.iddomaine)===String(domId);});
      if(domObj && Number(domObj.est_expire)===1){ hasExpired = true; }
    }
  });
  const $btn = $('#modifSubmit');
  if(hasExpired){
    $btn.prop('disabled', true)
      .html('<i class="bi bi-lock me-1"></i>Habilitation expiree - Enregistrement bloque')
      .addClass('btn-secondary').removeClass('btn-anac');
  } else {
    $btn.prop('disabled', false)
      .html('<i class="bi bi-check-lg me-1"></i>Enregistrer les modifications')
      .addClass('btn-anac').removeClass('btn-secondary');
  }
}

/* ======= REGLEMENTS PAR DOMAINE ======= */
let REG_CACHE={};

function loadReglementsDomaine(seq, iddom, preselIds){
  if(REG_CACHE[iddom]!==undefined){
    preloadRegChips(seq, REG_CACHE[iddom], preselIds);
    return;
  }
  apiPost({action:'reglements_domaine', iddomaine:iddom}).done(function(res){
    const regs=(res.success&&res.reglements)?res.reglements:[];
    REG_CACHE[iddom]=regs;
    preloadRegChips(seq, regs, preselIds);
  });
}

function preloadRegChips(seq, domRegs, preselIds){
  // Ajouter uniquement les reglements preselectionnes (deja enregistres)
  if(!preselIds||!preselIds.length) return;
  const existingIds=[];
  $('#rl_'+seq+' .reg-chip').each(function(){ existingIds.push(String($(this).data('id'))); });
  preselIds.forEach(function(id){
    if(!id||existingIds.includes(String(id))) return;
    // Chercher dans les reglements du domaine d'abord, puis dans tous
    const r=domRegs.find(function(x){return String(x.idreglement)===String(id);})||
             REGLEMENTS.find(function(x){return String(x.idreglement)===String(id);});
    if(r) addRegChip(seq, r.idreglement, r.code_reglement);
  });
}

/* ======= CHANGEMENT D'INSPECTEUR ======= */
$(document).on('change','.eq-insp',function(){
  const seq=$(this).data('seq');
  const iid=$(this).val()||'';
  $('#dom-wrap-'+seq).html('<div class="text-muted small">Selectionnez un inspecteur...</div>');
  $('#ed_'+seq).val('');
  $('#rl_'+seq).empty();
  if(iid){ loadDomaines(seq, iid, '', []); }
  else { checkExpiredDomains(); }
});

/* ======= RETIRER UN INSPECTEUR ======= */
$(document).on('click','.rm-insp',function(){
  const s=$(this).data('seq');
  const $ei=$('#ei_'+s);
  if($ei.hasClass('select2-hidden-accessible')) $ei.select2('destroy');
  $('#ic_'+s).remove();
  updateProgress();
});

/* ======= AJOUTER DES REGLEMENTS (MODALE CHECKBOXES MULTIPLES) ======= */
$(document).on('click','.btn-add-reg',function(){
  const seq=$(this).data('seq');
  const domId=$('#ed_'+seq).val()||'';
  // Reglements : du domaine si dispo, sinon tous
  let availRegs=(domId && REG_CACHE[domId] && REG_CACHE[domId].length) ? REG_CACHE[domId] : REGLEMENTS;
  // IDs deja selectionnes
  const existingIds=[];
  $('#rl_'+seq+' .reg-chip').each(function(){ existingIds.push(String($(this).data('id'))); });

  if(!availRegs.length){
    // Pas de reglement disponible : proposer d'en saisir un nouveau
    showAddNewRegModal(seq, domId);
    return;
  }

  // Construire les checkboxes
  let checkHtml='<div style="max-height:300px;overflow-y:auto;text-align:left">';
  const domNom = domId ? (DOM_CACHE[Object.keys(DOM_CACHE).find(k=>DOM_CACHE[k].find(d=>String(d.iddomaine)===domId))||'']||[]).find(d=>String(d.iddomaine)===domId) : null;
  checkHtml+='<div class="mb-2 small text-muted">'+(domId&&availRegs!==REGLEMENTS?'Reglements du domaine selectionne :':'Tous les reglements disponibles :')+'</div>';
  // Bouton tout selectionner
  checkHtml+='<div class="mb-2"><button type="button" class="btn btn-xs btn-outline-secondary" id="selAllRegs" style="font-size:.74rem;padding:2px 8px">Tout selectionner</button>'
    +'<button type="button" class="btn btn-xs btn-outline-secondary ms-1" id="deselAllRegs" style="font-size:.74rem;padding:2px 8px">Tout deselectionner</button></div>';
  availRegs.forEach(function(r){
    const already=existingIds.includes(String(r.idreglement));
    checkHtml+='<div class="form-check mb-1" style="text-align:left">'
      +'<input class="form-check-input swal-reg-chk" type="checkbox" value="'+esc(r.idreglement)+'"'+(already?' checked disabled':'')+' id="rc_'+esc(r.idreglement)+'">'
      +'<label class="form-check-label" for="rc_'+esc(r.idreglement)+'">'
      +'<span style="font-weight:700;color:#23408F">'+esc(r.code_reglement)+'</span>'
      +(r.libelle_reglement?'<span class="text-muted ms-1" style="font-size:.82rem">'+esc(r.libelle_reglement.substring(0,70))+'</span>':'')
      +(already?' <span class="badge" style="background:#d1e7dd;color:#0a5c36;font-size:.65rem">Deja ajoute</span>':'')
      +'</label></div>';
  });
  // Lien pour ajouter un nouveau reglement si le domaine n'en a pas d'autres
  checkHtml+='<div class="mt-2 pt-2 border-top"><button type="button" class="btn btn-xs btn-outline-primary" id="btnNewRegInline" style="font-size:.74rem;padding:2px 8px"><i class="bi bi-plus me-1"></i>Nouveau reglement</button></div>';
  checkHtml+='</div>';

  Swal.fire({
    title:'<i class="bi bi-journal-text me-2" style="color:#23408F"></i>Reglements vises',
    html:checkHtml,
    showCancelButton:true,
    confirmButtonText:'<i class="bi bi-check-lg me-1"></i>Ajouter la selection',
    cancelButtonText:'Annuler',
    confirmButtonColor:'#23408F',
    width:'520px',
    didOpen:function(){
      $('#selAllRegs').on('click',function(){ $('.swal-reg-chk:not(:disabled)').prop('checked',true); });
      $('#deselAllRegs').on('click',function(){ $('.swal-reg-chk:not(:disabled)').prop('checked',false); });
      $('#btnNewRegInline').on('click',function(){
        Swal.close();
        setTimeout(function(){ showAddNewRegModal(seq, domId); }, 200);
      });
    },
    preConfirm:function(){
      const sel=[];
      $('.swal-reg-chk:checked:not(:disabled)').each(function(){ sel.push($(this).val()); });
      if(!sel.length){ Swal.showValidationMessage('Cochez au moins un reglement.'); }
      return sel;
    }
  }).then(function(result){
    if(!result.isConfirmed || !result.value || !result.value.length) return;
    result.value.forEach(function(idReg){
      if(existingIds.includes(String(idReg))) return;
      const regObj = availRegs.find(function(r){return String(r.idreglement)===String(idReg);})||
                     REGLEMENTS.find(function(r){return String(r.idreglement)===String(idReg);});
      if(regObj) addRegChip(seq, regObj.idreglement, regObj.code_reglement);
    });
  });
});

/* ======= AJOUTER UN NOUVEAU REGLEMENT DYNAMIQUEMENT ======= */
function showAddNewRegModal(seq, domId){
  Swal.fire({
    title:'<i class="bi bi-plus-circle me-2" style="color:#23408F"></i>Nouveau reglement',
    html:'<div style="text-align:left">'
      +'<div class="mb-2"><label class="form-label small fw-bold">Code du reglement <span style="color:#D32F2F">*</span></label>'
      +'<input type="text" class="form-control form-control-sm" id="newRegCode" placeholder="Ex: OACI-Annexe1-Art.3" maxlength="50"></div>'
      +'<div class="mb-2"><label class="form-label small fw-bold">Libelle du reglement</label>'
      +'<input type="text" class="form-control form-control-sm" id="newRegLib" placeholder="Description du reglement" maxlength="200"></div>'
      +'<div class="small text-muted mt-2"><i class="bi bi-info-circle me-1"></i>Ce reglement sera associe au domaine selectionne et ajoute a votre liste.</div>'
      +'</div>',
    showCancelButton:true,
    confirmButtonText:'<i class="bi bi-check-lg me-1"></i>Creer et ajouter',
    cancelButtonText:'Annuler',
    confirmButtonColor:'#1E9C4B',
    preConfirm:function(){
      const code=($('#newRegCode').val()||'').trim();
      if(!code){ Swal.showValidationMessage('Le code du reglement est obligatoire.'); return false; }
      return {code:code, libelle:($('#newRegLib').val()||'').trim()};
    }
  }).then(function(result){
    if(!result.isConfirmed||!result.value) return;
    const {code, libelle} = result.value;
    // Inserer en BDD via l'endpoint reglements
    $.post(AGAI_BASE+'/api/reglements', {
      csrf_token: CSRF,
      action: 'create',
      code_reglement: code,
      libelle_reglement: libelle,
      iddomaine: domId||''
    }, null, 'json').done(function(res){
      if(res.success && res.idreglement){
        const newReg={idreglement:res.idreglement, code_reglement:code, libelle_reglement:libelle};
        // Ajouter au cache
        REGLEMENTS.push(newReg);
        if(domId){ if(!REG_CACHE[domId]) REG_CACHE[domId]=[]; REG_CACHE[domId].push(newReg); }
        addRegChip(seq, newReg.idreglement, newReg.code_reglement);
        Swal.fire({icon:'success',title:'Reglement ajoute',timer:1400,showConfirmButton:false});
      } else {
        Swal.fire({icon:'error',title:'Erreur',text:res.message||'Impossible de creer ce reglement.',confirmButtonColor:'#23408F'});
      }
    }).fail(function(){
      Swal.fire({icon:'error',title:'Erreur reseau',confirmButtonColor:'#23408F'});
    });
  });
}

function addRegChip(seq, id, code){
  $('#rl_'+seq).append('<span class="reg-chip" data-id="'+esc(id)+'" data-seq="'+seq+'">'
    +'<i class="bi bi-file-text me-1" style="font-size:.7rem"></i>'+esc(code)
    +' <i class="bi bi-x rm-reg" style="cursor:pointer;opacity:.7"></i></span>');
}
$(document).on('click','.rm-reg',function(){ $(this).closest('.reg-chip').remove(); });

$('#btnAddInsp').on('click',function(){ addInspCard(null, []); });

/* ======= SOUMISSION ======= */
$('#modifForm').on('submit',function(e){
  e.preventDefault();

  // Collecter l'equipe
  const eqI=[],eqD=[],eqRegs=[];
  let eqErr=false;
  $('#equipeCards .insp-card').each(function(){
    const $c=$(this);
    const ins=$c.find('.eq-insp').val();
    if(!ins) return; // Ligne sans inspecteur : ignoree
    // Domaine : select visible genere dynamiquement OU input hidden
    const domSel=$c.find('select.eq-dom-sel').val()||'';
    const domHid=$c.find('input.eq-dom').val()||'';
    const dom=domSel||domHid;
    if(!dom){ eqErr=true; return false; }
    eqI.push(ins); eqD.push(dom);
    const regs=[];
    $c.find('.reg-chip').each(function(){regs.push($(this).data('id'));});
    eqRegs.push(regs.join(','));
  });
  if(eqErr){
    Swal.fire({icon:'warning',title:'Domaine manquant',text:'Selectionnez un domaine pour chaque inspecteur de l\'equipe.',confirmButtonColor:'#23408F'});
    return;
  }

  const site=$('#m_site').val()||'';
  const siteLib=$('#m_site_lib').val().trim();
  const siteTxt=siteLib||($('#m_site option[value="'+site+'"]').text().replace(/^\s*--.*--\s*$/,'')||'');

  const data={
    action:'update',
    idaudit:IDAUDIT,
    num_audit:$('#m_num').val().trim(),
    type_activite:$('#m_type').val(),
    cadre:$('#m_cadre').val(),
    statut:$('#m_statut').val()||1,
    idtypeorga:$('#m_typeorga').val()||'',
    idorga:$('#m_orga').val()||'',
    idsite:site,
    site_inspection:siteTxt,
    idresponsable_audit:$('#m_resp').val()||'',
    idchef_inspecteur:$('#m_resp').val()||'', // RA = chef pour contrainte BDD
    date_previsionnelle:$('#m_dprev').val(),
    date_realisation:$('#m_dreal').val(),
    delai_execution:$('#m_delai').val()||'',
    date_delivrance_rapport:$('#m_drap').val(),
    date_notification:$('#m_dnotif').val(),
    notif_mail:$('#m_notif').is(':checked')?1:0,
    'eq_inspecteur[]':eqI,
    'eq_domaine[]':eqD,
    'eq_reglements_csv':eqRegs.join('|') // Format CSV par inspecteur, separe par |
  };

  const btn=$('#modifSubmit'); const h=btn.html();
  btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm me-1"></span>Enregistrement...');

  $.ajax({
    url:API_AUDITS, type:'POST', dataType:'json',
    data:Object.assign({csrf_token:CSRF},data),
    traditional:true // important pour les tableaux jQuery
  }).done(function(res){
    btn.prop('disabled',false).html(h);
    if(res.success){
      Swal.fire({icon:'success',title:'Audit modifie',
        text:res.message||'Modifications enregistrees.',
        confirmButtonColor:'#1E9C4B',confirmButtonText:'Retour aux audits'})
      .then(function(){window.location=AGAI_BASE+'/audits';});
    } else {
      Swal.fire({icon:'error',title:'Erreur',text:res.message||'Erreur lors de la modification.',confirmButtonColor:'#23408F'});
    }
  }).fail(function(){
    btn.prop('disabled',false).html(h);
    Swal.fire({icon:'error',title:'Erreur reseau',text:'Echec de la requete.',confirmButtonColor:'#23408F'});
  });
});
</script>