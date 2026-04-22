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
body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background: #f3f3f3; color: #111; }
.toolbar {
    position: sticky; top: 0; left: 0; right: 0; z-index: 20;
    background: #111; color: #fff; padding: 10px 16px; text-align: center;
}
.toolbar button {
    background: #c0392b; color: #fff; border: none; border-radius: 6px;
    padding: 8px 14px; font-size: .9rem; cursor: pointer;
}
.toolbar a { color: #fff; opacity: .75; margin-left: 12px; text-decoration: none; font-size: .85rem; }

.sheet {
    width: 190mm; min-height: 277mm; margin: 10mm auto; background: #fff;
    border: 1px solid #ddd; padding: 6mm;
    display: grid; grid-template-columns: 1fr; grid-auto-rows: 1fr; gap: 6mm;
}
.ticket {
    border: 1.4mm solid #111; border-radius: 2mm; overflow: hidden;
    min-height: 128mm; display: flex; flex-direction: column;
}
.ticket-head { background: #1a1a2e; color: #fff; padding: 6mm; text-align: center; }
.ticket-head img { max-height: 16mm; max-width: 55mm; object-fit: contain; margin-bottom: 2mm; }
.ticket-head .ev-name { font-size: 7mm; font-weight: 700; line-height: 1.1; }
.ticket-head .ev-sub { font-size: 3.2mm; opacity: .85; margin-top: 1.5mm; }
.ticket-head .ev-desc { font-size: 2.8mm; opacity: .8; margin-top: 1.8mm; }
.ticket-main {
    flex: 1; display: grid; grid-template-columns: 1fr 34mm; gap: 5mm;
    align-items: center; padding: 6mm;
}
.lbl { font-size: 2.7mm; letter-spacing: .4mm; text-transform: uppercase; color: #666; }
.guest { font-size: 6.4mm; font-weight: 700; margin-top: 1.5mm; }
.label { font-size: 4.1mm; color: #8B0000; margin-top: 2.2mm; font-weight: 700; }
.qr-wrap { text-align: center; }
.qr-code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 2.3mm; margin-top: 1.5mm; color: #666; word-break: break-all; }
.ticket-foot {
    border-top: .45mm dashed #888; text-align: center;
    font-size: 2.8mm; color: #555; padding: 2.4mm;
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
$chunks = array_chunk($tickets, 2);
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
        <header class="ticket-head">
            <?php if ($logo): ?><img src="<?= $h($logo) ?>" alt="Logo"><?php endif; ?>
            <div class="ev-name"><?= $evN ?></div>
            <?php if ($evSub): ?><div class="ev-sub"><?= $evSub ?></div><?php endif; ?>
            <?php if ($desc): ?><div class="ev-desc"><?= $desc ?></div><?php endif; ?>
        </header>
        <div class="ticket-main">
            <div>
                <div class="lbl">Invité</div>
                <div class="guest"><?= $name ?></div>
                <div class="label">Ticket <?= $label ?></div>
            </div>
            <div class="qr-wrap">
                <?php if (!$nonQr): ?>
                    <div id="<?= $qrId ?>"></div>
                    <div class="qr-code"><?= $code ?></div>
                <?php endif; ?>
            </div>
        </div>
        <footer class="ticket-foot">Présentez ce ticket à l'entrée — 1 ticket = 1 entrée</footer>
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
            $code = json_encode((string)($row['ticket_code'] ?? ''));
    ?>
    new QRCode(document.getElementById('<?= $qrId ?>'), {
        text: <?= $code ?>,
        width: 122,
        height: 122,
        colorDark: '#111',
        colorLight: '#fff',
        correctLevel: QRCode.CorrectLevel.M
    });
    <?php endforeach; endforeach; ?>
})();
</script>
</body>
</html>
