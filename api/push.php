<?php
declare(strict_types=1);
function pending_credit_requests():array{
 $all=[];foreach(query('SELECT id,name,archived,data FROM sbs_games')->fetchAll() as $g){
  if($g['archived'])continue;$p=json_decode($g['data'],true,512,JSON_THROW_ON_ERROR);
  foreach($p['creditRequests']??[] as $r)if($r['status']==='pending')$all[]=['game'=>$g['id'],'gameName'=>$g['name'],'id'=>$r['id'],'credits'=>$r['credits'],'at'=>$r['at']];
 }return $all;
}
function push_ready():bool{return is_file(dirname(__DIR__).'/vendor/autoload.php')&&extension_loaded('openssl')&&extension_loaded('curl')&&extension_loaded('mbstring');}
function push_keys():array{
 if(!push_ready())throw new RuntimeException('Push notifications need PHP OpenSSL, cURL, and Mbstring enabled.',503);
 require_once dirname(__DIR__).'/vendor/autoload.php';
 start_transaction();try{
  query('SELECT id FROM sbs_admin WHERE id=1'.(db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''))->fetch();
  $row=query('SELECT * FROM sbs_push_keys WHERE id=1')->fetch();
  if(!$row){$keys=\Minishlink\WebPush\VAPID::createVapidKeys();query('INSERT INTO sbs_push_keys (id,public_key,private_key) VALUES (1,?,?)',[$keys['publicKey'],$keys['privateKey']]);$row=['public_key'=>$keys['publicKey'],'private_key'=>$keys['privateKey']];}
  commit_transaction();return $row;
 }catch(Throwable $e){rollback_transaction();throw $e;}
}
function valid_push_subscription(array $s):bool{
 $url=parse_url($s['endpoint']??'');$host=strtolower($url['host']??'');
 if(($url['scheme']??'')!=='https'||isset($url['port'])||isset($url['user'])||isset($url['pass'])||strlen($s['endpoint']??'')>2048)return false;
 $allowed=$host==='fcm.googleapis.com'||$host==='updates.push.services.mozilla.com'||str_ends_with($host,'.push.apple.com')||str_ends_with($host,'.push.services.mozilla.com');
 if(!$allowed)return false;foreach(['p256dh'=>65,'auth'=>16] as $key=>$length){$value=$s['keys'][$key]??'';if(!is_string($value)||!preg_match('/^[A-Za-z0-9_-]+={0,2}$/',$value)||strlen(base64_decode(strtr($value,'-_','+/'),true)?:'')!==$length)return false;}return true;
}
function send_credit_push(string $game,bool $test=false,?string $deviceId=null):array{
 $rows=$deviceId?query('SELECT * FROM sbs_push_devices WHERE id=?',[$deviceId])->fetchAll():query('SELECT * FROM sbs_push_devices ORDER BY created_at DESC LIMIT 10')->fetchAll();
 if(!$rows)return ['sent'=>0,'failed'=>0];
 $keys=push_keys();$webPush=new \Minishlink\WebPush\WebPush(['VAPID'=>['subject'=>config()['site_url'],'publicKey'=>$keys['public_key'],'privateKey'=>$keys['private_key']]],['TTL'=>3600,'urgency'=>'normal'],10);
 $payload=json_encode(['title'=>$test?'Football Pool notifications enabled':'New credit request','body'=>$test?'This device can receive organizer alerts.':'A player requested more picking credits. Tap to review.','url'=>'/sbs/?game='.rawurlencode($game).'&admin=1&requests=1','badge'=>count(pending_credit_requests())],JSON_THROW_ON_ERROR);
 foreach($rows as $row)$webPush->queueNotification(\Minishlink\WebPush\Subscription::create(json_decode($row['subscription'],true)),$payload);
 $sent=0;$failed=0;foreach($webPush->flush() as $report){if($report->isSuccess())$sent++;else{$failed++;if($report->isSubscriptionExpired())query('DELETE FROM sbs_push_devices WHERE id=?',[hash('sha256',(string)$report->getRequest()->getUri())]);}}
 return ['sent'=>$sent,'failed'=>$failed];
}
