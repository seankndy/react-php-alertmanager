<?php
namespace SeanKndy\AlertManager\Alerts;

use React\Promise\PromiseInterface;
use SeanKndy\AlertManager\Receivers\AbstractReceiver;

class Alert
{
    const ACTIVE = 'ACTIVE';
    const RECOVERED = 'RECOVERED';
    const DELETED = 'DELETED';

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
    protected $attributes = [];
    /**
     * @var int
     */
    protected $createdAt;
    /**
     * @var int
     */
    protected $updatedAt;
    /**
     * This value + $updatedAt = time to mark alert expired
     * @var int
     */
    protected $expiryDuration;
    /**
     * @var \SplObjectStorage
     */
    private $receiverTransactions;

    public function __construct($id, string $state, array $attributes,
        int $createdAt = 0, int $expiryDuration = 600)
    {
        $this->id = $id;
        $this->state = $state;
        $this->attributes = $attributes;
        $this->createdAt = $createdAt ? $createdAt : \time();
        $this->updatedAt = $this->createdAt;
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
            if (!isset($a->id, $a->attributes)) {
                throw new \RuntimeException("ID and Attributes required.");
            }
            if (!isset($a->state)) {
                $a->state = self::ACTIVE;
            }
            if (!isset($a->createdAt)) {
                $a->createdAt = \time();
            }
            $alerts[] = new self($a->id, $a->state, (array)$a->attributes, $a->createdAt,
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
        $this->state = $alert->getState();
        $this->attributes = $alert->getAttributes();
        $this->expiryDuration = $alert->getExpiryDuration();
        $this->updatedAt = \time();
    }
}
