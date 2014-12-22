<?php

namespace Kuria\Dom;

class HtmlDocumentTest extends DomContainerTest
{
    protected function createTestInstance()
    {
        return new HtmlDocument();
    }

    protected function getTestSample()
    {
        return '<!doctype html>
<html>
<head>
    <title>Lorem ipsum</title>
</head>
<body>
    <h1>Lorem ipsum</h1>
    <p>Lorem ipsum dolor sit amet</p>
</body>
</html>';
    }

    protected function getBrokenSample()
    {
        return '<!doctype html><div';
    }

    protected function getBrokenSampleExpression()
    {
        return '//div';
    }

    protected function getTestSampleExpression()
    {
        return '//h1';
    }

    protected function getTestSampleExpressionNonMatching()
    {
        return '//div';
    }

    public function testAutoUtf8Config()
    {
        $dom = new HtmlDocument();

        $this->assertTrue($dom->isAutoUtf8Enabled()); // default should be true

        $this->assertFalse($dom->setAutoUtf8Enabled(false)->isAutoUtf8Enabled());
        $this->assertTrue($dom->setAutoUtf8Enabled(true)->isAutoUtf8Enabled());
    }

    public function provideAutoUtf8MetaInsertionSamples()
    {
        return array(
            // nice document with full structure
            array('<!doctype html>
<html>
<head>
    <title>Lorem ipsum</title>
</head>
<body>
    <h1>Hello</h1>
</body>
</html>'),

            // missing <head> tag
            array('<!doctype html>
<html>
<body>
    <h1>Hello</h1>
</body>
</html>'),

            // missing <html> tag
            array('<!doctype html>
<body>
    <h1>Hello</h1>
</body>'),

            // missing doctype
            array('<body><h1>Hello</h1></body>'),
        );
    }

    /**
     * @dataProvider provideAutoUtf8MetaInsertionSamples
     */
    public function testAutoUtf8MetaInsertion($sample)
    {
        $dom = new HtmlDocument();

        $dom->load($sample);

        $this->assertNotNull($dom->queryOne('/html/head/meta[@charset = "UTF-8"]'));
        $this->assertNotNull($dom->queryOne('/html/head/meta[@http-equiv and @content = "text/html; charset=UTF-8"]'));
    }

    public function testAutoUtf8ConversionUsingMetaCharset()
    {
        $dom = new HtmlDocument();

        $testStringUtf8 = 'foo-áž-bar';
        $testStringIso = mb_convert_encoding($testStringUtf8, 'ISO-8859-15', 'UTF-8');

        $dom->load('<!doctype html>
<html>
<head>
    <meta charset="iso-8859-15">
    <!-- the next meta tag is intentionally wrong to test that meta charset has precedence -->
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Lorem ipsum</title>
</head>
<body>
    <h1>' . $testStringIso . '</h1>
</body>');

        $this->assertSame($testStringUtf8, $dom->queryOne('//h1')->textContent);
    }

    public function testAutoUtf8ConversionUsingMetaHttpEquiv()
    {
        $dom = new HtmlDocument();

        $testStringUtf8 = 'foo-áž-bar';
        $testStringIso = mb_convert_encoding($testStringUtf8, 'ISO-8859-15', 'UTF-8');

        $dom->load('<!doctype html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-15">
    <title>Lorem ipsum</title>
</head>
<body>
    <h1>' . $testStringIso . '</h1>
</body>');

        $this->assertSame($testStringUtf8, $dom->queryOne('//h1')->textContent);
    }

    public function testTidyConfiguration()
    {
        $dom = new HtmlDocument();

        $this->assertFalse($dom->isTidyEnabled()); // default should be false

        $this->assertTrue($dom->setTidyEnabled(true)->isTidyEnabled());
        $this->assertFalse($dom->setTidyEnabled(false)->isTidyEnabled());

        $this->assertSame(array(), $dom->getTidyConfig()); // default should be empty
        $this->assertSame(array('foo' => 'bar'), $dom->setTidyConfig(array('foo' => 'bar'))->getTidyConfig());
    }

    /**
     * @requires extension tidy
     */
    public function testTidy()
    {
        $dom = new HtmlDocument();

        $dom->setTidyEnabled(true);

        // if tidy works, no errors should be generated by this line
        $dom->load('<!doctype html><p class="test>Hi');
    }
}
