<?php
namespace behaviour\custom;

use action\Animation;
use php\gui\event\UXMouseEvent;
use php\gui\framework\behaviour\custom\AbstractBehaviour;
use php\gui\UXDialog;
use php\gui\UXNode;
use php\util\SharedValue;
use script\TimerScript;

/**
 * Class DraggingBehaviour
 * @package behaviour\custom
 *
 * @packages framework
 */
class DraggingBehaviour extends AbstractBehaviour
{
    /**
     * @var bool
     */
    public $opacityEnabled = false;

    /**
     * @var float
     */
    public $opacity = 0.5;

    /**
     * @var bool
     */
    public $animated = true;

    /**
     * @var int
     */
    public $gridX = 1;

    /**
     * @var int
     */
    public $gridY = 1;

    /**
     * @var string ALL|LEFT_RIGHT|UP_DOWN
     */
    public $direction = 'ALL';

    /**
     * @var bool
     */
    public $limitedByParent = false;


    private $_dragPosition;
    private $_dragOpacity;
    private $_dragTransition;

    /**
     * @param mixed $target
     */
    protected function applyImpl($target)
    {
        if ($target instanceof UXNode) {
            $this->bindEvent($target, 'mouseDown', function (UXMouseEvent $e) {
                if ($this->enabled && $e->button == 'PRIMARY') {
                    $this->finishDrag(false);
                    if ($this->opacityEnabled) {
                        $this->_dragOpacity = $this->_target->opacity;
                        if ($this->animated) {
                            $this->_dragTransition = $this->animate('fadeTo', $this->_target, 300, $this->opacity);
                        } else {
                            $this->_target->opacity = $this->opacity;
                        }
                    }

                    $this->_dragPosition = [$e->screenX - $this->_target->x, $e->screenY - $this->_target->y];
                }
            });

            $move = function (UXMouseEvent $e) {
                if ($this->enabled && $this->_dragPosition !== null) {
                    if (in_array($this->direction, ['ALL', 'LEFT_RIGHT'])) {
                        $x = $e->screenX - $this->_dragPosition[0];

                        if ($this->gridX > 1) {
                            $x = round($x / $this->gridX) * $this->gridX;
                        }

                        if ($this->limitedByParent) {
                            if ($x < 0) {
                                $x = 0;
                            } elseif ($this->_target->parent && $x > $this->_target->parent->width - $this->_target->width) {
                                $x = $this->_target->parent->width - $this->_target->width;
                            }
                        }

                        $this->_target->x = $x;
                    }

                    if (in_array($this->direction, ['ALL', 'UP_DOWN'])) {
                        $y = $e->screenY - $this->_dragPosition[1];

                        if ($this->gridY > 1) {
                            $y = round($y / $this->gridY) * $this->gridY;
                        }


                        if ($this->limitedByParent) {
                            if ($y < 0) {
                                $y = 0;
                            } elseif ($this->_target->parent && $y > $this->_target->parent->height - $this->_target->height) {
                                $y = $this->_target->parent->height - $this->_target->height;
                            }
                        }

                        $this->_target->y = $y;
                    }
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
        if ($this->_dragOpacity === null) return;
        if ($animated) {
            $this->_dragTransition = $this->animate('fadeTo', $this->_target, 300, $this->_dragOpacity, function () {
                $this->_dragOpacity = $this->_dragTransition = null;
            });
        } else {
            $this->_target->opacity = $this->_dragOpacity;
            $this->_dragOpacity = null;
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
        $this->_dragPosition = $this->_dragOpacity = $this->_dragTransition = null;
    }

    public function getCode()
    {
        return 'dragging';
    }
}