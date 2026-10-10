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
 * Class FadeAnimationBehaviour
 * @package behaviour\custom
 *
 * @packages framework
 */
class FadeAnimationBehaviour extends AnimationBehaviour
{
    /**
     * @var float
     */
    public $opacity = 0.3;

    /**
     * @var float
     */
    public $initialOpacity = 1;

    /**
     * @param mixed $target
     */
    protected function applyImpl($target)
    {
        if (!($target instanceof UXNode) && !($target instanceof UXWindow)) {
            return;
        }

        $this->initialOpacity = $target->opacity;

        $this->_fadeInCallback();
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

    protected function _fadeInCallback()
    {
        if ($this->enabled && $this->checkRepeatLimits()) {
            $this->cancelAnimation($this->_transition);
            $generation = ++$this->_transitionGeneration;
            $this->_in = true;
            $this->_out = false;

            $this->_transition = $this->animate('fadeTo', $this->_target, $this->duration, $this->opacity, function () use ($generation) {
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

        if (!$wasEnabled && $this->_target) $this->_fadeInCallback();
    }


    protected function restore()
    {
        parent::restore();

        if (!$this->_out) {
            $this->cancelAnimation($this->_transition);
            $generation = ++$this->_transitionGeneration;
            $this->_in = false;
            $this->_out = true;
            $this->_transition = $this->animate('fadeTo', $this->_target, $this->duration, $this->initialOpacity, function () use ($generation) {
                if ($generation !== $this->_transitionGeneration) return;
                $this->_transition = null;
                $this->_out = false;
            });
        }
    }

    public function getCode()
    {
        return 'fadeAnim';
    }
}