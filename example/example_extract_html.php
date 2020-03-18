<?php

require_once '../vendor/autoload.php';

echo \SimpleHtmlDomParser\HtmlDomParser::file_get_html('http://www.google.com/')->plaintext;
