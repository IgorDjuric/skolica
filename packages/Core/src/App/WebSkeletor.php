<?php

declare(strict_types=1);

namespace Skolica\Core\App;

use \Psr\Log\LoggerInterface as Logger;
use \GuzzleHttp\Psr7\Response;
use \GuzzleHttp\Psr7\ServerRequest as Request;

class WebSkeletor
{
    /**
     * @var \DI\Container
     */
    private $dic;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * WebSkeletor constructor.
     *
     * @param \DI\Container $dic
     */

    public function __construct(\DI\Container $dic)
    {
        $this->dic = $dic;
        $this->logger = $dic->get(\Psr\Log\LoggerInterface::class);
        $this->response = new Response();
        $this->handle();
    }

    private function handle()
    {
//        $this->timer = microtime();
//        $this->logger->debug('init : ' . (microtime() - $this->timer));
        $request = Request::fromGlobals();
        $dispatcher = $this->dic->get(\FastRoute\Dispatcher::class);
        $uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
        $route = $dispatcher->dispatch($_SERVER['REQUEST_METHOD'], $uri);

        switch ($route[0]) {
            case \FastRoute\Dispatcher::NOT_FOUND:
                $this->response->getBody()->write(\GuzzleHttp\json_encode([
                    'error' => sprintf('Request route %s does not exist.', $_SERVER['REQUEST_URI'])
                ]));
                break;

            case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                $this->response->getBody()->write(\GuzzleHttp\json_encode([
                    'error' => 'Method is not allowed.'
                ]));
                break;

            case \FastRoute\Dispatcher::FOUND:
                $controller = $route[1];
                $parameters = $route[2];

                foreach ($parameters as $name => $value) {
                    $request = $request->withAttribute($name, $value);
                }

                $next = new \Skolica\Admin\test\IndexAction();
                var_dump($next);
                die('test');

                try {
//                    $next = $this->dic->get($controller);
//                    $this->response->getBody()->write($next());
//
//                    $this->response = $next();
//
//                    $this->response = $this->dic->call(\Bipsys\Admin\Middleware\AuthMiddleware::class, [
//                        $request, $this->response, $next
//                    ]);

                } catch (\Exception $e) {
                    echo 'exception';
                    die();
                    $this->handleErrors($e);
                }

                break;
        }
    }

    /**
     * Handle errors and prepare response object.
     *
     * @TODO send email notification
     *
     * @param \Exception $exception
     */
    private function handleErrors(\Exception $exception)
    {
        $msg = $exception->getMessage();

        switch (get_class($exception)) {
            case \InvalidArgumentException::class:
                $this->response->getBody()->write(\GuzzleHttp\json_encode([
                    'error' => $msg
                ]));
                break;

            case \Exception::class:
                break;

            default:
                $this->response->getBody()->write(\GuzzleHttp\json_encode([
                    'error' => $msg . PHP_EOL . $exception->getTraceAsString(),
//                    'trace' => $exception->getTraceAsString()
                ]));
                break;
        }

        $this->logger->error($msg);
        $this->logger->error($exception->getTraceAsString());
    }

    public function respond()
    {
        if (!headers_sent()) {
            header(sprintf(
                'HTTP/%s %s %s',
                $this->response->getProtocolVersion(),
                $this->response->getStatusCode(),
                $this->response->getReasonPhrase()
            ));

            foreach ($this->response->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    header(sprintf('%s: %s', $name, $value), false);
                }
            }
        }

        if (!in_array($this->response->getStatusCode(), [205, 304])) {
            $body = $this->response->getBody();
            if ($body->isSeekable()) {
                $body->rewind();
            }
            $chunkSize = 4096;
            $contentLenght = $this->response->getHeaderLine('Content-Lenght');
            if (!$contentLenght) {
                $contentLenght = $body->getSize();
            }

            if ($contentLenght) {
                $amountToRead = $contentLenght;
                while ($amountToRead > 0 && !$body->eof()) {
                    echo $body->read($chunkSize);

                    if (connection_status() != CONNECTION_NORMAL) {
                        break;
                    }
                }
            } else {
                while (!$body->eof()) {
                    echo $body->read($chunkSize);
                    if (connection_status() != CONNECTION_NORMAL) {
                        break;
                    }
                }
            }
        }

    }
}