<?php

use Xuple\EvoLayer\Base\Contracts\ProfileTransitionContributor;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\FilePrecondition;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionConflict;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionContext;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionExecutionException;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionExecutor;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionManager;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionPlan;

beforeEach(function () {
    $this->directory = sys_get_temp_dir().'/evolayer-profile-transition-'.bin2hex(random_bytes(4));
    mkdir($this->directory, 0755, true);
});

afterEach(function () {
    if (! is_dir($this->directory)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($this->directory);
});

test('preflight conflicts prevent every mutation', function () {
    $path = $this->directory.'/profile.env';
    file_put_contents($path, "BEFORE\n");
    $plan = new ProfileTransitionPlan;
    $plan->replace($path, "AFTER\n");
    $plan->conflict('managed page is modified');

    expect(fn () => (new ProfileTransitionExecutor)->execute($plan))
        ->toThrow(ProfileTransitionConflict::class)
        ->and(file_get_contents($path))->toBe("BEFORE\n");
});

test('dry run reports affected paths without writing', function () {
    $existing = $this->directory.'/existing.txt';
    $new = $this->directory.'/nested/new.txt';
    file_put_contents($existing, "BEFORE\n");
    $plan = new ProfileTransitionPlan;
    $plan->replace($existing, "AFTER\n");
    $plan->replace($new, "CREATED\n");

    $result = (new ProfileTransitionExecutor)->execute($plan, dryRun: true);

    expect($result->dryRun)->toBeTrue()
        ->and($result->affectedPaths)->toBe([$existing, $new])
        ->and(file_get_contents($existing))->toBe("BEFORE\n")
        ->and($new)->not->toBeFile();
});

test('duplicate operations require identical content and preconditions', function () {
    $replacement = $this->directory.'/replacement.txt';
    $deletion = $this->directory.'/deletion.txt';
    file_put_contents($replacement, "FIRST\n");
    file_put_contents($deletion, "FIRST\n");

    $replacementPlan = new ProfileTransitionPlan;
    $replacementPlan->replace($replacement, "AFTER\n");
    file_put_contents($replacement, "SECOND\n");

    expect(fn () => $replacementPlan->replace($replacement, "AFTER\n"))
        ->toThrow(LogicException::class, 'incompatible replacements');

    $deletionPlan = new ProfileTransitionPlan;
    $deletionPlan->delete($deletion);
    file_put_contents($deletion, "SECOND\n");

    expect(fn () => $deletionPlan->delete($deletion))
        ->toThrow(LogicException::class, 'incompatible deletions');
});

test('a successful plan replaces creates and deletes files', function () {
    $replaced = $this->directory.'/replaced.txt';
    $created = $this->directory.'/nested/created.txt';
    $deleted = $this->directory.'/deleted.txt';
    file_put_contents($replaced, "BEFORE\n");
    file_put_contents($deleted, "DELETE\n");
    $plan = new ProfileTransitionPlan;
    $plan->replace($replaced, "AFTER\n");
    $plan->replace($created, "CREATED\n");
    $plan->delete($deleted);

    (new ProfileTransitionExecutor)->execute($plan);

    expect(file_get_contents($replaced))->toBe("AFTER\n")
        ->and(file_get_contents($created))->toBe("CREATED\n")
        ->and($deleted)->not->toBeFile();
});

test('an apply failure restores exact contents existence and modes', function () {
    $replaced = $this->directory.'/replaced.txt';
    $created = $this->directory.'/nested/created.txt';
    $deleted = $this->directory.'/deleted.txt';
    file_put_contents($replaced, "BEFORE\n");
    file_put_contents($deleted, "DELETE\n");
    chmod($replaced, 0600);
    chmod($deleted, 0640);
    $plan = new ProfileTransitionPlan;
    $plan->replace($replaced, "AFTER\n");
    $plan->replace($created, "CREATED\n");
    $plan->delete($deleted);

    $executor = new class extends ProfileTransitionExecutor
    {
        protected function afterMutation(string $path, int $mutation): void
        {
            if ($mutation === 3) {
                throw new RuntimeException('injected apply failure');
            }
        }
    };

    expect(fn () => $executor->execute($plan))
        ->toThrow(RuntimeException::class, 'injected apply failure')
        ->and(file_get_contents($replaced))->toBe("BEFORE\n")
        ->and(fileperms($replaced) & 0777)->toBe(0600)
        ->and($created)->not->toBeFile()
        ->and(file_get_contents($deleted))->toBe("DELETE\n")
        ->and(fileperms($deleted) & 0777)->toBe(0640)
        ->and($this->directory.'/nested')->not->toBeDirectory();
});

test('the manager composes tagged contributors before executing', function () {
    $first = $this->directory.'/first.txt';
    $second = $this->directory.'/second.txt';
    $context = new ProfileTransitionContext('lean', $this->directory.'/.env', [], []);
    $firstContributor = new class($first) implements ProfileTransitionContributor
    {
        public function __construct(private string $path) {}

        public function id(): string
        {
            return 'first';
        }

        public function apiVersion(): int
        {
            return 1;
        }

        public function provides(): array
        {
            return ['first-ready'];
        }

        public function requires(): array
        {
            return [];
        }

        public function priority(): int
        {
            return 100;
        }

        public function contribute(ProfileTransitionContext $context, ProfileTransitionPlan $plan): void
        {
            $plan->replace($this->path, $context->profile);
        }
    };
    $secondContributor = new class($second) implements ProfileTransitionContributor
    {
        public function __construct(private string $path) {}

        public function id(): string
        {
            return 'second';
        }

        public function apiVersion(): int
        {
            return 1;
        }

        public function provides(): array
        {
            return ['second-ready'];
        }

        public function requires(): array
        {
            return ['first-ready'];
        }

        public function priority(): int
        {
            return 1;
        }

        public function contribute(ProfileTransitionContext $context, ProfileTransitionPlan $plan): void
        {
            $plan->replace($this->path, $context->environmentPath);
        }
    };
    $manager = new ProfileTransitionManager(
        new ProfileTransitionExecutor,
        $firstContributor,
        $secondContributor,
    );

    $manager->execute($context);

    expect(file_get_contents($first))->toBe('lean')
        ->and(file_get_contents($second))->toBe($this->directory.'/.env');
});

test('a file changed after planning aborts before snapshot or mutation', function () {
    $path = $this->directory.'/profile.env';
    file_put_contents($path, "PLANNED\n");
    $plan = new ProfileTransitionPlan;
    $plan->replace($path, "AFTER\n");
    file_put_contents($path, "CONCURRENT\n");

    try {
        (new ProfileTransitionExecutor)->execute($plan);
        $this->fail('Expected the changed precondition to abort execution.');
    } catch (ProfileTransitionExecutionException $exception) {
        expect($exception->getPrevious())->toBeInstanceOf(RuntimeException::class)
            ->and($exception->getPrevious()->getMessage())->toContain('changed after the plan was created')
            ->and($exception->rollbackFailures)->toBe([])
            ->and(file_get_contents($path))->toBe("CONCURRENT\n");
    }
});

test('a file swapped after snapshot is revalidated immediately before replacement', function () {
    $path = $this->directory.'/profile.env';
    file_put_contents($path, "PLANNED\n");
    $plan = new ProfileTransitionPlan;
    $plan->replace($path, "AFTER\n");

    $executor = new class extends ProfileTransitionExecutor
    {
        protected function beforeMutation(string $path, int $mutation): void
        {
            file_put_contents($path, "CONCURRENT\n");
        }
    };

    try {
        $executor->execute($plan);
        $this->fail('Expected the changed precondition to abort execution.');
    } catch (ProfileTransitionExecutionException $exception) {
        expect($exception->getPrevious()->getMessage())->toContain('changed after the plan was created')
            ->and($exception->rollbackFailures)->toBe([])
            ->and(file_get_contents($path))->toBe("CONCURRENT\n");
    }
});

test('a linked ancestor swapped after snapshot is rejected before staging', function () {
    $managedDirectory = $this->directory.'/managed';
    $outsideDirectory = $this->directory.'/outside';
    mkdir($managedDirectory);
    mkdir($outsideDirectory);
    $path = $managedDirectory.'/profile.env';
    file_put_contents($path, "PLANNED\n");
    $plan = new ProfileTransitionPlan;
    $plan->replace($path, "AFTER\n");

    $executor = new class($managedDirectory, $outsideDirectory) extends ProfileTransitionExecutor
    {
        public function __construct(
            private readonly string $managedDirectory,
            private readonly string $outsideDirectory,
        ) {}

        protected function beforeMutation(string $path, int $mutation): void
        {
            rename($this->managedDirectory, $this->managedDirectory.'-original');
            symlink($this->outsideDirectory, $this->managedDirectory);
        }
    };

    try {
        $executor->execute($plan);
        $this->fail('Expected the linked ancestor to abort execution.');
    } catch (ProfileTransitionExecutionException $exception) {
        expect($exception->getPrevious()->getMessage())->toContain('linked or non-directory ancestor')
            ->and($outsideDirectory.'/profile.env')->not->toBeFile();
    } finally {
        if (is_link($managedDirectory)) {
            unlink($managedDirectory);
        }

        if (is_dir($managedDirectory.'-original')) {
            rename($managedDirectory.'-original', $managedDirectory);
        }
    }
});

test('guarded evidence is revalidated immediately before an unrelated replacement', function () {
    $evidence = $this->directory.'/managed.tsx';
    $manifest = $this->directory.'/manifest.json';
    file_put_contents($evidence, "PRISTINE\n");
    file_put_contents($manifest, "BEFORE\n");
    $plan = new ProfileTransitionPlan;
    $plan->guard($evidence, FilePrecondition::capture($evidence));
    $plan->replace($manifest, "AFTER\n");

    $executor = new class($evidence) extends ProfileTransitionExecutor
    {
        public function __construct(private readonly string $evidence) {}

        protected function beforeMutation(string $path, int $mutation): void
        {
            file_put_contents($this->evidence, "CONCURRENT\n");
        }
    };

    try {
        $executor->execute($plan);
        $this->fail('Expected changed adoption evidence to abort execution.');
    } catch (ProfileTransitionExecutionException $exception) {
        expect($exception->getPrevious()->getMessage())->toContain('changed after the plan was created')
            ->and(file_get_contents($manifest))->toBe("BEFORE\n")
            ->and(file_get_contents($evidence))->toBe("CONCURRENT\n");
    }
});

test('rollback attempts every mutated path and retains the original apply failure', function () {
    $first = $this->directory.'/first.txt';
    $second = $this->directory.'/second.txt';
    file_put_contents($first, "FIRST BEFORE\n");
    file_put_contents($second, "SECOND BEFORE\n");
    $plan = new ProfileTransitionPlan;
    $plan->replace($first, "FIRST AFTER\n");
    $plan->replace($second, "SECOND AFTER\n");

    $executor = new class extends ProfileTransitionExecutor
    {
        /** @var list<string> */
        public array $rollbackAttempts = [];

        protected function afterMutation(string $path, int $mutation): void
        {
            if ($mutation === 2) {
                throw new RuntimeException('injected apply failure');
            }
        }

        protected function restoreSnapshot(string $path, array $snapshot): void
        {
            $this->rollbackAttempts[] = $path;

            throw new RuntimeException('injected rollback failure');
        }
    };

    try {
        $executor->execute($plan);
        $this->fail('Expected the apply failure.');
    } catch (ProfileTransitionExecutionException $exception) {
        expect($exception->getPrevious())->toBeInstanceOf(RuntimeException::class)
            ->and($exception->getPrevious()->getMessage())->toBe('injected apply failure')
            ->and($exception->rollbackFailures)->toHaveCount(2)
            ->and($executor->rollbackAttempts)->toBe([$second, $first])
            ->and(file_get_contents($first))->toBe("FIRST AFTER\n")
            ->and(file_get_contents($second))->toBe("SECOND AFTER\n");
    }
});

test('incompatible duplicate replacements are rejected while identical ones deduplicate', function () {
    $path = $this->directory.'/profile.env';
    $plan = new ProfileTransitionPlan;
    $plan->replace($path, "SAME\n");
    $plan->replace($path, "SAME\n");

    expect($plan->replacements())->toBe([$path => "SAME\n"])
        ->and(fn () => $plan->replace($path, "DIFFERENT\n"))
        ->toThrow(LogicException::class, 'incompatible replacements');
});
