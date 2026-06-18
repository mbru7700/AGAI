<?php
/**
 * Endpoint AJAX : Gestion des utilisateurs (CRUD + actions)
 * Accas : administrateurs uniquement. Route : /api/users
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }

Rbac::guardApi('users');                 // session + role admin + entete JSON

if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Jeton CSRF invalide. Rechargez la page.']);
    exit;
}

$db          = Database::getInstance();
$action      = $_POST['action'] ?? '';
$ANAC_ORGA   = 1282;                                       // AGENCE NATIONALE DE L'AVIATION CIVILE - GABON
$PUBLIC_URL  = rtrim(env('APP_URL', SITE_URL), '/') . '/'; // lien e-mail joignable (configurable en prod)

$ok   = function ($extra = []) { echo json_encode(['success' => true] + $extra); };
$fail = function ($msg) { echo json_encode(['success' => false, 'message' => $msg]); };

/* Prochain matricule operateur disponible : 4000, 4001, ... */
function nextOperateurMatricule($db): string
{
    $last = $db->query("SELECT matricule FROM users WHERE matricule REGEXP '^4[0-9]{3}$' ORDER BY CAST(matricule AS UNSIGNED) DESC LIMIT 1")->fetchColumn();
    $next = $last ? ((int) $last + 1) : 4000;
    return (string) $next;
}

/* email deja pris ? */
function emailExists($db, string $email, $excludeId = null): bool
{
    $sql = "SELECT iduser FROM users WHERE email = ?" . ($excludeId ? " AND iduser <> ?" : "");
    $st  = $db->prepare($sql);
    $st->execute($excludeId ? [$email, $excludeId] : [$email]);
    return (bool) $st->fetch();
}

/* matricule deja pris ? */
function matriculeExists($db, string $m, $excludeId = null): bool
{
    $sql = "SELECT iduser FROM users WHERE matricule = ?" . ($excludeId ? " AND iduser <> ?" : "");
    $st  = $db->prepare($sql);
    $st->execute($excludeId ? [$m, $excludeId] : [$m]);
    return (bool) $st->fetch();
}

try {
    switch ($action) {

        case 'list':
            $st = $db->query(
                "SELECT u.iduser, u.matricule, u.email, u.nom, u.prenom, u.role,
                        u.is_active, u.is_2fa_enabled, u.email_notifications, u.idorga,
                        o.nomorga, u.last_login
                 FROM users u
                 LEFT JOIN organisme o ON o.idorga = u.idorga
                 ORDER BY u.nom, u.prenom"
            );
            $ok(['data' => $st->fetchAll()]);
            break;

        case 'stats':
            // Denombrements simples pour le panneau de statistiques de la page.
            $row = $db->query(
                "SELECT
                    COUNT(*)                          AS total,
                    SUM(is_active = 1)                AS actifs,
                    SUM(is_active = 0)                AS inactifs,
                    SUM(idorga = 1282)                AS internes,
                    SUM(role = 'operateur')           AS operateurs,
                    SUM(is_2fa_enabled = 1)           AS deux_fa
                 FROM users"
            )->fetch();
            // On renvoie des entiers propres (les SUM peuvent revenir en chaine ou NULL).
            foreach ($row as $k => $v) { $row[$k] = (int) $v; }
            $ok(['stats' => $row]);
            break;

        case 'get':
            $st = $db->prepare("SELECT iduser, matricule, email, nom, prenom, role, idorga,
                                       is_active, is_2fa_enabled, email_notifications
                                FROM users WHERE iduser = ?");
            $st->execute([(int) ($_POST['iduser'] ?? 0)]);
            $u = $st->fetch();
            $u ? $ok(['data' => $u]) : $fail('Utilisateur introuvable.');
            break;

        // Verifications temps reel (doublons)
        case 'check_email':
            $email = trim($_POST['email'] ?? '');
            $excl  = (int) ($_POST['iduser'] ?? 0) ?: null;
            echo json_encode(['exists' => $email !== '' && emailExists($db, $email, $excl)]);
            break;

        case 'check_matricule':
            $m    = trim($_POST['matricule'] ?? '');
            $excl = (int) ($_POST['iduser'] ?? 0) ?: null;
            echo json_encode(['exists' => $m !== '' && matriculeExists($db, $m, $excl)]);
            break;

        case 'next_operateur_matricule':
            $ok(['matricule' => nextOperateurMatricule($db)]);
            break;

        case 'create':
            $role  = trim($_POST['role'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $is2fa = ((int) ($_POST['is_2fa_enabled'] ?? 1)) === 1 ? 1 : 0;
            $notif = ((int) ($_POST['email_notifications'] ?? 1)) === 1 ? 1 : 0;
            $send  = ((int) ($_POST['send_email'] ?? 1)) === 1;

            if (!array_key_exists($role, Rbac::roles())) { $fail('Role invalide.'); break; }
            if (!Security::validateEmail($email) || mb_strlen($email) > 100) { $fail('Email invalide.'); break; }
            if (emailExists($db, $email)) { $fail('Cet email est deja utilise.'); break; }

            if ($role === 'operateur') {
                // ---- Operateur : saisie manuelle, matricule auto 4xxx ----
                $prenom = trim($_POST['prenom'] ?? '');
                $nom    = trim($_POST['nom'] ?? '');
                $idorga = ($_POST['idorga'] ?? '') === '' ? null : (int) $_POST['idorga'];
                if ($prenom === '' || mb_strlen($prenom) > 100) { $fail('Prenom invalide.'); break; }
                if ($nom === '' || mb_strlen($nom) > 100) { $fail('Nom invalide.'); break; }
                if ($idorga === null) { $fail("Veuillez choisir l'organisme de l'operateur."); break; }
                $st = $db->prepare("SELECT idorga FROM organisme WHERE idorga = ?");
                $st->execute([$idorga]);
                if (!$st->fetch()) { $fail('Organisme inconnu.'); break; }

                $matricule = nextOperateurMatricule($db);
                if (matriculeExists($db, $matricule)) { $matricule = (string) ((int) $matricule + 1); }

            } else {
                // ---- Autre role : agent choisi dans personnel_anac ----
                $idpersonnel = (int) ($_POST['idpersonnel'] ?? 0);
                if ($idpersonnel <= 0) { $fail('Veuillez choisir un agent ANAC.'); break; }

                $st = $db->prepare("SELECT idpersonnel, numat, nomag, prenag, email_anac FROM personnel_anac WHERE idpersonnel = ?");
                $st->execute([$idpersonnel]);
                $p = $st->fetch();
                if (!$p) { $fail("Cet agent n'existe pas dans le personnel ANAC."); break; }

                $prenom    = trim($p['prenag'] ?? '');
                $nom       = trim($p['nomag'] ?? '');
                $matricule = trim((string) $p['numat']);
                $idorga    = $ANAC_ORGA;
                if ($matricule === '' || mb_strlen($matricule) > 20) { $fail('Matricule (numat) invalide pour cet agent.'); break; }
                if (matriculeExists($db, $matricule)) { $fail('Un utilisateur existe deja pour cet agent (matricule).'); break; }

                // Si l'agent n'a pas d'email enregistre (ou different), on met a jour personnel_anac
                $emailAnac = trim((string) ($p['email_anac'] ?? ''));
                if ($emailAnac === '' || strcasecmp($emailAnac, $email) !== 0) {
                    $db->prepare("UPDATE personnel_anac SET email_anac = ? WHERE idpersonnel = ?")->execute([$email, $idpersonnel]);
                }
            }

            // ---- Mot de passe : automatique (fort) ou choisi manuellement ----
            $pwdMode = (($_POST['pwd_mode'] ?? 'auto') === 'manual') ? 'manual' : 'auto';
            if ($pwdMode === 'manual') {
                $password = (string) ($_POST['password'] ?? '');
                $check    = Security::validatePasswordStrength($password);
                if (!$check['valid']) {
                    $fail('Mot de passe trop faible. Il doit contenir : ' . implode(', ', $check['errors']) . '.');
                    break;
                }
            } else {
                $password = Security::generateStrongPassword(14);
            }
            $hash = Security::hashPassword($password);

            $st = $db->prepare(
                "INSERT INTO users (matricule, email, password_hash, nom, prenom, role, idorga,
                                    is_active, is_2fa_enabled, email_notifications)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)"
            );
            $st->execute([$matricule, $email, $hash, $nom, $prenom, $role, $idorga, $is2fa, $notif]);

            Audit::log('create', 'users', "Creation utilisateur $email (role $role, matricule $matricule, mot de passe $pwdMode)");

            if ($send) {
                $mailer = new Mailer();
                $sent = $mailer->sendCredentials($email, trim($prenom . ' ' . $nom), $email, $password, $PUBLIC_URL);
                if ($sent) {
                    $ok(['message' => 'Utilisateur cree. Identifiants envoyes par email.']);
                } elseif ($pwdMode === 'manual') {
                    $ok(['message' => 'Utilisateur cree, mais email non envoye. Le mot de passe defini reste valide.']);
                } else {
                    $ok(['message' => 'Utilisateur cree, mais email non envoye.', 'password' => $password]);
                }
            } elseif ($pwdMode === 'manual') {
                // L'administrateur a saisi le mot de passe : inutile de le reafficher.
                $ok(['message' => 'Utilisateur cree. Mot de passe defini manuellement.']);
            } else {
                $ok(['message' => 'Utilisateur cree.', 'password' => $password]);
            }
            break;

        case 'update':
            $id = (int) ($_POST['iduser'] ?? 0);
            if ($id <= 0) { $fail('Identifiant invalide.'); break; }

            $prenom = trim($_POST['prenom'] ?? '');
            $nom    = trim($_POST['nom'] ?? '');
            $email  = trim($_POST['email'] ?? '');
            $role   = trim($_POST['role'] ?? '');
            $idorga = ($_POST['idorga'] ?? '') === '' ? null : (int) $_POST['idorga'];
            $is2fa  = ((int) ($_POST['is_2fa_enabled'] ?? 1)) === 1 ? 1 : 0;
            $notif  = ((int) ($_POST['email_notifications'] ?? 1)) === 1 ? 1 : 0;
            $active = ((int) ($_POST['is_active'] ?? 1)) === 1 ? 1 : 0;

            if ($prenom === '' || mb_strlen($prenom) > 100) { $fail('Prenom invalide.'); break; }
            if ($nom === '' || mb_strlen($nom) > 100) { $fail('Nom invalide.'); break; }
            if (!Security::validateEmail($email) || mb_strlen($email) > 100) { $fail('Email invalide.'); break; }
            if (!array_key_exists($role, Rbac::roles())) { $fail('Role invalide.'); break; }
            if (emailExists($db, $email, $id)) { $fail('Cet email est deja utilise.'); break; }
            if ($idorga !== null) {
                $st = $db->prepare("SELECT idorga FROM organisme WHERE idorga = ?");
                $st->execute([$idorga]);
                if (!$st->fetch()) { $fail('Organisme inconnu.'); break; }
            }
            if ($id === (int) $_SESSION['user_id'] && $active === 0) { $fail('Vous ne pouvez pas desactiver votre propre compte.'); break; }

            $st = $db->prepare(
                "UPDATE users SET email=?, nom=?, prenom=?, role=?, idorga=?,
                        is_2fa_enabled=?, email_notifications=?, is_active=?, updated_at=NOW()
                 WHERE iduser=?"
            );
            $st->execute([$email, $nom, $prenom, $role, $idorga, $is2fa, $notif, $active, $id]);
            Audit::log('update', 'users', "Modification utilisateur #$id ($email)");
            $ok(['message' => 'Utilisateur mis a jour.']);
            break;

        case 'toggle_active':
            $id = (int) ($_POST['iduser'] ?? 0);
            $active = ((int) ($_POST['active'] ?? 1)) === 1 ? 1 : 0;
            if ($id === (int) $_SESSION['user_id'] && $active === 0) { $fail('Vous ne pouvez pas desactiver votre propre compte.'); break; }
            $db->prepare("UPDATE users SET is_active=?, updated_at=NOW() WHERE iduser=?")->execute([$active, $id]);
            Audit::log($active ? 'enable' : 'disable', 'users', "Compte #$id " . ($active ? 'active' : 'desactive'));
            $ok(['message' => 'Statut mis a jour.']);
            break;

        case 'toggle_2fa':
            $id      = (int) ($_POST['iduser'] ?? 0);
            $enabled = ((int) ($_POST['enabled'] ?? 1)) === 1;
            $reason  = Security::cleanInput($_POST['reason'] ?? '');
            echo json_encode(Auth::set2FA($id, $enabled, $reason !== '' ? $reason : null));
            exit;

        case 'reset_password':
            $id = (int) ($_POST['iduser'] ?? 0);
            $st = $db->prepare("SELECT email, prenom, nom FROM users WHERE iduser = ?");
            $st->execute([$id]);
            $u = $st->fetch();
            if (!$u) { $fail('Utilisateur introuvable.'); break; }

            $send     = ((int) ($_POST['send_email'] ?? 1)) === 1;
            $password = Security::generateStrongPassword(12);
            $db->prepare("UPDATE users SET password_hash=?, otp_hash=NULL, otp_expires_at=NULL, otp_attempts=0, updated_at=NOW() WHERE iduser=?")
               ->execute([Security::hashPassword($password), $id]);
            Audit::log('password_reset', 'users', "Reinitialisation mot de passe utilisateur #$id");

            if ($send) {
                $mailer = new Mailer();
                $sent = $mailer->sendCredentials($u['email'], trim($u['prenom'] . ' ' . $u['nom']), $u['email'], $password, $PUBLIC_URL);
                $sent ? $ok(['message' => 'Mot de passe reinitialise et envoye par email.'])
                      : $ok(['message' => 'Mot de passe reinitialise, mais email non envoye.', 'password' => $password]);
            } else {
                $ok(['message' => 'Mot de passe reinitialise.', 'password' => $password]);
            }
            break;

        case 'delete':
            $id = (int) ($_POST['iduser'] ?? 0);
            if ($id === (int) $_SESSION['user_id']) { $fail('Vous ne pouvez pas supprimer votre propre compte.'); break; }

            $st = $db->prepare("SELECT role, is_active FROM users WHERE iduser=?");
            $st->execute([$id]);
            $target = $st->fetch();
            if (!$target) { $fail('Utilisateur introuvable.'); break; }
            if ($target['role'] === 'admin') {
                $cnt = (int) $db->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1")->fetchColumn();
                if ($cnt <= 1) { $fail("Impossible : c'est le dernier administrateur actif."); break; }
            }

            try {
                $db->prepare("DELETE FROM users WHERE iduser=?")->execute([$id]);
                Audit::log('delete', 'users', "Suppression utilisateur #$id");
                $ok(['message' => 'Utilisateur supprime.']);
            } catch (Throwable $e) {
                $fail('Suppression impossible : cet utilisateur a des donnees liees. Desactivez plutot son compte.');
            }
            break;

        default:
            $fail('Action inconnue.');
    }
} catch (Throwable $e) {
    error_log('users endpoint : ' . $e->getMessage());
    $fail('Erreur technique.');
}