<?php

namespace Xuple\EvoLayer\Base\Support;

use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * Registry of ontology.yaml files contributed by EvoDevOps packages.
 *
 * Each variant package (Commerce, SaaS, RLS, etc.) registers its own
 * ontology.yaml during its service provider's boot phase. The
 * OntologyCompiler walks the registry to produce a merged compile output
 * keyed by namespace, plus the host's own ontology.yaml.
 *
 * Bound as a singleton in BaseServiceProvider — Base self-registers.
 */
class OntologyRegistry
{
    /** @var array<string, string> namespace → absolute path */
    private array $sources = [];

    public function register(string $namespace, string $path): void
    {
        if (! is_file($path)) {
            throw new RuntimeException(
                "OntologyRegistry: cannot register namespace [{$namespace}] — file not found at [{$path}]."
            );
        }

        if (isset($this->sources[$namespace]) && $this->sources[$namespace] !== $path) {
            throw new RuntimeException(
                "OntologyRegistry: namespace [{$namespace}] already registered to ".
                "[{$this->sources[$namespace]}], cannot re-register to [{$path}]."
            );
        }

        $declared = $this->declaredNamespace($path);
        if ($declared !== null && $declared !== $namespace) {
            throw new RuntimeException(
                "OntologyRegistry: namespace mismatch — registering [{$namespace}] ".
                "but file [{$path}] declares [{$declared}]."
            );
        }

        $this->sources[$namespace] = $path;
    }

    /**
     * @return array<string, string> namespace → absolute path
     */
    public function all(): array
    {
        return $this->sources;
    }

    public function get(string $namespace): ?string
    {
        return $this->sources[$namespace] ?? null;
    }

    public function has(string $namespace): bool
    {
        return isset($this->sources[$namespace]);
    }

    private function declaredNamespace(string $path): ?string
    {
        $parsed = Yaml::parseFile($path);

        return is_array($parsed) && isset($parsed['namespace']) && is_string($parsed['namespace'])
            ? $parsed['namespace']
            : null;
    }
}
