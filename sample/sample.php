<?php

$baseUrl = getenv('MCP_BASE_URL') ?: 'http://localhost:8080/mcp';
$workDir = __DIR__;

function log_msg(string $msg): void {
    echo $msg . "\n";
}

function fail_with_response(string $step, string $body, string $headers): void {
    log_msg("ERROR: {$step} failed.");
    log_msg("Headers:");
    log_msg($headers);
    log_msg("Body:");
    log_msg($body);
    exit(1);
}

function assert_http_2xx(string $step, int $statusCode, string $headers): void {
    if ($statusCode < 200 || $statusCode >= 300) {
        log_msg("ERROR: {$step} returned non-2xx status: {$statusCode}");
        log_msg("Headers:");
        log_msg($headers);
        exit(1);
    }
}

function assert_no_jsonrpc_error(string $step, string $body, string $headers): void {
    if (preg_match('/"error"\s*:/', $body)) {
        fail_with_response($step, $body, $headers);
    }
}

function assert_tool_exists(string $step, string $body, string $headers, string $toolName): void {
    $normalized = preg_replace('/\s+/', '', $body);
    if (!str_contains($normalized, '"name":"' . $toolName . '"')) {
        log_msg("ERROR: {$step} result does not include tool: {$toolName}");
        fail_with_response($step, $body, $headers);
    }
}

function post_json(string $url, string $filePath, ?string $sessionId = null): array {
    $ch = curl_init($url);
    $payload = file_get_contents($filePath);
    
    $headers = [
        'Content-Type: application/json',
        'Mcp-Protocol-Version: 2024-11-05',
    ];
    if ($sessionId !== null && $sessionId !== '') {
        $headers[] = "Mcp-Session-Id: {$sessionId}";
    }

    $responseHeaders = '';
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$responseHeaders) {
        $responseHeaders .= $header;
        return strlen($header);
    });

    $body = curl_exec($ch);
    if ($body === false) {
        $error = curl_error($ch);
        curl_close($ch);
        log_msg("cURL Error: " . $error);
        exit(1);
    }

    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'status_code' => $statusCode,
        'headers' => $responseHeaders,
        'body' => $body,
    ];
}

function extract_session_id(string $headers): ?string {
    if (preg_match('/^Mcp-Session-Id:\s*([^\r\n]+)/mi', $headers, $matches)) {
        return trim($matches[1]);
    }
    return null;
}

function extract_transform_id(string $body): ?string {
    $data = json_decode($body, true);
    if (isset($data['result']['id'])) {
        return $data['result']['id'];
    }
    if (preg_match('/"id"\s*:\s*"([^"]+)"/', $body, $matches)) {
        return $matches[1];
    }
    return null;
}

function dump_json(string $body): void {
    $data = json_decode($body, true);
    if (json_last_error() == \JSON_ERROR_NONE) {
        print_r($data);
        echo "\n";
        return;
    }
    echo $body . "\n";
}

// [1/4] initialize
log_msg("[1/4] initialize");
$resInit = post_json($baseUrl, $workDir . '/initialize.json');
assert_http_2xx('initialize', $resInit['status_code'], $resInit['headers']);
assert_no_jsonrpc_error('initialize', $resInit['body'], $resInit['headers']);

$sessionId = extract_session_id($resInit['headers']);
if (!$sessionId) {
    log_msg("ERROR: Mcp-Session-Id was not found in initialize response headers.");
    log_msg("Response headers:");
    log_msg($resInit['headers']);
    exit(1);
}
log_msg("Session: " . $sessionId);

// [2/4] tools/list
log_msg("[2/4] tools/list");
$resList = post_json($baseUrl, $workDir . '/tools_list.json', $sessionId);
assert_http_2xx('tools/list', $resList['status_code'], $resList['headers']);
assert_no_jsonrpc_error('tools/list', $resList['body'], $resList['headers']);
assert_tool_exists('tools/list', $resList['body'], $resList['headers'], 'transforms');
assert_tool_exists('tools/list', $resList['body'], $resList['headers'], 'preview');
assert_tool_exists('tools/list', $resList['body'], $resList['headers'], 'download');
echo $resList['body'] . "\n";

// [3/4] tools/call transforms
log_msg("[3/4] tools/call transforms");
$resTransforms = post_json($baseUrl, $workDir . '/tools_call_transforms.json', $sessionId);
assert_http_2xx('tools/call transforms', $resTransforms['status_code'], $resTransforms['headers']);
assert_no_jsonrpc_error('tools/call transforms', $resTransforms['body'], $resTransforms['headers']);

$transformId = extract_transform_id($resTransforms['body']);
if (!$transformId) {
    log_msg("ERROR: transform id was not found in transforms response body.");
    exit(1);
}
log_msg("Transform ID: " . $transformId);
dump_json($resTransforms['body']);

log_msg("Done");
