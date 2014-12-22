<?php

namespace Kuria\Dom;

/**
 * XML fragment
 *
 * @author ShiraNai7 <shira.cz>
 */
class XmlFragment extends XmlDocument
{
    protected function initialize($content)
    {
        parent::initialize("<?xml version=\"1.0\" encoding=\"UTF-8\"?><root>{$content}</root>");
    }

    public function getContent()
    {
        $content = '';
        $document = $this->getDocument();

        foreach ($this->getXpath()->query('/root')->item(0)->childNodes as $item) {
            $content .= $document->saveXML($item);
        }

        return $content;
    }

    public function query($expression, \DOMNode $contextNode = null, $registerNodeNs = true)
    {
        // if no context node has been given, assume <root>
        if (null === $contextNode) {
            $contextNode = $this->getDocument()->getElementsByTagName('root')->item(0);

            // make sure the query is relative to the context node
            if ($contextNode && '' !== $expression && '.' !== $expression[0]) {
                $expression = '.' . $expression;
            }
        }

        return parent::query($expression, $contextNode, $registerNodeNs);
    }
}
