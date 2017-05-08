<?php

namespace Kuria\Dom;

class XmlFragmentTest extends DomContainerTest
{
    protected function initializeOptions()
    {
        return array(
            'is_fragment' => true,
            'encoded_string_element_query' => '/item[@id="1"]/alias',
            'context_node_query' => '/item[@id="1"]',
            'query' => '//alias',
            'query.expected_results' => 3,
            'context_query' => './alias',
            'context_query.expected_results' => 2,
            'remove_query' => '/item[@id="2"]',
            'prepend_child_target_query' => '.',
            'insert_after_target_query' => '/item[@id="1"]',
            'remove_all_target_query' => '/item[@id="2"]',
        );
    }

    public function testEscape()
    {
        $dom = $this->createContainer();

        $this->assertSame(
            '&lt;item url=&quot;http://example.com/?foo=bar&amp;amp;lorem=ipsum&quot;&gt;Test&lt;/item&gt;',
            $dom->escape('<item url="http://example.com/?foo=bar&amp;lorem=ipsum">Test</item>')
        );
    }

    public function testGetRoot()
    {
        /** @var XmlFragment $dom */
        $dom = $this->getContainer();

        $this->assertInstanceOf('DOMElement', $dom->getRoot());
        $this->assertSame('root', $dom->getRoot()->tagName);
    }

    protected function assertValidMinimalOutput($output, $encoding = DomContainer::INTERNAL_ENCODING)
    {
        $this->assertNotContains('<?xml', $output, '', true);
    }

    protected function assertValidSampleOutput($output, $encoding = DomContainer::INTERNAL_ENCODING)
    {
        $this->assertContains('<item id="1">', $output, '', true);
        $this->assertContains('<item id="2">', $output, '', true);
        $this->assertContains('<alias>', $output, '', true);
        $this->assertContains('<position>', $output, '', true);
    }

    protected function assertValidEmptyOutput($output, $encoding = DomContainer::INTERNAL_ENCODING)
    {
        $this->assertSame('', trim($output));
    }

    protected function assertValidOutputWithContextNode($output, $encoding = DomContainer::INTERNAL_ENCODING)
    {
        $this->assertNotContains('<?xml', $output, '', true);
        $this->assertNotContains('<item id="2">', $output, '', true);
        $this->assertContains('<item id="1">', $output, '', true);
        $this->assertContains('<alias>', $output, '', true);
        $this->assertContains('<position>', $output, '', true);
    }

    protected function assertValidOutputWithContextNodeChildrenOnly($output, $encoding = DomContainer::INTERNAL_ENCODING)
    {
        $this->assertNotContains('<?xml', $output, '', true);
        $this->assertNotContains('<item id="1">', $output, '', true);
        $this->assertNotContains('<item id="2">', $output, '', true);
        $this->assertContains('<alias>', $output, '', true);
        $this->assertContains('<position>', $output, '', true);
    }

    protected function createContainer()
    {
        return new XmlFragment();
    }

    protected function getSampleContent($encoding = DomContainer::INTERNAL_ENCODING)
    {
        return <<<XML
<item id="1">
    <alias>{$this->getEncodedTestString($encoding)}</alias>
    <alias>Lorem</alias>
    <position>3</position>
</item>
<item id="2">
    <alias>Foo</alias>
    <position>3</position>
</item>
XML;
    }

    protected function getInvalidSampleContent($encoding = DomContainer::INTERNAL_ENCODING)
    {
        return <<<XML
<item>
    <alias>{$this->getEncodedTestString($encoding)}</alias <!-- the syntax error is here -->
    <position>3</position>
</item>
XML;
    }
}
