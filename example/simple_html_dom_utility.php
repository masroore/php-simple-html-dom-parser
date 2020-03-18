<?php

require_once '../vendor/autoload.php';

use SimpleHtmlDomParser\HtmlDomParser;
use SimpleHtmlDomParser\SimpleHtmlDom;

// -----------------------------------------------------------------------------
// remove HTML comments
function html_no_comment($url)
{
    // create HTML DOM
    $html = HtmlDomParser::file_get_html($url);

    // remove all comment elements
    foreach ($html->find('comment') as $e) {
        $e->outertext = '';
    }

    $ret = $html->save();

    // clean up memory
    $html->clear();
    unset($html);

    return $ret;
}

// -----------------------------------------------------------------------------
// search elements that contains an specific text
function find_contains(SimpleHtmlDom $html, string $selector, string $keyword, int $index=-1)
{
    $ret = [];
    foreach ($html->find($selector) as $e) {
        if (strpos($e->innertext, $keyword)!==false) {
            $ret[] = $e;
        }
    }

    if ($index<0) {
        return $ret;
    }

    return $ret[$index] ?? null;
}
