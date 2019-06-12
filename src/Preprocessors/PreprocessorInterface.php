<?php
namespace SeanKndy\AlertManager\Preprocessors;

use React\Promise\PromiseInterface;
use SeanKndy\AlertManager\Alerts\Alert;
/**
 * Preprocessors run prior to an alert being queued and thus allow for global
 * intercept of alerts to mutate them.
 */
interface PreprocessorInterface
{
    /**
     * Process alert $alert async, return promise.
     * Regardless if process() resolves or rejects, $alert is queued.
     *
     * @param Alert $alert Alert to process
     * @return PromiseInterface
     */
    public function process(Alert $alert) : PromiseInterface;
}
