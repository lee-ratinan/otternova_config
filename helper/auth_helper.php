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
