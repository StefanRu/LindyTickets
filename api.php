<?php
/**
 * API REST — Lindy Tickets v2
 *
 * ── Événements ──
 * GET    ?action=events                        → Liste des événements
 * GET    ?action=event&event_id=X              → Détails d'un événement
 * POST   ?action=create_event                  → Créer un événement {name, event_date, location, password}
 * POST   ?action=update_event                  → Modifier {event_id, name, event_date, location, password}
 * POST   ?action=delete_event                  → Supprimer {event_id, password}
 *
 * ── Tickets / Invités ──
 * GET    ?action=stats&event_id=X              → Statistiques
 * GET    ?action=guests&event_id=X             → Liste complète
 * GET    ?action=search&event_id=X&q=...       → Recherche
 * GET    ?action=history&event_id=X&n=20       → Derniers scans
 * POST   ?action=checkin                       → Check-in {event_id, code, method}
 * POST   ?action=reset_checkin                 → Reset UN check-in {event_id, code, password}
 * POST   ?action=reset_all                     → Reset TOUS les check-ins {event_id, password}
 * POST   ?action=import                        → Import liste {event_id, guests[], password}
 * POST   ?action=add_guest                     → Ajouter 1 invité {event_id, nom, prenom, nb_tickets, password}
 * POST   ?action=delete_guest                  → Supprimer tickets d'un invité {event_id, nom, prenom, password}
 * GET    ?action=export&event_id=X&password=Y  → Export CSV
 *
 * ── Ticket PDF ──
 * GET    ?action=ticket_pdf&code=X             → Génère un ticket HTML imprimable
 */

require_once __DIR__ . '/config.php';

// JSON responses sauf pour export et ticket_pdf
$action = $_GET['action'] ?? '';
if (!in_array($action, ['export', 'ticket_pdf'])) {
    header('Content-Type: application/json; charset=utf-8');
}
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

try {
    $pdo = getDB();

    switch ($action) {

        // ═══════════════════════════════════════════
        //  ÉVÉNEMENTS
        // ═══════════════════════════════════════════

        case 'events':
            $rows = $pdo->query(
                "SELECT e.*, 
                    (SELECT COUNT(*) FROM tickets t WHERE t.event_id = e.id) AS total_tickets,
                    (SELECT COUNT(*) FROM tickets t WHERE t.event_id = e.id AND t.checked_in = 1) AS checked_in
                 FROM events e WHERE e.archived = 0 ORDER BY e.created_at DESC"
            )->fetchAll();
            json_out($rows);
            break;

        case 'event':
            $eid = (int) ($_GET['event_id'] ?? 0);
            $ev = $pdo->prepare("SELECT * FROM events WHERE id = :id");
            $ev->execute([':id' => $eid]);
            $row = $ev->fetch();
            $row ? json_out($row) : json_err('Événement introuvable', 404);
            break;

        case 'create_event':
            require_admin();
            $in = json_input();
            $stmt = $pdo->prepare(
                "INSERT INTO events (name, event_date, location) VALUES (:n, :d, :l)"
            );
            $stmt->execute([
                ':n' => trim($in['name'] ?? 'Nouvel événement'),
                ':d' => trim($in['event_date'] ?? ''),
                ':l' => trim($in['location'] ?? ''),
            ]);
            json_out(['status' => 'ok', 'event_id' => (int) $pdo->lastInsertId()]);
            break;

        case 'update_event':
            require_admin();
            $in = json_input();
            $eid = (int) ($in['event_id'] ?? 0);
            $stmt = $pdo->prepare(
                "UPDATE events SET name = :n, event_date = :d, location = :l WHERE id = :id"
            );
            $stmt->execute([
                ':n'  => trim($in['name'] ?? ''),
                ':d'  => trim($in['event_date'] ?? ''),
                ':l'  => trim($in['location'] ?? ''),
                ':id' => $eid,
            ]);
            json_out(['status' => 'ok']);
            break;

        case 'delete_event':
            require_admin();
            $in = json_input();
            $eid = (int) ($in['event_id'] ?? 0);
            // CASCADE supprime aussi les tickets
            $pdo->prepare("DELETE FROM events WHERE id = :id")->execute([':id' => $eid]);
            json_out(['status' => 'ok']);
            break;

        // ═══════════════════════════════════════════
        //  STATISTIQUES
        // ═══════════════════════════════════════════

        case 'stats':
            $eid = (int) ($_GET['event_id'] ?? 0);
            $total = (int) $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE event_id = :e");
            $total->execute([':e' => $eid]);
            $total = (int) $total->fetchColumn();

            $cin = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE event_id = :e AND checked_in = 1");
            $cin->execute([':e' => $eid]);
            $cin = (int) $cin->fetchColumn();

            json_out(['total' => $total, 'checked_in' => $cin, 'remaining' => $total - $cin]);
            break;

        // ═══════════════════════════════════════════
        //  LISTE / RECHERCHE / HISTORIQUE
        // ═══════════════════════════════════════════

        case 'guests':
            $eid = (int) ($_GET['event_id'] ?? 0);
            $stmt = $pdo->prepare(
                "SELECT ticket_code, nom, prenom, ticket_label, checked_in, checked_in_at
                 FROM tickets WHERE event_id = :e ORDER BY nom, prenom, ticket_label"
            );
            $stmt->execute([':e' => $eid]);
            json_out($stmt->fetchAll());
            break;

        case 'search':
            $eid = (int) ($_GET['event_id'] ?? 0);
            $q = '%' . ($_GET['q'] ?? '') . '%';
            $stmt = $pdo->prepare(
                "SELECT ticket_code, nom, prenom, ticket_label, checked_in, checked_in_at
                 FROM tickets WHERE event_id = :e
                 AND (CONCAT(prenom, ' ', nom) LIKE :q OR ticket_code LIKE :q2)
                 ORDER BY nom, prenom"
            );
            $stmt->execute([':e' => $eid, ':q' => $q, ':q2' => $q]);
            json_out($stmt->fetchAll());
            break;

        case 'history':
            $eid = (int) ($_GET['event_id'] ?? 0);
            $n = min(50, max(1, (int) ($_GET['n'] ?? 20)));
            $stmt = $pdo->prepare(
                "SELECT ticket_code, nom, prenom, ticket_label, checked_in_at, checked_in_by
                 FROM tickets WHERE event_id = :e AND checked_in = 1
                 ORDER BY checked_in_at DESC LIMIT $n"
            );
            $stmt->execute([':e' => $eid]);
            json_out($stmt->fetchAll());
            break;

        // ═══════════════════════════════════════════
        //  CHECK-IN / RESET
        // ═══════════════════════════════════════════

        case 'checkin':
            $in = json_input();
            $eid = (int) ($in['event_id'] ?? 0);
            $code = strtoupper(trim($in['code'] ?? ''));
            $method = $in['method'] ?? 'scanner';

            if (!$code) json_err('Code manquant', 400);

            $stmt = $pdo->prepare(
                "SELECT id, ticket_code, nom, prenom, ticket_label, checked_in, checked_in_at
                 FROM tickets WHERE ticket_code = :c AND event_id = :e"
            );
            $stmt->execute([':c' => $code, ':e' => $eid]);
            $ticket = $stmt->fetch();

            if (!$ticket) {
                json_out(['status' => 'unknown', 'message' => 'Ticket inconnu', 'code' => $code]);
                break;
            }
            if ($ticket['checked_in']) {
                json_out([
                    'status' => 'duplicate', 'message' => 'Déjà scanné',
                    'nom' => $ticket['nom'], 'prenom' => $ticket['prenom'],
                    'ticket_label' => $ticket['ticket_label'],
                    'checked_in_at' => $ticket['checked_in_at'],
                ]);
                break;
            }

            $now = date('Y-m-d H:i:s');
            $pdo->prepare(
                "UPDATE tickets SET checked_in=1, checked_in_at=:now, checked_in_by=:m WHERE id=:id"
            )->execute([':now' => $now, ':m' => $method, ':id' => $ticket['id']]);

            json_out([
                'status' => 'valid', 'message' => 'Entrée validée',
                'nom' => $ticket['nom'], 'prenom' => $ticket['prenom'],
                'ticket_label' => $ticket['ticket_label'], 'checked_in_at' => $now,
            ]);
            break;

        case 'reset_checkin':
            require_admin();
            $in = json_input();
            $code = strtoupper(trim($in['code'] ?? ''));
            $eid = (int) ($in['event_id'] ?? 0);
            $stmt = $pdo->prepare(
                "UPDATE tickets SET checked_in=0, checked_in_at=NULL, checked_in_by=NULL
                 WHERE ticket_code=:c AND event_id=:e"
            );
            $stmt->execute([':c' => $code, ':e' => $eid]);
            json_out(['status' => 'ok', 'reset' => $stmt->rowCount()]);
            break;

        case 'reset_all':
            require_admin();
            $in = json_input();
            $eid = (int) ($in['event_id'] ?? 0);
            $stmt = $pdo->prepare(
                "UPDATE tickets SET checked_in=0, checked_in_at=NULL, checked_in_by=NULL WHERE event_id=:e"
            );
            $stmt->execute([':e' => $eid]);
            json_out(['status' => 'ok', 'reset' => $stmt->rowCount()]);
            break;

        // ═══════════════════════════════════════════
        //  IMPORT / AJOUT / SUPPRESSION
        // ═══════════════════════════════════════════

        case 'import':
            require_admin();
            $in = json_input();
            $eid = (int) ($in['event_id'] ?? 0);
            $guests = $in['guests'] ?? [];
            $clear = $in['clear'] ?? true; // Par défaut, remplace tout

            if (empty($guests)) json_err('Liste vide', 400);

            $pdo->beginTransaction();
            if ($clear) {
                $pdo->prepare("DELETE FROM tickets WHERE event_id = :e")->execute([':e' => $eid]);
            }

            $stmt = $pdo->prepare(
                "INSERT INTO tickets (event_id, ticket_code, nom, prenom, ticket_label)
                 VALUES (:e, :code, :nom, :prenom, :label)"
            );
            $count = 0;
            foreach ($guests as $g) {
                $nom = trim($g['nom'] ?? '');
                $prenom = trim($g['prenom'] ?? '');
                $nb = max(1, (int) ($g['nb_tickets'] ?? 1));
                if (!$nom || !$prenom) continue;
                for ($i = 1; $i <= $nb; $i++) {
                    $stmt->execute([
                        ':e' => $eid, ':code' => generate_ticket_code(),
                        ':nom' => $nom, ':prenom' => $prenom, ':label' => "$i/$nb",
                    ]);
                    $count++;
                }
            }
            $pdo->commit();
            json_out(['status' => 'ok', 'imported' => $count]);
            break;

        case 'add_guest':
            require_admin();
            $in = json_input();
            $eid = (int) ($in['event_id'] ?? 0);
            $nom = trim($in['nom'] ?? '');
            $prenom = trim($in['prenom'] ?? '');
            $nb = max(1, (int) ($in['nb_tickets'] ?? 1));

            if (!$nom || !$prenom) json_err('Nom et prénom requis', 400);

            $stmt = $pdo->prepare(
                "INSERT INTO tickets (event_id, ticket_code, nom, prenom, ticket_label)
                 VALUES (:e, :code, :nom, :prenom, :label)"
            );
            $codes = [];
            for ($i = 1; $i <= $nb; $i++) {
                $code = generate_ticket_code();
                $stmt->execute([
                    ':e' => $eid, ':code' => $code,
                    ':nom' => $nom, ':prenom' => $prenom, ':label' => "$i/$nb",
                ]);
                $codes[] = $code;
            }
            json_out(['status' => 'ok', 'codes' => $codes, 'count' => $nb]);
            break;

        case 'delete_guest':
            require_admin();
            $in = json_input();
            $eid = (int) ($in['event_id'] ?? 0);
            $code = strtoupper(trim($in['code'] ?? ''));
            $stmt = $pdo->prepare("DELETE FROM tickets WHERE ticket_code = :c AND event_id = :e");
            $stmt->execute([':c' => $code, ':e' => $eid]);
            json_out(['status' => 'ok', 'deleted' => $stmt->rowCount()]);
            break;

        // ═══════════════════════════════════════════
        //  EXPORT CSV
        // ═══════════════════════════════════════════

        case 'export':
            require_admin_get();
            $eid = (int) ($_GET['event_id'] ?? 0);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="export_event_' . $eid . '_' . date('Y-m-d_His') . '.csv"');
            $out = fopen('php://output', 'w');
            fprintf($out, "\xEF\xBB\xBF"); // BOM UTF-8 pour Excel
            fputcsv($out, ['Nom','Prénom','Ticket','Code','Entré','Heure','Méthode'], ';');
            $stmt = $pdo->prepare(
                "SELECT nom,prenom,ticket_label,ticket_code,checked_in,checked_in_at,checked_in_by
                 FROM tickets WHERE event_id=:e ORDER BY nom,prenom"
            );
            $stmt->execute([':e' => $eid]);
            foreach ($stmt->fetchAll() as $r) {
                fputcsv($out, [
                    $r['nom'], $r['prenom'], $r['ticket_label'], $r['ticket_code'],
                    $r['checked_in'] ? 'Oui' : 'Non', $r['checked_in_at'] ?? '', $r['checked_in_by'] ?? '',
                ], ';');
            }
            fclose($out);
            exit;

        // ═══════════════════════════════════════════
        //  GÉNÉRATION TICKET PDF (HTML imprimable)
        // ═══════════════════════════════════════════

        case 'ticket_pdf':
            $code = strtoupper(trim($_GET['code'] ?? ''));
            if (!$code) { http_response_code(400); echo 'Code manquant'; exit; }

            $stmt = $pdo->prepare(
                "SELECT t.*, e.name AS event_name, e.event_date, e.location
                 FROM tickets t JOIN events e ON t.event_id = e.id
                 WHERE t.ticket_code = :c"
            );
            $stmt->execute([':c' => $code]);
            $ticket = $stmt->fetch();
            if (!$ticket) { http_response_code(404); echo 'Ticket introuvable'; exit; }

            header('Content-Type: text/html; charset=utf-8');
            render_ticket_html($ticket);
            exit;

        default:
            json_err('Action inconnue', 404);
    }

} catch (PDOException $e) {
    json_err('Erreur serveur: ' . $e->getMessage(), 500);
}

// ═══════════════════════════════════════════════════
//  HELPERS
// ═══════════════════════════════════════════════════

function json_out(array $d, int $c = 200): never {
    http_response_code($c);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}
function json_err(string $m, int $c = 400): never {
    json_out(['error' => $m], $c);
}
function json_input(): array {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}
function require_admin(): void {
    $in = json_input();
    if (($in['password'] ?? '') !== ADMIN_PASSWORD) json_err('Mot de passe incorrect', 403);
}
function require_admin_get(): void {
    if (($_GET['password'] ?? '') !== ADMIN_PASSWORD) json_err('Mot de passe incorrect', 403);
}

function render_ticket_html(array $t): void {
    $esc = fn($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $evName  = $esc($t['event_name']);
    $evSub   = $esc(implode('  •  ', array_filter([$t['event_date'], $t['location']])));
    $name    = $esc($t['prenom'] . ' ' . $t['nom']);
    $label   = $esc($t['ticket_label']);
    $code    = $esc($t['ticket_code']);
    $siteUrl = SITE_URL;
    echo <<<HTML
<!DOCTYPE html>
<html lang="fr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticket — {$name}</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
  @page { size: 148mm 105mm; margin: 0; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: system-ui, sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
  .toolbar { position: fixed; top: 0; left: 0; right: 0; background: #1a1a2e; color: #fff; padding: 12px 20px; text-align: center; z-index: 10; }
  .toolbar button { background: #e94560; color: #fff; border: none; border-radius: 8px; padding: 8px 20px; font-size: 0.9rem; cursor: pointer; margin: 0 4px; }
  .toolbar a { color: #fff; opacity: 0.7; font-size: 0.8rem; text-decoration: none; margin-left: 12px; }
  .ticket { width: 420px; background: #FFF8E7; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); overflow: hidden; margin-top: 60px; }
  .band { background: #1a1a2e; padding: 16px; text-align: center; color: #fff; }
  .band .title { font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #D4A017; }
  .band .evname { font-size: 20px; font-weight: 700; margin: 4px 0; }
  .band .sub { font-size: 10px; opacity: 0.7; }
  .accent { height: 3px; background: #e94560; }
  .body { display: flex; padding: 20px; align-items: center; }
  .info { flex: 1; }
  .info .guest { font-size: 22px; font-weight: 700; color: #1a1a2e; }
  .info .tlabel { font-size: 13px; color: #888; margin-top: 4px; }
  .info .stars { color: #D4A017; font-size: 16px; margin-top: 8px; letter-spacing: 4px; }
  .qr-box { text-align: center; }
  .qr-box canvas { display: block !important; }
  .qrcode { font-family: monospace; font-size: 9px; color: #aaa; margin-top: 6px; }
  .footer { text-align: center; font-size: 9px; color: #bbb; padding: 8px; border-top: 1px dashed #ddd; }
  @media print {
    .toolbar { display: none !important; }
    body { background: #fff; align-items: flex-start; padding-top: 0; }
    .ticket { box-shadow: none; margin-top: 0; border: 1px solid #ddd; }
  }
</style>
</head><body>
<div class="toolbar">
  <button onclick="window.print()">🖨️ Imprimer / PDF</button>
  <a href="{$siteUrl}">← Retour au scanner</a>
</div>
<div class="ticket">
  <div class="band">
    <div class="title">★ Rock n' Roll ★</div>
    <div class="evname">{$evName}</div>
    <div class="sub">{$evSub}</div>
  </div>
  <div class="accent"></div>
  <div class="body">
    <div class="info">
      <div class="guest">{$name}</div>
      <div class="tlabel">Ticket {$label}</div>
      <div class="stars">★ ★ ★ ★ ★</div>
    </div>
    <div class="qr-box">
      <div id="qr"></div>
      <div class="qrcode">{$code}</div>
    </div>
  </div>
  <div class="footer">Présentez ce ticket à l'entrée  ★  1 ticket = 1 entrée  ★  {$siteUrl}</div>
</div>
<script>new QRCode(document.getElementById('qr'),{text:"{$code}",width:110,height:110,colorDark:"#1a1a2e",colorLight:"#FFF8E7",correctLevel:QRCode.CorrectLevel.M});</script>
</body></html>
HTML;
}
