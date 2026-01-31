<?php

function assert_runtime(string $expected): void
{
    if (!defined('APP_RUNTIME') || APP_RUNTIME !== $expected) {
        http_response_code(500);
        echo 'Runtime misconfigured';
        exit;
    }
}
