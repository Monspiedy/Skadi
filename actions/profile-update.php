<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderErrorPage(405, 'Method not allowed', 'Profile settings must be submitted from the settings form.');
}
requireValidCsrfToken($_POST['csrf_token'] ?? null);

$input = normalizedProfileInput($_POST);
$errors = profileInputErrors($input);
$avatar = $_FILES['avatar'] ?? [];
$avatarError = isset($avatar['error']) ? (int) $avatar['error'] : UPLOAD_ERR_NO_FILE;
$validatedAvatar = null;
if ($avatarError !== UPLOAD_ERR_NO_FILE) {
    $validatedAvatar = validateUploadedImage($avatar, 'avatar');
    if (!$validatedAvatar['valid']) {
        $errors['avatar'] = $validatedAvatar['error'];
    }
}
if ($validatedAvatar !== null && $input['remove_avatar']) {
    $errors['avatar'] = 'Choose either a replacement avatar or removal, not both.';
}

$safeForm = array_intersect_key($input, array_flip([
    'display_name', 'email', 'biography', 'theme', 'profile_visibility',
    'friend_request_setting', 'show_online_status', 'remove_avatar',
]));
if ($errors !== []) {
    $_SESSION['profile_form'] = $safeForm;
    $_SESSION['profile_errors'] = $errors;
    redirectTo('profile-settings.php');
}

$storedPath = null;
try {
    if ($validatedAvatar !== null) {
        $storedPath = storeValidatedImage($validatedAvatar);
    }
    $oldAvatarPath = updateProfileSettings(db(), (int) currentUser()['id'], $input, $storedPath);
} catch (SocialException $exception) {
    if ($storedPath !== null) {
        deleteManagedAvatarUpload($storedPath);
    }
    $_SESSION['profile_form'] = $safeForm;
    $_SESSION['profile_errors'] = ['email' => $exception->getMessage()];
    redirectTo('profile-settings.php');
} catch (PDOException $exception) {
    if ($storedPath !== null) {
        deleteManagedAvatarUpload($storedPath);
    }
    if ($exception->getCode() === '23000') {
        $_SESSION['profile_form'] = $safeForm;
        $_SESSION['profile_errors'] = ['email' => 'This email cannot be used for the profile.'];
        redirectTo('profile-settings.php');
    }
    throw $exception;
} catch (Throwable $exception) {
    if ($storedPath !== null) {
        deleteManagedAvatarUpload($storedPath);
    }
    throw $exception;
}

if (($storedPath !== null || $input['remove_avatar']) && is_string($oldAvatarPath)
    && !deleteManagedAvatarUpload($oldAvatarPath)) {
    addFlashMessage('warning', 'Profile saved, but the replaced avatar file could not be removed.');
}
addFlashMessage('success', 'Profile settings saved.');
redirectTo('profile-settings.php');
