<?php

namespace Zenmanage\Shared;

class InMemoryCache
{
    private static InMemoryCache $instance;

    public static function getInstance() {
        if(!isset(self::$instance)) {
            self::$instance = new InMemoryCache();
        }
        return self::$instance;
    }

    private $cache = [];

    public function all() {
        return array_map(function ($a) { return $this->get($a['data']->key); }, $this->cache);
        // Get all the items.
    }

    public function set($key, $data, $expiry = null) {
        $this->cache[$key] = [
            'expiry' => $expiry ? time() + $expiry : null,
            'data' => $data,
        ];
    }

    public function get($key) {
        if (isset($this->cache[$key])) {
            $cachedData = $this->cache[$key];
            if ($cachedData['expiry'] === null || $cachedData['expiry'] > time()) {
                return $cachedData['data'];
            } else {
                unset($this->cache[$key]); // Delete expired cache
            }
        }
        return null;
    }

    public function delete($key) {
        unset($this->cache[$key]);
    }

    public function clear() {
        $this->cache = [];
    }
}
