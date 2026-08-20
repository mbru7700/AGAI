<?php
/**
 * Endpoint AJAX : Non-Conformites (FNC)
 * Route : /api/nonconformites
 * Actions : audits_eligibles, habilitations_insp, reglements_audit, next_num_fnc,
 *           create_sousdomaine, create_reglement, create, list, get,
 *           update_suivi, delete, stats
 *
 * Securite (OWASP) :
 *  - A01 Broken Access Control : guard module + verifications par action.
 *  - A03 Injection : 100% requetes preparees (PDO), aucune concatenation.
 *  - CSRF : jeton verifie a chaque appel.
 *  - A05/A09 : les details d'exception ne sont jamais renvoyes au client.
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }

// Guard : accessible si ouverture_nc OU suivi_nc est accorde
if (!Rbac::canAccess('ouverture_nc') && !Rbac::canAccess('suivi_nc')) {
    echo json_encode(['success'=>false,'message'=>'Acces refuse.']); exit;
}

// La consultation du PDF joint s'ouvre dans un onglet/iframe : requete GET sans corps POST.
// Elle reste protegee par la session, l'habilitation du module et un identifiant numerique.
$actionBrute = (string)($_POST['action'] ?? $_GET['action'] ?? '');
if ($actionBrute !== 'serve_fiche' && !Security::validateCSRF($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success'=>false,'message'=>'CSRF invalide.']); exit;
}

$db          = Database::getInstance();
$role        = Rbac::role();
$uid         = (int)($_SESSION['user_id'] ?? 0);
$sessionRole = $_SESSION['user']['role'] ?? '';
$action = trim((string)($_POST['action'] ?? $_GET['action'] ?? ''));
$ok   = function($x=[]) { echo json_encode(['success'=>true]+$x); };
$fail = function($m)     { echo json_encode(['success'=>false,'message'=>$m]); };

// Inspecteur connecte
$myInsp = null;
$stI = $db->prepare("SELECT idinspecteur FROM inspecteur WHERE iduser = ? LIMIT 1");
$stI->execute([$uid]); $ri = $stI->fetch();
if ($ri) $myInsp = (int)$ri['idinspecteur'];

$isCI   = in_array($sessionRole, ['admin','chef_inspecteur'], true);
$isInsp = ($sessionRole === 'inspecteur');

// Acces en ecriture (creation FNC, sous-domaine, reglement) : exige ouverture_nc
$canOuverture = Rbac::canAccess('ouverture_nc');

/* ------------------------------------------------------------------
 * Pieces jointes : fiche de non-conformite signee (PDF)
 * Stockage HORS zone publique, nom aleatoire, type MIME reel verifie.
 * ------------------------------------------------------------------ */
function fnc_dir(): string
{
    $dir = dirname(__DIR__, 2) . '/storage/uploads/fnc';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    return $dir;
}

/** Enregistre le PDF recu. Retourne le nom stocke, ou null si invalide/absent. */
function fnc_save_pdf(?array $file): ?string
{
    if (!$file || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { return null; }
    if ((int)($file['size'] ?? 0) <= 0) { return null; }
    if (!is_uploaded_file((string)($file['tmp_name'] ?? ''))) { return null; }

    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf') { return null; }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if ($mime !== 'application/pdf') { return null; }

    // Nom aleatoire : aucun nom fourni par le client n'est reutilise.
    $nom = 'fnc_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.pdf';
    if (!move_uploaded_file($file['tmp_name'], fnc_dir() . '/' . $nom)) { return null; }
    return $nom;
}

/**
 * Ajoute un nouveau PDF a la suite d'un document existant.
 * Le resultat est un fichier unique ; les deux fichiers d'origine sont
 * effaces afin de ne conserver qu'un seul document par rubrique.
 *
 * @param string|null $ancien  Nom du fichier deja enregistre (peut etre vide).
 * @param string      $nouveau Nom du fichier qui vient d'etre depose.
 * @return string     Nom du fichier a enregistrer en base.
 */
function fnc_cumuler_pdf(?string $ancien, string $nouveau): string
{
    $ancien = trim((string)$ancien);
    if ($ancien === '') { return $nouveau; }

    $dir      = fnc_dir();
    $pAncien  = $dir . '/' . basename($ancien);
    $pNouveau = $dir . '/' . basename($nouveau);
    if (!is_file($pAncien)) { return $nouveau; }

    $fusion  = 'fnc_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.pdf';
    $pFusion = $dir . '/' . $fusion;

    // Ordre de lecture : le document le plus ancien d'abord
    if (PdfMerger::fusionner([$pAncien, $pNouveau], $pFusion)) {
        @unlink($pAncien);
        @unlink($pNouveau);
        return $fusion;
    }

    // Fusion impossible sur ce serveur : on conserve le dernier document depose
    error_log('nonconformites: fusion PDF impossible - ' . PdfMerger::derniereErreur());
    @unlink($pAncien);
    return $nouveau;
}

/** Supprime physiquement une piece jointe devenue inutile (optimisation disque). */
function fnc_delete_pdf(?string $nom): void
{
    $nom = trim((string)$nom);
    if ($nom === '') { return; }
    $chemin = fnc_dir() . '/' . basename($nom);
    if (is_file($chemin)) { @unlink($chemin); }
}

try { switch ($action) {

// ----------------------------------------------------------------
// Audits eligibles a l'ouverture de NC (NCNS >= 1 et pas encore toutes fiches crees)
// ----------------------------------------------------------------
case 'audits_eligibles':
    $where = "WHERE a.ncns >= 1";
    $params = [];
    // Inspecteur : uniquement ses audits (RA ou membre d'equipe)
    if ($isInsp && $myInsp !== null) {
        $where .= " AND (a.idresponsable_audit=? OR a.idaudit IN (SELECT ae.idaudit FROM audit_equipe ae WHERE ae.idinspecteur=?))";
        $params = [$myInsp, $myInsp];
    }
    $rows = $db->execute(
        "SELECT a.idaudit, a.num_audit, a.type_activite, a.cadre,
                a.date_previsionnelle, a.date_delivrance_rapport, a.date_realisation,
                o.nomorga, s.indicateur_oaci, s.ville,
                a.ncns,
                (SELECT COUNT(*) FROM fiche_non_conformite f WHERE f.idaudit = a.idaudit) AS nb_fnc_crees,
                (a.ncns - (SELECT COUNT(*) FROM fiche_non_conformite f WHERE f.idaudit = a.idaudit)) AS reste_a_creer
         FROM audit a
         LEFT JOIN organisme o ON o.idorga = a.idorga
         LEFT JOIN site      s ON s.idsite  = a.idsite
         $where
         HAVING reste_a_creer > 0
         ORDER BY a.date_previsionnelle DESC",
        $params
    )->fetchAll();
    $ok(['data' => $rows]);
    break;

// ----------------------------------------------------------------
// SET_DATE_DELIVRANCE : renseigne la date de delivrance du rapport
//   et bascule le statut de l'audit a 3 (Effectue), directement depuis
//   la modale d'ouverture des FNC. Evite de repartir dans le module Audits.
// ----------------------------------------------------------------
case 'set_date_delivrance':
    $idaudit = (int)($_POST['idaudit'] ?? 0);
    if ($idaudit <= 0) { $fail('Audit invalide.'); break; }

    // Controle d'acces : les roles en lecture seule ne modifient pas l'audit.
    if (in_array($role, ['operateur','consultant'], true)) {
        Audit::log('access_denied','nonconformites',"set_date_delivrance refuse (role $role) audit #$idaudit");
        $fail('Acces refuse : vous n\'avez pas le droit de modifier cet audit.'); break;
    }

    // Validation stricte de la date (format AAAA-MM-JJ)
    $dateDeliv = trim((string)($_POST['date_delivrance_rapport'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDeliv)) { $fail('Date de delivrance invalide.'); break; }
    // Coherence calendaire (rejette 2026-13-40, etc.)
    $dt = DateTime::createFromFormat('Y-m-d', $dateDeliv);
    if (!$dt || $dt->format('Y-m-d') !== $dateDeliv) { $fail('Date de delivrance invalide.'); break; }

    // L'audit doit exister
    $stChk = $db->prepare("SELECT idaudit, date_realisation FROM audit WHERE idaudit = ? LIMIT 1");
    $stChk->execute([$idaudit]);
    $auditRow = $stChk->fetch();
    if (!$auditRow) { $fail('Audit introuvable.'); break; }

    // Mise a jour : date de delivrance + statut 3 (Effectue).
    // Si la date de realisation est vide, on l'aligne sur la date de delivrance.
    if (empty($auditRow['date_realisation']) || $auditRow['date_realisation'] === '0000-00-00') {
        $upd = $db->prepare(
            "UPDATE audit SET date_delivrance_rapport = ?, date_realisation = ?, statut = 3 WHERE idaudit = ?"
        );
        $upd->execute([$dateDeliv, $dateDeliv, $idaudit]);
    } else {
        $upd = $db->prepare(
            "UPDATE audit SET date_delivrance_rapport = ?, statut = 3 WHERE idaudit = ?"
        );
        $upd->execute([$dateDeliv, $idaudit]);
    }

    Audit::log('update','nonconformites',"Date delivrance rapport + statut=3 pour audit #$idaudit ($dateDeliv)");
    $ok(['date_delivrance_rapport' => $dateDeliv, 'statut' => 3]);
    break;

// ----------------------------------------------------------------
// Habilitations de l'inspecteur connecte (domaines + sous-domaines)
// ----------------------------------------------------------------
case 'habilitations_insp':
    $idInsp = (int)($_POST['idinspecteur'] ?? ($myInsp ?? 0));
    if (!$idInsp) { $fail('Inspecteur non identifie.'); break; }
    // Domaines habilites
    $domaines = $db->execute(
        "SELECT DISTINCT d.iddomaine, d.nomdomaine, d.libel_domaine
         FROM habilitation h
         JOIN domaine d ON d.iddomaine = h.iddomaine
         WHERE h.idinspecteur = ? ORDER BY d.nomdomaine",
        [$idInsp]
    )->fetchAll();
    // Sous-domaines de ces domaines
    $idsDomainesArr = array_column($domaines, 'iddomaine');
    $sousdomaines = [];
    if ($idsDomainesArr) {
        $in = implode(',', array_fill(0, count($idsDomainesArr), '?'));
        $sousdomaines = $db->execute(
            "SELECT sd.idsousdomaine, sd.nom_sousdomaine, sd.iddomaine, d.nomdomaine
             FROM sous_domaine sd JOIN domaine d ON d.iddomaine = sd.iddomaine
             WHERE sd.iddomaine IN ($in) ORDER BY d.nomdomaine, sd.nom_sousdomaine",
            $idsDomainesArr
        )->fetchAll();
    }
    $ok(['domaines' => $domaines, 'sousdomaines' => $sousdomaines]);
    break;

// ----------------------------------------------------------------
// Reglements associes a un audit (depuis audit_reglement)
// ----------------------------------------------------------------
case 'reglements_audit':
    $idaudit   = (int)($_POST['idaudit']   ?? 0);
    $iddomaine = (int)($_POST['iddomaine'] ?? 0);

    // 1) Priorite : tous les reglements rattaches au DOMAINE d'inspection choisi.
    //    C'est le referentiel applicable (ex: domaine OPS -> tous les reglements OPS).
    $rows = [];
    if ($iddomaine > 0) {
        $rows = $db->execute(
            "SELECT r.idreglement, r.code_reglement, r.libelle_reglement, r.description
             FROM reglement r
             WHERE r.iddomaine = ?
             ORDER BY r.code_reglement",
            [$iddomaine]
        )->fetchAll();
    }
    // 2) A defaut : les reglements vises par l'audit.
    if (!$rows && $idaudit > 0) {
        $rows = $db->execute(
            "SELECT r.idreglement, r.code_reglement, r.libelle_reglement, r.description
             FROM audit_reglement ar
             JOIN reglement r ON r.idreglement = ar.idreglement
             WHERE ar.idaudit = ?
             ORDER BY r.code_reglement",
            [$idaudit]
        )->fetchAll();
    }
    $ok(['data' => $rows]);
    break;

// ----------------------------------------------------------------
// Prochain numero FNC auto-incremente
// ----------------------------------------------------------------
case 'next_num_fnc':
    $idaudit  = (int)($_POST['idaudit'] ?? 0);
    $iddomaine= (int)($_POST['iddomaine'] ?? 0);
    $offset   = max(0, (int)($_POST['offset'] ?? 0)); // decalage pour numerotation multi-fiches
    // Indicateur OACI du site de l'audit
    $stSite = $db->prepare(
        "SELECT s.indicateur_oaci, YEAR(a.date_previsionnelle) AS annee
         FROM audit a LEFT JOIN site s ON s.idsite=a.idsite
         WHERE a.idaudit=? LIMIT 1"
    );
    $stSite->execute([$idaudit]); $site = $stSite->fetch();
    $oaci  = $site['indicateur_oaci'] ?? 'XXXX';
    $annee = $site['annee'] ?? date('Y');
    // Abrev domaine
    $stDom = $db->prepare("SELECT nomdomaine FROM domaine WHERE iddomaine=? LIMIT 1");
    $stDom->execute([$iddomaine]); $dom = $stDom->fetch();
    $abrevDom = strtoupper($dom['nomdomaine'] ?? 'DOM');
    // Previsualisation : le numero definitif sera l'identifiant de la fiche (idfnc).
    // On anticipe le prochain identifiant afin d'afficher un numero coherent.
    $nextId = (int)$db->execute("SELECT COALESCE(MAX(idfnc),0)+1 AS n FROM fiche_non_conformite")->fetch()['n'];
    $nb = $nextId + $offset;
    $numFnc = str_pad($nb, 3, '0', STR_PAD_LEFT).'/'.$oaci.'/'.$abrevDom.'/'.$annee;
    $ok(['num_fnc' => $numFnc, 'oaci' => $oaci, 'domaine' => $abrevDom, 'annee' => $annee]);
    break;

// ----------------------------------------------------------------
// Creer un sous-domaine depuis la FNC (inspecteur habilite)
// ----------------------------------------------------------------
case 'create_sousdomaine':
    if (!$canOuverture) { $fail('Acces refuse.'); break; }
    $nom   = trim(strip_tags($_POST['nom_sousdomaine'] ?? ''));
    $idDom = (int)($_POST['iddomaine'] ?? 0);
    if ($nom === '' || mb_strlen($nom) > 255) { $fail('Nom du sous-domaine requis (255 car. max).'); break; }
    if ($idDom <= 0) { $fail('Domaine parent requis.'); break; }
    // Verifier que le domaine existe
    $stD = $db->prepare("SELECT iddomaine FROM domaine WHERE iddomaine=? LIMIT 1");
    $stD->execute([$idDom]);
    if (!$stD->fetch()) { $fail('Domaine introuvable.'); break; }
    // Eviter les doublons -> renvoyer l'existant
    // Comparaison normalisee : casse, espaces de bord et espaces multiples ignores
    $stChk = $db->prepare(
        "SELECT idsousdomaine FROM sous_domaine
         WHERE iddomaine = ?
           AND LOWER(REPLACE(REPLACE(TRIM(nom_sousdomaine),'  ',' '),'  ',' ')) = LOWER(REPLACE(REPLACE(TRIM(?),'  ',' '),'  ',' '))
         LIMIT 1"
    );
    $stChk->execute([$idDom, $nom]); $exist = $stChk->fetch();
    if ($exist) {
        $ok(['idsousdomaine' => (int)$exist['idsousdomaine'], 'nom_sousdomaine' => $nom, 'iddomaine' => $idDom, 'message' => 'Sous-domaine deja existant - ajoute a la selection.']);
        break;
    }
    $db->prepare("INSERT INTO sous_domaine (iddomaine, nom_sousdomaine) VALUES (?,?)")->execute([$idDom, $nom]);
    $newId = (int)$db->lastInsertId();
    Audit::log('create','nonconformites',"Nouveau sous-domaine #$newId ($nom) via FNC (domaine #$idDom)");
    $ok(['idsousdomaine' => $newId, 'nom_sousdomaine' => $nom, 'iddomaine' => $idDom, 'message' => 'Sous-domaine cree.']);
    break;

// ----------------------------------------------------------------
// Creer un reglement depuis la FNC (insertion reelle en base)
// ----------------------------------------------------------------
case 'create_reglement':
    if (!$canOuverture) { $fail('Acces refuse.'); break; }
    $code  = trim(strip_tags($_POST['code_reglement']    ?? ''));
    $lib   = trim(strip_tags($_POST['libelle_reglement'] ?? ''));
    $idDom = (int)($_POST['iddomaine'] ?? 0);
    if ($code === '' || mb_strlen($code) > 255) { $fail('Code/reference du reglement requis (255 car. max).'); break; }
    if ($lib  === '' || mb_strlen($lib) > 2000)  { $fail('Libelle du reglement requis (2000 car. max).'); break; }
    if ($idDom <= 0) { $fail('Veuillez d\'abord choisir un domaine pour rattacher le reglement.'); break; }
    // Verifier que le domaine existe (FK reglement.iddomaine)
    $stD = $db->prepare("SELECT iddomaine FROM domaine WHERE iddomaine=? LIMIT 1");
    $stD->execute([$idDom]);
    if (!$stD->fetch()) { $fail('Domaine introuvable.'); break; }
    // Eviter les doublons (meme code dans le meme domaine) -> renvoyer l'existant
    // Comparaison normalisee : casse, espaces de bord et espaces multiples ignores
    $stChk = $db->prepare(
        "SELECT idreglement FROM reglement
         WHERE iddomaine = ?
           AND LOWER(REPLACE(REPLACE(TRIM(code_reglement),'  ',' '),'  ',' ')) = LOWER(REPLACE(REPLACE(TRIM(?),'  ',' '),'  ',' '))
         LIMIT 1"
    );
    $stChk->execute([$idDom, $code]); $exist = $stChk->fetch();
    if ($exist) {
        $ok(['idreglement' => (int)$exist['idreglement'], 'code_reglement' => $code, 'libelle_reglement' => $lib, 'message' => 'Reglement deja existant - ajoute a la selection.']);
        break;
    }
    $db->prepare("INSERT INTO reglement (iddomaine, code_reglement, libelle_reglement, description) VALUES (?,?,?,?)")
       ->execute([$idDom, $code, $lib, $lib]);
    $newReg = (int)$db->lastInsertId();
    Audit::log('create','nonconformites',"Nouveau reglement #$newReg ($code) via FNC (domaine #$idDom)");
    $ok(['idreglement' => $newReg, 'code_reglement' => $code, 'libelle_reglement' => $lib, 'message' => 'Reglement cree.']);
    break;

// ----------------------------------------------------------------
// Creer une FNC (fiche de non-conformite)
// ----------------------------------------------------------------
case 'create':
    if (!$canOuverture) { $fail('Acces refuse.'); break; }
    if (!$myInsp && !$isCI) { $fail('Seul un inspecteur peut creer une FNC.'); break; }
    $idaudit   = (int)($_POST['idaudit']   ?? 0);
    $iddomaine = (int)($_POST['iddomaine'] ?? 0);
    $categorie = Security::cleanInput($_POST['categorie'] ?? '');
    // Le numero definitif est construit APRES insertion, a partir de l'idfnc :
    // idfnc / indicateur OACI / domaine / annee. Cela garantit l'unicite absolue,
    // meme si plusieurs fiches sont creees simultanement (plus de doublons possibles).
    if (!$idaudit || !$iddomaine || !$categorie) {
        $fail('Champs obligatoires manquants (audit, domaine, categorie).'); break;
    }
    // Numero temporaire unique : il satisfait l'index UNIQUE le temps de l'insertion.
    $num_fnc = 'TMP-' . bin2hex(random_bytes(8));
    // Categorie sur liste blanche
    // Une observation ne donne pas lieu a une fiche de non-conformite.
    if ($categorie === 'observation') {
        $fail('La categorie Observation ne genere pas de fiche de non-conformite.'); break;
    }
    if (!in_array($categorie, ['critique','majeur','mineur'], true)) {
        $fail('Categorie invalide.'); break;
    }
    // Evaluation des risques de securite (OACI Doc 9859) : probabilite (1..5),
    // gravite (A..E), indice (ex 5A). La categorie DOIT decouler de la matrice.
    $probabilite = Security::cleanInput($_POST['probabilite'] ?? '');
    $gravite     = strtoupper(Security::cleanInput($_POST['gravite'] ?? ''));
    $indiceRisque= strtoupper(Security::cleanInput($_POST['indice_risque'] ?? ''));
    if (!in_array($probabilite, ['1','2','3','4','5'], true)) { $fail('Probabilite invalide.'); break; }
    if (!in_array($gravite, ['A','B','C','D','E'], true)) { $fail('Gravite invalide.'); break; }
    $indiceCalc = $probabilite . $gravite;
    if ($indiceRisque !== $indiceCalc) { $indiceRisque = $indiceCalc; }
    // Verification serveur : la categorie transmise correspond bien a la matrice OACI.
    $matriceOaci = [
        'critique' => ['5A','5B','5C','4A','4B','3A'],
        'majeur'   => ['5D','5E','4C','4D','4E','3B','3C','3D','2A','2B','2C','1A'],
        'mineur'   => ['3E','2D','2E','1B','1C','1D','1E'],
    ];
    $catAttendue = null;
    foreach ($matriceOaci as $cat => $indices) {
        if (in_array($indiceRisque, $indices, true)) { $catAttendue = $cat; break; }
    }
    if ($catAttendue === null) { $fail('Indice de risque invalide (' . htmlspecialchars($indiceRisque, ENT_QUOTES, 'UTF-8') . ').'); break; }
    // On se fie a la matrice cote serveur, pas a la valeur cliente (securite).
    $categorie = $catAttendue;
    // Justifications OACI (le "pourquoi"). On les reconstruit cote serveur a partir
    // des valeurs choisies pour garantir un texte officiel et coherent.
    $PROBA_JUSTIF = [
        '5' => 'Frequent : susceptible de se produire de nombreuses fois (s\'est produit frequemment).',
        '4' => 'Occasionnel : susceptible de se produire parfois (ne s\'est pas produit frequemment).',
        '3' => 'Faible : peu susceptible de se produire, mais possible (s\'est produit rarement).',
        '2' => 'Improbable : tres peu susceptible de se produire (on n\'a pas connaissance que cela se soit produit).',
        '1' => 'Extremement improbable : il est presque inconcevable que l\'evenement se produise.',
    ];
    $GRAVITE_JUSTIF = [
        'A' => 'Catastrophique : aeronef/equipement detruit ; multiples deces.',
        'B' => 'Dangereux : importante reduction des marges de securite, detresse physique ou charge de travail telle que les operateurs ne peuvent accomplir leurs taches avec exactitude ou completude.',
        'C' => 'Majeur : reduction des marges de securite, reduction de la capacite des operateurs, incident grave, personnes blessees.',
        'D' => 'Mineur : nuisance, limites de fonctionnement, recours a des procedures d\'urgence, incident mineur.',
        'E' => 'Negligeable : peu de consequences.',
    ];
    $justifProba   = trim(strip_tags((string)($_POST['justif_probabilite'] ?? '')));
    $justifGravite = trim(strip_tags((string)($_POST['justif_gravite'] ?? '')));
    if ($justifProba === '')   { $justifProba   = $PROBA_JUSTIF[$probabilite] ?? ''; }
    if ($justifGravite === '') { $justifGravite = $GRAVITE_JUSTIF[$gravite] ?? ''; }
    $justifProba   = mb_substr($justifProba, 0, 255);
    $justifGravite = mb_substr($justifGravite, 0, 500);
    // Inspecteur createur : depuis la session (jamais depuis le POST seul)
    $inspCreateur = $myInsp ?? (int)($_POST['idinspecteur_createur'] ?? 0);
    if ($inspCreateur <= 0) { $fail('Votre compte n\'est pas relie a une fiche inspecteur ; creation impossible.'); break; }

    // Verifier que l'inspecteur est bien rattache a cet audit (RA ou equipe), sauf admin/CI
    if ($isInsp) {
        $stChkA = $db->prepare(
            "SELECT 1 FROM audit a
             LEFT JOIN audit_equipe ae ON ae.idaudit=a.idaudit AND ae.idinspecteur=?
             WHERE a.idaudit=? AND (a.idresponsable_audit=? OR ae.idinspecteur IS NOT NULL) LIMIT 1"
        );
        $stChkA->execute([$inspCreateur, $idaudit, $inspCreateur]);
        if (!$stChkA->fetch()) { $fail('Vous n\'etes pas planifie sur cet audit.'); break; }
    }

    // Verifier quota NCNS
    $stAudit = $db->prepare("SELECT ncns, ncr, date_delivrance_rapport, idorga, date_previsionnelle FROM audit WHERE idaudit=?");
    $stAudit->execute([$idaudit]); $aud = $stAudit->fetch();
    if (!$aud) { $fail('Audit introuvable.'); break; }
    $stNbFnc = $db->prepare("SELECT COUNT(*) FROM fiche_non_conformite WHERE idaudit=?");
    $stNbFnc->execute([$idaudit]); $nbExist = (int)$stNbFnc->fetchColumn();
    if ($nbExist >= (int)($aud['ncns'] ?? 0)) {
        $fail('Quota atteint : toutes les '.$aud['ncns'].' fiches NC ont ete creees pour cet audit.'); break;
    }
    // Dates automatiques selon categorie
    $dateEmission = Security::cleanInput($_POST['date_emission'] ?? date('Y-m-d'));
    $dateRep      = null; $dateLimite = null;
    $dateRapport  = $aud['date_delivrance_rapport'] ?? null;
    switch ($categorie) {
        case 'critique':
            $dateRep    = $dateEmission;
            $dateLimite = $dateEmission;
            break;
        case 'majeur':
            $dateRep    = $dateRapport ? date('Y-m-d', strtotime($dateRapport.' +30 days'))  : null;
            $dateLimite = $dateRapport ? date('Y-m-d', strtotime($dateRapport.' +3 months')) : null;
            break;
        case 'mineur':
            $dateRep    = $dateRapport ? date('Y-m-d', strtotime($dateRapport.' +30 days'))  : null;
            $dateLimite = $dateRapport ? date('Y-m-d', strtotime($dateRapport.' +6 months')) : null;
            break;
        case 'observation':
            $dateRep = null; $dateLimite = null;
            break;
    }
    // Delai transmission
    $delaiTrans = null;
    if (!empty($aud['date_previsionnelle']) && !empty($aud['date_delivrance_rapport'])) {
        $d1 = new DateTime($aud['date_previsionnelle']);
        $d2 = new DateTime($aud['date_delivrance_rapport']);
        $delaiTrans = (int)$d1->diff($d2)->days;
    }
    // Nettoyage texte brut : trim + strip_tags (pas htmlspecialchars qui encode &#039;)
    $clean = function($v){ return trim(strip_tags((string)$v)); };
    // Nettoyage HTML riche (Quill) : autorise quelques balises de mise en forme,
    // supprime TOUS les attributs (protection XSS) et normalise le vide.
    $cleanRich = function($v){
        $v = (string)$v;
        // Balises autorisees uniquement (mise en forme simple + listes)
        $v = strip_tags($v, '<p><br><b><strong><i><em><u><s><ol><ul><li>');
        // Supprimer tout attribut residuel (onerror, style, href, etc.)
        $v = preg_replace('/<([a-z0-9]+)[^>]*>/i', '<$1>', $v);
        // Si le contenu ne contient que du vide Quill, retourner ''
        $txt = trim(preg_replace('/&nbsp;/', ' ', strip_tags($v)));
        return $txt === '' ? '' : trim($v);
    };

    // Detection des colonnes d'evaluation des risques (compatibilite pre-migration)
    $hasRisqueCols = false;
    try {
        $ck = $db->execute("SHOW COLUMNS FROM fiche_non_conformite LIKE 'indice_risque'")->fetch();
        $hasRisqueCols = (bool)$ck;
    } catch (\Throwable $e) { $hasRisqueCols = false; }

    if ($hasRisqueCols) {
        $db->prepare(
            "INSERT INTO fiche_non_conformite
             (num_fnc, idaudit, idinspecteur_createur, idorga, iddomaine,
              representant_operateur, titre_representant, manuel, autres,
              source_audit, date_emission, date_transmission_rapport, delais_transmission,
              libelle, description_constatation, etat, categorie, ref_reglement, ref_reglement_iem,
              probabilite, gravite, indice_risque, justif_probabilite, justif_gravite,
              date_reponse_exigee, date_limite_mise_conformite,
              agent_suivi, statut,
              analyse_causes, actions_correctives, observation,
              pac_pertinent, pac_exhaustif, pac_detaille, pac_specifique, pac_realiste, pac_coherent,
              pac_acceptation, verification_meo, nom_visa_date)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        )->execute([
            $num_fnc, $idaudit, $inspCreateur, (int)$aud['idorga'], $iddomaine,
            $clean($_POST['representant_operateur'] ?? ''),
            $clean($_POST['titre_representant'] ?? ''),
            $clean($_POST['manuel'] ?? ''),
            $clean($_POST['autres'] ?? ''),
            $clean($_POST['source_audit'] ?? ''),
            $dateEmission, $dateRapport, $delaiTrans,
            $cleanRich($_POST['libelle'] ?? ''),
            $cleanRich($_POST['description_constatation'] ?? ''),
            Security::cleanInput($_POST['etat'] ?? ''),
            $categorie,
            $clean($_POST['ref_reglement'] ?? ''),
            $clean($_POST['ref_reglement_iem'] ?? ''),
            (int)$probabilite, $gravite, $indiceRisque, $justifProba, $justifGravite,
            $dateRep, $dateLimite,
            $inspCreateur, 4,
            $clean($_POST['analyse_causes'] ?? ''),
            $clean($_POST['actions_correctives'] ?? ''),
            $clean($_POST['observation'] ?? ''),
            Security::cleanInput($_POST['pac_pertinent']  ?? ''),
            Security::cleanInput($_POST['pac_exhaustif']  ?? ''),
            Security::cleanInput($_POST['pac_detaille']   ?? ''),
            Security::cleanInput($_POST['pac_specifique'] ?? ''),
            Security::cleanInput($_POST['pac_realiste']   ?? ''),
            Security::cleanInput($_POST['pac_coherent']   ?? ''),
            Security::cleanInput($_POST['pac_acceptation'] ?? ''),
            (int)($_POST['verification_meo'] ?? 0),
            $clean($_POST['nom_visa_date'] ?? ''),
        ]);
    } else {
        $db->prepare(
        "INSERT INTO fiche_non_conformite
         (num_fnc, idaudit, idinspecteur_createur, idorga, iddomaine,
          representant_operateur, titre_representant, manuel, autres,
          source_audit, date_emission, date_transmission_rapport, delais_transmission,
          libelle, description_constatation, etat, categorie, ref_reglement, ref_reglement_iem,
          date_reponse_exigee, date_limite_mise_conformite,
          agent_suivi, statut,
          analyse_causes, actions_correctives, observation,
          pac_pertinent, pac_exhaustif, pac_detaille, pac_specifique, pac_realiste, pac_coherent,
          pac_acceptation, verification_meo, nom_visa_date)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
    )->execute([
        $num_fnc, $idaudit, $inspCreateur, (int)$aud['idorga'], $iddomaine,
        $clean($_POST['representant_operateur'] ?? ''),
        $clean($_POST['titre_representant'] ?? ''),
        $clean($_POST['manuel'] ?? ''),
        $clean($_POST['autres'] ?? ''),
        $clean($_POST['source_audit'] ?? ''),
        $dateEmission, $dateRapport, $delaiTrans,
        $cleanRich($_POST['libelle'] ?? ''),
        $cleanRich($_POST['description_constatation'] ?? ''),
        Security::cleanInput($_POST['etat'] ?? ''),
        $categorie,
        $clean($_POST['ref_reglement'] ?? ''),
        $clean($_POST['ref_reglement_iem'] ?? ''),
        $dateRep, $dateLimite,
        $inspCreateur, 4,
        $clean($_POST['analyse_causes'] ?? ''),
        $clean($_POST['actions_correctives'] ?? ''),
        $clean($_POST['observation'] ?? ''),
        Security::cleanInput($_POST['pac_pertinent']  ?? ''),
        Security::cleanInput($_POST['pac_exhaustif']  ?? ''),
        Security::cleanInput($_POST['pac_detaille']   ?? ''),
        Security::cleanInput($_POST['pac_specifique'] ?? ''),
        Security::cleanInput($_POST['pac_realiste']   ?? ''),
        Security::cleanInput($_POST['pac_coherent']   ?? ''),
        Security::cleanInput($_POST['pac_acceptation'] ?? ''),
        (int)($_POST['verification_meo'] ?? 0),
        $clean($_POST['nom_visa_date'] ?? ''),
    ]);
    }
    $idfnc = (int)$db->lastInsertId();

    // Sous-domaines (ids entiers valides uniquement)
    $sdsRaw = $_POST['sousdomaines'] ?? [];
    $sds = is_array($sdsRaw) ? $sdsRaw : explode(',', (string)$sdsRaw);
    foreach ($sds as $sd) {
        $sd = (int)trim((string)$sd);
        if ($sd > 0) {
            $db->prepare("INSERT IGNORE INTO fnc_sousdomaine (idfnc,idsousdomaine) VALUES (?,?)")->execute([$idfnc,$sd]);
        }
    }
    // Reglements (ids entiers valides uniquement : les faux ids non numeriques sont ignores)
    $regsRaw = $_POST['reglements'] ?? [];
    $regs = is_array($regsRaw) ? $regsRaw : explode(',', (string)$regsRaw);
    foreach ($regs as $rg) {
        $rg = (int)trim((string)$rg);
        if ($rg > 0) {
            $db->prepare("INSERT IGNORE INTO fnc_reglement (idfnc,idreglement) VALUES (?,?)")->execute([$idfnc,$rg]);
        }
    }
    // ---- Numero definitif : idfnc / OACI / domaine / annee ----
    $stNum = $db->prepare(
        "SELECT s.indicateur_oaci, YEAR(a.date_previsionnelle) AS annee, d.nomdomaine
         FROM audit a
         LEFT JOIN site s    ON s.idsite    = a.idsite
         LEFT JOIN domaine d ON d.iddomaine = ?
         WHERE a.idaudit = ? LIMIT 1"
    );
    $stNum->execute([$iddomaine, $idaudit]);
    $rNum   = $stNum->fetch() ?: [];
    $oaci   = trim((string)($rNum['indicateur_oaci'] ?? '')) ?: 'XXXX';
    $annNum = (int)($rNum['annee'] ?? 0) ?: (int)date('Y');
    $abrev  = strtoupper(trim((string)($rNum['nomdomaine'] ?? ''))) ?: 'DOM';

    $num_fnc = str_pad((string)$idfnc, 3, '0', STR_PAD_LEFT) . '/' . $oaci . '/' . $abrev . '/' . $annNum;
    $db->prepare("UPDATE fiche_non_conformite SET num_fnc=? WHERE idfnc=?")->execute([$num_fnc, $idfnc]);

    // Piece jointe : fiche signee (PDF), facultative a la creation
    $ficNom = fnc_save_pdf($_FILES['fichier_fnc'] ?? null);
    if ($ficNom !== null) {
        $db->prepare("UPDATE fiche_non_conformite SET fichier_fnc=? WHERE idfnc=?")->execute([$ficNom, $idfnc]);
    }

    Audit::log('create','nonconformites',"Creation FNC $num_fnc pour audit #$idaudit" . ($ficNom ? ' (avec piece jointe)' : ''));
    $ok([
        'idfnc'   => $idfnc,
        'num_fnc' => $num_fnc,
        'fichier' => $ficNom,
        'message' => "FNC $num_fnc creee avec succes." . ($ficNom ? ' Fiche signee jointe.' : '')
    ]);
    break;

// ----------------------------------------------------------------
// Liste des FNC
//  - Admin / CI : toutes les FNC
//  - Inspecteur : uniquement les FNC des audits ou il est PLANIFIE
//                 (responsable d'audit OU membre de l'equipe via audit_equipe)
// ----------------------------------------------------------------
case 'list':
    $where  = 'WHERE 1=1'; $params = [];
    if ($isInsp && $myInsp !== null) {
        // Audits auxquels l'inspecteur participe (RA ou membre d'equipe)
        $stAudits = $db->prepare(
            "SELECT DISTINCT a.idaudit FROM audit a
             LEFT JOIN audit_equipe ae ON ae.idaudit = a.idaudit
             WHERE a.idresponsable_audit = ? OR ae.idinspecteur = ?"
        );
        $stAudits->execute([$myInsp, $myInsp]);
        $myAuditIds = $stAudits->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($myAuditIds)) {
            // Voit toutes les FNC des audits ou il est planifie (peu importe le createur)
            $in = implode(',', array_fill(0, count($myAuditIds), '?'));
            $where .= " AND f.idaudit IN ($in)";
            $params = array_merge($params, $myAuditIds);
        } else {
            // Aucun audit planifie : ne voit rien (sauf eventuellement ses propres FNC)
            $where .= " AND f.idinspecteur_createur = ?";
            $params[] = $myInsp;
        }
    }
    // Filtres utilisateur
    $fAudit  = (int)($_POST['f_audit']   ?? 0);
    $fStatut = trim((string)($_POST['f_statut'] ?? ''));
    $fCat    = trim((string)($_POST['f_categorie'] ?? ''));
    if ($fAudit)  { $where .= ' AND f.idaudit=?';    $params[] = $fAudit; }
    if ($fStatut !== '') { $where .= ' AND f.statut=?'; $params[] = (int)$fStatut; }
    if ($fCat)    { $where .= ' AND f.categorie=?';  $params[] = $fCat; }
    // Perimetre "mes fiches" (page Suivi NC) : seulement celles dont l'inspecteur
    // a la charge (createur ou agent de suivi). Le CI voit tout.
    $onlyMine = !empty($_POST['only_mine']);
    if ($onlyMine && $isInsp && $myInsp !== null) {
        $where .= " AND (f.idinspecteur_createur=? OR f.agent_suivi=?)";
        $params[] = $myInsp; $params[] = $myInsp;
    }

    $rows = $db->execute(
        "SELECT f.*,
                a.num_audit, a.type_activite, a.cadre, a.date_previsionnelle, a.date_delivrance_rapport,
                a.site_inspection, a.date_realisation, a.type_activite_operateur, a.ncns AS audit_ncns,
                o.nomorga, s.indicateur_oaci, s.nomsite, s.ville,
                d.nomdomaine, d.libel_domaine,
                TRIM(CONCAT(COALESCE(i.preninspect,''),' ',COALESCE(i.nominspecteur,''))) AS nom_inspecteur,
                TRIM(CONCAT(COALESCE(g.preninspect,''),' ',COALESCE(g.nominspecteur,''))) AS nom_agent_suivi,
                (SELECT GROUP_CONCAT(DISTINCT sd.nom_sousdomaine ORDER BY sd.nom_sousdomaine SEPARATOR ', ')
                   FROM fnc_sousdomaine fsd JOIN sous_domaine sd ON sd.idsousdomaine=fsd.idsousdomaine
                  WHERE fsd.idfnc=f.idfnc) AS sousdomaines_noms,
                (SELECT GROUP_CONCAT(DISTINCT r.code_reglement ORDER BY r.code_reglement SEPARATOR ', ')
                   FROM fnc_reglement frg JOIN reglement r ON r.idreglement=frg.idreglement
                  WHERE frg.idfnc=f.idfnc) AS reglements_codes
         FROM fiche_non_conformite f
         LEFT JOIN audit       a  ON a.idaudit       = f.idaudit
         LEFT JOIN organisme   o  ON o.idorga        = f.idorga
         LEFT JOIN site        s  ON s.idsite         = a.idsite
         LEFT JOIN domaine     d  ON d.iddomaine      = f.iddomaine
         LEFT JOIN inspecteur  i  ON i.idinspecteur   = f.idinspecteur_createur
         LEFT JOIN inspecteur  g  ON g.idinspecteur   = f.agent_suivi
         $where
         ORDER BY f.created_at DESC",
        $params
    )->fetchAll();
    $ok(['data' => $rows]);
    break;

// ----------------------------------------------------------------
// Detail d'une FNC
// ----------------------------------------------------------------
case 'get':
    $idfnc = (int)($_POST['idfnc'] ?? 0);
    $row = $db->execute(
        "SELECT f.*,
                a.num_audit, a.type_activite, a.cadre, a.date_previsionnelle,
                a.date_delivrance_rapport, a.site_inspection, a.date_realisation,
                a.type_activite_operateur, a.statut AS statut_audit,
                o.nomorga, o.trigrorganisme,
                s.indicateur_oaci, s.nomsite, s.ville,
                d.nomdomaine, d.libel_domaine,
                TRIM(CONCAT(COALESCE(i.preninspect,''),' ',COALESCE(i.nominspecteur,''))) AS nom_inspecteur,
                TRIM(CONCAT(COALESCE(g.preninspect,''),' ',COALESCE(g.nominspecteur,''))) AS nom_agent_suivi,
                g.mailinspect AS mail_agent_suivi,
                i.mailinspect AS mail_inspecteur
         FROM fiche_non_conformite f
         LEFT JOIN audit       a ON a.idaudit       = f.idaudit
         LEFT JOIN organisme   o ON o.idorga        = f.idorga
         LEFT JOIN site        s ON s.idsite         = a.idsite
         LEFT JOIN domaine     d ON d.iddomaine      = f.iddomaine
         LEFT JOIN inspecteur  i ON i.idinspecteur   = f.idinspecteur_createur
         LEFT JOIN inspecteur  g ON g.idinspecteur   = f.agent_suivi
         WHERE f.idfnc = ? LIMIT 1",
        [$idfnc]
    )->fetch();
    if (!$row) { $fail('FNC introuvable.'); break; }

    // Controle d'acces : un inspecteur ne peut consulter que les FNC des audits ou il est planifie
    if ($isInsp && $myInsp !== null) {
        $stChk = $db->prepare(
            "SELECT 1 FROM audit a
             LEFT JOIN audit_equipe ae ON ae.idaudit=a.idaudit AND ae.idinspecteur=?
             WHERE a.idaudit=? AND (a.idresponsable_audit=? OR ae.idinspecteur IS NOT NULL) LIMIT 1"
        );
        $stChk->execute([$myInsp, (int)$row['idaudit'], $myInsp]);
        if (!$stChk->fetch()) { $fail('Acces refuse a cette fiche.'); break; }
    }

    // Sous-domaines
    $sds = $db->execute(
        "SELECT sd.idsousdomaine, sd.nom_sousdomaine FROM fnc_sousdomaine fsd
         JOIN sous_domaine sd ON sd.idsousdomaine=fsd.idsousdomaine WHERE fsd.idfnc=?",[$idfnc]
    )->fetchAll();
    // Reglements
    $regs = $db->execute(
        "SELECT r.idreglement, r.code_reglement, r.libelle_reglement FROM fnc_reglement frg
         JOIN reglement r ON r.idreglement=frg.idreglement WHERE frg.idfnc=?",[$idfnc]
    )->fetchAll();
    // Options pour l'edition : tous les sous-domaines du domaine + reglements de l'audit
    $sdOptions = $db->execute(
        "SELECT idsousdomaine, nom_sousdomaine FROM sous_domaine WHERE iddomaine=? ORDER BY nom_sousdomaine",
        [(int)($row['iddomaine'] ?? 0)]
    )->fetchAll();
    $regOptions = $db->execute(
        "SELECT r.idreglement, r.code_reglement, r.libelle_reglement
         FROM audit_reglement ar JOIN reglement r ON r.idreglement=ar.idreglement
         WHERE ar.idaudit=? ORDER BY r.code_reglement",
        [(int)($row['idaudit'] ?? 0)]
    )->fetchAll();
    if (!$regOptions) {
        $regOptions = $db->execute("SELECT idreglement, code_reglement, libelle_reglement FROM reglement ORDER BY code_reglement LIMIT 100")->fetchAll();
    }
    $ok(['data' => $row, 'sousdomaines' => $sds, 'reglements' => $regs, 'sd_options' => $sdOptions, 'reg_options' => $regOptions]);
    break;

// ----------------------------------------------------------------
// Modifier le contenu d'une FNC (RA + membres d'equipe de l'audit)
// ----------------------------------------------------------------
case 'update':
    if (!$canOuverture) { $fail('Acces refuse.'); break; }
    $idfnc = (int)($_POST['idfnc'] ?? 0);
    if (!$idfnc) { $fail('FNC manquante.'); break; }
    $stF = $db->prepare("SELECT idaudit, idinspecteur_createur FROM fiche_non_conformite WHERE idfnc=? LIMIT 1");
    $stF->execute([$idfnc]); $fnc = $stF->fetch();
    if (!$fnc) { $fail('FNC introuvable.'); break; }
    // Controle d'acces renforce (OWASP - IDOR / Broken Access Control) :
    //  - le CI (chef inspecteur / admin) peut modifier toutes les fiches ;
    //  - un inspecteur ne peut modifier QUE les fiches dont il est le createur.
    //    Il peut voir/imprimer celles des autres, mais pas les modifier.
    $estCI = in_array($role, ['admin','chef_inspecteur'], true);
    if (!$estCI) {
        if ($myInsp === null) { $fail('Acces refuse.'); break; }
        // Doit etre planifie sur l'audit
        $stChk = $db->prepare(
            "SELECT 1 FROM audit a
             LEFT JOIN audit_equipe ae ON ae.idaudit=a.idaudit AND ae.idinspecteur=?
             WHERE a.idaudit=? AND (a.idresponsable_audit=? OR ae.idinspecteur IS NOT NULL) LIMIT 1"
        );
        $stChk->execute([$myInsp, (int)$fnc['idaudit'], $myInsp]);
        if (!$stChk->fetch()) { $fail('Vous n\'etes pas planifie sur cet audit.'); break; }
        // ET doit etre le createur de la fiche
        if ((int)$fnc['idinspecteur_createur'] !== (int)$myInsp) {
            Audit::log('access_denied','nonconformites',"update FNC #$idfnc refuse : non createur (insp $myInsp)");
            $fail('Vous ne pouvez modifier que les fiches que vous avez saisies. Cette fiche appartient a un autre inspecteur.'); break;
        }
    }
    $categorie = Security::cleanInput($_POST['categorie'] ?? '');
    // Une observation ne donne pas lieu a une fiche de non-conformite.
    if ($categorie === 'observation') {
        $fail('La categorie Observation ne genere pas de fiche de non-conformite.'); break;
    }
    if (!in_array($categorie, ['critique','majeur','mineur'], true)) { $fail('Categorie invalide.'); break; }
    // Evaluation des risques (OACI Doc 9859) : recalcul serveur de la categorie.
    $probabilite = Security::cleanInput($_POST['probabilite'] ?? '');
    $gravite     = strtoupper(Security::cleanInput($_POST['gravite'] ?? ''));
    if (!in_array($probabilite, ['1','2','3','4','5'], true)) { $fail('Probabilite invalide.'); break; }
    if (!in_array($gravite, ['A','B','C','D','E'], true)) { $fail('Gravite invalide.'); break; }
    $indiceRisque = $probabilite . $gravite;
    $matriceOaci = [
        'critique' => ['5A','5B','5C','4A','4B','3A'],
        'majeur'   => ['5D','5E','4C','4D','4E','3B','3C','3D','2A','2B','2C','1A'],
        'mineur'   => ['3E','2D','2E','1B','1C','1D','1E'],
    ];
    $catAttendue = null;
    foreach ($matriceOaci as $cat => $indices) {
        if (in_array($indiceRisque, $indices, true)) { $catAttendue = $cat; break; }
    }
    if ($catAttendue === null) { $fail('Indice de risque invalide (' . htmlspecialchars($indiceRisque, ENT_QUOTES, 'UTF-8') . ').'); break; }
    $categorie = $catAttendue;
    // Justifications OACI (calculees cote serveur, jamais depuis le client)
    $PROBA_JUSTIF_U = [
        '5' => 'Frequent : susceptible de se produire de nombreuses fois (s\'est produit frequemment).',
        '4' => 'Occasionnel : susceptible de se produire parfois (ne s\'est pas produit frequemment).',
        '3' => 'Faible : peu susceptible de se produire, mais possible (s\'est produit rarement).',
        '2' => 'Improbable : tres peu susceptible de se produire (on n\'a pas connaissance que cela se soit produit).',
        '1' => 'Extremement improbable : il est presque inconcevable que l\'evenement se produise.',
    ];
    $GRAVITE_JUSTIF_U = [
        'A' => 'Catastrophique : aeronef/equipement detruit ; multiples deces.',
        'B' => 'Dangereux : importante reduction des marges de securite, detresse physique ou charge de travail excessive.',
        'C' => 'Majeur : reduction des marges de securite, incident grave, personnes blessees.',
        'D' => 'Mineur : nuisance, limites de fonctionnement, recours a des procedures d\'urgence, incident mineur.',
        'E' => 'Negligeable : peu de consequences.',
    ];
    $justifProba   = trim(strip_tags((string)($_POST['justif_probabilite'] ?? '')));
    $justifGravite = trim(strip_tags((string)($_POST['justif_gravite'] ?? '')));
    if ($justifProba === '')   { $justifProba   = $PROBA_JUSTIF_U[$probabilite] ?? ''; }
    if ($justifGravite === '') { $justifGravite = $GRAVITE_JUSTIF_U[$gravite] ?? ''; }
    $justifProba   = mb_substr($justifProba, 0, 255);
    $justifGravite = mb_substr($justifGravite, 0, 500);
    $clean = function($v){ return trim(strip_tags((string)$v)); };
    $cleanRich = function($v){
        $v = (string)$v;
        $v = strip_tags($v, '<p><br><b><strong><i><em><u><s><ol><ul><li>');
        $v = preg_replace('/<([a-z0-9]+)[^>]*>/i', '<$1>', $v);
        $txt = trim(preg_replace('/&nbsp;/', ' ', strip_tags($v)));
        return $txt === '' ? '' : trim($v);
    };
    $vdate = function($v){ $v=trim((string)$v); return preg_match('/^\d{4}-\d{2}-\d{2}$/',$v) ? $v : null; };

    /* ------------------------------------------------------------------
     * Suivi de la mise en conformite.
     * Le statut est deduit cote serveur, jamais accepte tel quel du client :
     *   PAC accepte                     -> 1 (accepte non verifie)
     *   PAC refuse                      -> 2 (rejete)
     *   Mise en oeuvre verifiee (coche) -> 3 (ferme)  [prioritaire]
     *   sinon                           -> 4 (ouvert)
     * ------------------------------------------------------------------ */
    $meoVal    = ((int)($_POST['verification_meo'] ?? 0) === 1) ? 1 : 0;
    $pacAcc    = Security::cleanInput($_POST['pac_acceptation'] ?? '');
    $statutFnc = 4;
    if ($pacAcc === 'acceptee') { $statutFnc = 1; }
    if ($pacAcc === 'refusee')  { $statutFnc = 2; }
    if ($meoVal === 1)          { $statutFnc = 3; }

    // Date de cloture : uniquement lorsque la fiche est fermee
    $dateCloture = ($statutFnc === 3)
        ? ($vdate($_POST['date_effective_cloture'] ?? '') ?? date('Y-m-d'))
        : null;

    // Le delai exige suit toujours la date limite de mise en conformite
    $delaiExige = $vdate($_POST['delais_mise_conformite_exige'] ?? '')
               ?? $vdate($_POST['date_limite_mise_conformite'] ?? '');
    $delaiReel  = $vdate($_POST['delais_mise_conformite_reel'] ?? '');

    // Statut du delai : D si la mise en conformite depasse l'echeance, sinon ND
    $statutDelai = null;
    if ($delaiExige !== null && $delaiReel !== null) {
        $statutDelai = (strtotime($delaiReel) > strtotime($delaiExige)) ? 'D' : 'ND';
    }

    // Detection des colonnes d'evaluation des risques
    $hasRisqueColsU = false;
    try { $hasRisqueColsU = (bool)$db->execute("SHOW COLUMNS FROM fiche_non_conformite LIKE 'indice_risque'")->fetch(); }
    catch (\Throwable $e) { $hasRisqueColsU = false; }
    $hasJustifColsU = false;
    try { $hasJustifColsU = (bool)$db->execute("SHOW COLUMNS FROM fiche_non_conformite LIKE 'justif_probabilite'")->fetch(); }
    catch (\Throwable $e) { $hasJustifColsU = false; }

    // Fragment SQL optionnel pour l'evaluation des risques
    $risqueSet = '';
    $risqueVals = [];
    if ($hasRisqueColsU) {
        $risqueSet .= ' probabilite=?, gravite=?, indice_risque=?,';
        $risqueVals = [(int)$probabilite, $gravite, $indiceRisque];
        if ($hasJustifColsU) {
            $risqueSet .= ' justif_probabilite=?, justif_gravite=?,';
            $risqueVals[] = $justifProba;
            $risqueVals[] = $justifGravite;
        }
    }

    $db->prepare(
        "UPDATE fiche_non_conformite SET
            representant_operateur=?, titre_representant=?, date_emission=?,
            description_constatation=?, libelle=?, etat=?, categorie=?,"
            . $risqueSet . "
            manuel=?, autres=?, date_reponse_exigee=?, date_limite_mise_conformite=?,
            analyse_causes=?, actions_correctives=?, observation=?,
            pac_pertinent=?, pac_exhaustif=?, pac_detaille=?, pac_specifique=?, pac_realiste=?, pac_coherent=?,
            pac_acceptation=?, verification_meo=?, nom_visa_date=?,
            statut=?, date_effective_cloture=?,
            delais_mise_conformite_exige=?, delais_mise_conformite_reel=?,
            efficacite_mise_conformite=?, statut_delais_efficacite=?,
            observations_courriers=?, relance=?,
            updated_at=NOW()
         WHERE idfnc=?"
    )->execute(array_merge([
        $clean($_POST['representant_operateur'] ?? ''),
        $clean($_POST['titre_representant'] ?? ''),
        $vdate($_POST['date_emission'] ?? '') ?? date('Y-m-d'),
        $cleanRich($_POST['description_constatation'] ?? ''),
        $cleanRich($_POST['libelle'] ?? ($_POST['description_constatation'] ?? '')),
        Security::cleanInput($_POST['etat'] ?? ''),
        $categorie,
    ], $risqueVals, [
        $clean($_POST['manuel'] ?? ''),
        $clean($_POST['autres'] ?? ''),
        $vdate($_POST['date_reponse_exigee'] ?? ''),
        $vdate($_POST['date_limite_mise_conformite'] ?? ''),
        $clean($_POST['analyse_causes'] ?? ''),
        $clean($_POST['actions_correctives'] ?? ''),
        $clean($_POST['observation'] ?? ''),
        Security::cleanInput($_POST['pac_pertinent'] ?? ''),
        Security::cleanInput($_POST['pac_exhaustif'] ?? ''),
        Security::cleanInput($_POST['pac_detaille'] ?? ''),
        Security::cleanInput($_POST['pac_specifique'] ?? ''),
        Security::cleanInput($_POST['pac_realiste'] ?? ''),
        Security::cleanInput($_POST['pac_coherent'] ?? ''),
        Security::cleanInput($_POST['pac_acceptation'] ?? ''),
        $meoVal,
        $clean($_POST['nom_visa_date'] ?? ''),
        $statutFnc,
        $dateCloture,
        $delaiExige,
        $delaiReel,
        $clean($_POST['efficacite_mise_conformite'] ?? ''),
        $statutDelai,
        $clean($_POST['observations_courriers'] ?? ''),
        $clean($_POST['relance'] ?? ''),
        $idfnc,
    ]));

    // Re-synchroniser sous-domaines
    $db->prepare("DELETE FROM fnc_sousdomaine WHERE idfnc=?")->execute([$idfnc]);
    $sdsRaw = $_POST['sousdomaines'] ?? [];
    $sdsArr = is_array($sdsRaw) ? $sdsRaw : explode(',', (string)$sdsRaw);
    foreach ($sdsArr as $sd) { $sd=(int)trim((string)$sd); if ($sd>0) { $db->prepare("INSERT IGNORE INTO fnc_sousdomaine (idfnc,idsousdomaine) VALUES (?,?)")->execute([$idfnc,$sd]); } }

    // Re-synchroniser reglements
    $db->prepare("DELETE FROM fnc_reglement WHERE idfnc=?")->execute([$idfnc]);
    $regsRaw = $_POST['reglements'] ?? [];
    $regsArr = is_array($regsRaw) ? $regsRaw : explode(',', (string)$regsRaw);
    foreach ($regsArr as $rg) { $rg=(int)trim((string)$rg); if ($rg>0) { $db->prepare("INSERT IGNORE INTO fnc_reglement (idfnc,idreglement) VALUES (?,?)")->execute([$idfnc,$rg]); } }

    // Autres documents du dossier (PDF, cumules dans un seul fichier)
    $autresNom = fnc_save_pdf($_FILES['autres_documents'] ?? null);
    if ($autresNom !== null) {
        $stAu = $db->prepare("SELECT autres_documents FROM fiche_non_conformite WHERE idfnc=? LIMIT 1");
        $stAu->execute([$idfnc]);
        $ancienAutres = (string)($stAu->fetchColumn() ?: '');
        $autresNom = fnc_cumuler_pdf($ancienAutres, $autresNom);
        $db->prepare("UPDATE fiche_non_conformite SET autres_documents=? WHERE idfnc=?")->execute([$autresNom, $idfnc]);
    }

    // Preuve de suivi et verification de l'efficacite (PDF, remplace l'ancienne)
    $preuveNom = fnc_save_pdf($_FILES['preuve_suivi'] ?? null);
    if ($preuveNom !== null) {
        $stPr = $db->prepare("SELECT preuve_suivi FROM fiche_non_conformite WHERE idfnc=? LIMIT 1");
        $stPr->execute([$idfnc]);
        $anciennePreuve = (string)($stPr->fetchColumn() ?: '');
        $preuveNom = fnc_cumuler_pdf($anciennePreuve, $preuveNom);
        $db->prepare("UPDATE fiche_non_conformite SET preuve_suivi=? WHERE idfnc=?")->execute([$preuveNom, $idfnc]);
    }

    // Piece jointe : un nouveau PDF remplace l'ancien, qui est efface du disque
    $ficNom = fnc_save_pdf($_FILES['fichier_fnc'] ?? null);
    if ($ficNom !== null) {
        $stOld = $db->prepare("SELECT fichier_fnc FROM fiche_non_conformite WHERE idfnc=? LIMIT 1");
        $stOld->execute([$idfnc]);
        $ancien = (string)($stOld->fetchColumn() ?: '');
        // Les documents s'accumulent dans un seul PDF, par ordre de depot
        $ficNom = fnc_cumuler_pdf($ancien, $ficNom);
        $db->prepare("UPDATE fiche_non_conformite SET fichier_fnc=? WHERE idfnc=?")->execute([$ficNom, $idfnc]);
    }

    Audit::log('update','nonconformites',"Modification FNC #$idfnc" . ($ficNom ? ' (document ajoute au dossier)' : ''));
    $ok(['message' => 'FNC mise a jour.' . ($ficNom ? ' Fiche signee remplacee.' : ''), 'fichier' => $ficNom]);
    break;

// ----------------------------------------------------------------
// Mettre a jour le statut / suivi FNC (CI / Admin)
// ----------------------------------------------------------------
case 'update_suivi':
    $idfnc  = (int)($_POST['idfnc'] ?? 0);
    if (!$idfnc) { $fail('FNC manquante.'); break; }
    if (!$isCI)  { $fail('Action reservee au CI et Admin.'); break; }
    $fields = ['statut','date_effective_cloture','efficacite_mise_conformite',
               'statut_delais_efficacite','preuve_suivi','observations_courriers','relance'];
    $sets = []; $vals = [];
    foreach ($fields as $f) {
        if (isset($_POST[$f])) {
            $sets[] = "`$f`=?";
            $vals[] = Security::cleanInput((string)$_POST[$f]);
        }
    }
    if (!$sets) { $fail('Aucune donnee a mettre a jour.'); break; }
    $vals[] = $idfnc;
    $db->prepare("UPDATE fiche_non_conformite SET ".implode(',',$sets).",updated_at=NOW() WHERE idfnc=?")
       ->execute($vals);
    Audit::log('update','nonconformites',"Maj suivi FNC #$idfnc");
    $ok(['message' => 'FNC mise a jour.']);
    break;

// ----------------------------------------------------------------
// Supprimer une FNC (admin/CI seulement)
// ----------------------------------------------------------------
case 'serve_fiche':
    // Lecture seule : aucune ecriture, donc pas de risque CSRF (OWASP : CSRF vise les actions d'etat)
    // Consultation de la fiche signee : le fichier est hors zone publique,
    // il n'est servi qu'apres controle de session et d'habilitation.
    $idfnc = (int)($_POST['idfnc'] ?? $_GET['idfnc'] ?? 0);
    if ($idfnc <= 0) { $fail('Fiche introuvable.'); break; }
    // Deux documents possibles : la fiche signee, ou la preuve de suivi
    $docDem = (string)($_POST['doc'] ?? $_GET['doc'] ?? '');
    $colDoc = 'fichier_fnc';
    if ($docDem === 'preuve') { $colDoc = 'preuve_suivi'; }
    if ($docDem === 'autres') { $colDoc = 'autres_documents'; }
    /* ------------------------------------------------------------------
     * Controle d'acces au document (OWASP A01 - reference directe non securisee).
     * L'habilitation au module ne suffit pas : un inspecteur ne doit consulter
     * que les pieces des fiches rattachees a un audit auquel il participe,
     * ou dont il est le redacteur ou l'agent de suivi.
     * ------------------------------------------------------------------ */
    $sqlFic = "SELECT f.$colDoc AS doc, f.num_fnc
               FROM fiche_non_conformite f
               LEFT JOIN audit a ON a.idaudit = f.idaudit
               WHERE f.idfnc = ?";
    $parFic = [$idfnc];
    if ($isInsp && $myInsp !== null) {
        $sqlFic .= " AND (f.idinspecteur_createur = ?
                       OR f.agent_suivi = ?
                       OR a.idresponsable_audit = ?
                       OR EXISTS (SELECT 1 FROM audit_equipe ae
                                   WHERE ae.idaudit = f.idaudit AND ae.idinspecteur = ?))";
        array_push($parFic, $myInsp, $myInsp, $myInsp, $myInsp);
    } elseif (!$isCI && !$isInsp) {
        // Aucun profil reconnu : acces refuse
        $sqlFic .= " AND 1=0";
    }
    $stFic = $db->prepare($sqlFic . " LIMIT 1");
    $stFic->execute($parFic); $rowFic = $stFic->fetch();
    if (!$rowFic) {
        Audit::log('access_denied','nonconformites',"Tentative de consultation du document FNC #$idfnc");
    }
    // Message identique que la fiche soit inexistante, interdite ou sans document :
    // aucune information n'est divulguee sur l'existence des enregistrements.
    if (!$rowFic || trim((string)$rowFic['doc']) === '') { $fail('Document indisponible.'); break; }
    $chemin = fnc_dir() . '/' . basename((string)$rowFic['doc']);
    if (!is_file($chemin)) { $fail('Fichier introuvable sur le serveur.'); break; }
    Audit::log('download','nonconformites',"Consultation fiche signee FNC #$idfnc");
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="FNC_' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$rowFic['num_fnc']) . '.pdf"');
    header('Content-Length: ' . filesize($chemin));
    header('X-Content-Type-Options: nosniff');
    readfile($chemin);
    exit;

case 'delete':
    $idfnc = (int)($_POST['idfnc'] ?? 0);
    if (!$isCI) { $fail('Action reservee au CI et Admin.'); break; }
    $stF = $db->prepare("SELECT num_fnc, fichier_fnc, preuve_suivi, autres_documents FROM fiche_non_conformite WHERE idfnc=?");
    $stF->execute([$idfnc]); $fnc = $stF->fetch();
    if (!$fnc) { $fail('FNC introuvable.'); break; }
    $db->prepare("DELETE FROM fiche_non_conformite WHERE idfnc=?")->execute([$idfnc]);
    // Liberation de l'espace disque : toutes les pieces suivent la fiche
    fnc_delete_pdf($fnc['fichier_fnc'] ?? null);
    fnc_delete_pdf($fnc['preuve_suivi'] ?? null);
    fnc_delete_pdf($fnc['autres_documents'] ?? null);
    Audit::log('delete','nonconformites',"Suppression FNC ".$fnc['num_fnc']);
    $ok(['message' => 'FNC supprimee.']);
    break;

// ----------------------------------------------------------------
// Stats pour tableau de bord suivi
// ----------------------------------------------------------------
case 'stats':
    $where = 'WHERE 1=1'; $params = [];
    if ($isInsp && $myInsp !== null) {
        $where .= " AND (f.idinspecteur_createur=? OR f.agent_suivi=?)";
        $params = [$myInsp, $myInsp];
    }
    $s = $db->execute(
        "SELECT
            COUNT(*) AS total,
            SUM(f.statut=4) AS ouvertes,
            SUM(f.statut=1) AS acceptees_non_verif,
            SUM(f.statut=2) AS rejetees,
            SUM(f.statut=3) AS fermees,
            SUM(f.categorie='critique') AS critiques,
            SUM(f.categorie='majeur')   AS majeures,
            SUM(f.categorie='mineur')   AS mineures,
            SUM(f.categorie='observation') AS observations,
            SUM(f.date_reponse_exigee < CURDATE() AND f.statut IN (1,4)) AS en_retard
         FROM fiche_non_conformite f $where",
        $params
    )->fetch();
    $ok($s ?: []);
    break;

// ----------------------------------------------------------------
// ALERTES : fiches dont les echeances approchent ou sont depassees
// ----------------------------------------------------------------
case 'alertes':
    // Toutes les fiches encore actives sont concernees : ouverte (4),
    // acceptee non verifiee (1) et rejetee (2). Seule une fiche FERMEE (3)
    // sort du suivi, son echeance n'ayant plus d'objet.
    $wA = "WHERE f.statut <> 3";
    $pA = [];
    if ($isInsp && $myInsp !== null) {
        $wA .= " AND (f.idinspecteur_createur=? OR f.agent_suivi=?)";
        $pA  = [$myInsp, $myInsp];
    }
    $rows = $db->execute(
        "SELECT f.idfnc, f.num_fnc, f.categorie, f.statut,
                f.date_reponse_exigee, f.date_limite_mise_conformite, f.date_emission,
                f.idinspecteur_createur, f.agent_suivi,
                a.num_audit, a.date_previsionnelle, a.date_realisation,
                YEAR(COALESCE(a.date_realisation, a.date_previsionnelle, f.date_emission)) AS annee,
                o.nomorga,
                d.nomdomaine,
                TRIM(CONCAT(COALESCE(i.preninspect,''),' ',COALESCE(i.nominspecteur,''))) AS nom_inspecteur,
                i.mailinspect AS mail_inspecteur,
                TRIM(CONCAT(COALESCE(g.preninspect,''),' ',COALESCE(g.nominspecteur,''))) AS nom_agent_suivi,
                g.mailinspect AS mail_agent_suivi
         FROM fiche_non_conformite f
         LEFT JOIN audit       a ON a.idaudit       = f.idaudit
         LEFT JOIN organisme   o ON o.idorga        = f.idorga
         LEFT JOIN domaine     d ON d.iddomaine     = f.iddomaine
         LEFT JOIN inspecteur  i ON i.idinspecteur  = f.idinspecteur_createur
         LEFT JOIN inspecteur  g ON g.idinspecteur  = f.agent_suivi
         $wA
         ORDER BY COALESCE(f.date_reponse_exigee, f.date_limite_mise_conformite) ASC",
        $pA
    )->fetchAll();

    // Calcul du nombre de jours restants, cote serveur pour eviter tout ecart de fuseau
    $auj = new DateTime(date('Y-m-d'));
    foreach ($rows as &$r) {
        foreach (['date_reponse_exigee' => 'j_reponse', 'date_limite_mise_conformite' => 'j_limite'] as $col => $cle) {
            $r[$cle] = null;
            $v = (string)($r[$col] ?? '');
            if ($v !== '' && $v !== '0000-00-00') {
                try {
                    $d = new DateTime(substr($v, 0, 10));
                    $r[$cle] = (int)$auj->diff($d)->format('%r%a');
                } catch (Throwable $e) { $r[$cle] = null; }
            }
        }
    }
    unset($r);
    $ok(['data' => $rows, 'aujourdhui' => date('Y-m-d')]);
    break;

// ----------------------------------------------------------------
// DETAIL D'UNE ALERTE : fiche, audit et equipe d'inspection
// ----------------------------------------------------------------
case 'detail_alerte':
    $idfnc = (int)($_POST['idfnc'] ?? 0);
    if ($idfnc <= 0) { $fail('Fiche introuvable.'); break; }

    $fic = $db->execute(
        "SELECT f.idfnc, f.num_fnc, f.categorie, f.statut, f.date_emission,
                f.date_reponse_exigee, f.date_limite_mise_conformite, f.libelle,
                f.description_constatation, f.etat,
                a.idaudit, a.num_audit, a.type_activite, a.cadre,
                a.date_previsionnelle, a.date_realisation, a.site_inspection,
                a.type_activite_operateur,
                o.nomorga, o.trigrorganisme,
                s.indicateur_oaci, s.nomsite, s.ville,
                d.nomdomaine, d.libel_domaine,
                TRIM(CONCAT(COALESCE(r.preninspect,''),' ',COALESCE(r.nominspecteur,''))) AS responsable,
                r.mailinspect AS mail_responsable,
                TRIM(CONCAT(COALESCE(i.preninspect,''),' ',COALESCE(i.nominspecteur,''))) AS nom_inspecteur,
                i.mailinspect AS mail_inspecteur,
                TRIM(CONCAT(COALESCE(g.preninspect,''),' ',COALESCE(g.nominspecteur,''))) AS nom_agent_suivi,
                g.mailinspect AS mail_agent_suivi
         FROM fiche_non_conformite f
         LEFT JOIN audit       a ON a.idaudit      = f.idaudit
         LEFT JOIN organisme   o ON o.idorga       = f.idorga
         LEFT JOIN site        s ON s.idsite       = a.idsite
         LEFT JOIN domaine     d ON d.iddomaine    = f.iddomaine
         LEFT JOIN inspecteur  r ON r.idinspecteur = a.idresponsable_audit
         LEFT JOIN inspecteur  i ON i.idinspecteur = f.idinspecteur_createur
         LEFT JOIN inspecteur  g ON g.idinspecteur = f.agent_suivi
         WHERE f.idfnc = ? LIMIT 1", [$idfnc]
    )->fetch();
    if (!$fic) { $fail('Fiche introuvable.'); break; }

    // Equipe d'inspection rattachee a l'audit
    $equipe = $db->execute(
        "SELECT DISTINCT ae.idinspecteur,
                TRIM(CONCAT(COALESCE(ins.preninspect,''),' ',COALESCE(ins.nominspecteur,''))) AS nom,
                ins.trigr_inspecteur, ins.mailinspect, ins.categorie,
                dm.nomdomaine
         FROM audit_equipe ae
         LEFT JOIN inspecteur ins ON ins.idinspecteur = ae.idinspecteur
         LEFT JOIN domaine    dm  ON dm.iddomaine     = ae.iddomaine
         WHERE ae.idaudit = ?
         ORDER BY nom", [(int)$fic['idaudit']]
    )->fetchAll();

    // Sous-domaines et reglements de la fiche
    $sds = $db->execute(
        "SELECT sd.nom_sousdomaine FROM fnc_sousdomaine fs
         JOIN sous_domaine sd ON sd.idsousdomaine = fs.idsousdomaine
         WHERE fs.idfnc = ?", [$idfnc]
    )->fetchAll(PDO::FETCH_COLUMN);

    $ok(['fiche' => $fic, 'equipe' => $equipe, 'sousdomaines' => $sds]);
    break;

// ----------------------------------------------------------------
// RELANCE : courriel a l'inspecteur en charge du suivi
// ----------------------------------------------------------------
case 'relance_mail':
    if (!$isCI) { $fail('Action reservee au chef inspecteur et a l\'administrateur.'); break; }

    // Identifiants de fiches : uniquement des entiers, valides en base
    $idsRaw = $_POST['idfncs'] ?? [];
    if (!is_array($idsRaw)) { $idsRaw = explode(',', (string)$idsRaw); }
    $ids = [];
    foreach ($idsRaw as $v) { $n = (int)$v; if ($n > 0 && !in_array($n, $ids, true)) { $ids[] = $n; } }
    if (!$ids) { $fail('Aucune fiche selectionnee.'); break; }
    if (count($ids) > 200) { $fail('Trop de fiches selectionnees.'); break; }

    $marks = implode(',', array_fill(0, count($ids), '?'));
    $lignes = $db->execute(
        "SELECT f.idfnc, f.num_fnc, f.categorie,
                f.date_reponse_exigee, f.date_limite_mise_conformite,
                a.num_audit,
                o.nomorga,
                COALESCE(g.idinspecteur, i.idinspecteur)                                   AS id_dest,
                COALESCE(NULLIF(TRIM(g.mailinspect),''), NULLIF(TRIM(i.mailinspect),''))   AS mail_dest,
                TRIM(CONCAT(COALESCE(COALESCE(g.preninspect,i.preninspect),''),' ',
                            COALESCE(COALESCE(g.nominspecteur,i.nominspecteur),'')))       AS nom_dest
         FROM fiche_non_conformite f
         LEFT JOIN audit      a ON a.idaudit      = f.idaudit
         LEFT JOIN organisme  o ON o.idorga       = f.idorga
         LEFT JOIN inspecteur i ON i.idinspecteur = f.idinspecteur_createur
         LEFT JOIN inspecteur g ON g.idinspecteur = f.agent_suivi
         WHERE f.idfnc IN ($marks) AND f.statut <> 3",
        $ids
    )->fetchAll();

    if (!$lignes) { $fail('Aucune fiche active parmi la selection : les fiches fermees ne font plus l\'objet de relance.'); break; }

    // Regroupement par destinataire : un seul courriel par inspecteur
    $parDest = [];
    $sansMail = [];
    $auj = new DateTime(date('Y-m-d'));
    foreach ($lignes as $l) {
        $mail = trim((string)($l['mail_dest'] ?? ''));
        if ($mail === '' || !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
            $sansMail[] = (string)$l['num_fnc']; continue;
        }
        $ech = (string)($l['date_reponse_exigee'] ?? '');
        $lib = 'Reponse exigee avant le';
        if ($ech === '' || $ech === '0000-00-00') {
            $ech = (string)($l['date_limite_mise_conformite'] ?? '');
            $lib = 'Mise en conformite avant le';
        }
        $jours = 0;
        if ($ech !== '' && $ech !== '0000-00-00') {
            try { $jours = (int)$auj->diff(new DateTime(substr($ech,0,10)))->format('%r%a'); }
            catch (Throwable $e) { $jours = 0; }
        }
        if (!isset($parDest[$mail])) { $parDest[$mail] = ['nom' => (string)$l['nom_dest'], 'fiches' => []]; }
        $parDest[$mail]['fiches'][] = [
            'num_fnc'          => (string)$l['num_fnc'],
            'num_audit'        => (string)($l['num_audit'] ?? ''),
            'operateur'        => (string)($l['nomorga'] ?? ''),
            'echeance'         => $ech !== '' ? date('d/m/Y', strtotime($ech)) : '-',
            'libelle_echeance' => $lib,
            'jours'            => $jours,
            'categorie'        => (string)($l['categorie'] ?? ''),
        ];
    }

    $envoyes = 0; $echecs = 0;
    $urlApp  = defined('SITE_URL') ? SITE_URL . '/ouverture-nc' : '';
    foreach ($parDest as $mail => $bloc) {
        try {
            $mailer = new Mailer();
            if ($mailer->sendRelanceFnc($mail, $bloc['nom'], $bloc['fiches'], $urlApp)) { $envoyes++; }
            else { $echecs++; }
        } catch (Throwable $eM) {
            error_log('relance FNC : ' . $eM->getMessage());
            $echecs++;
        }
    }

    Audit::log('email','nonconformites',
        "Relance echeances FNC : $envoyes destinataire(s), " . count($ids) . " fiche(s)");

    $msg = $envoyes . ' relance(s) envoyee(s).';
    if ($echecs)         { $msg .= ' ' . $echecs . ' echec(s) d\'envoi.'; }
    if ($sansMail)       { $msg .= ' Sans adresse : ' . implode(', ', array_slice($sansMail, 0, 5)) . '.'; }
    $ok(['message' => $msg, 'envoyes' => $envoyes, 'echecs' => $echecs, 'sans_mail' => $sansMail]);
    break;

default: $fail('Action inconnue.');
}} catch (Throwable $e) {
    // Ne jamais exposer le detail de l'exception au client (OWASP A05/A09).
    error_log('nonconformites: '.$e->getMessage());
    $fail('Erreur technique. Operation non realisee.');
}