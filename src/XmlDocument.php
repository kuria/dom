<?php declare(strict_types=1);

namespace Kuria\Dom;

class XmlDocument extends DomContainer
{
    function escape(string $string): string
    {
        return htmlspecialchars(
            $string,
            ENT_QUOTES | ENT_XML1,
            static::INTERNAL_ENCODING
        );
    }

    function loadEmpty(?array $properties = null): void
    {
        try {
            $this->loadString(
                <<<XML
<?xml version="1.0" encoding="{$this->escape(static::INTERNAL_ENCODING)}"?>
<root />
XML
                ,
                null,
                $properties
            );

            // remove the dummy root node
            $this->removeAll($this->getDocument()->childNodes);
        } catch (\Throwable $e) {
            $this->clear();

            throw $e;
        }
    }

    protected function populate(\DOMDocument $document, string $content, ?string $encoding = null): void
    {
        $document->loadXML($content, $this->getLibxmlFlags());
    }

    function save(?\DOMNode $contextNode = null, bool $childrenOnly = false): string
    {
        $document = $this->getDocument();

        if ($contextNode === null || !$childrenOnly) {
            $content = $document->saveXML($contextNode);
        } else {
            $content = '';
            foreach ($contextNode->childNodes as $node) {
                $content .= $document->saveXML($node);
            }
        }

        return $content;
    }

    /**
     * Get the root element
     *
     * @throws \RuntimeException if the root element is not found
     */
    function getRoot(): \DOMElement
    {
        /** @var \DOMElement|null $root */
        $root = $this->getDocument()->firstChild;

        if (!$root) {
            throw new \RuntimeException('The root element was not found');
        }

        return $root;
    }
}
