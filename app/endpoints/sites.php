<?php
/**
 * Endpoint AJAX : Sites d'inspection (table `site`). Route : /api/sites
 * ------------------------------------------------------------
 * Module "Donnees de structures". CRUD complet : list, pays (liste des
 * pays pour la liste deroulante), stats, get, check_oaci, create, update,
 * delete.
 *
 * Securite : session + role autorise (guardApi 'structures') + CSRF,
 * requetes preparees, validation serveur, journalisation, et refus de
 * suppression si le site est utilise par des audits.
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }

Rbac::guardApi('sites');

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

/* Compte les audits rattaches a un site (resiste a l'absence de la colonne idsite). */
function audits_using_site($db, int $idsite): int
{
    try {
        $st = $db->prepare("SELECT COUNT(*) FROM audit WHERE idsite = ?");
        $st->execute([$idsite]);
        return (int) $st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

try {
    switch ($action) {

        // ----------------------------------------------------------------
        case 'list':
            $rows = $db->query(
                "SELECT s.idsite, s.indicateur_oaci, s.nomsite, s.idpays, s.ville,
                        p.nompays,
                        (SELECT COUNT(*) FROM audit a WHERE a.idsite = s.idsite) AS nb_aud
                 FROM site s
                 LEFT JOIN pays_adna p ON p.idpays = s.idpays
                 ORDER BY s.idsite DESC"
            )->fetchAll();
            $ok(['data' => $rows]);
            break;

        // ----------------------------------------------------------------
        case 'pays':
            $rows = $db->query("SELECT MIN(idpays) AS idpays, nompays FROM pays_adna GROUP BY nompays ORDER BY nompays")->fetchAll();
            $ok(['data' => $rows]);
            break;

        // ----------------------------------------------------------------
        case 'stats':
            $st = $db->query(
                "SELECT
                    (SELECT COUNT(*) FROM site)                                                              AS total,
                    (SELECT COUNT(DISTINCT UPPER(TRIM(p.nompays))) FROM site s JOIN pays_adna p ON p.idpays=s.idpays
                       WHERE p.nompays IS NOT NULL AND TRIM(p.nompays) <> '')                               AS pays_couverts,
                    (SELECT COUNT(DISTINCT s2.idsite) FROM site s2
                       WHERE EXISTS (SELECT 1 FROM audit a WHERE a.idsite = s2.idsite))                     AS sites_avec,
                    (SELECT COUNT(*) FROM audit WHERE idsite IS NOT NULL AND idsite > 0)                    AS total_aud,
                    (SELECT COUNT(DISTINCT UPPER(TRIM(ville))) FROM site WHERE ville IS NOT NULL AND TRIM(ville)<>'')    AS villes,
                    (SELECT COUNT(*) FROM site s3
                       WHERE NOT EXISTS (SELECT 1 FROM audit a WHERE a.idsite = s3.idsite))                 AS sans_aud"
            )->fetch();
            foreach ($st as $k => $v) { $st[$k] = (int) $v; }
            $ok(['stats' => $st]);
            break;

        // ----------------------------------------------------------------
        // Synthese pour les modales des KPI :
        //  - pays couverts (avec nb de sites)
        //  - sites avec audits (avec nb d'audits)
        //  - audits planifies (detail par site)
        //  - villes distinctes (avec nb de sites)
        //  - sites sans audit
        case 'synthese':
            // Pays couverts (avec nombre de sites). On groupe sur le NOM du pays
            // (normalise) et non sur idpays, car un meme pays peut avoir plusieurs
            // idpays dans le referentiel (ex : "Gabon" en double) ; le regroupement
            // par nom les fusionne en une seule ligne.
            $pays = $db->query(
                "SELECT TRIM(p.nompays) AS nompays, COUNT(s.idsite) AS nb_sites
                 FROM site s
                 JOIN pays_adna p ON p.idpays = s.idpays
                 WHERE p.nompays IS NOT NULL AND TRIM(p.nompays) <> ''
                 GROUP BY UPPER(TRIM(p.nompays))
                 ORDER BY nb_sites DESC, nompays"
            )->fetchAll();

            // Sites sans pays renseigne (pour ne rien masquer dans la modale pays)
            $sitesSansPays = (int) $db->query(
                "SELECT COUNT(*) FROM site s
                 WHERE s.idpays IS NULL
                    OR NOT EXISTS (SELECT 1 FROM pays_adna p WHERE p.idpays = s.idpays AND TRIM(p.nompays) <> '')"
            )->fetchColumn();

            // Sites avec audits (avec nb d'audits)
            $sitesAvec = $db->query(
                "SELECT s.idsite, s.indicateur_oaci, s.nomsite, s.ville, p.nompays,
                        COUNT(a.idaudit) AS nb_aud
                 FROM site s
                 JOIN audit a ON a.idsite = s.idsite
                 LEFT JOIN pays_adna p ON p.idpays = s.idpays
                 GROUP BY s.idsite
                 ORDER BY nb_aud DESC, s.nomsite"
            )->fetchAll();

            // Villes distinctes (avec nb de sites). Regroupement normalise pour
            // fusionner d'eventuelles variantes de casse/espaces d'une meme ville.
            $villes = $db->query(
                "SELECT TRIM(s.ville) AS ville, COUNT(s.idsite) AS nb_sites
                 FROM site s
                 WHERE s.ville IS NOT NULL AND TRIM(s.ville) <> ''
                 GROUP BY UPPER(TRIM(s.ville))
                 ORDER BY nb_sites DESC, ville"
            )->fetchAll();

            // Sites sans audit
            $sansAudit = $db->query(
                "SELECT s.idsite, s.indicateur_oaci, s.nomsite, s.ville, p.nompays
                 FROM site s
                 LEFT JOIN pays_adna p ON p.idpays = s.idpays
                 WHERE NOT EXISTS (SELECT 1 FROM audit a WHERE a.idsite = s.idsite)
                 ORDER BY s.nomsite"
            )->fetchAll();

            $ok([
                'pays'            => $pays,
                'sites_sans_pays' => $sitesSansPays,
                'sites_avec'      => $sitesAvec,
                'villes'          => $villes,
                'sans_audit'      => $sansAudit,
            ]);
            break;

        // ----------------------------------------------------------------
        // Detail : infos site + audits associes ORDER DESC
        case 'detail':
            $id = (int) ($_POST['idsite'] ?? 0);
            if ($id <= 0) { $fail('Site introuvable.'); break; }
            $stS = $db->prepare(
                "SELECT s.idsite, s.indicateur_oaci, s.nomsite, s.ville, s.idpays, p.nompays
                 FROM site s
                 LEFT JOIN pays_adna p ON p.idpays = s.idpays
                 WHERE s.idsite = ?"
            );
            $stS->execute([$id]);
            $site = $stS->fetch();
            if (!$site) { $fail('Site introuvable.'); break; }
            $stAud = $db->prepare(
                "SELECT a.idaudit, a.num_audit, a.type_activite, a.statut, a.date_previsionnelle,
                        o.nomorga,
                        TRIM(CONCAT(COALESCE(r.preninspect,''),' ',COALESCE(r.nominspecteur,''))) AS responsable
                 FROM audit a
                 LEFT JOIN organisme  o ON o.idorga       = a.idorga
                 LEFT JOIN inspecteur r ON r.idinspecteur = a.idresponsable_audit
                 WHERE a.idsite = ?
                 ORDER BY a.idaudit DESC LIMIT 30"
            );
            $stAud->execute([$id]);
            $ok(['data' => $site, 'audits' => $stAud->fetchAll()]);
            break;

        // ----------------------------------------------------------------
        case 'get':
            $id = (int) ($_POST['idsite'] ?? 0);
            $st = $db->prepare(
                "SELECT s.idsite, s.indicateur_oaci, s.nomsite, s.idpays, s.ville, p.nompays
                 FROM site s LEFT JOIN pays_adna p ON p.idpays = s.idpays
                 WHERE s.idsite = ?"
            );
            $st->execute([$id]);
            $s = $st->fetch();
            if (!$s) { $fail('Site introuvable.'); break; }
            $ok(['data' => $s]);
            break;

        // ----------------------------------------------------------------
        // Doublon sur l'indicateur OACI (unique)
        case 'check_oaci':
            $oaci = clean_label($_POST['indicateur_oaci'] ?? '');
            $exc  = (int) ($_POST['idsite'] ?? 0);
            if ($oaci === '') { $ok(['exists' => false]); break; }
            $st = $db->prepare("SELECT idsite FROM site WHERE LOWER(indicateur_oaci) = LOWER(?) AND idsite <> ?");
            $st->execute([$oaci, $exc]);
            $ok(['exists' => (bool) $st->fetch()]);
            break;

        // ----------------------------------------------------------------
        case 'create':
        case 'update':
            $isUpdate = ($action === 'update');
            $id       = (int) ($_POST['idsite'] ?? 0);
            $oaci     = strtoupper(clean_label($_POST['indicateur_oaci'] ?? ''));
            $nom      = clean_label($_POST['nomsite'] ?? '');
            $idpays   = (int) ($_POST['idpays'] ?? 0);
            $ville    = clean_label($_POST['ville'] ?? '');

            if ($oaci === '' || mb_strlen($oaci) > 10) { $fail('L\'indicateur OACI est requis (10 caracteres maximum, ex : FOOL).'); break; }
            if ($nom === '' || mb_strlen($nom) > 150)   { $fail('Le nom du site est requis (150 caracteres maximum).'); break; }
            if (mb_strlen($ville) > 150) { $fail('Le nom de la ville est trop long (150 caracteres maximum).'); break; }

            // Le pays choisi doit exister (s'il est renseigne)
            if ($idpays > 0) {
                $stP = $db->prepare("SELECT idpays FROM pays_adna WHERE idpays = ?");
                $stP->execute([$idpays]);
                if (!$stP->fetch()) { $fail('Pays inconnu. Merci de le re-selectionner.'); break; }
            }
            $idpaysVal = ($idpays > 0) ? $idpays : null;
            $villeVal  = ($ville === '') ? null : $ville;

            // Doublon sur l'indicateur OACI
            $stDup = $db->prepare("SELECT idsite FROM site WHERE LOWER(indicateur_oaci) = LOWER(?) AND idsite <> ?");
            $stDup->execute([$oaci, $id]);
            if ($stDup->fetch()) { $fail('Un site avec cet indicateur OACI existe deja.'); break; }

            if (!$isUpdate) {
                $st = $db->prepare("INSERT INTO site (indicateur_oaci, nomsite, idpays, ville) VALUES (?, ?, ?, ?)");
                $st->execute([$oaci, $nom, $idpaysVal, $villeVal]);
                $newId = (int) $db->lastInsertId();
                Audit::log('create', 'structures', 'Creation site #' . $newId . ' (' . $oaci . ')');
                $ok(['message' => 'Site enregistre.', 'idsite' => $newId]);
            } else {
                if ($id <= 0) { $fail('Site introuvable.'); break; }
                $stG = $db->prepare("SELECT idsite FROM site WHERE idsite = ?");
                $stG->execute([$id]);
                if (!$stG->fetch()) { $fail('Site introuvable.'); break; }
                $st = $db->prepare("UPDATE site SET indicateur_oaci = ?, nomsite = ?, idpays = ?, ville = ? WHERE idsite = ?");
                $st->execute([$oaci, $nom, $idpaysVal, $villeVal, $id]);
                Audit::log('update', 'structures', 'Modification site #' . $id . ' (' . $oaci . ')');
                $ok(['message' => 'Site mis a jour.', 'idsite' => $id]);
            }
            break;

        // ----------------------------------------------------------------
        case 'delete':
            $id = (int) ($_POST['idsite'] ?? 0);
            if ($id <= 0) { $fail('Site introuvable.'); break; }

            $n = audits_using_site($db, $id);
            if ($n > 0) {
                $fail('Suppression impossible : ce site est rattache a ' . $n . ' audit(s).');
                break;
            }

            $db->prepare("DELETE FROM site WHERE idsite = ?")->execute([$id]);
            Audit::log('delete', 'structures', 'Suppression site #' . $id);
            $ok(['message' => 'Site supprime.']);
            break;

        // ----------------------------------------------------------------
        default:
            $fail('Action inconnue.');
    }
} catch (Throwable $e) {
    error_log('sites endpoint : ' . $e->getMessage());
    $fail('Erreur technique.');
}