<?php

declare(strict_types=1);

require __DIR__ . '/init.php';
$auth->requireAuth();

use App\Security;
use App\Storage\StorageKeys;

$message = '';
$faqData = $db->read(StorageKeys::FAQ, ['home' => []]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCsrfToken($_POST[$config['security']['csrf_token_name']] ?? null, $config)) {
        $message = 'Invalid CSRF token.';
    } else {
        $questions = $_POST['q'] ?? [];
        $answers = $_POST['a'] ?? [];
        $homeFaq = [];
        foreach ($questions as $i => $q) {
            $q = trim((string) $q);
            $a = Security::sanitizeAdminHtml(trim((string) ($answers[$i] ?? '')));
            if ($q !== '' && $a !== '') {
                $homeFaq[] = ['q' => $q, 'a' => $a];
            }
        }
        $faqData['home'] = $homeFaq;
        $db->write(StorageKeys::FAQ, $faqData);
        $message = 'FAQ saved successfully.';
    }
}

$homeFaq = $faqData['home'] ?? [];
$pageTitle = 'FAQ Management';
require __DIR__ . '/layout/header.php';
?>

<h1>FAQ Management</h1>
<p class="admin-note">Manage homepage FAQ items. Drag the <strong>⋮⋮</strong> handle to reorder. Answers support rich text formatting.</p>
<?php if ($message): ?><p class="admin-success"><?= Security::escape($message) ?></p><?php endif; ?>

<form method="POST" data-wysiwyg-form>
    <?= Security::csrfField($config) ?>
    <div id="faq-items">
        <?php foreach ($homeFaq as $i => $item): ?>
        <div class="faq-edit-row" data-faq-row>
            <div class="faq-edit-row-head">
                <span class="faq-drag-handle" draggable="true" title="Drag to reorder">⋮⋮</span>
                <strong>FAQ #<span data-faq-num><?= $i + 1 ?></span></strong>
                <button type="button" class="btn-link danger faq-remove">Delete</button>
            </div>
            <label>Question
                <input type="text" name="q[]" value="<?= Security::escape($item['q']) ?>" placeholder="Question">
            </label>
            <label>Answer
                <textarea name="a[]" class="wysiwyg" id="faq-a-<?= $i ?>" placeholder="Answer"><?= Security::escape($item['a']) ?></textarea>
            </label>
        </div>
        <?php endforeach; ?>
        <?php if ($homeFaq === []): ?>
        <div class="faq-edit-row" data-faq-row>
            <div class="faq-edit-row-head">
                <span class="faq-drag-handle" draggable="true" title="Drag to reorder">⋮⋮</span>
                <strong>FAQ #<span data-faq-num>1</span></strong>
                <button type="button" class="btn-link danger faq-remove">Delete</button>
            </div>
            <label>Question
                <input type="text" name="q[]" placeholder="Question">
            </label>
            <label>Answer
                <textarea name="a[]" class="wysiwyg" id="faq-a-0" placeholder="Answer"></textarea>
            </label>
        </div>
        <?php endif; ?>
    </div>

    <div class="faq-edit-actions">
        <button type="button" class="btn btn-secondary" id="faq-add">+ Add FAQ</button>
        <button type="submit" class="btn btn-primary">Save FAQ</button>
    </div>
</form>

<template id="faq-row-template">
    <div class="faq-edit-row" data-faq-row>
        <div class="faq-edit-row-head">
            <span class="faq-drag-handle" draggable="true" title="Drag to reorder">⋮⋮</span>
            <strong>FAQ #<span data-faq-num>1</span></strong>
            <button type="button" class="btn-link danger faq-remove">Delete</button>
        </div>
        <label>Question
            <input type="text" name="q[]" placeholder="Question">
        </label>
        <label>Answer
            <textarea name="a[]" class="wysiwyg" placeholder="Answer"></textarea>
        </label>
    </div>
</template>

<?php require __DIR__ . '/partials/wysiwyg-foot.php'; ?>
<?php require __DIR__ . '/layout/footer.php'; ?>
