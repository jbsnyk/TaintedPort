<?php

require_once __DIR__ . '/../config/database.php';

class ReferralCode {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function findByCode($code) {
        $stmt = $this->db->prepare('SELECT * FROM referral_codes WHERE code = :code');
        $stmt->bindValue(':code', $code, SQLITE3_TEXT);
        $result = $stmt->execute();
        return $result->fetchArray(SQLITE3_ASSOC);
    }

    public function incrementUsedCount($id) {
        $stmt = $this->db->prepare('UPDATE referral_codes SET used_count = used_count + 1 WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();
        return true;
    }

    public function getAll() {
        $result = $this->db->query('SELECT * FROM referral_codes ORDER BY created_at DESC');
        $codes = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $codes[] = $row;
        }
        return $codes;
    }

    public function create($code, $creditAmount, $maxUses) {
        $stmt = $this->db->prepare(
            'INSERT INTO referral_codes (code, credit_amount, max_uses) VALUES (:code, :credit_amount, :max_uses)'
        );
        $stmt->bindValue(':code', strtoupper(trim($code)), SQLITE3_TEXT);
        $stmt->bindValue(':credit_amount', floatval($creditAmount), SQLITE3_FLOAT);
        $stmt->bindValue(':max_uses', intval($maxUses), SQLITE3_INTEGER);
        $stmt->execute();
        return $this->db->lastInsertRowID();
    }

    public function delete($id) {
        $stmt = $this->db->prepare('DELETE FROM referral_codes WHERE id = :id');
        $stmt->bindValue(':id', intval($id), SQLITE3_INTEGER);
        $stmt->execute();
        return $this->db->changes() > 0;
    }
}
