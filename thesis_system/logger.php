<?php
/**
 * CEIT Thesis & Capstone System - Audit Logger Utility
 * Records administrative and user events for security, monitoring, and compliance.
 */

if (!function_exists('get_client_ip')) {
    function get_client_ip() {
        $ip_keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return substr($ip, 0, 45);
                }
            }
        }
        return '127.0.0.1';
    }
}

if (!function_exists('log_audit')) {
    /**
     * Log an event into the audit_logs table.
     *
     * @param string $action      Short action identifier (e.g., 'LOGIN', 'USER_CREATE', 'APPROVE_FACULTY')
     * @param string $description Detailed human-readable description
     * @param string $category    Category grouping ('Authentication', 'User Management', 'Faculty Approvals', 'Capstone & Panel', 'System')
     * @param string $status      'success', 'warning', or 'failed'
     * @param mixed  $details     Optional structured details (array, string, or json)
     * @param int|null $custom_uid Override user id if unauthenticated or acting on behalf
     * @param string|null $custom_name Override user name
     * @param string|null $custom_role Override user role
     * @param mysqli|null $db_conn Optional database connection (defaults to global $conn)
     * @return bool
     */
    function log_audit(
        $action,
        $description,
        $category = 'System',
        $status = 'success',
        $details = null,
        $custom_uid = null,
        $custom_name = null,
        $custom_role = null,
        $db_conn = null
    ) {
        global $conn;
        $active_conn = $db_conn ?: $conn;

        if (!$active_conn || !($active_conn instanceof mysqli)) {
            return false;
        }

        try {
            // Determine user attributes
            $user_id = $custom_uid;
            $user_name = $custom_name;
            $user_role = $custom_role;

            if ($user_id === null && isset($_SESSION['user_id'])) {
                $user_id = (int)$_SESSION['user_id'];
            }
            if ($user_name === null && isset($_SESSION['name'])) {
                $user_name = $_SESSION['name'];
            }
            if ($user_role === null && isset($_SESSION['role'])) {
                $user_role = $_SESSION['role'];
            }

            if (empty($user_name)) {
                $user_name = 'Unknown';
            }
            if (empty($user_role)) {
                $user_role = '';
            }

            // Client IP & User Agent
            $ip_address = get_client_ip();
            $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : 'Unknown';

            // Format details
            $details_str = null;
            if ($details !== null) {
                if (is_array($details) || is_object($details)) {
                    $details_str = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                } else {
                    $details_str = (string)$details;
                }
            }

            // Normalize status
            if (!in_array($status, ['success', 'warning', 'failed'])) {
                $status = 'success';
            }

            $stmt = $active_conn->prepare(
                "INSERT INTO audit_logs (user_id, user_name, user_role, action, category, description, ip_address, user_agent, status, details) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            if (!$stmt) {
                return false;
            }

            $stmt->bind_param(
                "isssssssss",
                $user_id,
                $user_name,
                $user_role,
                $action,
                $category,
                $description,
                $ip_address,
                $user_agent,
                $status,
                $details_str
            );

            return $stmt->execute();
        } catch (\Throwable $e) {
            // Silently suppress logging failures so application flow is never interrupted
            error_log("Audit log failed: " . $e->getMessage());
            return false;
        }
    }
}
