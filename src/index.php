<?php

declare(strict_types=1);

require_once 'Event.php';
require_once 'Ticket.php';
require_once 'EventRepository.php';
require_once 'TicketRepository.php';
require_once 'MysqlEventRepository.php';
require_once 'MysqlTicketRepository.php';
require_once 'TicketService.php';
require_once 'ConcurrencyException.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = new PDO(
    dsn: 'mysql:host=mysql;dbname=ticketflow;charset=utf8mb4',
    username: 'ticketflow',
    password: 'ticketflow',
    options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$service = new TicketService(
    eventRepository: new MysqlEventRepository($pdo),
    ticketRepository: new MysqlTicketRepository($pdo),
    pdo: $pdo
);

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// POST /reserve
if ($method === 'POST' && $path === '/reserve') {
    $body = json_decode(file_get_contents('php://input'), true);
    $eventId = (int) ($body['event_id'] ?? 0);
    $userId  = (int) ($body['user_id'] ?? 0);

    if (!$eventId || !$userId) {
        http_response_code(400);
        echo json_encode(
            ['error' => 'event_id and user_id are required'],
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }

    try {
        $ticket = $service->reserve($eventId, $userId);
        http_response_code(201);
        echo json_encode([
            'status'     => 'reserved',
            'event_id'   => $ticket->eventId,
            'user_id'    => $ticket->userId,
            'expires_at' => $ticket->expiresAt->format('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE);
    } catch (NoAvailabilityException|ConcurrencyException $e) {
        http_response_code(409);
        echo json_encode(['error' => 'No tickets available'], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log("500 error: " . get_class($e) . " - " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// GET /status
if ($method === 'GET' && $path === '/status') {
    $eventId = (int) ($_GET['event_id'] ?? 1);

    $stmt = $pdo->prepare("
        SELECT
            e.name AS event_name,
            e.total_capacity,
            e.available_capacity,
            COUNT(t.id) AS tickets_created,
            SUM(t.status = 'reserved') AS tickets_reserved,
            SUM(t.status = 'paid') AS tickets_paid,
            e.total_capacity - COUNT(t.id) AS difference
        FROM events AS e
        LEFT JOIN tickets AS t ON t.event_id = e.id
        WHERE e.id = :event_id
        GROUP BY e.id, e.name, e.total_capacity, e.available_capacity
    ");
    $stmt->execute(['event_id' => $eventId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// GET /reset — resets the event so the test can be repeated
if ($method === 'GET' && $path === '/reset') {
    $eventId = (int) ($_GET['event_id'] ?? 1);
    $pdo->prepare("DELETE FROM tickets WHERE event_id = :id")->execute(['id' => $eventId]);
    $pdo->prepare("UPDATE events SET available_capacity = total_capacity WHERE id = :id")->execute(['id' => $eventId]);
    echo json_encode(['status' => 'reset ok'], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Not found'], JSON_UNESCAPED_UNICODE);
