<?php
/**
 * Script d'installation — à exécuter UNE SEULE FOIS
 * Accès : https://tickets.votresite.com/install.php
 * SUPPRIMEZ CE FICHIER après installation !
 */

require_once __DIR__ . '/config.php';

$messages = [];
$success = true;

try {
    $pdo = getDB();
    $messages[] = "✅ Connexion à la base de données réussie.";

    // Exécuter le schéma
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            $pdo->exec($stmt);
        }
    }
    $messages[] = "✅ Tables créées avec succès.";

    // Vérifier
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $messages[] = "📋 Tables présentes : " . implode(', ', $tables);

    $count = $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
    $messages[] = "🎉 Événement par défaut créé. ($count événement(s))";

} catch (PDOException $e) {
    $success = false;
    $messages[] = "❌ Erreur : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Installation — Lindy Tickets</title>
<style>
    body { font-family: system-ui, sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
    .card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    h1 { font-size: 1.4rem; margin-bottom: 16px; }
    .msg { padding: 8px 12px; margin: 6px 0; border-radius: 6px; font-size: 0.9rem; }
    .ok { background: #d4edda; color: #155724; }
    .err { background: #f8d7da; color: #721c24; }
    .warn { background: #fff3cd; color: #856404; padding: 12px; border-radius: 8px; margin-top: 16px; font-size: 0.9rem; }
    a { color: #e94560; }
</style>
</head>
<body>
<div class="card">
    <h1>🎟️ Installation Lindy Tickets</h1>
    <?php foreach ($messages as $m): ?>
        <div class="msg <?= $success ? 'ok' : 'err' ?>"><?= htmlspecialchars($m) ?></div>
    <?php endforeach; ?>

    <?php if ($success): ?>
        <div class="warn">
            ⚠️ <strong>IMPORTANT :</strong> Supprimez ce fichier (<code>install.php</code>) de votre serveur !<br><br>
            Prochaines étapes :<br>
            → <a href="admin.php">Accéder à l'admin</a> pour importer votre liste Excel<br>
            → <a href="index.html">Accéder au scanner</a>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
