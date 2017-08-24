<?php declare(strict_types=1);

namespace Kuria\Dom;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;

class SimpleHtmlParserTest extends TestCase
{
    function testBasicGetters()
    {
        $parser = new SimpleHtmlParser('abc');

        $this->assertSame(3, $parser->getLength());
        $this->assertSame('abc', $parser->getHtml());
    }

    function testMatchComment()
    {
        $this->matchAndAssert('<!-- foo bar -->', [
            'type' => SimpleHtmlParser::COMMENT,
            'start' => 0,
            'end' => 16,
        ]);
    }

    function testMatchOpeningTag()
    {
        $this->matchAndAssert('<P>', [
            'type' => SimpleHtmlParser::OPENING_TAG,
            'start' => 0,
            'end' => 3,
            'name' => 'p',
            'attrs' => [],
        ]);
    }

    function testMatchOpeningTagWithSpecialCharacters()
    {
        $html = '<Foo:barž>';

        $this->matchAndAssert($html, [
            'type' => SimpleHtmlParser::OPENING_TAG,
            'start' => 0,
            'end' => strlen($html),
            'name' => 'Foo:barž',
            'attrs' => [],
        ]);
    }

    function testMatchOpeningTagWithAttributes()
    {
        $this->matchAndAssert('<A HREF="http://example.com?FOO" id="foo"  class=link >', [
            'type' => SimpleHtmlParser::OPENING_TAG,
            'start' => 0,
            'end' => 55,
            'name' => 'a',
            'attrs' => ['href' => 'http://example.com?FOO', 'id' => 'foo', 'class' => 'link'],
        ]);
    }

    function testMatchSelfClosingTag()
    {
        $this->matchAndAssert('<hr />', [
            'type' => SimpleHtmlParser::OPENING_TAG,
            'start' => 0,
            'end' => 6,
            'name' => 'hr',
            'attrs' => [],
        ]);
    }

    function testMatchSelfClosingTagWithAttributes()
    {
        $this->matchAndAssert('<hr data-lorem="ipsum" />', [
            'type' => SimpleHtmlParser::OPENING_TAG,
            'start' => 0,
            'end' => 25,
            'name' => 'hr',
            'attrs' => ['data-lorem' => 'ipsum'],
        ]);
    }

    function testMatchUnterminatedOpeningTag()
    {
        $this->matchAndAssert('<a href="http://example.com/"', [
            'type' => SimpleHtmlParser::OPENING_TAG,
            'start' => 0,
            'end' => 29,
            'name' => 'a',
            'attrs' => ['href' => 'http://example.com/'],
        ]);
    }

    function testMatchUnterminatedOpeningTagFollowedByAnotherElement()
    {
        $this->matchAndAssert('<a href="http://example.com/"<br id="foo">', [
            'type' => SimpleHtmlParser::OPENING_TAG,
            'start' => 0,
            'end' => 42,
            'name' => 'a',
            'attrs' => ['href' => 'http://example.com/', 'id' => 'foo', '<br' => true],
        ]);
    }

    function testMatchClosingTag()
    {
        $this->matchAndAssert('</A>', [
            'type' => SimpleHtmlParser::CLOSING_TAG,
            'start' => 0,
            'end' => 4,
            'name' => 'a',
        ]);
    }

    function testMatchClosingTagWithSpecialCharacters()
    {
        $html = '</Foo-barž>';

        $this->matchAndAssert($html, [
            'type' => SimpleHtmlParser::CLOSING_TAG,
            'start' => 0,
            'end' => strlen($html),
            'name' => 'Foo-barž',
        ]);
    }

    function testMatchClosingTagWithAttributes()
    {
        $this->matchAndAssert('</A id="nonsense">', [
            'type' => SimpleHtmlParser::CLOSING_TAG,
            'start' => 0,
            'end' => 18,
            'name' => 'a',
        ]);
    }

    function testMatchOther()
    {
        $this->matchAndAssert('<!doctype html>', [
            'type' => SimpleHtmlParser::OTHER,
            'start' => 0,
            'end' => 15,
            'symbol' => '!',
        ]);
        
        $this->matchAndAssert('<?= echo "Hi"; ?>', [
            'type' => SimpleHtmlParser::OTHER,
            'start' => 0,
            'end' => 17,
            'symbol' => '?',
        ]);
    }

    function testMatchInvalid()
    {
        $this->matchAndAssertFailure('<');
        $this->matchAndAssertFailure('< foo');
        $this->matchAndAssertFailure('<+bar');
        $this->matchAndAssertFailure('<#');
        
        $this->matchAndAssert('<?', [
            'type' => SimpleHtmlParser::INVALID,
            'start' => 0,
            'end' => 2,
        ]);

        $this->matchAndAssert('<!', [
            'type' => SimpleHtmlParser::INVALID,
            'start' => 0,
            'end' => 2,
        ]);
    }

    function testFind()
    {
        $html = <<<HTML
<!doctype html>
<!-- <title>Not a title</title> -->
<meta name="foo" value="first">
<title>Lorem ipsum</title>
<meta name="bar" value="second">
HTML;

        $parser = new SimpleHtmlParser($html);

        $this->assertElement($parser->find(SimpleHtmlParser::OPENING_TAG, 'title'), [
            'type' => SimpleHtmlParser::OPENING_TAG,
            'start' => 84,
            'end' => 91,
            'name' => 'title',
        ]);

        // find should work with and alter the iterator's position
        // finding any opening tag after the title should yield the second meta tag
        $this->assertElement($parser->find(SimpleHtmlParser::OPENING_TAG), [
            'type' => SimpleHtmlParser::OPENING_TAG,
            'start' => 111,
            'end' => 143,
            'name' => 'meta',
            'attrs' => ['name' => 'bar', 'value' => 'second'],
        ]);
    }

    function testFindNonTags()
    {
        $html = <<<HTML
<!doctype html>
<title>Lorem ipsum</title>
<!-- foo bar -->
HTML;

        $parser = new SimpleHtmlParser($html);

        $this->assertElement($parser->find(SimpleHtmlParser::OTHER), [
            'type' => SimpleHtmlParser::OTHER,
            'start' => 0,
            'end' => 15,
            'symbol' => '!',
        ]);

        $this->assertElement($parser->find(SimpleHtmlParser::COMMENT), [
            'type' => SimpleHtmlParser::COMMENT,
            'start' => 43,
            'end' => 59,
        ]);
    }

    function testFindStopOffsetMidElement()
    {
        $html = <<<HTML
<!-- foo bar -->
Lorem ipsum dolor sit amet<br>
HTML;

        $parser = new SimpleHtmlParser($html);

        $this->assertNull($parser->find(SimpleHtmlParser::OPENING_TAG, 'br', 10));
    }

    function testFindStopOffsetRightAfterElement()
    {
        $html = <<<HTML
<!-- foo bar -->
Lorem ipsum dolor sit amet<br>
HTML;

        $parser = new SimpleHtmlParser($html);

        $this->assertNull($parser->find(SimpleHtmlParser::OPENING_TAG, 'br', 16));
    }

    function testFindStopOffsetRightBetweenElements()
    {
        $html = <<<HTML
<!-- foo bar -->
Lorem ipsum dolor sit amet<br>
HTML;

        $parser = new SimpleHtmlParser($html);

        // find() can match elements after the stop offset in this case
        // this behavior is expected and documented
        $this->assertElement($parser->find(SimpleHtmlParser::OPENING_TAG, 'br', 28), [
            'name' => 'br',
            'start' => 43,
        ]);
    }

    function testGetHtmlWithElement()
    {
        $html = <<<HTML
<!-- test link -->
<a href="http://example.com/">
<!-- the end -->
HTML;

        $parser = new SimpleHtmlParser($html);

        $element = $parser->find(SimpleHtmlParser::OPENING_TAG, 'a');

        $this->assertElement($element, ['name' => 'a']);
        $this->assertSame('<a href="http://example.com/">', $parser->getHtml($element));
    }

    function testExceptionFromFindOnTagNameSpecifiedForNonTagType()
    {
        $parser = new SimpleHtmlParser('');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('OPENING_TAG or CLOSING_TAG');

        $parser->find(SimpleHtmlParser::COMMENT, 'foo');
    }

    function testIterator()
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
<script type="text/javascript"> // unclosed on purpose
HTML;

        $expected = [
            ['type' => SimpleHtmlParser::OTHER, 'start' => 0, 'end' => 15, 'symbol' => '!'],
            ['type' => SimpleHtmlParser::COMMENT, 'start' => 16, 'end' => 32],
            ['type' => SimpleHtmlParser::OPENING_TAG, 'start' => 33, 'end' => 40, 'name' => 'title', 'attrs' => []],
            ['type' => SimpleHtmlParser::CLOSING_TAG, 'start' => 51, 'end' => 59, 'name' => 'title'],
            ['type' => SimpleHtmlParser::OPENING_TAG, 'start' => 60, 'end' => 91, 'name' => 'script', 'attrs' => ['type' => 'text/javascript']],
            ['type' => SimpleHtmlParser::CLOSING_TAG, 'start' => 177, 'end' => 186, 'name' => 'script'],
            ['type' => SimpleHtmlParser::OPENING_TAG, 'start' => 187, 'end' => 217, 'name' => 'p', 'attrs' => ['<!--' => true, 'invalid' => true, 'on' =>  true, 'purpose' => true, '--' => true]],
            ['type' => SimpleHtmlParser::OPENING_TAG, 'start' => 222, 'end' => 251, 'name' => 'a', 'attrs' => ['href' => 'http://example.com']],
            ['type' => SimpleHtmlParser::CLOSING_TAG, 'start' => 261, 'end' => 265, 'name' => 'a'],
            ['type' => SimpleHtmlParser::CLOSING_TAG, 'start' => 266, 'end' => 270, 'name' => 'p'],
            ['type' => SimpleHtmlParser::OPENING_TAG, 'start' => 287, 'end' => 318, 'name' => 'script'],
        ];

        $parser = new SimpleHtmlParser($html);

        $this->assertSame(0, $parser->getOffset());
        $this->assertSame(0, $parser->key());

        foreach ($parser as $index => $element) {
            $this->assertArrayHasKey($index, $expected, 'element index is out of bounds');
            try {
                $this->assertElement($element, $expected[$index]);
            } catch (\Exception $e) {
                throw new AssertionFailedError(sprintf('Failed to assert validity of element at index "%s"', $index), 0, $e);
            }
            $this->assertSame($element['end'], $parser->getOffset());
        }

        $this->assertFalse($parser->valid());
        $parser->next();
        $this->assertNull($parser->current());
    }

    function testEmptyIterator()
    {
        $parser = new SimpleHtmlParser('No tags here, sorry.. :)');

        for ($i = 0; $i < 2; ++$i) {
            $this->assertSame(0, $parser->getOffset());
            $this->assertNull($parser->key());
            $this->assertNull($parser->current());
            $this->assertFalse($parser->valid());

            $parser->rewind();
        }
    }

    function testStates()
    {
        $html = <<<HTML
<!doctype html>
<title>Lorem ipsum</title>
<h1>Dolor sit amet</h1>
HTML;

        $parser = new SimpleHtmlParser($html);

        // initial state
        $this->assertSame(0, $parser->getOffset());
        $this->assertSame(0, $parser->countStates());

        $parser->pushState();
        $this->assertSame(1, $parser->countStates());

        $parser->next();

        // doctype
        $this->assertSame(15, $parser->getOffset());
        $this->assertElement($parser->current(), [
            'type' => SimpleHtmlParser::OTHER,
            'symbol' => '!',
        ]);

        $parser->pushState();
        $this->assertSame(2, $parser->countStates());

        $parser->next();

        // <title>
        $this->assertSame(23, $parser->getOffset());
        $this->assertElement($parser->current(), [
            'type' => SimpleHtmlParser::OPENING_TAG,
            'name' => 'title',
        ]);

        $parser->pushState();
        $this->assertSame(3, $parser->countStates());
        
        $parser->next();
        
        // </title>
        $this->assertSame(42, $parser->getOffset());
        $this->assertElement($parser->current(), [
            'type' => SimpleHtmlParser::CLOSING_TAG,
            'name' => 'title',
        ]);
        
        $parser->pushState();
        $this->assertSame(4, $parser->countStates());
        
        $parser->find(SimpleHtmlParser::CLOSING_TAG, 'h1');
        
        // </h1>
        $this->assertSame(66, $parser->getOffset());

        $parser->revertState();
        $this->assertSame(3, $parser->countStates());

        // reverted back to </title>
        $this->assertSame(42, $parser->getOffset());
        $this->assertElement($parser->current(), [
            'type' => SimpleHtmlParser::CLOSING_TAG,
            'name' => 'title',
        ]);

        $parser->popState(); // pop state @ <title>
        $this->assertSame(2, $parser->countStates());
        $this->assertSame(42, $parser->getOffset()); // popping sould not affect offset

        $parser->revertState();
        $this->assertSame(1, $parser->countStates());

        // reverted to doctype
        $this->assertSame(15, $parser->getOffset());
        $this->assertElement($parser->current(), [
            'type' => SimpleHtmlParser::OTHER,
            'symbol' => '!',
        ]);

        $parser->revertState();
        $this->assertSame(0, $parser->countStates());

        // reverted to the beginning
        $this->assertSame(0, $parser->getOffset());
    }

    function testClearStates()
    {
        $parser = new SimpleHtmlParser('');

        $this->assertSame(0, $parser->countStates());

        for ($i = 1; $i <= 3; ++$i) {
            $parser->pushState();
            $this->assertSame($i, $parser->countStates());
        }

        $parser->clearStates();

        $this->assertSame(0, $parser->countStates());
    }

    function testExceptionOnPopStateWithEmptyStack()
    {
        $parser = new SimpleHtmlParser('');

        $this->assertSame(0, $parser->countStates());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The state stack is empty');

        $parser->popState();
    }

    function testExceptionOnRevertStateWithEmptyStack()
    {
        $parser = new SimpleHtmlParser('');

        $this->assertSame(0, $parser->countStates());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The state stack is empty');

        $parser->revertState();
    }

    function testEscape()
    {
        $parser = new SimpleHtmlParser('');

        $this->assertSame(
            '&lt;a href=&quot;http://example.com/?foo=bar&amp;amp;lorem=ipsum&quot;&gt;Test&lt;/a&gt;',
            $parser->escape('<a href="http://example.com/?foo=bar&amp;lorem=ipsum">Test</a>')
        );
    }

    function testGetDoctype()
    {
        $html = <<<HTML
<!-- foo bar -->
<!DOCTYPE html>
HTML;

        $parser = new SimpleHtmlParser($html);

        $this->assertElement($parser->getDoctypeElement(), [
            'type' => SimpleHtmlParser::OTHER,
            'start' => 17,
            'end' => 32,
            'content' => 'DOCTYPE html',
        ]);
    }

    function testGetDoctypeNotFound()
    {
        $html = <<<HTML
<!-- foo bar -->
<title>Hello</title>
HTML;

        $parser = new SimpleHtmlParser($html);

        $this->assertNull($parser->getDoctypeElement());
    }

    function testEncodingDefaultFallback()
    {
        $html = <<<HTML
<!doctype html>
<title>Foo bar</title>
HTML;

        $parser = new SimpleHtmlParser($html);

        $this->assertSame('UTF-8', $parser->getEncoding());
        $this->assertNull($parser->getEncodingTag());
    }

    function testEncodingSpecifiedFallback()
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

    function testExceptionOnUnsupportedFallbackEncoding()
    {
        $parser = new SimpleHtmlParser('');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported fallback encoding');

        $parser->setFallbackEncoding('unknown');
    }

    function testEncodingMetaCharset()
    {
        $html = <<<HTML
<!doctype html>
<META CharSet="WIN-1251">
<title>Foo bar</title>
HTML;

        $parser = new SimpleHtmlParser($html);

        $this->assertSame('WIN-1251', $parser->getEncoding());
        $this->assertElement($parser->getEncodingTag(), [
            'type' => SimpleHtmlParser::OPENING_TAG,
            'start' => 16,
            'end' => 41,
            'name' => 'meta',
            'attrs' => ['charset' => 'WIN-1251'],
        ]);
    }

    function testEncodingMetaHttpEquiv()
    {
        $html = <<<HTML
<!doctype html>
<META Http-Equiv="content-type" Content="text/html; charset=WIN-1251">
<title>Foo bar</title>
HTML;

        $parser = new SimpleHtmlParser($html);

        $this->assertSame('WIN-1251', $parser->getEncoding());
        $this->assertElement($parser->getEncodingTag(), [
            'type' => SimpleHtmlParser::OPENING_TAG,
            'start' => 16,
            'end' => 86,
            'name' => 'meta',
            'attrs' => ['http-equiv' => 'content-type', 'content' => 'text/html; charset=WIN-1251'],
        ]);
    }

    function testEncodingDetectionDoesNotAlterState()
    {
        $html = <<<HTML
<!doctype html>
<title>Foo bar</title>
HTML;

        $parser = new SimpleHtmlParser($html);

        $this->assertElement($parser->find(SimpleHtmlParser::OPENING_TAG, 'title'));
        $this->assertSame(23, $parser->getOffset());
        $this->assertSame(0, $parser->countStates());

        $this->assertSame('UTF-8', $parser->getEncoding());
        $this->assertNull($parser->getEncodingTag());

        $this->assertElement($parser->current());
        $this->assertSame(23, $parser->getOffset());
        $this->assertSame(0, $parser->countStates());
    }

    private function matchAndAssert(string $html, array $expectedKeys): void
    {
        $parser = new SimpleHtmlParser($html);

        $this->assertElement($parser->current(), $expectedKeys);
    }

    private function matchAndAssertFailure(string $html): void
    {
        $parser = new SimpleHtmlParser($html);

        $this->assertNull($parser->current());
    }

    private function assertElement($element, array $expectedKeys = []): void
    {
        $this->assertInternalType('array', $element);
        $this->assertArrayHasKey('type', $element);

        // check type and keys
        $keys = ['type', 'start', 'end'];

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
