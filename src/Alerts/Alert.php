<?php
namespace SeanKndy\AlertManager\Alerts;

use React\Promise\PromiseInterface;
use SeanKndy\AlertManager\Receivers\AbstractReceiver;

class Alert
{
    const ACTIVE = 1;
    const RECOVERED = 2;

    /**
     * @var int
     */
    protected $state;
    /**
     * @var mixed
     */
    protected $id;
    /**
     * @var array
     */
    protected $labels = [];
    /**
     * @var array
     */
    protected $annotations = [];
    /**
     * @var int
     */
    protected $createdAt;
    /**
     * @var int
     */
    protected $expiryDuration;
    /**
     * @var \SplObjectStorage
     */
    private $receiverTransactions;

    public function __construct($id, int $state, array $labels, array $annotations = [],
        int $createdAt = 0, int $expiryDuration = 600)
    {
        $this->id = $id;
        $this->state = $state;
        $this->labels = $labels;
        $this->annotations = $annotations;
        $this->createdAt = $createdAt ? $createdAt : \time();
        $this->expiryDuration = $expiryDuration;
        $this->receiverTransactions = new \SplObjectStorage();
    }

    /**
     * Dispatch this alert to a Receiver
     *
     * @param AbstractReceiver $receiver
     *
     * @return PromiseInterface
     */
    public function dispatch(AbstractReceiver $receiver)
    {
        $this->receiverTransaction($receiver);
        return $receiver->receive($this);
    }

    /**
     * Get the value of Id
     *
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set the value of Id
     *
     * @param mixed id
     *
     * @return self
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Set the value of Labels
     *
     * @param array labels
     *
     * @return self
     */
    public function setLabels(array $labels)
    {
        $this->labels = $labels;

        return $this;
    }

    /**
     * Get the value of Labels
     *
     * @return array
     */
    public function getLabels()
    {
        return $this->labels;
    }

    /**
     * Set the value of Annotations
     *
     * @param array annotations
     *
     * @return self
     */
    public function setAnnotations(array $annotations)
    {
        $this->annotations = $annotations;

        return $this;
    }

    /**
     * Get the value of Annotations
     *
     * @return array
     */
    public function getAnnotations()
    {
        return $this->annotations;
    }

    /**
     * Set the value of Created At
     *
     * @param int createdAt
     *
     * @return self
     */
    public function setCreatedAt(int $createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get the value of Created At
     *
     * @return int
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set the value of State
     *
     * @param int state
     *
     * @return self
     */
    public function setState(int $state)
    {
        $this->state = $state;

        return $this;
    }

    /**
     * Get the value of State
     *
     * @return int
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * Get the value of Receiver Transactions
     *
     * @return \SplObjectStorage
     */
    public function getReceiverTransactions()
    {
        return $this->receiverTransactions;
    }

    /**
     * Set the value of Receiver Transactions
     *
     * @param \SplObjectStorage receiverTransactions
     *
     * @return self
     */
    public function setReceiverTransactions(\SplObjectStorage $receiverTransactions)
    {
        $this->receiverTransactions = $receiverTransactions;

        return $this;
    }

    /**
     * Add/set Receiver transaction
     *
     * @return self
     */
    public function receiverTransaction(AbstractReceiver $receiver)
    {
        $this->receiverTransactions[$receiver] = \time();

        return $this;
    }

    /**
     * Get the transaction timestamp for Receiver $receiver
     *
     * @return int|null
     */
    public function getReceiverTransactionTime(AbstractReceiver $receiver)
    {
        if (isset($this->receiverTransactions[$receiver])) {
            return $this->receiverTransactions[$receiver];
        }
        return null;
    }

    /**
     * Helper to determine if state == ACTIVE
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->state === self::ACTIVE;
    }

    /**
     * Helper to determine if state == RECOVERED
     *
     * @return bool
     */
    public function isRecovered()
    {
        return $this->state === self::RECOVERED;
    }

    /**
     * Has alert expired?
     *
     * @return bool
     */
    public function hasExpired()
    {
        return \time() - $this->createdAt >= $this->expiryDuration;
    }

    /**
     * Build Alert objects from JSON
     *
     * @throws RuntimeException
     * @return Alert
     */
    public static function fromJSON(string $jsonString, int $defaultExpiry = 600)
    {
        $json = \json_decode($jsonString);
        if (!$json) {
            throw new \RuntimeException("Failed to parse JSON string");
        }
        if (!\is_array($json)) {
            $json = [$json];
        }
        foreach ($json as $a) {
            if (!isset($a->id, $a->labels)) {
                throw new \RuntimeException("ID and Labels required.");
            }
            if (!isset($a->state)) {
                $a->state = Alert::ACTIVE;
            }
            if (!isset($a->annotations)) {
                $a->annotations = [];
            }
            if (!isset($a->createdAt)) {
                $a->createdAt = \time();
            }

            $alerts[] = new self($a->id, $a->state, (array)$a->labels, (array)$a->annotations,
                $a->createdAt, isset($a->expiryDuration) ? $a->expiryDuration : $defaultExpiry);
        }
        return $alerts;
    }

}
