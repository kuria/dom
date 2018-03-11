<?php declare(strict_types=1);

namespace Kuria\Dom;

class XmlDocumentTest extends DomContainerTest
{
    protected function initializeOptions(): array
    {
        return [
            'encoded_string_element_query' => '/list/item[@id="1"]/alias',
            'context_node_query' => '/list/item[@id="1"]',
            'query' => '//alias',
            'query.expected_results' => 3,
            'context_query' => './alias',
            'context_query.expected_results' => 2,
            'remove_query' => '/list/item[@id="2"]',
            'prepend_child_target_query' => '/list',
            'insert_after_target_query' => '/list/item[@id="1"]',
            'remove_all_target_query' => '/list',
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
        $this->assertSame('list', $dom->getRoot()->tagName);
    }

    function testExceptionOnMissingRoot()
    {
        /** @var XmlFragment $dom */
        $dom = $this->createContainer();
        $dom->loadEmpty();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The root element was not found');

        $dom->getRoot();
    }

    protected function assertValidMinimalOutput(string $output, string $encoding = DomContainer::INTERNAL_ENCODING): void
    {
        $this->assertRegExp(sprintf('~<\?xml[^>]*encoding="%s"~i', preg_quote($encoding, '~')), $output);
    }

    protected function assertValidSampleOutput(string $output, string $encoding = DomContainer::INTERNAL_ENCODING): void
    {
        $this->assertContains('<list>', $output, '', true);
        $this->assertContains('<item id="1">', $output, '', true);
        $this->assertContains('<item id="2">', $output, '', true);
        $this->assertContains('<alias>', $output, '', true);
        $this->assertContains('<position>', $output, '', true);
    }

    protected function assertValidEmptyOutput(string $output, string $encoding = DomContainer::INTERNAL_ENCODING): void
    {
        $this->assertRegExp('~^<\?xml[^>]*\?>\s*$~', $output);
    }

    protected function assertValidOutputWithContextNode(string $output, string $encoding = DomContainer::INTERNAL_ENCODING): void
    {
        $this->assertNotContains('<?xml', $output, '', true);
        $this->assertNotContains('<list>', $output, '', true);
        $this->assertNotContains('<item id="2">', $output, '', true);
        $this->assertContains('<item id="1">', $output, '', true);
        $this->assertContains('<alias>', $output, '', true);
        $this->assertContains('<position>', $output, '', true);
    }

    protected function assertValidOutputWithContextNodeChildrenOnly(string $output, string $encoding = DomContainer::INTERNAL_ENCODING): void
    {
        $this->assertNotContains('<?xml', $output, '', true);
        $this->assertNotContains('<list>', $output, '', true);
        $this->assertNotContains('<item id="1">', $output, '', true);
        $this->assertNotContains('<item id="2">', $output, '', true);
        $this->assertContains('<alias>', $output, '', true);
        $this->assertContains('<position>', $output, '', true);
    }

    protected function createContainer()
    {
        return new XmlDocument();
    }

    protected function getSampleContent(string $encoding = DomContainer::INTERNAL_ENCODING): string
    {
        return <<<XML
<?xml version="1.0" encoding="{$encoding}"?>
<list>
    <item id="1">
        <alias>{$this->getEncodedTestString($encoding)}</alias>
        <alias>Lorem</alias>
        <position>3</position>
    </item>
    <item id="2">
        <alias>Foo</alias>
        <position>3</position>
    </item>
</list>
XML;
    }

    protected function getInvalidSampleContent(string $encoding = DomContainer::INTERNAL_ENCODING): string
    {
        return <<<XML
<?xml version="1.0" encoding="{$encoding}"?>
<list>
    <item>
        <alias>{$this->getEncodedTestString($encoding)}</alias <!-- the syntax error is here -->
        <position>3</position>
    </item>
</list>
XML;
    }
}
