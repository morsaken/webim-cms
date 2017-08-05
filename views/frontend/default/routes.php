<?php
defined('WEBIM') or die('Dosya yok');

/**
 * @author Orhan POLAT
 */

use \System\Email;
use \Webim\App;
use \Webim\Library\Carbon;
use \Webim\Library\File;
use \Webim\Library\Message;
use \Webim\View\Manager as View;

App::make()->group((count(langs()) ? '(/:lang#(' . implode('|', array_keys(langs())) . ')#)?' : ''), function () {
  $folder = basename(dirname(__FILE__));

  //Default path
  $path = File::path('views.frontend.' . $folder);

  //Set default path
  View::setPath($path);

  //Publish date
  $date = Carbon::createFromTimestamp(
    strtotime(conf('system.' . lang() . '.publish_date'))
  );

  //Common conf
  $data = array(
    'root' => View::getPath()->folder('layouts')->src(),
    'title' => conf('frontend.' . lang() . '.title', 'Web-IM XI'),
    'description' => conf('frontend.' . lang() . '.description', 'Web Internet Manager'),
    'keywords' => conf('frontend.' . lang() . '.keywords'),
    'breadcrumb' => array(),
    'date' => $date->toATOMString()
  );

  //404
  $this->setNotFoundTemplate(function ($title, $body) use ($data) {
    return View::create('home')->data($data)->render();
  });

  $this->get('/', function () use ($data) {
    return View::create('home')->data($data)->render();
  });

  $this->post('/', function () {
    $this->response->setContentType('json');

    $message = Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'));

    if (filter_var(input('email'), FILTER_VALIDATE_EMAIL) !== false) {
      //File
      $file = View::getPath()->folder('layouts.email')->file('subscribe.html');

      //Create and send
      $send = Email::create('Web-IM E-Posta [' . conf('system.name', 'Web-IM XI') . ']')
        ->from(conf('email.from'), conf('email.from_name'))
        ->to(conf('email.from'), conf('email.from_name'))
        ->html($file, array(
          'ip' => $this->request->getClientIp(),
          'date' => Carbon::now()->toDayDateTimeString(),
          'email' => input('email')
        ))->send();

      if ($send) {
        //Save into file
        File::path('assets', 'notify.txt')->create()->write(date('Y-m-d H:i:s') . ' - ' . input('email') . PHP_EOL, true);

        $message->success = true;
        $message->text = lang('message.subscription_success', 'Kaydınız alındı, teşekkür ederiz...');
      } else {
        $message->text = lang('message.subscription_failed', 'Kaydınız alınamadı, lütfen tekrar deneyiniz!');
      }
    } else {
      $message->text = lang('message.invalid_email_address', 'Geçersiz e-posta adresi!');
    }

    return $message->forData();
  });

});