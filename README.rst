DOM
###

Wrappers around the `PHP DOM classes <http://php.net/manual/en/book.dom.php>`__ that handle the common DOM extension pitfalls.

.. contents::


Features
********

- HTML documents

  - encoding sniffing
  - optional tidy support (automatically fix broken HTML)

- HTML fragments
- XML documents
- XML fragments

- XPath queries
- creating documents from scratch
- optional error suppression
- helper methods for common tasks, such as:

  - querying multiple or a single node
  - checking for containment
  - removing a node
  - removing all nodes from a list
  - prepending a child node
  - inserting a node after another node
  - fetching ``<head>`` and ``<body>`` elements (HTML)
  - fetching root elements  (XML)


Requirements
************

- PHP 7.1+


Container methods
*****************

These methods are shared by both HTML and XML containers.


Loading documents
=================

.. code:: php

   <?php

   use Kuria\Dom\HtmlDocument; // or XmlDocument, HtmlFragment, etc.

   // using loadString()
   $dom = new HtmlDocument();
   $dom->setLibxmlFlags($customLibxmlFlags); // optional
   $dom->setIgnoreErrors($ignoreErrors); // optional
   $dom->loadString($html);

   // using static loadString() shortcut
   $dom = HtmlDocument::fromString($html);

   // using existing document instance
   $dom = new HtmlDocument();
   $dom->loadDocument($document);

   // using static loadDocument() shortcut
   $dom = HtmlDocument::fromDocument($document);

   // creating an empty document
   $dom = new HtmlDocument();
   $dom->loadEmpty();


Getting or changing document encoding
=====================================

.. code:: php

   <?php

   // get encoding
   $encoding = $dom->getEncoding();

   // set encoding
   $dom->setEncoding($newEncoding);

.. NOTE::

   The DOM extension uses UTF-8 encoding.

   This means that text nodes, attributes, etc.:

   - will be encoded using UTF-8 when read (e.g. ``$elem->textContent``)
   - should be encoded using UTF-8 when written (e.g. ``$elem->setAttribute()``)

   The encoding configured by ``setEncoding()`` is used when saving the document,
   see `Saving documents`_.


Saving documents
================

.. code:: php

   <?php

   // entire document
   $content = $dom->save();

   // single element
   $content = $dom->save($elem);

   // children of a single element
   $content = $dom->save($elem, true);


Getting DOM instances
=====================

After a document has been loaded, the DOM instances are available via getters:

.. code:: php

   <?php

   $document = $dom->getDocument();
   $xpath = $dom->getXpath();


Running XPath queries
=====================

.. code:: php

   <?php

   // get a DOMNodeList
   $divs = $dom->query('//div');

   // get a single DOMNode (or null)
   $div = $dom->query('//div');

   // check if a query matches
   $divExists = $dom->exists('//div');


Escaping strings
================

.. code:: php

   <?php

   $escapedString = $dom->escape($string);


DOM manipulation and traversal helpers
======================================

Helpers for commonly needed tasks that aren't easily achieved via existing DOM methods:

.. code:: php

   <?php

   // check if the document contains a node
   $hasNode = $dom->contains($node);

   // check if a node contains another node
   $hasNode = $dom->contains($node, $parentNode);

   // remove a node
   $dom->remove($node);

   // remove a list of nodes
   $dom->removeAll($nodes);

   // prepend a child node
   $dom->prependChild($newNode, $existingNode);

   // insert a node after another node
   $dom->insertAfter($newNode, $existingNode);


Usage examples
**************

HTML documents
==============

Loading an existing document
----------------------------

.. code:: php

   <?php

   use Kuria\Dom\HtmlDocument;

   $html = <<<HTML
   <!doctype html>
   <html>
       <head>
           <meta charset="UTF-8">
           <title>Example document</title>
       </head>
       <body>
           <h1>Hello world!</h1>
       </body>
   </html>
   HTML;

   $dom = HtmlDocument::fromString($html);

   var_dump($dom->queryOne('//title')->textContent);
   var_dump($dom->queryOne('//h1')->textContent);

Output:

::

  string(16) "Example document"
  string(12) "Hello world!"


Optionally, the markup can be fixed by `Tidy <http://php.net/manual/en/book.tidy.php>`_
prior to being loaded.

.. code:: php

   <?php

   $dom = new HtmlDocument();
   $dom->setTidyEnabled(true);
   $dom->loadString($html);


Creating an new document
------------------------

.. code:: php

   <?php

   use Kuria\Dom\HtmlDocument;

   // initialize empty document
   $dom = new HtmlDocument();
   $dom->loadEmpty(['formatOutput' => true]);

   // add <title>
   $title = $dom->getDocument()->createElement('title');
   $title->textContent = 'Lorem ipsum';

   $dom->getHead()->appendChild($title);

   // save
   echo $dom->save();

Output:

::

  <!DOCTYPE html>
  <html>
  <head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <title>Lorem ipsum</title>
  </head>
  <body>
      </body>
  </html>


HTML fragments
==============

Loading an existing fragment
----------------------------

.. code:: php

   <?php

   use Kuria\Dom\HtmlFragment;

   $dom = HtmlFragment::fromString('<div id="test"><span>Hello</span></div>');

   $element = $dom->queryOne('/div[@id="test"]/span');

   if ($element) {
       var_dump($element->textContent);
   }

Output:

::

  string(5) "Hello"


Creating a new fragment
-----------------------

.. code:: php

   <?php

   use Kuria\Dom\HtmlFragment;

   // initialize empty fragment
   $dom = new HtmlFragment();
   $dom->loadEmpty(['formatOutput' => true]);

   // add <a>
   $link = $dom->getDocument()->createElement('a');
   $link->setAttribute('href', 'http://example.com/');
   $link->textContent = 'example';

   $dom->getBody()->appendChild($link);

   // save
   echo $dom->save();

Output:

::

  <a href="http://example.com/">example</a>


XML documents
=============

Loading an existing document
----------------------------

.. code:: php

   <?php

   use Kuria\Dom\XmlDocument;

   $xml = <<<XML
   <?xml version="1.0" encoding="utf-8"?>
   <library>
       <book name="Don Quixote" author="Miguel de Cervantes" />
       <book name="Hamlet" author="William Shakespeare" />
       <book name="Alice's Adventures in Wonderland" author="Lewis Carroll" />
   </library>
   XML;

   $dom = XmlDocument::fromString($xml);

   foreach ($dom->query('/library/book') as $book) {
      /** @var \DOMElement $book */
      var_dump("{$book->getAttribute('name')} by {$book->getAttribute('author')}");
   }

Output:

::

  string(34) "Don Quixote by Miguel de Cervantes"
  string(29) "Hamlet by William Shakespeare"
  string(49) "Alice's Adventures in Wonderland by Lewis Carroll"


Creating a new document
-----------------------

.. code:: php

   <?php

   use Kuria\Dom\XmlDocument;

   // initialize empty document
   $dom = new XmlDocument();
   $dom->loadEmpty(['formatOutput' => true]);

   // add <users>
   $document = $dom->getDocument();
   $document->appendChild($document->createElement('users'));

   // add some users
   $bob = $document->createElement('user');
   $bob->setAttribute('username', 'bob');
   $bob->setAttribute('access-token', '123456');

   $john = $document->createElement('user');
   $john->setAttribute('username', 'john');
   $john->setAttribute('access-token', 'foobar');

   $dom->getRoot()->appendChild($bob);
   $dom->getRoot()->appendChild($john);

   // save
   echo $dom->save();

Output:

::

  <?xml version="1.0" encoding="UTF-8"?>
  <users>
    <user username="bob" access-token="123456"/>
    <user username="john" access-token="foobar"/>
  </users>


Handling XML namespaces in XPath queries
----------------------------------------

.. code:: php

   <?php

   use Kuria\Dom\XmlDocument;

   $xml = <<<XML
   <?xml version="1.0" encoding="UTF-8"?>
   <lib:root xmlns:lib="http://example.com/">
       <lib:book name="Don Quixote" author="Miguel de Cervantes" />
       <lib:book name="Hamlet" author="William Shakespeare" />
       <lib:book name="Alice's Adventures in Wonderland" author="Lewis Carroll" />
   </lib:root>
   XML;

   $dom = XmlDocument::fromString($xml);

   // register namespace in XPath
   $dom->getXpath()->registerNamespace('lib', 'http://example.com/');

   // query using the prefix
   foreach ($dom->query('//lib:book') as $book) {
       /** @var \DOMElement $book */
       var_dump($book->getAttribute('name'));
   }

Output:

::

  string(11) "Don Quixote"
  string(6) "Hamlet"
  string(32) "Alice's Adventures in Wonderland"


XML fragments
=============

Loading an existing fragment
----------------------------

.. code:: php

   <?php

   use Kuria\Dom\XmlFragment;

   $dom = XmlFragment::fromString('<fruits><fruit name="Apple" /><fruit name="Banana" /></fruits>');

   foreach ($dom->query('/fruits/fruit') as $fruit) {
       /** @var \DOMElement $fruit */
       var_dump($fruit->getAttribute('name'));
   }

Output:

::

  string(5) "Apple"
  string(6) "Banana"


Creating a new fragment
-----------------------

.. code:: php

   <?php

   use Kuria\Dom\XmlFragment;

   // initialize empty fragment
   $dom = new XmlFragment();
   $dom->loadEmpty(['formatOutput' => true]);

   // add a new element
   $person = $dom->getDocument()->createElement('person');
   $person->setAttribute('name', 'John Smith');

   $dom->getRoot()->appendChild($person);

   // save
   echo $dom->save();

Output:

::

  <person name="John Smith"/>
