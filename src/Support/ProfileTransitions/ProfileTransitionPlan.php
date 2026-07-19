<?php

namespace Xuple\EvoLayer\Base\Support\ProfileTransitions;

use LogicException;

final class ProfileTransitionPlan
{
    /** @var array<string, string> */
    private array $replacements = [];

    /** @var array<string, true> */
    private array $deletions = [];

    /** @var list<string> */
    private array $conflicts = [];

    public function replace(string $path, string $contents): void
    {
        if (isset($this->deletions[$path])) {
            throw new LogicException("Profile transition already plans to delete [{$path}].");
        }

        $this->replacements[$path] = $contents;
    }

    public function delete(string $path): void
    {
        if (isset($this->replacements[$path])) {
            throw new LogicException("Profile transition already plans to replace [{$path}].");
        }

        $this->deletions[$path] = true;
    }

    public function conflict(string $message): void
    {
        $this->conflicts[] = $message;
    }

    /** @return array<string, string> */
    public function replacements(): array
    {
        return $this->replacements;
    }

    /** @return list<string> */
    public function deletions(): array
    {
        return array_keys($this->deletions);
    }

    /** @return list<string> */
    public function conflicts(): array
    {
        return $this->conflicts;
    }

    /** @return list<string> */
    public function affectedPaths(): array
    {
        return array_values(array_unique([...array_keys($this->replacements), ...array_keys($this->deletions)]));
    }
}
