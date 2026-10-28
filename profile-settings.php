<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

requireAuth();
$profile = loadOwnProfile(db(), (int) currentUser()['id']);
if ($profile === null) {
    renderErrorPage(404, 'Profile not found', 'Your profile could not be loaded.');
}
$form = $_SESSION['profile_form'] ?? $profile;
$errors = $_SESSION['profile_errors'] ?? [];
unset($_SESSION['profile_form'], $_SESSION['profile_errors']);
$form = is_array($form) ? $form : $profile;
$errors = is_array($errors) ? $errors : [];
$pageTitle = 'Profile settings';
require SKADI_ROOT . '/includes/header.php';
?>
<section class="profile-settings social-page" aria-labelledby="profile-settings-heading">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Account and privacy</p>
            <h1 id="profile-settings-heading">Profile settings</h1>
        </div>
        <a class="btn btn-outline-secondary" href="<?= escape(baseUrl('profile.php?id=' . $profile['skadi_id'])) ?>">View public profile</a>
    </div>

    <form class="social-form" method="post" enctype="multipart/form-data"
        action="<?= escape(baseUrl('actions/profile-update.php')) ?>" novalidate>
        <?= csrfInput() ?>
        <div class="social-settings-grid">
            <div class="form-field">
                <label for="display_name">Display name</label>
                <input id="display_name" name="display_name" type="text" maxlength="80" required
                    value="<?= escape($form['display_name'] ?? '') ?>">
                <?php if (($error = fieldError($errors, 'display_name')) !== null): ?>
                    <span class="field-error"><?= escape($error) ?></span>
                <?php endif; ?>
            </div>
            <div class="form-field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" maxlength="254" required
                    value="<?= escape($form['email'] ?? '') ?>">
                <small>Email remains private and is used only for sign-in/account administration.</small>
                <?php if (($error = fieldError($errors, 'email')) !== null): ?>
                    <span class="field-error"><?= escape($error) ?></span>
                <?php endif; ?>
            </div>
            <div class="form-field">
                <label>Skadi ID</label>
                <input type="text" value="<?= escape($profile['skadi_id']) ?>" readonly>
                <small>Your Skadi ID and private login name cannot be changed.</small>
            </div>
            <div class="form-field">
                <label for="theme">Theme</label>
                <select id="theme" name="theme">
                    <?php foreach (['skadi' => 'Skadi', 'dark' => 'Dark', 'light' => 'Light'] as $value => $label): ?>
                        <option value="<?= escape($value) ?>" <?= ($form['theme'] ?? '') === $value ? 'selected' : '' ?>><?= escape($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label for="profile_visibility">Profile details</label>
                <select id="profile_visibility" name="profile_visibility">
                    <option value="public" <?= ($form['profile_visibility'] ?? '') === 'public' ? 'selected' : '' ?>>Public</option>
                    <option value="friends_only" <?= ($form['profile_visibility'] ?? '') === 'friends_only' ? 'selected' : '' ?>>Friends only</option>
                    <option value="private" <?= ($form['profile_visibility'] ?? '') === 'private' ? 'selected' : '' ?>>Private</option>
                </select>
                <small>This single rule protects your library, reviews, activity and friend list.</small>
            </div>
            <div class="form-field">
                <label for="friend_request_setting">Incoming friend requests</label>
                <select id="friend_request_setting" name="friend_request_setting">
                    <option value="everyone" <?= ($form['friend_request_setting'] ?? '') === 'everyone' ? 'selected' : '' ?>>Everyone</option>
                    <option value="friends_of_friends" <?= ($form['friend_request_setting'] ?? '') === 'friends_of_friends' ? 'selected' : '' ?>>Friends of friends</option>
                    <option value="nobody" <?= ($form['friend_request_setting'] ?? '') === 'nobody' ? 'selected' : '' ?>>Nobody</option>
                </select>
            </div>
        </div>

        <div class="form-field">
            <label for="biography">Biography</label>
            <textarea id="biography" name="biography" rows="5" maxlength="1000"><?= escape($form['biography'] ?? '') ?></textarea>
            <?php if (($error = fieldError($errors, 'biography')) !== null): ?>
                <span class="field-error"><?= escape($error) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-field avatar-setting">
            <label for="avatar">Avatar</label>
            <?php if (is_string($profile['avatar_path']) && isManagedAvatarUploadPath($profile['avatar_path'])): ?>
                <img class="profile-avatar" src="<?= escape(baseUrl($profile['avatar_path'])) ?>" alt="Current avatar">
            <?php endif; ?>
            <input id="avatar" name="avatar" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
            <small>JPG, PNG or WebP; maximum 2 MB; 64×64 to 2048×2048 pixels.</small>
            <?php if (($error = fieldError($errors, 'avatar')) !== null): ?>
                <span class="field-error"><?= escape($error) ?></span>
            <?php endif; ?>
            <?php if (!empty($profile['avatar_path'])): ?>
                <label class="form-check"><input name="remove_avatar" type="checkbox" value="1"> Remove current avatar</label>
            <?php endif; ?>
        </div>

        <label class="form-check">
            <input name="show_online_status" type="checkbox" value="1" <?= !empty($form['show_online_status']) ? 'checked' : '' ?>>
            Show my online status
        </label>
        <button class="btn btn-primary" type="submit">Save profile settings</button>
    </form>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
