<?php
/**
 * Module : Gestion des utilisateurs (page)
 * Accas : administrateurs uniquement.
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }

Rbac::guardPage('users');
$csrf      = Security::generateCSRF();
$pageTitle = 'Gestion des utilisateurs';
$active    = 'users';
$pageIcon  = 'bi-people';
$roles     = Rbac::roles();

require_once INCLUDES_PATH . '/layout_head.php';
?>

<div class="page-head">
  <div>
    <h1><i class="bi bi-people me-2" style="color:var(--anac-primary)"></i>Gestion des utilisateurs</h1>
    <div class="sub">Comptes, roles, organismes d'appartenance et double authentification.</div>
  </div>
  <button class="btn btn-anac" id="btnNew"><i class="bi bi-plus-lg me-2"></i>Nouvel utilisateur</button>
</div>

<style>
  .stat-card{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:14px 16px;display:flex;align-items:center;gap:12px;box-shadow:0 1px 2px rgba(16,30,54,.04);height:100%;}
  .stat-ic{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex:0 0 auto;}
  .stat-num{font-size:1.5rem;font-weight:700;line-height:1;color:#2C3E50;}
  .stat-lbl{font-size:.78rem;color:#6b7a90;margin-top:3px;}
  .ic-blue{background:rgba(35,64,143,.10);color:#23408F;}
  .ic-green{background:rgba(30,156,75,.12);color:#1E9C4B;}
  .ic-gold{background:rgba(243,195,0,.18);color:#b58a00;}
  .ic-red{background:rgba(211,47,47,.10);color:#D32F2F;}
  .pwd-rule{color:#8a93a3;transition:color .15s;}
  .pwd-rule.ok{color:#1E9C4B;font-weight:600;}
  /* Modale utilisateur : presentation HD, defilement du corps, boutons toujours visibles */
  @media (min-width:992px){ #userModal .modal-lg{ max-width:880px; } }
  #userModal .modal-body{ padding:1.25rem 1.5rem; }
  #userModal .modal-body::-webkit-scrollbar{ width:8px; }
  #userModal .modal-body::-webkit-scrollbar-thumb{ background:#cfd6e2; border-radius:8px; }
</style>

<!-- Bouton de bascule : l'utilisateur choisit d'afficher ou masquer les stats -->
<div class="d-flex justify-content-end mb-2">
  <button class="btn btn-sm btn-outline-secondary" id="btnToggleStats" type="button">
    <i class="bi bi-bar-chart-line-fill me-1"></i><span id="statsToggleLabel">Afficher les statistiques</span>
  </button>
</div>

<!-- Panneau de statistiques basiques (masque par defaut) -->
<div id="statsPanel" class="row g-3 mb-3" style="display:none;">
  <div class="col-6 col-md-4 col-xl-2"><div class="stat-card"><div class="stat-ic ic-blue"><i class="bi bi-people-fill"></i></div><div><div class="stat-num" id="st_total">0</div><div class="stat-lbl">Comptes au total</div></div></div></div>
  <div class="col-6 col-md-4 col-xl-2"><div class="stat-card"><div class="stat-ic ic-green"><i class="bi bi-check-circle-fill"></i></div><div><div class="stat-num" id="st_actifs">0</div><div class="stat-lbl">Comptes actifs</div></div></div></div>
  <div class="col-6 col-md-4 col-xl-2"><div class="stat-card"><div class="stat-ic ic-red"><i class="bi bi-slash-circle-fill"></i></div><div><div class="stat-num" id="st_inactifs">0</div><div class="stat-lbl">Comptes inactifs</div></div></div></div>
  <div class="col-6 col-md-4 col-xl-2"><div class="stat-card"><div class="stat-ic ic-blue"><i class="bi bi-building-fill-check"></i></div><div><div class="stat-num" id="st_internes">0</div><div class="stat-lbl">Internes ANAC</div></div></div></div>
  <div class="col-6 col-md-4 col-xl-2"><div class="stat-card"><div class="stat-ic ic-gold"><i class="bi bi-airplane-fill"></i></div><div><div class="stat-num" id="st_operateurs">0</div><div class="stat-lbl">Operateurs</div></div></div></div>
  <div class="col-6 col-md-4 col-xl-2"><div class="stat-card"><div class="stat-ic ic-green"><i class="bi bi-shield-lock-fill"></i></div><div><div class="stat-num" id="st_2fa">0</div><div class="stat-lbl">2FA active</div></div></div></div>
</div>

<div class="card-anac p-3 p-md-4">
  <div class="table-responsive">
    <table id="usersTable" class="table table-hover align-middle" style="width:100%">
      <thead>
        <tr>
          <th>Matricule</th><th>Nom &amp; prenom</th><th>Email</th><th>Role</th>
          <th>Organisme</th><th>2FA</th><th>Statut</th><th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<!-- ===== MODALE UTILISATEUR ===== -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <form id="userForm" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="userModalTitle">Nouvel utilisateur</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
        <div class="modal-body">
          <input type="hidden" name="action" id="u_action" value="create">
          <input type="hidden" name="iduser" id="u_iduser" value="">
          <input type="hidden" name="idpersonnel" id="u_idpersonnel" value="">

          <!-- 1) ROLE d'abord -->
          <div class="mb-3">
            <label class="form-label">Role *</label>
            <select class="form-select" name="role" id="u_role" required>
              <option value="">-- Choisir un role --</option>
              <?php foreach ($roles as $val => $lib): ?>
                <option value="<?php echo Security::escape($val); ?>"><?php echo Security::escape($lib); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- 2a) Choix d'un agent ANAC (roles != operateur) -->
          <div class="mb-3" id="blockPersonnel" style="display:none">
            <label class="form-label">Agent ANAC *</label>
            <select class="form-select" id="u_personnel" style="width:100%"></select>
            <div class="form-text">Selectionnez l'agent dans le personnel ANAC. L'organisme sera l'ANAC.</div>
          </div>

          <!-- 3) Champs identite -->
          <div id="identityFields" style="display:none">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Prenom *</label>
                <input type="text" class="form-control" name="prenom" id="u_prenom" maxlength="100" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Nom *</label>
                <input type="text" class="form-control" name="nom" id="u_nom" maxlength="100" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Matricule</label>
                <input type="text" class="form-control" name="matricule" id="u_matricule" readonly>
                <div class="form-text" id="matHelp"></div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Email professionnel *</label>
                <input type="email" class="form-control" name="email" id="u_email" maxlength="100" required>
                <div class="field-error" id="err_email">Cet email est deja utilise.</div>
              </div>
              <div class="col-md-12" id="grpOrga">
                <label class="form-label">Organisme d'appartenance</label>
                <div class="input-group">
                  <select class="form-select" name="idorga" id="u_idorga" style="width:100%"></select>
                  <button class="btn btn-add-org" type="button" id="btnAddOrg"
                          data-bs-toggle="tooltip" data-bs-placement="top"
                          title="Ajouter un organisme absent de la liste">
                    <i class="bi bi-plus-lg"></i>
                  </button>
                </div>
              </div>
            </div>

            <hr class="my-3">
            <div class="row g-3">
              <div class="col-md-4">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="u_2fa" name="is_2fa_enabled" checked>
                  <label class="form-check-label" for="u_2fa">Double authentification (2FA)</label>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="u_notif" name="email_notifications" checked>
                  <label class="form-check-label" for="u_notif">Notifications par email</label>
                </div>
              </div>
              <div class="col-md-4" id="activeWrap" style="display:none">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="u_active" name="is_active" checked>
                  <label class="form-check-label" for="u_active">Compte actif</label>
                </div>
              </div>
            </div>

            <div id="pwdWrap" class="mt-3 p-3" style="background:#f4f7fb;border-radius:10px;">
              <label class="form-label d-block mb-2"><i class="bi bi-shield-lock me-1"></i>Mot de passe</label>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="pwd_mode" id="pwd_auto" value="auto" checked>
                <label class="form-check-label" for="pwd_auto">Generer un mot de passe fort <strong>automatiquement</strong></label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="pwd_mode" id="pwd_manual" value="manual">
                <label class="form-check-label" for="pwd_manual">Definir <strong>moi-meme</strong> un mot de passe (plus facile a retenir)</label>
              </div>
              <div id="manualPwdBox" class="mt-2" style="display:none;">
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-key"></i></span>
                  <input type="password" class="form-control" id="u_password" placeholder="Saisir le mot de passe" autocomplete="new-password" maxlength="72">
                  <button class="btn btn-outline-secondary" type="button" id="btnTogglePwd" tabindex="-1" title="Afficher / masquer"><i class="bi bi-eye"></i></button>
                </div>
                <div class="mt-2 small">
                  <div class="pwd-rule" data-rule="len"><i class="bi bi-circle me-1"></i>Au moins 10 caracteres</div>
                  <div class="pwd-rule" data-rule="upper"><i class="bi bi-circle me-1"></i>Une lettre majuscule (A a Z)</div>
                  <div class="pwd-rule" data-rule="lower"><i class="bi bi-circle me-1"></i>Une lettre minuscule (a a z)</div>
                  <div class="pwd-rule" data-rule="digit"><i class="bi bi-circle me-1"></i>Un chiffre (0 a 9)</div>
                  <div class="pwd-rule" data-rule="special"><i class="bi bi-circle me-1"></i>Un caractere special (! @ # ? ...)</div>
                </div>
                <div class="form-text mt-1">Astuce : une phrase courte avec chiffre et symbole est facile a retenir et tres solide, par exemple Avion2025!Bleu</div>
              </div>
            </div>

            <div id="sendWrap" class="mt-3 p-3" style="background:#f4f7fb;border-radius:10px;">
              <label class="form-label d-block mb-2"><i class="bi bi-send me-1"></i>Communication des identifiants</label>
              <div class="form-text mb-2">Comment transmettre les identifiants a l'utilisateur ?</div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="send_email" id="send_yes" value="1" checked>
                <label class="form-check-label" for="send_yes">Envoyer les identifiants <strong>par email</strong></label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="send_email" id="send_no" value="0">
                <label class="form-check-label" for="send_no">Ne pas envoyer - <strong>afficher le mot de passe</strong> (copiable)</label>
              </div>
            </div>
            <!-- HABILITATIONS : modules accessibles avec sous-menus -->
            <div id="blockHabilitations" style="display:none;margin-top:16px">
              <hr class="my-2">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <div style="font-size:.73rem;text-transform:uppercase;letter-spacing:.05em;font-weight:700;color:#23408F"><i class="bi bi-key me-1"></i>Habilitations - Modules et sous-menus</div>
                <div class="d-flex gap-2">
                  <button type="button" class="btn btn-xs btn-outline-primary btn-all-mods" data-val="1" style="font-size:.75rem;padding:2px 8px">Tout cocher</button>
                  <button type="button" class="btn btn-xs btn-outline-secondary btn-all-mods" data-val="0" style="font-size:.75rem;padding:2px 8px">Tout decocher</button>
                </div>
              </div>
              <div class="small text-muted mb-2">Le Tableau de bord est toujours accorde. Cochez les modules et sous-menus souhaites.</div>
              <div id="modulesTree"></div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn btn-anac" id="userSubmit"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
        </div>
      </form>
  </div>
</div>

<!-- ===== MODALE AJOUT RAPIDE ORGANISME ===== -->
<div class="modal fade" id="orgModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Nouvel organisme</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="orgForm">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">Nom de l'organisme *</label>
              <input type="text" class="form-control" name="nomorga" id="o_nom" maxlength="255" required>
              <div class="field-error" id="err_org">Cet organisme existe deja en base.</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Sigle</label>
              <input type="text" class="form-control" name="trigrorganisme" id="o_sigle" maxlength="70">
            </div>
            <div class="col-md-6">
              <label class="form-label">Ville</label>
              <input type="text" class="form-control" name="ville_org" id="o_ville" maxlength="150">
            </div>
            <div class="col-md-6">
              <label class="form-label">Telephone</label>
              <input type="text" class="form-control" name="telorga" id="o_tel" maxlength="50">
            </div>
            <div class="col-md-12">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" name="emailorga" id="o_email" maxlength="100">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn btn-anac" id="orgSubmit"><i class="bi bi-check-lg me-1"></i>Ajouter</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>

<script>
const CSRF = '<?php echo Security::escape($csrf); ?>';
const API_USERS = AGAI_BASE + '/api/users';
const API_ORG   = AGAI_BASE + '/api/organisme';
const API_PERS  = AGAI_BASE + '/api/personnel';
const ROLE_LABELS = <?php echo json_encode($roles, JSON_UNESCAPED_UNICODE); ?>;
const ANAC_ORGA = 1282;

// Arbre des modules (depuis Rbac::MODULES, structure hierarchique)
const MODULES_TREE = <?php
    $tree = [];
    foreach (Rbac::MODULES as $key => $m) {
        $node = ['key'=>$key,'label'=>$m['label'],'icon'=>$m['icon'],'desc'=>$m['desc'],'is_group'=>!empty($m['is_group']),'children'=>[]];
        if (!empty($m['is_group'])) {
            foreach ($m['children'] as $ck=>$cm) {
                $node['children'][] = ['key'=>$ck,'label'=>$cm['label'],'icon'=>$cm['icon'],'desc'=>$cm['desc']];
            }
        }
        $tree[] = $node;
    }
    echo json_encode($tree, JSON_UNESCAPED_UNICODE);
?>;

// Modules par role : PLUS DE SUGGESTION AUTOMATIQUE
// Les modules sont vides par defaut - l'admin doit tout cocher explicitement

function loadAndShowModules(iduser){
  $('#blockHabilitations').show();
  if(iduser){
    // Modification : charger les modules reellement attribues en BDD
    apiPost(API_USERS,{action:'get_modules',iduser:iduser}).then(function(r){
      const mods = (r.success && Array.isArray(r.modules)) ? r.modules : [];
      renderModulesTree(mods);
    }).fail(function(){ renderModulesTree([]); });
  } else {
    // Creation : rien de coche sauf dashboard (oblige)
    renderModulesTree(['dashboard']);
  }
}

// Suppression de suggestModulesByRole : les modules ne sont JAMAIS suggeres automatiquement
function renderModulesTree(checkedModules){
  if(!Array.isArray(checkedModules)) checkedModules=[];
  let html='';
  MODULES_TREE.forEach(function(node){
    if(node.is_group){
      // Compter combien d'enfants sont coches
      const totalChildren=node.children.length;
      const nbChecked=node.children.filter(function(c){return checkedModules.includes(c.key);}).length;
      const allChk=nbChecked===totalChildren && totalChildren>0;
      const someChk=nbChecked>0&&!allChk;
      // Groupe coché si au moins 1 enfant est coché
      const grpChecked=nbChecked>0;
      html+='<div class="hab-group mb-2" style="border:1px solid #dde4f0;border-radius:12px;overflow:hidden">';
      html+='<div class="hab-group-head d-flex align-items-center gap-2 p-2 ps-3" style="background:#f0f4fb;cursor:pointer" onclick="toggleGroup(this)">'
        +'<input type="checkbox" class="form-check-input grp-chk mt-0 flex-shrink-0" data-group="'+esc(node.key)+'" '+(grpChecked?'checked':'')+' data-indeterminate="'+(someChk?'true':'false')+'">'
        +'<i class="bi '+esc(node.icon)+'" style="color:#23408F;width:18px"></i>'
        +'<div style="flex:1"><span style="font-weight:700;font-size:.9rem;color:#23408F">'+esc(node.label)+'</span>'
        +' <span class="badge" style="background:#23408F;color:#fff;font-size:.7rem">'+nbChecked+'/'+totalChildren+'</span>'
        +'<div style="font-size:.74rem;color:#7b8aa0">'+esc(node.desc)+'</div></div>'
        +'<i class="bi bi-chevron-down caret-icon" style="font-size:.85rem;color:#7b8aa0;transition:transform .2s"></i>'
        +'</div>';
      // Corps du groupe (sous-items)
      html+='<div class="hab-group-body" style="display:none;padding:8px 10px;background:#fafcff">';
      html+='<div class="row g-2">';
      node.children.forEach(function(child){
        const isCk=checkedModules.includes(child.key);
        html+='<div class="col-12 col-md-6">'
          +'<label class="hab-item d-flex align-items-start gap-2 p-2" style="border:1px solid '+(isCk?'#23408F':'#e8edf5')+';border-radius:8px;background:'+(isCk?'rgba(35,64,143,.05)':'#fff')+';cursor:pointer">'
          +'<input type="checkbox" class="form-check-input leaf-chk mt-0 flex-shrink-0" value="'+esc(child.key)+'" data-group="'+esc(node.key)+'" '+(isCk?'checked':'')+'>'
          +'<div><div style="font-size:.85rem;font-weight:600;color:#2C3E50"><i class="bi '+esc(child.icon)+' me-1"></i>'+esc(child.label)+'</div>'
          +'<div style="font-size:.74rem;color:#7b8aa0">'+esc(child.desc)+'</div></div>'
          +'</label></div>';
      });
      html+='</div></div>';
      html+='</div>';
    } else {
      // Module simple (pas de groupe)
      const isCk=checkedModules.includes(node.key);
      const isDash=(node.key==='dashboard');
      html+='<div class="hab-group mb-2" style="border:1px solid '+(isCk?'#23408F':'#dde4f0')+';border-radius:12px;background:'+(isCk?'rgba(35,64,143,.05)':'#fff')+';padding:10px 14px">';
      html+='<label class="d-flex align-items-center gap-2 m-0" style="cursor:'+(isDash?'default':'pointer')+'">'
        +'<input type="checkbox" class="form-check-input leaf-chk mt-0 flex-shrink-0" value="'+esc(node.key)+'" '+(isCk?'checked':'')+(isDash?' disabled':'')+'>'
        +'<i class="bi '+esc(node.icon)+'" style="color:#23408F;width:18px"></i>'
        +'<div><div style="font-size:.88rem;font-weight:700;color:#2C3E50">'+esc(node.label)+'</div>'
        +'<div style="font-size:.74rem;color:#7b8aa0">'+esc(node.desc)+(isDash?' (toujours accorde)':'')+'</div></div>'
        +'</label>';
      html+='</div>';
    }
  });
  $('#modulesTree').html(html);
  bindHabEvents();
}

function toggleGroup(head){
  const body=$(head).next('.hab-group-body');
  const caret=$(head).find('.caret-icon');
  if(body.is(':visible')){ body.slideUp(180); caret.css('transform','rotate(0deg)'); }
  else { body.slideDown(180); caret.css('transform','rotate(180deg)'); }
}

function updateGroupCheckbox(groupKey){
  const grpChk=$('.grp-chk[data-group="'+groupKey+'"]');
  const leaves=$('.leaf-chk[data-group="'+groupKey+'"]');
  const total=leaves.length;
  const checked=leaves.filter(':checked').length;
  const el=grpChk[0];
  if(el){
    // Groupe coche si au moins 1 enfant est coche
    // indeterminate (tiret) si certains cochés mais pas tous (pour info visuelle)
    // Mais la case de groupe RESTE cochee si au moins 1 est cochee
    if(checked===0){
      el.checked=false; el.indeterminate=false;
    } else if(checked===total){
      el.checked=true; el.indeterminate=false;
    } else {
      // Partiellement coche : coche + indeterminate pour montrer l'etat partiel
      el.checked=true; el.indeterminate=true;
    }
  }
  // Badge nombre/total
  const badge=grpChk.closest('.hab-group-head').find('.badge');
  badge.text(checked+'/'+total);
}

function bindHabEvents(){
  // Clic sur une case enfant -> met a jour le groupe + bordure
  $(document).off('change.hab').on('change.hab','.leaf-chk',function(){
    const lbl=$(this).closest('label.hab-item, label');
    if($(this).is(':checked')){ lbl.css({'border-color':'#23408F','background':'rgba(35,64,143,.05)'}); }
    else { lbl.css({'border-color':'#e8edf5','background':'#fff'}); }
    const gk=$(this).data('group');
    if(gk) updateGroupCheckbox(gk);
  });
  // Clic sur la case de groupe -> coche/decoche tous les enfants
  $(document).off('change.habgrp').on('change.habgrp','.grp-chk',function(){
    const gk=$(this).data('group');
    const el=this;
    // Si indeterminate au moment du clic, forcer cocher tout
    const shouldCheck=el.indeterminate?true:el.checked;
    el.indeterminate=false; el.checked=shouldCheck;
    $('.leaf-chk[data-group="'+gk+'"]').each(function(){
      $(this).prop('checked',shouldCheck).trigger('change.hab');
    });
  });
  // Initialiser les indeterminate d'apres l'etat initial
  $('.grp-chk').each(function(){
    if($(this).data('indeterminate')==='true'||$(this).attr('data-indeterminate')==='true'){
      this.indeterminate=true;
    }
  });
  MODULES_TREE.forEach(function(node){ if(node.is_group) updateGroupCheckbox(node.key); });
  // Ouvrir le premier groupe par defaut
  const first=$('.hab-group-head').first();
  if(first.length) toggleGroup(first[0]);
}

// Tout cocher / Tout decocher
$(document).on('click','.btn-all-mods',function(){
  const val=$(this).data('val');
  $('.leaf-chk:not(:disabled)').prop('checked',!!val).each(function(){
    const lbl=$(this).closest('label.hab-item,label');
    if(val){ lbl.css({'border-color':'#23408F','background':'rgba(35,64,143,.05)'}); }
    else { lbl.css({'border-color':'#e8edf5','background':'#fff'}); }
  });
  MODULES_TREE.forEach(function(node){ if(node.is_group) updateGroupCheckbox(node.key); });
});

function getCheckedModules(){
  const mods=['dashboard'];
  $('.leaf-chk:not(:disabled):checked').each(function(){ const v=$(this).val(); if(!mods.includes(v)) mods.push(v); });
  return mods;
}

/* Traduction francaise de DataTables (integree, sans appel reseau) */
const DT_FR = {
  processing:    "Traitement en cours...",
  search:        "Rechercher :",
  lengthMenu:    "Afficher _MENU_ lignes",
  info:          "Affichage de _START_ a _END_ sur _TOTAL_ lignes",
  infoEmpty:     "Affichage de 0 a 0 sur 0 ligne",
  infoFiltered:  "(filtre sur _MAX_ lignes au total)",
  loadingRecords:"Chargement...",
  zeroRecords:   "Aucun resultat trouve",
  emptyTable:    "Aucun utilisateur a afficher",
  paginate: { first:"Premier", previous:"Precedent", next:"Suivant", last:"Dernier" },
  aria: {
    sortAscending:  ": activer pour trier la colonne par ordre croissant",
    sortDescending: ": activer pour trier la colonne par ordre decroissant"
  }
};
let personnelLoaded = false;

function apiPost(url, data){
  data = Object.assign({csrf_token: CSRF}, data);
  return $.post(url, data, null, 'json');
}

/* ---------- Select2 ---------- */
function initSelect2(){
  $('#u_idorga').select2({ theme:'bootstrap-5', dropdownParent: $('#userModal'), placeholder:'Choisir un organisme', allowClear:true, width:'100%' });
  $('#u_personnel').select2({ theme:'bootstrap-5', dropdownParent: $('#userModal'), placeholder:'Rechercher un agent...', width:'100%' });
}

function loadOrganismes(selected){
  return apiPost(API_ORG, {action:'list'}).then(res => {
    if(!res.success) return;
    let opts = '<option value="">-- Aucun --</option>';
    res.data.forEach(o => {
      const label = o.nomorga + (o.trigrorganisme ? ' (' + o.trigrorganisme + ')' : '');
      opts += `<option value="${o.idorga}">${esc(label)}</option>`;
    });
    $('#u_idorga').html(opts);
    if(selected) $('#u_idorga').val(String(selected));
    $('#u_idorga').trigger('change.select2');
  });
}

function loadPersonnel(){
  if(personnelLoaded) return Promise.resolve();
  return apiPost(API_PERS, {action:'list'}).then(res => {
    if(!res.success){ Swal.fire({icon:'error',title:'Personnel',text:res.message,confirmButtonColor:'#23408F'}); return; }
    let opts = '<option value="">-- Choisir un agent --</option>';
    res.data.forEach(p => {
      const label = (p.nomag||'') + ' ' + (p.prenag||'') + ' [' + (p.numat||'') + ']';
      opts += `<option value="${p.idpersonnel}" data-numat="${esc(p.numat||'')}" data-nom="${esc(p.nomag||'')}" data-prenom="${esc(p.prenag||'')}" data-email="${esc(p.email_anac||'')}">${esc(label)}</option>`;
    });
    $('#u_personnel').html(opts);
    personnelLoaded = true;
  });
}

/* ---------- Affichage selon le role ---------- */
function applyRoleUI(role){
  if(!role){ $('#identityFields, #blockPersonnel').hide(); return; }
  $('#identityFields').show();

  if(role === 'operateur'){
    $('#blockPersonnel').hide();
    $('#u_prenom, #u_nom').prop('readonly', false).val('');
    $('#u_idpersonnel').val('');
    $('#grpOrga').show();
    $('#u_idorga').prop('disabled', false);
    loadOrganismes().then(()=> $('#u_idorga').val('').trigger('change.select2'));
    // matricule auto 4xxx
    apiPost(API_USERS, {action:'next_operateur_matricule'}).then(r => {
      if(r.success){ $('#u_matricule').val(r.matricule); $('#matHelp').text('Attribue automatiquement (operateurs : 4000, 4001, ...).'); }
    });
  } else {
    // Roles internes ANAC -> choix dans personnel_anac, organisme = ANAC
    loadPersonnel().then(()=> $('#blockPersonnel').show());
    $('#u_prenom, #u_nom').prop('readonly', true);
    $('#matHelp').text('Repris du matricule (numat) de l\'agent.');
    $('#grpOrga').show();
    loadOrganismes(ANAC_ORGA).then(()=> $('#u_idorga').prop('disabled', true).trigger('change.select2'));
  }
}

$('#u_role').on('change', function(){
  applyRoleUI(this.value);
  // Aucune suggestion automatique de modules selon le role
  // L'admin doit choisir explicitement les modules a attribuer
});

/* Selection d'un agent ANAC -> remplir identite */
$('#u_personnel').on('change', function(){
  const o = this.options[this.selectedIndex];
  if(!o || !o.value){ return; }
  $('#u_idpersonnel').val(o.value);
  $('#u_prenom').val(o.dataset.prenom || '');
  $('#u_nom').val(o.dataset.nom || '');
  $('#u_matricule').val(o.dataset.numat || '');
  $('#u_email').val(o.dataset.email || '');
  checkEmail();
  checkMatricule();
});

/* ---------- Verifications de doublons ---------- */
let emailTimer;
function checkEmail(){
  const email = $('#u_email').val().trim();
  if(!email){ $('#err_email').hide(); return; }
  apiPost(API_USERS, {action:'check_email', email:email, iduser:$('#u_iduser').val()||0}).then(r => {
    if(r.exists){ $('#err_email').show(); $('#userSubmit').prop('disabled', true); }
    else { $('#err_email').hide(); $('#userSubmit').prop('disabled', false); }
  });
}
$('#u_email').on('input', function(){ clearTimeout(emailTimer); emailTimer = setTimeout(checkEmail, 350); });

function checkMatricule(){
  const m = $('#u_matricule').val().trim();
  if(!m || $('#u_action').val()!=='create'){ return; }
  apiPost(API_USERS, {action:'check_matricule', matricule:m, iduser:$('#u_iduser').val()||0}).then(r => {
    if(r.exists){
      $('#userSubmit').prop('disabled', true);
      Swal.fire({icon:'warning',title:'Matricule existant',text:'Un utilisateur existe deja pour ce matricule.',confirmButtonColor:'#23408F'});
    } else { $('#userSubmit').prop('disabled', false); }
  });
}

/* ---------- DataTable ---------- */
let table;
function loadTable(){
  apiPost(API_USERS, {action:'list'})
    .done(res => {
      if(!res.success){ Swal.fire({icon:'error',title:'Erreur',text:res.message||'Chargement impossible',confirmButtonColor:'#23408F'}); return; }
      const rows = res.data.map(u => {
        const role = ROLE_LABELS[u.role] || u.role;
        const twofa = u.is_2fa_enabled == 1 ? '<span class="badge-soft b-green">Activee</span>' : '<span class="badge-soft b-gold">Desactivee</span>';
        const statut = u.is_active == 1 ? '<span class="badge-soft b-green">Actif</span>' : '<span class="badge-soft b-red">Inactif</span>';
        const orga = u.nomorga ? esc(u.nomorga) : '<span class="text-muted">-</span>';
        const actions = `
          <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-primary act-edit" data-id="${u.iduser}" title="Modifier"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-outline-secondary act-pwd" data-id="${u.iduser}" data-name="${esc(u.prenom+' '+u.nom)}" title="Reinitialiser le mot de passe"><i class="bi bi-key"></i></button>
            <button class="btn ${u.is_2fa_enabled==1?'btn-outline-warning':'btn-outline-success'} act-2fa" data-id="${u.iduser}" data-on="${u.is_2fa_enabled}" data-name="${esc(u.prenom+' '+u.nom)}" title="2FA"><i class="bi bi-shield-lock"></i></button>
            <button class="btn ${u.is_active==1?'btn-outline-dark':'btn-outline-success'} act-active" data-id="${u.iduser}" data-on="${u.is_active}" title="Activer/Desactiver"><i class="bi bi-power"></i></button>
            <button class="btn btn-outline-danger act-del" data-id="${u.iduser}" data-name="${esc(u.prenom+' '+u.nom)}" title="Supprimer"><i class="bi bi-trash"></i></button>
          </div>`;
        return [ esc(u.matricule), esc(u.prenom+' '+u.nom), esc(u.email), `<span class="badge-soft b-blue">${esc(role)}</span>`, orga, twofa, statut, actions ];
      });
      if(table){ table.clear(); table.rows.add(rows); table.draw(); }
      else {
        table = $('#usersTable').DataTable({
          data: rows,
          columnDefs:[{targets:7, orderable:false, className:'text-end'}],
          order:[[1,'asc']], pageLength:10,
          /* Traduction francaise integree : aucune dependance Internet (fonctionne en reseau ferme) */
          language: DT_FR
        });
      }
      if($('#statsPanel').is(':visible')){ loadStats(); }
    })
    .fail(() => Swal.fire({icon:'error',title:'Connexion',text:'Impossible de joindre le serveur.',confirmButtonColor:'#23408F'}));
}

/* ---------- Nouvel utilisateur ---------- */
$('#btnNew').on('click', function(){
  document.getElementById('userForm').reset();
  $('#u_action').val('create'); $('#u_iduser').val(''); $('#u_idpersonnel').val('');
  $('#userModalTitle').text('Nouvel utilisateur');
  $('#err_email').hide(); $('#userSubmit').prop('disabled', false);
  $('#identityFields, #blockPersonnel').hide();
  $('#pwdWrap').show(); $('#sendWrap').show(); $('#activeWrap').hide();
  $('#pwd_auto').prop('checked', true); $('#manualPwdBox').hide(); $('#u_password').val('');
  $('#u_2fa, #u_notif').prop('checked', true);
  loadAndShowModules(null);
  initSelect2();
  new bootstrap.Modal('#userModal').show();
});

/* ---------- Edition ---------- */
$(document).on('click', '.act-edit', function(){
  const id = $(this).data('id');
  apiPost(API_USERS, {action:'get', iduser:id}).then(res => {
    if(!res.success){ Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'}); return; }
    const u = res.data;
    document.getElementById('userForm').reset();
    $('#u_action').val('update'); $('#u_iduser').val(u.iduser);
    $('#userModalTitle').text('Modifier l\'utilisateur');
    $('#err_email').hide(); $('#userSubmit').prop('disabled', false);
    initSelect2();
    $('#blockPersonnel').hide();
    $('#identityFields').show();
    $('#u_role').val(u.role);
    $('#u_prenom').prop('readonly', false).val(u.prenom);
    $('#u_nom').prop('readonly', false).val(u.nom);
    $('#u_matricule').val(u.matricule);
    $('#matHelp').text('');
    $('#u_email').val(u.email);
    $('#u_2fa').prop('checked', u.is_2fa_enabled==1);
    $('#u_notif').prop('checked', u.email_notifications==1);
    $('#u_active').prop('checked', u.is_active==1);
    $('#pwdWrap').hide(); $('#sendWrap').hide(); $('#activeWrap').show();
    $('#grpOrga').show();
    loadOrganismes(u.idorga).then(function(){ $('#u_idorga').prop('disabled', false).trigger('change.select2'); });
    const modal = new bootstrap.Modal('#userModal');
    modal.show();
    // Charger les habilitations APRES l'ouverture de la modale (DOM visible)
    document.getElementById('userModal').addEventListener('shown.bs.modal', function handler(){
      this.removeEventListener('shown.bs.modal', handler);
      loadAndShowModules(u.iduser);
    });
  });
});

/* ---------- Soumettre ---------- */
$('#userForm').on('submit', function(e){
  e.preventDefault();
  // Garde cote client : si mot de passe manuel a la creation, tous les criteres doivent etre remplis
  if($('#u_action').val() === 'create' && $('#pwd_manual').is(':checked') && !pwdAllOk($('#u_password').val() || '')){
    Swal.fire({icon:'warning',title:'Mot de passe trop faible',text:'Merci de respecter tous les criteres indiques (ils passent au vert).',confirmButtonColor:'#23408F'});
    return;
  }
  const btn = $('#userSubmit'); const html = btn.html();
  btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
  const data = {
    action: $('#u_action').val(), iduser: $('#u_iduser').val(),
    idpersonnel: $('#u_idpersonnel').val(),
    role: $('#u_role').val(), prenom: $('#u_prenom').val(), nom: $('#u_nom').val(),
    email: $('#u_email').val(), idorga: $('#u_idorga').val(),
    is_2fa_enabled: $('#u_2fa').is(':checked') ? 1 : 0,
    email_notifications: $('#u_notif').is(':checked') ? 1 : 0,
    is_active: $('#u_active').is(':checked') ? 1 : 0,
    pwd_mode: $('input[name=pwd_mode]:checked').val() || 'auto',
    password: $('#u_password').val() || '',
    send_email: $('input[name=send_email]:checked').val() || '1'
  };
  apiPost(API_USERS, data).then(res => {
    btn.prop('disabled', false).html(html);
    if(res.success){
      // Sauvegarder les habilitations apres creation/modification
      const iduser = res.iduser || $('#u_iduser').val();
      const mods   = getCheckedModules();
      apiPost(API_USERS, {action:'set_modules', iduser:iduser, 'modules[]':mods}).always(function(){
        bootstrap.Modal.getInstance(document.getElementById('userModal')).hide();
        if(res.password){ showPassword('Utilisateur cree', res.password); }
        else { Swal.fire({icon:'success',title:'Enregistre',text:res.message,timer:1800,showConfirmButton:false,timerProgressBar:true}); }
        loadTable();
      });
    } else { Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'}); }
  }).fail(()=> { btn.prop('disabled', false).html(html); Swal.fire({icon:'error',title:'Erreur',text:'Echec de la requete.',confirmButtonColor:'#23408F'}); });
});

/* ---------- Afficher + copier le mot de passe ---------- */
function showPassword(title, pwd){
  Swal.fire({
    icon:'success', title:title, confirmButtonColor:'#23408F',
    html:`<p class="mb-2">Mot de passe provisoire (a communiquer maintenant) :</p>
          <div class="input-group">
            <input id="pwOut" class="form-control text-center" value="${esc(pwd)}" readonly style="font-family:monospace;font-weight:700;color:#23408F">
            <button class="btn btn-anac" type="button" id="pwCopy"><i class="bi bi-clipboard"></i> Copier</button>
          </div>`,
    didOpen:()=>{
      document.getElementById('pwCopy').addEventListener('click', ()=>{
        copyText(document.getElementById('pwOut').value)
          .then(()=> { document.getElementById('pwCopy').innerHTML = '<i class="bi bi-check2"></i> Copie'; })
          .catch(()=> {});
      });
    }
  });
}

/* ---------- Reset password ---------- */
$(document).on('click', '.act-pwd', function(){
  const id=$(this).data('id'), name=$(this).data('name');
  Swal.fire({
    title:'Reinitialiser le mot de passe', icon:'question', html:`Pour <strong>${esc(name)}</strong> :`,
    input:'radio', inputOptions:{'1':'Envoyer par email','0':'Afficher le nouveau mot de passe'}, inputValue:'1',
    confirmButtonColor:'#23408F', showCancelButton:true, cancelButtonText:'Annuler'
  }).then(r => {
    if(!r.isConfirmed) return;
    apiPost(API_USERS, {action:'reset_password', iduser:id, send_email:r.value}).then(res => {
      if(res.success){
        if(res.password){ showPassword('Mot de passe reinitialise', res.password); }
        else { Swal.fire({icon:'success',title:'Fait',text:res.message,timer:1800,showConfirmButton:false}); }
      } else { Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'}); }
    });
  });
});

/* ---------- 2FA ---------- */
$(document).on('click', '.act-2fa', function(){
  const id=$(this).data('id'), on=$(this).data('on')==1, name=$(this).data('name');
  Swal.fire({
    title: on ? 'Desactiver la 2FA ?' : 'Reactiver la 2FA ?', html:`Utilisateur : <strong>${esc(name)}</strong>`,
    input:'text', inputPlaceholder:'Motif (journalise)', icon: on?'warning':'question',
    showCancelButton:true, cancelButtonText:'Annuler', confirmButtonColor:'#23408F', confirmButtonText: on ? 'Desactiver' : 'Reactiver'
  }).then(r => {
    if(!r.isConfirmed) return;
    apiPost(API_USERS, {action:'toggle_2fa', iduser:id, enabled: on?0:1, reason:r.value||''}).then(res => {
      if(res.success){ Swal.fire({icon:'success',title:'Fait',text:res.message,timer:1800,showConfirmButton:false}); loadTable(); }
      else { Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'}); }
    });
  });
});

/* ---------- Activer / desactiver ---------- */
$(document).on('click', '.act-active', function(){
  const id=$(this).data('id'), on=$(this).data('on')==1;
  apiPost(API_USERS, {action:'toggle_active', iduser:id, active: on?0:1}).then(res => {
    if(res.success){ loadTable(); } else { Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'}); }
  });
});

/* ---------- Supprimer ---------- */
$(document).on('click', '.act-del', function(){
  const id=$(this).data('id'), name=$(this).data('name');
  Swal.fire({title:'Supprimer cet utilisateur ?', html:`<strong>${esc(name)}</strong><br>Cette action est definitive.`,
    icon:'warning', showCancelButton:true, confirmButtonColor:'#D32F2F', cancelButtonText:'Annuler', confirmButtonText:'Supprimer'
  }).then(r => {
    if(!r.isConfirmed) return;
    apiPost(API_USERS, {action:'delete', iduser:id}).then(res => {
      if(res.success){ Swal.fire({icon:'success',title:'Supprime',timer:1500,showConfirmButton:false}); loadTable(); }
      else { Swal.fire({icon:'error',title:'Impossible',text:res.message,confirmButtonColor:'#23408F'}); }
    });
  });
});

/* ---------- Ajout rapide organisme + controle doublon ---------- */
$('#btnAddOrg').on('click', ()=> new bootstrap.Modal('#orgModal').show());

let orgTimer;
$('#o_nom').on('input', function(){
  clearTimeout(orgTimer);
  const nom = this.value.trim();
  if(!nom){ $('#err_org').hide(); $('#orgSubmit').prop('disabled', false); return; }
  orgTimer = setTimeout(()=>{
    apiPost(API_ORG, {action:'check', nomorga:nom}).then(r => {
      if(r.exists){ $('#err_org').show(); $('#orgSubmit').prop('disabled', true); }
      else { $('#err_org').hide(); $('#orgSubmit').prop('disabled', false); }
    });
  }, 350);
});

$('#orgForm').on('submit', function(e){
  e.preventDefault();
  const data = { action:'create', nomorga:$('#o_nom').val(), trigrorganisme:$('#o_sigle').val(),
                 ville_org:$('#o_ville').val(), telorga:$('#o_tel').val(), emailorga:$('#o_email').val() };
  apiPost(API_ORG, data).then(res => {
    if(res.success){
      bootstrap.Modal.getInstance(document.getElementById('orgModal')).hide();
      document.getElementById('orgForm').reset(); $('#err_org').hide();
      loadOrganismes(res.idorga).then(()=> $('#u_idorga').prop('disabled', false).trigger('change.select2'));
      Swal.fire({icon:'success',title:'Organisme ajoute',timer:1400,showConfirmButton:false});
    } else { Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'}); }
  });
});

/* ---------- Mot de passe manuel : criteres en temps reel ---------- */
const PWD_RULES = {
  len:     p => p.length >= 10 && p.length <= 72,
  upper:   p => /[A-Z]/.test(p),
  lower:   p => /[a-z]/.test(p),
  digit:   p => /[0-9]/.test(p),
  special: p => /[^A-Za-z0-9]/.test(p)
};
function pwdAllOk(p){ return Object.keys(PWD_RULES).every(k => PWD_RULES[k](p)); }
function refreshPwdChecklist(){
  const p = $('#u_password').val() || '';
  Object.keys(PWD_RULES).forEach(k => {
    const ok = PWD_RULES[k](p);
    const el = $('#manualPwdBox .pwd-rule[data-rule="'+k+'"]');
    el.toggleClass('ok', ok);
    el.find('i').attr('class', (ok ? 'bi bi-check-circle-fill' : 'bi bi-circle') + ' me-1');
  });
}
$('#u_password').on('input', refreshPwdChecklist);
$('input[name=pwd_mode]').on('change', function(){
  if($('#pwd_manual').is(':checked')){ $('#manualPwdBox').slideDown(120); refreshPwdChecklist(); }
  else { $('#manualPwdBox').slideUp(120); }
});
$('#btnTogglePwd').on('click', function(){
  const inp = document.getElementById('u_password'); const eye = $(this).find('i');
  if(inp.type === 'password'){ inp.type = 'text'; eye.attr('class','bi bi-eye-slash'); }
  else { inp.type = 'password'; eye.attr('class','bi bi-eye'); }
});

/* ---------- Statistiques (panneau repliable, choix memorise) ---------- */
function loadStats(){
  apiPost(API_USERS, {action:'stats'}).done(res => {
    if(!res.success || !res.stats) return;
    const s = res.stats;
    $('#st_total').text(s.total);            $('#st_actifs').text(s.actifs);
    $('#st_inactifs').text(s.inactifs);      $('#st_internes').text(s.internes);
    $('#st_operateurs').text(s.operateurs);  $('#st_2fa').text(s.deux_fa);
  });
}
function setStatsVisible(show){
  $('#statsPanel').toggle(show);
  $('#statsToggleLabel').text(show ? 'Masquer les statistiques' : 'Afficher les statistiques');
  try { localStorage.setItem('agai_stats_users', show ? '1' : '0'); } catch(e){}
  if(show){ loadStats(); }
}
$('#btnToggleStats').on('click', function(){ setStatsVisible($('#statsPanel').is(':hidden')); });
/* Restaurer le choix precedent de l'utilisateur */
(function(){ let v = '0'; try { v = localStorage.getItem('agai_stats_users') || '0'; } catch(e){} if(v === '1'){ setStatsVisible(true); } })();

loadTable();
</script>