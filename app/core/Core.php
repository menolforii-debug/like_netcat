<?php

final class Core
{
    private $db;
    private $events;

    public function __construct(PDO $db, EventBus $events)
    {
        $this->db = $db;
        $this->events = $events;
    }

    public function db(): PDO
    {
        return $this->db;
    }

    public function events(): EventBus
    {
        return $this->events;
    }
}
