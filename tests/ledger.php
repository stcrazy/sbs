<?php
require dirname(__DIR__).'/api/domain.php';
$admin=['role'=>'admin'];$p=mutate_pool(fresh_pool(),$admin,['action'=>'invite','name'=>'Ledger Test','phone'=>'4155550177','credits'=>5,'cents'=>2000,'note'=>'Cash with invite']);$id=$p['players'][0]['id'];
if(balance_for($p,$id)!==5||array_sum(array_column($p['ledger'],'dollars'))!=20||count($p['ledger'])!==2)throw new Exception('Invite must save credits and payment together.');
$p=grant_invite_credits($p,$id,5);if(balance_for($p,$id)!==5)throw new Exception('Sharing must not grant initial credits twice.');
$p=mutate_pool($p,$admin,['action'=>'credit','playerId'=>$id,'credits'=>0,'cents'=>1000,'note'=>'Later payment']);if(balance_for($p,$id)!==5||array_sum(array_column($p['ledger'],'dollars'))!=30)throw new Exception('Payment must preserve picking credits.');
$unpaid=mutate_pool(fresh_pool(),$admin,['action'=>'invite','name'=>'Unpaid','phone'=>'4155550178','credits'=>5,'cents'=>0]);if(count($unpaid['ledger'])!==1||$unpaid['ledger'][0]['dollars']!=0)throw new Exception('Payment is optional.');
echo 'PASS: invite credits and optional payment, no duplicate grant, and separate later payments.'.PHP_EOL;
