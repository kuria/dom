<?php

namespace Kuria\Dom;

/**
 * XML document
 *
 * @author ShiraNai7 <shira.cz>
 */
class XmlDocument extends DomContainer
{
    protected function initialize($content)
    {
        if ($this->ignoreErrors) {
            @$this->document->loadXML($content, $this->libxmlFlags);
        } else {
            $this->document->loadXML($content, $this->libxmlFlags);
        }
    }

    public function getContent()
    {
        return $this->getDocument()->saveXML();
    }
}
