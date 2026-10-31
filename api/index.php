<?php
declare(strict_types=1);
require_once __DIR__.'/domain.php';require_once __DIR__.'/store.php';
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: same-origin');
function respond(array $body,int $status=200):never{http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode($body,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);exit;}
function request_json():array{if(stripos($_SERVER['CONTENT_TYPE']??'','application/json')!==0)throw new RuntimeException('JSON request required.',415);$raw=file_get_contents('php://input',false,null,0,20001);if(strlen($raw)>20000)throw new RuntimeException('Request too large.',413);$body=json_decode($raw,true,512,JSON_THROW_ON_ERROR);if(!is_array($body))throw new RuntimeException('Invalid request.');return $body;}
try{
 $path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);$route=preg_replace('~^/sbs/api/~','',$path);$method=$_SERVER['REQUEST_METHOD'];
 if($method!=='GET'&&$method!=='POST')throw new RuntimeException('Method not allowed.',405);
 if($method==='POST')origin_guard();
 if($route==='auth'){
  if($method==='GET')respond(['configured'=>(bool)query('SELECT id FROM sbs_admin WHERE id=1')->fetch(),'signedIn'=>(bool)current_user()]);
  $b=request_json();$action=$b['action']??'';
  if($action==='logout'){if(isset($_COOKIE['sbs_session']))query('DELETE FROM sbs_sessions WHERE token_hash=?',[hash('sha256',$_COOKIE['sbs_session'])]);set_session_cookie('',time()-3600);respond(['ok'=>true]);}
  rate_limit();
  if($action==='setup'){
   $key=config()['setup_key']??'';if(strlen($key)<32||!hash_equals($key,txt($b['key']??'',200)))throw new RuntimeException('Invalid setup link.',403);
   $password=txt($b['password']??'',200);if(strlen($password)<12)throw new RuntimeException('Use an organizer password of at least 12 characters.');
   start_transaction();try{if(query('SELECT id FROM sbs_admin WHERE id=1')->fetch())throw new RuntimeException('Organizer setup is already complete.',409);query('INSERT INTO sbs_admin (id,password_hash) VALUES (1,?)',[password_hash($password,PASSWORD_DEFAULT)]);query('INSERT INTO sbs_pool (id,data,revision) VALUES (1,?,0)',[json_encode(fresh_pool(),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);commit_transaction();}catch(Throwable $e){rollback_transaction();throw $e;}
   create_session('admin');respond(['ok'=>true]);
  }
  if($action==='admin'){$row=query('SELECT password_hash FROM sbs_admin WHERE id=1')->fetch();$password=txt($b['password']??'',200);if(!$row||!password_verify($password,$row['password_hash']))throw new RuntimeException('Unable to sign in. Check your password.',401);create_session('admin');respond(['ok'=>true]);}
  if($action==='player'){
   $token=txt($b['token']??'',100);$number='';try{$number=phone(txt($b['phone']??'',40));}catch(Throwable $ignored){}
   $grant=query('SELECT * FROM sbs_invites WHERE token_hash=?',[hash('sha256',$token)])->fetch();
   if(!$grant||!preg_match('/^[A-Za-z0-9_-]{43}$/',$token)||!$number||!hash_equals($grant['phone'],$number)||(int)$grant['expires_at']<=time())throw new RuntimeException('Unable to sign in. Check your phone number or ask the organizer for a new invitation link.',401);
   create_session('player',$grant['player_id'],(int)$grant['version']);respond(['ok'=>true]);
  }
  throw new RuntimeException('Unknown sign-in action.');
 }
 $u=require_user();$record=load_pool();if(!$record)throw new RuntimeException('Organizer setup is required.',409);$p=$record['pool'];$admin=$u['role']==='admin';
 if($route==='pool'){
  if($method==='GET'){
   $me=null;foreach($p['players'] as $x)if($x['id']===$u['playerId'])$me=['id'=>$x['id'],'name'=>$x['name'],'balance'=>balance_for($p,$x['id'])];
   if(!$admin&&!$me)throw new RuntimeException('Invitation required.',403);
   $user=['name'=>$admin?'Shawn':$me['name'],'email'=>''];
   if(!$admin){unset($p['adminId']);$p['players']=array_map(fn($x)=>['id'=>$x['id'],'name'=>$x['name']],$p['players']);$p['ledger']=array_values(array_filter($p['ledger'],fn($e)=>$e['playerId']===$u['playerId']));foreach($p['ledger'] as &$entry)unset($entry['by']);unset($entry);}
   $p['squares']=(object)$p['squares'];respond(['pool'=>$p,'admin'=>$admin,'me'=>$me,'user'=>$user]);
  }
  $b=request_json();start_transaction();try{$record=load_pool(true);$u=require_user();$before=$record['pool'];$p=mutate_pool($before,$u,$b);
   if(($b['action']??'')==='phone')foreach($before['players'] as $old)if($old['id']===($b['playerId']??''))foreach($p['players'] as $new)if($new['id']===$old['id']&&($old['phone']??'')!==($new['phone']??'')){query('UPDATE sbs_invites SET version=version+1,token_hash=?,phone=?,expires_at=0 WHERE player_id=?',[hash('sha256',new_token()),$new['phone'],$new['id']]);query('DELETE FROM sbs_sessions WHERE player_id=?',[$new['id']]);}
   save_pool($p,$record['revision']);commit_transaction();}catch(Throwable $e){rollback_transaction();throw $e;}respond(['ok'=>true]);
 }
 if($route==='invite'&&$method==='POST'){
  require_user(true);$b=request_json();start_transaction();try{$record=load_pool(true);$player=null;foreach($record['pool']['players'] as $x)if($x['id']===($b['playerId']??''))$player=$x;if(!$player||empty($player['phone']))throw new RuntimeException('Add a phone number for this player first.');$old=query('SELECT version FROM sbs_invites WHERE player_id=?',[$player['id']])->fetch();$version=($old?(int)$old['version']:0)+1;$token=new_token();$expires=time()+30*86400;query('DELETE FROM sbs_invites WHERE player_id=?',[$player['id']]);query('INSERT INTO sbs_invites (player_id,token_hash,phone,version,expires_at) VALUES (?,?,?,?,?)',[$player['id'],hash('sha256',$token),$player['phone'],$version,$expires]);query('DELETE FROM sbs_sessions WHERE player_id=?',[$player['id']]);commit_transaction();}catch(Throwable $e){rollback_transaction();throw $e;}
  respond(['url'=>rtrim(config()['site_url'],'/').'/#invite='.rawurlencode($token),'expiresAt'=>gmdate('c',$expires)]);
 }
 if($route==='image'&&$method==='POST'){
  $bytes=file_get_contents('php://input',false,null,0,524289);if(strlen($bytes)>524288)throw new RuntimeException('Image must be under 512 KB.',413);$type=(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);if(!in_array($type,['image/png','image/jpeg','image/webp'],true)||!@getimagesizefromstring($bytes))throw new RuntimeException('Choose a valid PNG, JPEG, or WebP image.');$id=uuid();$s=db()->prepare('INSERT INTO sbs_images (id,content_type,body) VALUES (?,?,?)');$s->bindValue(1,$id);$s->bindValue(2,$type);$s->bindValue(3,$bytes,PDO::PARAM_LOB);$s->execute();respond(['url'=>'/api/image/'.$id]);
 }
 if(preg_match('~^image/([a-f0-9-]{36})$~',$route,$m)&&$method==='GET'){
  $image=query('SELECT content_type,body FROM sbs_images WHERE id=?',[$m[1]])->fetch();if(!$image)throw new RuntimeException('Image not found.',404);header('Content-Type: '.$image['content_type']);header('Cache-Control: private, max-age=3600');if(is_resource($image['body']))fpassthru($image['body']);else echo $image['body'];exit;
 }
 throw new RuntimeException('Not found.',404);
}catch(Throwable $e){rollback_transaction();$status=$e->getCode();if($e instanceof PDOException){error_log('SBS database operation failed: '.$e->getMessage());respond(['error'=>'The pool database is unavailable. Contact the organizer.'],503);}if(!is_int($status)||$status<400||$status>599)$status=400;respond(['error'=>$e instanceof JsonException?'Invalid request.':$e->getMessage()],$status);}
