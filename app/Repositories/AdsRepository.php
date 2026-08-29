<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class AdsRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function isEmpty(): bool
    {
        $ads = (int) ($this->pdo->query('SELECT COUNT(*) FROM ads')?->fetchColumn() ?: 0);
        $settings = (int) ($this->pdo->query('SELECT COUNT(*) FROM ad_settings')?->fetchColumn() ?: 0);
        return $ads === 0 && $settings === 0;
    }

    /** @return array<string, mixed> */
    public function loadDocument(array $defaults): array
    {
        $doc = $defaults;

        $stmt = $this->pdo->query('SELECT enabled, download_modal_countdown FROM ad_settings WHERE id = 1 LIMIT 1');
        $row = $stmt ? $stmt->fetch() : false;
        if ($row) {
            $doc['enabled'] = (bool) ($row['enabled'] ?? false);
            $doc['download_modal_countdown'] = (int) ($row['download_modal_countdown'] ?? 5);
        }

        $doc['placement_map'] = $this->loadPlacementMap();
        $doc['ads'] = $this->loadAds();
        $doc['updated'] = $this->latestUpdatedAt($doc['ads']);

        return $doc;
    }

    /** @param array<string, mixed> $doc */
    public function saveDocument(array $doc, array $defaults): bool
    {
        $merged = array_replace_recursive($defaults, $doc);

        try {
            $ownsTransaction = !$this->pdo->inTransaction();
            if ($ownsTransaction) {
                $this->pdo->beginTransaction();
            }

            $this->pdo->exec('DELETE FROM ad_zone_assignments');
            $this->pdo->exec('DELETE FROM ad_placements');
            $this->pdo->exec('DELETE FROM ad_pages');
            $this->pdo->exec('DELETE FROM ads');
            $this->pdo->exec('DELETE FROM ad_settings');
            $stmt = $this->pdo->prepare(
                'INSERT INTO ad_settings (id, enabled, download_modal_countdown) VALUES (1, ?, ?)'
            );
            $stmt->execute([
                !empty($merged['enabled']) ? 1 : 0,
                max(0, min(30, (int) ($merged['download_modal_countdown'] ?? 5))),
            ]);

            $this->savePlacementMap($merged['placement_map'] ?? []);

            foreach ($merged['ads'] ?? [] as $ad) {
                if (is_array($ad)) {
                    $this->upsertAd($ad);
                }
            }

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return true;
        } catch (\Throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return false;
        }
    }

    /** @param array<string, mixed> $ad */
    public function upsertAd(array $ad): bool
    {
        $id = (string) ($ad['id'] ?? '');
        if ($id === '') {
            return false;
        }

        $content = is_array($ad['content'] ?? null) ? $ad['content'] : [];
        $popup = is_array($ad['popup'] ?? null) ? $ad['popup'] : [];

        try {
            $ownsTransaction = !$this->pdo->inTransaction();
            if ($ownsTransaction) {
                $this->pdo->beginTransaction();
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO ads
                (id, name, enabled, source, type, network, priority,
                 content_title, content_text, content_html, content_image_url, content_video_url, content_link_url, content_alt,
                 content_client_id, content_slot_id, content_network_code, content_width, content_height,
                 popup_delay_seconds, popup_show_once, popup_closable, popup_display, popup_content_mode, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                 name = VALUES(name), enabled = VALUES(enabled), source = VALUES(source), type = VALUES(type),
                 network = VALUES(network), priority = VALUES(priority),
                 content_title = VALUES(content_title), content_text = VALUES(content_text), content_html = VALUES(content_html),
                 content_image_url = VALUES(content_image_url), content_video_url = VALUES(content_video_url),
                 content_link_url = VALUES(content_link_url), content_alt = VALUES(content_alt),
                 content_client_id = VALUES(content_client_id), content_slot_id = VALUES(content_slot_id),
                 content_network_code = VALUES(content_network_code), content_width = VALUES(content_width),
                 content_height = VALUES(content_height), popup_delay_seconds = VALUES(popup_delay_seconds),
                 popup_show_once = VALUES(popup_show_once), popup_closable = VALUES(popup_closable),
                 popup_display = VALUES(popup_display), popup_content_mode = VALUES(popup_content_mode),
                 updated_at = VALUES(updated_at)'
            );
            $stmt->execute([
                $id,
                (string) ($ad['name'] ?? ''),
                !empty($ad['enabled']) ? 1 : 0,
                (string) ($ad['source'] ?? 'own'),
                (string) ($ad['type'] ?? 'banner'),
                (string) ($ad['network'] ?? 'custom'),
                (int) ($ad['priority'] ?? 0),
                (string) ($content['title'] ?? ''),
                (string) ($content['text'] ?? ''),
                (string) ($content['html'] ?? ''),
                (string) ($content['image_url'] ?? ''),
                (string) ($content['video_url'] ?? ''),
                (string) ($content['link_url'] ?? ''),
                (string) ($content['alt'] ?? 'Advertisement'),
                (string) ($content['client_id'] ?? ''),
                (string) ($content['slot_id'] ?? ''),
                (string) ($content['network_code'] ?? ''),
                (int) ($content['width'] ?? 728),
                (int) ($content['height'] ?? 90),
                (int) ($popup['delay_seconds'] ?? 3),
                !empty($popup['show_once_per_session']) ? 1 : 0,
                !isset($popup['closable']) || $popup['closable'] ? 1 : 0,
                in_array((string) ($popup['display'] ?? 'modal'), ['window', 'modal'], true) ? (string) $popup['display'] : 'modal',
                (string) ($popup['content_mode'] ?? 'html'),
                (int) ($ad['updated'] ?? time()),
            ]);

            $this->pdo->prepare('DELETE FROM ad_placements WHERE ad_id = ?')->execute([$id]);
            $this->pdo->prepare('DELETE FROM ad_pages WHERE ad_id = ?')->execute([$id]);

            $placeStmt = $this->pdo->prepare('INSERT INTO ad_placements (ad_id, placement) VALUES (?, ?)');
            foreach ($ad['placements'] ?? [] as $placement) {
                if (is_string($placement) && $placement !== '') {
                    $placeStmt->execute([$id, $placement]);
                }
            }

            $pageStmt = $this->pdo->prepare('INSERT INTO ad_pages (ad_id, page_type) VALUES (?, ?)');
            foreach ($ad['pages'] ?? [] as $page) {
                if (is_string($page) && $page !== '') {
                    $pageStmt->execute([$id, $page]);
                }
            }

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return true;
        } catch (\Throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return false;
        }
    }

    public function deleteAd(string $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM ads WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /** @param array<string, array<int, string>|string> $map */
    public function savePlacementMap(array $map): bool
    {
        try {
            $ownsTransaction = !$this->pdo->inTransaction();
            if ($ownsTransaction) {
                $this->pdo->beginTransaction();
            }
            $this->pdo->exec('DELETE FROM ad_zone_assignments');
            $stmt = $this->pdo->prepare(
                'INSERT INTO ad_zone_assignments (placement, ad_id, sort_order) VALUES (?, ?, ?)'
            );
            foreach ($map as $placement => $adIds) {
                $placement = trim((string) $placement);
                if ($placement === '') {
                    continue;
                }
                $ids = is_array($adIds) ? $adIds : [trim((string) $adIds)];
                $order = 0;
                foreach ($ids as $adId) {
                    $adId = trim((string) $adId);
                    if ($adId === '') {
                        continue;
                    }
                    $stmt->execute([$placement, $adId, $order]);
                    $order++;
                }
            }
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return true;
        } catch (\Throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return false;
        }
    }

    /** @param array<string, mixed> $data */
    public function importFromLegacy(array $data, array $defaults): void
    {
        $merged = array_replace_recursive($defaults, $data);

        $this->pdo->exec('DELETE FROM ad_zone_assignments');
        $this->pdo->exec('DELETE FROM ad_placements');
        $this->pdo->exec('DELETE FROM ad_pages');
        $this->pdo->exec('DELETE FROM ads');
        $this->pdo->exec('DELETE FROM ad_settings');

        $stmt = $this->pdo->prepare(
            'INSERT INTO ad_settings (id, enabled, download_modal_countdown) VALUES (1, ?, ?)'
        );
        $stmt->execute([
            !empty($merged['enabled']) ? 1 : 0,
            (int) ($merged['download_modal_countdown'] ?? 5),
        ]);

        foreach ($merged['ads'] ?? [] as $ad) {
            if (is_array($ad)) {
                $this->upsertAd($ad);
            }
        }

        $this->savePlacementMap($merged['placement_map'] ?? []);
    }

    /** @return array<string, array<int, string>> */
    private function loadPlacementMap(): array
    {
        $map = [];
        $stmt = $this->pdo->query(
            'SELECT placement, ad_id FROM ad_zone_assignments ORDER BY placement ASC, sort_order ASC, ad_id ASC'
        );
        foreach ($stmt ? $stmt->fetchAll() : [] as $row) {
            $placement = (string) ($row['placement'] ?? '');
            $adId = (string) ($row['ad_id'] ?? '');
            if ($placement === '' || $adId === '') {
                continue;
            }
            $map[$placement][] = $adId;
        }

        return $map;
    }

    /** @return array<int, array<string, mixed>> */
    private function loadAds(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM ads ORDER BY priority DESC, name ASC');
        $ads = [];
        foreach ($stmt ? $stmt->fetchAll() : [] as $row) {
            $id = (string) ($row['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $ads[] = $this->rowToAd($row, $this->loadAdPlacements($id), $this->loadAdPages($id));
        }

        return $ads;
    }

    /** @param array<string, mixed> $row @param string[] $placements @param string[] $pages @return array<string, mixed> */
    private function rowToAd(array $row, array $placements, array $pages): array
    {
        return [
            'id' => (string) ($row['id'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'enabled' => (bool) ($row['enabled'] ?? false),
            'source' => (string) ($row['source'] ?? 'own'),
            'type' => (string) ($row['type'] ?? 'banner'),
            'network' => (string) ($row['network'] ?? 'custom'),
            'priority' => (int) ($row['priority'] ?? 0),
            'placements' => $placements,
            'pages' => $pages !== [] ? $pages : ['all'],
            'content' => [
                'title' => (string) ($row['content_title'] ?? ''),
                'text' => (string) ($row['content_text'] ?? ''),
                'html' => (string) ($row['content_html'] ?? ''),
                'image_url' => (string) ($row['content_image_url'] ?? ''),
                'video_url' => (string) ($row['content_video_url'] ?? ''),
                'link_url' => (string) ($row['content_link_url'] ?? ''),
                'alt' => (string) ($row['content_alt'] ?? 'Advertisement'),
                'client_id' => (string) ($row['content_client_id'] ?? ''),
                'slot_id' => (string) ($row['content_slot_id'] ?? ''),
                'network_code' => (string) ($row['content_network_code'] ?? ''),
                'width' => (int) ($row['content_width'] ?? 728),
                'height' => (int) ($row['content_height'] ?? 90),
            ],
            'popup' => [
                'delay_seconds' => (int) ($row['popup_delay_seconds'] ?? 3),
                'show_once_per_session' => (bool) ($row['popup_show_once'] ?? false),
                'closable' => (bool) ($row['popup_closable'] ?? true),
                'display' => (string) ($row['popup_display'] ?? 'modal'),
                'content_mode' => (string) ($row['popup_content_mode'] ?? 'html'),
            ],
            'updated' => (int) ($row['updated_at'] ?? 0),
        ];
    }

    /** @return string[] */
    private function loadAdPlacements(string $adId): array
    {
        $stmt = $this->pdo->prepare('SELECT placement FROM ad_placements WHERE ad_id = ? ORDER BY placement');
        $stmt->execute([$adId]);
        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /** @return string[] */
    private function loadAdPages(string $adId): array
    {
        $stmt = $this->pdo->prepare('SELECT page_type FROM ad_pages WHERE ad_id = ? ORDER BY page_type');
        $stmt->execute([$adId]);
        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /** @param array<int, array<string, mixed>> $ads */
    private function latestUpdatedAt(array $ads): int
    {
        $max = 0;
        foreach ($ads as $ad) {
            $max = max($max, (int) ($ad['updated'] ?? 0));
        }
        return $max > 0 ? $max : time();
    }
}
