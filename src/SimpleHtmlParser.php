<?php declare(strict_types=1);

namespace Kuria\Dom;

/**
 * Simple HTML parser
 *
 * - parsing basic HTML elements
 * - sniffing the document's encoding
 * - tag and attribute names that contain only ASCII characters are lowercased
 * - loosely based on http://www.w3.org/TR/2011/WD-html5-20110113/parsing.html
 */
class SimpleHtmlParser implements \Iterator
{
    const COMMENT = 0;
    const OPENING_TAG = 1;
    const CLOSING_TAG = 2;
    const OTHER = 3;
    const INVALID = 4;

    /** http://www.w3.org/TR/html5/syntax.html#parsing-html-fragments */
    protected const RAWTEXT_TAG_MAP = [
        'style' => 0,
        'script' => 1,
        'noscript' => 2,
        'iframe' => 3,
        'noframes' => 4,
    ];

    /** Uppercased map of encodings supported by htmlspecialchars() */
    protected const SUPPORTED_ENCODING_MAP = [
        'ISO-8859-1' => 0, 'ISO8859-1' => 1, 'ISO-8859-5' => 2, 'ISO-8859-15' => 3,
        'ISO8859-15' => 4, 'UTF-8' => 5, 'CP866' => 6, 'IBM866' => 7,
        '866' => 8, 'CP1251' => 9, 'WINDOWS-1251' => 10, 'WIN-1251' => 11,
        '1251' => 12, 'CP1252' => 13, 'WINDOWS-1252' => 14, '1252' => 15,
        'KOI8-R' => 16, 'KOI8-RU' => 17, 'KOI8R' => 18, 'BIG5' => 19, '950' => 20,
        'GB2312' => 21, '936' => 22, 'BIG5-HKSCS' => 23, 'SHIFT_JIS' => 24,
        'SJIS' => 25, 'SJIS-WIN' => 26, 'CP932' => 27, '932' => 28,
        'EUC-JP' => 29, 'EUCJP' => 30, 'EUCJP-WIN' => 31, 'MACROMAN' => 32,
    ];

    /** @var string */
    protected $html;
    /** @var int */
    protected $length;
    /** @var bool iteration state */
    protected $valid = true;
    /** @var int */
    protected $offset = 0;
    /** @var int|null */
    protected $index;
    /** @var array|null */
    protected $current;
    /** @var array[] */
    protected $stateStack = [];
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

    function __construct(string $html)
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
     */
    function getHtml(?array $element = null): string
    {
        return $element !== null
            ? substr($this->html, $element['start'], $element['end'] - $element['start'])
            : $this->html;
    }

    /**
     * Get length of the HTML
     */
    function getLength(): int
    {
        return $this->length;
    }

    /**
     * Get encoding of the document
     */
    function getEncoding(): string
    {
        if ($this->encoding === null) {
            $this->detectEncoding();
        }

        return $this->encoding;
    }

    /**
     * Get the encoding-specifying meta tag, if any
     *
     * (META charset or META http-equiv="Content-Type")
     */
    function getEncodingTag(): ?array
    {
        if ($this->encoding === null) {
            $this->detectEncoding();
        }

        return $this->encodingTag;
    }

    /**
     * Set fallback encoding
     *
     * - used if no encoding is specified or the specified encoding is unsupported
     * - setting after the encoding has been determined has no effect
     * - must be supported by htmlspecialchars()
     *
     * @throws \InvalidArgumentException if the encoding is not supported
     */
    function setFallbackEncoding(string $fallbackEncoding): void
    {
        $fallbackEncoding = strtoupper($fallbackEncoding);

        if (!isset(static::SUPPORTED_ENCODING_MAP[$fallbackEncoding])) {
            throw new \InvalidArgumentException(sprintf('Unsupported fallback encoding "%s"', $fallbackEncoding));
        }

        $this->fallbackEncoding = $fallbackEncoding;
    }

    /**
     * Get the doctype element, if any
     *
     * Returns an element of type OTHER, with an extra "content" key.
     */
    function getDoctypeElement(): ?array
    {
        if ($this->doctypeElement === null) {
            $this->doctypeElement = $this->findDoctype();
        }

        return $this->doctypeElement ?: null;
    }
    
    /**
     * Escape a string
     *
     * @see htmlspecialchars()
     */
    function escape(string $string, int $mode = ENT_QUOTES, bool $doubleEncode = true): string
    {
        if ($this->encoding === null) {
            $this->detectEncoding();
        }
        
        return htmlspecialchars($string, $mode, $this->encoding, $doubleEncode);
    }

    /**
     * Find a specific element starting from the current offset
     *
     * - $tagName should be lowercase.
     * - stops searching after $stopOffset is reached, if specified (soft limit).
     */
    function find(int $elemType, ?string $tagName = null, int $stopOffset = null): ?array
    {
        if ($tagName !== null && static::OPENING_TAG !== $elemType && static::CLOSING_TAG !== $elemType) {
            throw new \LogicException('Can only specify tag name when searching for OPENING_TAG or CLOSING_TAG');
        }

        while ($this->valid && ($stopOffset === null || $this->offset < $stopOffset)) {
            $this->next();

            if (
                $this->valid
                && $elemType === $this->current['type']
                && ($tagName === null || $this->current['name'] === $tagName)
            ) {
                return $this->current;
            }
        }

        return null;
    }

    /**
     * Get current offset
     */
    function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * Store current iteration state
     *
     * Use revertState() or popState() when you are done.
     */
    function pushState(): void
    {
        $this->stateStack[] = [$this->valid, $this->offset, $this->index, $this->current];
    }

    /**
     * Pop the last stored iteration state without reverting to it
     *
     * @throws \LogicException if there are no states on the stack
     */
    function popState(): void
    {
        if (array_pop($this->stateStack) === null) {
            throw new \LogicException('The state stack is empty');
        }
    }

    /**
     * Revert to an earlier iteration state
     *
     * @throws \LogicException if there are no states on the stack
     */
    function revertState(): void
    {
        if (!$this->stateStack) {
            throw new \LogicException('The state stack is empty');
        }

        [$this->valid, $this->offset, $this->index, $this->current] = array_pop($this->stateStack);
    }

    /**
     * Get number of states on the stack
     */
    function countStates(): int
    {
        return sizeof($this->stateStack);
    }

    /**
     * Throw away all stored states
     */
    function clearStates(): void
    {
        $this->stateStack = [];
    }

    function current(): ?array
    {
        if ($this->current === null && $this->valid) {
            $this->next();
        }

        return $this->current;
    }

    function key(): ?int
    {
        if ($this->current === null && $this->valid) {
            $this->next();
        }

        return $this->index;
    }

    function next(): void
    {
        if (!$this->valid) {
            return;
        }

        // skip contents of known RAWTEXT tags
        if (
            $this->current !== null
            && $this->current['type'] === static::OPENING_TAG
            && isset(static::RAWTEXT_TAG_MAP[$this->current['name']])
        ) {
            $this->offset = ($end = stripos($this->html, "</{$this->current['name']}>", $this->offset)) !== false
                ? $end
                : $this->length;
        }

        // match a thing
        $this->current = $this->match($this->offset);

        if ($this->current !== null) {
            // advance offset and index
            $this->offset = $this->current['end'];

            if ($this->index !== null) {
                ++$this->index;
            } else {
                $this->index = 0;
            }
        } else {
            // could not match anything
            $this->offset = $this->length;
            $this->valid = false;
        }
    }

    function rewind(): void
    {
        $this->valid = true;
        $this->offset = 0;
        $this->index = null;
        $this->current = null;
    }

    function valid(): bool
    {
        if ($this->current === null && $this->valid) {
            $this->next();
        }

        return $this->valid;
    }

    /**
     * Match HTML element at the current offset
     */
    protected function match(int $offset): ?array
    {
        $result = null;

        if (
            $offset < $this->length
            && preg_match('~<!--|<(/?)([\w-:\x80-\xFF]+)|<[!?/]~', $this->html, $match, PREG_OFFSET_CAPTURE, $offset)
        ) {
            if ($match[0][0] === '<!--') {
                // comment
                $offset = $match[0][1] + 3;

                if (($end = strpos($this->html, '-->', $offset)) !== false) {
                    $result = [
                        'type' => static::COMMENT,
                        'start' => $match[0][1],
                        'end' => $end + 3,
                    ];
                }
            } elseif (isset($match[1])) {
                // opening or closing tag
                $offset = $match[0][1] + strlen($match[0][0]);
                [$attrs, $offset] = $this->matchAttributes($offset);
                preg_match('~\s*/?>~A', $this->html, $endMatch, 0, $offset);

                $isClosingTag = $match[1][0] === '/';

                $result = [
                    'type' => $isClosingTag ? static::CLOSING_TAG : static::OPENING_TAG,
                    'start' => $match[0][1],
                    'end' => $offset + ($endMatch ? strlen($endMatch[0]) : 0),
                    'name' => $this->normalizeIdentifier($match[2][0]),
                ];

                if (!$isClosingTag) {
                    $result['attrs'] = $attrs;
                }
            } else {
                // other
                $offset = $match[0][1] + 2;
                $end = strpos($this->html, '>', $offset);

                $result = $end !== false
                    ? [
                        'type' => static::OTHER,
                        'symbol' => $match[0][0][1],
                        'start' => $match[0][1],
                        'end' => $end + 1,
                    ]
                    : [
                        'type' => static::INVALID,
                        'start' => $match[0][1],
                        'end' => $match[0][1] + 2,
                    ];
            }
        }

        return $result;
    }

    /**
     * Match tag attributes
     *
     * Returns [attributes, offset] tuple.
     */
    protected function matchAttributes(int $offset): array
    {
        $attrs = [];

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
                    if ($this->html[$offset] === '"' || $this->html[$offset] === '\'') {
                        // quoted
                        if (preg_match('~"([^"]*+)"|\'([^\']*+)\'~A', $this->html, $match, 0, $offset)) {
                            $value = $match[2] ?? $match[1];
                            $offset += strlen($match[0]);
                        }
                    } elseif (preg_match('~[^\s"\'=<>`]++~A', $this->html, $match, 0, $offset)) {
                        // unquoted
                        $value = $match[0];
                        $offset += strlen($match[0]);
                    }
                }
            }

            $attrs[$this->normalizeIdentifier($name)] = $value;
        }

        return [$attrs, $offset];
    }

    protected function normalizeIdentifier(string $name): string
    {
        // lowercase only if the name consists of ASCII characters
        if (preg_match('~^[^\x80-\xFF]+$~', $name)) {
            return strtolower($name);
        }

        return $name;
    }

    /**
     * Try to find the doctype in the first 1024 bytes of the document
     */
    protected function findDoctype(): ?array
    {
        $this->pushState();
        $this->rewind();

        $found = false;

        while ($element = $this->find(static::OTHER, null, 1024)) {
            if ($element['symbol'] === '!') {
                $content = substr($this->html, $element['start'] + 2, $element['end'] - $element['start'] - 3);

                if (strncasecmp('doctype', $content, 7) === 0) {
                    $element['content'] = $content;
                    $found = true;
                    break;
                }
            }
        }

        $this->revertState();

        return $found ? $element : null;
    }

    /**
     * Try to determine the encoding from the first 1024 bytes of the document
     */
    protected function detectEncoding(): void
    {
        // http://www.w3.org/TR/html5/syntax.html#determining-the-character-encoding
        // http://www.w3.org/TR/html5/document-metadata.html#charset

        $this->pushState();
        $this->rewind();

        $found = false;
        $pragma = false;

        while ($metaTag = $this->find(static::OPENING_TAG, 'meta', 1024)) {
            if (isset($metaTag['attrs']['charset'])) {
                $found = true;
                break;
            } elseif (
                isset($metaTag['attrs']['http-equiv'], $metaTag['attrs']['content'])
                && strcasecmp($metaTag['attrs']['http-equiv'], 'content-type') === 0
            ) {
                $found = true;
                $pragma = true;
                break;
            }
        }

        $this->revertState();

        // handle the result
        $encoding = null;

        if ($found) {
            if ($pragma) {
                $encoding = static::parseCharsetFromContentType($metaTag['attrs']['content']);
            } else {
                $encoding = $metaTag['attrs']['charset'];
            }
        }
        
        if ($encoding !== null) {
            $encoding = strtoupper($encoding);
        }

        if ($encoding === null || !isset(static::SUPPORTED_ENCODING_MAP[$encoding])) {
            // no encoding has been specified or it is not supported
            $encoding = $this->fallbackEncoding;
        }

        $this->encoding = $encoding;
        $this->encodingTag = $found ? $metaTag : null;
    }

    /**
     * Attempt to extract the charset from a Content-Type header
     */
    static function parseCharsetFromContentType(string $contentType): ?string
    {
        // http://www.w3.org/TR/2011/WD-html5-20110113/fetching-resources.html#algorithm-for-extracting-an-encoding-from-a-content-type
        return preg_match('~charset\s*+=\s*+(["\'])?+(?(1)(.+)(?=\1)|([^\s;]+))~i', $contentType, $match)
            ? ($match[3] ?? $match[2])
            : null;
    }
}
