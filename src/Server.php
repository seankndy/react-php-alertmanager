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
     * @var int
     */
    private $defaultExpiryDuration = 600; // 10min
    /**
     * Used to verify user access to API
     * @var AuthorizerInterface
     */
    private $authorizer = null;

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

        $this->loop->futureTick(function() {
            $this->processQueue();
        });
    }

    private function handleRequest(ServerRequestInterface $request)
    {
        // while we don't have any sort of complex api parsing, versioning and
        // routing at this time, i am still going to enforce callers use a path
        // so future non-breakable changes to the API can be made.
        if ($request->getUri()->getPath() != '/api/v1/alerts') {
            return new HttpResponse(
                404,
                ['Content-Type' => 'application/json'],
                \json_encode(['status' => 'error'])
            );
        }

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

            if ($request->getMethod() === 'POST') {
                // build Alerts from request body

                try {
                    $alerts = Alert::fromJSON(
                        (string)$request->getBody(),
                        $this->defaultExpiryDuration
                    );
                } catch (\Throwable $e) {
                    return new HttpResponse(
                        400,
                        ['Content-Type' => 'application/json'],
                        \json_encode(['status' => 'error'])
                    );
                }

                // queue alerts
                foreach ($alerts as $alert) {
                    $this->emit('alert', [$alert]);
                    $this->queue->enqueue($alert);
                }

                // return positivity
                return new HttpResponse(
                    201,
                    ['Content-Type' => 'application/json'],
                    \json_encode(['status' => 'success'])
                );
            } else if ($request->getMethod() === 'GET') {
                // get queued alerts

                $alertArray = [];
                foreach ($this->queue as $alert) {
                    $alertArray[] = $alert->toArray();
                }
                return new HttpResponse(
                    200,
                    ['Content-Type' => 'application/json'],
                    \json_encode([
                        'status' => 'success',
                        'alerts' => $alertArray
                    ])
                );
            } else {
                return new HttpResponse(
                    405,
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

    public function setDefaultExpiryDuration(int $duration)
    {
        $this->defaultExpiryDuration = $duration;

        return $this;
    }

    public function setAuthorizer(AuthorizerInterface $authorizer)
    {
        $this->authorizer = $authorizer;

        return $this;
    }
}
