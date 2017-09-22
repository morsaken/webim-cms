<?php
namespace HTMLParser;

use \HTMLParser\Exceptions\CurlException;

/**
 * Class Curl
 *
 * @package HTMLParser
 */
class Curl implements CurlInterface {

  /**
   * A simple curl implementation to get the content of the url.
   *
   * @param string $url
   *
   * @return string
   * @throws \Exception, CurlException
   */
  public function get($url) {
    if (!function_exists('curl_init')) {
      throw new \Exception('Curl library not found');
    }

    $ch = curl_init($url);

    if (!ini_get('open_basedir')) {
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

    $content = curl_exec($ch);
    if ($content === false) {
      // there was a problem
      $error = curl_error($ch);
      throw new CurlException('Error retrieving "' . $url . '" (' . $error . ')');
    }

    return $content;
  }

}