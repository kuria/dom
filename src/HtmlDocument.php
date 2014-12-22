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
    protected $autoUtf8Enabled = true;
    /** @var bool */
    protected $tidyEnabled = false;
    /** @var array */
    protected $tidyConfig = array();

    /**
     * See if automatic UTF-8 conversion is enabled
     *
     * @return bool
     */
    public function isAutoUtf8Enabled()
    {
        return $this->autoUtf8Enabled;
    }

    /**
     * Toggle automatic UTF-8 conversion
     *
     * @param bool $autoUtf8Enabled
     * @return static
     */
    public function setAutoUtf8Enabled($autoUtf8Enabled)
    {
        $this->autoUtf8Enabled = $autoUtf8Enabled;

        return $this;
    }

    /**
     * See if tidy is enabled
     *
     * @return bool
     */
    public function isTidyEnabled()
    {
        return $this->tidyEnabled;
    }

    /**
     * Toggle tidy
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
     * Get tidy config
     *
     * @return array
     */
    public function getTidyConfig()
    {
        return $this->tidyConfig;
    }

    /**
     * Set tidy config
     *
     * @param array $tidyConfig
     * @return static
     */
    public function setTidyConfig(array $tidyConfig)
    {
        $this->tidyConfig = $tidyConfig;

        return $this;
    }

    protected function initialize($content)
    {
        // auto-convert to UTF-8
        if ($this->autoUtf8Enabled) {
            $content = $this->convertDocumentToUtf8($content);
        }

        // add xml header
        if (!preg_match('/^\s*<\?xml/i', $content)) {
            $content = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>" . $content;
        }

        // tidy
        if ($this->tidyEnabled && function_exists('tidy_repair_string')) {
            $content = tidy_repair_string($content, $this->tidyConfig, 'utf8');
        }

        // load
        // @codeCoverageIgnoreStart
        if (version_compare(PHP_VERSION, '5.4.0', '>=')) {
            if ($this->ignoreErrors) {
                @$this->document->loadHTML($content, $this->libxmlFlags);
            } else {
                $this->document->loadHTML($content, $this->libxmlFlags);
            }
        } else {
            if ($this->ignoreErrors) {
                @$this->document->loadHTML($content);
            } else {
                $this->document->loadHTML($content);
            }
        }
        // @codeCoverageIgnoreEnd

        // remove XML header
        foreach ($this->document->childNodes as $childNode) {
            if ($childNode->nodeType == XML_PI_NODE) {
                $this->document->removeChild($childNode);
                break;
            }
        }
        $this->document->encoding = 'UTF-8';
    }

    /**
     * Convert the given HTML document to UTF-8
     *
     * @param string $html
     * @return string
     */
    protected function convertDocumentToUtf8($html)
    {
        $specifiedEncoding = null;

        // attempt to find <head> and </head> tags
        if (
            false !== ($headTagPos = stripos($html, '<head>'))
            && false !== ($headTagEndPos = stripos($html, '</head>', $headTagPos + 6))
        ) {
            // process meta tags in the head
            list($head, $toInsert, $specifiedEncoding) = $this->processMetaTags(
                substr($html, $headTagPos + 6, $headTagEndPos - $headTagPos - 6)
            );

            // rebuild document
            $html = substr($html, 0, $headTagPos + 6)
                . $toInsert
                . $head
                . substr($html, $headTagEndPos)
            ;
        } else {
            // no head tag found, process the entire document :(
            list($html, $toInsert, $specifiedEncoding) = $this->processMetaTags($html);

            // insert extra html if any
            if ('' !== $toInsert) {
                // attempt to insert it after the <html> tag
                $replacement = '$1' . $toInsert;
                $html = preg_replace('~(<html[^>]*?>)~i', $replacement, $html, 1, $count);
                if (0 === $count) {
                    // no <html> tag found, attempt to insert right after the doctype
                    $html = preg_replace('~(<!doctype[^>]*?>)~i', $replacement, $html, 1, $count);
                    if (0 === $count) {
                        // no doctype found, just insert it at the beginning of the document then
                        $html = $toInsert . $html;
                    }
                }
            }
        }

        // convert to UTF-8
        return $this->toUtf8($html, $specifiedEncoding);
    }

    /**
     * Process meta tags in the given fragment of HTML code
     *
     * @param string $html
     * @return array processed html, html_to_insert, specified_encoding
     */
    protected function processMetaTags($html)
    {
        $toInsert = '';
        $specifiedEncoding = null;
        $metaCharsetTag = '<meta charset="UTF-8">';
        $metaHttpEquivTag = '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';

        // find and process the meta charset tag
        $html = preg_replace_callback('~<meta.*?charset=(["\'])(.*?)\\1.*?>~', function (array $match) use (&$specifiedEncoding, $metaCharsetTag) {
            $specifiedEncoding = $match[2];

            return $metaCharsetTag;
        }, $html, 1, $count);

        // add meta charset tag if not present
        if (0 === $count) {
            $toInsert .= $metaCharsetTag;
        }

        // find and process the http-equiv meta tag
        $html = preg_replace_callback('~<meta(.*?)http-equiv=(["\'])Content-Type\\2(.*?)>~i', function (array $match) use (&$specifiedEncoding, $metaHttpEquivTag) {
            if (
                null === $specifiedEncoding
                && preg_match('~content=(["\']).+?;\s*charset=(.*?)\\1~i', $match[1] . $match[3], $content)
            ) {
                $specifiedEncoding = $content[2];
            }

            return $metaHttpEquivTag;
        }, $html, 1, $count);

        // add meta http-equiv tag if not present
        if (0 === $count) {
            $toInsert .= $metaHttpEquivTag;
        }

        return array($html, $toInsert, $specifiedEncoding);
    }

    /**
     * Convert the given HTML string to UTF-8
     *
     * @param string      $html
     * @param string|null $currentEncoding
     * @return string
     */
    protected function toUtf8($html, $currentEncoding)
    {
        if (null === $currentEncoding) {
            // detect encoding
            $currentEncoding = mb_detect_encoding($html, null, true);

            // abort if encoding could not be detected for some reason (failsafe)
            // @codeCoverageIgnoreStart
            if (false === $currentEncoding) {
                return $html;
            }
            // @codeCoverageIgnoreEnd
        } else {
            // normalise case
            $currentEncoding = strtoupper($currentEncoding);
        }

        if ('ASCII' !== $currentEncoding && 'UTF-8' !== $currentEncoding) {
            return mb_convert_encoding($html, 'UTF-8', $currentEncoding);
        } else {
            return $html;
        }
    }

    public function getContent()
    {
        return $this->getDocument()->saveHTML();
    }
}
