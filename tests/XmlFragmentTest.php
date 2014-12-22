<?php

namespace Kuria\Dom;

class XmlFragmentTest extends DomContainerTest
{
    protected function createTestInstance()
    {
        return new XmlFragment();
    }

    protected function getTestSample()
    {
        return '<container>
    <item name="hello" />
</container>';
    }

    protected function getBrokenSample()
    {
        return '<item';
    }

    protected function getBrokenSampleExpression()
    {
        // XML documents cannot recover from errors, but they can be ignored
        return null;
    }

    protected function getTestSampleExpression()
    {
        return '/container/item';
    }

    protected function getTestSampleExpressionNonMatching()
    {
        return '/item';
    }
}
