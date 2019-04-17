<?php
namespace SeanKndy\AlertManager\Receivers;

use SeanKndy\AlertManager\Alerts\Alert;
use React\Promise\PromiseInterface;

class Email extends AbstractReceiver
{
    /**
     * @var string
     */
    protected $emailAddress;
    /**
     * @var string
     */
    protected $subjectTemplate;
    /**
     * @var string
     */
    protected $messageTemplate;

    public function __construct(string $emailAddress)
    {
        $this->emailAddress = $emailAddress;
    }

    /**
     * {@inheritDoc}
     */
    public function receive(Alert $alert) : PromiseInterface
    {
        echo "routing to {$this->emailAddress}!\n";
        $msg = "";
        foreach ($alert->getAttributes() as $key => $label) {
            $msg .= "$key: $label\n";
        }
        //\mail($this->emailAddress, 'alert', $msg);
        return \React\Promise\resolve([]);
    }
}
