<?php
namespace behaviour\custom;

use action\Animation;
use php\gui\framework\behaviour\custom\AnimationBehaviour;
use php\gui\framework\ScriptEvent;
use php\gui\UXLabel;
use php\gui\UXLabeled;
use php\gui\UXNode;
use php\lang\IllegalArgumentException;
use php\util\SharedValue;
use script\TimerScript;
use timer\AccurateTimer;

/**
 * Class VibrationAnimationBehaviour
 * @package behaviour\custom
 *
 * @packages framework
 */
class VibrationAnimationBehaviour extends AnimationBehaviour
{
    /**
     * @var int
     */
    public $offsetX = 100;

    /**
     * @var int
     */
    public $offsetY = 0;

    /**
     * @param mixed $target
     * @throws IllegalArgumentException
     */
    protected function applyImpl($target)
    {
        $this->_startAnimation();
    }

    public function _startAnimation()
    {
        if (!$this->enabled) {
            $this->later($this->duration, function () { $this->_startAnimation(); });
            return;
        }
        if (!$this->checkRepeatLimits()) return;
        $this->animate('displace', $this->_target, $this->duration, $this->offsetX, $this->offsetY, function () {
            $func = function () {
                if ($this->enabled) {
                    $this->_reverseAnimation();
                } else {
                    $this->later($this->duration, function () {
                        $this->_reverseAnimation();
                    });
                }
            };

            $this->later($this->delay, $func);
        });
    }

    public function _reverseAnimation()
    {
        $this->animate('displace', $this->_target, $this->duration, - $this->offsetX, - $this->offsetY, function () {
            $func = function () {
                if ($this->enabled) {
                    $this->_startAnimation();
                } else {
                    $this->later($this->duration, function () {
                        $this->_startAnimation();
                    });
                }
            };

            $this->later($this->delay, $func);
        });
    }

    public function getCode()
    {
        return 'vibrationAnim';
    }
}