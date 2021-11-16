<?php
namespace SeanKndy\AlertManager\Alerts;

use Carbon\Carbon;
use React\Promise\PromiseInterface;
use SeanKndy\AlertManager\Receivers\ReceivableInterface;

class Alert
{
    const ACTIVE = 'ACTIVE';
    const INACTIVE = 'INACTIVE'; // aka "deleted"
    const RECOVERED = 'RECOVERED';
    const ACKNOWLEDGED = 'ACKNOWLEDGED';

    /**
     * State: either ACTIVE, INACTIVE, RECOVERED or ACKNOWLEDGED
     * @var string
     */
    protected $state;
    /**
     * Serves as unique identifier for the Alert.
     * @var string
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
    private $dispatchLog;

    public function __construct(
        string $name,
        string $state,
        array $attributes = [],
        int $createdAt = 0,
        int $expiryDuration = 600
    ) {
        $this->name = $name;
        $this->setState($state);
        $this->attributes = $attributes;
        $this->createdAt = $createdAt ? $createdAt : Carbon::now()->timestamp;
        $this->updatedAt = Carbon::now()->timestamp;
        $this->expiryDuration = $expiryDuration;
        $this->dispatchLog = new \SplObjectStorage();
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
        $this->logDispatch($receiver);
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
        if (!\in_array($state, [self::ACTIVE, self::INACTIVE, self::RECOVERED, self::ACKNOWLEDGED])) {
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
     * Get the dispatch log
     *
     * @return \SplObjectStorage
     */
    public function getDispatchLog()
    {
        return $this->dispatchLog;
    }

    /**
     * Set the value of dispatchLog
     *
     * @param \SplObjectStorage $log
     *
     * @return self
     */
    public function setDispatchLog(\SplObjectStorage $log)
    {
        $this->dispatchLog = $log;

        return $this;
    }

    /**
     * Log a dispatch to receiver
     *
     * @param ReceivableInterface $receiver Receiver dispatched to
     * @param string $forState State of Alert when dispatched
     *
     * @return self
     */
    public function logDispatch(ReceivableInterface $receiver, string $forState = null)
    {
        if (!$forState) {
            $forState = $this->state;
        }

        if (isset($this->dispatchLog[$receiver])) {
            $log = $this->dispatchLog[$receiver];
        } else {
            $log = [];
        }
        $log[$forState] = Carbon::now()->timestamp;

        $this->dispatchLog[$receiver] = $log;

        return $this;
    }

    /**
     * Get the dispatch log for a receiver
     *
     * @param ReceivableInterface $receiver
     *
     * @return array
     */
    public function getDispatchLogForReceiver(ReceivableInterface $receiver)
    {
        if (isset($this->dispatchLog[$receiver])) {
            return $this->dispatchLog[$receiver];
        }
        return [];
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
     * Helper to determine if state == ACKNOWLEDGED
     *
     * @return bool
     */
    public function isAcknowledged()
    {
        return $this->state === self::ACKNOWLEDGED;
    }

    /**
     * Helper to determine if state == INACTIVE
     *
     * @return bool
     */
    public function isInactive()
    {
        return $this->state === self::INACTIVE;
    }

    /**
     * Has alert expired?
     *
     * @return bool
     */
    public function hasExpired()
    {
        return Carbon::now()->timestamp - $this->updatedAt >= $this->expiryDuration;
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
                $a->createdAt = Carbon::now()->timestamp;
            }
            $alerts[] = new self($a->name, $a->state, (array)$a->attributes, $a->createdAt,
                isset($a->expiryDuration) ? $a->expiryDuration : $defaultExpiry);
        }
        return $alerts;
    }

    /**
     * Convert this Alert object to array
     *
     * @return string
     */
    public function toArray()
    {
        $dispatchedTo = [];
        foreach ($this->dispatchLog as $receiver) {
            if ($this->dispatchLog[$receiver][$this->getState()]) {
                $dispatchedTo[] = [
                    'receiverId' => $receiver->receiverId(),
                    'time' => $this->dispatchLog[$receiver][$this->getState()]
                ];
            }
        }
        return [
            'name' => $this->name,
            'expiryDuration' => $this->expiryDuration,
            'state' => $this->state,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'attributes' => $this->attributes,
            'dispatchedTo' => $dispatchedTo
        ];
    }

    /**
     * Convert this Alert object to JSON string
     *
     * @return string
     */
    public function toJSON()
    {
        return \json_encode($this->toArray());
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
        $this->updatedAt = Carbon::now()->timestamp;
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
            'num-dispatched=' . \count($this->dispatchLog);
    }
}
