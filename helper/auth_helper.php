<?php
if (!function_exists('hasActiveSession')) {
    /**
     * Whether the user is signed in with a JWT that has not expired yet.
     * An expired session is destroyed.
     * @return bool
     */
    function hasActiveSession(): bool
    {
        $session = session();
        if (empty($session->get('access_token'))) {
            return false;
        }
        if ((int) $session->get('expires_at') <= time()) {
            $session->destroy();
            return false;
        }
        return true;
    }
}

if (!function_exists('avatarUrl')) {
    /**
     * URL of an avatar from auth_master.avatar_path; the default avatar when none is set.
     * The single place that decides where uploaded avatars are served from.
     * @param string|null $avatarPath
     * @return string
     */
    function avatarUrl(?string $avatarPath): string
    {
        $avatarPath = trim((string) $avatarPath);
        if ('' === $avatarPath) {
            return base_url('assets/img/profile-img.webp');
        }
        if (preg_match('#^https?://#i', $avatarPath)) {
            return $avatarPath;
        }
        return base_url(ltrim($avatarPath, '/'));
    }
}
