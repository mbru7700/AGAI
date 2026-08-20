<?php
/**
 * Rapport d'acte de supervision IX-GEN-R3-F-I-009 - Vue impression PDF
 * Rendu fidele a la charte ANAC ; l'utilisateur imprime via le navigateur (Ctrl+P -> PDF).
 * Les donnees sont chargees via l'action get_rapport (deja controlee en acces).
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardPage('rapports');

$idaudit = (int) ($_GET['audit'] ?? 0);
if ($idaudit <= 0) { header('Location: ' . SITE_URL . '/rapports'); exit; }
$csrf = Security::generateCSRF();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Rapport d'acte de supervision</title>
<link rel="icon" href="<?= defined('ASSETS_URL') ? ASSETS_URL : (SITE_URL.'/public') ?>/images/faviconLOGOANAC.ico" type="image/x-icon">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
<?php require_once INCLUDES_PATH . '/qrcode_inline.php'; ?>
<style>
  /* Pied de page imprime via les margin boxes @page : methode native, fiable
     sur toutes les versions recentes de Chrome (contrairement a position:fixed).
     Le texte ANAC est au centre, le numero de page dynamique a droite. */
  @page {
    size: A4;
    margin: 16mm 12mm 18mm;
    @bottom-center {
      content: "BP 2212 Libreville \00b7 (GABON) \00b7 Tel.: (241) 01 44 54 00 \00b7 Fax: (241) 01 44 54 01 \00b7 E-mail : anac@anac-gabon.com \00b7 www.anacgabon.org";
      font-family: Candara, 'Segoe UI', sans-serif; font-size: 8pt; color: #666;
    }
    @bottom-right {
      content: counter(page) " / " counter(pages);
      font-family: Candara, 'Segoe UI', sans-serif; font-size: 8pt; color: #23408F; font-weight: 700;
    }
  }
  body { font-family: Candara, 'Segoe UI', sans-serif; color:#2C3E50; font-size:12px; }
  /* Pied de page HTML : visible UNIQUEMENT a l'ecran (apercu). A l'impression,
     ce sont les margin boxes @page ci-dessus qui prennent le relais. */
  .page-footer {
    font-size: 8.5px; color: #23408F; display:flex; justify-content:space-between; align-items:center;
    margin: 16px 8px 0; padding-top: 4px; border-top: 1px solid #cdd9ee;
  }
  .page-footer .pf-center { color:#666; text-align:center; flex:1; }
  .page-footer .pf-page::after { content: "Page 1"; font-weight:700; color:#23408F; }
  @media print { .page-footer { display: none; } }
  .tbl-da td, .tbl-da th { vertical-align:top; }
  .peri-sub { background:#dbe4f3; color:#23408F; font-weight:700; font-size:11px; padding:3px 8px; border:1px solid #c3d2ea; border-bottom:none; }
  .peri-val { border:1px solid #c3d2ea; padding:5px 10px; margin-bottom:6px; font-size:11px; }
  .peri-val:last-child { margin-bottom:0; }
  .qr-zone { text-align:center; margin-top:22px; padding-top:14px; border-top:1px dashed #c3d2ea; }
  .qr-zone #qrbox { display:inline-block; }
  .qr-zone #qrbox img, .qr-zone #qrbox canvas { margin:0 auto; }
  .qr-cap { font-size:9px; color:#6b7a90; margin-top:6px; }
  .doc { max-width: 900px; margin: 0 auto; padding: 26px; position:relative; }
  /* Cadre bleu a l'ecran (apercu) */
  .doc::before { content:''; position:absolute; top:8px; left:8px; right:8px; bottom:8px; border:3px solid #23408F; border-radius:5px; pointer-events:none; }
  /* Cadre bleu fixe a l'IMPRESSION : couvre chaque page, pied de page a l'interieur */
  .print-frame { display:none; }
  @media print {
    .doc::before { display:none; }
    .print-frame {
      display:block; position:fixed; top:6mm; left:6mm; right:6mm; bottom:6mm;
      border:3px solid #23408F; border-radius:4px; pointer-events:none; z-index:0;
    }
  }
  .banniere { text-align:center; margin-bottom:8px; }
  .banniere img { max-width:100%; height:auto; }
  .doc-ref { text-align:right; font-size:10px; color:#666; }
  .doc-code { max-width:900px; margin:0 auto 4px; text-align:right; font-size:10px; font-weight:700; color:#23408F; letter-spacing:.5px; }
  .doc-title { text-align:center; font-weight:800; color:#23408F; font-size:15px; text-transform:uppercase; margin:8px 0 14px; border-bottom:2px solid #23408F; padding-bottom:6px; }
  .sec { margin-bottom:14px; page-break-inside:avoid; }
  .sec-h { background:#23408F; color:#fff; font-weight:700; font-size:12px; padding:4px 8px; border-radius:3px; margin-bottom:6px; }
  .chk { display:inline-block; margin-right:14px; font-size:11px; }
  .chk i { margin-right:3px; }
  .chk.on { font-weight:700; color:#23408F; }
  .kv { font-size:11px; margin-bottom:3px; }
  .kv b { color:#23408F; }
  table.tbl { width:100%; border-collapse:collapse; font-size:11px; }
  table.tbl th, table.tbl td { border:1px solid #999; padding:3px 6px; }
  table.tbl th { background:#eef3fb; color:#23408F; }
  .dom-title { font-weight:700; color:#23408F; font-size:12px; margin:8px 0 4px; }
  .sec-sub { font-weight:700; color:#23408F; font-size:11px; margin:10px 0 4px; padding-bottom:2px; border-bottom:1px solid #cdd9ee; }
  .charts { display:flex; gap:16px; }
  .charts > div { flex:1; height:190px; }
  .taux { display:flex; gap:12px; margin-top:8px; }
  .taux .box { flex:1; border:2px solid; border-radius:8px; padding:8px; text-align:center; }
  .taux .v { font-size:18px; font-weight:800; }
  .rich { font-size:11px; }
  .rich p { margin:0 0 4px; }
  .no-print { margin:10px 0; text-align:center; }
  @media print { .no-print { display:none; } body{ -webkit-print-color-adjust:exact; print-color-adjust:exact; } }
  .sign-tbl td { height:50px; vertical-align:top; }
</style>
</head>
<body>
<div class="print-frame"></div>
<div class="no-print">
  <button class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>Imprimer / Enregistrer en PDF</button>
  <a href="<?= SITE_URL ?>/rapport-saisie?audit=<?= (int)$idaudit ?>" class="btn btn-light btn-sm">Retour a la saisie</a>
</div>
<div class="doc-code">IX-GEN-R3-F-I-009</div>
<div class="doc" id="doc">
  <div class="text-center text-muted p-5">Chargement du rapport...</div>
</div>

<!-- Pied de page fixe (repete a chaque page a l'impression) -->
<div class="page-footer">
  <span class="pf-center">BP 2212 Libreville &middot; (GABON) &middot; Tel.: (241) 01 44 54 00 &middot; Fax: (241) 01 44 54 01 &middot; E-mail : anac@anac-gabon.com &middot; www.anacgabon.org</span>
  <span class="pf-page"></span>
</div>

<script>
const IDAUDIT = <?= (int)$idaudit ?>;
const CSRF = '<?= $csrf ?>';
const API = '<?= SITE_URL ?>/api/rapports';
const ASSETS = '<?= defined('ASSETS_URL') ? ASSETS_URL : (SITE_URL.'/public') ?>';
function esc(s){ return $('<div>').text(s==null?'':String(s)).html(); }
function fmtDate(s){ if(!s||s==='0000-00-00') return '-'; const p=String(s).substring(0,10).split('-'); return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:s; }
function stripHtmlLocal(s){ return String(s||'').replace(/<[^>]*>/g,'').replace(/&nbsp;/g,' ').trim(); }
function cleanDest(v){ const t=stripHtmlLocal(v||'').toLowerCase(); if(t==='destinataire(s)'||t==='destinataires') return ''; return v||''; }
const TYPES_FULL={audit:'Audit',inspection_programmee:'Inspection programmee',inspection_non_programmee:'Inspection non programmee',demonstration:'Demonstration',test:'Test',investigation:'Investigation'};
const CADRES_LBL={certification:'Certification',homologation:'Homologation',reconnaissance:'Reconnaissance',renouvellement:'Renouvellement',surveillance_continue:'Surveillance continue',traitement_evenement:"Traitement d'un evenement",fermeture_provisoire:'Fermeture provisoire',fermeture_definitive:'Fermeture definitive',delivrance_autorisation:"Delivrance d'une autorisation"};

$.when(
  $.post(API,{csrf_token:CSRF,action:'get_rapport',idaudit:IDAUDIT},null,'json'),
  $.post(API,{csrf_token:CSRF,action:'releve_nc',idaudit:IDAUDIT},null,'json')
).done(function(r1,r2){
  const res=r1[0], nc=(r2&&r2[0])?r2[0]:{};
  if(!res||!res.success){ $('#doc').html('<div class="alert alert-danger">Acces refuse ou rapport introuvable.</div>'); return; }
  render(res.rapport||{}, res.meta||{}, res.audit||{}, res.domaines||[], nc);
}).fail(function(){ $('#doc').html('<div class="alert alert-danger">Erreur de chargement.</div>'); });

function chk(on,label){ return '<span class="chk '+(on?'on':'')+'"><i class="bi '+(on?'bi-check-square-fill':'bi-square')+'"></i>'+esc(label)+'</span>'; }

function render(rap, meta, aud, domaines, nc){
  nc = nc || {};
  // Titre de l'onglet avec le numero d'audit (plus parlant)
  if(aud.num_audit){ document.title = 'Rapport ' + aud.num_audit; }
  const operateur = aud.nomorga||meta.nomorga||'';
  const activite  = meta.activite_operateur||aud.type_activite_operateur||'';
  const dateReal  = aud.date_realisation||aud.date_previsionnelle||'';
  const periode   = rap.periode_texte || (dateReal? ('le '+fmtDate(dateReal)) : '-');

  // Totaux
  let NCE=0,NCS=0,NCNS=0,NCNE=0,NCNA=0;
  domaines.forEach(function(d){ NCE+=d.nce||0;NCS+=d.ncs||0;NCNS+=d.ncns||0;NCNE+=d.ncne||0;NCNA+=d.ncna||0; });
  const NCR=NCE+NCS+NCNS+NCNE+NCNA; const den=NCS+NCNS;
  const tConf=den?(NCS/den*100).toFixed(2):'0.00', tNon=den?(NCNS/den*100).toFixed(2):'0.00';

  let h='';
  // Banniere
  h+='<div class="banniere"><img src="'+ASSETS+'/images/banierenteanac.png" alt="ANAC" onerror="this.style.display=\'none\'"></div>';
  h+='<div class="doc-title">Rapport d\'acte de supervision'+(aud.num_audit?(' - '+esc(aud.num_audit)):'')+'</div>';

  // Nature de l'acte
  h+='<div class="sec"><div class="sec-h">Nature de l\'acte</div>';
  Object.keys(TYPES_FULL).forEach(function(k){ h+=chk(k===aud.type_activite,TYPES_FULL[k]); });
  h+='</div>';
  // Dans le cadre (section a en-tete bleu, comme Nature de l'acte)
  h+='<div class="sec"><div class="sec-h">Dans le cadre</div>';
  Object.keys(CADRES_LBL).forEach(function(k){ h+=chk(k===aud.cadre,CADRES_LBL[k]); });
  h+='</div>';

  // Identification
  h+='<div class="sec"><div class="sec-h">Identification</div>'
    +'<div class="kv"><b>Periode :</b> '+esc(periode)+'</div>'
    +'<div class="kv"><b>Operateur :</b> '+esc(operateur)+'</div>'
    +'<div class="kv"><b>Activite de l\'operateur :</b> '+esc(activite)+'</div></div>';

  // Redaction / validation
  const inspNom=function(id){ const i=(meta.inspecteurs||[]).find(function(x){return String(x.idinspecteur)===String(id);}); return i?i.nom:''; };
  h+='<div class="sec"><div class="sec-h">Redaction et validation</div>'
    +'<table class="tbl"><tr><th>Role</th><th>Nom</th><th>Fonction</th><th style="width:110px">Visa</th><th style="width:90px">Date</th></tr>'
    +'<tr><td>Redacteur</td><td>'+esc(inspNom(rap.id_redacteur))+'</td><td>'+esc(rap.fonction_redacteur||'')+'</td><td>'+esc(rap.visa_redacteur||'')+'</td><td>'+esc(rap.date_redacteur?fmtDate(rap.date_redacteur):'')+'</td></tr>'
    +'<tr><td>Verificateur</td><td>'+esc(inspNom(rap.id_verificateur))+'</td><td>'+esc(rap.fonction_verificateur||'')+'</td><td>'+esc(rap.visa_verificateur||'')+'</td><td>'+esc(rap.date_verificateur?fmtDate(rap.date_verificateur):'')+'</td></tr>'
    +'<tr><td>Validation</td><td>'+esc(meta.ci_nom||'Chef Inspecteur')+'</td><td>Chef Inspecteur</td><td>'+esc(rap.visa_validation||'')+'</td><td>'+esc(rap.date_validation?fmtDate(rap.date_validation):'')+'</td></tr></table></div>';

  // Destinataires / ampliation
  const destVal = cleanDest(rap.destinataires);
  const amplVal = (rap.ampliation_anac||'').trim();
  if(destVal.trim() || amplVal){
    h+='<div class="sec"><div class="sec-h">Destinataires et ampliation ANAC</div>';
    h+='<table class="tbl tbl-da"><tr>'
      +'<th style="width:50%">Destinataire(s)</th><th style="width:50%">Ampliation ANAC</th></tr>'
      +'<tr><td style="vertical-align:top"><div class="rich">'+(destVal.trim()?destVal:'<span style="color:#888">-</span>')+'</div></td>'
      +'<td style="vertical-align:top">'+(amplVal?esc(amplVal.split('\n').join(', ')):'<span style="color:#888">-</span>')+'</td></tr></table>';
    h+='</div>';
  }

  // Sections riches
  const richSec=function(title,html){ if(!html||!html.trim()||html==='<p><br></p>') return ''; return '<div class="sec"><div class="sec-h">'+esc(title)+'</div><div class="rich">'+html+'</div></div>'; };
  const subBlk=function(title,html){ if(!html||!html.trim()||html==='<p><br></p>') return ''; return '<div style="margin-bottom:6px"><b style="color:#23408F">'+esc(title)+' :</b><div class="rich">'+html+'</div></div>'; };
  h+=richSec('Objectif(s) vise(s)', rap.objectifs);
  // Perimetre : chaque sous-titre sur sa propre ligne (bandeau clair),
  // les donnees en dessous. Libelles exactement comme le document modele.
  const periBlock=function(lbl,html){
    const v=(html&&html.trim()&&html!=='<p><br></p>')?html:'<span style="color:#888">-</span>';
    return '<div class="peri-sub">'+lbl+'</div><div class="peri-val"><div class="rich">'+v+'</div></div>';
  };
  const hasPeri = (rap.sites_geographiques&&rap.sites_geographiques.trim()&&rap.sites_geographiques!=='<p><br></p>')
               || (rap.unites_organisation&&rap.unites_organisation.trim()&&rap.unites_organisation!=='<p><br></p>')
               || (rap.activites_processus&&rap.activites_processus.trim()&&rap.activites_processus!=='<p><br></p>');
  if(hasPeri){
    h+='<div class="sec"><div class="sec-h">Perimetre</div>'
      +periBlock('Le(s) site(s) geographique(s):', rap.sites_geographiques)
      +periBlock('Le(s) unite(s) organisationnelle(s) :', rap.unites_organisation)
      +periBlock('les activites et/ou les processus et/ou les produits a prendre en consideration :', rap.activites_processus)
      +'</div>';
  }
  h+=richSec('Referentiel(s) opposables a l\'exploitant', rap.referentiels);

  // Responsables rencontres
  let resp=[]; try{ resp=JSON.parse(rap.responsables_operateur||'[]'); }catch(e){ resp=[]; }
  if(Array.isArray(resp) && resp.some(function(r){return (r.nom||r.fonction);})){
    h+='<div class="sec"><div class="sec-h">Responsables rencontres (Operateur)</div><table class="tbl"><tr><th>Nom & prenom</th><th>Fonction</th></tr>';
    resp.forEach(function(r){ if(r.nom||r.fonction) h+='<tr><td>'+esc(r.nom||'')+'</td><td>'+esc(r.fonction||'')+'</td></tr>'; });
    h+='</table></div>';
  }
  h+=richSec('Plan effectivement realise', rap.plan_realise);
  h+=richSec('Bilan de l\'acte - Points forts', rap.points_forts);

  // Criteres par domaine
  if(domaines.length){
    h+='<div class="sec"><div class="sec-h">Criteres retenus par domaine</div>';
    domaines.forEach(function(d,idx){
      h+='<div style="page-break-inside:avoid;margin-bottom:10px">';
      h+='<div class="dom-title">'+esc((d.nomdomaine||'').trim())+((d.libel_domaine||'').trim()?(' - '+esc(d.libel_domaine)):'')+'</div>';
      const somme=(d.nce||0)+(d.ncs||0)+(d.ncns||0)+(d.ncne||0)+(d.ncna||0);
      if(somme===0){
        // Aucun critere saisi pour ce domaine : message d'alerte, pas de tableau/graphique vides
        h+='<div style="padding:8px 10px;background:#fdecea;border:1px solid #f5c6cb;border-radius:5px;color:#B71C1C;font-size:11px">'
          +'<b>Criteres non saisis :</b> l\'inspecteur en charge de ce domaine n\'a pas encore renseigne ses criteres (NCE, NCS, NCNS, NCNE, NCNA).</div>';
      } else {
        h+='<table class="tbl"><tr><th>NCE</th><th>NCS</th><th>NCNS</th><th>NCNE</th><th>NCNA</th></tr>'
          +'<tr style="text-align:center"><td>'+(d.nce||0)+'</td><td>'+(d.ncs||0)+'</td><td>'+(d.ncns||0)+'</td><td>'+(d.ncne||0)+'</td><td>'+(d.ncna||0)+'</td></tr></table>';
        h+='<div class="charts"><div><canvas id="pbar_'+idx+'"></canvas></div><div><canvas id="ppie_'+idx+'"></canvas></div></div>';
      }
      if((d.observations||'').trim() && d.observations!=='<p><br></p>') h+='<div class="rich" style="margin-top:4px"><b>Observations :</b> '+d.observations+'</div>';
      h+='</div>';
    });
    h+='</div>';
  }

  // Releve des non-conformites (classe par domaine)
  const CAT_LBL={critique:'Critique',majeur:'Majeur',mineur:'Mineur',observation:'Observation'};
  const parDom=nc.par_domaine||[], parCat=nc.par_categorie||{}, anter=nc.anterieures||[];
  h+='<div class="sec"><div class="sec-h">Releve des non-conformites</div>';
  if(!parDom.length){
    h+='<div class="rich" style="color:#8a6d00">En attente ouverture des fiches. Aucune fiche de non-conformite ouverte pour cet audit.</div>';
  } else {
    parDom.forEach(function(g){
      const nom=(g.nomdomaine||'').trim()||('Domaine '+g.iddomaine);
      h+='<div class="dom-title">'+esc(nom)+((g.libel_domaine||'').trim()?(' - '+esc(g.libel_domaine)):'')+'</div>';
      h+='<table class="tbl"><tr><th style="width:90px">N&deg; FNC</th><th>Libelle</th><th style="width:80px">Cat.</th><th style="width:150px">Referentiel</th></tr>';
      g.fiches.forEach(function(f){
        h+='<tr><td>'+esc(f.num_fnc)+'</td><td>'+esc(stripHtmlLocal(f.description))+'</td><td>'+esc(CAT_LBL[f.categorie]||f.categorie||'-')+'</td><td>'+esc(f.referentiel||'-')+'</td></tr>';
      });
      h+='</table>';
    });
  }
  // Synthese des non-conformites par categorie (en-tete bleu comme le releve)
  // Observation exclue : elle ne genere pas de fiche de non-conformite.
  const totFnc=(parCat.critique||0)+(parCat.majeur||0)+(parCat.mineur||0);
  if(totFnc>0){
    h+='<div class="sec-h">Synthese des non-conformites par categorie</div>';
    h+='<table class="tbl"><tr><th>Critique</th><th>Majeur</th><th>Mineur</th><th>Total</th></tr>'
      +'<tr style="text-align:center"><td>'+(parCat.critique||0)+'</td><td>'+(parCat.majeur||0)+'</td><td>'+(parCat.mineur||0)+'</td><td><b>'+totFnc+'</b></td></tr></table>';
    // Histogramme centre (Critique/Majeur/Mineur uniquement)
    h+='<div style="height:200px;margin:8px auto 0;max-width:440px"><canvas id="synthBar"></canvas></div>';
  }
  // Liste des preuves documentees recueillies
  if((rap.preuves_documentees||'').trim() && rap.preuves_documentees!=='<p><br></p>'){
    h+='<div class="sec-h">Liste des preuves documentees recueillies</div><div class="rich">'+rap.preuves_documentees+'</div>';
  }
  // Statut des FNC anterieures encore en suivi (en-tete bleu, classe par domaine)
  h+='<div class="sec-h">Statut des FNCs notifiees a l\'operateur auparavant et faisant encore l\'objet d\'un suivi</div>';
  if(anter.length){
    // Grouper par domaine
    const grp={};
    anter.forEach(function(f){ const d=(f.nomdomaine||'').trim()||'Autres'; (grp[d]=grp[d]||[]).push(f); });
    Object.keys(grp).forEach(function(dom){
      h+='<div class="dom-title">'+esc(dom)+'</div>';
      h+='<table class="tbl"><tr><th style="width:80px">N&deg; FNC</th><th style="width:75px">Date FNC</th><th>Libelle</th><th style="width:70px">Cat.</th><th style="width:80px">Statut</th><th style="width:95px">Delai mise en conf.</th></tr>';
      grp[dom].forEach(function(f){
        h+='<tr><td>'+esc(f.num_fnc)+'</td><td>'+esc(fmtDate(f.date_emission))+'</td><td>'+esc(stripHtmlLocal(f.description))+'</td><td>'+esc(CAT_LBL[f.categorie]||f.categorie||'-')+'</td><td>'+esc(f.statut_lbl||'-')+'</td><td>'+esc(f.delai_conformite||'-')+'</td></tr>';
      });
      h+='</table>';
    });
  } else {
    h+='<div class="rich" style="color:#666">Aucune fiche anterieure non fermee pour cet operateur.</div>';
  }
  h+='</div>';

  // Conclusion
  h+='<div class="sec"><div class="sec-h">Conclusion</div>';
  h+='<div class="rich" style="margin-bottom:6px">Des activites realisees, le tableau ci-dessous resume les criteres retenus et ceux effectivement evalues sur site durant la periode de l\'audit. Les criteres non satisfaisants font l\'objet d\'emission de fiches de non-conformite attachees a ce rapport, pour lesquelles l\'operateur est invite a soumettre des plans d\'actions acceptables par l\'autorite.</div>';
  h+='<table class="tbl"><tr><th>Critere</th><th style="width:90px;text-align:center">Nombre</th></tr>'
    +'<tr><td>Nombre de criteres evalues (NCE)</td><td style="text-align:center">'+NCE+'</td></tr>'
    +'<tr><td>Criteres satisfaisants (NCS)</td><td style="text-align:center">'+NCS+'</td></tr>'
    +'<tr><td>Criteres non satisfaisants (NCNS)</td><td style="text-align:center">'+NCNS+'</td></tr>'
    +'<tr><td>Criteres non evalues (NCNE)</td><td style="text-align:center">'+NCNE+'</td></tr>'
    +'<tr><td>Criteres non applicables (NCNA)</td><td style="text-align:center">'+NCNA+'</td></tr>'
    +'<tr style="background:#eef3fb"><td><b>Total des criteres retenus (NCR)</b></td><td style="text-align:center"><b>'+NCR+'</b></td></tr></table>';
  h+='<div class="taux"><div class="box" style="border-color:#1E9C4B"><div>Taux de conformite</div><div class="v" style="color:#1E9C4B">'+tConf+' %</div><div style="font-size:9px">( NCS / (NCS+NCNS) ) x 100</div></div>'
    +'<div class="box" style="border-color:#D32F2F"><div>Taux de non-conformite</div><div class="v" style="color:#D32F2F">'+tNon+' %</div><div style="font-size:9px">( NCNS / (NCS+NCNS) ) x 100</div></div></div>';
  h+='<div class="rich" style="margin-top:6px">Taux de conformite de l\'operateur en rapport avec les criteres effectivement evalues est de <b>'+tConf+'%</b>. Taux de non-conformite est de <b>'+tNon+'%</b>. S\'agissant des criteres non evalues, ces derniers feront l\'objet d\'une autre planification en vue de leur evaluation effective.</div>';
  h+='<div class="charts" style="margin-top:8px"><div><canvas id="cbar"></canvas></div><div><canvas id="cpie"></canvas></div></div>';
  if((rap.conclusion||'').trim() && rap.conclusion!=='<p><br></p>') h+='<div class="rich" style="margin-top:6px">'+rap.conclusion+'</div>';
  h+='</div>';

  // Signatures
  h+='<div class="sec"><table class="tbl sign-tbl"><tr><th>Redacteur</th><th>Verificateur</th><th>Validation (CI)</th></tr>'
    +'<tr><td>'+esc(inspNom(rap.id_redacteur))+'</td><td>'+esc(inspNom(rap.id_verificateur))+'</td><td>'+esc(meta.ci_nom||'')+'</td></tr></table></div>';

  // QR code de verification (en bas de document)
  h+='<div class="qr-zone" style="page-break-inside:avoid">'
    +'<div id="qrbox"></div>'
    +'<div class="qr-cap">ANAC Gabon &middot; Document genere par le systeme AGAI</div>'
    +'</div>';

  $('#doc').html(h);

  // Generer le QR code avec les informations essentielles de l'audit
  (function(){
    const box=document.getElementById('qrbox'); if(!box) return;
    const redNom = inspNom(rap.id_redacteur)||'-';
    const verNom = inspNom(rap.id_verificateur)||'-';
    const valNom = meta.ci_nom||'-';
    const lignes=[
      'ANAC GABON - AGAI (Systeme securise)',
      'RAPPORT D ACTE DE SUPERVISION',
      'Reference : IX-GEN-R3-F-I-009',
      'N Audit : '+(aud.num_audit||'-'),
      'Operateur : '+(operateur||'-'),
      'Date de realisation : '+(dateReal?fmtDate(dateReal):'-'),
      'Redacteur : '+redNom,
      'Verificateur : '+verNom,
      'Validateur (CI) : '+valNom,
      'Taux de conformite : '+(tConf!=null?tConf+'%':'-'),
      'Document authentifie AGAI'
    ].filter(Boolean);
    // Retirer accents et caracteres speciaux : la librairie QR encode mal l'UTF-8
    // (chaque accent gonfle le contenu). Le texte reste parfaitement lisible.
    const sansAccents = function(s){
      return String(s)
        .normalize('NFD').replace(/[\u0300-\u036f]/g,'')
        .replace(/[^\x20-\x7E]/g,' ')   // ne garder que l'ASCII imprimable
        .replace(/\s+/g,' ').trim();
    };
    const texte = lignes.map(sansAccents).join('\n');
    const QR = window.QRCode || (typeof QRCode!=='undefined' ? QRCode : null);
    if(!QR){ box.innerHTML='<span style="font-size:9px;color:#c00">Librairie QR indisponible</span>'; return; }
    const niveau = (QR.CorrectLevel && QR.CorrectLevel.L!=null) ? QR.CorrectLevel.L : 1;
    // Cette librairie n'a pas d'auto-detection de version : on monte typeNumber
    // de 1 a 40 jusqu'a ce que le contenu tienne.
    let genere=false;
    for(let tn=1; tn<=40 && !genere; tn++){
      try{
        box.innerHTML='';
        new QR(box, { text:texte, typeNumber:tn, width:120, height:120,
          colorDark:'#000000', colorLight:'#ffffff', correctLevel:niveau });
        genere=true;
      }catch(e){ /* version trop petite : on essaie la suivante */ }
    }
    if(!genere){
      // Repli ultime : contenu reduit (ne devrait jamais arriver)
      try{
        box.innerHTML='';
        const court=sansAccents('ANAC GABON - AGAI\nN Audit : '+(aud.num_audit||'-')+'\nOperateur : '+(operateur||'-')+'\nTaux : '+(tConf!=null?tConf+'%':'-'));
        new QR(box, { text:court, typeNumber:10, width:120, height:120,
          colorDark:'#000000', colorLight:'#ffffff', correctLevel:niveau });
      }catch(e){ box.innerHTML='<span style="font-size:9px;color:#c00">QR indisponible</span>'; }
    }
  })();

  // Dessiner les graphiques (avec chiffres visibles)
  const hasDL=(typeof ChartDataLabels!=='undefined');
  const draw=function(barId,pieId,v){
    const labels=['NCE','NCS','NCNS','NCNE','NCNA'], colors=['#23408F','#1E9C4B','#D32F2F','#6b7a90','#F3C300'];
    const data=[v.nce||0,v.ncs||0,v.ncns||0,v.ncne||0,v.ncna||0];
    const tot=data.reduce(function(a,b){return a+b;},0);
    const be=document.getElementById(barId);
    if(be) new Chart(be,{type:'bar',data:{labels:labels,datasets:[{data:data,backgroundColor:colors}]},
      options:{animation:false,plugins:{legend:{display:false},
        datalabels:{anchor:'end',align:'end',color:'#2C3E50',font:{weight:'bold',size:11},formatter:function(val){return val;}}},
        scales:{y:{beginAtZero:true,ticks:{precision:0},suggestedMax:(Math.max.apply(null,data)||1)*1.2}}},
      plugins:hasDL?[ChartDataLabels]:[]});
    const pe=document.getElementById(pieId);
    if(pe){ new Chart(pe,{type:'pie',data:{labels:labels,datasets:[{data:data,backgroundColor:colors,borderColor:'#fff',borderWidth:1}]},
      options:{animation:false,layout:{padding:6},plugins:{legend:{position:'bottom',labels:{boxWidth:10,padding:6,font:{size:9}}},
        datalabels:{color:'#fff',font:{weight:'bold',size:11},textStrokeColor:'rgba(0,0,0,.45)',textStrokeWidth:3,
          formatter:function(val){ if(!tot) return ''; const p=val/tot*100; return (p%1===0?p.toFixed(0):p.toFixed(1))+'%'; }},
        tooltip:{callbacks:{label:function(c){const p=tot?Math.round(c.parsed/tot*100):0;return c.label+': '+c.parsed+' ('+p+'%)';}}}}},
      plugins:hasDL?[ChartDataLabels]:[]}); }
  };
  domaines.forEach(function(d,idx){ draw('pbar_'+idx,'ppie_'+idx,d); });
  draw('cbar','cpie',{nce:NCE,ncs:NCS,ncns:NCNS,ncne:NCNE,ncna:NCNA});

  // Histogramme de synthese (Critique / Majeur / Mineur ; Observation exclue)
  const sbe=document.getElementById('synthBar');
  if(sbe){
    const cats=['Critique','Majeur','Mineur'];
    const cvals=[parCat.critique||0,parCat.majeur||0,parCat.mineur||0];
    const ccols=['#D32F2F','#F3C300','#1E9C4B'];
    new Chart(sbe,{type:'bar',data:{labels:cats,datasets:[{data:cvals,backgroundColor:ccols,barPercentage:0.6,categoryPercentage:0.6}]},
      options:{animation:false,plugins:{legend:{display:false},
        datalabels:{anchor:'end',align:'end',color:'#2C3E50',font:{weight:'bold',size:12},formatter:function(v){return v;}}},
        scales:{y:{beginAtZero:true,ticks:{precision:0},suggestedMax:(Math.max.apply(null,cvals)||1)*1.2}}},
      plugins:hasDL?[ChartDataLabels]:[]});
  }
}
</script>
</body>
</html>