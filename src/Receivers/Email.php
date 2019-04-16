<?php
namespace SeanKndy\Alerter\Receivers;

use SeanKndy\Alerter\Alert;
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

    /**
     * {@inheritDoc}
     */
    public function receive(Alert $alert) : PromiseInterface
    {
        ;
    }
}
