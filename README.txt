Что это?
  В сущности ничего нового. Небольшой класс для отправки сообщений
  по электронной почте. Две разновидности отправки - sendmail и SMTP.

Как использовать?
  Да хотя бы вот так:

<?php
  $mailer = new xbMailer();
  $mailer->mailConfig = array(
    'type' => 'html'
  );
  $mailer->SMTPConfig = array(
    'host' => 'smtp.example.com',
    'user' => 'user@example.com',
    'pass' => 'somesecretword'
  );
  $mailer->send(array(
    'first@example.com'
    'Vasya <second@example.com>'
  ),'Test','Hello World!');
?>

Что поддерживается?
  Свойства:
    mailConfig - массив, содержащий настройки отправки. Поддерживает
                 следующие элементы:
                   type (тип содержимого; plain),
                   charset (кодировка; utf-8),
                   from (имя отправителя; No Reply),
                   replyto (адрес отправителя; no-reply@текущий_хост),
                   sendname (имя получателя; inknown)
    SMTPConfig - массив, содержащий настройки SMTP. Поддерживает
                 следующие элементы:
                   host (сервер; обязателен),
                   user (пользователь; обязателен),
                   pass (пароль; обязателен),
                   from (адрес отправителя),
                   port (порт; 25)
    mailer     - тип отправки (sendmail, smtp)
    SMTPValid  - флаг корректности настроек SMTP (readonly)
    lastError  - последняя ошибка отправки

  Методы:
    address, copy, hidden - добавление адресов отправки, для копий,
                            для скрытых копий. В качестве аргументов
                            передаются собственно адреса
    send($to,$subject,$message,$attach=false) - главный метод.
      $to      - адреса отправителей (строка либо массив)
      $subject - тема сообщения
      $message - тело сообщения
      $attach  - аттачи (массив имён существующих на сервере файлов)
