<?php
namespace script\storage;
use php\format\YamlProcessor;

/**
 * Yaml storage with the shared key/section API.
 * @packages framework
 */
class YamlStorage extends StructuredStorage
{
    private function processor()
    {
        return new YamlProcessor(YamlProcessor::STORAGE | ($this->prettyPrint ? YamlProcessor::SERIALIZE_BLOCK : YamlProcessor::SERIALIZE_FLOW));
    }
    protected function decodeDocument($text) { return $this->value($this->processor()->parse($text)); }
    protected function encodeDocument(array $data) { return $this->processor()->format($data); }
}
