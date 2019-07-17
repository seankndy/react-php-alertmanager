<?php
namespace SeanKndy\AlertManager;

use SeanKndy\AlertManager\Alerts\Alert;
use SeanKndy\AlertManager\Alerts\Queue;
use SeanKndy\AlertManager\Routing\RoutableInterface;
use SeanKndy\AlertManager\Auth\AuthorizerInterface;
use SeanKndy\AlertManager\Preprocessors\PreprocessorInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\LoopInterface;
use React\Http\Response as HttpResponse;
use React\Http\Server as HttpServer;
use React\Socket\Server as SocketServer;
use React\Promise\PromiseInterface;
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
     * @var \SplObjectStorage
     */
    private $preprocessors;
    /**
     * Used to verify user access to API
     * @var AuthorizerInterface
     */
    private $authorizer = null;
    /**
     * @var \FastRoute\Dispatcher
     */
    private $httpDispatcher;
    /**
     * @var bool
     */
    private $quiesce = false;

    public function __construct(string $listen, LoopInterface $loop,
        RoutableInterface $router, AuthorizerInterface $authorizer = null)
    {
        $this->loop = $loop;
        $this->router = $router;
        $this->queue = new Queue();
        $this->preprocessors = new \SplObjectStorage();
        $this->authorizer = $authorizer;

        $this->httpServer = new HttpServer(function (ServerRequestInterface $request) {
            return $this->handleRequest($request);
        });
        $this->httpServer->on('error', function ($e) {
            $this->emit('error', [$e]);
        });
        $socket = new SocketServer($listen, $this->loop);
        $this->httpServer->listen($socket);

        $alertsApi = new Api\V1\Alerts($this);
        $this->httpDispatcher = \FastRoute\simpleDispatcher(
            function(\FastRoute\RouteCollector $r) use ($alertsApi) {
                $r->addGroup('/api/v1', function (\FastRoute\RouteCollector $r) use ($alertsApi) {
                    $r->addRoute('GET', '/alerts', [$alertsApi, 'get']);
                    $r->addRoute('POST', '/alerts', [$alertsApi, 'create']);
                    $r->addRoute('POST', '/alerts/quiesce', [$alertsApi, 'quiesce']);
                });
            }
        );

        $this->loop->futureTick(function() {
            $this->processQueue();
        });
    }

    /**
     * Handle incoming http request.
     *
     * @param ServerRequestInterface $request The incoming request
     *
     * @return HttpResponse|PromiseInterface<HttpResponse>
     */
    private function handleRequest(ServerRequestInterface $request)
    {
        $routeInfo = $this->httpDispatcher->dispatch(
            $request->getMethod(), $request->getUri()->getPath()
        );

        switch ($routeInfo[0]) {
            case \FastRoute\Dispatcher::NOT_FOUND:
                return new HttpResponse(
                    404,
                    ['Content-Type' => 'application/json'],
                    \json_encode(['status' => 'error'])
                );
            case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                return new HttpResponse(
                    405,
                    ['Content-Type' => 'application/json'],
                    \json_encode(['status' => 'error'])
                );
            case \FastRoute\Dispatcher::FOUND:
                if ($this->authorizer) {
                    $authPromise = $this->authorizer->authorize($request);
                } else {
                    $authPromise = \React\Promise\resolve(true);
                }
                return $authPromise->then(function (bool $authenticated) use ($request, $routeInfo) {
                    if (!$authenticated) {
                        return new HttpResponse(
                            401,
                            ['Content-Type' => 'application/json'],
                            \json_encode(['status' => 'error'])
                        );
                    }
                    $vars = \array_merge([$request], $routeInfo[2]);
                    $result = \call_user_func_array($routeInfo[1], $vars);

                    if ($result instanceof PromiseInterface) {
                        return $result->done(function($response) {
                            return $response;
                        }, function ($e) {
                            return new HttpResponse(
                                500,
                                ['Content-Type' => 'application/json'],
                                \json_encode(['status' => 'error'])
                            );
                        });
                    } else if ($result instanceof HttpResponse) {
                        return $result;
                    } else {
                        return new HttpResponse(
                            500,
                            ['Content-Type' => 'application/json'],
                            \json_encode(['status' => 'error'])
                        );
                    }
                }, function (\Exception $e) { // error during authorization
                    return new HttpResponse(
                        500,
                        ['Content-Type' => 'application/json'],
                        \json_encode(['status' => 'error'])
                    );
                });
        }
    }

    /**
     * Process $this->queue() ; route alerts/delete expired alerts
     *
     * @return void
     */
    private function processQueue()
    {
        $this->queue->settle();

        $promises = [];
        foreach ($this->queue as $alert) {
            if ($alert->isInactive()) {
                continue; // skip inactive alerts, then delete them below.
            }

            if (!$alert->isRecovered() && $alert->hasExpired()) {
                // expire alert
                $alert->setState(Alert::RECOVERED);
                $this->emit('alert.expired', [$alert]);
            }
            if (!$this->quiesce && $promise = $this->router->route($alert)) {
                $promises[] = $promise;
            }
        }

        \React\Promise\all($promises)->otherwise(function (\Throwable $e) {
            $this->emit('error', [$e]);
        })->always(function() {
            // remove recovered or inactive alerts
            foreach ($this->queue as $key => $alert) {
                if ($alert->isInactive() || $alert->isRecovered()) {
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

    /**
     * Set AuthorizerInterface
     *
     * @param AuthorizerInterface $authorizer
     *
     * @return self
     */
    public function setAuthorizer(AuthorizerInterface $authorizer)
    {
        $this->authorizer = $authorizer;

        return $this;
    }

    /**
     * Quiet the server from routing any alerts for $duration seconds
     *
     * @param int $duration
     *
     * @return bool
     */
    public function startQuiesce(int $duration)
    {
        if ($this->quiesce) {
            return false;
        }

        $this->quiesce = true;
        $this->emit('quiesce.start', [$duration]);

        $this->loop->addTimer($duration, function() {
            $this->emit('quiesce.end', []);
            $this->quiesce = false;
        });

        return true;
    }

    /**
     * Queue an alert
     *
     * @param Alert $alert Alert to queue
     * @return PromiseInterface
     */
    public function queueAlert(Alert $alert)
    {
        $this->emit('alert', [$alert]);

        $this->runPreprocessors($alert)->always(function() use ($alert) {
            $this->queue->enqueue($alert);
        });
    }

    /**
     * Get queued alerts as array
     *
     * @return array
     */
    public function getQueuedAlerts()
    {
        return \iterator_to_array($this->queue, false);
    }

    /**
     * Push a pre-processor
     *
     * @param PreprocessorInterface $preprocessor
     * @return self
     */
    public function pushPreprocessor(PreprocessorInterface $preprocessor)
    {
        $this->preprocessor->attach($preprocessor);

        return $this;
    }

    /**
     * Remove a pre-processor
     *
     * @param PreprocessorInterface $preprocessor
     * @return self
     */
    public function removePreprocessor(PreprocessorInterface $preprocessor)
    {
        $this->preprocessor->detach($preprocessor);

        return $this;
    }

    /**
     * Run pre-processors on Alert
     *
     * @param Alert $alert Alert to run preprocessors on
     * @return PromiseInterface
     */
    private function runPreprocessors(Alert $alert)
    {
        $preprocessors = \iterator_to_array($this->preprocessors, false);

        // run process() calls in order and in sequence, unless one of them
        // rejects in which case preprocessors succeeding the failed one
        // will not run.
        return \array_reduce(
            $preprocessors,
            function ($prev, $cur) use ($alert) {
                return $prev->then(
                    function () use ($cur, $alert) {
                        return $cur->process(
                            $alert
                        )->otherwise(function (\Throwable $e) {
                            $this->emit('error', [$e]);
                        });
                    },
                    function ($e) {
                        return \React\Promise\reject($e);
                    }
                );
            },
            \React\Promise\resolve([])
        );
    }
}
