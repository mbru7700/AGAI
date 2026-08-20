<?php
/**
 * Endpoint AJAX : Domaines (table `domaine`). Route : /api/domaines
 * ------------------------------------------------------------
 * Module "Donnees de structures". CRUD complet : list, get, create,
 * update, delete, plus check_label (controle de doublon en direct).
 *
 * Securite : session + role autorise (guardApi 'structures') + CSRF
 * sur toute action mutatrice, requetes preparees partout, validation
 * serveur, journalisation, et garde-fou a la suppression (refus si le
 * domaine est rattache a des sous-domaines, reglements, habilitations,
 * audits ou fiches).
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }

Rbac::guardApi('domaines');

if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Jeton CSRF invalide. Rechargez la page.']);
    exit;
}

$db     = Database::getInstance();
$action = $_POST['action'] ?? '';

$ok   = function ($extra = []) { echo json_encode(['success' => true] + $extra); };
$fail = function ($msg) { echo json_encode(['success' => false, 'message' => $msg]); };

/* Nettoie un libelle (espaces, retours a la ligne, tabulations parasites). */
function clean_label($s): string
{
    return trim(preg_replace('/\s+/', ' ', (string) $s));
}

try {
    switch ($action) {

        // ----------------------------------------------------------------
        case 'list':
            // Perimetre AGAI uniquement : domaines marques gere_agai='AGAI' OU
            // deja utilises dans AGAI (audit, habilitation, sous-domaine, reglement).
            $rows = $db->query(
                "SELECT d.iddomaine, d.nomdomaine, d.libel_domaine, d.gere_agai,
                        (SELECT COUNT(*) FROM sous_domaine s WHERE s.iddomaine = d.iddomaine) AS nb_sd,
                        (SELECT COUNT(*) FROM reglement r   WHERE r.iddomaine = d.iddomaine) AS nb_reg,
                        (SELECT COUNT(*) FROM habilitation h WHERE h.iddomaine = d.iddomaine) AS nb_hab,
                        (SELECT COUNT(DISTINCT ae.idaudit) FROM audit_equipe ae WHERE ae.iddomaine = d.iddomaine) AS nb_aud
                 FROM domaine d
                 WHERE d.gere_agai = 'AGAI'
                    OR EXISTS (SELECT 1 FROM audit_equipe ae2 WHERE ae2.iddomaine = d.iddomaine)
                    OR EXISTS (SELECT 1 FROM habilitation  h2 WHERE h2.iddomaine  = d.iddomaine)
                    OR EXISTS (SELECT 1 FROM sous_domaine  s2 WHERE s2.iddomaine  = d.iddomaine)
                    OR EXISTS (SELECT 1 FROM reglement     r2 WHERE r2.iddomaine  = d.iddomaine)
                 ORDER BY d.iddomaine DESC"
            )->fetchAll();
            $ok(['data' => $rows]);
            break;

        // ----------------------------------------------------------------
        case 'stats':
            // Toutes les stats sont calculees SUR LE PERIMETRE AGAI uniquement.
            // Sous-requete du perimetre : les domaines "AGAI".
            $perimDom =
                "SELECT d.iddomaine FROM domaine d
                 WHERE d.gere_agai = 'AGAI'
                    OR EXISTS (SELECT 1 FROM audit_equipe ae WHERE ae.iddomaine = d.iddomaine)
                    OR EXISTS (SELECT 1 FROM habilitation  h  WHERE h.iddomaine  = d.iddomaine)
                    OR EXISTS (SELECT 1 FROM sous_domaine  s  WHERE s.iddomaine  = d.iddomaine)
                    OR EXISTS (SELECT 1 FROM reglement     r  WHERE r.iddomaine  = d.iddomaine)";
            $st = $db->query(
                "SELECT
                    (SELECT COUNT(*) FROM domaine d WHERE d.iddomaine IN ($perimDom)) AS total,
                    (SELECT COUNT(*) FROM sous_domaine s WHERE s.iddomaine IN ($perimDom)) AS sous,
                    (SELECT COUNT(*) FROM reglement r WHERE r.iddomaine IN ($perimDom)) AS regs,
                    (SELECT COUNT(*) FROM habilitation h WHERE h.iddomaine IN ($perimDom)) AS habs,
                    (SELECT COUNT(DISTINCT ae.iddomaine) FROM audit_equipe ae WHERE ae.iddomaine IN ($perimDom)) AS audits,
                    (SELECT COUNT(DISTINCT h.idinspecteur) FROM habilitation h WHERE h.iddomaine IN ($perimDom)) AS inspecteurs"
            )->fetch();
            foreach ($st as $k => $v) { $st[$k] = (int) $v; }
            $ok(['stats' => $st]);
            break;

        // ----------------------------------------------------------------
        // Synthese pour les modales des KPI (perimetre AGAI) :
        //  - habilitations detaillees (domaine + inspecteur + dates)
        //  - domaines audites (avec nb d'audits)
        //  - inspecteurs habilites (regroupes, avec nb de domaines)
        case 'synthese':
            $perimDom =
                "(d.gere_agai = 'AGAI'
                  OR EXISTS (SELECT 1 FROM audit_equipe ae WHERE ae.iddomaine = d.iddomaine)
                  OR EXISTS (SELECT 1 FROM habilitation  h2 WHERE h2.iddomaine = d.iddomaine)
                  OR EXISTS (SELECT 1 FROM sous_domaine  s2 WHERE s2.iddomaine = d.iddomaine)
                  OR EXISTS (SELECT 1 FROM reglement     r2 WHERE r2.iddomaine = d.iddomaine))";

            // Habilitations detaillees
            $habs = $db->query(
                "SELECT d.iddomaine, d.nomdomaine, d.libel_domaine,
                        h.numero_habilitation, h.date_habilitation, h.date_expiration,
                        i.nominspecteur, i.preninspect, i.trigr_inspecteur
                 FROM habilitation h
                 JOIN domaine d    ON d.iddomaine    = h.iddomaine
                 JOIN inspecteur i ON i.idinspecteur = h.idinspecteur
                 WHERE $perimDom
                 ORDER BY d.nomdomaine, h.date_expiration ASC"
            )->fetchAll();

            // Domaines audites (avec nombre d'audits distincts)
            $domAud = $db->query(
                "SELECT d.iddomaine, d.nomdomaine, d.libel_domaine,
                        COUNT(DISTINCT ae.idaudit) AS nb_aud
                 FROM domaine d
                 JOIN audit_equipe ae ON ae.iddomaine = d.iddomaine
                 WHERE $perimDom
                 GROUP BY d.iddomaine
                 ORDER BY nb_aud DESC, d.nomdomaine"
            )->fetchAll();

            // Inspecteurs habilites (regroupes) avec nombre de domaines et prochaine echeance
            $insp = $db->query(
                "SELECT i.idinspecteur, i.nominspecteur, i.preninspect, i.trigr_inspecteur,
                        COUNT(DISTINCT h.iddomaine) AS nb_dom,
                        MIN(h.date_expiration) AS prochaine_exp
                 FROM habilitation h
                 JOIN inspecteur i ON i.idinspecteur = h.idinspecteur
                 JOIN domaine d    ON d.iddomaine    = h.iddomaine
                 WHERE $perimDom
                 GROUP BY i.idinspecteur
                 ORDER BY i.nominspecteur, i.preninspect"
            )->fetchAll();

            $ok(['habilitations' => $habs, 'domaines_audites' => $domAud, 'inspecteurs' => $insp]);
            break;

        // ----------------------------------------------------------------
        // Detail complet : sous-domaines, reglements, habilitations/inspecteurs, audits
        case 'detail':
            $id = (int) ($_POST['iddomaine'] ?? 0);
            if ($id <= 0) { $fail('Domaine introuvable.'); break; }
            $stD = $db->prepare("SELECT iddomaine, nomdomaine, libel_domaine FROM domaine WHERE iddomaine = ?");
            $stD->execute([$id]);
            $d = $stD->fetch();
            if (!$d) { $fail('Domaine introuvable.'); break; }
            // Sous-domaines ORDER DESC
            $stSd = $db->prepare(
                "SELECT idsousdomaine, nom_sousdomaine AS nomsd FROM sous_domaine WHERE iddomaine = ? ORDER BY idsousdomaine DESC"
            );
            $stSd->execute([$id]);
            $sous = $stSd->fetchAll();
            // Reglements ORDER DESC
            $stReg = $db->prepare(
                "SELECT idreglement, code_reglement, libelle_reglement FROM reglement WHERE iddomaine = ? ORDER BY idreglement DESC"
            );
            $stReg->execute([$id]);
            $regs = $stReg->fetchAll();
            // Habilitations + inspecteurs
            $stHab = $db->prepare(
                "SELECT h.idhabilitation, h.idinspecteur, h.numero_habilitation,
                        h.date_habilitation, h.date_expiration,
                        i.nominspecteur, i.preninspect, i.trigr_inspecteur
                 FROM habilitation h
                 JOIN inspecteur i ON i.idinspecteur = h.idinspecteur
                 WHERE h.iddomaine = ?
                 ORDER BY h.date_expiration ASC, i.nominspecteur"
            );
            $stHab->execute([$id]);
            $habs = $stHab->fetchAll();
            // Audits recents associes ORDER DESC
            $stAud = $db->prepare(
                "SELECT DISTINCT a.idaudit, a.num_audit, a.type_activite, a.statut, a.date_previsionnelle,
                        o.nomorga
                 FROM audit_equipe ae
                 JOIN audit a ON a.idaudit = ae.idaudit
                 LEFT JOIN organisme o ON o.idorga = a.idorga
                 WHERE ae.iddomaine = ?
                 ORDER BY a.idaudit DESC
                 LIMIT 20"
            );
            $stAud->execute([$id]);
            $auds = $stAud->fetchAll();
            $ok(['data' => $d, 'sous_domaines' => $sous, 'reglements' => $regs, 'habilitations' => $habs, 'audits' => $auds]);
            break;

        // ----------------------------------------------------------------
        case 'get':
            $id = (int) ($_POST['iddomaine'] ?? 0);
            $st = $db->prepare("SELECT iddomaine, nomdomaine, libel_domaine FROM domaine WHERE iddomaine = ?");
            $st->execute([$id]);
            $d = $st->fetch();
            if (!$d) { $fail('Domaine introuvable.'); break; }
            $ok(['data' => $d]);
            break;

        // ----------------------------------------------------------------
        // Controle de doublon en direct. Si le domaine existe deja dans la
        // table PARTAGEE, on renvoie ses donnees pour pre-remplir le formulaire
        // et on indique s'il est deja dans le perimetre AGAI ou a "recuperer".
        case 'check_nom':
            $nom = clean_label($_POST['nomdomaine'] ?? '');
            $exc = (int) ($_POST['iddomaine'] ?? 0);
            if ($nom === '') { $ok(['exists' => false]); break; }
            $st = $db->prepare(
                "SELECT d.iddomaine, d.nomdomaine, d.libel_domaine, d.gere_agai,
                        (EXISTS (SELECT 1 FROM audit_equipe ae WHERE ae.iddomaine = d.iddomaine)
                         OR EXISTS (SELECT 1 FROM habilitation h WHERE h.iddomaine = d.iddomaine)
                         OR EXISTS (SELECT 1 FROM sous_domaine s WHERE s.iddomaine = d.iddomaine)
                         OR EXISTS (SELECT 1 FROM reglement r WHERE r.iddomaine = d.iddomaine)) AS utilise
                 FROM domaine d
                 WHERE LOWER(d.nomdomaine) = LOWER(?) AND d.iddomaine <> ?
                 LIMIT 1"
            );
            $st->execute([$nom, $exc]);
            $found = $st->fetch();
            if (!$found) { $ok(['exists' => false]); break; }
            $dansAgai = ($found['gere_agai'] === 'AGAI') || ((int) $found['utilise'] > 0);
            $ok(['exists' => true, 'dans_agai' => $dansAgai, 'data' => $found]);
            break;

        // ----------------------------------------------------------------
        case 'create':
        case 'update':
            $isUpdate = ($action === 'update');
            $id       = (int) ($_POST['iddomaine'] ?? 0);
            $nom      = clean_label($_POST['nomdomaine'] ?? '');
            $libel    = clean_label($_POST['libel_domaine'] ?? '');

            if ($nom === '' || mb_strlen($nom) > 255) { $fail('Le nom du domaine est requis (255 caracteres maximum).'); break; }
            if ($libel !== '' && mb_strlen($libel) > 255) { $fail('Le libelle du domaine est trop long (255 caracteres maximum).'); break; }
            // libel_domaine est NOT NULL en base : si vide, on reprend le nom
            if ($libel === '') { $libel = $nom; }

            // Doublon de nom (insensible a la casse), hors enregistrement courant.
            // En update (y compris recuperation), l'id courant est exclu, donc pas
            // de faux blocage. En create, si le nom existe, on refuse et le front
            // proposera la recuperation.
            $stDup = $db->prepare("SELECT iddomaine FROM domaine WHERE LOWER(nomdomaine) = LOWER(?) AND iddomaine <> ?");
            $stDup->execute([$nom, $id]);
            if ($stDup->fetch()) { $fail('Un domaine portant ce nom existe deja.'); break; }

            if (!$isUpdate) {
                $st = $db->prepare("INSERT INTO domaine (nomdomaine, libel_domaine, gere_agai) VALUES (?, ?, 'AGAI')");
                $st->execute([$nom, $libel]);
                $newId = (int) $db->lastInsertId();
                Audit::log('create', 'structures', 'Creation domaine AGAI #' . $newId . ' (' . $nom . ')');
                $ok(['message' => 'Domaine enregistre.', 'iddomaine' => $newId]);
            } else {
                if ($id <= 0) { $fail('Domaine introuvable.'); break; }
                $stG = $db->prepare("SELECT iddomaine FROM domaine WHERE iddomaine = ?");
                $stG->execute([$id]);
                if (!$stG->fetch()) { $fail('Domaine introuvable.'); break; }
                // gere_agai='AGAI' couvre la RECUPERATION d'un domaine cree par une
                // autre application : des que le CI le valide, il entre dans AGAI.
                $st = $db->prepare("UPDATE domaine SET nomdomaine = ?, libel_domaine = ?, gere_agai = 'AGAI' WHERE iddomaine = ?");
                $st->execute([$nom, $libel, $id]);
                Audit::log('update', 'structures', 'Modification domaine AGAI #' . $id . ' (' . $nom . ')');
                $ok(['message' => 'Domaine mis a jour.', 'iddomaine' => $id]);
            }
            break;

        // ----------------------------------------------------------------
        case 'delete':
            $id = (int) ($_POST['iddomaine'] ?? 0);
            if ($id <= 0) { $fail('Domaine introuvable.'); break; }

            // Garde-fou : on refuse la suppression si le domaine est utilise,
            // pour eviter toute suppression en cascade involontaire.
            $refs = [];
            $checks = [
                'sous_domaine'        => 'sous-domaine(s)',
                'reglement'           => 'reglement(s)',
                'habilitation'        => 'habilitation(s)',
                'audit_equipe'        => 'affectation(s) d\'audit',
                'fiche_non_conformite'=> 'fiche(s) de non-conformite',
            ];
            foreach ($checks as $table => $label) {
                $st = $db->prepare("SELECT COUNT(*) FROM `$table` WHERE iddomaine = ?");
                $st->execute([$id]);
                $n = (int) $st->fetchColumn();
                if ($n > 0) { $refs[] = $n . ' ' . $label; }
            }
            if ($refs) {
                $fail('Suppression impossible : ce domaine est rattache a ' . implode(', ', $refs) . '. Detachez ces elements d\'abord.');
                break;
            }

            $db->prepare("DELETE FROM domaine WHERE iddomaine = ?")->execute([$id]);
            Audit::log('delete', 'structures', 'Suppression domaine #' . $id);
            $ok(['message' => 'Domaine supprime.']);
            break;

        // ----------------------------------------------------------------
        default:
            $fail('Action inconnue.');
    }
} catch (Throwable $e) {
    error_log('domaines endpoint : ' . $e->getMessage());
    $fail('Erreur technique.');
}