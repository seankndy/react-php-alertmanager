<?php
namespace SeanKndy\AlertManager\Alerts;

class Queue implements \Iterator, \Countable, \ArrayAccess
{
    /**
     * @var Alert[]
     */
    private $alerts = [];

    public function enqueue(Alert $alert)
    {
        if (isset($this->alerts[$alert->getId()])) {
            $existingAlert = $this->alerts[$alert->getId()];

            // if alert is in same state, copy receiver transactions so
            // we do not incorrectly re-notify receivers
            if ($existingAlert->getState() == $alert->getState()) {
                $alert->setReceiverTransactions($existingAlert->getReceiverTransactions());
            }
        }
        $this->alerts[$alert->getId()] = $alert;
    }

    public function dequeue()
    {
        $alert = $this->current();
        unset($this->alerts[$this->key()]);
        return $alert;
    }

    public function rewind()
    {
        \reset($this->alerts);
    }

    public function current()
    {
        return \current($this->alerts);
    }

    public function key()
    {
        return \key($this->alerts);
    }

    public function next()
    {
        \next($this->alerts);
    }

    public function valid()
    {
        return \key($this->alerts) !== null;
    }

    public function count()
    {
        return \count($this->alerts);
    }

    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->enqueue($value);
        } else {
            $this->alerts[$offset] = $value;
        }
    }

    public function offsetExists($offset)
    {
        return isset($this->alerts[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->alerts[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->alerts[$offset]) ? $this->alerts[$offset] : null;
    }
}
