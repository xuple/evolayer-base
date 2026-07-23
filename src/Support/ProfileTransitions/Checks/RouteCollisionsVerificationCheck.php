<?php

namespace Xuple\EvoLayer\Base\Support\ProfileTransitions\Checks;

use Illuminate\Routing\Router;
use Xuple\EvoLayer\Base\Contracts\ProfileVerificationCheck;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileVerificationContext;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileVerificationManager;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileVerificationResult;
use Xuple\EvoLayer\Base\Support\PublishMap;
use Xuple\EvoLayer\Base\Support\RouteCollisionInspector;

final readonly class RouteCollisionsVerificationCheck implements ProfileVerificationCheck
{
    public function __construct(private Router $router, private PublishMap $map, private RouteCollisionInspector $routes) {}

    public function id(): string
    {
        return 'base.route-collisions';
    }

    public function apiVersion(): int
    {
        return ProfileVerificationManager::CONTRACT_VERSION;
    }

    public function capability(): string
    {
        return 'route-collisions';
    }

    public function verify(ProfileVerificationContext $context): ProfileVerificationResult
    {
        return $this->findings() === []
            ? ProfileVerificationResult::pass()
            : ProfileVerificationResult::fail('managed-route-collision', 'Rename the host route, disable the managed surface, or review and allowlist the exact collision ID before rerunning verification.');
    }

    public function fingerprint(ProfileVerificationContext $context): array
    {
        return ['collisions' => $this->findings()];
    }

    /** @return list<array{id: string, allowed: bool}> */
    private function findings(): array
    {
        return array_values(array_map(
            fn (array $collision): array => ['id' => $collision['id'], 'allowed' => $collision['allowed']],
            array_filter(
                $this->routes->inspect($this->router->getRoutes(), $this->map),
                fn (array $collision): bool => ! $collision['allowed'],
            ),
        ));
    }
}
