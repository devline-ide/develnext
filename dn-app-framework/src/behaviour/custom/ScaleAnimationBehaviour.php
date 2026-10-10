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
 * Class ScaleAnimationBehaviour
 * @package behaviour\custom
 *
 * @packages framework
 */
class ScaleAnimationBehaviour extends AnimationBehaviour
{
    /**
     * @var float
     */
    public $scale = 1.2;

    /**
     * @var float
     */
    public $initialScale = 1;

    /**
     * @param mixed $target
     */
    protected function applyImpl($target)
    {
        if (!($target instanceof UXNode) && !($target instanceof UXWindow)) {
            return;
        }

        $this->initialScale = $target->scaleX;

        $this->_scaleInCallback();
    }

    protected $_in = false;
    protected $_out = false;
    protected $_transition;
    protected $_transitionGeneration = 0;

    public function __clone()
    {
        parent::__clone();
        $this->_in = $this->_out = false;
        $this->_transition = null;
        $this->_transitionGeneration = 0;
    }

    protected function _scaleInCallback()
    {
        if ($this->enabled && $this->checkRepeatLimits()) {
            $this->cancelAnimation($this->_transition);
            $generation = ++$this->_transitionGeneration;
            $this->_in = true;
            $this->_out = false;

            $this->_transition = $this->animate('scaleTo', $this->_target, $this->duration, $this->scale, function () use ($generation) {
                if ($generation !== $this->_transitionGeneration) return;
                $this->_transition = null;
                $this->_in = false;
            });
        }
    }

    public function enable()
    {
        $wasEnabled = $this->enabled;
        parent::enable();

        if (!$wasEnabled && $this->_target) $this->_scaleInCallback();
    }


    protected function restore()
    {
        parent::restore();

        if (!$this->_out) {
            $this->cancelAnimation($this->_transition);
            $generation = ++$this->_transitionGeneration;
            $this->_in = false;
            $this->_out = true;
            $this->_transition = $this->animate('scaleTo', $this->_target, $this->duration, $this->initialScale, function () use ($generation) {
                if ($generation !== $this->_transitionGeneration) return;
                $this->_transition = null;
                $this->_out = false;
            });
        }
    }

    public function getCode()
    {
        return 'scaleAnim';
    }
}