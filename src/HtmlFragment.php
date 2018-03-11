<?php declare(strict_types=1);

namespace Kuria\Dom;

class HtmlFragment extends HtmlDocument
{

    function __construct()
    {
        // encoding handling is disabled by default, since it is always specified the "correct" way
        $this->setHandleEncoding(false);
    }

    function loadEmpty(?array $properties = null): void
    {
        $this->loadString('', null, $properties);
    }

    protected function populate(\DOMDocument $document, string $content, ?string $encoding = null): void
    {
        if (!$encoding) {
            $encoding = static::INTERNAL_ENCODING;
        }

        parent::populate(
            $document,
            <<<HTML
<!doctype html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset={$this->escape($encoding)}">
    </head>
    <body>{$content}</body>
</html>
HTML
            ,
            $encoding
        );
    }

    function save(?\DOMNode $contextNode = null, bool $childrenOnly = false): string
    {
        if ($contextNode === null) {
            $contextNode = $this->getBody();
            $childrenOnly = true;
        }

        return parent::save($contextNode, $childrenOnly);
    }

    function query(string $expression, ?\DOMNode $contextNode = null, bool $registerNodeNs = true): \DOMNodeList
    {
        // if no context node has been given, assume <body>
        if ($contextNode === null) {
            $contextNode = $this->getBody();

            // make sure the query is relative to the context node
            if ($expression !== '' && $expression[0] !== '.') {
                $expression = '.' . $expression;
            }
        }

        return parent::query($expression, $contextNode, $registerNodeNs);
    }
}
