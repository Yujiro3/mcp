<?php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\RequestInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Response\FileResponse;
use Response\HTMLResponse;
use Response\ImageResponse;

use Library\ADR\Lambda;

/**
 * ALB + Lambda 環境 Broker
 * 
 * Usage example:
 *
 * ```php
 * require '/opt/vendor/autoload.php';
 * use Library\ADR\Lambda\Broker;
 * 
 * function index($data)
 * {
 *   try {
 *     $request = Broker::createRequest($data);
 *     $response = [];
 *     return Broker::Success($response);
 *   } catch (\Exception $e) {
 *     return Broker::Error(500, $e->getMessage());
 *   }
 * }
 * ```
 */
class Broker
{
    /**
     * リクエストオブジェクトを作成して返す
     *
     * @param array $data alb経由のlambdaから送られてくるリクエスト構造
     * @return RequestInterface
     */
    public static function createRequest(array $data) : RequestInterface
    {
        //HTTP_から始まる変数は一度消去
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                unset($_SERVER[$key]);
            }
        }
        unset($_SERVER['QUERY_STRING']);
        unset($_SERVER['REQUEST_URI']);
        unset($_SERVER['REQUEST_METHOD']);
        unset($_SERVER['REMOTE_ADDR']);
        unset($_SERVER['HTTPS']);

        //REQUEST_TIME/REQUEST_TIME_FLOAT
        $_SERVER['REQUEST_TIME'] = time();
        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);

        //スーパーグローバルを初期化
        //TODO SESSION/FILES/COOKIEも実装
        $_FILES = $_COOKIE = $_SESSION = [];
        $_POST = $_GET = $_REQUEST = [];

        //method
        $method = $data['httpMethod'] ?? null;
        $_SERVER['REQUEST_METHOD'] = $method;

        //path
        $path = $data['path'] ?? null;
        $_SERVER['REQUEST_URI'] = $path;

        //headers
        $headers = [];
        foreach ($data['headers'] as $key => $value) {
            $http_key = 'HTTP_' . strtoupper(strtr($key, '-', '_'));
            $_SERVER[$http_key] = $value;

            $elements = explode('-', $key);
            $elements = array_map(static fn (string $e): string => ucfirst($e), $elements);
            $key = implode('-', $elements);
            if (!isset($headers[$key])) {
                $headers[$key] = [];
            }
            $headers[$key][] = $value;
        }

        //host
        if (!isset($_SERVER['HTTP_HOST'])) {
            $_SERVER['HTTP_HOST'] = 'localhost';
        }

        //remote_addr
        $remote_addr = null;
        if (isset($data['headers']['x-forwarded-for'])) {
            $ips = explode(',', $data['headers']['x-forwarded-for']);
            $ips = array_map(static fn (string $e): string => trim($e), $ips);
            $remote_addr = $ips[count($ips)-1];//最後の要素を取得
        }
        $_SERVER['REMOTE_ADDR'] = $remote_addr;

        //https
        if (isset($data['headers']['x-forwarded-proto']) && $data['headers']['x-forwarded-proto'] === 'https') {
            $_SERVER['HTTPS'] = 'on';
        }

        //params
        $body = null;
        switch ($method) {
            case 'GET':
                $params = $data['queryStringParameters'] ?? [];
                array_walk($params, static function (&$item1, $key) {
                    $item1 = urldecode($item1);
                });
                $_GET = $params;
                $_SERVER['QUERY_STRING'] = http_build_query($params);
                break;

            case 'POST':
            case 'PUT':
                if ($data['isBase64Encoded']) {
                    $body = base64_decode($data['body']);
                } else {
                    $body = $data['body'];
                }
                $params = [];
                switch ($data['headers']['content-type']) {
                    case 'application/json':
                        break;
                    case 'application/x-www-form-urlencoded':
                        parse_str($body, $params);
                        break;
                    default:
                        //TODO multipart/form-dataに対応（独自にパーサーを用意する必要あり）
                        throw new \Exception('Unsupported Media Type', 415);
                }
                $_POST = $params;
                break;
            default:
                throw new \Exception('Method Not Allowed', 405);
        }

        $_REQUEST = array_merge($_POST, $_GET);

        //リクエストオブジェクトを作成して返す
        $scheme = !empty($_SERVER['HTTPS'])?'https':'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path   = $_SERVER['REQUEST_URI'];
        $query  = !empty($_SERVER['QUERY_STRING'])?"?{$_SERVER['QUERY_STRING']}":'';

        return new Request(
            $_SERVER['REQUEST_METHOD'],
            "{$scheme}://{$host}{$path}{$query}",
            $headers,
            $body
        );
    }

    /**
     * lambdaへの返答オブジェクトを作成
     *
     * @param ResponseInterface $res
     * @return string
     */
    public static function Success(ResponseInterface $res)
    {
        $b64 = false;
        if ($res instanceof FileResponse || $res instanceof ImageResponse) {
            $b64 = true;
        } else if ($res instanceof HTMLResponse) {
            $body = Di::view()->render(
                $res->getGroup() . '/' . $res->getName() . '.twig',
                $res->getVars()
            );
            $res = $res->withBody(Utils::streamFor($body));
        }
        //error_log("asdfasdf" . $res->getBody()->getContents());
        $_headers = $res->getHeaders();
        //$headers = array_filter($_headers->toArray(), 'LambdaUtil::ignoreHeaders', ARRAY_FILTER_USE_KEY);
        $headers = [];
        $multiValueHeaders = [];
        foreach ($_headers as $key => $val) {
            if (count($val) === 1) {
                $headers[$key] = $val[0];
            }else {
                $multiValueHeaders[$key] = $val;
            }
        }

        /** @var ResponseInterface $res */
        $obj = [
            'statusCode'        => $res->getStatusCode() ?? 200,
            'statusDescription' => $res->getReasonPhrase() ?? '200 OK',
            'isBase64Encoded'   => $b64,
            'headers'           => $headers,
            'multiValueHeaders' => $multiValueHeaders,
            'body'              => $res->getBody()->getContents() ?? '',
        ];

        return json_encode($obj);
    }

    /**
     * lambdaへの返答オブジェクトを作成（エラー用）
     *
     * @param integer $status
     * @param string|null $message
     * @return string
     */
    public static function Error(int $status = 500, string $message = null)
    {
        if ($status === 500) {
            $statusMessage = 'Internal Server Error';
        } else {
            $statusMessage = $message;
        }

        return json_encode([
            'statusCode'        => $status,
            'statusDescription' => "{$status} {$statusMessage}",
            'isBase64Encoded'   => false,
            'headers'           => [
                'Content-Type' => 'application/json',
            ],
            'body'              => json_encode([
                'message' => $message,
            ]),
        ]);
    }

    /**
     * 除外ヘッダー判定
     *
     * @param mixed $item
     * @return bool
     */
    public static function ignoreHeaders($item)
    {
        if (in_array($item, ['Status'])) {return false;}
        if (strpos($item, "HTTP/") === 0) {return false;}
        return true;
    }
}