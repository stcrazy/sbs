<?php
declare(strict_types=1);
function config():array{
 static $config=null;if($config!==null)return $config;
 $path=getenv('SBS_CONFIG_PATH')?:dirname(__DIR__,3).'/.sbs-config.php';
 if(!is_file($path))throw new RuntimeException('The organizer needs to configure hosting before the pool can open.',503);
 $config=require $path;if(!is_array($config)||empty($config['db_dsn'])||empty($config['site_url']))throw new RuntimeException('Hosting configuration is incomplete.',503);
 $url=parse_url($config['site_url']);if(!$url||empty($url['host'])||(($url['scheme']??'')!=='https'&&!in_array($url['host'],['127.0.0.1','localhost'],true)))throw new RuntimeException('Configure the pool with an HTTPS website address.',503);
 return $config;
}
function db():PDO{
 static $db=null;if($db)return $db;$c=config();$db=new PDO($c['db_dsn'],$c['db_user']??null,$c['db_password']??null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 if($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$db->setAttribute(PDO::ATTR_EMULATE_PREPARES,false);
 else{$db->exec('PRAGMA busy_timeout=10000');$db->exec('PRAGMA journal_mode=WAL');}
 foreach([
  'CREATE TABLE IF NOT EXISTS sbs_pool (id INTEGER PRIMARY KEY, data LONGTEXT NOT NULL, revision INTEGER NOT NULL DEFAULT 0)',
  'CREATE TABLE IF NOT EXISTS sbs_admin (id INTEGER PRIMARY KEY, password_hash VARCHAR(255) NOT NULL)',
  'CREATE TABLE IF NOT EXISTS sbs_invites (player_id VARCHAR(36) PRIMARY KEY, token_hash VARCHAR(64) NOT NULL UNIQUE, phone VARCHAR(20) NOT NULL, version INTEGER NOT NULL, expires_at BIGINT NOT NULL)',
  'CREATE TABLE IF NOT EXISTS sbs_sessions (token_hash VARCHAR(64) PRIMARY KEY, role VARCHAR(10) NOT NULL, player_id VARCHAR(36), invite_version INTEGER, expires_at BIGINT NOT NULL)',
  'CREATE TABLE IF NOT EXISTS sbs_rate (bucket VARCHAR(64) PRIMARY KEY, hits INTEGER NOT NULL, reset_at BIGINT NOT NULL)',
  'CREATE TABLE IF NOT EXISTS sbs_images (id VARCHAR(36) PRIMARY KEY, content_type VARCHAR(30) NOT NULL, body MEDIUMBLOB NOT NULL)',
  'CREATE TABLE IF NOT EXISTS sbs_games (id VARCHAR(7) PRIMARY KEY, name VARCHAR(70) NOT NULL, game_date VARCHAR(10) NOT NULL, archived INTEGER NOT NULL DEFAULT 0, data LONGTEXT NOT NULL, revision INTEGER NOT NULL DEFAULT 0, created_at BIGINT NOT NULL)',
  'CREATE TABLE IF NOT EXISTS sbs_game_invites (game_id VARCHAR(7) NOT NULL, player_id VARCHAR(36) NOT NULL, token_hash VARCHAR(64) NOT NULL UNIQUE, phone VARCHAR(20) NOT NULL, version INTEGER NOT NULL, expires_at BIGINT NOT NULL, PRIMARY KEY (game_id,player_id))',
  'CREATE TABLE IF NOT EXISTS sbs_game_sessions (token_hash VARCHAR(64) PRIMARY KEY, game_id VARCHAR(7) NOT NULL, player_id VARCHAR(36) NOT NULL, invite_version INTEGER NOT NULL, expires_at BIGINT NOT NULL)',
  'CREATE TABLE IF NOT EXISTS sbs_game_images (game_id VARCHAR(7) NOT NULL, id VARCHAR(36) NOT NULL, content_type VARCHAR(30) NOT NULL, body MEDIUMBLOB NOT NULL, PRIMARY KEY (game_id,id))',
  'CREATE TABLE IF NOT EXISTS sbs_migrations (id VARCHAR(40) PRIMARY KEY)'
 ] as $sql)$db->exec($sql.($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4':''));
 migrate_original_game();return $db;
}
function query(string $sql,array $params=[]):PDOStatement{$s=db()->prepare($sql);$s->execute($params);return $s;}
function start_transaction():void{if(db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')db()->exec('BEGIN IMMEDIATE');else db()->beginTransaction();}
function commit_transaction():void{if(db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')db()->exec('COMMIT');else db()->commit();}
function rollback_transaction():void{try{if(db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')db()->exec('ROLLBACK');elseif(db()->inTransaction())db()->rollBack();}catch(Throwable $ignored){}}
function game_id(?string $id=null):string{$id=$id??($_GET['game']??'2026_01');if(!preg_match('/^\d{4}_\d{2}$/',$id)||substr($id,5)==='00')throw new RuntimeException('Invalid game code.',400);return $id;}
function load_pool(bool $lock=false,?string $game=null):?array{$game=game_id($game);$sql='SELECT * FROM sbs_games WHERE id=?';if($lock&&db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';$row=query($sql,[$game])->fetch();return $row?['pool'=>json_decode($row['data'],true,512,JSON_THROW_ON_ERROR),'revision'=>(int)$row['revision'],'game'=>['id'=>$row['id'],'name'=>$row['name'],'date'=>$row['game_date'],'archived'=>(bool)$row['archived']]]:null;}
function save_pool(array $p,int $revision,?string $game=null):void{$s=query('UPDATE sbs_games SET data=?,name=?,revision=revision+1 WHERE id=? AND revision=?',[json_encode($p,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$p['settings']['title'],game_id($game),$revision]);if($s->rowCount()!==1)throw new RuntimeException('The board changed. Please try again.',409);}
function new_token():string{return rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');}
function set_session_cookie(string $token,int $expires,?string $game=null):void{$secure=parse_url(config()['site_url'],PHP_URL_SCHEME)==='https';setcookie($game?'sbs_game_'.game_id($game):'sbs_session',$token,['expires'=>$expires,'path'=>'/sbs/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);}
function create_session(string $role,?string $player=null,?int $version=null,?string $game=null):void{$token=new_token();$expires=time()+14*86400;if($role==='admin')query('INSERT INTO sbs_sessions (token_hash,role,player_id,invite_version,expires_at) VALUES (?,?,?,?,?)',[hash('sha256',$token),$role,null,null,$expires]);else query('INSERT INTO sbs_game_sessions (token_hash,game_id,player_id,invite_version,expires_at) VALUES (?,?,?,?,?)',[hash('sha256',$token),game_id($game),$player,$version,$expires]);set_session_cookie($token,$expires,$role==='admin'?null:game_id($game));}
function current_user(?string $game=null):?array{
 $cookie=$_COOKIE['sbs_session']??'';
 if(preg_match('/^[A-Za-z0-9_-]{43}$/',$cookie)){$s=query('SELECT * FROM sbs_sessions WHERE token_hash=?',[hash('sha256',$cookie)])->fetch();if($s&&$s['role']==='admin'&&(int)$s['expires_at']>time())return ['role'=>'admin','playerId'=>null];}
 if($game===null)return null;$game=game_id($game);$cookie=$_COOKIE['sbs_game_'.$game]??($game==='2026_01'?($_COOKIE['sbs_session']??''):'');if(!preg_match('/^[A-Za-z0-9_-]{43}$/',$cookie))return null;
 $s=query('SELECT * FROM sbs_game_sessions WHERE token_hash=? AND game_id=?',[hash('sha256',$cookie),$game])->fetch();if(!$s||(int)$s['expires_at']<=time())return null;$grant=query('SELECT version FROM sbs_game_invites WHERE game_id=? AND player_id=?',[$game,$s['player_id']])->fetch();if(!$grant||(int)$grant['version']!==(int)$s['invite_version'])return null;return ['role'=>'player','playerId'=>$s['player_id'],'gameId'=>$game];
}
function require_user(bool $admin=false,?string $game=null):array{$u=current_user($game);if(!$u)throw new RuntimeException('Open your private invitation link and enter your phone number to sign in.',401);if($admin&&$u['role']!=='admin')throw new RuntimeException('Organizer access required.',403);return $u;}
function migrate_original_game():void{
 if(query('SELECT id FROM sbs_migrations WHERE id=?',['multiple_games_v1'])->fetch())return;
 start_transaction();try{
  if(query('SELECT id FROM sbs_migrations WHERE id=?',['multiple_games_v1'])->fetch()){commit_transaction();return;}
  $row=query('SELECT data,revision FROM sbs_pool WHERE id=1')->fetch();
  if($row&&!query('SELECT id FROM sbs_games WHERE id=?',['2026_01'])->fetch()){
   $p=json_decode($row['data'],true,512,JSON_THROW_ON_ERROR);query('INSERT INTO sbs_games (id,name,game_date,archived,data,revision,created_at) VALUES (?,?,?,0,?,?,?)',['2026_01',$p['settings']['title'],'',$row['data'],$row['revision'],time()]);
   query('INSERT INTO sbs_game_invites (game_id,player_id,token_hash,phone,version,expires_at) SELECT ?,player_id,token_hash,phone,version,expires_at FROM sbs_invites',['2026_01']);
   query('INSERT INTO sbs_game_sessions (token_hash,game_id,player_id,invite_version,expires_at) SELECT token_hash,?,player_id,invite_version,expires_at FROM sbs_sessions WHERE role=?',['2026_01','player']);
   query('INSERT INTO sbs_game_images (game_id,id,content_type,body) SELECT ?,id,content_type,body FROM sbs_images',['2026_01']);
  }
  query('INSERT INTO sbs_migrations (id) VALUES (?)',['multiple_games_v1']);commit_transaction();
 }catch(Throwable $e){rollback_transaction();if(!query('SELECT id FROM sbs_migrations WHERE id=?',['multiple_games_v1'])->fetch())throw $e;}
}
function origin_guard():void{$c=config();$url=parse_url($c['site_url']);$expected=$url['scheme'].'://'.$url['host'].(isset($url['port'])?':'.$url['port']:'');if(($_SERVER['HTTP_ORIGIN']??'')!==$expected)throw new RuntimeException('Invalid request origin.',403);}
function rate_limit():void{
 $ip=$_SERVER['REMOTE_ADDR']??'unknown';$bucket=hash('sha256','login:'.$ip);$now=time();start_transaction();try{$row=query('SELECT hits,reset_at FROM sbs_rate WHERE bucket=?'.(db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''),[$bucket])->fetch();if(!$row){query('INSERT INTO sbs_rate (bucket,hits,reset_at) VALUES (?,1,?)',[$bucket,$now+900]);}elseif((int)$row['reset_at']<=$now){query('UPDATE sbs_rate SET hits=1,reset_at=? WHERE bucket=?',[$now+900,$bucket]);}elseif((int)$row['hits']>=10){throw new RuntimeException('Too many sign-in attempts. Please wait 15 minutes.',429);}else query('UPDATE sbs_rate SET hits=hits+1 WHERE bucket=?',[$bucket]);commit_transaction();}catch(Throwable $e){rollback_transaction();throw $e;}
}
