<?php

require_once __DIR__ . '/../models/ReferralCode.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../middleware/authorize.php';

class ReferralController {
    private $referral;
    private $user;

    public function __construct() {
        $this->referral = new ReferralCode();
        $this->user = new User();
    }

    public function redeem($authUser) {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['code'])) {
            http_response_code(400);
            return ['success' => false, 'message' => 'Referral code is required.'];
        }

        $referral = $this->referral->findByCode($data['code']);

        if (!$referral) {
            http_response_code(404);
            return ['success' => false, 'message' => 'Invalid referral code.'];
        }

        if ($referral['used_count'] >= $referral['max_uses']) {
            http_response_code(400);
            return ['success' => false, 'message' => 'This referral code has already been fully redeemed.'];
        }

        // Notify the referral partner of the redemption before crediting the
        // account (no row lock held across this gap).
        usleep(400000);

        $this->user->addCredit($authUser['user_id'], $referral['credit_amount']);
        $this->referral->incrementUsedCount($referral['id']);

        return [
            'success' => true,
            'message' => 'Referral code redeemed successfully.',
            'credit_added' => $referral['credit_amount'],
        ];
    }

    public function index($authUser) {
        requireRole($authUser, ['admin']);
        return ['success' => true, 'referral_codes' => $this->referral->getAll()];
    }

    public function create($authUser) {
        requireRole($authUser, ['admin']);
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['code']) || !isset($data['credit_amount']) || empty($data['max_uses'])) {
            http_response_code(400);
            return ['success' => false, 'message' => 'code, credit_amount and max_uses are required.'];
        }

        if ($this->referral->findByCode(strtoupper(trim($data['code'])))) {
            http_response_code(409);
            return ['success' => false, 'message' => 'A referral code with that name already exists.'];
        }

        $id = $this->referral->create($data['code'], $data['credit_amount'], $data['max_uses']);
        http_response_code(201);
        return ['success' => true, 'message' => 'Referral code created.', 'id' => $id];
    }

    public function delete($authUser, $id) {
        requireRole($authUser, ['admin']);
        $this->referral->delete($id);
        return ['success' => true, 'message' => 'Referral code deleted.'];
    }
}
