<?php
require __DIR__.'/../api/domain.php';
function check($ok,$name){if(!$ok)throw new RuntimeException($name);echo "PASS: $name\n";}
$admin=['role'=>'admin'];$p=fresh_pool();
foreach(['classic','stadium','clean','retro'] as $theme){$p=mutate_pool($p,$admin,['action'=>'theme','theme'=>$theme]);check($p['settings']['theme']===$theme,"saves $theme");}
try{mutate_pool($p,$admin,['action'=>'theme','theme'=>'invalid']);throw new Exception('accepted invalid style');}catch(RuntimeException $e){echo "PASS: invalid style denied\n";}
try{mutate_pool($p,['role'=>'player','playerId'=>'other'],['action'=>'theme','theme'=>'classic']);throw new Exception('player changed style');}catch(RuntimeException $e){echo "PASS: player cannot change style\n";}
$s=$p['settings'];$s['priceCents']=2500;unset($s['theme']);
$p=mutate_pool($p,$admin,['action'=>'settings','settings'=>$s]);
check($p['settings']['theme']==='retro','setup save preserves style');
check(fresh_pool()['settings']['theme']==='classic','another game starts independently');
