<?php
/**
 * Page : Profil de risque des operateurs (Risk-Based Oversight - RBO)
 * Module : profil_risque  -  Route : /profil-risque
 *
 * Objectif metier
 * ---------------
 * Conformement a l'approche de surveillance basee sur les risques promue par
 * l'OACI (Doc 9734 - Manuel de supervision de la securite ; Doc 9859 - Manuel
 * de gestion de la securite), cette page attribue a chaque operateur un SCORE
 * DE RISQUE sur 100 et une CATEGORIE (Faible / Moyen / Eleve / Critique). Ce
 * profil aide l'ANAC a concentrer ses ressources de surveillance sur les
 * operateurs les plus a risque (frequence et intensite d'audit adaptees).
 *
 * Le score agrege 5 composantes ponderees, calculees a partir de l'historique
 * de surveillance deja present dans AGAI :
 *   1. Non-conformite (30%)  : taux moyen de criteres non satisfaisants (NCNS).
 *   2. Gravite        (25%)  : poids des non-conformites critiques / majeures.
 *   3. Delais         (20%)  : proportion de delais de mise en conformite depasses.
 *   4. NC ouvertes    (15%)  : part de fiches non encore fermees.
 *   5. Anciennete     (10%)  : temps écoulé depuis le dernier audit réalisé.
 *
 * Securite (OWASP)
 * ----------------
 *   - Rbac::guardPage('profil_risque') : acces reserve aux roles habilites.
 *   - Page en LECTURE SEULE : aucune donnee de la requete HTTP n'entre dans le
 *     SQL (requetes fixes, sans parametre issu du client).
 *   - Le SCORE est calcule ENTIEREMENT cote serveur (jamais cote client), pour
 *     empecher toute manipulation. Le client ne fait que filtrer / afficher.
 *   - Toute valeur affichee passe par Security::escape() (PHP) ou esc() (JS).
 *   - JSON encode avec les drapeaux JSON_HEX_* (anti-evasion de balise script).
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardPage('profil_risque');

$pageTitle = 'Profil de risque';
$active    = 'profil_risque';
$pageIcon  = 'bi-shield-exclamation';

/*
 * Ponderations des composantes (total = 100). Regroupees ici pour etre a la
 * fois documentees a l'ecran et utilisees dans le calcul : une seule source
 * de verite.
 */
$POIDS = [
    'nc'        => 30,  // taux de non-conformite (NCNS)
    'gravite'   => 25,  // gravite des non-conformites
    'delais'    => 20,  // depassement des delais de mise en conformite
    'ouvertes'  => 15,  // non-conformites encore ouvertes
    'anciennete'=> 10,  // anciennete du dernier audit
];

$OPERATEURS = [];
$GENERE_LE  = date('d/m/Y H:i');

try {
    $db = Database::getInstance();

    /* ---------------------------------------------------------------
     * 1) Agregats d'AUDITS par operateur (taux de conformite, volume,
     *    date du dernier audit realise).
     * --------------------------------------------------------------- */
    $auditsParOrga = $db->execute(
        "SELECT a.idorga,
                COUNT(*)                                   AS nb_audits,
                SUM(COALESCE(a.ncns,0))                     AS tot_ncns,
                SUM(COALESCE(a.ncs,0))                      AS tot_ncs,
                MAX(COALESCE(a.date_realisation, a.date_previsionnelle)) AS dernier_audit
           FROM audit a
          WHERE a.idorga > 0
          GROUP BY a.idorga"
    )->fetchAll(PDO::FETCH_ASSOC);

    /* ---------------------------------------------------------------
     * 2) Agregats de FICHES DE NON-CONFORMITE par operateur (gravite,
     *    statut, delais depasses).
     * --------------------------------------------------------------- */
    $fncParOrga = $db->execute(
        "SELECT f.idorga,
                COUNT(*)                                                          AS nb_fnc,
                SUM(CASE WHEN f.categorie = 'critique' THEN 1 ELSE 0 END)          AS nb_critique,
                SUM(CASE WHEN f.categorie = 'majeur'   THEN 1 ELSE 0 END)          AS nb_majeur,
                SUM(CASE WHEN f.categorie = 'mineur'   THEN 1 ELSE 0 END)          AS nb_mineur,
                SUM(CASE WHEN f.statut <> 3 THEN 1 ELSE 0 END)                     AS nb_ouvertes,
                SUM(CASE WHEN f.statut_delais_efficacite = 'D' THEN 1 ELSE 0 END)  AS nb_delais_depasses,
                SUM(CASE WHEN f.statut_delais_efficacite IS NOT NULL THEN 1 ELSE 0 END) AS nb_delais_evalues
           FROM fiche_non_conformite f
          WHERE f.idorga > 0
          GROUP BY f.idorga"
    )->fetchAll(PDO::FETCH_ASSOC);

    /* ---------------------------------------------------------------
     * 3) Referentiel des operateurs (nom, sigle, type d'exploitation).
     * --------------------------------------------------------------- */
    $filtreAgai = '';
    try {
        if ($db->query("SHOW COLUMNS FROM organisme LIKE 'gere_agai'")->fetch()) {
            $filtreAgai = " AND gere_agai = 'AGAI'";
        }
    } catch (\Throwable $e) { $filtreAgai = ''; }

    $orgas = $db->execute(
        "SELECT MIN(idorga) AS idorga, nomorga, MAX(trigrorganisme) AS trigrorganisme,
                MAX(cateoperater) AS cateoperater
           FROM organisme
          WHERE idorga > 0 AND TRIM(nomorga) <> ''" . $filtreAgai . "
          GROUP BY nomorga"
    )->fetchAll(PDO::FETCH_ASSOC);

    // Indexation pour jointure en memoire
    $idxAudit = [];
    foreach ($auditsParOrga as $r) { $idxAudit[(int)$r['idorga']] = $r; }
    $idxFnc = [];
    foreach ($fncParOrga as $r) { $idxFnc[(int)$r['idorga']] = $r; }

    $aujourdhui = new DateTime('today');

    foreach ($orgas as $o) {
        $idorga = (int) $o['idorga'];
        $au = $idxAudit[$idorga] ?? null;
        $fn = $idxFnc[$idorga] ?? null;

        // On ne profile que les operateurs ayant au moins un audit
        $nbAudits = $au ? (int) $au['nb_audits'] : 0;
        if ($nbAudits === 0) { continue; }

        $totNcns = $au ? (int) $au['tot_ncns'] : 0;
        $totNcs  = $au ? (int) $au['tot_ncs']  : 0;
        $nbFnc       = $fn ? (int) $fn['nb_fnc'] : 0;
        $nbCritique  = $fn ? (int) $fn['nb_critique'] : 0;
        $nbMajeur    = $fn ? (int) $fn['nb_majeur'] : 0;
        $nbMineur    = $fn ? (int) $fn['nb_mineur'] : 0;
        $nbOuvertes  = $fn ? (int) $fn['nb_ouvertes'] : 0;
        $nbDepasses  = $fn ? (int) $fn['nb_delais_depasses'] : 0;
        $nbEvalues   = $fn ? (int) $fn['nb_delais_evalues'] : 0;

        /* -----------------------------------------------------------
         * COMPOSANTE 1 - Taux de non-conformite (0..100)
         * NCNS / (NCNS + NCS). Si aucun critere evalue -> 0.
         * ----------------------------------------------------------- */
        $baseCrit = $totNcns + $totNcs;
        $cNc = $baseCrit > 0 ? ($totNcns / $baseCrit) * 100 : 0;

        /* -----------------------------------------------------------
         * COMPOSANTE 2 - Gravite des non-conformites (0..100)
         * Moyenne ponderee : critique=100, majeur=60, mineur=25.
         * ----------------------------------------------------------- */
        $cGravite = 0;
        if ($nbFnc > 0) {
            $cGravite = (($nbCritique * 100) + ($nbMajeur * 60) + ($nbMineur * 25)) / $nbFnc;
            if ($cGravite > 100) { $cGravite = 100; }
        }

        /* -----------------------------------------------------------
         * COMPOSANTE 3 - Delais de mise en conformite depasses (0..100)
         * Part des delais evalues qui sont depasses (statut = D).
         * ----------------------------------------------------------- */
        $cDelais = $nbEvalues > 0 ? ($nbDepasses / $nbEvalues) * 100 : 0;

        /* -----------------------------------------------------------
         * COMPOSANTE 4 - Non-conformites encore ouvertes (0..100)
         * Part de fiches non fermees.
         * ----------------------------------------------------------- */
        $cOuvertes = $nbFnc > 0 ? ($nbOuvertes / $nbFnc) * 100 : 0;

        /* -----------------------------------------------------------
         * COMPOSANTE 5 - Anciennete du dernier audit (0..100)
         * 0 mois -> 0 ; 24 mois ou plus -> 100 (lineaire, plafonne).
         * Un operateur non audite depuis longtemps est plus "a risque"
         * du point de vue de la surveillance (visibilite faible).
         * ----------------------------------------------------------- */
        $cAnciennete = 0;
        $dernier = $au['dernier_audit'] ?? null;
        if ($dernier && $dernier !== '0000-00-00') {
            try {
                $d = new DateTime(substr($dernier, 0, 10));
                $moisEcoules = ($aujourdhui->getTimestamp() - $d->getTimestamp()) / (30.44 * 86400);
                if ($moisEcoules < 0) { $moisEcoules = 0; }
                $cAnciennete = min(100, ($moisEcoules / 24) * 100);
            } catch (\Throwable $e) { $cAnciennete = 0; }
        } else {
            $cAnciennete = 100; // jamais realise reellement
        }

        /* -----------------------------------------------------------
         * SCORE GLOBAL PONDERE (0..100)
         * ----------------------------------------------------------- */
        $score =
              $cNc         * ($POIDS['nc'] / 100)
            + $cGravite    * ($POIDS['gravite'] / 100)
            + $cDelais     * ($POIDS['delais'] / 100)
            + $cOuvertes   * ($POIDS['ouvertes'] / 100)
            + $cAnciennete * ($POIDS['anciennete'] / 100);
        $score = round($score, 1);

        /* -----------------------------------------------------------
         * CATEGORIE + frequence de surveillance recommandee
         * ----------------------------------------------------------- */
        if ($score <= 25)      { $cat = 'faible';   $freq = 'Cycle standard'; }
        elseif ($score <= 50)  { $cat = 'moyen';    $freq = 'Surveillance renforcée'; }
        elseif ($score <= 75)  { $cat = 'eleve';    $freq = 'Audits rapprochés'; }
        else                   { $cat = 'critique'; $freq = 'Surveillance prioritaire'; }

        // Mois ecoules depuis le dernier audit (pour affichage detaille)
        $moisEcoules = null;
        if ($dernier && $dernier !== '0000-00-00') {
            try {
                $d = new DateTime(substr($dernier, 0, 10));
                $moisEcoules = round(($aujourdhui->getTimestamp() - $d->getTimestamp()) / (30.44 * 86400), 1);
                if ($moisEcoules < 0) { $moisEcoules = 0; }
            } catch (\Throwable $e) { $moisEcoules = null; }
        }

        $OPERATEURS[] = [
            'idorga'      => $idorga,
            'nom'         => $o['nomorga'],
            'sigle'       => $o['trigrorganisme'],
            'categorie_op'=> $o['cateoperater'],
            'nb_audits'   => $nbAudits,
            'nb_fnc'      => $nbFnc,
            'nb_critique' => $nbCritique,
            'nb_majeur'   => $nbMajeur,
            'nb_mineur'   => $nbMineur,
            'nb_ouvertes' => $nbOuvertes,
            'nb_delais_depasses' => $nbDepasses,
            'nb_delais_evalues'  => $nbEvalues,
            'tot_ncns'    => $totNcns,
            'tot_ncs'     => $totNcs,
            'mois_ecoules'=> $moisEcoules,
            'dernier_audit'=> $dernier,
            'c_nc'        => round($cNc, 1),
            'c_gravite'   => round($cGravite, 1),
            'c_delais'    => round($cDelais, 1),
            'c_ouvertes'  => round($cOuvertes, 1),
            'c_anciennete'=> round($cAnciennete, 1),
            'score'       => $score,
            'categorie'   => $cat,
            'frequence'   => $freq,
        ];
    }

    // Tri par score decroissant (les plus a risque en tete)
    usort($OPERATEURS, function ($a, $b) { return $b['score'] <=> $a['score']; });

} catch (\Throwable $e) {
    $OPERATEURS = [];
    if (function_exists('error_log')) { error_log('profil_risque: ' . $e->getMessage()); }
}

require_once INCLUDES_PATH . '/layout_head.php';
?>
<style>
.pr-explain{background:#fff;border:1px solid #eef1f6;border-radius:14px;border-left:4px solid #1E9C4B;margin-bottom:16px}
.pr-explain-head{cursor:pointer;display:flex;align-items:center;gap:8px;padding:13px 16px;user-select:none}
.pr-explain-head b{color:#1E9C4B;font-size:1rem}
.pr-explain-body{display:none;padding:0 16px 16px;font-size:.86rem;color:#2C3E50;line-height:1.65}
.pr-explain-body h6{color:#23408F;font-weight:800;margin:14px 0 6px;font-size:.9rem}
.pr-formula{background:#f5f7fa;border:1px solid #e6ebf3;border-radius:10px;padding:12px 14px;margin:8px 0}
.pr-comp{display:flex;align-items:center;gap:10px;padding:6px 0;border-bottom:1px dashed #e6ebf3}
.pr-comp:last-child{border-bottom:none}
.pr-comp-badge{flex:0 0 46px;text-align:center;font-weight:800;color:#fff;border-radius:8px;padding:3px 0;font-size:.82rem}
.pr-why{background:#f0f4fb;border-radius:6px;padding:5px 9px;margin-top:5px;font-size:.79rem;color:#3a5075}
.pr-why i{color:#23408F}
.pr-cat-detail{background:#fff;border:1px solid #eef1f6;border-radius:8px;padding:9px 12px;margin-bottom:8px}
.pr-cat-t{font-weight:800;font-size:.87rem;margin-bottom:3px}
.pr-cat-d{font-size:.82rem;color:#4a5a70;line-height:1.5}
.pr-comp-txt{flex:1}
.pr-calc{background:#eef4ff;border:1px solid #d8e1f0;border-radius:6px;padding:4px 8px;margin-top:4px;font-family:Consolas,Monaco,monospace;font-size:.78rem;color:#23408F}
.pr-calc-ex{font-size:.78rem;color:#6b7a90;margin-top:3px;font-style:italic}
.pr-cat-legend{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.pr-cat-chip{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:20px;font-size:.78rem;font-weight:700}
.kpi-card{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:14px 16px;height:100%}
.kpi-card .kpi-v{font-size:1.5rem;font-weight:800;color:#2C3E50}
.kpi-card .kpi-l{font-size:.78rem;color:#6b7a90;margin-top:2px}
.risk-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:.76rem;font-weight:800;color:#fff}
.rb-faible{background:#1E9C4B}.rb-moyen{background:#F3C300;color:#5a4700}.rb-eleve{background:#E8890C}.rb-critique{background:#D32F2F}
.score-bar{height:8px;border-radius:5px;background:#eef1f6;overflow:hidden;min-width:80px}
.score-bar > div{height:100%;border-radius:5px}
.pr-card{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:16px}
.pr-th{font-size:.72rem;text-transform:uppercase;letter-spacing:.03em;color:#6b7a90}
</style>

<div class="page-head">
  <div>
    <h1><i class="bi bi-shield-exclamation me-2" style="color:var(--anac-primary)"></i>Profil de risque des operateurs</h1>
    <div class="sub">Surveillance basee sur les risques (OACI Doc 9734 / Doc 9859) - genere le <?php echo Security::escape($GENERE_LE); ?></div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <button class="btn btn-outline-danger" id="btnPdf"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</button>
    <button class="btn btn-outline-success" id="btnXls"><i class="bi bi-file-earmark-excel me-1"></i>Excel</button>
  </div>
</div>

<!-- ESPACE EXPLICATIF (pliable) -->
<div class="pr-explain">
  <div class="pr-explain-head" id="explainToggle">
    <i class="bi bi-info-circle" style="color:#1E9C4B"></i>
    <b>Comment fonctionne le profil de risque dans AGAI ?</b>
    <i class="bi bi-chevron-down ms-auto" id="explainChevron" style="color:#1E9C4B;transition:transform .2s"></i>
  </div>
  <div class="pr-explain-body" id="explainBody">
    <p>Dans l'aviation civile, l'OACI recommande une <b>surveillance basee sur les risques</b> (Risk-Based Oversight) :
    plutot que de surveiller tous les operateurs de facon identique, l'autorite concentre ses ressources sur les
    operateurs les plus a risque. AGAI calcule automatiquement, pour chaque operateur, un <b>score de risque sur 100</b>
    a partir de son historique de surveillance (audits et non-conformites), puis le classe dans une categorie.</p>

    <div class="p-2 mb-2" style="background:#fff8e6;border-left:4px solid #F3C300;border-radius:6px;font-size:.83rem">
      <i class="bi bi-question-circle me-1" style="color:#b58a00"></i><b>Pourquoi multiplier chaque composante par un pourcentage (30%, 25%...) ?</b>
      Les 5 composantes n'ont pas la meme importance dans le risque reel. La <b>ponderation</b> (le pourcentage) traduit ce poids :
      une composante a 30% pese trois fois plus qu'une composante a 10% dans le score final. Chaque composante donne d'abord une
      note sur 100 (via sa formule), puis on la multiplie par son poids pour obtenir sa <b>contribution en points</b>. La somme des
      5 contributions donne le score final sur 100. Le total des poids fait exactement 100% (30+25+20+15+10), donc le score reste
      toujours sur une echelle de 0 a 100.
    </div>

    <h6>Les 5 composantes du score (avec leur formule)</h6>
    <div class="pr-formula">
      <div class="pr-comp">
        <span class="pr-comp-badge" style="background:#23408F">30%</span>
        <span class="pr-comp-txt"><b>Taux de non-conformite</b> - part des critères non satisfaisants (NCNS) sur l'ensemble des critères évalués. Plus un opérateur accumule de NCNS, plus son risque monte.
          <div class="pr-calc">Formule : (NCNS / (NCNS + NCS)) x 100</div>
          <div class="pr-calc-ex">Exemple : 6 NCNS et 14 NCS &rarr; 6/20 = <b>30/100</b>. Contribution au score : 30 x 30% = <b>9 points</b>.</div>
          <div class="pr-why"><i class="bi bi-arrow-return-right"></i> <b>Pourquoi 30% ?</b> C'est le poids le plus fort : le taux de non-conformite est l'indicateur le plus direct du niveau de securite d'un operateur.</div>
        </span>
      </div>
      <div class="pr-comp">
        <span class="pr-comp-badge" style="background:#D32F2F">25%</span>
        <span class="pr-comp-txt"><b>Gravite des non-conformites</b> - poids des fiches critiques et majeures (matrice OACI Doc 9859). Une critique pèse bien plus qu'une mineure. C'est une moyenne pondérée de la gravité des fiches.
          <div class="pr-calc">Formule : ((critiques x 100) + (majeures x 60) + (mineures x 25)) / nombre total de fiches</div>
          <div class="pr-calc-ex">Exemple : 1 critique + 2 majeures + 1 mineure (4 fiches) &rarr; (100 + 120 + 25)/4 = <b>61/100</b>. Contribution : 61 x 25% = <b>15,3 points</b>.</div>
          <div class="pr-why"><i class="bi bi-arrow-return-right"></i> <b>Pourquoi 25% ?</b> Une non-conformite critique peut mettre en jeu la securite des vols : la gravite pese donc presque autant que le volume de NCNS.</div>
        </span>
      </div>
      <div class="pr-comp">
        <span class="pr-comp-badge" style="background:#E8890C">20%</span>
        <span class="pr-comp-txt"><b>Delais depasses</b> - proportion de délais de mise en conformité non respectés. Un opérateur qui traîne à corriger est plus à risque.
          <div class="pr-calc">Formule : (delais depasses / delais evalues) x 100</div>
          <div class="pr-calc-ex">Exemple : 2 delais depasses sur 5 evalues &rarr; 2/5 = <b>40/100</b>. Contribution : 40 x 20% = <b>8 points</b>.</div>
          <div class="pr-why"><i class="bi bi-arrow-return-right"></i> <b>Pourquoi 20% ?</b> Le non-respect des delais montre un manque de reactivite de l'operatur, mais reste moins grave que la nature des ecarts eux-memes.</div>
        </span>
      </div>
      <div class="pr-comp">
        <span class="pr-comp-badge" style="background:#b58a00">15%</span>
        <span class="pr-comp-txt"><b>Non-conformites ouvertes</b> - part des fiches encore non fermées (statut différent de "Fermé"). Des écarts qui restent ouverts maintiennent le risque élevé.
          <div class="pr-calc">Formule : (fiches ouvertes / nombre total de fiches) x 100</div>
          <div class="pr-calc-ex">Exemple : 3 ouvertes sur 4 fiches &rarr; 3/4 = <b>75/100</b>. Contribution : 75 x 15% = <b>11,25 points</b>.</div>
          <div class="pr-why"><i class="bi bi-arrow-return-right"></i> <b>Pourquoi 15% ?</b> Une NC ouverte est un risque persistant, mais elle est deja comptee en partie dans le taux de non-conformite : son poids est donc modere pour eviter de compter deux fois.</div>
        </span>
      </div>
      <div class="pr-comp">
        <span class="pr-comp-badge" style="background:#6b7a90">10%</span>
        <span class="pr-comp-txt"><b>Anciennete du dernier audit</b> - temps écoulé depuis le dernier audit réalisé (echelle sur 24 mois). Moins un opérateur est audité récemment, moins l'autorité a de visibilité sur son niveau réel de sécurité.
          <div class="pr-calc">Formule : (mois écoulés / 24) x 100, plafonne a 100</div>
          <div class="pr-calc-ex">Exemple : dernier audit il y a 12 mois &rarr; 12/24 = <b>50/100</b>. Contribution : 50 x 10% = <b>5 points</b>.</div>
          <div class="pr-why"><i class="bi bi-arrow-return-right"></i> <b>Pourquoi 10% ?</b> C'est le poids le plus faible : l'anciennete est un indicateur de visibilite, pas de securite reelle. Elle nuance le score sans le dominer.</div>
        </span>
      </div>
    </div>
    <div class="pr-formula" style="border-left:3px solid #23408F">
      <b style="color:#23408F">Score final = somme des 5 contributions</b>
      <div class="pr-calc-ex">Avec les exemples ci-dessus : 9 + 15,3 + 8 + 11,25 + 5 = <b>48,55 / 100</b> &rarr; categorie <span class="risk-badge rb-moyen">Moyen</span></div>
    </div>

    <h6>Les categories de risque et ce qu'elles impliquent a l'ANAC Gabon</h6>
    <p class="mb-2">Le score determine une categorie, qui oriente la <b>frequence et l'intensite de la surveillance</b>. Voici concretement ce que chaque niveau signifie pour l'ANAC :</p>

    <div class="pr-cat-detail" style="border-left:4px solid #1E9C4B">
      <div class="pr-cat-t" style="color:#1E9C4B">Faible (0 à 25) - Cycle standard</div>
      <div class="pr-cat-d">L'operateur respecte bien la reglementation. L'ANAC applique le <b>programme de surveillance continue</b> : audits planifies selon le cycle habituel (par exemple une inspection annuelle de surveillance continue), sans mesure particuliere. C'est le regime de base pour un operateur sain.</div>
    </div>
    <div class="pr-cat-detail" style="border-left:4px solid #F3C300">
      <div class="pr-cat-t" style="color:#b58a00">Moyen (26 à 50) - Surveillance renforcée</div>
      <div class="pr-cat-d">Quelques signaux d'alerte. L'ANAC <b>augmente la vigilance</b> : suivi plus attentif des actions correctives, verification que les delais sont tenus, et eventuellement un point de controle intermediaire entre deux audits programmes. On garde l'operateur "sous l'oeil".</div>
    </div>
    <div class="pr-cat-detail" style="border-left:4px solid #E8890C">
      <div class="pr-cat-t" style="color:#E8890C">Élevé (51 à 75) - Audits rapprochés</div>
      <div class="pr-cat-d">Le risque est significatif. L'ANAC <b>rapproche les audits</b> (frequence augmentee, par exemple semestrielle au lieu d'annuelle), elargit le perimetre inspecte (plus de domaines OACI couverts) et peut convoquer l'operateur pour un plan d'action formel. Objectif : ramener l'operateur a un niveau acceptable.</div>
    </div>
    <div class="pr-cat-detail" style="border-left:4px solid #D32F2F">
      <div class="pr-cat-t" style="color:#D32F2F">Critique (76 à 100) - Surveillance prioritaire</div>
      <div class="pr-cat-d">Risque majeur pour la securite. L'ANAC place l'operateur en <b>priorite absolue</b> : audits inopines, mesures conservatoires possibles (restriction, suspension ou retrait de certificat/autorisation en cas de danger avere), suivi rapproche de chaque non-conformite. C'est le niveau ou l'autorite peut agir sur l'exploitation elle-meme.</div>
    </div>

    <div class="mt-2 p-2" style="background:#eef4ff;border-left:4px solid #23408F;border-radius:6px;font-size:.8rem">
      <i class="bi bi-info-circle me-1"></i>Ces actions sont des <b>orientations</b> : la decision finale (frequence exacte, mesures) revient toujours au jugement du Chef Inspecteur et aux procedures de l'ANAC. Le score aide a prioriser, il ne remplace pas la decision humaine.
    </div>

    <div class="mt-3 p-2" style="background:#eef4ff;border-left:4px solid #23408F;border-radius:6px;font-size:.82rem">
      <i class="bi bi-shield-check me-1"></i>Le score est recalcule <b>automatiquement cote serveur</b> a chaque ouverture de la page, a partir des donnees reelles de surveillance. Il ne peut pas etre modifie manuellement.
    </div>
  </div>
</div>

<!-- KPI -->
<div class="row g-2 mb-3" id="prKpi"></div>

<div class="pr-card mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-md-6">
      <label class="pr-th d-block mb-1">Rechercher un operateur</label>
      <select id="fOrga" class="form-select form-select-sm"><option value="">Tous les opérateurs</option></select>
    </div>
    <div class="col-md-3">
      <label class="pr-th d-block mb-1">Categorie de risque</label>
      <select id="fCat" class="form-select form-select-sm">
        <option value="">Toutes catégories</option>
        <option value="critique">Critique</option>
        <option value="eleve">Eleve</option>
        <option value="moyen">Moyen</option>
        <option value="faible">Faible</option>
      </select>
    </div>
    <div class="col-md-3">
      <button class="btn btn-sm w-100" id="btnResetPr" style="background:linear-gradient(135deg,#D32F2F,#b02525);color:#fff;border:none;font-weight:600"><i class="bi bi-arrow-counterclockwise me-1"></i>Réinitialiser les filtres</button>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-7">
    <div class="pr-card">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <b style="color:#23408F"><i class="bi bi-list-ol me-1"></i>Opérateurs par niveau de risque</b>
        <span class="text-muted" style="font-size:.78rem" id="prCount"></span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle" id="prTable">
          <thead><tr>
            <th class="pr-th">Operateur</th>
            <th class="pr-th text-center">Audits</th>
            <th class="pr-th text-center">FNC</th>
            <th class="pr-th" style="min-width:130px">Score de risque</th>
            <th class="pr-th">Categorie</th>
            <th class="pr-th">Surveillance</th>
            <th class="pr-th text-center">Detail</th>
          </tr></thead>
          <tbody></tbody>
        </table>
      </div>
      <div class="mt-1" style="font-size:.74rem;color:#8a97ad">
        <i class="bi bi-info-circle me-1"></i>Dans la colonne <b>FNC</b>, la mention <span style="color:#D32F2F;font-weight:700">(n crit.)</span> indique le nombre de non-conformites <b>critiques</b> parmi les fiches de l'operateur (ce sont les plus graves selon la matrice OACI).
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="pr-card mb-3">
      <b style="color:#23408F"><i class="bi bi-pie-chart me-1"></i>Répartition par catégorie</b>
      <div style="height:190px;position:relative;margin-top:8px"><canvas id="chCat"></canvas></div>
    </div>
    <div class="pr-card">
      <b style="color:#23408F"><i class="bi bi-bar-chart me-1"></i>Top 8 opérateurs les plus à risque</b>
      <div style="height:230px;position:relative;margin-top:8px"><canvas id="chTop"></canvas></div>
    </div>
  </div>
</div>

<!-- MODALE DETAIL -->
<div class="modal fade" id="prDetail" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content" style="border:none;border-radius:14px;overflow:hidden">
      <div class="modal-header" style="background:linear-gradient(135deg,#23408F,#1b3576);border:none">
        <h5 class="modal-title text-white"><i class="bi bi-shield-exclamation me-2" style="color:#F3C300"></i><span id="prDetailNom">Detail</span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="prDetailBody" style="background:#f5f7fa"></div>
    </div>
  </div>
</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const OPS = <?php echo json_encode($OPERATEURS, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
const POIDS = <?php echo json_encode($POIDS); ?>;

function esc(s){ return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }
function fmtDate(s){ if(!s||String(s).substring(0,10)==='0000-00-00') return '-'; const p=String(s).substring(0,10).split('-'); return p.length===3?(p[2]+'/'+p[1]+'/'+p[0]):s; }

const CAT_COLOR={faible:'#1E9C4B',moyen:'#F3C300',eleve:'#E8890C',critique:'#D32F2F'};
const CAT_LABEL={faible:'Faible',moyen:'Moyen',eleve:'Élevé',critique:'Critique'};
const CAT_CLASS={faible:'rb-faible',moyen:'rb-moyen',eleve:'rb-eleve',critique:'rb-critique'};

/* Espace explicatif pliable */
$('#explainToggle').on('click',function(){
  $('#explainBody').slideToggle(180);
  $('#explainChevron').css('transform', $('#explainBody').is(':visible')?'rotate(0deg)':'rotate(-90deg)');
});

/* Liste filtree commune a tous les visuels (KPI, graphiques, tableau) */
function getFiltered(){
  const cat=$('#fCat').val(), org=$('#fOrga').val();
  return OPS.filter(function(o){
    if(cat && o.categorie!==cat) return false;
    if(org && String(o.idorga)!==String(org)) return false;
    return true;
  });
}

/* KPI (calcules sur la liste filtree) */
function renderKpi(){
  const list=getFiltered();
  const n=list.length;
  const par={faible:0,moyen:0,eleve:0,critique:0};
  list.forEach(function(o){ par[o.categorie]++; });
  const moyen = n? (list.reduce(function(s,o){return s+Number(o.score);},0)/n).toFixed(1) : '0';
  const cards=[
    {v:n, l:'Operateurs profiles', c:'#23408F', k:'profiles'},
    {v:par.critique, l:'Risque critique', c:'#D32F2F', k:'critique'},
    {v:par.eleve, l:'Risque eleve', c:'#E8890C', k:'eleve'},
    {v:par.moyen, l:'Risque moyen', c:'#b58a00', k:'moyen'},
    {v:par.faible, l:'Risque faible', c:'#1E9C4B', k:'faible'},
    {v:moyen, l:'Score moyen', c:'#6b7a90', k:'moyenne'}
  ];
  $('#prKpi').html(cards.map(function(k){
    return '<div class="col-6 col-md-4 col-lg-2"><div class="kpi-card kpi-click" data-k="'+k.k+'" style="cursor:pointer" title="Cliquez pour l\'explication"><div class="kpi-v" style="color:'+k.c+'">'+k.v+'</div><div class="kpi-l">'+k.l+' <i class="bi bi-info-circle" style="font-size:.7rem;opacity:.5"></i></div></div></div>';
  }).join(''));
}

/* Explication de chaque KPI au clic */
const KPI_EXPL={
  profiles:{t:'Operateurs profiles',d:'Nombre d\'opérateurs ayant au moins un audit réalisé ou planifié, donc pour lesquels un profil de risque est calculable. Un opérateur sans aucun audit n\'apparaît pas ici (aucune donnée de surveillance à analyser).'},
  critique:{t:'Risque critique (76-100)',d:'Nombre d\'opérateurs dont le score dépasse 75. Ce sont les opérateurs à surveiller en priorité absolue : audits inopinés, mesures conservatoires possibles.'},
  eleve:{t:'Risque élevé (51-75)',d:'Nombre d\'opérateurs dont le score est entre 51 et 75. Ils nécessitent des audits rapprochés et un périmètre de contrôle élargi.'},
  moyen:{t:'Risque moyen (26-50)',d:'Nombre d\'opérateurs dont le score est entre 26 et 50. Ils font l\'objet d\'une surveillance renforcée (vigilance accrue sur les actions correctives).'},
  faible:{t:'Risque faible (0-25)',d:'Nombre d\'opérateurs dont le score est inférieur ou égal à 25. Ils suivent le cycle de surveillance standard, sans mesure particulière.'},
  moyenne:{t:'Score moyen',d:'Moyenne des scores de tous les operateurs affiches. C\'est un indicateur global du niveau de risque du secteur surveille. <br><br><b>Comment le lire (echelle sur 100) :</b><br>'
    +'<span style="color:#1E9C4B">&#9679; 0 a 25 : secteur sain</span> - la surveillance de l\'ANAC est efficace, les operateurs sont majoritairement conformes.<br>'
    +'<span style="color:#b58a00">&#9679; 26 a 50 : secteur a surveiller</span> - risque moyen, vigilance a maintenir.<br>'
    +'<span style="color:#E8890C">&#9679; 51 a 75 : secteur tendu</span> - beaucoup d\'operateurs a risque, renforcer la surveillance.<br>'
    +'<span style="color:#D32F2F">&#9679; 76 a 100 : secteur critique</span> - situation preoccupante, action prioritaire de l\'autorite.<br><br>'
    +'<b>Conclusion pour l\'ANAC Gabon :</b> un score moyen dans la tranche <b>26-50</b> signifie que le systeme de supervision est globalement <b>maitrise mais perfectible</b> : la majorite des operateurs sont sous controle, mais certains demandent une attention renforcee. C\'est un niveau courant et gerable pour une autorite active. Un score qui baisse dans le temps est le signe d\'une supervision qui porte ses fruits.'}
};
$(document).on('click','.kpi-click',function(){
  const k=$(this).attr('data-k'), e=KPI_EXPL[k]; if(!e) return;
  Swal.fire({icon:'info',title:e.t,html:'<div style="text-align:left;font-size:.9rem;line-height:1.6">'+e.d+'</div>',confirmButtonColor:'#23408F',confirmButtonText:'Compris'});
});

/* Remplir le filtre operateur */
(function(){
  const opts=OPS.slice().sort(function(a,b){return a.nom.localeCompare(b.nom);})
    .map(function(o){ return '<option value="'+o.idorga+'">'+esc(o.nom)+(o.sigle?' ('+esc(o.sigle)+')':'')+'</option>'; }).join('');
  $('#fOrga').append(opts);
})();

/* Tableau */
function renderTable(){
  const list=getFiltered();
  $('#prCount').text(list.length+' operateur(s)');
  if(!list.length){ $('#prTable tbody').html('<tr><td colspan="7" class="text-center text-muted py-4">Aucun operateur</td></tr>'); return; }
  $('#prTable tbody').html(list.map(function(o){
    const col=CAT_COLOR[o.categorie];
    return '<tr>'
      +'<td><div style="font-weight:600;font-size:.85rem">'+esc(o.nom)+'</div>'+(o.sigle?'<div class="text-muted" style="font-size:.72rem">'+esc(o.sigle)+'</div>':'')+'</td>'
      +'<td class="text-center">'+o.nb_audits+'</td>'
      +'<td class="text-center">'+o.nb_fnc+(Number(o.nb_critique)>0?' <span style="color:#D32F2F;font-weight:700" title="'+o.nb_critique+' non-conformite(s) critique(s)">('+o.nb_critique+' crit.)</span>':'')+'</td>'
      +'<td><div class="d-flex align-items-center gap-2"><div class="score-bar"><div style="width:'+o.score+'%;background:'+col+'"></div></div><b style="color:'+col+';font-size:.85rem">'+o.score+'</b></div></td>'
      +'<td><span class="risk-badge '+CAT_CLASS[o.categorie]+'">'+CAT_LABEL[o.categorie]+'</span></td>'
      +'<td style="font-size:.78rem;color:#5b6b85">'+esc(o.frequence)+'</td>'
      +'<td class="text-center"><button class="btn btn-sm btn-outline-primary btn-detail" data-id="'+o.idorga+'"><i class="bi bi-eye"></i></button></td>'
      +'</tr>';
  }).join(''));
}

/* Recalcule TOUS les visuels selon les filtres (KPI + graphiques + tableau) */
function renderAll(){ renderKpi(); renderTable(); renderCharts(); }
$('#fCat, #fOrga').on('change', renderAll);
$('#btnResetPr').on('click',function(){ $('#fCat').val(''); $('#fOrga').val(''); renderAll(); });

/* Graphiques */
let chCat=null, chTop=null;
// Plugin : affiche la valeur au centre de chaque portion du donut
const dataLabelPlugin={
  id:'dataLabel',
  afterDatasetsDraw:function(chart){
    const ctx=chart.ctx;
    chart.data.datasets.forEach(function(ds,di){
      const meta=chart.getDatasetMeta(di);
      meta.data.forEach(function(el,i){
        const val=ds.data[i];
        if(!val) return;
        const pos=el.tooltipPosition ? el.tooltipPosition() : el.getCenterPoint();
        ctx.save();
        ctx.fillStyle='#fff'; ctx.font='bold 13px Candara,Arial';
        ctx.textAlign='center'; ctx.textBaseline='middle';
        ctx.fillText(val, pos.x, pos.y);
        ctx.restore();
      });
    });
  }
};
function renderCharts(){
  const list=getFiltered();
  const par={faible:0,moyen:0,eleve:0,critique:0};
  list.forEach(function(o){ par[o.categorie]++; });
  if(chCat) chCat.destroy();
  chCat=new Chart(document.getElementById('chCat'),{
    type:'doughnut',
    data:{labels:['Faible','Moyen','Eleve','Critique'],
      datasets:[{data:[par.faible,par.moyen,par.eleve,par.critique],
        backgroundColor:[CAT_COLOR.faible,CAT_COLOR.moyen,CAT_COLOR.eleve,CAT_COLOR.critique],
        borderWidth:2,borderColor:'#fff'}]},
    options:{maintainAspectRatio:false,cutout:'58%',plugins:{legend:{position:'right',labels:{font:{size:11},boxWidth:12}}}},
    plugins:[dataLabelPlugin]
  });
  const top=list.slice(0,8);
  if(chTop) chTop.destroy();
  chTop=new Chart(document.getElementById('chTop'),{
    type:'bar',
    data:{labels:top.map(function(o){return o.sigle||o.nom.substring(0,14);}),
      datasets:[{data:top.map(function(o){return o.score;}),
        backgroundColor:top.map(function(o){return CAT_COLOR[o.categorie];})}]},
    options:{maintainAspectRatio:false,indexAxis:'y',plugins:{legend:{display:false}},scales:{x:{beginAtZero:true,max:100}}}
  });
}

/* Detail d'un operateur */
$(document).on('click','.btn-detail',function(){
  const id=$(this).attr('data-id');
  const o=OPS.find(function(x){return String(x.idorga)===String(id);});
  if(!o) return;
  $('#prDetailNom').text(o.nom);
  const comp=[
    {l:'Taux de non-conformite', v:o.c_nc, p:POIDS.nc, c:'#23408F',
     f:'(NCNS / (NCNS + NCS)) x 100',
     d:'NCNS = '+o.tot_ncns+' , NCS = '+o.tot_ncs},
    {l:'Gravite des non-conformites', v:o.c_gravite, p:POIDS.gravite, c:'#D32F2F',
     f:'((crit. x100)+(maj. x60)+(min. x25)) / nb fiches',
     d:'critiques = '+o.nb_critique+' , majeures = '+o.nb_majeur+' , mineures = '+o.nb_mineur+' (sur '+o.nb_fnc+' fiches)'},
    {l:'Delais depasses', v:o.c_delais, p:POIDS.delais, c:'#E8890C',
     f:'(delais depasses / delais evalues) x 100',
     d:'dépassés = '+o.nb_delais_depasses+' , évalués = '+o.nb_delais_evalues},
    {l:'Non-conformites ouvertes', v:o.c_ouvertes, p:POIDS.ouvertes, c:'#b58a00',
     f:'(fiches ouvertes / nb fiches) x 100',
     d:'ouvertes = '+o.nb_ouvertes+' (sur '+o.nb_fnc+' fiches)'},
    {l:'Anciennete du dernier audit', v:o.c_anciennete, p:POIDS.anciennete, c:'#6b7a90',
     f:'(mois écoulés / 24) x 100',
     d:(o.mois_ecoules!=null? (o.mois_ecoules+' mois écoulés') : 'aucun audit réalisé')}
  ];
  const col=CAT_COLOR[o.categorie];
  let h='<div class="text-center mb-3">'
    +'<div style="font-size:2.4rem;font-weight:800;color:'+col+'">'+o.score+'<span style="font-size:1rem;color:#9aa7bd">/100</span></div>'
    +'<span class="risk-badge '+CAT_CLASS[o.categorie]+'">'+CAT_LABEL[o.categorie]+'</span>'
    +'<div class="text-muted mt-1" style="font-size:.82rem">Surveillance recommandée : '+esc(o.frequence)+'</div>'
    +'</div>';

  // Tableau complet du calcul, avec donnees reelles + barre de proportion
  h+='<div style="background:#fff;border-radius:10px;padding:10px;overflow-x:auto">';
  h+='<div class="pr-th mb-2"><i class="bi bi-calculator me-1"></i>Détail du calcul du score</div>';
  h+='<table class="table table-sm align-middle" style="font-size:.78rem;margin-bottom:0">'
    +'<thead><tr style="background:#f5f7fa">'
    +'<th>Composante</th><th>Formule</th><th>Données réelles</th><th class="text-center">Note /100</th><th style="min-width:110px">Proportion</th><th class="text-center">Poids</th><th class="text-center">Contribution</th>'
    +'</tr></thead><tbody>';
  let totContrib=0;
  comp.forEach(function(c){
    const contrib=Number(c.v)*c.p/100;
    totContrib+=contrib;
    h+='<tr>'
      +'<td><span style="display:inline-block;width:9px;height:9px;border-radius:2px;background:'+c.c+';margin-right:5px"></span>'+c.l+'</td>'
      +'<td style="font-size:.72rem;color:#6b7a90">'+c.f+'</td>'
      +'<td style="font-size:.74rem;color:#2C3E50">'+esc(c.d)+'</td>'
      +'<td class="text-center"><b>'+c.v+'</b></td>'
      +'<td><div class="score-bar"><div style="width:'+c.v+'%;background:'+c.c+'"></div></div></td>'
      +'<td class="text-center">'+c.p+'%</td>'
      +'<td class="text-center"><b style="color:'+c.c+'">'+contrib.toFixed(2)+'</b></td>'
      +'</tr>';
  });
  h+='</tbody><tfoot><tr style="background:#eef4ff;font-weight:800">'
    +'<td colspan="6" class="text-end">SCORE FINAL (somme des contributions)</td>'
    +'<td class="text-center" style="color:'+col+';font-size:1rem">'+totContrib.toFixed(1)+'</td>'
    +'</tr></tfoot></table>';
  h+='</div>';

  // Donnees brutes de l'operateur
  h+='<div class="pr-th mt-3 mb-2">Données de surveillance de cet opérateur</div>';
  h+='<div class="row g-2">'
    +'<div class="col-6 col-md-3"><div class="kpi-card text-center"><div class="kpi-v" style="font-size:1.1rem">'+o.nb_audits+'</div><div class="kpi-l">Audits</div></div></div>'
    +'<div class="col-6 col-md-3"><div class="kpi-card text-center"><div class="kpi-v" style="font-size:1.1rem">'+o.nb_fnc+'</div><div class="kpi-l">Fiches NC</div></div></div>'
    +'<div class="col-6 col-md-3"><div class="kpi-card text-center"><div class="kpi-v" style="font-size:1.1rem;color:#D32F2F">'+o.nb_critique+'</div><div class="kpi-l">Critiques</div></div></div>'
    +'<div class="col-6 col-md-3"><div class="kpi-card text-center"><div class="kpi-v" style="font-size:1.1rem">'+o.nb_ouvertes+'</div><div class="kpi-l">NC ouvertes</div></div></div>'
    +'<div class="col-6 col-md-6"><div class="kpi-card text-center"><div class="kpi-v" style="font-size:1.1rem">'+o.nb_delais_depasses+'</div><div class="kpi-l">Delais depasses</div></div></div>'
    +'<div class="col-6 col-md-6"><div class="kpi-card text-center"><div class="kpi-v" style="font-size:1rem">'+fmtDate(o.dernier_audit)+'</div><div class="kpi-l">Dernier audit</div></div></div>'
    +'</div>';
  $('#prDetailBody').html(h);
  new bootstrap.Modal('#prDetail').show();
});

/* Export Excel */
$('#btnXls').on('click',function(){
  let html='<table border="1"><tr><th>Operateur</th><th>Sigle</th><th>Audits</th><th>FNC</th><th>Critiques</th><th>NC ouvertes</th><th>Score</th><th>Categorie</th><th>Surveillance</th></tr>';
  OPS.forEach(function(o){
    html+='<tr><td>'+esc(o.nom)+'</td><td>'+esc(o.sigle||'')+'</td><td>'+o.nb_audits+'</td><td>'+o.nb_fnc+'</td><td>'+o.nb_critique+'</td><td>'+o.nb_ouvertes+'</td><td>'+o.score+'</td><td>'+CAT_LABEL[o.categorie]+'</td><td>'+esc(o.frequence)+'</td></tr>';
  });
  html+='</table>';
  const blob=new Blob(['\ufeff'+html],{type:'application/vnd.ms-excel'});
  const a=document.createElement('a'); a.href=URL.createObjectURL(blob);
  a.download='Profil_risque_operateurs_'+new Date().toISOString().substring(0,10)+'.xls';
  a.click();
});

/* Export PDF (impression) */
$('#btnPdf').on('click',function(){ window.print(); });

/* Init */
renderAll();
</script>