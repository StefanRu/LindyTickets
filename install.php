<?php
require_once __DIR__ . '/config.php';
$msgs = []; $ok = true;
try {
    $pdo = getDB();
    $msgs[] = "✅ Connexion BDD réussie";
    foreach (array_filter(array_map('trim', explode(';', file_get_contents(__DIR__.'/schema.sql')))) as $s) {
        if ($s) $pdo->exec($s);
    }
    $msgs[] = "✅ Tables créées";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $msgs[] = "📋 Tables : " . implode(', ', $tables);
} catch (PDOException $e) { $ok = false; $msgs[] = "❌ " . $e->getMessage(); }
?><!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Install — Lindy Tickets v2</title>
<style>body{font-family:system-ui,sans-serif;max-width:550px;margin:40px auto;padding:20px;background:#f5f5f5}.card{background:#fff;border-radius:12px;padding:24px;box-shadow:0 2px 8px rgba(0,0,0,.1)}h1{font-size:1.3rem;margin-bottom:14px}.msg{padding:8px 12px;margin:5px 0;border-radius:6px;font-size:.88rem}.ok{background:#d4edda;color:#155724}.err{background:#f8d7da;color:#721c24}.warn{background:#fff3cd;color:#856404;padding:14px;border-radius:8px;margin-top:14px;font-size:.88rem}a{color:#e94560}</style></head><body>
<div class="card"><h1>🎟️ Installation v2</h1>
<?php foreach($msgs as $m): ?><div class="msg <?=$ok?'ok':'err'?>"><?=htmlspecialchars($m)?></div><?php endforeach; ?>
<?php if($ok): ?><div class="warn">⚠️ <strong>Supprimez install.php du serveur !</strong><br><br>→ <a href="admin.html">Admin</a> · <a href="index.html">Scanner</a></div><?php endif; ?>
</div></body></html>
