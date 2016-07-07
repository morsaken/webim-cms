<?php
defined("WEBIM") or die("Dosya yok");

/**
 * @author Orhan POLAT
 */

use \System\Email;
use \Webim\App;
use \Webim\Http\Session;
use \Webim\Image\Captcha;
use \Webim\Library\Carbon;
use \Webim\Library\File;
use \Webim\Library\Message;
use \Webim\View\Manager as View;

App::make()->group('(/:lang#[a-z]{2}#)?', function () {
 //Default path
 $path = File::path('views.frontend.default');

 //Set default path
 View::setPath($path);

 //Common conf
 $data = array(
  'root' => View::getPath()->folder('layouts')->src(),
  'title' => conf('frontend.' . lang() . '.title', 'Web-IM XI'),
  'description' => conf('frontend.' . lang() . '.description', 'Web Internet Manager'),
  'keywords' => conf('frontend.' . lang() . '.keywords'),
  'breadcrumb' => array()
 );

 $this->get('/', function() use ($data) {
  //Publish date
  $date = Carbon::createFromTimestamp(
   strtotime(conf('system.publish_date'))
  );

  $data['date'] = $date->toATOMString();

  return View::create('home')->data($data)->render();
 });

 $this->post('/', function() {
  $this->response->setContentType('json');

  $message = Message::result('Geçersiz doğrulama kodu!');

  $code = strtolower(Session::current()->get('captcha'));

  Session::current()->delete('captcha');

  if ($code == strtolower(input('code'))) {
   if (filter_var(input('email'), FILTER_VALIDATE_EMAIL) !== false) {
    //Create and send
    $send = Email::create('Web-IM E-Posta [' . conf('system.name', 'Web-IM XI') . ']')
     ->from(conf('email.from'), conf('email.from_name'))
     ->to(conf('email.from'), conf('email.from_name'))
     ->html(null, array(
      'ip' => $this->request->getClientIp(),
      'date' => Carbon::now()->toDayDateTimeString(),
      'email' => input('email')
     ))->send();

    if ($send) {
     //Save into file
     File::path('assets', 'notify.txt')->create()->write(date('Y-m-d H:i:s') . ' - ' . input('email') . PHP_EOL, true);

     $message->success = true;
     $message->text = 'E-posta adresiniz iletildi, teşekkür ederiz...';
    } else {
     $message->text = 'Kaydınız alınamadı, lütfen tekrar deneyiniz!';
    }
   } else {
    $message->text = 'Geçersiz e-posta adresi!';
   }
  }

  return $message->forData();
 });

 $this->get('/captcha', function($lang = null) {
  //Fonts
  $fonts = File::in('views.frontend.default.layouts.fonts')->fileIn('*.ttf')->files();

  //Create
  $captcha = Captcha::create($fonts);

  //Set to check
  Session::current()->set('captcha', $captcha->getStr());

  //Display
  $captcha->simple()->fontSize(14)->size(70, 20)->bgColor(238, 238, 238)->strColor(46, 50, 143)->display();
 });
});