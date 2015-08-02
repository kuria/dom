<?php

namespace Kuria\Dom;

class XmlDocumentTest extends DomContainerTest
{
    protected function initializeOptions()
    {
        return array(
            'encoded_string_element_query' => '/list/item[@id="1"]/alias',
            'context_node_query' => '/list/item[@id="1"]',
            'query' => '//alias',
            'query.expected_results' => 3,
            'context_query' => './alias',
            'context_query.expected_results' => 2,
            'remove_query' => '/list/item[@id="2"]',
            'prepend_child_target_query' => '/list',
            'insert_after_target_query' => '/list/item[@id="1"]',
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

    protected function assertValidOutput($output, $encoding = DomContainer::INTERNAL_ENCODING)
    {
        $this->assertRegExp(sprintf('~<?xml[^>]*encoding="%s"~i', preg_quote($encoding, '~')), $output);
        $this->assertContains('<list>', $output, '', true);
        $this->assertContains('<item id="1">', $output, '', true);
        $this->assertContains('<item id="2">', $output, '', true);
        $this->assertContains('<alias>', $output, '', true);
        $this->assertContains('<position>', $output, '', true);
    }

    protected function assertValidOutputWithContextNode($output, $encoding = DomContainer::INTERNAL_ENCODING)
    {
        $this->assertNotContains('<?xml', $output, '', true);
        $this->assertNotContains('<list>', $output, '', true);
        $this->assertNotContains('<item id="2">', $output, '', true);
        $this->assertContains('<item id="1">', $output, '', true);
        $this->assertContains('<alias>', $output, '', true);
        $this->assertContains('<position>', $output, '', true);
    }

    protected function assertValidOutputWithContextNodeChildrenOnly($output, $encoding = DomContainer::INTERNAL_ENCODING)
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

    protected function getSampleContent($encoding = DomContainer::INTERNAL_ENCODING)
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

    protected function getInvalidSampleContent($encoding = DomContainer::INTERNAL_ENCODING)
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
