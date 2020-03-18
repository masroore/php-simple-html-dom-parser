<?php

require_once '../vendor/autoload.php';

use SimpleHtmlDomParser\HtmlDomParser;

// 1. Write a function with parameter "$element"
function my_callback($element)
{
    switch (strtolower($element->tag)) {
        case 'input':
            $element->outertext = 'input';
            break;
        case 'img':
            $element->outertext = 'img';
            break;
        case 'a':
            $element->outertext = 'a';
            break;
    }
}

// 2. create HTML Dom
$html = HtmlDomParser::file_get_html('http://www.google.com/');

// 3. Register the callback function with it's function name
$html->set_callback('my_callback');

// 4. Callback function will be invoked while dumping
echo $html;
