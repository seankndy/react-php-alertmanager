<?php
namespace SeanKndy\AlertManager\Alerts;

use React\Promise\PromiseInterface;
use SeanKndy\AlertManager\Receivers\ReceivableInterface;

class Alert
{
    const ACTIVE = 'ACTIVE';
    const RECOVERED = 'RECOVERED';
    const DELETED = 'DELETED';

    /**
     * State: either ACTIVE, RECOVERED or DELETED
     * @var int
     */
    protected $state;
    /**
     * Serves as unique identifier for the Alert.
     * @var mixed
     */
    protected $name;
    /**
     * All alert details are arbitrarily stored here.
     * @var array
     */
    protected $attributes = [];
    /**
     * Time of creation.
     * @var int
     */
    protected $createdAt;
    /**
     * Time last updated.
     * @var int
     */
    protected $updatedAt;
    /**
     * This value + $updatedAt = time to mark alert expired
     * @var int
     */
    protected $expiryDuration;
    /**
     * Keep history of which Receiver's this Alert has dispatched to.
     * @var \SplObjectStorage
     */
    private $receiverTransactions;

    public function __construct($name, string $state, array $attributes,
        int $createdAt = 0, int $expiryDuration = 600)
    {
        $this->name = $name;
        $this->setState($state);
        $this->attributes = $attributes;
        $this->createdAt = $createdAt ? $createdAt : \time();
        $this->updatedAt = \time();
        $this->expiryDuration = $expiryDuration;
        $this->receiverTransactions = new \SplObjectStorage();
    }

    /**
     * Dispatch this alert to a Receiver
     *
     * @param ReceivableInterface $receiver
     *
     * @return PromiseInterface
     */
    public function dispatch(ReceivableInterface $receiver)
    {
        $this->receiverTransaction($receiver);
        return $receiver->receive($this);
    }

    /**
     * Get the value of name
     *
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set the value of name
     *
     * @param mixed name
     *
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
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
     * Set the value of Update At
     *
     * @param int updatedAt
     *
     * @return self
     */
    public function setUpdatedAt(int $updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Get the value of Updated At
     *
     * @return int
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * Set the value of State
     *
     * @param string $state
     *
     * @return self
     */
    public function setState(string $state)
    {
        if (!\in_array($state, [self::ACTIVE, self::RECOVERED, self::DELETED])) {
            throw new \InvalidArgumentException("Invalid state given: $state");
        }
        $this->state = $state;

        return $this;
    }

    /**
     * Get the value of State
     *
     * @return string
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
    public function receiverTransaction(ReceivableInterface $receiver)
    {
        $this->receiverTransactions[$receiver] = \time();

        return $this;
    }

    /**
     * Get the transaction timestamp for Receiver $receiver
     *
     * @return int|null
     */
    public function getReceiverTransactionTime(ReceivableInterface $receiver)
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
     * Helper to determine if state == DELETED
     *
     * @return bool
     */
    public function isDeleted()
    {
        return $this->state === self::DELETED;
    }

    /**
     * Has alert expired?
     *
     * @return bool
     */
    public function hasExpired()
    {
        return \time() - $this->updatedAt >= $this->expiryDuration;
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
            if (!isset($a->name, $a->attributes)) {
                throw new \RuntimeException("Name and Attributes required.");
            }
            if (!isset($a->state)) {
                $a->state = self::ACTIVE;
            }
            if (!isset($a->createdAt)) {
                $a->createdAt = \time();
            }
            $alerts[] = new self($a->name, $a->state, (array)$a->attributes, $a->createdAt,
                isset($a->expiryDuration) ? $a->expiryDuration : $defaultExpiry);
        }
        return $alerts;
    }

    /**
     * Set the value of Attributes
     *
     * @param array attributes
     *
     * @return self
     */
    public function setAttributes(array $attributes)
    {
        $this->attributes = $attributes;

        return $this;
    }

    /**
     * Get the value of Attributes
     *
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Set the value of Expiry Duration
     *
     * @param int expiryDuration
     *
     * @return self
     */
    public function setExpiryDuration(int $expiryDuration)
    {
        $this->expiryDuration = $expiryDuration;

        return $this;
    }

    /**
     * Get the value of Expiry Duration
     *
     * @return int
     */
    public function getExpiryDuration()
    {
        return $this->expiryDuration;
    }

    /**
     * Update this alert with values from another Alert
     *
     * @return void
     */
    public function updateFromAlert(Alert $alert)
    {
        $this->setState($alert->getState());
        $this->attributes = $alert->getAttributes();
        $this->expiryDuration = $alert->getExpiryDuration();
        $this->updatedAt = \time();
    }

    /**
     * String representation
     *
     * @return string
     */
    public function __toString()
    {
        return 'name='.$this->name.'; ' .
            'state='.$this->state.'; ' .
            'num-attributes='.\count($this->attributes).'; ' .
            'created-at=' . date(DATE_ATOM, $this->createdAt) . '; ' .
            'updated-at=' . date(DATE_ATOM, $this->updatedAt) . '; ' .
            'expiry-duration=' . $this->expiryDuration . 'sec; ' .
            'num-receiver-transactions=' . \count($this->receiverTransactions);
    }
}
