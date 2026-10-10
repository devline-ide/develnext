<?php
namespace behaviour\custom;

use action\Animation;
use php\gui\event\UXMouseEvent;
use php\gui\framework\behaviour\custom\AbstractBehaviour;
use php\gui\UXDialog;
use php\gui\UXNode;
use php\gui\UXWindow;
use php\util\SharedValue;
use script\TimerScript;

/**
 * Class DraggingFormBehaviour
 * @package behaviour\custom
 *
 * @packages framework
 */
class DraggingFormBehaviour extends AbstractBehaviour
{
    /**
     * @var bool
     */
    public $opacityEnabled = false;

    /**
     * @var float
     */
    public $opacity = 0.7;

    /**
     * @var bool
     */
    public $animated = true;

    private $_dragPosition;
    private $_dragOpacity;
    private $_dragTransition;
    private $_dragWindow;

    /**
     * @param mixed $target
     */
    protected function applyImpl($target)
    {
        if ($target instanceof UXWindow)  {
            $target = $target->layout;
        }

        if ($target instanceof UXNode) {
            $this->bindEvent($target, 'mouseDown', function (UXMouseEvent $e) use ($target) {
                if (!$this->enabled) {
                    return;
                }

                if ($e->button == 'PRIMARY') {
                    $this->finishDrag(false);
                    $this->_dragWindow = $target->window;
                    if (!$this->_dragWindow) return;
                    if ($this->opacityEnabled) {
                        $this->_dragOpacity = $target->window->opacity;
                        if ($this->animated) {
                            $this->_dragTransition = $this->animate('fadeTo', $target->window, 300, $this->opacity);
                        } else {
                            $target->window->opacity = $this->opacity;
                        }
                    }

                    $this->_dragPosition = [$e->screenX - $target->window->x, $e->screenY - $target->window->y];
                }
            });

            $move = function (UXMouseEvent $e) {
                if ($this->enabled && $this->_dragPosition !== null) {
                    $this->_dragWindow->x = $e->screenX - $this->_dragPosition[0];
                    $this->_dragWindow->y = $e->screenY - $this->_dragPosition[1];
                }
            };

            $this->bindEvent($target, 'mouseDrag', $move);

            $this->bindEvent($target, 'mouseUp', function (UXMouseEvent $e) {
                if ($e->button == 'PRIMARY' && $this->_dragPosition !== null) {
                    $this->finishDrag($this->animated);
                }
            });
        }
    }

    private function finishDrag($animated)
    {
        $this->_dragPosition = null;
        $this->cancelAnimation($this->_dragTransition);
        $this->_dragTransition = null;
        if ($this->_dragOpacity === null) { $this->_dragWindow = null; return; }
        if ($animated) {
            $this->_dragTransition = $this->animate('fadeTo', $this->_dragWindow, 300, $this->_dragOpacity, function () {
                $this->_dragOpacity = $this->_dragTransition = $this->_dragWindow = null;
            });
        } else {
            $this->_dragWindow->opacity = $this->_dragOpacity;
            $this->_dragOpacity = $this->_dragWindow = null;
        }
    }

    public function disable()
    {
        parent::disable();
        $this->finishDrag(false);
    }

    public function free()
    {
        try { $this->finishDrag(false); }
        finally { parent::free(); }
    }

    public function __clone()
    {
        parent::__clone();
        $this->_dragPosition = $this->_dragOpacity = $this->_dragTransition = $this->_dragWindow = null;
    }

    public function getCode()
    {
        return 'draggingForm';
    }
}