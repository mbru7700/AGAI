<?php
/**
 * Endpoint AJAX : Sous-domaines (table `sous_domaine`).
 * Route : /api/sousdomaines
 * ------------------------------------------------------------
 * Module "Donnees de structures". CRUD complet : list, domaines (liste
 * des domaines parents), stats, get, check_nom, create, update, delete.
 *
 * Securite : session + role autorise (guardApi 'structures') + CSRF,
 * requetes preparees, validation serveur (le domaine parent doit exister),
 * journalisation, et refus de suppression si le sous-domaine est utilise
 * par une fiche de non-conformite.
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }

Rbac::guardApi('sousdomaines');

if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Jeton CSRF invalide. Rechargez la page.']);
    exit;
}

$db     = Database::getInstance();
$action = $_POST['action'] ?? '';

$ok   = function ($extra = []) { echo json_encode(['success' => true] + $extra); };
$fail = function ($msg) { echo json_encode(['success' => false, 'message' => $msg]); };

function clean_label($s): string
{
    return trim(preg_replace('/\s+/', ' ', (string) $s));
}

try {
    switch ($action) {

        // ----------------------------------------------------------------
        case 'list':
            $rows = $db->query(
                "SELECT sd.idsousdomaine, sd.iddomaine, sd.nom_sousdomaine,
                        d.nomdomaine, d.libel_domaine,
                        (SELECT COUNT(*) FROM fiche_non_conformite f WHERE f.idsousdomaine = sd.idsousdomaine) AS nb_fnc
                 FROM sous_domaine sd
                 LEFT JOIN domaine d ON d.iddomaine = sd.iddomaine
                 ORDER BY sd.idsousdomaine DESC"
            )->fetchAll();
            $ok(['data' => $rows]);
            break;

        // ----------------------------------------------------------------
        // Liste des domaines parents (pour les listes deroulantes)
        case 'domaines':
            $rows = $db->query("SELECT iddomaine, nomdomaine, libel_domaine FROM domaine ORDER BY nomdomaine")->fetchAll();
            $ok(['data' => $rows]);
            break;

        // ----------------------------------------------------------------
        case 'stats':
            $st = $db->query(
                "SELECT
                    (SELECT COUNT(*) FROM sous_domaine)                       AS total,
                    (SELECT COUNT(DISTINCT iddomaine) FROM sous_domaine)       AS dom_couverts"
            )->fetch();
            $ok(['stats' => $st]);
            break;

        // ----------------------------------------------------------------
        case 'get':
            $id = (int) ($_POST['idsousdomaine'] ?? 0);
            $st = $db->prepare("SELECT idsousdomaine, iddomaine, nom_sousdomaine FROM sous_domaine WHERE idsousdomaine = ?");
            $st->execute([$id]);
            $sd = $st->fetch();
            if (!$sd) { $fail('Sous-domaine introuvable.'); break; }
            $ok(['data' => $sd]);
            break;

        // ----------------------------------------------------------------
        // Doublon : un meme nom de sous-domaine dans le meme domaine parent
        case 'check_nom':
            $nom = clean_label($_POST['nom_sousdomaine'] ?? '');
            $dom = (int) ($_POST['iddomaine'] ?? 0);
            $exc = (int) ($_POST['idsousdomaine'] ?? 0);
            if ($nom === '' || $dom <= 0) { $ok(['exists' => false]); break; }
            $st = $db->prepare("SELECT idsousdomaine FROM sous_domaine WHERE iddomaine = ? AND LOWER(nom_sousdomaine) = LOWER(?) AND idsousdomaine <> ?");
            $st->execute([$dom, $nom, $exc]);
            $ok(['exists' => (bool) $st->fetch()]);
            break;

        // ----------------------------------------------------------------
        case 'create':
        case 'update':
            $isUpdate = ($action === 'update');
            $id       = (int) ($_POST['idsousdomaine'] ?? 0);
            $dom      = (int) ($_POST['iddomaine'] ?? 0);
            $nom      = clean_label($_POST['nom_sousdomaine'] ?? '');

            if ($dom <= 0) { $fail('Veuillez choisir le domaine parent.'); break; }
            if ($nom === '' || mb_strlen($nom) > 255) { $fail('Le nom du sous-domaine est requis (255 caracteres maximum).'); break; }

            // Le domaine parent doit exister reellement
            $stD = $db->prepare("SELECT iddomaine FROM domaine WHERE iddomaine = ?");
            $stD->execute([$dom]);
            if (!$stD->fetch()) { $fail('Domaine parent inconnu. Merci de le re-selectionner.'); break; }

            // Doublon dans le meme domaine
            $stDup = $db->prepare("SELECT idsousdomaine FROM sous_domaine WHERE iddomaine = ? AND LOWER(nom_sousdomaine) = LOWER(?) AND idsousdomaine <> ?");
            $stDup->execute([$dom, $nom, $id]);
            if ($stDup->fetch()) { $fail('Ce sous-domaine existe deja pour ce domaine.'); break; }

            if (!$isUpdate) {
                $st = $db->prepare("INSERT INTO sous_domaine (iddomaine, nom_sousdomaine) VALUES (?, ?)");
                $st->execute([$dom, $nom]);
                $newId = (int) $db->lastInsertId();
                Audit::log('create', 'structures', 'Creation sous-domaine #' . $newId . ' (' . $nom . ')');
                $ok(['message' => 'Sous-domaine enregistre.', 'idsousdomaine' => $newId]);
            } else {
                if ($id <= 0) { $fail('Sous-domaine introuvable.'); break; }
                $stG = $db->prepare("SELECT idsousdomaine FROM sous_domaine WHERE idsousdomaine = ?");
                $stG->execute([$id]);
                if (!$stG->fetch()) { $fail('Sous-domaine introuvable.'); break; }
                $st = $db->prepare("UPDATE sous_domaine SET iddomaine = ?, nom_sousdomaine = ? WHERE idsousdomaine = ?");
                $st->execute([$dom, $nom, $id]);
                Audit::log('update', 'structures', 'Modification sous-domaine #' . $id . ' (' . $nom . ')');
                $ok(['message' => 'Sous-domaine mis a jour.', 'idsousdomaine' => $id]);
            }
            break;

        // ----------------------------------------------------------------
        case 'delete':
            $id = (int) ($_POST['idsousdomaine'] ?? 0);
            if ($id <= 0) { $fail('Sous-domaine introuvable.'); break; }

            $st = $db->prepare("SELECT COUNT(*) FROM fiche_non_conformite WHERE idsousdomaine = ?");
            $st->execute([$id]);
            $n = (int) $st->fetchColumn();
            if ($n > 0) {
                $fail('Suppression impossible : ce sous-domaine est rattache a ' . $n . ' fiche(s) de non-conformite. Detachez-les d\'abord.');
                break;
            }

            $db->prepare("DELETE FROM sous_domaine WHERE idsousdomaine = ?")->execute([$id]);
            Audit::log('delete', 'structures', 'Suppression sous-domaine #' . $id);
            $ok(['message' => 'Sous-domaine supprime.']);
            break;

        // ----------------------------------------------------------------
        default:
            $fail('Action inconnue.');
    }
} catch (Throwable $e) {
    error_log('sousdomaines endpoint : ' . $e->getMessage());
    $fail('Erreur technique.');
}