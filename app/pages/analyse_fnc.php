<?php
/**
 * Page : Analyse FNC - Tableau de bord interactif des non-conformites
 * Module : analyse_fnc  -  Route : /analyse-fnc
 *
 * Meme architecture que analyse_psc.php : tous les agregats sont calcules
 * cote serveur au chargement puis livres en un seul bloc JSON. Le filtrage,
 * le croisement (operateur x statut) et les graphiques sont entierement
 * recalcules cote client (aucun aller-retour AJAX par changement de filtre).
 *
 * Statuts FNC (colonne fiche_non_conformite.statut) :
 *   1 = Accepte non verifie   2 = Rejete   3 = Ferme   4 = Ouvert
 *
 * Securite :
 *   - Rbac::guardPage('analyse_fnc') : reserve aux roles habilites (memes
 *     droits que analyse_psc / mise_oeuvre, cf. Rbac::MATRIX).
 *   - Aucune donnee provenant de la requete HTTP n'entre dans le SQL :
 *     page en lecture seule, sans parametre, requete unique et fixe.
 *   - Toute donnee affichee passe par Security::escape() (PHP) ou l'echappement
 *     JS local esc() avant insertion dans le DOM.
 *   - JSON encode avec JSON_HEX_* pour empecher toute evasion de balise <script>.
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardPage('analyse_fnc');

$pageTitle = 'Analyse FNC';
$active    = 'analyse_fnc';
$pageIcon  = 'bi-pie-chart';

$FNC = [];

try {
    $db = Database::getInstance();

    // Controle d'acces (OWASP - Broken Access Control) :
    //  - le CI (admin / chef_inspecteur) voit toutes les FNC ;
    //  - un inspecteur ne voit que les FNC des domaines pour lesquels il est
    //    habilite (membre d'une equipe d'audit sur ce domaine) ou dont il est
    //    responsable d'audit. Le filtrage est fait cote serveur (dans la requete).
    $role   = Rbac::role();
    $estCI  = in_array($role, ['admin', 'chef_inspecteur'], true);
    $uid    = (int) ($_SESSION['user_id'] ?? 0);
    $myInsp = 0;
    $stI = $db->prepare("SELECT idinspecteur FROM inspecteur WHERE iduser=? LIMIT 1");
    $stI->execute([$uid]); $rowI = $stI->fetch();
    if ($rowI) { $myInsp = (int) $rowI['idinspecteur']; }

    // Clause de restriction et parametres selon le profil
    $restr   = '';
    $params  = [];
    if (!$estCI && $myInsp > 0) {
        // FNC dont l'inspecteur est createur, agent de suivi, responsable de
        // l'audit, ou membre de l'equipe sur le domaine de la fiche.
        $restr = " WHERE (
                      f.idinspecteur_createur = ?
                   OR f.agent_suivi = ?
                   OR a.idresponsable_audit = ?
                   OR EXISTS (SELECT 1 FROM audit_equipe ae
                               WHERE ae.idaudit = f.idaudit
                                 AND ae.idinspecteur = ?
                                 AND (ae.iddomaine = f.iddomaine OR ae.iddomaine IS NULL OR ae.iddomaine = 0))
                  )";
        $params = [$myInsp, $myInsp, $myInsp, $myInsp];
    }

    $rows = $db->execute(
        "SELECT f.idfnc, f.num_fnc, f.statut, f.categorie, f.idaudit,
                f.date_emission, f.date_reponse_exigee, f.date_limite_mise_conformite, f.date_effective_cloture,
                YEAR(f.date_emission) AS annee,
                o.nomorga, o.trigrorganisme,
                d.nomdomaine, d.libel_domaine,
                s.nomsite, s.indicateur_oaci,
                a.num_audit, a.ncns AS audit_ncns,
                TRIM(CONCAT(COALESCE(ra.preninspect,''),' ',COALESCE(ra.nominspecteur,''))) AS resp_audit,
                TRIM(CONCAT(COALESCE(ag.preninspect,''),' ',COALESCE(ag.nominspecteur,''))) AS agent_suivi
         FROM fiche_non_conformite f
         LEFT JOIN organisme  o  ON o.idorga     = f.idorga
         LEFT JOIN domaine    d  ON d.iddomaine  = f.iddomaine
         LEFT JOIN audit      a  ON a.idaudit    = f.idaudit
         LEFT JOIN site       s  ON s.idsite     = a.idsite
         LEFT JOIN inspecteur ra ON ra.idinspecteur = a.idresponsable_audit
         LEFT JOIN inspecteur ag ON ag.idinspecteur = f.agent_suivi"
         . $restr .
        " ORDER BY f.date_emission DESC, f.idfnc DESC",
        $params
    )->fetchAll();

    foreach ($rows as $r) {
        $site = trim((string) ($r['nomsite'] ?? ''));
        if ($site === '') { $site = trim((string) ($r['indicateur_oaci'] ?? '')); }

        $FNC[] = [
            'id'        => (int) $r['idfnc'],
            'num'       => (string) $r['num_fnc'],
            'statut'    => (int) ($r['statut'] ?? 4),
            'categorie' => (string) ($r['categorie'] ?? ''),
            'annee'     => (int) ($r['annee'] ?? 0),
            'operateur' => trim((string) ($r['nomorga'] ?? '')) ?: 'Non renseigne',
            'trigr'     => (string) ($r['trigrorganisme'] ?? ''),
            'domaine'   => trim((string) ($r['nomdomaine'] ?? '')) ?: 'Non classe',
            'site'      => $site !== '' ? $site : 'Non renseigne',
            'audit'     => (string) ($r['num_audit'] ?? ''),
            'idaudit'   => (int) ($r['idaudit'] ?? 0),
            'audit_ncns'=> (int) ($r['audit_ncns'] ?? 0),
            'ra'        => trim((string) ($r['resp_audit'] ?? '')) ?: '-',
            'agent'     => trim((string) ($r['agent_suivi'] ?? '')) ?: '-',
            'd_emission'=> substr((string) ($r['date_emission'] ?? ''), 0, 10),
            'd_reponse' => substr((string) ($r['date_reponse_exigee'] ?? ''), 0, 10),
            'd_limite'  => substr((string) ($r['date_limite_mise_conformite'] ?? ''), 0, 10),
            'd_cloture' => substr((string) ($r['date_effective_cloture'] ?? ''), 0, 10),
        ];
    }
} catch (Throwable $e) {
    error_log('analyse_fnc: ' . $e->getMessage());
    $FNC = [];
}

require_once INCLUDES_PATH . '/layout_head.php';
?>
<style>
.tdb-bar{background:linear-gradient(135deg,#23408F,#1b3576);border-radius:14px;padding:14px 18px;margin-bottom:16px;color:#fff}
.tdb-bar label{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#c9d6f0;margin-bottom:3px;display:block}
.tdb-bar .form-select{border:none;border-radius:8px;font-size:.83rem;font-weight:600;color:#23408F}
.kpi{background:#fff;border:1px solid #e6ebf3;border-radius:11px;padding:9px 10px;position:relative;overflow:hidden;transition:.18s;height:100%}
.kpi:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(35,64,143,.12)}
.kpi .v{font-size:1.25rem;font-weight:800;line-height:1.05;color:#2C3E50}
.kpi .l{font-size:.64rem;color:#6b7a90;text-transform:uppercase;letter-spacing:.2px;font-weight:600;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.kpi .ic{position:absolute;right:8px;top:8px;font-size:1.3rem;opacity:.16}
/* Six cartes par ligne sur grand ecran pour tout afficher sur deux rangees */
@media (min-width:992px){ .col-lg-cust{flex:0 0 auto;width:16.666%;} }
.kpi.k-blue{border-left:4px solid #23408F;background:rgba(35,64,143,.06)}
.kpi.k-gold{border-left:4px solid #E8890C;background:rgba(232,137,12,.07)}
.kpi.k-green{border-left:4px solid #1E9C4B;background:rgba(30,156,75,.07)}
.kpi.k-red{border-left:4px solid #D32F2F;background:rgba(211,47,47,.06)}
.kpi.k-grey{border-left:4px solid #7A8798;background:rgba(122,135,152,.07)}
.panel{background:#fff;border:1px solid #e6ebf3;border-radius:14px;padding:14px;height:100%}
.panel h6{color:#23408F;font-weight:700;font-size:.9rem;margin-bottom:10px;display:flex;align-items:center;gap:6px}
.panel .hint{font-size:.72rem;color:#93a1b5;font-weight:400;margin-left:auto}
.chart-box{position:relative;height:280px}
.chart-box.sm{height:230px}
#fncTable thead th, #pivotTable thead th{background:var(--anac-primary)!important;color:#fff!important;text-transform:uppercase;letter-spacing:.4px;font-size:.74rem}
.chip{display:inline-flex;align-items:center;gap:5px;background:#eef3fb;color:#23408F;border-radius:50px;padding:3px 11px;font-size:.76rem;font-weight:600;margin-right:5px}
.chip .x{cursor:pointer;font-weight:800}
.bar-wrap{background:#eef2f7;border-radius:50px;height:15px;overflow:hidden;min-width:80px}
.bar-in{height:100%;border-radius:50px}
#statutTable td{padding:.32rem .4rem;font-size:.8rem;border-color:#f0f3f8}
#statutTable tr.tot td{border-top:2px solid #23408F;font-weight:800;color:#23408F}
#pivotTable td, #pivotTable th{padding:.45rem .5rem;font-size:.81rem;text-align:center;border-color:#f0f3f8}
#pivotTable td:first-child, #pivotTable th:first-child{text-align:left}
#pivotTable tbody tr:hover{background:#f8fafc}
#pivotTable tr.tot td{border-top:2px solid #23408F;font-weight:800;color:#23408F;background:#f4f7fd}
.s-badge{display:inline-block;padding:.2rem .55rem;border-radius:20px;font-size:.72rem;font-weight:700}
@media print{.tdb-bar,.no-print{display:none!important}}
</style>

<div class="page-head">
  <div>
    <h1><i class="bi bi-pie-chart me-2" style="color:var(--anac-primary)"></i>Analyse FNC</h1>
    <div class="sub">Tableau de bord interactif des fiches de non-conformite : repartition, tendances et croisement par operateur.</div>
  </div>
  <div class="d-flex gap-2 no-print">
    <button class="btn btn-outline-secondary" id="btnReset"><i class="bi bi-arrow-counterclockwise me-1"></i>Reinitialiser</button>
    <button class="btn btn-outline-danger" id="btnPrintAna"><i class="bi bi-printer me-1"></i>Imprimer</button>
  </div>
</div>

<div class="tdb-bar no-print">
  <div class="row g-2 align-items-end">
    <div class="col-6 col-md-3 col-lg-2"><label>Annee</label><select class="form-select form-select-sm" id="f_annee"></select></div>
    <div class="col-6 col-md-3 col-lg-2"><label>Statut</label><select class="form-select form-select-sm" id="f_statut"></select></div>
    <div class="col-6 col-md-3 col-lg-2"><label>Categorie</label><select class="form-select form-select-sm" id="f_cat"></select></div>
    <div class="col-6 col-md-3 col-lg-2"><label>Domaine</label><select class="form-select form-select-sm" id="f_dom"></select></div>
    <div class="col-6 col-md-3 col-lg-2"><label>Site</label><select class="form-select form-select-sm" id="f_site"></select></div>
    <div class="col-6 col-md-3 col-lg-2"><label>Operateur</label><select class="form-select form-select-sm" id="f_orga"></select></div>
  </div>
  <div class="mt-2" id="chips"></div>
</div>

<div class="row g-3 mb-3" id="kpis"></div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="panel">
      <h6><i class="bi bi-graph-up-arrow"></i>Evolution annuelle <span class="hint">cliquez un point pour filtrer l'annee</span></h6>
      <div class="chart-box"><canvas id="chEvol"></canvas></div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="panel">
      <h6><i class="bi bi-pie-chart"></i>Repartition par statut</h6>
      <div class="chart-box"><canvas id="chStatut"></canvas></div>
      <table class="table table-sm align-middle mb-0" id="statutTable"></table>
    </div>
  </div>
</div>

<div class="row g-3 mt-0">
  <div class="col-lg-4">
    <div class="panel">
      <h6><i class="bi bi-diagram-3"></i>Par domaine <span class="hint">cliquez pour filtrer</span></h6>
      <div class="chart-box sm"><canvas id="chDomaine"></canvas></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="panel">
      <h6><i class="bi bi-geo-alt"></i>Par site <span class="hint">cliquez pour filtrer</span></h6>
      <div class="chart-box sm"><canvas id="chSite"></canvas></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="panel">
      <h6><i class="bi bi-flag"></i>Par categorie <span class="hint">cliquez pour filtrer</span></h6>
      <div class="chart-box sm"><canvas id="chCategorie"></canvas></div>
    </div>
  </div>
</div>

<div class="panel mt-3">
  <h6><i class="bi bi-grid-3x3"></i>Tableau croise dynamique - FNC par operateur <span class="hint">total, repartition par statut et taux de fermeture</span></h6>
  <div class="table-responsive">
    <table id="pivotTable" class="table table-hover align-middle mb-0" style="width:100%">
      <thead><tr>
        <th>Operateur</th>
        <th>Ouvert</th><th>Accepte non verifie</th><th>Rejete</th><th>Ferme</th>
        <th>Total</th><th style="min-width:150px">Taux de fermeture</th>
      </tr></thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<div class="panel mt-3">
  <h6><i class="bi bi-table"></i>Detail des fiches de non-conformite</h6>
  <div class="table-responsive">
    <table id="fncTable" class="table table-hover align-middle" style="width:100%">
      <thead><tr>
        <th>N FNC</th><th>Annee</th><th>Operateur</th><th>Domaine</th><th>Site</th>
        <th>RA</th><th>Inspecteur en charge</th>
        <th>Categorie</th><th>Statut</th><th>Emission</th><th>Echeance</th><th>Cloture</th>
      </tr></thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
/* Donnees agregees calculees cote serveur (une seule fiche par ligne) */
const FNC = <?php echo json_encode($FNC, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;

const ST_LBL = {1:'Accepte non verifie', 2:'Rejete', 3:'Ferme', 4:'Ouvert'};
const ST_COL = {1:'#E8890C', 2:'#D32F2F', 3:'#1E9C4B', 4:'#23408F'};
const CAT_LBL = {critique:'Critique', majeur:'Majeur', mineur:'Mineur', observation:'Observation'};
const CAT_COL = {critique:'#D32F2F', majeur:'#E8890C', mineur:'#F3C300', observation:'#7A8798'};
const C_BLUE='#23408F', C_GREEN='#1E9C4B';

let chEvol=null, chStatut=null, chDomaine=null, chSite=null, chCategorie=null, fncTable=null;

function esc(s){ const d=document.createElement('div'); d.textContent=(s==null?'':s); return d.innerHTML; }
function pct(n,t){ return t? Math.round(n*1000/t)/10 : 0; }
function fmtDate(s){ if(!s) return '-'; const p=String(s).substring(0,10).split('-'); return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:'-'; }
function bar(v,color){ return '<div class="bar-wrap"><div class="bar-in" style="width:'+Math.min(v,100)+'%;background:'+color+'"></div></div>'; }
function kpi(cls,ic,val,lbl){
  return '<div class="col-4 col-md-3 col-lg-cust"><div class="kpi '+cls+'"><i class="bi '+ic+' ic"></i>'
    +'<div class="v">'+val+'</div><div class="l">'+lbl+'</div></div></div>';
}
function badgeStatut(st){
  return '<span class="s-badge" style="background:'+ST_COL[st]+'22;color:'+ST_COL[st]+'">'+esc(ST_LBL[st]||('Statut '+st))+'</span>';
}
function badgeCat(c){
  if(!c) return '<span class="text-muted">-</span>';
  const col=CAT_COL[c]||'#7A8798';
  return '<span class="s-badge" style="background:'+col+'22;color:'+col+'">'+esc(CAT_LBL[c]||c)+'</span>';
}

/* ---------- Filtres ---------- */
function fillFilters(){
  const annees=[...new Set(FNC.map(f=>f.annee))].filter(Boolean).sort((a,b)=>b-a);
  const doms  =[...new Set(FNC.map(f=>f.domaine))].sort();
  const sites =[...new Set(FNC.map(f=>f.site))].sort();
  const orgas =[...new Set(FNC.map(f=>f.operateur))].sort();
  const cats  =[...new Set(FNC.map(f=>f.categorie).filter(Boolean))];
  $('#f_annee').html('<option value="">Toutes</option>'+annees.map(a=>'<option value="'+a+'">'+a+'</option>').join(''));
  $('#f_statut').html('<option value="">Tous</option>'+[1,2,3,4].map(k=>'<option value="'+k+'">'+esc(ST_LBL[k])+'</option>').join(''));
  $('#f_cat').html('<option value="">Toutes</option>'+cats.map(c=>'<option value="'+esc(c)+'">'+esc(CAT_LBL[c]||c)+'</option>').join(''));
  $('#f_dom').html('<option value="">Tous</option>'+doms.map(d=>'<option value="'+esc(d)+'">'+esc(d)+'</option>').join(''));
  $('#f_site').html('<option value="">Tous</option>'+sites.map(s=>'<option value="'+esc(s)+'">'+esc(s)+'</option>').join(''));
  $('#f_orga').html('<option value="">Tous</option>'+orgas.map(o=>'<option value="'+esc(o)+'">'+esc(o)+'</option>').join(''));
}
function filtres(){
  return {
    an: $('#f_annee').val(), st: $('#f_statut').val(), cat: $('#f_cat').val(),
    dm: $('#f_dom').val(), si: $('#f_site').val(), og: $('#f_orga').val()
  };
}
function filtered(){
  const f=filtres();
  return FNC.filter(function(x){
    if(f.an  && String(x.annee)!==String(f.an)) return false;
    if(f.st  && String(x.statut)!==String(f.st)) return false;
    if(f.cat && x.categorie!==f.cat) return false;
    if(f.dm  && x.domaine!==f.dm) return false;
    if(f.si  && x.site!==f.si) return false;
    if(f.og  && x.operateur!==f.og) return false;
    return true;
  });
}
function renderChips(){
  const f=filtres(); let h='';
  const add=(k,v,lbl)=>{ if(v) h+='<span class="chip">'+esc(lbl)+' : '+esc(v==='1'?ST_LBL[1]:v==='2'?ST_LBL[2]:v==='3'?ST_LBL[3]:v==='4'?ST_LBL[4]:v)+' <span class="x" data-f="'+k+'">&times;</span></span>'; };
  add('f_annee',f.an,'Annee'); add('f_statut',f.st,'Statut'); add('f_cat',f.cat,'Categorie');
  add('f_dom',f.dm,'Domaine'); add('f_site',f.si,'Site'); add('f_orga',f.og,'Operateur');
  $('#chips').html(h);
}
$(document).on('click','.chip .x',function(){ $('#'+$(this).data('f')).val(''); render(); });

/* ---------- Graphiques ---------- */
function drawEvol(list){
  const parAn={};
  list.forEach(function(x){
    if(!x.annee) return;
    if(!parAn[x.annee]) parAn[x.annee]={total:0,ferme:0};
    parAn[x.annee].total++;
    if(x.statut===3) parAn[x.annee].ferme++;
  });
  const labels=Object.keys(parAn).sort((a,b)=>a-b);
  const total=labels.map(a=>parAn[a].total), ferme=labels.map(a=>parAn[a].ferme);
  const taux=labels.map(a=>pct(parAn[a].ferme,parAn[a].total));
  if(chEvol) chEvol.destroy();
  chEvol=new Chart(document.getElementById('chEvol'), {
    data:{labels:labels, datasets:[
      {type:'bar', label:'FNC emises', data:total, backgroundColor:'rgba(35,64,143,.75)', borderRadius:5, barPercentage:.6},
      {type:'bar', label:'FNC fermees', data:ferme, backgroundColor:'rgba(30,156,75,.85)', borderRadius:5, barPercentage:.6},
      {type:'line', label:'Taux de fermeture (%)', data:taux, borderColor:'#E8890C', borderDash:[6,4], tension:.35, fill:false, borderWidth:2, pointRadius:4, yAxisID:'y2'}
    ]},
    options:{
      responsive:true, maintainAspectRatio:false, interaction:{mode:'index',intersect:false},
      plugins:{legend:{position:'bottom',labels:{boxWidth:12,font:{size:11}}}, tooltip:{backgroundColor:'#1b3576',padding:10}},
      scales:{y:{beginAtZero:true,title:{display:true,text:'Nombre de FNC'},grid:{color:'#eef2f7'}},
              y2:{beginAtZero:true,max:100,position:'right',title:{display:true,text:'%'},grid:{drawOnChartArea:false}},
              x:{grid:{display:false}}},
      onClick:function(e,el){ if(el.length){ $('#f_annee').val(labels[el[0].index]); render(); } }
    }
  });
}
function drawStatut(list){
  const cnt={1:0,2:0,3:0,4:0};
  list.forEach(function(x){ if(cnt[x.statut]!==undefined) cnt[x.statut]++; });
  const ks=[1,2,3,4].filter(k=>cnt[k]>0);
  if(chStatut) chStatut.destroy();
  if(!ks.length){ const c=document.getElementById('chStatut').getContext('2d'); c.clearRect(0,0,999,999); }
  else {
    chStatut=new Chart(document.getElementById('chStatut'), {
      type:'doughnut',
      data:{labels:ks.map(k=>ST_LBL[k]), datasets:[{data:ks.map(k=>cnt[k]), backgroundColor:ks.map(k=>ST_COL[k]), borderWidth:2, borderColor:'#fff'}]},
      options:{responsive:true,maintainAspectRatio:false,cutout:'58%',
        plugins:{legend:{position:'bottom',labels:{boxWidth:12,font:{size:11}}},
          tooltip:{backgroundColor:'#1b3576',padding:10,callbacks:{label:function(c){
            const t=c.dataset.data.reduce((x,y)=>x+y,0); return ' '+c.label+' : '+c.parsed+' ('+pct(c.parsed,t)+'%)'; }}}},
        onClick:function(e,el){ if(el.length){ $('#f_statut').val(ks[el[0].index]); render(); } }
      }
    });
  }
  const total=list.length;
  let h='<tbody>';
  [1,2,3,4].forEach(function(k){
    const n=cnt[k]||0, p=pct(n,total||1);
    h+='<tr><td style="width:150px">'+esc(ST_LBL[k])+'</td><td>'+bar(p,ST_COL[k])+'</td>'
      +'<td style="width:88px;text-align:right;font-weight:700">'+n+' <span class="text-muted" style="font-weight:500">('+p+'%)</span></td></tr>';
  });
  h+='<tr class="tot"><td>Total</td><td class="text-end" colspan="2">'+total+' fiche(s)</td></tr>';
  $('#statutTable').html(h+'</tbody>');
}
function topBarChart(canvasId, chartVarSetter, list, field, filterId, colorFn){
  const cnt={};
  list.forEach(function(x){ const k=x[field]||'Non renseigne'; cnt[k]=(cnt[k]||0)+1; });
  const arr=Object.keys(cnt).map(k=>({k:k,v:cnt[k]})).sort((a,b)=>b.v-a.v).slice(0,10);
  const el=document.getElementById(canvasId);
  const existing=Chart.getChart(el); if(existing) existing.destroy();
  const chart=new Chart(el, {
    type:'bar',
    data:{labels:arr.map(x=>x.k), datasets:[{label:'FNC', data:arr.map(x=>x.v),
      backgroundColor:arr.map(x=>colorFn?colorFn(x.k):'rgba(35,64,143,.75)'), borderRadius:5}]},
    options:{indexAxis:'y', responsive:true, maintainAspectRatio:false,
      plugins:{legend:{display:false}, tooltip:{backgroundColor:'#1b3576',padding:10}},
      scales:{x:{beginAtZero:true,grid:{color:'#eef2f7'}},y:{grid:{display:false}}},
      onClick:function(e,el2){ if(el2.length){ $('#'+filterId).val(arr[el2[0].index].k); render(); } }
    }
  });
  chartVarSetter(chart);
}

/* ---------- Tableau croise dynamique : operateur x statut ---------- */
function renderPivot(list){
  const par={};
  list.forEach(function(x){
    const k=x.operateur||'Non renseigne';
    if(!par[k]) par[k]={1:0,2:0,3:0,4:0,total:0};
    if(par[k][x.statut]!==undefined) par[k][x.statut]++;
    par[k].total++;
  });
  const keys=Object.keys(par).sort((a,b)=>par[b].total-par[a].total);
  let h='';
  const tot={1:0,2:0,3:0,4:0,total:0};
  keys.forEach(function(k){
    const v=par[k], taux=pct(v[3],v.total);
    tot[1]+=v[1]; tot[2]+=v[2]; tot[3]+=v[3]; tot[4]+=v[4]; tot.total+=v.total;
    h+='<tr><td style="font-weight:700">'+esc(k)+'</td>'
      +'<td>'+(v[4]||'-')+'</td><td>'+(v[1]||'-')+'</td><td>'+(v[2]||'-')+'</td><td>'+(v[3]||'-')+'</td>'
      +'<td style="font-weight:800;color:#23408F">'+v.total+'</td>'
      +'<td><div class="d-flex align-items-center gap-2 justify-content-center"><div style="flex:1">'+bar(taux,C_GREEN)+'</div><span style="font-weight:700;min-width:38px">'+taux+'%</span></div></td></tr>';
  });
  if(!h){ h='<tr><td colspan="7" class="text-center text-muted">Aucune donnee pour ce filtre</td></tr>'; }
  else {
    const tauxTot=pct(tot[3],tot.total);
    h+='<tr class="tot"><td>Total general</td><td>'+tot[4]+'</td><td>'+tot[1]+'</td><td>'+tot[2]+'</td><td>'+tot[3]+'</td>'
      +'<td>'+tot.total+'</td><td>'+tauxTot+'%</td></tr>';
  }
  $('#pivotTable tbody').html(h);
}

/* ---------- Rendu global ---------- */
function render(){
  const list=filtered();
  renderChips();

  const cnt={1:0,2:0,3:0,4:0};
  list.forEach(function(x){ if(cnt[x.statut]!==undefined) cnt[x.statut]++; });
  const totalFerme=cnt[3]||0;

  // Categories
  const nCrit=list.filter(x=>x.categorie==='critique').length;
  const nMaj =list.filter(x=>x.categorie==='majeur').length;
  const nMin =list.filter(x=>x.categorie==='mineur').length;
  // En retard : date de reponse exigee depassee ET fiche non fermee
  const today=new Date().toISOString().substring(0,10);
  const nRetard=list.filter(function(x){ return x.d_reponse && x.d_reponse<today && x.statut!==3; }).length;
  // NCNS attendus : somme comptee une fois par audit
  const parAudit={};
  list.forEach(function(x){ if(x.idaudit){ parAudit[x.idaudit]=x.audit_ncns||0; } });
  let ncns=0; for(const k in parAudit){ ncns+=parAudit[k]; }
  const reste=Math.max(0, ncns-list.length);
  const tauxSaisie = ncns>0 ? Math.round((list.length/ncns)*1000)/10 : 0;

  $('#kpis').html(
     kpi('k-blue','bi-collection', list.length,'Total FNC')
    +kpi('k-grey','bi-list-ol', ncns,'NCNS attendus')
    +kpi('k-gold','bi-pencil-square', reste,'Reste a saisir')
    +kpi('k-green','bi-percent', (ncns>0?tauxSaisie+' %':'-'),'Taux de saisie')
    +kpi('k-blue','bi-hourglass-split', cnt[4],'Ouvert')
    +kpi('k-red','bi-clock-history', nRetard,'En retard')
    +kpi('k-red','bi-exclamation-octagon', nCrit,'Critiques')
    +kpi('k-gold','bi-exclamation-triangle', nMaj,'Majeures')
    +kpi('k-green','bi-info-circle', nMin,'Mineures')
    +kpi('k-green','bi-check2-circle', cnt[3],'Ferme')
    +kpi('k-grey','bi-percent', pct(totalFerme,list.length)+' %','Taux de fermeture')
  );

  drawEvol(list);
  drawStatut(list);
  topBarChart('chDomaine', c=>chDomaine=c, list, 'domaine', 'f_dom');
  topBarChart('chSite',    c=>chSite=c,    list, 'site',    'f_site');
  topBarChart('chCategorie', c=>chCategorie=c, list, 'categorie', 'f_cat', k=>CAT_COL[k]||'#7A8798');
  renderPivot(list);

  const rows=list.map(function(x){
    return [esc(x.num), x.annee||'-', esc(x.operateur), esc(x.domaine), esc(x.site),
            esc(x.ra), esc(x.agent),
            badgeCat(x.categorie), badgeStatut(x.statut), fmtDate(x.d_emission), fmtDate(x.d_limite), fmtDate(x.d_cloture)];
  });
  if(fncTable){ fncTable.clear(); fncTable.rows.add(rows); fncTable.draw(false); }
  else {
    fncTable=$('#fncTable').DataTable({
      data:rows, order:[[9,'desc']], pageLength:10,
      columnDefs:[{targets:[1,7,8,9,10,11],className:'text-center'}],
      language:{search:'Rechercher :',lengthMenu:'Afficher _MENU_ lignes',info:'_START_ a _END_ sur _TOTAL_',
        infoEmpty:'0 fiche',zeroRecords:'Aucune fiche',emptyTable:'Aucune fiche de non-conformite',
        paginate:{first:'Premier',previous:'Precedent',next:'Suivant',last:'Dernier'}}
    });
  }
}

$('#f_annee,#f_statut,#f_cat,#f_dom,#f_site,#f_orga').on('change', render);
$('#btnReset').on('click',function(){ $('#f_annee,#f_statut,#f_cat,#f_dom,#f_site,#f_orga').val(''); render(); });
$('#btnPrintAna').on('click',function(){ window.print(); });

fillFilters();
render();
</script>