<?php
/**
 * @author Orhan POLAT
 */

//Register auto load
\Webim\Library\AutoLoader::register(__DIR__ . DS);

/**
 * Navigation
 *
 * @param string $title
 * @param string $link
 * @param null|array $meta
 * @param array $children
 *
 * @return stdClass
 */
function nav($title, $link, $meta = null, $children = array()) {
  $nav = new \stdClass();
  $nav->name = array_get($meta, 'name');
  $nav->link = $link;
  $nav->url = !preg_match('/^(http|ftp)./', $link) ? url($link) : $link;
  $nav->title = $title;
  $nav->icon = array_get($meta, 'icon');
  $nav->class = array_get($meta, 'class');
  $nav->children = $children;
  $nav->active = url_is($link, true);

  return $nav;
}

/**
 * Menu
 *
 * @param $list
 *
 * @return array
 */
function menu($list) {
  $nav = array();

  foreach ($list as $menu) {
    $children = array();

    if (isset($menu->children) && count($menu->children)) {
      $children = menu($menu->children);
    }

    $nav[] = nav($menu->title, $menu->url, $menu, $children);
  }

  return $nav;
}

/**
 * Button
 *
 * @param string $title
 * @param string $url
 * @param string $icon
 *
 * @return stdClass
 */
function btn($title, $url, $icon) {
  $nav = new \stdClass();
  $nav->title = $title;
  $nav->url = $url;
  $nav->icon = $icon;

  return $nav;
}

/**
 * Breadcrumb
 *
 * @param array $crumbs
 * @param string|array $title
 * @param null|string $link
 *
 * @return array
 */
function crumb(array $crumbs, $title, $link = null) {
  if (is_array($title)) {
    foreach ($title as $crumb) {
      $crumbs = call_user_func(__FUNCTION__, $crumbs, @$crumb[0], @$crumb[1]);
    }

    return $crumbs;
  }

  foreach ($crumbs as $crumb) {
    $crumb->active = false;
  }

  $crumb = new \stdClass();
  $crumb->link = $link;
  $crumb->url = url($link);
  $crumb->title = $title;
  $crumb->active = true;

  $crumbs[] = $crumb;

  return $crumbs;
}

/**
 * Cascade list
 *
 * @param array $list
 * @param bool $simple
 * @param int $level
 *
 * @return array
 */
function cascade($list, $simple = true, $level = 0) {
  $cascaded = array();

  foreach ($list as $item) {
    //Set level
    $item->level = $level;

    //Url
    $url = isset($item->full_url) ? $item->full_url : $item->url;

    //Set
    $cascaded[$url] = ($simple ? $item->title : $item);

    if (isset($item->children) && count($item->children)) {
      $cascaded += call_user_func(__FUNCTION__, $item->children, $simple, ($level + 1));
    }
  }

  return $cascaded;
}

/**
 * Cascade page menu
 *
 * @param array $pages
 * @param string $url
 * @param null|int $parent_id
 * @param int $level
 *
 * @return array
 */
function cascadePageMenu($url, $pages, $parent_id = null, $level = 0) {
  $list = array();

  foreach ($pages as $page) {
    $use = false;

    if ($parent_id) {
      if ($page->parent_id == $parent_id) {
        $use = true;
      }
    } elseif ($page->url == $url) {
      $use = true;
    }

    if ($use) {
      //Menu item
      $menu = new \stdClass();
      $menu->url = isset($page->full_url) ? $page->full_url : $page->url;
      $menu->title = $page->title;
      $menu->level = $level;

      $list[$menu->url] = $menu;
      $list += call_user_func(__FUNCTION__, $url, $pages, $page->id, ($level + 1));
    }
  }

  return $list;
}

/**
 * Page menu
 *
 * @param object $page
 * @param array $pages
 *
 * @return array
 */
function makePageMenu($page, $pages) {
  $root = $page;
  $parent_id = $page->parent_id;

  while ($parent_id) {
    foreach ($pages as $item) {
      if ($item->id == $parent_id) {
        $root = $item;
        $parent_id = $item->parent_id;
      }
    }
  }

  //Cascade menu list
  $menu = cascadePageMenu($root->url, $pages);

  return count($menu) > 1 ? $menu : array();
}

/**
 * Make pages
 *
 * @param array $pages
 * @param null|string $url
 * @param int $level
 *
 * @return array
 */
function makePages($pages, $url = '', $level = 0) {
  $list = array();

  if (!is_null($url)) {
    $url = (strlen($url) ? $url . '/' : '');
  }

  foreach ($pages as $key => $page) {
    $sub = $page;

    $sub->url = (!is_null($url) ? $url : '') . $page->url;
    $sub->level = $level;

    $list[$sub->url] = $sub;

    if (isset($page->children) && count($page->children)) {
      $list += call_user_func(__FUNCTION__, $page->children, (!is_null($url) ? $page->url : null), ($level + 1));
    }
  }

  return $list;
}

/**
 * Menu html
 *
 * @param array $menus
 * @param bool $sub
 * @param bool $init
 *
 * @return string
 */
function makeMenu($menus, $sub = false, $init = true) {
  //Return
  $html = array();

  if (count($menus) && ($sub || $init)) {
    $html[] = '<ul class="nav ' . ($sub ? 'sub-nav' : 'sidebar-menu') . '">';
  }

  if ($init) {
    foreach ($menus as $type => $list) {
      $html[] = '<li class="sidebar-label pt15">' . $type . '</li>';

      $html[] = call_user_func(__FUNCTION__, $list, false, false);
    }
  } else {
    foreach ($menus as $key => $menu) {
      $html[] = '<li' . (url_is($menu->link, ($key != 0)) ? ' class="active"' : '') . '>';

      if (count($menu->sub)) {
        $html[] = '<a class="accordion-toggle' . (url_is($menu->link, true) ? ' menu-open' : '') . '" href="#">';
        $html[] = '<span class="' . $menu->icon . '"></span>';
        $html[] = '<span class="sidebar-title">' . e($menu->title) . '</span>';
        $html[] = '<span class="caret"></span>';
        $html[] = '</a>';

        $html[] = call_user_func(__FUNCTION__, $menu->sub, true, false);
      } else {
        $html[] = '<a href="' . $menu->url . '">';
        $html[] = '<span class="' . $menu->icon . '"></span>';
        $html[] = ($sub ? e($menu->title) : '<span class="sidebar-title">' . e($menu->title) . '</span>');

        if (!is_null($menu->badge)) {
          $html[] = '<span class="sidebar-title-tray">';
          $html[] = '<span class="label label-xs bg-primary">' . e($menu->badge) . '</span>';
          $html[] = '</span>';
        }

        $html[] = '</a>';
      }

      $html[] = '</li>';
    }
  }

  if (count($menus) && ($sub || $init)) {
    $html[] = '</ul>';
  }

  return implode("\n", $html);
}

/**
 * Url up level
 *
 * @param array $breadcrumb
 *
 * @return string
 */
function urlUp(array $breadcrumb) {
  $total = count($breadcrumb);

  if ($total - 2) {
    return $breadcrumb[$total - 2]->url;
  }

  return url();
}

/**
 * Minify HTML
 *
 * @param string $input
 *
 * @return string mixed
 */
function minifyHTML($input) {
  if (trim($input) === '') return $input;
  // Remove extra white-space(s) between HTML attribute(s)
  $input = preg_replace_callback('#<([^\/\s<>!]+)(?:\s+([^<>]*?)\s*|\s*)(\/?)>#s', function ($matches) {
    return '<' . $matches[1] . preg_replace('#([^\s=]+)(\=([\'"]?)(.*?)\3)?(\s+|$)#s', ' $1$2', $matches[2]) . $matches[3] . '>';
  }, str_replace("\r", "", $input));

  // Minify inline CSS declaration(s)
  if (strpos($input, ' style=') !== false) {
    $input = preg_replace_callback('#<([^<]+?)\s+style=([\'"])(.*?)\2(?=[\/\s>])#s', function ($matches) {
      return '<' . $matches[1] . ' style=' . $matches[2] . minifyCSS($matches[3]) . $matches[2];
    }, $input);
  }

  if (strpos($input, '</style>') !== false) {
    $input = preg_replace_callback('#<style(.*?)>(.*?)</style>#is', function ($matches) {
      return '<style' . $matches[1] . '>' . minifyCSS($matches[2]) . '</style>';
    }, $input);
  }

  if (strpos($input, '</script>') !== false) {
    $input = preg_replace_callback('#<script(.*?)>(.*?)</script>#is', function ($matches) {
      return '<script' . $matches[1] . '>' . minifyJS($matches[2]) . '</script>';
    }, $input);
  }

  return preg_replace(
    array(
      // t = text
      // o = tag open
      // c = tag close
      // Keep important white-space(s) after self-closing HTML tag(s)
      '#<(img|input)(>| .*?>)#s',
      // Remove a line break and two or more white-space(s) between tag(s)
      '#(<!--.*?-->)|(>)(?:\n*|\s{2,})(<)|^\s*|\s*$#s',
      '#(<!--.*?-->)|(?<!\>)\s+(<\/.*?>)|(<[^\/]*?>)\s+(?!\<)#s', // t+c || o+t
      '#(<!--.*?-->)|(<[^\/]*?>)\s+(<[^\/]*?>)|(<\/.*?>)\s+(<\/.*?>)#s', // o+o || c+c
      '#(<!--.*?-->)|(<\/.*?>)\s+(\s)(?!\<)|(?<!\>)\s+(\s)(<[^\/]*?\/?>)|(<[^\/]*?\/?>)\s+(\s)(?!\<)#s', // c+t || t+o || o+t -- separated by long white-space(s)
      '#(<!--.*?-->)|(<[^\/]*?>)\s+(<\/.*?>)#s', // empty tag
      '#<(img|input)(>| .*?>)<\/\1>#s', // reset previous fix
      '#(&nbsp;)&nbsp;(?![<\s])#', // clean up ...
      '#(?<=\>)(&nbsp;)(?=\<)#', // --ibid
      // Remove HTML comment(s) except IE comment(s)
      '#\s*<!--(?!\[if\s).*?-->\s*|(?<!\>)\n+(?=\<[^!])#s'
    ),
    array(
      '<$1$2</$1>',
      '$1$2$3',
      '$1$2$3',
      '$1$2$3$4$5',
      '$1$2$3$4$5$6$7',
      '$1$2$3',
      '<$1$2',
      '$1 ',
      '$1',
      ''
    ),
    $input
  );
}

/**
 * Minify CSS
 *
 * @param string $input
 *
 * @return string mixed
 */
function minifyCSS($input) {
  if (trim($input) === '') return $input;

  return preg_replace(
    array(
      // Remove comment(s)
      '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')|\/\*(?!\!)(?>.*?\*\/)|^\s*|\s*$#s',
      // Remove unused white-space(s)
      '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\'|\/\*(?>.*?\*\/))|\s*+;\s*+(})\s*+|\s*+([*$~^|]?+=|[{};,>~+]|\s*+-(?![0-9\.])|!important\b)\s*+|([[(:])\s++|\s++([])])|\s++(:)\s*+(?!(?>[^{}"\']++|"(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')*+{)|^\s++|\s++\z|(\s)\s+#si',
      // Replace `0(cm|em|ex|in|mm|pc|pt|px|vh|vw|%)` with `0`
      '#(?<=[\s:])(0)(cm|em|ex|in|mm|pc|pt|px|vh|vw|%)#si',
      // Replace `:0 0 0 0` with `:0`
      '#:(0\s+0|0\s+0\s+0\s+0)(?=[;\}]|\!important)#i',
      // Replace `background-position:0` with `background-position:0 0`
      '#(background-position):0(?=[;\}])#si',
      // Replace `0.6` with `.6`, but only when preceded by `:`, `,`, `-` or a white-space
      '#(?<=[\s:,\-])0+\.(\d+)#s',
      // Minify string value
      '#(\/\*(?>.*?\*\/))|(?<!content\:)([\'"])([a-z_][a-z0-9\-_]*?)\2(?=[\s\{\}\];,])#si',
      '#(\/\*(?>.*?\*\/))|(\burl\()([\'"])([^\s]+?)\3(\))#si',
      // Minify HEX color code
      '#(?<=[\s:,\-]\#)([a-f0-6]+)\1([a-f0-6]+)\2([a-f0-6]+)\3#i',
      // Replace `(border|outline):none` with `(border|outline):0`
      '#(?<=[\{;])(border|outline):none(?=[;\}\!])#',
      // Remove empty selector(s)
      '#(\/\*(?>.*?\*\/))|(^|[\{\}])(?:[^\s\{\}]+)\{\}#s'
    ),
    array(
      '$1',
      '$1$2$3$4$5$6$7',
      '$1',
      ':0',
      '$1:0 0',
      '.$1',
      '$1$3',
      '$1$2$4$5',
      '$1$2$3',
      '$1:0',
      '$1$2'
    ),
    $input
  );
}

/**
 * Minify Javascript
 *
 * @param string $input
 *
 * @return string mixed
 */
function minifyJS($input) {
  if (trim($input) === '') return $input;

  return preg_replace(
    array(
      // Remove comment(s)
      '#\s*("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')\s*|\s*\/\*(?!\!|@cc_on)(?>[\s\S]*?\*\/)\s*|\s*(?<![\:\=])\/\/.*(?=[\n\r]|$)|^\s*|\s*$#',
      // Remove white-space(s) outside the string and regex
      '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\'|\/\*(?>.*?\*\/)|\/(?!\/)[^\n\r]*?\/(?=[\s.,;]|[gimuy]|$))|\s*([!%&*\(\)\-=+\[\]\{\}|;:,.<>?\/])\s*#s',
      // Remove the last semicolon
      '#;+\}#',
      // Minify object attribute(s) except JSON attribute(s). From `{'foo':'bar'}` to `{foo:'bar'}`
      '#([\{,])([\'])(\d+|[a-z_][a-z0-9_]*)\2(?=\:)#i',
      // --ibid. From `foo['bar']` to `foo.bar`
      '#([a-z0-9_\)\]])\[([\'"])([a-z_][a-z0-9_]*)\2\]#i'
    ),
    array(
      '$1',
      '$1$2',
      '}',
      '$1$3',
      '$1.$3'
    ),
    $input
  );
}