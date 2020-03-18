<?php declare(strict_types=1);

namespace SimpleHtmlDomParser;

/**
 * simple html dom parser
 *
 * Paperg - in the find routine: allow us to specify that we want case
 * insensitive testing of the value of the selector.
 *
 * Paperg - change $size from protected to public so we can easily access it
 *
 * Paperg - added ForceTagsClosed in the constructor which tells us whether we
 * trust the html or not.  Default is to NOT trust it.
 *
 * @package PlaceLocalInclude
 */
class SimpleHtmlDom
{
    /**
     * The root node of the document
     *
     * @var object
     */
    public $root = null;

    /**
     * List of nodes in the current DOM
     *
     * @var array
     */
    public $nodes = [];

    /**
     * Callback function to run for each element in the DOM.
     *
     * @var callable|null
     */
    public $callback = null;

    /**
     * Indicates how tags and attributes are matched
     *
     * @var bool When set to **true** tags and attributes will be converted to
     * lowercase before matching.
     */
    public $lowercase = false;

    /**
     * Original document size
     *
     * Holds the original document size.
     *
     * @var int
     */
    public $original_size;

    /**
     * Current document size
     *
     * Holds the current document size. The document size is determined by the
     * string length of ({@see SimpleHtmlDom::$doc}).
     *
     * _Note_: Using this variable is more efficient than calling `strlen($doc)`
     *
     * @var int
     * */
    public $size;

    /**
     * Current position in the document
     *
     * @var int
     */
    protected $pos;

    /**
     * The document
     *
     * @var string
     */
    protected $doc;

    /**
     * Current character
     *
     * Holds the current character at position {@see SimpleHtmlDom::$pos} in
     * the document {@see SimpleHtmlDom::$doc}
     *
     * _Note_: Using this variable is more efficient than calling
     * `substr($doc, $pos, 1)`
     *
     * @var string
     */
    protected $char;

    protected $cursor;

    /**
     * Parent node of the next node detected by the parser
     *
     * @var object
     */
    protected $parent;
    protected $noise = [];

    /**
     * Tokens considered blank in HTML
     *
     * @var string
     */
    protected $token_blank = " \t\r\n";

    /**
     * Tokens to identify the equal sign for attributes, stopping either at the
     * closing tag ("/" i.e. "<html />") or the end of an opening tag (">" i.e.
     * "<html>")
     *
     * @var string
     */
    protected $token_equal = ' =/>';

    /**
     * Tokens to identify the end of a tag name. A tag name either ends on the
     * ending slash ("/" i.e. "<html/>") or whitespace ("\s\r\n\t")
     *
     * @var string
     */
    protected $token_slash = " />\r\n\t";

    /**
     * Tokens to identify the end of an attribute
     *
     * @var string
     */
    protected $token_attr = ' >';

    // Note that this is referenced by a child node, and so it needs to be
    // public for that node to see this information.
    public $_charset = '';
    public $_target_charset = '';

    /**
     * Innertext for <br> elements
     *
     * @var string
     */
    protected $default_br_text = '';

    /**
     * Suffix for <span> elements
     *
     * @var string
     */
    public $default_span_text = '';

    /**
     * Defines a list of self-closing tags (Void elements) according to the HTML
     * Specification
     *
     * _Remarks_:
     * - Use `isset()` instead of `in_array()` on array elements to boost
     * performance about 30%
     * - Sort elements by name for better readability!
     *
     * @link https://www.w3.org/TR/html HTML Specification
     * @link https://www.w3.org/TR/html/syntax.html#void-elements Void elements
     */
    protected $self_closing_tags = [
        'area' => 1,
        'base' => 1,
        'br' => 1,
        'col' => 1,
        'embed' => 1,
        'hr' => 1,
        'img' => 1,
        'input' => 1,
        'link' => 1,
        'meta' => 1,
        'param' => 1,
        'source' => 1,
        'track' => 1,
        'wbr' => 1
    ];

    /**
     * Defines a list of tags which - if closed - close all optional closing
     * elements within if they haven't been closed yet. (So, an element where
     * neither opening nor closing tag is omissible consistently closes every
     * optional closing element within)
     *
     * _Remarks_:
     * - Use `isset()` instead of `in_array()` on array elements to boost
     * performance about 30%
     * - Sort elements by name for better readability!
     */
    protected $block_tags = [
        'body' => 1,
        'div' => 1,
        'form' => 1,
        'root' => 1,
        'span' => 1,
        'table' => 1
    ];

    /**
     * Defines elements whose end tag is omissible.
     *
     * * key = Name of an element whose end tag is omissible.
     * * value = Names of elements whose end tag is omissible, that are closed
     * by the current element.
     *
     * _Remarks_:
     * - Use `isset()` instead of `in_array()` on array elements to boost
     * performance about 30%
     * - Sort elements by name for better readability!
     *
     * **Example**
     *
     * An `li` element’s end tag may be omitted if the `li` element is immediately
     * followed by another `li` element. To do that, add following element to the
     * array:
     *
     * ```php
     * 'li' => array('li'),
     * ```
     *
     * With this, the following two examples are considered equal. Note that the
     * second example is missing the closing tags on `li` elements.
     *
     * ```html
     * <ul><li>First Item</li><li>Second Item</li></ul>
     * ```
     *
     * <ul><li>First Item</li><li>Second Item</li></ul>
     *
     * ```html
     * <ul><li>First Item<li>Second Item</ul>
     * ```
     *
     * <ul><li>First Item<li>Second Item</ul>
     *
     * @var array A two-dimensional array where the key is the name of an
     * element whose end tag is omissible and the value is an array of elements
     * whose end tag is omissible, that are closed by the current element.
     *
     * @link https://www.w3.org/TR/html/syntax.html#optional-tags Optional tags
     *
     * @todo The implementation of optional closing tags doesn't work in all cases
     * because it only consideres elements who close other optional closing
     * tags, not taking into account that some (non-blocking) tags should close
     * these optional closing tags. For example, the end tag for "p" is omissible
     * and can be closed by an "address" element, whose end tag is NOT omissible.
     * Currently a "p" element without closing tag stops at the next "p" element
     * or blocking tag, even if it contains other elements.
     *
     * @todo Known sourceforge issue #2977341
     * B tags that are not closed cause us to return everything to the end of
     * the document.
     */
    protected $optional_closing_tags = [
        // Not optional, see
        // https://www.w3.org/TR/html/textlevel-semantics.html#the-b-element
        'b' => ['b' => 1],
        'dd' => ['dd' => 1, 'dt' => 1],
        // Not optional, see
        // https://www.w3.org/TR/html/grouping-content.html#the-dl-element
        'dl' => ['dd' => 1, 'dt' => 1],
        'dt' => ['dd' => 1, 'dt' => 1],
        'li' => ['li' => 1],
        'optgroup' => ['optgroup' => 1, 'option' => 1],
        'option' => ['optgroup' => 1, 'option' => 1],
        'p' => ['p' => 1],
        'rp' => ['rp' => 1, 'rt' => 1],
        'rt' => ['rp' => 1, 'rt' => 1],
        'td' => ['td' => 1, 'th' => 1],
        'th' => ['td' => 1, 'th' => 1],
        'tr' => ['td' => 1, 'th' => 1, 'tr' => 1],
    ];

    /**
     * SimpleHtmlDom constructor.
     * @param string|null $str
     * @param bool $lowercase
     * @param bool $forceTagsClosed
     * @param string $target_charset
     * @param bool $stripRN
     * @param string $defaultBRText
     * @param string $defaultSpanText
     * @param int $options
     */
    public function __construct(
        string $str = null,
        bool $lowercase = true,
        bool $forceTagsClosed = true,
        string $target_charset = DEFAULT_TARGET_CHARSET,
        bool $stripRN = true,
        string $defaultBRText = DEFAULT_BR_TEXT,
        string $defaultSpanText = DEFAULT_SPAN_TEXT,
        int $options = 0
    ) {
        if ($str) {
            if (preg_match('/^http:///i', $str) || is_file($str)) {
                $this->load_file($str);
            } else {
                $this->load(
                    $str,
                    $lowercase,
                    $stripRN,
                    $defaultBRText,
                    $defaultSpanText,
                    $options
                );
            }
        }
        // Forcing tags to be closed implies that we don't trust the html, but
        // it can lead to parsing errors if we SHOULD trust the html.
        if (!$forceTagsClosed) {
            $this->optional_closing_array = [];
        }

        $this->_target_charset = $target_charset;
    }

    public function __destruct()
    {
        $this->clear();
    }

    /**
     * load html from string
     *
     * @param string $str
     * @param bool $lowercase
     * @param bool $stripRN
     * @param string $defaultBRText
     * @param string $defaultSpanText
     * @param int $options
     * @return $this
     */
    public function load(
        string $str,
        bool $lowercase = true,
        bool $stripRN = true,
        string $defaultBRText = DEFAULT_BR_TEXT,
        string $defaultSpanText = DEFAULT_SPAN_TEXT,
        int $options = 0
    ) {
        // prepare
        $this->prepare($str, $lowercase, $defaultBRText, $defaultSpanText);

        // Per sourceforge http://sourceforge.net/tracker/?func=detail&aid=2949097&group_id=218559&atid=1044037
        // Script tags removal now preceeds style tag removal.
        // strip out <script> tags
        $this->remove_noise("'<\s*script[^>]*[^/]>(.*?)<\s*/\s*script\s*>'is");
        $this->remove_noise("'<\s*script\s*>(.*?)<\s*/\s*script\s*>'is");

        // strip out the \r \n's if we are told to.
        if ($stripRN) {
            $this->doc = str_replace("\r", ' ', $this->doc);
            $this->doc = str_replace("\n", ' ', $this->doc);

            // set the length of content since we have changed it.
            $this->size = strlen($this->doc);
        }

        // strip out cdata
        $this->remove_noise("'<!\[CDATA\[(.*?)\]\]>'is", true);
        // strip out comments
        $this->remove_noise("'<!--(.*?)-->'is");
        // strip out <style> tags
        $this->remove_noise("'<\s*style[^>]*[^/]>(.*?)<\s*/\s*style\s*>'is");
        $this->remove_noise("'<\s*style\s*>(.*?)<\s*/\s*style\s*>'is");
        // strip out preformatted tags
        $this->remove_noise("'<\s*(?:code)[^>]*>(.*?)<\s*/\s*(?:code)\s*>'is");
        // strip out server side scripts
        $this->remove_noise("'(<\?)(.*?)(\?>)'s", true);

        if ($options & namespace\HDOM_SMARTY_AS_TEXT) { // Strip Smarty scripts
            $this->remove_noise("'(\{\w)(.*?)(\})'s", true);
        }

        // parsing
        $this->parse();
        // end
        $this->root->_[namespace\HDOM_INFO_END] = $this->cursor;
        $this->parse_charset();

        // make load function chainable
        return $this;
    }

    /**
     * load html from file
     *
     * @return bool
     */
    public function load_file(): ?bool
    {
        $args = func_get_args();

        if (($doc = file_get_contents(...$args)) !== false) {
            $this->load($doc, true);
        } else {
            return false;
        }
    }

    /**
     * Set the callback function
     *
     * @param callable $fn Callback function to run for each element in the DOM.
     * @return void
     */
    public function set_callback($fn): void
    {
        $this->callback = $fn;
    }

    /**
     * Remove callback function
     *
     * @return void
     */
    public function remove_callback(): void
    {
        $this->callback = null;
    }

    /**
     * save dom as string
     *
     * @param string $filepath
     * @return mixed
     */
    public function save($filepath = '')
    {
        $ret = $this->root->innertext();
        if ($filepath !== '') {
            file_put_contents($filepath, $ret, LOCK_EX);
        }
        return $ret;
    }

    // find dom node by css selector
    // Paperg - allow us to specify that we want case insensitive testing of the value of the selector.
    public function find($selector, $idx = null, $lowercase = false)
    {
        return $this->root->find($selector, $idx, $lowercase);
    }

    /**
     * clean up memory due to php5 circular references memory leak...
     */
    public function clear(): void
    {
        foreach ($this->nodes as $n) {
            $n->clear();
            $n = null;
        }

        // This add next line is documented in the sourceforge repository.
        // 2977248 as a fix for ongoing memory leaks that occur even with the
        // use of clear.
        if (isset($this->children)) {
            foreach ($this->children as $n) {
                $n->clear();
                $n = null;
            }
        }

        if (isset($this->parent)) {
            $this->parent->clear();
            unset($this->parent);
        }

        if (isset($this->root)) {
            $this->root->clear();
            unset($this->root);
        }

        unset($this->doc);
        unset($this->noise);
    }

    /**
     * @param bool $show_attr
     */
    public function dump(bool $show_attr = true): void
    {
        $this->root->dump($show_attr);
    }

    /**
     * prepare HTML data and init everything
     *
     * @param string $str
     * @param bool $lowercase
     * @param string $defaultBRText
     * @param string $defaultSpanText
     */
    protected function prepare(
        string $str,
        bool $lowercase = true,
        string $defaultBRText = DEFAULT_BR_TEXT,
        string $defaultSpanText = DEFAULT_SPAN_TEXT
    ): void {
        $this->clear();

        $this->doc = trim($str);
        $this->size = strlen($this->doc);
        $this->original_size = $this->size; // original size of the html
        $this->pos = 0;
        $this->cursor = 1;
        $this->noise = [];
        $this->nodes = [];
        $this->lowercase = $lowercase;
        $this->default_br_text = $defaultBRText;
        $this->default_span_text = $defaultSpanText;
        $this->root = new SimpleHtmlDomNode($this);
        $this->root->tag = 'root';
        $this->root->_[namespace\HDOM_INFO_BEGIN] = -1;
        $this->root->nodetype = namespace\HDOM_TYPE_ROOT;
        $this->parent = $this->root;
        if ($this->size > 0) {
            $this->char = $this->doc[0];
        }
    }

    /**
     * Parse HTML content
     *
     * @return bool True on success
     */
    protected function parse(): ?bool
    {
        while (true) {
            // Read next tag if there is no text between current position and the
            // next opening tag.
            if (($s = $this->copy_until_char('<')) === '') {
                if ($this->read_tag()) {
                    continue;
                }

                return true;
            }

            // Add a text node for text between tags
            $node = new SimpleHtmlDomNode($this);
            ++$this->cursor;
            $node->_[namespace\HDOM_INFO_TEXT] = $s;
            $this->link_nodes($node, false);
        }
    }

    // PAPERG - dkchou - added this to try to identify the character set of the
    // page we have just parsed so we know better how to spit it out later.
    // NOTE:  IF you provide a routine called
    // get_last_retrieve_url_contents_content_type which returns the
    // CURLINFO_CONTENT_TYPE from the last curl_exec
    // (or the content_type header from the last transfer), we will parse THAT,
    // and if a charset is specified, we will use it over any other mechanism.
    protected function parse_charset()
    {
        $charset = null;

        if (function_exists('get_last_retrieve_url_contents_content_type')) {
            $contentTypeHeader = get_last_retrieve_url_contents_content_type();
            $success = preg_match('/charset=(.+)/', $contentTypeHeader, $matches);
            if ($success) {
                $charset = $matches[1];
                HtmlDomParser::debug_log(2, 'header content-type found charset of: ' . $charset);
            }
        }

        if (empty($charset)) {
            $el = $this->root->find('meta[http-equiv=Content-Type]', 0, true);

            if (!empty($el)) {
                $fullvalue = $el->content;
                HtmlDomParser::debug_log(2, 'meta content-type tag found' . $fullvalue);

                if (!empty($fullvalue)) {
                    $success = preg_match('/charset=(.+)/i', $fullvalue, $matches);

                    if ($success) {
                        $charset = $matches[1];
                    } else {
                        // If there is a meta tag, and they don't specify the
                        // character set, research says that it's typically ISO-8859-1
                        $charset = 'ISO-8859-1';
                        HtmlDomParser::debug_log(2, "meta content-type tag couldn't be parsed. using iso-8859 default.");
                    }
                }
            }
        }

        // If we couldn't find a charset above, then lets try to detect one based on the text we got...
        if (empty($charset)) {
            // Use this in case mb_detect_charset isn't installed/loaded on this machine.
            $charset = false;
            if (function_exists('mb_detect_encoding')) {
                // Have php try to detect the encoding from the text given to us.
                $charset = mb_detect_encoding(
                    $this->doc . 'ascii',
                    $encoding_list = ['UTF-8', 'CP1252']
                );
                HtmlDomParser::debug_log(2, 'mb_detect found: ' . $charset);
            }

            // and if this doesn't work...  then we need to just wrongheadedly
            // assume it's UTF-8 so that we can move on - cause this will
            // usually give us most of what we need...
            if ($charset === false) {
                HtmlDomParser::debug_log(2, 'since mb_detect failed - using default of utf-8');
                $charset = 'UTF-8';
            }
        }

        $_cs = strtolower($charset);
        if (strcasecmp($_cs, 'ISO-8859-1') === 0
            || strcasecmp($_cs, 'Latin1') === 0
            || strcasecmp($_cs, 'Latin-1') === 0) {
            // Since CP1252 is a superset, if we get one of it's subsets, we want it instead.
            HtmlDomParser::debug_log(2, 'replacing ' . $charset . ' with CP1252 as its a superset');
            $charset = 'CP1252';
        }

        HtmlDomParser::debug_log(1, 'EXIT - ' . $charset);

        return $this->_charset = $charset;
    }

    /**
     * Parse tag from current document position.
     *
     * @return bool True if a tag was found, false otherwise
     */
    protected function read_tag()
    {
        // Set end position if no further tags found
        if ($this->char !== '<') {
            $this->root->_[namespace\HDOM_INFO_END] = $this->cursor;
            return false;
        }

        $begin_tag_pos = $this->pos;
        $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next

        // end tag
        if ($this->char === '/') {
            $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next

            // Skip whitespace in end tags (i.e. in "</   html>")
            $this->skip($this->token_blank);
            $tag = $this->copy_until_char('>');

            // Skip attributes in end tags
            if (($pos = strpos($tag, ' ')) !== false) {
                $tag = substr($tag, 0, $pos);
            }

            $parent_lower = strtolower($this->parent->tag);
            $tag_lower = strtolower($tag);

            // The end tag is supposed to close the parent tag. Handle situations
            // when it doesn't
            if ($parent_lower !== $tag_lower) {
                // Parent tag does not have to be closed necessarily (optional closing tag)
                // Current tag is a block tag, so it may close an ancestor
                if (isset($this->optional_closing_tags[$parent_lower])
                    && isset($this->block_tags[$tag_lower])) {
                    $this->parent->_[namespace\HDOM_INFO_END] = 0;
                    $org_parent = $this->parent;

                    // Traverse ancestors to find a matching opening tag
                    // Stop at root node
                    while (($this->parent->parent)
                        && strtolower($this->parent->tag) !== $tag_lower
                    ) {
                        $this->parent = $this->parent->parent;
                    }

                    // If we don't have a match add current tag as text node
                    if (strtolower($this->parent->tag) !== $tag_lower) {
                        $this->parent = $org_parent; // restore origonal parent

                        if ($this->parent->parent) {
                            $this->parent = $this->parent->parent;
                        }

                        $this->parent->_[namespace\HDOM_INFO_END] = $this->cursor;
                        return $this->as_text_node($tag);
                    }
                } elseif (($this->parent->parent)
                    && isset($this->block_tags[$tag_lower])
                ) {
                    // Grandparent exists and current tag is a block tag, so our
                    // parent doesn't have an end tag
                    $this->parent->_[namespace\HDOM_INFO_END] = 0; // No end tag
                    $org_parent = $this->parent;

                    // Traverse ancestors to find a matching opening tag
                    // Stop at root node
                    while (($this->parent->parent)
                        && strtolower($this->parent->tag) !== $tag_lower
                    ) {
                        $this->parent = $this->parent->parent;
                    }

                    // If we don't have a match add current tag as text node
                    if (strtolower($this->parent->tag) !== $tag_lower) {
                        $this->parent = $org_parent; // restore origonal parent
                        $this->parent->_[namespace\HDOM_INFO_END] = $this->cursor;
                        return $this->as_text_node($tag);
                    }
                } elseif (($this->parent->parent)
                    && strtolower($this->parent->parent->tag) === $tag_lower
                ) { // Grandparent exists and current tag closes it
                    $this->parent->_[namespace\HDOM_INFO_END] = 0;
                    $this->parent = $this->parent->parent;
                } else { // Random tag, add as text node
                    return $this->as_text_node($tag);
                }
            }

            // Set end position of parent tag to current cursor position
            $this->parent->_[namespace\HDOM_INFO_END] = $this->cursor;

            if ($this->parent->parent) {
                $this->parent = $this->parent->parent;
            }

            $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
            return true;
        }

        // start tag
        $node = new SimpleHtmlDomNode($this);
        $node->_[namespace\HDOM_INFO_BEGIN] = $this->cursor;
        ++$this->cursor;
        $tag = $this->copy_until($this->token_slash); // Get tag name
        $node->tag_start = $begin_tag_pos;

        // doctype, cdata & comments...
        // <!DOCTYPE html>
        // <![CDATA[ ... ]]>
        // <!-- Comment -->
        if (isset($tag[0]) && $tag[0] === '!') {
            $node->_[namespace\HDOM_INFO_TEXT] = '<' . $tag . $this->copy_until_char('>');

            if (isset($tag[2]) && $tag[1] === '-' && $tag[2] === '-') { // Comment ("<!--")
                $node->nodetype = namespace\HDOM_TYPE_COMMENT;
                $node->tag = 'comment';
            } else { // Could be doctype or CDATA but we don't care
                $node->nodetype = namespace\HDOM_TYPE_UNKNOWN;
                $node->tag = 'unknown';
            }

            if ($this->char === '>') {
                $node->_[namespace\HDOM_INFO_TEXT] .= '>';
            }

            $this->link_nodes($node, true);
            $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
            return true;
        }

        // The start tag cannot contain another start tag, if so add as text
        // i.e. "<<html>"
        if (($pos = strpos($tag, '<')) !== false) {
            $tag = '<' . substr($tag, 0, -1);
            $node->_[namespace\HDOM_INFO_TEXT] = $tag;
            $this->link_nodes($node, false);
            $this->char = $this->doc[--$this->pos]; // prev
            return true;
        }

        // Handle invalid tag names (i.e. "<html#doc>")
        if (!preg_match('/^\w[\w:-]*$/', $tag)) {
            $node->_[namespace\HDOM_INFO_TEXT] = '<' . $tag . $this->copy_until('<>');

            // Next char is the beginning of a new tag, don't touch it.
            if ($this->char === '<') {
                $this->link_nodes($node, false);
                return true;
            }

            // Next char closes current tag, add and be done with it.
            if ($this->char === '>') {
                $node->_[namespace\HDOM_INFO_TEXT] .= '>';
            }
            $this->link_nodes($node, false);
            $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
            return true;
        }

        // begin tag, add new node
        $node->nodetype = namespace\HDOM_TYPE_ELEMENT;
        $tag_lower = strtolower($tag);
        $node->tag = ($this->lowercase) ? $tag_lower : $tag;

        // handle optional closing tags
        if (isset($this->optional_closing_tags[$tag_lower])) {
            // Traverse ancestors to close all optional closing tags
            while (isset($this->optional_closing_tags[$tag_lower][strtolower($this->parent->tag)])) {
                $this->parent->_[namespace\HDOM_INFO_END] = 0;
                $this->parent = $this->parent->parent;
            }
            $node->parent = $this->parent;
        }

        $guard = 0; // prevent infinity loop

        // [0] Space between tag and first attribute
        $space = [$this->copy_skip($this->token_blank), '', ''];

        // attributes
        do {
            // Everything until the first equal sign should be the attribute name
            $name = $this->copy_until($this->token_equal);

            if ($name === '' && $this->char !== null && $space[0] === '') {
                break;
            }

            if ($guard === $this->pos) { // Escape infinite loop
                $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
                continue;
            }

            $guard = $this->pos;

            // handle endless '<'
            // Out of bounds before the tag ended
            if ($this->pos >= $this->size - 1 && $this->char !== '>') {
                $node->nodetype = namespace\HDOM_TYPE_TEXT;
                $node->_[namespace\HDOM_INFO_END] = 0;
                $node->_[namespace\HDOM_INFO_TEXT] = '<' . $tag . $space[0] . $name;
                $node->tag = 'text';
                $this->link_nodes($node, false);
                return true;
            }

            // handle mismatch '<'
            // Attributes cannot start after opening tag
            if ($this->doc[$this->pos - 1] == '<') {
                $node->nodetype = namespace\HDOM_TYPE_TEXT;
                $node->tag = 'text';
                $node->attr = [];
                $node->_[namespace\HDOM_INFO_END] = 0;
                $node->_[namespace\HDOM_INFO_TEXT] = substr(
                    $this->doc,
                    $begin_tag_pos,
                    $this->pos - $begin_tag_pos - 1
                );
                $this->pos -= 2;
                $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
                $this->link_nodes($node, false);
                return true;
            }

            if ($name !== '/' && $name !== '') { // this is a attribute name
                // [1] Whitespace after attribute name
                $space[1] = $this->copy_skip($this->token_blank);

                $name = $this->restore_noise($name); // might be a noisy name

                if ($this->lowercase) {
                    $name = strtolower($name);
                }

                if ($this->char === '=') { // attribute with value
                    $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
                    $this->parse_attr($node, $name, $space); // get attribute value
                } else {
                    //no value attr: nowrap, checked selected...
                    $node->_[namespace\HDOM_INFO_QUOTE][] = namespace\HDOM_QUOTE_NO;
                    $node->attr[$name] = true;
                    if ($this->char != '>') {
                        $this->char = $this->doc[--$this->pos];
                    } // prev
                }

                $node->_[namespace\HDOM_INFO_SPACE][] = $space;

                // prepare for next attribute
                $space = [
                    $this->copy_skip($this->token_blank),
                    '',
                    ''
                ];
            } else { // no more attributes
                break;
            }
        } while ($this->char !== '>' && $this->char !== '/'); // go until the tag ended

        $this->link_nodes($node, true);
        $node->_[namespace\HDOM_INFO_ENDSPACE] = $space[0];

        // handle empty tags (i.e. "<div/>")
        if ($this->copy_until_char('>') === '/') {
            $node->_[namespace\HDOM_INFO_ENDSPACE] .= '/';
            $node->_[namespace\HDOM_INFO_END] = 0;
        } else {
            // reset parent
            if (!isset($this->self_closing_tags[strtolower($node->tag)])) {
                $this->parent = $node;
            }
        }

        $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next

        // If it's a BR tag, we need to set it's text to the default text.
        // This way when we see it in plaintext, we can generate formatting that the user wants.
        // since a br tag never has sub nodes, this works well.
        if ($node->tag === 'br') {
            $node->_[namespace\HDOM_INFO_INNER] = $this->default_br_text;
        }

        return true;
    }

    /**
     * Parse attribute from current document position
     *
     * @param object $node Node for the attributes
     * @param string $name Name of the current attribute
     * @param array $space Array for spacing information
     * @return void
     */
    protected function parse_attr($node, $name, &$space)
    {
        // Per sourceforge: http://sourceforge.net/tracker/?func=detail&aid=3061408&group_id=218559&atid=1044037
        // If the attribute is already defined inside a tag, only pay attention
        // to the first one as opposed to the last one.
        // https://stackoverflow.com/a/26341866
        if (isset($node->attr[$name])) {
            return;
        }

        // [2] Whitespace between "=" and the value
        $space[2] = $this->copy_skip($this->token_blank);

        switch ($this->char) {
            case '"': // value is anything between double quotes
                $node->_[namespace\HDOM_INFO_QUOTE][] = namespace\HDOM_QUOTE_DOUBLE;
                $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
                $node->attr[$name] = $this->restore_noise($this->copy_until_char('"'));
                $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
                break;
            case '\'': // value is anything between single quotes
                $node->_[namespace\HDOM_INFO_QUOTE][] = namespace\HDOM_QUOTE_SINGLE;
                $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
                $node->attr[$name] = $this->restore_noise($this->copy_until_char('\''));
                $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
                break;
            default: // value is anything until the first space or end tag
                $node->_[namespace\HDOM_INFO_QUOTE][] = namespace\HDOM_QUOTE_NO;
                $node->attr[$name] = $this->restore_noise($this->copy_until($this->token_attr));
        }
        // PaperG: Attributes should not have \r or \n in them, that counts as
        // html whitespace.
        $node->attr[$name] = str_replace("\r", '', $node->attr[$name]);
        $node->attr[$name] = str_replace("\n", '', $node->attr[$name]);
        // PaperG: If this is a "class" selector, lets get rid of the preceeding
        // and trailing space since some people leave it in the multi class case.
        if ($name === 'class') {
            $node->attr[$name] = trim($node->attr[$name]);
        }
    }

    /**
     * Link node to parent node
     *
     * @param object $node Node to link to parent
     * @param bool $is_child True if the node is a child of parent
     * @return void
     */
    // link node's parent
    protected function link_nodes(&$node, $is_child)
    {
        $node->parent = $this->parent;
        $this->parent->nodes[] = $node;
        if ($is_child) {
            $this->parent->children[] = $node;
        }
    }

    /**
     * Add tag as text node to current node
     *
     * @param string $tag Tag name
     * @return bool True on success
     */
    protected function as_text_node($tag)
    {
        $node = new SimpleHtmlDomNode($this);
        ++$this->cursor;
        $node->_[namespace\HDOM_INFO_TEXT] = '</' . $tag . '>';
        $this->link_nodes($node, false);
        $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
        return true;
    }

    /**
     * Seek from the current document position to the first occurrence of a
     * character not defined by the provided string. Update the current document
     * position to the new position.
     *
     * @param string $chars A string containing every allowed character.
     * @return void
     */
    protected function skip($chars)
    {
        $this->pos += strspn($this->doc, $chars, $this->pos);
        $this->char = ($this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
    }

    /**
     * Copy substring from the current document position to the first occurrence
     * of a character not defined by the provided string.
     *
     * @param string $chars A string containing every allowed character.
     * @return string Substring from the current document position to the first
     * occurrence of a character not defined by the provided string.
     */
    protected function copy_skip($chars)
    {
        $pos = $this->pos;
        $len = strspn($this->doc, $chars, $pos);
        $this->pos += $len;
        $this->char = ($this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
        if ($len === 0) {
            return '';
        }
        return substr($this->doc, $pos, $len);
    }

    /**
     * Copy substring from the current document position to the first occurrence
     * of any of the provided characters.
     *
     * @param string $chars A string containing every character to stop at.
     * @return string Substring from the current document position to the first
     * occurrence of any of the provided characters.
     */
    protected function copy_until($chars)
    {
        $pos = $this->pos;
        $len = strcspn($this->doc, $chars, $pos);
        $this->pos += $len;
        $this->char = ($this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
        return substr($this->doc, $pos, $len);
    }

    /**
     * Copy substring from the current document position to the first occurrence
     * of the provided string.
     *
     * @param string $char The string to stop at.
     * @return string Substring from the current document position to the first
     * occurrence of the provided string.
     */
    protected function copy_until_char($char)
    {
        if ($this->char === null) {
            return '';
        }

        if (($pos = strpos($this->doc, $char, $this->pos)) === false) {
            $ret = substr($this->doc, $this->pos, $this->size - $this->pos);
            $this->char = null;
            $this->pos = $this->size;
            return $ret;
        }

        if ($pos === $this->pos) {
            return '';
        }

        $pos_old = $this->pos;
        $this->char = $this->doc[$pos];
        $this->pos = $pos;
        return substr($this->doc, $pos_old, $pos - $pos_old);
    }

    /**
     * Remove noise from HTML content
     *
     * Noise is stored to {@see SimpleHtmlDom::$noise}
     *
     * @param string $pattern The regex pattern used for finding noise
     * @param bool $remove_tag True to remove the entire match. Default is false
     * to only remove the captured data.
     */
    protected function remove_noise($pattern, $remove_tag = false)
    {
        HtmlDomParser::debug_log_entry(1);

        $count = preg_match_all(
            $pattern,
            $this->doc,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );

        for ($i = $count - 1; $i > -1; --$i) {
            $key = '___noise___' . sprintf('% 5d', count($this->noise) + 1000);

            HtmlDomParser::debug_log(2, 'key is: ' . $key);

            $idx = ($remove_tag) ? 0 : 1; // 0 = entire match, 1 = submatch
            $this->noise[$key] = $matches[$i][$idx][0];
            $this->doc = substr_replace($this->doc, $key, $matches[$i][$idx][1], strlen($matches[$i][$idx][0]));
        }

        // reset the length of content
        $this->size = strlen($this->doc);

        if ($this->size > 0) {
            $this->char = $this->doc[0];
        }
    }

    /**
     * Restore noise to HTML content
     *
     * Noise is restored from {@see SimpleHtmlDom::$noise}
     *
     * @param string $text A subset of HTML containing noise
     * @return string The same content with noise restored
     */
    public function restore_noise($text)
    {
        HtmlDomParser::debug_log_entry(1);

        while (($pos = strpos($text, '___noise___')) !== false) {
            // Sometimes there is a broken piece of markup, and we don't GET the
            // pos+11 etc... token which indicates a problem outside of us...

            // todo: "___noise___1000" (or any number with four or more digits)
            // in the DOM causes an infinite loop which could be utilized by
            // malicious software
            if (strlen($text) > $pos + 15) {
                $key = '___noise___'
                    . $text[$pos + 11]
                    . $text[$pos + 12]
                    . $text[$pos + 13]
                    . $text[$pos + 14]
                    . $text[$pos + 15];


                HtmlDomParser::debug_log(2, 'located key of: ' . $key);


                if (isset($this->noise[$key])) {
                    $text = substr($text, 0, $pos)
                        . $this->noise[$key]
                        . substr($text, $pos + 16);
                } else {
                    // do this to prevent an infinite loop.
                    $text = substr($text, 0, $pos)
                        . 'UNDEFINED NOISE FOR KEY: '
                        . $key
                        . substr($text, $pos + 16);
                }
            } else {
                // There is no valid key being given back to us... We must get
                // rid of the ___noise___ or we will have a problem.
                $text = substr($text, 0, $pos)
                    . 'NO NUMERIC NOISE KEY'
                    . substr($text, $pos + 11);
            }
        }
        return $text;
    }

    // Sometimes we NEED one of the noise elements.
    public function search_noise($text)
    {
        HtmlDomParser::debug_log_entry(1);

        foreach ($this->noise as $noiseElement) {
            if (strpos($noiseElement, $text) !== false) {
                return $noiseElement;
            }
        }
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->root->innertext();
    }

    /**
     * @param string $name
     * @return string
     */
    public function __get(string $name)
    {
        switch ($name) {
            case 'innertext':
            case 'outertext':
                return $this->root->innertext();
            case 'plaintext':
                return $this->root->text();
            case 'charset':
                return $this->_charset;
            case 'target_charset':
                return $this->_target_charset;
        }
    }

    /**
     * @param string $name
     * @param $value
     */
    public function __set(string $name, $value)
    {
    }

    public function __isset(string $name): bool
    {
    }

    // camel naming conventions
    public function childNodes($idx = -1)
    {
        return $this->root->childNodes($idx);
    }

    public function firstChild()
    {
        return $this->root->first_child();
    }

    public function lastChild()
    {
        return $this->root->last_child();
    }

    public function createElement($name, $value = null)
    {
        return @HtmlDomParser::str_get_html("<$name>$value</$name>")->first_child();
    }

    public function createTextNode($value)
    {
        return @end(HtmlDomParser::str_get_html($value)->nodes);
    }

    public function getElementById($id)
    {
        return $this->find("#$id", 0);
    }

    public function getElementsById($id, $idx = null)
    {
        return $this->find("#$id", $idx);
    }

    public function getElementByTagName($name)
    {
        return $this->find($name, 0);
    }

    public function getElementsByTagName($name, $idx = -1)
    {
        return $this->find($name, $idx);
    }

    public function loadFile()
    {
        $args = func_get_args();
        $this->load_file($args);
    }
}
