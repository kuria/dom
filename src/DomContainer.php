<?php

namespace Kuria\Dom;

/**
 * DOM container
 *
 * @author ShiraNai7 <shira.cz>
 */
abstract class DomContainer
{
    /** http://php.net/manual/en/intro.dom.php */
    const INTERNAL_ENCODING = 'UTF-8';

    /** @var \DOMDocument|null */
    protected $document;
    /** @var \DOMXPath|null */
    protected $xpath;
    /** @var bool */
    protected $ignoreErrors = false;
    /** @var int */
    protected $libxmlFlags = 0;

    /**
     * Clear the document and xpath instances
     */
    public function clear()
    {
        $this->document = null;
        $this->xpath = null;
    }

    /**
     * See if the container is initialized
     *
     * @return bool
     */
    public function isLoaded()
    {
        return null !== $this->document;
    }

    /**
     * See if load errors are ignored
     *
     * @return bool
     */
    public function getIgnoreErrors()
    {
        return $this->ignoreErrors;
    }

    /**
     * Set whether load errors should be ignored
     *
     * @param bool $ignoreErrors
     * @return static
     */
    public function setIgnoreErrors($ignoreErrors)
    {
        $this->ignoreErrors = $ignoreErrors;

        return $this;
    }

    /**
     * Get configured libxml flags
     *
     * @return int
     */
    public function getLibxmlFlags()
    {
        return $this->libxmlFlags;
    }

    /**
     * Set one or more libxml flags
     *
     * The flags are added to the previously set flags unless $add is FALSE.
     *
     * @param int  $libxmlFlags
     * @param bool $add
     * @return static
     */
    public function setLibxmlFlags($libxmlFlags, $add = true)
    {
        if ($add) {
            $this->libxmlFlags |= $libxmlFlags;
        } else {
            $this->libxmlFlags = $libxmlFlags;
        }

        return $this;
    }

    /**
     * Get the DOM document instance
     *
     * If the the document hasn't been intialized yet, an empty document will be loaded - {@see loadEmpty()}.
     *
     * @return \DOMDocument
     */
    public function getDocument()
    {
        if (null === $this->document) {
            $this->loadEmpty();
        }

        return $this->document;
    }

    /**
     * @return \DOMXPath
     */
    public function getXpath()
    {
        if (null === $this->xpath) {
            $this->xpath = $this->createXpath();
        }

        return $this->xpath;
    }

    /**
     * @return \DOMXPath
     */
    protected function createXpath()
    {
        return new \DOMXPath($this->getDocument());
    }

    /**
     * Load an empty document
     *
     * @param array|null $properties optional map of DOMDocument properties to set before loading
     * @return static
     */
    abstract public function loadEmpty(array $properties = null);

    /**
     * Load document from a string
     *
     * The specified encoding may or may not be used, depending on the
     * container's implementation.
     *
     * @param string      $content    the content
     * @param string|null $encoding   encoding of the content, if known
     * @param array|null  $properties optional map of DOMDocument properties to set before loading
     * @return static
     */
    public function loadString($content, $encoding = null, array $properties = null)
    {
        $this->clear();
        
        $originalUseInternalErrors = libxml_use_internal_errors($this->ignoreErrors);

        $e = null;
        try {
            $this->document = new \DOMDocument();

            if (null !== $properties) {
                foreach ($properties as $property => $value) {
                    $this->document->{$property} = $value;
                }
            }

            $this->populate($content, $encoding);
        } catch (\Exception $e) {
        } catch (\Throwable $e) {
        }

        if ($originalUseInternalErrors !== $this->ignoreErrors) {
            // restore original config
            libxml_use_internal_errors($originalUseInternalErrors);
        }

        if (null !== $e) {
            $this->clear();

            throw $e;
        }

        return $this;
    }

    /**
     * Populate the document with the given content
     *
     * @param string      $content
     * @param string|null $encoding
     */
    abstract protected function populate($content, $encoding = null);

    /**
     * Use an existing DOMDocument instance
     *
     * @param \DOMDocument   $document document instance
     * @param \DOMXPath|null $xpath    xpath instance, if already created
     * @return static
     */
    public function loadDocument(\DOMDocument $document, \DOMXPath $xpath = null)
    {
        $this->clear();

        $this->document = $document;
        $this->xpath = $xpath;

        return $this;
    }

    /**
     * Get content as a string
     *
     * @param \DOMNode|null $contextNode  output only a subset of the document
     * @param bool          $childrenOnly output only children of the context node
     * @return string
     */
    abstract public function save(\DOMNode $contextNode = null, $childrenOnly = false);

    /**
     * Escape the given string
     *
     * @param string $string
     * @return string
     */
    abstract public function escape($string);

    /**
     * Get document encoding
     *
     *  - this encoding is used during save()
     *  - the internal encoding is always UTF-8
     *
     * @return string
     */
    public function getEncoding()
    {
        return $this->getDocument()->encoding;
    }

    /**
     * Change document encoding
     *
     *  - this encoding is used during save()
     *  - has no effect on the internal encoding (which is always UTF-8)
     *
     * @param string $newEncoding
     * @return static
     */
    public function setEncoding($newEncoding)
    {
        $this->getDocument()->encoding = $newEncoding;

        return $this;
    }

    /**
     * Perform a XPath query
     *
     * @param string        $expression     the XPath expression
     * @param \DOMNode|null $contextNode    specify node for relative XPath query
     * @param bool          $registerNodeNs register context node 1/0
     * @throws \RuntimeException on invalid expression or context node
     * @return \DOMNodeList
     */
    public function query($expression, \DOMNode $contextNode = null, $registerNodeNs = true)
    {
        try {
            $result = $this->getXpath()->query($expression, $contextNode, $registerNodeNs);

            // make sure an exception is thrown on failure
            // @codeCoverageIgnoreStart
            if (false === $result) {
                throw new \RuntimeException('Invalid expression or context node');
            }
            // @codeCoverageIgnoreEnd
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf('Error while evaluating XPath query "%s"', $expression), 0, $e);
        }

        return $result;
    }

    /**
     * Perform a XPath query and return the first result
     *
     * @param string        $expression     the XPath expression
     * @param \DOMNode|null $contextNode    specify node for relative XPath query
     * @param bool          $registerNodeNs register context node 1/0
     * @return \DOMNode|null
     */
    public function queryOne($expression, \DOMNode $contextNode = null, $registerNodeNs = true)
    {
        $nodes = $this->query($expression, $contextNode, $registerNodeNs);

        if ($nodes->length > 0) {
            return $nodes->item(0);
        }
    }

    /**
     * See if a node is descendant of another node
     *
     * If no parent node is given, the document is used in its place.
     *
     * @param \DOMNode      $node
     * @param \DOMNode|null $parentNode
     * @return bool
     */
    public function contains(\DOMNode $node, \DOMNode $parentNode = null)
    {
        if (null === $parentNode) {
            $parentNode = $this->getDocument();
        }

        while (null !== $node->parentNode) {
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
     * @param \DOMNode $existingNode
     * @throws \DOMException if the existing node has no parent
     * @return \DOMNode the removed node
     */
    public function remove(\DOMNode $existingNode)
    {
        if (!$existingNode->parentNode) {
            throw new \DOMException('Cannot remove a node that has no parent');
        }

        return $existingNode->parentNode->removeChild($existingNode);
    }

    /**
     * Safely purge a node list (in reverse)
     *
     * @param \DOMNodeList $nodes
     */
    public function removeAll(\DOMNodeList $nodes)
    {
        for ($i = $nodes->length - 1; $i >= 0; --$i) {
            $node = $nodes->item($i);
            $node->parentNode->removeChild($node);
        }
    }

    /**
     * Prepend a node to the given existing node
     *
     * @param \DOMNode $newNode
     * @param \DOMNode $existingNode
     * @return \DOMNode the inserted node
     */
    public function prependChild(\DOMNode $newNode, \DOMNode $existingNode)
    {
        return $existingNode->firstChild
            ? $existingNode->insertBefore($newNode, $existingNode->firstChild)
            : $existingNode->appendChild($newNode);
    }

    /**
     * Insert a node after the given existing node
     *
     * @param \DOMNode $newNode
     * @param \DOMNode $existingNode
     * @return \DOMNode the inserted node
     */
    public function insertAfter(\DOMNode $newNode, \DOMNode $existingNode)
    {
        return $existingNode->parentNode->insertBefore($newNode, $existingNode->nextSibling);
    }
}
