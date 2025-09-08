<?php
require_once __DIR__.'/db.php'; require_once __DIR__.'/functions.php';
$d=read_json(); $uid=$d['uid']??''; $ap=$d['applied']??'';
if($uid===''||$ap===''||!is_hex32($ap)) json_out(['ok'=>false,'msg'=>'bad input'],400);
$pdo=get_pdo(); $pdo->prepare('INSERT INTO logs(uid,action,applied) VALUES(?,?,?)')->execute([$uid,'confirm',strtoupper($ap)]);
json_out(['ok'=>true,'msg'=>'confirmed']);
