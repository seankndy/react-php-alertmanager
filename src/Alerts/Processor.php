<?php

namespace SeanKndy\AlertManager\Alerts;

use SeanKndy\AlertManager\Routing\RoutableInterface;
use SeanKndy\AlertManager\Preprocessors\PreprocessorInterface;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use Evenement\EventEmitter;

final class Processor extends EventEmitter implements \Iterator, \Countable
{
    private LoopInterface $loop;

    /**
     * @var Alert[]
     */
    private array $alerts;

    private RoutableInterface $router;

    private \SplObjectStorage $preprocessors;

    private bool $quiesce = false;

    public function __construct(LoopInterface $loop, RoutableInterface $router)
    {
        $this->loop = $loop;
        $this->router = $router;
        $this->alerts = [];
        $this->preprocessors = new \SplObjectStorage();

        $this->loop->futureTick(function() {
            $this->process();
        });
    }

    public function add(Alert $alert): PromiseInterface
    {
        return $this->runPreprocessors($alert)->always(function() use ($alert) {
            if (isset($this->alerts[$alert->getName()])) {
                $this->emit('alert.updated', [$this->alerts[$alert->getName()], $alert]);
                $this->alerts[$alert->getName()]->updateFromAlert($alert);
            } else {
                $this->emit('alert.new', [$alert]);
                $this->alerts[$alert->getName()] = $alert;
            }
        });
    }

    /**
     * Process alerts; route alerts/delete expired alerts
     */
    private function process(): void
    {
        $promises = [];
        foreach ($this->alerts as $alert) {
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
            foreach ($this->alerts as $name => $alert) {
                if ($alert->isInactive() || $alert->isRecovered()) {
                    $this->emit('alert.deleted', [$alert]);
                    unset($this->alerts[$name]);
                }
            }
            // process queue again
            $this->loop->addTimer(1.0, function() {
                $this->process();
            });
        });
    }

    /**
     * Quiet the system from routing any alerts for $duration seconds.
     */
    public function quiesce(int $duration): bool
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

    public function pushPreprocessor(PreprocessorInterface $preprocessor): self
    {
        $this->preprocessors->attach($preprocessor);

        return $this;
    }

    public function removePreprocessor(PreprocessorInterface $preprocessor): self
    {
        $this->preprocessors->detach($preprocessor);

        return $this;
    }

    private function runPreprocessors(Alert $alert): PromiseInterface
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

    public function current()
    {
        return current($this->alerts);
    }

    public function next()
    {
        return next($this->alerts);
    }

    public function key()
    {
        return key($this->alerts);
    }

    public function valid(): bool
    {
        return key($this->alerts) !== null;
    }

    public function rewind()
    {
        return reset($this->alerts);
    }

    public function count(): int
    {
        return count($this->alerts);
    }
}
