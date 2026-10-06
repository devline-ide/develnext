<?php
namespace ide\scripts\elements;

use ide\scripts\AbstractScriptComponent;
use ide\scripts\ScriptComponentContainer;
use script\storage\JsonStorage;

class JsonStorageComponent extends AbstractScriptComponent
{
    public function getType() { return JsonStorage::class; }
    public function getGroup() { return 'Данные'; }
    public function getDescription() { return 'JSON файл с секциями и типизированными значениями'; }
    public function getPlaceholder(ScriptComponentContainer $container) { return 'JSON Файл'; }
    public function getIdPattern() { return 'json%s'; }
    public function getName() { return 'JSON Файл'; }
    public function getIcon() { return 'icons/iniFile16.png'; }
    public function isOrigin($any) { return $any instanceof JsonStorageComponent; }
}
