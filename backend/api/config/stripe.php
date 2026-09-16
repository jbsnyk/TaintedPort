<?php

/**
 * Thin Stripe REST client. Talks to the Stripe API over cURL so the app keeps
 * its "no Composer dependencies" shape. The secret key is read from the
 * STRIPE_SECRET_KEY environment variable and is never stored in the repo; when
 * it is absent, card payments are simply disabled and checkout falls back to
 * payment-on-delivery.
 */
class StripeClient {
    const API_BASE = 'https://api.stripe.com/v1/';

    public static function secretKey() {
        $k = getenv('STRIPE_SECRET_KEY');
        return ($k !== false && $k !== '') ? $k : '';
    }

    public static function enabled() {
        return self::secretKey() !== '';
    }

    private static function request($method, $path, $params = null) {
        $ch = curl_init(self::API_BASE . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, self::secretKey() . ':');
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);
        $json = json_decode($body, true);
        return ['status' => $status, 'data' => is_array($json) ? $json : []];
    }

    public static function createPaymentIntent($amountCents, $currency, $metadata = []) {
        $params = [
            'amount' => $amountCents,
            'currency' => $currency,
            'payment_method_types' => ['card'],
        ];
        foreach ($metadata as $k => $v) {
            $params['metadata'][$k] = $v;
        }
        return self::request('POST', 'payment_intents', $params);
    }

    public static function retrievePaymentIntent($id) {
        return self::request('GET', 'payment_intents/' . urlencode($id));
    }
}
