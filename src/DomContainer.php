<?php

namespace Kuria\Dom;

/**
 * DOM container
 *
 * @author ShiraNai7 <shira.cz>
 */
abstract class DomContainer
{
    /** @var \DOMDocument|null */
    protected $document;
    /** @var \DOMXPath|null */
    protected $xpath;
    /** @var bool */
    protected $preserveWhitespace = true;
    /** @var bool */
    protected $ignoreErrors = false;
    /** @var int */
    protected $libxmlFlags = 0;

    /**
     * Convert to string
     *
     * @return string
     */
    public function __toString()
    {
        if ($this->document) {
            return (string) $this->getContent();
        } else {
            return '';
        }
    }

    /**
     * Clear document and xpath instances
     *
     * @return static
     */
    public function clear()
    {
        $this->document = null;
        $this->xpath = null;
    }

    /**
     * Get ignore errors
     *
     * @return bool
     */
    public function getIgnoreErrors()
    {
        return $this->ignoreErrors;
    }

    /**
     * Set ignore errors
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
     * Get preserve whitespace
     *
     * @return bool
     */
    public function getPreserveWhitespace()
    {
        return $this->preserveWhitespace;
    }

    /**
     * Set preserve whitespace
     *
     * @param bool $preserveWhitespace
     * @return static
     */
    public function setPreserveWhitespace($preserveWhitespace)
    {
        $this->preserveWhitespace = $preserveWhitespace;

        return $this;
    }

    /**
     * Get libxml flags
     *
     * @return int
     */
    public function getLibxmlFlags()
    {
        return $this->libxmlFlags;
    }

    /**
     * Set libxml flags
     *
     * @param int $libxmlFlags
     * @return static
     */
    public function setLibxmlFlags($libxmlFlags)
    {
        $this->libxmlFlags = $libxmlFlags;

        return $this;
    }

    /**
     * Get document
     *
     * @throws \RuntimeException if the document is not yet initialized
     * @return \DOMDocument
     */
    public function getDocument()
    {
        if (null === $this->document) {
            throw new \RuntimeException('Document is not yet initialized');
        }

        return $this->document;
    }

    /**
     * Get xpath
     *
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
     * Create XPath instance
     *
     * @return \DOMXPath
     */
    protected function createXpath()
    {
        return new \DOMXPath($this->getDocument());
    }

    /**
     * Load content
     *
     * @param string $content the content
     * @return static
     */
    public function load($content)
    {
        $this->clear();

        $this->document = new \DOMDocument();
        $this->document->preserveWhiteSpace = $this->preserveWhitespace;
        $this->document->strictErrorChecking = !$this->ignoreErrors;

        $this->initialize($content);

        return $this;
    }

    /**
     * Populate document with given content
     *
     * @param string $content
     */
    abstract protected function initialize($content);

    /**
     * Get content as a string
     *
     * @return string
     */
    abstract public function getContent();

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
                throw new \RuntimeException('XPath query error - invalid expression or context node');
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
}
