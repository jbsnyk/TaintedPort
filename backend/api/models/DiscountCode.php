<?php

require_once __DIR__ . '/../config/database.php';

class DiscountCode {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function findByCode($code) {
        $stmt = $this->db->prepare('SELECT * FROM discount_codes WHERE code = :code');
        $stmt->bindValue(':code', $code, SQLITE3_TEXT);
        $result = $stmt->execute();
        return $result->fetchArray(SQLITE3_ASSOC);
    }

    public function getAll() {
        $result = $this->db->query('SELECT * FROM discount_codes ORDER BY created_at DESC');
        $codes = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $codes[] = $row;
        }
        return $codes;
    }

    public function create($data) {
        $stmt = $this->db->prepare(
            'INSERT INTO discount_codes (code, type, value, min_order_value, max_uses, expires_at)
             VALUES (:code, :type, :value, :min_order_value, :max_uses, :expires_at)'
        );
        $stmt->bindValue(':code', strtoupper(trim($data['code'])), SQLITE3_TEXT);
        $stmt->bindValue(':type', $data['type'], SQLITE3_TEXT);
        $stmt->bindValue(':value', floatval($data['value']), SQLITE3_FLOAT);
        $stmt->bindValue(':min_order_value', isset($data['min_order_value']) ? floatval($data['min_order_value']) : 0, SQLITE3_FLOAT);
        $stmt->bindValue(':max_uses', !empty($data['max_uses']) ? intval($data['max_uses']) : null, !empty($data['max_uses']) ? SQLITE3_INTEGER : SQLITE3_NULL);
        $stmt->bindValue(':expires_at', !empty($data['expires_at']) ? $data['expires_at'] : null, !empty($data['expires_at']) ? SQLITE3_TEXT : SQLITE3_NULL);
        $stmt->execute();
        return $this->db->lastInsertRowID();
    }

    public function update($id, $data) {
        $stmt = $this->db->prepare(
            'UPDATE discount_codes SET type = :type, value = :value, min_order_value = :min_order_value,
             max_uses = :max_uses, expires_at = :expires_at, active = :active WHERE id = :id'
        );
        $stmt->bindValue(':type', $data['type'], SQLITE3_TEXT);
        $stmt->bindValue(':value', floatval($data['value']), SQLITE3_FLOAT);
        $stmt->bindValue(':min_order_value', isset($data['min_order_value']) ? floatval($data['min_order_value']) : 0, SQLITE3_FLOAT);
        $stmt->bindValue(':max_uses', !empty($data['max_uses']) ? intval($data['max_uses']) : null, !empty($data['max_uses']) ? SQLITE3_INTEGER : SQLITE3_NULL);
        $stmt->bindValue(':expires_at', !empty($data['expires_at']) ? $data['expires_at'] : null, !empty($data['expires_at']) ? SQLITE3_TEXT : SQLITE3_NULL);
        $stmt->bindValue(':active', !empty($data['active']) ? 1 : 0, SQLITE3_INTEGER);
        $stmt->bindValue(':id', intval($id), SQLITE3_INTEGER);
        $stmt->execute();
        return $this->db->changes() > 0;
    }

    public function delete($id) {
        $stmt = $this->db->prepare('DELETE FROM discount_codes WHERE id = :id');
        $stmt->bindValue(':id', intval($id), SQLITE3_INTEGER);
        $stmt->execute();
        return $this->db->changes() > 0;
    }

    public function incrementUsedCount($id) {
        $stmt = $this->db->prepare('UPDATE discount_codes SET used_count = used_count + 1 WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();
        return true;
    }

    /**
     * Validates a code against expiry/max-uses/min-order-value and returns
     * the discount amount computed server-side. Used by the legitimate
     * checkout flow's "apply code" step.
     */
    public function validate($code, $subtotal) {
        $discount = $this->findByCode(strtoupper(trim($code)));

        if (!$discount || empty($discount['active'])) {
            return ['valid' => false, 'message' => 'Invalid discount code.'];
        }

        if ($discount['expires_at'] && strtotime($discount['expires_at']) < time()) {
            return ['valid' => false, 'message' => 'This discount code has expired.'];
        }

        if ($discount['max_uses'] !== null && $discount['used_count'] >= $discount['max_uses']) {
            return ['valid' => false, 'message' => 'This discount code has reached its usage limit.'];
        }

        if ($subtotal < $discount['min_order_value']) {
            return [
                'valid' => false,
                'message' => 'This code requires a minimum order of €' . number_format($discount['min_order_value'], 2) . '.',
            ];
        }

        $amount = $discount['type'] === 'percent'
            ? round($subtotal * ($discount['value'] / 100), 2)
            : min($discount['value'], $subtotal);

        return [
            'valid' => true,
            'id' => $discount['id'],
            'code' => $discount['code'],
            'type' => $discount['type'],
            'value' => floatval($discount['value']),
            'discount_amount' => $amount,
            'discount_percent' => $discount['type'] === 'percent' ? floatval($discount['value']) : round(($amount / max($subtotal, 0.01)) * 100, 2),
        ];
    }
}
