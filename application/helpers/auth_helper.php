<?php

if (!function_exists('check_role')) {
    function check_role($required_roles) {
        $CI =& get_instance();
        $role = strtolower($CI->session->userdata('role'));

        // Ensure $required_roles is always an array
        if (!is_array($required_roles)) {
            $required_roles = [$required_roles];
        }

        // Convert all roles to lowercase for comparison
        $required_roles = array_map('strtolower', $required_roles);

        // Always allow admin
        if ($role === 'admin') {
            return true;
        }

        // Check if current role is in the allowed list
        if (!in_array($role, $required_roles)) {
            show_error("Access denied: " . implode(' or ', $required_roles) . " only.", 403);
        }

        return true;
    }
}

if (!function_exists('check_not_role')) {
    function check_not_role($role) {
        $CI =& get_instance();
        $userRole = $CI->session->userdata('role');
        if (strtolower($userRole) === strtolower($role)) {
            show_error("Access denied for role: $role", 403, 'Forbidden');
        }
    }
}

if (!function_exists('require_department_and_authority')) {
    function require_department_and_authority() {
        $CI =& get_instance();

        $departmentId = $CI->session->userdata('department_id');
        $userRole = $CI->session->userdata('role');
        $userEmpId = $CI->session->userdata('emp_id');

        // Redirect if no department selected
        if (!$departmentId) {
            $CI->session->set_flashdata('error', 'Please select a department first.');
            redirect('Department_attendance');
            exit;
        }

        // If user is secretary, check if they have authority over this department
        if ($userRole === 'secretary') {
            $CI->load->database();

            $query = $CI->db->get_where('secretary_departments', [
                'secretary_id' => $userEmpId,
                'department_id' => $departmentId
            ]);

            if ($query->num_rows() === 0) {
                $CI->session->set_flashdata('error', 'You do not have authority over this department.');
                redirect('Department_attendance');
                exit;
            }
        }

        // For other roles you can add extra checks if needed
    }
}

if (!function_exists('role_exists')) {
    function role_exists() {
        $CI =& get_instance();
        $userRole = $CI->session->userdata('role');
        if (empty($userRole)) {
            redirect('Routing');
        }
    }
}

if (!function_exists('organ_chosen')) {
    function organ_chosen() {
        $CI =& get_instance();
        $selected_organ = $CI->session->userdata('organ');
        if (empty($selected_organ)) {
            redirect('Organ');
        }
    }
}
