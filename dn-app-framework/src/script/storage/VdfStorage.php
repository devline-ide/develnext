<?php
namespace script\storage;
use php\lib\str;

/**
 * Vdf storage with the shared key/section API.
 * @packages framework
 */
class VdfStorage extends StructuredStorage
{
    public $rootName = 'Config';
    public $escapeSequences = false;

    protected function sectionName($section)
    {
        $name = "$section" === '' ? $this->rootName : "$section";
        $this->key($name);
        return $name;
    }
    private function key($key)
    {
        if ("$key" === '' || str::startsWith("$key", '#') || str::contains("$key", "\0")) throw new \Exception('VDF keys must be nonempty and cannot start with #');
    }
    private function quoted($value)
    {
        if (str::contains($value, "\0") || (!$this->escapeSequences && str::contains($value, '"'))) throw new \Exception('VDF quotes require escapeSequences; null bytes are unsupported');
        if ($this->escapeSequences) {
            $value = str::replace($value, '\\', '\\\\'); $value = str::replace($value, '"', '\\"');
            $value = str::replace($value, "\n", '\\n'); $value = str::replace($value, "\r", '\\r'); $value = str::replace($value, "\t", '\\t');
        }
        return '"' . $value . '"';
    }
    protected function convert($value, $section) { return $this->strings($this->value($value)); }
    private function strings($value, $depth = 0)
    {
        if ($depth >= 128 || $value === null) throw new \Exception('VDF requires string/block values with nesting below 128');
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) { $this->key($key); $result[$key] = $this->strings($item, $depth + 1); }
            return $result;
        }
        $value = is_bool($value) ? ($value ? '1' : '0') : "$value";
        $this->quoted($value);
        return $value;
    }
    private function token($text, &$position, &$tokens)
    {
        $length = str::length($text);
        while ($position < $length) {
            $c = str::sub($text, $position, $position + 1);
            if (str::contains(" \t\r\n", $c)) { $position++; continue; }
            if (str::sub($text, $position, $position + 2) === '//') {
                while ($position < $length && str::sub($text, $position, $position + 1) !== "\n") $position++;
                continue;
            }
            break;
        }
        if ($position >= $length) return null;
        if (++$tokens > 100000) throw new \Exception('Oversized VDF document');
        $c = str::sub($text, $position, $position + 1); $position++;
        if ($c === '{' || $c === '}') return ['text'=>$c, 'brace'=>true];
        if ($c === '"') {
            $value = '';
            while ($position < $length) {
                $c = str::sub($text, $position, $position + 1); $position++;
                if ($c === '"') return ['text'=>$value, 'brace'=>false];
                if ($c === '\\' && $this->escapeSequences) {
                    if ($position >= $length) throw new \Exception('Incomplete VDF escape');
                    $c = str::sub($text, $position, $position + 1); $position++;
                    $escapes = ['n'=>"\n", 'r'=>"\r", 't'=>"\t", '\\'=>'\\', '"'=>'"'];
                    if (!isset($escapes[$c])) throw new \Exception('Unsupported VDF escape');
                    $value .= $escapes[$c];
                } else $value .= $c;
            }
            throw new \Exception('Unclosed VDF quote');
        }
        $value = $c;
        while ($position < $length) {
            $c = str::sub($text, $position, $position + 1);
            if (str::contains(" \t\r\n{}\"", $c)) break;
            $value .= $c; $position++;
        }
        if (str::startsWith($value, '[')) throw new \Exception('Conditional VDF tokens require a provider');
        return ['text'=>$value, 'brace'=>false];
    }
    private function block($text, &$position, &$tokens, $depth, $nested)
    {
        if ($depth >= 128) throw new \Exception('VDF nesting is too deep');
        $result = []; $names = [];
        while (true) {
            $name = $this->token($text, $position, $tokens);
            if ($name === null) { if ($nested) throw new \Exception('Unclosed VDF block'); return $result; }
            if ($name['brace'] && $name['text'] === '}') {
                if (!$nested) throw new \Exception('Unexpected VDF closing brace');
                return $result;
            }
            if ($name['brace']) throw new \Exception('Expected VDF key');
            $this->key($name['text']); $folded = str::lower($name['text']);
            if (isset($names[$folded])) throw new \Exception('Duplicate VDF key: ' . $name['text']);
            $names[$folded] = true;
            $value = $this->token($text, $position, $tokens);
            if ($value === null || ($value['brace'] && $value['text'] !== '{')) throw new \Exception('Missing VDF value');
            $result[$name['text']] = $value['brace'] ? $this->block($text, $position, $tokens, $depth + 1, true) : $value['text'];
        }
    }
    protected function decodeDocument($text)
    {
        if (str::contains($text, "\0")) throw new \Exception('Binary VDF is unsupported');
        $position = str::startsWith($text, "﻿") ? 1 : 0; $tokens = 0;
        $root = $this->block($text, $position, $tokens, 0, false);
        foreach ($root as $value) { if (!is_array($value)) throw new \Exception('VDF storage requires named root blocks'); }
        return $root;
    }
    private function writeBlock(array $entries, $depth)
    {
        if ($depth >= 128) throw new \Exception('VDF nesting is too deep');
        $result = ''; $names = [];
        foreach ($entries as $name => $value) {
            $this->key($name); $folded = str::lower("$name");
            if (isset($names[$folded])) throw new \Exception('Duplicate VDF key: ' . $name);
            $names[$folded] = true;
            $indent = $this->prettyPrint ? str::repeat("\t", $depth) : '';
            $result .= $indent . $this->quoted("$name");
            if (is_array($value)) $result .= "\n" . $indent . "{\n" . $this->writeBlock($value, $depth + 1) . $indent . "}\n";
            else $result .= "\t" . $this->quoted($value) . "\n";
        }
        return $result;
    }
    protected function encodeDocument(array $data) { return $this->writeBlock($data, 0); }
}
