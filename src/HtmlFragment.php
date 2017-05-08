<?php

namespace Kuria\Dom;

/**
 * HTML fragment
 *
 * @author ShiraNai7 <shira.cz>
 */
class HtmlFragment extends HtmlDocument
{
    /**
     * Encoding handling is disabled by default, since it is always specified
     * the "correct" way. This saves some needless processing.
     *
     * @var bool
     */
    protected $handleEncoding = false;

    public function loadEmpty($encoding = null, array $properties = null)
    {
        return $this->loadString('', $encoding, $properties);
    }

    protected function populate($content, $encoding = null)
    {
        if (!$encoding) {
            $encoding = static::INTERNAL_ENCODING;
        }

        parent::populate(
            <<<HTML
<!doctype html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset={$this->escape($encoding)}">
    </head>
    <body>
        {$content}
    </body>
</html>
HTML
            ,
            $encoding
        );
    }

    public function save(\DOMNode $contextNode = null, $childrenOnly = false)
    {
        if (null === $contextNode) {
            $contextNode = $this->getXpath()->query('/html/body')->item(0);
            $childrenOnly = true;
        }

        return parent::save($contextNode, $childrenOnly);
    }

    public function query($expression, \DOMNode $contextNode = null, $registerNodeNs = true)
    {
        // if no context node has been given, assume <body>
        if (null === $contextNode) {
            $contextNode = $this->getDocument()->getElementsByTagName('body')->item(0);

            // make sure the query is relative to the context node
            if ($contextNode && '' !== $expression && '.' !== $expression[0]) {
                $expression = '.' . $expression;
            }
        }

        return parent::query($expression, $contextNode, $registerNodeNs);
    }
}
