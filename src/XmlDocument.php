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

    public function loadEmpty($encoding = null, array $properties = null)
    {
        if (null === $encoding) {
            $encoding = static::INTERNAL_ENCODING;
        }

        $e = null;
        try {
            $this->loadString(
                <<<XML
<?xml version="1.0" encoding="{$this->escape($encoding)}"?>
<root />
XML
                ,
                $properties
            );

            // remove the dummy root node
            $this->removeAll($this->getDocument()->childNodes);
        } catch (\Exception $e) {
        } catch (\Throwable $e) {
        }

        if (null !== $e) {
            $this->clear();

            throw $e;
        }

        return $this;
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
