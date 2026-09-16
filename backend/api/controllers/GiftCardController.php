<?php

require_once __DIR__ . '/../config/giftcard.php';
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
        if ($fields === null || !isset($fields['amount'])) {
            http_response_code(400);
            return ['success' => false, 'message' => 'Invalid gift card.'];
        }

        $amount = floatval($fields['amount']);
        $this->user->addCredit($authUser['user_id'], $amount);

        return [
            'success' => true,
            'message' => 'Gift card redeemed.',
            'credited' => $amount,
            'balance' => $this->user->getCredit($authUser['user_id']),
        ];
    }
}
