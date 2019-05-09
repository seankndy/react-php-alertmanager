<?php
namespace SeanKndy\AlertManager\Receivers;

use SeanKndy\AlertManager\Alerts\Alert;
use SeanKndy\AlertManager\Alerts\ThrottledReceiverAlert;
use SeanKndy\AlertManager\Support\Traits\ConfigTrait;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use React\ChildProcess\Process;
use Shuchkin\ReactSMTP\Client as SmtpClient;

class Email extends AbstractReceiver
{
    use ConfigTrait;

    /**
     * @var LoopInterface
     */
    protected $loop;
    /**
     * @var string
     */
    protected $emailAddress;

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
            'password' => '',
            'message_template' => ''
        ], $config);
    }

    /**
     * {@inheritDoc}
     */
    public function receive(Alert $alert) : PromiseInterface
    {
        if (!$this->emailAddress) {
            return \React\Promise\resolve([]);
        }

        if ($alert instanceof ThrottledReceiverAlert) {
            $env = $this->config;
            $env['from'] = $this->config['active_from'];
            $env['to'] = $this->emailAddress;
            $env['subject'] = '';
            $env['message'] = 'Alerts to this receiver have been throttled until ' .
                \date(DATE_ATOM, $alert->getAttributes()['expiresAt']) . '.';
        } else {
            $env = $this->config;
            $env['from'] = $alert->isRecovered() ?
                $this->config['recovery_from'] :
                $this->config['active_from'];
            $env['to'] = $this->emailAddress;
            $env['subject'] = $this->interpolate(
                $alert->getAttributes(), $this->config['subject_template']
            );
            $env['message'] = $this->interpolate(
                $alert->getAttributes(),
                ($alert->isRecovered() ?
                    'RECOVERED from ' . $this->config['message_template'] :
                    $this->config['message_template'])
            );
        }

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

    /**
     * Get the email address
     *
     * @return string
     */
    public function getEmailAddress()
    {
        return $this->emailAddress;
    }

    public function __toString()
    {
        return parent::__toString() . '; ' .
            'email=' . $this->emailAddress;
    }
}
