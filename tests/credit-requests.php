<?php
require dirname(__DIR__).'/api/domain.php';
function expect(bool $ok,string $why):void{if(!$ok)throw new Exception($why);echo 'PASS: '.$why.PHP_EOL;}
function denied(callable $fn,string $why):void{try{$fn();}catch(RuntimeException $e){echo 'PASS: '.$why.PHP_EOL;return;}throw new Exception($why);}
$admin=['role'=>'admin'];$p=mutate_pool(fresh_pool(),$admin,['action'=>'invite','name'=>'Test Player','phone'=>'4155550199']);$id=$p['players'][0]['id'];$user=['role'=>'player','playerId'=>$id];
$p=mutate_pool($p,$user,['action'=>'requestCredits','credits'=>4,'note'=>'Four more please']);$request=$p['creditRequests'][0];expect($request['playerId']===$id&&balance_for($p,$id)===0,'request uses own account and does not grant credits');
denied(fn()=>mutate_pool($p,$user,['action'=>'requestCredits','credits'=>1]),'duplicate pending request denied');
denied(fn()=>mutate_pool($p,$user,['action'=>'resolveRequest','requestId'=>$request['id'],'approve'=>true]),'player cannot approve requests');
$p=mutate_pool($p,$admin,['action'=>'resolveRequest','requestId'=>$request['id'],'approve'=>true]);expect(balance_for($p,$id)===4&&$p['ledger'][0]['dollars']==0,'approval issues requested credits with no payment');
denied(fn()=>mutate_pool($p,$admin,['action'=>'resolveRequest','requestId'=>$request['id'],'approve'=>true]),'approval cannot issue twice');
$p=mutate_pool($p,$user,['action'=>'requestCredits','credits'=>2]);$p=mutate_pool($p,$admin,['action'=>'resolveRequest','requestId'=>$p['creditRequests'][1]['id'],'approve'=>false]);expect(balance_for($p,$id)===4,'declining does not change balance');
denied(fn()=>mutate_pool($p,$user,['action'=>'buy','index'=>0,'label'=>'','image'=>'/api/image/11111111-1111-4111-8111-111111111111']),'picker requires a name');
denied(fn()=>mutate_pool($p,$user,['action'=>'buy','index'=>0,'label'=>'Lucky','image'=>'']),'picker requires artwork');
$p=mutate_pool($p,$user,['action'=>'buy','index'=>0,'label'=>'Lucky','image'=>'/api/image/11111111-1111-4111-8111-111111111111']);expect($p['squares'][0]['label']==='Lucky'&&balance_for($p,$id)===3,'nickname and artwork save together for one credit');
