<?php
/**
 * @author Orhan POLAT
 */

namespace Commerce;


class Cart {

  protected function add($product, $quantity = 1) {
    $cart = $this->get();

    if (isset($cart['items'][$product->id])) {
      $cart['items'][$product->id]->quantity += $quantity;
    } else {
      $cart['items'][$product->id] = $this->item($product, $quantity);
    }

    //Save cart
    $cart = $this->save($cart, true);

    //Cart total
    return $this->sum($cart);
  }

  protected function remove() {

  }

  protected function get() {
    $cart = array(
      'id' => 0,
      'total' => 0,
      'total_final' => '',
      'currency' => me('currency', array_first(array_keys(conf('currency', array())))),
      'version' => 0,
      'items' => array()
    );

    if (Session::current()->get('cart.items')) {
      $cart['items'] = Session::current()->get('cart.items', array());
    } elseif (me('id')) {
      $content = Content::init()
        ->only('type', 'cart')
        ->where('url', me('id'))
        ->load()->with('meta')->get('rows.0');

      if ($content) {
        $cart['id'] = $content->id;
        $cart['version'] = $content->version;

        $ids = array();

        foreach ($content->meta as $product) {
          $ids[$product->id] = $product->count;
        }

        if (count($ids)) {
          foreach (Product::load(null, null, function ($content) use ($ids) {
            $content->whereIn('id', array_keys($ids));
          }) as $product) {
            $cart['items'][$product->id] = $this->item($product, intval($ids[$product->id]));
          }
        }
      }
    }

    //Save cart
    $cart = $this->save($cart);

    //Cart total
    return $this->sum($cart);
  }

  private function item($product, $quantity = 0) {
    $item = new \stdClass();
    $item->id = $product->id;
    $item->url = $product->url;
    $item->title = $product->title;
    $item->poster = $product->poster->image;
    $item->currency = $product->meta->currency;
    $item->price = $product->price;
    $item->sell_price = $product->sell_price;
    $item->quantity = $quantity;
    $item->price_sum = Product::formatPrice($item->sell_price->raw * $item->quantity, $item->currency);

    return $item;
  }

  private function sum($cart) {
    $total = 0;

    //Default currency
    $currency = me('currency', array_first(array_keys(conf('currency', array()))));

    //Default format
    $format = lang('currency.' . $currency . '.format', '%%s ' . $currency);

    foreach ($cart['items'] as &$item) {
      $total += ($item->price * $item->quantity);
      $cart['currency'] = $item->currency;

      $item->total_price = $item->price * $item->quantity;

      //Format
      $format = lang('currency.' . $item->currency . '.format', '%%s ' . $item->currency);

      $item->total_price_final = sprintf($format, number_format(
        floatval($item->total_price),
        lang('currency.digits_after_decimal', 2),
        lang('currency.decimal_symbol', '.'),
        lang('currency.thousand_separator', ',')
      ));
    }

    $cart['total'] = $total;
    $cart['total_final'] = sprintf($format, number_format(
      floatval($cart['total']),
      lang('currency.digits_after_decimal', 2),
      lang('currency.decimal_symbol', '.'),
      lang('currency.thousand_separator', ',')
    ));

    return $cart;
  }

  private function save($cart, $strict = false) {
    if (me('id') && (!Session::current()->get('cart.saved') || $strict)) {
      if ($cart['id'] == 0) {
        $current = Content::init()->where('type', 'cart')->where('url', me('id'))->load()->get('rows.0');

        $cart['id'] = array_get($current, 'id');
        $cart['version'] = array_get($current, 'version');
      }

      //Add to db
      $save = Content::init()
        ->set('id', $cart['id'])
        ->set('type', 'cart')
        ->set('language', lang())
        ->set('url', me('id'))
        ->set('title', me('full_name'))
        ->set('version', $cart['version'])
        ->set('publish_date', Carbon::now())
        ->save(function ($id) use ($cart) {
          $this->saveMeta($id, $cart['items']);
        });

      if ($save->success()) {
        Session::current()->set('cart.saved', true);

        $cart['id'] = $save->returns('id');
        $cart['version'] = $save->returns('version');
      }
    }

    //Set to session
    Session::current()->set('cart.items', $cart['items']);

    return $cart;
  }

  /**
   * Call statically class
   *
   * @param string $method
   * @param array $args
   *
   * @return mixed
   */
  public static function __callStatic($method, $args) {
    $instance = new static;

    return call_user_func_array(array($instance, $method), $args);
  }

}