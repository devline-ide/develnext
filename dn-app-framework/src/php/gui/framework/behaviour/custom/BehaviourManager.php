<?php
namespace php\gui\framework\behaviour\custom;
use Traversable;
use php\gui\framework\Instances;

/**
 * Class BehaviourManager
 * @package behaviour\custom
 *
 * @packages framework
 */
abstract class BehaviourManager
{
    private static $factory;
    private static $scriptReleaseHandler;
    private static $formReleaseHandler;
    private static $guiLifecycleHandler;

    /** Optional lifetime provider for builtin and directly applied GUI behaviours. */
    public static function setGuiLifecycleHandler(callable $handler = null)
    {
        self::$guiLifecycleHandler = $handler;
    }

    public static function guiLifecycle($operation, $target, $behaviour)
    {
        if (self::$guiLifecycleHandler) call_user_func(self::$guiLifecycleHandler, $operation, $target, $behaviour);
    }

    /** Optional form lifetime provider; the begin phase prevents resurrection during cleanup. */
    public static function setFormReleaseHandler(callable $handler = null)
    {
        self::$formReleaseHandler = $handler;
    }

    public static function beginFormRelease($form)
    {
        if (self::$formReleaseHandler) call_user_func(self::$formReleaseHandler, $form, false);
    }

    public static function releaseForm($form)
    {
        if (self::$formReleaseHandler) call_user_func(self::$formReleaseHandler, $form, true);
    }

    /** Optional owner cleanup, independent of Script.data() and GUI parent observers. */
    public static function setScriptReleaseHandler(callable $handler = null)
    {
        self::$scriptReleaseHandler = $handler;
    }

    public static function releaseScript($script)
    {
        if (self::$scriptReleaseHandler) call_user_func(self::$scriptReleaseHandler, $script);
    }

    /** Optional application provider; the framework keeps its default managers. */
    public static function setFactory(callable $factory = null)
    {
        self::$factory = $factory;
    }

    public static function create($owner, $defaultClass)
    {
        if (self::$factory) {
            $manager = call_user_func(self::$factory, $owner, $defaultClass);
            if ($manager !== null) {
                if (!($manager instanceof BehaviourManager)) throw new \InvalidArgumentException('Invalid behaviour manager provider');
                return $manager;
            }
        }
        return new $defaultClass($owner);
    }

    /**
     * @param $targetId
     * @param AbstractBehaviour $behaviour
     * @return mixed
     */
    abstract public function apply($targetId, AbstractBehaviour $behaviour);

    /**
     * @param $target
     * @param $type
     * @return AbstractBehaviour
     */
    public function getBehaviour($target, $type)
    {
        if ($target instanceof Traversable || is_array($target)) {
            $result = [];

            foreach ($target as $one) {
                $result[] = $this->getBehaviour($one, $type);
            }

            return new Instances($result);
        }

        if (method_exists($target, 'data')) {
            $data = $target->data('~behaviour~' . $type);

            if ($data == null) {
                /** @var AbstractBehaviour $data */
                $data = new $type();
                $data->disable();
                $this->apply($target->id, $data);
            }

            return $data;
        }

        return null;
    }
}
