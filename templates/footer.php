    </main>
    <?php
    $adPageType = $adPageType ?? 'all';
    $adServiceId = $adServiceId ?? null;
    $adProviderId = $adProviderId ?? null;
    if (isset($adManager) && $adManager->hasPlacement('footer_banner', $adPageType, $adServiceId, $adProviderId)): ?>
    <div class="cz-strip cz-strip-footer">
        <div class="container">
            <?php $placement = 'footer_banner'; require __DIR__ . '/partials/ad-zone.php'; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php
    $footerText = $settings ? (string) ($settings->get('footer_text') ?: '') : '';
    if ($footerText === '') {
        $footerText = 'Free online video URL tool for supported platforms. Retrieve public metadata and permitted viewing options.';
    }
    $footerSiteName = $settings ? (string) ($settings->get('site_name') ?: $config['app']['name']) : $config['app']['name'];
    ?>
    <footer class="site-footer">
        <div class="footer-top-strip" aria-hidden="true"></div>
        <div class="container">
            <div class="footer-brand">
                <a href="<?= App\Security::escape($baseUrl) ?>/" class="footer-logo">
                    <span class="footer-logo-text"><?= App\Security::escape($footerSiteName) ?></span>
                </a>
                <p class="footer-tagline"><?= App\Security::escape($footerText) ?></p>
            </div>
            <div class="footer-grid">
                <div class="footer-col">
                    <h4>Video Platforms</h4>
                    <ul>
                        <?php foreach (($videoPlatforms ?? $platforms) as $p): ?>
                        <li><a href="<?= App\Security::escape($baseUrl . '/' . $p['slug']) ?>"><?php $iconSize = 'xs'; require __DIR__ . '/partials/platform-icon.php'; ?><span><?= App\Security::escape($p['name']) ?></span><?php require __DIR__ . '/partials/platform-new-badge.php'; ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php if (!empty($audioPlatforms)): ?>
                <div class="footer-col">
                    <h4>Audio Platforms</h4>
                    <ul>
                        <?php foreach ($audioPlatforms as $p): ?>
                        <li><a href="<?= App\Security::escape($baseUrl . '/' . $p['slug']) ?>"><?php $iconSize = 'xs'; require __DIR__ . '/partials/platform-icon.php'; ?><span><?= App\Security::escape($p['name']) ?></span><?php require __DIR__ . '/partials/platform-new-badge.php'; ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                <div class="footer-col">
                    <h4>Legal</h4>
                    <ul>
                        <li><a href="<?= App\Security::escape($baseUrl) ?>/privacy">Privacy Policy</a></li>
                        <li><a href="<?= App\Security::escape($baseUrl) ?>/terms">Terms of Service</a></li>
                        <li><a href="<?= App\Security::escape($baseUrl) ?>/dmca">DMCA / Copyright</a></li>
                        <li><a href="<?= App\Security::escape($baseUrl) ?>/faq">FAQs</a></li>
                        <li><a href="<?= App\Security::escape($baseUrl) ?>/contact">Contact</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> <?= App\Security::escape($footerSiteName) ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>
    <?php if (isset($adManager) && $adManager->isEnabled()): ?>
    <?php
        $footerAdPageType = $adPageType ?? 'all';
        $footerAdServiceId = $adServiceId ?? null;
        $footerAdProviderId = $adProviderId ?? null;
        $adCfg = $adManager->getConfig($footerAdPageType, $footerAdServiceId, $footerAdProviderId);
    ?>
    <script>window.__DFZ__=<?= json_encode($adCfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
    <script src="<?= App\Security::escape($baseUrl) ?>/assets/js/zones.js" defer></script>
    <?php endif; ?>
    <?php if (!empty($resultToken ?? null)): ?>
    <?php
        $dlCountdown = isset($adManager)
            ? max(0, (int) ($adManager->getData()['download_modal_countdown'] ?? 5))
            : 5;
        $downloadModalHtml = '';
        if (isset($adManager)) {
            foreach ($adManager->getDownloadModalAds($adServiceId ?? null, ($adProviderId ?? '') !== '' ? $adProviderId : null) as $ad) {
                $downloadModalHtml .= $adManager->renderAd($ad, 'download_modal');
            }
        }
        $downloadCfg = [
            'countdown' => $dlCountdown,
            'modalHtml' => $downloadModalHtml,
            'useGate' => $dlCountdown > 0,
        ];
    ?>
    <script>window.__DOWNLOAD_CONFIG__=<?= json_encode($downloadCfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
    <script src="<?= App\Security::escape($baseUrl) ?>/assets/js/download.js" defer></script>
    <script>
    window.__RESULT_TOKEN__ = <?= json_encode($resultToken) ?>;
    window.__CLEANUP_URL__ = <?= json_encode($baseUrl . '/api/cleanup/' . $resultToken) ?>;
    </script>
    <?php endif; ?>
    <script src="<?= App\Security::escape($baseUrl) ?>/assets/js/session-cleanup.js"></script>
    <script src="<?= App\Security::escape($baseUrl) ?>/assets/js/main.js" defer></script>
    <?php App\CustomCodes::renderBodyEnd($settings ?? null, $baseUrl); ?>
</body>
</html>
