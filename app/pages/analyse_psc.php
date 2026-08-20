<?php
/**
 * Page : Analyse PSC - Tableau de bord interactif (Programme vs Actes de supervision)
 * Module : analyse_psc  -  Route : /analyse-psc
 *
 * Tous les agregats sont calcules cote serveur au chargement (aucune dependance AJAX).
 * Securite : Rbac::guardPage, requetes preparees, sorties echappees, JSON durci.
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardPage('analyse_psc');

$pageTitle = 'Analyse PSC';
$active    = 'analyse_psc';
$pageIcon  = 'bi-bar-chart';

$MOIS_LBL = [1=>'Janv',2=>'Fevr',3=>'Mars',4=>'Avr',5=>'Mai',6=>'Juin',7=>'Juil',8=>'Aout',9=>'Sept',10=>'Oct',11=>'Nov',12=>'Dec'];

$PROGS    = [];   // programmes + metriques
$ACTES_AN = [];   // annee => [num_audit => statut]
$MATCHED  = [];   // annee => [num_audit => 1]
$HORS     = [];   // annee => nb actes hors matrice

try {
    $db = Database::getInstance();

    /* ---------- 1) Carte des actes : "ANNEE|CODE|SEMAINE" ---------- */
    $sitesByName = [];
    foreach ($db->execute("SELECT indicateur_oaci, nomsite FROM site WHERE indicateur_oaci<>''")->fetchAll() as $sx) {
        $sitesByName[mb_strtoupper(trim((string)$sx['nomsite']))] = $sx['indicateur_oaci'];
    }

    $STATUS = [];
    $auditRows = $db->execute(
        "SELECT a.num_audit, a.statut, a.date_previsionnelle, a.site_inspection,
                s.indicateur_oaci, o.trigrorganisme
         FROM audit a
         LEFT JOIN site s      ON s.idsite = a.idsite
         LEFT JOIN organisme o ON o.idorga = a.idorga
         WHERE a.date_previsionnelle IS NOT NULL AND a.date_previsionnelle <> '0000-00-00'"
    )->fetchAll();

    foreach ($auditRows as $ar) {
        try { $dt = new DateTime(substr((string)$ar['date_previsionnelle'], 0, 10)); }
        catch (Throwable $e) { continue; }
        $isoY = (int)$dt->format('o');
        $isoW = (int)$dt->format('W');
        if ($isoW < 1 || $isoW > 53) continue;
        $info = ['statut' => (int)($ar['statut'] ?? 1), 'num' => (string)$ar['num_audit']];

        $codes = [];
        $oaci = trim((string)$ar['indicateur_oaci']);
        if ($oaci === '') {
            $si = mb_strtoupper(trim((string)$ar['site_inspection']));
            if ($si !== '') { $oaci = $sitesByName[$si] ?? trim(explode(' ', $si)[0]); }
        }
        if ($oaci !== '') { $codes[] = $oaci; }
        $trg = trim((string)$ar['trigrorganisme']);
        if ($trg !== '') { $codes[] = $trg; }

        foreach ($codes as $cd) { $STATUS[$isoY.'|'.mb_strtoupper($cd).'|'.$isoW] = $info; }
        if (!isset($ACTES_AN[$isoY])) $ACTES_AN[$isoY] = [];
        $ACTES_AN[$isoY][(string)$ar['num_audit']] = (int)($ar['statut'] ?? 1);
    }

    /* ---------- 2) Programmes + metriques ---------- */
    $sqlBase =
        "SELECT p.idprogramme, p.annee, p.titre, p.statut, p.matrice, %MODE%
                t.nomtypeorg, d.nomdomaine, d.libel_domaine
         FROM psc_programme p
         LEFT JOIN type_organisme t ON t.idtypeorga = p.idtypeorga
         LEFT JOIN domaine d        ON d.iddomaine  = p.iddomaine
         ORDER BY p.annee DESC, p.idprogramme DESC";
    try   { $rows = $db->execute(str_replace('%MODE%', 'p.mode_cible,', $sqlBase))->fetchAll(); }
    catch (Throwable $e) { $rows = $db->execute(str_replace('%MODE%', '', $sqlBase))->fetchAll(); }

    foreach ($rows as $p) {
        $annee = (int)$p['annee'];
        $mode  = (($p['mode_cible'] ?? 'site') === 'operateur') ? 'operateur' : 'site';

        $mat = $p['matrice'] ? json_decode((string)$p['matrice'], true) : null;
        if (!is_array($mat)) $mat = [];
        $groupes = $mat['groupes'] ?? null;
        if ($groupes === null && isset($mat['lignes']) && is_array($mat['lignes'])) {
            $groupes = [['rubrique' => '', 'items' => $mat['lignes']]];
        }
        if (!is_array($groupes)) $groupes = [];

        $nbProg = 0; $nbDecl = 0; $nbEff = 0;
        $parStatut = [1=>0,2=>0,3=>0,4=>0,5=>0,6=>0,7=>0];
        $parCible  = [];
        $parMois   = [];
        for ($m = 1; $m <= 12; $m++) { $parMois[$m] = ['prog'=>0,'decl'=>0,'eff'=>0]; }

        foreach ($groupes as $g) {
            $items = (isset($g['items']) && is_array($g['items'])) ? $g['items'] : [];
            foreach ($items as $it) {
                $cell = (isset($it['cellules']) && is_array($it['cellules'])) ? $it['cellules'] : [];
                foreach ($cell as $sem => $val) {
                    $sem  = (int)$sem;
                    // Une cellule peut, selon le format des donnees, contenir un
                    // tableau (ex : ['code'=>..., 'indicateur'=>...]) plutot qu'une
                    // simple chaine. On extrait alors la valeur exploitable pour
                    // eviter l'avertissement "Array to string conversion".
                    if (is_array($val)) {
                        $val = $val['indicateur_oaci'] ?? $val['code'] ?? $val['valeur'] ?? $val['site'] ?? reset($val);
                        if (is_array($val)) { $val = ''; }
                    }
                    $code = mb_strtoupper(trim((string)$val));
                    if ($code === '' || $sem < 1 || $sem > 53) continue;

                    // Mois "proprietaire" de la semaine ISO (jeudi)
                    $dw = new DateTime(); $dw->setISODate($annee, $sem, 4);
                    $mo = (int)$dw->format('n');

                    $nbProg++;
                    if (!isset($parCible[$code])) $parCible[$code] = ['prog'=>0,'decl'=>0,'eff'=>0];
                    $parCible[$code]['prog']++;
                    $parMois[$mo]['prog']++;

                    $k = $annee.'|'.$code.'|'.$sem;
                    if (isset($STATUS[$k])) {
                        $st = (int)$STATUS[$k]['statut'];
                        if (!isset($MATCHED[$annee])) $MATCHED[$annee] = [];
                        $MATCHED[$annee][(string)$STATUS[$k]['num']] = 1;
                        $nbDecl++;
                        $parCible[$code]['decl']++;
                        $parMois[$mo]['decl']++;
                        if (isset($parStatut[$st])) $parStatut[$st]++;
                        if ($st === 3) { $nbEff++; $parCible[$code]['eff']++; $parMois[$mo]['eff']++; }
                    }
                }
            }
        }

        $PROGS[] = [
            'id'      => (int)$p['idprogramme'],
            'annee'   => $annee,
            'titre'   => (string)$p['titre'],
            'type'    => (string)($p['nomtypeorg'] ?? ''),
            'domaine' => trim((string)($p['nomdomaine'] ?? '')) ?: trim((string)($p['libel_domaine'] ?? '')),
            'mode'    => $mode,
            'etat'    => (string)($p['statut'] ?? 'brouillon'),
            'prog'    => $nbProg,
            'decl'    => $nbDecl,
            'eff'     => $nbEff,
            'statuts' => $parStatut,
            'cibles'  => $parCible,
            'mois'    => $parMois,
        ];
    }

    foreach ($ACTES_AN as $an => $nums) {
        $HORS[$an] = max(count($nums) - count($MATCHED[$an] ?? []), 0);
    }
} catch (Throwable $e) {
    error_log('analyse_psc: ' . $e->getMessage());
    $PROGS = []; $HORS = [];
}

require_once INCLUDES_PATH . '/layout_head.php';
?>
<style>
.tdb-bar{background:linear-gradient(135deg,#23408F,#1b3576);border-radius:14px;padding:14px 18px;margin-bottom:16px;color:#fff}
.tdb-bar label{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#c9d6f0;margin-bottom:3px;display:block}
.tdb-bar .form-select{border:none;border-radius:8px;font-size:.83rem;font-weight:600;color:#23408F}
.kpi{background:#fff;border:1px solid #e6ebf3;border-radius:14px;padding:14px;position:relative;overflow:hidden;transition:.18s;height:100%}
.kpi:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(35,64,143,.12)}
.kpi .v{font-size:1.6rem;font-weight:800;line-height:1.05;color:#2C3E50}
.kpi .l{font-size:.74rem;color:#6b7a90;text-transform:uppercase;letter-spacing:.4px;font-weight:600;margin-top:2px}
.kpi .ic{position:absolute;right:10px;top:10px;font-size:1.6rem;opacity:.16}
.kpi.k-blue{border-left:4px solid #23408F}.kpi.k-gold{border-left:4px solid #E8890C}
.kpi.k-green{border-left:4px solid #1E9C4B}.kpi.k-red{border-left:4px solid #D32F2F}
.kpi.k-grey{border-left:4px solid #7A8798}
.panel{background:#fff;border:1px solid #e6ebf3;border-radius:14px;padding:14px;height:100%}
.panel h6{color:#23408F;font-weight:700;font-size:.9rem;margin-bottom:10px;display:flex;align-items:center;gap:6px}
.panel .hint{font-size:.72rem;color:#93a1b5;font-weight:400;margin-left:auto}
.chart-box{position:relative;height:280px}
.chart-box.sm{height:230px}
#anaTable thead th{background:var(--anac-primary)!important;color:#fff!important;text-transform:uppercase;letter-spacing:.4px;font-size:.74rem}
.chip{display:inline-flex;align-items:center;gap:5px;background:#eef3fb;color:#23408F;border-radius:50px;padding:3px 11px;font-size:.76rem;font-weight:600;margin-right:5px}
.chip .x{cursor:pointer;font-weight:800}
#evolTable thead th{background:var(--anac-primary)!important;color:#fff!important;text-transform:uppercase;letter-spacing:.4px;font-size:.7rem}
.bar-wrap{background:#eef2f7;border-radius:50px;height:15px;overflow:hidden;min-width:80px}
.bar-in{height:100%;border-radius:50px}
#statutTable td{padding:.32rem .4rem;font-size:.8rem;border-color:#f0f3f8}
#statutTable tr.tot td{border-top:2px solid #23408F;font-weight:800;color:#23408F}
@media print{.tdb-bar,.no-print{display:none!important}}
</style>

<div class="page-head">
  <div>
    <h1><i class="bi bi-bar-chart me-2" style="color:var(--anac-primary)"></i>Analyse PSC</h1>
    <div class="sub">Tableau de bord interactif : programme de surveillance vs actes de supervision realises.</div>
  </div>
  <div class="d-flex gap-2 no-print">
    <button class="btn btn-outline-secondary" id="btnReset"><i class="bi bi-arrow-counterclockwise me-1"></i>Reinitialiser</button>
    <button class="btn btn-outline-danger" id="btnPrintAna"><i class="bi bi-printer me-1"></i>Imprimer</button>
  </div>
</div>

<div class="tdb-bar no-print">
  <div class="row g-2 align-items-end">
    <div class="col-6 col-md-3 col-lg-2"><label>Annee</label><select class="form-select form-select-sm" id="f_annee"></select></div>
    <div class="col-6 col-md-3 col-lg-3"><label>Type d'activite</label><select class="form-select form-select-sm" id="f_type"></select></div>
    <div class="col-6 col-md-3 col-lg-3"><label>Domaine</label><select class="form-select form-select-sm" id="f_dom"></select></div>
    <div class="col-6 col-md-3 col-lg-2"><label>Mode</label>
      <select class="form-select form-select-sm" id="f_mode"><option value="">Tous</option><option value="site">Site</option><option value="operateur">Operateur</option></select>
    </div>
    <div class="col-12 col-lg-2"><label>Cible</label><select class="form-select form-select-sm" id="f_cible"></select></div>
  </div>
  <div class="mt-2" id="chips"></div>
</div>

<div class="row g-3 mb-3" id="kpis"></div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="panel">
      <h6><i class="bi bi-graph-up-arrow"></i>Evolution d'une annee sur l'autre <span class="hint">cliquez un point pour filtrer l'annee</span></h6>
      <div class="chart-box"><canvas id="chEvol"></canvas></div>
      <div class="table-responsive mt-3">
        <table id="evolTable" class="table table-sm align-middle mb-0">
          <thead><tr>
            <th>Annee</th><th class="text-center">Programmes</th><th class="text-center">Actes programmes</th>
            <th class="text-center">Declenches</th><th class="text-center">Effectues</th>
            <th style="min-width:150px">Taux de couverture</th><th style="min-width:150px">Taux de realisation</th>
          </tr></thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="panel">
      <h6><i class="bi bi-pie-chart"></i>Repartition par statut</h6>
      <div class="chart-box"><canvas id="chStatut"></canvas></div>
      <table class="table table-sm align-middle mb-0 mt-2" id="statutTable"></table>
      <div class="small text-muted mt-2" id="horsProgTxt"></div>
    </div>
  </div>
</div>

<div class="row g-3 mt-0">
  <div class="col-lg-7">
    <div class="panel">
      <h6><i class="bi bi-calendar3"></i>Repartition mensuelle <span class="hint">programme vs realise</span></h6>
      <div class="chart-box sm"><canvas id="chMois"></canvas></div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="panel">
      <h6><i class="bi bi-geo-alt"></i>Top cibles <span class="hint">cliquez une barre pour filtrer</span></h6>
      <div class="chart-box sm"><canvas id="chCible"></canvas></div>
    </div>
  </div>
</div>

<div class="panel mt-3">
  <h6><i class="bi bi-table"></i>Comparatif par programme</h6>
  <div class="table-responsive">
    <table id="anaTable" class="table table-hover align-middle" style="width:100%">
      <thead><tr>
        <th>Annee</th><th>Programme</th><th>Type</th><th>Domaine</th><th>Mode</th><th>Etat</th>
        <th class="text-center">Prog.</th><th class="text-center">Decl.</th><th class="text-center">Eff.</th>
        <th class="text-center">Couverture</th><th class="text-center">Realisation</th>
      </tr></thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
/* Donnees agregees calculees cote serveur */
const PROGS = <?php echo json_encode($PROGS, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
const HORS  = <?php echo json_encode($HORS,  JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
const MOIS  = <?php echo json_encode(array_values($MOIS_LBL), JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;

const ST_LBL={1:'Planifie',2:'Reporte',3:'Effectue',4:'Suspendu',5:'A surveiller',6:'Annule',7:'Inopine'};
const ST_COL={1:'#23408F',2:'#E8890C',3:'#1E9C4B',4:'#D32F2F',5:'#C62828',6:'#8E1F1F',7:'#7A8798'};
const C_BLUE='#23408F', C_GOLD='#E8890C', C_GREEN='#1E9C4B';

let chEvol=null, chStatut=null, chMois=null, chCible=null, anaTable=null;

function esc(s){ const d=document.createElement('div'); d.textContent=(s==null?'':s); return d.innerHTML; }
function pct(n,t){ return t? Math.round(n*1000/t)/10 : 0; }
function badgeTaux(v){ const c=v>=75?'b-green':(v>=40?'b-gold':'b-red'); return '<span class="badge-soft '+c+'">'+v+' %</span>'; }
function kpi(cls,ic,val,lbl){
  return '<div class="col-6 col-md-4 col-lg-2"><div class="kpi '+cls+'"><i class="bi '+ic+' ic"></i>'
    +'<div class="v">'+val+'</div><div class="l">'+lbl+'</div></div></div>';
}

/* ---------- Filtres ---------- */
function fillFilters(){
  const annees=[...new Set(PROGS.map(p=>p.annee))].sort((a,b)=>b-a);
  const types =[...new Set(PROGS.map(p=>p.type).filter(Boolean))].sort();
  const doms  =[...new Set(PROGS.map(p=>p.domaine).filter(Boolean))].sort();
  const cibles=[...new Set(PROGS.flatMap(p=>Object.keys(p.cibles||{})))].sort();
  $('#f_annee').html('<option value="">Toutes</option>'+annees.map(a=>'<option value="'+a+'">'+a+'</option>').join(''));
  $('#f_type').html('<option value="">Tous</option>'+types.map(t=>'<option value="'+esc(t)+'">'+esc(t)+'</option>').join(''));
  $('#f_dom').html('<option value="">Tous</option>'+doms.map(d=>'<option value="'+esc(d)+'">'+esc(d)+'</option>').join(''));
  $('#f_cible').html('<option value="">Toutes</option>'+cibles.map(c=>'<option value="'+esc(c)+'">'+esc(c)+'</option>').join(''));
}
function filtres(){
  return {an:$('#f_annee').val(), ty:$('#f_type').val(), dm:$('#f_dom').val(), md:$('#f_mode').val(), cb:$('#f_cible').val()};
}
function filtered(){
  const f=filtres();
  return PROGS.filter(function(p){
    if(f.an && String(p.annee)!==String(f.an)) return false;
    if(f.ty && p.type!==f.ty) return false;
    if(f.dm && p.domaine!==f.dm) return false;
    if(f.md && p.mode!==f.md) return false;
    if(f.cb && !(p.cibles && p.cibles[f.cb])) return false;
    return true;
  });
}
function renderChips(){
  const f=filtres(); let h='';
  const add=(k,v,lbl)=>{ if(v) h+='<span class="chip">'+esc(lbl)+' : '+esc(v)+' <span class="x" data-f="'+k+'">&times;</span></span>'; };
  add('f_annee',f.an,'Annee'); add('f_type',f.ty,'Type'); add('f_dom',f.dm,'Domaine');
  add('f_mode',f.md,'Mode'); add('f_cible',f.cb,'Cible');
  $('#chips').html(h);
}
$(document).on('click','.chip .x',function(){ $('#'+$(this).data('f')).val(''); render(); });

/* ---------- Agregation ---------- */
function agrege(list){
  const f=filtres();
  let prog=0,decl=0,eff=0;
  const statuts={1:0,2:0,3:0,4:0,5:0,6:0,7:0};
  const cibles={};
  const mois=Array.from({length:12},()=>({prog:0,decl:0,eff:0}));
  list.forEach(function(p){
    if(f.cb){ // focus sur une cible : on ne compte que celle-ci
      const c=p.cibles[f.cb]; if(!c) return;
      prog+=c.prog; decl+=c.decl; eff+=c.eff;
      if(!cibles[f.cb]) cibles[f.cb]={prog:0,decl:0,eff:0};
      cibles[f.cb].prog+=c.prog; cibles[f.cb].decl+=c.decl; cibles[f.cb].eff+=c.eff;
      return;
    }
    prog+=p.prog; decl+=p.decl; eff+=p.eff;
    Object.keys(p.statuts||{}).forEach(k=>statuts[k]=(statuts[k]||0)+p.statuts[k]);
    Object.keys(p.cibles||{}).forEach(function(c){
      if(!cibles[c]) cibles[c]={prog:0,decl:0,eff:0};
      cibles[c].prog+=p.cibles[c].prog; cibles[c].decl+=p.cibles[c].decl; cibles[c].eff+=p.cibles[c].eff;
    });
    for(let m=1;m<=12;m++){
      const v=(p.mois&&p.mois[m])?p.mois[m]:{prog:0,decl:0,eff:0};
      mois[m-1].prog+=v.prog; mois[m-1].decl+=v.decl; mois[m-1].eff+=v.eff;
    }
  });
  return {prog,decl,eff,statuts,cibles,mois};
}

/* ---------- Graphiques ---------- */
function drawEvol(){
  const f=filtres();
  const parAn={};
  PROGS.filter(function(p){
    if(f.ty && p.type!==f.ty) return false;
    if(f.dm && p.domaine!==f.dm) return false;
    if(f.md && p.mode!==f.md) return false;
    return true;
  }).forEach(function(p){
    if(!parAn[p.annee]) parAn[p.annee]={prog:0,decl:0,eff:0};
    parAn[p.annee].prog+=p.prog; parAn[p.annee].decl+=p.decl; parAn[p.annee].eff+=p.eff;
  });
  const labels=Object.keys(parAn).sort((a,b)=>a-b);
  const prog=labels.map(a=>parAn[a].prog), decl=labels.map(a=>parAn[a].decl), eff=labels.map(a=>parAn[a].eff);
  const tauxR=labels.map(a=>pct(parAn[a].eff,parAn[a].prog));
  if(chEvol) chEvol.destroy();
  chEvol=new Chart(document.getElementById('chEvol'), {
    data:{labels:labels, datasets:[
      {type:'line', label:'Actes programmes', data:prog, borderColor:C_BLUE, backgroundColor:'rgba(35,64,143,.12)', tension:.35, fill:true, borderWidth:3, pointRadius:5, pointHoverRadius:8},
      {type:'line', label:'Declenches', data:decl, borderColor:C_GOLD, backgroundColor:'rgba(232,137,12,.10)', tension:.35, fill:true, borderWidth:3, pointRadius:5},
      {type:'line', label:'Effectues', data:eff, borderColor:C_GREEN, backgroundColor:'rgba(30,156,75,.10)', tension:.35, fill:true, borderWidth:3, pointRadius:5},
      {type:'line', label:'Taux de realisation (%)', data:tauxR, borderColor:'#7A8798', borderDash:[6,4], tension:.35, fill:false, borderWidth:2, pointRadius:4, yAxisID:'y2'}
    ]},
    options:{
      responsive:true, maintainAspectRatio:false, interaction:{mode:'index',intersect:false},
      plugins:{legend:{position:'bottom',labels:{boxWidth:12,font:{size:11}}},
        tooltip:{backgroundColor:'#1b3576',padding:10}},
      scales:{y:{beginAtZero:true,title:{display:true,text:'Nombre d\'actes'},grid:{color:'#eef2f7'}},
              y2:{beginAtZero:true,max:100,position:'right',title:{display:true,text:'%'},grid:{drawOnChartArea:false}},
              x:{grid:{display:false}}},
      onClick:function(e,el){ if(el.length){ $('#f_annee').val(labels[el[0].index]); render(); } }
    }
  });
}
function drawStatut(a){
  const ks=[1,2,3,4,5,6,7].filter(k=>(a.statuts[k]||0)>0);
  if(chStatut) chStatut.destroy();
  chStatut=new Chart(document.getElementById('chStatut'), {
    type:'doughnut',
    data:{labels:ks.map(k=>ST_LBL[k]), datasets:[{data:ks.map(k=>a.statuts[k]),
      backgroundColor:ks.map(k=>ST_COL[k]), borderWidth:2, borderColor:'#fff'}]},
    options:{responsive:true,maintainAspectRatio:false,cutout:'58%',
      plugins:{legend:{position:'bottom',labels:{boxWidth:12,font:{size:11}}},
        tooltip:{backgroundColor:'#1b3576',padding:10,callbacks:{label:function(c){
          const t=c.dataset.data.reduce((x,y)=>x+y,0); return ' '+c.label+' : '+c.parsed+' ('+pct(c.parsed,t)+'%)'; }}}}}
  });
  if(!ks.length){ chStatut.destroy(); chStatut=null;
    document.getElementById('chStatut').getContext('2d').clearRect(0,0,999,999); }
}
function drawMois(a){
  if(chMois) chMois.destroy();
  chMois=new Chart(document.getElementById('chMois'), {
    data:{labels:MOIS, datasets:[
      {type:'bar', label:'Programmes', data:a.mois.map(m=>m.prog), backgroundColor:'rgba(35,64,143,.75)', borderRadius:5, barPercentage:.8},
      {type:'bar', label:'Declenches', data:a.mois.map(m=>m.decl), backgroundColor:'rgba(232,137,12,.85)', borderRadius:5, barPercentage:.8},
      {type:'line', label:'Effectues', data:a.mois.map(m=>m.eff), borderColor:C_GREEN, backgroundColor:'rgba(30,156,75,.15)', tension:.35, borderWidth:3, pointRadius:4, fill:true}
    ]},
    options:{responsive:true,maintainAspectRatio:false,
      plugins:{legend:{position:'bottom',labels:{boxWidth:12,font:{size:11}}},tooltip:{backgroundColor:'#1b3576',padding:10}},
      scales:{y:{beginAtZero:true,grid:{color:'#eef2f7'}},x:{grid:{display:false}}}}
  });
}
function drawCible(a){
  const arr=Object.keys(a.cibles).map(k=>({k:k,v:a.cibles[k]}))
    .sort((x,y)=>y.v.prog-x.v.prog).slice(0,10);
  if(chCible) chCible.destroy();
  chCible=new Chart(document.getElementById('chCible'), {
    type:'bar',
    data:{labels:arr.map(x=>x.k), datasets:[
      {label:'Programmes', data:arr.map(x=>x.v.prog), backgroundColor:'rgba(35,64,143,.75)', borderRadius:5},
      {label:'Effectues',  data:arr.map(x=>x.v.eff),  backgroundColor:'rgba(30,156,75,.85)', borderRadius:5}
    ]},
    options:{indexAxis:'y', responsive:true, maintainAspectRatio:false,
      plugins:{legend:{position:'bottom',labels:{boxWidth:12,font:{size:11}}},tooltip:{backgroundColor:'#1b3576',padding:10}},
      scales:{x:{beginAtZero:true,grid:{color:'#eef2f7'}},y:{grid:{display:false}}},
      onClick:function(e,el){ if(el.length){ $('#f_cible').val(arr[el[0].index].k); render(); } }
    }
  });
}

function bar(v,color){ return '<div class="bar-wrap"><div class="bar-in" style="width:'+Math.min(v,100)+'%;background:'+color+'"></div></div>'; }

/* Liste des statuts en barres (base = actes declenches) */
function renderStatutList(a){
  const total=a.decl||0;
  let h='<tbody>';
  [1,2,3,4,5,6,7].forEach(function(k){
    const n=a.statuts[k]||0, p=pct(n,total||1);
    h+='<tr><td style="width:110px">'+esc(ST_LBL[k])+'</td><td>'+bar(p,ST_COL[k])+'</td>'
      +'<td style="width:88px;text-align:right;font-weight:700">'+n+' <span class="text-muted" style="font-weight:500">('+p+'%)</span></td></tr>';
  });
  h+='<tr class="tot"><td>Total declenches</td><td class="text-end" colspan="2">'+total+' / '+a.prog+' programmes</td></tr>';
  $('#statutTable').html(h+'</tbody>');
}

/* Tableau d'evolution annuelle */
function renderEvolTable(){
  const f=filtres();
  const parAn={};
  PROGS.filter(function(p){
    if(f.ty && p.type!==f.ty) return false;
    if(f.dm && p.domaine!==f.dm) return false;
    if(f.md && p.mode!==f.md) return false;
    return true;
  }).forEach(function(p){
    if(!parAn[p.annee]) parAn[p.annee]={nb:0,prog:0,decl:0,eff:0};
    parAn[p.annee].nb++; parAn[p.annee].prog+=p.prog; parAn[p.annee].decl+=p.decl; parAn[p.annee].eff+=p.eff;
  });
  const annees=Object.keys(parAn).sort((a,b)=>b-a);
  let h='';
  annees.forEach(function(an){
    const v=parAn[an], cv=pct(v.decl,v.prog), rl=pct(v.eff,v.prog);
    const sel=(f.an && String(f.an)===String(an))?' style="background:#eef3fb"':'';
    h+='<tr'+sel+'><td class="fw-bold">'+esc(an)+'</td><td class="text-center">'+v.nb+'</td>'
      +'<td class="text-center">'+v.prog+'</td><td class="text-center">'+v.decl+'</td><td class="text-center">'+v.eff+'</td>'
      +'<td><div class="d-flex align-items-center gap-2">'+bar(cv,C_BLUE)+'<span style="font-weight:700">'+cv+'%</span></div></td>'
      +'<td><div class="d-flex align-items-center gap-2">'+bar(rl,C_GREEN)+'<span style="font-weight:700">'+rl+'%</span></div></td></tr>';
  });
  if(!h) h='<tr><td colspan="7" class="text-center text-muted">Aucune donnee</td></tr>';
  $('#evolTable tbody').html(h);
}

/* ---------- Rendu global ---------- */
function render(){
  const list=filtered();
  const a=agrege(list);
  renderChips();

  $('#kpis').html(
     kpi('k-blue','bi-journal-text',   list.length,'Programmes')
    +kpi('k-blue','bi-calendar3-week', a.prog,'Actes programmes')
    +kpi('k-gold','bi-rocket-takeoff', a.decl,'Declenches')
    +kpi('k-green','bi-check2-circle', a.eff,'Effectues')
    +kpi('k-grey','bi-percent',        pct(a.decl,a.prog)+' %','Couverture')
    +kpi('k-green','bi-graph-up',      pct(a.eff,a.prog)+' %','Realisation')
  );

  drawEvol(); drawStatut(a); drawMois(a); drawCible(a);
  renderStatutList(a); renderEvolTable();

  const f=filtres();
  let hors=0;
  Object.keys(HORS||{}).forEach(function(an){
    if(f.an && String(an)!==String(f.an)) return;
    hors += Number(HORS[an]||0);
  });
  $('#horsProgTxt').html('<i class="bi bi-info-circle me-1"></i>'+hors+' acte(s) hors matrice du programme (inopines ou hors planification).');

  const rows=list.map(function(p){
    const cv=pct(p.decl,p.prog), rl=pct(p.eff,p.prog);
    const etat=(p.etat==='valide')?'<span class="badge-soft b-green">Valide</span>':'<span class="badge-soft b-gold">Brouillon</span>';
    const mode=(p.mode==='operateur')?'<span class="badge-soft b-blue">Operateur</span>':'<span class="badge-soft b-grey">Site</span>';
    return [p.annee, esc(p.titre), esc(p.type), esc(p.domaine), mode, etat, p.prog, p.decl, p.eff, badgeTaux(cv), badgeTaux(rl)];
  });
  if(anaTable){ anaTable.clear(); anaTable.rows.add(rows); anaTable.draw(false); }
  else {
    anaTable=$('#anaTable').DataTable({
      data:rows, order:[[0,'desc']], pageLength:10,
      columnDefs:[{targets:[6,7,8,9,10],className:'text-center'}],
      language:{search:'Rechercher :',lengthMenu:'Afficher _MENU_ lignes',info:'_START_ a _END_ sur _TOTAL_',
        infoEmpty:'0 programme',zeroRecords:'Aucun programme',emptyTable:'Aucun programme',
        paginate:{first:'Premier',previous:'Precedent',next:'Suivant',last:'Dernier'}}
    });
  }
}

$('#f_annee,#f_type,#f_dom,#f_mode,#f_cible').on('change', render);
$('#btnReset').on('click',function(){ $('#f_annee,#f_type,#f_dom,#f_mode,#f_cible').val(''); render(); });
$('#btnPrintAna').on('click',function(){ window.print(); });

fillFilters();
render();
</script>