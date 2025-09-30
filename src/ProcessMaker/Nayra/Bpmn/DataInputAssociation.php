<?php

namespace ProcessMaker\Nayra\Bpmn;

use ProcessMaker\Nayra\Contracts\Bpmn\DataInputAssociationInterface;

/**
 * Transition to check if the activity is a loop not yet completed or a single instance
 */
class DataInputAssociation implements DataInputAssociationInterface
{
    use BaseTrait;

    public function getSources() {}

    public function getTarget()
    {
        return $this->getProperty(static::BPMN_PROPERTY_TARGET_REF);
    }

    public function getTransformation() {}

    public function getAssignments()
    {
        return $this->getProperty(static::BPMN_PROPERTY_ASSIGNMENT);
    }
}
