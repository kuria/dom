<?php

namespace Kuria\Dom;

class XmlDocumentTest extends DomContainerTest
{
    protected function createTestInstance()
    {
        return new XmlDocument();
    }

    protected function getTestSample()
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<root xmlns:f="http://www.w3schools.com/furniture">
    <item name="hello" />
</root>';
    }

    protected function getBrokenSample()
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<root></root>
<item>';
    }

    protected function getBrokenSampleExpression()
    {
        // XML documents cannot recover from errors, but they can be ignored
        return null;
    }

    protected function getTestSampleExpression()
    {
        return '/root/item';
    }

    protected function getTestSampleExpressionNonMatching()
    {
        return '/item';
    }
}
