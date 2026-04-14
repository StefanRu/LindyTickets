<?php
/** @var array $tk — ticket + event data */
$h = fn($s) => htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8');
$evN   = $h($tk['event_name']);
$evSub = implode('  —  ', array_filter([$tk['event_date'] ?? '', $tk['location'] ?? '']));
$evSub = $h($evSub);
$desc  = $h($tk['description'] ?? '');
$logo  = $tk['logo_url'] ?? '';
$name  = $h($tk['prenom'] . ' ' . $tk['nom']);
$label = $h($tk['ticket_label']);
$code  = $h($tk['ticket_code']);
$nonQr = !empty($tk['non_qrcode_event']);
$url   = SITE_URL;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Ticket — <?= $name ?></title>
<?php if (!$nonQr): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<?php endif; ?>
<style>
@page { size: 160mm 110mm; margin: 0; }
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: Georgia, 'Times New Roman', serif;
    background: #f5f0e6;
    display: flex; justify-content: center; align-items: center;
    min-height: 100vh;
}
.toolbar {
    position: fixed; top: 0; left: 0; right: 0;
    background: #1a1a1a; color: #fff; padding: 10px 20px;
    text-align: center; z-index: 10; font-family: system-ui, sans-serif;
}
.toolbar button {
    background: #c0392b; color: #fff; border: none; border-radius: 6px;
    padding: 7px 18px; font-size: .88rem; cursor: pointer; margin: 0 4px;
}
.toolbar a {
    color: #fff; opacity: .7; font-size: .82rem;
    text-decoration: none; margin-left: 12px; font-family: system-ui;
}

.ticket {
    width: 460px; background: #FFF8E7;
    border-radius: 4px; overflow: hidden; margin-top: 56px;
    box-shadow: 0 4px 20px rgba(0,0,0,.15);
    border: 2px solid #D4A017;
    position: relative;
}

/* Corner stars */
.ticket::before, .ticket::after {
    content: '★'; position: absolute; color: #D4A017; font-size: 14px; z-index: 2;
}
.ticket::before { top: 8px; left: 10px; }
.ticket::after { top: 8px; right: 10px; }

.band {
    background: #1a1a2e;
    padding: 20px 24px; color: #fff; text-align: center;
    position: relative;
}
.band .logo-row {
    margin-bottom: 8px;
}
.band .logo-row img {
    max-height: 50px; max-width: 200px; object-fit: contain;
}
.band .ev-name {
    font-size: 24px; font-weight: 700; letter-spacing: 1px;
    text-shadow: 0 1px 3px rgba(0,0,0,.3);
}
.band .ev-sub {
    font-size: 12px; opacity: .8; margin-top: 4px; font-family: system-ui, sans-serif;
}
.band .ev-desc {
    font-size: 11px; opacity: .7; margin-top: 6px; font-style: italic;
    font-family: system-ui, sans-serif; line-height: 1.4;
    max-width: 380px; margin-left: auto; margin-right: auto;
}

/* Gold accent strip with stars */
.accent {
    height: 5px;
    background: linear-gradient(90deg, #8B0000, #D4A017 30%, #D4A017 70%, #8B0000);
}

.stars-row {
    text-align: center; color: #D4A017; font-size: 11px;
    letter-spacing: 8px; padding: 6px 0 2px;
}

.body {
    display: flex; padding: 16px 24px 12px; align-items: center; gap: 20px;
}
.info { flex: 1; }
.info .lbl {
    font-size: 9px; text-transform: uppercase; letter-spacing: 2px;
    color: #999; margin-bottom: 3px; font-family: system-ui;
}
.info .guest-name {
    font-size: 24px; font-weight: 700; color: #1a1a2e; line-height: 1.2;
}
.info .tnum {
    font-size: 13px; color: #8B0000; margin-top: 6px; font-weight: 600;
}

.qr-box { text-align: center; flex-shrink: 0; }
.qr-code {
    font-family: 'Courier New', monospace; font-size: 8px;
    color: #bbb; margin-top: 4px; letter-spacing: 1px;
}

.footer {
    text-align: center; font-size: 9px; color: #bbb;
    padding: 8px 16px; border-top: 1px dashed #D4A017;
    font-family: system-ui; letter-spacing: .3px;
}

@media print {
    .toolbar { display: none !important; }
    body { background: #fff; align-items: flex-start; padding: 0; }
    .ticket { box-shadow: none; margin: 0; }
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
<?php if ($logo): ?>
        <div class="logo-row"><img src="<?= $h($logo) ?>" alt="Logo"></div>
<?php endif; ?>
        <div class="ev-name"><?= $evN ?></div>
<?php if ($evSub): ?>
        <div class="ev-sub"><?= $evSub ?></div>
<?php endif; ?>
<?php if ($desc): ?>
        <div class="ev-desc"><?= $desc ?></div>
<?php endif; ?>
    </div>
    <div class="accent"></div>
    <div class="stars-row">★ ★ ★ ★ ★</div>
    <div class="body">
        <div class="info">
            <div class="lbl">Invit&eacute;</div>
            <div class="guest-name"><?= $name ?></div>
            <div class="tnum">Ticket <?= $label ?></div>
        </div>
        <div class="qr-box">
<?php if ($nonQr): ?>
            <div style="font-size:12px;color:#8B0000;font-family:system-ui">Sans QR code</div>
<?php else: ?>
            <div id="qr"></div>
            <div class="qr-code"><?= $code ?></div>
<?php endif; ?>
        </div>
    </div>
    <div class="footer">★ Pr&eacute;sentez ce ticket &agrave; l'entr&eacute;e &mdash; 1 ticket = 1 entr&eacute;e ★</div>
</div>
<?php if (!$nonQr): ?>
<script>
new QRCode(document.getElementById('qr'), {
    text: "<?= $code ?>",
    width: 110, height: 110,
    colorDark: "#1a1a2e", colorLight: "#FFF8E7",
    correctLevel: QRCode.CorrectLevel.M
});
</script>
<?php endif; ?>
</body>
</html>
