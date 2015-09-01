<?php

namespace Kuria\Dom;

class SimpleHtmlParserTest extends \PHPUnit_Framework_TestCase
{
    public function testBasicGetters()
    {
        $parser = new SimpleHtmlParser('abc');

        $this->assertSame(3, $parser->getLength());
        $this->assertSame('abc', $parser->getHtml());
    }

    public function testMatchComment()
    {
        $this->matchAndAssert('<!-- foo bar -->', array(
            'type' => SimpleHtmlParser::COMMENT,
            'start' => 0,
            'end' => 16,
        ));
    }

    public function testMatchOpeningTag()
    {
        $this->matchAndAssert('<P>', array(
            'type' => SimpleHtmlParser::OPENING_TAG,
            'start' => 0,
            'end' => 3,
            'name' => 'p',
            'attrs' => array(),
        ));
    }

    public function testMatchOpeningTagWithAttributes()
    {
        $this->matchAndAssert('<A HREF="http://example.com?FOO" id="foo"  class=link >', array(
            'type' => SimpleHtmlParser::OPENING_TAG,
            'start' => 0,
            'end' => 55,
            'name' => 'a',
            'attrs' => array('href' => 'http://example.com?FOO', 'id' => 'foo', 'class' => 'link'),
        ));
    }

    public function testMatchSelfClosingTag()
    {
        $this->matchAndAssert('<hr />', array(
            'type' => SimpleHtmlParser::OPENING_TAG,
            'start' => 0,
            'end' => 6,
            'name' => 'hr',
            'attrs' => array(),
        ));
    }

    public function testMatchSelfClosingTagWithAttributes()
    {
        $this->matchAndAssert('<hr data-lorem="ipsum" />', array(
            'type' => SimpleHtmlParser::OPENING_TAG,
            'start' => 0,
            'end' => 25,
            'name' => 'hr',
            'attrs' => array('data-lorem' => 'ipsum'),
        ));
    }

    public function testMatchUnterminatedOpeningTag()
    {
        $this->matchAndAssert('<a href="http://example.com/"', array(
            'type' => SimpleHtmlParser::OPENING_TAG,
            'start' => 0,
            'end' => 29,
            'name' => 'a',
            'attrs' => array('href' => 'http://example.com/'),
        ));
    }

    public function testMatchUnterminatedOpeningTagFollowedByAnotherElement()
    {
        $this->matchAndAssert('<a href="http://example.com/"<br id="foo">', array(
            'type' => SimpleHtmlParser::OPENING_TAG,
            'start' => 0,
            'end' => 42,
            'name' => 'a',
            'attrs' => array('href' => 'http://example.com/', 'id' => 'foo', '<br' => true),
        ));
    }

    public function testMatchClosingTag()
    {
        $this->matchAndAssert('</A>', array(
            'type' => SimpleHtmlParser::CLOSING_TAG,
            'start' => 0,
            'end' => 4,
            'name' => 'a',
        ));
    }

    public function testMatchClosingTagWithAttributes()
    {
        $this->matchAndAssert('</A id="nonsense">', array(
            'type' => SimpleHtmlParser::CLOSING_TAG,
            'start' => 0,
            'end' => 18,
            'name' => 'a',
        ));
    }

    public function testMatchOther()
    {
        $this->matchAndAssert('<!doctype html>', array(
            'type' => SimpleHtmlParser::OTHER,
            'start' => 0,
            'end' => 15,
            'symbol' => '!',
        ));
        
        $this->matchAndAssert('<?= echo "Hi"; ?>', array(
            'type' => SimpleHtmlParser::OTHER,
            'start' => 0,
            'end' => 17,
            'symbol' => '?',
        ));
    }

    public function testMatchInvalid()
    {
        $this->matchAndAssertFailure('<');
        $this->matchAndAssertFailure('< foo');
        $this->matchAndAssertFailure('<-bar');
        $this->matchAndAssertFailure('<#');
        
        $this->matchAndAssert('<?', array(
            'type' => SimpleHtmlParser::INVALID,
            'start' => 0,
            'end' => 2,
        ));

        $this->matchAndAssert('<!', array(
            'type' => SimpleHtmlParser::INVALID,
            'start' => 0,
            'end' => 2,
        ));
    }

    public function testFind()
    {
        $html = <<<HTML
<!doctype html>
<!-- <title>Not a title</title> -->
<meta name="foo" value="first">
<title>Lorem ipsum</title>
<meta name="bar" value="second">
HTML;

        $parser = new SimpleHtmlParser($html);

        $this->assertElement($parser->find(SimpleHtmlParser::OPENING_TAG, 'title'), array(
            'type' => SimpleHtmlParser::OPENING_TAG,
            'start' => 84,
            'end' => 91,
            'name' => 'title',
        ));

        // find should work with and alter the iterator's position
        // finding any opening tag after the title should yield the second meta tag
        $this->assertElement($parser->find(SimpleHtmlParser::OPENING_TAG), array(
            'type' => SimpleHtmlParser::OPENING_TAG,
            'start' => 111,
            'end' => 143,
            'name' => 'meta',
            'attrs' => array('name' => 'bar', 'value' => 'second'),
        ));
    }

    public function testFindNonTags()
    {
        $html = <<<HTML
<!doctype html>
<title>Lorem ipsum</title>
<!-- foo bar -->
HTML;

        $parser = new SimpleHtmlParser($html);

        $this->assertElement($parser->find(SimpleHtmlParser::OTHER), array(
            'type' => SimpleHtmlParser::OTHER,
            'start' => 0,
            'end' => 15,
            'symbol' => '!',
        ));

        $this->assertElement($parser->find(SimpleHtmlParser::COMMENT), array(
            'type' => SimpleHtmlParser::COMMENT,
            'start' => 43,
            'end' => 59,
        ));
    }

    public function testFindStopOffsetMidElement()
    {
        $html = <<<HTML
<!-- foo bar -->
Lorem ipsum dolor sit amet<br>
HTML;

        $parser = new SimpleHtmlParser($html);

        $this->assertFalse($parser->find(SimpleHtmlParser::OPENING_TAG, 'br', 10));
    }

    public function testFindStopOffsetRightAfterElement()
    {
        $html = <<<HTML
<!-- foo bar -->
Lorem ipsum dolor sit amet<br>
HTML;

        $parser = new SimpleHtmlParser($html);

        $this->assertFalse($parser->find(SimpleHtmlParser::OPENING_TAG, 'br', 16));
    }

    public function testFindStopOffsetRightBetweenElements()
    {
        $html = <<<HTML
<!-- foo bar -->
Lorem ipsum dolor sit amet<br>
HTML;

        $parser = new SimpleHtmlParser($html);

        // find() can match elements after the stop offset in this case
        // this behavior is expected and documented
        $this->assertElement($parser->find(SimpleHtmlParser::OPENING_TAG, 'br', 28), array(
            'name' => 'br',
            'start' => 43,
        ));
    }

    public function testGetHtmlWithElement()
    {
        $html = <<<HTML
<!-- test link -->
<a href="http://example.com/">
<!-- the end -->
HTML;

        $parser = new SimpleHtmlParser($html);

        $element = $parser->find(SimpleHtmlParser::OPENING_TAG, 'a');

        $this->assertElement($element, array('name' => 'a'));
        $this->assertSame('<a href="http://example.com/">', $parser->getHtml($element));
    }

    /**
     * @expectedException        LogicException
     * @expectedExceptionMessage OPENING_TAG or CLOSING_TAG
     */
    public function testExceptionFromFindOnTagNameSpecifiedForNonTagType()
    {
        $parser = new SimpleHtmlParser('');

        $parser->find(SimpleHtmlParser::COMMENT, 'foo');
    }

    public function testIterator()
    {
        $html = <<<HTML
<!doctype html>
<!-- foo bar -->
<title>Lorem ipsum</title>
<script type="text/javascript">
    document.write("<h1>Lorem ipsum</h1>"); // should not be picked up as a real tag
</script>
<p <!-- invalid on purpose -->
    <a href="http://example.com">Click here</a>
</p>

Dolor sit amet
HTML;

        $expected = array(
            array('type' => SimpleHtmlParser::OTHER, 'start' => 0, 'end' => 15, 'symbol' => '!'),
            array('type' => SimpleHtmlParser::COMMENT, 'start' => 16, 'end' => 32),
            array('type' => SimpleHtmlParser::OPENING_TAG, 'start' => 33, 'end' => 40, 'name' => 'title', 'attrs' => array()),
            array('type' => SimpleHtmlParser::CLOSING_TAG, 'start' => 51, 'end' => 59, 'name' => 'title'),
            array('type' => SimpleHtmlParser::OPENING_TAG, 'start' => 60, 'end' => 91, 'name' => 'script', 'attrs' => array('type' => 'text/javascript')),
            array('type' => SimpleHtmlParser::CLOSING_TAG, 'start' => 177, 'end' => 186, 'name' => 'script'),
            array('type' => SimpleHtmlParser::OPENING_TAG, 'start' => 187, 'end' => 217, 'name' => 'p', 'attrs' => array('<!--' => true, 'invalid' => true, 'on' =>  true, 'purpose' => true, '--' => true)),
            array('type' => SimpleHtmlParser::OPENING_TAG, 'start' => 222, 'end' => 251, 'name' => 'a', 'attrs' => array('href' => 'http://example.com')),
            array('type' => SimpleHtmlParser::CLOSING_TAG, 'start' => 261, 'end' => 265, 'name' => 'a'),
            array('type' => SimpleHtmlParser::CLOSING_TAG, 'start' => 266, 'end' => 270, 'name' => 'p'),
        );

        $parser = new SimpleHtmlParser($html);

        $this->assertSame(0, $parser->getOffset());
        $this->assertSame(0, $parser->key());

        foreach ($parser as $index => $element) {
            $this->assertArrayHasKey($index, $expected, 'element index is out of bounds');
            try {
                $this->assertElement($element, $expected[$index]);
            } catch (\Exception $e) {
                throw new \PHPUnit_Framework_AssertionFailedError(sprintf('Failed to assert validity of element at index "%s"', $index), 0, $e);
            }
            $this->assertSame($element['end'], $parser->getOffset());
        }
    }

    public function testEmptyIterator()
    {
        $parser = new SimpleHtmlParser('No tags here, sorry.. :)');

        for ($i = 0; $i < 2; ++$i) {
            $this->assertSame(0, $parser->getOffset());
            $this->assertNull($parser->key());
            $this->assertFalse($parser->current());
            $this->assertFalse($parser->valid());

            $parser->rewind();
        }
    }

    public function testStates()
    {
        $html = <<<HTML
<!doctype html>
<title>Lorem ipsum</title>
<h1>Dolor sit amet</h1>
HTML;

        $parser = new SimpleHtmlParser($html);

        // initial state
        $this->assertSame(0, $parser->getOffset());
        $this->assertSame(0, $parser->getStateStackSize());

        $parser->pushState();
        $this->assertSame(1, $parser->getStateStackSize());

        $parser->next();

        // doctype
        $this->assertSame(15, $parser->getOffset());
        $this->assertElement($parser->current(), array(
            'type' => SimpleHtmlParser::OTHER,
            'symbol' => '!',
        ));

        $parser->pushState();
        $this->assertSame(2, $parser->getStateStackSize());

        $parser->next();

        // <title>
        $this->assertSame(23, $parser->getOffset());
        $this->assertElement($parser->current(), array(
            'type' => SimpleHtmlParser::OPENING_TAG,
            'name' => 'title',
        ));

        $parser->pushState();
        $this->assertSame(3, $parser->getStateStackSize());
        
        $parser->next();
        
        // </title>
        $this->assertSame(42, $parser->getOffset());
        $this->assertElement($parser->current(), array(
            'type' => SimpleHtmlParser::CLOSING_TAG,
            'name' => 'title',
        ));
        
        $parser->pushState();
        $this->assertSame(4, $parser->getStateStackSize());
        
        $parser->find(SimpleHtmlParser::CLOSING_TAG, 'h1');
        
        // </h1>
        $this->assertSame(66, $parser->getOffset());

        $parser->revertState();
        $this->assertSame(3, $parser->getStateStackSize());

        // reverted back to </title>
        $this->assertSame(42, $parser->getOffset());
        $this->assertElement($parser->current(), array(
            'type' => SimpleHtmlParser::CLOSING_TAG,
            'name' => 'title',
        ));

        $parser->popState(); // pop state @ <title>
        $this->assertSame(2, $parser->getStateStackSize());
        $this->assertSame(42, $parser->getOffset()); // popping sould not affect offset

        $parser->revertState();
        $this->assertSame(1, $parser->getStateStackSize());

        // reverted to doctype
        $this->assertSame(15, $parser->getOffset());
        $this->assertElement($parser->current(), array(
            'type' => SimpleHtmlParser::OTHER,
            'symbol' => '!',
        ));

        $parser->revertState();
        $this->assertSame(0, $parser->getStateStackSize());

        // reverted to the beginning
        $this->assertSame(0, $parser->getOffset());
    }

    public function testClearStates()
    {
        $parser = new SimpleHtmlParser('');

        $this->assertSame(0, $parser->getStateStackSize());

        for ($i = 1; $i <= 3; ++$i) {
            $parser->pushState();
            $this->assertSame($i, $parser->getStateStackSize());
        }

        $parser->clearStates();

        $this->assertSame(0, $parser->getStateStackSize());
    }

    /**
     * @expectedException        LogicException
     * @expectedExceptionMessage The state stack is empty
     */
    public function testExceptionOnPopStateWithEmptyStack()
    {
        $parser = new SimpleHtmlParser('');

        $this->assertSame(0, $parser->getStateStackSize());

        $parser->popState();
    }

    /**
     * @expectedException        LogicException
     * @expectedExceptionMessage The state stack is empty
     */
    public function testExceptionOnRevertStateWithEmptyStack()
    {
        $parser = new SimpleHtmlParser('');

        $this->assertSame(0, $parser->getStateStackSize());

        $parser->revertState();
    }

    public function testEscape()
    {
        $parser = new SimpleHtmlParser('');

        $this->assertSame(
            '&lt;a href=&quot;http://example.com/?foo=bar&amp;amp;lorem=ipsum&quot;&gt;Test&lt;/a&gt;',
            $parser->escape('<a href="http://example.com/?foo=bar&amp;lorem=ipsum">Test</a>')
        );
    }

    public function testGetDoctype()
    {
        $html = <<<HTML
<!-- foo bar -->
<!DOCTYPE html>
HTML;

        $parser = new SimpleHtmlParser($html);

        $this->assertElement($parser->getDoctypeElement(), array(
            'type' => SimpleHtmlParser::OTHER,
            'start' => 17,
            'end' => 32,
            'content' => 'DOCTYPE html',
        ));
    }

    public function testGetDoctypeNotFound()
    {
        $html = <<<HTML
<!-- foo bar -->
<title>Hello</title>
HTML;

        $parser = new SimpleHtmlParser($html);

        $this->assertNull($parser->getDoctypeElement());
    }

    public function testEncodingDefaultFallback()
    {
        $html = <<<HTML
<!doctype html>
<title>Foo bar</title>
HTML;

        $parser = new SimpleHtmlParser($html);

        $this->assertSame('UTF-8', $parser->getEncoding());
        $this->assertNull($parser->getEncodingTag());
    }

    public function testEncodingSpecifiedFallback()
    {
        $html = <<<HTML
<!doctype html>
<title>Foo bar</title>
HTML;

        $parser = new SimpleHtmlParser($html);

        $parser->setFallbackEncoding('ISO-8859-15');

        $this->assertNull($parser->getEncodingTag());
        $this->assertSame('ISO-8859-15', $parser->getEncoding());
    }

    /**
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage Unsupported fallback encoding
     */
    public function testExceptionOnUnsupportedFallbackEncoding()
    {
        $parser = new SimpleHtmlParser('');

        $parser->setFallbackEncoding('unknown');
    }

    public function testEncodingMetaCharset()
    {
        $html = <<<HTML
<!doctype html>
<META CharSet="WIN-1251">
<title>Foo bar</title>
HTML;

        $parser = new SimpleHtmlParser($html);

        $this->assertSame('WIN-1251', $parser->getEncoding());
        $this->assertElement($parser->getEncodingTag(), array(
            'type' => SimpleHtmlParser::OPENING_TAG,
            'start' => 16,
            'end' => 41,
            'name' => 'meta',
            'attrs' => array('charset' => 'WIN-1251'),
        ));
    }

    public function testEncodingMetaHttpEquiv()
    {
        $html = <<<HTML
<!doctype html>
<META Http-Equiv="content-type" Content="text/html; charset=WIN-1251">
<title>Foo bar</title>
HTML;

        $parser = new SimpleHtmlParser($html);

        $this->assertSame('WIN-1251', $parser->getEncoding());
        $this->assertElement($parser->getEncodingTag(), array(
            'type' => SimpleHtmlParser::OPENING_TAG,
            'start' => 16,
            'end' => 86,
            'name' => 'meta',
            'attrs' => array('http-equiv' => 'content-type', 'content' => 'text/html; charset=WIN-1251'),
        ));
    }

    public function testEncodingDetectionDoesNotAlterState()
    {
        $html = <<<HTML
<!doctype html>
<title>Foo bar</title>
HTML;

        $parser = new SimpleHtmlParser($html);

        $this->assertElement($parser->find(SimpleHtmlParser::OPENING_TAG, 'title'));
        $this->assertSame(23, $parser->getOffset());
        $this->assertSame(0, $parser->getStateStackSize());

        $this->assertSame('UTF-8', $parser->getEncoding());
        $this->assertNull($parser->getEncodingTag());

        $this->assertElement($parser->current());
        $this->assertSame(23, $parser->getOffset());
        $this->assertSame(0, $parser->getStateStackSize());
    }

    /**
     * @param string $html
     * @param array  $expectedKeys
     */
    private function matchAndAssert($html, array $expectedKeys)
    {
        $parser = new SimpleHtmlParser($html);

        $this->assertElement($parser->current(), $expectedKeys);
    }

    /**
     * @param string $html
     */
    private function matchAndAssertFailure($html)
    {
        $parser = new SimpleHtmlParser($html);

        $this->assertFalse($parser->current());
    }

    /**
     * @param array $element
     * @param array $expectedKeys
     */
    private function assertElement($element, array $expectedKeys = array())
    {
        $this->assertInternalType('array', $element);
        $this->assertArrayHasKey('type', $element);

        // check type and keys
        $keys = array('type', 'start', 'end');

        switch ($element['type']) {
            case SimpleHtmlParser::COMMENT:
            case SimpleHtmlParser::INVALID;
                // no extra attributes
                break;
            case SimpleHtmlParser::OPENING_TAG:
                $keys[] = 'name';
                $keys[] = 'attrs';
                break;
            case SimpleHtmlParser::CLOSING_TAG:
                $keys[] = 'name';
                break;
            case SimpleHtmlParser::OTHER:
                $keys[] = 'symbol';
                break;
            default:
                $this->fail(sprintf('Failed asserting that "%s" is a valid element type', $element['type']));
        }

        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $element);
        }

        $unknownKeys = array_diff(array_keys($element), $keys, array_keys($expectedKeys));

        if ($unknownKeys) {
            $this->fail(sprintf(
                'Failed asserting that element contains only known keys, found unknown key(s): %s',
                implode(', ', $unknownKeys)
            ));
        }

        // check expected keys
        foreach ($expectedKeys as $expectedKey => $expectedValue) {
            $this->assertArrayHasKey($expectedKey, $element);
            $this->assertEquals($expectedValue, $element[$expectedKey]);
        }
    }
}
