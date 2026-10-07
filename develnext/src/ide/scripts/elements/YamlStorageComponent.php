<?php
namespace ide\scripts\elements;

use ide\scripts\AbstractScriptComponent;
use ide\scripts\ScriptComponentContainer;
use script\storage\YamlStorage;

class YamlStorageComponent extends AbstractScriptComponent
{
    public function getType() { return YamlStorage::class; }
    public function getGroup() { return 'Данные'; }
    public function getDescription() { return 'YAML и YML файл с секциями и типизированными значениями'; }
    public function getPlaceholder(ScriptComponentContainer $container) { return 'YAML / YML Файл'; }
    public function getIdPattern() { return 'yaml%s'; }
    public function getName() { return 'YAML / YML Файл'; }
    public function getIcon() { return 'icons/iniFile16.png'; }
    public function isOrigin($any) { return $any instanceof YamlStorageComponent; }
}
