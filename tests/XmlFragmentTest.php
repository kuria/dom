<?php declare(strict_types=1);

namespace Kuria\Dom;

class XmlFragmentTest extends DomContainerTest
{
    protected function initializeOptions(): array
    {
        return [
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
        ];
    }

    function testEscape()
    {
        $dom = $this->createContainer();

        $this->assertSame(
            '&lt;item url=&quot;http://example.com/?foo=bar&amp;amp;lorem=ipsum&quot;&gt;Test&lt;/item&gt;',
            $dom->escape('<item url="http://example.com/?foo=bar&amp;lorem=ipsum">Test</item>')
        );
    }

    function testGetRoot()
    {
        /** @var XmlFragment $dom */
        $dom = $this->getContainer();

        $this->assertInstanceOf('DOMElement', $dom->getRoot());
        $this->assertSame('root', $dom->getRoot()->tagName);
    }

    protected function assertValidMinimalOutput(string $output, string $encoding = DomContainer::INTERNAL_ENCODING): void
    {
        $this->assertNotContains('<?xml', $output, '', true);
    }

    protected function assertValidSampleOutput(string $output, string $encoding = DomContainer::INTERNAL_ENCODING): void
    {
        $this->assertContains('<item id="1">', $output, '', true);
        $this->assertContains('<item id="2">', $output, '', true);
        $this->assertContains('<alias>', $output, '', true);
        $this->assertContains('<position>', $output, '', true);
    }

    protected function assertValidEmptyOutput(string $output, string $encoding = DomContainer::INTERNAL_ENCODING): void
    {
        $this->assertSame('', trim($output));
    }

    protected function assertValidOutputWithContextNode(string $output, string $encoding = DomContainer::INTERNAL_ENCODING): void
    {
        $this->assertNotContains('<?xml', $output, '', true);
        $this->assertNotContains('<item id="2">', $output, '', true);
        $this->assertContains('<item id="1">', $output, '', true);
        $this->assertContains('<alias>', $output, '', true);
        $this->assertContains('<position>', $output, '', true);
    }

    protected function assertValidOutputWithContextNodeChildrenOnly(string $output, string $encoding = DomContainer::INTERNAL_ENCODING)
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

    protected function getSampleContent(string $encoding = DomContainer::INTERNAL_ENCODING): string
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

    protected function getInvalidSampleContent(string $encoding = DomContainer::INTERNAL_ENCODING): string
    {
        return <<<XML
<item>
    <alias>{$this->getEncodedTestString($encoding)}</alias <!-- the syntax error is here -->
    <position>3</position>
</item>
XML;
    }
}
