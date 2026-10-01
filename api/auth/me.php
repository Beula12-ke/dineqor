<?php
require_once __DIR__ . '/../../includes/auth.php';
$u = current_user();
if (!$u) json_out(['ok' => true, 'user' => null, 'csrf' => csrf_token()]);
$defaultDeliveryAddress = null;
if ($u['user_type'] === 'customer') {
    $address = db()->prepare('SELECT default_delivery_address FROM customers WHERE user_id=?');
    $address->execute([(int)$u['id']]);
    $defaultDeliveryAddress = $address->fetchColumn() ?: null;
}
json_out(['ok' => true, 'csrf' => csrf_token(), 'user' => [
    'id' => (int)$u['id'], 'name' => $u['full_name'], 'email' => $u['email'], 'phone' => $u['phone'],
    'default_delivery_address' => $defaultDeliveryAddress,
    'user_type' => $u['user_type'], 'staff_role' => $u['staff_role'],
    'restaurant_id' => $u['restaurant_id'], 'permissions' => user_permissions($u),
    'password_change_required' => !empty($u['password_change_required']),
    'dashboard' => dashboard_for($u),
]]);
