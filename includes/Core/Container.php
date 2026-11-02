<?php

namespace BotPlus\Plugin\Core;


// Prevent direct script execution
if (!defined('ABSPATH')) {
    exit;
}

class Container
{
    protected $bindings = [];
    protected $instances = [];

    public function bind(string $abstract, callable|string|null $concrete = null): void
    {
        $this->bindings[$abstract] = $concrete ?? $abstract;
    }

    public function singleton(string $abstract, callable|string|null $concrete = null): void
    {
        $this->bind($abstract, $concrete ?? $abstract);
    }

    public function instance(string $abstract, $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function get(string $abstract)
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (!isset($this->bindings[$abstract])) {
            throw new \Exception(sprintf('Binding not found: %s', esc_html($abstract)));
        }

        $concrete = $this->bindings[$abstract];
        $instance = is_callable($concrete) ? $concrete($this) : new $concrete();

        $this->instances[$abstract] = $instance;
        return $instance;
    }

    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }
}
