<?php
/**
 * API REST — Gestion des tickets
 *
 * GET  /api.php?action=stats          → Statistiques
 * GET  /api.php?action=guests         → Liste des invités
 * GET  /api.php?action=search&q=...   → Recherche
 * GET  /api.php?action=history&n=20   → Historique des scans
 * POST /api.php?action=checkin        → Check-in un ticket {code, method}
 * POST /api.php?action=import         → Import JSON (admin, nécessite password)
 * POST /api.php?action=reset          → Reset check-ins (admin)
 * POST /api.php?action=update_event   → Modifier infos événement (admin)
 * GET  /api.php?action=event          → Infos événement
 * GET  /api.php?action=export         → Export CSV (admin)
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    $pdo = getDB();

    switch ($action) {

        // ─── Infos événement ───
        case 'event':
            $event = $pdo->query("SELECT * FROM events WHERE id = 1")->fetch();
            json_response($event ?: ['name' => '', 'event_date' => '', 'location' => '']);
            break;

        // ─── Statistiques ───
        case 'stats':
            $total = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE event_id = 1")->fetchColumn();
            $checkedIn = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE event_id = 1 AND checked_in = 1")->fetchColumn();
            json_response([
                'total'      => $total,
                'checked_in' => $checkedIn,
                'remaining'  => $total - $checkedIn,
            ]);
            break;

        // ─── Liste complète des invités ───
        case 'guests':
            $rows = $pdo->query(
                "SELECT ticket_code, nom, prenom, ticket_label, checked_in, checked_in_at
                 FROM tickets WHERE event_id = 1
                 ORDER BY nom, prenom, ticket_label"
            )->fetchAll();
            json_response($rows);
            break;

        // ─── Recherche ───
        case 'search':
            $q = '%' . ($_GET['q'] ?? '') . '%';
            $stmt = $pdo->prepare(
                "SELECT ticket_code, nom, prenom, ticket_label, checked_in, checked_in_at
                 FROM tickets WHERE event_id = 1
                 AND (CONCAT(prenom, ' ', nom) LIKE :q OR ticket_code LIKE :q2)
                 ORDER BY nom, prenom"
            );
            $stmt->execute([':q' => $q, ':q2' => $q]);
            json_response($stmt->fetchAll());
            break;

        // ─── Historique des derniers scans ───
        case 'history':
            $n = min(50, max(1, (int) ($_GET['n'] ?? 20)));
            $rows = $pdo->query(
                "SELECT ticket_code, nom, prenom, ticket_label, checked_in_at, checked_in_by
                 FROM tickets WHERE event_id = 1 AND checked_in = 1
                 ORDER BY checked_in_at DESC LIMIT $n"
            )->fetchAll();
            json_response($rows);
            break;

        // ─── Check-in ───
        case 'checkin':
            $input = json_decode(file_get_contents('php://input'), true);
            $code = strtoupper(trim($input['code'] ?? ''));
            $method = $input['method'] ?? 'scanner';

            if (!$code) {
                json_error('Code ticket manquant', 400);
            }

            // Trouver le ticket
            $stmt = $pdo->prepare(
                "SELECT id, ticket_code, nom, prenom, ticket_label, checked_in, checked_in_at
                 FROM tickets WHERE ticket_code = :code AND event_id = 1"
            );
            $stmt->execute([':code' => $code]);
            $ticket = $stmt->fetch();

            if (!$ticket) {
                json_response([
                    'status'  => 'unknown',
                    'message' => 'Ticket inconnu',
                    'code'    => $code,
                ]);
                break;
            }

            if ($ticket['checked_in']) {
                json_response([
                    'status'        => 'duplicate',
                    'message'       => 'Déjà scanné',
                    'nom'           => $ticket['nom'],
                    'prenom'        => $ticket['prenom'],
                    'ticket_label'  => $ticket['ticket_label'],
                    'checked_in_at' => $ticket['checked_in_at'],
                ]);
                break;
            }

            // Valider l'entrée
            $now = date('Y-m-d H:i:s');
            $upd = $pdo->prepare(
                "UPDATE tickets SET checked_in = 1, checked_in_at = :now, checked_in_by = :method
                 WHERE id = :id"
            );
            $upd->execute([':now' => $now, ':method' => $method, ':id' => $ticket['id']]);

            json_response([
                'status'        => 'valid',
                'message'       => 'Entrée validée',
                'nom'           => $ticket['nom'],
                'prenom'        => $ticket['prenom'],
                'ticket_label'  => $ticket['ticket_label'],
                'checked_in_at' => $now,
            ]);
            break;

        // ─── Import JSON (admin) ───
        case 'import':
            require_admin();
            $input = json_decode(file_get_contents('php://input'), true);
            $guests = $input['guests'] ?? [];

            if (empty($guests)) {
                json_error('Liste vide', 400);
            }

            $pdo->beginTransaction();

            // Supprimer les anciens tickets
            $pdo->exec("DELETE FROM tickets WHERE event_id = 1");

            $stmt = $pdo->prepare(
                "INSERT INTO tickets (event_id, ticket_code, nom, prenom, ticket_label)
                 VALUES (1, :code, :nom, :prenom, :label)"
            );

            $count = 0;
            foreach ($guests as $g) {
                $nom = trim($g['nom'] ?? '');
                $prenom = trim($g['prenom'] ?? '');
                $nb = max(1, (int) ($g['nb_tickets'] ?? 1));

                if (!$nom || !$prenom) continue;

                for ($i = 1; $i <= $nb; $i++) {
                    $code = strtoupper(bin2hex(random_bytes(6)));
                    $stmt->execute([
                        ':code'  => $code,
                        ':nom'   => $nom,
                        ':prenom'=> $prenom,
                        ':label' => "$i/$nb",
                    ]);
                    $count++;
                }
            }

            $pdo->commit();
            json_response(['status' => 'ok', 'imported' => $count]);
            break;

        // ─── Reset check-ins (admin) ───
        case 'reset':
            require_admin();
            $pdo->exec("UPDATE tickets SET checked_in = 0, checked_in_at = NULL, checked_in_by = NULL WHERE event_id = 1");
            $affected = $pdo->query("SELECT ROW_COUNT()")->fetchColumn();
            json_response(['status' => 'ok', 'reset' => (int) $affected]);
            break;

        // ─── Modifier événement (admin) ───
        case 'update_event':
            require_admin();
            $input = json_decode(file_get_contents('php://input'), true);
            $stmt = $pdo->prepare(
                "UPDATE events SET name = :name, event_date = :d, location = :loc WHERE id = 1"
            );
            $stmt->execute([
                ':name' => $input['name'] ?? '',
                ':d'    => $input['event_date'] ?? '',
                ':loc'  => $input['location'] ?? '',
            ]);
            json_response(['status' => 'ok']);
            break;

        // ─── Export CSV (admin) ───
        case 'export':
            require_admin_get();
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="checkin_export_' . date('Y-m-d_His') . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Nom', 'Prénom', 'Ticket', 'Code', 'Entré', 'Heure check-in', 'Méthode'], ';');
            $rows = $pdo->query(
                "SELECT nom, prenom, ticket_label, ticket_code, checked_in, checked_in_at, checked_in_by
                 FROM tickets WHERE event_id = 1 ORDER BY nom, prenom"
            )->fetchAll();
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['nom'], $r['prenom'], $r['ticket_label'], $r['ticket_code'],
                    $r['checked_in'] ? 'Oui' : 'Non',
                    $r['checked_in_at'] ?? '',
                    $r['checked_in_by'] ?? '',
                ], ';');
            }
            fclose($out);
            exit;

        default:
            json_error('Action inconnue', 404);
    }

} catch (PDOException $e) {
    json_error('Erreur serveur : ' . $e->getMessage(), 500);
}

// ─── Helpers ───

function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function json_error(string $message, int $code = 400): void {
    json_response(['error' => $message], $code);
}

function require_admin(): void {
    $input = json_decode(file_get_contents('php://input'), true);
    $pass = $input['password'] ?? '';
    if ($pass !== ADMIN_PASSWORD) {
        json_error('Mot de passe admin incorrect', 403);
    }
}

function require_admin_get(): void {
    $pass = $_GET['password'] ?? '';
    if ($pass !== ADMIN_PASSWORD) {
        json_error('Mot de passe admin incorrect', 403);
    }
}
