<?php declare(strict_types=1);

namespace SimpleHtmlDomParser;

/**
 * simple html dom node
 * PaperG - added ability for "find" routine to lowercase the value of the
 * selector.
 *
 * PaperG - added $tag_start to track the start position of the tag in the total
 * byte index
 *
 * @package PlaceLocalInclude
 */
class SimpleHtmlDomNode
{
    /**
     * Node type
     *
     * Default is {@see namespace\HDOM_TYPE_TEXT}
     *
     * @var int
     */
    public $nodetype = namespace\HDOM_TYPE_TEXT;

    /**
     * Tag name
     *
     * Default is 'text'
     *
     * @var string
     */
    public $tag = 'text';

    /**
     * List of attributes
     *
     * @var array
     */
    public $attr = [];

    /**
     * List of child node objects
     *
     * @var array
     */
    public $children = [];
    public $nodes = [];

    /**
     * The parent node object
     *
     * @var object|null
     */
    public $parent = null;

    // The "info" array - see HDOM_INFO_... for what each element contains.
    public $_ = [];

    /**
     * Start position of the tag in the document
     *
     * @var int
     */
    public $tag_start = 0;

    /**
     * The DOM object
     *
     * @var object|null
     */
    private $dom = null;

    /**
     * Construct new node object
     *
     * Adds itself to the list of DOM Nodes {@see SimpleHtmlDom::$nodes}
     * @param SimpleHtmlDom $dom
     */
    public function __construct(SimpleHtmlDom $dom)
    {
        $this->dom = $dom;
        $dom->nodes[] = $this;
    }

    public function __destruct()
    {
        $this->clear();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->outertext();
    }

    /**
     * clean up memory due to php5 circular references memory leak...
     */
    public function clear(): void
    {
        $this->dom = null;
        $this->nodes = null;
        $this->parent = null;
        $this->children = null;
    }

    /**
     * dump node's tree
     *
     * @param bool $show_attr
     * @param int $depth
     */
    public function dump(bool $show_attr = true, int $depth = 0): void
    {
        $lead = str_repeat('	', $depth);

        echo $lead . $this->tag;

        if ($show_attr && count($this->attr) > 0) {
            echo '(';
            foreach ($this->attr as $k => $v) {
                echo "[$k]=>\"{$this->$k}\", ";
            }
            echo ')';
        }

        echo "\n";

        if ($this->nodes) {
            foreach ($this->nodes as $c) {
                $c->dump($show_attr, $depth + 1);
            }
        }
    }


    /**
     * Debugging function to dump a single dom node with a bunch of information about it
     *
     * @param bool $echo
     * @return string|void
     */
    public function dump_node(bool $echo = true)
    {
        $string = $this->tag;

        if (count($this->attr) > 0) {
            $string .= '(';
            foreach ($this->attr as $k => $v) {
                $string .= "[$k]=>\"" . $this->$k . '", ';
            }
            $string .= ')';
        }

        if (count($this->_) > 0) {
            $string .= ' $_ (';
            foreach ($this->_ as $k => $v) {
                if (is_array($v)) {
                    $string .= "[$k]=>(";
                    foreach ($v as $k2 => $v2) {
                        $string .= "[$k2]=>\"" . $v2 . '", ';
                    }
                    $string .= ')';
                } else {
                    $string .= "[$k]=>\"" . $v . '", ';
                }
            }
            $string .= ')';
        }

        if (isset($this->text)) {
            $string .= ' text: (' . $this->text . ')';
        }

        $string .= " HDOM_INNER_INFO: '";

        if (isset($this->_[namespace\HDOM_INFO_INNER])) {
            $string .= $this->_[namespace\HDOM_INFO_INNER] . "'";
        } else {
            $string .= ' NULL ';
        }

        $string .= sprintf(' children: %s', count($this->children));
        $string .= sprintf(' nodes: %s', count($this->nodes));
        $string .= " tag_start: {$this->tag_start}\n";

        if ($echo) {
            echo $string;
            return;
        }

        return $string;
    }

    /**
     * Return or set parent node
     *
     * @param object|null $parent (optional) The parent node, `null` to return
     * the current parent node.
     * @return object|null The parent node
     */
    public function parent($parent = null)
    {
        // I am SURE that this doesn't work properly.
        // It fails to unset the current node from it's current parents nodes or
        // children list first.
        if ($parent !== null) {
            $this->parent = $parent;
            $this->parent->nodes[] = $this;
            $this->parent->children[] = $this;
        }

        return $this->parent;
    }

    /**
     * @return bool True if the node has at least one child node
     */
    public function has_child()
    {
        return !empty($this->children);
    }

    /**
     * Get child node at specified index
     *
     * @param int $idx The index of the child node to return, `-1` to return all
     * child nodes.
     * @return object|array|null The child node at the specified index, all child
     * nodes or null if the index is invalid.
     */
    public function children($idx = -1)
    {
        if ($idx === -1) {
            return $this->children;
        }

        if (isset($this->children[$idx])) {
            return $this->children[$idx];
        }

        return null;
    }

    /**
     * Get first child node
     *
     * @return object|null The first child node or null if the current node has
     * no child nodes.
     *
     * @todo Use `empty()` instead of `count()` to improve performance on large
     * arrays.
     */
    public function first_child()
    {
        if (count($this->children) > 0) {
            return $this->children[0];
        }
        return null;
    }

    /**
     * Get last child node
     *
     * @return object|null The last child node or null if the current node has
     * no child nodes.
     *
     * @todo Use `end()` to slightly improve performance on large arrays.
     */
    public function last_child()
    {
        if (($count = count($this->children)) > 0) {
            return $this->children[$count - 1];
        }
        return null;
    }

    /**
     * Get next sibling node
     *
     * @return object|null The sibling node or null if the current node has no
     * sibling nodes.
     */
    public function next_sibling()
    {
        if ($this->parent === null) {
            return null;
        }

        $idx = 0;
        $count = count($this->parent->children);

        while ($idx < $count && $this !== $this->parent->children[$idx]) {
            ++$idx;
        }

        if (++$idx >= $count) {
            return null;
        }

        return $this->parent->children[$idx];
    }

    /**
     * Get previous sibling node
     *
     * @return object|null The sibling node or null if the current node has no
     * sibling nodes.
     */
    public function prev_sibling()
    {
        if ($this->parent === null) {
            return null;
        }

        $idx = 0;
        $count = count($this->parent->children);

        while ($idx < $count && $this !== $this->parent->children[$idx]) {
            ++$idx;
        }

        if (--$idx < 0) {
            return null;
        }

        return $this->parent->children[$idx];
    }

    /**
     * Traverse ancestors to the first matching tag.
     *
     * @param string $tag Tag to find
     * @return object|null First matching node in the DOM tree or null if no
     * match was found.
     *
     * @todo Null is returned implicitly by calling ->parent on the root node.
     * This behaviour could change at any time, rendering this function invalid.
     */
    public function find_ancestor_tag($tag)
    {
        HtmlDomParser::debug_log_entry(1);

        // Start by including ourselves in the comparison.
        $returnDom = $this;

        while ($returnDom !== null) {
            HtmlDomParser::debug_log(2, 'Current tag is: ' . $returnDom->tag);

            if ($returnDom->tag === $tag) {
                break;
            }

            $returnDom = $returnDom->parent;
        }

        return $returnDom;
    }

    /**
     * Get node's inner text (everything inside the opening and closing tags)
     *
     * @return string
     */
    public function innertext()
    {
        if (isset($this->_[namespace\HDOM_INFO_INNER])) {
            return $this->_[namespace\HDOM_INFO_INNER];
        }

        if (isset($this->_[namespace\HDOM_INFO_TEXT])) {
            return $this->dom->restore_noise($this->_[namespace\HDOM_INFO_TEXT]);
        }

        $ret = '';

        foreach ($this->nodes as $n) {
            $ret .= $n->outertext();
        }

        return $ret;
    }

    /**
     * Get node's outer text (everything including the opening and closing tags)
     *
     * @return string
     */
    public function outertext()
    {
        $text = '';

        if ($this->tag === 'text') {
            if (!empty($this->text)) {
                $text = ' with text: ' . $this->text;
            }
        }

        HtmlDomParser::debug_log(1, 'Innertext of tag: ' . $this->tag . $text);

        if ($this->tag === 'root') {
            return $this->innertext();
        }

        // trigger callback
        if ($this->dom && $this->dom->callback !== null) {
            call_user_func_array($this->dom->callback, [$this]);
        }

        if (isset($this->_[namespace\HDOM_INFO_OUTER])) {
            return $this->_[namespace\HDOM_INFO_OUTER];
        }

        if (isset($this->_[namespace\HDOM_INFO_TEXT])) {
            return $this->dom->restore_noise($this->_[namespace\HDOM_INFO_TEXT]);
        }

        // render begin tag
        if ($this->dom && $this->dom->nodes[$this->_[namespace\HDOM_INFO_BEGIN]]) {
            $ret = $this->dom->nodes[$this->_[namespace\HDOM_INFO_BEGIN]]->makeup();
        } else {
            $ret = '';
        }

        // render inner text
        if (isset($this->_[namespace\HDOM_INFO_INNER])) {
            // If it's a br tag...  don't return the namespace\HDOM_INNER_INFO that we
            // may or may not have added.
            if ($this->tag !== 'br') {
                $ret .= $this->_[namespace\HDOM_INFO_INNER];
            }
        } else {
            if ($this->nodes) {
                foreach ($this->nodes as $n) {
                    $ret .= $this->convert_text($n->outertext());
                }
            }
        }

        // render end tag
        if (isset($this->_[namespace\HDOM_INFO_END]) && $this->_[namespace\HDOM_INFO_END] != 0) {
            $ret .= '</' . $this->tag . '>';
        }

        return $ret;
    }

    /**
     * Get node's plain text (everything excluding all tags)
     *
     * @return string
     */
    public function text()
    {
        if (isset($this->_[namespace\HDOM_INFO_INNER])) {
            return $this->_[namespace\HDOM_INFO_INNER];
        }

        switch ($this->nodetype) {
            case namespace\HDOM_TYPE_TEXT:
                return $this->dom->restore_noise($this->_[namespace\HDOM_INFO_TEXT]);
            case namespace\HDOM_TYPE_UNKNOWN:
            case namespace\HDOM_TYPE_COMMENT:
                return '';
        }

        if (strcasecmp($this->tag, 'script') === 0) {
            return '';
        }
        if (strcasecmp($this->tag, 'style') === 0) {
            return '';
        }

        $ret = '';

        // In rare cases, (always node type 1 or namespace\HDOM_TYPE_ELEMENT - observed
        // for some span tags, and some p tags) $this->nodes is set to NULL.
        // NOTE: This indicates that there is a problem where it's set to NULL
        // without a clear happening.
        // WHY is this happening?
        if ($this->nodes !== null) {
            foreach ($this->nodes as $n) {
                // Start paragraph after a blank line
                if ($n->tag === 'p') {
                    $ret .= "\n\n";
                }

                $ret .= $this->convert_text($n->text());

                // If this node is a span... add a space at the end of it so
                // multiple spans don't run into each other.  This is plaintext
                // after all.
                if ($n->tag === 'span') {
                    $ret .= $this->dom->default_span_text;
                }
            }
        }
        return trim($ret);
    }

    /**
     * Get node's xml text (inner text as a CDATA section)
     *
     * @return string
     */
    public function xmltext()
    {
        $ret = $this->innertext();
        $ret = str_ireplace('<![CDATA[', '', $ret);
        $ret = str_replace(']]>', '', $ret);
        return $ret;
    }

    // build node's text with tag
    public function makeup()
    {
        // text, comment, unknown
        if (isset($this->_[namespace\HDOM_INFO_TEXT])) {
            return $this->dom->restore_noise($this->_[namespace\HDOM_INFO_TEXT]);
        }

        $ret = '<' . $this->tag;
        $i = -1;

        foreach ($this->attr as $key => $val) {
            ++$i;

            // skip removed attribute
            if ($val === null || $val === false) {
                continue;
            }

            $ret .= $this->_[namespace\HDOM_INFO_SPACE][$i][0];

            //no value attr: nowrap, checked selected...
            if ($val === true) {
                $ret .= $key;
            } else {
                switch ($this->_[namespace\HDOM_INFO_QUOTE][$i]) {
                    case namespace\HDOM_QUOTE_DOUBLE:
                        $quote = '"';
                        break;
                    case namespace\HDOM_QUOTE_SINGLE:
                        $quote = '\'';
                        break;
                    default:
                        $quote = '';
                }

                $ret .= $key
                    . $this->_[namespace\HDOM_INFO_SPACE][$i][1]
                    . '='
                    . $this->_[namespace\HDOM_INFO_SPACE][$i][2]
                    . $quote
                    . $val
                    . $quote;
            }
        }

        $ret = $this->dom->restore_noise($ret);
        return $ret . $this->_[namespace\HDOM_INFO_ENDSPACE] . '>';
    }

    /**
     * Find elements by CSS selector
     *
     * @param string $selector The CSS selector
     * @param int|null $idx Index of element to return form the list of matching
     * elements (default: `null` = disabled).
     * @param bool $lowercase Matches tag names case insensitive (lowercase) if
     * enabled (default: `false`)
     * @return array|object|null A list of elements matching the specified CSS
     * selector or a single element if $idx is specified or null if no element
     * was found.
     */
    public function find($selector, $idx = null, $lowercase = false)
    {
        $selectors = $this->parse_selector($selector);
        if (($count = count($selectors)) === 0) {
            return [];
        }
        $found_keys = [];

        // find each selector
        for ($c = 0; $c < $count; ++$c) {
            // The change on the below line was documented on the sourceforge
            // code tracker id 2788009
            // used to be: if (($level=count($selectors[0]))===0) return array();
            if (($level = count($selectors[$c])) === 0) {
                return [];
            }

            if (!isset($this->_[namespace\HDOM_INFO_BEGIN])) {
                return [];
            }

            $head = [$this->_[namespace\HDOM_INFO_BEGIN] => 1];
            $cmd = ' '; // Combinator

            // handle descendant selectors, no recursive!
            for ($l = 0; $l < $level; ++$l) {
                $ret = [];

                foreach ($head as $k => $v) {
                    $n = ($k === -1) ? $this->dom->root : $this->dom->nodes[$k];
                    //PaperG - Pass this optional parameter on to the seek function.
                    $n->seek($selectors[$c][$l], $ret, $cmd, $lowercase);
                }

                $head = $ret;
                $cmd = $selectors[$c][$l][4]; // Next Combinator
            }

            foreach ($head as $k => $v) {
                if (!isset($found_keys[$k])) {
                    $found_keys[$k] = 1;
                }
            }
        }

        // sort keys
        ksort($found_keys);

        $found = [];
        foreach ($found_keys as $k => $v) {
            $found[] = $this->dom->nodes[$k];
        }

        // return nth-element or array
        if ($idx === null) {
            return $found;
        }

        if ($idx < 0) {
            $idx = count($found) + $idx;
        }

        return $found[$idx] ?? null;
    }

    /**
     * Seek DOM elements by selector
     *
     * **Note**
     * The selector element must be compatible to a selector from
     * {@see SimpleHtmlDomNode::parse_selector()}
     *
     * @param array $selector A selector element
     * @param array $ret An array of matches
     * @param bool $lowercase Matches tag names case insensitive (lowercase) if
     * enabled (default: `false`)
     * @return void
     */
    protected function seek($selector, &$ret, $parent_cmd, $lowercase = false)
    {
        HtmlDomParser::debug_log_entry(1);

        list($tag, $id, $class, $attributes, $cmb) = $selector;
        $nodes = [];

        if ($parent_cmd === ' ') { // Descendant Combinator
            // Find parent closing tag if the current element doesn't have a closing
            // tag (i.e. void element)
            $end = !empty($this->_[namespace\HDOM_INFO_END]) ? $this->_[namespace\HDOM_INFO_END] : 0;
            if ($end === 0) {
                $parent = $this->parent;
                while (!isset($parent->_[namespace\HDOM_INFO_END]) && $parent !== null) {
                    --$end;
                    $parent = $parent->parent;
                }
                $end += $parent->_[namespace\HDOM_INFO_END];
            }

            // Get list of target nodes
            $nodes_start = $this->_[namespace\HDOM_INFO_BEGIN] + 1;
            $nodes_count = $end - $nodes_start;
            $nodes = array_slice($this->dom->nodes, $nodes_start, $nodes_count, true);
        } elseif ($parent_cmd === '>') { // Child Combinator
            $nodes = $this->children;
        } elseif ($parent_cmd === '+'
            && $this->parent
            && in_array($this, $this->parent->children, true)) { // Next-Sibling Combinator
            $index = array_search($this, $this->parent->children, true) + 1;
            $nodes[] = $this->parent->children[$index];
        } elseif ($parent_cmd === '~'
            && $this->parent
            && in_array($this, $this->parent->children, true)) { // Subsequent Sibling Combinator
            $index = array_search($this, $this->parent->children, true);
            $nodes = array_slice($this->parent->children, $index);
        }

        // Go throgh each element starting at this element until the end tag
        // Note: If this element is a void tag, any previous void element is
        // skipped.
        foreach ($nodes as $node) {
            $pass = true;

            // Skip root nodes
            if (!$node->parent) {
                $pass = false;
            }

            // Skip if node isn't a child node (i.e. text nodes)
            if ($pass && !in_array($node, $node->parent->children, true)) {
                $pass = false;
            }

            // Skip if tag doesn't match
            if ($pass && $tag !== '' && $tag !== $node->tag && $tag !== '*') {
                $pass = false;
            }

            // Skip if ID doesn't exist
            if ($pass && $id !== '' && !isset($node->attr['id'])) {
                $pass = false;
            }

            // Check if ID matches
            if ($pass && $id !== '' && isset($node->attr['id'])) {
                // Note: Only consider the first ID (as browsers do)
                $node_id = explode(' ', trim($node->attr['id']))[0];

                if ($id !== $node_id) {
                    $pass = false;
                }
            }

            // Check if all class(es) exist
            if ($pass && $class !== '' && is_array($class) && !empty($class)) {
                if (isset($node->attr['class'])) {
                    $node_classes = explode(' ', $node->attr['class']);

                    if ($lowercase) {
                        $node_classes = array_map('strtolower', $node_classes);
                    }

                    foreach ($class as $c) {
                        if (!in_array($c, $node_classes)) {
                            $pass = false;
                            break;
                        }
                    }
                } else {
                    $pass = false;
                }
            }

            // Check attributes
            if ($pass
                && $attributes !== ''
                && is_array($attributes)
                && !empty($attributes)) {
                foreach ($attributes as $a) {
                    [
                        $att_name,
                        $att_expr,
                        $att_val,
                        $att_inv,
                        $att_case_sensitivity
                    ] = $a;

                    // Handle indexing attributes (i.e. "[2]")
                    /**
                     * Note: This is not supported by the CSS Standard but adds
                     * the ability to select items compatible to XPath (i.e.
                     * the 3rd element within it's parent).
                     *
                     * Note: This doesn't conflict with the CSS Standard which
                     * doesn't work on numeric attributes anyway.
                     */
                    if (is_numeric($att_name)
                        && $att_expr === ''
                        && $att_val === '') {
                        $count = 0;

                        // Find index of current element in parent
                        foreach ($node->parent->children as $c) {
                            if ($c->tag === $node->tag) {
                                ++$count;
                            }
                            if ($c === $node) {
                                break;
                            }
                        }

                        // If this is the correct node, continue with next
                        // attribute
                        if ($count === (int)$att_name) {
                            continue;
                        }
                    }

                    // Check attribute availability
                    if ($att_inv) { // Attribute should NOT be set
                        if (isset($node->attr[$att_name])) {
                            $pass = false;
                            break;
                        }
                    } elseif ($att_name !== 'plaintext'
                        && !isset($node->attr[$att_name])) {
                        // todo: "plaintext" is not a valid CSS selector!
                        // Attribute should be set
                        $pass = false;
                        break;
                    }

                    // Continue with next attribute if expression isn't defined
                    if ($att_expr === '') {
                        continue;
                    }

                    // If they have told us that this is a "plaintext"
                    // search then we want the plaintext of the node - right?
                    // todo "plaintext" is not a valid CSS selector!
                    if ($att_name === 'plaintext') {
                        $nodeKeyValue = $node->text();
                    } else {
                        $nodeKeyValue = $node->attr[$att_name];
                    }

                    HtmlDomParser::debug_log(
                        2,
                        'testing node: '
                        . $node->tag
                        . ' for attribute: '
                        . $att_name
                        . $att_expr
                        . $att_val
                        . ' where nodes value is: '
                        . $nodeKeyValue
                    );

                    // If lowercase is set, do a case insensitive test of
                    // the value of the selector.
                    if ($lowercase) {
                        $check = $this->match(
                            $att_expr,
                            strtolower($att_val),
                            strtolower($nodeKeyValue),
                            $att_case_sensitivity
                        );
                    } else {
                        $check = $this->match(
                            $att_expr,
                            $att_val,
                            $nodeKeyValue,
                            $att_case_sensitivity
                        );
                    }

                    HtmlDomParser::debug_log(2, 'after match: ' . ($check ? 'true' : 'false'));

                    if (!$check) {
                        $pass = false;
                        break;
                    }
                }
            }

            // Found a match. Add to list and clear node
            if ($pass) {
                $ret[$node->_[namespace\HDOM_INFO_BEGIN]] = 1;
            }
            unset($node);
        }

        // It's passed by reference so this is actually what this function returns.
        HtmlDomParser::debug_log(1, 'EXIT - ret: ', $ret);
    }

    /**
     * Match value and pattern for a given CSS expression
     *
     * **Supported Expressions**
     *
     * | Expression | Description
     * | ---------- | -----------
     * | `=`        | $value and $pattern must be equal
     * | `!=`       | $value and $pattern must not be equal
     * | `^=`       | $value must start with $pattern
     * | `$=`       | $value must end with $pattern
     * | `*=`       | $value must contain $pattern
     *
     * @param string $exp The expression.
     * @param string $pattern The pattern
     * @param string $value The value
     * @value bool True if $value matches $pattern
     * @return bool|false|int
     */
    protected function match(string $exp, string $pattern, string $value, bool $case_sensitivity)
    {
        HtmlDomParser::debug_log_entry(1);

        if ($case_sensitivity === 'i') {
            $pattern = strtolower($pattern);
            $value = strtolower($value);
        }

        switch ($exp) {
            case '=':
                return ($value === $pattern);
            case '!=':
                return ($value !== $pattern);
            case '^=':
                return preg_match('/^' . preg_quote($pattern, '/') . '/', $value);
            case '$=':
                return preg_match('/' . preg_quote($pattern, '/') . '$/', $value);
            case '*=':
                return preg_match('/' . preg_quote($pattern, '/') . '/', $value);
            case '|=':
                /**
                 * [att|=val]
                 *
                 * Represents an element with the att attribute, its value
                 * either being exactly "val" or beginning with "val"
                 * immediately followed by "-" (U+002D).
                 */
                return strpos($value, $pattern) === 0;
            case '~=':
                /**
                 * [att~=val]
                 *
                 * Represents an element with the att attribute whose value is a
                 * whitespace-separated list of words, one of which is exactly
                 * "val". If "val" contains whitespace, it will never represent
                 * anything (since the words are separated by spaces). Also if
                 * "val" is the empty string, it will never represent anything.
                 */
                return in_array($pattern, explode(' ', trim($value)), true);
        }
        return false;
    }

    /**
     * Parse CSS selector
     *
     * @param string $selector_string CSS selector string
     * @return array List of CSS selectors. The format depends on the type of
     * selector:
     *
     * ```php
     *
     * array( // list of selectors (each separated by a comma), i.e. 'img, p, div'
     *   array( // list of combinator selectors, i.e. 'img > p > div'
     *     array( // selector element
     *       [0], // (string) The element tag
     *       [1], // (string) The element id
     *       [2], // (array<string>) The element classes
     *       [3], // (array<array<string>>) The list of attributes, each
     *            // with four elements: name, expression, value, inverted
     *       [4]  // (string) The selector combinator (' ' | '>' | '+' | '~')
     *     )
     *   )
     * )
     * ```
     *
     * @link https://www.w3.org/TR/selectors/#compound Compound selector
     */
    protected function parse_selector(string $selector_string): array
    {
        HtmlDomParser::debug_log_entry(1);

        /**
         * Pattern of CSS selectors, modified from mootools (https://mootools.net/)
         *
         * Paperg: Add the colon to the attribute, so that it properly finds
         * <tag attr:ibute="something" > like google does.
         *
         * Note: if you try to look at this attribute, you MUST use getAttribute
         * since $dom->x:y will fail the php syntax check.
         *
         * Notice the \[ starting the attribute? and the @? following? This
         * implies that an attribute can begin with an @ sign that is not
         * captured. This implies that an html attribute specifier may start
         * with an @ sign that is NOT captured by the expression. Farther study
         * is required to determine of this should be documented or removed.
         *
         * Matches selectors in this order:
         *
         * [0] - full match
         *
         * [1] - tag name
         *     ([\w:\*-]*)
         *     Matches the tag name consisting of zero or more words, colons,
         *     asterisks and hyphens.
         *
         * [2] - id name
         *     (?:\#([\w-]+))
         *     Optionally matches a id name, consisting of an "#" followed by
         *     the id name (one or more words and hyphens).
         *
         * [3] - class names (including dots)
         *     (?:\.([\w\.-]+))?
         *     Optionally matches a list of classs, consisting of an "."
         *     followed by the class name (one or more words and hyphens)
         *     where multiple classes can be chained (i.e. ".foo.bar.baz")
         *
         * [4] - attributes
         *     ((?:\[@?(?:!?[\w:-]+)(?:(?:[!*^$|~]?=)[\"']?(?:.*?)[\"']?)?(?:\s*?(?:[iIsS])?)?\])+)?
         *     Optionally matches the attributes list
         *
         * [5] - separator
         *     ([\/, >+~]+)
         *     Matches the selector list separator
         */
        // phpcs:ignore Generic.Files.LineLength
        $pattern = "/([\w:\*-]*)(?:\#([\w-]+))?(?:|\.([\w\.-]+))?((?:\[@?(?:!?[\w:-]+)(?:(?:[!*^$|~]?=)[\"']?(?:.*?)[\"']?)?(?:\s*?(?:[iIsS])?)?\])+)?([\/, >+~]+)/is";

        preg_match_all(
            $pattern,
            trim($selector_string) . ' ', // Add final ' ' as pseudo separator
            $matches,
            PREG_SET_ORDER
        );

        HtmlDomParser::debug_log(2, 'Matches Array: ', $matches);

        $selectors = [];
        $result = [];

        foreach ($matches as $m) {
            $m[0] = trim($m[0]);

            // Skip NoOps
            if ($m[0] === '' || $m[0] === '/' || $m[0] === '//') {
                continue;
            }

            // Convert to lowercase
            if ($this->dom->lowercase) {
                $m[1] = strtolower($m[1]);
            }

            // Extract classes
            if ($m[3] !== '') {
                $m[3] = explode('.', $m[3]);
            }

            /* Extract attributes (pattern based on the pattern above!)

             * [0] - full match
             * [1] - attribute name
             * [2] - attribute expression
             * [3] - attribute value
             * [4] - case sensitivity
             *
             * Note: Attributes can be negated with a "!" prefix to their name
             */
            if ($m[4] !== '') {
                preg_match_all(
                    "/\[@?(!?[\w:-]+)(?:([!*^$|~]?=)[\"']?(.*?)[\"']?)?(?:\s*?([iIsS])?)?\]/is",
                    trim($m[4]),
                    $attributes,
                    PREG_SET_ORDER
                );

                // Replace element by array
                $m[4] = [];

                foreach ($attributes as $att) {
                    // Skip empty matches
                    if (trim($att[0]) === '') {
                        continue;
                    }

                    $inverted = (isset($att[1][0]) && $att[1][0] === '!');
                    $m[4][] = [
                        $inverted ? substr($att[1], 1) : $att[1], // Name
                        $att[2] ?? '', // Expression
                        $att[3] ?? '', // Value
                        $inverted, // Inverted Flag
                        isset($att[4]) ? strtolower($att[4]) : '', // Case-Sensitivity
                    ];
                }
            }

            // Sanitize Separator
            if ($m[5] !== '' && trim($m[5]) === '') { // Descendant Separator
                $m[5] = ' ';
            } else { // Other Separator
                $m[5] = trim($m[5]);
            }

            // Clear Separator if it's a Selector List
            if ($is_list = ($m[5] === ',')) {
                $m[5] = '';
            }

            // Remove full match before adding to results
            array_shift($m);
            $result[] = $m;

            if ($is_list) { // Selector List
                $selectors[] = $result;
                $result = [];
            }
        }

        if (count($result) > 0) {
            $selectors[] = $result;
        }
        return $selectors;
    }

    /**
     * @param string $name
     * @return bool|false|string
     */
    public function __get(string $name)
    {
        if (isset($this->attr[$name])) {
            return $this->convert_text($this->attr[$name]);
        }
        switch ($name) {
            case 'outertext':
                return $this->outertext();
            case 'innertext':
                return $this->innertext();
            case 'plaintext':
                return $this->text();
            case 'xmltext':
                return $this->xmltext();
            default:
                return array_key_exists($name, $this->attr);
        }
    }

    /**
     * @param string $name
     * @param $value
     * @return mixed
     */
    public function __set(string $name, $value)
    {
        HtmlDomParser::debug_log_entry(1);

        switch ($name) {
            case 'outertext':
                return $this->_[namespace\HDOM_INFO_OUTER] = $value;
            case 'innertext':
                if (isset($this->_[namespace\HDOM_INFO_TEXT])) {
                    return $this->_[namespace\HDOM_INFO_TEXT] = $value;
                }
                return $this->_[namespace\HDOM_INFO_INNER] = $value;
        }

        if (!isset($this->attr[$name])) {
            $this->_[namespace\HDOM_INFO_SPACE][] = [' ', '', ''];
            $this->_[namespace\HDOM_INFO_QUOTE][] = namespace\HDOM_QUOTE_DOUBLE;
        }

        $this->attr[$name] = $value;
    }

    /**
     * @param $name
     * @return bool
     */
    public function __isset($name)
    {
        if ($name === 'innertext' || $name === 'plaintext' || $name === 'outertext') {
            return true;
        }
        //no value attr: nowrap, checked selected...
        return array_key_exists($name, $this->attr) ? true : isset($this->attr[$name]);
    }

    /**
     * @param $name
     */
    public function __unset($name)
    {
        if (isset($this->attr[$name])) {
            unset($this->attr[$name]);
        }
    }

    /**
     * Function to convert the text from one character set to another if the two sets are not the same.
     *
     * @param string $text
     * @return bool|false|string
     */
    public function convert_text(string $text)
    {
        HtmlDomParser::debug_log_entry(1);

        $converted_text = $text;

        $sourceCharset = '';
        $targetCharset = '';

        if ($this->dom) {
            $sourceCharset = strtoupper($this->dom->_charset);
            $targetCharset = strtoupper($this->dom->_target_charset);
        }

        HtmlDomParser::debug_log(3, "source charset: {$sourceCharset} target charset: {$targetCharset}");

        if (!empty($sourceCharset)
            && !empty($targetCharset)
            && strcasecmp($sourceCharset, $targetCharset) !== 0) {
            // Check if the reported encoding could have been incorrect and the text is actually already UTF-8
            $converted_text = (strcasecmp($targetCharset, 'UTF-8') === 0) && self::is_utf8($text) ? $text : iconv($sourceCharset, $targetCharset, $text);
        }

        // Lets make sure that we don't have that silly BOM issue with any of the utf-8 text we output.
        if ($targetCharset === 'UTF-8') {
            if (substr($converted_text, 0, 3) === "\xef\xbb\xbf") {
                $converted_text = substr($converted_text, 3);
            }

            if (substr($converted_text, -3) === "\xef\xbb\xbf") {
                $converted_text = substr($converted_text, 0, -3);
            }
        }

        return $converted_text;
    }

    /**
     * Returns true if $string is valid UTF-8 and false otherwise.
     *
     * @param mixed $str String to be tested
     * @return boolean
     */
    public static function is_utf8(string $str): bool
    {
        $len = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            $c = ord($str[$i]);
            if ($c > 128) {
                if ($c >= 254) {
                    return false;
                }

                if ($c >= 252) {
                    $bits = 6;
                } elseif ($c >= 248) {
                    $bits = 5;
                } elseif ($c >= 240) {
                    $bits = 4;
                } elseif ($c >= 224) {
                    $bits = 3;
                } elseif ($c >= 192) {
                    $bits = 2;
                } else {
                    return false;
                }

                if (($i + $bits) > $len) {
                    return false;
                }

                while ($bits > 1) {
                    $i++;
                    $b = ord($str[$i]);
                    if ($b < 128 || $b > 191) {
                        return false;
                    }
                    $bits--;
                }
            }
        }

        return true;
    }

    /**
     * Function to try a few tricks to determine the displayed size of an img on
     * the page. NOTE: This will ONLY work on an IMG tag. Returns FALSE on all
     * other tag types.
     *
     * @return array|bool an array containing the 'height' and 'width' of the image
     * on the page or -1 if we can't figure it out.
     * @version April 19 2012
     * @author John Schlick
     */
    public function get_display_size()
    {
        $width = -1;
        $height = -1;

        if ($this->tag !== 'img') {
            return false;
        }

        // See if there is aheight or width attribute in the tag itself.
        if (isset($this->attr['width'])) {
            $width = $this->attr['width'];
        }

        if (isset($this->attr['height'])) {
            $height = $this->attr['height'];
        }

        // Now look for an inline style.
        if (isset($this->attr['style'])) {
            // Thanks to user gnarf from stackoverflow for this regular expression.
            $attributes = [];

            preg_match_all(
                '/([\w-]+)\s*:\s*([^;]+)\s*;?/',
                $this->attr['style'],
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $match) {
                $attributes[$match[1]] = $match[2];
            }

            // If there is a width in the style attributes:
            if (isset($attributes['width']) && $width == -1) {
                // check that the last two characters are px (pixels)
                if (strtolower(substr($attributes['width'], -2)) === 'px') {
                    $proposed_width = substr($attributes['width'], 0, -2);
                    // Now make sure that it's an integer and not something stupid.
                    if (filter_var($proposed_width, FILTER_VALIDATE_INT)) {
                        $width = $proposed_width;
                    }
                }
            }

            // If there is a width in the style attributes:
            if (isset($attributes['height']) && $height == -1) {
                // check that the last two characters are px (pixels)
                if (strtolower(substr($attributes['height'], -2)) === 'px') {
                    $proposed_height = substr($attributes['height'], 0, -2);
                    // Now make sure that it's an integer and not something stupid.
                    if (filter_var($proposed_height, FILTER_VALIDATE_INT)) {
                        $height = $proposed_height;
                    }
                }
            }
        }

        // Future enhancement:
        // Look in the tag to see if there is a class or id specified that has
        // a height or width attribute to it.

        // Far future enhancement
        // Look at all the parent tags of this image to see if they specify a
        // class or id that has an img selector that specifies a height or width
        // Note that in this case, the class or id will have the img subselector
        // for it to apply to the image.

        // ridiculously far future development
        // If the class or id is specified in a SEPARATE css file thats not on
        // the page, go get it and do what we were just doing for the ones on
        // the page.

        $result = [
            'height' => $height,
            'width' => $width
        ];

        return $result;
    }

    /**
     * @return array
     */
    public function getAllAttributes()
    {
        return $this->attr;
    }

    /**
     * @param string $name
     * @return bool|false|string
     */
    public function getAttribute(string $name)
    {
        return $this->__get($name);
    }

    /**
     * @param string $name
     * @param $value
     */
    public function setAttribute(string $name, $value): void
    {
        $this->__set($name, $value);
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasAttribute(string $name): bool
    {
        return $this->__isset($name);
    }

    /**
     * @param string $name
     */
    public function removeAttribute(string $name): void
    {
        $this->__set($name, null);
    }

    /**
     * @param string $id
     * @return array|object|null
     */
    public function getElementById(string $id)
    {
        return $this->find("#$id", 0);
    }

    /**
     * @param string $id
     * @param int|null $idx
     * @return array|object|null
     */
    public function getElementsById(string $id, int $idx = null)
    {
        return $this->find("#$id", $idx);
    }

    /**
     * @param string $name
     * @return array|object|null
     */
    public function getElementByTagName(string $name)
    {
        return $this->find($name, 0);
    }

    /**
     * @param string $name
     * @param int|null $idx
     * @return array|object|null
     */
    public function getElementsByTagName(string $name, int $idx = null)
    {
        return $this->find($name, $idx);
    }

    /**
     * @return object|null
     */
    public function parentNode()
    {
        return $this->parent();
    }

    /**
     * @param int $idx
     * @return array|object|null
     */
    public function childNodes($idx = -1)
    {
        return $this->children($idx);
    }

    /**
     * @return object|null
     */
    public function firstChild()
    {
        return $this->first_child();
    }

    /**
     * @return object|null
     */
    public function lastChild()
    {
        return $this->last_child();
    }

    /**
     * @return object|null
     */
    public function nextSibling()
    {
        return $this->next_sibling();
    }

    /**
     * @return object|null
     */
    public function previousSibling()
    {
        return $this->prev_sibling();
    }

    /**
     * @return bool
     */
    public function hasChildNodes(): bool
    {
        return $this->has_child();
    }

    /**
     * @return string
     */
    public function nodeName(): string
    {
        return $this->tag;
    }

    /**
     * @param SimpleHtmlDomNode $node
     * @return SimpleHtmlDomNode
     */
    public function appendChild(SimpleHtmlDomNode $node): SimpleHtmlDomNode
    {
        $node->parent($this);
        return $node;
    }
}
