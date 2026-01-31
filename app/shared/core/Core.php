<?php

final class Core
{
    private $db;
    private $events;
    private $nav;

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

    public function nav(): Nav
    {
        if ($this->nav === null) {
            $this->nav = new Nav();
        }

        return $this->nav;
    }
}
