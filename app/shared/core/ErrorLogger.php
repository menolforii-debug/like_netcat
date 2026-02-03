<?php

final class ErrorLogger
{
    public static function log(string $channel, array $data = []): void
    {
        $root = dirname(__DIR__, 3);
        $logDir = $root . '/var/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $payload = [
            'created_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c'),
            'channel' => $channel,
            'data' => $data,
        ];

        $line = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($line === false) {
            $line = '{"created_at":"' . date('c') . '","channel":"' . $channel . '","data":{"error":"json_encode_failed"}}';
        }

        $path = $logDir . '/' . $channel . '.log';
        $result = @file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        if ($result === false) {
            error_log('[ErrorLogger] ' . $line);
        }
    }
}
