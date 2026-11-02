<?php
declare(strict_types=1);
function uuid():string{$b=random_bytes(16);$b[6]=chr((ord($b[6])&15)|64);$b[8]=chr((ord($b[8])&63)|128);$h=bin2hex($b);return substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20);}
function txt(mixed $v,int $max=200):string{if(!is_string($v)||preg_match_all('/./us',$v)>$max)throw new RuntimeException('Invalid text.');return trim($v);}
function whole(mixed $v,int $min,int $max):int{if(!is_int($v)||$v<$min||$v>$max)throw new RuntimeException('Invalid number.');return $v;}
function phone(string $v):string{$v=trim($v);if($v==='')return '';if(strlen($v)>40||!preg_match('/^[+\d\s().-]+$/',$v))throw new RuntimeException('Enter a valid phone number.');$v=preg_replace('/[\s().-]/','',$v);if(preg_match('/^\d{10}$/',$v))$v='+1'.$v;elseif(preg_match('/^1\d{10}$/',$v))$v='+'.$v;if(!preg_match('/^\+[1-9]\d{7,14}$/',$v))throw new RuntimeException('Enter a 10-digit U.S. phone number, or include + and the country code.');return $v;}
function asset(mixed $v):string{$v=txt($v,100);if($v!==''&&!preg_match('~^/api/image/[a-f0-9-]{36}$~',$v))throw new RuntimeException('Invalid image.');return $v;}
function fresh_pool():array{return ['adminId'=>'organizer','settings'=>['theme'=>'classic','title'=>'Shawn’s Football Pool','price'=>25,'rowTeam'=>'NFC','colTeam'=>'AFC','rowLogo'=>'','colLogo'=>'','cornerLogo'=>'','venmo'=>'','payouts'=>[25,25,25,25],'rules'=>'Each square costs 1 credit. Numbers are assigned after all 100 squares are filled. Match the last digit of each team’s score at Q1, Q2, Q3, and the final score (including overtime) to find the winner. Payouts: 25% each. Picking credits are provided by the organizer. You only owe for squares picked; unused credits cost nothing.'],'players'=>[],'ledger'=>[],'squares'=>[],'notices'=>[],'rows'=>[],'cols'=>[],'scores'=>[]];}
function balance_for(array $p,string $id):int{return array_reduce($p['ledger'],fn($sum,$e)=>$sum+($e['playerId']===$id?$e['credits']:0),0);}
function grant_invite_credits(array $p,string $id,mixed $credits=5):array{
 $credits=whole($credits,0,1000);foreach($p['players'] as &$player)if($player['id']===$id){
  if(!empty($player['inviteCreditsGranted']))return $p;
  $player['inviteCreditsGranted']=true;
  if($credits)$p['ledger'][]=['id'=>uuid(),'playerId'=>$id,'credits'=>$credits,'dollars'=>0,'note'=>'Invitation picking credits; only picked squares are owed','at'=>gmdate('c'),'by'=>'organizer'];
  return $p;
 }throw new RuntimeException('Player not found.');
}
function digit_draw():array{$a=range(0,9);for($i=9;$i>0;$i--){$j=random_int(0,$i);[$a[$i],$a[$j]]=[$a[$j],$a[$i]];}return $a;}
function valid_digits(mixed $v):bool{if(!is_array($v)||count($v)!==10)return false;foreach($v as $n)if(!is_int($n)||$n<0||$n>9)return false;return count(array_unique($v))===10;}
function mutate_pool(array $p,array $u,array $b):array{
 $a=$b['action']??'';$admin=$u['role']==='admin';$pid=$u['playerId']??'';$who=null;foreach($p['players'] as $x)if($x['id']===$pid)$who=$x;
 if(!$admin&&!$who)throw new RuntimeException('Invitation required.');
 $at=gmdate('c');$entry=function(string $id,int $credits,float $dollars,string $note)use(&$p,$u,$at){$p['ledger'][]=['id'=>uuid(),'playerId'=>$id,'credits'=>$credits,'dollars'=>$dollars,'note'=>$note,'at'=>$at,'by'=>$u['role']==='admin'?'organizer':$u['playerId']];};
 if($a==='requestCredits'){
  if($admin)throw new RuntimeException('Sign in as a player to request credits.');
  if(count($p['rows']))throw new RuntimeException('The board is locked.');
  $amount=whole($b['credits']??5,1,100);$requests=$p['creditRequests']??[];
  foreach($requests as $r)if($r['playerId']===$pid&&$r['status']==='pending')throw new RuntimeException('You already have a pending request.');
  $requests[]=['id'=>uuid(),'playerId'=>$pid,'credits'=>$amount,'note'=>txt($b['note']??'',200),'status'=>'pending','at'=>$at];$p['creditRequests']=$requests;return $p;
 }
 if($a==='buy'||$a==='personalize'){
  if(!$admin&&isset($b['playerId'])&&$b['playerId']!==$pid)throw new RuntimeException('You can only use your own credits and edit your own squares.',403);
  if(count($p['rows'])&&(!$admin||count($p['scores'])))throw new RuntimeException('The board is locked.');$i=whole($b['index']??null,0,99);if($admin){$who=null;foreach($p['players'] as $x)if($x['id']===($b['playerId']??''))$who=$x;}if(!$who)throw new RuntimeException('Choose a player.');$label=txt($b['label']??'',24);if(!$label)throw new RuntimeException('Choose a name or nickname.');$image=asset($b['image']??'');if(!$image)throw new RuntimeException('Choose an image or sign the square before confirming.');
  if($a==='buy'){if(isset($p['squares'][$i]))throw new RuntimeException('That square was just taken. Please choose another.');if(balance_for($p,$who['id'])<1)throw new RuntimeException('Not enough credits.');$entry($who['id'],-1,0,'Purchased square '.($i+1));$p['squares'][$i]=['playerId'=>$who['id'],'label'=>$label,'image'=>$image,'at'=>$at];}
  else{if(!isset($p['squares'][$i])||$p['squares'][$i]['playerId']!==$who['id'])throw new RuntimeException('This square belongs to another player.');$p['squares'][$i]['label']=$label;$p['squares'][$i]['image']=$image;}return $p;
 }
 if(!$admin)throw new RuntimeException('Organizer access required.');
 if($a==='resolveRequest'){
  $p['creditRequests']??=[];$found=false;foreach($p['creditRequests'] as &$r)if($r['id']===($b['requestId']??'')){
   if($r['status']!=='pending')throw new RuntimeException('This request has already been handled.');
   if(!is_bool($b['approve']??null))throw new RuntimeException('Choose approve or decline.');
   $r['status']=$b['approve']?'approved':'declined';$r['resolvedAt']=$at;
   if($b['approve']){$amount=whole($b['credits']??$r['credits'],1,1000);$entry($r['playerId'],$amount,0,'Approved credit request '.$r['id']);$r['granted']=$amount;}$found=true;break;
  }unset($r);if(!$found)throw new RuntimeException('Request not found.');return $p;
 }
 if($a==='invite'){$name=txt($b['name']??'',50);$number=phone(txt($b['phone']??'',40));$email=strtolower(txt($b['email']??'',254));if(!$name||!$number)throw new RuntimeException('Enter the player’s name and phone number.');if($email&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid email or leave it blank.');foreach($p['players'] as $x)if(($x['phone']??'')===$number)throw new RuntimeException('This phone number is already assigned to a player.');if(count($p['players'])>=300)throw new RuntimeException('Player limit reached.');$id=uuid();$p['players'][]=['id'=>$id,'name'=>$name,'phone'=>$number,'email'=>$email];if(array_key_exists('credits',$b))$p=grant_invite_credits($p,$id,$b['credits']);$cents=whole($b['cents']??0,0,10000000);if($cents){$note=txt($b['note']??'',200);if(!$note)throw new RuntimeException('Enter a payment method or note.');$entry($id,0,$cents/100,$note);}}
 elseif($a==='phone'){$number=phone(txt($b['phone']??'',40));$found=false;foreach($p['players'] as $x)if($number&&$x['id']!==($b['playerId']??'')&&($x['phone']??'')===$number)throw new RuntimeException('This number belongs to another player.');foreach($p['players'] as &$x)if($x['id']===($b['playerId']??'')){$x['phone']=$number;$found=true;}unset($x);if(!$found)throw new RuntimeException('Player not found.');}
 elseif($a==='credit'){$id=$b['playerId']??'';$exists=false;foreach($p['players'] as $x)if($x['id']===$id)$exists=true;if(!$exists)throw new RuntimeException('Player not found.');$credits=whole($b['credits']??null,-1000,1000);$dollars=whole($b['cents']??null,0,10000000)/100;$note=txt($b['note']??'',200);if(!$note||(!$credits&&!$dollars))throw new RuntimeException('Enter an amount and a payment or adjustment note.');if(balance_for($p,$id)+$credits<0)throw new RuntimeException('This would make the credit balance negative.');$entry($id,$credits,$dollars,$note);}
 elseif($a==='theme'){$theme=$b['theme']??null;if(!in_array($theme,['classic','stadium','clean','retro'],true))throw new RuntimeException('Choose one of the available styles.');$p['settings']['theme']=$theme;}
 elseif($a==='settings'){$v=$b['settings']??[];$price=whole($v['priceCents']??null,1,1000000)/100;$pay=$v['payouts']??[];if(!is_array($pay)||count($pay)!==4)throw new RuntimeException('Enter four payout percentages.');foreach($pay as $n)whole($n,0,100);if(array_sum($pay)!==100)throw new RuntimeException('Payout percentages must total 100.');if(count($p['squares'])&&$price!=$p['settings']['price'])throw new RuntimeException('The price cannot change after purchases begin.');$venmo=ltrim(txt($v['venmo']??'',40),'@');if($venmo&&!preg_match('/^[a-zA-Z0-9_-]+$/',$venmo))throw new RuntimeException('Enter a Venmo username, without a URL.');$p['settings']=['theme'=>$p['settings']['theme']??'classic','title'=>txt($v['title']??'',70)?:'Football Pool','price'=>$price,'rowTeam'=>txt($v['rowTeam']??'',30)?:'NFC','colTeam'=>txt($v['colTeam']??'',30)?:'AFC','rowLogo'=>asset($v['rowLogo']??''),'colLogo'=>asset($v['colLogo']??''),'cornerLogo'=>asset($v['cornerLogo']??''),'rules'=>txt($v['rules']??'',1200),'venmo'=>$venmo,'payouts'=>$pay];}
 elseif($a==='notice'){$title=txt($b['title']??'',100);$body=txt($b['body']??'',1500);if(!$title||!$body)throw new RuntimeException('Enter a title and message.');array_unshift($p['notices'],['id'=>uuid(),'title'=>$title,'body'=>$body,'at'=>$at]);$p['notices']=array_slice($p['notices'],0,100);}
 elseif($a==='numbers'){if(count($p['rows']))throw new RuntimeException('Numbers are already locked.');if(count($p['squares'])!==100)throw new RuntimeException('Fill all 100 squares first.');$rows=($b['random']??false)?digit_draw():($b['rows']??[]);$cols=($b['random']??false)?digit_draw():($b['cols']??[]);if(!valid_digits($rows)||!valid_digits($cols))throw new RuntimeException('Each axis must contain 0–9 exactly once.');$p['rows']=$rows;$p['cols']=$cols;}
 elseif($a==='score'){if(count($p['squares'])!==100)throw new RuntimeException('Refill all squares before entering scores.');if(!count($p['rows']))throw new RuntimeException('Assign board numbers first.');$q=whole($b['quarter']??null,0,3);$row=whole($b['row']??null,0,999);$col=whole($b['col']??null,0,999);if($q>count($p['scores']))throw new RuntimeException('Enter quarter scores in order.');if($q>0&&($row<$p['scores'][$q-1]['row']||$col<$p['scores'][$q-1]['col']))throw new RuntimeException('Cumulative scores cannot decrease.');if(isset($p['scores'][$q+1])&&($row>$p['scores'][$q+1]['row']||$col>$p['scores'][$q+1]['col']))throw new RuntimeException('This score exceeds the next quarter score.');$p['scores'][$q]=['row'=>$row,'col'=>$col,'paid'=>(bool)($b['paid']??false)];}
 elseif($a==='release'){if(count($p['scores']))throw new RuntimeException('Squares cannot be erased after scores are entered.');$i=whole($b['index']??null,0,99);$square=$p['squares'][$i]??null;if(!$square)throw new RuntimeException('Square is already available.');$entry($square['playerId'],1,0,'Released square '.($i+1).'; credit returned');unset($p['squares'][$i]);}
 else throw new RuntimeException('Unknown action.');return $p;
}
