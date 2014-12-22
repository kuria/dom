<?php

namespace Kuria\Dom;

/**
 * HTML fragment
 *
 * @author ShiraNai7 <shira.cz>
 */
class HtmlFragment extends HtmlDocument
{
    protected function initialize($content)
    {
        parent::initialize("<!doctype html>
<html>
<head></head>
<body>{$content}</body>
</html>");
    }

    public function getContent()
    {
        $content = '';
        $document = $this->getDocument();

        foreach ($this->getXpath()->query('/html/body')->item(0)->childNodes as $item) {
            $content .= $document->saveXML($item);
        }

        return $content;
    }

    public function query($expression, \DOMNode $contextNode = null, $registerNodeNs = true)
    {
        // if no context node has been given, assume <body>
        if (null === $contextNode) {
            $contextNode = $this->getDocument()->getElementsByTagName('body')->item(0);

            // make sure the query is relative to the context node
            if ($contextNode && '' !== $expression && '.' !== $expression[0]) {
                $expression = '.' . $expression;
            }
        }

        return parent::query($expression, $contextNode, $registerNodeNs);
    }
}
