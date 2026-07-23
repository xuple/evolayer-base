<?php

use Xuple\EvoLayer\Base\Contracts\ProfileTransitionContributor;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileDefinition;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileRegistry;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionContext;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionExecutor;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionManager;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionPlan;

function contractContributor(
    string $id,
    array $provides,
    array $requires,
    int $priority,
    ArrayObject $calls,
    int $apiVersion = 1,
): ProfileTransitionContributor {
    return new class($id, $provides, $requires, $priority, $calls, $apiVersion) implements ProfileTransitionContributor
    {
        public function __construct(
            private string $contributorId,
            private array $providedCapabilities,
            private array $requiredCapabilities,
            private int $contributorPriority,
            private ArrayObject $calls,
            private int $version,
        ) {}

        public function id(): string
        {
            return $this->contributorId;
        }

        public function apiVersion(): int
        {
            return $this->version;
        }

        public function provides(): array
        {
            return $this->providedCapabilities;
        }

        public function requires(): array
        {
            return $this->requiredCapabilities;
        }

        public function priority(): int
        {
            return $this->contributorPriority;
        }

        public function contribute(ProfileTransitionContext $context, ProfileTransitionPlan $plan): void
        {
            $this->calls->append($this->contributorId);
        }
    };
}

function contractContext(?ProfileDefinition $definition = null): ProfileTransitionContext
{
    return new ProfileTransitionContext('test', '/tmp/.env', [], [], definition: $definition);
}

test('profile dependencies determine contributor order before priority tie breakers', function () {
    $calls = new ArrayObject;
    $consumer = contractContributor('consumer', ['complete'], ['prepared'], 1, $calls);
    $provider = contractContributor('provider', ['prepared'], [], 999, $calls);
    $manager = new ProfileTransitionManager(new ProfileTransitionExecutor, $consumer, $provider);

    $plan = $manager->plan(contractContext());

    expect($plan->conflicts())->toBe([])
        ->and($calls->getArrayCopy())->toBe(['provider', 'consumer']);
});

test('profile contracts reject duplicate IDs unsupported APIs and duplicate capability providers', function () {
    $calls = new ArrayObject;
    $manager = new ProfileTransitionManager(
        new ProfileTransitionExecutor,
        contractContributor('duplicate', ['shared'], [], 1, $calls),
        contractContributor('duplicate', ['other'], [], 2, $calls),
        contractContributor('unsupported', ['shared'], [], 3, $calls, apiVersion: 999),
    );

    $conflicts = $manager->plan(contractContext())->conflicts();

    expect($conflicts)->toContain('Duplicate profile contributor ID [duplicate].')
        ->and($conflicts)->toContain('Profile contributor [unsupported] uses unsupported API version [999].')
        ->and($conflicts)->toContain('Profile capability [shared] has more than one provider.')
        ->and($calls)->toHaveCount(0);
});

test('profile contracts reject missing capabilities and dependency cycles before contribution', function () {
    $calls = new ArrayObject;
    $first = contractContributor('first', ['first-ready'], ['second-ready'], 1, $calls);
    $second = contractContributor('second', ['second-ready'], ['first-ready'], 2, $calls);
    $definition = new ProfileDefinition(
        id: 'test',
        schemaVersion: 1,
        examples: [],
        features: [],
        requiredCapabilities: ['starter.application'],
        allowedOverrides: [],
        verificationRequirements: [],
    );
    $manager = new ProfileTransitionManager(new ProfileTransitionExecutor, $first, $second);

    $conflicts = $manager->plan(contractContext($definition))->conflicts();

    expect($conflicts)->toContain('Profile [test] requires missing capability [starter.application].')
        ->and($calls)->toHaveCount(0);

    $cycleConflicts = $manager->plan(contractContext())->conflicts();
    expect($cycleConflicts)->toContain('Profile contributor dependencies contain a cycle.')
        ->and($calls)->toHaveCount(0);
});

test('base registers only its generic demo and lean profiles', function () {
    $registry = app(ProfileRegistry::class);

    expect($registry->ids())->toBe(['demo', 'lean'])
        ->and($registry->get('demo'))->toBeInstanceOf(ProfileDefinition::class)
        ->and($registry->get('application'))->toBeNull();
});
