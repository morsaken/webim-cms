<?php
/**
 * Website: http://sourceforge.net/projects/simplehtmldom/
 * Acknowledge: Jose Solorzano (https://sourceforge.net/projects/php-html/)
 * Contributions by:
 *     Yousuke Kumakura (Attribute filters)
 *     Vadim Voituk (Negative indexes supports of "find" method)
 *     Antcs (Constructor with automatically load contents either text or file/url)
 *
 * all affected sections have comments starting with "PaperG"
 *
 * Paperg - Added case insensitive testing of the value of the selector.
 * Paperg - Added tag_start for the starting index of tags - NOTE: This works but not accurately.
 *  This tag_start gets counted AFTER \r\n have been crushed out, and after the remove_noice calls so it will not reflect the REAL position of the tag in the source,
 *  it will almost always be smaller by some amount.
 *  We use this to determine how far into the file the tag in question is.  This "percentage will never be accurate as the $dom->size is the "real" number of bytes the dom was created from.
 *  but for most purposes, it's a really good estimation.
 * Paperg - Added the forceTagsClosed to the dom constructor.  Forcing tags closed is great for malformed html, but it CAN lead to parsing errors.
 * Allow the user to tell us how much they trust the html.
 * Paperg add the text and plaintext to the selectors for the find syntax.  plaintext implies text in the innertext of a node.  text implies that the tag is a text node.
 * This allows for us to find tags based on the text they contain.
 * Create find_ancestor_tag to see if a tag is - at any level - inside of another specific tag.
 * Paperg: added parse_charset so that we know about the character set of the source document.
 *  NOTE:  If the user's system has a routine called get_last_retrieve_url_contents_content_type availalbe, we will assume it's returning the content-type header from the
 *  last transfer or curl_exec, and we will parse that and use it in preference to any other method of charset detection.
 *
 * Found infinite loop in the case of broken html in restore_noise.  Rewrote to protect from that.
 * PaperG (John Schlick) Added get_display_size for "IMG" tags.
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @author S.C. Chen <me578022@gmail.com>
 * @author John Schlick
 * @author Rus Carroll
 * @version 1.5 ($Rev: 196 $)
 * @package PlaceLocalInclude
 * @subpackage simple_html_dom
 */

namespace HTML;

define('DEFAULT_TARGET_CHARSET', 'UTF-8');
define('DEFAULT_BR_TEXT', "\r\n");
define('DEFAULT_SPAN_TEXT', " ");
define('MAX_FILE_SIZE', 600000);

/**
 * simple html dom parser
 * Paperg - in the find routine: allow us to specify that we want case
 * insensitive testing of the value of the selector.
 * Paperg - change $size from protected to public so we can easily access it
 * Paperg - added ForceTagsClosed in the constructor which tells us whether we
 * trust the html or not. Default is to NOT trust it.
 *
 * @package PlaceLocalInclude
 */
class DOMNode {
  public $root = null;
  public $nodes = array();
  public $callback = null;
  public $lowercase = false;
  // Used to keep track of how large the text was when we started.
  public $original_size;
  public $size;
  protected $pos;
  protected $doc;
  protected $char;
  protected $cursor;
  protected $parent;
  protected $noise = array();
  protected $token_blank = " \t\r\n";
  protected $token_equal = ' =/>';
  protected $token_slash = " />\r\n\t";
  protected $token_attr = ' >';
  // Note that this is referenced by a child node, and so it needs to be public
  // for that node to see this information.
  public $_charset = '';
  public $_target_charset = '';
  protected $default_br_text = "";
  public $default_span_text = "";

  // use isset instead of in_array, performance boost about 30%...
  protected $self_closing_tags = array(
    'img' => 1,
    'br' => 1,
    'input' => 1,
    'meta' => 1,
    'link' => 1,
    'hr' => 1,
    'base' => 1,
    'embed' => 1,
    'spacer' => 1
  );
  protected $block_tags = array(
    'root' => 1,
    'body' => 1,
    'form' => 1,
    'div' => 1,
    'span' => 1,
    'table' => 1
  );
  // Known sourceforge issue #2977341
  // B tags that are not closed cause us to return everything to the end of the
  // document.
  protected $optional_closing_tags = array(
    'tr' => array(
      'tr' => 1,
      'td' => 1,
      'th' => 1
    ),
    'th' => array(
      'th' => 1
    ),
    'td' => array(
      'td' => 1
    ),
    'li' => array(
      'li' => 1
    ),
    'dt' => array(
      'dt' => 1,
      'dd' => 1
    ),
    'dd' => array(
      'dd' => 1,
      'dt' => 1
    ),
    'dl' => array(
      'dd' => 1,
      'dt' => 1
    ),
    'p' => array(
      'p' => 1
    ),
    'nobr' => array(
      'nobr' => 1
    ),
    'b' => array(
      'b' => 1
    ),
    'option' => array(
      'option' => 1
    )
  );

  function __construct($str = null, $lowercase = true, $forceTagsClosed = true, $target_charset = DEFAULT_TARGET_CHARSET, $stripRN = true, $defaultBRText = DEFAULT_BR_TEXT, $defaultSpanText = DEFAULT_SPAN_TEXT) {
    if ($str) {
      if (preg_match("/^http:\\/\\//i", $str) || is_file($str)) {
        $this->load_file($str);
      } else {
        $this->load($str, $lowercase, $stripRN, $defaultBRText, $defaultSpanText);
      }
    }
    // Forcing tags to be closed implies that we don't trust the html, but it can
    // lead to parsing errors if we SHOULD trust the html.
    if (!$forceTagsClosed) {
      $this->optional_closing_array = array();
    }
    $this->_target_charset = $target_charset;
  }

  function __destruct() {
    $this->clear();
  }

  // load html from string
  function load($str, $lowercase = true, $stripRN = true, $defaultBRText = DEFAULT_BR_TEXT, $defaultSpanText = DEFAULT_SPAN_TEXT) {
    global $debugObject;

    // prepare
    $this->prepare($str, $lowercase, $stripRN, $defaultBRText, $defaultSpanText);
    // strip out comments
    $this->remove_noise("'<!--(.*?)-->'is");
    // strip out cdata
    $this->remove_noise("'<!\\[CDATA\\[(.*?)\\]\\]>'is", true);
    // Per sourceforge
    // http://sourceforge.net/tracker/?func=detail&aid=2949097&group_id=218559&atid=1044037
    // Script tags removal now preceeds style tag removal.
    // strip out <script> tags
    $this->remove_noise("'<\\s*script[^>]*[^/]>(.*?)<\\s*/\\s*script\\s*>'is");
    $this->remove_noise("'<\\s*script\\s*>(.*?)<\\s*/\\s*script\\s*>'is");
    // strip out <style> tags
    $this->remove_noise("'<\\s*style[^>]*[^/]>(.*?)<\\s*/\\s*style\\s*>'is");
    $this->remove_noise("'<\\s*style\\s*>(.*?)<\\s*/\\s*style\\s*>'is");
    // strip out preformatted tags
    $this->remove_noise("'<\\s*(?:code)[^>]*>(.*?)<\\s*/\\s*(?:code)\\s*>'is");
    // strip out server side scripts
    $this->remove_noise("'(<\\?)(.*?)(\\?>)'s", true);
    // strip smarty scripts
    $this->remove_noise("'(\\{\\w)(.*?)(\\})'s", true);

    // parsing
    while ($this->parse())
      ;
    // end
    $this->root->_[HDOM_INFO_END] = $this->cursor;
    $this->parse_charset();

    // make load function chainable
    return $this;
  }

  // load html from file
  function load_file() {
    $args = func_get_args();
    $this->load(call_user_func_array('file_get_contents', $args), true);
    // Throw an error if we can't properly load the dom.
    if (($error = error_get_last()) !== null) {
      $this->clear();

      return false;
    }
  }

  // set callback function
  function set_callback($function_name) {
    $this->callback = $function_name;
  }

  // remove callback function
  function remove_callback() {
    $this->callback = null;
  }

  // save dom as string
  function save($filepath = '') {
    $ret = $this->root->innertext();
    if ($filepath !== '')
      file_put_contents($filepath, $ret, LOCK_EX);

    return $ret;
  }

  // find dom node by css selector
  // Paperg - allow us to specify that we want case insensitive testing of the
  // value of the selector.
  function find($selector, $idx = null, $lowercase = false) {
    return $this->root->find($selector, $idx, $lowercase);
  }

  // clean up memory due to php5 circular references memory leak...
  function clear() {
    foreach ($this->nodes as $n) {
      $n->clear();
      $n = null;
    }
    // This add next line is documented in the sourceforge repository. 2977248 as
    // a fix for ongoing memory leaks that occur even with the use of clear.
    if (isset($this->children))
      foreach ($this->children as $n) {
        $n->clear();
        $n = null;
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

  function dump($show_attr = true) {
    $this->root->dump($show_attr);
  }

  // prepare HTML data and init everything
  protected function prepare($str, $lowercase = true, $stripRN = true, $defaultBRText = DEFAULT_BR_TEXT, $defaultSpanText = DEFAULT_SPAN_TEXT) {
    $this->clear();

    // set the length of content before we do anything to it.
    $this->size = strlen($str);
    // Save the original size of the html that we got in. It might be useful to
    // someone.
    $this->original_size = $this->size;

    // before we save the string as the doc... strip out the \r \n's if we are
    // told to.
    if ($stripRN) {
      $str = str_replace("\r", " ", $str);
      $str = str_replace("\n", " ", $str);

      // set the length of content since we have changed it.
      $this->size = strlen($str);
    }

    $this->doc = $str;
    $this->pos = 0;
    $this->cursor = 1;
    $this->noise = array();
    $this->nodes = array();
    $this->lowercase = $lowercase;
    $this->default_br_text = $defaultBRText;
    $this->default_span_text = $defaultSpanText;
    $this->root = new DOM($this);
    $this->root->tag = 'root';
    $this->root->_[HDOM_INFO_BEGIN] = -1;
    $this->root->nodetype = HDOM_TYPE_ROOT;
    $this->parent = $this->root;
    if ($this->size > 0)
      $this->char = $this->doc[0];
  }

  // parse html content
  protected function parse() {
    if (($s = $this->copy_until_char('<')) === '') {
      return $this->read_tag();
    }

    // text
    $node = new DOM($this);
    ++$this->cursor;
    $node->_[HDOM_INFO_TEXT] = $s;
    $this->link_nodes($node, false);

    return true;
  }

  // PAPERG - dkchou - added this to try to identify the character set of the
  // pages we have just parsed so we know better how to spit it out later.
  // NOTE: IF you provide a routine called
  // get_last_retrieve_url_contents_content_type which returns the
  // CURLINFO_CONTENT_TYPE from the last curl_exec
  // (or the content_type header from the last transfer), we will parse THAT, and
  // if a charset is specified, we will use it over any other mechanism.
  protected function parse_charset() {
    global $debugObject;

    $charset = null;
    /*
    if (function_exists('get_last_retrieve_url_contents_content_type')){
     $contentTypeHeader = get_last_retrieve_url_contents_content_type();
     $success = preg_match('/charset=(.+)/', $contentTypeHeader, $matches);
     if ($success){
      $charset = $matches[1];
      if (is_object($debugObject)){
       $debugObject->debugLog(2, 'header content-type found charset of: ' . $charset);
      }
     }
    }*/

    if (empty($charset)) {
      $el = $this->root->find('meta[http-equiv=Content-Type]', 0);
      if (!empty($el)) {
        $fullvalue = $el->content;
        if (is_object($debugObject)) {
          $debugObject->debugLog(2, 'meta content-type tag found' . $fullvalue);
        }

        if (!empty($fullvalue)) {
          $success = preg_match('/charset=(.+)/', $fullvalue, $matches);
          if ($success) {
            $charset = $matches[1];
          } else {
            // If there is a meta tag, and they don't specify the character set,
            // research says that it's typically ISO-8859-1
            if (is_object($debugObject)) {
              $debugObject->debugLog(2, 'meta content-type tag couldn\'t be parsed. using iso-8859 default.');
            }
            $charset = 'ISO-8859-1';
          }
        }
      }
    }

    // If we couldn't find a charset above, then lets try to detect one based on
    // the text we got...
    if (empty($charset)) {
      if (function_exists("mb_detect_encoding")) {
        // Have php try to detect the encoding from the text given to us.
        $charset = mb_detect_encoding($this->root->plaintext . "ascii", $encoding_list = array(
          "UTF-8",
          "CP1252"
        ));
        if (is_object($debugObject)) {
          $debugObject->debugLog(2, 'mb_detect found: ' . $charset);
        }
      } else {
        $charset = false;
      }

      // and if this doesn't work... then we need to just wrongheadedly assume it's
      // UTF-8 so that we can move on - cause this will usually give us most of
      // what we need...
      if ($charset === false) {
        if (is_object($debugObject)) {
          $debugObject->debugLog(2, 'since mb_detect failed - using default of utf-8');
        }
        $charset = 'UTF-8';
      }
    }

    // Since CP1252 is a superset, if we get one of it's subsets, we want it
    // instead.
    if ((strtolower($charset) == strtolower('ISO-8859-1')) || (strtolower($charset) == strtolower('Latin1')) || (strtolower($charset) == strtolower('Latin-1'))) {
      if (is_object($debugObject)) {
        $debugObject->debugLog(2, 'replacing ' . $charset . ' with CP1252 as its a superset');
      }
      $charset = 'CP1252';
    }

    if (is_object($debugObject)) {
      $debugObject->debugLog(1, 'EXIT - ' . $charset);
    }

    return $this->_charset = $charset;
  }

  // read tag info
  protected function read_tag() {
    if ($this->char !== '<') {
      $this->root->_[HDOM_INFO_END] = $this->cursor;

      return false;
    }
    $begin_tag_pos = $this->pos;
    $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next

    // end tag
    if ($this->char === '/') {
      $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
      // This
      // represents
      // the
      // change
      // in
      // the
      // simple_html_dom
      // trunk
      // from
      // revision
      // 180
      // to
      // 181.
      // $this->skip($this->token_blank_t);
      $this->skip($this->token_blank);
      $tag = $this->copy_until_char('>');

      // skip attributes in end tag
      if (($pos = strpos($tag, ' ')) !== false)
        $tag = substr($tag, 0, $pos);

      $parent_lower = strtolower($this->parent->tag);
      $tag_lower = strtolower($tag);

      if ($parent_lower !== $tag_lower) {
        if (isset($this->optional_closing_tags[$parent_lower]) && isset($this->block_tags[$tag_lower])) {
          $this->parent->_[HDOM_INFO_END] = 0;
          $org_parent = $this->parent;

          while (($this->parent->parent) && strtolower($this->parent->tag) !== $tag_lower)
            $this->parent = $this->parent->parent;

          if (strtolower($this->parent->tag) !== $tag_lower) {
            $this->parent = $org_parent; // restore origonal parent
            if ($this->parent->parent)
              $this->parent = $this->parent->parent;
            $this->parent->_[HDOM_INFO_END] = $this->cursor;

            return $this->as_text_node($tag);
          }
        } else if (($this->parent->parent) && isset($this->block_tags[$tag_lower])) {
          $this->parent->_[HDOM_INFO_END] = 0;
          $org_parent = $this->parent;

          while (($this->parent->parent) && strtolower($this->parent->tag) !== $tag_lower)
            $this->parent = $this->parent->parent;

          if (strtolower($this->parent->tag) !== $tag_lower) {
            $this->parent = $org_parent; // restore origonal parent
            $this->parent->_[HDOM_INFO_END] = $this->cursor;

            return $this->as_text_node($tag);
          }
        } else if (($this->parent->parent) && strtolower($this->parent->parent->tag) === $tag_lower) {
          $this->parent->_[HDOM_INFO_END] = 0;
          $this->parent = $this->parent->parent;
        } else
          return $this->as_text_node($tag);
      }

      $this->parent->_[HDOM_INFO_END] = $this->cursor;
      if ($this->parent->parent)
        $this->parent = $this->parent->parent;

      $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
      return true;
    }

    $node = new DOM($this);
    $node->_[HDOM_INFO_BEGIN] = $this->cursor;
    ++$this->cursor;
    $tag = $this->copy_until($this->token_slash);
    $node->tag_start = $begin_tag_pos;

    // doctype, cdata & comments...
    if (isset($tag[0]) && $tag[0] === '!') {
      $node->_[HDOM_INFO_TEXT] = '<' . $tag . $this->copy_until_char('>');

      if (isset($tag[2]) && $tag[1] === '-' && $tag[2] === '-') {
        $node->nodetype = HDOM_TYPE_COMMENT;
        $node->tag = 'comment';
      } else {
        $node->nodetype = HDOM_TYPE_UNKNOWN;
        $node->tag = 'unknown';
      }
      if ($this->char === '>')
        $node->_[HDOM_INFO_TEXT] .= '>';
      $this->link_nodes($node, true);
      $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
      return true;
    }

    // text
    if (($pos = strpos($tag, '<')) !== false) {
      $tag = '<' . substr($tag, 0, -1);
      $node->_[HDOM_INFO_TEXT] = $tag;
      $this->link_nodes($node, false);
      $this->char = $this->doc[--$this->pos]; // prev
      return true;
    }

    if (!preg_match("/^[\\w-:]+$/", $tag)) {
      $node->_[HDOM_INFO_TEXT] = '<' . $tag . $this->copy_until('<>');
      if ($this->char === '<') {
        $this->link_nodes($node, false);

        return true;
      }

      if ($this->char === '>')
        $node->_[HDOM_INFO_TEXT] .= '>';
      $this->link_nodes($node, false);
      $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
      return true;
    }

    // begin tag
    $node->nodetype = HDOM_TYPE_ELEMENT;
    $tag_lower = strtolower($tag);
    $node->tag = ($this->lowercase) ? $tag_lower : $tag;

    // handle optional closing tags
    if (isset($this->optional_closing_tags[$tag_lower])) {
      while (isset($this->optional_closing_tags[$tag_lower][strtolower($this->parent->tag)])) {
        $this->parent->_[HDOM_INFO_END] = 0;
        $this->parent = $this->parent->parent;
      }
      $node->parent = $this->parent;
    }

    $guard = 0; // prevent infinity loop
    $space = array(
      $this->copy_skip($this->token_blank),
      '',
      ''
    );

    // attributes
    do {
      if ($this->char !== null && $space[0] === '') {
        break;
      }
      $name = $this->copy_until($this->token_equal);
      if ($guard === $this->pos) {
        $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
        continue;
      }
      $guard = $this->pos;

      // handle endless '<'
      if ($this->pos >= $this->size - 1 && $this->char !== '>') {
        $node->nodetype = HDOM_TYPE_TEXT;
        $node->_[HDOM_INFO_END] = 0;
        $node->_[HDOM_INFO_TEXT] = '<' . $tag . $space[0] . $name;
        $node->tag = 'text';
        $this->link_nodes($node, false);

        return true;
      }

      // handle mismatch '<'
      if ($this->doc[$this->pos - 1] == '<') {
        $node->nodetype = HDOM_TYPE_TEXT;
        $node->tag = 'text';
        $node->attr = array();
        $node->_[HDOM_INFO_END] = 0;
        $node->_[HDOM_INFO_TEXT] = substr($this->doc, $begin_tag_pos, $this->pos - $begin_tag_pos - 1);
        $this->pos -= 2;
        $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
        $this->link_nodes($node, false);

        return true;
      }

      if ($name !== '/' && $name !== '') {
        $space[1] = $this->copy_skip($this->token_blank);
        $name = $this->restore_noise($name);
        if ($this->lowercase)
          $name = strtolower($name);
        if ($this->char === '=') {
          $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
          $this->parse_attr($node, $name, $space);
        } else {
          // no value attr: nowrap, checked selected...
          $node->_[HDOM_INFO_QUOTE][] = HDOM_QUOTE_NO;
          $node->attr[$name] = true;
          if ($this->char != '>')
            $this->char = $this->doc[--$this->pos]; // prev
        }
        $node->_[HDOM_INFO_SPACE][] = $space;
        $space = array(
          $this->copy_skip($this->token_blank),
          '',
          ''
        );
      } else
        break;
    } while ($this->char !== '>' && $this->char !== '/');

    $this->link_nodes($node, true);
    $node->_[HDOM_INFO_ENDSPACE] = $space[0];

    // check self closing
    if ($this->copy_until_char_escape('>') === '/') {
      $node->_[HDOM_INFO_ENDSPACE] .= '/';
      $node->_[HDOM_INFO_END] = 0;
    } else {
      // reset parent
      if (!isset($this->self_closing_tags[strtolower($node->tag)]))
        $this->parent = $node;
    }
    $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next

    // If it's a BR tag, we need to set it's text to the default text.
    // This
    // way
    // when
    // we
    // see
    // it
    // in
    // plaintext,
    // we
    // can
    // generate
    // formatting
    // that
    // the
    // user
    // wants.
    // since
    // a
    // br
    // tag
    // never
    // has
    // sub
    // nodes,
    // this
    // works
    // well.
    if ($node->tag == "br") {
      $node->_[HDOM_INFO_INNER] = $this->default_br_text;
    }

    return true;
  }

  // parse attributes
  protected function parse_attr($node, $name, &$space) {
    // Per sourceforge:
    // http://sourceforge.net/tracker/?func=detail&aid=3061408&group_id=218559&atid=1044037
    // If the attribute is already defined inside a tag, only pay atetntion to the
    // first one as opposed to the last one.
    if (isset($node->attr[$name])) {
      return;
    }

    $space[2] = $this->copy_skip($this->token_blank);
    switch ($this->char) {
      case '"' :
        $node->_[HDOM_INFO_QUOTE][] = HDOM_QUOTE_DOUBLE;
        $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
        $node->attr[$name] = $this->restore_noise($this->copy_until_char_escape('"'));
        $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
        break;
      case '\'' :
        $node->_[HDOM_INFO_QUOTE][] = HDOM_QUOTE_SINGLE;
        $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
        $node->attr[$name] = $this->restore_noise($this->copy_until_char_escape('\''));
        $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
        break;
      default :
        $node->_[HDOM_INFO_QUOTE][] = HDOM_QUOTE_NO;
        $node->attr[$name] = $this->restore_noise($this->copy_until($this->token_attr));
    }
    // PaperG: Attributes should not have \r or \n in them, that counts as html
    // whitespace.
    $node->attr[$name] = str_replace("\r", "", $node->attr[$name]);
    $node->attr[$name] = str_replace("\n", "", $node->attr[$name]);
    // PaperG: If this is a "class" selector, lets get rid of the preceeding and
    // trailing space since some people leave it in the multi class case.
    if ($name == "class") {
      $node->attr[$name] = trim($node->attr[$name]);
    }
  }

  // link node's parent
  protected function link_nodes(&$node, $is_child) {
    $node->parent = $this->parent;
    $this->parent->nodes[] = $node;
    if ($is_child) {
      $this->parent->children[] = $node;
    }
  }

  // as a text node
  protected function as_text_node($tag) {
    $node = new DOM($this);
    ++$this->cursor;
    $node->_[HDOM_INFO_TEXT] = '</' . $tag . '>';
    $this->link_nodes($node, false);
    $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
    return true;
  }

  protected function skip($chars) {
    $this->pos += strspn($this->doc, $chars, $this->pos);
    $this->char = ($this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
  }

  protected function copy_skip($chars) {
    $pos = $this->pos;
    $len = strspn($this->doc, $chars, $pos);
    $this->pos += $len;
    $this->char = ($this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
    if ($len === 0)
      return '';

    return substr($this->doc, $pos, $len);
  }

  protected function copy_until($chars) {
    $pos = $this->pos;
    $len = strcspn($this->doc, $chars, $pos);
    $this->pos += $len;
    $this->char = ($this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
    return substr($this->doc, $pos, $len);
  }

  protected function copy_until_char($char) {
    if ($this->char === null)
      return '';

    if (($pos = strpos($this->doc, $char, $this->pos)) === false) {
      $ret = substr($this->doc, $this->pos, $this->size - $this->pos);
      $this->char = null;
      $this->pos = $this->size;

      return $ret;
    }

    if ($pos === $this->pos)
      return '';
    $pos_old = $this->pos;
    $this->char = $this->doc[$pos];
    $this->pos = $pos;

    return substr($this->doc, $pos_old, $pos - $pos_old);
  }

  protected function copy_until_char_escape($char) {
    if ($this->char === null)
      return '';

    $start = $this->pos;
    while (1) {
      if (($pos = strpos($this->doc, $char, $start)) === false) {
        $ret = substr($this->doc, $this->pos, $this->size - $this->pos);
        $this->char = null;
        $this->pos = $this->size;

        return $ret;
      }

      if ($pos === $this->pos)
        return '';

      if ($this->doc[$pos - 1] === '\\') {
        $start = $pos + 1;
        continue;
      }

      $pos_old = $this->pos;
      $this->char = $this->doc[$pos];
      $this->pos = $pos;

      return substr($this->doc, $pos_old, $pos - $pos_old);
    }
  }

  // remove noise from html content
  // save the noise in the $this->noise array.
  protected function remove_noise($pattern, $remove_tag = false) {
    global $debugObject;
    if (is_object($debugObject)) {
      $debugObject->debugLogEntry(1);
    }

    $count = preg_match_all($pattern, $this->doc, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

    for ($i = $count - 1; $i > -1; --$i) {
      $key = '___noise___' . sprintf('% 5d', count($this->noise) + 1000);
      if (is_object($debugObject)) {
        $debugObject->debugLog(2, 'key is: ' . $key);
      }
      $idx = ($remove_tag) ? 0 : 1;
      $this->noise[$key] = $matches[$i][$idx][0];
      $this->doc = substr_replace($this->doc, $key, $matches[$i][$idx][1], strlen($matches[$i][$idx][0]));
    }

    // reset the length of content
    $this->size = strlen($this->doc);
    if ($this->size > 0) {
      $this->char = $this->doc[0];
    }
  }

  // restore noise to html content
  function restore_noise($text) {
    global $debugObject;
    if (is_object($debugObject)) {
      $debugObject->debugLogEntry(1);
    }

    while (($pos = strpos($text, '___noise___')) !== false) {
      // Sometimes there is a broken piece of markup, and we don't GET the pos+11
      // etc... token which indicates a problem outside of us...
      if (strlen($text) > $pos + 15) {
        $key = '___noise___' . $text[$pos + 11] . $text[$pos + 12] . $text[$pos + 13] . $text[$pos + 14] . $text[$pos + 15];
        if (is_object($debugObject)) {
          $debugObject->debugLog(2, 'located key of: ' . $key);
        }

        if (isset($this->noise[$key])) {
          $text = substr($text, 0, $pos) . $this->noise[$key] . substr($text, $pos + 16);
        } else {
          // do this to prevent an infinite loop.
          $text = substr($text, 0, $pos) . 'UNDEFINED NOISE FOR KEY: ' . $key . substr($text, $pos + 16);
        }
      } else {
        // There is no valid key being given back to us... We must get rid of the
        // ___noise___ or we will have a problem.
        $text = substr($text, 0, $pos) . 'NO NUMERIC NOISE KEY' . substr($text, $pos + 11);
      }
    }

    return $text;
  }

  // Sometimes we NEED one of the noise elements.
  function search_noise($text) {
    global $debugObject;
    if (is_object($debugObject)) {
      $debugObject->debugLogEntry(1);
    }

    foreach ($this->noise as $noiseElement) {
      if (strpos($noiseElement, $text) !== false) {
        return $noiseElement;
      }
    }
  }

  function __toString() {
    return $this->root->innertext();
  }

  function __get($name) {
    switch ($name) {
      case 'outertext' :
        return $this->root->innertext();
      case 'innertext' :
        return $this->root->innertext();
      case 'plaintext' :
        return $this->root->text();
      case 'charset' :
        return $this->_charset;
      case 'target_charset' :
        return $this->_target_charset;
    }
  }

  // camel naming conventions
  function childNodes($idx = -1) {
    return $this->root->childNodes($idx);
  }

  function firstChild() {
    return $this->root->first_child();
  }

  function lastChild() {
    return $this->root->last_child();
  }

  function createElement($name, $value = null) {
    return @htmlFromString("<$name>$value</$name>")->first_child();
  }

  function createTextNode($value) {
    return @end(htmlFromString($value)->nodes);
  }

  function getElementById($id) {
    return $this->find("#$id", 0);
  }

  function getElementsById($id, $idx = null) {
    return $this->find("#$id", $idx);
  }

  function getElementByTagName($name) {
    return $this->find($name, 0);
  }

  function getElementsByTagName($name, $idx = -1) {
    return $this->find($name, $idx);
  }

  function loadFile() {
    $args = func_get_args();
    $this->load_file($args);
  }

  /**
   * Combine a base URL and a relative URL to produce a new
   * absolute URL.
   * The base URL is often the URL of a pages,
   * and the relative URL is a URL embedded on that pages.
   *
   * This function implements the "absolutize" algorithm from
   * the RFC3986 specification for URLs.
   *
   * This function supports multi-byte characters with the UTF-8 encoding,
   * per the URL specification.
   *
   * Parameters:
   * baseUrl  the absolute base URL.
   *
   * url  the relative URL to convert.
   *
   * Return values:
   * An absolute URL that combines parts of the base and relative
   * URLs, or FALSE if the base URL is not absolute or if either
   * URL cannot be parsed.
   */
  public static function url_to_absolute($baseUrl, $relativeUrl) {
    // If relative URL has a scheme, clean path and return.
    $r = static::split_url($relativeUrl);
    if ($r === false)
      return false;
    if (!empty($r['scheme'])) {
      if (!empty($r['path']) && $r['path'][0] == '/')
        $r['path'] = static::url_remove_dot_segments($r['path']);

      return static::join_url($r);
    }

    // Make sure the base URL is absolute.
    $b = static::split_url($baseUrl);
    if ($b === false || empty($b['scheme']) || empty($b['host']))
      return false;
    $r['scheme'] = $b['scheme'];

    // If relative URL has an authority, clean path and return.
    if (isset($r['host'])) {
      if (!empty($r['path']))
        $r['path'] = static::url_remove_dot_segments($r['path']);

      return static::join_url($r);
    }
    unset($r['port']);
    unset($r['user']);
    unset($r['pass']);

    // Copy base authority.
    $r['host'] = $b['host'];
    if (isset($b['port']))
      $r['port'] = $b['port'];
    if (isset($b['user']))
      $r['user'] = $b['user'];
    if (isset($b['pass']))
      $r['pass'] = $b['pass'];

    // If relative URL has no path, use base path
    if (empty($r['path'])) {
      if (!empty($b['path']))
        $r['path'] = $b['path'];
      if (!isset($r['query']) && isset($b['query']))
        $r['query'] = $b['query'];

      return static::join_url($r);
    }

    // If relative URL path doesn't start with /, merge with base path
    if ($r['path'][0] != '/') {
      $base = mb_strrchr($b['path'], '/', true, 'UTF-8');
      if ($base === false)
        $base = '';
      $r['path'] = $base . '/' . $r['path'];
    }
    $r['path'] = static::url_remove_dot_segments($r['path']);

    return static::join_url($r);
  }

  /**
   * Filter out "." and ".." segments from a URL's path and return
   * the result.
   *
   * This function implements the "remove_dot_segments" algorithm from
   * the RFC3986 specification for URLs.
   *
   * This function supports multi-byte characters with the UTF-8 encoding,
   * per the URL specification.
   *
   * Parameters:
   * path the path to filter
   *
   * Return values:
   * The filtered path with "." and ".." removed.
   */
  public static function url_remove_dot_segments($path) {
    // multi-byte character explode
    $inSegs = preg_split('!/!u', $path);
    $outSegs = array();
    foreach ($inSegs as $seg) {
      if ($seg == '' || $seg == '.')
        continue;
      if ($seg == '..')
        array_pop($outSegs);
      else
        array_push($outSegs, $seg);
    }
    $outPath = implode('/', $outSegs);
    if ($path[0] == '/')
      $outPath = '/' . $outPath;
    // compare last multi-byte character against '/'
    if ($outPath != '/' && (strlen($path) - 1) == strrpos($path, '/'))
      $outPath .= '/';

    return $outPath;
  }

  /**
   * This function parses an absolute or relative URL and splits it
   * into individual components.
   *
   * RFC3986 specifies the components of a Uniform Resource Identifier (URI).
   * A portion of the ABNFs are repeated here:
   *
   * URI-reference = URI
   * / relative-ref
   *
   * URI  = scheme ":" hier-part [ "?" query ] [ "#" fragment ]
   *
   * relative-ref = relative-part [ "?" query ] [ "#" fragment ]
   *
   * hier-part = "//" authority path-abempty
   * / path-absolute
   * / path-rootless
   * / path-empty
   *
   * relative-part = "//" authority path-abempty
   * / path-absolute
   * / path-noscheme
   * / path-empty
   *
   * authority = [ userinfo "@" ] host [ ":" port ]
   *
   * So, a URL has the following major components:
   *
   * scheme
   * The name of a method used to interpret the rest of
   * the URL. Examples: "http", "https", "mailto", "file'.
   *
   * authority
   * The name of the authority governing the URL's name
   * space. Examples: "example.com", "user@example.com",
   * "example.com:80", "user:password@example.com:80".
   *
   * The authority may include a host name, port number,
   * user name, and password.
   *
   * The host may be a name, an IPv4 numeric address, or
   * an IPv6 numeric address.
   *
   * path
   * The hierarchical path to the URL's resource.
   * Examples: "/index.htm", "/scripts/pages.php".
   *
   * query
   * The data for a query. Examples: "?search=google.com".
   *
   * fragment
   * The name of a secondary resource relative to that named
   * by the path. Examples: "#section1", "#header".
   *
   * An "absolute" URL must include a scheme and path. The authority, query,
   * and fragment components are optional.
   *
   * A "relative" URL does not include a scheme and must include a path. The
   * authority, query, and fragment components are optional.
   *
   * This function splits the $url argument into the following components
   * and returns them in an associative array. Keys to that array include:
   *
   * "scheme" The scheme, such as "http".
   * "host"  The host name, IPv4, or IPv6 address.
   * "port"  The port number.
   * "user"  The user name.
   * "pass"  The user password.
   * "path"  The path, such as a file path for "http".
   * "query"  The query.
   * "fragment" The fragment.
   *
   * One or more of these may not be present, depending upon the URL.
   *
   * Optionally, the "user", "pass", "host" (if a name, not an IP address),
   * "path", "query", and "fragment" may have percent-encoded characters
   * decoded. The "scheme" and "port" cannot include percent-encoded
   * characters and are never decoded. Decoding occurs after the URL has
   * been parsed.
   *
   * Parameters:
   * url  the URL to parse.
   *
   * decode  an optional boolean flag selecting whether
   * to decode percent encoding or not. Default = TRUE.
   *
   * Return values:
   * the associative array of URL parts, or FALSE if the URL is
   * too malformed to recognize any parts.
   */
  public static function split_url($url, $decode = false) {
    // Character sets from RFC3986.
    $xunressub = 'a-zA-Z\d\-._~\!$&\'()*+,;=';
    $xpchar = $xunressub . ':@% ';

    // Scheme from RFC3986.
    $xscheme = '([a-zA-Z][a-zA-Z\d+-.]*)';

    // User info (user + password) from RFC3986.
    $xuserinfo = '(([' . $xunressub . '%]*)' . '(:([' . $xunressub . ':%]*))?)';

    // IPv4 from RFC3986 (without digit constraints).
    $xipv4 = '(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})';

    // IPv6 from RFC2732 (without digit and grouping constraints).
    $xipv6 = '(\[([a-fA-F\d.:]+)\])';

    // Host name from RFC1035. Technically, must start with a letter.
    // Relax that restriction to better parse URL structure, then
    // leave host name validation to application.
    $xhost_name = '([a-zA-Z\d-.%]+)';

    // Authority from RFC3986. Skip IP future.
    $xhost = '(' . $xhost_name . '|' . $xipv4 . '|' . $xipv6 . ')';
    $xport = '(\d*)';
    $xauthority = '((' . $xuserinfo . '@)?' . $xhost . '?(:' . $xport . ')?)';

    // Path from RFC3986. Blend absolute & relative for efficiency.
    $xslash_seg = '(/[' . $xpchar . ']*)';
    $xpath_authabs = '((//' . $xauthority . ')((/[' . $xpchar . ']*)*))';
    $xpath_rel = '([' . $xpchar . ']+' . $xslash_seg . '*)';
    $xpath_abs = '(/(' . $xpath_rel . ')?)';
    $xapath = '(' . $xpath_authabs . '|' . $xpath_abs . '|' . $xpath_rel . ')';

    // Query and fragment from RFC3986.
    $xqueryfrag = '([' . $xpchar . '/?' . ']*)';

    // URL.
    $xurl = '^(' . $xscheme . ':)?' . $xapath . '?' . '(\?' . $xqueryfrag . ')?(#' . $xqueryfrag . ')?$';

    // Split the URL into components.
    if (!preg_match('!' . $xurl . '!', $url, $m))
      return false;

    //Set as default
    $parts = array();

    if (!empty($m[2]))
      $parts['scheme'] = strtolower($m[2]);

    if (!empty($m[7])) {
      if (isset($m[9]))
        $parts['user'] = $m[9];
      else
        $parts['user'] = '';
    }
    if (!empty($m[10]))
      $parts['pass'] = $m[11];

    if (!empty($m[13]))
      $h = $parts['host'] = $m[13];
    else if (!empty($m[14]))
      $parts['host'] = $m[14];
    else if (!empty($m[16]))
      $parts['host'] = $m[16];
    else if (!empty($m[5]))
      $parts['host'] = '';
    if (!empty($m[17]))
      $parts['port'] = $m[18];

    if (!empty($m[19]))
      $parts['path'] = $m[19];
    else if (!empty($m[21]))
      $parts['path'] = $m[21];
    else if (!empty($m[25]))
      $parts['path'] = $m[25];

    if (!empty($m[27]))
      $parts['query'] = $m[28];
    if (!empty($m[29]))
      $parts['fragment'] = $m[30];

    if (!$decode)
      return $parts;
    if (!empty($parts['user']))
      $parts['user'] = rawurldecode($parts['user']);
    if (!empty($parts['pass']))
      $parts['pass'] = rawurldecode($parts['pass']);
    if (!empty($parts['path']))
      $parts['path'] = rawurldecode($parts['path']);
    if (isset($h))
      $parts['host'] = rawurldecode($parts['host']);
    if (!empty($parts['query']))
      $parts['query'] = rawurldecode($parts['query']);
    if (!empty($parts['fragment']))
      $parts['fragment'] = rawurldecode($parts['fragment']);

    return $parts;
  }

  /**
   * This function joins together URL components to form a complete URL.
   *
   * RFC3986 specifies the components of a Uniform Resource Identifier (URI).
   * This function implements the specification's "component recomposition"
   * algorithm for combining URI components into a full URI string.
   *
   * The $parts argument is an associative array containing zero or
   * more of the following:
   *
   * "scheme" The scheme, such as "http".
   * "host"  The host name, IPv4, or IPv6 address.
   * "port"  The port number.
   * "user"  The user name.
   * "pass"  The user password.
   * "path"  The path, such as a file path for "http".
   * "query"  The query.
   * "fragment" The fragment.
   *
   * The "port", "user", and "pass" values are only used when a "host"
   * is present.
   *
   * The optional $encode argument indicates if appropriate URL components
   * should be percent-encoded as they are assembled into the URL. Encoding
   * is only applied to the "user", "pass", "host" (if a host name, not an
   * IP address), "path", "query", and "fragment" components. The "scheme"
   * and "port" are never encoded. When a "scheme" and "host" are both
   * present, the "path" is presumed to be hierarchical and encoding
   * processes each segment of the hierarchy separately (i.e., the slashes
   * are left alone).
   *
   * The assembled URL string is returned.
   *
   * Parameters:
   * parts  an associative array of strings containing the
   * individual parts of a URL.
   *
   * encode  an optional boolean flag selecting whether
   * to do percent encoding or not. Default = true.
   *
   * Return values:
   * Returns the assembled URL string. The string is an absolute
   * URL if a scheme is supplied, and a relative URL if not. An
   * empty string is returned if the $parts array does not contain
   * any of the needed values.
   */
  public static function join_url($parts, $encode = false) {
    if ($encode) {
      if (isset($parts['user']))
        $parts['user'] = rawurlencode($parts['user']);
      if (isset($parts['pass']))
        $parts['pass'] = rawurlencode($parts['pass']);
      if (isset($parts['host']) && !preg_match('!^(\[[\da-f.:]+\]])|([\da-f.:]+)$!ui', $parts['host']))
        $parts['host'] = rawurlencode($parts['host']);
      if (!empty($parts['path']))
        $parts['path'] = preg_replace('!%2F!ui', '/', rawurlencode($parts['path']));
      if (isset($parts['query']))
        $parts['query'] = rawurlencode($parts['query']);
      if (isset($parts['fragment']))
        $parts['fragment'] = rawurlencode($parts['fragment']);
    }

    $url = '';
    if (!empty($parts['scheme']))
      $url .= $parts['scheme'] . ':';
    if (isset($parts['host'])) {
      $url .= '//';
      if (isset($parts['user'])) {
        $url .= $parts['user'];
        if (isset($parts['pass']))
          $url .= ':' . $parts['pass'];
        $url .= '@';
      }
      if (preg_match('!^[\da-f]*:[\da-f.:]+$!ui', $parts['host']))
        $url .= '[' . $parts['host'] . ']'; // IPv6
      else
        $url .= $parts['host']; // IPv4 or name
      if (isset($parts['port']))
        $url .= ':' . $parts['port'];
      if (!empty($parts['path']) && $parts['path'][0] != '/')
        $url .= '/';
    }
    if (!empty($parts['path']))
      $url .= $parts['path'];
    if (isset($parts['query']))
      $url .= '?' . $parts['query'];
    if (isset($parts['fragment']))
      $url .= '#' . $parts['fragment'];

    return $url;
  }

  /**
   * This function encodes URL to form a URL which is properly
   * percent encoded to replace disallowed characters.
   *
   * RFC3986 specifies the allowed characters in the URL as well as
   * reserved characters in the URL. This function replaces all the
   * disallowed characters in the URL with their repective percent
   * encodings. Already encoded characters are not encoded again,
   * such as '%20' is not encoded to '%2520'.
   *
   * Parameters:
   * url  the url to encode.
   *
   * Return values:
   * Returns the encoded URL string.
   */
  public static function encode_url($url) {
    $reserved = array(
      ":" => '!%3A!ui',
      "/" => '!%2F!ui',
      "?" => '!%3F!ui',
      "#" => '!%23!ui',
      "[" => '!%5B!ui',
      "]" => '!%5D!ui',
      "@" => '!%40!ui',
      "!" => '!%21!ui',
      "$" => '!%24!ui',
      "&" => '!%26!ui',
      "'" => '!%27!ui',
      "(" => '!%28!ui',
      ")" => '!%29!ui',
      "*" => '!%2A!ui',
      "+" => '!%2B!ui',
      "," => '!%2C!ui',
      ";" => '!%3B!ui',
      "=" => '!%3D!ui',
      "%" => '!%25!ui'
    );

    $url = rawurlencode($url);
    $url = preg_replace(array_values($reserved), array_keys($reserved), $url);

    return $url;
  }

}