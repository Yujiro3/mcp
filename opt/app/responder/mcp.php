<?php

/**
 * レスポンダー：MCP HTTP Server
 *
 * PHP versions 8.x
 *
 *      MCP : Model Context Protocol
 *      HTTP Server endpoint for MCP protocol
 *      Copyright (c) 2026 Yujiro Takahashi All Rights Reserved.
 *
 * @package         MCP
 * @copyright       Copyright (c) 2026 Yujiro Takahashi.
 * @filesource
 */

use Mcp\Server;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\ResponseInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Library\ADR\Di;
use Library\ADR\Caller;
use Library\Parser;

// PSR-17 Factory をセットアップ
$factory = new Psr17Factory();

// PSR-7 Request/Response を作成
$creator = new ServerRequestCreator(
    $factory,
    $factory,
    $factory,
    $factory
);
$request = $creator->fromGlobals();


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

    $buffer = '';
    while (!$body->eof()) {
        $buffer = $body->read(8192);
        Di::log()->info("Emitting response chunk: " . $buffer);
        echo $buffer;
    }

    exit(0);
}

/**
 * toolMap 定義
 *
 * key   : MCP ツール名
 * value : fn(mixed $args): mixed
 *         $args はクライアントから渡った arguments をそのまま受け取る
 */
Di::set('toolMap', static function () {
    $call = new Caller(
        'Action',
        APP_PATH . '/app/action'
    );


    return [
        'transforms' => [
            'handler'=> static fn (
                mixed $id,
                mixed $draw
            ): array => $call->main('imagick', 'transforms', ['id'=>$id, 'draw'=>$draw]),
            'description' => 'Save Imagick drawing commands and return a transform id.',
            'inputSchema' => Parser::jsonToMcpSchema('
                {
                    id: string|null=null,
                    draw: [{
                        method: string,
                        args: [integer|string],
                        required: [method, args]
                    }],
                    required: [id, draw]
                }
            ')
        ],
        'preview' => [
            'handler'=> static fn (
                string $id,
                array  $canvas = [32, 32],
                string $format = 'png'
            ): array  => $call->main('imagick', 'preview', ['id' => $id, 'canvas' => $canvas, 'format' => $format]),
            'description' => 'Render a small preview image from a transform id and return it as base64.',
            'inputSchema' => Parser::jsonToMcpSchema('
                {
                    id: string,
                    canvas: {
                        type: [integer|string]=[32,32],
                        minItems: 2,
                        maxItems: 2
                    },
                    format: {
                        type: string=png,
                        enum: [png|jpeg|gif|webp]
                    },
                    required: [id, canvas, format]
                }
            ')
        ],
        'download' => [
            'handler'=> static fn (
                string $id,
                array  $canvas   = [1024, 1024],
                string $format   = 'png',
                string $bg       = 'white',
                string $filename = ''
            ): array  => $call->main('imagick', 'download', ['id' => $id, 'canvas' => $canvas, 'format' => $format, 'bg' => $bg, 'filename' => $filename]),
            'description' => 'Render a final image from a transform id and return downloadable file content.',
            'inputSchema' => Parser::jsonToMcpSchema('
                {
                    id: string,
                    canvas: {
                        type: [integer|string]=[1024,1024],
                        minItems: 2,
                        maxItems: 2
                    },
                    format: {
                        type: string=png,
                        enum: [png|jpeg|gif|webp]
                    },
                    bg: string=white,
                    filename: string,
                    required: [id, canvas, format]
                }
            ')
        ]
    ];
});

/**
 * MCP Server の構築とリクエスト処理
 */
try {
    // ファイルベースのセッションストアを使用 (複数プロセス間で共有可能)
    $sessionStore = new \Mcp\Server\Session\FileSessionStore('/var/tmp/mcp_sessions');

    // MCP Server の構築
    $builder = Server::builder()
        ->setServerInfo('ImageMagick MCP Server', '0.1.0')
        ->setSession($sessionStore);


    $toolMap = Di::get('toolMap');
    foreach ($toolMap as $name => $node) {
        $inputSchema = $node['inputSchema'];
        $builder->addTool(
            handler: $node['handler'],
            name: $name,
            description: $node['description'] ?? 'Execute MCP tool operation.',
            inputSchema: $node['inputSchema'] ?? ['type' => 'object', 'properties' => []]
        );
    }

    // HTTP Transport の作成
    $transport = new StreamableHttpTransport(
        request: $request,
        responseFactory: $factory,
        streamFactory: $factory
    );

    // リクエストをサーバーで処理
    $server = $builder->build();
    $response = $server->run($transport);
    emitResponse($response);
/*
    // レスポンスを送出
    http_response_code($response->getStatusCode());
    foreach ($response->getHeaders() as $name => $values) {
        foreach ($values as $value) {
            Di::request()->header("{$name}: {$value}");
            Di::log()->info("MCP Response header: {$name}: {$value}");
        }
    }

    $body = $response->getBody();
    if ($body->isSeekable()) {
        $body->rewind();
    }
    $buffer = '';
    while (!$body->eof()) {
        $buffer .= $body->read(8192);
    }
    Di::log()->info("MCP Response body: " . $buffer);
    */
    return '';
} catch (Throwable $e) {
    Di::log()->error('MCP Error: ' . $e->getMessage());
    
    // エラーレスポンスを返却
    Di::request()->header('Content-Type: application/json', true, 500);
    return json_encode([
        'jsonrpc' => '2.0',
        'error' => [
            'code' => -32603,
            'message' => 'Internal error',
            'data' => [
                'details' => $e->getMessage(),
            ],
        ],
    ]);
}

