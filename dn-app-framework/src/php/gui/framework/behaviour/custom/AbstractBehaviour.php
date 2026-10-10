<?php
namespace php\gui\framework\behaviour\custom;
use ide\Logger;
use php\gui\framework\ScriptEvent;
use php\gui\UXNode;
use php\io\IOException;
use php\lang\IllegalArgumentException;
use php\lang\IllegalStateException;
use php\gui\UXDialog;
use php\lib\str;
use ReflectionClass;
use ReflectionProperty;
use script\TimerScript;
use timer\AccurateTimer;

/**
 * Class AbstractBehaviour
 * @package behaviour\custom
 *
 * @getters
 *
 * @packages framework
 */
abstract class AbstractBehaviour
{
    /**
     * @hidden
     * @var bool
     */
    protected $enabled = true;
    protected $_configuredEnabled = true;
    protected $_triggerActive = null;
    private $_triggerUpdating = false;

    /**
     * @var BehaviourManager
     */
    protected $_manager;

    /**
     * @var mixed
     */
    protected $_target;

    private $_parentObserver;
    private $_parentListener;
    private $_eventGroup;
    private $_boundEvents = [];
    private $_boundObservers = [];
    private $_animations = [];
    private $_pendingTimers = [];
    protected $_lifetimeReleased = false;
    protected $_lifetimeGeneration = 0;

    protected function bindObserver($target, $property, callable $callback)
    {
        $observer = $target->observer($property);
        $listener = $observer->addListener($callback);
        $this->_boundObservers[] = [$observer, $listener];
        return $listener;
    }

    protected function later($period, callable $callback)
    {
        if ($this->_lifetimeReleased) return null;
        $generation = $this->_lifetimeGeneration;
        $timer = null;
        $timer = waitAsync($period, function () use ($callback, $generation, &$timer) {
            $this->finishLater($timer);
            if (!$this->_lifetimeReleased && $generation === $this->_lifetimeGeneration) $callback();
        });
        $this->_pendingTimers[spl_object_hash($timer)] = $timer;
        return $timer;
    }

    protected function finishLater($timer)
    {
        if ($timer) unset($this->_pendingTimers[spl_object_hash($timer)]);
    }

    protected function finishAnimation($timer, $target, $slot)
    {
        if ($timer) unset($this->_animations[spl_object_hash($timer)]);
        if ($slot && $target->data($slot) === $timer) $target->data($slot, null);
    }

    protected function cancelAnimation($timer)
    {
        if (!$timer) return;
        $animation = $this->_animations[spl_object_hash($timer)] ?? null;
        if (!$animation) return;
        $timer->stop();
        $this->finishAnimation($timer, $animation[1], $animation[2]);
    }

    protected function animate($method, ...$arguments)
    {
        if ($this->_lifetimeReleased) return null;
        $callback = $arguments && is_callable($arguments[count($arguments) - 1]) ? array_pop($arguments) : null;
        $generation = $this->_lifetimeGeneration;
        $timer = null;
        $target = $arguments[0];
        $slot = $method === 'scaleTo' ? \action\Animation::class . '#scaleTo' :
            (in_array($method, ['moveTo', 'displace'], true) ? \action\Animation::class . '#moveTo' : null);
        $arguments[] = function () use ($callback, $generation, &$timer, $target, $slot) {
            $this->finishAnimation($timer, $target, $slot);
            if (!$this->_lifetimeReleased && $generation === $this->_lifetimeGeneration && $callback) $callback();
        };
        $timer = call_user_func_array([\action\Animation::class, $method], $arguments);
        if ($timer) $this->_animations[spl_object_hash($timer)] = [$timer, $target, $slot];
        return $timer;
    }

    protected function applyImplOwned($target) { $this->applyImpl($target); }

    protected function bindEvent($target, $event, callable $callback)
    {
        if (!$this->_eventGroup) $this->_eventGroup = get_class($this) . ':' . spl_object_hash($this);
        $this->_boundEvents[$event] = $target;
        $target->on($event, $callback, $this->_eventGroup);
    }

    /**
     * @var TimerScript[]
     */
    protected $__timers = [];

    /**
     * AbstractBehaviour constructor.
     * @param mixed $target
     */
    public function __construct($target = null)
    {
        if ($target) {
            $this->apply($target);
        }
    }

    /**
     * @non-getter
     * @return string
     */
    public function getCode()
    {
        return null;
    }

    /**
     * @param mixed $target
     */
    abstract protected function applyImpl($target);

    /**
     * @non-getter
     * @param array $properties
     */
    public function setProperties(array $properties)
    {
        foreach ($properties as $name => $value) {
            if ($name[0] == '_') continue;
            if ($name === 'enabled') { $this->setEnabled($value); continue; }

            try {
                $this->{$name} = $value;
            } catch (\Exception $e) {

            }
        }
    }

    /**
     * @non-getter
     * @return array
     */
    public function getProperties()
    {
        $class = new ReflectionClass($this);

        $result = [];

        foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();

            if ($name[0] == '_') continue;

            $result[$name] = $property->getValue($this);
        }

        foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $name = $method->getName();

            if ($method->isStatic()) continue;

            if ($method->getDeclaringClass()->getName() === AbstractBehaviour::class) {
                continue;
            }

            if (str::startsWith($name, 'set')) {
                $name = str::sub($name, 3);

                if (method_exists($this, 'get' . $name)) {
                    try {
                        $result[str::lowerFirst($name)] = $this->{"get$name"}();
                    } catch (\Exception $e) {
                        Logger::error("Unable to get behaviour property '$name', getter throws exception = " . $e->getMessage());
                    }
                }
            }
        }

        $result['enabled'] = $this->getEnabled();
        return $result;
    }

    /**
     * @param $target
     * @throws \php\lang\IllegalStateException
     */
    public function apply($target)
    {
        if ($this->_target) {
            throw new IllegalStateException("This behaviour already used");
        }

        $this->_target = $target;
        $this->_lifetimeReleased = false;
        ++$this->_lifetimeGeneration;

        try {
            BehaviourManager::guiLifecycle('apply', $target, $this);
            $code = $this->getCode();

            if ($code && method_exists($target, 'data')) {
                $target->data("--property-$code", $this);
            }

            /** @var UXNode $target */
            $this->applyImplOwned($target);
            BehaviourManager::guiLifecycle('check', $target, $this);

            try {
                $this->_parentObserver = $target->observer('parent');
                $this->_parentListener = $this->_parentObserver->addListener(function ($old, $new) use ($target) {
                    if (!$new && $this->_target === $target) {
                        $this->free();
                    }
                });
            } catch (IllegalArgumentException $e) {
                ;
            }
        } catch (\Throwable $failure) {
            try { $this->free(); } catch (\Throwable $cleanup) { }
            throw $failure;
        }
    }

    public function disable()
    {
        $wasEnabled = $this->enabled;
        if (!$this->_triggerUpdating) $this->_configuredEnabled = false;
        $this->enabled = false;
        if ($wasEnabled && !$this->_triggerUpdating && $this->_triggerActive !== null) $this->restore();
    }

    public function enable()
    {
        if (!$this->_triggerUpdating) $this->_configuredEnabled = true;
        $this->enabled = $this->_configuredEnabled && ($this->_triggerActive === null || $this->_triggerActive);
    }

    public function getEnabled() { return $this->_triggerActive === null ? (bool) $this->enabled : $this->_configuredEnabled; }
    public function setEnabled($value)
    {
        if (is_string($value)) {
            $value = strtolower($value);
            if (!in_array($value, ['', '0', '1', 'false', 'true'], true)) throw new \InvalidArgumentException('Invalid boolean: enabled');
            $value = in_array($value, ['1', 'true'], true);
        } elseif (!is_bool($value) && $value !== 0 && $value !== 1) throw new \InvalidArgumentException('Invalid boolean: enabled');
        if ($value) $this->enable(); else $this->disable();
    }
    protected function restore() { }

    protected function prepareTrigger($active)
    {
        $this->_configuredEnabled = (bool) $this->enabled;
        $this->_triggerActive = (bool) $active;
        $this->enabled = $this->_configuredEnabled && $this->_triggerActive;
    }

    protected function updateTrigger($active)
    {
        $wasEnabled = $this->enabled;
        $this->_triggerActive = (bool) $active;
        $this->_triggerUpdating = true;
        try {
            if ($this->_configuredEnabled && $this->_triggerActive) $this->enable();
            else { $this->disable(); if ($wasEnabled) $this->restore(); }
        } finally { $this->_triggerUpdating = false; }
    }

    /**
     * @non-getter
     * @return int
     */
    public function getSort()
    {
        return 0;
    }

    protected function timer($interval, callable $callback)
    {
        $generation = $this->_lifetimeGeneration;
        $this->__timers[] = $timerScript = new TimerScript($interval, true, function (ScriptEvent $e = null) use ($callback, $generation) {
            if ($this->_lifetimeReleased || $generation !== $this->_lifetimeGeneration || $this->_target->isFree()) {
                return;
            }

            if ($this->enabled) {
                $callback($e);
            }
        });

        $timerScript->start();

        return $timerScript;
    }

    protected function accurateTimer($interval, callable $handle)
    {
        $generation = $this->_lifetimeGeneration;
        $this->__timers[] = $timer = new AccurateTimer($interval, function (...$arguments) use ($handle, $generation) {
            if ($this->_lifetimeReleased || $generation !== $this->_lifetimeGeneration) return true;
            return call_user_func_array($handle, $arguments);
        });

        $timer->start();

        return $timer;
    }

    public function free()
    {
        $this->_lifetimeReleased = true;
        ++$this->_lifetimeGeneration;
        $observer = $this->_parentObserver;
        $listener = $this->_parentListener;
        $this->_parentObserver = $this->_parentListener = null;
        $failure = null;
        try { if ($observer && $listener) $observer->removeListener($listener); }
        catch (\Throwable $cause) { $failure = $cause; }
        foreach ($this->_boundEvents as $event => $target) {
            try { $target->off($event, $this->_eventGroup); }
            catch (\Throwable $cause) { if (!$failure) $failure = $cause; }
        }
        $this->_boundEvents = [];
        foreach ($this->_boundObservers as $binding) {
            try { $binding[0]->removeListener($binding[1]); }
            catch (\Throwable $cause) { if (!$failure) $failure = $cause; }
        }
        $this->_boundObservers = [];
        foreach ($this->_animations as $animation) {
            try {
                $animation[0]->stop();
                if ($animation[2] && $animation[1]->data($animation[2]) === $animation[0]) $animation[1]->data($animation[2], null);
            }
            catch (\Throwable $cause) { if (!$failure) $failure = $cause; }
        }
        $this->_animations = [];
        foreach (array_merge($this->__timers, $this->_pendingTimers) as $timer) {
            try { $timer->free(); }
            catch (\Throwable $cause) { if (!$failure) $failure = $cause; }
        }
        $this->__timers = [];
        $this->_pendingTimers = [];
        try { BehaviourManager::guiLifecycle('release', $this->_target, $this); }
        catch (\Throwable $cause) { if (!$failure) $failure = $cause; }
        if ($failure) throw $failure;
    }

    public function __clone()
    {
        $this->_target = null;
        $this->_parentObserver = $this->_parentListener = $this->_eventGroup = null;
        $this->_boundEvents = $this->__timers = [];
        $this->_boundObservers = $this->_animations = $this->_pendingTimers = [];
        $this->_lifetimeReleased = false;
        $this->_lifetimeGeneration = 0;
        if ($this->_triggerActive !== null) $this->enabled = $this->_configuredEnabled;
        $this->_triggerActive = null;
        $this->_triggerUpdating = false;
    }

    public function __set($name, $value)
    {
        if (method_exists($this, "set$name")) {
            $this->{"set$name"}($value);
            return;
        }

        throw new \Exception("Unable to set the '$name' property");
    }

    public function __get($name)
    {
        if (method_exists($this, "get$name")) {
            return $this->{"get$name"}();
        }

        throw new \Exception("Unable to get the '$name' property");
    }

    public function __isset($name)
    {
        if (method_exists($this, "get$name")) {
            return true;
        }

        return false;
    }

    /**
     * @param $target
     * @return $this
     * @throws IllegalStateException
     */
    static function get($target)
    {
        $type = get_called_class();

        if (method_exists($target, 'data')) {
            $data = $target->data('~behaviour~' . $type);

            return $data;
        }

        return null;
    }
}