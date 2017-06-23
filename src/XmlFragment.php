<?php

namespace Kuria\Dom;

/**
 * XML fragment
 *
 * @author ShiraNai7 <shira.cz>
 */
class XmlFragment extends XmlDocument
{
    public function loadEmpty(array $properties = null)
    {
        return $this->loadString('', null, $properties);
    }

    protected function populate($content, $encoding = null)
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

    public function save(\DOMNode $contextNode = null, $childrenOnly = false)
    {
        if ($contextNode === null) {
            $contextNode = $this->getRoot();
            $childrenOnly = true;
        }

        return parent::save($contextNode, $childrenOnly);
    }

    public function query($expression, \DOMNode $contextNode = null, $registerNodeNs = true)
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
