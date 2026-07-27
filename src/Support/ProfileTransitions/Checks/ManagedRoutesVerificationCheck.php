<?php

namespace Xuple\EvoLayer\Base\Support\ProfileTransitions\Checks;

use Illuminate\Routing\Router;
use Xuple\EvoLayer\Base\Contracts\ProfileVerificationCheck;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileVerificationContext;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileVerificationManager;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileVerificationResult;
use Xuple\EvoLayer\Base\Support\PublishMap;
use Xuple\EvoLayer\Base\Support\RouteCollisionInspector;

final readonly class ManagedRoutesVerificationCheck implements ProfileVerificationCheck
{
    public function __construct(private Router $router, private PublishMap $map, private RouteCollisionInspector $routes) {}

    public function id(): string
    {
        return 'base.managed-routes';
    }

    public function apiVersion(): int
    {
        return ProfileVerificationManager::CONTRACT_VERSION;
    }

    public function capability(): string
    {
        return 'managed-routes';
    }

    public function verify(ProfileVerificationContext $context): ProfileVerificationResult
    {
        return $this->findings() === []
            ? ProfileVerificationResult::pass()
            : ProfileVerificationResult::fail('managed-route-state-drift', 'Clear and deliberately rebuild the route cache, then rerun profile verification.');
    }

    public function fingerprint(ProfileVerificationContext $context): array
    {
        return ['findings' => $this->findings()];
    }

    /** @return list<array{id: string, type: string, surface: string, package_route: string}> */
    private function findings(): array
    {
        return $this->routes->stateFindings($this->router->getRoutes(), $this->map);
    }
}
