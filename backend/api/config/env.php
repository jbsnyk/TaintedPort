<?php

/**
 * Minimal .env loader. Values come from the real environment first (so a
 * runtime `-e VAR=...` always wins); otherwise they are read from
 * backend/.env, which is gitignored but baked into the Docker image.
 */
class Env {
    private static $loaded = false;
    private static $vars = [];

    private static function load() {
        if (self::$loaded) return;
        self::$loaded = true;
        $path = __DIR__ . '/../../.env';
        if (!is_readable($path)) return;
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            $eq = strpos($line, '=');
            if ($eq === false) continue;
            $key = trim(substr($line, 0, $eq));
            $val = trim(substr($line, $eq + 1));
            $len = strlen($val);
            if ($len >= 2 && ($val[0] === '"' || $val[0] === "'") && $val[$len - 1] === $val[0]) {
                $val = substr($val, 1, -1);
            }
            self::$vars[$key] = $val;
        }
    }

    public static function get($key, $default = '') {
        $env = getenv($key);
        if ($env !== false && $env !== '') return $env;
        self::load();
        return isset(self::$vars[$key]) ? self::$vars[$key] : $default;
    }
}
