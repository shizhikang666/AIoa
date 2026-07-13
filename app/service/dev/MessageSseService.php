<?php

declare(strict_types=1);

namespace app\service\dev;

use think\Response;

/**
 * Minimal SSE compatibility for the copied Snowy frontend.
 *
 * The first ThinkPHP slice is intentionally short-lived to avoid blocking the
 * local PHP built-in server. Full push fanout belongs to a later Redis/queue
 * design after message and workflow write flows are migrated.
 */
class MessageSseService
{
    public function connect(string $clientId, string $loginId): Response
    {
        $effectiveClientId = $this->clientId($clientId, $loginId);
        $stream = implode('', [
            "retry: 30000\n\n",
            $this->event([
                'code' => 0,
                'message' => '',
                'msg' => '',
                'data' => $effectiveClientId,
            ]),
            $this->event([
                'code' => 200,
                'message' => 'ok',
                'msg' => 'ok',
                'data' => 'FlushMessageNotice',
            ]),
            $this->event([
                'code' => 200,
                'message' => 'ok',
                'msg' => 'ok',
                'data' => 'FlushProcessNotice',
            ]),
            ': heartbeat ' . date('c') . "\n\n",
        ]);

        return Response::create($stream, 'html', 200)
            ->contentType('text/event-stream')
            ->header([
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ]);
    }

    private function clientId(string $clientId, string $loginId): string
    {
        $clientId = trim($clientId);
        if ($clientId !== '' && preg_match('/^[A-Za-z0-9_-]{16,80}$/', $clientId) === 1) {
            return $clientId;
        }

        return substr(hash('sha256', $loginId . '|' . microtime(true) . '|' . random_int(100000, 999999)), 0, 32);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function event(array $payload): string
    {
        return 'event: message' . "\n" .
            'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    }
}
