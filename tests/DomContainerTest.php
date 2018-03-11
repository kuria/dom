<?php declare(strict_types=1);

namespace Kuria\Dom;

use PHPUnit\Framework\Error\Warning;
use PHPUnit\Framework\TestCase;

abstract class DomContainerTest extends TestCase
{
    /** @var array|null */
    private $options;

    protected function getOption(string $name)
    {
        if ($this->options === null) {
            $this->options = $this->initializeOptions() + [
                'is_fragment' => false,
                'custom_encoding' => 'iso-8859-15',
                'non_matching_query' => '/nonexistent',
            ];
        }

        if (!isset($this->options[$name])) {
            throw new \RuntimeException(sprintf('Undefined test option "%s"', $name));
        }

        return $this->options[$name];
    }

    abstract protected function initializeOptions(): array;

    function testConfiguration()
    {
        $dom = $this->createContainer();

        // defaults
        $this->assertFalse($dom->isIgnoringErrors());
        $this->assertSame(0, $dom->getLibxmlFlags());

        $dom->setIgnoreErrors(true);
        $dom->setLibxmlFlags(LIBXML_NOBLANKS);

        $this->assertTrue($dom->isIgnoringErrors());
        $this->assertSame(LIBXML_NOBLANKS, $dom->getLibxmlFlags());

        $dom->setLibxmlFlags(LIBXML_NOCDATA);

        $this->assertSame(LIBXML_NOBLANKS | LIBXML_NOCDATA, $dom->getLibxmlFlags());

        $dom->setLibxmlFlags(LIBXML_NOBLANKS, false);

        $this->assertSame(LIBXML_NOBLANKS, $dom->getLibxmlFlags());
    }

    function testAutomaticLoadEmptyOnUninitializedDocument()
    {
        $dom = $this->createContainer();

        $this->assertFalse($dom->isLoaded());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The document has not beed loaded yet');

        $dom->getDocument();
    }

    function testLoadEmpty()
    {
        $dom = $this->createContainer();

        $dom->loadEmpty();
        $this->assertValidContainer($dom);

        $output = $dom->save();

        $this->assertValidMinimalOutput($output);
        $this->assertValidEmptyOutput($output);
    }

    function testLoadString()
    {
        $dom = $this->createContainer();
        $dom->loadString($this->getSampleContent());
        $this->assertValidContainer($dom);
        $this->assertValidEncodedTestString($dom);
    }

    function testLoadStringProperties()
    {
        $defaultDom = new \DOMDocument();
        $this->assertFalse($defaultDom->resolveExternals);
        $this->assertFalse($defaultDom->recover);

        $dom = $this->createContainer();
        $dom->loadString($this->getSampleContent(), null, [
            'resolveExternals' => true,
            'recover' => true,
        ]);

        $this->assertTrue($dom->getDocument()->resolveExternals);
        $this->assertTrue($dom->getDocument()->recover);
    }

    function testLoadStringNonUtf8()
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

    function testFromString()
    {
        $dom = $this->createContainer()::fromString($this->getSampleContent());
        $this->assertValidContainer($dom);
        $this->assertValidEncodedTestString($dom);
    }

    function testLoadDocument()
    {
        $anotherDom = $this->getContainer();
        $document = $anotherDom->getDocument();
        $xpath = $anotherDom->getXpath();

        $dom = $this->createContainer();
        $dom->loadDocument($document, $xpath);

        $this->assertSame($document, $dom->getDocument());
        $this->assertSame($xpath, $dom->getXpath());
        $this->assertValidContainer($dom, $document->encoding);
        $this->assertValidEncodedTestString($dom);
    }

    function testFromDocument()
    {
        $anotherDom = $this->getContainer();
        $document = $anotherDom->getDocument();
        $xpath = $anotherDom->getXpath();

        $dom = $this->createContainer()::fromDocument($document, $xpath);

        $this->assertSame($document, $dom->getDocument());
        $this->assertSame($xpath, $dom->getXpath());
        $this->assertValidContainer($dom, $document->encoding);
        $this->assertValidEncodedTestString($dom);
    }

    function testIgnoreErrorsDisabled()
    {
        $dom = $this->createContainer();

        $this->expectException(Warning::class);
        $this->expectExceptionMessage('DOMDocument::');

        $dom->loadString($this->getInvalidSampleContent());
    }

    function testIgnoreErrorsEnabled()
    {
        $dom = $this->createContainer();
        $dom->setIgnoreErrors(true);
        $dom->loadString($this->getInvalidSampleContent());

        $this->assertTrue($dom->isLoaded());
    }

    abstract function testEscape();

    function testClear()
    {
        $dom = $this->getContainer();

        $this->assertTrue($dom->isLoaded());
        $dom->clear();
        $this->assertFalse($dom->isLoaded());
    }

    function testSave()
    {
        $dom = $this->getContainer();
        $context = $this->getContextNode($dom);

        $sampleOutput = $dom->save();

        $this->assertValidMinimalOutput($sampleOutput);
        $this->assertValidSampleOutput($sampleOutput);
        $this->assertValidOutputWithContextNode($dom->save($context));
        $this->assertValidOutputWithContextNodeChildrenOnly($dom->save($context, true));
    }

    function testEncoding()
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
     */
    abstract protected function assertValidMinimalOutput(string $output, string $encoding = DomContainer::INTERNAL_ENCODING): void;

    /**
     * Assert that the output contains the sample parts
     */
    abstract protected function assertValidSampleOutput(string $output, string $encoding = DomContainer::INTERNAL_ENCODING): void;

    /**
     * Assert that the output contains the default parts loaded by loadEmpty()
     */
    abstract protected function assertValidEmptyOutput(string $output, string $encoding = DomContainer::INTERNAL_ENCODING): void;

    /**
     * Assert that the output contains only the context node and its children
     */
    abstract protected function assertValidOutputWithContextNode(string $output, string $encoding = DomContainer::INTERNAL_ENCODING): void;

    /**
     * Assert that the output contains only children of the context node
     */
    abstract protected function assertValidOutputWithContextNodeChildrenOnly(string $output, string $encoding = DomContainer::INTERNAL_ENCODING);

    function testQuery()
    {
        $dom = $this->getContainer();

        $result = $dom->query($this->getOption('query'));
        $this->assertInstanceOf('DOMNodeList', $result);
        $this->assertSame($this->getOption('query.expected_results'), $result->length);

        $result = $dom->query($this->getOption('context_query'), $this->getContextNode($dom));
        $this->assertInstanceOf('DOMNodeList', $result);
        $this->assertSame($this->getOption('context_query.expected_results'), $result->length);
    }

    function testQueryOne()
    {
        $dom = $this->getContainer();

        $result = $dom->queryOne($this->getOption('query'));
        $this->assertInstanceOf('DOMNode', $result);

        $result = $dom->queryOne($this->getOption('context_query'), $this->getContextNode($dom));
        $this->assertInstanceOf('DOMNode', $result);
    }

    function testExceptionOnInvalidQuery()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Error while evaluating XPath query');

        $this->getContainer()->query('invalid ^ 123 456 query / - ... blah blah O_o');
    }

    function testExists()
    {
        $dom = $this->getContainer();

        $this->assertTrue($dom->exists($this->getOption('query')));
        $this->assertTrue($dom->exists($this->getOption('context_query'), $this->getContextNode($dom)));
        $this->assertFalse($dom->exists($this->getOption('non_matching_query')));
        $this->assertFalse($dom->exists($this->getOption('non_matching_query'), $this->getContextNode($dom)));
    }

    function testContainsAndRemove()
    {
        $dom = $this->getContainer();
        $nodeToRemove = $dom->queryOne($this->getOption('remove_query'));

        $this->assertNotNull($nodeToRemove);
        $this->assertTrue($dom->contains($nodeToRemove));
        $this->assertFalse($dom->contains(new \DOMElement('test')));
        $dom->remove($nodeToRemove);
        $this->assertFalse($dom->contains($nodeToRemove));
    }

    function testExceptionOnRemovingRootNode()
    {
        $dom = $this->getContainer();

        $this->expectException(\DOMException::class);
        $this->expectExceptionMessage('Cannot remove');

        $dom->remove($dom->getDocument());
    }

    function testPrependChild()
    {
        $dom = $this->getContainer();
        $newNode = new \DOMElement('test');
        $existingNode = $dom->queryOne($this->getOption('prepend_child_target_query'));

        $this->assertNotNull($existingNode);
        $dom->prependChild($newNode, $existingNode);
        $this->assertSame($newNode, $existingNode->firstChild);

        $dom->removeAll($existingNode->childNodes);
        $dom->prependChild($newNode, $existingNode);
        $this->assertSame($newNode, $existingNode->firstChild);
        $this->assertSame($newNode, $existingNode->lastChild);
    }

    function testInsertAfter()
    {
        $dom = $this->getContainer();
        $newNode = new \DOMElement('test');
        $existingNode = $dom->queryOne($this->getOption('insert_after_target_query'));

        $this->assertNotNull($existingNode);

        $dom->insertAfter($newNode, $existingNode);

        $this->assertSame($newNode, $existingNode->nextSibling);
    }

    function testRemoveAll()
    {
        $dom = $this->getContainer();

        $targetNode = $dom->queryOne($this->getOption('remove_all_target_query'));

        $this->assertGreaterThan(1, $targetNode->childNodes->length);

        $dom->removeAll($targetNode->childNodes);

        $this->assertSame(0, $targetNode->childNodes->length);
    }

    /**
     * Get already populated container
     */
    protected function getContainer(string $encoding = DomContainer::INTERNAL_ENCODING): DomContainer
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

    abstract protected function getSampleContent(string $encoding = DomContainer::INTERNAL_ENCODING): string;

    abstract protected function getInvalidSampleContent(string $encoding = DomContainer::INTERNAL_ENCODING): string;

    protected function getEncodedTestString(string $encoding = DomContainer::INTERNAL_ENCODING, string $string = 'Foo-áž-bar'): string
    {
        if (strcasecmp('UTF-8', $encoding) !== 0) {
            $string = mb_convert_encoding($string, $encoding, 'UTF-8');
        }

        return $string;
    }

    protected function assertValidContainer(DomContainer $dom, $expectedEncoding = DomContainer::INTERNAL_ENCODING)
    {
        $this->assertTrue($dom->isLoaded());
        $this->assertInstanceOf('DOMDocument', $dom->getDocument());
        $this->assertInstanceOf('DOMXPath', $dom->getXpath());
        $this->assertTrue(
            strcasecmp($actualEncoding = $dom->getEncoding(), $expectedEncoding) === 0,
            sprintf('getEncoding() should return "%s", but got "%s"', $actualEncoding, $expectedEncoding)
        );
    }

    protected function assertValidEncodedTestString(DomContainer $dom)
    {
        $this->assertNotNull($encodedStringElement = $dom->queryOne($this->getOption('encoded_string_element_query')));
        $this->assertSame($this->getEncodedTestString(), $encodedStringElement->textContent);
    }

    protected function getContextNode(DomContainer $dom): \DOMNode
    {
        $node = $dom->queryOne($this->getOption('context_node_query'));

        $this->assertNotNull($node);

        return $node;
    }
}
