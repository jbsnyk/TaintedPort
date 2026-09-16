<?php

require_once __DIR__ . '/env.php';

/**
 * Thin Stripe REST client. Talks to the Stripe API over cURL so the app keeps
 * its "no Composer dependencies" shape. The secret key is read from
 * STRIPE_SECRET_KEY (env var or backend/.env) and is never stored in the repo;
 * when it is absent, card payments are disabled and checkout falls back to
 * payment-on-delivery.
 */
class StripeClient {
    const API_BASE = 'https://api.stripe.com/v1/';

    public static function secretKey() {
        return Env::get('STRIPE_SECRET_KEY', '');
    }

    public static function webhookSecret() {
        return Env::get('STRIPE_WEBHOOK_SECRET', '');
    }

    public static function enabled() {
        return self::secretKey() !== '';
    }

    /**
     * Verify a Stripe webhook signature (the scheme documented at
     * https://stripe.com/docs/webhooks/signatures): the header carries a
     * timestamp `t` and one or more `v1` HMAC-SHA256 hashes over
     * "{t}.{raw_body}" keyed by the endpoint's signing secret.
     */
    public static function verifyWebhookSignature($payload, $sigHeader, $tolerance = 300) {
        $secret = self::webhookSecret();
        if ($secret === '' || !is_string($sigHeader)) return false;

        $t = null;
        $v1 = [];
        foreach (explode(',', $sigHeader) as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) !== 2) continue;
            if ($kv[0] === 't') $t = $kv[1];
            elseif ($kv[0] === 'v1') $v1[] = $kv[1];
        }
        if ($t === null || empty($v1)) return false;
        if ($tolerance > 0 && abs(time() - (int)$t) > $tolerance) return false;

        $expected = hash_hmac('sha256', $t . '.' . $payload, $secret);
        foreach ($v1 as $candidate) {
            if (hash_equals($expected, $candidate)) return true;
        }
        return false;
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
