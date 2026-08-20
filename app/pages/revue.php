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
$active    = 'mes_audits';   // entree de menu "Revue documentaire\"
require_once INCLUDES_PATH . '/layout_head.php';
?>
<?php require_once INCLUDES_PATH . '/qrcode_inline.php'; ?>
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

/* Panneau d'accueil "Traiter la revue" */
.rev-welcome{display:flex;align-items:center;gap:16px;background:#fff;border:1px dashed #c5d0e6;border-radius:14px;padding:18px 20px;margin-bottom:14px;}
.rev-welcome-ic{width:52px;height:52px;flex:0 0 52px;border-radius:12px;background:linear-gradient(135deg,#23408F,#1b3576);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.5rem;}
.rev-welcome-txt{flex:1 1 auto;}
.rev-welcome-t{font-weight:800;color:#2C3E50;font-size:1rem;}
.rev-welcome-s{font-size:.83rem;color:#6b7a90;margin-top:2px;}

/* Cartes de choix dans la modale */
.mode-card{background:#fff;border:2px solid #e6ebf3;border-radius:14px;padding:20px 18px;height:100%;cursor:pointer;transition:.16s;text-align:center;}
.mode-card:hover{border-color:#23408F;transform:translateY(-3px);box-shadow:0 10px 24px rgba(35,64,143,.14);}
.mode-card-ic{width:58px;height:58px;margin:0 auto 12px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.7rem;color:#fff;}
.mc-blue{background:linear-gradient(135deg,#23408F,#1b3576);}
.mc-red{background:linear-gradient(135deg,#D32F2F,#b02525);}
.mode-card-t{font-weight:800;color:#2C3E50;font-size:1.05rem;margin-bottom:6px;}
.mode-card-s{font-size:.82rem;color:#6b7a90;line-height:1.45;min-height:60px;}
.mode-card-go{margin-top:12px;font-weight:700;font-size:.85rem;color:#23408F;}
.mode-card:hover .mode-card-go{text-decoration:underline;}

/* Barre de bascule saisie / PDF */
.mode-switch{display:flex;align-items:center;gap:8px;flex-wrap:wrap;background:#f0f4fb;border:1px solid #d8e1f0;border-radius:10px;padding:7px 10px;}
.ms-btn{border:1px solid #c5d0e6;background:#fff;color:#5b6b85;border-radius:8px;padding:5px 12px;font-size:.82rem;font-weight:600;transition:.15s;}
.ms-btn.on{background:#23408F;color:#fff;border-color:#23408F;}
.ms-btn:hover{border-color:#23408F;}
.ms-hint{font-size:.72rem;color:#8a97ad;margin-left:auto;}
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

<!-- MODALE : consultation du PDF joint -->
<div class="modal fade" id="modalPdfView" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content" style="border:none;border-radius:14px;overflow:hidden">
      <div class="modal-header" style="background:linear-gradient(135deg,#23408F,#1b3576);border:none">
        <h5 class="modal-title text-white"><i class="bi bi-file-earmark-pdf me-2" style="color:#F3C300"></i>Revue documentaire (PDF joint)</h5>
        <div class="ms-auto d-flex gap-2 align-items-center">
          <a href="#" id="pdfViewDl" target="_blank" class="btn btn-sm btn-light"><i class="bi bi-box-arrow-up-right me-1"></i>Ouvrir dans un onglet</a>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
      </div>
      <div class="modal-body" style="padding:0;background:#525659">
        <iframe id="pdfViewFrame" src="" style="width:100%;height:78vh;border:none"></iframe>
      </div>
    </div>
  </div>
</div>

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
    // Impression directe si on arrive avec ?print=1 (depuis le tableau Mes audits)
    var params=new URLSearchParams(window.location.search);
    if(params.get('print')==='1'){
      setTimeout(function(){ $('#btnPrint').trigger('click'); }, 700);
    }
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

  // Le bouton "Imprimer" (impression du formulaire des 6 rubriques) n'a de sens
  // qu'en mode saisie. Si l'equipe est en mode PDF joint, on le masque : le PDF
  // se consulte via les boutons "Consulter le PDF".
  var modePdf = Object.values(REVUES).some(function(r){ return r && r.fichier_joint; });
  if(modePdf){ $('#btnPrint').hide(); } else { $('#btnPrint').show(); }

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
    renderPdfConsultInsp();
    return;
  }

  // Sinon : l'inspecteur saisit sa propre fiche (canEdit=true)
  $('#inspTabs').html('');
  $('#tabContent').html('<div>'+buildPane(myM, myRev, true, false)+'</div>');
  $('#revueZone').show();
  initAllQuills([myM]);
  renderPdfConsultInsp();
}

/* Liste des PDF joints par l'equipe, en consultation, pour un inspecteur simple.
   Affichee sous sa fiche (meme information que la zone de consolidation du RA). */
function renderPdfConsultInsp(){
  var seen={}, nomsEq={};
  EQUIPE.forEach(function(m){ nomsEq[parseInt(m.idinspecteur)]={nom:m.nom, ra:Number(m.est_responsable)===1}; });
  var list='';
  Object.values(REVUES).forEach(function(r){
    if(r.fichier_joint){
      var iid=parseInt(r.idinspecteur);
      var info=nomsEq[iid]||{nom:('Inspecteur #'+iid), ra:false};
      list+='<div class="d-flex align-items-center gap-2 p-2 mb-1" style="background:#f5f7fa;border:1px solid #eef1f6;border-radius:8px">'
        +'<i class="bi bi-file-earmark-pdf text-danger"></i>'
        +'<div style="flex:1;font-size:.84rem">'+esc(info.nom)
        +(info.ra?' <span style="background:#D32F2F;color:#fff;font-size:.62rem;font-weight:700;padding:.05rem .35rem;border-radius:8px">RA</span>':'')
        +(Number(r.est_consolide)===1?' <span style="background:#1E9C4B;color:#fff;font-size:.62rem;font-weight:700;padding:.05rem .35rem;border-radius:8px">Final</span>':'')
        +'</div>'
        +'<button type="button" class="btn btn-sm btn-outline-primary btn-view-pdf" data-insp="'+iid+'"><i class="bi bi-eye me-1"></i>Consulter</button>'
        +'</div>';
    }
  });
  if(list){
    $('#consolidateZone').html(
      '<div class="rev-card">'
      +'<div class="sec-title"><i class="bi bi-collection me-2"></i>Documents PDF joints par l\'equipe</div>'
      +'<p class="text-muted small mb-2">Consultez les revues jointes par les autres membres de l\'equipe.</p>'
      +list
      +'</div>'
    ).show();
  } else {
    $('#consolidateZone').hide();
  }
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
      +'<div class="small text-muted mb-2">Cliquez sur un inspecteur pour consulter sa fiche.</div>'
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
      // Fiche d'un autre inspecteur : consultation seule, meme pour le RA ou le CI
      var estMoi = (MY_INSP_ID && iid === MY_INSP_ID);
      content2+='<div class="tab-pane-custom'+(i===0?' active':'')+'" id="tp2_'+i+'">'+buildPane(m,rev,estMoi,!estMoi)+'</div>';
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
  var hasTexte = !!(rev && SECTS.some(function(s){ return (rev[s.k]||'').replace(/<[^>]*>/g,'').trim()!==''; }));
  var html='';

  if(locked && !forceReadOnly){
    html+='<div class="alert alert-success py-2 small"><i class="bi bi-check-circle me-1"></i>'
        + 'Revue complete et validee. Les boutons Enregistrer et Consolider sont desactives : '
        + 'le document ne peut plus etre modifie.</div>';
  }
  if(forceReadOnly && !cons){
    html+='<div class="alert alert-secondary py-2 small"><i class="bi bi-eye me-1"></i>'
        + 'Consultation. Seul l\'inspecteur auteur de cette fiche peut la modifier.</div>';
  }

  // Le choix du mode (saisie / PDF) se fait sur la page "Mes audits".
  // Ici, on affiche directement la zone correspondant a l'etat de la revue :
  // le formulaire des 6 rubriques par defaut, ou le PDF joint s'il existe.

  // ZONE TEXTE (affichee sauf si un PDF a ete joint)
  var showText = !hasPdf;
  var zoneTextStyle = showText ? '' : ' style="display:none"';
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

  // ZONE PDF (visible si PDF present, ou apres choix "pdf")
  var showPdf = (hasPdf) ? true : false;
  var zonePdfStyle = showPdf ? '' : ' style="display:none"';
  html+='<div class="zone-pdf-'+iid+'"'+zonePdfStyle+'>';
  if(hasPdf){
    html+='<div class="alert alert-info d-flex align-items-center gap-2">'
      +'<i class="bi bi-file-pdf fs-5 text-danger"></i>'
      +'<div style="flex:1">Revue jointe au format PDF.</div>'
      +'<button type="button" class="btn btn-sm btn-outline-primary btn-view-pdf" data-insp="'+iid+'"><i class="bi bi-eye me-1"></i>Consulter le PDF</button>';
    if(effectiveCanEdit){
      html+='<button class="btn btn-sm btn-outline-danger btn-del-pdf ms-1" data-insp="'+iid+'"><i class="bi bi-x me-1"></i>Retirer</button>';
    }
    html+='</div>';
  } else if(effectiveCanEdit){
    html+='<div class="rev-card">'
      +'<label class="form-label fw-bold"><i class="bi bi-file-earmark-arrow-up me-1"></i>Joindre le PDF de la revue</label>'
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
    // Chaque inspecteur ne modifie QUE sa propre fiche.
    // Le responsable d'audit et le chef inspecteur consultent celles des autres :
    // une revue engage son auteur, elle ne doit pas etre modifiable par un tiers.
    var canEdit = (MY_INSP_ID && iid===MY_INSP_ID);
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

// Consulter le PDF joint dans une modale (tous les membres de l'equipe)
$(document).on('click','.btn-view-pdf, .btn-view-pdf-ra',function(){
  var iid=$(this).data('insp');
  var url=AGAI_BASE+'/api/revue?serve=1&idaudit='+IDAUDIT+'&idinsp='+iid;
  $('#pdfViewFrame').attr('src', url);
  $('#pdfViewDl').attr('href', url);
  new bootstrap.Modal('#modalPdfView').show();
});
// Liberer l'iframe a la fermeture (economie memoire)
$(document).on('hidden.bs.modal','#modalPdfView',function(){ $('#pdfViewFrame').attr('src',''); });

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
      // Un inspecteur simple est redirige vers son tableau apres enregistrement.
      // Le RA / CI reste sur la page (pour consulter les autres revues, consolider).
      if(!IS_RA && !IS_CI){
        Swal.fire({icon:'success',title:'Revue enregistree',text:'Votre revue a bien ete sauvegardee.',confirmButtonColor:'#23408F',confirmButtonText:'Retour a mes audits'}).then(function(){
          window.location.href = AGAI_BASE + '/mes-audits';
        });
      } else {
        Swal.fire({icon:'success',title:'Revue enregistree',text:'La revue a bien ete sauvegardee.',confirmButtonColor:'#23408F',confirmButtonText:'OK'}).then(function(){
          refreshRevues();
        });
      }
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
  var toutConsolide = (total > 0 && cons === total);

  // Liste des PDF joints par les inspecteurs (consultation dans la consolidation)
  var pdfList='';
  var nomsEq={};
  EQUIPE.forEach(function(m){ nomsEq[parseInt(m.idinspecteur)]={nom:m.nom, ra:Number(m.est_responsable)===1}; });
  Object.values(REVUES).forEach(function(r){
    if(r.fichier_joint){
      var iid=parseInt(r.idinspecteur);
      var info=nomsEq[iid]||{nom:('Inspecteur #'+iid), ra:false};
      pdfList+='<div class="d-flex align-items-center gap-2 p-2 mb-1" style="background:#f5f7fa;border:1px solid #eef1f6;border-radius:8px">'
        +'<i class="bi bi-file-earmark-pdf text-danger"></i>'
        +'<div style="flex:1;font-size:.84rem">'+esc(info.nom)
        +(info.ra?' <span style="background:#D32F2F;color:#fff;font-size:.62rem;font-weight:700;padding:.05rem .35rem;border-radius:8px">RA</span>':'')
        +(Number(r.est_consolide)===1?' <span style="background:#1E9C4B;color:#fff;font-size:.62rem;font-weight:700;padding:.05rem .35rem;border-radius:8px">Final</span>':'')
        +'</div>'
        +'<button type="button" class="btn btn-sm btn-outline-primary btn-view-pdf" data-insp="'+iid+'"><i class="bi bi-eye me-1"></i>Consulter</button>'
        +'</div>';
    }
  });
  var pdfBloc = pdfList
    ? '<div class="mt-2 mb-2"><div style="font-size:.75rem;font-weight:700;color:#23408F;text-transform:uppercase;letter-spacing:.03em;margin-bottom:6px"><i class="bi bi-collection me-1"></i>Documents PDF joints par l\'equipe</div>'+pdfList+'</div>'
    : '';

  $('#consolidateZone').html(
    '<div class="rev-card">'
    +'<div class="sec-title">Consolidation</div>'
    +'<p class="text-muted small mb-2">'+total+' revue(s) saisie(s) sur '+nbEq+' inspecteur(s) dans l\'equipe. '+cons+' consolide(s).</p>'
    +pdfBloc
    +(total<nbEq?'<div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle me-1"></i>Tous les inspecteurs n\'ont pas encore saisi leur revue.</div>':'')
    // Toutes les revues deja consolidees : plus rien a consolider, le bouton est desactive
    +(toutConsolide
      ? '<div class="alert alert-success py-2 small mb-2"><i class="bi bi-lock me-1"></i>Toutes les revues sont consolidees. Le dossier est clos.</div>'
        +'<button class="btn btn-success" id="btnConsolidateAll" disabled><i class="bi bi-check-all me-1"></i>Revues consolidees</button>'
      : '<button class="btn btn-success" id="btnConsolidateAll"><i class="bi bi-check-all me-1"></i>Consolider toutes les revues</button>')
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

  // Numero de la revue documentaire : idrevue / annee / IX-GEN.
  // L'annee vient de la date de consolidation si presente, sinon l'annee courante.
  var numRevue=(function(){
    if(!rev || !rev.idrevue) return '';
    var annee='';
    if(rev.date_consolidation){ annee=String(rev.date_consolidation).substring(0,4); }
    if(!annee || annee==='0000'){ annee=String(new Date().getFullYear()); }
    return rev.idrevue+'/'+annee+'/IX-GEN';
  })();

  // ----- QR code d'authentification de la revue documentaire -----
  var qrRevue=(function(){
    var lignes=[
      'ANAC GABON - AGAI (Systeme securise)',
      'FORMULAIRE DE REVUE DOCUMENTAIRE',
      'Reference : IX-GEN-R3-F-I-017',
      'N Audit : '+(a.num_audit||'-'),
      'Nature : '+(TYPE_LABELS[a.type_activite]||a.type_activite||'-'),
      'Operateur : '+(a.operateur||'-'),
      'Site : '+(a.site_inspection||'-'),
      'RA : '+(a.ra_nom||'-'),
      'Statut : '+(Number(rev.est_consolide)===1?'Consolidee (RA)':'En cours'),
      'Document authentifie AGAI'
    ];
    var sansAccents=function(s){
      return String(s).normalize('NFD').replace(/[\u0300-\u036f]/g,'')
        .replace(/[^\x20-\x7E]/g,' ').replace(/\s+/g,' ').trim();
    };
    var texte=lignes.map(sansAccents).join('\n');
    var QR=window.QRCode||(typeof QRCode!=='undefined'?QRCode:null);
    if(!QR) return '';
    var niveau=(QR.CorrectLevel&&QR.CorrectLevel.L!=null)?QR.CorrectLevel.L:1;
    var box=document.createElement('div');
    for(var tn=1; tn<=40; tn++){
      try{
        box.innerHTML='';
        new QR(box,{text:texte,typeNumber:tn,width:110,height:110,colorDark:'#000000',colorLight:'#ffffff',correctLevel:niveau});
        var img=box.querySelector('img'), cv=box.querySelector('canvas');
        if(img&&img.src) return img.src;
        if(cv) return cv.toDataURL('image/png');
      }catch(e){}
    }
    return '';
  })();

  var seen={}, teamRows='';
  var raNom='', membresNoms=[];
  EQUIPE.forEach(function(m){
    if(seen[m.idinspecteur]) return; seen[m.idinspecteur]=true;
    var isRA=Number(m.est_responsable)===1;
    teamRows+='<tr><td style="'+(isRA?'color:#D32F2F;font-weight:700':'')+'">'+esc(m.nom)+(isRA?' (R.A)':'')+'</td><td>'+esc(m.nomdomaine)+'</td></tr>';
    if(isRA){ raNom=m.nom; } else { membresNoms.push(m.nom); }
  });
  if(!raNom && a.ra_nom){ raNom=a.ra_nom; }
  var sectHtml=SECTS.map(function(s){
    return '<div style="margin-bottom:14px;page-break-inside:avoid">'
      +'<p style="font-weight:700;font-size:10pt;margin:0 0 4px;color:#23408F">'+s.n+'. '+s.t+'</p>'
      +'<div style="min-height:44px;border:1px solid #bbb;border-radius:3px;padding:8px;font-size:9.5pt">'
      +(rev[s.k]||'&nbsp;')+'</div></div>';
  }).join('');
  var imgBase = IMG_BASE;
  var titreDoc = numRevue ? ('Revue documentaire N ' + numRevue) : 'Formulaire de Revue Documentaire';
  var html='<!DOCTYPE html><html><head><meta charset="UTF-8"><title>'+esc(titreDoc)+'</title>'
    +'<style>'
    +'*{box-sizing:border-box;margin:0;padding:0}'
    +'body{font-family:Candara,Arial,sans-serif;font-size:10pt;color:#1a1a1a;background:#fff}'
    +'@page{size:A4;margin:12mm 12mm 32mm 12mm;'
    +'@bottom-center{content:"BP 2212 Libreville (GABON)  -  Tel.: (241) 01 44 54 00  -  Fax: (241) 01 44 54 01  -  E-mail : anac@anac-gabon.com  -  www.anacgabon.org";font-family:Candara,Arial,sans-serif;font-size:7.5pt;color:#23408F}'
    +'@bottom-right{content:"Page " counter(page) "/" counter(pages);font-family:Candara,Arial,sans-serif;font-size:7.5pt;color:#555}}'
    +'.doc-code{text-align:right;font-size:9pt;font-weight:700;color:#23408F;letter-spacing:.5px;margin:0 0 8px;padding-right:10px}'
    +'.page{padding:12px;border:3px solid #23408F;min-height:246mm}'
    +'.hdr{text-align:center;border-bottom:3px solid #23408F;padding-bottom:10px;margin-bottom:12px}'
    +'.hdr img{max-height:95px;width:auto;display:block;margin:0 auto}'
    +'h1{text-align:center;font-size:13pt;font-weight:700;text-transform:uppercase;color:#23408F;margin:0 0 10px;letter-spacing:.03em}'
    +'.meta{background:#f0f4fb;border:1px solid #c5d0e6;border-radius:4px;padding:8px 12px;margin-bottom:10px;font-size:9pt}'
    +'.meta div{margin-bottom:3px}.meta span{font-weight:700;color:#23408F}'
    +'.team-tbl{width:100%;border-collapse:collapse;font-size:9pt;margin-top:4px}'
    +'.team-tbl th{background:#23408F;color:#fff;padding:5px 8px;text-align:left}'
    +'.team-tbl td{padding:4px 8px;border-bottom:1px solid #dde}'
    +'.sign{display:flex;justify-content:space-between;gap:24px;margin-top:16px;padding-top:10px;border-top:1px solid #bbb;font-size:9pt}'
    +'.sign-col{flex:1}'
    +'.sign-name{margin-top:14px;padding:3px 0;border-bottom:1px dotted #999;font-size:8.5pt}'
    +'.qr-box{text-align:center;flex:0 0 auto}'
    +'@media print{body{margin:0}.page{border:3px solid #23408F}}'
    +'</style></head><body>'
    +'<div class="doc-code">IX-GEN-R3-F-I-017 &nbsp;&nbsp; Version 02</div>'
    +'<div class="page">'
    +'<div class="hdr"><img src="'+imgBase+'banierenteanac.png" onerror="this.style.display=\'none\'"></div>'
    +'<h1>Formulaire de Revue Documentaire</h1>'
    +'<div class="meta">'
    +(numRevue?'<div><span>Revue documentaire N :</span> '+esc(numRevue)+'</div>':'')
    +'<div><span>Reference audit :</span> '+esc(a.num_audit)+'</div>'
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
    +(qrRevue
        ? '<div class="qr-box"><img src="'+qrRevue+'" style="width:24mm;height:24mm" alt="QR"><div style="font-size:7pt;color:#64748b;margin-top:2px">Authentification AGAI</div></div>'
        : '')
    +'<div class="sign-col">'
    +'<b>Signature du Chef d\'equipe (R.A)</b>'
    +'<div class="sign-name">'+esc(raNom||'-')+'</div>'
    +'</div>'
    +'<div class="sign-col">'
    +'<b>Visa des membres de l\'equipe</b>'
    +(membresNoms.length
        ? membresNoms.map(function(n){ return '<div class="sign-name">'+esc(n)+'</div>'; }).join('')
        : '<div class="sign-name">________________________</div>')
    +'</div>'
    +'</div>'
    +'</div>'
    +'</body></html>';
  var w=window.open('','_blank','width=900,height=700');
  w.document.write(html);
  w.document.close();
  w.onload=function(){ setTimeout(function(){ w.print(); },300); };
});
</script>