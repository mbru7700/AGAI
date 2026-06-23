<?php
/**
 * Mes audits planifies
 * Colonne Revue  : bouton unique "Saisir" -> redirection vers revue.php
 * Colonne Actions: bouton PDF (modale) si PDF joint
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardPage('audits');

$csrf      = Security::generateCSRF();
$role      = Rbac::role();
$pageTitle = 'Mes audits';
$active    = 'audits';
$titre     = in_array($role, ['admin','chef_inspecteur'], true) ? 'Tous les audits' : 'Mes audits planifies';
require_once INCLUDES_PATH . '/layout_head.php';
?>
<style>
.filter-bar{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:13px 16px;box-shadow:0 1px 2px rgba(16,30,54,.04);margin-bottom:14px;}
.tbl-wrap{background:#fff;border:1px solid #eef1f6;border-radius:14px;overflow:hidden;box-shadow:0 1px 2px rgba(16,30,54,.04);}
table.tbl{width:100%;border-collapse:separate;border-spacing:0;}
table.tbl thead th{background:#f7f9fc;color:#5b6b85;font-size:.73rem;text-transform:uppercase;letter-spacing:.04em;padding:10px 13px;border-bottom:1px solid #eef1f6;white-space:nowrap;}
table.tbl tbody td{padding:11px 13px;border-bottom:1px solid #f1f4f9;vertical-align:middle;font-size:.9rem;}
table.tbl tbody tr:hover{background:#fafcff;}
.empty{padding:34px;text-align:center;color:#9aa7bd;font-size:.9rem;}
.s-badge{display:inline-block;padding:.2rem .55rem;border-radius:20px;font-size:.72rem;font-weight:700;}
.s1{background:#e8f0fe;color:#23408F}.s2{background:#fff3cd;color:#856404}
.s3{background:#d1e7dd;color:#0a5c36}.s4{background:#f8d7da;color:#842029}.s5{background:#f0e6ff;color:#5a189a}
.ra-tag{background:#D32F2F;color:#fff;font-size:.68rem;padding:.1rem .4rem;border-radius:10px;margin-left:5px;font-weight:700;}
.prog-wrap{display:flex;align-items:center;gap:6px;}
.prog-bar{width:60px;height:6px;background:#eef1f6;border-radius:3px;overflow:hidden;flex:0 0 auto;}
.prog-fill{height:100%;border-radius:3px;}
.prog-full{background:#1E9C4B;} .prog-part{background:#F3C300;} .prog-none{background:#eef1f6;}
.prog-lbl{font-size:.78rem;font-weight:700;color:#2C3E50;}
</style>

<div class="page-head">
  <div>
    <h1><i class="bi bi-clipboard-check me-2" style="color:var(--anac-primary)"></i><?php echo Security::escape($titre); ?></h1>
    <div class="sub">Suivi des actes de supervision et revues documentaires.</div>
  </div>
  <?php if (in_array($role, ['admin','chef_inspecteur'], true)): ?>
  <a href="<?php echo SITE_URL; ?>/dashboard" class="btn btn-anac"><i class="bi bi-plus-lg me-1"></i>Nouveau declenchement</a>
  <?php endif; ?>
</div>

<div class="filter-bar d-flex gap-3 flex-wrap">
  <div style="min-width:200px"><select id="fStatut" style="width:100%">
    <option value="">Tous les statuts</option>
    <option value="1">Planifiee</option><option value="2">Reportee</option>
    <option value="3">Effectuee</option><option value="4">Suspendue</option><option value="5">A surveiller</option>
  </select></div>
  <div style="min-width:200px"><select id="fType" style="width:100%">
    <option value="">Tous les types</option>
    <option value="audit">Audit</option>
    <option value="inspection_programmee">Inspection programmee</option>
    <option value="inspection_non_programmee">Inspection non programmee</option>
    <option value="demonstration">Demonstration</option>
    <option value="test">Test</option>
    <option value="investigation">Investigation</option>
  </select></div>
</div>

<div class="tbl-wrap">
  <table class="tbl">
    <thead>
      <tr>
        <th>Numero</th>
        <th>Type / Cadre</th>
        <th>Operateur</th>
        <th>Responsable</th>
        <th>Date prev.</th>
        <th>Statut</th>
        <th>Revue</th>
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
        <h5 class="modal-title"><i class="bi bi-file-pdf me-2 text-danger"></i>Revue documentaire - PDF</h5>
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

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>
<script>
const CSRF    = '<?php echo Security::escape($csrf); ?>';
const API_REV = AGAI_BASE + '/api/revue';
const IS_CI   = ('<?php echo Security::escape($role); ?>' === 'admin' || '<?php echo Security::escape($role); ?>' === 'chef_inspecteur');

function apiPost(data){ data=Object.assign({csrf_token:CSRF},data); return $.post(API_REV,data,null,'json'); }
function fmtDate(s){ if(!s||s==='0000-00-00') return '-'; const p=String(s).split('-'); return p.length===3?(p[2]+'/'+p[1]+'/'+p[0]):s; }

const STATUT={1:'Planifiee',2:'Reportee',3:'Effectuee',4:'Suspendue',5:'A surveiller'};
const SCLS={1:'s1',2:'s2',3:'s3',4:'s4',5:'s5'};
const TYPES={audit:'Audit',inspection_programmee:'Insp. prog.',inspection_non_programmee:'Insp. non prog.',demonstration:'Demo',test:'Test',investigation:'Investigation'};
let ALL=[];

function progHtml(nb, tot){
  if(!tot) return '<span class="text-muted small">-</span>';
  const pct=Math.round((nb/tot)*100);
  const cls=pct===100?'prog-full':(pct>0?'prog-part':'prog-none');
  return '<div class="prog-wrap">'
    +'<span class="prog-lbl">'+nb+'/'+tot+'</span>'
    +'<div class="prog-bar"><div class="prog-fill '+cls+'" style="width:'+pct+'%"></div></div>'
    +'</div>';
}

function rowHtml(a){
  const sc   = SCLS[a.statut]||'s1';
  const type = TYPES[a.type_activite]||esc(a.type_activite||'');
  const raTag= String(a.est_ra)==='1'?'<span class="ra-tag">RA</span>':'';
  const nb   = Number(a.nb_revues||0);
  const tot  = Number(a.nb_equipe||0);
  const hasPdf = a.mon_pdf && String(a.mon_pdf).trim().length>0;
  const pdfInsp = a.pdf_idinspecteur || 0;

  // Colonne Revue : bouton unique
  const revBtn='<a href="'+AGAI_BASE+'/revue?audit='+esc(a.idaudit)+'" class="btn btn-sm btn-anac">'
    +'<i class="bi bi-pencil-square me-1"></i>Revue</a>';

  // Colonne Actions : PDF du RA ou de l'inspecteur si disponible
  const pdfBtn = hasPdf
    ? '<button class="btn btn-sm btn-outline-danger btn-pdf ms-1" data-audit="'+esc(a.idaudit)+'" data-insp="'+esc(pdfInsp)+'" title="Voir le PDF joint"><i class="bi bi-file-pdf me-1"></i>PDF</button>'
    : '<button class="btn btn-sm btn-outline-secondary ms-1" disabled title="Aucun PDF joint"><i class="bi bi-file-pdf"></i></button>';

  return '<tr data-type="'+esc(a.type_activite||'')+'" data-statut="'+esc(a.statut||'')+'">'
    +'<td><b style="color:#23408F">'+esc(a.num_audit||'')+'</b></td>'
    +'<td><div style="font-weight:600">'+type+'</div><div class="text-muted" style="font-size:.78rem">'+esc(a.cadre||'')+'</div></td>'
    +'<td>'+esc(a.operateur||'-')+'</td>'
    +'<td>'+esc(a.ra_nom||'-')+raTag+'</td>'
    +'<td>'+fmtDate(a.date_previsionnelle)+'</td>'
    +'<td><span class="s-badge '+sc+'">'+esc(STATUT[a.statut]||'-')+'</span></td>'
    +'<td>'+progHtml(nb,tot)+' <span class="d-block mt-1">'+revBtn+'</span></td>'
    +'<td style="text-align:right;white-space:nowrap">'+pdfBtn+'</td>'
    +'</tr>';
}

function render(){
  const st=$('#fStatut').val(), ty=$('#fType').val();
  let list=ALL;
  if(st) list=list.filter(a=>String(a.statut)===st);
  if(ty) list=list.filter(a=>String(a.type_activite)===ty);
  if(!list.length){ $('#tbody').html('<tr><td colspan="8" class="empty"><i class="bi bi-inbox me-2"></i>Aucun audit.</td></tr>'); return; }
  $('#tbody').html(list.map(rowHtml).join(''));
}

$(document).on('click','.btn-pdf',function(){
  const idaudit=$(this).data('audit');
  const idinsp=$(this).data('insp')||0;
  const url=AGAI_BASE+'/api/revue?serve=1&idaudit='+idaudit+'&idinsp='+idinsp;
  $('#pdfFrame').attr('src',url);
  $('#pdfDl').attr('href',url+'&dl=1');
  $('#pdfPrint').off('click').on('click',function(){ document.getElementById('pdfFrame').contentWindow.print(); });
  new bootstrap.Modal('#pdfModal').show();
});

$('#fStatut,#fType').select2({theme:'bootstrap-5',width:'100%',allowClear:true,placeholder:'Tous'});
$('#fStatut,#fType').on('change',render);

apiPost({action:'mes_audits'}).done(function(res){
  if(!res.success){ $('#tbody').html('<tr><td colspan="8" class="empty">'+esc(res.message||'Erreur')+'</td></tr>'); return; }
  ALL=res.data||[]; render();
}).fail(function(){ $('#tbody').html('<tr><td colspan="8" class="empty">Echec du chargement.</td></tr>'); });
</script>