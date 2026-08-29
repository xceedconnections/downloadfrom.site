<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class FaqRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function isEmpty(): bool
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM faq_items');
        return (int) ($stmt?->fetchColumn() ?: 0) === 0;
    }

    /** @return array<string, array<int, array{q: string, a: string}>> */
    public function loadAll(): array
    {
        $data = [];
        $stmt = $this->pdo->query(
            'SELECT section, question, answer FROM faq_items ORDER BY section ASC, sort_order ASC, id ASC'
        );
        foreach ($stmt ? $stmt->fetchAll() : [] as $row) {
            $section = (string) ($row['section'] ?? 'home');
            if (!isset($data[$section])) {
                $data[$section] = [];
            }
            $data[$section][] = [
                'q' => (string) ($row['question'] ?? ''),
                'a' => (string) ($row['answer'] ?? ''),
            ];
        }

        return $data;
    }

    /** @return array<int, array{q: string, a: string}> */
    public function loadSection(string $section): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT question, answer FROM faq_items WHERE section = ? ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute([$section]);
        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            $items[] = [
                'q' => (string) ($row['question'] ?? ''),
                'a' => (string) ($row['answer'] ?? ''),
            ];
        }

        return $items;
    }

    /** @param array<string, array<int, array{q?: string, a?: string}>> $data */
    public function saveAll(array $data): bool
    {
        try {
            $this->pdo->beginTransaction();
            $this->pdo->exec('DELETE FROM faq_items');
            $stmt = $this->pdo->prepare(
                'INSERT INTO faq_items (section, sort_order, question, answer) VALUES (?, ?, ?, ?)'
            );

            foreach ($data as $section => $items) {
                if (!is_array($items)) {
                    continue;
                }
                $order = 0;
                foreach ($items as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $q = trim((string) ($item['q'] ?? ''));
                    $a = trim((string) ($item['a'] ?? ''));
                    if ($q === '' || $a === '') {
                        continue;
                    }
                    $stmt->execute([(string) $section, $order++, $q, $a]);
                }
            }

            $this->pdo->commit();
            return true;
        } catch (\Throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return false;
        }
    }

    /** @param array<int, array{q?: string, a?: string}> $items */
    public function saveSection(string $section, array $items): bool
    {
        $all = $this->loadAll();
        $all[$section] = $items;
        return $this->saveAll($all);
    }

    /** @param array<string, array<int, array{q?: string, a?: string}>> $data */
    public function importFromLegacy(array $data): void
    {
        $this->saveAll($data);
    }
}
