<?php

namespace Kuria\Dom;

abstract class DomContainerTest extends \PHPUnit_Framework_TestCase
{
    public function testIgnoreErrorsEnabled()
    {
        $dom = $this->createTestInstance();

        $this->assertFalse($dom->getIgnoreErrors()); // default should be false
        $this->assertTrue($dom->setIgnoreErrors(true)->getIgnoreErrors());

        $dom->load($this->getBrokenSample());

        $brokenSampleExpression = $this->getBrokenSampleExpression();
        if (null !== $brokenSampleExpression) {
            $this->assertInstanceOf('DOMNode', $dom->queryOne($this->getBrokenSampleExpression()));
        }
    }

    /**
     * @expectedException PHPUnit_Framework_Error_Warning
     */
    public function testIgnoreErrorsDisabled()
    {
        $dom = $this->createTestInstance();

        $this->assertFalse($dom->getIgnoreErrors()); // default should be false

        $dom->load($this->getBrokenSample());
    }

    public function testPreserveWhitespaceConfig()
    {
        $dom = $this->createTestInstance();

        $this->assertTrue($dom->getPreserveWhitespace()); // default should be true

        // false
        $this->assertFalse($dom->setPreserveWhitespace(false)->getPreserveWhitespace());
        $dom->load($this->getTestSample());
        $this->assertFalse($dom->getDocument()->preserveWhiteSpace);
        $dom->clear();

        // true
        $this->assertTrue($dom->setPreserveWhitespace(true)->getPreserveWhitespace());
        $dom->load($this->getTestSample());
        $this->assertTrue($dom->getDocument()->preserveWhiteSpace);
    }

    public function testLibxmlFlagsConfig()
    {
        $dom = $this->createTestInstance();

        $this->assertSame(0, $dom->getLibxmlFlags()); // default should be 0
        $this->assertSame(LIBXML_NOENT, $dom->setLibxmlFlags(LIBXML_NOENT)->getLibxmlFlags());
    }

    /**
     * @expectedException        RuntimeException
     * @expectedExceptionMessage Document is not yet initialized
     */
    public function testExceptionOnUninitializedDocument()
    {
        $dom = $this->createTestInstance();

        $dom->getDocument();
    }

    public function testGetDocument()
    {
        $dom = $this->createTestInstance();

        $dom->load($this->getTestSample());

        $this->assertInstanceOf('DOMDocument', $dom->getDocument());
    }

    public function testGetXpath()
    {
        $dom = $this->createTestInstance();

        $dom->load($this->getTestSample());

        $this->assertInstanceOf('DOMXPath', $dom->getXpath());
    }

    public function testGetContent()
    {
        $dom = $this->createTestInstance();

        $dom->load($this->getTestSample());

        $content = $dom->getContent();

        $this->assertInternalType('string', $content);
        $this->assertGreaterThan(0, strlen($content));
    }

    public function testToString()
    {
        $dom = $this->createTestInstance();

        $dom->load($this->getTestSample());

        $content = (string) $dom;

        $this->assertInternalType('string', $content);
        $this->assertGreaterThan(0, strlen($content));
    }

    public function testToStringOnUninitializedDocument()
    {
        $dom = $this->createTestInstance();

        $this->assertSame('', (string) $dom);
    }

    public function testQueries()
    {
        $dom = $this->createTestInstance();

        $dom->load($this->getTestSample());

        $this->assertInstanceOf('DOMNodeList', $dom->query($this->getTestSampleExpression()));
        $this->assertInstanceOf('DOMNode', $dom->queryOne($this->getTestSampleExpression()));
        $this->assertNull($dom->queryOne($this->getTestSampleExpressionNonMatching()));
    }

    /**
     * @expectedException        RuntimeException
     * @expectedExceptionMessage Error while evaluating
     */
    public function testExceptionOnInvalidXpathQuery()
    {
        $dom = $this->createTestInstance();

        $dom->load($this->getTestSample());

        $dom->query('foo bar');
    }

    /**
     * Create test instance
     *
     * @return DomContainer
     */
    abstract protected function createTestInstance();

    /**
     * Get test content sample
     *
     * @return string
     */
    abstract protected function getTestSample();

    /**
     * Get broken content sample
     *
     * @return string
     */
    abstract protected function getBrokenSample();

    /**
     * Get XPath expression for the broken content sample
     *
     * @return string
     */
    abstract protected function getBrokenSampleExpression();

    /**
     * Get XPath expression for the test sample
     *
     * @return string
     */
    abstract protected function getTestSampleExpression();

    /**
     * Get non-matching XPath expression for the test sample
     *
     * @return string
     */
    abstract protected function getTestSampleExpressionNonMatching();
}
