<?php
require dirname(__DIR__).'/api/domain.php';
$dir=dirname(__DIR__).'/.local/migration-'.uuid();mkdir($dir,0700,true);
$config=['site_url'=>'http://127.0.0.1:8091/sbs/','db_dsn'=>'sqlite:'.$dir.'/fixture.sqlite'];file_put_contents($dir.'/config.php','<?php return '.var_export($config,true).';');putenv('SBS_CONFIG_PATH='.$dir.'/config.php');
$old=new PDO($config['db_dsn']);$old->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$old->exec('CREATE TABLE sbs_pool (id INTEGER PRIMARY KEY,data TEXT NOT NULL,revision INTEGER NOT NULL); CREATE TABLE sbs_invites (player_id TEXT PRIMARY KEY,token_hash TEXT,phone TEXT,version INTEGER,expires_at BIGINT); CREATE TABLE sbs_sessions (token_hash TEXT PRIMARY KEY,role TEXT,player_id TEXT,invite_version INTEGER,expires_at BIGINT); CREATE TABLE sbs_images (id TEXT PRIMARY KEY,content_type TEXT,body BLOB)');
$p=fresh_pool();$pid=uuid();$p['players'][]=['id'=>$pid,'name'=>'Existing Player','phone'=>'+14155550123','email'=>''];$p['ledger'][]=['id'=>uuid(),'playerId'=>$pid,'credits'=>2,'dollars'=>50,'note'=>'Existing payment','at'=>gmdate('c'),'by'=>'organizer'];$p['squares'][42]=['playerId'=>$pid,'label'=>'Keep me','image'=>'','at'=>gmdate('c')];
$s=$old->prepare('INSERT INTO sbs_pool VALUES (1,?,7)');$s->execute([json_encode($p)]);$cookie=str_repeat('A',43);$s=$old->prepare('INSERT INTO sbs_invites VALUES (?,?,?,?,?)');$s->execute([$pid,hash('sha256','private-invite'),'+14155550123',3,time()+86400]);$s=$old->prepare('INSERT INTO sbs_sessions VALUES (?,?,?,?,?)');$s->execute([hash('sha256',$cookie),'player',$pid,3,time()+86400]);$old->exec("INSERT INTO sbs_images VALUES ('old-logo','image/png','preserved-image-bytes')");
require dirname(__DIR__).'/api/store.php';$record=load_pool(false,'2026_01');
function expect_migration(bool $v,string $label):void{if(!$v)throw new RuntimeException('FAILED: '.$label);echo 'PASS: '.$label.PHP_EOL;}
expect_migration($record['pool']===$p&&$record['revision']===7,'existing board, roster, ledger, and revision preserved');
expect_migration(query('SELECT body FROM sbs_game_images WHERE game_id=?',['2026_01'])->fetchColumn()==='preserved-image-bytes','existing images preserved under original game');
$_COOKIE['sbs_session']=$cookie;expect_migration(current_user('2026_01')['playerId']===$pid,'existing player cookie remains valid for original game');expect_migration(current_user('2026_02')===null,'legacy session cannot open another game');
expect_migration((int)query('SELECT COUNT(*) FROM sbs_pool')->fetchColumn()===1,'legacy source records remain intact');migrate_original_game();expect_migration((int)query('SELECT COUNT(*) FROM sbs_games')->fetchColumn()===1,'migration is idempotent');
echo 'All migration checks passed.'.PHP_EOL;
