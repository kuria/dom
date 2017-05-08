<?php

namespace Kuria\Dom;

/**
 * HTML document
 *
 * @author ShiraNai7 <shira.cz>
 */
class HtmlDocument extends DomContainer
{
    /** @var bool */
    protected $tidyEnabled = false;
    /** @var array */
    protected $tidyConfig = array();
    /** @var bool */
    protected $handleEncoding = true;

    /**
     * @return bool
     */
    public function isTidyEnabled()
    {
        return $this->tidyEnabled;
    }

    /**
     * Toggle automatic tidy usage
     *
     * {@see setTidyConfig()} to configure the process
     *
     * @param bool $tidyEnabled
     * @return static
     */
    public function setTidyEnabled($tidyEnabled)
    {
        $this->tidyEnabled = $tidyEnabled;

        return $this;
    }

    /**
     * Get tidy configuration
     *
     * @return array
     */
    public function getTidyConfig()
    {
        return $this->tidyConfig;
    }

    /**
     * Add or set tidy configuration
     *
     * @param array $tidyConfig
     * @param bool  $merge
     * @return static
     */
    public function setTidyConfig(array $tidyConfig, $merge = true)
    {
        if ($merge) {
            $this->tidyConfig = $tidyConfig + $this->tidyConfig;
        } else {
            $this->tidyConfig = $tidyConfig;
        }

        return $this;
    }

    /**
     * See whether automatic encoding handling is enabled
     *
     * @return bool
     */
    public function getHandleEncoding()
    {
        return $this->handleEncoding;
    }

    /**
     * Set whether the encoding meta tag should be automatically updated
     * or inserted to ensure the DOM extension handles the encoding correctly.
     *
     * Has no effect on already loaded document.
     *
     * @param bool $handleEncoding
     * @return static
     */
    public function setHandleEncoding($handleEncoding)
    {
        $this->handleEncoding = $handleEncoding;

        return $this;
    }

    public function setEncoding($newEncoding)
    {
        parent::setEncoding($newEncoding);

        // update or insert the appropriate meta tag manually
        // (as setting DOMDocument->encoding alone is not enough for HTML documents)
        $contentTypeMetaFound = false;

        foreach ($this->query('/html/head/meta') as $meta) {
            $httpEquivAttr = null;
            $contentAttr = null;

            foreach ($meta->attributes as $attr) {
                if (
                    null === $httpEquivAttr
                    && 0 === strcasecmp('http-equiv', $attr->nodeName)
                    && 0 === strcasecmp('Content-Type', $attr->nodeValue)
                ) {
                    $httpEquivAttr = $attr;
                } elseif (
                    null === $contentAttr
                    && 0 === strcasecmp('content', $attr->nodeName)
                ) {
                    $contentAttr = $attr;
                } elseif (0 === strcasecmp('charset', $attr->nodeName)) {
                    // remove the <meta charset="..."> tag
                    // (the DOM extension does not support it)
                    $this->remove($meta);
                    break 2;
                }

                if (isset($httpEquivAttr, $contentAttr)) {
                    $contentTypeMetaFound = true;
                    break 2;
                }
            }
        }

        $newContentType = "text/html; charset={$newEncoding}";

        if ($contentTypeMetaFound) {
            $contentAttr->nodeValue = $newContentType;
        } else {
            $meta = $this->document->createElement('meta');
            $meta->setAttribute('http-equiv', 'Content-Type');
            $meta->setAttribute('content', $newContentType);
            $this->prependChild($meta, $this->document->getElementsByTagName('head')->item(0));
        }

        return $this;
    }

    public function escape($string)
    {
        return htmlspecialchars(
            $string,
            PHP_VERSION_ID >= 50400
                ? ENT_QUOTES | ENT_HTML5
                : ENT_QUOTES,
            static::INTERNAL_ENCODING
        );
    }

    public function loadEmpty($encoding = null, array $properties = null)
    {
        if (null === $encoding) {
            $encoding = static::INTERNAL_ENCODING;
        }

        $handleEncoding = $this->handleEncoding;

        $e = null;
        try {
            // suppress encoding handling as it is always specified the "correct" way
            $this->handleEncoding = false;

            $this->loadString(<<<HTML
<!doctype html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset={$this->escape($encoding)}">
    </head>
    <body>
    </body>
</html>
HTML
                ,
                $encoding,
                $properties
            );
        } catch (\Exception $e) {
        } catch (\Throwable $e) {
        }

        // restore prior handleEncoding value
        $this->handleEncoding = $handleEncoding;

        if (null !== $e) {
            $this->clear();

            throw $e;
        }

        return $this;
    }

    protected function populate($content, $encoding = null)
    {
        // handle document encoding
        if ($this->handleEncoding) {
            $this->handleEncoding($content, $encoding);
        }

        // tidy
        if ($this->tidyEnabled && function_exists('tidy_repair_string')) {
            $content = tidy_repair_string($content, $this->tidyConfig, 'raw');
        }

        // load
        if (PHP_VERSION_ID >= 50400) {
            $this->document->loadHTML($content, $this->libxmlFlags);
        } else {
            $this->document->loadHTML($content); // @codeCoverageIgnore
        }
    }

    public function save(\DOMNode $contextNode = null, $childrenOnly = false)
    {
        $document = $this->getDocument();

        if (null === $contextNode) {
            $content = $document->saveHTML();
        } else {
            // saveHTML($node) is supported since PHP 5.3.6
            $useSaveHtmlArgument = PHP_VERSION_ID >= 50306;

            if ($childrenOnly) {
                $content = '';
                foreach ($contextNode->childNodes as $node) {
                    $content .= $useSaveHtmlArgument
                        ? $document->saveHTML($node)
                        : $document->saveXML($node)
                    ;
                }
            } else {
                $content = $useSaveHtmlArgument
                    ? $document->saveHTML($contextNode)
                    : $document->saveXML($contextNode)
                ;
            }
        }

        return $content;
    }

    /**
     * Make sure the passed HTML document string contains an encoding
     * specification that is supported by the DOM extension.
     *
     * @param string      $htmlDocument  the HTML document string to be modified
     * @param string|null $knownEncoding encoding, if already known
     */
    public static function handleEncoding(&$htmlDocument, $knownEncoding = null)
    {
        $document = new SimpleHtmlParser($htmlDocument);

        $encodingTag = $document->getEncodingTag();
        $specifiedEncoding = $document->getEncoding();
        $usedEncoding = $knownEncoding ?: $specifiedEncoding;

        if (
            null === $encodingTag // no tag, need to insert
            || isset($encodingTag['attrs']['charset']) // the DOM extension does not support <meta charset="...">
            || null !== $knownEncoding && 0 !== strcasecmp($specifiedEncoding, $knownEncoding) // the encodings are different
        ) {
            $replacement = "<meta http-equiv=\"Content-Type\" content=\"text/html; charset={$document->escape($usedEncoding)}\">";
            
            if (null === $encodingTag) {
                $insertAfter = $document->find(SimpleHtmlParser::OPENING_TAG, 'head', 1024) ?: $document->getDoctypeElement();
            }

            $document = null;

            if (null !== $encodingTag) {
                // replace the existing tag
                $htmlDocument = substr_replace($htmlDocument, $replacement, $encodingTag['start'], $encodingTag['end'] - $encodingTag['start']);
            } else {
                // insert new tag
                if (null !== $insertAfter) {
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
