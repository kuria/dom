DOM
===

Wrappers around the [PHP DOM classes](http://php.net/manual/en/book.dom.php).


## Features

- HTML documents
    - full documents
    - partial content (fragments)
    - automatic UTF-8 conversion
    - integrated Tidy support (repairing poorly coded HTML documents)
- XML documents
    - full documents
    - partial content (fragments)
- XPath queries


## Requirements

- PHP 5.3 or newer


## Usage example

    use Kuria\Dom\HtmlFragment;

    $dom = new HtmlFragment();
    $dom->load('<div id="test"><span>Hello</span></div>');

    $element = $dom->queryOne('//div[@id = "test"]/span');

    if ($element) {
        var_dump($element->textContent);
    }


### Output

    string(5) "Hello"
