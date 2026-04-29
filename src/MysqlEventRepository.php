<?php

declare(strict_types=1);

class MysqlEventRepository implements EventRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function findById(int $id): Event
    {
        $stmt = $this->pdo->prepare("SELECT * FROM events WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new RuntimeException("Event not found: $id");
        }

        return Event::fromRow($row);
    }

    public function save(Event $event): void
    {
        $stmt = $this->pdo->prepare("UPDATE events SET available_capacity = :capacity WHERE id = :id");
        $stmt->execute([
            'capacity' => $event->availableCapacity,
            'id'       => $event->id,
        ]);
    }
}
