<?php

namespace Kuria\Dom;

class HtmlDocumentTest extends DomContainerTest
{
    protected function initializeOptions()
    {
        return array(
            'encoded_string_element_query' => '//h1',
            'context_node_query' => '//ul[@id="bar"]',
            'query' => '/html/body/ul/li',
            'query.expected_results' => 5,
            'context_query' => './li',
            'context_query.expected_results' => 3,
            'remove_query' => '//h1',
            'prepend_child_target_query' => '//body',
            'insert_after_target_query' => '//ul[@id="foo"]',
            'remove_all_target_query' => '//ul[@id="bar"]',
        );
    }

    public function testConfiguration()
    {
        /** @var HtmlDocument $dom */
        $dom = parent::testConfiguration();

        $this->assertTrue($dom->getHandleEncoding());
        $this->assertFalse($dom->isTidyEnabled());
        $this->assertEmpty($dom->getTidyConfig());

        return $dom;
    }

    public function testEscape()
    {
        $dom = $this->createContainer();

        $this->assertSame(
            '&lt;a href=&quot;http://example.com/?foo=bar&amp;amp;lorem=ipsum&quot;&gt;Test&lt;/a&gt;',
            $dom->escape('<a href="http://example.com/?foo=bar&amp;lorem=ipsum">Test</a>')
        );
    }
    
    public function testSetEncodingUpdatesExistingMetaHttpEquiv()
    {
        $dom = $this->getContainer($this->getOption('custom_encoding'));
        
        $httpEquivMeta= $dom->queryOne('/html/head/meta[@http-equiv="Content-Type"]');

        $this->assertNotNull($httpEquivMeta);
        $this->assertContains('charset=' . $this->getOption('custom_encoding'), $httpEquivMeta->attributes->getNamedItem('content')->nodeValue);
        
        $dom->setEncoding(DomContainer::INTERNAL_ENCODING);

        $this->assertContains('charset=' . DomContainer::INTERNAL_ENCODING, $httpEquivMeta->attributes->getNamedItem('content')->nodeValue);
    }
    
    public function testSetEncodingCreatesNewMetaHttpEquiv()
    {
        $dom = $this->createContainer();
        $dom->setHandleEncoding(false);
        $dom->loadString(<<<HTML
<!doctype html>
<title>Hello</title>
<h1>Test</h1>
HTML
        );

        $this->assertNull($dom->queryOne('/html/head/meta[@http-equiv="Content-Type"]'));

        $dom->setEncoding(DomContainer::INTERNAL_ENCODING);

        $httpEquivMeta= $dom->queryOne('/html/head/meta[@http-equiv="Content-Type"]');
        $this->assertNotNull($httpEquivMeta);
        $this->assertContains('charset=' . DomContainer::INTERNAL_ENCODING, $httpEquivMeta->attributes->getNamedItem('content')->nodeValue);
    }

    public function testSetEncodingRemovesMetaCharset()
    {
        $dom = $this->createContainer();
        $dom->setHandleEncoding(false);
        $dom->loadString(<<<HTML
<!doctype html>
<meta charset="UTF-8">
<title>Hello</title>
<h1>Test</h1>
HTML
        );

        $this->assertNotNull($dom->queryOne('/html/head/meta[@charset]'));
        $this->assertNull($dom->queryOne('/html/head/meta[@http-equiv="Content-Type"]'));

        $dom->setEncoding(DomContainer::INTERNAL_ENCODING);

        $this->assertNull($dom->queryOne('/html/head/meta[@charset]'));
        $this->assertNotNull($dom->queryOne('/html/head/meta[@http-equiv="Content-Type"]'));
    }
    
    /**
     * @requires extension tidy
     */
    public function testTidy()
    {
        $dom = $this->createContainer();

        $tidyConfig = array(
            'doctype' => 'loose',
            'drop-font-tags' => true,
        );
        
        $dom->setTidyEnabled(true);

        $dom->setTidyConfig($tidyConfig);
        $this->assertSame($tidyConfig, $dom->getTidyConfig());
        $dom->setTidyConfig(array('foo' => 'bar'));
        $this->assertEquals($tidyConfig + array('foo' => 'bar'), $dom->getTidyConfig());
        $dom->setTidyConfig($tidyConfig, false);
        $this->assertSame($tidyConfig, $dom->getTidyConfig());
        
        $dom->loadString(<<<HTML
<!doctype html>
<center>
    <p>Hello</p>
</center>
HTML
        );
       
        $this->assertNull($dom->queryOne('//center'));
        $this->assertNotNull($dom->queryOne('//p'));
    }

    public function testDisabledHandleEncoding()
    {
        $dom = $this->createContainer();

        $dom->setHandleEncoding(false);

        $dom->loadString(<<<HTML
<!doctype html>
<p>Hello</p>
HTML
        );

        $this->assertNull($dom->queryOne('//meta'));
    }

    public function testHandleEncodingKeepsValidMetaHttpEquiv()
    {
        $html = <<<HTML
<!doctype html>
<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-15">
HTML;

        HtmlDocument::handleEncoding($html);

        $this->assertContains('<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-15">', $html, '', true);
    }

    public function testHandleEncodingReplacesMetaHttpEquivIfDifferentKnownEncoding()
    {
        $html = <<<HTML
<!doctype html>
<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-15">
HTML;

        HtmlDocument::handleEncoding($html, 'UTF-8');

        $this->assertContains('<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">', $html, '', true);
    }

    public function testHandleEncodingConvertsMetaCharsetToHttpEquiv()
    {
        $html = <<<HTML
<!doctype html>
<meta charset="UTF-8">
HTML;

        HtmlDocument::handleEncoding($html);

        $this->assertContains('<meta http-equiv="Content-Type"', $html, '', true);
        $this->assertNotContains('<meta charset=', $html, '', true);
    }

    public function testHandleEncodingCreatesMetaHttpEquivInHead()
    {
        $html = <<<HTML
<!doctype html>
<head>
    <title>Hello</title>
</head>
HTML;

        HtmlDocument::handleEncoding($html);

        $headPosition = stripos($html, '<head>');
        $metaHttpEquivPosition = stripos($html, '<meta http-equiv="Content-Type"');

        $this->assertInternalType('integer', $headPosition);
        $this->assertInternalType('integer', $metaHttpEquivPosition);
        $this->assertGreaterThan($headPosition, $metaHttpEquivPosition);
    }

    public function testHandleEncodingCreatesMetaHttpEquivAfterDoctype()
    {
        $html = <<<HTML
<!doctype html>
<title>Hello</title>
HTML;

        HtmlDocument::handleEncoding($html);

        $doctypePosition = stripos($html, '<!doctype html>');
        $metaHttpEquivPosition = stripos($html, '<meta http-equiv="Content-Type"');

        $this->assertInternalType('integer', $doctypePosition);
        $this->assertInternalType('integer', $metaHttpEquivPosition);
        $this->assertGreaterThan($doctypePosition, $metaHttpEquivPosition);
    }

    public function testHandleEncodingCreatesMetaHttpEquivAtStart()
    {
        $html = <<<HTML
<title>Hello</title>
HTML;

        HtmlDocument::handleEncoding($html);

        $titlePosition = stripos($html, '<title>');
        $metaHttpEquivPosition = stripos($html, '<meta http-equiv="Content-Type"');

        $this->assertInternalType('integer', $titlePosition);
        $this->assertInternalType('integer', $metaHttpEquivPosition);
        $this->assertLessThan($titlePosition, $metaHttpEquivPosition);
    }

    protected function assertValidMinimalOutput($output, $encoding = DomContainer::INTERNAL_ENCODING)
    {
        $this->assertContains('<!doctype', $output, '', true);
        $this->assertContains('<html>', $output, '', true);
        $this->assertContains('<head>', $output, '', true);
        $this->assertRegExp(sprintf('~<meta http-equiv="Content-Type" content="text/html; charset=%s">~i', preg_quote($encoding, '~')), $output);
        $this->assertContains('<body>', $output, '', true);
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
        $this->assertRegExp('~<body>\s*</body>~', $output);
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
        return new HtmlDocument();
    }

    protected function getSampleContent($encoding = DomContainer::INTERNAL_ENCODING)
    {
        return <<<HTML
<!doctype html>
<html>
    <head>
        <meta charset="{$encoding}">
        <title>Sample content</title>
    </head>
    <body>
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
    </body>
</html>
HTML;
    }

    protected function getInvalidSampleContent($encoding = DomContainer::INTERNAL_ENCODING)
    {
        return <<<HTML
<!doctype html>
<h1>{$this->getEncodedTestString($encoding)}</h1 <!-- the syntax error is here -->
<p>
    Hello. This is the <strong>sample document</strong>!
</p>
HTML;
    }
}
