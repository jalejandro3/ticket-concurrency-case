<?php

declare(strict_types=1);

interface TicketRepository
{
    public function save(Ticket $ticket): Ticket;
    public function countPaidByEventId(int $eventId): int;
}
