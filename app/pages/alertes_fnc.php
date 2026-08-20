<?php
/**
 * Page : Alertes FNC - suivi des echeances de non-conformite
 * Module : alertes_fnc  -  Route : /alertes-fnc
 *
 * Compare les dates de reponse exigee et de mise en conformite a la date
 * du jour, classe les fiches par urgence et permet d'envoyer une relance
 * a l'inspecteur en charge du suivi.
 *
 * Securite : Rbac::guardPage, CSRF sur chaque appel, sorties echappees,
 * calculs de dates cote serveur, envoi de courriel reserve au CI/admin.
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardPage('alertes_fnc');

$csrf      = Security::generateCSRF();
$role      = Rbac::role();
$isCI      = in_array($role, ['admin', 'chef_inspecteur'], true);
$pageTitle = 'Alertes FNC';
$active    = 'alertes_fnc';
$pageIcon  = 'bi-bell';
require_once INCLUDES_PATH . '/layout_head.php';
?>
<style>
.al-card{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:13px 15px;border-left:4px solid #23408F;
         cursor:pointer;transition:.15s;height:100%}
.al-card:hover{transform:translateY(-2px);box-shadow:0 8px 18px rgba(35,64,143,.14)}
.al-card.on{box-shadow:0 0 0 2px rgba(35,64,143,.3)}
.al-num{font-size:1.6rem;font-weight:800;line-height:1;color:#2C3E50}
.al-lbl{font-size:.72rem;color:#6b7a90;text-transform:uppercase;letter-spacing:.3px;font-weight:600;margin-top:3px}
.al-b-red{border-left-color:#D32F2F}.al-b-org{border-left-color:#E8890C}
.al-b-blue{border-left-color:#23408F}.al-b-green{border-left-color:#1E9C4B}
.flbl{font-size:.72rem;font-weight:700;color:#5b6b85;text-transform:uppercase;margin-bottom:3px}
#alTable thead th{background:#23408F;color:#fff;text-transform:uppercase;letter-spacing:.4px;
                  font-size:.7rem;font-weight:700;padding:9px 10px;border:none}
#alTable td{padding:8px 10px;font-size:.83rem;border-bottom:1px solid #f0f3f8;vertical-align:middle}
.pill{padding:2px 10px;border-radius:20px;font-size:.72rem;font-weight:700;white-space:nowrap}
.ligne-retard{background:#fff5f5}
.ligne-urgent{background:#fffaf0}
</style>

<div class="page-head">
  <div>
    <h1><i class="bi bi-bell me-2" style="color:#D32F2F"></i>Alertes FNC</h1>
    <div class="sub">Echeances de reponse et de mise en conformite comparees a la date du jour.</div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if ($isCI): ?>
    <button class="btn btn-outline-primary" id="btnRelanceSel" disabled>
      <i class="bi bi-envelope-paper me-1"></i>Relancer la selection (<span id="nbSel">0</span>)</button>
    <?php endif; ?>
    <button class="btn btn-outline-danger" id="btnPdf"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</button>
    <button class="btn btn-outline-success" id="btnXls"><i class="bi bi-file-earmark-excel me-1"></i>Excel</button>
    <button class="btn btn-outline-secondary" id="btnRafraichir"><i class="bi bi-arrow-clockwise me-1"></i>Actualiser</button>
  </div>
</div>

<!-- Indicateurs cliquables -->
<div class="row g-2 mb-3" id="alKpi">
  <div class="col-6 col-md-2"><div class="al-card al-b-red"   data-f="retard"><div class="al-num" id="k_retard" style="color:#D32F2F">-</div><div class="al-lbl">En retard</div></div></div>
  <div class="col-6 col-md-2"><div class="al-card al-b-org"   data-f="urgent"><div class="al-num" id="k_urgent" style="color:#E8890C">-</div><div class="al-lbl">Sous 7 jours</div></div></div>
  <div class="col-6 col-md-2"><div class="al-card al-b-blue"  data-f="proche"><div class="al-num" id="k_proche" style="color:#23408F">-</div><div class="al-lbl">Sous 30 jours</div></div></div>
  <div class="col-6 col-md-2"><div class="al-card al-b-green" data-f="ok"><div class="al-num" id="k_ok" style="color:#1E9C4B">-</div><div class="al-lbl">Au-dela de 30 j</div></div></div>
  <div class="col-6 col-md-2"><div class="al-card"            data-f="sansdate"><div class="al-num" id="k_sansdate" style="color:#7A8798">-</div><div class="al-lbl">Sans echeance</div></div></div>
  <div class="col-6 col-md-2"><div class="al-card"            data-f=""><div class="al-num" id="k_total">-</div><div class="al-lbl">Total suivi</div></div></div>
</div>

<!-- Filtres -->
<div class="card-anac p-3 mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-md-2"><div class="flbl">N FNC</div><select id="fFnc" style="width:100%"></select></div>
    <div class="col-md-2"><div class="flbl">N Audit</div><select id="fAud" style="width:100%"></select></div>
    <div class="col-md-1"><div class="flbl">Annee</div><select id="fAnnee" style="width:100%"></select></div>
    <div class="col-md-2"><div class="flbl">Operateur</div><select id="fOrga" style="width:100%"></select></div>
    <div class="col-md-2"><div class="flbl">Domaine</div><select id="fDom" style="width:100%"></select></div>
    <div class="col-md-2"><div class="flbl">Categorie</div>
      <select id="fCat" style="width:100%">
        <option value="">Toutes</option><option value="critique">Critique</option>
        <option value="majeur">Majeur</option><option value="mineur">Mineur</option>
      </select></div>
    <div class="col-md-2"><div class="flbl">Statut</div>
      <select id="fStatut" style="width:100%">
        <option value="">Tous (hors fermees)</option>
        <option value="4">Ouvert</option>
        <option value="1">Accepte non verifie</option>
        <option value="2">Rejete</option>
      </select></div>
    <div class="col-md-2"><div class="flbl">Inspecteur en charge</div><select id="fInsp" style="width:100%"></select></div>
    <div class="col-md-1"><button class="btn btn-sm btn-outline-secondary w-100" id="btnReset" title="Reinitialiser"><i class="bi bi-x-lg"></i></button></div>
  </div>
</div>

<!-- Tableau -->
<div class="card-anac p-0" style="overflow:hidden">
  <div style="background:#f8fafc;padding:10px 15px;border-bottom:1px solid #eef1f6;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <strong style="font-size:.85rem;color:#23408F"><i class="bi bi-list-check me-1"></i>Fiches a echeance</strong>
    <span style="margin-left:auto;font-size:.74rem;color:#6b7a90" id="alResume"></span>
  </div>
  <div class="table-responsive">
    <table id="alTable" style="width:100%;border-collapse:collapse">
      <thead><tr>
        <?php if ($isCI): ?><th style="width:34px"><input type="checkbox" id="chkAll" title="Tout selectionner"></th><?php endif; ?>
        <th>N FNC</th><th>Audit</th><th>Operateur</th><th>Domaine</th><th>Categorie</th><th>Statut</th>
        <th>Reponse exigee</th><th>Mise en conformite</th><th>Echeance la plus proche</th>
        <th>Inspecteur en charge</th><th class="text-end">Actions</th>
      </tr></thead>
      <tbody><tr><td colspan="12" style="padding:30px;text-align:center;color:#9aa7bd">
        <span class="spinner-border spinner-border-sm me-2"></span>Chargement...</td></tr></tbody>
    </table>
  </div>
</div>

<!-- MODALE : detail d'une alerte -->
<div class="modal fade" id="modalDetailAl" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,#23408F,#1b3576)">
        <h5 class="modal-title text-white"><i class="bi bi-info-circle me-2" style="color:#F3C300"></i>
          Detail de la fiche - <span id="dtNum">-</span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="background:#f5f7fa"><div id="dtBody"></div></div>
    </div>
  </div>
</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>
<script>
const CSRF  = '<?php echo Security::escape($csrf); ?>';
const API   = AGAI_BASE + '/api/nonconformites';
const IS_CI = <?php echo $isCI ? 'true' : 'false'; ?>;
function apiPost(d){ return $.post(API, Object.assign({csrf_token:CSRF}, d), null, 'json'); }
function esc(s){ const d=document.createElement('div'); d.textContent=(s==null?'':s); return d.innerHTML; }
function fmtDate(d){ const s=String(d||'').substring(0,10);
  return /^\d{4}-\d{2}-\d{2}$/.test(s) ? s.substring(8,10)+'/'+s.substring(5,7)+'/'+s.substring(0,4) : '-'; }

const ST_LBL={1:'Accepte non verifie',2:'Rejete',3:'Ferme',4:'Ouvert'};
const ST_BG ={1:'#fef3c7',2:'#fee2e2',3:'#d1fae5',4:'#e8f0fe'};
const ST_TC ={1:'#92400e',2:'#991b1b',3:'#065f46',4:'#23408F'};
const CAT_BG={critique:'#fee2e2',majeur:'#fef3c7',mineur:'#dbeafe'};
const CAT_TC={critique:'#991b1b',majeur:'#92400e',mineur:'#1e40af'};
let ALERTES = [], FILTRE_URGENCE = '';

/* Urgence : calculee sur l'echeance la plus proche des deux dates */
function urgenceDe(f){
  const j = joursProches(f);
  if(j === null) return 'sansdate';
  if(j < 0)  return 'retard';
  if(j <= 7) return 'urgent';
  if(j <= 30) return 'proche';
  return 'ok';
}
function joursProches(f){
  const v = [f.j_reponse, f.j_limite].filter(function(x){ return x !== null && x !== undefined; });
  if(!v.length) return null;
  return Math.min.apply(null, v.map(Number));
}
function badgeJours(j){
  if(j === null) return '<span class="pill" style="background:#eef2f7;color:#5b6b85">Sans echeance</span>';
  if(j < 0)  return '<span class="pill" style="background:#D32F2F;color:#fff">Retard de '+Math.abs(j)+' j</span>';
  if(j === 0) return '<span class="pill" style="background:#D32F2F;color:#fff">Aujourd\'hui</span>';
  if(j <= 7) return '<span class="pill" style="background:#E8890C;color:#fff">Dans '+j+' j</span>';
  if(j <= 30) return '<span class="pill" style="background:#23408F;color:#fff">Dans '+j+' j</span>';
  return '<span class="pill" style="background:#e8f5ec;color:#157a3a">Dans '+j+' j</span>';
}

/* ---------- Chargement ---------- */
function charger(){
  apiPost({action:'alertes'}).done(function(res){
    if(!res || !res.success){
      $('#alTable tbody').html('<tr><td colspan="12" style="padding:24px;text-align:center;color:#D32F2F">'
        + esc((res && res.message) || 'Chargement impossible') + '</td></tr>');
      return;
    }
    ALERTES = res.data || [];
    majKpi(); majFiltres(); rendre();
  }).fail(function(){
    $('#alTable tbody').html('<tr><td colspan="12" style="padding:24px;text-align:center;color:#D32F2F">Echec de la requete.</td></tr>');
  });
}

function majKpi(){
  const c = {retard:0, urgent:0, proche:0, ok:0, sansdate:0};
  ALERTES.forEach(function(f){ c[urgenceDe(f)]++; });
  $('#k_retard').text(c.retard);   $('#k_urgent').text(c.urgent);
  $('#k_proche').text(c.proche);   $('#k_ok').text(c.ok);
  $('#k_sansdate').text(c.sansdate); $('#k_total').text(ALERTES.length);
}

function majFiltres(){
  const uniq=function(t){ return [...new Set(ALERTES.map(function(f){ return (f[t]||'').trim(); }).filter(Boolean))].sort(); };
  const fill=function(sel, vals, lbl){
    const cur=$(sel).val();
    $(sel).html('<option value="">'+lbl+'</option>'+vals.map(function(v){ return '<option value="'+esc(v)+'">'+esc(v)+'</option>'; }).join(''));
    if(cur && vals.indexOf(cur)>=0) $(sel).val(cur);
    $(sel).trigger('change.select2');
  };
  fill('#fFnc',  uniq('num_fnc'),   'Toutes');
  fill('#fAud',  uniq('num_audit'), 'Tous');
  fill('#fAnnee',[...new Set(ALERTES.map(function(f){ return String(f.annee||''); }).filter(Boolean))].sort().reverse(), 'Toutes');
  fill('#fOrga', uniq('nomorga'),   'Tous');
  fill('#fDom',  uniq('nomdomaine'),'Tous');
  const insps=[...new Set(ALERTES.map(function(f){ return (f.nom_agent_suivi||f.nom_inspecteur||'').trim(); }).filter(Boolean))].sort();
  fill('#fInsp', insps, 'Tous');
}

function filtrees(){
  const fo=$('#fOrga').val()||'', fd=$('#fDom').val()||'', fc=$('#fCat').val()||'', fi=$('#fInsp').val()||'';
  const fn=$('#fFnc').val()||'', fa=$('#fAud').val()||'', fy=$('#fAnnee').val()||'', fs=$('#fStatut').val()||'';
  return ALERTES.filter(function(f){
    if(FILTRE_URGENCE && urgenceDe(f)!==FILTRE_URGENCE) return false;
    if(fn && String(f.num_fnc||'')!==fn) return false;
    if(fa && String(f.num_audit||'')!==fa) return false;
    if(fy && String(f.annee||'')!==fy) return false;
    if(fs && String(f.statut)!==fs) return false;
    if(fo && (f.nomorga||'')!==fo) return false;
    if(fd && (f.nomdomaine||'')!==fd) return false;
    if(fc && (f.categorie||'')!==fc) return false;
    if(fi && ((f.nom_agent_suivi||f.nom_inspecteur||'')!==fi)) return false;
    return true;
  });
}

function rendre(){
  const list = filtrees().sort(function(a,b){
    const ja=joursProches(a), jb=joursProches(b);
    if(ja===null) return 1; if(jb===null) return -1;
    return ja-jb;
  });
  if(!list.length){
    $('#alTable tbody').html('<tr><td colspan="12" style="padding:30px;text-align:center;color:#9aa7bd">'
      + '<i class="bi bi-check-circle me-2 text-success"></i>Aucune fiche pour ces criteres.</td></tr>');
    $('#alResume').text(''); majSelection();
    return;
  }
  let h='';
  list.forEach(function(f){
    const j = joursProches(f), u = urgenceDe(f);
    const cls = u==='retard' ? 'ligne-retard' : (u==='urgent' ? 'ligne-urgent' : '');
    const insp = (f.nom_agent_suivi||f.nom_inspecteur||'-');
    const mail = (f.mail_agent_suivi||f.mail_inspecteur||'');
    h += '<tr class="'+cls+'">'
      + (IS_CI ? '<td><input type="checkbox" class="chk-fnc" value="'+esc(f.idfnc)+'"></td>' : '')
      + '<td style="font-family:monospace;font-weight:700;color:#23408F">'+esc(f.num_fnc)+'</td>'
      + '<td style="font-size:.78rem">'+esc(f.num_audit||'-')+'</td>'
      + '<td>'+esc(f.nomorga||'-')+'</td>'
      + '<td><span class="pill" style="background:#eef3fb;color:#23408F">'+esc(f.nomdomaine||'-')+'</span></td>'
      + '<td><span class="pill" style="background:'+(CAT_BG[f.categorie]||'#f1f5f9')+';color:'+(CAT_TC[f.categorie]||'#555')+'">'+esc(f.categorie||'-')+'</span></td>'
      + '<td><span class="pill" style="background:'+(ST_BG[f.statut]||'#f1f5f9')+';color:'+(ST_TC[f.statut]||'#555')+'">'+esc(ST_LBL[f.statut]||'-')+'</span></td>'
      + '<td style="font-size:.79rem">'+fmtDate(f.date_reponse_exigee)+'</td>'
      + '<td style="font-size:.79rem">'+fmtDate(f.date_limite_mise_conformite)+'</td>'
      + '<td>'+badgeJours(j)+'</td>'
      + '<td style="font-size:.79rem">'+esc(insp)
      +   (mail?'':'<br><span style="font-size:.7rem;color:#D32F2F"><i class="bi bi-exclamation-triangle me-1"></i>sans adresse</span>')
      + '</td>'
      + '<td class="text-end">'
      +   '<button class="btn btn-sm btn-outline-secondary me-1 btn-detail-al" data-id="'+esc(f.idfnc)+'" title="Voir le detail"><i class="bi bi-eye"></i></button>'
      +   (IS_CI ? '<button class="btn btn-sm btn-outline-primary me-1 btn-relance-un" data-id="'+esc(f.idfnc)+'" title="Relancer l\'inspecteur"><i class="bi bi-envelope"></i></button>' : '')
      +   '<a class="btn btn-sm btn-outline-secondary" href="'+AGAI_BASE+'/ouverture-nc" title="Ouvrir le module"><i class="bi bi-box-arrow-up-right"></i></a>'
      + '</td></tr>';
  });
  $('#alTable tbody').html(h);
  $('#alResume').text(list.length + ' fiche(s) affichee(s) sur ' + ALERTES.length);
  majSelection();
}

/* ---------- Selection et relance ---------- */
function majSelection(){
  const n = $('.chk-fnc:checked').length;
  $('#nbSel').text(n);
  $('#btnRelanceSel').prop('disabled', n === 0);
}
$(document).on('change', '.chk-fnc', majSelection);
$(document).on('change', '#chkAll', function(){
  $('.chk-fnc').prop('checked', $(this).is(':checked')); majSelection();
});

function envoyerRelance(ids){
  if(!ids.length) return;
  Swal.fire({
    title:'Envoyer la relance ?',
    html:'Un courriel sera adresse a l\'inspecteur en charge du suivi pour <strong>'+ids.length+'</strong> fiche(s).',
    icon:'question', showCancelButton:true, cancelButtonText:'Annuler',
    confirmButtonText:'Envoyer', confirmButtonColor:'#23408F'
  }).then(function(r){
    if(!r.isConfirmed) return;
    Swal.fire({title:'Envoi en cours', html:'Transmission des courriels au serveur de messagerie.',
      allowOutsideClick:false, allowEscapeKey:false, didOpen:function(){ Swal.showLoading(); }});
    apiPost({action:'relance_mail', idfncs:ids}).done(function(res){
      Swal.close();
      if(!res || !res.success){
        Swal.fire({icon:'error',title:'Echec',text:(res&&res.message)||'Envoi impossible.',confirmButtonColor:'#23408F'});
        return;
      }
      Swal.fire({icon:(res.echecs>0?'warning':'success'), title:'Relance traitee',
        text:res.message||'', confirmButtonColor:'#23408F'});
      $('.chk-fnc, #chkAll').prop('checked', false); majSelection();
    }).fail(function(){
      Swal.close();
      Swal.fire({icon:'error',title:'Echec de la requete',confirmButtonColor:'#23408F'});
    });
  });
}
$(document).on('click','.btn-relance-un',function(){ envoyerRelance([$(this).data('id')]); });
$('#btnRelanceSel').on('click',function(){
  envoyerRelance($('.chk-fnc:checked').map(function(){ return $(this).val(); }).get());
});

/* ---------- Filtres ---------- */
$('#fFnc,#fAud,#fAnnee,#fOrga,#fDom,#fCat,#fStatut,#fInsp').select2({theme:'bootstrap-5', width:'100%'});
$('#fFnc,#fAud,#fAnnee,#fOrga,#fDom,#fCat,#fStatut,#fInsp').on('change', rendre);
$('#btnReset').on('click', function(){
  $('#fFnc,#fAud,#fAnnee,#fOrga,#fDom,#fCat,#fStatut,#fInsp').val('').trigger('change.select2');
  FILTRE_URGENCE=''; $('#alKpi .al-card').removeClass('on'); rendre();
});
$(document).on('click','#alKpi .al-card',function(){
  const f = String($(this).data('f')||'');
  const actif = $(this).hasClass('on');
  $('#alKpi .al-card').removeClass('on');
  if(f && !actif){ $(this).addClass('on'); FILTRE_URGENCE = f; } else { FILTRE_URGENCE = ''; }
  rendre();
});
$('#btnRafraichir').on('click', charger);

/* ============================================================
 *  DETAIL D'UNE ALERTE : fiche, audit et equipe d'inspection
 * ============================================================ */
function dRow(lbl, val, large){
  const v=(val===null||val===undefined||String(val).trim()==='')?'<span style="color:#adb5bd">-</span>':val;
  return '<div class="'+(large?'col-12':'col-md-4')+'">'
    +'<div style="font-size:.68rem;font-weight:700;color:#7b8aa0;text-transform:uppercase;letter-spacing:.3px;margin-bottom:2px">'+esc(lbl)+'</div>'
    +'<div style="background:#f5f7fa;border:1px solid #eef1f6;border-radius:7px;padding:6px 10px;font-size:.84rem;min-height:32px">'+v+'</div></div>';
}
function dSec(titre, icone, contenu){
  return '<div style="background:#fff;border:1px solid #e6ebf3;border-radius:12px;padding:14px 16px;margin-bottom:14px">'
    +'<div style="font-size:.8rem;font-weight:800;color:#23408F;text-transform:uppercase;letter-spacing:.4px;'
    +'border-bottom:2px solid #eef3fb;padding-bottom:7px;margin-bottom:11px"><i class="bi '+icone+' me-2"></i>'+esc(titre)+'</div>'
    +'<div class="row g-2">'+contenu+'</div></div>';
}

$(document).on('click','.btn-detail-al',function(){
  const id=$(this).data('id');
  $('#dtBody').html('<div class="text-center py-4"><span class="spinner-border text-primary"></span></div>');
  new bootstrap.Modal('#modalDetailAl').show();
  apiPost({action:'detail_alerte', idfnc:id}).done(function(res){
    if(!res || !res.success){
      $('#dtBody').html('<div class="alert alert-danger">'+esc((res&&res.message)||'Detail indisponible')+'</div>');
      return;
    }
    const f=res.fiche||{}, eq=res.equipe||[], sds=res.sousdomaines||[];
    $('#dtNum').text(f.num_fnc||'-');

    let h = dSec('Fiche de non-conformite','bi-clipboard-check',
        dRow('N FNC','<strong style="color:#D32F2F;font-family:monospace">'+esc(f.num_fnc||'')+'</strong>')
      + dRow('Categorie','<span class="pill" style="background:'+(CAT_BG[f.categorie]||'#f1f5f9')+';color:'+(CAT_TC[f.categorie]||'#555')+'">'+esc(f.categorie||'-')+'</span>')
      + dRow('Date d\'emission', fmtDate(f.date_emission))
      + dRow('Reponse exigee avant le', fmtDate(f.date_reponse_exigee))
      + dRow('Mise en conformite avant le', fmtDate(f.date_limite_mise_conformite))
      + dRow('Domaine', esc(f.nomdomaine||'-'))
      + dRow('Sous-domaine(s)', sds.map(esc).join(', '), true)
      + dRow('Libelle', esc(f.libelle||''), true));

    h += dSec('Acte de supervision','bi-flag',
        dRow('N Audit','<strong style="color:#23408F">'+esc(f.num_audit||'-')+'</strong>')
      + dRow('Nature', esc(f.type_activite||'-'))
      + dRow('Cadre', esc(f.cadre||'-'))
      + dRow('Date prevue', fmtDate(f.date_previsionnelle))
      + dRow('Date de realisation', fmtDate(f.date_realisation))
      + dRow('Lieu', esc(f.ville||f.nomsite||f.indicateur_oaci||f.site_inspection||'-')));

    h += dSec('Operateur','bi-buildings',
        dRow('Operateur','<strong>'+esc(f.nomorga||'-')+'</strong>'+(f.trigrorganisme?(' <span style="color:#6b7a90">('+esc(f.trigrorganisme)+')</span>'):''))
      + dRow('Activites de l\'operateur', esc(f.type_activite_operateur||'-'))
      + dRow('Indicateur OACI', esc(f.indicateur_oaci||'-')));

    /* Equipe d'inspection */
    let tb='';
    if(eq.length){
      eq.forEach(function(m){
        tb += '<tr>'
           +  '<td style="padding:6px 9px;border-bottom:1px solid #f0f3f8;font-weight:600">'+esc(m.nom||'-')+'</td>'
           +  '<td style="padding:6px 9px;border-bottom:1px solid #f0f3f8">'+esc(m.trigr_inspecteur||'-')+'</td>'
           +  '<td style="padding:6px 9px;border-bottom:1px solid #f0f3f8">'
           +    '<span class="pill" style="background:#eef3fb;color:#23408F">'+esc(m.nomdomaine||'-')+'</span></td>'
           +  '<td style="padding:6px 9px;border-bottom:1px solid #f0f3f8;font-size:.78rem">'+esc(m.categorie||'-')+'</td>'
           +  '<td style="padding:6px 9px;border-bottom:1px solid #f0f3f8;font-size:.78rem">'+esc(m.mailinspect||'-')+'</td>'
           +  '</tr>';
      });
    } else {
      tb = '<tr><td colspan="5" style="padding:14px;text-align:center;color:#9aa7bd">Aucun membre enregistre pour cet audit.</td></tr>';
    }
    h += '<div style="background:#fff;border:1px solid #e6ebf3;border-radius:12px;padding:14px 16px;margin-bottom:14px">'
      +  '<div style="font-size:.8rem;font-weight:800;color:#23408F;text-transform:uppercase;letter-spacing:.4px;'
      +  'border-bottom:2px solid #eef3fb;padding-bottom:7px;margin-bottom:11px">'
      +  '<i class="bi bi-people me-2"></i>Equipe d\'inspection'
      +  '<span style="float:right;font-weight:600;font-size:.72rem;color:#6b7a90">Responsable : '+esc(f.responsable||'-')+'</span></div>'
      +  '<div class="table-responsive"><table style="width:100%;border-collapse:collapse;font-size:.82rem">'
      +  '<thead><tr style="background:#23408F;color:#fff">'
      +    '<th style="padding:7px 9px;text-align:left;font-size:.7rem;text-transform:uppercase">Inspecteur</th>'
      +    '<th style="padding:7px 9px;text-align:left;font-size:.7rem;text-transform:uppercase">Trigramme</th>'
      +    '<th style="padding:7px 9px;text-align:left;font-size:.7rem;text-transform:uppercase">Domaine</th>'
      +    '<th style="padding:7px 9px;text-align:left;font-size:.7rem;text-transform:uppercase">Categorie</th>'
      +    '<th style="padding:7px 9px;text-align:left;font-size:.7rem;text-transform:uppercase">Adresse electronique</th>'
      +  '</tr></thead><tbody>'+tb+'</tbody></table></div></div>';

    h += dSec('Suivi de la fiche','bi-person-check',
        dRow('Inspecteur redacteur', esc(f.nom_inspecteur||'-'))
      + dRow('Agent en charge du suivi', esc(f.nom_agent_suivi||f.nom_inspecteur||'-'))
      + dRow('Adresse de relance', esc(f.mail_agent_suivi||f.mail_inspecteur||'-')));

    $('#dtBody').html(h);
  });
});

/* ============================================================
 *  EXPORTS : le tableau tel qu'il est filtre
 * ============================================================ */
const AL_COLS = ['N FNC','N Audit','Annee','Operateur','Domaine','Categorie','Statut',
                 'Reponse exigee','Mise en conformite','Jours restants','Etat',
                 'Inspecteur en charge','Adresse electronique'];

function alLignes(){
  return filtrees().sort(function(a,b){
    const ja=joursProches(a), jb=joursProches(b);
    if(ja===null) return 1; if(jb===null) return -1; return ja-jb;
  }).map(function(f){
    const j=joursProches(f);
    let etat='Sans echeance';
    if(j!==null){ etat = j<0 ? ('Retard de '+Math.abs(j)+' j') : (j===0 ? 'Echeance du jour' : ('Dans '+j+' j')); }
    return [ f.num_fnc||'', f.num_audit||'', f.annee||'', f.nomorga||'', f.nomdomaine||'', f.categorie||'',
             (ST_LBL[f.statut]||''),
             fmtDate(f.date_reponse_exigee), fmtDate(f.date_limite_mise_conformite),
             (j===null?'':j), etat,
             (f.nom_agent_suivi||f.nom_inspecteur||''), (f.mail_agent_suivi||f.mail_inspecteur||'') ];
  });
}
function alDateJour(){
  const d=new Date();
  const m=['janvier','fevrier','mars','avril','mai','juin','juillet','aout','septembre','octobre','novembre','decembre'];
  return d.getDate()+' '+m[d.getMonth()]+' '+d.getFullYear();
}
function alTableHTML(){
  const lignes = alLignes();
  let h = '<table style="width:100%;border-collapse:collapse;font-family:Candara,Arial;font-size:9.5px">'
        + '<thead><tr>' + AL_COLS.map(function(c){
            return '<td bgcolor="#23408F" style="background-color:#23408F;color:#FFFFFF;font-weight:bold;'
                 + 'padding:5px 7px;border:1px solid #1b3576;text-align:center;font-size:8.5px">'+esc(c)+'</td>';
          }).join('') + '</tr></thead><tbody>';
  lignes.forEach(function(l, i){
    const bg = (i % 2) ? '#f7f9fc' : '#ffffff';
    h += '<tr>' + l.map(function(v){
      return '<td bgcolor="'+bg+'" style="background-color:'+bg+';padding:4px 7px;border:1px solid #d6e0f2">'
           + esc(v===null||v===undefined?'':String(v)) + '</td>';
    }).join('') + '</tr>';
  });
  return h + '</tbody></table>';
}

$('#btnXls').on('click', function(){
  const n = filtrees().length;
  if(!n){ Swal.fire({icon:'info',title:'Aucune donnee',text:'Le tableau est vide pour ces criteres.',confirmButtonColor:'#23408F'}); return; }
  const html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8"></head><body>'
    + '<table><tr><td colspan="'+AL_COLS.length+'" style="font-family:Candara;font-size:15pt;font-weight:bold;color:#23408F;text-align:center">'
    +   'ALERTES SUR LES FICHES DE NON-CONFORMITE</td></tr>'
    + '<tr><td colspan="'+AL_COLS.length+'" style="font-family:Candara;font-size:9pt;color:#5b6b85;text-align:center">'
    +   'Agence Nationale de l\'Aviation Civile du Gabon &middot; Edite le '+alDateJour()+' &middot; '+n+' fiche(s) ouverte(s)</td></tr>'
    + '<tr><td colspan="'+AL_COLS.length+'"></td></tr></table>'
    + alTableHTML() + '</body></html>';
  const blob = new Blob(['\ufeff'+html], {type:'application/vnd.ms-excel'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'Alertes_FNC_' + new Date().toISOString().substring(0,10) + '.xls';
  document.body.appendChild(a); a.click(); a.remove();
});

$('#btnPdf').on('click', function(){
  const n = filtrees().length;
  if(!n){ Swal.fire({icon:'info',title:'Aucune donnee',text:'Le tableau est vide pour ces criteres.',confirmButtonColor:'#23408F'}); return; }
  const w = window.open('', '_blank', 'width=1150,height=800');
  w.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Alertes FNC</title>'
    + '<style>@page{size:A4 landscape;margin:9mm}'
    + 'body{font-family:Candara,Arial,sans-serif;color:#2C3E50;margin:0}'
    + '.ban{width:100%;max-height:105px;object-fit:contain;display:block;margin-bottom:6px}'
    + 'h2{color:#23408F;text-align:center;font-size:15px;text-transform:uppercase;margin:4px 0 2px}'
    + '.st{text-align:center;color:#6b7a90;font-size:10px;margin-bottom:10px}'
    + '.pied{margin-top:10px;border-top:2px solid #1E9C4B;padding-top:5px;font-size:9px;color:#6b7a90;'
    + 'display:flex;justify-content:space-between}</style></head><body>'
    + '<img class="ban" src="'+AGAI_BASE+'/public/images/banierenteanac.png" alt="ANAC" '
    +   'onerror="this.style.display=\'none\'">'
    + '<h2>Alertes sur les fiches de non-conformite</h2>'
    + '<div class="st">Agence Nationale de l\'Aviation Civile du Gabon &middot; Edite le '+alDateJour()+'</div>'
    + alTableHTML()
    + '<div class="pied"><span>AGAI &middot; Suivi des non-conformites</span><span><strong>'+n+'</strong> fiche(s) ouverte(s)</span></div>'
    + '<scr'+'ipt>window.onload=function(){setTimeout(function(){window.print();},400);};</scr'+'ipt>'
    + '</body></html>');
  w.document.close(); w.focus();
});

charger();
</script>