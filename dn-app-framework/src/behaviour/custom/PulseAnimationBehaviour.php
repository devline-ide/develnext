<?php
namespace behaviour\custom;

use action\Animation;
use php\gui\framework\behaviour\custom\AnimationBehaviour;
use php\gui\framework\ScriptEvent;
use php\gui\UXNode;
use php\gui\UXWindow;
use script\TimerScript;
use timer\AccurateTimer;

/**
 * Class PulseAnimationBehaviour
 * @package behaviour\custom
 *
 * @packages framework
 */
class PulseAnimationBehaviour extends AnimationBehaviour
{
    /**
     * @var bool
     */
    public $animated = true;

    /**
     * @var float
     */
    public $scale = 1.2;

    protected $_instantOut = false;

    public function __clone()
    {
        parent::__clone();
        $this->_instantOut = false;
    }

    /**
     * @param mixed $target
     */
    protected function applyImpl($target)
    {
        if (!($target instanceof UXNode)) {
            return;
        }

        if ($this->animated) {
            $this->_scaleInCallback();
        } else {
            $this->accurateTimer($this->duration, function ($timer) use ($target) {
                $timer->interval = $this->duration;

                if ($this->enabled || $this->_instantOut) {
                    if (!$this->_instantOut && !$this->checkRepeatLimits()) return true;
                    if ($this->_instantOut) {
                        $target->scaleX = $target->scaleY = 1.0;
                    } else {
                        $target->scaleX = $target->scaleY = $this->scale;
                    }
                    $this->_instantOut = !$this->_instantOut;
                }
            });
        }
    }

    protected function _scaleOutCallback()
    {
        $this->animate('scaleTo', $this->_target, $this->duration, 1.0, function () {
            $this->_scaleInCallback();
        });
    }

    protected function _scaleInCallback()
    {
        if ($this->enabled) {
            if (!$this->checkRepeatLimits()) return;
            $this->animate('scaleTo', $this->_target, $this->duration, $this->scale, function () {
                $this->_scaleOutCallback();
            });
        } else {
            $this->later($this->duration, function () {
                $this->_scaleInCallback();
            });
        }
    }

    public function getCode()
    {
        return 'pulseAnim';
    }
}