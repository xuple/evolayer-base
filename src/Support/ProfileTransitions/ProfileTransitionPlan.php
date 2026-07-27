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

    /** @var array<string, FilePrecondition> */
    private array $preconditions = [];

    /** @var array<string, FilePrecondition> */
    private array $guards = [];

    public function replace(string $path, string $contents, ?FilePrecondition $precondition = null): void
    {
        if (isset($this->deletions[$path])) {
            throw new LogicException("Profile transition already plans to delete [{$path}].");
        }

        $precondition ??= FilePrecondition::capture($path);

        if (isset($this->replacements[$path])) {
            if ($this->replacements[$path] === $contents
                && $this->preconditions[$path] == $precondition) {
                return;
            }

            throw new LogicException("Profile transition contains incompatible replacements for [{$path}].");
        }

        if ($precondition->exists && hash('sha256', $contents) === $precondition->sha256) {
            return;
        }

        $this->replacements[$path] = $contents;
        $this->preconditions[$path] = $precondition;
    }

    public function delete(string $path, ?FilePrecondition $precondition = null): void
    {
        if (isset($this->replacements[$path])) {
            throw new LogicException("Profile transition already plans to replace [{$path}].");
        }

        $precondition ??= FilePrecondition::capture($path);

        if (isset($this->deletions[$path])) {
            if ($this->preconditions[$path] == $precondition) {
                return;
            }

            throw new LogicException("Profile transition contains incompatible deletions for [{$path}].");
        }

        $this->deletions[$path] = true;
        $this->preconditions[$path] = $precondition;
    }

    public function conflict(string $message): void
    {
        $this->conflicts[] = $message;
    }

    public function guard(string $path, FilePrecondition $precondition): void
    {
        if (isset($this->guards[$path]) && $this->guards[$path] != $precondition) {
            throw new LogicException("Profile transition contains incompatible guards for [{$path}].");
        }

        $this->guards[$path] = $precondition;
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

    public function precondition(string $path): FilePrecondition
    {
        return $this->preconditions[$path]
            ?? throw new LogicException("Profile transition path [{$path}] has no precondition.");
    }

    /** @return array<string, FilePrecondition> */
    public function guards(): array
    {
        return $this->guards;
    }
}
