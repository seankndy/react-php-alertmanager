<?php
namespace SeanKndy\AlertManager\Receivers;

use SeanKndy\AlertManager\Alerts\Alert;
use SeanKndy\AlertManager\Support\Traits\ConfigTrait;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use React\ChildProcess\Process;

class Email extends AbstractReceiver
{
    use ConfigTrait;

    protected LoopInterface $loop;

    protected string $emailAddress;

    public function __construct($id, LoopInterface $loop,
        string $emailAddress, array $config)
    {
        parent::__construct($id);

        $this->loop = $loop;
        $this->emailAddress = $emailAddress;

        $this->config = \array_merge([
            'server' => 'localhost',
            'port' => 25,
            'active_from' => 'no-reply@localhost.localdomain',
            'recovery_from' => 'no-reply@localhost.localdomain',
            'username' => '',
            'password' => ''
        ], $config);
    }

    public function receive(Alert $alert): PromiseInterface
    {
        if (!$this->emailAddress || !$this->alertTemplate) {
            return \React\Promise\resolve([]);
        }

        // allow 'recovery_from' and 'active_from' alert attributes to override
        // global config
        $attribs = $alert->getAttributes();
        $recovery_from = $attribs['recovery_from'] ?? $this->config['recovery_from'];
        $active_from = $attribs['active_from'] ?? $this->config['active_from'];

        $env = $this->config;
        $env['from'] = $alert->isRecovered() ? $recovery_from : $active_from;
        $env['to'] = $this->emailAddress;
        $env['subject'] = $this->alertTemplate->brief($alert);
        $env['message'] = $this->alertTemplate->detail($alert);

        $process = new Process('php '.__DIR__.'/../../bin/send-email.php', null, $env);
        $process->start($this->loop);
        $deferred = new \React\Promise\Deferred();
        /*
        $process->stdout->on('data', function ($chunk) {
            echo $chunk;
        });
        $process->stderr->on('data', function ($chunk) {
            echo $chunk;
        });
        */
        $process->on('exit', function($exitCode, $termSignal) use ($deferred) {
            $deferred->resolve($exitCode);
        });
        return $deferred->promise();
    }

    public function setEmailAddress($emailAddress): void
    {
        $this->emailAddress = $emailAddress;
    }

    public function getEmailAddress(): string
    {
        return $this->emailAddress;
    }

    public function __toString(): string
    {
        return parent::__toString() . '; ' .
            'email=' . $this->emailAddress;
    }
}
