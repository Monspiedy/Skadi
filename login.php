<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

requireGuest();

$form = $_SESSION['login_form'] ?? [];
$errors = $_SESSION['login_errors'] ?? [];
unset($_SESSION['login_form'], $_SESSION['login_errors']);

$form = is_array($form) ? $form : [];
$errors = is_array($errors) ? $errors : [];
$pageTitle = 'Sign in';
require SKADI_ROOT . '/includes/header.php';
?>
<section class="auth-panel" aria-labelledby="login-heading">
    <p class="eyebrow">Account access</p>
    <h1 id="login-heading">Sign in</h1>

    <form class="auth-form" method="post" action="<?= escape(baseUrl('actions/login.php')) ?>" novalidate>
        <?= csrfInput() ?>

        <?php if (($error = fieldError($errors, 'credentials')) !== null): ?>
            <div class="form-summary" role="alert"><?= escape($error) ?></div>
        <?php endif; ?>

        <div class="form-field">
            <label for="identifier">Login name or email</label>
            <input id="identifier" name="identifier" type="text" maxlength="254"
                autocomplete="username" required value="<?= escape($form['identifier'] ?? '') ?>">
        </div>

        <div class="form-field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" maxlength="72"
                autocomplete="current-password" required>
        </div>

        <button type="submit">Sign in</button>
    </form>
    <p>Need an account? <a href="<?= escape(baseUrl('register.php')) ?>">Register</a>.</p>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
