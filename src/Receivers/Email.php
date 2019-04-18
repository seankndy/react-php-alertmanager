<?php
namespace SeanKndy\AlertManager\Receivers;

use SeanKndy\AlertManager\Alerts\Alert;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use React\ChildProcess\Process;
use Shuchkin\ReactSMTP\Client as SmtpClient;

class Email extends AbstractReceiver
{
    /**
     * @var LoopInterface
     */
    protected $loop;
    /**
     * @var string
     */
    protected $emailAddress;
    /**
     * @var array
     */
    protected $config;
    /**
     * @var
     */
    protected $smtpClient;
    /**
     * @var string
     */
    protected $subjectTemplate;
    /**
     * @var string
     */
    protected $messageTemplate;

    public function __construct(LoopInterface $loop, string $emailAddress, array $config)
    {
        $this->loop = $loop;
        $this->emailAddress = $emailAddress;
        $this->config = \array_merge([
            'server' => 'localhost',
            'port' => 25,
            'from' => 'no-reply@localhost.localdomain',
            'username' => '',
            'password' => ''
        ], $config);
    }

    /**
     * {@inheritDoc}
     */
    public function receive(Alert $alert) : PromiseInterface
    {
        echo "firing email to {$this->emailAddress}\n";
        $env = $this->config;
        $env['to'] = $this->emailAddress;
        $env['subject'] = $this->interpolate($alert->getAttributes(), $this->subjectTemplate);
        $env['message'] = $this->interpolate($alert->getAttributes(), $this->messageTemplate);

        $process = new Process('php '.__DIR__.'/../../bin/send-email.php', null, $env);
        $process->start($this->loop);
        $deferred = new \React\Promise\Deferred();
        $process->stdout->on('data', function ($chunk) {
            echo $chunk;
        });
        $process->stderr->on('data', function ($chunk) {
            echo $chunk;
        });
        $process->on('exit', function($exitCode, $termSignal) use ($deferred) {
            $deferred->resolve($exitCode);
        });
        return $deferred->promise();
    }

    /**
     * Interpolate values from $vars [variable=>value] into $str
     * where $str uses %foo% for variable named 'foo' from $vars.
     * Case insensitive.
     *
     * @var array $vars Array of variable=>value pairs used as interpolation source
     * @var string $str Template string using variables from $vars as %var%
     *
     * @return string The interpolated string
     */
    private function interpolate(array $vars, string $str)
    {
        return \str_ireplace(
            \array_map(function ($var) {
                return "%$var%";
            }, \array_keys($vars)),
            \array_values($vars),
            $str
        );
    }
}
