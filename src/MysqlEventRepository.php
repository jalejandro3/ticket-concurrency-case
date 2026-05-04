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
        $currentVersion = $event->version;
        $newVersion = $currentVersion + 1;

        $stmt = $this->pdo->prepare("UPDATE events SET version = :new_version, available_capacity = :capacity WHERE id = :id AND version = :current_version");
        $stmt->execute([
            'current_version'  => $currentVersion,
            'new_version' => $newVersion,
            'capacity' => $event->availableCapacity,
            'id'       => $event->id,
        ]);

        $updatedRow = $stmt->rowCount();

        if ($updatedRow === 0) {
            throw new RuntimeException("Concurrency error: event was updated by another process");
        }
    }
}
