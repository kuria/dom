<?php

namespace Kuria\Dom;

/**
 * Simple HTML parser
 *
 *  - parsing basic HTML elements
 *  - sniffing the document's encoding
 *  - tag and attribute names are always lowercased
 *  - loosely based on http://www.w3.org/TR/2011/WD-html5-20110113/parsing.html
 *
 * @author ShiraNai7 <shira.cz>
 */
class SimpleHtmlParser implements \Iterator
{
    const COMMENT = 0;
    const OPENING_TAG = 1;
    const CLOSING_TAG = 2;
    const OTHER = 3;
    const INVALID = 4;

    /** @var array http://www.w3.org/TR/html5/syntax.html#parsing-html-fragments */
    protected static $rawtextTagMap = array(
        'style' => 0,
        'script' => 1,
        'noscript' => 2,
        'iframe' => 3,
        'noframes' => 4,
    );
    /** @var array uppercased map of encodings supported by htmlspecialchars() */
    protected static $supportedEncodingMap = array(
        'ISO-8859-1' => 0, 'ISO8859-1' => 1, 'ISO-8859-5' => 2, 'ISO-8859-15' => 3,
        'ISO8859-15' => 4, 'UTF-8' => 5, 'CP866' => 6, 'IBM866' => 7,
        '866' => 8, 'CP1251' => 9, 'WINDOWS-1251' => 10, 'WIN-1251' => 11,
        '1251' => 12, 'CP1252' => 13, 'WINDOWS-1252' => 14, '1252' => 15,
        'KOI8-R' => 16, 'KOI8-RU' => 17, 'KOI8R' => 18, 'BIG5' => 19, '950' => 20,
        'GB2312' => 21, '936' => 22, 'BIG5-HKSCS' => 23, 'SHIFT_JIS' => 24,
        'SJIS' => 25, 'SJIS-WIN' => 26, 'CP932' => 27, '932' => 28,
        'EUC-JP' => 29, 'EUCJP' => 30, 'EUCJP-WIN' => 31, 'MACROMAN' => 32,
    );
    /** @var string */
    protected $html;
    /** @var int */
    protected $length;
    /** @var int */
    protected $offset = 0;
    /** @var int|null */
    protected $index;
    /** @var array|bool|null */
    protected $current;
    /** @var array[] */
    protected $stateStack = array();
    /** @var string|null */
    protected $encoding;
    /** @var string */
    protected $fallbackEncoding = 'UTF-8';
    /** @var array|null */
    protected $encodingTag;
    /** @var bool */
    protected $encodingDetected = false;
    /** @var array|bool|null */
    protected $doctypeElement;

    /**
     * @param string $html the HTML document
     */
    public function __construct($html)
    {
        $this->html = $html;
        $this->length = strlen($html);
    }

    /**
     * Get HTML
     *
     * If no element is given, returns the entire document.
     *
     * If an element is given, returns only a section of the document that
     * corresponds to the matched element.
     *
     * @param array|null $element
     * @return string
     */
    public function getHtml(array $element = null)
    {
        return null !== $element
            ? substr($this->html, $element['start'], $element['end'] - $element['start'])
            : $this->html
        ;
    }

    /**
     * Get length of the HTML
     *
     * @return string
     */
    public function getLength()
    {
        return $this->length;
    }

    /**
     * Get encoding of the document
     *
     * @return string
     */
    public function getEncoding()
    {
        if (null === $this->encoding) {
            $this->detectEncoding();
        }

        return $this->encoding;
    }

    /**
     * Get the encoding-specifying meta tag, if any
     *
     * (META charset or META http-equiv="Content-Type")
     *
     * @return array|null
     */
    public function getEncodingTag()
    {
        if (null === $this->encoding) {
            $this->detectEncoding();
        }

        return $this->encodingTag;
    }

    /**
     * Set fallback encoding
     *
     *  - used if no encoding is specified or the specified encoding is unsupported
     *  - setting after the encoding has been determined has no effect
     *  - must be supported by htmlspecialchars()
     *
     * @throws \InvalidArgumentException if the encoding is not supported
     * @param string $fallbackEncoding
     */
    public function setFallbackEncoding($fallbackEncoding)
    {
        $fallbackEncoding = strtoupper($fallbackEncoding);

        if (!isset(self::$supportedEncodingMap[$fallbackEncoding])) {
            throw new \InvalidArgumentException(sprintf('Unsupported fallback encoding "%s"', $fallbackEncoding));
        }

        $this->fallbackEncoding = $fallbackEncoding;
    }

    /**
     * Get the doctype element, if any
     *
     * Returns an element of type OTHER, with an extra "content" key.
     *
     * @return array|null
     */
    public function getDoctypeElement()
    {
        if (null === $this->doctypeElement) {
            $this->doctypeElement = $this->findDoctype();
        }

        return $this->doctypeElement ?: null;
    }
    
    /**
     * Escape a string
     *
     * @param string $string       the string to escape
     * @param string $mode         flags for htmlspecialchars
     * @param bool   $doubleEncode encode already existing entities 1/0
     * @return string
     */
    public function escape($string, $mode = ENT_QUOTES, $doubleEncode = true)
    {
        if (null === $this->encoding) {
            $this->detectEncoding();
        }
        
        return htmlspecialchars($string, $mode, $this->encoding, $doubleEncode);
    }

    /**
     * Find a specific element starting from the current offset
     *
     * @param int         $elemType   type of the element to find
     * @param string|null $tagName    tag name (lowercase, only valid if $elemType is opening or closing tag!)
     * @param int|null    $stopOffset stop searching after this offset is passed (soft limit)
     * @throws \LogicException
     * @return array|bool
     */
    public function find($elemType, $tagName = null, $stopOffset = null)
    {
        if (null !== $tagName && self::OPENING_TAG !== $elemType && self::CLOSING_TAG !== $elemType) {
            throw new \LogicException('Can only specify tag name when searching for OPENING_TAG or CLOSING_TAG');
        }

        while (false !== $this->current && (null === $stopOffset || $this->offset < $stopOffset)) {
            $this->next();

            if (
                false !== $this->current
                && $elemType === $this->current['type']
                && (null === $tagName || $this->current['name'] === $tagName)
            ) {
                return $this->current;
            }
        }

        return false;
    }

    /**
     * Get current offset
     *
     * @return int
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * Store current iteration state
     *
     * Don't forget to revertState() or popState() when you are done.
     */
    public function pushState()
    {
        $this->stateStack[] = array($this->offset, $this->index, $this->current);
    }

    /**
     * Pop the last stored iteration state without reverting to it
     *
     * @throws \LogicException if there are no states on the stack
     */
    public function popState()
    {
        if (null === array_pop($this->stateStack)) {
            throw new \LogicException('The state stack is empty');
        }
    }

    /**
     * Revert to an earlier iteration state
     *
     * @throws \LogicException if there are no states on the stack
     */
    public function revertState()
    {
        if (!$this->stateStack) {
            throw new \LogicException('The state stack is empty');
        }

        list($this->offset, $this->index, $this->current) = array_pop($this->stateStack);
    }

    /**
     * Get number of states on the stack
     *
     * @return int
     */
    public function getStateStackSize()
    {
        return sizeof($this->stateStack);
    }

    /**
     * Throw away all stored states
     */
    public function clearStates()
    {
        $this->stateStack = array();
    }

    public function current()
    {
        if (null === $this->current) {
            $this->next();
        }

        return $this->current;
    }

    public function key()
    {
        if (null === $this->current) {
            $this->next();
        }

        return $this->index;
    }

    public function next()
    {
        // skip contents of known RAWTEXT tags
        if (
            false !== $this->current
            && $this->current['type'] === self::OPENING_TAG
            && isset(self::$rawtextTagMap[$this->current['name']])
        ) {
            $this->offset = false !== ($end = stripos($this->html, "</{$this->current['name']}>", $this->offset))
                ? $end
                : $this->length
            ;
        }

        // match a thing
        if (false !== $this->current) {
            $this->current = $this->match($this->offset);

            if (false !== $this->current) {
                // advance offset and index
                $this->offset = $this->current['end'];

                if (null !== $this->index) {
                    ++$this->index;
                } else {
                    $this->index = 0;
                }

            } else {
                // could not match anything
                $this->offset = $this->length;
            }
        }
    }

    public function rewind()
    {
        $this->offset = 0;
        $this->index = null;
        $this->current = null;
    }

    public function valid()
    {
        if (null === $this->current) {
            $this->next();
        }

        return false !== $this->current;
    }

    /**
     * Match the HTML at the current offset
     *
     * @return array|bool
     */
    protected function match($offset)
    {
        $result = false;

        if (
            $offset < $this->length
            && preg_match('~<!--|<(/?)(\w+)|<[!?/]~', $this->html, $match, PREG_OFFSET_CAPTURE, $offset)
        ) {
            if ('<!--' === $match[0][0]) {
                // comment
                $offset = $match[0][1] + 3;

                if (false !== ($end = strpos($this->html, '-->', $offset))) {
                    $result = array(
                        'type' => self::COMMENT,
                        'start' => $match[0][1],
                        'end' => $end + 3,
                    );
                }
            } elseif (isset($match[1])) {
                // opening or closing tag
                $offset = $match[0][1] + strlen($match[0][0]);
                list($attrs, $offset) = $this->matchAttributes($offset);
                preg_match('~\s*/?>~A', $this->html, $endMatch, 0, $offset);

                $isClosingTag = '/' === $match[1][0];

                $result = array(
                    'type' => $isClosingTag ? self::CLOSING_TAG : self::OPENING_TAG,
                    'start' => $match[0][1],
                    'end' => $offset + ($endMatch ? strlen($endMatch[0]) : 0),
                    'name' => strtolower($match[2][0]),
                );

                if (!$isClosingTag) {
                    $result['attrs'] = $attrs;
                }
            } else {
                // other
                $offset = $match[0][1] + 2;
                $end = strpos($this->html, '>', $offset);

                $result = false !== $end
                    ? array(
                        'type' => self::OTHER,
                        'symbol' => $match[0][0][1],
                        'start' => $match[0][1],
                        'end' => $end + 1,
                    )
                    : array(
                        'type' => self::INVALID,
                        'start' => $match[0][1],
                        'end' => $match[0][1] + 2,
                    )
                ;
            }
        }

        return $result;
    }

    /**
     * Match tag attributes
     *
     * @param int $offset
     * @param int $mode
     * @return array attributes, offset
     */
    protected function matchAttributes($offset)
    {
        $attrs = array();

        while (
            $offset >= 0
            && $offset < $this->length
            && preg_match('~\s*([^\x00-\x20"\'>/=]+)~A', $this->html, $match, 0, $offset)
        ) {
            $name = $match[1];
            $value = true;
            $offset += strlen($match[0]);

            // parse value
            if (preg_match('~\s*=\s*~A', $this->html, $match, 0, $offset)) {
                $offset += strlen($match[0]);

                if ($offset < $this->length) {
                    if ('"' === $this->html[$offset] || '\'' === $this->html[$offset]) {
                        // quoted
                        if (preg_match('~"([^"]*+)"|\'([^\']*+)\'~A', $this->html, $match, 0, $offset)) {
                            $value = $match[isset($match[2]) ? 2 : 1];
                            $offset += strlen($match[0]);
                        }
                    } elseif (preg_match('~[^\s"\'=<>`]++~A', $this->html, $match, 0, $offset)) {
                        // unquoted
                        $value = $match[0];
                        $offset += strlen($match[0]);
                    }
                }
            }

            $attrs[strtolower($name)] = $value;
        }

        return array($attrs, $offset);
    }

    /**
     * Try to find the doctype in the first 1024 bytes of the document
     *
     * @return array|bool
     */
    protected function findDoctype()
    {
        $this->pushState();
        $this->rewind();

        $found = false;

        while ($element = $this->find(self::OTHER, null, 1024)) {
            if ('!' === $element['symbol']) {
                $content = substr($this->html, $element['start'] + 2, $element['end'] - $element['start'] - 3);

                if (0 === strncasecmp('doctype', $content, 7)) {
                    $element['content'] = $content;
                    $found = true;
                    break;
                }
            }
        }

        $this->revertState();

        return $found ? $element : false;
    }

    /**
     * Try to determine the encoding from the first 1024 bytes of the document
     */
    protected function detectEncoding()
    {
        // http://www.w3.org/TR/html5/syntax.html#determining-the-character-encoding
        // http://www.w3.org/TR/html5/document-metadata.html#charset

        $this->pushState();
        $this->rewind();

        $found = false;
        $pragma = false;

        while ($metaTag = $this->find(self::OPENING_TAG, 'meta', 1024)) {
            if (isset($metaTag['attrs']['charset'])) {
                $found = true;
                break;
            } elseif (
                isset($metaTag['attrs']['http-equiv'], $metaTag['attrs']['content'])
                && 0 === strcasecmp($metaTag['attrs']['http-equiv'], 'content-type')
            ) {
                $found = true;
                $pragma = true;
                break;
            }
        }

        $this->revertState();

        // handle the result
        $encoding = false;

        if ($found) {
            if ($pragma) {
                $encoding = self::parseCharsetFromContentType($metaTag['attrs']['content']);
            } else {
                $encoding = $metaTag['attrs']['charset'];
            }
        }
        
        if (false !== $encoding) {
            $encoding = strtoupper($encoding);
        }

        if (false === $encoding || !isset(self::$supportedEncodingMap[$encoding])) {
            // no encoding has been specified or it is not supported
            $encoding = $this->fallbackEncoding;
        }

        $this->encoding = $encoding;
        $this->encodingTag = $found ? $metaTag : null;
    }

    /**
     * Attempt to extract the charset from a Content-Type header
     *
     * @param string $contentType
     * @return string|bool false on failure
     */
    public static function parseCharsetFromContentType($contentType)
    {
        // http://www.w3.org/TR/2011/WD-html5-20110113/fetching-resources.html#algorithm-for-extracting-an-encoding-from-a-content-type
        return preg_match('~charset\s*+=\s*+(["\'])?+(?(1)(.+)(?=\1)|([^\s;]+))~i', $contentType, $match)
            ? $match[isset($match[3]) ? 3 : 2]
            : false
        ;
    }
}
