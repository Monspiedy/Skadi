<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

requireGuest();

$form = $_SESSION['registration_form'] ?? [];
$errors = $_SESSION['registration_errors'] ?? [];
unset($_SESSION['registration_form'], $_SESSION['registration_errors']);

$form = is_array($form) ? $form : [];
$errors = is_array($errors) ? $errors : [];
$pageTitle = 'Create account';
require SKADI_ROOT . '/includes/header.php';
?>
<section class="auth-panel" aria-labelledby="register-heading">
    <p class="eyebrow">Join Skadi</p>
    <h1 id="register-heading">Create account</h1>
    <p>Your login name and Skadi ID are permanent. Your display name can be changed later.</p>

    <form class="auth-form" method="post" action="<?= escape(baseUrl('actions/register.php')) ?>" novalidate>
        <?= csrfInput() ?>

        <div class="form-field">
            <label for="login_name">Login name</label>
            <input id="login_name" name="login_name" type="text" maxlength="50"
                autocomplete="username" required
                value="<?= escape($form['login_name'] ?? '') ?>"
                aria-describedby="login-name-help<?= fieldError($errors, 'login_name') ? ' login-name-error' : '' ?>">
            <small id="login-name-help">3-50 letters, numbers or underscores. This cannot be changed.</small>
            <?php if (($error = fieldError($errors, 'login_name')) !== null): ?>
                <span class="field-error" id="login-name-error"><?= escape($error) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" maxlength="254"
                autocomplete="email" required value="<?= escape($form['email'] ?? '') ?>"
                <?= fieldError($errors, 'email') ? 'aria-describedby="email-error"' : '' ?>>
            <?php if (($error = fieldError($errors, 'email')) !== null): ?>
                <span class="field-error" id="email-error"><?= escape($error) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="display_name">Display name</label>
            <input id="display_name" name="display_name" type="text" maxlength="80"
                autocomplete="nickname" required value="<?= escape($form['display_name'] ?? '') ?>"
                <?= fieldError($errors, 'display_name') ? 'aria-describedby="display-name-error"' : '' ?>>
            <?php if (($error = fieldError($errors, 'display_name')) !== null): ?>
                <span class="field-error" id="display-name-error"><?= escape($error) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" maxlength="72"
                autocomplete="new-password" required
                aria-describedby="password-help<?= fieldError($errors, 'password') ? ' password-error' : '' ?>">
            <small id="password-help">12-72 bytes with uppercase, lowercase, number and symbol characters.</small>
            <?php if (($error = fieldError($errors, 'password')) !== null): ?>
                <span class="field-error" id="password-error"><?= escape($error) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password"
                maxlength="72" autocomplete="new-password" required
                <?= fieldError($errors, 'password_confirmation') ? 'aria-describedby="confirmation-error"' : '' ?>>
            <?php if (($error = fieldError($errors, 'password_confirmation')) !== null): ?>
                <span class="field-error" id="confirmation-error"><?= escape($error) ?></span>
            <?php endif; ?>
        </div>

        <button type="submit">Create account with EUR 100.00</button>
    </form>
    <p>Already registered? <a href="<?= escape(baseUrl('login.php')) ?>">Sign in</a>.</p>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
