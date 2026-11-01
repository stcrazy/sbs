<?php
declare(strict_types=1);
require_once __DIR__.'/domain.php';require_once __DIR__.'/store.php';require_once __DIR__.'/push.php';
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');
function respond(array $body,int $status=200):never{http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode($body,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);exit;}
function request_json():array{if(stripos($_SERVER['CONTENT_TYPE']??'','application/json')!==0)throw new RuntimeException('JSON request required.',415);$raw=file_get_contents('php://input',false,null,0,20001);if(strlen($raw)>20000)throw new RuntimeException('Request too large.',413);$body=json_decode($raw,true,512,JSON_THROW_ON_ERROR);if(!is_array($body))throw new RuntimeException('Invalid request.');return $body;}
try{
 $path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);$route=preg_replace('~^/sbs/api/~','',$path);$method=$_SERVER['REQUEST_METHOD'];$game=game_id();
 if($method!=='GET'&&$method!=='POST')throw new RuntimeException('Method not allowed.',405);
 if($method==='POST')origin_guard();
 if($route==='auth'){
  if($method==='GET')respond(['configured'=>(bool)query('SELECT id FROM sbs_admin WHERE id=1')->fetch(),'signedIn'=>(bool)current_user($game),'pushAvailable'=>push_ready()]);
  $b=request_json();$action=$b['action']??'';
  if($action==='logout'){foreach($_COOKIE as $key=>$cookie){if($key==='sbs_session'){query('DELETE FROM sbs_sessions WHERE token_hash=?',[hash('sha256',$cookie)]);query('DELETE FROM sbs_game_sessions WHERE token_hash=?',[hash('sha256',$cookie)]);set_session_cookie('',time()-3600);}elseif(preg_match('/^sbs_game_(\d{4}_\d{2})$/',$key,$match)){query('DELETE FROM sbs_game_sessions WHERE token_hash=?',[hash('sha256',$cookie)]);set_session_cookie('',time()-3600,$match[1]);}}respond(['ok'=>true]);}
  rate_limit();
  if($action==='setup'){
   $key=config()['setup_key']??'';if(strlen($key)<32||!hash_equals($key,txt($b['key']??'',200)))throw new RuntimeException('Invalid setup link.',403);
   $password=txt($b['password']??'',200);if(strlen($password)<12)throw new RuntimeException('Use an organizer password of at least 12 characters.');
   start_transaction();try{if(query('SELECT id FROM sbs_admin WHERE id=1')->fetch())throw new RuntimeException('Organizer setup is already complete.',409);query('INSERT INTO sbs_admin (id,password_hash) VALUES (1,?)',[password_hash($password,PASSWORD_DEFAULT)]);$initial=fresh_pool();query('INSERT INTO sbs_games (id,name,game_date,archived,data,revision,created_at) VALUES (?,?,?,0,?,0,?)',['2026_01',$initial['settings']['title'],'',json_encode($initial,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),time()]);commit_transaction();}catch(Throwable $e){rollback_transaction();throw $e;}
   create_session('admin');respond(['ok'=>true]);
  }
  if($action==='admin'){$row=query('SELECT password_hash FROM sbs_admin WHERE id=1')->fetch();$password=txt($b['password']??'',200);if(!$row||!password_verify($password,$row['password_hash']))throw new RuntimeException('Unable to sign in. Check your password.',401);create_session('admin');respond(['ok'=>true]);}
  if($action==='player'){
   $number='';try{$number=phone(txt($b['phone']??'',40));}catch(Throwable $ignored){}
   start_transaction();try{
    $record=load_pool(true,$game);$contact=null;if($record&&$number)foreach($record['pool']['players'] as $person)if(($person['phone']??'')===$number)$contact=$person;
    if(!$contact)throw new RuntimeException('That phone number is not on this game’s player list. Check the number or ask Shawn to add you.',401);
    $grant=query('SELECT * FROM sbs_game_invites WHERE game_id=? AND player_id=?',[$game,$contact['id']])->fetch();
    if(!$grant){$grant=['player_id'=>$contact['id'],'version'=>1];query('INSERT INTO sbs_game_invites (game_id,player_id,token_hash,phone,version,expires_at) VALUES (?,?,?,?,?,?)',[$game,$contact['id'],hash('sha256',new_token()),$number,1,0]);}
    commit_transaction();
   }catch(Throwable $e){rollback_transaction();throw $e;}
   // A deliberate player sign-in exits organizer access on this device.
   if(!empty($_COOKIE['sbs_session'])){query('DELETE FROM sbs_sessions WHERE token_hash=?',[hash('sha256',$_COOKIE['sbs_session'])]);set_session_cookie('',time()-3600);}
   create_session('player',$grant['player_id'],(int)$grant['version'],$game);respond(['ok'=>true]);
  }
  throw new RuntimeException('Unknown sign-in action.');
 }
 if($route==='games'){
  $owner=current_user();$admin=$owner&&$owner['role']==='admin';
  if($method==='GET'){$games=[];$rows=query('SELECT * FROM sbs_games ORDER BY created_at DESC,id DESC')->fetchAll();foreach($rows as $row){if(!$admin&&!current_user($row['id']))continue;$state=json_decode($row['data'],true,512,JSON_THROW_ON_ERROR);$games[]=['id'=>$row['id'],'name'=>$row['name'],'date'=>$row['game_date'],'archived'=>(bool)$row['archived'],'picked'=>count($state['squares']),'players'=>count($state['players']),'price'=>$state['settings']['price']];}respond(['games'=>$games,'admin'=>(bool)$admin,'signedIn'=>(bool)($admin||count($games))]);}
  require_user(true);$b=request_json();start_transaction();try{
   query('SELECT id FROM sbs_admin WHERE id=1'.(db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''))->fetch();
   if(($b['action']??'')==='create'){
    $name=txt($b['name']??'',70);if(!$name)throw new RuntimeException('Enter a game name.');$year=whole($b['year']??null,2000,2100);$date=txt($b['date']??'',10);if($date!==''&&(!($dt=DateTimeImmutable::createFromFormat('!Y-m-d',$date))||$dt->format('Y-m-d')!==$date))throw new RuntimeException('Enter a valid game date.');
    $ids=query('SELECT id FROM sbs_games WHERE SUBSTR(id,1,4)=?',[(string)$year])->fetchAll();$next=1;foreach($ids as $row)$next=max($next,((int)substr($row['id'],5))+1);if($next>99)throw new RuntimeException('This year already has 99 game codes.');$id=sprintf('%04d_%02d',$year,$next);$state=fresh_pool();$state['settings']['title']=$name;
    if(!empty($b['copyFrom'])){$source=load_pool(false,game_id(txt($b['copyFrom'],7)));if(!$source)throw new RuntimeException('Contact source game not found.');foreach($source['pool']['players'] as $contact)$state['players'][]=['id'=>uuid(),'name'=>$contact['name'],'phone'=>$contact['phone']??'','email'=>$contact['email']??''];}
    query('INSERT INTO sbs_games (id,name,game_date,archived,data,revision,created_at) VALUES (?,?,?,0,?,0,?)',[$id,$name,$date,json_encode($state,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),time()]);commit_transaction();respond(['ok'=>true,'id'=>$id]);
   }
   if(($b['action']??'')==='archive'){$id=game_id(txt($b['id']??'',7));if(!is_bool($b['archived']??null))throw new RuntimeException('Invalid archive choice.');if(!load_pool(true,$id))throw new RuntimeException('Game not found.',404);query('UPDATE sbs_games SET archived=? WHERE id=?',[$b['archived']?1:0,$id]);commit_transaction();respond(['ok'=>true]);}
   throw new RuntimeException('Unknown game action.');
  }catch(Throwable $e){rollback_transaction();throw $e;}
 }
 if($route==='requests'&&$method==='GET'){require_user(true);$pending=pending_credit_requests();respond(['count'=>count($pending),'requests'=>$pending]);}
 if($route==='push'){
  require_user(true);
  if($method==='GET'){if(!push_ready())respond(['available'=>false,'reason'=>'Enable PHP OpenSSL, cURL, and Mbstring in Hostinger.']);$keys=push_keys();respond(['available'=>true,'publicKey'=>$keys['public_key']]);}
  $b=request_json();$action=$b['action']??'';
  if($action==='subscribe'){
   $subscription=$b['subscription']??[];if(!is_array($subscription)||!valid_push_subscription($subscription))throw new RuntimeException('Unsupported push subscription.');
   $id=hash('sha256',$subscription['endpoint']);start_transaction();try{
    query('SELECT id FROM sbs_admin WHERE id=1'.(db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''))->fetch();
    if(!query('SELECT id FROM sbs_push_devices WHERE id=?',[$id])->fetch()&&(int)query('SELECT COUNT(*) FROM sbs_push_devices')->fetchColumn()>=10)throw new RuntimeException('At most 10 notification devices are supported.');
    query('DELETE FROM sbs_push_devices WHERE id=?',[$id]);query('INSERT INTO sbs_push_devices (id,subscription,created_at) VALUES (?,?,?)',[$id,json_encode($subscription,JSON_THROW_ON_ERROR),time()]);commit_transaction();
   }catch(Throwable $e){rollback_transaction();throw $e;}respond(['ok'=>true]);
  }
  $endpoint=txt($b['endpoint']??'',2048);$id=hash('sha256',$endpoint);
  if($action==='unsubscribe'){query('DELETE FROM sbs_push_devices WHERE id=?',[$id]);respond(['ok'=>true]);}
  if($action==='test'){respond(send_credit_push($game,true,$id));}
  throw new RuntimeException('Unknown notification action.');
 }
 $u=require_user(false,$game);$record=load_pool(false,$game);if(!$record)throw new RuntimeException('Game not found.',404);$p=$record['pool'];$admin=$u['role']==='admin';
 if($route==='pool'){
  if($method==='GET'){
   $me=null;foreach($p['players'] as $x)if($x['id']===$u['playerId'])$me=['id'=>$x['id'],'name'=>$x['name'],'balance'=>balance_for($p,$x['id'])];
   if(!$admin&&!$me)throw new RuntimeException('Invitation required.',403);
   $user=['name'=>$admin?'Shawn':$me['name'],'email'=>''];
   if(!$admin){$p['creditRequests']=array_values(array_filter($p['creditRequests']??[],fn($r)=>$r['playerId']===$u['playerId']));unset($p['adminId']);$p['players']=array_map(fn($x)=>['id'=>$x['id'],'name'=>$x['name']],$p['players']);$p['ledger']=array_values(array_filter($p['ledger'],fn($e)=>$e['playerId']===$u['playerId']));foreach($p['ledger'] as &$entry)unset($entry['by']);unset($entry);}
   $p['squares']=(object)$p['squares'];respond(['pool'=>$p,'game'=>$record['game'],'admin'=>$admin,'me'=>$me,'user'=>$user]);
  }
  $b=request_json();start_transaction();try{$record=load_pool(true,$game);$u=require_user(false,$game);if($record['game']['archived'])throw new RuntimeException('This game is archived. Restore it from Games to make changes.',409);$before=$record['pool'];if(in_array($b['action']??'',['buy','personalize'],true)&&!empty($b['image'])){if(!query('SELECT id FROM sbs_game_images WHERE game_id=? AND id=?',[$game,basename((string)$b['image'])])->fetch())throw new RuntimeException('Choose an image uploaded for this game.');}$p=mutate_pool($before,$u,$b);
   if(($b['action']??'')==='phone')foreach($before['players'] as $old)if($old['id']===($b['playerId']??''))foreach($p['players'] as $new)if($new['id']===$old['id']&&($old['phone']??'')!==($new['phone']??'')){query('UPDATE sbs_game_invites SET version=version+1,token_hash=?,phone=?,expires_at=0 WHERE game_id=? AND player_id=?',[hash('sha256',new_token()),$new['phone'],$game,$new['id']]);query('DELETE FROM sbs_game_sessions WHERE game_id=? AND player_id=?',[$game,$new['id']]);}
   save_pool($p,$record['revision'],$game);commit_transaction();}catch(Throwable $e){rollback_transaction();throw $e;}if(($b['action']??'')==='requestCredits'){try{send_credit_push($game);}catch(Throwable $pushError){error_log('SBS push delivery failed; request remains pending.');}}respond(['ok'=>true]);
 }
 if($route==='invite'&&$method==='POST'){
  require_user(true,$game);$b=request_json();start_transaction();try{
   $record=load_pool(true,$game);if($record['game']['archived'])throw new RuntimeException('This game is archived.',409);
   $player=null;foreach($record['pool']['players'] as $x)if($x['id']===($b['playerId']??''))$player=$x;
   if(!$player||empty($player['phone']))throw new RuntimeException('Add a phone number for this player first.');
   $updated=grant_invite_credits($record['pool'],$player['id'],$b['credits']??5);save_pool($updated,$record['revision'],$game);commit_transaction();
  }catch(Throwable $e){rollback_transaction();throw $e;}
  respond(['url'=>rtrim(config()['site_url'],'/').'/?game='.rawurlencode($game).'&player=1','balance'=>balance_for($updated,$player['id'])]);
 }
 if($route==='image'&&$method==='POST'){
  if($record['game']['archived'])throw new RuntimeException('This game is archived.',409);$bytes=file_get_contents('php://input',false,null,0,524289);if(strlen($bytes)>524288)throw new RuntimeException('Image must be under 512 KB.',413);$type=(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);if(!in_array($type,['image/png','image/jpeg','image/webp'],true)||!@getimagesizefromstring($bytes))throw new RuntimeException('Choose a valid PNG, JPEG, or WebP image.');$id=uuid();$s=db()->prepare('INSERT INTO sbs_game_images (game_id,id,content_type,body) VALUES (?,?,?,?)');$s->bindValue(1,$game);$s->bindValue(2,$id);$s->bindValue(3,$type);$s->bindValue(4,$bytes,PDO::PARAM_LOB);$s->execute();respond(['url'=>'/api/image/'.$id]);
 }
 if(preg_match('~^image/([a-f0-9-]{36})$~',$route,$m)&&$method==='GET'){
  $image=query('SELECT content_type,body FROM sbs_game_images WHERE game_id=? AND id=?',[$game,$m[1]])->fetch();if(!$image)throw new RuntimeException('Image not found.',404);header('Content-Type: '.$image['content_type']);header('Cache-Control: private, max-age=3600');if(is_resource($image['body']))fpassthru($image['body']);else echo $image['body'];exit;
 }
 throw new RuntimeException('Not found.',404);
}catch(Throwable $e){rollback_transaction();$status=$e->getCode();if($e instanceof PDOException){error_log('SBS database operation failed: '.$e->getMessage());respond(['error'=>'The pool database is unavailable. Contact the organizer.'],503);}if(!is_int($status)||$status<400||$status>599)$status=400;respond(['error'=>$e instanceof JsonException?'Invalid request.':$e->getMessage()],$status);}
