<?php

namespace EvoDevOps\Base\Database\Factories;

use EvoDevOps\Base\Models\FormSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormSubmission>
 */
class FormSubmissionFactory extends Factory
{
    protected $model = FormSubmission::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'type' => fake()->randomElement(['contact', 'enquiry', 'complaint']),
            'status' => 'new',
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional(0.6)->phoneNumber(),
            'subject' => fake()->sentence(6, true),
            'message' => fake()->paragraphs(2, true),
            'honeypot' => null,
        ];
    }

    public function contact(): static
    {
        return $this->state(['type' => 'contact']);
    }

    public function enquiry(): static
    {
        return $this->state(['type' => 'enquiry']);
    }

    public function complaint(): static
    {
        return $this->state(['type' => 'complaint']);
    }

    public function read(): static
    {
        return $this->state(['status' => 'read']);
    }

    public function archived(): static
    {
        return $this->state(['status' => 'archived']);
    }
}
