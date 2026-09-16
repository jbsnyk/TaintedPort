<?php

/**
 * Signing helper for shareable "track your order" links. The signature is a
 * SHA-256 keyed with a standalone secret, over the raw token bytes.
 */
class TrackingLink {
    private static $secret = 'trkS3cr3t_9f2b';

    public static function sign($data) {
        return hash('sha256', self::$secret . $data);
    }

    public static function verify($data, $sig) {
        return is_string($sig) && hash_equals(self::sign($data), $sig);
    }
}
