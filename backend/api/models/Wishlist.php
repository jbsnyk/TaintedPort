<?php

require_once __DIR__ . '/../config/database.php';

class Wishlist {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getByUser($userId) {
        $stmt = $this->db->prepare(
            'SELECT w.id as wishlist_id, w.wine_id, w.created_at, wi.name, wi.region, wi.type, wi.vintage,
                    wi.price, wi.image_url, wi.description_short, wi.stock_quantity, wi.low_stock_threshold
             FROM wishlists w
             JOIN wines wi ON w.wine_id = wi.id
             WHERE w.user_id = :user_id
             ORDER BY w.created_at DESC'
        );
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        $items = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $row['price'] = floatval($row['price']);
            $items[] = $row;
        }
        return $items;
    }

    public function getWineIdsForUser($userId) {
        $stmt = $this->db->prepare('SELECT wine_id FROM wishlists WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        $ids = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $ids[] = intval($row['wine_id']);
        }
        return $ids;
    }

    public function add($userId, $wineId) {
        $stmt = $this->db->prepare(
            'INSERT INTO wishlists (user_id, wine_id) VALUES (:user_id, :wine_id)
             ON CONFLICT(user_id, wine_id) DO NOTHING'
        );
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':wine_id', $wineId, SQLITE3_INTEGER);
        $stmt->execute();
        return true;
    }

    public function remove($userId, $wineId) {
        $stmt = $this->db->prepare('DELETE FROM wishlists WHERE user_id = :user_id AND wine_id = :wine_id');
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':wine_id', $wineId, SQLITE3_INTEGER);
        $stmt->execute();
        return true;
    }
}
