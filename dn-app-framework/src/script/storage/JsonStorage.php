<?php
namespace script\storage;
use php\format\JsonProcessor;

/**
 * Json storage with the shared key/section API.
 * @packages framework
 */
class JsonStorage extends StructuredStorage
{
    protected function decodeDocument($text)
    {
        $root = (new JsonProcessor())->parse($text);
        if (!($root instanceof \stdClass)) throw new \Exception('JSON storage root must be an object of sections');
        foreach ((array) $root as $section => $values) {
            if (!($values instanceof \stdClass)) throw new \Exception("JSON storage section must be an object: $section");
        }
        $loaded = (new JsonProcessor(JsonProcessor::DESERIALIZE_AS_ARRAYS))->parse($text);
        return $this->value($loaded);
    }
    protected function encodeDocument(array $data)
    {
        $sections = new \stdClass();
        foreach ($data as $name => $values) $sections->{"$name"} = (object) $values;
        $processor = new JsonProcessor(JsonProcessor::SERIALIZE_NULLS | ($this->prettyPrint ? JsonProcessor::SERIALIZE_PRETTY_PRINT : 0));
        return $processor->format($sections);
    }
}
