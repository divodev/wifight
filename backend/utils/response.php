<?php
header('Content-Type: application/json; charset=utf-8');

class Response
{

  public static function success($data = null, $message = 'Success', $code = 200)
  {
    http_response_code($code);
    echo json_encode([
      'success' => true,
      'message' => $message,
      'data' => $data
    ]);
  }

  public static function error($message = 'Error', $code = 400)
  {
    http_response_code($code);
    echo json_encode([
      'success' => false,
      'message' => $message
    ]);
  }

  public static function json($data, $code = 200)
  {
    http_response_code($code);
    echo json_encode($data);
  }
}
