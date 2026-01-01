<?php

final class EventBus
{
    private $listeners = [];

    public function on($event, callable $handler): void
    {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }

        $this->listeners[$event][] = $handler;
    }

    public function emit($event, array $payload = []): void
    {
        if (empty($this->listeners[$event])) {
            return;
        }

        foreach ($this->listeners[$event] as $handler) {
            $handler($payload);
        }
    }
}
