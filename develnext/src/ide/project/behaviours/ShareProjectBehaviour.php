<?php
namespace ide\project\behaviours;

use ide\project\AbstractProjectBehaviour;

/** Retains the serialized behaviour name for old projects; the retired service is never started. */
class ShareProjectBehaviour extends AbstractProjectBehaviour
{
    public function inject()
    {
    }

    public function getPriority()
    {
        return self::PRIORITY_COMPONENT;
    }
}
