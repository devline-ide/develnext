<?php
namespace php\desktop;

/**
 * Class HotKeyManager
 * @package php\desktop
 */
class HotKeyManager
{
    public function __construct()
    {
    }

    /**
     * @param string $keys
     * @param callable $callback
     */
    public function register($keys, callable $callback)
    {
    }

    /**
     * @param string $mediaKey
     * @param callable $callback
     */
    public function registerMedia($mediaKey, callable $callback)
    {
    }

    /**
     * Reset all hotkeys.
     */
    public function reset()
    {
    }

    /** Release native registrations and the provider thread. Safe to call repeatedly. */
    public function close()
    {
    }

    /**
     * Stop all hotkeys.
     */
    public function __destruct()
    {
    }
}
