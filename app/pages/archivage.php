<?php
/**
 * Page : Archivage documentaire des actes de supervision
 * Module : archivage  -  Route : /archivage
 *
 * Presente, pour chaque acte, l'inventaire des huit pieces attendues,
 * signale celles qui manquent et permet de les consulter en fenetre modale.
 *
 * Securite : Rbac::guardPage, CSRF sur les appels de donnees,
 * sorties echappees, visibilite restreinte selon le role.
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardPage('archivage');

$csrf      = Security::generateCSRF();
$pageTitle = 'Archivage';
$active    = 'archivage';
$pageIcon  = 'bi-archive';
require_once INCLUDES_PATH . '/layout_head.php';
?>
<?php require_once INCLUDES_PATH . '/qrcode_inline.php'; ?>
<style>
.ar-card{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:13px 15px;border-left:4px solid #23408F;
         cursor:pointer;transition:.15s;height:100%}
.ar-card:hover{transform:translateY(-2px);box-shadow:0 8px 18px rgba(35,64,143,.14)}
.ar-card.on{box-shadow:0 0 0 2px rgba(35,64,143,.3)}
.ar-num{font-size:1.55rem;font-weight:800;line-height:1;color:#2C3E50}
.ar-lbl{font-size:.71rem;color:#6b7a90;text-transform:uppercase;letter-spacing:.3px;font-weight:600;margin-top:3px}
.b-green{border-left-color:#1E9C4B}.b-org{border-left-color:#E8890C}.b-red{border-left-color:#D32F2F}
.flbl{font-size:.72rem;font-weight:700;color:#5b6b85;text-transform:uppercase;margin-bottom:3px}
#arTable thead th{background:#23408F;color:#fff;text-transform:uppercase;letter-spacing:.4px;
                  font-size:.69rem;font-weight:700;padding:9px 8px;border:none;text-align:center}
#arTable thead th:nth-child(1),#arTable thead th:nth-child(2),#arTable thead th:nth-child(3){text-align:left}
#arTable td{padding:7px 8px;font-size:.82rem;border-bottom:1px solid #f0f3f8;vertical-align:middle;text-align:center}
#arTable td:nth-child(1),#arTable td:nth-child(2),#arTable td:nth-child(3){text-align:left}
.doc-ok{color:#1E9C4B;font-size:1.05rem;cursor:pointer}
.doc-ko{color:#dfe4ec;font-size:1.05rem}
.doc-warn{color:#E8890C;font-size:1.05rem;cursor:pointer}
.pill{padding:2px 9px;border-radius:20px;font-size:.71rem;font-weight:700;white-space:nowrap}
.doc-row{display:flex;align-items:center;gap:10px;padding:9px 12px;border:1px solid #e6ebf3;border-radius:10px;margin-bottom:8px;background:#fff}
.doc-ic{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;color:#fff;flex:0 0 auto}
.manque{background:#fff5f5;border-color:#fecaca}
</style>

<div class="page-head">
  <div>
    <h1><i class="bi bi-archive me-2" style="color:var(--anac-primary)"></i>Archivage documentaire</h1>
    <div class="sub">Inventaire des pieces de chaque acte de supervision et controle de completude.</div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <button class="btn btn-outline-danger" id="btnPdf"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</button>
    <button class="btn btn-outline-success" id="btnXls"><i class="bi bi-file-earmark-excel me-1"></i>Excel</button>
    <button class="btn btn-outline-secondary" id="btnRefresh"><i class="bi bi-arrow-clockwise me-1"></i>Actualiser</button>
  </div>
</div>

<div class="fnc-card mb-3" style="border-left:4px solid #1E9C4B;background:#fff;border:1px solid #eef1f6;border-radius:12px">
  <div id="guideArchToggle" style="cursor:pointer;display:flex;align-items:center;gap:8px;padding:11px 15px;user-select:none">
    <i class="bi bi-info-circle" style="color:#1E9C4B"></i>
    <b style="color:#1E9C4B">Comment fonctionne l'archivage documentaire ?</b>
    <i class="bi bi-chevron-down ms-auto" id="guideArchChevron" style="color:#1E9C4B;transition:transform .2s"></i>
  </div>
  <div id="guideArchBody" style="display:none;padding:0 15px 13px;font-size:.83rem;color:#1e3a5f;line-height:1.6">
    Chaque acte de supervision constitue un <b>dossier documentaire</b> compose de 7 pieces. Le tableau indique, pour chaque acte, les pieces presentes (vert),
    partielles (orange) ou manquantes. Cliquez sur <b>Dossier documentaire</b> pour consulter le detail et ouvrir chaque piece :
    <div class="mt-2" style="padding-left:6px">
      <div><b>1. Fiche de mandat</b> : generee automatiquement a partir des donnees de l'acte (equipe, domaines, reglements).</div>
      <div><b>2. Revue documentaire</b> : disponible des que le Responsable d'Audit (RA) a saisi ou joint sa revue.</div>
      <div><b>3. Lettre de notification</b> : le document transmis a l'operateur.</div>
      <div><b>4. Rapport d'acte de supervision</b> : le rapport joint (PDF) ou saisi en ligne.</div>
      <div><b>5. Fiches de non-conformite</b> : une fiche par critere non satisfaisant (NCNS). Le dossier est complet quand le nombre de fiches creees atteint le NCNS de l'acte.</div>
      <div><b>6. Listes de verification</b> et <b>7. Autres pieces</b> : preuves et complements.</div>
    </div>
    <div class="mt-2 p-2" style="background:#eef4ff;border-left:4px solid #23408F;border-radius:6px">
      <i class="bi bi-shield-check me-1"></i>Les documents s'ouvrent en <b>consultation seule</b> (PDF imprimable). Un dossier complet peut etre archive en toute securite.
    </div>
  </div>
</div>

<!-- Indicateurs -->
<div class="row g-2 mb-3" id="arKpi">
  <div class="col-6 col-md-3"><div class="ar-card" data-f=""><div class="ar-num" id="k_total">-</div><div class="ar-lbl">Actes de supervision</div></div></div>
  <div class="col-6 col-md-3"><div class="ar-card b-green" data-f="complet"><div class="ar-num" id="k_complet" style="color:#1E9C4B">-</div><div class="ar-lbl">Dossiers complets</div></div></div>
  <div class="col-6 col-md-3"><div class="ar-card b-org" data-f="partiel"><div class="ar-num" id="k_partiel" style="color:#E8890C">-</div><div class="ar-lbl">Dossiers incomplets</div></div></div>
  <div class="col-6 col-md-3"><div class="ar-card b-red" data-f="vide"><div class="ar-num" id="k_vide" style="color:#D32F2F">-</div><div class="ar-lbl">Aucune piece</div></div></div>
</div>

<!-- Filtres -->
<div class="card-anac p-3 mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-md-2"><div class="flbl">N audit</div><select id="fNum" style="width:100%"></select></div>
    <div class="col-md-1"><div class="flbl">Annee</div><select id="fAnnee" style="width:100%"></select></div>
    <div class="col-md-2"><div class="flbl">Operateur</div><select id="fOrga" style="width:100%"></select></div>
    <div class="col-md-2"><div class="flbl">Nature</div><select id="fType" style="width:100%"></select></div>
    <div class="col-md-2"><div class="flbl">Responsable d'audit</div><select id="fRa" style="width:100%"></select></div>
    <div class="col-md-2"><div class="flbl">Etat du dossier</div>
      <select id="fEtat" style="width:100%">
        <option value="">Tous</option>
        <option value="complet">Complet</option>
        <option value="partiel">Incomplet</option>
        <option value="vide">Aucune piece</option>
      </select></div>
    <div class="col-md-1"><button class="btn btn-sm btn-outline-secondary w-100" id="btnReset" title="Reinitialiser"><i class="bi bi-x-lg"></i></button></div>
  </div>
</div>

<!-- Tableau -->
<div class="card-anac p-0" style="overflow:hidden">
  <div style="background:#f8fafc;padding:10px 15px;border-bottom:1px solid #eef1f6;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
    <strong style="font-size:.85rem;color:#23408F"><i class="bi bi-folder2-open me-1"></i>Dossiers documentaires</strong>
    <span style="font-size:.72rem;color:#6b7a90;display:flex;gap:12px;flex-wrap:wrap">
      <span><i class="bi bi-check-circle-fill doc-ok"></i> present</span>
      <span><i class="bi bi-dash-circle doc-ko"></i> absent</span>
      <span><i class="bi bi-exclamation-circle-fill doc-warn"></i> partiel</span>
    </span>
    <span style="margin-left:auto;font-size:.74rem;color:#6b7a90" id="arResume"></span>
  </div>
  <div class="table-responsive">
    <table id="arTable" style="width:100%;border-collapse:collapse">
      <thead><tr>
        <th style="min-width:150px">N audit</th><th>Operateur</th><th>Responsable</th>
        <th title="Fiche de mandat">Mandat</th>
        <th title="Revue documentaire">Revue</th>
        <th title="Lettre de notification">Notif.</th>
        <th title="Rapport d'acte de supervision">Rapport</th>
        <th title="Fiches de non-conformite">FNC</th>
        <th title="Listes de verification signees">Checklist</th>
        <th title="Preuves et autres pieces">Autres</th>
        <th>Completude</th><th class="text-end">Actions</th>
      </tr></thead>
      <tbody><tr><td colspan="12" style="padding:30px;text-align:center;color:#9aa7bd">
        <span class="spinner-border spinner-border-sm me-2"></span>Chargement...</td></tr></tbody>
    </table>
  </div>
</div>

<!-- MODALE : dossier d'un acte -->
<div class="modal fade" id="modalDossier" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,#23408F,#1b3576)">
        <h5 class="modal-title text-white"><i class="bi bi-folder2-open me-2" style="color:#F3C300"></i>
          Dossier documentaire - <span id="dsNum">-</span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="background:#f5f7fa"><div id="dsBody"></div></div>
    </div>
  </div>
</div>

<!-- MODALE : lecture d'une piece -->
<div class="modal fade" id="modalDoc" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content" style="height:90vh">
      <div class="modal-header" style="background:linear-gradient(135deg,#23408F,#1b3576)">
        <h5 class="modal-title text-white" id="docTitre"><i class="bi bi-file-earmark-pdf me-2" style="color:#F3C300"></i>Document</h5>
        <div class="ms-auto d-flex gap-2 me-3">
          <a id="docDl" class="btn btn-sm btn-light" download><i class="bi bi-download me-1"></i>Telecharger</a>
          <button id="docPrint" type="button" class="btn btn-sm btn-light"><i class="bi bi-printer me-1"></i>Imprimer</button>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0" style="background:#525659">
        <iframe id="docFrame" src="" style="width:100%;height:100%;border:none"></iframe>
      </div>
    </div>
  </div>
</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>
<script>
const CSRF = '<?php echo Security::escape($csrf); ?>';
const API  = AGAI_BASE + '/api/archivage';
function apiPost(d){ return $.post(API, Object.assign({csrf_token:CSRF}, d), null, 'json'); }
function esc(s){ const d=document.createElement('div'); d.textContent=(s==null?'':s); return d.innerHTML; }
function fmtDate(d){ const s=String(d||'').substring(0,10);
  return /^\d{4}-\d{2}-\d{2}$/.test(s) ? s.substring(8,10)+'/'+s.substring(5,7)+'/'+s.substring(0,4) : '-'; }

let ACTES = [], FILTRE_ETAT = '', DOSSIER = null;

const IMG_BASE    = AGAI_BASE + '/public/images/';
const TYPE_LABELS = {audit:'Audit', inspection_programmee:'Inspection programmee',
  inspection_non_programmee:'Inspection non programmee', demonstration:'Demonstration',
  test:'Test', investigation:'Investigation'};
const CADRE_LABELS= {certification:'Certification', homologation:'Homologation',
  reconnaissance:'Reconnaissance', renouvellement:'Renouvellement',
  surveillance_continue:'Surveillance continue', traitement_evenement:"Traitement d'un evenement",
  fermeture_provisoire:'Fermeture provisoire', fermeture_definitive:'Fermeture definitive',
  delivrance_autorisation:"Delivrance d'une autorisation"};
const STATUT_LBL  = {1:'Planifiee',2:'Reportee',3:'Effectuee',4:'Suspendue',
                     5:'A surveiller',6:'Annulee',7:'Inopinee'};

/* ------------------------------------------------------------------
 * Etat de chaque piece : 'ok', 'ko' ou 'partiel'
 * La fiche de mandat est generee a la demande : toujours disponible.
 * ------------------------------------------------------------------ */
function pieces(a){
  const nbEq   = Number(a.nb_equipe||0);
  const nbRev  = Number(a.nb_revues||0);
  const nbFnc  = Number(a.nb_fnc||0);
  const nbSign = Number(a.nb_fnc_signees||0);
  const ncns   = Number(a.ncns||0);                // criteres non satisfaisants attendus
  const raTraite = Number(a.ra_a_traite||0) > 0;   // le RA a saisi ou joint sa revue

  // FNC : le nombre de fiches attendues correspond au NCNS de l'acte. Le dossier
  // FNC est complet quand toutes les fiches attendues ont ete creees (nbFnc >= NCNS).
  // S'il n'y a pas de NCNS declare, on se rabat sur la presence d'au moins une fiche.
  let etatFnc;
  if(ncns > 0){
    etatFnc = (nbFnc >= ncns) ? 'ok' : (nbFnc > 0 ? 'partiel' : 'ko');
  } else {
    etatFnc = nbFnc === 0 ? 'ko' : 'ok';
  }

  return {
    mandat:    'ok',
    // La revue est COMPLETE des que le RA a traite la sienne (elle fait foi pour
    // tout l'audit). Sinon, incomplet meme si des inspecteurs ont saisi.
    revue:     raTraite ? 'ok' : (nbRev > 0 ? 'partiel' : 'ko'),
    notif:     (a.lettre_notification||'').trim() ? 'ok' : 'ko',
    rapport:   ((a.rapport_audit||'').trim() || Number(a.rapport_saisi||0)>0) ? 'ok' : 'ko',
    checklist: (a.checklist_signee||'').trim()    ? 'ok' : 'ko',
    fnc:       etatFnc,
    annexes:   Number(a.nb_fnc_annexes||0) > 0    ? 'ok' : 'ko'
  };
}
const LIB_PIECE = {
  mandat:'Fiche de mandat', revue:'Revue documentaire', notif:'Lettre de notification',
  rapport:'Rapport d\'acte de supervision', checklist:'Listes de verification signees',
  fnc:'Fiches de non-conformite', annexes:'Autres pieces (preuves et complements)'
};

/* Un dossier est complet lorsque toutes les pieces sont presentes. */
function completude(a){
  const p = pieces(a);
  const cles = Object.keys(p);
  const ok  = cles.filter(function(k){ return p[k]==='ok'; }).length;
  const pct = Math.round(ok*100/cles.length);
  let etat = 'partiel';
  if(ok === cles.length) etat = 'complet';
  else if(ok <= 1)       etat = 'vide';   // seul le mandat, genere d'office
  return {ok:ok, total:cles.length, pct:pct, etat:etat, p:p};
}
function manquantes(a){
  const p = pieces(a);
  return Object.keys(p).filter(function(k){ return p[k]!=='ok'; })
                       .map(function(k){ return LIB_PIECE[k] + (p[k]==='partiel' ? ' (incomplet)' : ''); });
}

function icone(etat, titre, action){
  if(etat==='ok')      return '<i class="bi bi-check-circle-fill doc-ok" title="'+esc(titre)+'"'+(action||'')+'></i>';
  if(etat==='partiel') return '<i class="bi bi-exclamation-circle-fill doc-warn" title="'+esc(titre)+' - incomplet"'+(action||'')+'></i>';
  return '<i class="bi bi-dash-circle doc-ko" title="'+esc(titre)+' - absent"></i>';
}

/* ---------- Chargement ---------- */
function charger(){
  apiPost({action:'list'}).done(function(res){
    if(!res || !res.success){
      $('#arTable tbody').html('<tr><td colspan="12" style="padding:24px;text-align:center;color:#D32F2F">'
        + esc((res&&res.message)||'Chargement impossible') + '</td></tr>');
      return;
    }
    ACTES = res.data || [];
    majKpi(); majFiltres(); rendre();
  }).fail(function(){
    $('#arTable tbody').html('<tr><td colspan="12" style="padding:24px;text-align:center;color:#D32F2F">Echec de la requete.</td></tr>');
  });
}

function majKpi(){
  let c=0, p=0, v=0;
  ACTES.forEach(function(a){ const e=completude(a).etat;
    if(e==='complet') c++; else if(e==='vide') v++; else p++; });
  $('#k_total').text(ACTES.length); $('#k_complet').text(c);
  $('#k_partiel').text(p); $('#k_vide').text(v);
}

function majFiltres(){
  const uniq=function(t){ return [...new Set(ACTES.map(function(a){ return String(a[t]||'').trim(); }).filter(Boolean))].sort(); };
  const fill=function(sel, vals, lbl){
    const cur=$(sel).val();
    $(sel).html('<option value="">'+lbl+'</option>'+vals.map(function(v){ return '<option value="'+esc(v)+'">'+esc(v)+'</option>'; }).join(''));
    if(cur && vals.indexOf(cur)>=0) $(sel).val(cur);
    $(sel).trigger('change.select2');
  };
  fill('#fNum',   uniq('num_audit').reverse(), 'Tous');
  fill('#fAnnee', uniq('annee').reverse(),     'Toutes');
  fill('#fOrga',  uniq('nomorga'),             'Tous');
  fill('#fType',  uniq('type_activite'),       'Toutes');
  fill('#fRa',    uniq('responsable'),         'Tous');
}

function filtrees(){
  const fn=$('#fNum').val()||'', fy=$('#fAnnee').val()||'', fo=$('#fOrga').val()||'';
  const ft=$('#fType').val()||'', fr=$('#fRa').val()||'', fe=$('#fEtat').val()||FILTRE_ETAT;
  return ACTES.filter(function(a){
    if(fn && String(a.num_audit||'')!==fn) return false;
    if(fy && String(a.annee||'')!==fy) return false;
    if(fo && String(a.nomorga||'')!==fo) return false;
    if(ft && String(a.type_activite||'')!==ft) return false;
    if(fr && String(a.responsable||'')!==fr) return false;
    if(fe && completude(a).etat!==fe) return false;
    return true;
  });
}

function rendre(){
  const list = filtrees();
  if(!list.length){
    $('#arTable tbody').html('<tr><td colspan="12" style="padding:30px;text-align:center;color:#9aa7bd">'
      + '<i class="bi bi-inbox me-2"></i>Aucun acte pour ces criteres.</td></tr>');
    $('#arResume').text(''); return;
  }
  let h='';
  list.forEach(function(a){
    const c = completude(a), p = c.p;
    const col = c.etat==='complet' ? '#1E9C4B' : (c.etat==='vide' ? '#D32F2F' : '#E8890C');
    h += '<tr>'
      + '<td style="font-family:monospace;font-weight:700;color:#23408F">'+esc(a.num_audit||'-')+'</td>'
      + '<td>'+esc(a.nomorga||'-')+'</td>'
      + '<td style="font-size:.79rem">'+esc(a.responsable||'-')+'</td>'
      + '<td>'+icone(p.mandat,   LIB_PIECE.mandat)+'</td>'
      + '<td>'+icone(p.revue,    LIB_PIECE.revue)+'</td>'
      + '<td>'+icone(p.notif,    LIB_PIECE.notif)+'</td>'
      + '<td>'+icone(p.rapport,  LIB_PIECE.rapport)+'</td>'
      + '<td>'+icone(p.fnc,      LIB_PIECE.fnc)+'</td>'
      + '<td>'+icone(p.checklist,LIB_PIECE.checklist)+'</td>'
      + '<td>'+icone(p.annexes,  LIB_PIECE.annexes)+'</td>'
      + '<td><div style="display:flex;align-items:center;gap:6px;justify-content:center">'
      +   '<div style="background:#eef2f7;border-radius:50px;height:7px;width:52px;overflow:hidden">'
      +     '<div style="width:'+c.pct+'%;height:100%;background:'+col+'"></div></div>'
      +   '<span style="font-size:.74rem;font-weight:700;color:'+col+'">'+c.ok+'/'+c.total+'</span></div></td>'
      + '<td class="text-end"><button class="btn btn-sm btn-outline-primary btn-dossier" data-id="'+esc(a.idaudit)+'" title="Ouvrir le dossier">'
      +   '<i class="bi bi-folder2-open me-1"></i>Dossier</button></td>'
      + '</tr>';
  });
  $('#arTable tbody').html(h);
  const nbC = list.filter(function(a){ return completude(a).etat==='complet'; }).length;
  $('#arResume').text(list.length+' acte(s) affiche(s) - '+nbC+' dossier(s) complet(s)');
}

/* ---------- Consultation d'une piece ---------- */
function ouvrirDocUrl(url, titre, couleur){
  $('#docTitre').html('<i class="bi bi-file-earmark-pdf me-2" style="color:#F3C300"></i>'
    + '<span style="background:#fff;color:'+(couleur||'#23408F')+';padding:1px 9px;border-radius:6px">'+esc(titre)+'</span>');
  $('#docFrame').attr('src', url);
  $('#docDl').attr('href', url).attr('download', String(titre||'document').replace(/[^A-Za-z0-9._-]/g,'_')+'.pdf');
  $('#docPrint').data('url', url);
  new bootstrap.Modal('#modalDoc').show();
}
function ouvrirDoc(params, titre, couleur){
  ouvrirDocUrl(API + '?action=serve&' + $.param(params), titre, couleur);
}
/* QRE : consultation via l'API dediee /api/qre - fichier joint (serve) ou saisie en ligne (imprimer) */
function ouvrirDocQre(idqre, hasFichier, titre, couleur){
  const apiQre = AGAI_BASE + '/api/qre';
  const url = hasFichier
    ? apiQre + '?action=serve&idqre=' + encodeURIComponent(idqre)
    : apiQre + '?action=imprimer&idqre=' + encodeURIComponent(idqre);
  ouvrirDocUrl(url, titre, couleur);
}
$(document).on('click','#docPrint',function(){
  const w=window.open($(this).data('url'), '_blank');
  if(w){ w.addEventListener('load', function(){ try{ w.print(); }catch(e){} }); }
});
$('#modalDoc').on('hidden.bs.modal', function(){ $('#docFrame').attr('src',''); });
$('#modalDoc').on('show.bs.modal', function(){
  const nb=$('.modal.show').length;
  $(this).css('z-index', 1060+(nb+1)*20);
  setTimeout(function(){ $('.modal-backdrop').last().css('z-index', 1060+(nb+1)*20-10); },0);
});
$(document).on('click','.lien-doc',function(){
  ouvrirDoc($(this).data('p'), $(this).data('t'), $(this).data('c'));
});

/* ---------- Dossier complet ---------- */
function ligneDoc(couleur, icone, titre, sousTitre, boutons, manque){
  return '<div class="doc-row'+(manque?' manque':'')+'">'
    + '<div class="doc-ic" style="background:'+couleur+'"><i class="bi '+icone+'"></i></div>'
    + '<div style="flex:1">'
    +   '<div style="font-weight:700;font-size:.86rem;color:#2C3E50">'+esc(titre)+'</div>'
    +   '<div style="font-size:.75rem;color:'+(manque?'#D32F2F':'#6b7a90')+'">'+sousTitre+'</div>'
    + '</div>'
    + '<div style="display:flex;gap:6px;flex-wrap:wrap">'+(boutons||'')+'</div></div>';
}
function btnDoc(params, titre, couleur){
  return '<button class="btn btn-sm btn-outline-primary lien-doc" data-p=\''+JSON.stringify(params)+'\' '
       + 'data-t="'+esc(titre)+'" data-c="'+esc(couleur||'#23408F')+'"><i class="bi bi-eye me-1"></i>Consulter</button>';
}
function btnDocQre(idqre, hasFichier, titre, couleur){
  return '<button class="btn btn-sm btn-outline-primary lien-doc-qre" data-idqre="'+esc(idqre)+'" data-f="'+(hasFichier?1:0)+'" '
       + 'data-t="'+esc(titre)+'" data-c="'+esc(couleur||'#23408F')+'"><i class="bi bi-eye me-1"></i>Consulter</button>';
}
$(document).on('click','.lien-doc-qre',function(){
  ouvrirDocQre($(this).data('idqre'), $(this).data('f')==1, $(this).data('t'), $(this).data('c'));
});

$(document).on('click','.btn-dossier',function(){
  const id=$(this).data('id');
  $('#dsBody').html('<div class="text-center py-4"><span class="spinner-border text-primary"></span></div>');
  new bootstrap.Modal('#modalDossier').show();

  apiPost({action:'detail', idaudit:id}).done(function(res){
    if(!res || !res.success){
      $('#dsBody').html('<div class="alert alert-danger">'+esc((res&&res.message)||'Dossier indisponible')+'</div>');
      return;
    }
    const a=res.audit||{}, eq=res.equipe||[], rev=res.revues||[], qre=res.qre||[], fnc=res.fnc||[];
    DOSSIER = res;                       // conserve pour l'impression du mandat
    $('#dsNum').text(a.num_audit||'-');

    /* Synthese de completude */
    const acte = ACTES.find(function(x){ return String(x.idaudit)===String(id); }) || a;
    const c = completude(acte), mq = manquantes(acte);
    const colC = c.etat==='complet' ? '#1E9C4B' : (c.etat==='vide' ? '#D32F2F' : '#E8890C');

    let h = '<div style="background:#fff;border:1px solid #e6ebf3;border-left:4px solid '+colC+';border-radius:12px;padding:14px 16px;margin-bottom:14px">'
      + '<div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">'
      +   '<div><div style="font-size:1.5rem;font-weight:800;color:'+colC+';line-height:1">'+c.ok+'/'+c.total+'</div>'
      +   '<div style="font-size:.72rem;color:#6b7a90;text-transform:uppercase;font-weight:600">Pieces presentes</div></div>'
      +   '<div style="flex:1;min-width:180px"><div style="background:#eef2f7;border-radius:50px;height:9px;overflow:hidden">'
      +     '<div style="width:'+c.pct+'%;height:100%;background:'+colC+'"></div></div></div>'
      +   '<div>'+(c.etat==='complet'
            ? '<span class="pill" style="background:#1E9C4B;color:#fff"><i class="bi bi-check-circle me-1"></i>Dossier complet et archivable</span>'
            : '<span class="pill" style="background:'+colC+';color:#fff"><i class="bi bi-exclamation-triangle me-1"></i>Dossier incomplet</span>')+'</div>'
      + '</div>'
      + (mq.length ? '<div style="margin-top:11px;background:#fff5f5;border-left:4px solid #D32F2F;border-radius:8px;padding:10px 13px;font-size:.82rem">'
          + '<strong style="color:#991b1b">Pieces manquantes :</strong> ' + esc(mq.join(', ')) + '</div>' : '')
      + '</div>';

    /* Informations de l'acte */
    const info = function(l,v){ return '<div class="col-md-3"><div style="font-size:.68rem;font-weight:700;color:#7b8aa0;text-transform:uppercase">'+esc(l)+'</div>'
      + '<div style="background:#f5f7fa;border:1px solid #eef1f6;border-radius:7px;padding:5px 9px;font-size:.83rem;min-height:30px">'+(v||'<span style="color:#adb5bd">-</span>')+'</div></div>'; };
    h += '<div style="background:#fff;border:1px solid #e6ebf3;border-radius:12px;padding:14px 16px;margin-bottom:14px">'
      + '<div style="font-size:.8rem;font-weight:800;color:#23408F;text-transform:uppercase;border-bottom:2px solid #eef3fb;padding-bottom:7px;margin-bottom:11px">'
      +   '<i class="bi bi-info-circle me-2"></i>Acte de supervision</div><div class="row g-2">'
      + info('N audit','<strong style="color:#23408F">'+esc(a.num_audit||'')+'</strong>')
      + info('Nature', esc(a.type_activite||''))
      + info('Cadre', esc(a.cadre||''))
      + info('Annee', esc(a.annee||''))
      + info('Operateur','<strong>'+esc(a.nomorga||'')+'</strong>')
      + info('Activites', esc(a.type_activite_operateur||''))
      + info('Lieu', esc(a.ville||a.nomsite||a.indicateur_oaci||a.site_inspection||''))
      + info('Date prevue', fmtDate(a.date_previsionnelle))
      + info('Date de realisation', fmtDate(a.date_realisation))
      + info('Remise du rapport', fmtDate(a.date_delivrance_rapport))
      + info('Responsable d\'audit','<strong>'+esc(a.responsable||'')+'</strong>')
      + info('Contact RA', esc(a.mail_responsable||''))
      + '</div></div>';

    /* Equipe */
    let te='';
    if(eq.length){
      eq.forEach(function(m){
        te += '<tr><td style="padding:6px 9px;border-bottom:1px solid #f0f3f8;font-weight:600">'+esc(m.nom||'-')+'</td>'
           +  '<td style="padding:6px 9px;border-bottom:1px solid #f0f3f8">'+esc(m.trigr_inspecteur||'-')+'</td>'
           +  '<td style="padding:6px 9px;border-bottom:1px solid #f0f3f8"><span class="pill" style="background:#eef3fb;color:#23408F">'+esc(m.nomdomaine||'-')+'</span></td>'
           +  '<td style="padding:6px 9px;border-bottom:1px solid #f0f3f8;font-size:.78rem">'+esc(m.mailinspect||'-')+'</td>'
           +  '<td style="padding:6px 9px;border-bottom:1px solid #f0f3f8;text-align:center">'
           +    (Number(m.a_revue)>0 ? '<i class="bi bi-check-circle-fill doc-ok"></i>' : '<i class="bi bi-dash-circle doc-ko"></i>')+'</td></tr>';
      });
    } else { te='<tr><td colspan="5" style="padding:12px;text-align:center;color:#9aa7bd">Aucun membre enregistre.</td></tr>'; }
    h += '<div style="background:#fff;border:1px solid #e6ebf3;border-radius:12px;padding:14px 16px;margin-bottom:14px">'
      + '<div style="font-size:.8rem;font-weight:800;color:#23408F;text-transform:uppercase;border-bottom:2px solid #eef3fb;padding-bottom:7px;margin-bottom:11px">'
      +   '<i class="bi bi-people me-2"></i>Equipe d\'inspection</div>'
      + '<div class="table-responsive"><table style="width:100%;border-collapse:collapse;font-size:.82rem">'
      + '<thead><tr style="background:#23408F;color:#fff">'
      +   '<th style="padding:7px 9px;text-align:left;font-size:.69rem;text-transform:uppercase">Inspecteur</th>'
      +   '<th style="padding:7px 9px;text-align:left;font-size:.69rem;text-transform:uppercase">Trigramme</th>'
      +   '<th style="padding:7px 9px;text-align:left;font-size:.69rem;text-transform:uppercase">Domaine</th>'
      +   '<th style="padding:7px 9px;text-align:left;font-size:.69rem;text-transform:uppercase">Adresse</th>'
      +   '<th style="padding:7px 9px;text-align:center;font-size:.69rem;text-transform:uppercase">Revue</th>'
      + '</tr></thead><tbody>'+te+'</tbody></table></div></div>';

    /* Inventaire documentaire */
    let d = '<div style="background:#fff;border:1px solid #e6ebf3;border-radius:12px;padding:14px 16px">'
      + '<div style="font-size:.8rem;font-weight:800;color:#23408F;text-transform:uppercase;border-bottom:2px solid #eef3fb;padding-bottom:7px;margin-bottom:11px">'
      +   '<i class="bi bi-files me-2"></i>Pieces du dossier</div>';

    /* 1. Mandat */
    d += ligneDoc('#23408F','bi-flag-fill','1. Fiche de mandat d\'acte de supervision',
         'Document genere a partir des donnees de l\'acte : equipe, domaines et reglements vises',
         '<button class="btn btn-sm btn-outline-primary" id="btnMandat"><i class="bi bi-eye me-1"></i>Consulter</button>', false);

    /* 2. Revue documentaire : visible en consultation seulement quand le RA a
       traite sa revue (saisie ou PDF). La revue du RA fait foi pour l'acte. */
    const raTraite = Number(a.ra_a_traite||0) > 0;
    let bRev='', sRev='';
    if(raTraite){
      // Trouver la revue du RA
      const raId = a.ra_id ? String(a.ra_id) : (a.idresponsable_audit ? String(a.idresponsable_audit) : '');
      let revRA = rev.find(function(r){ return raId && String(r.idinspecteur)===raId; });
      if(!revRA) revRA = rev.find(function(r){ return Number(r.est_consolide)===1; }) || rev[0];
      if(revRA){
        const fRA=(revRA.fichier_joint||'').trim();
        if(fRA){
          // Revue jointe au format PDF : consultation du document
          bRev = btnDoc({type:'revue', idrevue:revRA.idrevue}, 'Revue documentaire', '#1E9C4B');
          sRev = 'Revue documentaire jointe au format PDF par le Responsable d\'Audit';
        } else {
          // Revue saisie : impression de la fiche IX-GEN-R3-F-I-017 (avec QR)
          bRev = '<a class="btn btn-sm btn-outline-primary" href="'+AGAI_BASE+'/revue?audit='+esc(a.idaudit)+'&print=1" target="_blank"><i class="bi bi-eye me-1"></i>Consulter</a>';
          sRev = 'Revue documentaire saisie en ligne par le Responsable d\'Audit';
        }
      }
    } else {
      sRev = 'En attente : le Responsable d\'Audit n\'a pas encore traite la revue documentaire';
    }
    d += ligneDoc('#1E9C4B','bi-file-earmark-text','2. Revue documentaire', esc(sRev),
         bRev || '<a class="btn btn-sm btn-outline-secondary" href="'+AGAI_BASE+'/mes-audits" target="_blank"><i class="bi bi-box-arrow-up-right me-1"></i>Module Revue</a>',
         !raTraite);

    /* 3. Notification */
    const aNotif=(a.lettre_notification||'').trim();
    d += ligneDoc('#E8890C','bi-envelope-paper','3. Lettre de notification',
         aNotif ? ('Transmise le '+fmtDate(a.date_notification)) : 'Aucune lettre de notification enregistree',
         aNotif ? btnDoc({type:'notification', idaudit:a.idaudit}, 'Notification '+(a.num_audit||''), '#E8890C')
                : '<a class="btn btn-sm btn-outline-secondary" href="'+AGAI_BASE+'/notifications" target="_blank"><i class="bi bi-box-arrow-up-right me-1"></i>Module Notifications</a>',
         !aNotif);

    /* 4. Rapport : si un PDF est joint, on le consulte ; sinon, si le rapport a
       ete saisi en ligne, on ouvre la vue imprimable (module rapport-pdf). */
    const aRap=(a.rapport_audit||'').trim();
    const aRapSaisi = Number(a.rapport_saisi||0) > 0 || (a.date_delivrance_rapport||'').trim()!=='';
    let bRap='', sRap='';
    if(aRap){
      bRap = btnDoc({type:'rapport', idaudit:a.idaudit}, 'Rapport '+(a.num_audit||''), '#23408F');
      sRap = 'Rapport joint au format PDF'+(a.date_delivrance_rapport?(' - remis le '+fmtDate(a.date_delivrance_rapport)):'');
    } else if(aRapSaisi){
      bRap = '<a class="btn btn-sm btn-outline-primary" href="'+AGAI_BASE+'/rapport-pdf?audit='+esc(a.idaudit)+'" target="_blank"><i class="bi bi-eye me-1"></i>Consulter</a>';
      sRap = 'Rapport saisi en ligne'+(a.date_delivrance_rapport?(' - remis le '+fmtDate(a.date_delivrance_rapport)):'');
    } else {
      sRap = 'Aucun rapport joint ni saisi';
    }
    d += ligneDoc('#23408F','bi-file-earmark-pdf','4. Rapport d\'acte de supervision', esc(sRap),
         bRap || '<a class="btn btn-sm btn-outline-secondary" href="'+AGAI_BASE+'/rapports" target="_blank"><i class="bi bi-box-arrow-up-right me-1"></i>Module Rapports</a>',
         !(aRap||aRapSaisi));

    /* 5 et 7 (calcul commun) : FNC et pieces complementaires */
    let bFnc='', bAnx='', nbS=0, nbA=0;
    fnc.forEach(function(f){
      // Consultation en PDF imprimable (lecture seule), pour ne pas risquer de
      // modifier la fiche via la modale de suivi. Si un PDF signe est joint, on
      // le consulte ; sinon on ouvre l'apercu imprimable de la fiche (print).
      const fSigne=(f.fichier_fnc||'').trim();
      if(fSigne){ nbS++; }
      if(fSigne){
        bFnc += btnDoc({type:'fnc', idfnc:f.idfnc}, 'FNC '+(f.num_fnc||''), '#D32F2F');
      } else {
        bFnc += '<a class="btn btn-sm btn-outline-danger" href="'+AGAI_BASE+'/ouverture-nc?print='+esc(f.idfnc)+'" target="_blank" title="Consulter la fiche '+esc(f.num_fnc||'')+' en PDF"><i class="bi bi-printer me-1"></i>FNC '+esc(f.num_fnc||'')+'</a> ';
      }
      if((f.preuve_suivi||'').trim()){ nbA++; bAnx += btnDoc({type:'preuve', idfnc:f.idfnc}, 'Preuve '+(f.num_fnc||''), '#1E9C4B'); }
      if((f.autres_documents||'').trim()){ nbA++; bAnx += btnDoc({type:'autres', idfnc:f.idfnc}, 'Autres '+(f.num_fnc||''), '#b58a00'); }
    });

    /* 5. FNC */
    d += ligneDoc('#D32F2F','bi-exclamation-triangle','5. Fiches de non-conformite',
         fnc.length ? (fnc.length+' fiche(s) de non-conformite - '+nbS+' document(s) signe(s) joint(s)') : 'Aucune fiche de non-conformite pour cet acte',
         bFnc || '<a class="btn btn-sm btn-outline-secondary" href="'+AGAI_BASE+'/ouverture-nc" target="_blank"><i class="bi bi-box-arrow-up-right me-1"></i>Module FNC</a>',
         !fnc.length);

    /* 6. Checklists */
    const aChk=(a.checklist_signee||'').trim();
    d += ligneDoc('#1E9C4B','bi-list-check','6. Listes de verification signees',
         aChk ? 'Scannees en un document unique, signees par les inspecteurs' : 'Aucune liste de verification jointe',
         aChk ? btnDoc({type:'checklist', idaudit:a.idaudit}, 'Checklist '+(a.num_audit||''), '#1E9C4B')
              : '<a class="btn btn-sm btn-outline-secondary" href="'+AGAI_BASE+'/rapports" target="_blank"><i class="bi bi-box-arrow-up-right me-1"></i>Module Rapports</a>',
         !aChk);

    /* 7. Autres pieces (preuves de suivi et documents complementaires) */
    d += ligneDoc('#b58a00','bi-folder2-open','7. Autres pieces (preuves de suivi et complements)',
         nbA ? (nbA+' document(s) complementaire(s)') : 'Aucune preuve de suivi ni piece complementaire',
         bAnx || '<a class="btn btn-sm btn-outline-secondary" href="'+AGAI_BASE+'/ouverture-nc" target="_blank"><i class="bi bi-box-arrow-up-right me-1"></i>Module FNC</a>',
         !nbA);

    $('#dsBody').html(h + d + '</div>');
  });
});

/* ============================================================
 *  FICHE DE MANDAT D'ACTE DE SUPERVISION
 *  Reprend la mise en page du module Audits et inspections.
 * ============================================================ */
$(document).on('click','#btnMandat',function(){
  if(!DOSSIER){ return; }
  const a  = DOSSIER.audit || {};
  const eq = DOSSIER.equipe || [];
  const rg = DOSSIER.reglements || [];
  const re = DOSSIER.reg_equipe || [];

  /* Reglements rattaches a chaque inspecteur */
  const parInsp = {};
  re.forEach(function(x){
    const k = String(x.idinspecteur);
    if(!parInsp[k]) parInsp[k] = [];
    if(x.code_reglement && parInsp[k].indexOf(x.code_reglement) < 0) parInsp[k].push(x.code_reglement);
  });

  const raNom = (a.responsable||'').trim();
  let lignesEq = '';
  eq.forEach(function(m){
    const estRa = raNom && (m.nom||'').trim() === raNom;
    lignesEq += '<tr><td>'+esc(m.nom||'-')+(estRa?' <strong>(R.A)</strong>':'')+'</td>'
             +  '<td>'+esc(m.nomdomaine||'-')+'</td>'
             +  '<td>'+esc((parInsp[String(m.idinspecteur)]||[]).join(', ')||'-')+'</td></tr>';
  });
  if(!lignesEq){ lignesEq = '<tr><td colspan="3" style="text-align:center;color:#888">Aucun membre enregistre</td></tr>'; }

  let lignesRg = '';
  rg.forEach(function(r){
    lignesRg += '<div style="padding:3px 0;border-bottom:1px solid #eef2f7">'
             +  '<strong>'+esc(r.code_reglement||'')+'</strong>'
             +  (r.libelle_reglement ? ' - '+esc(r.libelle_reglement) : '') + '</div>';
  });
  if(!lignesRg){ lignesRg = '<div style="color:#888">Aucun reglement vise</div>'; }

  const dv = function(l, v){
    return '<div><div class="dl">'+esc(l)+'</div><div class="dv">'+(v||'-')+'</div></div>';
  };

  // QR code d'authentification (meme methode que le module Audits)
  const qrMandat = (function(){
    const lignes = [
      'ANAC GABON - AGAI (Systeme securise)',
      'FICHE DE MANDAT D ACTE DE SUPERVISION',
      'N Audit : ' + (a.num_audit || '-'),
      'Nature : ' + (TYPE_LABELS[a.type_activite] || a.type_activite || '-'),
      'Cadre : ' + (CADRE_LABELS[a.cadre] || a.cadre || '-'),
      'Operateur : ' + (a.nomorga || '-'),
      'Responsable audit : ' + (a.responsable || '-'),
      'Date previsionnelle : ' + (a.date_previsionnelle ? fmtDate(a.date_previsionnelle) : '-'),
      'Date realisation : ' + (a.date_realisation ? fmtDate(a.date_realisation) : '-'),
      'Document authentifie AGAI'
    ];
    const sansAccents = function(s){
      return String(s).normalize('NFD').replace(/[\u0300-\u036f]/g,'')
        .replace(/[^\x20-\x7E]/g,' ').replace(/\s+/g,' ').trim();
    };
    const texte = lignes.map(sansAccents).join('\n');
    const QR = window.QRCode || (typeof QRCode!=='undefined' ? QRCode : null);
    if(!QR) return '';
    const niveau = (QR.CorrectLevel && QR.CorrectLevel.L!=null) ? QR.CorrectLevel.L : 1;
    const box = document.createElement('div');
    for(let tn=1; tn<=40; tn++){
      try{
        box.innerHTML='';
        new QR(box, { text:texte, typeNumber:tn, width:110, height:110,
          colorDark:'#000000', colorLight:'#ffffff', correctLevel:niveau });
        const img = box.querySelector('img'), cv = box.querySelector('canvas');
        if(img && img.src) return img.src;
        if(cv) return cv.toDataURL('image/png');
      }catch(e){}
    }
    return '';
  })();

  const w = window.open('', '_blank', 'width=980,height=800');
  w.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8">'
    +'<title>Mandat '+esc(a.num_audit||'')+'</title><style>'
    +'@page{size:A4 portrait;margin:9mm}'
    +'*{box-sizing:border-box}'
    +'body{font-family:Candara,"Segoe UI",Arial,sans-serif;color:#1e293b;margin:0;font-size:10pt}'
    +'.page{border:3px solid #23408F;padding:10px;min-height:270mm}'
    +'.hdr{border-bottom:3px solid #23408F;padding-bottom:8px;margin-bottom:12px;text-align:center}'
    +'.hdr img{max-height:60px;width:auto}'
    +'.ref{text-align:right;font-size:8pt;color:#555;margin-bottom:4px}'
    +'h1{text-align:center;font-size:13pt;font-weight:700;text-transform:uppercase;color:#23408F;'
    +'margin:6px 0 12px;letter-spacing:.03em}'
    +'.sec{margin-bottom:12px;break-inside:avoid}'
    +'.sh{background:#23408F;color:#fff;padding:7px 12px;font-weight:700;font-size:9pt;'
    +'-webkit-print-color-adjust:exact;print-color-adjust:exact}'
    +'.sb{border:1px solid #dde4f0;border-top:none;padding:10px 12px}'
    +'.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:6px 16px}'
    +'.grid-2{display:grid;grid-template-columns:repeat(2,1fr);gap:6px 16px}'
    +'.dl{font-size:7.5pt;text-transform:uppercase;color:#64748b;font-weight:700;letter-spacing:.03em;margin-bottom:1px}'
    +'.dv{font-size:9.5pt;font-weight:600;color:#1e293b;border-bottom:1px solid #e8edf5;padding-bottom:2px}'
    +'table.t{width:100%;border-collapse:collapse;font-size:9pt}'
    +'table.t th{background:#23408F;color:#fff;padding:5px 8px;text-align:left;'
    +'-webkit-print-color-adjust:exact;print-color-adjust:exact}'
    +'table.t td{padding:4px 8px;border-bottom:1px solid #dde}'
    +'@media print{.page{border:3px solid #23408F}}'
    +'</style></head><body><div class="page">'
    +'<div class="ref">Audit AGAI - ANAC Gabon - '+new Date().toLocaleDateString('fr-FR')+'</div>'
    +'<div class="hdr"><img src="'+IMG_BASE+'banierenteanac.png" onerror="this.style.display=\'none\'"></div>'
    +'<h1>Fiche de mandat d\'acte de supervision</h1>'

    +'<div class="sec"><div class="sh">Informations generales</div><div class="sb"><div class="grid">'
    +  dv('N Audit', '<strong>'+esc(a.num_audit||'')+'</strong>')
    +  dv('Nature',  esc(TYPE_LABELS[a.type_activite]||a.type_activite||''))
    +  dv('Cadre',   esc(CADRE_LABELS[a.cadre]||a.cadre||''))
    +  dv('Statut',  esc(STATUT_LBL[a.statut]||''))
    +  dv('Activite operateur', esc(a.type_activite_operateur||''))
    +  dv('Site',    esc(a.ville||a.nomsite||a.indicateur_oaci||a.site_inspection||''))
    +'</div></div></div>'

    +'<div class="sec"><div class="sh">Operateur et responsable</div><div class="sb"><div class="grid-2">'
    +  dv('Operateur', '<strong>'+esc(a.nomorga||'')+'</strong>'
        + (a.trigrorganisme ? ' ('+esc(a.trigrorganisme)+')' : ''))
    +  dv('Responsable d\'Audit (R.A)', esc(a.responsable||''))
    +'</div></div></div>'

    +'<div class="sec"><div class="sh">Planification</div><div class="sb"><div class="grid">'
    +  dv('Date previsionnelle', fmtDate(a.date_previsionnelle))
    +  dv('Date realisation',    fmtDate(a.date_realisation))
    +  dv('Delai execution',     (a.delai_execution===null||a.delai_execution===undefined||a.delai_execution==='')
                                  ? '-' : (a.delai_execution+' j'))
    +  dv('Date rapport',        fmtDate(a.date_delivrance_rapport))
    +  dv('Date notification',   fmtDate(a.date_notification))
    +'</div></div></div>'

    +'<div class="sec"><div class="sh">Equipe d\'audit</div><div class="sb">'
    +'<table class="t"><thead><tr><th>Inspecteur</th><th>Domaine</th><th>Reglements</th></tr></thead>'
    +'<tbody>'+lignesEq+'</tbody></table></div></div>'

    +'<div class="sec"><div class="sh">Reglements vises</div><div class="sb">'+lignesRg+'</div></div>'

    +'<div style="margin-top:22px;display:flex;justify-content:space-between;align-items:flex-end;font-size:9pt">'
    +(qrMandat
        ? '<div style="text-align:center"><img src="'+qrMandat+'" style="width:26mm;height:26mm" alt="QR"><div style="font-size:7pt;color:#64748b;margin-top:2px">Authentification AGAI</div></div>'
        : '<div></div>')
    +'<div style="text-align:right"><strong>Visa du Chef Inspecteur</strong><br><br><br>_______________________</div>'
    +'</div>'

    +'</div>'
    +'<scr'+'ipt>window.onload=function(){setTimeout(function(){window.print();},400);};</scr'+'ipt>'
    +'</body></html>');
  w.document.close(); w.focus();
});

/* ---------- Filtres ---------- */
$('#fNum,#fAnnee,#fOrga,#fType,#fRa,#fEtat').select2({theme:'bootstrap-5', width:'100%'});
$('#fNum,#fAnnee,#fOrga,#fType,#fRa,#fEtat').on('change', function(){ FILTRE_ETAT=''; $('#arKpi .ar-card').removeClass('on'); rendre(); });
$('#btnReset').on('click', function(){
  $('#fNum,#fAnnee,#fOrga,#fType,#fRa,#fEtat').val('').trigger('change.select2');
  FILTRE_ETAT=''; $('#arKpi .ar-card').removeClass('on'); rendre();
});
$(document).on('click','#arKpi .ar-card',function(){
  const f=String($(this).data('f')||''); const actif=$(this).hasClass('on');
  $('#arKpi .ar-card').removeClass('on'); $('#fEtat').val('').trigger('change.select2');
  if(f && !actif){ $(this).addClass('on'); FILTRE_ETAT=f; } else { FILTRE_ETAT=''; }
  rendre();
});
$('#btnRefresh').on('click', charger);

$('#guideArchToggle').on('click',function(){
  $('#guideArchBody').slideToggle(180);
  $('#guideArchChevron').css('transform', $('#guideArchBody').is(':visible')?'rotate(0deg)':'rotate(-90deg)');
});

/* ---------- Exports ---------- */
const AR_COLS = ['N audit','Annee','Nature','Operateur','Responsable',
                 'Mandat','Revue','Notification','Rapport','FNC','Checklist','Autres','QRE',
                 'Pieces presentes','Etat du dossier','Pieces manquantes'];
function etatTxt(v){ return v==='ok' ? 'Present' : (v==='partiel' ? 'Incomplet' : 'Absent'); }
function arLignes(){
  return filtrees().map(function(a){
    const c=completude(a), p=c.p;
    return [ a.num_audit||'', a.annee||'', a.type_activite||'', a.nomorga||'', a.responsable||'',
             etatTxt(p.mandat), etatTxt(p.revue), etatTxt(p.notif), etatTxt(p.rapport),
             etatTxt(p.fnc), etatTxt(p.checklist), etatTxt(p.annexes), etatTxt(p.qre),
             c.ok+'/'+c.total,
             (c.etat==='complet'?'Complet':(c.etat==='vide'?'Aucune piece':'Incomplet')),
             manquantes(a).join(' ; ') ];
  });
}
function arDateJour(){
  const d=new Date();
  const m=['janvier','fevrier','mars','avril','mai','juin','juillet','aout','septembre','octobre','novembre','decembre'];
  return d.getDate()+' '+m[d.getMonth()]+' '+d.getFullYear();
}
function arTableHTML(){
  let h='<table style="width:100%;border-collapse:collapse;font-family:Candara,Arial;font-size:9px">'
      + '<thead><tr>'+AR_COLS.map(function(c){
          return '<td bgcolor="#23408F" style="background-color:#23408F;color:#FFFFFF;font-weight:bold;'
               + 'padding:5px 6px;border:1px solid #1b3576;text-align:center;font-size:8px">'+esc(c)+'</td>';
        }).join('')+'</tr></thead><tbody>';
  arLignes().forEach(function(l,i){
    const bg=(i%2)?'#f7f9fc':'#ffffff';
    h += '<tr>'+l.map(function(v){
      return '<td bgcolor="'+bg+'" style="background-color:'+bg+';padding:4px 6px;border:1px solid #d6e0f2">'+esc(String(v==null?'':v))+'</td>';
    }).join('')+'</tr>';
  });
  return h+'</tbody></table>';
}
$('#btnXls').on('click',function(){
  const n=filtrees().length;
  if(!n){ Swal.fire({icon:'info',title:'Aucune donnee',text:'Le tableau est vide pour ces criteres.',confirmButtonColor:'#23408F'}); return; }
  const html='<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8"></head><body>'
    + '<table><tr><td colspan="'+AR_COLS.length+'" style="font-family:Candara;font-size:15pt;font-weight:bold;color:#23408F;text-align:center">'
    +   'ARCHIVAGE DOCUMENTAIRE DES ACTES DE SUPERVISION</td></tr>'
    + '<tr><td colspan="'+AR_COLS.length+'" style="font-family:Candara;font-size:9pt;color:#5b6b85;text-align:center">'
    +   'Agence Nationale de l\'Aviation Civile du Gabon &middot; Edite le '+arDateJour()+' &middot; '+n+' acte(s)</td></tr>'
    + '<tr><td colspan="'+AR_COLS.length+'"></td></tr></table>' + arTableHTML() + '</body></html>';
  const blob=new Blob(['\ufeff'+html],{type:'application/vnd.ms-excel'});
  const a=document.createElement('a'); a.href=URL.createObjectURL(blob);
  a.download='Archivage_actes_'+new Date().toISOString().substring(0,10)+'.xls';
  document.body.appendChild(a); a.click(); a.remove();
});
$('#btnPdf').on('click',function(){
  const n=filtrees().length;
  if(!n){ Swal.fire({icon:'info',title:'Aucune donnee',text:'Le tableau est vide pour ces criteres.',confirmButtonColor:'#23408F'}); return; }
  const w=window.open('','_blank','width=1200,height=820');
  w.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Archivage</title>'
    + '<style>@page{size:A4 landscape;margin:8mm}body{font-family:Candara,Arial;color:#2C3E50;margin:0}'
    + '.ban{width:100%;max-height:100px;object-fit:contain;display:block;margin-bottom:6px}'
    + 'h2{color:#23408F;text-align:center;font-size:14px;text-transform:uppercase;margin:4px 0 2px}'
    + '.st{text-align:center;color:#6b7a90;font-size:9px;margin-bottom:9px}'
    + '.pied{margin-top:9px;border-top:2px solid #1E9C4B;padding-top:5px;font-size:8.5px;color:#6b7a90;display:flex;justify-content:space-between}'
    + '</style></head><body>'
    + '<img class="ban" src="'+AGAI_BASE+'/public/images/banierenteanac.png" onerror="this.style.display=\'none\'">'
    + '<h2>Archivage documentaire des actes de supervision</h2>'
    + '<div class="st">Agence Nationale de l\'Aviation Civile du Gabon &middot; Edite le '+arDateJour()+'</div>'
    + arTableHTML()
    + '<div class="pied"><span>AGAI &middot; Controle de completude documentaire</span><span><strong>'+n+'</strong> acte(s)</span></div>'
    + '<scr'+'ipt>window.onload=function(){setTimeout(function(){window.print();},400);};</scr'+'ipt>'
    + '</body></html>');
  w.document.close(); w.focus();
});

charger();
</script>