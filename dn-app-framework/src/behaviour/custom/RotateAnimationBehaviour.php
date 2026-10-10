<?php
namespace behaviour\custom;

use action\Animation;
use php\gui\framework\behaviour\custom\AnimationBehaviour;
use php\gui\framework\ScriptEvent;
use php\gui\UXNode;
use script\TimerScript;

/**
 * Class RotateAnimationBehaviour
 * @package behaviour\custom
 *
 * @packages framework
 */
class RotateAnimationBehaviour extends AnimationBehaviour
{
    /**
     * @var bool
     */
    public $negative = false;

    protected $_rotationProgress = 0.0;

    public function __clone()
    {
        parent::__clone();
        $this->_rotationProgress = 0.0;
    }

    /**
     * @param mixed $target
     */
    protected function applyImpl($target)
    {
        if (!($target instanceof UXNode)) {
            return;
        }

        $this->timer(25, function (ScriptEvent $e) use ($target) {
            if ($this->_rotationProgress == 0.0 && !$this->checkRepeatLimits()) {
                $e->sender->stop();
                return;
            }

            $percent = ($e->sender->interval * 100 / $this->duration) / 100;

            $step = min(360 - $this->_rotationProgress, 360 * $percent);
            $target->rotate += $this->negative ? -$step : $step;
            $this->_rotationProgress += $step;
            if ($this->_rotationProgress >= 360) $this->_rotationProgress = 0.0;
        });
    }

    protected function restore()
    {
        $this->_target->rotate = 0;
    }

    public function getCode()
    {
        return 'rotateAnim';
    }
}
