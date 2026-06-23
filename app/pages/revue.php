<?php
/**
 * Revue documentaire IX-GEN-R3-F-I-017
 * - Inspecteur : voit et saisit uniquement sa propre fiche
 * - RA / CI / admin : voit toutes les fiches, peut saisir pour chacun
 * - Choix exclusif saisie OU PDF joint
 * - Editeur riche Quill.js
 * - Impression avec en-tete et pied de page ANAC
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardPage('audits');

$idaudit = (int) ($_GET['audit'] ?? 0);
if ($idaudit <= 0) { header('Location: ' . SITE_URL . '/mes-audits'); exit; }

$csrf      = Security::generateCSRF();
$role      = Rbac::role();
$uid       = (int) ($_SESSION['user_id'] ?? 0);
$pageTitle = 'Revue documentaire';
$active    = 'audits';
require_once INCLUDES_PATH . '/layout_head.php';
?>
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
<style>
.rev-head{background:linear-gradient(135deg,#23408F,#1b3576);color:#fff;border-radius:14px;padding:18px 22px;margin-bottom:14px;}
.rh-num{font-size:1.1rem;font-weight:700;}
.rh-sub{font-size:.85rem;opacity:.85;}
.rev-card{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:18px 20px;box-shadow:0 1px 2px rgba(16,30,54,.04);margin-bottom:12px;}
.sec-title{font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;font-weight:700;color:#23408F;border-bottom:2px solid #23408F;padding-bottom:4px;margin:0 0 10px;}
.team-row{display:flex;gap:8px;align-items:center;padding:6px 10px;border-radius:8px;border:1px solid #eef1f6;margin-bottom:5px;font-size:.88rem;}
.team-row.est-ra{border-color:#D32F2F;background:#fff8f8;}
.ra-dot{width:10px;height:10px;border-radius:50%;background:#D32F2F;flex:0 0 auto;}
.insp-dot{width:10px;height:10px;border-radius:50%;background:#23408F;flex:0 0 auto;}
.form-section{border:1px solid #eef1f6;border-radius:12px;padding:14px 16px;margin-bottom:10px;background:#fcfdff;}
.fs-num{display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:50%;background:#23408F;color:#fff;font-size:.78rem;font-weight:700;margin-right:8px;flex:0 0 auto;}
.fs-title{font-weight:700;color:#2C3E50;font-size:.93rem;}
.insp-tab-btn{padding:8px 16px;border:1px solid #eef1f6;border-radius:8px 8px 0 0;background:#f7f9fc;color:#5b6b85;font-size:.85rem;cursor:pointer;margin-right:3px;border-bottom:none;}
.insp-tab-btn.active{background:#23408F;color:#fff;border-color:#23408F;}
.tab-pane-custom{display:none;border:1px solid #eef1f6;border-radius:0 12px 12px 12px;padding:18px 20px;background:#fff;}
.tab-pane-custom.active{display:block;}
.cons-badge{background:#1E9C4B;color:#fff;font-size:.72rem;font-weight:700;padding:.18rem .5rem;border-radius:20px;margin-left:5px;}
.ql-container.ql-snow{border-radius:0 0 8px 8px;min-height:160px;}
.ql-toolbar.ql-snow{border-radius:8px 8px 0 0;}
.mode-choice{display:flex;gap:10px;margin-bottom:12px;}
.mode-choice label{flex:1;display:flex;align-items:center;gap:8px;padding:10px 14px;border:2px solid #dee2e6;border-radius:10px;cursor:pointer;font-size:.9rem;transition:all .15s;}
.mode-choice label:has(input:checked){border-color:#23408F;background:rgba(35,64,143,.06);}
.ra-info-box{background:rgba(35,64,143,.07);border:1px solid rgba(35,64,143,.2);border-radius:10px;padding:10px 14px;margin-bottom:12px;font-size:.85rem;color:#23408F;}
</style>

<div class="page-head">
  <div>
    <h1><i class="bi bi-file-text me-2" style="color:var(--anac-primary)"></i>Revue documentaire</h1>
    <div class="sub">Formulaire IX-GEN-R3-F-I-017 - Version 02</div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?php echo SITE_URL; ?>/mes-audits" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Mes audits</a>
    <button class="btn btn-outline-primary" id="btnPrint"><i class="bi bi-printer me-1"></i>Imprimer</button>
  </div>
</div>

<div id="auditHead" class="rev-head">
  <div class="d-flex align-items-center gap-2"><span class="spinner-border spinner-border-sm text-white"></span><span>Chargement...</span></div>
</div>
<div class="rev-card" id="equipeCard" style="display:none">
  <div class="sec-title">Equipe d'audit</div>
  <div id="equipeList"></div>
</div>
<div id="revueZone" style="display:none">
  <div id="inspTabs" class="d-flex flex-wrap mb-0"></div>
  <div id="tabContent"></div>
</div>
<div id="consolidateZone" style="display:none;margin-top:10px"></div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>
<script>
const CSRF    = '<?php echo Security::escape($csrf); ?>';
const API_REV = AGAI_BASE + '/api/revue';
const IDAUDIT = <?php echo (int) $idaudit; ?>;
const ROLE    = '<?php echo Security::escape($role); ?>';
const IS_CI   = (ROLE === 'admin' || ROLE === 'chef_inspecteur');
const IMG_BASE = AGAI_BASE + '/public/images/';
const TYPE_LABELS = {
  audit:'Audit', inspection_programmee:'Inspection programmee',
  inspection_non_programmee:'Inspection non programmee',
  demonstration:'Demonstration', test:'Test', investigation:'Investigation'
};

function apiPost(data){ data=Object.assign({csrf_token:CSRF},data); return $.post(API_REV,data,null,'json'); }
function postFile(fd){ fd.append('csrf_token',CSRF); return $.ajax({url:API_REV,type:'POST',data:fd,processData:false,contentType:false,dataType:'json'}); }
function fmtDate(s){ if(!s||s==='0000-00-00') return '-'; const p=String(s).split('-'); return p.length===3?(p[2]+'/'+p[1]+'/'+p[0]):s; }

let AUDIT=null, EQUIPE=[], REVUES={}, MY_INSP_ID=null, IS_RA=false;
let QUILLS={};

const SECTS=[
  {k:'contexte_objectif',         n:'1', t:'Contexte et objectif'},
  {k:'perimetre_activite',        n:'2', t:"Perimetre de l'activite"},
  {k:'references_reglementaires', n:'3', t:'Reference(s) reglementaire(s)'},
  {k:'criteres_audit',            n:'4', t:"Criteres d'audit retenus pour etre evalues, selon le scope de l'activite"},
  {k:'liste_documentation',       n:'5', t:"Liste de la documentation examinee, selon le scope de l'activite"},
  {k:'points_attention',          n:'6', t:"Points d'attention et conclusion"},
];

/* ======= DEMARRAGE : charger my_insp en premier, puis tout le reste ======= */
apiPost({action:'my_insp'}).done(function(r){
  MY_INSP_ID = (r.success && r.idinspecteur) ? parseInt(r.idinspecteur) : null;
}).always(function(){
  // Charger l'audit, l'equipe et les revues en parallele
  $.when(
    apiPost({action:'get_audit',  idaudit:IDAUDIT}),
    apiPost({action:'list_revues',idaudit:IDAUDIT})
  ).done(function(ra, rb){
    const resAudit  = ra[0];
    const resRevues = rb[0];
    if(!resAudit.success){ $('#auditHead').html('<div class="text-danger small p-2">'+esc(resAudit.message||'Acces refuse.')+'</div>'); return; }
    AUDIT  = resAudit.audit;
    EQUIPE = resAudit.equipe || [];
    REVUES = {};
    (resRevues.data||[]).forEach(function(r){ REVUES[parseInt(r.idinspecteur)]=r; });
    // Detecter si je suis RA
    if(AUDIT && MY_INSP_ID){
      IS_RA = parseInt(AUDIT.ra_id) === MY_INSP_ID;
    }
    renderHead();
    renderEquipe();
    renderTabs();
  }).fail(function(){
    $('#auditHead').html('<div class="text-danger small p-2">Erreur de chargement.</div>');
  });
});

/* ======= EN-TETE AUDIT ======= */
function renderHead(){
  var a=AUDIT;
  $('#auditHead').html(
    '<div class="d-flex justify-content-between align-items-start flex-wrap gap-2">'
    +'<div><div class="rh-num"><i class="bi bi-clipboard-check me-2"></i>'+esc(a.num_audit)+'</div>'
    +'<div class="rh-sub mt-1">'+esc(TYPE_LABELS[a.type_activite]||a.type_activite)+' &middot; '+esc(a.cadre||'')+'</div></div>'
    +'<div class="text-end">'
    +'<div class="rh-sub">Operateur : <b>'+esc(a.operateur||'-')+'</b></div>'
    +'<div class="rh-sub">Site : '+esc(a.site_inspection||'-')+'</div>'
    +'<div class="rh-sub">RA : <b>'+esc(a.ra_nom||'-')+'</b></div>'
    +'<div class="rh-sub">Date prev. : '+fmtDate(a.date_previsionnelle)+'</div>'
    +'</div></div>'
  );
}

/* ======= EQUIPE ======= */
function renderEquipe(){
  var html='';
  var seen={};
  EQUIPE.forEach(function(m){
    if(seen[m.idinspecteur]) return; seen[m.idinspecteur]=true;
    var ra=Number(m.est_responsable)===1;
    html+='<div class="team-row'+(ra?' est-ra':'')+'"><div class="'+(ra?'ra-dot':'insp-dot')+'"></div>'
      +'<div style="flex:1"><b>'+esc(m.nom)+'</b>'+(ra?' <span style="color:#D32F2F;font-size:.75rem">(R.A)</span>':'')+'</div>'
      +'<div class="text-muted" style="font-size:.82rem">'+esc(m.nomdomaine)+'</div></div>';
  });
  $('#equipeList').html(html||'<div class="text-muted small">Aucun inspecteur dans l\'equipe.</div>');
  $('#equipeCard').show();
}

/* ======= ONGLETS ======= */
function renderTabs(){
  var seen={}, members=[];
  EQUIPE.forEach(function(m){ if(!seen[m.idinspecteur]){seen[m.idinspecteur]=true; members.push(m);} });

  if(!members.length){
    $('#revueZone').html('<div class="alert alert-info mt-2">Aucun inspecteur dans l\'equipe.</div>').show();
    return;
  }

  // Revue commune du RA (liee a l'idinspecteur du RA)
  var raId = AUDIT && AUDIT.ra_id ? parseInt(AUDIT.ra_id) : null;
  var raRevue = raId ? (REVUES[raId] || null) : null;
  var raHasSoumis = raRevue && (raRevue.fichier_joint || raRevue.contexte_objectif);

  // ---- VUE RA / CI : 2 zones distinctes ----
  if(IS_RA || IS_CI){
    renderTabsRA(members, raId, raRevue, raHasSoumis);
    return;
  }

  // ---- VUE INSPECTEUR : son propre onglet uniquement ----
  var myM = members.find(function(m){ return parseInt(m.idinspecteur)===MY_INSP_ID; });
  if(!myM){
    $('#revueZone').html('<div class="alert alert-warning mt-2"><i class="bi bi-exclamation-triangle me-1"></i>Vous ne figurez pas dans l\'equipe de cet audit.</div>').show();
    return;
  }
  var myRev = REVUES[MY_INSP_ID] || null;

  // Si le RA a soumis : afficher sa revue en lecture seule a l'inspecteur
  if(raHasSoumis){
    var raM = members.find(function(m){ return parseInt(m.idinspecteur)===raId; }) || {idinspecteur:raId, nom:'Responsable d\'Audit', est_responsable:1, nomdomaine:''};
    $('#inspTabs').html('');
    $('#tabContent').html(
      '<div class="alert alert-info mb-3"><i class="bi bi-lock me-2"></i>Le Responsable d\'Audit a soumis la revue de cet audit. Elle s\'applique a tous les membres de l\'equipe en lecture seule.</div>'
      + buildPane(raM, raRevue, false, true)
    );
    $('#revueZone').show();
    initAllQuills([raM]);
    return;
  }

  // Sinon : l'inspecteur saisit sa propre fiche (canEdit=true)
  $('#inspTabs').html('');
  $('#tabContent').html('<div>'+buildPane(myM, myRev, true, false)+'</div>');
  $('#revueZone').show();
  initAllQuills([myM]);
}

/* ======= VUE RA : fiche commune + fiches individuelles ======= */
function renderTabsRA(members, raId, raRevue, raHasSoumis){
  var raM = raId ? (members.find(function(m){ return parseInt(m.idinspecteur)===raId; }) || {idinspecteur:raId, nom:'Responsable d\'Audit', est_responsable:1, nomdomaine:''}) : null;

  // ZONE 1 : Fiche RA commune (haut de page)
  var zone1 = '<div class="rev-card mb-3" style="border:2px solid #23408F">'
    +'<div class="sec-title" style="color:#23408F"><i class="bi bi-person-badge me-2"></i>Revue documentaire commune (Responsable d\'Audit)'
    +(raHasSoumis ? '<span class="cons-badge ms-2">Soumise - s\'applique a tous</span>' : '<span style="background:#F3C300;color:#2C3E50;font-size:.72rem;font-weight:700;padding:.18rem .5rem;border-radius:20px;margin-left:8px">Non soumise</span>')
    +'</div>';
  if(!raHasSoumis){
    zone1 += '<div class="alert alert-light border py-2 small mb-3"><i class="bi bi-info-circle me-1 text-primary"></i>Quand vous enregistrez ou joignez un PDF ici, cette revue s\'applique automatiquement a tous les inspecteurs en lecture seule.</div>';
  }
  if(raM){
    zone1 += buildPane(raM, raRevue, true, false);
  }
  zone1 += '</div>';

  // ZONE 2 : Fiches individuelles des inspecteurs (onglets)
  var others = members.filter(function(m){ return parseInt(m.idinspecteur)!==raId; });
  var zone2 = '';
  if(others.length){
    zone2 += '<div class="rev-card">'
      +'<div class="sec-title"><i class="bi bi-people me-2"></i>Fiches individuelles des inspecteurs</div>'
      +'<div class="small text-muted mb-2">Cliquez sur un inspecteur pour consulter ou modifier sa fiche.</div>'
      +'<div id="inspTabs2" class="d-flex flex-wrap mb-0"></div>'
      +'<div id="tabContent2"></div>'
      +'</div>';
  }

  $('#inspTabs').html('');
  $('#tabContent').html(zone1 + zone2);
  $('#revueZone').show();

  // Init Quill pour la zone RA
  if(raM) initAllQuills([raM]);

  // Init onglets inspecteurs
  if(others.length){
    var tabs2='', content2='';
    others.forEach(function(m,i){
      var iid=parseInt(m.idinspecteur);
      var rev=REVUES[iid]||null;
      var hasSaisi=rev&&(rev.fichier_joint||rev.contexte_objectif);
      var badge=hasSaisi?'<span style="background:#1E9C4B;color:#fff;font-size:.68rem;font-weight:700;padding:.1rem .4rem;border-radius:10px;margin-left:5px">Saisi</span>':'';
      tabs2+='<button class="insp-tab-btn'+(i===0?' active':'')+'" data-idx2="'+i+'" onclick="switchTab2('+i+')">'+esc(m.nom)+badge+'</button>';
      content2+='<div class="tab-pane-custom'+(i===0?' active':'')+'" id="tp2_'+i+'">'+buildPane(m,rev,true,false)+'</div>';
    });
    $('#inspTabs2').html(tabs2);
    $('#tabContent2').html(content2);
    initAllQuills(others);
  }

  renderConsolidateZone(true);
}

function switchTab(idx){
  $('.insp-tab-btn').removeClass('active'); $('[data-idx="'+idx+'"]').addClass('active');
  $('.tab-pane-custom').removeClass('active'); $('#tp_'+idx).addClass('active');
}
function switchTab2(idx){
  $('[data-idx2]').removeClass('active'); $('[data-idx2="'+idx+'"]').addClass('active');
  $('#tabContent2 .tab-pane-custom').removeClass('active'); $('#tp2_'+idx).addClass('active');
}

/* ======= CONSTRUCTION D'UN PANNEAU ======= */
function buildPane(m, rev, canEdit, forceReadOnly){
  var iid=parseInt(m.idinspecteur);
  var cons = rev && Number(rev.est_consolide)===1;
  var locked = forceReadOnly || cons;
  var effectiveCanEdit = canEdit && !locked;
  var hasPdf = !!(rev && rev.fichier_joint);
  var html='';

  if(locked && !forceReadOnly){
    html+='<div class="alert alert-success py-2 small"><i class="bi bi-check-circle me-1"></i>Revue consolidee.</div>';
  }

  if(!locked && effectiveCanEdit){
    var modeText = hasPdf ? '' : 'checked';
    var modePdf  = hasPdf ? 'checked' : '';
    html+='<div class="rev-card mb-3"><div class="sec-title">Mode de saisie</div>'
      +'<div class="mode-choice">'
      +'<label><input type="radio" name="mode_'+iid+'" value="texte" class="mode-radio" data-insp="'+iid+'" '+modeText+'>'
      +'<span><i class="bi bi-pencil-square me-1"></i>Saisir les 6 sections</span></label>'
      +'<label><input type="radio" name="mode_'+iid+'" value="pdf" class="mode-radio" data-insp="'+iid+'" '+modePdf+'>'
      +'<span><i class="bi bi-file-pdf me-1"></i>Joindre un PDF</span></label>'
      +'</div></div>';
  }

  // ZONE TEXTE
  var zoneTextStyle = (hasPdf && !locked) ? ' style="display:none"' : '';
  html+='<div class="zone-texte-'+iid+'"'+zoneTextStyle+'>';
  SECTS.forEach(function(s){
    var val = rev ? (rev[s.k]||'') : '';
    html+='<div class="form-section">'
      +'<div class="d-flex align-items-center mb-2"><span class="fs-num">'+s.n+'</span><span class="fs-title">'+s.t+'</span></div>'
      +'<div id="q_'+iid+'_'+s.k+'" class="quill-box"></div>'
      +'<input type="hidden" id="qv_'+iid+'_'+s.k+'" value="'+esc(val)+'">'
      +'</div>';
  });
  if(effectiveCanEdit){
    html+='<div class="mt-2"><button class="btn btn-anac btn-save-revue" data-insp="'+iid+'"><i class="bi bi-save me-1"></i>Enregistrer</button></div>';
  }
  html+='</div>';

  // ZONE PDF
  var zonePdfStyle = (!hasPdf && !locked) ? ' style="display:none"' : (locked && !hasPdf ? ' style="display:none"' : '');
  html+='<div class="zone-pdf-'+iid+'"'+zonePdfStyle+'>';
  if(hasPdf){
    html+='<div class="alert alert-info d-flex align-items-center gap-2">'
      +'<i class="bi bi-file-pdf fs-5 text-danger"></i>'
      +'<div style="flex:1">PDF joint.</div>'
      +'<a href="'+AGAI_BASE+'/api/revue?serve=1&idaudit='+IDAUDIT+'&idinsp='+iid+'" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>Voir</a>';
    if(effectiveCanEdit){
      html+='<button class="btn btn-sm btn-outline-danger btn-del-pdf ms-1" data-insp="'+iid+'"><i class="bi bi-x me-1"></i>Retirer</button>';
    }
    html+='</div>';
  } else if(effectiveCanEdit){
    html+='<div class="rev-card">'
      +'<label class="form-label fw-bold">Joindre le PDF de la revue</label>'
      +'<input type="file" class="form-control file-revue" data-insp="'+iid+'" accept=".pdf">'
      +'<div class="form-text mt-1">PDF uniquement, 10 Mo maximum.</div>'
      +'</div>';
  }
  html+='</div>';

  return html;
}

/* ======= QUILL ======= */
var TOOLBAR=[[{header:[1,2,3,false]}],['bold','italic','underline','strike'],
  [{list:'ordered'},{list:'bullet'}],[{color:[]},{background:[]}],['clean']];

function initAllQuills(members){
  // Ne pas reinitialiser les Quill deja crees
  members.forEach(function(m){
    var iid=parseInt(m.idinspecteur);
    var rev=REVUES[iid]||null;
    var cons=rev&&Number(rev.est_consolide)===1;
    // Est-ce que cet utilisateur peut editer cette fiche ?
    var canEdit = IS_CI || IS_RA || (MY_INSP_ID && iid===MY_INSP_ID);
    if(!QUILLS[iid]) QUILLS[iid]={};
    SECTS.forEach(function(s){
      var el=document.getElementById('q_'+iid+'_'+s.k);
      if(!el) return;
      // Ne pas recreer si deja initialise
      if(el.__quill){ QUILLS[iid][s.k]=el.__quill; return; }
      var readOnly = cons || !canEdit;
      var q=new Quill(el,{theme:'snow',modules:{toolbar:readOnly?false:TOOLBAR},readOnly:readOnly});
      var raw=document.getElementById('qv_'+iid+'_'+s.k);
      var val=raw?raw.value:'';
      if(val){
        if(val.trim().charAt(0)==='<'){ q.root.innerHTML=val; }
        else { q.setText(val); }
      }
      el.__quill=q;
      QUILLS[iid][s.k]=q;
    });
  });
}

/* ======= EVENEMENTS ======= */

// Basculer mode saisie / PDF
$(document).on('change','.mode-radio',function(){
  var iid=$(this).data('insp');
  if($(this).val()==='texte'){ $('.zone-texte-'+iid).show(); $('.zone-pdf-'+iid).hide(); }
  else { $('.zone-texte-'+iid).hide(); $('.zone-pdf-'+iid).show(); }
});

// Le RA consulte le PDF joint par un inspecteur
$(document).on('click','.btn-view-pdf-ra',function(){
  var iid=$(this).data('insp');
  var url=AGAI_BASE+'/api/revue?serve=1&idaudit='+IDAUDIT+'&idinsp='+iid;
  var w=window.open(url,'_blank','width=900,height=700');
  if(!w){ Swal.fire({icon:'info',title:'Autoriser les popups',text:'Autorisez les popups pour afficher le PDF.',confirmButtonColor:'#23408F'}); }
});

// Retirer PDF
$(document).on('click','.btn-del-pdf',function(){
  var iid=$(this).data('insp');
  Swal.fire({icon:'question',title:'Retirer le PDF ?',text:'La fiche de revue reviendra en mode saisie texte.',showCancelButton:true,confirmButtonText:'Retirer',cancelButtonText:'Annuler',confirmButtonColor:'#D32F2F'}).then(function(r){
    if(!r.isConfirmed) return;
    apiPost({action:'del_pdf',idaudit:IDAUDIT,idinspecteur:iid}).done(function(res){
      if(res.success){ Swal.fire({icon:'success',timer:1200,showConfirmButton:false}); refreshRevues(); }
      else { Swal.fire({icon:'error',text:res.message,confirmButtonColor:'#23408F'}); }
    });
  });
});

// Enregistrer revue texte
$(document).on('click','.btn-save-revue',function(){
  var iid=$(this).data('insp');
  if(!QUILLS[iid]){ Swal.fire({icon:'warning',title:'Editeur non pret'}); return; }
  var data={action:'save_revue',idaudit:IDAUDIT,idinspecteur:iid};
  SECTS.forEach(function(s){
    data[s.k]=QUILLS[iid][s.k]?QUILLS[iid][s.k].root.innerHTML:'';
  });
  var btn=$(this), h=btn.html();
  btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
  apiPost(data).done(function(res){
    btn.prop('disabled',false).html(h);
    if(res.success){
      Swal.fire({icon:'success',title:'Revue enregistree',text:'La revue a bien ete sauvegardee.',confirmButtonColor:'#23408F',confirmButtonText:'OK'}).then(function(){
        refreshRevues();
      });
    } else { Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'}); }
  }).fail(function(){ btn.prop('disabled',false).html(h); Swal.fire({icon:'error',text:'Echec de la requete.',confirmButtonColor:'#23408F'}); });
});

// Upload PDF
$(document).on('change','.file-revue',function(){
  var iid=$(this).data('insp'), file=this.files[0]; if(!file) return;
  var fd=new FormData();
  fd.append('fichier',file); fd.append('action','upload_revue');
  fd.append('idaudit',IDAUDIT); fd.append('idinspecteur',iid);
  Swal.fire({title:'Envoi en cours...',allowOutsideClick:false,didOpen:function(){Swal.showLoading();}});
  postFile(fd).done(function(res){
    Swal.close();
    if(res.success){ Swal.fire({icon:'success',title:'PDF enregistre',timer:1400,showConfirmButton:false}); refreshRevues(); }
    else { Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'}); }
  }).fail(function(){ Swal.close(); Swal.fire({icon:'error',text:'Echec de l\'envoi.',confirmButtonColor:'#23408F'}); });
});

/* ======= RECHARGEMENT DES REVUES ======= */
function refreshRevues(){
  apiPost({action:'list_revues',idaudit:IDAUDIT}).done(function(res){
    REVUES={};
    (res.data||[]).forEach(function(r){ REVUES[parseInt(r.idinspecteur)]=r; });
    // Conserver l'onglet actif
    var activeIdx=$('.insp-tab-btn.active').data('idx')||0;
    renderTabs();
    switchTab(activeIdx);
  });
}

/* ======= CONSOLIDATION ======= */
function renderConsolidateZone(canSeeAll){
  if(!canSeeAll){ $('#consolidateZone').hide(); return; }
  var total=Object.keys(REVUES).length;
  var cons=Object.values(REVUES).filter(function(r){return Number(r.est_consolide)===1;}).length;
  var seen={}, nbEq=0;
  EQUIPE.forEach(function(m){ if(!seen[m.idinspecteur]){seen[m.idinspecteur]=true;nbEq++;} });
  $('#consolidateZone').html(
    '<div class="rev-card">'
    +'<div class="sec-title">Consolidation</div>'
    +'<p class="text-muted small mb-2">'+total+' revue(s) saisie(s) sur '+nbEq+' inspecteur(s) dans l\'equipe. '+cons+' consolide(s).</p>'
    +(total<nbEq?'<div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle me-1"></i>Tous les inspecteurs n\'ont pas encore saisi leur revue.</div>':'')
    +'<button class="btn btn-success" id="btnConsolidateAll"><i class="bi bi-check-all me-1"></i>Consolider toutes les revues</button>'
    +'</div>'
  ).show();
  $('#btnConsolidateAll').off('click').on('click',function(){
    var pending=Object.values(REVUES).filter(function(r){return Number(r.est_consolide)===0;});
    if(!pending.length){ Swal.fire({icon:'info',title:'Deja consolide',confirmButtonColor:'#23408F'}); return; }
    Swal.fire({icon:'question',title:'Consolider ?',html:'<b>'+pending.length+'</b> revue(s) seront consolidees. Action definitive.',showCancelButton:true,confirmButtonText:'Consolider',cancelButtonText:'Annuler',confirmButtonColor:'#1E9C4B'}).then(function(r){
      if(!r.isConfirmed) return;
      var done=0;
      pending.forEach(function(rev){
        apiPost({action:'consolider',idaudit:IDAUDIT,idinspecteur:rev.idinspecteur}).always(function(){
          if(++done===pending.length){ Swal.fire({icon:'success',title:'Consolide',timer:1400,showConfirmButton:false}); refreshRevues(); }
        });
      });
    });
  });
}

/* ======= IMPRESSION ANAC ======= */
$('#btnPrint').on('click',function(){
  if(!AUDIT){ Swal.fire({icon:'info',title:'Donnees en cours de chargement...'}); return; }
  var a=AUDIT;
  var revList=Object.values(REVUES);
  var rev=revList.find(function(r){return Number(r.est_consolide)===1;})||revList[0]||{};
  var seen={}, teamRows='';
  EQUIPE.forEach(function(m){
    if(seen[m.idinspecteur]) return; seen[m.idinspecteur]=true;
    var isRA=Number(m.est_responsable)===1;
    teamRows+='<tr><td style="'+(isRA?'color:#D32F2F;font-weight:700':'')+'">'+esc(m.nom)+(isRA?' (R.A)':'')+'</td><td>'+esc(m.nomdomaine)+'</td></tr>';
  });
  var sectHtml=SECTS.map(function(s){
    return '<div style="margin-bottom:14px;page-break-inside:avoid">'
      +'<p style="font-weight:700;font-size:10pt;margin:0 0 4px;color:#23408F">'+s.n+'. '+s.t+'</p>'
      +'<div style="min-height:44px;border:1px solid #bbb;border-radius:3px;padding:8px;font-size:9.5pt">'
      +(rev[s.k]||'&nbsp;')+'</div></div>';
  }).join('');
  var imgBase = IMG_BASE;
  var html='<!DOCTYPE html><html><head><meta charset="UTF-8"><title>IX-GEN-R3-F-I-017</title>'
    +'<style>'
    +'*{box-sizing:border-box;margin:0;padding:0}'
    +'body{font-family:Candara,Arial,sans-serif;font-size:10pt;color:#1a1a1a;background:#fff}'
    +'@page{margin:15mm 12mm 40mm 12mm;border:3px solid #23408F}'
    +'.page{padding:10px;border:3px solid #23408F;min-height:270mm}'
    +'.ref-line{text-align:right;font-size:8pt;color:#555;margin-bottom:6px}'
    +'.hdr{display:flex;justify-content:space-between;align-items:center;border-bottom:3px solid #23408F;padding-bottom:8px;margin-bottom:10px}'
    +'.hdr-left{font-size:9pt;font-weight:700;color:#23408F;line-height:1.4}'
    +'.hdr-center img{height:60px}'
    +'.hdr-right{font-size:8.5pt;text-align:right;color:#2C3E50;line-height:1.4}'
    +'h1{text-align:center;font-size:13pt;font-weight:700;text-transform:uppercase;color:#23408F;margin:0 0 10px;letter-spacing:.03em}'
    +'.meta{background:#f0f4fb;border:1px solid #c5d0e6;border-radius:4px;padding:8px 12px;margin-bottom:10px;font-size:9pt}'
    +'.meta div{margin-bottom:3px}.meta span{font-weight:700;color:#23408F}'
    +'.team-tbl{width:100%;border-collapse:collapse;font-size:9pt;margin-top:4px}'
    +'.team-tbl th{background:#23408F;color:#fff;padding:5px 8px;text-align:left}'
    +'.team-tbl td{padding:4px 8px;border-bottom:1px solid #dde}'
    +'.sign{display:flex;justify-content:space-between;margin-top:14px;padding-top:10px;border-top:1px solid #bbb;font-size:9pt}'
    +'.footer-img{position:fixed;bottom:0;left:0;right:0;text-align:center}'
    +'.footer-img img{width:100%;max-height:35mm}'
    +'@media print{body{margin:0}.page{border:3px solid #23408F}.footer-img{position:fixed;bottom:0}}'
    +'</style></head><body>'
    +'<div class="page">'
    +'<div class="ref-line">IX-GEN-R3-F-I-017 &nbsp;&nbsp; Version 02</div>'
    +'<div class="hdr">'
    +'<div class="hdr-left">AGENCE NATIONALE<br>DE L\'AVIATION CIVILE<br><span style="font-size:8pt;color:#555">ANAC GABON</span></div>'
    +'<div class="hdr-center"><img src="'+imgBase+'banierenteanac.png" onerror="this.style.display=\'none\'"></div>'
    +'<div class="hdr-right"><b>REPUBLIQUE GABONAISE</b><br>UNION - TRAVAIL - JUSTICE</div>'
    +'</div>'
    +'<h1>Formulaire de Revue Documentaire</h1>'
    +'<div class="meta">'
    +'<div><span>Reference :</span> '+esc(a.num_audit)+'</div>'
    +'<div><span>Nature :</span> '+esc(TYPE_LABELS[a.type_activite]||a.type_activite)
    +' &nbsp;|&nbsp; <span>Cadre :</span> '+esc(a.cadre||'-')+'</div>'
    +'<div><span>Operateur :</span> '+esc(a.operateur||'-')
    +' &nbsp;|&nbsp; <span>Site :</span> '+esc(a.site_inspection||'-')+'</div>'
    +'<div><span>Date previsionnelle :</span> '+fmtDate(a.date_previsionnelle)+'</div>'
    +'<div style="margin-top:6px"><span>Equipe :</span>'
    +'<table class="team-tbl" style="margin-top:4px"><thead><tr><th>Inspecteur</th><th>Domaine</th></tr></thead><tbody>'+teamRows+'</tbody></table>'
    +'</div></div>'
    +sectHtml
    +'<div class="sign">'
    +'<div><b>Signature du Chef d\'equipe</b><br><br><br>________________________</div>'
    +'<div style="text-align:right"><b>Visa des membres de l\'equipe</b><br><br><br>________________________</div>'
    +'</div>'
    +'</div>'
    +'<div class="footer-img"><img src="'+imgBase+'pied_page_anac.jpg" onerror="this.style.display=\'none\'"></div>'
    +'</body></html>';
  var w=window.open('','_blank','width=900,height=700');
  w.document.write(html);
  w.document.close();
  w.onload=function(){ setTimeout(function(){ w.print(); },300); };
});
</script>