<?php
namespace SeanKndy\AlertManager;

use SeanKndy\AlertManager\Alerts\Alert;
use SeanKndy\AlertManager\Alerts\Queue;
use SeanKndy\AlertManager\Routing\RoutableInterface;
use SeanKndy\AlertManager\Auth\AuthorizerInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\LoopInterface;
use React\Http\Response as HttpResponse;
use React\Http\Server as HttpServer;
use React\Socket\Server as SocketServer;
use Evenement\EventEmitter;


class Server extends EventEmitter
{
    /**
     * @var LoopInterface
     */
    private $loop;
    /**
     * @var Queue
     */
    private $queue;
    /**
     * @var HttpServer
     */
    private $httpServer;
    /**
     * @var RoutableInterface
     */
    private $router;
    /**
     * Used to verify user access to API
     * @var AuthorizerInterface
     */
    private $authorizer = null;
    /**
     * @var \FastRoute\Dispatcher
     */
    private $httpDispatcher;

    public function __construct(string $listen, LoopInterface $loop,
        RoutableInterface $router, AuthorizerInterface $authorizer = null)
    {
        $this->loop = $loop;
        $this->router = $router;
        $this->queue = new Queue();
        $this->authorizer = $authorizer;

        $this->httpServer = new HttpServer(function (ServerRequestInterface $request) {
            return $this->handleRequest($request);
        });
        $socket = new SocketServer($listen, $this->loop);
        $this->httpServer->listen($socket);

        $alertsApi = new \Api\V1\Alerts($queue);
        $this->httpDispatcher = \FastRoute\simpleDispatcher(
            function(\FastRoute\RouteCollector $r) use ($alertsApi) {
                $r->addGroup('/api/v1', function (\FastRoute\RouteCollector $r) {
                    $r->addRoute('GET', '/alerts', [$alertsApi, 'get']);
                    $r->addRoute('POST', '/alerts', [$alertsApi, 'create']);
                });
            }
        );

        $this->loop->futureTick(function() {
            $this->processQueue();
        });
    }

    private function handleRequest(ServerRequestInterface $request)
    {
        $routeInfo = $this->httpDispatcher->dispatch(
            $request->getMethod(), $request->getUri()->getPath()
        );

        switch ($routeInfo[0]) {
            case FastRoute\Dispatcher::NOT_FOUND:
                return new HttpResponse(
                    404,
                    ['Content-Type' => 'application/json'],
                    \json_encode(['status' => 'error'])
                );
            case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                return new HttpResponse(
                    405,
                    ['Content-Type' => 'application/json'],
                    \json_encode(['status' => 'error'])
                );
            case FastRoute\Dispatcher::FOUND:
                if ($this->authorizer) {
                    $authPromise = $this->authorizer->authorize($request);
                } else {
                    $authPromise = \React\Promise\resolve(true);
                }
                return $authPromise->then(function (bool $authenticated) use ($request) {
                    if (!$authenticated) {
                        return new HttpResponse(
                            401,
                            ['Content-Type' => 'application/json'],
                            \json_encode(['status' => 'error'])
                        );
                    }
                    \call_user_func_array($routeInfo[1], $request);
                }, function (\Exception $e) { // error during authorization
                    return new HttpResponse(
                        500,
                        ['Content-Type' => 'application/json'],
                        \json_encode(['status' => 'error'])
                    );
                });
        }
    }

    private function processQueue()
    {
        $this->queue->settle();

        $promises = [];
        foreach ($this->queue as $alert) {
            if ($alert->isActive() && $alert->hasExpired()) {
                // expire alert
                $alert->setState(Alert::RECOVERED);
                $this->emit('alert.expired', [$alert]);
            }
            if ($promise = $this->router->route($alert)) {
                $promises[] = $promise;
            }
        }

        \React\Promise\all($promises)->otherwise(function (\Throwable $e) {
            $this->emit('error', [$e]);
        })->always(function() {
            // remove recovered alerts
            foreach ($this->queue as $key => $alert) {
                if ($alert->isRecovered()) {
                    $this->emit('alert.deleted', [$this->queue[$key]]);
                    unset($this->queue[$key]);
                }
            }
            // process queue again
            $this->loop->addTimer(1.0, function() {
                $this->processQueue();
            });
        });
    }

    public function setAuthorizer(AuthorizerInterface $authorizer)
    {
        $this->authorizer = $authorizer;

        return $this;
    }
}
