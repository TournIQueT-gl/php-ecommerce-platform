<?php
declare(strict_types=1);

namespace App\Events;

use App\Repositories\DomainEventRepository;
use App\Support\Logger;
use Throwable;

final class EventDispatcher
{
    public function __construct(private DomainEventRepository $events) {}

    /**
     * Queue an event for durable processing. Event delivery is intentionally
     * fail-soft so a missing foundation migration can never break a core user
     * action during a staged deployment.
     */
    public function dispatch(DomainEvent $event): ?int
    {
        try {
            return $this->events->enqueue($event);
        } catch (Throwable $e) {
            Logger::warning('Domain event could not be queued', [
                'event_key' => $event->key,
                'subject_user_id' => $event->subjectUserId,
                'actor_user_id' => $event->actorUserId,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
