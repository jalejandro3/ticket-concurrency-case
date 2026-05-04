<?php

declare(strict_types=1);

class Event
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly int $totalCapacity,
        public int $availableCapacity,
        public int $version,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            name: $row['name'],
            totalCapacity: (int) $row['total_capacity'],
            availableCapacity: (int) $row['available_capacity'],
            version: (int) $row['version'],
        );
    }
}
