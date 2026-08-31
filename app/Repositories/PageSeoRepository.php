<?php

declare(strict_types=1);

namespace App\Repositories;

use App\PageSeoDefaults;
use PDO;

final class PageSeoRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function isEmpty(): bool
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM page_seo');
        return (int) ($stmt?->fetchColumn() ?: 0) === 0;
    }

    /** @return array<string, array<string, string>> */
    public function loadAllKeyed(): array
    {
        $rows = [];
        $stmt = $this->pdo->query(
            'SELECT page_key, page_label, page_type, title, h1, meta_description, description,
                    keywords, og_image, robots, seo_content
             FROM page_seo ORDER BY page_type ASC, page_label ASC'
        );
        foreach ($stmt ? $stmt->fetchAll() : [] as $row) {
            $key = (string) ($row['page_key'] ?? '');
            if ($key === '') {
                continue;
            }
            $rows[$key] = $this->normalizeRow($row);
        }

        return $rows;
    }

    /** @return list<array<string, string>> */
    public function loadAll(): array
    {
        return array_values($this->loadAllKeyed());
    }

    /** @return array<string, string>|null */
    public function get(string $pageKey): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT page_key, page_label, page_type, title, h1, meta_description, description,
                    keywords, og_image, robots, seo_content
             FROM page_seo WHERE page_key = ? LIMIT 1'
        );
        $stmt->execute([$pageKey]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return $this->normalizeRow($row);
    }

    /** @param array<string, string> $data */
    public function save(string $pageKey, array $data): bool
    {
        $existing = $this->get($pageKey);
        if ($existing === null) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE page_seo SET
                page_label = :page_label,
                title = :title,
                h1 = :h1,
                meta_description = :meta_description,
                description = :description,
                keywords = :keywords,
                og_image = :og_image,
                robots = :robots,
                seo_content = :seo_content
             WHERE page_key = :page_key'
        );

        return $stmt->execute([
            'page_key' => $pageKey,
            'page_label' => trim((string) ($data['page_label'] ?? $existing['page_label'])),
            'title' => trim((string) ($data['title'] ?? '')),
            'h1' => trim((string) ($data['h1'] ?? '')),
            'meta_description' => trim((string) ($data['meta_description'] ?? '')),
            'description' => trim((string) ($data['description'] ?? '')),
            'keywords' => trim((string) ($data['keywords'] ?? '')),
            'og_image' => trim((string) ($data['og_image'] ?? '')),
            'robots' => trim((string) ($data['robots'] ?? 'index, follow')) ?: 'index, follow',
            'seo_content' => trim((string) ($data['seo_content'] ?? '')),
        ]);
    }

    public function seedDefaultsIfEmpty(): void
    {
        if (!$this->isEmpty()) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO page_seo
                (page_key, page_label, page_type, title, h1, meta_description, description, keywords, og_image, robots, seo_content)
             VALUES
                (:page_key, :page_label, :page_type, :title, :h1, :meta_description, :description, :keywords, :og_image, :robots, :seo_content)'
        );

        foreach (PageSeoDefaults::all() as $row) {
            $stmt->execute($row);
        }
    }

    /** @param array<string, mixed> $row
     *  @return array<string, string>
     */
    private function normalizeRow(array $row): array
    {
        return [
            'page_key' => (string) ($row['page_key'] ?? ''),
            'page_label' => (string) ($row['page_label'] ?? ''),
            'page_type' => (string) ($row['page_type'] ?? 'core'),
            'title' => (string) ($row['title'] ?? ''),
            'h1' => (string) ($row['h1'] ?? ''),
            'meta_description' => (string) ($row['meta_description'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'keywords' => (string) ($row['keywords'] ?? ''),
            'og_image' => (string) ($row['og_image'] ?? ''),
            'robots' => (string) ($row['robots'] ?? 'index, follow'),
            'seo_content' => (string) ($row['seo_content'] ?? ''),
        ];
    }
}
