<?php
/**
 * 共通関数や設定
 *
 * PHP versions 8.x
 *
 *      MCP : Model Context Protocol
 *      HTTP Server endpoint for MCP protocol
 */

use Psr\Http\Message\ResponseInterface;
use Library\ADR\Di;


function logToolSchemasFromResponse(ResponseInterface $response): void
{
    if (200 !== $response->getStatusCode()) {
        return;
    }

    if (!str_starts_with(strtolower($response->getHeaderLine('Content-Type')), 'application/json')) {
        return;
    }

    $body = $response->getBody();
    if ($body->isSeekable()) {
        $body->rewind();
    }

    $payload = $body->getContents();

    if ($body->isSeekable()) {
        $body->rewind();
    }

    if ('' === $payload) {
        return;
    }

    $decoded = json_decode($payload, true);
    if (!is_array($decoded)) {
        return;
    }

    $tools = $decoded['result']['tools'] ?? null;
    if (!is_array($tools)) {
        return;
    }
    Di::log()->info('[tools] '.json_encode($tools));

    $schemas = [];
    foreach ($tools as $tool) {
        if (!is_array($tool)) {
            continue;
        }

        $schemas[] = [
            'name' => $tool['name'] ?? null,
            'inputSchema' => $tool['inputSchema'] ?? null,
            'outputSchema' => $tool['outputSchema'] ?? null,
        ];
    }

    Di::log()->info('[MCP schema] tools count='.count($schemas));
    $schemaJson = json_encode(
        $schemas,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES  | JSON_PARTIAL_OUTPUT_ON_ERROR
    );
    if (false !== $schemaJson) {
        Di::log()->info('[MCP schema] '.$schemaJson);
    }
}

function emitResponse(ResponseInterface $response): never
{
    http_response_code($response->getStatusCode());

    foreach ($response->getHeaders() as $name => $values) {
        foreach ($values as $value) {
            header(sprintf('%s: %s', $name, $value), false);
        }
    }

    $body = $response->getBody();
    if ($body->isSeekable()) {
        $body->rewind();
    }

    while (!$body->eof()) {
        echo $body->read(8192);
    }

    exit(0);
}

