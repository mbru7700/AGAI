<?php
/**
 * Page : Tentatives de connexion - Power BI style
 * Meme design que audit-logs
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardPage('cybersecurite');
$csrf      = Security::generateCSRF();
$pageTitle = 'Tentatives de connexion';
$active    = 'login_attempts';
$pageIcon  = 'bi-shield-exclamation';
require_once INCLUDES_PATH . '/layout_head.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:10px;margin-bottom:16px;}
.kpi-card{background:#fff;border:1px solid #eef1f6;border-radius:12px;padding:13px 14px;box-shadow:0 1px 3px rgba(16,30,54,.04);position:relative;overflow:hidden;}
.kpi-card::before{content:'';position:absolute;top:0;left:0;width:4px;height:100%;}
.kc-red::before{background:#D32F2F;} .kc-gold::before{background:#F3C300;}
.kc-blue::before{background:#23408F;} .kc-dark::before{background:#2C3E50;}
.kpi-num{font-size:1.65rem;font-weight:800;line-height:1;color:#2C3E50;}
.kpi-lbl{font-size:.72rem;color:#7b8aa0;margin-top:3px;}
.kpi-delta{font-size:.72rem;font-weight:700;margin-top:4px;}
.compare-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px;}
.compare-card{background:#fff;border:1px solid #eef1f6;border-radius:12px;padding:12px 14px;box-shadow:0 1px 3px rgba(16,30,54,.04);}
.compare-label{font-size:.72rem;color:#7b8aa0;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;}
.compare-vals{display:flex;justify-content:space-between;align-items:flex-end;gap:10px;}
.compare-today{font-size:1.5rem;font-weight:800;}
.compare-yest{font-size:.88rem;color:#9aa7bd;font-weight:600;}
.compare-badge{font-size:.72rem;font-weight:700;padding:2px 7px;border-radius:20px;}
.badge-up{background:#d1fae5;color:#065f46;} .badge-down{background:#fee2e2;color:#991b1b;} .badge-eq{background:#f1f5f9;color:#64748b;}
.chart-card{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:14px;box-shadow:0 1px 3px rgba(16,30,54,.04);}
.chart-title{font-size:.79rem;font-weight:700;color:#D32F2F;display:flex;align-items:center;gap:6px;margin-bottom:10px;text-transform:uppercase;letter-spacing:.04em;}
.filter-bar{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:12px 16px;margin-bottom:14px;box-shadow:0 1px 3px rgba(16,30,54,.04);}
table.tbl{width:100%;border-collapse:separate;border-spacing:0;}
table.tbl thead th{background:#D32F2F;color:#fff;font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;padding:9px 12px;border:none;font-weight:600;white-space:nowrap;}
table.tbl tbody td{padding:9px 12px;border-bottom:1px solid #f1f4f9;vertical-align:middle;font-size:.84rem;}
table.tbl tbody tr:hover{background:#fff5f5;}
.ip-card{background:#fff5f5;border:1px solid #fecaca;border-radius:10px;padding:10px 14px;margin-bottom:6px;display:flex;align-items:center;gap:12px;cursor:pointer;transition:all .15s;}
.ip-card:hover{background:#fee2e2;border-color:#D32F2F;transform:translateX(2px);}
.ip-badge{background:#D32F2F;color:#fff;border-radius:20px;padding:2px 8px;font-size:.72rem;font-weight:800;}
.threat-critical{background:#7f1d1d;color:#fff;} .threat-high{background:#D32F2F;color:#fff;} .threat-med{background:#F3C300;color:#2C3E50;}
.email-card{background:#fafcff;border:1px solid #e8f0fe;border-radius:8px;padding:8px 12px;margin-bottom:5px;display:flex;align-items:center;justify-content:space-between;}
.bloq-card{background:#fff8e0;border:1px solid #fde68a;border-radius:8px;padding:8px 12px;margin-bottom:5px;display:flex;align-items:center;justify-content:space-between;}
.hour-grid{display:grid;grid-template-columns:repeat(12,1fr);gap:3px;margin-bottom:6px;}
.hour-cell{border-radius:4px;height:30px;display:flex;align-items:center;justify-content:center;font-size:.66rem;font-weight:700;cursor:help;}
.pagi{display:flex;gap:4px;justify-content:center;margin-top:12px;flex-wrap:wrap;}
.pagi-btn{padding:5px 11px;border:1px solid #e2e8f0;border-radius:6px;background:#fff;font-size:.82rem;cursor:pointer;transition:all .15s;}
.pagi-btn:hover,.pagi-btn.active{background:#D32F2F;color:#fff;border-color:#D32F2F;}
.empty{padding:30px;text-align:center;color:#9aa7bd;}
.alert-bloq{background:linear-gradient(135deg,rgba(211,47,47,.08),rgba(211,47,47,.03));border:1px solid rgba(211,47,47,.2);border-left:4px solid #D32F2F;border-radius:10px;padding:12px 16px;margin-bottom:14px;}
</style>

<div class="page-head">
  <div>
    <h1><i class="bi bi-shield-exclamation me-2" style="color:#D32F2F"></i>Tentatives de connexion</h1>
    <div class="sub">Detection des intrusions et analyse des attaques brute force - AGAI.</div>
  </div>
  <div class="d-flex gap-2 align-items-center">
    <span class="badge" style="background:#fee2e2;color:#991b1b;font-size:.78rem" id="lastRefresh"></span>
    <button class="btn btn-sm btn-outline-secondary" id="btnRefresh"><i class="bi bi-arrow-clockwise me-1"></i>Actualiser</button>
  </div>
</div>

<!-- Alerte comptes bloques -->
<div id="alerteBloques" style="display:none" class="alert-bloq mb-3">
  <div class="d-flex align-items-center gap-2">
    <i class="bi bi-exclamation-triangle-fill" style="color:#D32F2F;font-size:1.1rem"></i>
    <div>
      <strong style="color:#D32F2F"><span id="nbBloques">0</span> compte(s) actuellement verrouille(s)</strong>
      <div style="font-size:.8rem;color:#555">Ces comptes ont depasse le seuil de tentatives. Cliquez sur "Debloquer" pour restaurer l'acces.</div>
    </div>
    <button class="btn btn-sm ms-auto" style="background:#D32F2F;color:#fff" onclick="openBloques()">
      <i class="bi bi-unlock me-1"></i>Voir et debloquer
    </button>
  </div>
</div>

<!-- ===== FILTRES ===== -->
<div class="filter-bar mb-3">
  <div class="d-flex align-items-center gap-2 mb-2">
    <i class="bi bi-funnel-fill" style="color:#D32F2F"></i>
    <strong style="color:#D32F2F;font-size:.82rem">Filtres</strong>
    <span class="badge ms-1" id="filterBadge" style="background:#fee2e2;color:#991b1b;font-size:.72rem">Mois courant</span>
    <button class="btn btn-xs btn-outline-secondary ms-auto" id="btnReset" style="font-size:.72rem;padding:2px 8px"><i class="bi bi-x-lg me-1"></i>Reset</button>
  </div>
  <div class="row g-2">
    <div class="col-6 col-md-3">
      <div style="font-size:.72rem;font-weight:700;color:#5b6b85;text-transform:uppercase;margin-bottom:3px">Annee</div>
      <select id="f_annee" style="width:100%"><option value="">Toutes</option></select>
    </div>
    <div class="col-6 col-md-3">
      <div style="font-size:.72rem;font-weight:700;color:#5b6b85;text-transform:uppercase;margin-bottom:3px">Mois</div>
      <select id="f_mois" class="form-select form-select-sm">
        <option value="">Tous</option>
        <?php $mn=['01'=>'Janvier','02'=>'Fevrier','03'=>'Mars','04'=>'Avril','05'=>'Mai','06'=>'Juin','07'=>'Juillet','08'=>'Aout','09'=>'Septembre','10'=>'Octobre','11'=>'Novembre','12'=>'Decembre'];
        foreach($mn as $k=>$v): ?><option value="<?php echo $k; ?>"><?php echo $v; ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <div style="font-size:.72rem;font-weight:700;color:#5b6b85;text-transform:uppercase;margin-bottom:3px">Date precise</div>
      <input type="date" class="form-control form-control-sm" id="f_date">
    </div>
    <div class="col-6 col-md-3">
      <div style="font-size:.72rem;font-weight:700;color:#5b6b85;text-transform:uppercase;margin-bottom:3px">Adresse IP</div>
      <input type="text" class="form-control form-control-sm" id="f_ip" placeholder="Ex: 127.0.0.1" style="font-family:monospace">
    </div>
  </div>
</div>

<!-- ===== KPI ===== -->
<div class="kpi-grid">
  <div class="kpi-card kc-red" title="Total des tentatives d'authentification echouees dans la periode">
    <div class="kpi-num" id="k_total" style="color:#D32F2F">-</div>
    <div class="kpi-lbl">Total echecs</div>
    <div class="kpi-delta" id="d_total"></div>
  </div>
  <div class="kpi-card kc-gold" title="Nombre d'adresses IP distinctes ayant genere des echecs">
    <div class="kpi-num" id="k_ips" style="color:#b58a00">-</div>
    <div class="kpi-lbl">IPs uniques</div>
    <div class="kpi-delta" id="d_ips"></div>
  </div>
  <div class="kpi-card kc-blue" title="Nombre d'adresses email distinctes ciblees par les attaques">
    <div class="kpi-num" id="k_emails" style="color:#23408F">-</div>
    <div class="kpi-lbl">Emails cibles</div>
  </div>
  <div class="kpi-card kc-red" title="Tentatives survenues dans la derniere heure (alerte en temps reel)">
    <div class="kpi-num" id="k_heure" style="color:#D32F2F">-</div>
    <div class="kpi-lbl">Derniere heure</div>
  </div>
  <div class="kpi-card kc-dark" title="Comptes verrouilles en ce moment (depassement seuil 5 tentatives)">
    <div class="kpi-num" id="k_bloques" style="color:#D32F2F;cursor:pointer" onclick="openBloques()">-</div>
    <div class="kpi-lbl">Comptes verrou.</div>
  </div>
</div>

<!-- ===== COMPARAISON ===== -->
<div class="compare-grid mb-3">
  <div class="compare-card">
    <div class="compare-label"><i class="bi bi-calendar-day me-1"></i>Echecs connexion</div>
    <div class="compare-vals">
      <div><div class="compare-today" id="c_ech_today" style="color:#D32F2F">-</div><div style="font-size:.72rem;color:#7b8aa0">Aujourd'hui</div></div>
      <div><div class="compare-yest" id="c_ech_yest">-</div><div style="font-size:.72rem;color:#7b8aa0">Hier</div></div>
      <div class="compare-badge" id="c_ech_badge">-</div>
    </div>
  </div>
  <div class="compare-card">
    <div class="compare-label"><i class="bi bi-geo-alt me-1"></i>IPs attaquantes</div>
    <div class="compare-vals">
      <div><div class="compare-today" id="c_ip_today" style="color:#b58a00">-</div><div style="font-size:.72rem;color:#7b8aa0">Aujourd'hui</div></div>
      <div><div class="compare-yest" id="c_ip_yest">-</div><div style="font-size:.72rem;color:#7b8aa0">Hier</div></div>
      <div class="compare-badge" id="c_ip_badge">-</div>
    </div>
  </div>
  <div class="compare-card">
    <div class="compare-label"><i class="bi bi-lock me-1" style="color:#D32F2F"></i>Niveau de menace</div>
    <div style="display:flex;align-items:center;gap:10px;margin-top:6px">
      <div id="threatLevel" style="font-size:1.1rem;font-weight:800">-</div>
      <div id="threatDesc" style="font-size:.78rem;color:#7b8aa0">Calcul en cours...</div>
    </div>
  </div>
</div>

<!-- ===== LIGNE 1 : Courbe + Heatmap ===== -->
<div class="row g-3 mb-3">
  <div class="col-md-7">
    <div class="chart-card">
      <div class="chart-title"><i class="bi bi-graph-up-arrow"></i>Evolution des tentatives d'intrusion (echecs / IPs)</div>
      <div style="height:200px;position:relative"><canvas id="chartCourbe" role="img" aria-label="Courbe tentatives">Courbe</canvas></div>
    </div>
  </div>
  <div class="col-md-5">
    <div class="chart-card">
      <div class="chart-title"><i class="bi bi-clock-history"></i>Heures d'attaque (intensite sur 24h)</div>
      <div id="heatmap"></div>
      <div class="d-flex justify-content-between mt-1" style="font-size:.68rem;color:#9aa7bd">
        <span>00h</span><span>06h</span><span>12h</span><span>18h</span><span>23h</span>
      </div>
    </div>
  </div>
</div>

<!-- ===== LIGNE 2 : Top IPs + Top emails ===== -->
<div class="row g-3 mb-3">
  <div class="col-md-6">
    <div class="chart-card" style="max-height:300px;overflow-y:auto">
      <div class="chart-title"><i class="bi bi-geo-alt-fill"></i>IPs les plus actives (cliquer = detail)</div>
      <div id="topIpsBox"></div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="chart-card" style="max-height:300px;overflow-y:auto">
      <div class="chart-title"><i class="bi bi-envelope-exclamation-fill"></i>Comptes les plus cibles</div>
      <div id="topEmailsBox"></div>
    </div>
  </div>
</div>

<!-- ===== JOURNAL TENTATIVES ===== -->
<div style="background:#fff;border:1px solid #fecaca;border-radius:14px;overflow:hidden;box-shadow:0 1px 3px rgba(211,47,47,.08)">
  <div class="d-flex align-items-center justify-content-between px-3 py-2" style="border-bottom:1px solid #fee2e2;background:#fff5f5">
    <div style="font-size:.8rem;font-weight:700;color:#D32F2F;text-transform:uppercase;letter-spacing:.04em">
      <i class="bi bi-list-ul me-1"></i>Journal des tentatives
    </div>
    <div class="d-flex gap-2 align-items-center">
      <input type="text" id="f_search" class="form-control form-control-sm" placeholder="Email, IP..." style="width:200px;font-size:.8rem">
      <span class="badge" style="background:#fee2e2;color:#991b1b;font-size:.72rem" id="totalBadge">-</span>
    </div>
  </div>
  <div style="overflow-x:auto">
    <table class="tbl">
      <thead><tr>
        <th>Date / Heure</th><th>Email cible</th><th>Adresse IP</th><th>User Agent</th>
      </tr></thead>
      <tbody id="tbody"><tr><td colspan="4" class="empty"><span class="spinner-border spinner-border-sm me-2"></span>Chargement...</td></tr></tbody>
    </table>
  </div>
  <div id="pagiBox" class="pagi py-2 px-3"></div>
</div>

<!-- MODALE : Detail IP -->
<div class="modal fade" id="ipModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,#991b1b,#7f1d1d)">
        <h5 class="modal-title text-white"><i class="bi bi-geo-alt-fill me-2"></i><span id="ipModalTitle">Detail IP</span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <div id="ipModalContent" style="max-height:460px;overflow-y:auto">
          <div class="text-center py-4"><span class="spinner-border text-danger"></span></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- MODALE : Comptes bloques -->
<div class="modal fade" id="blocModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,#D32F2F,#991b1b)">
        <h5 class="modal-title text-white"><i class="bi bi-lock-fill me-2"></i>Comptes verrouilles</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="blocContent">
        <div class="text-center py-3"><span class="spinner-border text-danger"></span></div>
      </div>
    </div>
  </div>
</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>
<script>
const CSRF = '<?php echo Security::escape($csrf); ?>';
const API  = AGAI_BASE + '/api/login-attempts';
let chartCourbe=null, currentPage=1, totalPages=1;
let BLOC_LIST=[];

function apiPost(d){ return $.post(API, Object.assign({csrf_token:CSRF},d), null, 'json'); }
function fmtDT(s){ if(!s) return '-'; const d=new Date(s); return d.toLocaleDateString('fr-FR')+' '+d.toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit',second:'2-digit'}); }
function destroyC(c){ if(c){try{c.destroy();}catch(e){}} return null; }

$('#f_annee').select2({theme:'bootstrap-5',width:'100%',allowClear:true,placeholder:'Toutes'});
$('#f_annee').on('change',refreshAll);
$('#f_mois,#f_date').on('change',refreshAll);
$('#f_ip').on('input', function(){ refreshAll(); });
$('#btnRefresh').on('click',refreshAll);
$('#btnReset').on('click',function(){ $('#f_annee').val('').trigger('change'); $('#f_mois,#f_date,#f_ip,#f_search').val(''); refreshAll(); });
$('#f_search').on('input',function(){ currentPage=1; loadTable(); });

function getFilters(){ return {f_annee:$('#f_annee').val()||'',f_mois:$('#f_mois').val()||'',f_date:$('#f_date').val()||'',ip_filter:$('#f_ip').val()||''}; }

function updateFilterBadge(){
  const fA=$('#f_annee').val(),fM=$('#f_mois').val(),fD=$('#f_date').val(),fI=$('#f_ip').val();
  const moisNoms=['','Janvier','Fevrier','Mars','Avril','Mai','Juin','Juillet','Aout','Septembre','Octobre','Novembre','Decembre'];
  const moisActuel=new Date().getMonth()+1;
  const anneeActuelle=new Date().getFullYear();
  let lbl='Mois courant ('+moisNoms[moisActuel]+' '+anneeActuelle+')';
  if(fD) lbl='Date : '+fD;
  else if(fA&&fM) lbl='Annee '+fA+' - '+moisNoms[parseInt(fM)];
  else if(fA) lbl='Annee '+fA;
  else if(fM) lbl=moisNoms[parseInt(fM)];
  if(fI) lbl+=' | IP : '+fI;
  $('#filterBadge').text(lbl);
}

function deltaBadge(now,yest,goodIfDown){
  if(!yest) return '<span class="compare-badge badge-eq">-</span>';
  const pct=Math.round((now-yest)/yest*100);
  const isGood=goodIfDown?(pct<=0):(pct>=0);
  const icon=pct>0?'<i class="bi bi-arrow-up-short"></i>':pct<0?'<i class="bi bi-arrow-down-short"></i>':'';
  const cls=isGood?'badge-up':'badge-down';
  return '<span class="compare-badge '+cls+'">'+icon+(pct>=0?'+':'')+pct+'%</span>';
}

function threatLevel(total,ips){
  if(total===0) return {lbl:'<span style="color:#1E9C4B;font-size:1.1rem">Aucune</span>',desc:'Aucune tentative detectee'};
  if(total>=50||ips>=10) return {lbl:'<span style="color:#7f1d1d;font-size:1.1rem">CRITIQUE</span>',desc:'Attaque en cours - Intervention immediate'};
  if(total>=20||ips>=5)  return {lbl:'<span style="color:#D32F2F;font-size:1.1rem">ELEVE</span>',desc:'Activite suspecte elevee'};
  if(total>=5)            return {lbl:'<span style="color:#b58a00;font-size:1.1rem">MOYEN</span>',desc:'Quelques tentatives detectees'};
  return {lbl:'<span style="color:#1E9C4B;font-size:1.1rem">FAIBLE</span>',desc:'Activite normale'};
}

function loadDashboard(){
  apiPost(Object.assign({action:'dashboard'},getFilters())).done(function(res){
    if(!res.success) return;
    const k=res.kpi||{};
    // KPI
    $('#k_total').text(k.total_echecs||0);
    $('#k_ips').text(k.ips_uniques||0);
    $('#k_emails').text(k.emails_cibles||0);
    $('#k_heure').text(k.derniere_heure||0);
    $('#k_bloques').text(res.bloques||0);
    BLOC_LIST=res.bloc_list||[];
    // Alerte bloques
    if((res.bloques||0)>0){
      $('#nbBloques').text(res.bloques); $('#alerteBloques').show();
    } else { $('#alerteBloques').hide(); }
    // Deltas
    function kpiDelta(el,now,prev){
      if(!prev){$(el).html('');return;}
      const p=Math.round((now-prev)/prev*100);
      const good=p<=0;
      $(el).html('<i class="bi bi-arrow-'+(p>=0?'up':'down')+'-short"></i>'+(p>=0?'+':'')+p+'% vs hier')
           .css('color',good?'#1E9C4B':'#D32F2F');
    }
    kpiDelta('#d_total',res.echToday||0,res.echYest||0);
    kpiDelta('#d_ips',res.ipToday||0,res.ipYest||0);
    // Comparaison
    $('#c_ech_today').text(res.echToday||0); $('#c_ech_yest').text(res.echYest||0);
    $('#c_ech_badge').html(deltaBadge(res.echToday||0,res.echYest||0,true));
    $('#c_ip_today').text(res.ipToday||0);   $('#c_ip_yest').text(res.ipYest||0);
    $('#c_ip_badge').html(deltaBadge(res.ipToday||0,res.ipYest||0,true));
    // Niveau de menace
    const threat=threatLevel(res.echToday||0,res.ipToday||0);
    $('#threatLevel').html(threat.lbl); $('#threatDesc').text(threat.desc);
    // Courbe
    renderCourbe(res.courbe||[]);
    // Heatmap
    renderHeatmap(res.par_heure||[]);
    // Top IPs
    renderTopIps(res.top_ips||[]);
    // Top emails
    renderTopEmails(res.top_emails||[]);
    // Filtre annees
    if(res.annees&&res.annees.length){
      const cur=$('#f_annee').val();
      let opts='<option value="">Toutes</option>';
      res.annees.forEach(function(y){ opts+='<option value="'+y+'">'+y+'</option>'; });
      $('#f_annee').html(opts);
      if(cur) $('#f_annee').val(cur);
      $('#f_annee').trigger('change.select2');
    }
    $('#lastRefresh').text('Actualise le '+new Date().toLocaleTimeString('fr-FR'));
  });
}

function renderCourbe(data){
  chartCourbe=destroyC(chartCourbe);
  if(!data.length) return;
  chartCourbe=new Chart(document.getElementById('chartCourbe'),{type:'bar',data:{
    labels:data.map(function(r){return new Date(r.jour).toLocaleDateString('fr-FR',{day:'2-digit',month:'short'});}),
    datasets:[
      {label:'Echecs',data:data.map(function(r){return r.nb_echecs||0;}),backgroundColor:'rgba(211,47,47,.75)',borderRadius:4,order:1},
      {label:'IPs uniques',data:data.map(function(r){return r.nb_ips||0;}),type:'line',borderColor:'#F3C300',backgroundColor:'transparent',tension:.3,pointRadius:3,borderWidth:2,order:0},
    ]
  },options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},
    plugins:{legend:{position:'bottom',labels:{font:{size:9},boxWidth:10}}},
    scales:{x:{ticks:{font:{size:8},maxRotation:30}},y:{beginAtZero:true,ticks:{font:{size:9},stepSize:1}}}}});
}

function renderHeatmap(data){
  const byHr={}; for(let h=0;h<24;h++) byHr[h]=0;
  data.forEach(function(r){ byHr[r.heure]=(r.nb||0); });
  const maxVal=Math.max(...Object.values(byHr))||1;
  let html='<div class="hour-grid">';
  for(let h=0;h<24;h++){
    const v=byHr[h];
    const pct=Math.round(v/maxVal*100);
    const col=pct>80?'#7f1d1d':pct>50?'#D32F2F':pct>25?'#F3C300':pct>5?'#fecaca':'#eef1f6';
    const tc=pct>25?'#fff':pct>5?'#991b1b':'#9aa7bd';
    html+='<div class="hour-cell" style="background:'+col+';color:'+tc+'" title="'+h+'h : '+v+' tentative(s)">'+(v>0?v:'')+'</div>';
  }
  html+='</div>';
  $('#heatmap').html(html);
}

function renderTopIps(data){
  if(!data.length){ $('#topIpsBox').html('<div class="empty small"><i class="bi bi-check-circle-fill text-success me-1"></i>Aucune IP suspecte</div>'); return; }
  const maxN=data[0].nb||1;
  let h='';
  data.forEach(function(ip){
    const pct=Math.round((ip.nb||0)/maxN*100);
    const threat=ip.nb>=20?'Critique':ip.nb>=10?'Eleve':'Moyen';
    const thcls=ip.nb>=20?'threat-critical':ip.nb>=10?'threat-high':'threat-med';
    h+='<div class="ip-card" onclick="openIpDetail(\''+esc(ip.ip_address||'')+'\')">'+
      '<div><i class="bi bi-geo-alt-fill" style="color:#D32F2F;font-size:1rem"></i></div>'+
      '<div style="flex:1;min-width:0">'+
        '<div style="font-weight:700;font-family:monospace;font-size:.88rem">'+esc(ip.ip_address||'')+'</div>'+
        '<div style="height:5px;background:#fecaca;border-radius:3px;margin:3px 0;overflow:hidden"><div style="height:100%;width:'+pct+'%;background:#D32F2F;border-radius:3px"></div></div>'+
        '<div style="font-size:.7rem;color:#9aa7bd">'+fmtDT(ip.premier)+' &rarr; '+fmtDT(ip.dernier)+'</div>'+
      '</div>'+
      '<div class="text-end">'+
        '<div class="ip-badge">'+ip.nb+'</div>'+
        '<div><span class="badge mt-1 '+thcls+'" style="font-size:.65rem">'+threat+'</span></div>'+
      '</div>'+
    '</div>';
  });
  $('#topIpsBox').html(h);
}

function renderTopEmails(data){
  if(!data.length){ $('#topEmailsBox').html('<div class="empty small">Aucun compte cible</div>'); return; }
  let h='';
  data.forEach(function(e){
    // Extraire l'email depuis la description brute (format : "Tentative Email: xxx" ou directement l'email)
    let emailCible = e.desc_brut||'-';
    const mEmail = (e.desc_brut||'').match(/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/);
    if(mEmail) emailCible = mEmail[0];
    h+='<div class="email-card">'+
      '<div><div style="font-size:.82rem;font-weight:600"><i class="bi bi-envelope-exclamation me-1 text-danger"></i>'+esc(emailCible)+'</div>'+
        '<div style="font-size:.7rem;color:#9aa7bd;margin-top:2px">Dernier: '+fmtDT(e.dernier)+' &middot; IP: '+esc(e.derniere_ip||'-')+'</div></div>'+
      '<div style="font-weight:800;color:#D32F2F;font-size:1rem">'+e.nb+'x</div>'+
    '</div>';
  });
  $('#topEmailsBox').html(h);
}

// ===== MODALE IP =====
function openIpDetail(ip){
  $('#ipModalTitle').text('Tentatives depuis : '+ip);
  $('#ipModalContent').html('<div class="text-center py-4"><span class="spinner-border text-danger"></span></div>');
  new bootstrap.Modal('#ipModal').show();
  apiPost({action:'ip_detail',ip:ip}).done(function(res){
    if(!res.success||!res.data.length){
      $('#ipModalContent').html('<div class="empty">Aucune donnee</div>'); return;
    }
    let h='<table class="tbl" style="font-size:.82rem"><thead><tr>'
      +'<th>Date / Heure</th><th>Email cible</th><th>IP</th>'
      +'</tr></thead><tbody>';
    res.data.forEach(function(r){
      const emailMatch=(r.description||'').match(/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/);
      const email=emailMatch?emailMatch[0]:(r.description||'-');
      h+='<tr style="background:#fff5f5">'
        +'<td style="white-space:nowrap">'+fmtDT(r.created_at)+'</td>'
        +'<td style="font-weight:600;color:#D32F2F">'+esc(email||'-')+'</td>'
        +'<td style="font-family:monospace;font-size:.8rem">'+esc(r.ip_address||'-')+'</td>'
        +'</tr>';
    });
    h+='</tbody></table>';
    $('#ipModalContent').html(h);
  });
}

// ===== MODALE BLOQUES =====
function openBloques(){
  new bootstrap.Modal('#blocModal').show();
  if(!BLOC_LIST.length){
    $('#blocContent').html('<div class="empty"><i class="bi bi-check-circle-fill text-success me-1"></i>Aucun compte verrouille</div>'); return;
  }
  let h='<div class="small text-muted mb-2 px-2">'+BLOC_LIST.length+' compte(s) verrouille(s). Cliquez sur Debloquer pour restaurer l\'acces.</div>';
  BLOC_LIST.forEach(function(u){
    h+='<div class="bloq-card">'+
      '<div>'+
        '<div style="font-weight:700;font-size:.88rem"><i class="bi bi-person-fill-lock me-1 text-danger"></i>'+esc(u.email||'')+'</div>'+
        '<div style="font-size:.75rem;color:#7b8aa0">'+esc(u.nom||'')+' '+esc(u.prenom||'')+' &middot; '+u.login_attempts+' tentatives &middot; Verr. jusqu\'au '+fmtDT(u.locked_until)+'</div>'+
      '</div>'+
      '<button class="btn btn-sm" style="background:#D32F2F;color:#fff;font-size:.75rem" onclick="debloquer(\''+esc(u.email||'')+'\',this)"><i class="bi bi-unlock me-1"></i>Debloquer</button>'+
    '</div>';
  });
  $('#blocContent').html(h);
}

function debloquer(email,btn){
  $(btn).prop('disabled',true).html('<span class="spinner-border spinner-border-sm"></span>');
  apiPost({action:'debloquer',email:email}).done(function(res){
    if(res.success){
      $(btn).closest('.bloq-card').fadeOut(300,function(){ $(this).remove(); });
      BLOC_LIST=BLOC_LIST.filter(function(u){return u.email!==email;});
      $('#k_bloques').text(BLOC_LIST.length);
      if(!BLOC_LIST.length) $('#alerteBloques').hide();
      Swal.fire({icon:'success',title:'Compte debloque',text:email,timer:2000,showConfirmButton:false});
    } else { Swal.fire({icon:'error',text:res.message}); $(btn).prop('disabled',false).html('<i class="bi bi-unlock me-1"></i>Debloquer'); }
  });
}

// ===== TABLEAU =====
function loadTable(){
  $('#tbody').html('<tr><td colspan="4" class="empty"><span class="spinner-border spinner-border-sm me-1"></span>Chargement...</td></tr>');
  const params=Object.assign({action:'list',page:currentPage,per:30,search:$('#f_search').val()||''},getFilters());
  apiPost(params).done(function(res){
    if(!res.success){ $('#tbody').html('<tr><td colspan="4" class="empty">Erreur</td></tr>'); return; }
    totalPages=res.pages||1;
    $('#totalBadge').text((res.total||0)+' tentatives');
    if(!res.data||!res.data.length){ $('#tbody').html('<tr><td colspan="4" class="empty"><i class="bi bi-check-circle-fill text-success me-1"></i>Aucune tentative</td></tr>'); renderPagi(); return; }
    $('#tbody').html(res.data.map(function(r){
      // Extraire email depuis la description brute
      const emailMatch=(r.description||'').match(/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/);
      const email=emailMatch?emailMatch[0]:(r.description||'-');
      // Simplifier le User Agent
      const ua=r.user_agent||'';
      let uaSimple=ua;
      if(ua.includes('Chrome')) uaSimple='Chrome';
      else if(ua.includes('Firefox')) uaSimple='Firefox';
      else if(ua.includes('Edge')||ua.includes('Edg/')) uaSimple='Edge';
      else if(ua.includes('Safari')) uaSimple='Safari';
      else if(ua.includes('curl')) uaSimple='cURL (script)';
      else if(ua.includes('Python')) uaSimple='Python (bot)';
      else if(ua.includes('bot')||ua.includes('Bot')) uaSimple='Bot';
      const uaIcon=uaSimple==='Chrome'?'<i class="bi bi-browser-chrome me-1"></i>':
        uaSimple==='Firefox'?'<i class="bi bi-browser-firefox me-1"></i>':
        uaSimple==='Edge'?'<i class="bi bi-browser-edge me-1"></i>':
        uaSimple.includes('bot')||uaSimple.includes('Python')||uaSimple.includes('cURL')?'<i class="bi bi-robot me-1 text-danger"></i>':
        '<i class="bi bi-globe me-1"></i>';
      return '<tr style="background:#fff5f5">'
        +'<td style="white-space:nowrap;font-size:.78rem">'+fmtDT(r.created_at)+'</td>'
        +'<td style="font-weight:600;color:#D32F2F;font-size:.82rem">'+esc(email)+'</td>'
        +'<td style="font-family:monospace;font-size:.78rem;cursor:pointer;color:#23408F;text-decoration:underline" onclick="openIpDetail(\''+esc(r.ip_address||'')+'\')">'+esc(r.ip_address||'-')+'</td>'
        +'<td style="font-size:.8rem;color:#555" title="'+esc(ua)+'">'+uaIcon+esc(uaSimple)+'</td>'
        +'</tr>';
    }).join(''));
    renderPagi();
  });
}

function renderPagi(){
  if(totalPages<=1){ $('#pagiBox').html(''); return; }
  let h='';
  const s=Math.max(1,currentPage-2), e=Math.min(totalPages,currentPage+2);
  if(s>1) h+='<button class="pagi-btn" onclick="goPage(1)">1</button>';
  if(s>2) h+='<span class="pagi-btn" style="cursor:default;border:none">…</span>';
  for(let i=s;i<=e;i++) h+='<button class="pagi-btn'+(i===currentPage?' active':'')+ '" onclick="goPage('+i+')">'+i+'</button>';
  if(e<totalPages-1) h+='<span class="pagi-btn" style="cursor:default;border:none">…</span>';
  if(e<totalPages) h+='<button class="pagi-btn" onclick="goPage('+totalPages+')">'+totalPages+'</button>';
  $('#pagiBox').html(h);
}
function goPage(p){ currentPage=p; loadTable(); }

function refreshAll(){ updateFilterBadge(); loadDashboard(); currentPage=1; loadTable(); }
refreshAll();
setInterval(refreshAll, 60000);
</script>