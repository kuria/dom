DOM
===

Wrappers around the [PHP DOM classes](http://php.net/manual/en/book.dom.php).


## Features

- works around the common DOM extension pitfalls
    - suppressing errors
    - handling the encoding of HTML documents
- HTML documents and fragments
    - encoding sniffing
    - tidy support
- XML documents and fragments
- XPath queries
- several helper methods


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
