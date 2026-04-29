<?php

declare(strict_types=1);

interface EventRepository
{
    public function findById(int $id): Event;
    public function save(Event $event): void;
}
