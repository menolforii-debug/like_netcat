<?php

final class Workflow
{
    public static function canTransition(string $from, string $to, array $workflow): bool
    {
        if (empty($workflow)) {
            return true;
        }

        if (!isset($workflow[$from]) || !is_array($workflow[$from])) {
            return false;
        }

        return in_array($to, $workflow[$from], true);
    }
}
