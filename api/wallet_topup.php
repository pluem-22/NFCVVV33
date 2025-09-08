<?php
require_once __DIR__.'/db.php'; require_once __DIR__.'/functions.php';
$d=read_json(); $uid=$d['uid']??''; $amt=$d['amount']??null;
if($uid===''||!is_numeric($amt)||($amt=(int)$amt)<=0) json_out(['allow'=>false,'msg'=>'bad input'],400);
$pdo=get_pdo(); $row=$pdo->prepare('SELECT balance FROM cards WHERE uid=?'); $row->execute([$uid]); $row=$row->fetch();
if(!$row) json_out(['allow'=>false,'msg'=>'UID not found']);
$new=(int)$row['balance']+$amt;
$pdo->beginTransaction();
try{
  $pdo->prepare('UPDATE cards SET balance=? WHERE uid=?')->execute([$new,$uid]);
  $pdo->prepare('INSERT INTO logs(uid,action,delta,balance_after)VALUES(?,?,?,?)')->execute([$uid,'topup',$amt,$new]);
  $pdo->commit();
}catch(Throwable $e){ $pdo->rollBack(); json_out(['allow'=>false,'msg'=>'db error'],500); }
json_out(['allow'=>true,'newBlock4'=>block4_from_balance($new),'msg'=>'OK']);
