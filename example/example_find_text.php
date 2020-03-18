<?php

require_once '../vendor/autoload.php';

use SimpleHtmlDomParser\HtmlDomParser;
use SimpleHtmlDomParser\SimpleHtmlDomNode;

/**
 * @param HtmlDomParser $dom
 * @param string $selector
 * @param string $keyword
 * @return array|SimpleHtmlDomNode
 */
function find_contains(HtmlDomParser $dom, string $selector, string $keyword)
{
    // init
    $elements = new SimpleHtmlDomNode();

    foreach ($dom->find($selector) as $e) {
        if (strpos($e->innerText(), $keyword) !== false) {
            $elements[] = $e;
        }
    }

    return $elements;
}

// -----------------------------------------------------------------------------

$html = '
<p class="lall">lall<br></p>
<p class="lall">foo</p>
<ul><li class="lall">test321<br>foo</li><!----></ul>
';

$document = new HtmlDomParser($html);

foreach (find_contains($document, '.lall', 'foo') as $child_dom) {
    echo $child_dom->html() . "\n";
}
