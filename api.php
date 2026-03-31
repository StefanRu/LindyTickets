<?php
require_once __DIR__ . '/config.php';

// ── Logging ──
define('LOG_FILE', __DIR__ . '/debug.log');

function wlog(string $level, string $msg, array $ctx = []): void {
    $ts = date('Y-m-d H:i:s');
    $extra = $ctx ? ' ' . json_encode($ctx, JSON_UNESCAPED_UNICODE) : '';
    @file_put_contents(LOG_FILE, "[$ts] [$level] $msg$extra\n", FILE_APPEND | LOCK_EX);
}

// ── Input caching (php://input can only be read once) ──
$_RAW_BODY = file_get_contents('php://input');
$_JSON_BODY = null;

function getBody(): array {
    global $_RAW_BODY, $_JSON_BODY;
    if ($_JSON_BODY === null) {
        $_JSON_BODY = json_decode($_RAW_BODY, true);
        if (!is_array($_JSON_BODY)) {
            $_JSON_BODY = [];
        }
    }
    return $_JSON_BODY;
}

function checkAdmin(): void {
    $body = getBody();
    $pass = $body['password'] ?? '';
    if ($pass !== ADMIN_PASSWORD) {
        wlog('ERROR', 'Auth failed');
        sendJson(['error' => 'Mot de passe incorrect'], 403);
    }
}

function checkAdminGet(): void {
    if (($_GET['password'] ?? '') !== ADMIN_PASSWORD) {
        sendJson(['error' => 'Mot de passe incorrect'], 403);
    }
}

function sendJson(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function newCode(): string {
    return strtoupper(bin2hex(random_bytes(6)));
}

// ── CORS ──
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$action = $_GET['action'] ?? '';
wlog('INFO', "=> $action", ['method' => $_SERVER['REQUEST_METHOD'], 'eid' => $_GET['event_id'] ?? null]);

try {
    $db = getDB();
    route($db, $action);
} catch (PDOException $e) {
    wlog('ERROR', 'PDO: ' . $e->getMessage());
    sendJson(['error' => 'Erreur BDD: ' . $e->getMessage()], 500);
} catch (Throwable $e) {
    wlog('ERROR', 'Exception: ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
    sendJson(['error' => 'Erreur: ' . $e->getMessage()], 500);
}

// ══════════════════════════════════════════════════════════════
function route(PDO $db, string $action): void {
    switch ($action) {

    // ── EVENTS ──────────────────────────────────────────────

    case 'events':
        $rows = $db->query(
            "SELECT e.*,
                (SELECT COUNT(*) FROM tickets t WHERE t.event_id = e.id) AS total_tickets,
                (SELECT COUNT(*) FROM tickets t WHERE t.event_id = e.id AND t.checked_in = 1) AS checked_in
             FROM events e WHERE e.archived = 0 ORDER BY e.created_at DESC"
        )->fetchAll();
        sendJson($rows);

    case 'event':
        $eid = (int)($_GET['event_id'] ?? 0);
        $st = $db->prepare("SELECT * FROM events WHERE id=?");
        $st->execute([$eid]);
        $row = $st->fetch();
        if (!$row) sendJson(['error' => 'Evenement introuvable'], 404);
        sendJson($row);

    case 'create_event':
        checkAdmin();
        $b = getBody();
        $name = trim($b['name'] ?? '');
        if ($name === '') sendJson(['error' => 'Nom requis'], 400);
        $st = $db->prepare("INSERT INTO events (name, event_date, location, description, logo_url) VALUES (?, ?, ?, ?, ?)");
        $st->execute([$name, trim($b['event_date'] ?? ''), trim($b['location'] ?? ''), trim($b['description'] ?? ''), trim($b['logo_url'] ?? '')]);
        $id = (int)$db->lastInsertId();
        wlog('INFO', "Event created #$id: $name");
        sendJson(['status' => 'ok', 'event_id' => $id]);

    case 'update_event':
        checkAdmin();
        $b = getBody();
        $eid = (int)($b['event_id'] ?? 0);
        if (!$eid) sendJson(['error' => 'event_id requis'], 400);
        $st = $db->prepare("UPDATE events SET name=?, event_date=?, location=?, description=?, logo_url=? WHERE id=?");
        $st->execute([trim($b['name'] ?? ''), trim($b['event_date'] ?? ''), trim($b['location'] ?? ''), trim($b['description'] ?? ''), trim($b['logo_url'] ?? ''), $eid]);
        wlog('INFO', "Event updated #$eid");
        sendJson(['status' => 'ok']);

    case 'delete_event':
        checkAdmin();
        $b = getBody();
        $eid = (int)($b['event_id'] ?? 0);
        if (!$eid) sendJson(['error' => 'event_id requis'], 400);
        $db->prepare("DELETE FROM events WHERE id=?")->execute([$eid]);
        wlog('INFO', "Event deleted #$eid");
        sendJson(['status' => 'ok']);

    // ── STATS ───────────────────────────────────────────────

    case 'stats':
        $eid = (int)($_GET['event_id'] ?? 0);
        if (!$eid) sendJson(['error' => 'event_id requis'], 400);
        $st1 = $db->prepare("SELECT COUNT(*) FROM tickets WHERE event_id=?");
        $st1->execute([$eid]);
        $total = (int)$st1->fetchColumn();
        $st2 = $db->prepare("SELECT COUNT(*) FROM tickets WHERE event_id=? AND checked_in=1");
        $st2->execute([$eid]);
        $cin = (int)$st2->fetchColumn();
        sendJson(['total' => $total, 'checked_in' => $cin, 'remaining' => $total - $cin]);

    // ── GUESTS / SEARCH / HISTORY ───────────────────────────

    case 'guests':
        $eid = (int)($_GET['event_id'] ?? 0);
        $st = $db->prepare("SELECT ticket_code,nom,prenom,ticket_label,checked_in,checked_in_at FROM tickets WHERE event_id=? ORDER BY nom,prenom,ticket_label");
        $st->execute([$eid]);
        sendJson($st->fetchAll());

    case 'search':
        $eid = (int)($_GET['event_id'] ?? 0);
        $q = '%' . ($_GET['q'] ?? '') . '%';
        $st = $db->prepare("SELECT ticket_code,nom,prenom,ticket_label,checked_in,checked_in_at FROM tickets WHERE event_id=? AND (CONCAT(prenom,' ',nom) LIKE ? OR ticket_code LIKE ?) ORDER BY nom,prenom");
        $st->execute([$eid, $q, $q]);
        sendJson($st->fetchAll());

    case 'history':
        $eid = (int)($_GET['event_id'] ?? 0);
        $n = min(50, max(1, (int)($_GET['n'] ?? 20)));
        $st = $db->prepare("SELECT ticket_code,nom,prenom,ticket_label,checked_in_at,checked_in_by FROM tickets WHERE event_id=? AND checked_in=1 ORDER BY checked_in_at DESC LIMIT $n");
        $st->execute([$eid]);
        sendJson($st->fetchAll());

    // ── CHECK-IN ────────────────────────────────────────────

    case 'checkin':
        $b = getBody();
        $eid  = (int)($b['event_id'] ?? 0);
        $code = strtoupper(trim($b['code'] ?? ''));
        $meth = $b['method'] ?? 'scanner';
        if (!$code || !$eid) sendJson(['error' => 'code et event_id requis'], 400);

        $st = $db->prepare("SELECT id,nom,prenom,ticket_label,checked_in,checked_in_at FROM tickets WHERE ticket_code=? AND event_id=?");
        $st->execute([$code, $eid]);
        $tk = $st->fetch();

        if (!$tk) {
            wlog('INFO', "Checkin UNKNOWN $code");
            sendJson(['status' => 'unknown', 'message' => 'Ticket inconnu', 'code' => $code]);
        }
        if ($tk['checked_in']) {
            wlog('INFO', "Checkin DUPLICATE $code");
            sendJson(['status' => 'duplicate', 'message' => 'Deja scanne', 'nom' => $tk['nom'], 'prenom' => $tk['prenom'], 'ticket_label' => $tk['ticket_label'], 'checked_in_at' => $tk['checked_in_at']]);
        }
        $now = date('Y-m-d H:i:s');
        $db->prepare("UPDATE tickets SET checked_in=1,checked_in_at=?,checked_in_by=? WHERE id=?")->execute([$now, $meth, $tk['id']]);
        wlog('INFO', "Checkin OK $code " . $tk['prenom'] . ' ' . $tk['nom']);
        sendJson(['status' => 'valid', 'message' => 'Entree validee', 'nom' => $tk['nom'], 'prenom' => $tk['prenom'], 'ticket_label' => $tk['ticket_label'], 'checked_in_at' => $now]);

    // ── RESET ───────────────────────────────────────────────

    case 'reset_checkin':
        checkAdmin();
        $b = getBody();
        $code = strtoupper(trim($b['code'] ?? ''));
        $eid  = (int)($b['event_id'] ?? 0);
        $st = $db->prepare("UPDATE tickets SET checked_in=0,checked_in_at=NULL,checked_in_by=NULL WHERE ticket_code=? AND event_id=?");
        $st->execute([$code, $eid]);
        wlog('INFO', "Reset checkin $code: " . $st->rowCount());
        sendJson(['status' => 'ok', 'reset' => $st->rowCount()]);

    case 'reset_all':
        checkAdmin();
        $b = getBody();
        $eid = (int)($b['event_id'] ?? 0);
        $st = $db->prepare("UPDATE tickets SET checked_in=0,checked_in_at=NULL,checked_in_by=NULL WHERE event_id=?");
        $st->execute([$eid]);
        wlog('INFO', "Reset ALL event $eid: " . $st->rowCount());
        sendJson(['status' => 'ok', 'reset' => $st->rowCount()]);

    // ── IMPORT / ADD / DELETE GUEST ─────────────────────────

    case 'import':
        checkAdmin();
        $b = getBody();
        $eid    = (int)($b['event_id'] ?? 0);
        $guests = $b['guests'] ?? [];
        $clear  = $b['clear'] ?? true;
        if (!$eid) sendJson(['error' => 'event_id requis'], 400);
        if (empty($guests)) sendJson(['error' => 'Liste vide'], 400);

        $db->beginTransaction();
        if ($clear) {
            $db->prepare("DELETE FROM tickets WHERE event_id=?")->execute([$eid]);
        }
        $ins = $db->prepare("INSERT INTO tickets (event_id,ticket_code,nom,prenom,ticket_label) VALUES (?,?,?,?,?)");
        $count = 0;
        foreach ($guests as $g) {
            $nom = trim($g['nom'] ?? '');
            $pre = trim($g['prenom'] ?? '');
            $nb  = max(1, (int)($g['nb_tickets'] ?? 1));
            if (!$nom || !$pre) continue;
            for ($i = 1; $i <= $nb; $i++) {
                $ins->execute([$eid, newCode(), $nom, $pre, "$i/$nb"]);
                $count++;
            }
        }
        $db->commit();
        wlog('INFO', "Import event $eid: $count tickets");
        sendJson(['status' => 'ok', 'imported' => $count]);

    case 'add_guest':
        checkAdmin();
        $b   = getBody();
        $eid = (int)($b['event_id'] ?? 0);
        $nom = trim($b['nom'] ?? '');
        $pre = trim($b['prenom'] ?? '');
        $nb  = max(1, (int)($b['nb_tickets'] ?? 1));
        if (!$eid) sendJson(['error' => 'event_id requis'], 400);
        if (!$nom || !$pre) sendJson(['error' => 'Nom et prenom requis'], 400);

        $ins = $db->prepare("INSERT INTO tickets (event_id,ticket_code,nom,prenom,ticket_label) VALUES (?,?,?,?,?)");
        $codes = [];
        for ($i = 1; $i <= $nb; $i++) {
            $c = newCode();
            $ins->execute([$eid, $c, $nom, $pre, "$i/$nb"]);
            $codes[] = $c;
        }
        wlog('INFO', "Added $pre $nom ($nb) to event $eid");
        sendJson(['status' => 'ok', 'codes' => $codes, 'count' => $nb]);

    case 'delete_guest':
        checkAdmin();
        $b   = getBody();
        $eid = (int)($b['event_id'] ?? 0);
        $code = strtoupper(trim($b['code'] ?? ''));
        $st = $db->prepare("DELETE FROM tickets WHERE ticket_code=? AND event_id=?");
        $st->execute([$code, $eid]);
        wlog('INFO', "Deleted ticket $code");
        sendJson(['status' => 'ok', 'deleted' => $st->rowCount()]);

    // ── LOGO UPLOAD ─────────────────────────────────────────

    case 'upload_logo':
        if (($_POST['password'] ?? '') !== ADMIN_PASSWORD) sendJson(['error' => 'Mot de passe incorrect'], 403);
        $eid = (int)($_POST['event_id'] ?? 0);
        if (!$eid) sendJson(['error' => 'event_id requis'], 400);
        if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) sendJson(['error' => 'Fichier manquant'], 400);

        $allowed = ['image/png','image/jpeg','image/gif','image/webp','image/svg+xml'];
        $mime = mime_content_type($_FILES['logo']['tmp_name']);
        if (!in_array($mime, $allowed)) sendJson(['error' => 'Format non supporte (png/jpg/gif/webp/svg)'], 400);

        $ext = match($mime) { 'image/png'=>'png','image/jpeg'=>'jpg','image/gif'=>'gif','image/webp'=>'webp','image/svg+xml'=>'svg', default=>'png' };
        $dir = __DIR__ . '/uploads';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $filename = 'logo_' . $eid . '_' . time() . '.' . $ext;
        $path = $dir . '/' . $filename;
        move_uploaded_file($_FILES['logo']['tmp_name'], $path);

        $url = 'uploads/' . $filename;
        $db->prepare("UPDATE events SET logo_url=? WHERE id=?")->execute([$url, $eid]);
        wlog('INFO', "Logo uploaded for event $eid: $url");
        sendJson(['status' => 'ok', 'logo_url' => $url]);

    // ── EXPORT CSV ──────────────────────────────────────────

    case 'export':
        checkAdminGet();
        $eid = (int)($_GET['event_id'] ?? 0);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="export_' . $eid . '_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['Nom','Prenom','Ticket','Code','Entre','Heure','Methode'], ';');
        $st = $db->prepare("SELECT nom,prenom,ticket_label,ticket_code,checked_in,checked_in_at,checked_in_by FROM tickets WHERE event_id=? ORDER BY nom,prenom");
        $st->execute([$eid]);
        foreach ($st->fetchAll() as $r) {
            fputcsv($out, [$r['nom'],$r['prenom'],$r['ticket_label'],$r['ticket_code'],$r['checked_in']?'Oui':'Non',$r['checked_in_at'] ?? '',$r['checked_in_by'] ?? ''], ';');
        }
        fclose($out);
        exit;

    // ── TICKET PDF ──────────────────────────────────────────

    case 'ticket_pdf':
        $code = strtoupper(trim($_GET['code'] ?? ''));
        if (!$code) { http_response_code(400); echo 'Code manquant'; exit; }
        $st = $db->prepare("SELECT t.*,e.name AS event_name,e.event_date,e.location FROM tickets t JOIN events e ON t.event_id=e.id WHERE t.ticket_code=?");
        $st->execute([$code]);
        $tk = $st->fetch();
        if (!$tk) { http_response_code(404); echo 'Ticket introuvable'; exit; }
        header('Content-Type: text/html; charset=utf-8');
        require __DIR__ . '/ticket_template.php';
        exit;

    // ── LOG VIEWER ──────────────────────────────────────────

    case 'log':
        checkAdminGet();
        if (($_GET['clear'] ?? '') === '1') {
            @file_put_contents(LOG_FILE, '');
            header('Location: api.php?action=log&password=' . urlencode($_GET['password']));
            exit;
        }
        header('Content-Type: text/html; charset=utf-8');
        $lines = (int)($_GET['lines'] ?? 300);
        $content = '';
        if (file_exists(LOG_FILE)) {
            $all = file(LOG_FILE, FILE_IGNORE_NEW_LINES);
            $content = htmlspecialchars(implode("\n", array_slice($all, -$lines)));
        }
        $pw = urlencode($_GET['password']);
        require __DIR__ . '/log_template.php';
        exit;

    default:
        sendJson(['error' => "Action inconnue: $action"], 404);
    }
}
