<!-- cron/maintenance_logs.php (nouveau) – Point d'entrée CLI-only, à planifier :

Windows/XAMPP local : Planificateur de tâches → C:\xampp\php\php.exe
 C:\xampp\htdocs\AGAI\cron\maintenance_logs.php

Linux prod : crontab -e → 0 2 * * * /usr/bin/php /var/www/AGAI/cron/maintenance_logs.php -->





<?php
/**
 * ============================================================
 * AGAI - ANAC Gabon
 * Script de maintenance planifiee des journaux
 * ------------------------------------------------------------
 * A executer EXCLUSIVEMENT en ligne de commande (CLI), jamais via le web.
 * Fait pivoter logs/app.log et logs/errors.log, purge les anciennes
 * tentatives de connexion et archive les journaux d'audit expires.
 *
 * Ce fichier vit hors de /public : il n'est donc deja pas accessible
 * depuis internet (voir la configuration .htaccess a la racine, qui
 * route tout le trafic web vers /public). Le controle php_sapi_name()
 * ci-dessous est une securite supplementaire en profondeur.
 *
 * ------------------------------------------------------------
 * PLANIFICATION - Windows (developpement local XAMPP)
 * ------------------------------------------------------------
 * Planificateur de taches Windows > Creer une tache de base :
 *   Programme/script : C:\xampp\php\php.exe
 *   Arguments        : C:\xampp\htdocs\AGAI\cron\maintenance_logs.php
 *   Declencheur      : Quotidien, ex. 02:00
 *
 * ------------------------------------------------------------
 * PLANIFICATION - Linux (production)
 * ------------------------------------------------------------
 * crontab -e (utilisateur du serveur web, PAS root) :
 *   0 2 * * * /usr/bin/php /var/www/AGAI/cron/maintenance_logs.php >> /var/www/AGAI/logs/cron.log 2>&1
 *
 * ============================================================
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Acces interdit : ce script ne peut etre execute que par le planificateur de taches (CLI).');
}

require_once __DIR__ . '/../config/config.php';

echo '[' . date('Y-m-d H:i:s') . '] Demarrage de la maintenance des journaux AGAI...' . PHP_EOL;

$summary = LogRotator::runAll();

if (empty($summary['success'])) {
    echo '[' . date('Y-m-d H:i:s') . '] ECHEC : ' . ($summary['message'] ?? 'erreur inconnue') . PHP_EOL;
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo '[' . date('Y-m-d H:i:s') . '] Maintenance terminee avec succes.' . PHP_EOL;
exit(0);