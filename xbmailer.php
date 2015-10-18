<?php
  /**
   * xbMailer
   * Универсальный интерфейс для отправки электронной почты
   *
   * @version 1.0
   * @author  Xander Bass
   */

  /**
   * Class xbMailer
   * @property      array  $mailConfig
   * @property      array  $SMTPConfig
   * @property-read bool   $SMTPValid
   * @property      string $mailer
   * @property-read string $charset
   * @property-read string $lastError
   */
  class xbMailer {
    protected $_mailConfig = array();
    protected $_SMTPConfig = array();
    protected $_SMTPValid  = false;
    protected $_mailer     = 'mail';
    protected $_charset    = 'utf-8';
    protected $_lastError  = 'ok';

    protected $_addresses = array();
    protected $_copies    = array();
    protected $_hidden    = array();

    /******** ОБЩИЕ МЕТОДЫ КЛАССА ********/
    function __construct() { $this->mailConfig = array(); }

    function __get($n) { $N = "_$n"; return property_exists($this,$N) ? $this->$N : false; }

    function __set($n,$v) {
      switch ($n) {
        // mailConfig
        case 'mailConfig':
          $_ = is_array($v) ? $v : '';
          foreach (array(
            'version'  => '1.0',
            'type'     => 'plain',
            'charset'  => $this->charset,
            'from'     => 'No Reply',
            'replyto'  => 'no-reply@'.$_SERVER['SERVER_NAME'],
            'sendname' => 'unknown'
          ) as $k => $d) if (isset($_[$k])) {
            if (is_null($_[$k])) $_[$k] = $d;
          } else { $_[$k] = $d; }
          $this->_mailConfig = $_;
          return $_;
        // SMTPConfig
        case 'SMTPConfig':
          if (!is_array($v)) return false;
          $_ = $v;
          foreach (array('host','user','pass') as $k) {
            if (!isset($_[$k]))  return false;
            if (is_null($_[$k])) return false;
          }
          foreach (array('port' => 25,'te' => 8,'priority' => 3) as $k => $d)
            if (isset($_[$k])) {
              if (is_null($_[$k])) $_[$k] = $d;
            } else { $_[$k] = $d; }
          $this->_SMTPConfig = $_;
          if (isset($_['from'])) $this->_mailConfig['replyto'] = $_['from'];
          $this->_SMTPValid  = true;
          return $_;
        // mailer
        case 'mailer':
          if ( !in_array($v,array('sendmail','smtp'))
            || (($v == 'smtp') && !$this->_SMTPValid)
          ) return false;
          $this->_mailer = $v;
          return $this->_mailer;
      }
      return false;
    }

    /******** ВНУТРЕННИЕ МЕТОДЫ КЛАССА ********/
    protected function correct_mail($str,$onlyAddr=false) {
      $mtpl = '([\w\.\-]+)\@([\w\.\-]+)';
      $ftpl = '/^(.*)\<'.$mtpl.'\>$/siu';
      $name = $this->_mailConfig['sendname'];
      $mail = '';
      if (preg_match($ftpl,$str)) {
        $name = preg_replace($ftpl,'\1',$str);
        $mail = preg_replace($ftpl,'\2@\3',$str);
      } elseif (preg_match('/^'.$mtpl.'$/siu',$str)) { $mail = $str; }
      if (empty($host) || empty($mail)) return false;
      if ($onlyAddr) return $mail;
      return $this->encode_str($name).' <'.$mail.'>';
    }

    protected function _socket($socket,$resp,$str='') {
      $sresp = null;
      while (@substr($sresp,3,1) != ' ') {
        if (!($sresp = fgets($socket,256))) {
          $this->_lastError = $str;
          return false;
        }
      }
      if (!(substr($sresp,0,3) == $resp)) {
        $this->_lastError = $str;
        return false;
      }
      return true;
    }

    protected function encode_str($s) {
      return '=?'.$this->_mailConfig['charset'].'?B?'.base64_encode($s)."?=";
    }

    protected function encode_file($fn,$boundary) {
      $msg = 'Content-Type: application/octet-stream; name="'.basename($fn).'"'."\r\n";
      $msg.= "Content-transfer-encoding: base64\r\n";
      $msg.= 'Content-Disposition: attachment; filename="'.basename($fn).'"'."\r\n\r\n";
      $f   = fopen($fn,"rb");
      $msg.= chunk_split(base64_encode(fread($f,filesize($fn))));
      fclose($f);
      $msg.= "\r\n$boundary";
      return $msg;
    }

    protected function get_reply() {
      return $this->encode_str($this->_mailConfig['from']).' <'.$this->_mailConfig['replyto'].'>';
    }

    protected function get_headers($to,$subject) {
      $headers = array(
        "MIME-Version" => $this->_mailConfig['version'],
        "Content-Type" => 'text/'.$this->_mailConfig['type'].'; charset="'.$this->_mailConfig['charset'].'"',
        "Content-Transfer-Encoding" => "8bit",
        "From"         => $this->get_reply(),
        "Reply-To"     => $this->get_reply(),
        "Subject"      => $this->encode_str($subject),
        "To"           => implode(',',$to)
      );
      if (!empty($this->_copies)) $headers["CC"]  = implode(',',$this->_copies);
      if (!empty($this->_hidden)) $headers["BCC"] = implode(',',$this->_hidden);
      $headers["X-Mailer"]   = "uniMailer ".$this->_mailer;
      $headers["X-Priority"] = $this->_SMTPConfig['priority'];
      return $headers;
    }

    /******** ПУБЛИЧНЫЕ МЕТОДЫ КЛАССА ********/
    /* CLASS:METHOD
      @name        : address
      @description : Добавление адресов

      @return : array
    */
    public function address() {
      $I = func_get_args();
      if (!is_array($I)) return false;
      if (empty($I))     return false;
      foreach ($I as $str)
        if ($_ = $this->correct_mail($str))
          if (!in_array($_,$this->_addresses)) $this->_addresses[] = $_;
      return $this->_addresses;
    }

    /* CLASS:METHOD
      @name        : copy
      @description : Добавление адресов для копий

      @return : array
    */
    public function copy() {
      $I = func_get_args();
      if (!is_array($I)) return false;
      if (empty($I))     return false;
      foreach ($I as $str)
        if ($_ = $this->correct_mail($str))
          if (!in_array($_,$this->_copies)) $this->_copies[] = $_;
      return $this->_copies;
    }

    /* CLASS:METHOD
      @name        : hidden
      @description : Добавление адресов для скрытых копий

      @return : array
    */
    public function hidden() {
      $I = func_get_args();
      if (!is_array($I)) return false;
      if (empty($I))     return false;
      foreach ($I as $str)
        if ($_ = $this->correct_mail($str))
          if (!in_array($_,$this->_hidden)) $this->_hidden[] = $_;
      return $this->_hidden;
    }

    /* CLASS:METHOD
      @name        : send
      @description : Отправка сообщения

      @param : $mto     |        | value |        | Адреса
      @param : $subject | string | value |        | Адреса
      @param : $message | string | value |        | Адреса
      @param : $attach  |        | value | @FALSE | Адреса

      @return : string
    */
    public function send($mto,$subject,$message,$attach=false) {
      $dto = new DateTime('now');
      $bnd = "--".self::key()."\r\n";

      $rec = $this->_addresses;
      if (is_array($mto)) {
        foreach ($mto as $v) if ($_ = $this->correct_mail($v)) $rec[] = $_;
      } else {
        if (!is_null($mto)) {
          $_mto = explode(',',$mto);
          foreach ($_mto as $a)
            if ($_ = $this->correct_mail($a)) $rec[] = $_;
        }
      }
      if (empty($rec)) return false;

      $headers = $this->get_headers($rec,$subject);

      $msg = "$message\r\n";

      if (is_array($attach)) if (count($attach) > 0) {
        $headers["Content-Type"] = 'multipart/mixed; boundary="'.$bnd.'"';
        unset($headers["Content-Transfer-Encoding"]);
        $msg = $bnd;
        $msg.= 'Content-Type: text/'.$this->_mailConfig['type'].'; charset="'.$this->_mailConfig['charset'].'"'."\r\n";
        $msg.= "Content-Transfer-Encoding: 8bit\r\n\r\n$message\r\n\r\n$bnd";
        foreach ($attach as $fn) $msg.= $this->encode_file($fn,$bnd);
      }

      $_ = 'Date: '.$dto->format('D, d M Y H:i:s')." UT\r\n";
      foreach ($headers as $k => $v) $_.= "$k: $v\r\n";
      $headers = $_;

      switch ($this->_mailer) {
        case 'smtp':
          if (!$socket = fsockopen(
            $this->_SMTPConfig['host'],
            $this->_SMTPConfig['port'],
            $en,$es,30)
          ) {
            $this->lastError = "$en : $es";
            return false;
          }

          if (!$this->_socket($socket,"220",'no_socket')) return false;

          $lines = array(
            array("250","EHLO ".$this->_SMTPConfig['host'],"no_ehlo"),
            array("334","AUTH LOGIN","no_auth"),
            array("334",base64_encode($this->_SMTPConfig['user']),"no_login"),
            array("235",base64_encode($this->_SMTPConfig['pass']),"no_pass"),
            array("250","MAIL FROM: <".$this->_mailConfig['replyto'].">","no_from")
          );

          foreach ($rec as $mtoi)
            $lines[] = array("250","RCPT TO: <".$this->correct_mail($mtoi,true).">","no_to");

          $lines[] = array("354","DATA","no_data");
          $lines[] = array("250","$headers\r\n$msg\r\n.","no_mail");

          foreach ($lines as $line) {
            fputs($socket,$line[1]."\r\n");
            if (!$this->_socket($socket,$line[0],$line[2])) {
              fclose($socket);
              return false;
            }
          }

          $this->_lastError = 'ok';
          fputs($socket,"QUIT\r\n");
          fclose($socket);
          return true;
        default:
          $ret = true;
          $nst = array();
          foreach ($rec as $to)
            if (!mail($to,$subject,$msg,$headers)) {
              $ret = false;
              $nst[] = $to;
            }
          $this->_lastError = $ret ? 'ok' : 'not sent by semdmail to: '.implode(',',$nst);
          return $ret;
      }
    }

    /* CLASS:STATIC
      @name        : key
      @description : Генерирует строку случайных символов

      @param : $c | integer | value | 32 | Количество символов

      @return : string | Сгенерированная строка
    */
    public static function key($c=32) {
      $s = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
      $R = '';
      for($_ = 0; $_ < $c; $_++) $R.= $s[rand(0,61)];
      return $R;
    }
  }

  /**
   * @copyleft Xander Bass. 2015
   */
?>