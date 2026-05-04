<?php

declare(strict_types=1);

class NoAvailabilityException extends RuntimeException {}

class TicketService
{
    private const MAX_TRY_COUNT = 3;

    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly TicketRepository $ticketRepository,
        private readonly PDO $pdo
    ) {}

    /**
     * @throws Throwable
     */
    public function reserve(int $eventId, int $userId): Ticket
    {
        $attempts = 0;

        while ($attempts < self::MAX_TRY_COUNT) {
            $this->pdo->beginTransaction();
            $attempts++;

            try {
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

                $this->pdo->commit();

                return $ticket;
            } catch (NoAvailabilityException $e) {
                $this->pdo->rollBack();
                throw $e;
            } catch (RuntimeException $e) {
                $this->pdo->rollBack();
                if ($attempts >= self::MAX_TRY_COUNT) {
                    throw new ConcurrencyException("Failed to reserve ticket after " . self::MAX_TRY_COUNT . " attempts");
                }
            }
        }
    }
}
