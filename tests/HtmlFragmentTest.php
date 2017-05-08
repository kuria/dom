<?php

namespace Kuria\Dom;

class HtmlFragmentTest extends DomContainerTest
{
    protected function initializeOptions()
    {
        return array(
            'is_fragment' => true,
            'encoded_string_element_query' => '//h1',
            'context_node_query' => '//ul[@id="bar"]',
            'query' => '/ul/li',
            'query.expected_results' => 5,
            'context_query' => './li',
            'context_query.expected_results' => 3,
            'remove_query' => '//h1',
            'prepend_child_target_query' => '.',
            'insert_after_target_query' => '//ul[@id="foo"]',
            'remove_all_target_query' => '//ul[@id="bar"]',
        );
    }

    public function testEscape()
    {
        $dom = $this->createContainer();

        $this->assertSame(
            '&lt;a href=&quot;http://example.com/?foo=bar&amp;amp;lorem=ipsum&quot;&gt;Test&lt;/a&gt;',
            $dom->escape('<a href="http://example.com/?foo=bar&amp;lorem=ipsum">Test</a>')
        );
    }

    protected function assertValidMinimalOutput($output, $encoding = DomContainer::INTERNAL_ENCODING)
    {
        $this->assertNotContains('<!doctype', $output, '', true);
        $this->assertNotContains('<html>', $output, '', true);
        $this->assertNotContains('<head>', $output, '', true);
        $this->assertNotContains('<meta', $output, '', true);
        $this->assertNotContains('<body>', $output, '', true);
    }

    protected function assertValidSampleOutput($output, $encoding = DomContainer::INTERNAL_ENCODING)
    {
        $this->assertContains('<h1>', $output, '', true);
        $this->assertContains('<p>', $output, '', true);
        $this->assertContains('<ul id="foo">', $output, '', true);
        $this->assertContains('<ul id="bar">', $output, '', true);
        $this->assertContains('<li>', $output, '', true);
    }

    protected function assertValidEmptyOutput($output, $encoding = DomContainer::INTERNAL_ENCODING)
    {
        $this->assertSame('', trim($output));
    }

    protected function assertValidOutputWithContextNode($output, $encoding = DomContainer::INTERNAL_ENCODING)
    {
        $this->assertNotContains('<!doctype', $output, '', true);
        $this->assertNotContains('<html>', $output, '', true);
        $this->assertNotContains('<head>', $output, '', true);
        $this->assertNotContains('<meta', $output, '', true);
        $this->assertNotContains('<body>', $output, '', true);
        $this->assertNotContains('<h1>', $output, '', true);
        $this->assertNotContains('<p>', $output, '', true);
        $this->assertNotContains('<ul id="foo">', $output, '', true);
        $this->assertContains('<ul id="bar">', $output, '', true);
        $this->assertContains('<li>', $output, '', true);
    }

    protected function assertValidOutputWithContextNodeChildrenOnly($output, $encoding = DomContainer::INTERNAL_ENCODING)
    {
        $this->assertNotContains('<!doctype', $output, '', true);
        $this->assertNotContains('<html>', $output, '', true);
        $this->assertNotContains('<head>', $output, '', true);
        $this->assertNotContains('<meta', $output, '', true);
        $this->assertNotContains('<body>', $output, '', true);
        $this->assertNotContains('<h1>', $output, '', true);
        $this->assertNotContains('<p>', $output, '', true);
        $this->assertNotContains('<ul id="foo">', $output, '', true);
        $this->assertNotContains('<ul id="bar">', $output, '', true);
        $this->assertContains('<li>', $output, '', true);
    }

    protected function createContainer()
    {
        return new HtmlFragment();
    }

    protected function getSampleContent($encoding = DomContainer::INTERNAL_ENCODING)
    {
        return <<<HTML
<h1>{$this->getEncodedTestString($encoding)}</h1>
<p>
    Hello. This is the <strong>sample document</strong>!
</p>
<ul id="foo">
    <li>Lorem ipsum</li>
    <li>Dolor sit amet</li>
</ul>
<ul id="bar">
    <li>Condimentum velit</li>
    <li>Justo magnis</li>
    <li>Phasellus orci</li>
</ul>
HTML;
    }

    protected function getInvalidSampleContent($encoding = DomContainer::INTERNAL_ENCODING)
    {
        return <<<HTML
<h1>{$this->getEncodedTestString($encoding)}</h1 <!-- the syntax error is here -->
<p>
    Hello. This is the <strong>sample document</strong>!
</p>
HTML;
    }
}
