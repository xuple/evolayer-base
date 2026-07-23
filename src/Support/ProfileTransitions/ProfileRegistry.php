<?php

namespace Xuple\EvoLayer\Base\Support\ProfileTransitions;

use InvalidArgumentException;

final class ProfileRegistry
{
    /** @var array<string, ProfileDefinition> */
    private array $definitions = [];

    public function register(ProfileDefinition $definition): void
    {
        if (isset($this->definitions[$definition->id])) {
            throw new InvalidArgumentException("Profile [{$definition->id}] is already registered.");
        }

        $this->definitions[$definition->id] = $definition;
    }

    public function get(string $id): ?ProfileDefinition
    {
        return $this->definitions[$id] ?? null;
    }

    /** @return list<string> */
    public function ids(): array
    {
        $ids = array_keys($this->definitions);
        sort($ids);

        return $ids;
    }
}
