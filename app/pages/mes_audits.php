<?php
/**
 * Mes audits - Revues documentaires
 * - Design uniforme (KPI, en-tetes bleu ANAC, filtres Select2)
 * - Bouton Revue : actif si nb_revues < nb_equipe, grisse si complet (y/y)
 * - RA voit toutes les revues, y compris la sienne (impact y/y global)
 * - Chaque inspecteur voit ses propres revues uniquement
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardPage('audits');

$csrf      = Security::generateCSRF();
$role      = Rbac::role();
$uid       = (int) ($_SESSION['user_id'] ?? 0);
$pageTitle = 'Mes audits';
$active    = 'mes-audits';
$isCI      = in_array($role, ['admin','chef_inspecteur','consultant'], true);
$titre     = $isCI ? 'Audits et revues documentaires' : 'Mes audits - Revues documentaires';
require_once INCLUDES_PATH . '/layout_head.php';
?>
<style>
.stat-card{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:14px 16px;display:flex;align-items:center;gap:12px;box-shadow:0 1px 2px rgba(16,30,54,.04);height:100%;}
.stat-ic{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex:0 0 auto;}
.ic-blue{background:rgba(35,64,143,.10);color:#23408F;} .ic-green{background:rgba(30,156,75,.12);color:#1E9C4B;}
.ic-gold{background:rgba(243,195,0,.18);color:#b58a00;} .ic-red{background:rgba(211,47,47,.10);color:#D32F2F;}
.ic-dark{background:rgba(44,62,80,.09);color:#2C3E50;}
.stat-num{font-size:1.5rem;font-weight:700;color:#2C3E50;line-height:1;} .stat-lbl{font-size:.78rem;color:#7b8aa0;}
.filter-bar{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:13px 16px;box-shadow:0 1px 2px rgba(16,30,54,.04);}
table.tbl{width:100%;border-collapse:separate;border-spacing:0;}
table.tbl thead th{background:#23408F;color:#fff;font-size:.71rem;text-transform:uppercase;letter-spacing:.04em;padding:10px 12px;border:none;font-weight:600;white-space:nowrap;}
table.tbl tbody td{padding:10px 12px;border-bottom:1px solid #f1f4f9;vertical-align:middle;font-size:.86rem;}
table.tbl tbody tr:hover{background:#fafcff;}
.empty{padding:36px;text-align:center;color:#9aa7bd;}
/* Statuts */
.s-badge{display:inline-block;padding:.2rem .55rem;border-radius:20px;font-size:.72rem;font-weight:700;}
.s1{background:#e8f0fe;color:#23408F;} .s2{background:#fff3cd;color:#856404;}
.s3{background:#d1e7dd;color:#0a5c36;} .s4{background:#f8d7da;color:#842029;}
.s5{background:#f0e6ff;color:#5a189a;} .s6{background:#e2e3e5;color:#383d41;}
.s7{background:#cfe2ff;color:#084298;}
/* Progression revue */
.prog-wrap{display:flex;align-items:center;gap:6px;white-space:nowrap;}
.prog-bar{width:55px;height:7px;background:#eef1f6;border-radius:4px;overflow:hidden;flex:0 0 auto;}
.prog-fill{height:100%;border-radius:4px;transition:width .3s;}
.prog-full{background:#1E9C4B;} .prog-part{background:#F3C300;} .prog-none{background:#dee2e6;}
.prog-lbl{font-size:.78rem;font-weight:700;}
.prog-lbl.complete{color:#1E9C4B;} .prog-lbl.partial{color:#b58a00;} .prog-lbl.empty2{color:#9aa7bd;}
/* Boutons revue */
.btn-revue-saisir{background:linear-gradient(135deg,#23408F,#1b3576);color:#fff !important;border:none;border-radius:8px;padding:5px 12px;font-size:.79rem;font-weight:600;display:inline-flex;align-items:center;gap:5px;text-decoration:none;transition:all .15s;}
.btn-revue-saisir:hover{background:linear-gradient(135deg,#1b3576,#13276a);transform:translateY(-1px);box-shadow:0 3px 8px rgba(35,64,143,.3);}
.btn-revue-continuer{background:linear-gradient(135deg,#b58a00,#9a7500);color:#fff !important;border:none;border-radius:8px;padding:5px 12px;font-size:.79rem;font-weight:600;display:inline-flex;align-items:center;gap:5px;text-decoration:none;transition:all .15s;}
.btn-revue-continuer:hover{background:linear-gradient(135deg,#9a7500,#7d5f00);transform:translateY(-1px);box-shadow:0 3px 8px rgba(181,138,0,.3);}
.btn-revue-consulter{background:#f5f7fa;color:#5b6b85 !important;border:1px solid #d0d7e3;border-radius:8px;padding:5px 12px;font-size:.79rem;font-weight:600;display:inline-flex;align-items:center;gap:5px;text-decoration:none;}
.btn-revue-consulter:hover{background:#eef1f6;}
.ra-tag{background:#D32F2F;color:#fff;font-size:.68rem;padding:.1rem .4rem;border-radius:10px;margin-left:4px;font-weight:700;}
</style>

<div class="page-head">
  <div>
    <h1><i class="bi bi-clipboard-check me-2" style="color:var(--anac-primary)"></i><?php echo Security::escape($titre); ?></h1>
    <div class="sub">Suivi des actes de supervision et gestion des revues documentaires.</div>
  </div>
</div>

<!-- KPI masquable -->
<div class="d-flex justify-content-end mb-2">
  <button class="btn btn-sm btn-outline-secondary" id="btnToggleStats">
    <i class="bi bi-bar-chart-line-fill me-1"></i><span id="statsLbl">Afficher les statistiques</span>
  </button>
</div>
<div id="statsPanel" class="mb-3" style="display:none">
  <div class="row g-3">
    <div class="col-6 col-md-2"><div class="stat-card"><div class="stat-ic ic-blue"><i class="bi bi-clipboard-data-fill"></i></div><div><div class="stat-num" id="st_total">0</div><div class="stat-lbl">Total audits</div></div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card"><div class="stat-ic ic-blue"><i class="bi bi-calendar-check-fill"></i></div><div><div class="stat-num" id="st_plan" style="color:#23408F">0</div><div class="stat-lbl">Planifies</div></div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card"><div class="stat-ic ic-green"><i class="bi bi-check-circle-fill"></i></div><div><div class="stat-num" id="st_eff" style="color:#1E9C4B">0</div><div class="stat-lbl">Effectues</div></div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card" style="cursor:pointer" id="cardAvecRevue" title="Voir les audits avec revue complete">
      <div class="stat-ic ic-green"><i class="bi bi-file-earmark-check-fill"></i></div>
      <div><div class="stat-num" id="st_rev_ok" style="color:#1E9C4B">0</div><div class="stat-lbl">Revues completes</div></div>
    </div></div>
    <div class="col-6 col-md-2"><div class="stat-card" style="cursor:pointer" id="cardSansRevue" title="Voir les audits sans revue">
      <div class="stat-ic ic-gold"><i class="bi bi-file-earmark-text"></i></div>
      <div><div class="stat-num" id="st_rev_part" style="color:#b58a00">0</div><div class="stat-lbl">Revues en cours</div></div>
    </div></div>
    <div class="col-6 col-md-2"><div class="stat-card" style="cursor:pointer" id="cardNonRevue" title="Voir les audits sans aucune revue">
      <div class="stat-ic ic-red"><i class="bi bi-file-earmark-x"></i></div>
      <div><div class="stat-num" id="st_rev_non" style="color:#D32F2F">0</div><div class="stat-lbl">Revues non saisies</div></div>
    </div></div>
  </div>
</div>

<!-- Filtres -->
<div class="filter-bar mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label small fw-bold mb-1" style="color:#5b6b85;text-transform:uppercase;font-size:.72rem;letter-spacing:.04em">Statut</label>
      <select id="fStatut" style="width:100%">
        <option value="">Tous les statuts</option>
        <option value="1">Planifie</option><option value="2">Reporte</option>
        <option value="3">Effectue</option><option value="4">Suspendu</option>
        <option value="5">A surveiller</option><option value="6">Annule</option><option value="7">Inopine</option>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small fw-bold mb-1" style="color:#5b6b85;text-transform:uppercase;font-size:.72rem;letter-spacing:.04em">Nature</label>
      <select id="fType" style="width:100%">
        <option value="">Toutes les natures</option>
        <option value="audit">Audit</option>
        <option value="inspection_programmee">Inspection programmee</option>
        <option value="inspection_non_programmee">Inspection non programmee</option>
        <option value="demonstration">Demonstration</option>
        <option value="test">Test</option>
        <option value="investigation">Investigation</option>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small fw-bold mb-1" style="color:#5b6b85;text-transform:uppercase;font-size:.72rem;letter-spacing:.04em">Etat revue</label>
      <select id="fRevue" class="form-select form-select-sm">
        <option value="">Tous</option>
        <option value="ok">Completes (y/y)</option>
        <option value="partial">En cours</option>
        <option value="none">Non saisies</option>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small fw-bold mb-1" style="visibility:hidden">-</label>
      <button class="btn btn-sm btn-outline-secondary w-100" id="btnReset"><i class="bi bi-x-lg me-1"></i>Reset filtres</button>
    </div>
  </div>
  <div class="mt-2 small text-muted" id="resCount"></div>
</div>

<!-- Tableau -->
<div style="background:#fff;border:1px solid #eef1f6;border-radius:14px;overflow:hidden;box-shadow:0 1px 2px rgba(16,30,54,.04)">
  <table class="tbl">
    <thead>
      <tr>
        <th>N Audit</th>
        <th>Nature / Cadre</th>
        <th>Operateur</th>
        <th>Responsable (RA)</th>
        <th>Date prev.</th>
        <th>Statut</th>
        <th>Revue documentaire</th>
        <th style="text-align:right">Actions</th>
      </tr>
    </thead>
    <tbody id="tbody">
      <tr><td colspan="8" class="empty"><span class="spinner-border spinner-border-sm me-2"></span>Chargement...</td></tr>
    </tbody>
  </table>
</div>

<!-- Modale PDF -->
<div class="modal fade" id="pdfModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width:92vw">
    <div class="modal-content" style="height:88vh;display:flex;flex-direction:column">
      <div class="modal-header py-2">
        <h5 class="modal-title"><i class="bi bi-file-pdf me-2 text-danger"></i>Revue documentaire</h5>
        <div class="ms-auto d-flex gap-2 me-2">
          <a id="pdfDl" href="#" download class="btn btn-sm btn-outline-primary"><i class="bi bi-download me-1"></i>Telecharger</a>
          <button class="btn btn-sm btn-outline-secondary" id="pdfPrint"><i class="bi bi-printer me-1"></i>Imprimer</button>
        </div>
        <button type="button" class="btn-close ms-2" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0" style="flex:1;overflow:hidden">
        <iframe id="pdfFrame" src="" style="width:100%;height:100%;border:none"></iframe>
      </div>
    </div>
  </div>
</div>

<!-- Modale liste audits par etat de revue -->
<div class="modal fade" id="listModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h5 class="modal-title" id="listModalTitle"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-3" id="listModalBody"></div>
    </div>
  </div>
</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>
<script>
const CSRF    = '<?php echo Security::escape($csrf); ?>';
const API_REV = AGAI_BASE + '/api/revue';
const IS_CI   = <?php echo $isCI ? 'true' : 'false'; ?>;

function apiPost(data){ data=Object.assign({csrf_token:CSRF},data); return $.post(API_REV,data,null,'json'); }
function fmtDate(s){ if(!s||s==='0000-00-00') return '-'; const p=String(s).substring(0,10).split('-'); return p.length===3?(p[2]+'/'+p[1]+'/'+p[0]):s; }

const TYPES={audit:'Audit',inspection_programmee:'Insp. prog.',inspection_non_programmee:'Insp. non prog.',demonstration:'Demo',test:'Test',investigation:'Investigation'};
const STATUT={1:{t:'Planifie',c:'s1'},2:{t:'Reporte',c:'s2'},3:{t:'Effectue',c:'s3'},4:{t:'Suspendu',c:'s4'},5:{t:'A surveiller',c:'s5'},6:{t:'Annule',c:'s6'},7:{t:'Inopine',c:'s7'}};

let ALL=[];

/* ===== KPI ===== */
function calcStats(list){
  const s={total:list.length,plan:0,eff:0,rev_ok:0,rev_part:0,rev_non:0};
  list.forEach(function(a){
    if(Number(a.statut)===1) s.plan++;
    if(Number(a.statut)===3) s.eff++;
    const nb=Number(a.nb_revues||0), tot=Number(a.nb_equipe||0);
    if(tot>0&&nb>=tot) s.rev_ok++;
    else if(nb>0) s.rev_part++;
    else s.rev_non++;
  });
  return s;
}
function updateStats(list){
  const s=calcStats(list);
  $('#st_total').text(s.total); $('#st_plan').text(s.plan); $('#st_eff').text(s.eff);
  $('#st_rev_ok').text(s.rev_ok); $('#st_rev_part').text(s.rev_part); $('#st_rev_non').text(s.rev_non);
}

/* ===== TOGGLE STATS ===== */
function setStatsVisible(show){
  $('#statsPanel').toggle(show);
  $('#statsLbl').text(show?'Masquer les statistiques':'Afficher les statistiques');
  try{localStorage.setItem('agai_stats_mesaudits',show?'1':'0');}catch(e){}
}
$('#btnToggleStats').on('click',function(){ setStatsVisible($('#statsPanel').is(':hidden')); });

/* ===== RENDU LIGNE ===== */
function progHtml(nb, tot){
  if(!tot) return '<span class="text-muted small">-</span>';
  const pct=Math.round((nb/tot)*100);
  const complete=pct>=100;
  const partial=pct>0&&pct<100;
  const cls=complete?'prog-full':partial?'prog-part':'prog-none';
  const lblCls=complete?'complete':partial?'partial':'empty2';
  return '<div class="prog-wrap">'
    +'<span class="prog-lbl '+lblCls+'">'+nb+'/'+tot+'</span>'
    +'<div class="prog-bar"><div class="prog-fill '+cls+'" style="width:'+pct+'%"></div></div>'
    +(complete?'<i class="bi bi-check-circle-fill text-success" style="font-size:.85rem"></i>':'')
    +'</div>';
}

function revueBtn(a){
  const nb=Number(a.nb_revues||0), tot=Number(a.nb_equipe||0);
  const complet=(tot>0&&nb>=tot);
  const url=AGAI_BASE+'/revue?audit='+esc(a.idaudit);
  if(complet){
    return '<a href="'+url+'" class="btn-revue-consulter">'
      +'<i class="bi bi-eye" style="font-size:.8rem"></i>Consulter'
      +'</a>'
      +'<div style="font-size:.67rem;color:#1E9C4B;font-weight:700;margin-top:3px"><i class="bi bi-check-circle me-1"></i>Revue complete</div>';
  }
  if(nb>0){
    return '<a href="'+url+'" class="btn-revue-continuer">'
      +'<i class="bi bi-pencil-fill" style="font-size:.75rem"></i>Continuer'
      +'</a>'
      +'<div style="font-size:.67rem;color:#b58a00;font-weight:700;margin-top:3px"><i class="bi bi-clock me-1"></i>En cours de saisie</div>';
  }
  return '<a href="'+url+'" class="btn-revue-saisir">'
    +'<i class="bi bi-pencil-square" style="font-size:.78rem"></i>Saisir'
    +'</a>'
    +'<div style="font-size:.67rem;color:#9aa7bd;margin-top:3px"><i class="bi bi-dash-circle me-1"></i>Non saisie</div>';
}

function rowHtml(a){
  const st=STATUT[a.statut]||{t:a.statut||'-',c:'s1'};
  const type=TYPES[a.type_activite]||esc(a.type_activite||'');
  const raTag=String(a.est_ra)==='1'?'<span class="ra-tag">RA</span>':'';
  const nb=Number(a.nb_revues||0), tot=Number(a.nb_equipe||0);
  const hasPdf=a.mon_pdf&&String(a.mon_pdf).trim().length>0;
  const pdfInsp=a.pdf_idinspecteur||0;

  const pdfBtn=hasPdf
    ?'<button class="btn btn-sm btn-outline-danger btn-pdf" data-audit="'+esc(a.idaudit)+'" data-insp="'+esc(pdfInsp)+'" title="Voir le PDF joint"><i class="bi bi-file-pdf me-1"></i>PDF</button>'
    :'<button class="btn btn-sm btn-outline-secondary" disabled title="Aucun PDF"><i class="bi bi-file-pdf"></i></button>';

  return '<tr>'
    +'<td><b style="color:#23408F;font-size:.88rem">'+esc(a.num_audit||'')+'</b></td>'
    +'<td><div style="font-weight:600;font-size:.86rem">'+type+'</div><div class="text-muted" style="font-size:.76rem">'+esc(a.cadre||'')+'</div></td>'
    +'<td style="font-size:.84rem">'+esc(a.operateur||'-')+'</td>'
    +'<td style="font-size:.84rem;color:#D32F2F;font-weight:600">'+esc(a.ra_nom||'-')+raTag+'</td>'
    +'<td style="font-size:.84rem">'+fmtDate(a.date_previsionnelle)+'</td>'
    +'<td><span class="s-badge '+st.c+'">'+esc(st.t)+'</span></td>'
    +'<td>'
    +'<div class="mb-1">'+progHtml(nb,tot)+'</div>'
    +'<div>'+revueBtn(a)+'</div>'
    +'</td>'
    +'<td style="text-align:right;white-space:nowrap">'+pdfBtn+'</td>'
    +'</tr>';
}

/* ===== FILTRE + RENDU ===== */
function getFiltered(){
  const st=$('#fStatut').val(), ty=$('#fType').val(), rev=$('#fRevue').val();
  return ALL.filter(function(a){
    if(st && String(a.statut)!==st) return false;
    if(ty && a.type_activite!==ty) return false;
    if(rev){
      const nb=Number(a.nb_revues||0), tot=Number(a.nb_equipe||0);
      if(rev==='ok'    && !(tot>0&&nb>=tot)) return false;
      if(rev==='partial' && !(nb>0&&nb<tot)) return false;
      if(rev==='none'  && nb>0)             return false;
    }
    return true;
  });
}
function render(){
  const list=getFiltered();
  updateStats(list);
  if(!list.length){
    $('#tbody').html('<tr><td colspan="8" class="empty"><i class="bi bi-inbox me-2"></i>Aucun audit.</td></tr>');
    $('#resCount').text(''); return;
  }
  $('#tbody').html(list.map(rowHtml).join(''));
  $('#resCount').html('<i class="bi bi-clipboard-check me-1"></i>'+list.length+' audit(s) affiches sur '+ALL.length);
}

/* ===== MODALES LISTES PAR ETAT REVUE ===== */
function showListModal(type){
  const TYPES_MAP={
    ok:{title:'Revues completes (y/y)',icon:'bi-file-earmark-check-fill text-success',filter:function(a){const nb=Number(a.nb_revues||0),tot=Number(a.nb_equipe||0);return tot>0&&nb>=tot;}},
    partial:{title:'Revues en cours',icon:'bi-file-earmark-text text-warning',filter:function(a){const nb=Number(a.nb_revues||0),tot=Number(a.nb_equipe||0);return nb>0&&nb<tot;}},
    none:{title:'Revues non saisies',icon:'bi-file-earmark-x text-danger',filter:function(a){return Number(a.nb_revues||0)===0;}}
  };
  const cfg=TYPES_MAP[type]; if(!cfg) return;
  const list=ALL.filter(cfg.filter);
  $('#listModalTitle').html('<i class="bi '+cfg.icon+' me-2"></i>'+cfg.title+' ('+list.length+')');
  if(!list.length){
    $('#listModalBody').html('<div class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>Aucun audit dans cette categorie.</div>');
  } else {
    let h='<div class="table-responsive"><table class="table table-sm table-hover align-middle">'
      +'<thead style="background:#23408F;color:#fff"><tr>'
      +'<th style="padding:8px 12px;font-size:.72rem;text-transform:uppercase">N Audit</th>'
      +'<th style="padding:8px 12px;font-size:.72rem;text-transform:uppercase">Nature</th>'
      +'<th style="padding:8px 12px;font-size:.72rem;text-transform:uppercase">Operateur</th>'
      +'<th style="padding:8px 12px;font-size:.72rem;text-transform:uppercase">Progression</th>'
      +'<th style="padding:8px 12px;font-size:.72rem;text-transform:uppercase">Statut</th>'
      +'<th style="padding:8px 12px;font-size:.72rem;text-transform:uppercase;text-align:right">Action</th>'
      +'</tr></thead><tbody>';
    list.forEach(function(a){
      const st=STATUT[a.statut]||{t:a.statut||'-',c:'s1'};
      const nb=Number(a.nb_revues||0),tot=Number(a.nb_equipe||0);
      h+='<tr>'
        +'<td style="font-family:monospace;font-weight:700;color:#23408F;font-size:.85rem">'+esc(a.num_audit||'')+'</td>'
        +'<td style="font-size:.82rem">'+esc(TYPES[a.type_activite]||a.type_activite||'')+'</td>'
        +'<td style="font-size:.82rem">'+esc(a.operateur||'-')+'</td>'
        +'<td>'+progHtml(nb,tot)+'</td>'
        +'<td><span class="s-badge '+st.c+'">'+esc(st.t)+'</span></td>'
        +'<td style="text-align:right">'
        +'<a href="'+AGAI_BASE+'/revue?audit='+esc(a.idaudit)+'" class="btn btn-xs btn-anac" style="padding:3px 8px;font-size:.78rem">'
        +(nb>0&&tot>0&&nb>=tot?'<i class="bi bi-eye me-1"></i>Consulter':'<i class="bi bi-pencil-square me-1"></i>Saisir')
        +'</a></td></tr>';
    });
    h+='</tbody></table></div>';
    $('#listModalBody').html(h);
  }
  new bootstrap.Modal('#listModal').show();
}
$('#cardAvecRevue').on('click',function(){ showListModal('ok'); });
$('#cardSansRevue').on('click',function(){ showListModal('partial'); });
$('#cardNonRevue').on('click', function(){ showListModal('none'); });

/* ===== PDF ===== */
$(document).on('click','.btn-pdf',function(){
  const idaudit=$(this).data('audit'), idinsp=$(this).data('insp')||0;
  const url=AGAI_BASE+'/api/revue?serve=1&idaudit='+idaudit+'&idinsp='+idinsp;
  $('#pdfFrame').attr('src',url);
  $('#pdfDl').attr('href',url+'&dl=1');
  $('#pdfPrint').off('click').on('click',function(){ document.getElementById('pdfFrame').contentWindow.print(); });
  new bootstrap.Modal('#pdfModal').show();
});
$('#pdfModal').on('hidden.bs.modal',function(){ $('#pdfFrame').attr('src',''); });

/* ===== SELECTS ===== */
$('#fStatut,#fType').select2({theme:'bootstrap-5',width:'100%',allowClear:true,placeholder:'Tous'});
$('#fStatut,#fType,#fRevue').on('change',render);
$('#btnReset').on('click',function(){
  $('#fStatut,#fType').val('').trigger('change');
  $('#fRevue').val(''); render();
});

/* ===== DEMARRAGE ===== */
apiPost({action:'mes_audits'}).done(function(res){
  if(!res.success){ $('#tbody').html('<tr><td colspan="8" class="empty">'+esc(res.message||'Erreur')+'</td></tr>'); return; }
  ALL=res.data||[]; render();
}).fail(function(){ $('#tbody').html('<tr><td colspan="8" class="empty">Echec.</td></tr>'); });

(function(){
  let v='0'; try{v=localStorage.getItem('agai_stats_mesaudits')||'0';}catch(e){}
  if(v==='1') setStatsVisible(true);
})();
</script>