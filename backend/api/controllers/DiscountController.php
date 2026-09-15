<?php

require_once __DIR__ . '/../models/DiscountCode.php';
require_once __DIR__ . '/../middleware/authorize.php';

class DiscountController {
    private $discount;

    public function __construct() {
        $this->discount = new DiscountCode();
    }

    /**
     * Used by the checkout page's "apply code" step. Looks the code up and
     * computes the real discount server-side - this is the legitimate path;
     * see OrderController::create for the checkout endpoint's own handling.
     */
    public function validate($authUser) {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['code'])) {
            http_response_code(400);
            return ['success' => false, 'message' => 'Discount code is required.'];
        }

        $subtotal = isset($data['subtotal']) ? floatval($data['subtotal']) : 0;
        $result = $this->discount->validate($data['code'], $subtotal);

        if (!$result['valid']) {
            http_response_code(400);
            return ['success' => false, 'message' => $result['message']];
        }

        return ['success' => true] + $result;
    }

    public function index($authUser) {
        requireRole($authUser, ['admin']);
        return ['success' => true, 'discount_codes' => $this->discount->getAll()];
    }

    public function create($authUser) {
        requireRole($authUser, ['admin']);
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['code']) || empty($data['type']) || !isset($data['value'])) {
            http_response_code(400);
            return ['success' => false, 'message' => 'code, type and value are required.'];
        }

        if (!in_array($data['type'], ['percent', 'fixed'], true)) {
            http_response_code(400);
            return ['success' => false, 'message' => "type must be 'percent' or 'fixed'."];
        }

        if ($this->discount->findByCode(strtoupper(trim($data['code'])))) {
            http_response_code(409);
            return ['success' => false, 'message' => 'A discount code with that name already exists.'];
        }

        $id = $this->discount->create($data);
        http_response_code(201);
        return ['success' => true, 'message' => 'Discount code created.', 'id' => $id];
    }

    public function update($authUser, $id) {
        requireRole($authUser, ['admin']);
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['type']) || !isset($data['value'])) {
            http_response_code(400);
            return ['success' => false, 'message' => 'type and value are required.'];
        }

        $this->discount->update($id, $data);
        return ['success' => true, 'message' => 'Discount code updated.'];
    }

    public function delete($authUser, $id) {
        requireRole($authUser, ['admin']);
        $this->discount->delete($id);
        return ['success' => true, 'message' => 'Discount code deleted.'];
    }
}
