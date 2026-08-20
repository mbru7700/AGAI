<?php
/**
 * ============================================================
 * AGAI - ANAC Gabon
 * Classe LogRotator - Rotation et purge des journaux
 * ------------------------------------------------------------
 * Regroupe toute la logique de "menage" des journaux applicatifs :
 *
 *  1) rotateFileLogs()     - fait pivoter logs/app.log et logs/errors.log
 *                             (declenchement par taille ET/OU quotidien),
 *                             compresse l'archive (gzip), purge les archives
 *                             plus vieilles que LOG_RETENTION_DAYS.
 *  2) purgeLoginAttempts()  - supprime les lignes de la table login_attempts
 *                             plus vieilles que LOG_RETENTION_DAYS (donnees
 *                             purement operationnelles, pas de valeur d'audit
 *                             au-dela de la fenetre de detection brute-force).
 *  3) archiveAuditLogs()    - export CSV.gz (hors /public, dans storage/archives)
 *                             PUIS suppression des lignes audit_logs plus
 *                             vieilles que AUDIT_LOG_RETENTION_DAYS. La
 *                             tracabilite est conservee (fichier archive),
 *                             jamais de suppression "seche" du journal d'audit.
 *  4) runAll()              - orchestre les 3 taches avec verrou anti-execution
 *                             concurrente (cron + declenchement manuel admin).
 *
 * Points d'entree :
 *  - Tache planifiee : cron/maintenance_logs.php (cron Linux / Planificateur
 *    de taches Windows en local XAMPP)
 *  - Declenchement manuel : action 'purger_logs' de
 *    app/endpoints/login-attempts.php (reserve role admin)
 *  - Filet de securite : appel probabiliste (rotation fichiers uniquement)
 *    depuis config/config.php a chaque requete
 *
 * @package AGAI
 * @author  ANAC Gabon
 */
class LogRotator
{
    /** Fichiers de logs geres : nom logique => chemin absolu. */
    private static function files(): array
    {
        return [
            'app'    => LOG_PATH . '/app.log',
            'errors' => LOG_PATH . '/errors.log',
        ];
    }

    /**
     * Pose un verrou exclusif non bloquant pour empecher deux executions
     * paralleles (ex : cron ET clic admin au meme moment).
     * Retourne le handle de fichier (a garder ouvert) ou null si deja verrouille.
     */
    private static function acquireLock()
    {
        $lockFile = LOG_PATH . '/.rotation.lock';
        $fp = @fopen($lockFile, 'c');
        if (!$fp) {
            return null;
        }
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            return null;
        }
        return $fp;
    }

    private static function releaseLock($fp): void
    {
        if ($fp) {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * Fait pivoter un fichier de log si necessaire :
     *  - taille courante >= LOG_MAX_SIZE_MB (declenchement immediat)
     *  - OU la derniere rotation ne date pas d'aujourd'hui (rotation quotidienne
     *    forcee, evite d'accumuler des mois de logs "juste en dessous" du seuil)
     *
     * L'archive est copiee puis le fichier original est vide (et non supprime,
     * pour ne pas casser un descripteur de fichier deja ouvert par error_log()).
     * L'archive est ensuite compressee en gzip pour limiter l'espace disque.
     */
    public static function rotateFileLogs(): array
    {
        $result = ['rotated' => [], 'skipped' => [], 'purged_archives' => 0];

        $archiveDir = LOG_PATH . '/archive';
        if (!is_dir($archiveDir)) {
            @mkdir($archiveDir, 0755, true);
        }
        $markerDir = LOG_PATH . '/.rotation_markers';
        if (!is_dir($markerDir)) {
            @mkdir($markerDir, 0755, true);
        }

        $maxBytes = (defined('LOG_MAX_SIZE_MB') ? LOG_MAX_SIZE_MB : 5) * 1024 * 1024;
        $today    = date('Y-m-d');

        foreach (self::files() as $name => $path) {
            if (!is_file($path)) {
                $result['skipped'][] = $name;
                continue;
            }

            $size       = filesize($path);
            $markerFile = $markerDir . '/' . $name . '.date';
            $lastDate   = is_file($markerFile) ? trim((string) @file_get_contents($markerFile)) : '';

            $needRotate = ($size >= $maxBytes) || ($size > 0 && $lastDate !== $today);
            if (!$needRotate) {
                $result['skipped'][] = $name;
                continue;
            }

            $stamp   = date('Y-m-d_His');
            $archive = $archiveDir . "/{$name}-{$stamp}.log";

            if (!@copy($path, $archive)) {
                $result['skipped'][] = $name;
                continue;
            }

            // Vide le fichier original (garde le meme inode : sûr avec error_log/file_put_contents)
            @file_put_contents($path, '');

            // Compression gzip de l'archive puis suppression du .log brut
            if (function_exists('gzencode')) {
                $data = @file_get_contents($archive);
                if ($data !== false) {
                    $gz = @gzencode($data, 9);
                    if ($gz !== false && @file_put_contents($archive . '.gz', $gz) !== false) {
                        @unlink($archive);
                        $archive .= '.gz';
                    }
                }
            }

            @file_put_contents($markerFile, $today);
            $result['rotated'][] = [
                'file'       => $name,
                'archive'    => basename($archive),
                'size_bytes' => $size,
            ];
        }

        // Purge des archives plus anciennes que LOG_RETENTION_DAYS
        $retentionDays = defined('LOG_RETENTION_DAYS') ? LOG_RETENTION_DAYS : 30;
        $limit = time() - ($retentionDays * 86400);
        $old = glob($archiveDir . '/*') ?: [];
        foreach ($old as $file) {
            if (is_file($file) && filemtime($file) < $limit) {
                if (@unlink($file)) {
                    $result['purged_archives']++;
                }
            }
        }

        return $result;
    }

    /**
     * Purge la table login_attempts au-dela de LOG_RETENTION_DAYS.
     * Donnees purement operationnelles (detection brute-force a court terme),
     * sans valeur d'audit une fois la fenetre de detection depassee.
     */
    public static function purgeLoginAttempts(): int
    {
        try {
            $db   = Database::getInstance();
            $days = defined('LOG_RETENTION_DAYS') ? LOG_RETENTION_DAYS : 30;
            $stmt = $db->prepare("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
            $stmt->execute([$days]);
            return $stmt->rowCount();
        } catch (Throwable $e) {
            error_log('LogRotator::purgeLoginAttempts : ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Archive (CSV compresse, hors /public) puis supprime les lignes audit_logs
     * plus anciennes que AUDIT_LOG_RETENTION_DAYS. La suppression ne se produit
     * JAMAIS sans que l'archive ait ete correctement ecrite sur disque au
     * prealable : la tracabilite du journal d'audit est toujours preservee.
     */
    public static function archiveAuditLogs(): array
    {
        $result = ['archived' => 0, 'archive_file' => null];

        try {
            $db   = Database::getInstance();
            $days = defined('AUDIT_LOG_RETENTION_DAYS') ? AUDIT_LOG_RETENTION_DAYS : 730;

            $stmt = $db->prepare(
                "SELECT * FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY idlog ASC"
            );
            $stmt->execute([$days]);
            $rows = $stmt->fetchAll();

            if (empty($rows)) {
                return $result;
            }

            $archiveDir = STORAGE_PATH . '/archives';
            if (!is_dir($archiveDir)) {
                @mkdir($archiveDir, 0755, true);
            }

            $csvPath = $archiveDir . '/audit_logs_' . date('Y-m-d_His') . '.csv';
            $fp = @fopen($csvPath, 'w');
            if (!$fp) {
                throw new RuntimeException("Impossible d'ecrire l'archive : $csvPath");
            }
            fputcsv($fp, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($fp, $row);
            }
            fclose($fp);

            // Compression gzip puis suppression du CSV brut
            $data = @file_get_contents($csvPath);
            if ($data === false) {
                throw new RuntimeException("Impossible de relire l'archive : $csvPath");
            }
            $gz = gzencode($data, 9);
            if ($gz === false || @file_put_contents($csvPath . '.gz', $gz) === false) {
                throw new RuntimeException("Impossible de compresser l'archive : $csvPath");
            }
            @unlink($csvPath);

            // Suppression UNIQUEMENT des lignes effectivement archivees (par idlog, par lots)
            $ids     = array_column($rows, 'idlog');
            $deleted = 0;
            foreach (array_chunk($ids, 500) as $chunk) {
                $in  = implode(',', array_fill(0, count($chunk), '?'));
                $del = $db->prepare("DELETE FROM audit_logs WHERE idlog IN ($in)");
                $del->execute($chunk);
                $deleted += $del->rowCount();
            }

            $result['archived']     = $deleted;
            $result['archive_file'] = basename($csvPath) . '.gz';
        } catch (Throwable $e) {
            error_log('LogRotator::archiveAuditLogs : ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Execute l'ensemble des taches de maintenance des journaux.
     * Point d'entree utilise par cron/maintenance_logs.php et par l'action
     * 'purger_logs' de l'endpoint login-attempts.php.
     */
    public static function runAll(): array
    {
        $lock = self::acquireLock();
        if ($lock === null) {
            return ['success' => false, 'message' => 'Une operation de maintenance des journaux est deja en cours.'];
        }

        try {
            $files   = self::rotateFileLogs();
            $purged  = self::purgeLoginAttempts();
            $archive = self::archiveAuditLogs();

            $summary = [
                'success'               => true,
                'files_rotated'         => $files['rotated'],
                'files_archives_purged' => $files['purged_archives'],
                'login_attempts_purged' => $purged,
                'audit_logs_archived'   => $archive['archived'],
                'audit_archive_file'    => $archive['archive_file'],
                'ran_at'                => date('Y-m-d H:i:s'),
            ];

            if (function_exists('logger')) {
                logger('Maintenance des journaux executee', 'info', $summary);
            }

            return $summary;
        } finally {
            self::releaseLock($lock);
        }
    }
}