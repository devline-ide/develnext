<?php
namespace script\storage;

use php\format\JsonProcessor;
use php\io\File;
use php\io\IOException;
use php\io\Stream;
use php\lib\str;

/**
 * JSON storage with the AbstractStorage key/section API and typed values.
 * The file contains an object of sections; the default section is named "".
 * @packages framework
 */
class JsonStorage extends AbstractStorage
{
    protected $_path;
    public $prettyPrint = true;

    public function __construct($path = null)
    {
        $this->_path = $path;
        if ($path) $this->load();
    }

    private function value($value, $depth = 0)
    {
        if ($depth >= 128 || is_object($value) || is_resource($value)) {
            throw new \Exception('JSON values must be scalars or arrays with nesting below 128');
        }
        if (is_float($value) && !is_finite($value)) throw new \Exception('Invalid JSON number');
        if (!is_array($value)) return $value;
        $result = [];
        foreach ($value as $key => $item) $result[$key] = $this->value($item, $depth + 1);
        return $result;
    }

    public function load()
    {
        if (!$this->_path || $this->disabled) return false;
        try {
            $text = str::decode(Stream::getContents($this->_path), 'UTF-8');
            $root = (new JsonProcessor())->parse($text);
            if (!($root instanceof \stdClass)) throw new \Exception('JSON storage root must be an object of sections');
            foreach ((array) $root as $section => $values) {
                if (!($values instanceof \stdClass)) throw new \Exception("JSON storage section must be an object: $section");
            }
            $loaded = (new JsonProcessor(JsonProcessor::DESERIALIZE_AS_ARRAYS))->parse($text);
            $this->data = $this->value($loaded);
        } catch (\Exception $e) {
            $this->trigger('error', ['error' => $e]);
            return false;
        }
        return true;
    }

    public function save()
    {
        if (!$this->_path || $this->disabled) return false;
        $temporary = null;
        try {
            $sections = new \stdClass();
            foreach ($this->data as $name => $values) $sections->{"$name"} = (object) $values;
            $processor = new JsonProcessor(JsonProcessor::SERIALIZE_NULLS | ($this->prettyPrint ? JsonProcessor::SERIALIZE_PRETTY_PRINT : 0));
            $text = $processor->format($sections);
            // Stage beside the destination; a failed write/rename leaves the previous file intact.
            $file = new File($this->_path);
            $temporary = File::createTemp('.json-storage-', '.tmp', $file->getAbsoluteFile()->getParent());
            Stream::putContents($temporary->getPath(), str::encode($text, 'UTF-8'));
            if (!$temporary->renameTo($file->getAbsolutePath(), true)) throw new IOException('Cannot replace JSON storage file');
        } catch (\Exception $e) {
            $this->trigger('error', ['error' => $e]);
            return false;
        } finally {
            if ($temporary) $temporary->delete();
        }
        $this->trigger('save');
        return true;
    }

    public function getPath() { return $this->_path; }

    public function setPath($path)
    {
        if ($this->disabled || $path == $this->_path) return;
        if ($this->_path && $this->autoSave && !$this->save()) return;
        $this->_path = $path;
        $this->load();
    }

    public function get($key, $section = '')
    {
        return $this->disabled ? null : ($this->data["$section"][$key] ?? null);
    }

    public function set($key, $value, $section = '', $checkAutoSave = true)
    {
        if ($this->disabled) return;
        try { $value = $this->value($value); }
        catch (\Exception $e) { $this->trigger('error', ['error' => $e]); return; }
        $this->data["$section"][$key] = $value;
        if ($checkAutoSave && $this->autoSave) $this->save();
    }

    public function put(array $values, $section = '')
    {
        if ($this->disabled) return;
        try { $values = $this->value($values); }
        catch (\Exception $e) { $this->trigger('error', ['error' => $e]); return; }
        if (!isset($this->data["$section"])) $this->data["$section"] = [];
        foreach ($values as $key => $value) $this->data["$section"][$key] = $value;
        if ($this->autoSave) $this->save();
    }

    public function removeSection($section)
    {
        if (!$this->disabled) parent::removeSection($section);
    }

    public function sections()
    {
        $names = [];
        foreach ($this->data as $name => $values) {
            if ("$name" !== '') $names[] = "$name";
        }
        return $names;
    }
}
