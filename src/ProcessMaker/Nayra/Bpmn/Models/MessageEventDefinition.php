<?php

namespace ProcessMaker\Nayra\Bpmn\Models;

use ProcessMaker\Nayra\Bpmn\EventDefinitionTrait;
use ProcessMaker\Nayra\Contracts\Bpmn\EventDefinitionInterface;
use ProcessMaker\Nayra\Contracts\Bpmn\FlowNodeInterface;
use ProcessMaker\Nayra\Contracts\Bpmn\MessageEventDefinitionInterface;
use ProcessMaker\Nayra\Contracts\Bpmn\MessageInterface;
use ProcessMaker\Nayra\Contracts\Bpmn\OperationInterface;
use ProcessMaker\Nayra\Contracts\Bpmn\ThrowEventInterface;
use ProcessMaker\Nayra\Contracts\Bpmn\TokenInterface;
use ProcessMaker\Nayra\Contracts\Engine\ExecutionInstanceInterface;

/**
 * MessageEventDefinition class
 */
class MessageEventDefinition implements MessageEventDefinitionInterface
{
    use EventDefinitionTrait;

    /**
     * Get the message.
     *
     * @return MessageInterface
     */
    public function getPayload()
    {
        return $this->getProperty(static::BPMN_PROPERTY_MESSAGE);
    }

    /**
     * Sets the message to be used in the message event definition
     *
     * @param MessageInterface $message
     *
     * @return $this
     */
    public function setPayload(MessageInterface $message)
    {
        return $this->setProperty(static::BPMN_PROPERTY_MESSAGE, $message);
    }

    /**
     * Get the Operation that is used by the Message Event.
     *
     * @return OperationInterface
     */
    public function getOperation()
    {
        return $this->getProperty(static::BPMN_PROPERTY_OPERATION);
    }

    /**
     * Sets the operation of the message event definition
     *
     * @param OperationInterface $operation
     * @return $this
     */
    public function setOperation(OperationInterface $operation)
    {
        return $this->setProperty(static::BPMN_PROPERTY_OPERATION, $operation);
    }

    /**
     * Assert the event definition rule for trigger the event.
     *
     * @param EventDefinitionInterface $event
     * @param FlowNodeInterface $target
     * @param ExecutionInstanceInterface|null $instance
     * @param TokenInterface|null $token
     *
     * @return bool
     */
    public function assertsRule(EventDefinitionInterface $event, FlowNodeInterface $target, ExecutionInstanceInterface $instance = null, TokenInterface $token = null)
    {
        return true;
    }

    /**
     * Implement the event definition behavior when an event is triggered.
     *
     * @param EventDefinitionInterface $event
     * @param FlowNodeInterface $target
     * @param ExecutionInstanceInterface|null $instance
     * @param TokenInterface|null $token
     *
     * @return $this
     */
    public function execute(EventDefinitionInterface $event, FlowNodeInterface $target, ExecutionInstanceInterface $instance = null, TokenInterface $token = null)
    {
        $throwEvent = $token->getOwnerElement();
        $this->evaluateMessagePayload($throwEvent, $token, $instance);
        return $this;
    }

    /**
     * Evaluate the message payload
     *
     * @param ThrowEventInterface $throwEvent
     * @param TokenInterface $token
     * @param ExecutionInstanceInterface $targetInstance
     * @return void
     */
    private function evaluateMessagePayload(ThrowEventInterface $throwEvent, TokenInterface $token, ExecutionInstanceInterface $targetInstance)
    {
        // Initialize message payload
        $payload = [];
        $associations = $throwEvent->getDataInputAssociations();
        // Get data from source token instance
        $sourceDataStore = $token->getInstance()->getDataStore();

        // Associate data inputs to message payload
        foreach ($associations as $association) {
            $data = $sourceDataStore->getData();
            $source = $association->getSource();
            $target = $association->getTarget();

            // Add reference to source
            $hasSource = $source && $source->getName();
            $hasTarget = $target && $target->getName();
            $data['sourceRef'] = $hasSource ? $sourceDataStore->getDotData($source->getName()) : null;

            // Apply transformation
            $this->applyTransformation($association, $data, $payload, $hasTarget, $hasSource);

            // Evaluate assignments
            $this->evaluateAssignments($association, $data, $payload);
        }
        // Update data into target $instance
        $dataStore = $targetInstance->getDataStore();
        foreach ($payload as $load) {
            $dataStore->setDotData($load['key'], $load['value']);
        }
    }

    /**
     * Apply transformation to the data and add to payload
     *
     * @param mixed $association
     * @param array $data
     * @param array &$payload
     * @param bool $hasTarget
     * @param bool $hasSource
     */
    private function applyTransformation($association, array $data, array &$payload, bool $hasTarget, bool $hasSource)
    {
        $transformation = $association->getTransformation();
        $target = $association->getTarget();

        if ($hasTarget && $transformation && is_callable($transformation)) {
            $value = $transformation($data);
            $payload[] = ['key' => $target->getName(), 'value' => $value];
        } elseif ($hasTarget && $hasSource) {
            $payload[] = ['key' => $target->getName(), 'value' => $data['sourceRef']];
        }
    }

    /**
     * Evaluate assignments and add to payload
     *
     * @param mixed $association
     * @param array $data
     * @param array &$payload
     */
    private function evaluateAssignments($association, array $data, array &$payload)
    {
        $assignments = $association->getAssignments();
        foreach ($assignments as $assignment) {
            $from = $assignment->getFrom();
            $to = trim($assignment->getTo()?->getBody());
            if (is_callable($from)) {
                $payload[] = ['key' => $to, 'value' => $from($data)];
            }
        }
    }

    /**
     * Check if the $eventDefinition should be catch
     *
     * @param EventDefinitionInterface $eventDefinition
     *
     * @return bool
     */
    public function shouldCatchEventDefinition(EventDefinitionInterface $eventDefinition)
    {
        $targetPayload = $this->getPayload();
        $sourcePayload = $eventDefinition->getPayload();

        return (!$targetPayload && !$sourcePayload)
            || ($targetPayload && $sourcePayload && $targetPayload->getId() === $sourcePayload->getId());
    }
}
