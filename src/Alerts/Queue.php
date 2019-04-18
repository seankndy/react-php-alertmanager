<?php
namespace SeanKndy\AlertManager\Alerts;

class Queue implements \Iterator, \Countable, \ArrayAccess
{
    /**
     * @var Alert[]
     */
    private $settled = [];
    /**
     * @var Alert[]
     */
    private $pending = [];

    /**
     * Enqueue Alert to pending queue
     *
     * @return self
     */
    public function enqueue(Alert $alert)
    {
        $this->pending[$alert->getId()] = $alert;

        return $this;
    }

    /**
     * Merge pending alerts ($pending) with the live queue ($settled)
     *
     * @return self
     */
    public function settle()
    {
        foreach ($this->pending as $pendingAlert) {
            if (isset($this->settled[$pendingAlert->getId()])) {
                // alert ID already exists in settled queue, update it
                $existingAlert = $this->settled[$pendingAlert->getId()];
                $existingAlert->updateFromAlert($pendingAlert);
            } else {
                // add alert to settled queue
                $this->settled[$pendingAlert->getId()] = $pendingAlert;
            }
        }

        $this->pending = [];
        return $this;
    }

    public function dequeue()
    {
        $alert = $this->current();
        $this->next();
        return $alert;
    }

    public function rewind()
    {
        \reset($this->settled);
    }

    public function current()
    {
        return \current($this->settled);
    }

    public function key()
    {
        return \key($this->settled);
    }

    public function next()
    {
        \next($this->settled);
    }

    public function valid()
    {
        return \key($this->settled) !== null;
    }

    public function count()
    {
        return \count($this->settled);
    }

    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->enqueue($value);
        } else {
            $this->settled[$offset] = $value;
        }
    }

    public function offsetExists($offset)
    {
        return isset($this->settled[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->settled[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->settled[$offset]) ? $this->settled[$offset] : null;
    }
}
