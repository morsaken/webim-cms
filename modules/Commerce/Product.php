<?php
/**
 * @author Orhan POLAT
 */

namespace Commerce;

use \System\Content;

class Product {

  /**
   * List
   *
   * @param null|mixed $limit
   * @param null|mixed $categories
   * @param null|\Closure $filters
   *
   * @return \stdClass
   */
  protected function load($limit = null, $categories = null, \Closure $filters = null) {
    $class = $this;

    return Content::published('product', $limit, $categories, $filters)
      ->with('meta')
      ->with(function ($rows) use ($class) {
        return array_map(function (&$row) use ($class) {
          //Stock status
          $stock = new \stdClass();
          $stock->in = array_get($row, 'meta.stock_count', 0) > 0;
          $stock->label = $stock->in ? lang('label.in_stock', 'Stokta') : lang('label.not_in_stock', 'Mevcut Değil');

          $row->stock = $stock;

          $row->price = $class->formatPrice($row->meta->price, $row->meta->currency);
          $row->discount = $class->formatPrice($row->meta->discount, $row->meta->currency);
          $row->sell_price = $class->formatPrice($row->meta->sell_price, $row->meta->currency);
        }, $rows);
      });
  }

  /**
   * Format price
   *
   * @param float $price
   * @param string $currency_code
   *
   * @return \stdClass
   */
  protected function formatPrice($price, $currency_code) {
    $currencies = conf('currency', array());
    $defaultCurrency = me('currency', array_first(array_keys($currencies)));

    //Price format
    $format = lang('currency.' . $defaultCurrency . '.format', '%%s ' . $defaultCurrency);

    //Currency
    $currency = new \stdClass();
    $currency->code = $defaultCurrency;
    $currency->icon = lang('currency.' . $defaultCurrency . '.icon', $defaultCurrency);

    $formatted = new \stdClass();
    $formatted->raw = $price;
    $formatted->currency = $currency;
    $formatted->converted = $price / array_get($currencies, $currency_code, 1) * array_get($currencies, $defaultCurrency, 1);
    $formatted->formatted = number_format(
      floatval($formatted->converted),
      lang('currency.digits_after_decimal', 2),
      lang('currency.decimal_symbol', '.'),
      lang('currency.thousand_separator', ',')
    );
    $formatted->string = sprintf($format, $formatted->formatted);

    return $formatted;
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