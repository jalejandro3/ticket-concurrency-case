<?php

declare(strict_types=1);

class MysqlTicketRepository implements TicketRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function save(Ticket $ticket): Ticket
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO tickets (event_id, user_id, status, reserved_at, expires_at, paid_at)
             VALUES (:event_id, :user_id, :status, :reserved_at, :expires_at, :paid_at)"
        );

        $stmt->execute([
            'event_id' => $ticket->eventId,
            'user_id' => $ticket->userId,
            'status' => $ticket->status,
            'reserved_at' => $ticket->reservedAt->format('Y-m-d H:i:s'),
            'expires_at' => $ticket->expiresAt->format('Y-m-d H:i:s'),
            'paid_at' => $ticket->paidAt?->format('Y-m-d H:i:s'),
        ]);

        return $ticket;
    }

    public function countPaidByEventId(int $eventId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tickets WHERE event_id = :event_id AND status = 'paid'");
        $stmt->execute(['event_id' => $eventId]);

        return (int) $stmt->fetchColumn();
    }
}
