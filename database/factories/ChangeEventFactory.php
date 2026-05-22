<?php

namespace EvoDevOps\Base\Database\Factories;

use EvoDevOps\Base\Models\ChangeEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChangeEvent>
 */
class ChangeEventFactory extends Factory
{
    protected $model = ChangeEvent::class;

    public function definition(): array
    {
        return [
            'event_name' => fake()->randomElement([
                'form_submission.created',
                'ai_triage.completed',
                'form_submission.marked_read',
                'form_submission.archived',
            ]),
            'event_version' => 1,
            'source' => 'app',
            'properties' => [],
            'occurred_at' => now(),
        ];
    }
}
