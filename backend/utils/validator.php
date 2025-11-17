<?php
class Validator
{
  public static function email($email)
  {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
  }

  public static function required($value)
  {
    return !empty($value);
  }

  public static function minLength($value, $length)
  {
    return strlen($value) >= $length;
  }

  public static function macAddress($mac)
  {
    return preg_match('/^([0-9A-Fa-f]{2}[:-]) {5} ([0-9A-Fa-f]{2})$/', $mac);
  }

  public static function ipAddress($ip)
  {
    return filter_var($ip, FILTER_VALIDATE_IP);
  }

  public static function voucherCode($code)
  {
    // Matches the format from your portal.js
    return preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $code);
  }
}
