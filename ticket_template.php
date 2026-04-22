<?php
/** @var array $tickets */
$h = fn($s) => htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8');
$tickets = isset($tickets) && is_array($tickets) ? array_values($tickets) : (isset($tk) ? [$tk] : []);
$url = SITE_URL;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Tickets PDF</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
@page { size: A4 portrait; margin: 10mm; }
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: Georgia, 'Times New Roman', serif;
    background: #f5f0e6;
    color: #111;
}
.toolbar {
    position: sticky; top: 0; left: 0; right: 0;
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

.sheet {
    width: 190mm;
    min-height: 277mm;
    margin: 10mm auto;
    background: #fff;
    border: 1px solid #ddd;
    padding: 4mm;
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    grid-auto-rows: 62mm;
    gap: 4mm;
}

.ticket {
    background: #FFF8E7;
    border-radius: 4px;
    overflow: hidden;
    border: 1mm solid #111;
    position: relative;
    break-inside: avoid;
}

.ticket::before, .ticket::after {
    content: '★'; position: absolute; color: #D4A017; font-size: 10px; z-index: 2;
}
.ticket::before { top: 5px; left: 7px; }
.ticket::after { top: 5px; right: 7px; }

.band {
    background: #1a1a2e;
    padding: 5mm 4mm 3mm;
    color: #fff;
    text-align: center;
}
.band .logo-row img { max-height: 10mm; max-width: 32mm; object-fit: contain; }
.band .ev-name { font-size: 4.6mm; font-weight: 700; letter-spacing: .2mm; line-height: 1.1; }
.band .ev-sub { font-size: 2.4mm; opacity: .85; margin-top: .8mm; font-family: system-ui, sans-serif; }
.band .ev-desc {
    font-size: 2.2mm; opacity: .78; margin-top: .8mm; font-style: italic;
    font-family: system-ui, sans-serif; line-height: 1.25;
    max-height: 6mm; overflow: hidden;
}

.accent { height: 1.8mm; background: linear-gradient(90deg, #8B0000, #D4A017 30%, #D4A017 70%, #8B0000); }
.stars-row { text-align: center; color: #D4A017; font-size: 2.2mm; letter-spacing: 1.4mm; padding-top: .7mm; }

.body { display: flex; padding: 2.8mm 3.2mm 2.2mm; align-items: center; gap: 2.5mm; }
.info { flex: 1; min-width: 0; }
.info .lbl {
    font-size: 2.1mm; text-transform: uppercase; letter-spacing: .4mm;
    color: #999; margin-bottom: .8mm; font-family: system-ui;
}
.info .guest-name {
    font-size: 4.5mm; font-weight: 700; color: #1a1a2e; line-height: 1.08;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.info .tnum { font-size: 2.8mm; color: #8B0000; margin-top: 1.1mm; font-weight: 600; }

.qr-box { text-align: center; flex-shrink: 0; width: 22mm; }
.qr-code {
    font-family: 'Courier New', monospace; font-size: 1.85mm;
    color: #777; margin-top: .7mm; letter-spacing: .1mm; line-height: 1.1;
}

.footer {
    text-align: center; font-size: 2.05mm; color: #666;
    padding: 1.6mm 2mm; border-top: .35mm dashed #D4A017;
    font-family: system-ui; letter-spacing: .1mm;
}

@media print {
    .toolbar { display: none !important; }
    body { background: #fff; }
    .sheet { margin: 0 auto; border: none; page-break-after: always; }
    .sheet:last-child { page-break-after: auto; }
}
</style>
</head>
<body>
<div class="toolbar">
    <button onclick="window.print()">Imprimer / PDF</button>
    <a href="<?= $h($url) ?>">Retour au scanner</a>
</div>
<?php
$chunks = array_chunk($tickets, 8);
foreach ($chunks as $page => $group):
?>
<div class="sheet">
<?php foreach ($group as $i => $row):
    $evN = $h($row['event_name'] ?? 'Évènement');
    $evSub = implode('  —  ', array_filter([$row['event_date'] ?? '', $row['location'] ?? '']));
    $evSub = $h($evSub);
    $desc = $h($row['description'] ?? '');
    $logo = $row['logo_url'] ?? '';
    $name = $h(($row['prenom'] ?? '') . ' ' . ($row['nom'] ?? ''));
    $label = $h($row['ticket_label'] ?? '');
    $code = $h($row['ticket_code'] ?? '');
    $nonQr = !empty($row['non_qrcode_event']);
    $qrId = 'qr_' . $page . '_' . $i;
?>
    <article class="ticket">
        <div class="band">
            <?php if ($logo): ?><div class="logo-row"><img src="<?= $h($logo) ?>" alt="Logo"></div><?php endif; ?>
            <div class="ev-name"><?= $evN ?></div>
            <?php if ($evSub): ?><div class="ev-sub"><?= $evSub ?></div><?php endif; ?>
            <?php if ($desc): ?><div class="ev-desc"><?= $desc ?></div><?php endif; ?>
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
            <?php if (!$nonQr): ?>
                <div id="<?= $qrId ?>"></div>
                <div class="qr-code"><?= $code ?></div>
            <?php endif; ?>
            </div>
        </div>
        <div class="footer">★ Pr&eacute;sentez ce ticket &agrave; l'entr&eacute;e — 1 ticket = 1 entr&eacute;e ★</div>
    </article>
<?php endforeach; ?>
</div>
<?php endforeach; ?>

<script>
(function(){
<?php foreach ($chunks as $page => $group):
    foreach ($group as $i => $row):
        if (!empty($row['non_qrcode_event'])) { continue; }
        $qrId = 'qr_' . $page . '_' . $i;
        $rawCode = json_encode((string)($row['ticket_code'] ?? ''));
?>
    new QRCode(document.getElementById('<?= $qrId ?>'), {
        text: <?= $rawCode ?>,
        width: 68,
        height: 68,
        colorDark: '#1a1a2e',
        colorLight: '#FFF8E7',
        correctLevel: QRCode.CorrectLevel.M
    });
<?php endforeach; endforeach; ?>
})();
</script>
</body>
</html>
