<?php

namespace Kuria\Dom;

abstract class DomContainerTest extends \PHPUnit_Framework_TestCase
{
    /** @var array|null */
    private $options;

    protected function getOption($name)
    {
        if ($this->options === null) {
            $this->options = $this->initializeOptions() + array(
                'is_fragment' => false,
                'custom_encoding' => 'ISO-8859-15',
            );
        }

        if (!isset($this->options[$name])) {
            throw new \RuntimeException(sprintf('Undefined test option "%s"', $name));
        }

        return $this->options[$name];
    }
    
    /**
     * @return array
     */
    abstract protected function initializeOptions();

    /**
     * @return DomContainer
     */
    public function testConfiguration()
    {
        $dom = $this->createContainer();

        // defaults
        $this->assertFalse($dom->getIgnoreErrors());
        $this->assertSame(0, $dom->getLibxmlFlags());

        $this->assertTrue($dom->setIgnoreErrors(true)->getIgnoreErrors());
        $this->assertSame(LIBXML_NOBLANKS, $dom->setLibxmlFlags(LIBXML_NOBLANKS)->getLibxmlFlags());
        $this->assertSame(LIBXML_NOBLANKS | LIBXML_NOCDATA, $dom->setLibxmlFlags(LIBXML_NOCDATA)->getLibxmlFlags());
        $this->assertSame(LIBXML_NOBLANKS, $dom->setLibxmlFlags(LIBXML_NOBLANKS, false)->getLibxmlFlags());

        return $dom;
    }

    public function testAutomaticLoadEmptyOnUninitializedDocument()
    {
        $dom = $this->createContainer();

        $this->assertFalse($dom->isLoaded());

        $document = $dom->getDocument();

        $this->assertInstanceOf('DOMDocument', $document);

        $output = $dom->save();

        $this->assertValidMinimalOutput($output);
        $this->assertValidEmptyOutput($output);
    }

    public function testLoadEmpty()
    {
        $dom = $this->createContainer();

        $dom->loadEmpty();
        $this->assertValidContainer($dom);

        $output = $dom->save();

        $this->assertValidMinimalOutput($output);
        $this->assertValidEmptyOutput($output);
    }

    public function testLoadString()
    {
        $dom = $this->createContainer();
        $dom->loadString($this->getSampleContent());
        $this->assertValidContainer($dom);
        $this->assertValidEncodedTestString($dom);
    }

    public function testLoadStringProperties()
    {
        $defaultDom = new \DOMDocument();
        $this->assertFalse($defaultDom->resolveExternals);
        $this->assertFalse($defaultDom->recover);

        $dom = $this->createContainer();
        $dom->loadString($this->getSampleContent(), null, array(
            'resolveExternals' => true,
            'recover' => true,
        ));

        $this->assertTrue($dom->getDocument()->resolveExternals);
        $this->assertTrue($dom->getDocument()->recover);
    }

    public function testLoadStringNonUtf8()
    {
        $encoding = $this->getOption('custom_encoding');

        $dom = $this->createContainer();
        $dom->loadString(
            $this->getSampleContent($encoding),
            $this->getOption('is_fragment') ? $encoding : null
        );
        $this->assertValidContainer($dom, $encoding);
        $this->assertValidEncodedTestString($dom);
    }

    public function testLoadDocument()
    {
        $dom = $this->createContainer();
        
        $document = $this->getContainer()->getDocument();
        $dom->loadDocument($document);
        
        $this->assertValidContainer($dom, $document->encoding);
        $this->assertValidEncodedTestString($dom);
    }

    /**
     * @expectedException        PHPUnit_Framework_Error_Warning
     * @expectedExceptionMessage DOMDocument
     */
    public function testIgnoreErrorsDisabled()
    {
        $dom = $this->createContainer();
        $dom->loadString($this->getInvalidSampleContent());
    }

    public function testIgnoreErrorsEnabled()
    {
        $dom = $this->createContainer();
        $dom->setIgnoreErrors(true);
        $dom->loadString($this->getInvalidSampleContent());

        $this->assertTrue($dom->isLoaded());
    }

    abstract public function testEscape();

    public function testSave()
    {
        $dom = $this->getContainer();
        $context = $this->getContextNode($dom);

        $sampleOutput = $dom->save();

        $this->assertValidMinimalOutput($sampleOutput);
        $this->assertValidSampleOutput($sampleOutput);
        $this->assertValidOutputWithContextNode($dom->save($context));
        $this->assertValidOutputWithContextNodeChildrenOnly($dom->save($context, true));
    }

    public function testEncoding()
    {
        $dom = $this->getContainer($this->getOption('custom_encoding'));
        $output = $dom->save();

        $customEncoding = $this->getOption('custom_encoding');

        $this->assertValidMinimalOutput($output, $customEncoding);
        $this->assertValidSampleOutput($output, $customEncoding);

        $dom->setEncoding(DomContainer::INTERNAL_ENCODING);
        $output = $dom->save();

        $this->assertValidMinimalOutput($output);
        $this->assertValidSampleOutput($output);
    }

    /**
     * Assert that the output contains the required base parts
     *
     * (Does not apply to partial output with a context node.)
     *
     * @param string $output
     * @param string $encoding
     */
    abstract protected function assertValidMinimalOutput($output, $encoding = DomContainer::INTERNAL_ENCODING);

    /**
     * Assert that the output contains the sample parts
     *
     * @param string $output
     * @param string $encoding
     */
    abstract protected function assertValidSampleOutput($output, $encoding = DomContainer::INTERNAL_ENCODING);

    /**
     * Assert that the output contains the default parts loaded by loadEmpty()
     *
     * @param string $output
     * @param string $encoding
     */
    abstract protected function assertValidEmptyOutput($output, $encoding = DomContainer::INTERNAL_ENCODING);

    /**
     * Assert that the output contains only the context node and its children
     *
     * @param string $output
     * @param string $encoding
     */
    abstract protected function assertValidOutputWithContextNode($output, $encoding = DomContainer::INTERNAL_ENCODING);

    /**
     * Assert that the output contains only children of the context node
     *
     * @param string $output
     * @param string $encoding
     */
    abstract protected function assertValidOutputWithContextNodeChildrenOnly($output, $encoding = DomContainer::INTERNAL_ENCODING);

    public function testQuery()
    {
        $dom = $this->getContainer();

        $result = $dom->query($this->getOption('query'));
        $this->assertInstanceOf('DOMNodeList', $result);
        $this->assertSame($this->getOption('query.expected_results'), $result->length);
        
        $result = $dom->query($this->getOption('context_query'), $this->getContextNode($dom));
        $this->assertInstanceOf('DOMNodeList', $result);
        $this->assertSame($this->getOption('context_query.expected_results'), $result->length);
    }

    public function testQueryOne()
    {
        $dom = $this->getContainer();

        $result = $dom->queryOne($this->getOption('query'));
        $this->assertInstanceOf('DOMNode', $result);

        $result = $dom->queryOne($this->getOption('context_query'), $this->getContextNode($dom));
        $this->assertInstanceOf('DOMNode', $result);
    }

    /**
     * @expectedException        RuntimeException
     * @expectedExceptionMessage Error while evaluating XPath query
     */
    public function testExceptionOnInvalidQuery()
    {
        $this->getContainer()->query('invalid ^ 123 456 query / - ... blah blah O_o');
    }

    public function testClear()
    {
        $dom = $this->getContainer();

        $this->assertTrue($dom->isLoaded());
        $dom->clear();
        $this->assertFalse($dom->isLoaded());
    }

    public function testContainsAndRemove()
    {
        $dom = $this->getContainer();
        $nodeToRemove = $dom->queryOne($this->getOption('remove_query'));

        $this->assertNotNull($nodeToRemove);
        $this->assertTrue($dom->contains($nodeToRemove));
        $this->assertFalse($dom->contains(new \DOMElement('test')));
        $this->assertSame($nodeToRemove, $dom->remove($nodeToRemove));
        $this->assertFalse($dom->contains($nodeToRemove));
    }

    /**
     * @expectedException        DOMException
     * @expectedExceptionMessage Cannot remove
     */
    public function testExceptionOnRemovingRootNode()
    {
        $dom = $this->getContainer();

        $dom->remove($dom->getDocument());
    }

    public function testPrependChild()
    {
        $dom = $this->getContainer();
        $newNode = new \DOMElement('test');
        $existingNode = $dom->queryOne($this->getOption('prepend_child_target_query'));

        $this->assertNotNull($existingNode);
        $this->assertSame($newNode, $dom->prependChild($newNode, $existingNode));
        $this->assertSame($newNode, $existingNode->firstChild);
    }

    public function testInsertAfter()
    {
        $dom = $this->getContainer();
        $newNode = new \DOMElement('test');
        $existingNode = $dom->queryOne($this->getOption('insert_after_target_query'));

        $this->assertNotNull($existingNode);
        $this->assertSame($newNode, $dom->insertAfter($newNode, $existingNode));
        $this->assertSame($newNode, $existingNode->nextSibling);
    }

    public function testRemoveAll()
    {
        $dom = $this->getContainer();

        $targetNode = $dom->queryOne($this->getOption('remove_all_target_query'));

        $this->assertGreaterThan(1, $targetNode->childNodes->length);

        $dom->removeAll($targetNode->childNodes);

        $this->assertSame(0, $targetNode->childNodes->length);
    }

    /**
     * Get already populated container
     *
     * @param string $encoding
     * @return DomContainer
     */
    protected function getContainer($encoding = DomContainer::INTERNAL_ENCODING)
    {
        $dom = $this->createContainer();
        $dom->loadString(
            $this->getSampleContent($encoding),
            $this->getOption('is_fragment')
                ? $encoding
                : null
        );

        return $dom;
    }

    /**
     * Create a blank contaner
     *
     * @return DomContainer
     */
    abstract protected function createContainer();

    /**
     * @param string $encoding
     * @return string
     */
    abstract protected function getSampleContent($encoding = DomContainer::INTERNAL_ENCODING);

    /**
     * @param string $encoding
     * @return string
     */
    abstract protected function getInvalidSampleContent($encoding = DomContainer::INTERNAL_ENCODING);

    /**
     * @param string $encoding
     * @param string $string
     * @return string
     */
    protected function getEncodedTestString($encoding = DomContainer::INTERNAL_ENCODING, $string = 'Foo-áž-bar')
    {
        if (strcasecmp('UTF-8', $encoding) !== 0) {
            $string = mb_convert_encoding($string, $encoding, 'UTF-8');
        }

        return $string;
    }

    protected function assertValidContainer(DomContainer $dom, $encoding = DomContainer::INTERNAL_ENCODING)
    {
        $this->assertTrue($dom->isLoaded());
        $this->assertInstanceof('DOMDocument', $dom->getDocument());
        $this->assertInstanceof('DOMXPath', $dom->getXpath());
        $this->assertTrue(strcasecmp($returnedEncoding = $dom->getEncoding(), $encoding) === 0, sprintf('getEncoding() should return the used encoding ("%s" returned vs "%s" actual)', $returnedEncoding, $encoding));
    }

    protected function assertValidEncodedTestString(DomContainer $dom)
    {
        $this->assertNotNull($encodedStringElement = $dom->queryOne($this->getOption('encoded_string_element_query')));
        $this->assertSame($this->getEncodedTestString(), $encodedStringElement->textContent);
    }

    /**
     * @param DomContainer $dom
     * @return \DOMNode
     */
    protected function getContextNode(DomContainer $dom)
    {
        $node = $dom->queryOne($this->getOption('context_node_query'));

        $this->assertNotNull($node);

        return $node;
    }
}
