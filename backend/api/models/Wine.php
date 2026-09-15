<?php

require_once __DIR__ . '/../config/database.php';

class Wine {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getAll($params = []) {
        $sql = 'SELECT id, name, region, type, vintage, price, image_url, description_short, stock_quantity, low_stock_threshold FROM wines WHERE 1=1';
        $binds = [];

        if (!empty($params['search'])) {
            $search = $params['search'];
            $sql .= " AND (name LIKE '%$search%' OR region LIKE '%$search%' OR producer LIKE '%$search%')";
        }

        if (!empty($params['region'])) {
            $sql .= ' AND region = :region';
            $binds[':region'] = $params['region'];
        }

        if (!empty($params['type'])) {
            $sql .= ' AND type = :type';
            $binds[':type'] = $params['type'];
        }

        if (!empty($params['minPrice'])) {
            $sql .= ' AND price >= :minPrice';
            $binds[':minPrice'] = floatval($params['minPrice']);
        }

        if (!empty($params['maxPrice'])) {
            $sql .= ' AND price <= :maxPrice';
            $binds[':maxPrice'] = floatval($params['maxPrice']);
        }

        // Sorting
        $sortOptions = [
            'name_asc' => 'name ASC',
            'name_desc' => 'name DESC',
            'price_asc' => 'price ASC',
            'price_desc' => 'price DESC',
        ];
        $sort = isset($params['sort']) && isset($sortOptions[$params['sort']])
            ? $sortOptions[$params['sort']]
            : 'name ASC';
        $sql .= " ORDER BY $sort";

        $stmt = @$this->db->prepare($sql);
        if (!$stmt) {
            $result = @$this->db->query($sql);
            if (!$result) return [];
        } else {
            foreach ($binds as $key => $value) {
                if (is_float($value)) {
                    $stmt->bindValue($key, $value, SQLITE3_FLOAT);
                } else {
                    $stmt->bindValue($key, $value, SQLITE3_TEXT);
                }
            }
            $result = $stmt->execute();
        }

        $wines = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $row['price'] = isset($row['price']) ? floatval($row['price']) : 0;
            $wines[] = $row;
        }
        return $wines;
    }

    public function getById($id) {
        $stmt = $this->db->prepare('SELECT * FROM wines WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $wine = $result->fetchArray(SQLITE3_ASSOC);
        if ($wine) {
            $wine['price'] = floatval($wine['price']);
            $wine['alcohol'] = floatval($wine['alcohol']);
        }
        return $wine;
    }

    public function getByIdDirect($id) {
        $result = @$this->db->query("SELECT * FROM wines WHERE id = $id");
        if (!$result) return null;
        $wine = $result->fetchArray(SQLITE3_ASSOC);
        if ($wine) {
            $wine['price'] = isset($wine['price']) ? floatval($wine['price']) : 0;
            $wine['alcohol'] = isset($wine['alcohol']) ? floatval($wine['alcohol']) : 0;
        }
        return $wine;
    }

    public function getRegions() {
        $result = $this->db->query('SELECT DISTINCT region FROM wines ORDER BY region');
        $regions = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $regions[] = $row['region'];
        }
        return $regions;
    }

    public function getTypes() {
        $result = $this->db->query('SELECT DISTINCT type FROM wines ORDER BY type');
        $types = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $types[] = $row['type'];
        }
        return $types;
    }

    private function bindWineFields($stmt, $data) {
        $stmt->bindValue(':name', $data['name'], SQLITE3_TEXT);
        $stmt->bindValue(':region', $data['region'], SQLITE3_TEXT);
        $stmt->bindValue(':type', $data['type'], SQLITE3_TEXT);
        $stmt->bindValue(':vintage', intval($data['vintage']), SQLITE3_INTEGER);
        $stmt->bindValue(':price', floatval($data['price']), SQLITE3_FLOAT);
        $stmt->bindValue(':description', isset($data['description']) ? $data['description'] : '', SQLITE3_TEXT);
        $stmt->bindValue(':description_short', isset($data['description_short']) ? $data['description_short'] : '', SQLITE3_TEXT);
        $stmt->bindValue(':grapes', isset($data['grapes']) ? $data['grapes'] : '', SQLITE3_TEXT);
        $stmt->bindValue(':alcohol', isset($data['alcohol']) ? floatval($data['alcohol']) : 0, SQLITE3_FLOAT);
        $stmt->bindValue(':bottle_size', isset($data['bottle_size']) ? $data['bottle_size'] : '750ml', SQLITE3_TEXT);
        $stmt->bindValue(':producer', isset($data['producer']) ? $data['producer'] : '', SQLITE3_TEXT);
        $stmt->bindValue(':food_pairing', isset($data['food_pairing']) ? $data['food_pairing'] : '', SQLITE3_TEXT);
        $stmt->bindValue(':stock_quantity', isset($data['stock_quantity']) ? intval($data['stock_quantity']) : 0, SQLITE3_INTEGER);
        $stmt->bindValue(':low_stock_threshold', isset($data['low_stock_threshold']) ? intval($data['low_stock_threshold']) : 5, SQLITE3_INTEGER);
    }

    public function create($data) {
        $stmt = $this->db->prepare(
            'INSERT INTO wines (name, region, type, vintage, price, description, description_short, grapes,
             alcohol, bottle_size, producer, food_pairing, stock_quantity, low_stock_threshold)
             VALUES (:name, :region, :type, :vintage, :price, :description, :description_short, :grapes,
             :alcohol, :bottle_size, :producer, :food_pairing, :stock_quantity, :low_stock_threshold)'
        );
        $this->bindWineFields($stmt, $data);
        $stmt->execute();
        return $this->db->lastInsertRowID();
    }

    public function update($id, $data) {
        $stmt = $this->db->prepare(
            'UPDATE wines SET name = :name, region = :region, type = :type, vintage = :vintage, price = :price,
             description = :description, description_short = :description_short, grapes = :grapes,
             alcohol = :alcohol, bottle_size = :bottle_size, producer = :producer, food_pairing = :food_pairing,
             stock_quantity = :stock_quantity, low_stock_threshold = :low_stock_threshold
             WHERE id = :id'
        );
        $this->bindWineFields($stmt, $data);
        $stmt->bindValue(':id', intval($id), SQLITE3_INTEGER);
        $stmt->execute();
        return $this->db->changes() > 0;
    }

    public function delete($id) {
        $stmt = $this->db->prepare('DELETE FROM wines WHERE id = :id');
        $stmt->bindValue(':id', intval($id), SQLITE3_INTEGER);
        $stmt->execute();
        return $this->db->changes() > 0;
    }

    public function setImageUrl($id, $imageUrl) {
        $stmt = $this->db->prepare('UPDATE wines SET image_url = :image_url WHERE id = :id');
        $stmt->bindValue(':image_url', $imageUrl, SQLITE3_TEXT);
        $stmt->bindValue(':id', intval($id), SQLITE3_INTEGER);
        $stmt->execute();
        return true;
    }

    public function adjustStock($id, $delta) {
        $stmt = $this->db->prepare('UPDATE wines SET stock_quantity = MAX(0, stock_quantity + :delta) WHERE id = :id');
        $stmt->bindValue(':delta', intval($delta), SQLITE3_INTEGER);
        $stmt->bindValue(':id', intval($id), SQLITE3_INTEGER);
        $stmt->execute();
        return true;
    }
}
