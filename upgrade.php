<?php
/** Upgrade v3.1 — ajoute logo + description aux evenements. A lancer une seule fois puis supprimer. */
require_once __DIR__ . '/config.php';
$db = getDB();
$msgs = [];
try {
    $db->exec("ALTER TABLE events ADD COLUMN description TEXT DEFAULT NULL AFTER location");
    $msgs[] = "Colonne description ajoutee";
} catch (PDOException $e) { $msgs[] = "description: " . $e->getMessage(); }
try {
    $db->exec("ALTER TABLE events ADD COLUMN logo_url VARCHAR(500) DEFAULT '' AFTER description");
    $msgs[] = "Colonne logo_url ajoutee";
} catch (PDOException $e) { $msgs[] = "logo_url: " . $e->getMessage(); }
try {
    $db->exec("ALTER TABLE events ADD COLUMN non_qrcode_event TINYINT(1) NOT NULL DEFAULT 0 AFTER logo_url");
    $msgs[] = "Colonne non_qrcode_event ajoutee";
} catch (PDOException $e) { $msgs[] = "non_qrcode_event: " . $e->getMessage(); }
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Upgrade</title></head><body style="font-family:sans-serif;max-width:500px;margin:40px auto;padding:20px">
<h1>Upgrade v3.1</h1>
<?php foreach($msgs as $m): ?><p><?= htmlspecialchars($m) ?></p><?php endforeach; ?>
<p><b>Supprimez ce fichier !</b> <a href="admin.html">Admin</a></p>
</body></html>
