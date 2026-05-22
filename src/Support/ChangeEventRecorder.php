<?php

namespace EvoDevOps\Base\Support;

use EvoDevOps\Base\Models\ChangeEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class ChangeEventRecorder
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>  $properties
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $eventName,
        ?Model $subject = null,
        ?Authenticatable $actor = null,
        ?array $before = null,
        ?array $after = null,
        array $properties = [],
        array $metadata = [],
        ?string $correlationId = null,
        ?string $causationId = null,
        string $source = 'app',
    ): ChangeEvent {
        $event = new ChangeEvent([
            'actor_user_id' => $actor?->getAuthIdentifier(),
            'event_name' => $eventName,
            'event_version' => 1,
            'correlation_id' => $correlationId,
            'causation_id' => $causationId,
            'source' => $source,
            'before' => $before,
            'after' => $after,
            'properties' => $properties,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);

        if ($subject) {
            $event->subject()->associate($subject);
        }

        $event->save();

        return $event;
    }
}
