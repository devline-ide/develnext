<?php
namespace php\gui\framework\behaviour\custom;
use php\gui\animation\UXAnimationTimer;
use script\TimerScript;


/**
 * Class AnimationBehaviour
 * @package behaviour\custom
 *
 * @packages framework
 */
abstract class AnimationBehaviour extends AbstractBehaviour
{
    /**
     * @var int
     **/
    public $delay = 0;

    /**
     *
     * @var int
     **/
    public $duration = 1000;

    /**
     * @var string
     */
    public $when = 'ALWAYS';

    /**
     * Сколько раз повторить анимацию, -1 значит бесконечно раз
     * @var int
     **/
    public $repeatCount = -1;

    /**
     * @var UXAnimationTimer[]
     */
    protected $__animTimers = [];
    private $_repeats = 0;

    public function apply($target)
    {
        if ($this->_target) throw new \php\lang\IllegalStateException('This behaviour already used');
        $types = $this->getWhenEventTypes();

        if ($types) {
            $this->prepareTrigger(false);

            $this->bindEvent($target, $types[0], function () {
                $this->updateTrigger(true);
            });

            $this->bindEvent($target, $types[1], function () {
                $this->updateTrigger(false);
            });
        }

        $this->__apply($target);
    }

    protected function applyImplOwned($target)
    {
        if ($this->delay > 0 && $this->when == 'ALWAYS') {
            $this->later($this->delay, function () use ($target) {
                try {
                    BehaviourManager::guiLifecycle('check', $target, $this);
                    $this->applyImpl($target);
                    BehaviourManager::guiLifecycle('check', $target, $this);
                } catch (\Throwable $failure) {
                    try { $this->free(); } catch (\Throwable $cleanup) { }
                    throw $failure;
                }
            });
        } else $this->applyImpl($target);
    }

    public function __apply($target)
    {
        parent::apply($target);
    }

    protected function getWhenEventTypes()
    {
        switch ($this->when) {
            case 'HOVER':
                return ['mouseEnter', 'mouseExit'];
            case 'CLICK':
                return ['mouseDown', 'mouseUp'];
        }

        return null;
    }

    /**
     * @return bool
     */
    protected function checkRepeatLimits()
    {
        if ($this->repeatCount == -1) return true;

        if ($this->_repeats >= $this->repeatCount) return false;

        $this->_repeats += 1;

        return true;
    }

    protected function animTimer(callable $func)
    {
        $generation = $this->_lifetimeGeneration;
        $this->__animTimers[] = $timer = new UXAnimationTimer(function (...$arguments) use ($func, $generation) {
            if ($this->_lifetimeReleased || $generation !== $this->_lifetimeGeneration) return true;
            return call_user_func_array($func, $arguments);
        });
        $timer->start();
        return $timer;
    }

    public function free()
    {
        $failure = null;
        try { parent::free(); }
        catch (\Throwable $cause) { $failure = $cause; }
        foreach ($this->__animTimers as $timer) {
            try { $timer->stop(); }
            catch (\Throwable $cause) { if (!$failure) $failure = $cause; }
        }

        $this->__animTimers = [];
        if ($failure) throw $failure;
    }

    public function __clone()
    {
        parent::__clone();
        $this->__animTimers = [];
        $this->_repeats = 0;
    }

    protected function restore()
    {
        ;
    }
}