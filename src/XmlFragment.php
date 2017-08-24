<?php declare(strict_types=1);

namespace Kuria\Dom;

class XmlFragment extends XmlDocument
{
    function loadEmpty(array $properties = null): void
    {
        $this->loadString('', null, $properties);
    }

    protected function populate(string $content, ?string $encoding = null): void
    {
        if (!$encoding) {
            $encoding = static::INTERNAL_ENCODING;
        }

        parent::populate(
            <<<XML
<?xml version="1.0" encoding="{$this->escape($encoding)}"?>
<root>{$content}</root>
XML
            ,
            $encoding
        );
    }

    function save(\DOMNode $contextNode = null, bool $childrenOnly = false): string
    {
        if ($contextNode === null) {
            $contextNode = $this->getRoot();
            $childrenOnly = true;
        }

        return parent::save($contextNode, $childrenOnly);
    }

    function query(string $expression, \DOMNode $contextNode = null, bool $registerNodeNs = true): \DOMNodeList
    {
        // if no context node has been given, assume <root>
        if ($contextNode === null) {
            $contextNode = $this->getRoot();

            // make sure the query is relative to the context node
            if ($expression !== '' && $expression[0] !== '.') {
                $expression = '.' . $expression;
            }
        }

        return parent::query($expression, $contextNode, $registerNodeNs);
    }
}
