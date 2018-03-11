Changelog
#########

3.0.0
*****

- removed deprecated ``SimpleHtmlParser`` alias
- changed most class members from protected to private
- removed implicit empty document creation
- cs fixes, added codestyle checks


2.2.0
*****

- implemented ``DomContainer::fromString()``
- implemented ``DomContainer::fromDocument()``
- implemented ``DomContainer::exists()``


2.1.0
*****

- moved ``SimpleHtmlParser`` into its own component


2.0.0
*****

- updated to PHP 7.1
- methods now return ``NULL`` on failure instead of ``FALSE``
- removed useless return values from several methods
- code style improvements


1.0.1
*****

- code style and test improvements


1.0.0
*****

- ability to create empty documents
- implemented `removeAll()`
- implemented `getHead()` and `getBody()` for HTML containers
- implemented `getRoot()` for XML containers
- `SimpleHtmlParser` improvements
- code style fixes


0.2.0
*****

- refactoring
- improved HTML document handling
- multiple ways to initialize the container
- new container methods
- minor improvements


0.1.0
*****

Initial release
