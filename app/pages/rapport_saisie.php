<?php
/**
 * Rapport d'acte de supervision IX-GEN-R3-F-I-009 - Page de saisie
 * - Gabarit calque sur la revue documentaire (editeur riche Quill.js)
 * - En-tetes (nature/cadre) coches automatiquement depuis l'audit
 * - Chaque inspecteur ne saisit que le rapport d'un audit ou il est autorise
 * - Validation = le Chef Inspecteur (CI) de l'audit, en lecture seule
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardPage('rapports');

$idaudit = (int) ($_GET['audit'] ?? 0);
if ($idaudit <= 0) { header('Location: ' . SITE_URL . '/rapports'); exit; }

$csrf      = Security::generateCSRF();
$role      = Rbac::role();
$uid       = (int) ($_SESSION['user_id'] ?? 0);
$pageTitle = 'Rapport d\'acte de supervision';
$active    = 'rapports';
require_once INCLUDES_PATH . '/layout_head.php';
?>
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
<style>
.rap-head{background:linear-gradient(135deg,#23408F,#1b3576);color:#fff;border-radius:14px;padding:18px 22px;margin-bottom:14px;}
.rap-card{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:18px 20px;box-shadow:0 1px 2px rgba(16,30,54,.04);margin-bottom:14px;}
.rap-sec-title{font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;font-weight:700;color:#23408F;border-bottom:2px solid #23408F;padding-bottom:5px;margin:0 0 12px;display:flex;align-items:center;gap:6px;}
.rap-lbl{font-weight:600;color:#2C3E50;font-size:.84rem;margin-bottom:3px;}
.rap-checks{display:flex;flex-wrap:wrap;gap:8px 18px;}
.rap-check{font-size:.84rem;color:#6b7a90;display:inline-flex;align-items:center;gap:5px;}
.rap-check.on{color:#23408F;font-weight:700;}
.rap-check.on i{color:#1E9C4B;}
.rap-note{margin-top:10px;font-size:.78rem;color:#6b7a90;background:#f7f9fc;border-left:3px solid #23408F;padding:7px 11px;border-radius:6px;}
.ql-container.ql-snow{border-radius:0 0 8px 8px;min-height:120px;}
.ql-toolbar.ql-snow{border-radius:8px 8px 0 0;}
.quill-box{background:#fff;}
.dom-block .quill-box .ql-container{min-height:160px;}
.quill-lg .ql-container{min-height:200px;}
.bilan-recap{border:1px solid #e2e8f0;border-radius:10px;padding:10px 12px;background:#fcfdff;}
.bilan-dom{margin-bottom:8px;padding-bottom:8px;border-bottom:1px dashed #e2e8f0;}
.bilan-dom:last-child{border-bottom:none;margin-bottom:0;padding-bottom:0;}
.bilan-dom-h{font-weight:700;color:#23408F;font-size:.86rem;margin-bottom:2px;}
.nc-empty{background:#fff8e6;border:1px solid #f3e2a8;border-radius:10px;padding:12px 14px;font-size:.85rem;color:#8a6d00;}
.nc-dom{margin-bottom:14px;}
.nc-dom-h{font-weight:700;color:#23408F;font-size:.9rem;margin-bottom:6px;padding-bottom:3px;border-bottom:2px solid #eef3fb;}
.nc-table td,.nc-table th{font-size:.82rem;vertical-align:middle;}
.nc-table th{background:#eef3fb;color:#23408F;}
.nc-badge{color:#fff;font-size:.72rem;font-weight:700;padding:2px 8px;border-radius:20px;display:inline-block;}
.nc-synth-h,.nc-ant-h{font-weight:700;color:#23408F;font-size:.86rem;margin-bottom:8px;}
.nc-synth{display:flex;flex-wrap:wrap;gap:10px;}
.nc-synth-item{border:2px solid;border-radius:10px;padding:8px 16px;text-align:center;min-width:90px;background:#fff;}
.nc-synth-n{display:block;font-size:1.4rem;font-weight:800;}
.nc-synth-l{font-size:.78rem;color:#2C3E50;}
.resp-row{display:flex;gap:8px;margin-bottom:6px;align-items:center;}
.ampl-row{margin-bottom:6px;}
.ro-field{background:#eef3fb !important;}
.dom-block{border:1px solid #e2e8f0;border-radius:12px;padding:14px 16px;margin-bottom:14px;background:#fcfdff;}
.dom-head{display:flex;flex-wrap:wrap;align-items:center;gap:8px;padding-bottom:8px;border-bottom:1px dashed #dbe3f0;}
.dom-name{font-weight:800;color:#23408F;font-size:1rem;}
.dom-lib{color:#6b7a90;font-size:.85rem;}
.dom-insp{margin-left:auto;font-size:.8rem;color:#2C3E50;background:#eef3fb;padding:2px 10px;border-radius:20px;}
.crit-grid{display:flex;flex-direction:column;gap:6px;}
.crit-row{display:flex;align-items:center;gap:8px;}
.crit-dot{width:12px;height:12px;border-radius:3px;flex-shrink:0;}
.crit-lbl{flex:1;font-size:.8rem;color:#2C3E50;margin:0;}
.crit-in{width:80px;flex-shrink:0;text-align:center;font-weight:700;}
.chart-box{background:#fff;border:1px solid #eef1f6;border-radius:10px;padding:8px;height:200px;display:flex;flex-direction:column;}
.chart-t{font-size:.72rem;font-weight:700;color:#23408F;text-align:center;margin-bottom:4px;}
.chart-box canvas{flex:1;min-height:0;}
.concl-table td,.concl-table th{font-size:.84rem;vertical-align:middle;}
.taux-box{border:2px solid;border-radius:12px;padding:12px;text-align:center;background:#fff;}
.taux-lbl{font-size:.82rem;color:#2C3E50;font-weight:600;}
.taux-val{font-size:1.6rem;font-weight:800;}
.taux-f{font-size:.72rem;color:#6b7a90;}
</style>

<div class="container-fluid px-3 py-2" style="max-width:1100px">
  <a href="<?= SITE_URL ?>/rapports" class="btn btn-sm btn-light mb-2"><i class="bi bi-arrow-left me-1"></i>Retour aux rapports</a>

  <div class="text-center mb-2">
    <img src="<?= ASSETS_URL ?>/images/banierenteanac.png" alt="ANAC Gabon"
         style="max-width:100%;height:auto;border-radius:10px"
         onerror="this.style.display='none'">
  </div>

  <div class="rap-head">
    <div style="font-size:1.15rem;font-weight:700"><i class="bi bi-file-earmark-text me-2" style="color:#F3C300"></i>Rapport d'acte de supervision</div>
    <div style="font-size:.85rem;opacity:.85" id="rh_sub">Chargement...</div>
  </div>

  <div id="rapZone">
    <div class="text-center text-muted p-5"><span class="spinner-border me-2"></span>Chargement du rapport...</div>
  </div>
</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>
<script>
const IDAUDIT = <?= (int) $idaudit ?>;
const CSRF    = '<?= $csrf ?>';
const API     = AGAI_BASE + '/api/rapports';
let AUD=null, RAP=null, META=null, MY_INSP_ID=null, CAN_EDIT=false, DOMAINES=[];
let DOM_CHARTS={}, DOM_QUILLS={};
let QUILLS={};

function apiPost(data){ return $.post(API, Object.assign({csrf_token:CSRF}, data), null, 'json'); }
function esc(s){ return $('<div>').text(s==null?'':String(s)).html(); }
function fmtDate(s){ if(!s||s==='0000-00-00') return '-'; const p=String(s).substring(0,10).split('-'); return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:s; }

// Libelles complets (cases a cocher)
const TYPES_FULL={audit:'Audit',inspection_programmee:'Inspection programmee',inspection_non_programmee:'Inspection non programmee',demonstration:'Demonstration',test:'Test',investigation:'Investigation'};
const CADRES_LBL={certification:'Certification',homologation:'Homologation',reconnaissance:'Reconnaissance',renouvellement:'Renouvellement',surveillance_continue:'Surveillance continue',traitement_evenement:"Traitement d'un evenement",fermeture_provisoire:'Fermeture provisoire',fermeture_definitive:'Fermeture definitive',delivrance_autorisation:"Delivrance d'une autorisation"};
// Ampliation ANAC (liste deroulante Select2)
const AMPLIATION_ANAC=['DG','DG-DD','DG-DC','DG-CE','DG-DZ','DG-DA','DG-YY','DG-IX','DG-XD','DG-XA','DG-XZ','IX-OPS','IX-AIR','IX-AVS','IX-FAC','IX-ANS','IX-AGA','IX-PEL','IX-OCV','IX-MDA','DG-QM','DG-QD','DG-QA','DG-QZ','QM-QUA','QM-SEC','DG-CD','DG-CZ','CD-COM','CD-REP','CD-DOB','DG-PE','DG-ED','DG-EZ','DG-IQ','DD-COU','DD-DR','DG-RD','DG-RZ','DR-DN','DR-DS','DR-DE','DR-DO','DE-ED','DE-EZ','DE-EL','EL-PEL','EL-FOR','DE-EM','DE-EX','EX-OPS','EX-MDA','DE','DNA','DN-AD','DN-AZ','NA-ATS','NA-CNS','NA-AIS','NA-PAN','NA-SAR-MET','DN','DN-ND','DN-NZ','DN-NN','NN-AIR','NN-IMA','DN-NM','DU','DU-UD','DU-UZ','DU-US','DU-UF','DA','DA-AD','DA-AZ','DA-AP','AP-EGA','AP-GPB','AP-EIS','DA-AG','DA-AE','DJ','DJ-JD','DJ-JZ','DJ-JR','DJ-JS','DJ-JJ','DTA','DT-TD','DT-TZ','DRH','DH-HD','DH-HZ','DF','DF-FD','DF-FZ','DF-FA','FA-ADM','FA-MGX','DF-FH','FH-GRH','FH-ADP','DF-FC','FC-FIN','FC-CPT'];

// Sections a editeur riche (Quill)
const SECTS=[
  {k:'objectifs',              t:'Objectif(s) vise(s)'},
  {k:'sites_geographiques',    t:'Le(s) site(s) geographique(s)'},
  {k:'unites_organisation',    t:'Le(s) unite(s) organisationnelle(s)'},
  {k:'activites_processus',    t:'Activites / processus / produits a considerer'},
  {k:'referentiels',           t:'Referentiel(s) opposables a l\'exploitant'},
  {k:'plan_realise',           t:'Plan effectivement realise'},
  {k:'points_forts',           t:'Points forts'}
];
// Regroupement en sections du rapport (fidele au formulaire IX-GEN-R3-F-I-009)
const SECT_GROUPS=[
  {title:'Objectifs',   icon:'bi-bullseye',          keys:['objectifs']},
  {title:'Perimetre',   icon:'bi-geo-alt',           keys:['sites_geographiques','unites_organisation','activites_processus']},
  {title:'Referentiels',icon:'bi-journal-text',      keys:['referentiels']},
  {title:'Deroulement', icon:'bi-clipboard-check',   keys:['plan_realise']},
  {title:'Bilan de l\'acte', icon:'bi-award',        keys:['points_forts']}
];

const TOOLBAR=[[{header:[1,2,3,false]}],['bold','italic','underline','strike'],
  [{list:'ordered'},{list:'bullet'}],[{color:[]},{background:[]}],['clean']];

$(function(){
  // Diagnostic : verifier que les dependances sont chargees
  if(typeof AGAI_BASE==='undefined'){ $('#rapZone').html('<div class="alert alert-danger">Erreur : AGAI_BASE non defini. Rechargez la page.</div>'); return; }
  if(typeof Quill==='undefined'){ $('#rapZone').html('<div class="alert alert-warning">Editeur Quill non charge. Verifiez votre connexion (CDN).</div>'); return; }
  // Identifier l'inspecteur connecte (ne bloque pas si echec)
  apiPost({action:'whoami_insp'}).always(function(who){
    MY_INSP_ID = (who && who.success && who.idinspecteur) ? parseInt(who.idinspecteur) : null;
    // Charger le rapport
    apiPost({action:'get_rapport', idaudit:IDAUDIT}).done(function(res){
      if(!res || !res.success){
        $('#rapZone').html('<div class="alert alert-danger"><b>Acces refuse ou erreur :</b> '+esc((res&&res.message)||'Reponse invalide du serveur.')+'</div>');
        return;
      }
      RAP = res.rapport||{}; META = res.meta||{}; AUD = res.audit||{};
      CAN_EDIT = !!res.can_edit; DOMAINES = res.domaines||[];
      try { buildForm(); }
      catch(e){ $('#rapZone').html('<div class="alert alert-danger"><b>Erreur d\'affichage :</b> '+esc(e.message)+'</div>'); }
    }).fail(function(xhr){
      let msg='Erreur de chargement du rapport.';
      if(xhr && xhr.status){ msg+=' (HTTP '+xhr.status+')'; }
      if(xhr && xhr.responseText){ msg+='<br><small style="color:#666">'+esc(xhr.responseText.substring(0,300))+'</small>'; }
      $('#rapZone').html('<div class="alert alert-danger">'+msg+'</div>');
    });
  });
});

function buildForm(){
  const typeActe = AUD.type_activite||'';
  const cadre    = AUD.cadre||'';
  const operateur= AUD.nomorga||META.nomorga||'';
  const activite = META.activite_operateur||AUD.type_activite_operateur||'';
  const dateReal = AUD.date_realisation||AUD.date_previsionnelle||'';
  const periode  = RAP.periode_texte || (dateReal? ('le '+fmtDate(dateReal)) : '');
  $('#rh_sub').text((AUD.num_audit? ('Audit '+AUD.num_audit+' - ') : '')+operateur);

  // Cases a cocher nature + cadre
  let typeChecks=''; Object.keys(TYPES_FULL).forEach(function(k){ const on=(k===typeActe);
    typeChecks+='<span class="rap-check '+(on?'on':'')+'"><i class="bi '+(on?'bi-check-square-fill':'bi-square')+'"></i>'+esc(TYPES_FULL[k])+'</span>'; });
  let cadreChecks=''; Object.keys(CADRES_LBL).forEach(function(k){ const on=(k===cadre);
    cadreChecks+='<span class="rap-check '+(on?'on':'')+'"><i class="bi '+(on?'bi-check-square-fill':'bi-square')+'"></i>'+esc(CADRES_LBL[k])+'</span>'; });

  // Listes redacteur / verificateur (inspecteurs de l'audit)
  const inspOpts=function(sel){ let o='<option value="">-- Choisir --</option>';
    (META.inspecteurs||[]).forEach(function(i){ const nom=(i.nom||'').trim()||('Inspecteur '+i.idinspecteur);
      o+='<option value="'+esc(i.idinspecteur)+'"'+(String(i.idinspecteur)===String(sel)?' selected':'')+'>'+esc(nom)+(i.trigr?(' ('+esc(i.trigr)+')'):'')+'</option>'; });
    return o; };

  // Ampliations existantes
  const amplExist=(RAP.ampliation_anac||'').split('\n').map(function(s){return s.trim();}).filter(Boolean);
  const amplLines=amplExist.length?amplExist:[''];
  // Responsables existants (JSON : [{nom,fonction}])
  let respList=[]; try{ respList=RAP.responsables_operateur? JSON.parse(RAP.responsables_operateur):[]; }catch(e){ respList=[]; }
  if(!Array.isArray(respList)||!respList.length) respList=[{nom:'',fonction:''}];

  const ro = CAN_EDIT ? '' : ' disabled';
  let h='';

  // --- Nature de l'acte ---
  h+='<div class="rap-card"><div class="rap-sec-title"><i class="bi bi-flag-fill"></i>Nature de l\'acte</div>'
    +'<div class="rap-checks">'+typeChecks+'</div>'
    +'<div class="rap-lbl mt-3">Dans le cadre :</div><div class="rap-checks">'+cadreChecks+'</div>'
    +'<div class="rap-note"><i class="bi bi-info-circle me-1"></i>La nature et le cadre sont repris automatiquement de l\'audit (declenchement PSC).</div></div>';

  // --- Identification ---
  const dateRealVal = (AUD.date_realisation && AUD.date_realisation!=='0000-00-00') ? String(AUD.date_realisation).substring(0,10) : '';
  h+='<div class="rap-card"><div class="rap-sec-title"><i class="bi bi-card-list"></i>Identification</div><div class="row g-3">'
    +'<div class="col-md-6"><label class="rap-lbl">Periode</label><input type="text" id="f_periode" class="form-control" maxlength="255" value="'+esc(periode)+'" placeholder="le 25 au 26 Aout 2025"'+ro+'></div>'
    +'<div class="col-md-6"><label class="rap-lbl">Date de realisation <span class="text-danger">*</span></label>'
      +'<input type="date" id="f_date_realisation" class="form-control" value="'+esc(dateRealVal)+'" max="'+new Date().toISOString().substring(0,10)+'"'+ro+'></div>'
    +'<div class="col-md-6"><label class="rap-lbl">Operateur</label><input type="text" class="form-control ro-field" value="'+esc(operateur)+'" readonly></div>'
    +'<div class="col-md-6"><label class="rap-lbl">Activite de l\'operateur</label><input type="text" class="form-control ro-field" value="'+esc(activite)+'" readonly></div>'
    +'</div></div>';

  // --- Redaction / validation (tableau avec Fonction, Visa, Date) ---
  const visaRed = (RAP.visa_redacteur||''), dateRed = (RAP.date_redacteur||'');
  const visaVer = (RAP.visa_verificateur||''), dateVer = (RAP.date_verificateur||'');
  const visaVal = (RAP.visa_validation||''), dateVal = (RAP.date_validation||'');
  h+='<div class="rap-card"><div class="rap-sec-title"><i class="bi bi-people"></i>Redaction et validation</div>'
    +'<div class="table-responsive"><table class="table table-sm table-bordered align-middle" style="font-size:.84rem">'
    +'<thead><tr><th style="width:110px">Role</th><th>Nom</th><th>Fonction</th><th style="width:120px">Visa</th><th style="width:130px">Date</th></tr></thead><tbody>'
    // Redacteur
    +'<tr><td><b>Redacteur</b></td>'
    +'<td><select id="f_redacteur" class="form-select form-select-sm"'+ro+'>'+inspOpts(RAP.id_redacteur)+'</select></td>'
    +'<td><input type="text" id="f_fonction_red" class="form-control form-control-sm" maxlength="150" value="'+esc(RAP.fonction_redacteur||'')+'" placeholder="Ex : Inspecteur CNS"'+ro+'></td>'
    +'<td><input type="text" id="f_visa_red" class="form-control form-control-sm" maxlength="80" value="'+esc(visaRed)+'" placeholder="Signature"'+ro+'></td>'
    +'<td><input type="date" id="f_date_red" class="form-control form-control-sm" value="'+esc(dateRed)+'"'+ro+'></td></tr>'
    // Verificateur
    +'<tr><td><b>Verificateur</b></td>'
    +'<td><select id="f_verificateur" class="form-select form-select-sm"'+ro+'>'+inspOpts(RAP.id_verificateur)+'</select></td>'
    +'<td><input type="text" id="f_fonction_ver" class="form-control form-control-sm" maxlength="150" value="'+esc(RAP.fonction_verificateur||'')+'" placeholder="Ex : Inspecteur ATS/AIM"'+ro+'></td>'
    +'<td><input type="text" id="f_visa_ver" class="form-control form-control-sm" maxlength="80" value="'+esc(visaVer)+'" placeholder="Signature"'+ro+'></td>'
    +'<td><input type="date" id="f_date_ver" class="form-control form-control-sm" value="'+esc(dateVer)+'"'+ro+'></td></tr>'
    // Validation (CI)
    +'<tr><td><b>Validation</b></td>'
    +'<td><input type="text" class="form-control form-control-sm ro-field" value="'+esc(META.ci_nom||'Chef Inspecteur')+'" readonly></td>'
    +'<td><input type="text" class="form-control form-control-sm ro-field" value="Chef Inspecteur" readonly></td>'
    +'<td><input type="text" id="f_visa_val" class="form-control form-control-sm" maxlength="80" value="'+esc(visaVal)+'" placeholder="Signature"'+ro+'></td>'
    +'<td><input type="date" id="f_date_val" class="form-control form-control-sm" value="'+esc(dateVal)+'"'+ro+'></td></tr>'
    +'</tbody></table></div>'
    +'<div class="form-text">La validation revient au Chef Inspecteur de l\'audit. Chaque intervenant renseigne son visa et sa date.</div></div>';

  // --- Destinataires / ampliation ---
  h+='<div class="rap-card"><div class="rap-sec-title"><i class="bi bi-envelope"></i>Destinataires et ampliation ANAC</div><div class="row g-3">'
    +'<div class="col-md-6"><label class="rap-lbl">Destinataire(s)</label><div id="q_destinataires" class="quill-box"></div></div>'
    +'<div class="col-md-6"><label class="rap-lbl">Ampliation ANAC</label><div id="f_amplList">'+amplLines.map(amplRow).join('')+'</div>'
    +(CAN_EDIT?'<button type="button" class="btn btn-sm btn-outline-primary mt-1" id="f_amplAdd"><i class="bi bi-plus-lg me-1"></i>Ajouter une ligne</button>':'')+'</div>'
    +'</div></div>';

  // --- Responsables rencontres ---
  h+='<div class="rap-card"><div class="rap-sec-title"><i class="bi bi-person-lines-fill"></i>Responsables rencontres (Operateur)</div>'
    +'<div id="f_respList">'+respList.map(respRow).join('')+'</div>'
    +(CAN_EDIT?'<button type="button" class="btn btn-sm btn-outline-primary mt-1" id="f_respAdd"><i class="bi bi-plus-lg me-1"></i>Ajouter un responsable</button>':'')+'</div>';

  // --- Sections riches (Quill), regroupees par grande section du formulaire ---
  const estVide = function(v){ const t=String(v||'').replace(/<[^>]*>/g,'').replace(/&nbsp;/g,' ').trim(); return t===''; };
  const prefillSites = (META.nomsite||'') ? ('<p>'+esc(META.nomsite)+'</p>') : '';
  const prefillRefs  = (META.referentiels||'') ? META.referentiels : '';
  const defForSect = function(k){
    if(!estVide(RAP[k])) return RAP[k];
    if(k==='sites_geographiques') return prefillSites;
    if(k==='referentiels')        return prefillRefs;
    return '';
  };
  const labelOf = function(k){ const s=SECTS.find(function(x){return x.k===k;}); return s?s.t:k; };
  // Valeurs initiales des sections riches, stockees en objet JS (evite l'injection
  // fragile de HTML dans un attribut value="" qui tronque au premier guillemet).
  window.SECT_INIT = window.SECT_INIT || {};
  SECT_GROUPS.forEach(function(g){
    h+='<div class="rap-card"><div class="rap-sec-title"><i class="bi '+g.icon+'"></i>'+esc(g.title)+'</div>';
    g.keys.forEach(function(k){
      window.SECT_INIT[k] = defForSect(k);
      h+='<div class="mb-3"><label class="rap-lbl">'+esc(labelOf(k))+'</label>'
        +'<div id="q_'+k+'" class="quill-box"></div></div>';
    });
    // Sous le Bilan de l'acte : recapitulatif des observations saisies par domaine
    if(g.title.indexOf('Bilan')>=0){
      h+='<div class="mt-2"><label class="rap-lbl">Observations par domaine (recapitulatif)</label>'
        +'<div id="bilan_obs_recap" class="bilan-recap"><span class="text-muted" style="font-size:.82rem">Les observations saisies dans chaque domaine apparaitront ici.</span></div></div>';
    }
    h+='</div>';
  });

  // --- Criteres retenus par domaine (chaque inspecteur saisit SON domaine) ---
  h+='<div class="rap-card"><div class="rap-sec-title"><i class="bi bi-bar-chart-line"></i>Criteres retenus par domaine</div>';
  if(!DOMAINES.length){
    h+='<div class="text-muted" style="font-size:.86rem">Aucun domaine n\'est encore assigne a cet audit (equipe d\'audit vide).</div>';
  } else {
    h+='<div class="rap-note mb-3"><i class="bi bi-info-circle me-1"></i>Chaque inspecteur saisit uniquement les criteres du domaine qui lui est assigne. Les graphiques se mettent a jour automatiquement.</div>';
    DOMAINES.forEach(function(d, idx){ h+=domaineBlock(d, idx); });
  }
  h+='</div>';

  // --- Releve des non-conformites (auto depuis les FNC de l'audit) ---
  h+='<div class="rap-card"><div class="rap-sec-title"><i class="bi bi-exclamation-triangle"></i>Releve des non-conformites</div>';
  h+='<div id="nc_zone"><div class="text-muted" style="font-size:.85rem"><span class="spinner-border spinner-border-sm me-2"></span>Chargement des fiches de non-conformite...</div></div>';
  // Synthese NC par categorie
  h+='<div id="nc_synthese" class="mt-3"></div>';
  // Preuves documentees
  window.SECT_INIT = window.SECT_INIT || {};
  window.SECT_INIT['preuves_documentees'] = RAP.preuves_documentees||'';
  h+='<div class="mt-3"><label class="rap-lbl">Liste des preuves documentees recueillies</label>'
    +'<div id="q_preuves" class="quill-box"></div></div>';
  // Listes de verification signees (checklists) - PDF unique combine
  const ckHas = (AUD.checklist_signee && String(AUD.checklist_signee).trim());
  h+='<div class="mt-3 p-3" style="border:1px solid #e2e8f0;border-radius:10px;background:#fcfdff">'
    +'<div class="d-flex align-items-center gap-2 mb-1">'
      +'<span style="background:#1E9C4B;color:#fff;width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.74rem;font-weight:800"><i class="bi bi-list-check"></i></span>'
      +'<label class="rap-lbl mb-0">Listes de verification signees (checklists)</label></div>'
    +(CAN_EDIT?('<div class="d-flex gap-2 align-items-center"><input type="file" id="f_checklist" class="form-control form-control-sm" accept="application/pdf" style="max-width:420px">'
      +'<button type="button" id="f_checklist_btn" class="btn btn-sm" style="background:#1E9C4B;color:#fff;font-weight:600"><i class="bi bi-upload me-1"></i>Deposer</button></div>'
      +'<div class="form-text" style="font-size:.76rem"><i class="bi bi-shield-check me-1 text-success"></i>PDF uniquement. Un nouveau depot est <b>combine</b> avec le document existant (fusion automatique).</div>'):'')
    +'<div id="f_checklist_actuel" class="mt-2" style="'+(ckHas?'':'display:none')+'">'
      +'<span class="badge" style="background:#e8f5ec;color:#157a3a;font-weight:600"><i class="bi bi-check-circle me-1"></i>Un document est deja depose</span>'
      +' <button type="button" class="btn btn-sm btn-outline-primary ms-1" id="f_checklist_voir"><i class="bi bi-eye me-1"></i>Consulter</button></div>'
    +'</div>';
  // FNC anterieures ouvertes
  h+='<div id="nc_anterieures" class="mt-3"></div>';
  h+='</div>';

  // --- Conclusion (calculs automatiques : totaux + taux) ---
  h+='<div class="rap-card"><div class="rap-sec-title"><i class="bi bi-check2-circle"></i>Conclusion</div>';
  h+='<div class="rap-note mb-3"><i class="bi bi-info-circle me-1"></i>Les totaux et les taux sont calcules automatiquement a partir des criteres de tous les domaines.</div>';
  h+='<div id="concl_zone"></div>';
  h+='<div class="mt-3"><label class="rap-lbl">Commentaire de conclusion</label>'
    +'<div id="q_conclusion" class="quill-box"></div></div>';
  window.SECT_INIT = window.SECT_INIT || {};
  window.SECT_INIT['conclusion'] = RAP.conclusion||'';
  window.SECT_INIT['destinataires'] = cleanDest(RAP.destinataires);
  h+='</div>';

  if(CAN_EDIT){
    h+='<div class="d-flex justify-content-end gap-2 mb-4"><a href="'+AGAI_BASE+'/rapports" class="btn btn-light">Annuler</a>'
      +'<button class="btn btn-outline-primary" id="f_pdf"><i class="bi bi-file-earmark-pdf me-1"></i>Imprimer le rapport (PDF)</button>'
      +'<button class="btn btn-anac" id="f_save"><i class="bi bi-save me-1"></i>Enregistrer</button></div>';
  } else {
    h+='<div class="alert alert-secondary"><i class="bi bi-eye me-1"></i>Consultation seule : vous n\'etes pas autorise a modifier ce rapport.</div>';
  }

  $('#rapZone').html(h);
  initQuills();
  initSelects();
  initAllDomCharts();
  // Editeurs riches additionnels (destinataires, conclusion, preuves)
  [['q_destinataires','destinataires'],['q_conclusion','conclusion'],['q_preuves','preuves_documentees']].forEach(function(t){
    const el=document.getElementById(t[0]); if(!el) return;
    const q=new Quill(el,{theme:'snow',modules:{toolbar:CAN_EDIT?TOOLBAR:false},readOnly:!CAN_EDIT});
    let val=(window.SECT_INIT && window.SECT_INIT[t[1]]!=null) ? window.SECT_INIT[t[1]] : '';
    // Nettoyage d'anciennes valeurs erronees
    const plain=String(val).replace(/<[^>]*>/g,'').trim();
    if(t[1]==='destinataires' && (plain==='Destinataire(s)'||plain==='Destinataires')) val='';
    if(val && String(val).replace(/<[^>]*>/g,'').replace(/&nbsp;/g,'').trim()!==''){
      if(String(val).trim().charAt(0)==='<'){ q.root.innerHTML=val; } else { q.setText(val); }
    }
    QUILLS[t[1]]=q;
  });
  refreshConclusion();
  refreshBilanObs();
  loadReleveNC();
}

/* Calcule et affiche le tableau recapitulatif + les taux (somme de tous les domaines) */
function refreshConclusion(){
  let NCE=0,NCS=0,NCNS=0,NCNE=0,NCNA=0;
  // On lit les valeurs saisies en direct dans les blocs (ou les valeurs chargees)
  if($('.dom-block').length){
    $('.dom-block').each(function(){
      const v=readDomVals($(this));
      NCE+=v.nce||0; NCS+=v.ncs||0; NCNS+=v.ncns||0; NCNE+=v.ncne||0; NCNA+=v.ncna||0;
    });
  } else {
    DOMAINES.forEach(function(d){ NCE+=d.nce||0; NCS+=d.ncs||0; NCNS+=d.ncns||0; NCNE+=d.ncne||0; NCNA+=d.ncna||0; });
  }
  const NCR = NCE+NCS+NCNS+NCNE+NCNA;
  const denom = NCS+NCNS;
  const tauxConf = denom? (NCS/denom*100).toFixed(2) : '0.00';
  const tauxNonConf = denom? (NCNS/denom*100).toFixed(2) : '0.00';

  const row=function(lbl,val,color){ return '<tr><td>'+lbl+'</td><td style="text-align:center;font-weight:700'+(color?';color:'+color:'')+'">'+val+'</td></tr>'; };
  let html='<div class="table-responsive"><table class="table table-sm table-bordered concl-table">'
    +'<thead><tr><th>Critere</th><th style="text-align:center;width:120px">Nombre</th></tr></thead><tbody>'
    +row('Nombre de criteres evalues (NCE)',NCE,'#23408F')
    +row('Criteres juges satisfaisants (NCS)',NCS,'#1E9C4B')
    +row('Criteres juges non satisfaisants (NCNS)',NCNS,'#D32F2F')
    +row('Criteres non evalues (NCNE)',NCNE,'#6b7a90')
    +row('Criteres non applicables (NCNA)',NCNA,'#b58a00')
    +'<tr style="background:#eef3fb"><td style="font-weight:700">Total des criteres retenus (NCR)</td><td style="text-align:center;font-weight:800;color:#23408F">'+NCR+'</td></tr>'
    +'</tbody></table></div>';
  // Taux
  html+='<div class="row g-2 mt-1"><div class="col-md-6"><div class="taux-box" style="border-color:#1E9C4B">'
    +'<div class="taux-lbl">Taux de conformite</div><div class="taux-val" style="color:#1E9C4B">'+tauxConf+' %</div>'
    +'<div class="taux-f">( NCS / (NCS + NCNS) ) x 100</div></div></div>'
    +'<div class="col-md-6"><div class="taux-box" style="border-color:#D32F2F">'
    +'<div class="taux-lbl">Taux de non-conformite</div><div class="taux-val" style="color:#D32F2F">'+tauxNonConf+' %</div>'
    +'<div class="taux-f">( NCNS / (NCS + NCNS) ) x 100</div></div></div></div>';
  // Phrase de synthese
  html+='<div class="rap-note mt-3" style="border-left-color:#1E9C4B">'
    +'Des activites realisees, '+NCE+' critere(s) ont ete evalues : '+NCS+' juge(s) satisfaisant(s) et '+NCNS+' non satisfaisant(s). '
    +'Les criteres non satisfaisants font l\'objet d\'emission de fiches de non-conformite attachees a ce rapport. '
    +'Taux de conformite : <b>'+tauxConf+'%</b>, taux de non-conformite : <b>'+tauxNonConf+'%</b>. '
    +(NCNE>0?('S\'agissant des '+NCNE+' critere(s) non evalue(s), ces derniers feront l\'objet d\'une autre planification.'):'')
    +'</div>';
  // Graphiques globaux
  html+='<div class="row g-2 mt-2"><div class="col-md-6"><div class="chart-box"><div class="chart-t">Recapitulatif (batons)</div><canvas id="conclBar" height="150"></canvas></div></div>'
    +'<div class="col-md-6"><div class="chart-box"><div class="chart-t">Repartition (%)</div><canvas id="conclPie" height="150"></canvas></div></div></div>';
  $('#concl_zone').html(html);
  // Dessiner les graphiques globaux
  renderConclCharts({nce:NCE,ncs:NCS,ncns:NCNS,ncne:NCNE,ncna:NCNA});
}
function renderConclCharts(v){
  if(typeof Chart==='undefined') return;
  const labels=['NCE','NCS','NCNS','NCNE','NCNA'];
  const colors=['#23408F','#1E9C4B','#D32F2F','#6b7a90','#F3C300'];
  const data=[v.nce,v.ncs,v.ncns,v.ncne,v.ncna];
  const total=data.reduce(function(a,b){return a+b;},0);
  const hasDL=(typeof ChartDataLabels!=='undefined');
  const barEl=document.getElementById('conclBar');
  if(barEl){ if(DOM_CHARTS['cbar']) DOM_CHARTS['cbar'].destroy();
    DOM_CHARTS['cbar']=new Chart(barEl,{type:'bar',data:{labels:labels,datasets:[{data:data,backgroundColor:colors}]},
      options:{plugins:{legend:{display:false},datalabels:{anchor:'end',align:'end',color:'#2C3E50',font:{weight:'bold',size:11}}},
        scales:{y:{beginAtZero:true,ticks:{precision:0},suggestedMax:(Math.max.apply(null,data)||1)*1.2}},responsive:true,maintainAspectRatio:false},
      plugins:hasDL?[ChartDataLabels]:[]}); }
  const pieEl=document.getElementById('conclPie');
  if(pieEl){ if(DOM_CHARTS['cpie']) DOM_CHARTS['cpie'].destroy();
    DOM_CHARTS['cpie']=new Chart(pieEl,{type:'pie',data:{labels:labels,datasets:[{data:data,backgroundColor:colors}]},
      options:{plugins:{legend:{position:'bottom',labels:{boxWidth:10,font:{size:9}}},
        datalabels:{color:'#fff',font:{weight:'bold',size:11},textStrokeColor:'rgba(0,0,0,.45)',textStrokeWidth:3,formatter:function(val){ if(!total) return ''; const p=val/total*100; return (p%1===0?p.toFixed(0):p.toFixed(1))+'%'; }},
        tooltip:{callbacks:{label:function(c){const pct=total?Math.round(c.parsed/total*100):0;return c.label+': '+c.parsed+' ('+pct+'%)';}}}},responsive:true,maintainAspectRatio:false},
      plugins:hasDL?[ChartDataLabels]:[]}); }
}

function amplRow(val){
  let opts='<option value="">-- Choisir --</option>';
  AMPLIATION_ANAC.forEach(function(c){ opts+='<option value="'+esc(c)+'"'+(String(c)===String(val)?' selected':'')+'>'+esc(c)+'</option>'; });
  const ro = CAN_EDIT?'':' disabled';
  return '<div class="input-group input-group-sm ampl-row"><select class="form-select ampl-sel"'+ro+'>'+opts+'</select>'
    +(CAN_EDIT?'<button type="button" class="btn btn-outline-danger ampl-del"><i class="bi bi-x-lg"></i></button>':'')+'</div>';
}
function respRow(r){
  r=r||{}; const ro=CAN_EDIT?'':' disabled';
  return '<div class="resp-row">'
    +'<input type="text" class="form-control form-control-sm resp-nom" placeholder="Nom & prenom" maxlength="150" value="'+esc(r.nom||'')+'"'+ro+'>'
    +'<input type="text" class="form-control form-control-sm resp-fct" placeholder="Fonction" maxlength="150" value="'+esc(r.fonction||'')+'"'+ro+'>'
    +(CAN_EDIT?'<button type="button" class="btn btn-outline-danger btn-sm resp-del"><i class="bi bi-x-lg"></i></button>':'')+'</div>';
}

/* Bloc d'un domaine : titre, saisie NCE/NCS/NCNS/NCNE/NCNA, 2 graphiques, observations.
   Verrouille si l'inspecteur connecte n'est pas l'assigne du domaine. */
function domaineBlock(d, idx){
  const editable = CAN_EDIT && d.can_edit_dom;
  const ro = editable ? '' : ' disabled';
  const lock = editable ? '' : '<span class="badge bg-light text-muted ms-2" style="font-weight:500"><i class="bi bi-lock me-1"></i>Saisie reservee a l\'inspecteur du domaine</span>';
  const nomDom = (d.nomdomaine||'').trim() || ('Domaine '+d.iddomaine);
  const libDom = (d.libel_domaine||'').trim();
  const num = function(v){ return (v==null?0:parseInt(v)||0); };
  let h='<div class="dom-block" data-idx="'+idx+'" data-iddomaine="'+esc(d.iddomaine)+'" data-idinspecteur="'+esc(d.idinspecteur)+'">';
  h+='<div class="dom-head"><span class="dom-name">'+esc(nomDom)+'</span>'
    +(libDom?'<span class="dom-lib"> - '+esc(libDom)+'</span>':'')
    +'<span class="dom-insp"><i class="bi bi-person me-1"></i>'+esc(d.insp_nom||'-')+'</span>'+lock+'</div>';
  // Ligne 1 : criteres (gauche) + graphiques (droite)
  h+='<div class="row g-3 mt-1"><div class="col-lg-5"><div class="crit-grid">';
  const rows=[['nce','Nombre de criteres evalues (NCE)','#23408F'],
              ['ncs','Criteres satisfaisants (NCS)','#1E9C4B'],
              ['ncns','Criteres non satisfaisants (NCNS)','#D32F2F'],
              ['ncne','Criteres non evalues (NCNE)','#6b7a90'],
              ['ncna','Criteres non applicables (NCNA)','#F3C300']];
  rows.forEach(function(r){
    h+='<div class="crit-row"><span class="crit-dot" style="background:'+r[2]+'"></span>'
      +'<label class="crit-lbl">'+esc(r[1])+'</label>'
      +'<input type="number" min="0" class="form-control form-control-sm crit-in" data-k="'+r[0]+'" value="'+num(d[r[0]])+'"'+ro+'></div>';
  });
  h+='</div></div>';
  // Graphiques
  h+='<div class="col-lg-7"><div class="row g-2">'
    +'<div class="col-6"><div class="chart-box"><div class="chart-t">Diagramme en batons</div><canvas id="barChart_'+idx+'" height="150"></canvas></div></div>'
    +'<div class="col-6"><div class="chart-box"><div class="chart-t">Camembert (%)</div><canvas id="pieChart_'+idx+'" height="150"></canvas></div></div>'
    +'</div></div></div>';
  // Ligne 2 : observations en PLEINE LARGEUR
  h+='<div class="mt-3"><label class="rap-lbl">Observations du domaine</label>'
    +'<div id="qdom_'+idx+'" class="quill-box quill-lg"></div>'
    +'<input type="hidden" id="qdomv_'+idx+'" value="'+esc(d.observations||'')+'"></div>';
  if(editable){
    h+='<div class="text-end mt-2"><button type="button" class="btn btn-sm btn-anac dom-save"><i class="bi bi-save me-1"></i>Enregistrer ce domaine</button></div>';
  }
  h+='</div>';
  return h;
}
function stripHtmlLocal(s){ return String(s||'').replace(/<[^>]*>/g,'').replace(/&nbsp;/g,' ').trim(); }
/* Recapitulatif des observations par domaine sous le Bilan de l'acte */
function refreshBilanObs(){
  const $zone=$('#bilan_obs_recap'); if(!$zone.length) return;
  let html='';
  DOMAINES.forEach(function(d,idx){
    let obs = DOM_QUILLS[idx] ? DOM_QUILLS[idx].root.innerHTML : (d.observations||'');
    if(obs && stripHtmlLocal(obs)){
      const nom=(d.nomdomaine||'').trim()||('Domaine '+d.iddomaine);
      html+='<div class="bilan-dom"><div class="bilan-dom-h">'+esc(nom)+'</div><div class="rich">'+obs+'</div></div>';
    }
  });
  $zone.html(html || '<span class="text-muted" style="font-size:.82rem">Aucune observation de domaine saisie pour le moment.</span>');
}

/* Charge et affiche le releve des non-conformites (FNC de l'audit + anterieures) */
function loadReleveNC(){
  apiPost({action:'releve_nc', idaudit:IDAUDIT}).done(function(res){
    if(!res || !res.success){ $('#nc_zone').html('<div class="text-muted" style="font-size:.85rem">Releve indisponible.</div>'); return; }
    renderReleveNC(res.par_domaine||[], res.par_categorie||{}, res.anterieures||[]);
  }).fail(function(){ $('#nc_zone').html('<div class="text-danger" style="font-size:.85rem">Erreur de chargement du releve.</div>'); });
}
const CAT_LBL={critique:'Critique',majeur:'Majeur',mineur:'Mineur',observation:'Observation'};
const CAT_COLOR={critique:'#D32F2F',majeur:'#F3C300',mineur:'#1E9C4B',observation:'#6b7a90'};
function renderReleveNC(parDomaine, parCategorie, anterieures){
  if(!parDomaine.length){
    $('#nc_zone').html('<div class="nc-empty"><i class="bi bi-info-circle me-1"></i>En attente ouverture des fiches. Aucune fiche de non-conformite n\'a encore ete ouverte pour cet audit.</div>');
  } else {
    let h='';
    parDomaine.forEach(function(g){
      const nom=(g.nomdomaine||'').trim()||('Domaine '+g.iddomaine);
      h+='<div class="nc-dom"><div class="nc-dom-h">'+esc(nom)+((g.libel_domaine||'').trim()?(' - '+esc(g.libel_domaine)):'')+'</div>';
      h+='<div class="table-responsive"><table class="table table-sm table-bordered nc-table"><thead><tr>'
        +'<th style="width:110px">N FNC</th><th>Libelle</th><th style="width:110px">Cat.</th><th style="width:180px">Referentiel</th></tr></thead><tbody>';
      g.fiches.forEach(function(f){
        const col=CAT_COLOR[f.categorie]||'#6b7a90';
        h+='<tr><td>'+esc(f.num_fnc)+'</td><td>'+esc(stripHtmlLocal(f.description))+'</td>'
          +'<td><span class="nc-badge" style="background:'+col+'">'+esc(CAT_LBL[f.categorie]||f.categorie||'-')+'</span></td>'
          +'<td style="font-size:.8rem">'+esc(f.referentiel||'-')+'</td></tr>';
      });
      h+='</tbody></table></div></div>';
    });
    $('#nc_zone').html(h);
  }
  const totalNC=(parCategorie.critique||0)+(parCategorie.majeur||0)+(parCategorie.mineur||0)+(parCategorie.observation||0);
  if(totalNC>0){
    let s='<div class="nc-synth-h">Synthese des non-conformites par categorie</div>';
    s+='<div class="table-responsive"><table class="table table-sm table-bordered nc-table" style="max-width:520px"><thead><tr>'
      +'<th>Critique</th><th>Majeur</th><th>Mineur</th><th>Observation</th><th>Total</th></tr></thead><tbody><tr style="text-align:center;font-weight:700">'
      +'<td style="color:'+CAT_COLOR.critique+'">'+(parCategorie.critique||0)+'</td>'
      +'<td style="color:'+CAT_COLOR.majeur+'">'+(parCategorie.majeur||0)+'</td>'
      +'<td style="color:'+CAT_COLOR.mineur+'">'+(parCategorie.mineur||0)+'</td>'
      +'<td style="color:'+CAT_COLOR.observation+'">'+(parCategorie.observation||0)+'</td>'
      +'<td style="color:#23408F">'+totalNC+'</td></tr></tbody></table></div>';
    s+='<div style="height:210px;max-width:560px;margin-top:6px"><canvas id="nc_synth_bar"></canvas></div>';
    $('#nc_synthese').html(s);
    // Histogramme des categories (avec etiquettes)
    const sbe=document.getElementById('nc_synth_bar');
    if(sbe && typeof Chart!=='undefined'){
      if(window._ncSynthChart){ window._ncSynthChart.destroy(); }
      const hasDL=(typeof ChartDataLabels!=='undefined');
      window._ncSynthChart=new Chart(sbe,{type:'bar',
        data:{labels:['Critique','Majeur','Mineur','Observation'],
          datasets:[{data:[parCategorie.critique||0,parCategorie.majeur||0,parCategorie.mineur||0,parCategorie.observation||0],
            backgroundColor:[CAT_COLOR.critique,CAT_COLOR.majeur,CAT_COLOR.mineur,CAT_COLOR.observation]}]},
        options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},
          datalabels:{anchor:'end',align:'end',color:'#2C3E50',font:{weight:'bold',size:12},formatter:function(v){return v;}}},
          scales:{y:{beginAtZero:true,ticks:{precision:0}}}},
        plugins:hasDL?[ChartDataLabels]:[]});
    }
  } else { $('#nc_synthese').html(''); }
  if(anterieures.length){
    // Grouper par domaine
    const grp={};
    anterieures.forEach(function(f){ const d=(f.nomdomaine||'').trim()||'Autres'; (grp[d]=grp[d]||[]).push(f); });
    let a='<div class="nc-ant-h"><i class="bi bi-clock-history me-1"></i>Statut des FNCs notifiees a l\'operateur auparavant et faisant encore l\'objet d\'un suivi</div>';
    Object.keys(grp).forEach(function(dom){
      a+='<div class="nc-dom-h" style="margin-top:8px">'+esc(dom)+'</div>';
      a+='<div class="table-responsive"><table class="table table-sm table-bordered nc-table"><thead><tr>'
        +'<th style="width:100px">N FNC</th><th style="width:90px">Date FNC</th><th>Libelle</th><th style="width:90px">Cat.</th><th style="width:100px">Statut</th><th style="width:110px">Delai mise en conf.</th></tr></thead><tbody>';
      grp[dom].forEach(function(f){
        const col=CAT_COLOR[f.categorie]||'#6b7a90';
        a+='<tr><td>'+esc(f.num_fnc)+'</td><td style="font-size:.8rem">'+esc(fmtDate(f.date_emission))+'</td><td>'+esc(stripHtmlLocal(f.description))+'</td>'
          +'<td><span class="nc-badge" style="background:'+col+'">'+esc(CAT_LBL[f.categorie]||f.categorie||'-')+'</span></td>'
          +'<td style="font-size:.8rem">'+esc(f.statut_lbl||'-')+'</td><td style="font-size:.8rem">'+esc(f.delai_conformite||'-')+'</td></tr>';
      });
      a+='</tbody></table></div>';
    });
    $('#nc_anterieures').html(a);
  } else {
    $('#nc_anterieures').html('<div class="nc-ant-h"><i class="bi bi-clock-history me-1"></i>FNC anterieures</div><div class="text-muted" style="font-size:.82rem">Aucune fiche anterieure non fermee pour cet operateur.</div>');
  }
}
/* Retire l'ancien placeholder 'Destinataire(s)' saisi par erreur */
function cleanDest(v){
  const t=stripHtmlLocal(v||'').toLowerCase();
  if(t==='destinataire(s)'||t==='destinataires') return '';
  return v||'';
}

/* Lecture des valeurs d'un bloc domaine */
function readDomVals($block){
  const v={};
  $block.find('.crit-in').each(function(){ v[$(this).data('k')]=parseInt($(this).val())||0; });
  return v;
}

/* Initialise / met a jour les 2 graphiques d'un domaine */
function renderDomCharts(idx, vals){
  if(typeof Chart==='undefined') return;
  const labels=['NCE','NCS','NCNS','NCNE','NCNA'];
  const colors=['#23408F','#1E9C4B','#D32F2F','#6b7a90','#F3C300'];
  const data=[vals.nce||0, vals.ncs||0, vals.ncns||0, vals.ncne||0, vals.ncna||0];
  const total=data.reduce(function(a,b){return a+b;},0);
  const hasDL = (typeof ChartDataLabels!=='undefined');
  // Baton : nombre affiche au sommet de chaque barre
  const barEl=document.getElementById('barChart_'+idx);
  if(barEl){
    if(DOM_CHARTS['bar_'+idx]) DOM_CHARTS['bar_'+idx].destroy();
    DOM_CHARTS['bar_'+idx]=new Chart(barEl,{type:'bar',
      data:{labels:labels,datasets:[{data:data,backgroundColor:colors}]},
      options:{plugins:{legend:{display:false},
        datalabels:{anchor:'end',align:'end',color:'#2C3E50',font:{weight:'bold',size:11},formatter:function(v){return v;}}},
        scales:{y:{beginAtZero:true,ticks:{precision:0},suggestedMax:(Math.max.apply(null,data)||1)*1.2}},responsive:true,maintainAspectRatio:false},
      plugins: hasDL?[ChartDataLabels]:[]});
  }
  // Camembert : nombre + pourcentage dans chaque part
  const pieEl=document.getElementById('pieChart_'+idx);
  if(pieEl){
    if(DOM_CHARTS['pie_'+idx]) DOM_CHARTS['pie_'+idx].destroy();
    DOM_CHARTS['pie_'+idx]=new Chart(pieEl,{type:'pie',
      data:{labels:labels,datasets:[{data:data,backgroundColor:colors}]},
      options:{plugins:{legend:{position:'bottom',labels:{boxWidth:10,font:{size:9}}},
        datalabels:{color:'#fff',font:{weight:'bold',size:11},textStrokeColor:'rgba(0,0,0,.45)',textStrokeWidth:3,formatter:function(v){ if(!total) return ''; const p=v/total*100; return (p%1===0?p.toFixed(0):p.toFixed(1))+'%'; }},
        tooltip:{callbacks:{label:function(c){const pct=total?Math.round(c.parsed/total*100):0;return c.label+': '+c.parsed+' ('+pct+'%)';}}}},
        responsive:true,maintainAspectRatio:false},
      plugins: hasDL?[ChartDataLabels]:[]});
  }
}
function initAllDomCharts(){
  DOMAINES.forEach(function(d,idx){
    renderDomCharts(idx,{nce:d.nce,ncs:d.ncs,ncns:d.ncns,ncne:d.ncne,ncna:d.ncna});
    // Editeur riche Quill pour les observations du domaine
    const el=document.getElementById('qdom_'+idx); if(!el) return;
    const editable = CAN_EDIT && d.can_edit_dom;
    const q=new Quill(el,{theme:'snow',modules:{toolbar:editable?TOOLBAR:false},readOnly:!editable});
    const raw=document.getElementById('qdomv_'+idx); const val=raw?raw.value:'';
    if(val){ if(val.trim().charAt(0)==='<'){ q.root.innerHTML=val; } else { q.setText(val); } }
    DOM_QUILLS[idx]=q;
  });
}

/* Mise a jour live des graphiques a la saisie */
$(document).on('input','.dom-block .crit-in',function(){
  const $b=$(this).closest('.dom-block'); const idx=parseInt($b.data('idx'));
  renderDomCharts(idx, readDomVals($b));
  refreshConclusion();
});

/* Enregistrement des criteres d'un domaine */
$(document).on('click','.dom-save',function(){
  const $b=$(this).closest('.dom-block');
  const idx=parseInt($b.data('idx'));
  const vals=readDomVals($b);
  const obs = DOM_QUILLS[idx] ? DOM_QUILLS[idx].root.innerHTML : '';
  const payload=Object.assign({action:'save_rapport_domaine', idaudit:IDAUDIT,
    iddomaine:$b.data('iddomaine'), idinspecteur:$b.data('idinspecteur'),
    observations:obs}, vals);
  const btn=$(this); const html=btn.html();
  btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
  apiPost(payload).done(function(res){
    btn.prop('disabled',false).html(html);
    if(res&&res.success){
      // Mettre a jour la copie locale pour la conclusion
      DOMAINES[idx].nce=vals.nce; DOMAINES[idx].ncs=vals.ncs; DOMAINES[idx].ncns=vals.ncns;
      DOMAINES[idx].ncne=vals.ncne; DOMAINES[idx].ncna=vals.ncna;
      DOMAINES[idx].observations = obs;
      refreshConclusion();
      refreshBilanObs();
      Swal.fire({icon:'success',title:'Domaine enregistre',timer:1000,showConfirmButton:false});
    }
    else { Swal.fire({icon:'error',title:'Erreur',text:(res&&res.message)||'Echec.',confirmButtonColor:'#23408F'}); }
  }).fail(function(){ btn.prop('disabled',false).html(html); Swal.fire({icon:'error',title:'Erreur',text:'Echec de l\'enregistrement.',confirmButtonColor:'#23408F'}); });
});

/* Impression PDF : ouvre la vue impression dediee dans un nouvel onglet */
$(document).on('click','#f_pdf',function(){
  window.open(AGAI_BASE + '/rapport-pdf?audit=' + encodeURIComponent(IDAUDIT), '_blank');
});

function initQuills(){
  SECTS.forEach(function(s){
    const el=document.getElementById('q_'+s.k); if(!el) return;
    const q=new Quill(el,{theme:'snow',modules:{toolbar:CAN_EDIT?TOOLBAR:false},readOnly:!CAN_EDIT});
    // Valeur initiale depuis l'objet JS (robuste, pas de troncature d'attribut)
    const val=(window.SECT_INIT && window.SECT_INIT[s.k]!=null) ? window.SECT_INIT[s.k] : '';
    if(val){ if(String(val).trim().charAt(0)==='<'){ q.root.innerHTML=val; } else { q.setText(val); } }
    QUILLS[s.k]=q;
  });
}
function initSelects(){
  if($.fn.select2){
    $('#f_redacteur,#f_verificateur').select2({theme:'bootstrap-5',width:'100%'});
    $('#f_amplList .ampl-sel').each(function(){ $(this).select2({theme:'bootstrap-5',width:'100%'}); });
  }
}

/* Evenements */
$(document).on('click','#f_amplAdd',function(){
  const $row=$(amplRow('')); $('#f_amplList').append($row);
  if($.fn.select2){ $row.find('.ampl-sel').select2({theme:'bootstrap-5',width:'100%'}); }
});
$(document).on('click','.ampl-del',function(){
  if($('#f_amplList .ampl-row').length>1){ $(this).closest('.ampl-row').remove(); }
  else { const $s=$(this).closest('.ampl-row').find('.ampl-sel'); $s.val('').trigger('change'); }
});
$(document).on('click','#f_respAdd',function(){ $('#f_respList').append(respRow({})); });
$(document).on('click','.resp-del',function(){
  if($('#f_respList .resp-row').length>1){ $(this).closest('.resp-row').remove(); }
  else { $(this).closest('.resp-row').find('input').val(''); }
});

/* Enregistrement */
$(document).on('click','#f_save',function(){
  // Date de realisation obligatoire (elle declenche le passage statut=3)
  const dRealVal=$('#f_date_realisation').val();
  if(!dRealVal){
    Swal.fire({icon:'warning',title:'Date de realisation requise',text:'Veuillez indiquer la date de realisation de l\'acte avant d\'enregistrer.',confirmButtonColor:'#23408F'});
    return;
  }
  if(dRealVal>new Date().toISOString().substring(0,10)){
    Swal.fire({icon:'warning',title:'Date invalide',text:'La date de realisation ne peut pas etre dans le futur.',confirmButtonColor:'#23408F'});
    return;
  }
  const ampl=[]; $('#f_amplList .ampl-sel').each(function(){ const v=$(this).val(); if(v) ampl.push(v); });
  const resp=[]; $('#f_respList .resp-row').each(function(){
    const nom=$(this).find('.resp-nom').val().trim(), fct=$(this).find('.resp-fct').val().trim();
    if(nom||fct) resp.push({nom:nom,fonction:fct});
  });
  const payload={ action:'save_rapport', idaudit:IDAUDIT,
    periode_texte:$('#f_periode').val()||'',
    date_realisation:$('#f_date_realisation').val()||'',
    rapport_methode:'saisie',
    id_redacteur:$('#f_redacteur').val()||'', fonction_redacteur:$('#f_fonction_red').val()||'',
    id_verificateur:$('#f_verificateur').val()||'', fonction_verificateur:$('#f_fonction_ver').val()||'',
    visa_redacteur:$('#f_visa_red').val()||'', date_redacteur:$('#f_date_red').val()||'',
    visa_verificateur:$('#f_visa_ver').val()||'', date_verificateur:$('#f_date_ver').val()||'',
    visa_validation:$('#f_visa_val').val()||'', date_validation:$('#f_date_val').val()||'',
    destinataires:(QUILLS['destinataires']?QUILLS['destinataires'].root.innerHTML:''), ampliation_anac:ampl.join('\n'),
    responsables_operateur:JSON.stringify(resp) };
  SECTS.forEach(function(s){ payload[s.k]=QUILLS[s.k]?QUILLS[s.k].root.innerHTML:''; });
  payload.conclusion = QUILLS['conclusion'] ? QUILLS['conclusion'].root.innerHTML : '';
  payload.preuves_documentees = QUILLS['preuves_documentees'] ? QUILLS['preuves_documentees'].root.innerHTML : '';

  // Diagnostic : verifier en console ce qui est reellement envoye
  console.log('AGAI payload rapport:', {
    sites: payload.sites_geographiques,
    unites: payload.unites_organisation,
    activites: payload.activites_processus,
    referentiels: payload.referentiels,
    quills_dispo: Object.keys(QUILLS)
  });

  const btn=$('#f_save'); const html=btn.html();
  btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');

  // 1) Enregistrer l'entete du rapport, PUIS 2) tous les domaines editables.
  apiPost(payload).done(function(res){
    if(!res || !res.success){
      btn.prop('disabled',false).html(html);
      Swal.fire({icon:'error',title:'Erreur',text:(res&&res.message)||'Echec.',confirmButtonColor:'#23408F'});
      return;
    }
    // Sauvegarde de tous les domaines que l'utilisateur peut editer
    const domSaves=[];
    $('.dom-block').each(function(){
      const $b=$(this); const idx=parseInt($b.data('idx'));
      // Seuls les domaines editables (non verrouilles) sont enregistres
      if($b.find('.crit-in').first().prop('disabled')) return;
      const vals=readDomVals($b);
      const obs = DOM_QUILLS[idx] ? DOM_QUILLS[idx].root.innerHTML : '';
      domSaves.push(apiPost(Object.assign({action:'save_rapport_domaine', idaudit:IDAUDIT,
        iddomaine:$b.data('iddomaine'), idinspecteur:$b.data('idinspecteur'),
        observations:obs}, vals)));
    });
    $.when.apply($, domSaves).always(function(){
      btn.prop('disabled',false).html(html);
      // Rafraichir la conclusion (les totaux audit ont ete recalcules cote serveur)
      refreshConclusion();
      Swal.fire({
        icon:'success',
        title:'Rapport enregistre',
        text:domSaves.length?('Entete + '+domSaves.length+' domaine(s) enregistre(s).'):'Entete enregistree.',
        showCancelButton:true,
        confirmButtonText:'<i class="bi bi-file-earmark-pdf me-1"></i>Voir le rapport (PDF)',
        cancelButtonText:'Continuer la saisie',
        confirmButtonColor:'#23408F',
        cancelButtonColor:'#6b7a90'
      }).then(function(res){
        if(res.isConfirmed){
          window.location.href = AGAI_BASE + '/rapport-pdf?audit=' + encodeURIComponent(IDAUDIT);
        }
      });
    });
  }).fail(function(){
    btn.prop('disabled',false).html(html);
    Swal.fire({icon:'error',title:'Erreur',text:'Echec de l\'enregistrement.',confirmButtonColor:'#23408F'});
  });
});

/* ===== Listes de verification signees (checklists) ===== */
$(document).on('click','#f_checklist_btn',function(){
  const f=$('#f_checklist')[0];
  if(!f || !f.files.length){ Swal.fire({icon:'info',title:'Aucun fichier',text:'Choisissez d\'abord un fichier PDF.',confirmButtonColor:'#23408F'}); return; }
  const file=f.files[0];
  if(!/\.pdf$/i.test(file.name)){ Swal.fire({icon:'error',title:'Format invalide',text:'Les listes de verification doivent etre un seul PDF.',confirmButtonColor:'#D32F2F'}); return; }
  const btn=$(this), html=btn.html();
  btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
  const fd=new FormData();
  fd.append('csrf_token',CSRF); fd.append('action','upload_checklist'); fd.append('idaudit',IDAUDIT);
  fd.append('fichier_checklist',file);
  $.ajax({url:API,type:'POST',data:fd,processData:false,contentType:false,dataType:'json'})
  .done(function(res){
    btn.prop('disabled',false).html(html);
    if(res && res.success){
      if(AUD) AUD.checklist_signee=res.checklist;
      $('#f_checklist').val('');
      $('#f_checklist_actuel').show();
      Swal.fire({icon:'success',title:'Checklists deposees',timer:1400,showConfirmButton:false});
    } else { Swal.fire({icon:'error',title:'Erreur',text:(res&&res.message)||'Echec du depot.',confirmButtonColor:'#D32F2F'}); }
  })
  .fail(function(jq){ btn.prop('disabled',false).html(html); Swal.fire({icon:'error',title:'Erreur',text:(jq.responseJSON&&jq.responseJSON.message)||'Echec du depot.',confirmButtonColor:'#D32F2F'}); });
});
$(document).on('click','#f_checklist_voir',function(){
  const url = API + '?serve=1&doc=checklist&idaudit=' + encodeURIComponent(IDAUDIT) + '&t=' + Date.now();
  let m=document.getElementById('chkViewModal');
  if(!m){
    const html='<div class="modal fade" id="chkViewModal" tabindex="-1" aria-hidden="true">'
      +'<div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content">'
      +'<div class="modal-header" style="background:#23408F;color:#fff"><h6 class="modal-title"><i class="bi bi-list-check me-2"></i>Listes de verification signees</h6>'
      +'<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>'
      +'<div class="modal-body p-0"><iframe id="chkViewFrame" style="width:100%;height:78vh;border:0"></iframe></div>'
      +'<div class="modal-footer"><a id="chkViewDl" class="btn btn-sm btn-outline-primary" target="_blank"><i class="bi bi-download me-1"></i>Ouvrir dans un onglet</a>'
      +'<button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Fermer</button></div>'
      +'</div></div></div>';
    document.body.insertAdjacentHTML('beforeend', html);
  }
  document.getElementById('chkViewFrame').src = url;
  document.getElementById('chkViewDl').href = url;
  new bootstrap.Modal('#chkViewModal').show();
});
</script>