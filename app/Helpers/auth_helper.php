<?php

/**
 * Role helpers, migrated from the CodeIgniter 3 application/helpers/auth_helper.php.
 *
 * CI3 reached the framework through `get_instance()`; CI4 exposes the same
 * pieces through the `session()` and `db_connect()` service functions, and a
 * redirect has to be *returned* by the caller instead of ending the request
 * from inside the helper.
 */

use App\Exceptions\ForbiddenException;
use CodeIgniter\HTTP\RedirectResponse;

if (! function_exists('check_role')) {
    /**
     * Aborts with 403 unless the session holds one of the allowed roles.
     *
     * @param list<string>|string $required_roles
     */
    function check_role($required_roles): bool
    {
        $role = strtolower((string) session()->get('role'));

        // Ensure $required_roles is always an array
        if (! is_array($required_roles)) {
            $required_roles = [$required_roles];
        }

        // Convert all roles to lowercase for comparison
        $required_roles = array_map('strtolower', $required_roles);

        // Always allow admin
        if ($role === 'admin') {
            return true;
        }

        // Check if current role is in the allowed list
        if (! in_array($role, $required_roles, true)) {
            throw new ForbiddenException('Access denied: ' . implode(' or ', $required_roles) . ' only.');
        }

        return true;
    }
}

if (! function_exists('check_not_role')) {
    /**
     * Aborts with 403 when the session holds exactly the forbidden role.
     */
    function check_not_role(string $role): void
    {
        $userRole = (string) session()->get('role');

        if (strtolower($userRole) === strtolower($role)) {
            throw new ForbiddenException("Access denied for role: {$role}");
        }
    }
}

if (! function_exists('require_department_and_authority')) {
    /**
     * Returns a redirect when the visitor has no department, or is a secretary
     * without authority over the selected one. Returns null when access is fine,
     * so callers must `return` whatever comes back.
     */
    function require_department_and_authority(): ?RedirectResponse
    {
        $session = session();

        $departmentId = $session->get('department_id');
        $userRole     = $session->get('role');
        $userEmpId    = $session->get('emp_id');

        // Redirect if no department selected
        if (! $departmentId) {
            $session->setFlashdata('error', 'Please select a department first.');

            return redirect()->to(site_url('Department_attendance'));
        }

        // If user is secretary, check if they have authority over this department
        if ($userRole === 'secretary') {
            $rows = db_connect()->table('secretary_departments')
                ->where('secretary_id', $userEmpId)
                ->where('department_id', $departmentId)
                ->countAllResults();

            if ($rows === 0) {
                $session->setFlashdata('error', 'You do not have authority over this department.');

                return redirect()->to(site_url('Department_attendance'));
            }
        }

        // For other roles you can add extra checks if needed
        return null;
    }
}

if (! function_exists('role_exists')) {
    /**
     * Returns a redirect to the routing page when no role is set, else null.
     */
    function role_exists(): ?RedirectResponse
    {
        if (empty(session()->get('role'))) {
            return redirect()->to(site_url('Routing'));
        }

        return null;
    }
}

if (! function_exists('organ_chosen')) {
    /**
     * Returns a redirect to the organ picker when none has been chosen.
     *
     * Routes normally rely on the `organ` filter (App\Filters\OrganFilter)
     * instead; this is kept so the old call sites keep working.
     */
    function organ_chosen(): ?RedirectResponse
    {
        if (empty(session()->get('organ'))) {
            return redirect()->to(site_url('Organ'));
        }

        return null;
    }
}
