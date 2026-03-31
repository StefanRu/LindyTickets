<?php
/**
 * Configuration base de données — Lindy Tickets v2
 * À modifier avec vos identifiants hébergeur
 */

define('DB_HOST', '');
define('DB_NAME', '');        // Nom de la BDD
define('DB_USER', ''); // Utilisateur BDD
define('DB_PASS', ''); // Mot de passe BDD

// Clé secrète pour l'admin (changez-la !)
define('ADMIN_PASSWORD', '');

// URL du site
define('SITE_URL', '');

date_default_timezone_set('Europe/Paris');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function generate_ticket_code(): string {
    return strtoupper(bin2hex(random_bytes(6)));
}
