<?php
declare(strict_types=1);

namespace App\Events;

final class DomainEvent
{
    public readonly string $occurredAtUtc;

    public function __construct(
        public readonly string $key,
        public readonly ?int $subjectUserId = null,
        public readonly ?int $actorUserId = null,
        public readonly array $payload = [],
        ?string $occurredAtUtc = null
    ) {
        if (!preg_match('/^[a-z0-9_.-]{3,64}$/', $key)) {
            throw new \InvalidArgumentException('Invalid domain event key.');
        }
        $this->occurredAtUtc = $occurredAtUtc ?: gmdate('Y-m-d H:i:s');
    }
}
