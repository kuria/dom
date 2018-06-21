<?php declare(strict_types=1);

namespace Kuria\Dom;

abstract class DomContainer
{
    /** @see http://php.net/manual/en/intro.dom.php */
    const INTERNAL_ENCODING = 'UTF-8';

    /** @var \DOMDocument|null */
    private $document;

    /** @var \DOMXPath|null */
    private $xpath;

    /** @var bool */
    private $ignoreErrors = false;

    /** @var int */
    private $libxmlFlags = 0;

    /**
     * Create container from a string
     *
     * @see DomContainer::loadString()
     * @return static
     */
    static function fromString(string $content, ?string $encoding = null, ?array $documentProps = null)
    {
        $dom = new static();
        $dom->loadString($content, $encoding, $documentProps);

        return $dom;
    }

    /**
     * Create container using existing DOM instances
     *
     * @see DomContainer::loadDocument()
     * @return static
     */
    static function fromDocument(\DOMDocument $document, ?\DOMXPath $xpath = null)
    {
        $dom = new static();
        $dom->loadDocument($document, $xpath);

        return $dom;
    }

    function __sleep()
    {
        throw new \LogicException(sprintf(
            '%s cannot be serialized because PHP\'s DOM objects are not serializable',
            static::class
        ));
    }

    /**
     * Clear the document and xpath instances
     */
    function clear()
    {
        $this->document = null;
        $this->xpath = null;
    }

    /**
     * See if the container is initialized
     */
    function isLoaded(): bool
    {
        return $this->document !== null;
    }

    /**
     * See if load errors are ignored
     */
    function isIgnoringErrors(): bool
    {
        return $this->ignoreErrors;
    }

    /**
     * Set whether load errors should be ignored
     */
    function setIgnoreErrors(bool $ignoreErrors): void
    {
        $this->ignoreErrors = $ignoreErrors;
    }

    /**
     * Get configured libxml flags
     */
    function getLibxmlFlags(): int
    {
        return $this->libxmlFlags;
    }

    /**
     * Set one or more libxml flags
     *
     * The flags are added to the previously set flags unless $add is FALSE.
     */
    function setLibxmlFlags(int $libxmlFlags, bool $add = true): void
    {
        if ($add) {
            $this->libxmlFlags |= $libxmlFlags;
        } else {
            $this->libxmlFlags = $libxmlFlags;
        }
    }

    /**
     * Get the DOM document instance
     *
     * @throws \LogicException if the document hasn't been loaded yet
     */
    function getDocument(): \DOMDocument
    {
        if ($this->document === null) {
            throw new \LogicException('The document has not beed loaded yet');
        }

        return $this->document;
    }

    function getXpath(): \DOMXPath
    {
        if ($this->xpath === null) {
            $this->xpath = $this->createXpath();
        }

        return $this->xpath;
    }

    protected function createXpath(): \DOMXPath
    {
        return new \DOMXPath($this->getDocument());
    }

    /**
     * Load an empty document
     */
    abstract function loadEmpty(?array $documentProps = null): void;

    /**
     * Load document from a string
     *
     * The specified encoding may or may not be used, depending on the container's implementation.
     */
    function loadString(string $content, ?string $encoding = null, ?array $documentProps = null): void
    {
        $this->clear();

        $originalUseInternalErrors = libxml_use_internal_errors($this->ignoreErrors);

        try {
            $document = new \DOMDocument();

            if ($documentProps !== null) {
                foreach ($documentProps as $property => $value) {
                    $document->{$property} = $value;
                }
            }

            $this->populate($document, $content, $encoding);
            $this->document = $document;
        } catch (\Throwable $e) {
            $this->clear();

            throw $e;
        } finally {
            if ($originalUseInternalErrors !== $this->ignoreErrors) {
                // restore original config
                libxml_use_internal_errors($originalUseInternalErrors);
            }
        }
    }

    /**
     * Populate document with the given content
     */
    abstract protected function populate(\DOMDocument $document, string $content, ?string $encoding = null): void;

    /**
     * Load existing DOM instances
     */
    function loadDocument(\DOMDocument $document, ?\DOMXPath $xpath = null): void
    {
        $this->clear();

        $this->document = $document;
        $this->xpath = $xpath;
    }

    /**
     * Dump the document or its part into a string
     *
     * - if $contextNode is specified, only a subset of the document is returned
     * - if $contextNode is specified and $childrenOnly is TRUE, only children of the context node are returned
     */
    abstract function save(?\DOMNode $contextNode = null, bool $childrenOnly = false): string;

    /**
     * Escape the given string
     */
    abstract function escape(string $string): string;

    /**
     * Get document encoding
     *
     * - this encoding is used during save()
     * - the internal encoding is always UTF-8
     */
    function getEncoding(): string
    {
        return $this->getDocument()->encoding;
    }

    /**
     * Change document encoding
     *
     * - this encoding is used during save()
     * - has no effect on the internal encoding (which is always UTF-8)
     */
    function setEncoding(string $newEncoding): void
    {
        $this->getDocument()->encoding = $newEncoding;
    }

    /**
     * Perform an XPath query
     *
     * @throws \RuntimeException on invalid expression or context node
     * @return \DOMNodeList|\DOMNode[]
     */
    function query(string $expression, ?\DOMNode $contextNode = null, bool $registerNodeNs = true): \DOMNodeList
    {
        try {
            $result = $this->getXpath()->query($expression, $contextNode, $registerNodeNs);

            // make sure an exception is thrown on failure
            if ($result === false) {
                throw new \RuntimeException('Invalid expression or context node');
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException(sprintf('Error while evaluating XPath query "%s"', $expression), 0, $e);
        }

        return $result;
    }

    /**
     * Perform an XPath query and return the first result
     */
    function queryOne(string $expression, ?\DOMNode $contextNode = null, bool $registerNodeNs = true): ?\DOMNode
    {
        $nodes = $this->query($expression, $contextNode, $registerNodeNs);

        return $nodes->length > 0 ? $nodes->item(0) : null;
    }

    /**
     * Perform an XPath query and see if it has matched
     */
    function exists(string $expression, ?\DOMNode $contextNode = null, bool $registerNodeNs = true): bool
    {
        return $this->query($expression, $contextNode, $registerNodeNs)->length > 0;
    }

    /**
     * See if a node is descendant of another node
     *
     * If no parent node is given, the document is used in its place.
     */
    function contains(\DOMNode $node, ?\DOMNode $parentNode = null): bool
    {
        if ($parentNode === null) {
            $parentNode = $this->getDocument();
        }

        while ($node->parentNode !== null) {
            $node = $node->parentNode;

            if ($parentNode === $node) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove a node
     *
     * Returns the removed node.
     *
     * @throws \DOMException if the existing node has no parent
     */
    function remove(\DOMNode $node): void
    {
        if (!$node->parentNode) {
            throw new \DOMException('Cannot remove a node that has no parent');
        }

        $node->parentNode->removeChild($node);
    }

    /**
     * Safely purge a node list (in reverse)
     */
    function removeAll(\DOMNodeList $nodes): void
    {
        for ($i = $nodes->length - 1; $i >= 0; --$i) {
            $node = $nodes->item($i);
            $node->parentNode->removeChild($node);
        }
    }

    /**
     * Prepend a node to the given existing node
     */
    function prependChild(\DOMNode $newNode, \DOMNode $existingNode): void
    {
        if ($existingNode->firstChild) {
            $existingNode->insertBefore($newNode, $existingNode->firstChild);
        } else {
            $existingNode->appendChild($newNode);
        }
    }

    /**
     * Insert a node after the given existing node
     */
    function insertAfter(\DOMNode $newNode, \DOMNode $existingNode): void
    {
        $existingNode->parentNode->insertBefore($newNode, $existingNode->nextSibling);
    }
}
