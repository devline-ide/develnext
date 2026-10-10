<?php
namespace behaviour\custom;

use php\framework\Logger;
use php\gui\framework\behaviour\custom\AbstractBehaviour;
use php\gui\UXPopupWindow;
use php\gui\UXScreen;
use php\gui\UXWindow;
use php\lib\reflect;

/**
 * Class WidgetFormBehaviour
 * @package behaviour\custom
 *
 * @packages framework
 */
class WidgetFormBehaviour extends AbstractBehaviour
{
    /**
     * @var UXPopupWindow
     */
    protected $popup;

    protected $_widgetLayout;
    protected $_widgetSize;
    protected $_widgetPosition;
    protected $_widgetWasEnabled = false;

    /**
     * @var int
     */
    protected $offsetX = 10;

    /**
     * @var int
     */
    protected $offsetY = 10;

    /**
     * @var string
     */
    protected $position = 'BOTTOM_RIGHT';

    public function apply($target)
    {
        if ($target instanceof UXWindow && $target->data(__CLASS__ . '#owner') && $target->data(__CLASS__ . '#owner') !== $this) {
            throw new \php\lang\IllegalStateException('Form already has a widget behaviour');
        }
        parent::apply($target);
    }

    /**
     * @param mixed $target
     */
    protected function applyImpl($target)
    {
        if ($target instanceof UXWindow) {
            // Stage style must be set before showing; fail before moving content.
            $target->style = 'UTILITY';
            $target->data(__CLASS__ . '#owner', $this);
            $popup = $this->popup = new UXPopupWindow();
            $popup->anchorLocation = 'WINDOW_TOP_LEFT';
            $popup->data('~behaviour-form', $target);
            $layout = $this->_widgetLayout = $target->layout;
            $layout->data(__CLASS__ . '#owner', $this);
            $this->_widgetSize = $target->size;
            $this->_widgetPosition = [$target->x, $target->y];
            if (is_finite($this->_widgetPosition[0])) $popup->x = $this->_widgetPosition[0];
            if (is_finite($this->_widgetPosition[1])) $popup->y = $this->_widgetPosition[1];
            $target->makeVirtualLayout();
            if ($this->_lifetimeReleased) return;
            $popup->layout = $layout;
            if ($this->_lifetimeReleased) return;
            $target->x = -9999;
            if ($this->_lifetimeReleased) return;
            $target->y = -9999;
            if ($this->_lifetimeReleased) return;
            $target->size = [0, 0];
            if ($this->_lifetimeReleased) return;

            $this->bindEvent($target, 'show', function () {
                $x = is_finite($this->popup->x) ? $this->popup->x : $this->_widgetPosition[0];
                $y = is_finite($this->popup->y) ? $this->popup->y : $this->_widgetPosition[1];
                if (is_finite($x) && is_finite($y)) $this->popup->show($this->_target, $x, $y);
                else $this->popup->show($this->_target);
                $this->resetPosition();
            });
            $this->bindEvent($target, 'hiding', function () { $this->popup->hide(); });
            foreach (['width', 'height'] as $property) {
                $this->bindObserver($popup, $property, function () {
                    if ($this->enabled && $this->popup && $this->popup->visible) {
                        $this->later(0, function () { $this->resetPosition(); });
                    }
                });
            }
            $this->_widgetWasEnabled = (bool) $this->enabled;
            $this->accurateTimer(16, function () {
                $enabled = (bool) $this->enabled;
                if ($enabled && !$this->_widgetWasEnabled && $this->popup && $this->popup->visible) $this->resetPosition();
                $this->_widgetWasEnabled = $enabled;
            });

            $this->bindObserver($target, 'x', function ($_, $new) use ($target) {
                if ($new == -9999) return;

                $this->popup->x = $new;

                $this->later(0, function () use ($target) {
                    $target->x = -9999;
                });
            });

            $this->bindObserver($target, 'y', function ($_, $new) use ($target) {
                if ($new == -9999) return;

                $this->popup->y = $new;

                $this->later(0, function () use ($target) {
                    $target->y = -9999;
                });
            });
        } else {
            $class = reflect::typeOf($this);
            $object = reflect::typeOf($target);
            Logger::warn("Unable to apply '$class' behaviour to '$object' object");
        }
    }

    public function enable()
    {
        $wasEnabled = (bool) $this->enabled;
        parent::enable();
        $this->_widgetWasEnabled = true;
        if (!$wasEnabled && !$this->_lifetimeReleased && $this->popup && $this->popup->visible) $this->resetPosition();
    }

    public function disable()
    {
        parent::disable();
        $this->_widgetWasEnabled = false;
    }

    public function free()
    {
        $popup = $this->popup;
        $layout = $this->_widgetLayout;
        $size = $this->_widgetSize;
        $position = $this->_widgetPosition;
        $restore = $layout && $this->_target->data('~~virtual-layout') !== null && $this->_target->layout->data(__CLASS__ . '#owner') === $this;
        $this->popup = $this->_widgetLayout = $this->_widgetSize = $this->_widgetPosition = null;
        $failure = null;
        try { parent::free(); } catch (\Throwable $cause) { $failure = $cause; }
        if ($popup) {
            try { $popup->hide(); }
            catch (\Throwable $cause) { if (!$failure) $failure = $cause; }
            try { $popup->layout = new \php\gui\layout\UXAnchorPane(); }
            catch (\Throwable $cause) { if (!$failure) $failure = $cause; }
            try { $popup->data('~behaviour-form', null); }
            catch (\Throwable $cause) { if (!$failure) $failure = $cause; }
        }
        try {
            if ($this->_target && $this->_target->data(__CLASS__ . '#owner') === $this) $this->_target->data(__CLASS__ . '#owner', null);
            if ($layout && $layout->data(__CLASS__ . '#owner') === $this) $layout->data(__CLASS__ . '#owner', null);
        } catch (\Throwable $cause) { if (!$failure) $failure = $cause; }
        if ($restore) {
            try { $this->_target->layout = $layout; }
            catch (\Throwable $cause) { if (!$failure) $failure = $cause; }
            try { $this->_target->size = $size; $this->_target->x = $position[0]; $this->_target->y = $position[1]; }
            catch (\Throwable $cause) { if (!$failure) $failure = $cause; }
        }
        if ($failure) throw $failure;
    }

    public function __clone()
    {
        parent::__clone();
        $this->popup = $this->_widgetLayout = $this->_widgetSize = $this->_widgetPosition = null;
        $this->_widgetWasEnabled = false;
    }

    /**
     * @return string
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * Reset the position of the widget to the default value.
     * --RU--
     * Сбросить позицию виджета к значению по умолчанию.
     */
    public function resetPosition()
    {
        $this->setPosition($this->position);
    }

    /**
     * Set position of the widget on screen.
     * --RU--
     * Позиция виджета относительно экрана.
     * @param string $position
     */
    public function setPosition($position)
    {
        $this->position = $position;
        if (!$this->enabled) { $this->_widgetWasEnabled = false; return; }

        if ($this->enabled && !$this->_lifetimeReleased && $this->popup) {
            $screen = UXScreen::getPrimary();
            $x = $screen->visualBounds['x'];
            $y = $screen->visualBounds['y'];

            switch ($position) {
                case 'CENTER':
                case 'BOTTOM_CENTER':
                case 'TOP_CENTER':
                    $x = round($screen->visualBounds['x'] + ($screen->visualBounds['width'] - $this->popup->width) / 2);
                    break;

                case 'TOP_RIGHT':
                case 'BOTTOM_RIGHT':
                case 'MIDDLE_RIGHT':
                    $x = round($screen->visualBounds['x'] + $screen->visualBounds['width'] - $this->popup->width);
                    break;
            }

            switch ($position) {
                case 'MIDDLE_LEFT':
                case 'CENTER':
                case 'MIDDLE_RIGHT':
                    $y = round($screen->visualBounds['y'] + ($screen->visualBounds['height'] - $this->popup->height) / 2);
                    break;

                case 'BOTTOM_LEFT':
                case 'BOTTOM_CENTER':
                case 'BOTTOM_RIGHT':
                    $y = round($screen->visualBounds['y'] + $screen->visualBounds['height'] - $this->popup->height);
                    break;
            }

            if ($this->offsetX) {
                if ($x <= $screen->visualBounds['x']) {
                    $x += $this->offsetX;
                } else {
                    $x -= $this->offsetX;
                }
            }

            if ($this->offsetY) {
                if ($y <= $screen->visualBounds['y']) {
                    $y += $this->offsetY;
                } else {
                    $y -= $this->offsetY;
                }
            }

            $this->popup->x = $x;
            $this->popup->y = $y;
            $this->_widgetWasEnabled = true;

            Logger::info("Set widget position ($this->position) as ($x, $y)");
        }
    }

    /**
     * Offset by Y.
     * --RU--
     * Смещение виджета по горизонтали.
     * @return int
     */
    public function getOffsetX()
    {
        return $this->offsetX;
    }

    /**
     * @param int $offsetX
     */
    public function setOffsetX($offsetX)
    {
        $this->offsetX = $offsetX;
        $this->resetPosition();
    }

    /**
     * Offset by X.
     * --RU--
     * Смещение виджета по вертикали.
     * @return int
     */
    public function getOffsetY()
    {
        return $this->offsetY;
    }

    /**
     * @param int $offsetY
     */
    public function setOffsetY($offsetY)
    {
        $this->offsetY = $offsetY;
        $this->resetPosition();
    }

    public function getCode()
    {
        return 'widget';
    }
}
