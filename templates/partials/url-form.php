<form class="url-form" action="<?= App\Security::escape($baseUrl) ?>/process" method="POST" id="url-form">
    <?= App\Security::csrfField($config) ?>
    <?php
        $showServiceSelect = $showServiceSelect ?? false;
        $selectedService = $selectedService ?? ($currentService ?? App\ServiceConfig::SERVICE_ALL);
        $videoEnabled = App\ServiceConfig::isServiceEnabled($settings, App\ServiceConfig::SERVICE_VIDEO);
        $audioEnabled = App\ServiceConfig::isServiceEnabled($settings, App\ServiceConfig::SERVICE_AUDIO);
        $hasServicePicker = $showServiceSelect && ($videoEnabled || $audioEnabled);
        $isAudioOnly = ($selectedService ?? '') === App\ServiceConfig::SERVICE_AUDIO
            || (($currentService ?? '') === App\ServiceConfig::SERVICE_AUDIO && !$hasServicePicker);
    ?>
    <?php if (!$hasServicePicker): ?>
    <input type="hidden" name="service" value="<?= App\Security::escape($currentService ?? App\ServiceConfig::SERVICE_VIDEO) ?>">
    <?php endif; ?>

    <label for="video-url" class="sr-only"><?= $isAudioOnly ? 'Audio URL' : 'Paste URL' ?></label>

    <div class="url-form-bar<?= $hasServicePicker ? '' : ' url-form-bar-simple' ?>">
        <div class="url-form-input-wrap">
            <input
                type="url"
                id="video-url"
                name="url"
                class="url-input"
                placeholder="<?= $isAudioOnly ? 'Paste your audio or music URL here...' : 'Paste your video or audio URL here...' ?>"
                value="<?= $prefillUrl ?? '' ?>"
                required
                autocomplete="off"
                inputmode="url"
            >
            <button type="button" class="url-paste-btn" id="paste-btn">Paste</button>
        </div>

        <?php if ($hasServicePicker): ?>
        <div class="url-form-service">
            <label for="service-select" class="sr-only">Download type</label>
            <select id="service-select" name="service" class="service-select">
                <?php if ($videoEnabled && $audioEnabled): ?>
                <option value="<?= App\Security::escape(App\ServiceConfig::SERVICE_ALL) ?>" <?= $selectedService === App\ServiceConfig::SERVICE_ALL ? 'selected' : '' ?>>
                    All
                </option>
                <?php endif; ?>
                <?php if ($videoEnabled): ?>
                <option value="<?= App\Security::escape(App\ServiceConfig::SERVICE_VIDEO) ?>" <?= $selectedService === App\ServiceConfig::SERVICE_VIDEO ? 'selected' : '' ?>>
                    Video
                </option>
                <?php endif; ?>
                <?php if ($audioEnabled): ?>
                <option value="<?= App\Security::escape(App\ServiceConfig::SERVICE_AUDIO) ?>" <?= $selectedService === App\ServiceConfig::SERVICE_AUDIO ? 'selected' : '' ?>>
                    Audio
                </option>
                <?php endif; ?>
            </select>
            <span class="service-select-caret" aria-hidden="true">▾</span>
        </div>
        <?php endif; ?>
    </div>

    <button type="submit" class="btn btn-primary btn-lg url-form-submit" id="url-form-submit">
        <span class="url-form-submit-label">Generate Links</span>
        <span class="url-form-submit-loading">
            <span class="url-form-spinner" aria-hidden="true"></span>
            Generating…
        </span>
    </button>
</form>
