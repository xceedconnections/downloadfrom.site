<?php

declare(strict_types=1);

namespace App\Provider;

interface VideoProviderInterface
{
    public function getPlatform(): string;

    public function getManifest(): array;

    public function detect(string $url): bool;

    public function fetch(string $url): array;

    public function getMetadata(string $url): array;

    public function getAvailableLinks(string $url): array;

    public function getThumbnail(): ?string;

    public function getTitle(): ?string;

    public function getDuration(): ?int;

    public function getAuthor(): ?string;
}

interface ExtractorInterface
{
    public function extract(string $url): array;
}
