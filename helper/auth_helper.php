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
            return base_url('assets/img/profile-img.svg');
        }
        if (preg_match('#^https?://#i', $avatarPath)) {
            return $avatarPath;
        }
        return base_url(ltrim($avatarPath, '/'));
    }
}

if (!function_exists('userDepartmentCode')) {
    /**
     * otternova_department.department_code of the signed-in staff member, from the session user.
     * A session from before the API sent department_code is filled in once from LINK_INTERNAL_API v1/staff/me.
     * @return string|null null when signed out, or when it cannot be found
     */
    function userDepartmentCode(): ?string
    {
        $user = session('user');
        if (! is_array($user)) {
            return null;
        }
        if (! array_key_exists('department_code', $user)) {
            try {
                $response = service('curlrequest')->get(getenv('LINK_INTERNAL_API') . 'v1/staff/me', [
                    'headers'     => ['Accept' => 'application/json', 'Authorization' => 'Bearer ' . session('access_token')],
                    'timeout'     => 15,
                    'http_errors' => false,
                ]);
                $body = json_decode($response->getBody(), true);
            } catch (Throwable $e) {
                log_message('error', 'LINK_INTERNAL_API v1/staff/me failed: ' . $e->getMessage());
                return null;
            }
            if ($response->getStatusCode() !== 200 || ! is_array($body['data']['user'] ?? null)) {
                return null;
            }
            $user['department_code'] = $body['data']['user']['department_code'] ?? null;
            session()->set('user', $user);
        }
        $code = $user['department_code'];
        return is_string($code) && $code !== '' ? $code : null;
    }
}

if (!function_exists('inDepartment')) {
    /**
     * Whether the signed-in staff member belongs to one of the departments
     * @param list<string> $codes department_code values, e.g. LEDGER_DEPARTMENTS
     * @return bool
     */
    function inDepartment(array $codes): bool
    {
        return in_array(userDepartmentCode(), $codes, true);
    }
}
