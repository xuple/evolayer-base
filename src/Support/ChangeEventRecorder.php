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

        // Authenticatable contracts don't expose morph-friendly identity, so we
        // associate via the model interface when available.
        if ($actor instanceof Model) {
            $event->actor()->associate($actor);
        } elseif ($actor !== null) {
            // Best-effort: store the auth identifier in actor_id with a
            // placeholder actor_type so the record is traceable.
            $event->actor_type = $actor::class;
            $event->actor_id = $actor->getAuthIdentifier();
        }

        $event->save();

        return $event;
    }
}
