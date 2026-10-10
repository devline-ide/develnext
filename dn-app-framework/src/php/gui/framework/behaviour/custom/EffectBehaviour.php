<?php
namespace php\gui\framework\behaviour\custom;


use php\gui\effect\UXEffect;
use php\gui\UXNode;

/**
 * Class EffectBehaviour
 * @package php\gui\framework\behaviour\custom
 *
 * @packages framework
 */
abstract class EffectBehaviour extends AbstractBehaviour
{
    /**
     * @var UXEffect
     */
    protected $_effect;

    /**
     * @var string
     */
    public $when = 'ALWAYS';

    /**
     * @return UXEffect
     */
    public abstract function makeEffect();
    public abstract function updateEffect(UXEffect $effect);

    public function apply($target)
    {
        if ($this->_target) throw new \php\lang\IllegalStateException('This behaviour already used');
        $types = $this->getWhenEventTypes();

        if ($types && $target instanceof UXNode) {
            $this->prepareTrigger($types[2] ?? false);

            $this->bindEvent($target, $types[0], function () {
                $this->updateTrigger(true);
            });

            $this->bindEvent($target, $types[1], function () {
                $this->updateTrigger(false);
            });
        }

        $this->__apply($target);
    }

    public function __apply($target)
    {
        parent::apply($target);
    }

    /**
     * @param mixed $target
     */
    protected function applyImpl($target)
    {
        $this->_effect = $this->makeEffect();

        if ($this->enabled && $target instanceof UXNode) {
            if (!$target->effects->has($this->_effect)) {
                $target->effects->add($this->_effect);
            }
        }

        $this->updateEffect($this->_effect);
    }

    protected function getWhenEventTypes()
    {
        switch ($this->when) {
            case 'HOVER':
                return ['mouseEnter', 'mouseExit'];
            case 'HOVER_INVERT':
                return ['mouseExit', 'mouseEnter', true];
            case 'CLICK':
                return ['mouseDown', 'mouseUp'];
            case 'CLICK_INVERT':
                return ['mouseUp', 'mouseDown', true];
        }

        return null;
    }

    public function __set($name, $value)
    {
        parent::__set($name, $value);

        if ($this->_effect) {
            $this->updateEffect($this->_effect);
        }
    }

    public function disable()
    {
        parent::disable();

        if ($this->_effect && $this->_target instanceof UXNode) {
            $this->_target->effects->disable($this->_effect);
        }
    }

    public function enable()
    {
        parent::enable();

        if ($this->enabled && $this->_effect && $this->_target instanceof UXNode) {
            if (!$this->_target->effects->has($this->_effect)) {
                $this->_target->effects->add($this->_effect);
            }

            $this->_target->effects->enable($this->_effect);
        }
    }

    public function free()
    {
        try { parent::free(); }
        finally {
            if ($this->_target instanceof UXNode && $this->_effect) {
                $this->_target->effects->remove($this->_effect);
            }
        }
    }

    protected function restore()
    {
        ;
    }
}