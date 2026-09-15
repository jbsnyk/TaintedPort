<?php

require_once __DIR__ . '/../config/database.php';

class Cart {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getItems($userId) {
        $stmt = $this->db->prepare(
            'SELECT c.id, c.wine_id, w.name as wine_name, w.image_url as wine_image,
                    COALESCE(c.custom_price, w.price) as price, c.quantity,
                    (COALESCE(c.custom_price, w.price) * c.quantity) as subtotal
             FROM cart_items c
             JOIN wines w ON c.wine_id = w.id
             WHERE c.user_id = :user_id'
        );
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        $items = [];
        $total = 0;
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $row['price'] = floatval($row['price']);
            $row['subtotal'] = floatval($row['subtotal']);
            $total += $row['subtotal'];
            $items[] = $row;
        }

        return ['items' => $items, 'total' => round($total, 2)];
    }

    public function addItem($userId, $wineId, $quantity, $customPrice = null) {
        // Check if wine exists
        $stmt = $this->db->prepare('SELECT id FROM wines WHERE id = :id');
        $stmt->bindValue(':id', $wineId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        if (!$result->fetchArray()) {
            return false;
        }

        // Client-supplied price is still trusted (the vuln), but it is scoped
        // to THIS user's cart line via cart_items.custom_price instead of
        // mutating the shared wines catalogue that every other shopper sees.
        $cpType = $customPrice !== null ? SQLITE3_FLOAT : SQLITE3_NULL;

        // Upsert: insert or bump quantity; a provided custom_price overrides,
        // a subsequent add without one keeps whatever was set.
        $stmt = $this->db->prepare(
            'INSERT INTO cart_items (user_id, wine_id, quantity, custom_price)
             VALUES (:user_id, :wine_id, :qty, :cp)
             ON CONFLICT(user_id, wine_id) DO UPDATE SET
                 quantity = quantity + :qty2,
                 custom_price = COALESCE(:cp2, custom_price)'
        );
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':wine_id', $wineId, SQLITE3_INTEGER);
        $stmt->bindValue(':qty', $quantity, SQLITE3_INTEGER);
        $stmt->bindValue(':qty2', $quantity, SQLITE3_INTEGER);
        $stmt->bindValue(':cp', $customPrice, $cpType);
        $stmt->bindValue(':cp2', $customPrice, $cpType);
        $stmt->execute();
        return true;
    }

    public function updateItem($userId, $wineId, $quantity) {
        if ($quantity <= 0) {
            return $this->removeItem($userId, $wineId);
        }

        $stmt = $this->db->prepare(
            'UPDATE cart_items SET quantity = :qty WHERE user_id = :user_id AND wine_id = :wine_id'
        );
        $stmt->bindValue(':qty', $quantity, SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':wine_id', $wineId, SQLITE3_INTEGER);
        $stmt->execute();
        return true;
    }

    public function removeItem($userId, $wineId) {
        $stmt = $this->db->prepare(
            'DELETE FROM cart_items WHERE user_id = :user_id AND wine_id = :wine_id'
        );
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':wine_id', $wineId, SQLITE3_INTEGER);
        $stmt->execute();
        return true;
    }

    public function clear($userId) {
        $stmt = $this->db->prepare('DELETE FROM cart_items WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $stmt->execute();
        return true;
    }
}
