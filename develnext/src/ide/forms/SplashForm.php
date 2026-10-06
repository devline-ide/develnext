<?php
namespace ide\forms;

use ide\Ide;
use ide\Logger;
use php\gui\effect\UXSepiaToneEffect;
use php\gui\UXApplication;
use php\gui\UXImage;
use php\gui\UXImageArea;
use php\gui\UXImageView;
use php\gui\UXLabel;
use php\lang\Thread;
use php\lib\str;

/**
 * @property UXLabel $version
 * @property UXImageView $image
 */
class SplashForm extends AbstractIdeForm
{
    protected $minimumDisplayElapsed = false;
    protected $mainReady = false;
    protected $onMainReady;

    public function whenMainReady(callable $callback)
    {
        $this->onMainReady = $callback;
        $this->mainReady = true;
        $this->tryHideWhenReady();
    }

    protected function tryHideWhenReady()
    {
        if ($this->minimumDisplayElapsed && $this->mainReady) {
            $this->hide();

            if ($callback = $this->onMainReady) {
                $this->onMainReady = null;
                $callback();
            }
        }
    }

    protected function init()
    {
        Logger::debug("Init form ...");

        $this->centerOnScreen();

        $versionCode = $this->_app->getConfig()->get('app.versionCode');
        $this->version->text = $this->_app->getVersion();

        if ($this->_app->isSnapshotVersion()) {
            $effect = new UXSepiaToneEffect();
            $effect->level = 0.5;
            $this->image->effects->add($effect);

        }

        if ($versionCode) {
            $this->versionCode->text = str::upperFirst($versionCode);

            $codeImg = new UXImageArea(new UXImage('res://.data/img/code/' . $versionCode . '.png'));
            $codeImg->stretch = true;
            $codeImg->smartStretch = true;
            $codeImg->size = [64, 64];
            $codeImg->position = [690 - 64 - 14, 14];

            $this->add($codeImg);
        }

        waitAsync(2000, function() {
            $this->minimumDisplayElapsed = true;
            $this->tryHideWhenReady();
        });

    }

    /**
     * @event show
     */
    public function doShow()
    {
        $this->opacity = 1;

        uiLater(function () {
            $this->toFront();
        });
    }

    /**
     * @event click
     */
    public function hide()
    {
        parent::hide();
    }
}
