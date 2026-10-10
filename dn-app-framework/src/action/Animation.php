<?php
namespace action;

use php\framework\Logger;
use php\gui\animation\UXAnimationTimer;
use php\gui\animation\UXFadeAnimation;
use php\gui\animation\UXPathAnimation;
use php\gui\framework\behaviour\PositionableBehaviour;
use php\gui\framework\Instances;
use php\gui\framework\ObjectGroup;
use php\gui\framework\ScriptEvent;
use php\gui\UXNode;
use php\gui\UXWindow;
use php\lang\IllegalArgumentException;
use php\lib\reflect;
use php\time\Time;
use script\TimerScript;
use timer\AccurateTimer;

/**
 * Class Animation
 * @package action
 *
 * @packages framework
 */
class Animation
{
    /**
     * Fade animation
     * --RU--
     * Анимация затухания
     *
     * @param $object
     * @param $duration
     * @param $value
     * @param callable|null $callback
     * @return null|UXAnimationTimer
     */
    static function fadeTo($object, $duration, $value, callable $callback = null)
    {
        if ($object instanceof Instances) {
            $cnt = sizeof($object);

            $done = function () use (&$cnt, $callback) {
                $cnt--;

                if ($cnt <= 0 && $callback) {
                    $callback();
                }
            };

            if (!$cnt && $callback) $callback();
            foreach ($object->getInstances() as $instance) {
                Animation::fadeTo($instance, $duration, $value, $done);
            }
            return null;
        }

        $initial = $object->opacity;
        $diff = $value - $initial;
        $duration = max(0, (double) $duration) * 1000000;
        $started = Time::nanos();

        $timer = new UXAnimationTimer(function () use ($object, $initial, $diff, $duration, $started, $value, $callback) {
            $progress = $duration > 0 ? min(1, (Time::nanos() - $started) / $duration) : 1;
            $opacity = $initial + $diff * $progress;

            if ($opacity > 1) {
                $opacity = 1;
            }

            $object->opacity = $opacity < 0 ? 0 : $opacity;

            if ($progress >= 1) {
                $object->opacity = (double) $value;

                if ($callback) {
                    $callback();
                }

                return true;
            }

            return false;
        });

        $timer->start();

        return $timer;
    }

    static function fadeIn($object, $duration, callable $callback = null)
    {
        return self::fadeTo($object, $duration, 1.0, $callback);
    }

    static function fadeOut($object, $duration, callable $callback = null)
    {
        return self::fadeTo($object, $duration, 0.0, $callback);
    }

    /**
     * Scale animation.
     * --RU--
     * Анимация масштабирования.
     *
     * @param UXNode|Instances $object
     * @param int $duration
     * @param double $value
     * @param callable $callback
     * @return UXAnimationTimer
     */
    static function scaleTo($object, $duration, $value, callable $callback = null)
    {
        static::stopScale($object);

        if ($object instanceof Instances) {
            $cnt = sizeof($object);

            $done = function () use (&$cnt, $callback) {
                $cnt--;

                if ($cnt <= 0 && $callback) {
                    $callback();
                }
            };

            if (!$cnt && $callback) $callback();
            foreach ($object->getInstances() as $instance) {
                Animation::scaleTo($instance, $duration, $value, $done);
            }

            return null;
        }

        $initial = $object->scaleX;
        $diff = $value - $initial;
        $duration = max(0, (double) $duration) * 1000000;
        $started = Time::nanos();

        $timer = new UXAnimationTimer(function () use ($object, $value, $initial, $diff, $duration, $started, $callback) {
            $progress = $duration > 0 ? min(1, (Time::nanos() - $started) / $duration) : 1;
            $object->scaleX = $initial + $diff * $progress;
            $object->scaleY = $object->scaleX;

            if ($progress >= 1) {
                $object->scaleX = $object->scaleY = $value;

                if ($callback) {
                    $callback();
                }

                return true;
            }

            return false;
        });

        $object->data(Animation::class . "#scaleTo", $timer);

        $timer->start();
        return $timer;
    }

    static function stopScale($object)
    {
        if ($object instanceof Instances) {
            foreach ($object->getInstances() as $instance) self::stopScale($instance);
            return;
        }
        $timer = $object->data(Animation::class . "#scaleTo");

        if ($timer instanceof UXAnimationTimer) {
            $timer->stop();
        }
    }

    /**
     * @param UXNode|UXWindow|Instances $object
     */
    static function stopMove($object)
    {
        if ($object instanceof Instances) {
            foreach ($object->getInstances() as $instance) self::stopMove($instance);
            return;
        }
        $timer = $object->data(Animation::class . "#moveTo");

        if ($timer instanceof UXAnimationTimer) {
            $timer->stop();
        }
    }

    /**
     * Displace animation.
     * --RU--
     * Анимация смещения.
     *
     * @param UXNode|UXWindow|Instances $object
     * @param int $duration
     * @param double $x
     * @param double $y
     * @param callable $callback
     * @return UXAnimationTimer
     */
    static function displace($object, $duration, $x, $y, callable $callback = null)
    {
        if ($object instanceof Instances) {
            $cnt = sizeof($object);
            $done = function () use (&$cnt, $callback) {
                $cnt--;
                if ($cnt <= 0 && $callback) $callback();
            };
            if (!$cnt && $callback) $callback();
            $result = [];
            foreach ($object->getInstances() as $instance) {
                $result[] = self::displace($instance, $duration, $x, $y, $done);
            }
            return $result;
        }
        return self::moveTo($object, $duration, $object->x + $x, $object->y + $y, $callback);
    }

    /**
     * Move to point animation.
     * --RU--
     * Анимация перемещения к точке.
     *
     * @param UXNode|UXWindow|Instances $object
     * @param int $duration
     * @param double $x
     * @param double $y
     * @param callable|null $callback
     * @return array|null|UXAnimationTimer
     */
    static function moveTo($object, $duration, $x, $y, callable $callback = null)
    {
        if ($object instanceof Instances) {
            $cnt = sizeof($object);

            $done = function () use (&$cnt, $callback) {
                $cnt--;

                if ($cnt <= 0 && $callback) {
                    $callback();
                }
            };

            $result = [];

            if (!$cnt && $callback) $callback();
            foreach ($object->getInstances() as $instance) {
                $result[] = Animation::moveTo($instance, $duration, $x, $y, $done);
            }

            return $result;
        }

        if ($object instanceof UXWindow) {
            if (!$object->visible) {
                if ($callback) {
                    AccurateTimer::executeAfter($duration, $callback);
                }

                return null;
            }
        }

        if ($object instanceof UXNode || $object instanceof UXWindow || $object instanceof PositionableBehaviour) {
            $initialX = $object->x;
            $initialY = $object->y;
            $xOffset = $x - $initialX;
            $yOffset = $y - $initialY;
            $duration = max(0, (double) $duration) * 1000000;
            $started = Time::nanos();

            $timer = new UXAnimationTimer(function () use ($object, $initialX, $initialY, $xOffset, $yOffset, $duration, $started, $x, $y, $callback) {
                $progress = $duration > 0 ? min(1, (Time::nanos() - $started) / $duration) : 1;
                $object->x = $initialX + $xOffset * $progress;
                $object->y = $initialY + $yOffset * $progress;

                if ($progress >= 1) {
                    $object->position = [$x, $y];

                    if ($callback) {
                        $object->data(Animation::class . "#moveTo", null);
                        $callback();
                    }

                    return true;
                }

                return false;
            });

            $object->data(Animation::class . "#moveTo", $timer);
            $timer->start();

            return $timer;
        }

        Logger::warn("Cannot animate object(" . reflect::typeOf($object) . "), it's not supported for this type");
    }
}