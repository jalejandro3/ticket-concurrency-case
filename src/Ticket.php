<?php

declare(strict_types=1);

class Ticket
{
    public int $id;

    public function __construct(
        public readonly int $eventId,
        public readonly int $userId,
        public string $status,
        public readonly DateTime $reservedAt,
        public readonly DateTime $expiresAt,
        public ?DateTime $paidAt = null
    ) {}

    public static function fromRow(array $row): self
    {
        $ticket = new self(
            eventId: (int) $row['event_id'],
            userId: (int) $row['user_id'],
            status: $row['status'],
            reservedAt: new DateTime($row['reserved_at']),
            expiresAt: new DateTime($row['expires_at']),
            paidAt: $row['paid_at'] ? new DateTime($row['paid_at']) : null
        );

        $ticket->id = (int) $row['id'];

        return $ticket;
    }
}
