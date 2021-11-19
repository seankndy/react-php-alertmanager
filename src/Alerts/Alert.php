<?php
namespace SeanKndy\AlertManager\Alerts;

use Carbon\Carbon;
use React\Promise\PromiseInterface;
use SeanKndy\AlertManager\Receivers\ReceivableInterface;

class Alert
{
    public static int $defaultExpiryDuration = 600;

    const ACTIVE = 'ACTIVE';
    const INACTIVE = 'INACTIVE'; // aka "deleted"
    const RECOVERED = 'RECOVERED';
    const ACKNOWLEDGED = 'ACKNOWLEDGED';

    /**
     * State: either ACTIVE, INACTIVE, RECOVERED or ACKNOWLEDGED
     */
    protected string $state;
    /**
     * Serves as unique identifier for the Alert.
     */
    protected string $name;
    /**
     * All alert details are arbitrarily stored here.
     */
    protected array $attributes = [];
    /**
     * Time of creation.
     */
    protected int $createdAt;
    /**
     * Time last updated.
     */
    protected int $updatedAt;
    /**
     * This value + $updatedAt = time to mark alert expired
     */
    protected int $expiryDuration;
    /**
     * Keep history of which Receiver's this Alert has dispatched to.
     */
    private \SplObjectStorage $dispatchLog;

    public function __construct(
        string $name,
        string $state,
        array $attributes = [],
        int $createdAt = 0,
        int $expiryDuration = null
    ) {
        $this->name = $name;
        $this->setState($state);
        $this->attributes = $attributes;
        $this->createdAt = $createdAt ?: Carbon::now()->timestamp;
        $this->updatedAt = Carbon::now()->timestamp;
        $this->expiryDuration = $expiryDuration === null ? self::$defaultExpiryDuration : $expiryDuration;
        $this->dispatchLog = new \SplObjectStorage();
    }

    /**
     * Dispatch this alert to a Receiver
     */
    public function dispatch(ReceivableInterface $receiver): PromiseInterface
    {
        $this->logDispatch($receiver);
        return $receiver->receive($this);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName($name): self
    {
        $this->name = $name;

        return $this;
    }

    public function setCreatedAt(int $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function setUpdatedAt(int $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getUpdatedAt(): int
    {
        return $this->updatedAt;
    }

    public function setState(string $state): self
    {
        if (!\in_array($state, [self::ACTIVE, self::INACTIVE, self::RECOVERED, self::ACKNOWLEDGED])) {
            throw new \InvalidArgumentException("Invalid state given: $state");
        }
        $this->state = $state;

        return $this;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getDispatchLog(): \SplObjectStorage
    {
        return $this->dispatchLog;
    }

    public function setDispatchLog(\SplObjectStorage $log): self
    {
        $this->dispatchLog = $log;

        return $this;
    }

    /**
     * Log a dispatch to receiver
     *
     * @param ReceivableInterface $receiver Receiver dispatched to
     * @param string|null $forState State of Alert when dispatchedf
     */
    public function logDispatch(ReceivableInterface $receiver, string $forState = null): self
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
     */
    public function getDispatchLogForReceiver(ReceivableInterface $receiver): array
    {
        if (isset($this->dispatchLog[$receiver])) {
            return $this->dispatchLog[$receiver];
        }
        return [];
    }

    /**
     * Helper to determine if state == ACTIVE
     */
    public function isActive(): bool
    {
        return $this->state === self::ACTIVE;
    }

    /**
     * Helper to determine if state == RECOVERED
     */
    public function isRecovered(): bool
    {
        return $this->state === self::RECOVERED;
    }

    /**
     * Helper to determine if state == ACKNOWLEDGED
     */
    public function isAcknowledged(): bool
    {
        return $this->state === self::ACKNOWLEDGED;
    }

    /**
     * Helper to determine if state == INACTIVE
     */
    public function isInactive(): bool
    {
        return $this->state === self::INACTIVE;
    }

    public function hasExpired(): bool
    {
        return Carbon::now()->timestamp - $this->updatedAt >= $this->expiryDuration;
    }

    /**
     * Build Alert objects from JSON
     *
     * @throws \RuntimeException
     * @return Alert[]
     */
    public static function fromJSON(string $jsonString): array
    {
        $json = \json_decode($jsonString);
        if (!$json) {
            throw new \RuntimeException("Failed to parse JSON string");
        }
        if (!\is_array($json)) {
            $json = [$json];
        }

        $alerts = [];
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

            $alerts[] = new self(
                $a->name,
                $a->state,
                (array)$a->attributes,
                $a->createdAt,
                $a->expiryDuration ?? self::$defaultExpiryDuration
            );
        }
        return $alerts;
    }

    /**
     * Convert this Alert object to array
     */
    public function toArray(): array
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
     */
    public function toJSON(): string
    {
        return \json_encode($this->toArray());
    }

    /**
     * Set the value of Attributes
     */
    public function setAttributes(array $attributes): self
    {
        $this->attributes = $attributes;

        return $this;
    }

    /**
     * Get the value of Attributes
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Set the value of Expiry Duration
     */
    public function setExpiryDuration(int $expiryDuration): self
    {
        $this->expiryDuration = $expiryDuration;

        return $this;
    }

    /**
     * Get the value of Expiry Duration
     */
    public function getExpiryDuration(): int
    {
        return $this->expiryDuration;
    }

    /**
     * Update this alert with values from another Alert
     */
    public function updateFromAlert(Alert $alert): void
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
