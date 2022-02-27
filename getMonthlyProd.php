<?php
require_once "constants.php";
require_once "ids.php"; // Contains the identifier + Password to connect to RTone web API
require_once "common.php";
require_once "helpers.php";

$db = connect_to_db();


// $_GET should contain serial, start, end 
//http://localhost/~heusse/Monitor/getMonthlyProd.php?family=ticpmepmi&serial=8121069742770395288&end=1638903599
// $_GET['end']=1638903599;
// $_GET['family']="ticpmepmi";
// $_GET['serial']=8121069742770395288;

$endDate=$_GET['end'];
$dateYr=date("Y",$endDate);
$dateMonth=date("m",$endDate);
$startts= strtotime("$dateYr-$dateMonth-01T00:00:00");


if($dateMonth<12){
    $dateMonth++;
}
else{
    $dateMonth="1";
    $dateYr++;
}
$endts= strtotime("$dateYr-$dateMonth-01T00:00:00");
$serial=$_GET['serial'];

header('Content-Type: application/json');

if(strcmp($_GET['family'], "rbee")==0) {

  $retreadings["rbee_".$serial] = get_rbee_prod(
    $db,
    $serial,
    new DateTime('@'.$startts),
    new DateTime('@'.$endts)
  );
}
elseif (strcmp($_GET['family'], "tic")==0) {
  $retreadings["tic_".$serial] = get_tic_prod(
    $db,
    $serial,
    new DateTime('@'.$startts),
    new DateTime('@'.$endts)
  );
}
elseif (strcmp($_GET['family'],"ticpmepmi")==0){
  $reqArgs=array($serial,$startts-2*3600); # time offset to be sure to get first day of month, regardless of time zone/daylightsaving ?.
  $qr="select sum(i.eait) seait from ".tp."ticpmepmiindex i inner join (select min(date) date,ptcour,eait,deveui from ".tp."ticpmepmiindex where deveui=? and UNIX_TIMESTAMP(date)>? group by ptcour)  sub on i.date=sub.date and i.deveui=sub.deveui and i.ptcour=sub.ptcour;";
  $select_messages = $db->prepare($qr);
  $select_messages->setFetchMode(PDO::FETCH_ASSOC);
  $select_messages->execute($reqArgs);
  $readings =$select_messages->fetchAll();
  $eait1=$readings[0]['seait'];

  $reqArgs=array($serial,$endts);
  $qr="select sum(i.eait) seait from ".tp."ticpmepmiindex i inner join (select max(date) date,ptcour,eait,deveui from ".tp."ticpmepmiindex where deveui=? and UNIX_TIMESTAMP(date)<? group by ptcour)  sub on i.date=sub.date and i.deveui=sub.deveui and i.ptcour=sub.ptcour;1";
  $select_messages = $db->prepare($qr);
  $select_messages->setFetchMode(PDO::FETCH_ASSOC);
  $select_messages->execute($reqArgs);
  $readings =$select_messages->fetchAll();
  $eait2=$readings[0]['seait'];

  if(is_null($eait1)) $retreadings["ticpmepmi_".$serial]=0;
  else $retreadings["ticpmepmi_".$serial]=1000*($eait2-$eait1);
}
echo json_encode($retreadings);

?>