<?php declare(strict_types=1);

namespace Kuria\Dom;

use Kuria\SimpleHtmlParser\SimpleHtmlParser;

class HtmlDocument extends DomContainer
{
    /** @var bool */
    private $tidyEnabled = false;
    /** @var array */
    private $tidyConfig = [];
    /** @var bool */
    private $handleEncoding = true;

    function isTidyEnabled(): bool
    {
        return $this->tidyEnabled;
    }

    /**
     * Toggle automatic tidy usage
     *
     * @see HtmlDocument::setTidyConfig()
     */
    function setTidyEnabled(bool $tidyEnabled): void
    {
        $this->tidyEnabled = $tidyEnabled;
    }

    function getTidyConfig(): array
    {
        return $this->tidyConfig;
    }

    /**
     * Add or replace tidy configuration
     */
    function setTidyConfig(array $tidyConfig, bool $merge = true): void
    {
        if ($merge) {
            $this->tidyConfig = $tidyConfig + $this->tidyConfig;
        } else {
            $this->tidyConfig = $tidyConfig;
        }
    }

    /**
     * See whether automatic encoding handling is enabled
     */
    function isHandlingEncoding(): bool
    {
        return $this->handleEncoding;
    }

    /**
     * Set whether the encoding meta tag should be automatically updated
     * or inserted to ensure the DOM extension handles the encoding correctly.
     *
     * Has no effect on already loaded document.
     */
    function setHandleEncoding(bool $handleEncoding): void
    {
        $this->handleEncoding = $handleEncoding;
    }

    function setEncoding(string $newEncoding): void
    {
        parent::setEncoding($newEncoding);

        // update or insert the appropriate meta tag manually
        // (as setting DOMDocument->encoding alone is not enough for HTML documents)
        $httpEquivAttr = null;
        $contentAttr = null;

        foreach ($this->query('/html/head/meta') as $meta) {
            foreach ($meta->attributes as $attr) {
                if (
                    $httpEquivAttr === null
                    && strcasecmp('http-equiv', $attr->nodeName) === 0
                    && strcasecmp('Content-Type', $attr->nodeValue) === 0
                ) {
                    $httpEquivAttr = $attr;
                } elseif (
                    $contentAttr === null
                    && strcasecmp('content', $attr->nodeName) === 0
                ) {
                    $contentAttr = $attr;
                } elseif (strcasecmp('charset', $attr->nodeName) === 0) {
                    // remove the <meta charset="..."> tag
                    // (the DOM extension does not support it)
                    $this->remove($meta);
                    break 2;
                }

                if (isset($httpEquivAttr, $contentAttr)) {
                    break 2;
                }
            }
        }

        $newContentType = "text/html; charset={$newEncoding}";

        if (isset($httpEquivAttr, $contentAttr)) {
            $contentAttr->nodeValue = $newContentType;
        } else {
            $meta = $this->getDocument()->createElement('meta');
            $meta->setAttribute('http-equiv', 'Content-Type');
            $meta->setAttribute('content', $newContentType);
            $this->prependChild($meta, $this->getHead());
        }
    }

    function escape(string $string): string
    {
        return htmlspecialchars(
            $string,
            ENT_QUOTES | ENT_HTML5,
            static::INTERNAL_ENCODING
        );
    }

    function loadEmpty(?array $properties = null): void
    {
        $handleEncoding = $this->handleEncoding;

        try {
            // suppress encoding handling as it is always specified the "correct" way
            $this->handleEncoding = false;

            $this->loadString(
                <<<HTML
<!doctype html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset={$this->escape(static::INTERNAL_ENCODING)}">
    </head>
    <body>
    </body>
</html>
HTML
                ,
                null,
                $properties
            );
        } catch (\Throwable $e) {
            $this->clear();

            throw $e;
        } finally {
            // restore prior handleEncoding value
            $this->handleEncoding = $handleEncoding;
        }
    }

    protected function populate(\DOMDocument $document, string $content, ?string $encoding = null): void
    {
        // handle document encoding
        if ($this->handleEncoding) {
            static::handleEncoding($content, $encoding);
        }

        // tidy
        if ($this->tidyEnabled && function_exists('tidy_repair_string')) {
            $content = tidy_repair_string($content, $this->tidyConfig, 'raw');
        }

        // load
        $document->loadHTML($content, $this->getLibxmlFlags());
    }

    function save(?\DOMNode $contextNode = null, bool $childrenOnly = false): string
    {
        $document = $this->getDocument();

        if ($contextNode === null) {
            $content = $document->saveHTML();
        } else {
            if ($childrenOnly) {
                $content = '';
                foreach ($contextNode->childNodes as $node) {
                    $content .= $document->saveHTML($node);
                }
            } else {
                $content = $document->saveHTML($contextNode);
            }
        }

        return $content;
    }

    /**
     * Get the head element
     *
     * @throws \RuntimeException if the head element is not found
     */
    function getHead(): \DOMElement
    {
        /** @var \DOMElement|null $head */
        $head = $this->getDocument()->getElementsByTagName('head')->item(0);

        if (!$head) {
            throw new \RuntimeException('The head element was not found');
        }

        return $head;
    }

    /**
     * Get the body element
     *
     * @throws \RuntimeException if the body element is not found
     */
    function getBody(): \DOMElement
    {
        /** @var \DOMElement|null $body */
        $body = $this->getDocument()->getElementsByTagName('body')->item(0);

        if (!$body) {
            throw new \RuntimeException('The body element was not found');
        }

        return $body;
    }

    /**
     * Make sure the passed HTML document string contains an encoding
     * specification that is supported by the DOM extension.
     */
    static function handleEncoding(string &$htmlDocument, ?string $knownEncoding = null): void
    {
        $document = new SimpleHtmlParser($htmlDocument);

        $encodingTag = $document->getEncodingTag();
        $specifiedEncoding = $document->getEncoding();
        $usedEncoding = $knownEncoding ?: $specifiedEncoding;

        if (
            $encodingTag === null // no tag, need to insert
            || isset($encodingTag['attrs']['charset']) // the DOM extension does not support <meta charset="...">
            || $knownEncoding !== null && strcasecmp($specifiedEncoding, $knownEncoding) !== 0 // the encodings are different
        ) {
            $replacement = "<meta http-equiv=\"Content-Type\" content=\"text/html; charset={$document->escape($usedEncoding)}\">";
            $insertAfter = null;

            if ($encodingTag === null) {
                $insertAfter = $document->find(SimpleHtmlParser::OPENING_TAG, 'head', 1024) ?: $document->getDoctypeElement();
            }

            $document = null;

            if ($encodingTag !== null) {
                // replace the existing tag
                $htmlDocument = substr_replace($htmlDocument, $replacement, $encodingTag['start'], $encodingTag['end'] - $encodingTag['start']);
            } else {
                // insert new tag
                if ($insertAfter !== null) {
                    // after <head> or the doctype
                    $htmlDocument = substr_replace($htmlDocument, $replacement, $insertAfter['end'], 0);
                } else {
                    // at the beginning (since there is no head tag nor doctype
                    $htmlDocument = "{$replacement}\n{$htmlDocument}";
                }
            }
        }
    }
}
