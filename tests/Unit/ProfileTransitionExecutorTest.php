<?php

use Xuple\EvoLayer\Base\Contracts\ProfileTransitionContributor;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionConflict;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionContext;
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

        public function contribute(ProfileTransitionContext $context, ProfileTransitionPlan $plan): void
        {
            $plan->replace($this->path, $context->profile);
        }
    };
    $secondContributor = new class($second) implements ProfileTransitionContributor
    {
        public function __construct(private string $path) {}

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
