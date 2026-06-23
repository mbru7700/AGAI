<?php
/**
 * Endpoint AJAX : Audits et inspections. Route : /api/audits
 * ------------------------------------------------------------
 * Phase 1 : lecture seule (list, stats, get).
 * Phase 2a : creation / edition / suppression de l'audit, plus les listes
 * de support (exploitants, inspecteurs) et le controle du numero.
 * L'equipe (audit_equipe) et les reglements (audit_reglement) seront
 * geres dans les phases 2b et 2c.
 *
 * Securite : session + role autorise (guardApi 'audits') + CSRF,
 * requetes preparees, validation serveur (enums, references existantes),
 * journalisation, suppression en transaction.
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }

Rbac::guardApi('audits');

if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Jeton CSRF invalide. Rechargez la page.']);
    exit;
}

$db     = Database::getInstance();
$action = $_POST['action'] ?? '';

$ok   = function ($extra = []) { echo json_encode(['success' => true] + $extra); };
$fail = function ($msg) { echo json_encode(['success' => false, 'message' => $msg]); };

/* Valeurs autorisees (liste blanche serveur, alignee sur les enum SQL). */
const AUDIT_TYPES  = ['audit','inspection_programmee','inspection_non_programmee','demonstration','test','investigation'];
const AUDIT_CADRES = ['certification','homologation','reconnaissance','renouvellement','surveillance_continue','traitement_evenement','fermeture_provisoire','fermeture_definitive','delivrance_autorisation'];

/* Date nullable : chaine vide -> NULL. */
function nd($v) { $v = trim((string) $v); return $v === '' ? null : $v; }

/* Codes de type pour le numero d'audit. */
const AUDIT_TYPE_CODES = [
    'audit' => 'AUDIT', 'inspection_programmee' => 'INSP', 'inspection_non_programmee' => 'INSP-NP',
    'demonstration' => 'DEMO', 'test' => 'TEST', 'investigation' => 'INVEST',
];

/* Genere un numero d'audit unique au format 001/2026/AUDIT-CI. */
function gen_num_audit($db, string $type): string
{
    $year = (int) date('Y');
    $code = AUDIT_TYPE_CODES[$type] ?? 'SUP';
    $seq  = (int) $db->query("SELECT COUNT(*) FROM audit WHERE YEAR(created_at) = " . $year)->fetchColumn() + 1;
    do {
        $num = str_pad((string) $seq, 3, '0', STR_PAD_LEFT) . '/' . $year . '/' . $code . '-CI';
        $st  = $db->prepare("SELECT idaudit FROM audit WHERE num_audit = ?");
        $st->execute([$num]);
        $exists = (bool) $st->fetch();
        $seq++;
    } while ($exists);
    return $num;
}

try {
    switch ($action) {

        // ----------------------------------------------------------------
        case 'list':
            $uid  = (int) ($_SESSION['user_id'] ?? 0);
            $role = $_SESSION['user']['role'] ?? '';

            // Resoudre l'idinspecteur de l'utilisateur connecte
            $myInsp = null;
            $stI = $db->prepare("SELECT idinspecteur FROM inspecteur WHERE iduser = ? LIMIT 1");
            $stI->execute([$uid]);
            $rowI = $stI->fetch();
            if ($rowI) { $myInsp = (int) $rowI['idinspecteur']; }

            // Clause WHERE selon le role
            $where  = '';
            $params = [];

            if (in_array($role, ['admin', 'chef_inspecteur', 'consultant'], true)) {
                // Voit tout - aucun filtre
                $where  = '';
                $params = [];
            } elseif ($myInsp !== null) {
                // Inspecteur ou RA : uniquement les audits ou il est planifie
                // (equipe + responsable + chef)
                $where  = "WHERE (
                    a.idaudit IN (SELECT ae.idaudit FROM audit_equipe ae WHERE ae.idinspecteur = ?)
                    OR a.idresponsable_audit = ?
                    OR a.idchef_inspecteur   = ?
                )";
                $params = [$myInsp, $myInsp, $myInsp];
            } else {
                // Role inspecteur sans fiche inspecteur en BDD : aucun audit visible
                $where  = "WHERE 1=0";
                $params = [];
            }

            $st = $db->execute(
                "SELECT a.idaudit, a.num_audit, a.type_activite, a.cadre, a.site_inspection,
                        a.date_previsionnelle, a.date_realisation, a.statut, a.est_ferme,
                        a.type_activite_operateur, a.idorga, a.idsite, a.delai_execution,
                        a.date_delivrance_rapport, a.date_notification, a.date_fermeture,
                        o.nomorga,
                        TRIM(CONCAT(COALESCE(r.preninspect,''),' ',COALESCE(r.nominspecteur,''))) AS responsable,
                        TRIM(CONCAT(COALESCE(c.preninspect,''),' ',COALESCE(c.nominspecteur,''))) AS chef
                 FROM audit a
                 LEFT JOIN organisme  o ON o.idorga       = a.idorga
                 LEFT JOIN inspecteur r ON r.idinspecteur = a.idresponsable_audit
                 LEFT JOIN inspecteur c ON c.idinspecteur = a.idchef_inspecteur
                 $where
                 ORDER BY a.idaudit DESC",
                $params
            );
            $audits = $st->fetchAll();

            // Enrichir chaque audit avec ses inspecteurs (nom + domaine + est_resp)
            if (!empty($audits)) {
                $ids    = array_column($audits, 'idaudit');
                $inIds  = implode(',', array_map('intval', $ids));
                $stEq   = $db->query(
                    "SELECT ae.idaudit, ae.idinspecteur, ae.est_responsable,
                            TRIM(CONCAT(COALESCE(i.preninspect,''),' ',COALESCE(i.nominspecteur,''))) AS nom,
                            d.nomdomaine
                     FROM audit_equipe ae
                     JOIN inspecteur i ON i.idinspecteur = ae.idinspecteur
                     LEFT JOIN domaine d ON d.iddomaine  = ae.iddomaine
                     WHERE ae.idaudit IN ($inIds)
                     ORDER BY ae.est_responsable DESC, i.nominspecteur"
                );
                $eqMap = [];
                foreach ($stEq->fetchAll() as $eq) {
                    $eqMap[(int) $eq['idaudit']][] = $eq;
                }
                foreach ($audits as &$audit) {
                    $audit['inspecteurs'] = $eqMap[(int) $audit['idaudit']] ?? [];
                }
                unset($audit);
            }

            $ok(['data' => $audits]);
            break;

        // ----------------------------------------------------------------
        // ----------------------------------------------------------------
        // stats : voir l'action 'stats' plus bas (version avec filtrage par role)
        case 'lists':
            $exploitants = $db->query(
                "SELECT MIN(idorga) AS idorga, nomorga, MAX(trigrorganisme) AS trigrorganisme
                 FROM organisme WHERE idorga > 0 AND TRIM(nomorga) <> ''
                 GROUP BY nomorga ORDER BY nomorga"
            )->fetchAll();
            $inspecteurs = $db->query(
                "SELECT idinspecteur, categorie,
                        TRIM(CONCAT(COALESCE(preninspect,''),' ',COALESCE(nominspecteur,''))) AS nom,
                        trigr_inspecteur
                 FROM inspecteur ORDER BY nominspecteur, preninspect"
            )->fetchAll();
            $domaines = $db->query(
                "SELECT iddomaine, nomdomaine, libel_domaine FROM domaine ORDER BY nomdomaine"
            )->fetchAll();
            $reglements = $db->query(
                "SELECT r.idreglement, r.code_reglement, r.libelle_reglement, r.iddomaine, d.nomdomaine
                 FROM reglement r LEFT JOIN domaine d ON d.iddomaine = r.iddomaine
                 ORDER BY d.nomdomaine, r.code_reglement"
            )->fetchAll();
            $types_orga = $db->query("SELECT idtypeorga, nomtypeorg FROM type_organisme ORDER BY nomtypeorg")->fetchAll();
            $sites = $db->query("SELECT idsite, indicateur_oaci, nomsite FROM site ORDER BY indicateur_oaci")->fetchAll();
            $ok([
                'exploitants' => $exploitants, 'inspecteurs' => $inspecteurs, 'domaines' => $domaines,
                'reglements' => $reglements, 'types_orga' => $types_orga, 'sites' => $sites,
            ]);
            break;

        // ----------------------------------------------------------------
        // Numero d'audit auto-genere (apercu pour le formulaire de declenchement)
        case 'next_num':
            $type = trim($_POST['type_activite'] ?? '');
            if (!in_array($type, AUDIT_TYPES, true)) { $type = 'audit'; }
            $ok(['num_audit' => gen_num_audit($db, $type)]);
            break;

        // ----------------------------------------------------------------
        // Domaines sur lesquels un inspecteur est habilite (avec statut d'expiration)
        case 'insp_domaines':
            $iid = (int) ($_POST['idinspecteur'] ?? 0);
            if ($iid <= 0) { $ok(['domaines' => []]); break; }
            // Habilitations actives ET expirees (on affiche tout, on marque les expires)
            $stD = $db->prepare(
                "SELECT h.iddomaine, d.nomdomaine, d.libel_domaine,
                        MAX(h.date_expiration) AS date_expiration,
                        CASE WHEN MAX(h.date_expiration) < CURDATE() THEN 1 ELSE 0 END AS est_expire
                 FROM habilitation h
                 JOIN domaine d ON d.iddomaine = h.iddomaine
                 WHERE h.idinspecteur = ?
                 GROUP BY h.iddomaine, d.nomdomaine, d.libel_domaine
                 ORDER BY est_expire ASC, d.nomdomaine"
            );
            $stD->execute([$iid]);
            $domaines = $stD->fetchAll();
            $ok(['domaines' => $domaines]);
            break;

        // ----------------------------------------------------------------
        // Reglements disponibles pour un domaine donne
        case 'reglements_domaine':
            $iddom = (int) ($_POST['iddomaine'] ?? 0);
            if ($iddom <= 0) { $ok(['reglements' => []]); break; }
            $stR = $db->prepare(
                "SELECT r.idreglement, r.code_reglement, r.libelle_reglement
                 FROM reglement r
                 WHERE r.iddomaine = ?
                 ORDER BY r.code_reglement"
            );
            $stR->execute([$iddom]);
            $regs = $stR->fetchAll();
            // Si aucun reglement pour ce domaine, retourner tous
            if (empty($regs)) {
                $stR2 = $db->query(
                    "SELECT r.idreglement, r.code_reglement, r.libelle_reglement,
                            d.nomdomaine
                     FROM reglement r
                     LEFT JOIN domaine d ON d.iddomaine = r.iddomaine
                     ORDER BY d.nomdomaine, r.code_reglement"
                );
                $regs = $stR2->fetchAll();
            }
            $ok(['reglements' => $regs]);
            break;
            $st = $db->prepare(
                "SELECT h.iddomaine, d.nomdomaine, d.libel_domaine,
                        MAX(h.date_expiration) AS date_expiration,
                        CASE WHEN MAX(h.date_expiration) < CURDATE() THEN 1 ELSE 0 END AS expired
                 FROM habilitation h
                 JOIN domaine d ON d.iddomaine = h.iddomaine
                 WHERE h.idinspecteur = ?
                 GROUP BY h.iddomaine, d.nomdomaine, d.libel_domaine
                 ORDER BY d.nomdomaine"
            );
            $st->execute([$iid]);
            $ok(['data' => $st->fetchAll()]);
            break;

        // ----------------------------------------------------------------
        case 'get':
            $id = (int) ($_POST['idaudit'] ?? 0);
            // Recuperer l'audit avec toutes les jointures utiles
            $st = $db->prepare(
                "SELECT a.*,
                        o.nomorga, o.trigrorganisme,
                        TRIM(CONCAT(COALESCE(r.preninspect,''),' ',COALESCE(r.nominspecteur,''))) AS responsable,
                        TRIM(CONCAT(COALESCE(c.preninspect,''),' ',COALESCE(c.nominspecteur,''))) AS chef,
                        s.indicateur_oaci, s.nomsite AS nom_site_oaci
                 FROM audit a
                 LEFT JOIN organisme  o ON o.idorga       = a.idorga
                 LEFT JOIN inspecteur r ON r.idinspecteur = a.idresponsable_audit
                 LEFT JOIN inspecteur c ON c.idinspecteur = a.idchef_inspecteur
                 LEFT JOIN site s       ON s.idsite       = a.idsite
                 WHERE a.idaudit = ?"
            );
            $st->execute([$id]);
            $a = $st->fetch();
            if (!$a) { $fail('Audit introuvable.'); break; }
            // Equipe avec noms + domaines + sous-domaines
            $stEq = $db->prepare(
                "SELECT ae.idequipe, ae.idinspecteur, ae.iddomaine, ae.est_responsable,
                        TRIM(CONCAT(COALESCE(i.preninspect,''),' ',COALESCE(i.nominspecteur,''))) AS nom,
                        i.trigr_inspecteur, i.mailinspect,
                        d.nomdomaine, d.libel_domaine
                 FROM audit_equipe ae
                 JOIN inspecteur i ON i.idinspecteur = ae.idinspecteur
                 LEFT JOIN domaine d ON d.iddomaine  = ae.iddomaine
                 WHERE ae.idaudit = ?
                 ORDER BY ae.est_responsable DESC, i.nominspecteur"
            );
            $stEq->execute([$id]);
            $equipeRows = $stEq->fetchAll();
            // Reglements avec libelles + domaine
            $stRg = $db->prepare(
                "SELECT ar.idreglement, r.code_reglement, r.libelle_reglement,
                        d.nomdomaine AS domaine_reglement, ar.idequipe
                 FROM audit_reglement ar
                 JOIN reglement r ON r.idreglement = ar.idreglement
                 LEFT JOIN domaine d ON d.iddomaine = r.iddomaine
                 WHERE ar.idaudit = ?
                 ORDER BY d.nomdomaine, r.code_reglement"
            );
            $stRg->execute([$id]);
            $reglements = $stRg->fetchAll();
            // IDs seuls pour la compatibilite avec la modale d'edition
            $reglementIds = array_map(fn($r) => (int) $r['idreglement'], $reglements);
            $ok(['data' => $a, 'equipe' => $equipeRows, 'reglements' => $reglementIds, 'reglements_detail' => $reglements]);
            break;

        // ----------------------------------------------------------------
        case 'stats':
            $uid  = (int) ($_SESSION['user_id'] ?? 0);
            $role = $_SESSION['user']['role'] ?? '';
            // Resoudre idinspecteur
            $myInsp = null;
            if (!in_array($role, ['admin', 'chef_inspecteur', 'consultant'], true)) {
                $stI = $db->prepare("SELECT idinspecteur FROM inspecteur WHERE iduser = ? LIMIT 1");
                $stI->execute([$uid]);
                $rowI = $stI->fetch();
                $myInsp = $rowI ? (int) $rowI['idinspecteur'] : null;
            }
            $where = ''; $params = [];
            if ($role === 'inspecteur' && $myInsp !== null) {
                $where = "WHERE a.idaudit IN (SELECT ae.idaudit FROM audit_equipe ae WHERE ae.idinspecteur = ?)
                             OR a.idresponsable_audit = ?";
                $params = [$myInsp, $myInsp];
            }
            $row = $db->execute(
                "SELECT
                    COUNT(*)              AS total,
                    SUM(a.statut = 1)     AS planifies,
                    SUM(a.statut = 2)     AS reportes,
                    SUM(a.statut = 3)     AS effectues,
                    SUM(a.statut = 4)     AS suspendus,
                    SUM(a.est_ferme = 1)  AS fermes,
                    SUM(a.statut = 5)     AS surveiller
                 FROM audit a $where",
                $params
            )->fetch();
            foreach ($row as $k => $v) { $row[$k] = (int) $v; }
            // Taux d'execution
            $total = $row['total'] ?: 1;
            $row['taux_execution'] = round(($row['effectues'] / $total) * 100, 1);
            $row['taux_planif']    = round((($row['planifies'] + $row['effectues']) / $total) * 100, 1);
            // Evolution par annee (date_previsionnelle)
            $stAnn = $db->execute(
                "SELECT YEAR(a.date_previsionnelle) AS annee,
                        SUM(a.statut=1) AS planifies, SUM(a.statut=2) AS reportes,
                        SUM(a.statut=3) AS effectues, SUM(a.statut=4) AS suspendus,
                        COUNT(*) AS total
                 FROM audit a " . ($where ? "WHERE " . ltrim($where, 'WHERE ') : '') . "
                 " . ($where ? "AND" : "WHERE") . " a.date_previsionnelle IS NOT NULL AND YEAR(a.date_previsionnelle) > 2000
                 GROUP BY YEAR(a.date_previsionnelle) ORDER BY annee DESC LIMIT 5",
                $params
            )->fetchAll();
            // Evolution par mois (annee courante)
            $stMois = $db->execute(
                "SELECT MONTH(a.date_previsionnelle) AS mois,
                        SUM(a.statut=1) AS planifies, SUM(a.statut=2) AS reportes,
                        SUM(a.statut=3) AS effectues, COUNT(*) AS total
                 FROM audit a " . ($where ? "WHERE " . ltrim($where, 'WHERE ') . " AND" : "WHERE") . "
                 YEAR(a.date_previsionnelle) = YEAR(CURDATE())
                   AND a.date_previsionnelle IS NOT NULL
                 GROUP BY MONTH(a.date_previsionnelle) ORDER BY mois",
                $params
            )->fetchAll();
            $ok(['stats' => $row, 'par_annee' => $stAnn, 'par_mois' => $stMois]);
            break;
            $eq = $db->prepare("SELECT idinspecteur, iddomaine, est_responsable FROM audit_equipe WHERE idaudit = ? ORDER BY idequipe");
            $eq->execute([$id]);
            $rg = $db->prepare("SELECT idreglement FROM audit_reglement WHERE idaudit = ?");
            $rg->execute([$id]);
            $regs = [];
            foreach ($rg->fetchAll() as $r) { $regs[] = (int) $r['idreglement']; }
            $ok(['data' => $a, 'equipe' => $eq->fetchAll(), 'reglements' => $regs]);
            break;

        // ----------------------------------------------------------------
        case 'check_num':
            $num = trim($_POST['num_audit'] ?? '');
            $exc = (int) ($_POST['idaudit'] ?? 0);
            if ($num === '') { $ok(['exists' => false]); break; }
            $st = $db->prepare("SELECT idaudit FROM audit WHERE LOWER(num_audit) = LOWER(?) AND idaudit <> ?");
            $st->execute([$num, $exc]);
            $ok(['exists' => (bool) $st->fetch()]);
            break;

        // ----------------------------------------------------------------
        case 'create':
        case 'update':
            $isUpdate = ($action === 'update');
            $id       = (int) ($_POST['idaudit'] ?? 0);

            $num   = trim($_POST['num_audit'] ?? '');
            $type  = trim($_POST['type_activite'] ?? '');
            $cadre = trim($_POST['cadre'] ?? '');
            $idorga = (int) ($_POST['idorga'] ?? 0);
            $idtypeorga = (int) ($_POST['idtypeorga'] ?? 0);
            $idsite = (int) ($_POST['idsite'] ?? 0);
            $site  = trim($_POST['site_inspection'] ?? '');
            $typeOp = trim($_POST['type_activite_operateur'] ?? '');
            $resp  = (int) ($_POST['idresponsable_audit'] ?? 0);
            $chef  = (int) ($_POST['idchef_inspecteur'] ?? 0);
            $statut = (int) ($_POST['statut'] ?? 1);
            $estFerme = (int) ($_POST['est_ferme'] ?? 0) === 1 ? 1 : 0;
            $delai = trim($_POST['delai_execution'] ?? '');
            $delai = ($delai === '') ? null : (int) $delai;
            $autoNum  = (int) ($_POST['auto_num'] ?? 0) === 1;
            $notifMail = (int) ($_POST['notif_mail'] ?? 1) === 1 ? 1 : 0;

            if (!in_array($type, AUDIT_TYPES, true))  { $fail('Type d\'activite invalide.'); break; }
            if (!in_array($cadre, AUDIT_CADRES, true)) { $fail('Cadre invalide.'); break; }

            // Numero : auto-genere (declenchement) ou saisi (modale existante)
            if ($autoNum && !$isUpdate) { $num = gen_num_audit($db, $type); }

            // Site : par identifiant (declenchement) ou par texte (modale existante)
            if ($idsite > 0) {
                $stS = $db->prepare("SELECT nomsite FROM site WHERE idsite = ?");
                $stS->execute([$idsite]);
                $rowS = $stS->fetch();
                if (!$rowS) { $fail('Site inconnu. Merci de le re-selectionner.'); break; }
                $site = $rowS['nomsite'];
            }
            // Type d'activite operateur : par identifiant (type_organisme) ou texte
            $idtypeorgaVal = null;
            if ($idtypeorga > 0) {
                $stTO = $db->prepare("SELECT nomtypeorg FROM type_organisme WHERE idtypeorga = ?");
                $stTO->execute([$idtypeorga]);
                $rowTO = $stTO->fetch();
                if (!$rowTO) { $fail('Type d\'organisme inconnu. Merci de le re-selectionner.'); break; }
                $typeOp = $rowTO['nomtypeorg'];
                $idtypeorgaVal = $idtypeorga;
            }
            $idsiteVal = ($idsite > 0) ? $idsite : null;

            // idchef_inspecteur n'est plus exploite par l'application : on le
            // renseigne avec le responsable d'audit pour satisfaire la contrainte
            // de la table, mais il n'a plus de signification metier.
            $chef = $resp;

            // Validation
            if ($num === '' || mb_strlen($num) > 50) { $fail('Le numero de l\'audit est requis (50 caracteres maximum).'); break; }
            if ($idorga <= 0) { $fail('Veuillez choisir l\'exploitant concerne.'); break; }
            if ($site === '' || mb_strlen($site) > 100) { $fail('Veuillez choisir le site d\'inspection.'); break; }
            if ($resp <= 0) { $fail('Veuillez choisir le responsable de l\'audit.'); break; }
            if ($statut < 1 || $statut > 5) { $statut = 1; }

            // References existantes
            $chk = $db->prepare("SELECT idorga FROM organisme WHERE idorga = ?");
            $chk->execute([$idorga]);
            if (!$chk->fetch()) { $fail('Exploitant inconnu. Merci de le re-selectionner.'); break; }
            $c = $db->prepare("SELECT idinspecteur FROM inspecteur WHERE idinspecteur = ?");
            $c->execute([$resp]);
            if (!$c->fetch()) { $fail('Responsable d\'audit inconnu. Merci de le re-selectionner.'); break; }

            // Doublon de numero
            $stDup = $db->prepare("SELECT idaudit FROM audit WHERE LOWER(num_audit) = LOWER(?) AND idaudit <> ?");
            $stDup->execute([$num, $id]);
            if ($stDup->fetch()) { $fail('Un audit portant ce numero existe deja.'); break; }

            $dprev = nd($_POST['date_previsionnelle'] ?? '');
            $dreal = nd($_POST['date_realisation'] ?? '');
            $drap  = nd($_POST['date_delivrance_rapport'] ?? '');
            $dnotif = nd($_POST['date_notification'] ?? '');
            $dferm = nd($_POST['date_fermeture'] ?? '');
            if (!$estFerme) { $dferm = null; }

            // Equipe d'inspecteurs par domaine (tableaux paralleles).
            // Reglements : soit par ligne (eq_regs_json, declenchement), soit a plat (modale).
            $eqIns = $_POST['eq_inspecteur'] ?? [];
            $eqDom = $_POST['eq_domaine'] ?? [];
            $eqRes = $_POST['eq_resp'] ?? [];
            $regs  = $_POST['reglements'] ?? [];
            if (!is_array($eqIns)) { $eqIns = []; }
            if (!is_array($regs))  { $regs = []; }

            $perLineRegs = isset($_POST['eq_regs_json']);
            $eqRegs = [];
            if ($perLineRegs) {
                $tmp = json_decode((string) $_POST['eq_regs_json'], true);
                if (is_array($tmp)) { $eqRegs = $tmp; }
            }

            $equipe = [];
            foreach ($eqIns as $k => $iv) {
                $iiv = (int) $iv;
                $idv = (int) ($eqDom[$k] ?? 0);
                if ($iiv <= 0 || $idv <= 0) { continue; }
                // Responsable : flag explicite (modale) ou inspecteur = responsable de l'audit
                $rv = ((isset($eqRes[$k]) && (string) $eqRes[$k] === '1') || $iiv === $resp) ? 1 : 0;
                $lineRegs = [];
                if (isset($eqRegs[$k]) && is_array($eqRegs[$k])) {
                    foreach ($eqRegs[$k] as $rr) { $rr = (int) $rr; if ($rr > 0) { $lineRegs[$rr] = true; } }
                }
                $equipe[] = ['insp' => $iiv, 'dom' => $idv, 'resp' => $rv, 'regs' => array_keys($lineRegs)];
            }
            // Reglements a plat (chemin modale uniquement)
            $regIds = [];
            foreach ($regs as $rv) { $rv = (int) $rv; if ($rv > 0) { $regIds[$rv] = true; } }
            $regIds = array_keys($regIds);

            // Controle serveur : en modification, on est moins strict (habilitations peuvent etre expirees)
            // On avertit mais on n'empeche pas si c'est une modification (isUpdate=true)
            foreach ($equipe as $e) {
                $hb = $db->prepare("SELECT MAX(date_expiration) FROM habilitation WHERE idinspecteur = ? AND iddomaine = ?");
                $hb->execute([$e['insp'], $e['dom']]);
                $de = $hb->fetchColumn();
                if (!$isUpdate) {
                    // En creation : strict
                    if ($de === false || $de === null) {
                        $fail('Un inspecteur de l\'equipe n\'est pas habilite sur le domaine selectionne.'); break 2;
                    }
                    if ($de < date('Y-m-d')) {
                        $fail('Habilitation expiree pour un inspecteur sur le domaine selectionne.'); break 2;
                    }
                }
                // En modification : on accepte meme si habilitation absente ou expiree
                // (l'inspecteur a pu etre affecte avant que l'habilitation expire)
            }

            // Gerer eq_reglements_csv (format CSV par inspecteur, separe par |)
            if (!empty($_POST['eq_reglements_csv'])) {
                $csvParts = explode('|', (string) $_POST['eq_reglements_csv']);
                // Reconstruire eq_reglements au format attendu
                foreach ($equipe as $idx => $e) {
                    $parts = isset($csvParts[$idx]) ? explode(',', $csvParts[$idx]) : [];
                    $equipe[$idx]['regs'] = array_filter(array_map('intval', $parts));
                }
            }

            $db->beginTransaction();
            try {
                if (!$isUpdate) {
                    $st = $db->prepare(
                        "INSERT INTO audit
                            (num_audit, type_activite, cadre, idorga, site_inspection, idsite, date_previsionnelle,
                             type_activite_operateur, idtypeorga, idresponsable_audit, idchef_inspecteur, statut, notif_mail,
                             date_realisation, date_delivrance_rapport, delai_execution, date_notification,
                             est_ferme, date_fermeture)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
                    );
                    $st->execute([$num, $type, $cadre, $idorga, $site, $idsiteVal, $dprev, $typeOp, $idtypeorgaVal, $resp, $chef, $statut, $notifMail,
                                  $dreal, $drap, $delai, $dnotif, $estFerme, $dferm]);
                    $auditId = (int) $db->lastInsertId();
                } else {
                    if ($id <= 0) { $db->rollBack(); $fail('Audit introuvable.'); break; }
                    $stG = $db->prepare("SELECT idaudit FROM audit WHERE idaudit = ?");
                    $stG->execute([$id]);
                    if (!$stG->fetch()) { $db->rollBack(); $fail('Audit introuvable.'); break; }
                    $st = $db->prepare(
                        "UPDATE audit SET
                            num_audit = ?, type_activite = ?, cadre = ?, idorga = ?, site_inspection = ?, idsite = ?,
                            date_previsionnelle = ?, type_activite_operateur = ?, idtypeorga = ?, idresponsable_audit = ?,
                            idchef_inspecteur = ?, statut = ?, notif_mail = ?, date_realisation = ?, date_delivrance_rapport = ?,
                            delai_execution = ?, date_notification = ?, est_ferme = ?, date_fermeture = ?
                         WHERE idaudit = ?"
                    );
                    $st->execute([$num, $type, $cadre, $idorga, $site, $idsiteVal, $dprev, $typeOp, $idtypeorgaVal, $resp, $chef, $statut, $notifMail,
                                  $dreal, $drap, $delai, $dnotif, $estFerme, $dferm, $id]);
                    $auditId = $id;
                    $db->prepare("DELETE FROM audit_equipe    WHERE idaudit = ?")->execute([$auditId]);
                    $db->prepare("DELETE FROM audit_reglement WHERE idaudit = ?")->execute([$auditId]);
                }

                if ($equipe) {
                    $insE = $db->prepare("INSERT INTO audit_equipe (idaudit, idinspecteur, iddomaine, est_responsable) VALUES (?,?,?,?)");
                    $insR = $db->prepare("INSERT INTO audit_reglement (idaudit, idequipe, idreglement) VALUES (?,?,?)");
                    foreach ($equipe as $e) {
                        $insE->execute([$auditId, $e['insp'], $e['dom'], $e['resp']]);
                        $eqId = (int) $db->lastInsertId();
                        if ($perLineRegs) {
                            foreach ($e['regs'] as $rid) { $insR->execute([$auditId, $eqId, $rid]); }
                        }
                    }
                }
                // Reglements a plat (modale) : non rattaches a une ligne d'equipe
                if (!$perLineRegs && $regIds) {
                    $insRf = $db->prepare("INSERT INTO audit_reglement (idaudit, idequipe, idreglement) VALUES (?, NULL, ?)");
                    foreach ($regIds as $rid) { $insRf->execute([$auditId, $rid]); }
                }

                $db->commit();
            } catch (Throwable $e) {
                $db->rollBack();
                throw $e;
            }

            // Rapport sur l'equipe (ligne inserees vs lignes du POST ignorees par le controle)
            $nbInserted   = count($equipe);
            $nbSubmitted  = count(array_filter(
                array_keys((array) ($eqIns ?? [])),
                fn($k) => ((int) ($eqIns[$k] ?? 0)) > 0 && ((int) ($eqDom[$k] ?? 0)) > 0
            ));
            $nbIgnored = max(0, $nbSubmitted - $nbInserted);
            $equipeMsg = '';
            if ($nbInserted > 0 && $nbIgnored > 0) {
                $equipeMsg = $nbInserted . ' inspecteur(s)-domaine(s) insere(s). ' . $nbIgnored . ' ligne(s) ignoree(s) (habilitation expiree ou invalide).';
            } elseif ($nbInserted === 0 && $nbSubmitted > 0) {
                $equipeMsg = 'Attention : aucune ligne d\'equipe n\'a ete inseree (habilitations invalides ou expirees).';
            }

            Audit::log($isUpdate ? 'update' : 'create', 'audits', ($isUpdate ? 'Modification' : 'Creation') . ' audit #' . $auditId . ' (' . $num . ')');

            // ---- Notifications par mail (si notif_mail=1 et equipe inseree) ----
            $mailsSent = 0; $mailsFailed = 0;
            if ($notifMail && !$isUpdate && $nbInserted > 0) {

                // Mois en francais a partir de la date previsionnelle
                $moisFr = ['', 'janvier','fevrier','mars','avril','mai','juin',
                           'juillet','aout','septembre','octobre','novembre','decembre'];
                $moisAnnee = '';
                if ($dprev) {
                    $dt = \DateTime::createFromFormat('Y-m-d', $dprev);
                    if ($dt) { $moisAnnee = $moisFr[(int) $dt->format('n')] . ' ' . $dt->format('Y'); }
                }
                if ($moisAnnee === '') { $moisAnnee = $moisFr[(int) date('n')] . ' ' . date('Y'); }

                // Signataire : inspecteur lie a l'utilisateur connecte (nom ANAC)
                // Si l'utilisateur connecte n'est pas un inspecteur, on prend le nom du user.
                $uid = (int) ($_SESSION['user_id'] ?? 0);
                $stCI = $db->prepare(
                    "SELECT TRIM(CONCAT(COALESCE(preninspect,''),' ',COALESCE(nominspecteur,''))) AS nomci
                     FROM inspecteur WHERE iduser = ? LIMIT 1"
                );
                $stCI->execute([$uid]);
                $rowCI = $stCI->fetch();
                if ($rowCI && trim($rowCI['nomci']) !== '') {
                    $ciSig = trim($rowCI['nomci']);
                } else {
                    $ciSig = trim(($_SESSION['user']['prenom'] ?? '') . ' ' . ($_SESSION['user']['nom'] ?? ''));
                }

                // Libelles de type et cadre
                $typeLabels = [
                    'audit' => 'Audit', 'inspection_programmee' => 'Inspection programmee',
                    'inspection_non_programmee' => 'Inspection non programmee',
                    'demonstration' => 'Demonstration', 'test' => 'Test', 'investigation' => 'Investigation',
                ];
                $cadreLabels = [
                    'certification' => 'Certification', 'homologation' => 'Homologation',
                    'reconnaissance' => 'Reconnaissance', 'renouvellement' => 'Renouvellement',
                    'surveillance_continue' => 'Surveillance continue',
                    'traitement_evenement' => "Traitement d'un evenement",
                    'fermeture_provisoire' => 'Fermeture provisoire',
                    'fermeture_definitive' => 'Fermeture definitive',
                    'delivrance_autorisation' => "Delivrance d'une autorisation",
                ];

                // Nom de l'operateur
                $stOrg = $db->prepare("SELECT nomorga FROM organisme WHERE idorga = ?");
                $stOrg->execute([$idorga]);
                $rowOrg = $stOrg->fetch();
                $nomOrga = $rowOrg['nomorga'] ?? '';

                // Equipe complete : regrouper par inspecteur (cumuler les domaines)
                $equipeMap = [];
                $stInsp = $db->prepare(
                    "SELECT i.idinspecteur, i.nominspecteur, i.preninspect, i.mailinspect,
                            d.nomdomaine, e.est_responsable
                     FROM audit_equipe e
                     JOIN inspecteur i ON i.idinspecteur = e.idinspecteur
                     JOIN domaine d    ON d.iddomaine    = e.iddomaine
                     WHERE e.idaudit = ?
                     ORDER BY e.est_responsable DESC, i.nominspecteur"
                );
                $stInsp->execute([$auditId]);
                foreach ($stInsp->fetchAll() as $er) {
                    $id2 = (int) $er['idinspecteur'];
                    if (!isset($equipeMap[$id2])) {
                        $equipeMap[$id2] = [
                            'nom'      => $er['nominspecteur'],
                            'prenom'   => $er['preninspect'],
                            'email'    => $er['mailinspect'],
                            'domaines' => [],
                            'est_resp' => (int) $er['est_responsable'] === 1,
                        ];
                    }
                    $equipeMap[$id2]['domaines'][] = $er['nomdomaine'];
                }
                $equipeList = array_values($equipeMap);

                // Nom du RA pour l'afficher dans le mail
                $raNom = '';
                foreach ($equipeList as $m) {
                    if ($m['est_resp']) {
                        $raNom = trim($m['prenom'] . ' ' . $m['nom']);
                        break;
                    }
                }
                // Si le RA n'est pas dans l'equipe, recup direct
                if ($raNom === '') {
                    $stRA = $db->prepare(
                        "SELECT TRIM(CONCAT(COALESCE(preninspect,''),' ',COALESCE(nominspecteur,''))) AS nomra
                         FROM inspecteur WHERE idinspecteur = ? LIMIT 1"
                    );
                    $stRA->execute([$resp]);
                    $rowRA = $stRA->fetch();
                    if ($rowRA) { $raNom = trim($rowRA['nomra']); }
                }

                // Collecte des destinataires : tous les inspecteurs de l'equipe + le RA
                // (le RA est deja dans $equipeList s'il est dans l'equipe, sinon on l'ajoute)
                $destMap = [];
                foreach ($equipeList as $m) {
                    if (!empty($m['email'])) { $destMap[$m['email']] = $m; }
                }
                // RA : si son email n'est pas deja dans la liste, on l'ajoute
                $stRAemail = $db->prepare(
                    "SELECT i.mailinspect, i.nominspecteur, i.preninspect
                     FROM inspecteur i WHERE i.idinspecteur = ? LIMIT 1"
                );
                $stRAemail->execute([$resp]);
                $rowRAe = $stRAemail->fetch();
                if ($rowRAe && !empty($rowRAe['mailinspect']) && !isset($destMap[$rowRAe['mailinspect']])) {
                    $destMap[$rowRAe['mailinspect']] = [
                        'nom'      => $rowRAe['nominspecteur'],
                        'prenom'   => $rowRAe['preninspect'],
                        'email'    => $rowRAe['mailinspect'],
                        'domaines' => [],
                        'est_resp' => true,
                    ];
                }

                $params = [
                    'num_audit'     => $num,
                    'type_activite' => $typeLabels[$type]  ?? $type,
                    'cadre'         => $cadreLabels[$cadre] ?? $cadre,
                    'mois_annee'    => $moisAnnee,
                    'operateur'     => $nomOrga,
                    'site'          => $site,
                    'ci_nom'        => $ciSig,
                    'ra_nom'        => $raNom,
                    'equipe'        => $equipeList,
                    'agai_url'      => SITE_URL,
                ];

                $mailer = new Mailer();
                foreach ($destMap as $insp) {
                    $displayName = trim($insp['prenom'] . ' ' . $insp['nom']);
                    $sent = $mailer->sendNotifAudit(
                        $insp['email'],
                        $displayName,
                        array_merge($params, ['dest_nom' => $displayName])
                    );
                    $sent ? $mailsSent++ : $mailsFailed++;
                }
                Audit::log('mail', 'audits', 'Notifications audit #' . $auditId . ' : ' . $mailsSent . ' envoyee(s), ' . $mailsFailed . ' echec(s).');
            }

            $notifMsg = '';
            if ($notifMail && !$isUpdate) {
                if ($nbInserted === 0) {
                    $notifMsg = 'Aucun email envoye (aucun inspecteur valide dans l\'equipe).';
                } elseif ($mailsFailed > 0 && $mailsSent === 0) {
                    $notifMsg = 'Erreur : aucun email n\'a pu etre envoye (' . $mailsFailed . ' echec(s)). Verifiez les adresses mail des inspecteurs.';
                } elseif ($mailsFailed > 0) {
                    $notifMsg = $mailsSent . ' email(s) envoye(s). ' . $mailsFailed . ' echec(s) - verifiez les adresses mail.';
                } else {
                    $notifMsg = $mailsSent . ' inspecteur(s) notifie(s) par mail.';
                }
            }

            $ok([
                'message'    => $isUpdate ? 'Audit mis a jour.' : 'Audit enregistre.',
                'idaudit'    => $auditId,
                'num_audit'  => $num,
                'equipe_msg' => $equipeMsg,
                'nb_equipe'  => $nbInserted,
                'notif_msg'  => $notifMsg,
            ]);
            break;

        // ----------------------------------------------------------------
        case 'delete':
            $id = (int) ($_POST['idaudit'] ?? 0);
            if ($id <= 0) { $fail('Audit introuvable.'); break; }

            // Refus si des fiches de non-conformite sont rattachees a cet audit
            $st = $db->prepare("SELECT COUNT(*) FROM fiche_non_conformite WHERE idaudit = ?");
            $st->execute([$id]);
            if ((int) $st->fetchColumn() > 0) {
                $fail('Suppression impossible : des fiches de non-conformite sont rattachees a cet audit.');
                break;
            }

            $db->beginTransaction();
            try {
                $db->prepare("DELETE FROM audit_equipe    WHERE idaudit = ?")->execute([$id]);
                $db->prepare("DELETE FROM audit_reglement WHERE idaudit = ?")->execute([$id]);
                $db->prepare("DELETE FROM audit           WHERE idaudit = ?")->execute([$id]);
                $db->commit();
            } catch (Throwable $e) {
                $db->rollBack();
                throw $e;
            }
            Audit::log('delete', 'audits', 'Suppression audit #' . $id);
            $ok(['message' => 'Audit supprime.']);
            break;

        // ----------------------------------------------------------------
        default:
            $fail('Action inconnue.');
    }
} catch (Throwable $e) {
    error_log('audits endpoint : ' . $e->getMessage());
    $fail('Erreur technique.');
}