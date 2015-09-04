DOM
===

Wrappers around the [PHP DOM classes](http://php.net/manual/en/book.dom.php).


## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Usage example](#usage)


## <a name="features"></a> Features

- works around the common DOM extension pitfalls
    - suppressing errors
    - handling the encoding of HTML documents
- HTML documents and fragments
    - encoding sniffing
    - tidy support
- XML documents and fragments
- XPath queries
- several helper methods


## <a name="requirements"></a> Requirements

- PHP 5.3 or newer


## <a name="usage"></a> Usage example

Loading a HTML fragment.

(Use other containers to load HTML documents, XML documents or XML fragments.)

    use Kuria\Dom\HtmlFragment;

    $dom = new HtmlFragment();
    $dom->loadString('<div id="test"><span>Hello</span></div>');

    $element = $dom->queryOne('//div[@id="test"]/span');

    if ($element) {
        var_dump($element->textContent);
    }


### Output

    string(5) "Hello"
