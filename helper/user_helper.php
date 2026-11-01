<?php
if (!function_exists('userAvatarUrl')) {
    /**
     * URL of a user's avatar, or the default avatar when none is set.
     * Uploaded avatars are served relative to base_url() for now; change it here once their location is decided.
     * @param string|null $avatarPath auth_master.avatar_path
     * @return string
     */
    function userAvatarUrl(?string $avatarPath): string
    {
        if ($avatarPath === null || trim($avatarPath) === '') {
            return base_url('assets/img/profile-img.webp');
        }
        return base_url(ltrim($avatarPath, '/'));
    }
}
if (!function_exists('userDisplayName')) {
    /**
     * The name to show for a signed-in user: display_name, falling back to full_name.
     * @param array $user the 'user' session entry
     * @return string
     */
    function userDisplayName(array $user): string
    {
        $name = trim((string) ($user['display_name'] ?? ''));
        return $name !== '' ? $name : trim((string) ($user['full_name'] ?? ''));
    }
}
if (!function_exists('localTimeTag')) {
    /**
     * A <time> tag for a UTC datetime from the API; assets/js/local-time.js shows it in the viewer's timezone.
     * Without JavaScript the UTC value is shown as it is.
     * @param string|null $utc e.g. '2026-11-01 10:30:00' (UTC)
     * @param string $style 'datetime' or 'date'
     * @return string
     */
    function localTimeTag(?string $utc, string $style = 'datetime'): string
    {
        if ($utc === null || trim($utc) === '') {
            return '';
        }
        $timestamp = strtotime($utc . ' UTC');
        if ($timestamp === false) {
            return esc($utc);
        }
        return '<time datetime="' . gmdate('Y-m-d\TH:i:s\Z', $timestamp) . '" data-local-time="' . esc($style, 'attr') . '">'
            . esc($utc) . ' UTC</time>';
    }
}
