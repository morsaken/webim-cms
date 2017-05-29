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
 * @param array $children
 *
 * @return stdClass
 */
function nav($title, $link, $children = array()) {
  $nav = new \stdClass();
  $nav->title = $title;
  $nav->url = !preg_match('/^(http|ftp)./', $link) ? url($link) : $link;
  $nav->link = $link;
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

    $nav[] = nav($menu->title, $menu->url, $children);
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
 * @param string $title
 * @param null|string $link
 *
 * @return array
 */
function crumb(array &$crumbs, $title, $link = null) {
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

      unset($item->children);
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
 * HTML Dom from file
 *
 * @param string $url
 * @param bool $use_include_path
 * @param NULL|string $context
 * @param int $offset
 * @param int $maxLen
 * @param bool $lowercase
 * @param bool $forceTagsClosed
 *
 * @return bool|\HTML\DOM
 */
function htmlFromFile($url, $use_include_path = false, $context = null, $offset = -1, $maxLen = -1, $lowercase = true, $forceTagsClosed = true) {
  // We DO force the tags to be terminated.
  $dom = new \HTML\DOMNode(null, $lowercase, $forceTagsClosed);

  // For sourceforge users: uncomment the next line and comment the
  // retrieve_url_contents line 2 lines down if it is not already done.
  $contents = @file_get_contents($url, $use_include_path, $context, $offset);

  // Paper - use our own mechanism for getting the contents as we want to
  // control the timeout.
  // $contents = retrieve_url_contents($url);
  if (empty($contents) || strlen($contents) > 600000) {
    return false;
  }

  // The second parameter can force the selectors to all be lowercase.
  $dom->load($contents, $lowercase);

  return $dom;
}

/**
 * HTML Dom from string
 *
 * @param string $str
 * @param bool $lowercase
 * @param bool $forceTagsClosed
 *
 * @return bool|\HTML\DOM
 */
function htmlFromString($str, $lowercase = true, $forceTagsClosed = true) {
  $dom = new \HTML\DOMNode(null, $lowercase, $forceTagsClosed);

  if (empty($str) || strlen($str) > 600000) {
    $dom->clear();

    return false;
  }

  $dom->load($str, $lowercase);

  return $dom;
}

/**
 * Dump HTML Dom tree
 *
 * @param \HTML\DOM $node
 */
function htmlDump($node) {
  $node->dump($node);
}