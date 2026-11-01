<?php
require dirname(__DIR__).'/api/domain.php';
function check(bool $condition,string $name):void{if(!$condition)throw new RuntimeException('FAILED: '.$name);echo 'PASS: '.$name.PHP_EOL;}
function rejects(callable $fn,string $name):void{try{$fn();}catch(RuntimeException $e){echo 'PASS: '.$name.PHP_EOL;return;}throw new RuntimeException('FAILED: '.$name);}
$admin=['role'=>'admin'];$p=fresh_pool();
$p=mutate_pool($p,$admin,['action'=>'invite','name'=>'Test Player','phone'=>'4155550123']);$id=$p['players'][0]['id'];$user=['role'=>'player','playerId'=>$id];
check($p['players'][0]['phone']==='+14155550123','phone-based invitations need no email');
rejects(fn()=>mutate_pool($p,$admin,['action'=>'invite','name'=>'Duplicate','phone'=>'4155550123']),'duplicate phone denied');
$p=mutate_pool($p,$admin,['action'=>'credit','playerId'=>$id,'credits'=>100,'cents'=>250000,'note'=>'Test payment']);
$p=mutate_pool($p,$user,['action'=>'buy','index'=>47,'label'=>'Lucky']);
check(balance_for($p,$id)===99&&$p['squares'][47]['playerId']===$id,'purchase records one owner and one credit debit');
rejects(fn()=>mutate_pool($p,$user,['action'=>'buy','index'=>47,'label'=>'Again']),'duplicate square denied');
rejects(fn()=>mutate_pool($p,$user,['action'=>'credit','playerId'=>$id,'credits'=>1,'cents'=>0,'note'=>'Bad']),'player cannot issue credits');
rejects(fn()=>mutate_pool($p,$user,['action'=>'phone','playerId'=>$id,'phone'=>'4155550124']),'player cannot change login number');
rejects(fn()=>mutate_pool($p,['role'=>'player','playerId'=>'stranger'],['action'=>'buy','index'=>1,'label'=>'Bad']),'uninvited identity denied');
$p=mutate_pool($p,$admin,['action'=>'release','index'=>47]);check(balance_for($p,$id)===100,'release restores credit');
rejects(fn()=>mutate_pool($p,$admin,['action'=>'numbers','random'=>true]),'numbers require a full board');
for($i=0;$i<100;$i++)$p=mutate_pool($p,$user,['action'=>'buy','index'=>$i,'label'=>'Square '.$i]);
$p=mutate_pool($p,$admin,['action'=>'numbers','rows'=>range(0,9),'cols'=>range(0,9)]);check(count($p['rows'])===10,'complete board accepts unique digits');
$p=mutate_pool($p,$admin,['action'=>'release','index'=>47]);check(!isset($p['squares'][47]),'organizer can erase after draw before scoring');
rejects(fn()=>mutate_pool($p,$admin,['action'=>'score','quarter'=>0,'row'=>24,'col'=>17]),'cannot score a board with erased squares');
$p=mutate_pool($p,$admin,['action'=>'buy','index'=>47,'playerId'=>$id,'label'=>'Replacement']);
rejects(fn()=>mutate_pool($p,$user,['action'=>'personalize','index'=>47,'label'=>'Bad']),'locked squares cannot change artwork');
$p=mutate_pool($p,$admin,['action'=>'score','quarter'=>0,'row'=>24,'col'=>17]);check($p['scores'][0]['row']%10===4&&$p['scores'][0]['col']%10===7,'score digits identify row 4 column 7');
rejects(fn()=>mutate_pool($p,$admin,['action'=>'score','quarter'=>1,'row'=>0,'col'=>0]),'cumulative score cannot decrease');
rejects(fn()=>mutate_pool($p,$admin,['action'=>'release','index'=>47]),'cannot erase after scoring starts');
check(valid_digits(digit_draw()),'random numbers are a permutation of 0–9');
rejects(fn()=>phone('4155550123&body=bad'),'phone URL injection denied');
echo 'All PHP domain checks passed.'.PHP_EOL;

$f=fresh_pool();$f=mutate_pool($f,$admin,['action'=>'invite','name'=>'Invited','phone'=>'4155550199']);$fid=$f['players'][0]['id'];$f=grant_invite_credits($f,$fid);check(balance_for($f,$fid)===5,'invitation defaults to five credits');check($f['ledger'][0]['dollars']===0,'invitation credits record no payment');$f=grant_invite_credits($f,$fid,5);check(balance_for($f,$fid)===5&&count($f['ledger'])===1,'replacement invitation never grants twice');
rejects(fn()=>grant_invite_credits($f,$fid,-1),'negative invitation grant denied');
