<?php
namespace script\storage;

use php\io\File;
use php\io\IOException;
use php\io\Stream;
use php\lib\str;

/**
 * Common key/section lifecycle for authored structured storage providers.
 * Codecs validate their own syntax before replacing stored data.
 * @packages framework
 */
abstract class StructuredStorage extends AbstractStorage
{
    protected $_path;
    private $codecErrorPath;
    public $prettyPrint = true;

    public function __construct($path = null)
    {
        $this->_path = $path;
        if ($path) $this->load();
    }

    protected function value($value, $depth = 0)
    {
        if ($depth >= 128 || is_object($value) || is_resource($value)) {
            throw new \Exception('Storage values must be scalars or arrays with nesting below 128');
        }
        if (is_float($value) && !is_finite($value)) throw new \Exception('Invalid storage number');
        if (!is_array($value)) return $value;
        $result = [];
        foreach ($value as $key => $item) $result[$key] = $this->value($item, $depth + 1);
        return $result;
    }

    protected function sectionName($section) { return "$section"; }
    protected function convert($value, $section) { return $this->value($value); }
    abstract protected function decodeDocument($text);
    abstract protected function encodeDocument(array $data);

    public function load()
    {
        if (!$this->_path || $this->disabled) return false;
        try {
            $text = str::decode(Stream::getContents($this->_path), 'UTF-8');
            try { $this->data = $this->decodeDocument($text); $this->codecErrorPath = null; }
            catch (\Exception $e) { $this->codecErrorPath = $this->_path; throw $e; }
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
            if ($this->codecErrorPath === $this->_path) throw new \Exception('Storage file could not be decoded; load a valid document before saving');
            $text = $this->encodeDocument($this->data);
            // Stage beside the destination; a failed write/rename leaves the previous file intact.
            $file = new File($this->_path);
            $temporary = File::createTemp('.storage-', '.tmp', $file->getAbsoluteFile()->getParent());
            Stream::putContents($temporary->getPath(), str::encode($text, 'UTF-8'));
            if (!$temporary->renameTo($file->getAbsolutePath(), true)) throw new IOException('Cannot replace storage file');
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
        return $this->disabled ? null : ($this->data[$this->sectionName($section)][$key] ?? null);
    }

    public function set($key, $value, $section = '', $checkAutoSave = true)
    {
        if ($this->disabled) return;
        try { $value = $this->convert($value, $this->sectionName($section)); }
        catch (\Exception $e) { $this->trigger('error', ['error' => $e]); return; }
        $this->data[$this->sectionName($section)][$key] = $value;
        if ($checkAutoSave && $this->autoSave) $this->save();
    }

    public function put(array $values, $section = '')
    {
        if ($this->disabled) return;
        try {
            $converted = [];
            foreach ($values as $key => $value) $converted[$key] = $this->convert($value, $this->sectionName($section));
            $values = $converted;
        }
        catch (\Exception $e) { $this->trigger('error', ['error' => $e]); return; }
        if (!isset($this->data[$this->sectionName($section)])) $this->data[$this->sectionName($section)] = [];
        foreach ($values as $key => $value) $this->data[$this->sectionName($section)][$key] = $value;
        if ($this->autoSave) $this->save();
    }

    public function section($name = '') { return parent::section($this->sectionName($name)); }
    public function remove($key, $section = '') { parent::remove($key, $this->sectionName($section)); }

    public function removeSection($section)
    {
        if (!$this->disabled) parent::removeSection($this->sectionName($section));
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
