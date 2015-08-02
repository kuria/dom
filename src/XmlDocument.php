<?php

namespace Kuria\Dom;

/**
 * XML document
 *
 * @author ShiraNai7 <shira.cz>
 */
class XmlDocument extends DomContainer
{
    public function escape($string)
    {
        return htmlspecialchars(
            $string,
            PHP_VERSION_ID >= 50400
                ? ENT_QUOTES | ENT_XML1
                : ENT_QUOTES,
            static::INTERNAL_ENCODING
        );
    }

    protected function populate($content, $encoding = null)
    {
        $this->document->loadXML($content, $this->libxmlFlags);
    }

    public function save(\DOMNode $contextNode = null, $childrenOnly = false)
    {
        $document = $this->getDocument();

        if (null === $contextNode || !$childrenOnly) {
            $content = $document->saveXML($contextNode);
        } else {
            $content = '';
            foreach ($contextNode->childNodes as $node) {
                $content .= $document->saveXML($node);
            }
        }

        return $content;
    }
}
