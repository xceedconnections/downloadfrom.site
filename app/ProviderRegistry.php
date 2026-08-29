<?php

declare(strict_types=1);

namespace App;

use App\Provider\VideoProviderInterface;

class ProviderRegistry
{
    /** @var array<string, VideoProviderInterface> */
    private array $providers = [];

    public function register(VideoProviderInterface $provider): void
    {
        $this->providers[$provider->getPlatform()] = $provider;
    }

    public function get(string $platform): ?VideoProviderInterface
    {
        return $this->providers[$platform] ?? null;
    }

    public function all(): array
    {
        return $this->providers;
    }
}
