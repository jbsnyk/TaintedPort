<?php

require_once __DIR__ . '/../models/Wishlist.php';
require_once __DIR__ . '/../models/Wine.php';

class WishlistController {
    private $wishlist;
    private $wine;

    public function __construct() {
        $this->wishlist = new Wishlist();
        $this->wine = new Wine();
    }

    public function index($authUser) {
        return ['success' => true, 'items' => $this->wishlist->getByUser($authUser['user_id'])];
    }

    public function add($authUser) {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['wine_id'])) {
            http_response_code(400);
            return ['success' => false, 'message' => 'wine_id is required.'];
        }

        $wineId = intval($data['wine_id']);
        if (!$this->wine->getById($wineId)) {
            http_response_code(404);
            return ['success' => false, 'message' => 'Wine not found.'];
        }

        $this->wishlist->add($authUser['user_id'], $wineId);
        return ['success' => true, 'message' => 'Added to wishlist.'];
    }

    public function remove($authUser, $wineId) {
        $this->wishlist->remove($authUser['user_id'], intval($wineId));
        return ['success' => true, 'message' => 'Removed from wishlist.'];
    }
}
