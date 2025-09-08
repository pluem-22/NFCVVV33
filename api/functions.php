<?php
function read_json(){ $d=json_decode(file_get_contents('php://input'),true); return is_array($d)?$d:[]; }
function json_out($x,$code=200){ http_response_code($code); header('Content-Type:application/json; charset=utf-8'); echo json_encode($x); exit; }
function block4_from_balance(int $cents): string {
  $bin = hash('sha256', SERVER_SECRET==='' ? (string)$cents : (SERVER_SECRET.'|'.$cents), true);
  return strtoupper(bin2hex(substr($bin,0,16))); // HEX32
}
function is_hex32($s){ return (bool)preg_match('/^[0-9A-Fa-f]{32}$/',$s); }
