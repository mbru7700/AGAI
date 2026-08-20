<?php
/**
 * Classe PdfMerger - Fusion automatique de documents PDF
 * ------------------------------------------------------------
 * PHP ne sait pas assembler des PDF nativement : un moteur est necessaire.
 * Cette classe en detecte un automatiquement, dans cet ordre :
 *
 *   1. Ghostscript   (binaire externe, gere tous les PDF, y compris scannes)
 *   2. FPDI + FPDF   (bibliotheque PHP installee via Composer dans /vendor)
 *
 * Si aucun moteur n'est disponible, la fusion echoue proprement et
 * l'appelant conserve le comportement de remplacement classique.
 *
 * Securite :
 *   - Aucun chemin fourni par le client n'est utilise : seuls des chemins
 *     construits par l'application sont acceptes (verification realpath).
 *   - Les arguments passes a Ghostscript sont echappes (escapeshellarg).
 *   - Le fichier produit est verifie (existence, taille, en-tete %PDF).
 */
class PdfMerger
{
    /** Chemins usuels de Ghostscript, testes dans l'ordre. */
    private const GS_CANDIDATS = [
        // Windows / XAMPP
        'C:\\Program Files\\gs\\gs10.03.1\\bin\\gswin64c.exe',
        'C:\\Program Files\\gs\\gs10.02.1\\bin\\gswin64c.exe',
        'C:\\Program Files\\gs\\gs10.01.2\\bin\\gswin64c.exe',
        'C:\\Program Files\\gs\\gs9.56.1\\bin\\gswin64c.exe',
        'C:\\Program Files (x86)\\gs\\gs9.56.1\\bin\\gswin32c.exe',
        // Linux / macOS
        '/usr/bin/gs',
        '/usr/local/bin/gs',
        '/opt/homebrew/bin/gs',
    ];

    /** Dernier message d'erreur, pour journalisation. */
    private static string $derniereErreur = '';

    public static function derniereErreur(): string
    {
        return self::$derniereErreur;
    }

    /**
     * Indique si un moteur de fusion est disponible sur ce serveur.
     * Utile pour informer l'administrateur dans l'interface.
     */
    public static function moteurDisponible(): ?string
    {
        if (self::cheminGhostscript() !== null) { return 'ghostscript'; }
        if (self::fpdiDisponible())             { return 'fpdi'; }
        return null;
    }

    /**
     * Fusionne plusieurs PDF en un seul, dans l'ordre fourni.
     *
     * @param string[] $sources    Chemins absolus des PDF a assembler (ordre de lecture).
     * @param string   $destination Chemin absolu du fichier resultat.
     * @return bool    true si la fusion a reussi.
     */
    public static function fusionner(array $sources, string $destination): bool
    {
        self::$derniereErreur = '';

        // Ne garder que des fichiers reellement presents et non vides
        $valides = [];
        foreach ($sources as $src) {
            $reel = realpath($src);
            if ($reel !== false && is_file($reel) && filesize($reel) > 0) {
                $valides[] = $reel;
            }
        }
        if (count($valides) === 0) {
            self::$derniereErreur = 'Aucun fichier source exploitable.';
            return false;
        }
        // Un seul fichier : simple copie, aucune fusion necessaire
        if (count($valides) === 1) {
            return @copy($valides[0], $destination);
        }

        $gs = self::cheminGhostscript();
        if ($gs !== null && self::fusionnerGhostscript($gs, $valides, $destination)) {
            return true;
        }
        if (self::fpdiDisponible() && self::fusionnerFpdi($valides, $destination)) {
            return true;
        }

        if (self::$derniereErreur === '') {
            self::$derniereErreur = 'Aucun moteur de fusion disponible (Ghostscript ou FPDI).';
        }
        return false;
    }

    /* ================================================================
     *  Moteur 1 : Ghostscript
     * ================================================================ */

    private static function cheminGhostscript(): ?string
    {
        // 1. Chemin explicite defini dans la configuration de l'application
        if (defined('GHOSTSCRIPT_PATH') && GHOSTSCRIPT_PATH !== '' && is_file(GHOSTSCRIPT_PATH)) {
            return GHOSTSCRIPT_PATH;
        }

        // 2. Detection dynamique sous Windows : toute version installee dans
        //    C:\Program Files\gs\<version>\bin est reconnue, sans avoir a
        //    modifier le code a chaque mise a jour de Ghostscript.
        foreach (['C:\\Program Files\\gs', 'C:\\Program Files (x86)\\gs'] as $racine) {
            if (!is_dir($racine)) { continue; }
            $versions = glob($racine . '\\gs*', GLOB_ONLYDIR) ?: [];
            // La version la plus recente d'abord
            rsort($versions, SORT_NATURAL);
            foreach ($versions as $dossier) {
                foreach (['gswin64c.exe', 'gswin32c.exe'] as $bin) {
                    $chemin = $dossier . '\\bin\\' . $bin;
                    if (is_file($chemin)) { return $chemin; }
                }
            }
        }

        // 3. Emplacements usuels (Linux, macOS, versions Windows connues)
        foreach (self::GS_CANDIDATS as $c) {
            if (is_file($c)) { return $c; }
        }

        // 4. Dernier recours : binaire present dans le PATH du systeme
        if (function_exists('exec')) {
            $sortie = []; $code = 1;
            @exec((stripos(PHP_OS_FAMILY, 'Windows') === 0 ? 'where gswin64c' : 'which gs') . ' 2>&1', $sortie, $code);
            if ($code === 0 && !empty($sortie[0]) && is_file(trim($sortie[0]))) {
                return trim($sortie[0]);
            }
        }
        return null;
    }

    private static function fusionnerGhostscript(string $gs, array $sources, string $destination): bool
    {
        if (!function_exists('exec')) {
            self::$derniereErreur = 'La fonction exec() est desactivee sur ce serveur.';
            return false;
        }

        $cmd = escapeshellarg($gs)
             . ' -dBATCH -dNOPAUSE -dQUIET -dSAFER'
             . ' -sDEVICE=pdfwrite'
             . ' -dPDFSETTINGS=/prepress'
             . ' -sOutputFile=' . escapeshellarg($destination);
        foreach ($sources as $s) { $cmd .= ' ' . escapeshellarg($s); }

        $sortie = [];
        $code   = 1;
        @exec($cmd . ' 2>&1', $sortie, $code);

        if ($code === 0 && self::pdfValide($destination)) { return true; }

        self::$derniereErreur = 'Ghostscript : code ' . $code . ' - ' . implode(' | ', array_slice($sortie, 0, 3));
        if (is_file($destination) && !self::pdfValide($destination)) { @unlink($destination); }
        return false;
    }

    /* ================================================================
     *  Moteur 2 : FPDI + FPDF (Composer)
     * ================================================================ */

    private static function fpdiDisponible(): bool
    {
        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (is_file($autoload)) { require_once $autoload; }
        return class_exists('\setasign\Fpdi\Fpdi');
    }

    private static function fusionnerFpdi(array $sources, string $destination): bool
    {
        try {
            $pdf = new \setasign\Fpdi\Fpdi();
            foreach ($sources as $fichier) {
                $nbPages = $pdf->setSourceFile($fichier);
                for ($p = 1; $p <= $nbPages; $p++) {
                    $tpl  = $pdf->importPage($p);
                    $spec = $pdf->getTemplateSize($tpl);
                    $pdf->AddPage($spec['orientation'], [$spec['width'], $spec['height']]);
                    $pdf->useTemplate($tpl);
                }
            }
            $pdf->Output($destination, 'F');
            return self::pdfValide($destination);
        } catch (Throwable $e) {
            self::$derniereErreur = 'FPDI : ' . $e->getMessage();
            if (is_file($destination) && !self::pdfValide($destination)) { @unlink($destination); }
            return false;
        }
    }

    /* ================================================================
     *  Verification du resultat
     * ================================================================ */

    private static function pdfValide(string $chemin): bool
    {
        if (!is_file($chemin) || filesize($chemin) < 100) { return false; }
        $fh = @fopen($chemin, 'rb');
        if (!$fh) { return false; }
        $entete = fread($fh, 5);
        fclose($fh);
        return $entete === '%PDF-';
    }
}