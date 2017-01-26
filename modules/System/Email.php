<?php
/**
 * @author Orhan POLAT
 */

namespace System;

use \Webim\Library\File;
use \Webim\Library\Message;
use \Webim\Mail\PHPMailer;
use \Webim\View\Manager as View;

class Email {

  /**
   * Mailer
   *
   * @var PHPMailer
   */
  protected $mailer;

  /**
   * HTML content
   *
   * @var string
   */
  protected $html;

  /**
   * Constructor
   *
   * @param null|string $subject
   */
  public function __construct($subject) {
    //Create instance
    $this->mailer = new PHPMailer();
    $this->mailer->CharSet = 'UTF-8';
    $this->mailer->Subject = $subject;
    $this->mailer->isHTML(true);

    //SMTP Mailing
    if (conf('email.smtp.active', 'no') == 'yes') {
      $this->mailer->isSMTP();
      $this->mailer->Host = conf('email.smtp.host');
      $this->mailer->Port = conf('email.smtp.port');

      if (in_array(conf('email.smtp.secure'), array('ssl', 'tls'), true)) {
        $this->mailer->SMTPSecure = conf('email.smtp.secure');
      }

      $this->mailer->SMTPAuth = (conf('email.smtp.auth') == 'yes');
      $this->mailer->Username = conf('email.smtp.user');
      $this->mailer->Password = conf('email.smtp.pass');
    }

    //Set default from
    $this->mailer->setFrom(conf('email.from'), conf('email.from_name'));
  }

  /**
   * Create class
   *
   * @param null|string $subject
   *
   * @return $this
   */
  public static function create($subject = null) {
    return new static($subject);
  }

  /**
   * From
   *
   * @param string $address
   * @param string $name
   *
   * @return $this
   */
  public function from($address, $name = '') {
    //Set from
    $this->mailer->setFrom($address, $name);

    return $this;
  }

  /**
   * To
   *
   * @param string $address
   * @param string $name
   *
   * @return $this
   */
  public function to($address, $name = '') {
    //Add to
    $this->mailer->addAddress($address, $name);

    return $this;
  }

  /**
   * Reply to
   *
   * @param string $address
   * @param string $name
   *
   * @return $this
   */
  public function replyTo($address, $name = '') {
    //Add reply to
    $this->mailer->addReplyTo($address, $name);

    return $this;
  }

  /**
   * CC
   *
   * @param string $address
   * @param string $name
   *
   * @return $this
   */
  public function cc($address, $name = '') {
    //Add cc
    $this->mailer->addCC($address, $name);

    return $this;
  }

  /**
   * BCC
   *
   * @param string $address
   * @param string $name
   *
   * @return $this
   */
  public function bcc($address, $name = '') {
    //Add bcc
    $this->mailer->addBCC($address, $name);

    return $this;
  }

  /**
   * Attachment
   *
   * @param \Webim\Library\File $file
   * @param string $name
   *
   * @return $this
   */
  public function attach(File $file, $name = '') {
    //Add attachment
    $this->mailer->addAttachment($file->getPath(), (strlen($name) ? $name : $file->name));

    return $this;
  }

  /**
   * Embed image
   *
   * @param \Webim\Library\File $image
   * @param string $cid
   *
   * @return $this
   */
  public function embedImage(File $image, $cid) {
    //Add embedded image
    $this->mailer->addEmbeddedImage($image->getPath(), $cid);

    return $this;
  }

  /**
   * HTML
   *
   * @param null|\Webim\Library\File $file
   * @param array $params
   *
   * @return $this
   */
  public function html(File $file = null, $params = array()) {
    //Content
    $content = null;

    if (!is_null($file) && ($file instanceof File) && $file->exists()) {
      //Get content
      $content = $file->content();
    }

    return $this->body($content, $params);
  }

  /**
   * Message body
   *
   * @param null|string $content
   * @param array $params
   *
   * @return $this
   */
  public function body($content = null, $params = array()) {
    if (is_null($content)) {
      //Default content
      $content = $this->defaultHTML(nl2br(implode("\n", $this->serialize($params))));
    }

    //Change with params
    $body = $this->setParams($content, $params);

    //HTML for web
    $this->html = $this->setImages($body, false);

    //Set body
    $this->mailer->Body = $this->setImages($body);

    return $this;
  }

  /**
   * Send
   *
   * @param bool $alsoMail
   *
   * @return \Webim\Library\Message
   */
  public function send($alsoMail = true) {
    $message = Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'));

    if ($alsoMail) {
      Mail::create($this->mailer->Subject, $this->html)->to('forsaken')->send();
    }

    if ($this->mailer->Send()) {
      $message->success = true;
      $message->text = lang('message.email_sent', 'E-posta iletildi...');
    } else {
      $message->text = lang('message.email_not_sent', [$this->mailer->ErrorInfo], 'E-posta iletim hatası: "%s"');
    }

    return $message;
  }

  /**
   * Set params
   *
   * @param string $content
   * @param array $params
   *
   * @return string
   */
  private function setParams($content, $params = array()) {
    if (preg_match_all('/{{([a-zA-Z0-9\-_]+)\:begin}}(.*?){{\1\:end}}/s', $content, $matches)) {
      $keys = $matches[1];
      $vals = $matches[2];

      foreach ($keys as $i => $key) {
        if (isset($params[$key]) && is_array($params[$key])) {
          //Converted
          $converted = '';

          foreach ($params[$key] as $values) {
            //Add to converted
            $converted .= $vals[$i];

            foreach ($values as $k => $v) {
              $converted = preg_replace('/{{' . $key . '\:' . $k . '}}/s', $v, $converted);
            }
          }

          //Replace converted content
          $content = preg_replace('/{{' . $key . '\:begin}}.*?{{' . $key . '\:end}}/s', $converted, $content);
        }
      }
    }

    foreach ($params as $k => $v) {
      if (!is_array($v)) {
        $content = preg_replace('/{{' . $k . '}}/s', $v, $content);
      }
    }

    return $content;
  }

  /**
   * Set img
   *
   * @param string $content
   * @param bool $embed
   *
   * @return string
   */
  private function setImages($content, $embed = true) {
    //Images
    $count = 0;

    if (preg_match_all('/{{img\(([a-zA-Z0-9\-_\/.]+)\)}}/s', $content, $matches)) {
      foreach ($matches[1] as $src) {
        $path = View::getPath()->folder('layouts');
        $image = File::in($path)->file($src);

        if ($image->exists()) {
          //Counter
          $count++;

          if ($embed) {
            //CID
            $cid = 'image' . $count . '@webim.mail';
            $content = str_replace('{{img(' . $src . ')}}', 'cid:' . $cid, $content);

            //Embed
            $this->embedImage($image, $cid);
          } else {
            $content = str_replace('{{img(' . $src . ')}}', $image->src(), $content);
          }
        }
      }
    }

    return $content;
  }

  /**
   * Default HTML content
   *
   * @param string $body
   * @param string $title
   *
   * @return string
   */
  private function defaultHTML($body, $title = '') {
    //Return
    return '<html>
   <head>
    <title>' . $title . '</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style type="text/css">
    <!--
    * {
     font-family: Arial, sans-serif;
     font-size: 12px;
    }
    -->
    </style>
   </head>
   <body>
    ' . $body . '
   </body>
  </html>';
  }

  /**
   * Serialize array
   *
   * @param array $array
   *
   * @return array
   */
  private function serialize($array = array()) {
    //Return
    $text = array();

    foreach ($array as $key => $value) {
      if (!is_array($value)) {
        $text[] = $key . ': ' . $value;
      } else {
        if (!is_numeric($key)) {
          $text[] = "\n" . $key;
        }

        $text = array_merge($text, $this->serialize($value));
      }
    }

    return $text;
  }

}