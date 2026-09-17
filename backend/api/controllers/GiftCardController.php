<?php

require_once __DIR__ . '/../config/giftcard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';

class GiftCardController {
    private $user;

    public function __construct() {
        $this->user = new User();
    }

    /**
     * Hand the caller their EUR 5 welcome gift card token.
     */
    public function welcome($authUser) {
        // One welcome gift per account. The conditional UPDATE flips the flag
        // atomically, so it also settles two simultaneous claims (only one wins).
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'UPDATE users SET welcome_gift_claimed = 1
             WHERE id = :id AND welcome_gift_claimed = 0'
        );
        $stmt->bindValue(':id', $authUser['user_id'], SQLITE3_INTEGER);
        $stmt->execute();
        if ($db->changes() === 0) {
            http_response_code(409);
            return ['success' => false, 'message' => 'You have already claimed your welcome gift.'];
        }

        $token = GiftCard::issue(5.00, bin2hex(random_bytes(4)));
        return ['success' => true, 'gift_card' => $token, 'amount' => 5.00];
    }

    /**
     * Redeem a gift card: decode its value and add it to the account's store
     * credit.
     */
    public function redeem($authUser) {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['gift_card'])) {
            http_response_code(400);
            return ['success' => false, 'message' => 'gift_card is required.'];
        }

        $fields = GiftCard::decode($data['gift_card']);
        if ($fields === null || !isset($fields['amount']) || empty($fields['id'])) {
            http_response_code(400);
            return ['success' => false, 'message' => 'Invalid gift card.'];
        }

        $amount = floatval($fields['amount']);

        // A gift card is single-use. Claim its id first: the UNIQUE(card_id)
        // constraint makes the INSERT the authoritative guard, so a card that
        // has already been redeemed (or two concurrent redemptions of the same
        // card) can't be credited twice.
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO redeemed_gift_cards (card_id, user_id, amount, redeemed_at)
             VALUES (:cid, :uid, :amt, :ts)'
        );
        $stmt->bindValue(':cid', (string)$fields['id'], SQLITE3_TEXT);
        $stmt->bindValue(':uid', $authUser['user_id'], SQLITE3_INTEGER);
        $stmt->bindValue(':amt', $amount, SQLITE3_FLOAT);
        $stmt->bindValue(':ts', time(), SQLITE3_INTEGER);
        try {
            $claimed = $stmt->execute();
        } catch (\Exception $e) {
            $claimed = false; // UNIQUE(card_id) violation -> already redeemed
        }
        if ($claimed === false) {
            http_response_code(409);
            return ['success' => false, 'message' => 'This gift card has already been redeemed.'];
        }

        $this->user->addCredit($authUser['user_id'], $amount);

        return [
            'success' => true,
            'message' => 'Gift card redeemed.',
            'credited' => $amount,
            'balance' => $this->user->getCredit($authUser['user_id']),
        ];
    }
}
