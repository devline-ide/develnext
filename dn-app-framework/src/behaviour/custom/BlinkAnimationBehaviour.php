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
 * Class BlinkAnimationBehaviour
 * @package behaviour\custom
 *
 * @packages framework
 */
class BlinkAnimationBehaviour extends AnimationBehaviour
{
    /**
     * @var bool
     */
    public $animated = true;

    /**
     * @var float
     */
    public $minOpacity = 0.3;

    /**
     * @var float
     */
    public $maxOpacity = 1.0;

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
        if (!($target instanceof UXNode) && !($target instanceof UXWindow)) {
            return;
        }

        if ($this->animated) {
            $this->_fadeInCallback();
        } else {
            $this->accurateTimer($this->duration, function ($timer) use ($target) {
                $timer->interval = $this->duration;

                if ($this->enabled || $this->_instantOut) {
                    if (!$this->_instantOut && !$this->checkRepeatLimits()) return true;
                    if ($this->minOpacity <= 0.0000001) {
                        $target->visible = $this->_instantOut;
                    } else {
                        $target->visible = true;

                        if (!$this->_instantOut) {
                            $target->opacity = $this->minOpacity;
                        } else {
                            $target->opacity = $this->maxOpacity;
                        }
                    }
                    $this->_instantOut = !$this->_instantOut;
                }
            });
        }
    }

    protected function _fadeOutCallback()
    {
        $this->animate('fadeTo', $this->_target, $this->duration, $this->maxOpacity, function () {
            $this->_fadeInCallback();
        });
    }

    protected function _fadeInCallback()
    {
        if ($this->enabled) {
            if (!$this->checkRepeatLimits()) return;
            $this->animate('fadeTo', $this->_target, $this->duration, $this->minOpacity, function () {
                $this->_fadeOutCallback();
            });
        } else {
            $this->later($this->duration, function () {
                $this->_fadeInCallback();
            });
        }
    }

    public function getCode()
    {
        return 'blinkAnim';
    }
}