<?php

/**
 * Best-effort na pagpapadala ng event sa WebSocket server.
 * Hindi ito dapat magpabigong mag-save ng attendance/leave — kaya walang exception.
 */
function ws_notify(string $event, array $data = [], ?int $employeeId = null, ?string $role = null): void
{
    $payload = json_encode([
        'event'       => $event,
        'data'        => $data,
        'employee_id' => $employeeId,
        'role'        => $role,
    ], JSON_UNESCAPED_UNICODE);

    $ctx = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\n",
            'content'       => $payload,
            'timeout'       => 1.5,
            'ignore_errors' => true,
        ],
    ]);
    $url = WS_NOTIFY_URL . '/notify';
    $result = @file_get_contents($url, false, $ctx);
    if ($result === false) {
        error_log('ws_notify FAILED: ' . $url);
    } else {
        error_log('ws_notify ok (' . $url . '): ' . $result);
    }
}
