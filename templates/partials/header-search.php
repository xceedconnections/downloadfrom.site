<?php

declare(strict_types=1);

/** @var array $config */
/** @var string $baseUrl */

?>
<div class="bh-search-panel" id="bh-search-panel" hidden>
    <div class="container">
        <form class="bh-search-form" action="<?= App\Security::escape($baseUrl) ?>/process" method="POST">
            <?= App\Security::csrfField($config) ?>
            <input type="hidden" name="service" value="<?= App\Security::escape(App\ServiceConfig::SERVICE_ALL) ?>">
            <label for="header-search-url" class="sr-only">Paste video or audio URL</label>
            <input
                type="url"
                id="header-search-url"
                name="url"
                class="bh-search-input"
                placeholder="Paste video or audio URL to download..."
                required
                autocomplete="off"
            >
            <button type="submit" class="bh-search-submit">Generate Links</button>
        </form>
    </div>
</div>
