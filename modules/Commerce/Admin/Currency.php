<?php
/**
 * @author Orhan POLAT
 */

namespace Commerce\Admin;

use \Admin\Manager;
use \System\Settings as SysSettings;
use \Webim\Library\Message;
use \Webim\View\Manager as View;

class Currency {

 /**
  * World currency list
  *
  * @var array
  */
 protected static $list = array(
  'TRY' => 'Turkish Lira (TRY)',
  'USD' => 'United States Dollars (USD)',
  'EUR' => 'Euro (EUR)',
  'GBP' => 'United Kingdom Pounds (GBP)',
  'CAD' => 'Canadian Dollars (CAD)',
  'ALL' => 'Albanian Lek (ALL)',
  'DZD' => 'Algerian Dinar (DZD)',
  'AOA' => 'Angolan Kwanza (AOA)',
  'ARS' => 'Argentine Pesos (ARS)',
  'AMD' => 'Armenian Dram (AMD)',
  'AWG' => 'Aruban Florin (AWG)',
  'AUD' => 'Australian Dollars (AUD)',
  'BBD' => 'Barbadian Dollar (BBD)',
  'AZN' => 'Azerbaijani Manat (AZN)',
  'BDT' => 'Bangladesh Taka (BDT)',
  'BSD' => 'Bahamian Dollar (BSD)',
  'BHD' => 'Bahraini Dinar (BHD)',
  'BYR' => 'Belarusian Ruble (BYR)',
  'BZD' => 'Belize Dollar (BZD)',
  'BTN' => 'Bhutanese Ngultrum (BTN)',
  'BAM' => 'Bosnia and Herzegovina Convertible Mark (BAM)',
  'BRL' => 'Brazilian Real (BRL)',
  'BOB' => 'Bolivian Boliviano (BOB)',
  'BWP' => 'Botswana Pula (BWP)',
  'BND' => 'Brunei Dollar (BND)',
  'BGN' => 'Bulgarian Lev (BGN)',
  'MMK' => 'Burmese Kyat (MMK)',
  'KHR' => 'Cambodian Riel',
  'KYD' => 'Cayman Dollars (KYD)',
  'XAF' => 'Central African CFA Franc (XAF)',
  'CLP' => 'Chilean Peso (CLP)',
  'CNY' => 'Chinese Yuan Renminbi (CNY)',
  'COP' => 'Colombian Peso (COP)',
  'KMF' => 'Comorian Franc (KMF)',
  'CRC' => 'Costa Rican Colones (CRC)',
  'HRK' => 'Croatian Kuna (HRK)',
  'CZK' => 'Czech Koruny (CZK)',
  'DKK' => 'Danish Kroner (DKK)',
  'DOP' => 'Dominican Peso (DOP)',
  'XCD' => 'East Caribbean Dollar (XCD)',
  'EGP' => 'Egyptian Pound (EGP)',
  'ETB' => 'Ethiopian Birr (ETB)',
  'XPF' => 'CFP Franc (XPF)',
  'FJD' => 'Fijian Dollars (FJD)',
  'GMD' => 'Gambian Dalasi (GMD)',
  'GHS' => 'Ghanaian Cedi (GHS)',
  'GTQ' => 'Guatemalan Quetzal (GTQ)',
  'GYD' => 'Guyanese Dollar (GYD)',
  'GEL' => 'Georgian Lari (GEL)',
  'HTG' => 'Haitian Gourde (HTG)',
  'HNL' => 'Honduran Lempira (HNL)',
  'HKD' => 'Hong Kong Dollars (HKD)',
  'HUF' => 'Hungarian Forint (HUF)',
  'ISK' => 'Icelandic Kronur (ISK)',
  'INR' => 'Indian Rupees (INR)',
  'IDR' => 'Indonesian Rupiah (IDR)',
  'ILS' => 'Israeli New Shekel (NIS)',
  'JMD' => 'Jamaican Dollars (JMD)',
  'JPY' => 'Japanese Yen (JPY)',
  'JEP' => 'Jersey Pound',
  'JOD' => 'Jordanian Dinar (JOD)',
  'KZT' => 'Kazakhstani Tenge (KZT)',
  'KES' => 'Kenyan Shilling (KES)',
  'KWD' => 'Kuwaiti Dinar (KWD)',
  'KGS' => 'Kyrgyzstani Som (KGS)',
  'LVL' => 'Latvian Lati (LVL)',
  'LBP' => 'Lebanese Pounds (LBP)',
  'LRD' => 'Liberian Dollar (LRD)',
  'LTL' => 'Lithuanian Litai (LTL)',
  'MGA' => 'Malagasy Ariary (MGA)',
  'MKD' => 'Macedonia Denar (MKD)',
  'MOP' => 'Macanese Pataca (MOP)',
  'MVR' => 'Maldivian Rufiyaa (MVR)',
  'MXN' => 'Mexican Pesos (MXN)',
  'MYR' => 'Malaysian Ringgits (MYR)',
  'MUR' => 'Mauritian Rupee (MUR)',
  'MDL' => 'Moldovan Leu (MDL)',
  'MAD' => 'Moroccan Dirham',
  'MNT' => 'Mongolian Tugrik',
  'MZN' => 'Mozambican Metical',
  'NAD' => 'Namibian Dollar',
  'NPR' => 'Nepalese Rupee (NPR)',
  'ANG' => 'Netherlands Antillean Guilder',
  'NZD' => 'New Zealand Dollars (NZD)',
  'NIO' => 'Nicaraguan Córdoba (NIO)',
  'NGN' => 'Nigerian Naira (NGN)',
  'NOK' => 'Norwegian Kroner (NOK)',
  'OMR' => 'Omani Rial (OMR)',
  'PKR' => 'Pakistani Rupee (PKR)',
  'PGK' => 'Papua New Guinean Kina (PGK)',
  'PYG' => 'Paraguayan Guarani (PYG)',
  'PEN' => 'Peruvian Nuevo Sol (PEN)',
  'PHP' => 'Philippine Peso (PHP)',
  'PLN' => 'Polish Zlotych (PLN)',
  'QAR' => 'Qatari Rial (QAR)',
  'RON' => 'Romanian Lei (RON)',
  'RUB' => 'Russian Rubles (RUB)',
  'RWF' => 'Rwandan Franc (RWF)',
  'WST' => 'Samoan Tala (WST)',
  'SAR' => 'Saudi Riyal (SAR)',
  'STD' => 'Sao Tome And Principe Dobra (STD)',
  'RSD' => 'Serbian dinar (RSD)',
  'SCR' => 'Seychellois Rupee (SCR)',
  'SGD' => 'Singapore Dollars (SGD)',
  'SYP' => 'Syrian Pound (SYP)',
  'ZAR' => 'South African Rand (ZAR)',
  'KRW' => 'South Korean Won (KRW)',
  'LKR' => 'Sri Lankan Rupees (LKR)',
  'SRD' => 'Surinamese Dollar (SRD)',
  'SEK' => 'Swedish Kronor (SEK)',
  'CHF' => 'Swiss Francs (CHF)',
  'TWD' => 'Taiwan Dollars (TWD)',
  'THB' => 'Thai baht (THB)',
  'TZS' => 'Tanzanian Shilling (TZS)',
  'TTD' => 'Trinidad and Tobago Dollars (TTD)',
  'TND' => 'Tunisian Dinar (TND)',
  'TMT' => 'Turkmenistani Manat (TMT)',
  'UGX' => 'Ugandan Shilling (UGX)',
  'UAH' => 'Ukrainian Hryvnia (UAH)',
  'AED' => 'United Arab Emirates Dirham (AED)',
  'UYU' => 'Uruguayan Pesos (UYU)',
  'UZS' => 'Uzbekistan som (UZS)',
  'VUV' => 'Vanuatu Vatu (VUV)',
  'VEF' => 'Venezuelan Bolivares (VEF)',
  'VND' => 'Vietnamese đồng (VND)',
  'XOF' => 'West African CFA franc (XOF)',
  'YER' => 'Yemeni Rial (YER)',
  'ZMW' => 'Zambian Kwacha (ZMW)'
 );

 /**
  * Manager
  *
  * @var \Admin\Manager
  */
 protected static $manager;

 /**
  * Register current class and routes
  *
  * @param \Admin\Manager $manager
  */
 public static function register(Manager $manager) {
  $manager->addRoute($manager->prefix . '/ecommerce/currencies', __CLASS__ . '::getIndex');
  $manager->addRoute($manager->prefix . '/ecommerce/currencies', __CLASS__ . '::postForm', 'POST');
  $manager->addRoute($manager->prefix . '/ecommerce/currencies/:currency+', __CLASS__ . '::deleteForm', 'DELETE');

  $parent = $manager->addMenu(lang('admin.menu.modules', 'Modüller'), $manager->prefix . '/ecommerce', lang('admin.menu.ecommerce', 'E-Ticaret'), null, 'fa fa-shopping-cart');
  $manager->addMenu(lang('admin.menu.modules', 'Modüller'), $manager->prefix . '/ecommerce/currencies', lang('admin.menu.currencies', 'Para Birimleri'), $parent, 'fa fa-tags');

  static::$manager = $manager;
 }

 /**
  * Index
  *
  * @param null|string $lang
  *
  * @return string
  */
 public function getIndex($lang = null) {
  $manager = static::$manager;

  $manager->set('caption', lang('admin.menu.currencies', 'Para Birimleri'));
  $manager->breadcrumb($manager->prefix . '/ecommerce', lang('admin.menu.ecommerce', 'E-Ticaret'));
  $manager->breadcrumb($manager->prefix . '/ecommerce/currencies', lang('admin.menu.currencies', 'Para Birimleri'));

  $manager->put('currencyList', static::$list);

  return View::create('modules.ecommerce.currencies')->data($manager::data())->render();
 }

 /**
  * Post form
  *
  * @param null|string $lang
  *
  * @return string
  */
 public function postForm($lang = null) {
  $manager = static::$manager;

  $manager->app->response->setContentType('json');

  $settings = array();

  $ratios = explode(',', input('ratio'));

  foreach (explode(',', input('currency')) as $key => $currency) {
   $settings['currency.' . $currency] = array_get($ratios, $key);
  }

  return SysSettings::init()->saveAll('system', $settings)->forData();
 }

 /**
  * Delete form
  *
  * @param null|string $lang
  * @param string $currency
  *
  * @return string
  */
 public function deleteForm($lang = null, $currency) {
  $manager = static::$manager;

  $manager->app->response->setContentType('json');

  if (strlen($currency) && isset(static::$list[$currency])) {
   return SysSettings::init()->remove('system', 'currency.' . $currency)->forData();
  } else {
   return Message::result(lang('message.nothing_done'))->forData();
  }
 }

}