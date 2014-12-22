<?php

namespace Kuria\Dom;

class HtmlFragmentTest extends DomContainerTest
{
    protected function createTestInstance()
    {
        return new HtmlFragment();
    }

    protected function getTestSample()
    {
        return '<h1>Lorem ipsum</h1>
<p>Lorem <span>ipsum</span> dolor sit amet</p>';
    }

    protected function getBrokenSample()
    {
        return '<div';
    }

    protected function getBrokenSampleExpression()
    {
        return '//div';
    }

    protected function getTestSampleExpression()
    {
        return '/p/span';
    }

    protected function getTestSampleExpressionNonMatching()
    {
        return '/div';
    }
}
