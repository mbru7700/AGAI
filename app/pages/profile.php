<?php
/**
 * Module : Mon profil (page)
 * ------------------------------------------------------------
 * Accessible a TOUT utilisateur connecte. Affiche ses informations
 * de compte en lecture seule et permet de changer son mot de passe.
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }

Rbac::guardAuthPage();           // session valide suffisante (tout role)
$csrf      = Security::generateCSRF();
$pageTitle = 'Mon profil';
$active     = '';                // pas un module du menu lateral
$pageIcon  = 'bi-person-circle';

// Donnees fraiches du compte connecte
$uid = (int) ($_SESSION['user_id'] ?? 0);
$db  = Database::getInstance();
$st  = $db->prepare(
    "SELECT u.matricule, u.email, u.nom, u.prenom, u.role, u.idorga,
            u.is_2fa_enabled, u.email_notifications, u.last_login, u.created_at,
            o.nomorga
     FROM users u
     LEFT JOIN organisme o ON o.idorga = u.idorga
     WHERE u.iduser = ? LIMIT 1"
);
$st->execute([$uid]);
$u = $st->fetch() ?: [];

$fmt = function ($d) {
    if (empty($d)) { return null; }
    $ts = strtotime($d);
    return $ts ? date('d/m/Y H:i', $ts) : null;
};

require_once INCLUDES_PATH . '/layout_head.php';
?>

<div class="page-head">
  <div>
    <h1><i class="bi bi-person-circle me-2" style="color:var(--anac-primary)"></i>Mon profil</h1>
    <div class="sub">Consultez les informations de votre compte et changez votre mot de passe.</div>
  </div>
</div>

<style>
  .info-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 28px;}
  @media (max-width:767px){ .info-grid{grid-template-columns:1fr;} }
  .info-row .lbl{font-size:.78rem;color:#6b7a90;margin-bottom:2px;}
  .info-row .val{font-size:1rem;color:#2C3E50;font-weight:600;word-break:break-word;}
  .badge-soft{display:inline-block;padding:.25rem .6rem;border-radius:20px;font-size:.78rem;font-weight:600;}
  .b-green{background:rgba(30,156,75,.12);color:#1E9C4B;}
  .b-gold{background:rgba(243,195,0,.18);color:#b58a00;}
  .b-blue{background:rgba(35,64,143,.10);color:#23408F;}
  .b-red{background:rgba(211,47,47,.10);color:#D32F2F;}
  .pwd-rule{color:#8a93a3;transition:color .15s;}
  .pwd-rule.ok{color:#1E9C4B;font-weight:600;}
  .profile-ic{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:rgba(35,64,143,.10);color:#23408F;font-size:1.3rem;flex:0 0 auto;}
</style>

<div class="row g-4">

  <!-- ===== Informations du compte (lecture seule) ===== -->
  <div class="col-12 col-xl-7">
    <div class="card-anac p-3 p-md-4 h-100">
      <div class="d-flex align-items-center gap-3 mb-3">
        <div class="profile-ic"><i class="bi bi-person-vcard"></i></div>
        <div>
          <div style="font-size:1.1rem;font-weight:700;color:#2C3E50;"><?php echo Security::escape(trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? ''))); ?></div>
          <div><span class="badge-soft b-blue"><?php echo Security::escape(Rbac::roleLabel($u['role'] ?? '')); ?></span></div>
        </div>
      </div>
      <hr>
      <div class="info-grid">
        <div class="info-row"><div class="lbl">Matricule</div><div class="val"><?php echo Security::escape($u['matricule'] ?? '-'); ?></div></div>
        <div class="info-row"><div class="lbl">Email professionnel</div><div class="val"><?php echo Security::escape($u['email'] ?? '-'); ?></div></div>
        <div class="info-row"><div class="lbl">Nom</div><div class="val"><?php echo Security::escape($u['nom'] ?? '-'); ?></div></div>
        <div class="info-row"><div class="lbl">Prenom</div><div class="val"><?php echo Security::escape($u['prenom'] ?? '-'); ?></div></div>
        <div class="info-row"><div class="lbl">Organisme d'appartenance</div><div class="val"><?php echo Security::escape($u['nomorga'] ?? '-'); ?></div></div>
        <div class="info-row"><div class="lbl">Role</div><div class="val"><?php echo Security::escape(Rbac::roleLabel($u['role'] ?? '')); ?></div></div>
        <div class="info-row">
          <div class="lbl">Double authentification (2FA)</div>
          <div class="val">
            <?php echo (!empty($u['is_2fa_enabled']))
                ? '<span class="badge-soft b-green">Activee</span>'
                : '<span class="badge-soft b-gold">Desactivee</span>'; ?>
          </div>
        </div>
        <div class="info-row">
          <div class="lbl">Notifications par email</div>
          <div class="val">
            <?php echo (!empty($u['email_notifications']))
                ? '<span class="badge-soft b-green">Activees</span>'
                : '<span class="badge-soft b-gold">Desactivees</span>'; ?>
          </div>
        </div>
        <div class="info-row"><div class="lbl">Derniere connexion</div><div class="val"><?php echo Security::escape($fmt($u['last_login'] ?? null) ?? 'Jamais'); ?></div></div>
        <div class="info-row"><div class="lbl">Compte cree le</div><div class="val"><?php echo Security::escape($fmt($u['created_at'] ?? null) ?? '-'); ?></div></div>
      </div>
      <div class="form-text mt-3"><i class="bi bi-info-circle me-1"></i>Ces informations sont en lecture seule. Pour toute correction (nom, email, role...), contactez un administrateur.</div>
    </div>
  </div>

  <!-- ===== Changer mon mot de passe ===== -->
  <div class="col-12 col-xl-5">
    <div class="card-anac p-3 p-md-4 h-100">
      <h5 class="mb-1"><i class="bi bi-shield-lock me-2" style="color:var(--anac-primary)"></i>Changer mon mot de passe</h5>
      <div class="form-text mb-3">Choisissez un mot de passe facile a retenir pour vous, mais respectant tous les criteres de securite.</div>

      <div class="mb-3">
        <label class="form-label">Mot de passe actuel</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-lock"></i></span>
          <input type="password" class="form-control" id="cur_pwd" autocomplete="current-password">
          <button class="btn btn-outline-secondary toggle-pwd" type="button" data-target="cur_pwd" tabindex="-1"><i class="bi bi-eye"></i></button>
        </div>
      </div>

      <div class="mb-2">
        <label class="form-label">Nouveau mot de passe</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-key"></i></span>
          <input type="password" class="form-control" id="new_pwd" autocomplete="new-password" maxlength="72">
          <button class="btn btn-outline-secondary toggle-pwd" type="button" data-target="new_pwd" tabindex="-1"><i class="bi bi-eye"></i></button>
        </div>
      </div>

      <div class="small mb-3">
        <div class="pwd-rule" data-rule="len"><i class="bi bi-circle me-1"></i>Au moins 10 caracteres</div>
        <div class="pwd-rule" data-rule="upper"><i class="bi bi-circle me-1"></i>Une lettre majuscule (A a Z)</div>
        <div class="pwd-rule" data-rule="lower"><i class="bi bi-circle me-1"></i>Une lettre minuscule (a a z)</div>
        <div class="pwd-rule" data-rule="digit"><i class="bi bi-circle me-1"></i>Un chiffre (0 a 9)</div>
        <div class="pwd-rule" data-rule="special"><i class="bi bi-circle me-1"></i>Un caractere special (! @ # ? ...)</div>
      </div>

      <div class="mb-2">
        <label class="form-label">Confirmer le nouveau mot de passe</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
          <input type="password" class="form-control" id="cfm_pwd" autocomplete="new-password" maxlength="72">
          <button class="btn btn-outline-secondary toggle-pwd" type="button" data-target="cfm_pwd" tabindex="-1"><i class="bi bi-eye"></i></button>
        </div>
        <div class="form-text" id="matchMsg"></div>
      </div>

      <div class="d-grid mt-3">
        <button class="btn btn-anac" id="btnChangePwd"><i class="bi bi-check-lg me-1"></i>Mettre a jour mon mot de passe</button>
      </div>
    </div>
  </div>

</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>

<script>
const CSRF = '<?php echo Security::escape($csrf); ?>';
const API_PROFILE = AGAI_BASE + '/api/profile';

function apiPost(url, data){
  data = Object.assign({csrf_token: CSRF}, data);
  return $.post(url, data, null, 'json');
}

/* Afficher / masquer chaque champ */
$('.toggle-pwd').on('click', function(){
  const inp = document.getElementById($(this).data('target'));
  const eye = $(this).find('i');
  if(inp.type === 'password'){ inp.type = 'text'; eye.attr('class','bi bi-eye-slash'); }
  else { inp.type = 'password'; eye.attr('class','bi bi-eye'); }
});

/* Criteres en temps reel */
const PWD_RULES = {
  len:     p => p.length >= 10 && p.length <= 72,
  upper:   p => /[A-Z]/.test(p),
  lower:   p => /[a-z]/.test(p),
  digit:   p => /[0-9]/.test(p),
  special: p => /[^A-Za-z0-9]/.test(p)
};
function pwdAllOk(p){ return Object.keys(PWD_RULES).every(k => PWD_RULES[k](p)); }
function refreshChecklist(){
  const p = $('#new_pwd').val() || '';
  Object.keys(PWD_RULES).forEach(k => {
    const ok = PWD_RULES[k](p);
    const el = $('.pwd-rule[data-rule="'+k+'"]');
    el.toggleClass('ok', ok);
    el.find('i').attr('class', (ok ? 'bi bi-check-circle-fill' : 'bi bi-circle') + ' me-1');
  });
}
function refreshMatch(){
  const n = $('#new_pwd').val() || '', c = $('#cfm_pwd').val() || '';
  const m = $('#matchMsg');
  if(c === ''){ m.text('').css('color',''); return; }
  if(n === c){ m.html('<i class="bi bi-check-circle-fill me-1"></i>Les mots de passe correspondent').css('color','#1E9C4B'); }
  else { m.html('<i class="bi bi-x-circle-fill me-1"></i>Les mots de passe ne correspondent pas').css('color','#D32F2F'); }
}
$('#new_pwd').on('input', function(){ refreshChecklist(); refreshMatch(); });
$('#cfm_pwd').on('input', refreshMatch);

/* Soumission */
$('#btnChangePwd').on('click', function(){
  const cur = $('#cur_pwd').val() || '', neu = $('#new_pwd').val() || '', cfm = $('#cfm_pwd').val() || '';
  if(cur === ''){ Swal.fire({icon:'warning',title:'Mot de passe actuel',text:'Veuillez saisir votre mot de passe actuel.',confirmButtonColor:'#23408F'}); return; }
  if(!pwdAllOk(neu)){ Swal.fire({icon:'warning',title:'Mot de passe trop faible',text:'Le nouveau mot de passe doit respecter tous les criteres (ils passent au vert).',confirmButtonColor:'#23408F'}); return; }
  if(neu !== cfm){ Swal.fire({icon:'warning',title:'Confirmation',text:'Le nouveau mot de passe et sa confirmation ne correspondent pas.',confirmButtonColor:'#23408F'}); return; }

  const btn = $(this); const html = btn.html();
  btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
  apiPost(API_PROFILE, {action:'change_password', current_password:cur, new_password:neu, confirm_password:cfm})
    .done(res => {
      btn.prop('disabled', false).html(html);
      if(res.success){
        $('#cur_pwd, #new_pwd, #cfm_pwd').val('');
        refreshChecklist(); refreshMatch();
        Swal.fire({icon:'success',title:'Mot de passe change',text:res.message,confirmButtonColor:'#23408F'});
      } else {
        Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'});
      }
    })
    .fail(() => { btn.prop('disabled', false).html(html); Swal.fire({icon:'error',title:'Connexion',text:'Impossible de joindre le serveur.',confirmButtonColor:'#23408F'}); });
});
</script>