<?php
namespace SeanKndy\AlertManager;

use SeanKndy\AlertManager\Alerts\Alert;
use SeanKndy\AlertManager\Alerts\Queue;
use SeanKndy\AlertManager\Routing\RoutableInterface;
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

    public function __construct(string $listen, LoopInterface $loop,
        RoutableInterface $router)
    {
        $this->loop = $loop;
        $this->router = $router;
        $this->queue = new Queue();

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
        // only accept POST
        if ($request->getMethod() !== 'POST') {
            return new HttpResponse(
                405,
                ['Content-Type' => 'application/json'],
                \json_encode(['status' => 'error'])
            );
        }

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

        // must have valid authorization key
        // should fire off code to verify $request->getHeaderLine('Authorization'));

        // build Alerts from request body
        try {
            $alerts = Alert::fromJSON((string)$request->getBody(), $this->defaultExpiryDuration);
            $this->emit('alert', [$alert]);
        } catch (\Throwable $e) {
            return new HttpResponse(
                400,
                ['Content-Type' => 'application/json'],
                \json_encode(['status' => 'error'])
            );
        }

        // queue alerts
        foreach ($alerts as $alert) {
            $this->queue->enqueue($alert);
        }

        // return positivity
        return new HttpResponse(
            201,
            ['Content-Type' => 'application/json'],
            \json_encode(['status' => 'success'])
        );
    }

    private function processQueue()
    {
        $this->queue->settle();

        $promises = [];
        echo "queue has " . \count($this->queue) . " entries\n";
        foreach ($this->queue as $alert) {
            if ($alert->isActive() && $alert->hasExpired()) {
                // expire alert
                $alert->setState(Alert::RECOVERED);
            }
            if (!$alert->isDeleted()) {
                if ($promise = $this->router->route($alert)) {
                    $this->emit('routed', [$alert]);
                    $promises[] = $promise;
                }
            }
        }

        \React\Promise\all($promises)->always(function() {
            // remove deleted and recovered alerts
            foreach ($this->queue as $key => $alert) {
                if (!$alert->isActive()) {
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
}
