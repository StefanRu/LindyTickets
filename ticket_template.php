<?php
/** @var array $tk — ticket row with event_name, event_date, location, prenom, nom, ticket_label, ticket_code */
$h = fn($s) => htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8');
$evName = $h($tk['event_name']);
$evDate = $h($tk['event_date'] ?? '');
$evLoc  = $h($tk['location'] ?? '');
$evSub  = implode('  —  ', array_filter([$tk['event_date'] ?? '', $tk['location'] ?? '']));
$evSub  = $h($evSub);
$name   = $h($tk['prenom'] . ' ' . $tk['nom']);
$label  = $h($tk['ticket_label']);
$code   = $h($tk['ticket_code']);
$url    = SITE_URL;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Ticket — <?= $name ?></title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
@page { size: 148mm 105mm; margin: 0; }
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Segoe UI', system-ui, sans-serif;
    background: #f0f2f5;
    display: flex; justify-content: center; align-items: center;
    min-height: 100vh;
}
.toolbar {
    position: fixed; top: 0; left: 0; right: 0;
    background: #2c3e50; color: #fff; padding: 10px 20px;
    text-align: center; z-index: 10;
}
.toolbar button {
    background: #3498db; color: #fff; border: none; border-radius: 6px;
    padding: 7px 18px; font-size: .88rem; cursor: pointer; margin: 0 4px;
}
.toolbar a {
    color: #fff; opacity: .7; font-size: .82rem;
    text-decoration: none; margin-left: 12px;
}
.ticket {
    width: 440px; background: #fff; border-radius: 14px;
    box-shadow: 0 6px 30px rgba(0,0,0,.12);
    overflow: hidden; margin-top: 56px;
}
.band {
    background: linear-gradient(135deg, #2c3e50, #34495e);
    padding: 20px 24px; color: #fff; text-align: center;
}
.band .ev-name { font-size: 22px; font-weight: 700; margin-bottom: 4px; }
.band .ev-sub { font-size: 12px; opacity: .8; }
.accent {
    height: 4px;
    background: linear-gradient(90deg, #3498db, #2ecc71, #e67e22);
}
.body {
    display: flex; padding: 22px 24px; align-items: center; gap: 20px;
}
.info { flex: 1; }
.info .lbl {
    font-size: 10px; text-transform: uppercase;
    letter-spacing: 1.5px; color: #95a5a6; margin-bottom: 4px;
}
.info .guest-name {
    font-size: 22px; font-weight: 700; color: #2c3e50; line-height: 1.2;
}
.info .tnum { font-size: 13px; color: #7f8c8d; margin-top: 6px; }
.qr-box { text-align: center; flex-shrink: 0; }
.qr-box canvas { display: block !important; }
.qr-code { font-family: monospace; font-size: 9px; color: #bdc3c7; margin-top: 5px; letter-spacing: 1px; }
.footer {
    text-align: center; font-size: 10px; color: #bdc3c7;
    padding: 10px 16px; border-top: 1px solid #ecf0f1;
}
@media print {
    .toolbar { display: none !important; }
    body { background: #fff; align-items: flex-start; padding: 0; }
    .ticket { box-shadow: none; margin: 0; border: 1px solid #ddd; }
}
</style>
</head>
<body>
<div class="toolbar">
    <button onclick="window.print()">Imprimer / PDF</button>
    <a href="<?= $url ?>">Retour au scanner</a>
</div>
<div class="ticket">
    <div class="band">
        <div class="ev-name"><?= $evName ?></div>
        <?php if ($evSub): ?><div class="ev-sub"><?= $evSub ?></div><?php endif; ?>
    </div>
    <div class="accent"></div>
    <div class="body">
        <div class="info">
            <div class="lbl">Invit&eacute;</div>
            <div class="guest-name"><?= $name ?></div>
            <div class="tnum">Ticket <?= $label ?></div>
        </div>
        <div class="qr-box">
            <div id="qr"></div>
            <div class="qr-code"><?= $code ?></div>
        </div>
    </div>
    <div class="footer">Pr&eacute;sentez ce ticket &agrave; l'entr&eacute;e &mdash; 1 ticket = 1 entr&eacute;e</div>
</div>
<script>
new QRCode(document.getElementById('qr'), {
    text: "<?= $code ?>",
    width: 110, height: 110,
    colorDark: "#2c3e50", colorLight: "#ffffff",
    correctLevel: QRCode.CorrectLevel.M
});
</script>
</body>
</html>
