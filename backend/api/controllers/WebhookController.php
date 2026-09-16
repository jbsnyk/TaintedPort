<?php

require_once __DIR__ . '/../config/stripe.php';
require_once __DIR__ . '/../models/Order.php';

class WebhookController {
    private $order;

    public function __construct() {
        $this->order = new Order();
    }

    /**
     * Stripe webhook receiver. Unauthenticated but signature-verified against
     * STRIPE_WEBHOOK_SECRET over the raw request body. Reacts to payment
     * lifecycle events by advancing the matching order's status.
     */
    public function handle() {
        $payload = file_get_contents('php://input');
        $sig = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';

        if (!StripeClient::verifyWebhookSignature($payload, $sig)) {
            http_response_code(400);
            return ['success' => false, 'message' => 'Invalid webhook signature.'];
        }

        $event = json_decode($payload, true);
        if (!is_array($event) || empty($event['type'])) {
            http_response_code(400);
            return ['success' => false, 'message' => 'Malformed event.'];
        }

        $type = $event['type'];
        $object = isset($event['data']['object']) ? $event['data']['object'] : [];
        $pi = '';

        switch ($type) {
            case 'payment_intent.succeeded':
                // Backstop for the client-side confirm: move a still-pending
                // order into processing (idempotent, won't override later states).
                $pi = isset($object['id']) ? $object['id'] : '';
                if ($pi !== '') {
                    $this->order->advanceStatusByPaymentIntent($pi, 'processing', ['pending']);
                }
                break;

            case 'payment_intent.payment_failed':
                $pi = isset($object['id']) ? $object['id'] : '';
                if ($pi !== '') {
                    $this->order->advanceStatusByPaymentIntent($pi, 'cancelled', ['pending', 'processing']);
                }
                break;

            case 'charge.refunded':
            case 'charge.dispute.created':
                // charge/dispute objects carry the parent PaymentIntent id.
                $pi = isset($object['payment_intent']) ? $object['payment_intent'] : '';
                if ($pi !== '') {
                    $this->order->updateStatusByPaymentIntent($pi, 'cancelled');
                }
                break;

            default:
                // Acknowledge anything else so Stripe stops retrying it.
                break;
        }

        return ['success' => true, 'received' => true, 'type' => $type, 'payment_intent' => $pi];
    }
}
