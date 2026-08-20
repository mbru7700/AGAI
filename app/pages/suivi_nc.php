<?php
/**
 * Page : Suivi des Fiches de Non-Conformite (FNC)
 * Tableau de suivi complet avec tous les champs requis
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardPage('suivi_nc');
$csrf      = Security::generateCSRF();
$role      = Rbac::role();
$isCI      = in_array($role, ['admin','chef_inspecteur'], true);
$uid       = (int)($_SESSION['user_id'] ?? 0);
// idinspecteur du user connecte (pour autoriser le suivi de SES fiches)
$monIdInspecteur = 0;
try {
    $db = Database::getInstance();
    $stI = $db->prepare("SELECT idinspecteur FROM inspecteur WHERE iduser=? LIMIT 1");
    $stI->execute([$uid]);
    $monIdInspecteur = (int)($stI->fetchColumn() ?: 0);
} catch (\Throwable $e) { $monIdInspecteur = 0; }
$pageTitle = 'Suivi NC';
$active    = 'suivi_nc';
$pageIcon  = 'bi-clipboard-check';
require_once INCLUDES_PATH . '/layout_head.php';
?>
<style>
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-bottom:16px;}
.kpi-card{background:#fff;border:1px solid #eef1f6;border-radius:12px;padding:12px 14px;box-shadow:0 1px 3px rgba(16,30,54,.04);text-align:center;position:relative;overflow:hidden;cursor:pointer;transition:.15s;}
.kpi-card:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(35,64,143,.12);}
.kpi-card.active{box-shadow:0 0 0 2px rgba(35,64,143,.4);}
.kpi-card::before{content:'';position:absolute;top:0;left:0;width:4px;height:100%;}
.kc-red::before{background:#D32F2F;} .kc-gold::before{background:#F3C300;}
.kc-blue::before{background:#23408F;} .kc-green::before{background:#1E9C4B;}
.kc-purple::before{background:#7c3aed;}
.kpi-num{font-size:1.6rem;font-weight:800;line-height:1;color:#2C3E50;}
.kpi-lbl{font-size:.72rem;color:#7b8aa0;margin-top:3px;}
.flbl-s{font-size:.7rem;font-weight:700;color:#5b6b85;text-transform:uppercase;margin-bottom:3px;letter-spacing:.02em;}
/* Bouton Reinitialiser : plus lisible et institutionnel */
.btn-reset-agai{background:linear-gradient(135deg,#D32F2F,#b02525);color:#fff;border:none;border-radius:9px;font-size:.8rem;font-weight:600;padding:7px 12px;white-space:nowrap;box-shadow:0 2px 6px rgba(211,47,47,.25);transition:.15s;}
.btn-reset-agai:hover{filter:brightness(1.06);transform:translateY(-1px);color:#fff;box-shadow:0 4px 10px rgba(211,47,47,.35);}
.filter-bar{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:12px 16px;margin-bottom:12px;}
.tbl-wrap{background:#fff;border:1px solid #eef1f6;border-radius:14px;overflow:hidden;box-shadow:0 1px 3px rgba(16,30,54,.04);}
table.tbl{width:100%;border-collapse:separate;border-spacing:0;}
table.tbl thead th{background:#23408F;color:#fff;font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;padding:9px 10px;white-space:nowrap;font-weight:600;}
table.tbl tbody td{padding:8px 10px;border-bottom:1px solid #f1f4f9;vertical-align:top;font-size:.8rem;}
table.tbl tbody tr:hover{background:#fafcff;}
table.tbl tbody tr.en-retard{background:#fff5f5;}
.cat-badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:.7rem;font-weight:800;}
.cat-critique{background:#fee2e2;color:#991b1b;}
.cat-majeur{background:#fef3c7;color:#92400e;}
.cat-mineur{background:#dbeafe;color:#1e40af;}
.cat-observation{background:#d1fae5;color:#065f46;}
.stat-badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:.7rem;font-weight:700;}
.stat-1{background:#e8f0fe;color:#23408F;}
.stat-2{background:#fee2e2;color:#991b1b;}
.stat-3{background:#d1fae5;color:#065f46;}
.stat-4{background:#fef3c7;color:#92400e;}
.retard-icon{color:#D32F2F;font-size:.8rem;}
</style>

<div class="page-head">
  <div>
    <h1><i class="bi bi-clipboard-check me-2" style="color:#23408F"></i>Suivi des Non-Conformites</h1>
    <div class="sub">Tableau de suivi complet des fiches NC — etat, delais, efficacite, cloture.</div>
  </div>
  <button class="btn btn-sm btn-outline-success" id="btnExcelNC"><i class="bi bi-file-earmark-excel me-1"></i>Exporter Excel</button>
</div>

<!-- KPI -->
<div class="alert border mb-2" style="font-size:.82rem;color:#1e3a5f;background:#eef4ff;border-left:4px solid #23408F !important">
  <i class="bi bi-person-check me-1"></i><b>Perimetre de ces indicateurs :</b> vos propres fiches uniquement, c'est-a-dire celles que
  <b>vous avez saisies</b> ou dont <b>vous etes l'agent de suivi</b>. Pour la vue d'ensemble de toute l'equipe, consultez la page <b>Non-conformites (Ouverture)</b>.
</div>
<div class="kpi-grid" id="kpiRow">
  <div class="kpi-card kc-blue" data-f="" title="Total des FNC (cliquer pour tout afficher)"><div class="kpi-num" id="k_total">-</div><div class="kpi-lbl">Total FNC</div></div>
  <div class="kpi-card kc-purple" style="cursor:default" title="Criteres non satisfaisants (fiches attendues) sur mes audits"><div class="kpi-num" id="k_ncns" style="color:#7c3aed">-</div><div class="kpi-lbl">NCNS attendus</div></div>
  <div class="kpi-card kc-gold" style="cursor:default" title="Fiches restant a saisir (NCNS - FNC)"><div class="kpi-num" id="k_reste" style="color:#b58a00">-</div><div class="kpi-lbl">Reste a saisir</div></div>
  <div class="kpi-card kc-green" style="cursor:default" title="Taux de saisie (FNC / NCNS)"><div class="kpi-num" id="k_taux" style="color:#1E9C4B">-</div><div class="kpi-lbl">Taux de saisie</div></div>
  <div class="kpi-card kc-gold" data-f="4" title="FNC en statut Ouvert"><div class="kpi-num" id="k_ouv">-</div><div class="kpi-lbl">Ouvertes</div></div>
  <div class="kpi-card kc-red" data-f="c-critique" title="FNC Critiques"><div class="kpi-num" id="k_crit" style="color:#D32F2F">-</div><div class="kpi-lbl">Critiques</div></div>
  <div class="kpi-card kc-gold" data-f="c-majeur" title="FNC Majeures"><div class="kpi-num" id="k_maj" style="color:#b58a00">-</div><div class="kpi-lbl">Majeures</div></div>
  <div class="kpi-card kc-blue" data-f="c-mineur" title="FNC Mineures"><div class="kpi-num" id="k_min">-</div><div class="kpi-lbl">Mineures</div></div>
  <div class="kpi-card kc-green" data-f="3" title="FNC Fermees"><div class="kpi-num" id="k_ferm" style="color:#1E9C4B">-</div><div class="kpi-lbl">Fermees</div></div>
  <div class="kpi-card kc-red" data-f="retard" title="FNC en retard (date reponse exigee depassee)"><div class="kpi-num" id="k_retard" style="color:#D32F2F">-</div><div class="kpi-lbl">En retard</div></div>
</div>

<!-- Filtres -->
<div class="filter-bar">
  <div class="row g-2 align-items-end">
    <div class="col-6 col-md-2"><div class="flbl-s">N FNC</div><select id="fFnc" style="width:100%"><option value="">Toutes</option></select></div>
    <div class="col-6 col-md-2"><div class="flbl-s">N Audit</div><select id="fAudit" style="width:100%"><option value="">Tous</option></select></div>
    <div class="col-6 col-md-2"><div class="flbl-s">Operateur</div><select id="fOrga" style="width:100%"><option value="">Tous</option></select></div>
    <div class="col-6 col-md-2"><div class="flbl-s">Inspecteur</div><select id="fInsp" style="width:100%"><option value="">Tous</option></select></div>
    <div class="col-6 col-md-2"><div class="flbl-s">Domaine</div><select id="fDom" style="width:100%"><option value="">Tous</option></select></div>
    <div class="col-6 col-md-2"><div class="flbl-s">Categorie</div>
      <select id="fCat" style="width:100%">
        <option value="">Toutes</option><option value="critique">Critique</option>
        <option value="majeur">Majeur</option><option value="mineur">Mineur</option>
      </select>
    </div>
    <div class="col-6 col-md-2"><div class="flbl-s">Statut</div>
      <select id="fStatut" style="width:100%">
        <option value="">Tous</option>
        <option value="4">Ouvert</option><option value="1">Accepte non verifie</option>
        <option value="2">Rejete</option><option value="3">Ferme</option>
      </select>
    </div>
    <div class="col-6 col-md-3"><div class="flbl-s">Recherche</div>
      <input type="text" id="fSearch" class="form-control form-control-sm" placeholder="N FNC, Operateur...">
    </div>
    <div class="col-6 col-md-2 d-flex align-items-end">
      <button class="btn btn-reset-agai w-100" id="btnResetFiltres" title="Reinitialiser tous les filtres">
        <i class="bi bi-arrow-counterclockwise me-1"></i>Reinitialiser
      </button>
    </div>
  </div>
</div>

<!-- Tableau de suivi -->
<div class="tbl-wrap" style="overflow-x:auto">
  <table class="tbl" style="min-width:1600px">
    <thead><tr>
      <th>#</th>
      <th>N FNC / Agent / Date emiss.</th>
      <th>N Audit / Source / Date audit</th>
      <th>Date transm. rapport / Delai</th>
      <th>Operateur / Lieu / Activite</th>
      <th>Referentiel / Domaine / Sous-dom.</th>
      <th>Libelle / Etat</th>
      <th>Categorie</th>
      <th>Date rep. exigee</th>
      <th>Date lim. conformite</th>
      <th>Statut / Cloture</th>
      <th>Actions</th>
    </tr></thead>
    <tbody id="tbodySuivi">
      <tr><td colspan="12" style="padding:30px;text-align:center;color:#9aa7bd"><span class="spinner-border spinner-border-sm me-2"></span>Chargement...</td></tr>
    </tbody>
  </table>
</div>
<div id="pagiBox" style="display:flex;gap:4px;justify-content:center;margin-top:12px;flex-wrap:wrap"></div>

<!-- MODALE SUIVI / MISE A JOUR -->
<div class="modal fade" id="modalSuivi" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,#23408F,#1b3576)">
        <h5 class="modal-title text-white"><i class="bi bi-pencil-square me-2" style="color:#F3C300"></i>Mise a jour suivi &mdash; <span id="suiviNumFnc">-</span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="suivi_idfnc">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-bold" style="font-size:.82rem">Statut <span class="text-danger">*</span></label>
            <select class="form-select form-select-sm" id="suivi_statut">
              <option value="4">Ouvert</option>
              <option value="1">Accepte mais non verifie</option>
              <option value="2">Rejetee</option>
              <option value="3">Ferme</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-bold" style="font-size:.82rem">Date effective de cloture</label>
            <input type="date" class="form-control form-control-sm" id="suivi_cloture">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-bold" style="font-size:.82rem">Statut delais efficacite</label>
            <select class="form-select form-select-sm" id="suivi_delais_eff">
              <option value="">-- Choisir --</option>
              <option value="D">Depasse (D)</option>
              <option value="ND">Non Depasse (ND)</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-bold" style="font-size:.82rem">Efficacite de mise en conformite</label>
            <input type="text" class="form-control form-control-sm" id="suivi_efficacite" placeholder="Description efficacite">
          </div>
          <div class="col-12">
            <label class="form-label fw-bold" style="font-size:.82rem">Preuve de suivi et verification</label>
            <textarea class="form-control form-control-sm" id="suivi_preuve" rows="2" placeholder="Decrire les preuves..."></textarea>
          </div>
          <div class="col-12">
            <label class="form-label fw-bold" style="font-size:.82rem">Observations / Courriers / Relance</label>
            <textarea class="form-control form-control-sm" id="suivi_obs" rows="3" placeholder="Courriers, relances, observations..."></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-anac" id="btnSaveSuivi"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
      </div>
    </div>
  </div>
</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>
<script>
const CSRF  = '<?php echo Security::escape($csrf); ?>';
const API   = AGAI_BASE + '/api/nonconformites';
const IS_CI = <?php echo $isCI ? 'true' : 'false'; ?>;
const MY_INSP = <?php echo (int)$monIdInspecteur; ?>;
let ALL_FNC = [];
let FILTRE_RETARD = false;

function apiPost(d){ return $.post(API, Object.assign({csrf_token:CSRF},d), null, 'json'); }
function esc(s){ const d=document.createElement('div'); d.textContent=String(s||''); return d.innerHTML; }
function fmtDate(s){ if(!s||s==='0000-00-00'||!s) return '-'; const p=String(s).substring(0,10).split('-'); return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:s; }

const CATEG_CLS = {critique:'cat-critique',majeur:'cat-majeur',mineur:'cat-mineur',observation:'cat-observation'};
const STAT_CLS  = {1:'stat-1',2:'stat-2',3:'stat-3',4:'stat-4'};
const STAT_LBL  = {1:'Accepte non verifie',2:'Rejete',3:'Ferme',4:'Ouvert'};
const TYPE_LBL  = {audit:'Audit',inspection_programmee:'Insp. prog.',inspection_non_programmee:'Insp. non prog.',demonstration:'Demo',test:'Test',investigation:'Investigation'};

// Stats calculees en local : elles refletent les filtres actifs (dynamiques).
// On se base sur les filtres de la barre, hors clic sur les cartes KPI.
function filtreesBarre(){
  const fFnc=$('#fFnc').val()||'', fA=$('#fAudit').val()||'', fO=$('#fOrga').val()||'';
  const fI=$('#fInsp').val()||'', fD=$('#fDom').val()||'';
  const fC=$('#fCat').val()||'', fS=$('#fStatut').val()||'';
  const fQ=($('#fSearch').val()||'').toLowerCase().trim();
  return ALL_FNC.filter(function(f){
    if(fFnc && String(f.num_fnc)!==fFnc) return false;
    if(fA && String(f.idaudit)!==String(fA)) return false;
    if(fO && String(f.nomorga||'')!==fO) return false;
    if(fI && String(f.nom_inspecteur||'')!==fI) return false;
    if(fD && String(f.nomdomaine||'')!==fD) return false;
    if(fC && f.categorie!==fC) return false;
    if(fS && String(f.statut)!==String(fS)) return false;
    if(fQ && !((f.num_fnc||'').toLowerCase().includes(fQ)||(f.nomorga||'').toLowerCase().includes(fQ)||(f.num_audit||'').toLowerCase().includes(fQ))) return false;
    return true;
  });
}
function loadStats(){
  const A=filtreesBarre();
  const today=new Date().toISOString().substring(0,10);
  const n=function(fn){ return A.filter(fn).length; };
  $('#k_total').text(A.length);
  $('#k_ouv').text( n(function(f){return String(f.statut)==='4';}) );
  $('#k_crit').text(n(function(f){return f.categorie==='critique';}) );
  $('#k_maj').text( n(function(f){return f.categorie==='majeur';}) );
  $('#k_min').text( n(function(f){return f.categorie==='mineur';}) );
  $('#k_ferm').text(n(function(f){return String(f.statut)==='3';}) );
  $('#k_retard').text(n(function(f){ return f.date_reponse_exigee && String(f.date_reponse_exigee).substring(0,10) < today && String(f.statut)!=='3'; }) );
  // NCNS attendus : somme comptee UNE fois par audit (donnee de l'audit, pas de la fiche)
  const parAudit={};
  A.forEach(function(f){ if(f.idaudit!=null){ parAudit[f.idaudit]=parseInt(f.audit_ncns||0,10)||0; } });
  let ncns=0; for(const k in parAudit){ ncns+=parAudit[k]; }
  const fnc=A.length, reste=Math.max(0,ncns-fnc);
  const taux = ncns>0 ? Math.round((fnc/ncns)*1000)/10 : 0;
  $('#k_ncns').text(ncns);
  $('#k_reste').text(reste);
  $('#k_taux').text(ncns>0 ? (taux+'%') : '-');
}

$('#fFnc,#fAudit,#fOrga,#fInsp,#fDom,#fCat,#fStatut').select2({theme:'bootstrap-5',width:'100%',allowClear:true});

function loadList(){
  apiPost({action:'list',only_mine:1}).done(function(res){
    if(!res.success){ $('#tbodySuivi').html('<tr><td colspan="12" style="padding:20px;text-align:center;color:#D32F2F">Erreur</td></tr>'); return; }
    ALL_FNC = res.data||[];
    fillAuditFilter();
    loadStats();
    renderTable(getFiltered());
  });
}

function fillAuditFilter(){
  const uniq=function(vals){ return [...new Set(vals.filter(Boolean))].sort(); };
  const fill=function(sel, valeurs, opts){
    const cur=$(sel).val();
    let html='<option value="">'+opts.libelle+'</option>';
    valeurs.forEach(function(v){
      const val=opts.valFn?opts.valFn(v):v, lbl=opts.lblFn?opts.lblFn(v):v;
      html+='<option value="'+esc(val)+'">'+esc(lbl)+'</option>';
    });
    $(sel).html(html);
    if(cur) $(sel).val(cur);
    $(sel).trigger('change.select2');
  };
  // Audit : on garde la correspondance id -> numero
  const seenA={}, cur=$('#fAudit').val();
  let optsA='<option value="">Tous</option>';
  ALL_FNC.forEach(function(f){ if(f.idaudit && !seenA[f.idaudit]){ seenA[f.idaudit]=1; optsA+='<option value="'+f.idaudit+'">'+esc(f.num_audit)+'</option>'; } });
  $('#fAudit').html(optsA); if(cur) $('#fAudit').val(cur); $('#fAudit').trigger('change.select2');

  fill('#fFnc',  uniq(ALL_FNC.map(function(f){return f.num_fnc;})),        {libelle:'Toutes'});
  fill('#fOrga', uniq(ALL_FNC.map(function(f){return f.nomorga;})),        {libelle:'Tous'});
  fill('#fInsp', uniq(ALL_FNC.map(function(f){return f.nom_inspecteur;})), {libelle:'Tous'});
  fill('#fDom',  uniq(ALL_FNC.map(function(f){return f.nomdomaine;})),     {libelle:'Tous'});
}

function getFiltered(){
  const fFnc=$('#fFnc').val()||'', fA=$('#fAudit').val()||'', fO=$('#fOrga').val()||'';
  const fI=$('#fInsp').val()||'', fD=$('#fDom').val()||'';
  const fC=$('#fCat').val()||'', fS=$('#fStatut').val()||'';
  const fQ=($('#fSearch').val()||'').toLowerCase().trim();
  const today=new Date().toISOString().substring(0,10);
  return ALL_FNC.filter(function(f){
    if(fFnc && String(f.num_fnc)!==fFnc) return false;
    if(fA && String(f.idaudit)!==String(fA)) return false;
    if(fO && String(f.nomorga||'')!==fO) return false;
    if(fI && String(f.nom_inspecteur||'')!==fI) return false;
    if(fD && String(f.nomdomaine||'')!==fD) return false;
    if(fC && f.categorie!==fC) return false;
    if(fS && String(f.statut)!==String(fS)) return false;
    if(fQ && !((f.num_fnc||'').toLowerCase().includes(fQ)||(f.nomorga||'').toLowerCase().includes(fQ)||(f.num_audit||'').toLowerCase().includes(fQ))) return false;
    if(FILTRE_RETARD){
      const enRetard = f.date_reponse_exigee && String(f.date_reponse_exigee).substring(0,10) < today && String(f.statut)!=='3';
      if(!enRetard) return false;
    }
    return true;
  });
}

function stripTagsSuivi(v){
  let s=String(v||'');
  const ta=document.createElement('textarea'); ta.innerHTML=s; s=ta.value;
  return s.replace(/<[^>]*>/g,'').replace(/&nbsp;/g,' ').replace(/\s+/g,' ').trim();
}

/* ===== EXPORT EXCEL (tableau filtre) ===== */
$('#btnExcelNC').on('click', function(){
  const data=getFiltered();
  if(!data.length){
    Swal.fire({icon:'info',title:'Aucune donnee',text:'Le tableau ne contient aucune fiche pour ces criteres.',confirmButtonColor:'#23408F'});
    return;
  }
  const cols=['N FNC','N Audit','Source','Date audit','Date emiss. FNC','Date transm. rapport',
    'Delai transm.','Operateur','Activite oper.','Domaine','Lieu','Sous-domaine','Referentiel',
    'Libelle','Etat','Categorie','Ref. Regl.','Date rep. exigee','Date lim. conformite',
    'Agent ANAC','Statut','Cloture'];
  const ligne=function(f){
    const dActe=f.date_realisation||f.date_previsionnelle;
    return [
      f.num_fnc||'', f.num_audit||'', f.source_audit||'',
      fmtDate(dActe), fmtDate(f.date_emission), fmtDate(f.date_transmission_rapport),
      (f.delais_transmission!=null?f.delais_transmission:''),
      f.nomorga||'', f.type_activite_operateur||'',
      f.nomdomaine||'', (f.ville||f.nomsite||f.indicateur_oaci||f.site_inspection||''),
      f.sousdomaines_noms||'', f.reglements_codes||'',
      stripTagsSuivi(f.libelle||f.description_constatation||''),
      (f.etat||'').replace(/_/g,' '), (f.categorie||''),
      f.ref_reglement||'', fmtDate(f.date_reponse_exigee), fmtDate(f.date_limite_mise_conformite),
      (f.nom_inspecteur||''), (STAT_LBL[f.statut]||''), fmtDate(f.date_effective_cloture)
    ];
  };
  const nbCol=cols.length;
  const dateJour=new Date().toLocaleDateString('fr-FR');
  let html='<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8">'
    +'<style>body,table,td,th{font-family:Candara,"Segoe UI",Arial,sans-serif}table{border-collapse:collapse}'
    +'td,th{border:1px solid #b8c4d9;padding:4px 6px;font-size:10pt;vertical-align:top}</style></head><body><table>'
    +'<tr><td colspan="'+nbCol+'" style="font-size:15pt;font-weight:bold;color:#23408F;text-align:center;border:none">Suivi des non-conformites (mes fiches)</td></tr>'
    +'<tr><td colspan="'+nbCol+'" style="font-size:9pt;color:#5b6b85;text-align:center;border:none">Agence Nationale de l\'Aviation Civile du Gabon &middot; Edite le '+dateJour+' &middot; '+data.length+' fiche(s)</td></tr>'
    +'<tr><td colspan="'+nbCol+'"></td></tr><tr>'
    + cols.map(function(c){ return '<td bgcolor="#23408F" style="background-color:#23408F;color:#FFFFFF;font-weight:bold;text-align:center;font-size:9pt">'+esc(c)+'</td>'; }).join('')
    + '</tr>';
  data.forEach(function(f){
    html+='<tr>'+ligne(f).map(function(v){ return '<td>'+esc(v===null||v===undefined?'':String(v))+'</td>'; }).join('')+'</tr>';
  });
  html+='</table></body></html>';
  const blob=new Blob(['\ufeff'+html],{type:'application/vnd.ms-excel'});
  const a=document.createElement('a');
  a.href=URL.createObjectURL(blob);
  a.download='Suivi_NC_'+new Date().toISOString().substring(0,10)+'.xls';
  document.body.appendChild(a); a.click(); a.remove();
});
function renderTable(list){
  if(!list.length){
    $('#tbodySuivi').html('<tr><td colspan="12" style="padding:30px;text-align:center;color:#9aa7bd"><i class="bi bi-inbox me-2"></i>Aucune FNC trouvee</td></tr>'); return;
  }
  const today=new Date().toISOString().substring(0,10);
  let h='';
  list.forEach(function(f,i){
    const retard=f.date_reponse_exigee&&f.date_reponse_exigee<today&&f.statut<3;
    const src=(TYPE_LBL[f.type_activite]||f.type_activite||'-')+' / '+(f.cadre||'').replace(/_/g,' ');
    const agent=f.nom_inspecteur||f.nom_agent_suivi||'';
    const lieu=f.ville||f.nomsite||f.indicateur_oaci||f.site_inspection||'';
    h+='<tr'+(retard?' class="en-retard"':'')+'>'
      +'<td style="font-size:.75rem;color:#9aa7bd">'+(i+1)+'</td>'
      // N FNC + Agent ANAC + Date emiss. FNC dessous
      +'<td><strong style="color:#D32F2F;font-family:monospace;font-size:.82rem">'+esc(f.num_fnc)+'</strong>'
        +(agent?'<div style="font-size:.68rem;color:#1E9C4B;margin-top:2px"><i class="bi bi-person-badge me-1"></i>'+esc(agent)+'</div>':'')
        +'<div style="font-size:.68rem;color:#6b7a90;margin-top:2px"><i class="bi bi-calendar-event me-1"></i>Emise : '+fmtDate(f.date_emission)+'</div></td>'
      // N Audit + Source + Date audit dessous
      +'<td><strong style="color:#23408F;font-size:.8rem">'+esc(f.num_audit||'-')+'</strong>'
        +'<div style="font-size:.68rem;color:#6b7a90;margin-top:2px">'+esc(src)+'</div>'
        +'<div style="font-size:.68rem;color:#6b7a90;margin-top:2px"><i class="bi bi-calendar-check me-1"></i>'+fmtDate(f.date_previsionnelle)+'</div></td>'
      // Date transm. rapport + Delai dessous
      +'<td style="font-size:.78rem;white-space:nowrap">'+fmtDate(f.date_transmission_rapport)
        +(f.delais_transmission!==null&&f.delais_transmission!==''?'<div style="font-size:.68rem;color:#6b7a90;margin-top:2px">Delai : '+esc(f.delais_transmission)+'j</div>':'')+'</td>'
      // Operateur + Lieu + Activite dessous
      +'<td style="font-size:.8rem;font-weight:600;max-width:140px">'+esc(f.nomorga||'-')
        +(lieu?'<div style="font-size:.68rem;color:#6b7a90;font-weight:400;margin-top:2px"><i class="bi bi-geo-alt me-1"></i>'+esc(lieu)+'</div>':'')
        +'<div style="font-size:.68rem;color:#6b7a90;font-weight:400;margin-top:2px"><i class="bi bi-briefcase me-1"></i>'+esc(f.type_activite_operateur||TYPE_LBL[f.type_activite]||'-')+'</div></td>'
      // Referentiel + Domaine + Sous-domaine dessous
      +'<td style="font-size:.75rem;max-width:140px">'+esc(f.reglements_codes||'-')
        +'<div style="font-size:.68rem;margin-top:2px"><span style="color:#23408F;font-weight:700"><i class="bi bi-diagram-3 me-1"></i>'+esc(f.nomdomaine||'-')+'</span></div>'
        +(f.sousdomaines_noms?'<div style="font-size:.66rem;color:#9aa7bd;margin-top:2px">'+esc(f.sousdomaines_noms)+'</div>':'')+'</td>'
      +'<td style="font-size:.75rem;max-width:150px"><div style="max-height:50px;overflow:hidden;text-overflow:ellipsis">'+esc(stripTagsSuivi(f.libelle||f.description_constatation||'-').substring(0,100))+'</div>'
        +'<span style="font-size:.68rem;color:#9aa7bd">'+esc(f.etat||'-').replace(/_/g,' ')+'</span></td>'
      +'<td><span class="cat-badge '+(CATEG_CLS[f.categorie]||'')+'">'+esc(f.categorie||'-')+'</span></td>'
      +'<td style="font-size:.78rem;white-space:nowrap'+(retard?';color:#D32F2F;font-weight:700':'')+'">'
        +(retard?'<i class="bi bi-clock-history retard-icon me-1"></i>':'')
        +fmtDate(f.date_reponse_exigee)+'</td>'
      +'<td style="font-size:.78rem;white-space:nowrap">'+fmtDate(f.date_limite_mise_conformite)+'</td>'
      // Statut + Cloture dessous
      +'<td style="white-space:nowrap"><span class="stat-badge '+(STAT_CLS[f.statut]||'')+'">'+esc(STAT_LBL[f.statut]||'-')+'</span>'
        +(f.date_effective_cloture&&f.date_effective_cloture!=='0000-00-00'?'<div style="font-size:.68rem;color:#1E9C4B;margin-top:3px"><i class="bi bi-check2-circle me-1"></i>Cloture : '+fmtDate(f.date_effective_cloture)+'</div>':'')+'</td>'
      +'<td style="white-space:nowrap">'
        +(function(){
           const estMien = IS_CI || (MY_INSP>0 && (String(f.idinspecteur_createur)===String(MY_INSP) || String(f.agent_suivi)===String(MY_INSP)));
           const estFermee = String(f.statut)==='3';
           if(estMien && !estFermee){
             return '<button class="btn btn-xs btn-outline-primary me-1 btn-suivi-edit" data-id="'+f.idfnc+'" data-num="'+esc(f.num_fnc)+'" title="Faire le suivi de cette fiche"><i class="bi bi-clipboard-check"></i></button>';
           }
           if(!estFermee){
             return '<span class="d-inline-block me-1" title="Fiche suivie par un autre inspecteur : consultation seule"><button class="btn btn-xs btn-outline-secondary" disabled style="opacity:.45;cursor:not-allowed;pointer-events:none"><i class="bi bi-lock"></i></button></span>';
           }
           return '';
         })()
        +'<button class="btn btn-xs btn-outline-danger btn-print-suivi" data-id="'+f.idfnc+'" title="Imprimer"><i class="bi bi-printer"></i></button>'
      +'</td>'
      +'</tr>';
  });
  $('#tbodySuivi').html(h);
}

// Rafraichit stats + tableau ensemble (stats dynamiques selon les filtres)
function refreshSuivi(){ loadStats(); renderTable(getFiltered()); }

$('#fFnc,#fAudit,#fOrga,#fInsp,#fDom,#fCat,#fStatut').on('change', refreshSuivi);
$('#fSearch').on('input', refreshSuivi);
$('#btnResetFiltres').on('click',function(){
  $('#fFnc,#fAudit,#fOrga,#fInsp,#fDom,#fCat,#fStatut').val('').trigger('change.select2');
  $('#fSearch').val('');
  $('#kpiRow .kpi-card').removeClass('active');
  FILTRE_RETARD=false;
  refreshSuivi();
});

/* Cartes d'indicateurs cliquables : filtre rapide du tableau */
$(document).on('click','#kpiRow .kpi-card',function(){
  const f=String($(this).data('f')||'');
  const dejaActif=$(this).hasClass('active');
  $('#kpiRow .kpi-card').removeClass('active');
  $('#fCat,#fStatut').val('');
  FILTRE_RETARD=false;
  if(!dejaActif){
    $(this).addClass('active');
    if(f==='retard'){ FILTRE_RETARD=true; }
    else if(f.indexOf('c-')===0){ $('#fCat').val(f.substring(2)); }
    else if(f){ $('#fStatut').val(f); }
  }
  $('#fCat,#fStatut').trigger('change.select2');
  renderTable(getFiltered());
});

/* ===== MODALE SUIVI ===== */
$(document).on('click','.btn-suivi-edit',function(){
  const id=$(this).data('id');
  // On ouvre le veritable formulaire "Suivi de la FNC" (page Ouverture NC),
  // identique pour les deux pages : evaluation des risques, PAC, cloture...
  window.location.href = AGAI_BASE + '/ouverture-nc?suivi=' + encodeURIComponent(id);
});
$('#btnSaveSuivi').on('click',function(){
  const id=$('#suivi_idfnc').val();
  const btn=$(this); btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
  apiPost({
    action:'update_suivi', idfnc:id,
    statut:$('#suivi_statut').val(),
    date_effective_cloture:$('#suivi_cloture').val(),
    efficacite_mise_conformite:$('#suivi_efficacite').val(),
    statut_delais_efficacite:$('#suivi_delais_eff').val(),
    preuve_suivi:$('#suivi_preuve').val(),
    observations_courriers:$('#suivi_obs').val(),
  }).done(function(res){
    btn.prop('disabled',false).html('<i class="bi bi-check-lg me-1"></i>Enregistrer');
    if(res.success){
      bootstrap.Modal.getInstance('#modalSuivi').hide();
      Swal.fire({icon:'success',title:'Suivi mis a jour',timer:1500,showConfirmButton:false});
      loadList(); loadStats();
    } else Swal.fire({icon:'error',text:res.message});
  });
});

/* ===== IMPRESSION depuis liste ===== */
$(document).on('click','.btn-print-suivi',function(){
  const id=$(this).data('id');
  window.open(AGAI_BASE+'/ouverture-nc?print='+id,'_blank');
});

/* ===== INIT ===== */
loadStats(); loadList();
</script>