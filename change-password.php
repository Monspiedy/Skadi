<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

requireAuth(true);

$errors = $_SESSION['password_change_errors'] ?? [];
unset($_SESSION['password_change_errors']);
$errors = is_array($errors) ? $errors : [];
$mustChangePassword = !empty(currentUser()['must_change_password']);
$pageTitle = 'Change password';
require SKADI_ROOT . '/includes/header.php';
?>
<section class="auth-panel" aria-labelledby="password-heading">
    <p class="eyebrow">Account security</p>
    <h1 id="password-heading">Change password</h1>
    <?php if ($mustChangePassword): ?>
        <div class="form-summary" role="status">
            This account uses a temporary password. Replace it before using protected features.
        </div>
    <?php endif; ?>

    <form class="auth-form" method="post" action="<?= escape(baseUrl('actions/change-password.php')) ?>" novalidate>
        <?= csrfInput() ?>

        <div class="form-field">
            <label for="current_password">Current password</label>
            <input id="current_password" name="current_password" type="password" maxlength="72"
                autocomplete="current-password" required
                <?= fieldError($errors, 'current_password') ? 'aria-describedby="current-password-error"' : '' ?>>
            <?php if (($error = fieldError($errors, 'current_password')) !== null): ?>
                <span class="field-error" id="current-password-error"><?= escape($error) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="new_password">New password</label>
            <input id="new_password" name="new_password" type="password" maxlength="72"
                autocomplete="new-password" required
                aria-describedby="new-password-help<?= fieldError($errors, 'new_password') ? ' new-password-error' : '' ?>">
            <small id="new-password-help">12-72 bytes with uppercase, lowercase, number and symbol characters.</small>
            <?php if (($error = fieldError($errors, 'new_password')) !== null): ?>
                <span class="field-error" id="new-password-error"><?= escape($error) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="new_password_confirmation">Confirm new password</label>
            <input id="new_password_confirmation" name="new_password_confirmation" type="password"
                maxlength="72" autocomplete="new-password" required
                <?= fieldError($errors, 'new_password_confirmation') ? 'aria-describedby="new-confirmation-error"' : '' ?>>
            <?php if (($error = fieldError($errors, 'new_password_confirmation')) !== null): ?>
                <span class="field-error" id="new-confirmation-error"><?= escape($error) ?></span>
            <?php endif; ?>
        </div>

        <button type="submit">Save new password</button>
    </form>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
