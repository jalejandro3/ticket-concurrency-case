<?php

declare(strict_types=1);

class NoAvailabilityException extends RuntimeException {}

class TicketService
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly TicketRepository $ticketRepository,
    ) {}

    public function reserve(int $eventId, int $userId): Ticket
    {
        $event = $this->eventRepository->findById($eventId);

        if ($event->availableCapacity <= 0) {
            throw new NoAvailabilityException("No tickets available for event $eventId");
        }

        $ticket = new Ticket(
            eventId: $eventId,
            userId: $userId,
            status: 'reserved',
            reservedAt: new DateTime(),
            expiresAt: new DateTime('+10 minutes')
        );

        $this->ticketRepository->save($ticket);

        $event->availableCapacity -= 1;
        $this->eventRepository->save($event);

        return $ticket;
    }
}
