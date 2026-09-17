<?php

/**
 * Mirrors AdminController::requireAdmin() so both checks read the role/
 * is_admin claim from the same decoded token consistently.
 */
function requireAdmin($authUser) {
    if (empty($authUser['is_admin'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin access required.']);
        exit;
    }
}

function requireRole($authUser, array $roles) {
    $role = isset($authUser['role']) ? $authUser['role'] : (!empty($authUser['is_admin']) ? 'admin' : 'user');
    if (!in_array($role, $roles, true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to perform this action.']);
        exit;
    }
}
