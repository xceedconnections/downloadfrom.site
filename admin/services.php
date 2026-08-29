<?php

declare(strict_types=1);

require __DIR__ . '/init.php';
$auth->requireAuth();

use App\ServiceConfig;
use App\Security;

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCsrfToken($_POST[$config['security']['csrf_token_name']] ?? null, $config)) {
        $error = 'Invalid CSRF token.';
    } else {
        $data = $settings->all();
        $savedServices = ServiceConfig::defaultServices();

        foreach (array_keys($savedServices) as $serviceId) {
            $savedServices[$serviceId]['enabled'] = isset($_POST['service_enabled'][$serviceId]);
            $savedServices[$serviceId]['name'] = trim((string) ($_POST['service_name'][$serviceId] ?? $savedServices[$serviceId]['name']));
            $savedServices[$serviceId]['nav_label'] = $savedServices[$serviceId]['name'];
            $assigned = array_values(array_filter((array) ($_POST['service_providers'][$serviceId] ?? [])));
            $savedServices[$serviceId]['providers'] = $assigned;
        }

        $data['services'] = $savedServices;
        if ($settings->save($data)) {
            $message = 'Services saved successfully.';
            $services = ServiceConfig::getServices($settings);
        } else {
            $error = 'Failed to save services.';
        }
    }
}

$pageTitle = 'Services';
require __DIR__ . '/layout/header.php';
?>

<h1>Services</h1>
<p class="admin-note">Organize your site into services. Each service appears as a dropdown in the public header with its assigned providers.</p>

<?php if ($message): ?><p class="admin-success"><?= Security::escape($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="admin-error"><?= Security::escape($error) ?></p><?php endif; ?>

<form method="POST" class="admin-form admin-form-wide">
    <?= Security::csrfField($config) ?>

    <?php foreach (ServiceConfig::defaultServices() as $serviceId => $defaults):
        $svc = $services[$serviceId] ?? $defaults;
        $providerMap = $serviceId === ServiceConfig::SERVICE_AUDIO ? $allAudioPlatforms : $allVideoPlatforms;
        $assigned = $svc['providers'] ?? [];
    ?>
    <fieldset class="admin-fieldset">
        <legend><?= Security::escape($defaults['name']) ?></legend>
        <label class="checkbox">
            <input type="checkbox" name="service_enabled[<?= Security::escape($serviceId) ?>]" <?= !empty($svc['enabled']) ? 'checked' : '' ?>>
            Enable this service on the website
        </label>
        <label>Service name
            <input type="text" name="service_name[<?= Security::escape($serviceId) ?>]" value="<?= Security::escape($svc['name'] ?? $defaults['name']) ?>">
        </label>
        <p class="admin-note">This name is used in the header menu and on the service page.</p>
        <p class="admin-note">Assign providers to this service. Leave all unchecked to include <strong>all enabled</strong> <?= $serviceId === ServiceConfig::SERVICE_AUDIO ? 'audio' : 'video' ?> providers.</p>
        <div class="service-provider-grid">
            <?php foreach ($providerMap as $pid => $platform): ?>
            <label class="checkbox">
                <input type="checkbox" name="service_providers[<?= Security::escape($serviceId) ?>][]" value="<?= Security::escape($pid) ?>" <?= $assigned === [] || in_array($pid, $assigned, true) ? 'checked' : '' ?>>
                <?= Security::escape($platform['name']) ?>
            </label>
            <?php endforeach; ?>
        </div>
        <p class="admin-note hint">
            Configure individual providers in
            <?php if ($serviceId === ServiceConfig::SERVICE_AUDIO): ?>
            <a href="audio-providers.php">Audio Provider Settings</a>.
            <?php else: ?>
            <a href="providers.php">Video Provider Settings</a>.
            <?php endif; ?>
        </p>
    </fieldset>
    <?php endforeach; ?>

    <button type="submit" class="btn btn-primary">Save Services</button>
</form>

<?php require __DIR__ . '/layout/footer.php'; ?>
