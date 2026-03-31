<?php
/** @var string $content — escaped log content */
/** @var string $pw — url-encoded password */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Debug Log</title>
<style>
body { font-family: monospace; background: #1a1a2e; color: #ddd; padding: 14px; font-size: 12px; line-height: 1.6; }
h1 { color: #fff; font-size: 1rem; margin-bottom: 8px; }
.bar { margin-bottom: 10px; }
.bar a { color: #e94560; margin-right: 14px; font-size: .85rem; text-decoration: none; }
pre { background: #111; padding: 14px; border-radius: 8px; overflow: auto; white-space: pre-wrap; word-break: break-all; max-height: 85vh; }
</style>
</head>
<body>
<h1>Debug Log</h1>
<div class="bar">
    <a href="api.php?action=log&password=<?= $pw ?>">Rafraichir</a>
    <a href="api.php?action=log&password=<?= $pw ?>&lines=1000">1000 lignes</a>
    <a href="api.php?action=log&password=<?= $pw ?>&clear=1" onclick="return confirm('Vider le log ?')">Vider</a>
    <a href="admin.html">Admin</a>
</div>
<pre id="log"><?= $content ?></pre>
<script>
var el = document.getElementById('log');
el.scrollTop = el.scrollHeight;
el.innerHTML = el.textContent
    .replace(/\[INFO\]/g, '<span style="color:#27ae60">[INFO]</span>')
    .replace(/\[ERROR\]/g, '<span style="color:#e74c3c">[ERROR]</span>')
    .replace(/\[DEBUG\]/g, '<span style="color:#f39c12">[DEBUG]</span>');
</script>
</body>
</html>
