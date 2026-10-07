<?php
namespace ide\scripts\elements;

use ide\scripts\AbstractScriptComponent;
use ide\scripts\ScriptComponentContainer;
use script\storage\VdfStorage;

class VdfStorageComponent extends AbstractScriptComponent
{
    public function getType() { return VdfStorage::class; }
    public function getGroup() { return 'Данные'; }
    public function getDescription() { return 'Valve KeyValues 1, текстовые именованные блоки'; }
    public function getPlaceholder(ScriptComponentContainer $container) { return 'VDF Файл'; }
    public function getIdPattern() { return 'vdf%s'; }
    public function getName() { return 'VDF Файл'; }
    public function getIcon() { return 'icons/iniFile16.png'; }
    public function isOrigin($any) { return $any instanceof VdfStorageComponent; }
}
