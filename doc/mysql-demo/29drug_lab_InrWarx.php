<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>Drug_Lab_ARV</title>
<meta name="language" content="TH" >
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">


<?php 
 
//print_r($_GET);
 
 $message =  "Drug_Lab Inr-Warfarin \n" ; 
 
 
  include("connect243.php");  

echo $sql = "select lab_order_number , hn , pname , ward ,labresult , labdate , drugname , drugdate  , an ,department
from line_durg_lab_InrWar
  where lab_order_number = '".$_GET['lab_order_number']."'  limit 1 ";
 $qry = mysqli_query($conn2,$sql);
 $dat = mysqli_fetch_row($qry);   
 
 
 $hn = $dat[1];
 $pname = $dat[2];
 $ward = $dat[3];
 $labresult = $dat[4]; 
 $labdate = $dat[5];
 $drugname = $dat[6];
 $drugdate = $dat[7]; 
 $an = $dat[8]; 
 $department = $dat[9];  
 
$message =  $message ."HN : ".$hn."\n";
$message =  $message ."AN : ".$an."\n";
$message =  $message ."Name : ".$pname."\n";
$message =  $message ."แผนก : ".$department."\n";
$message =  $message ."labresult : ".$labresult."\n"; 
$message =  $message ."วันที่ตรวจLab : ".$labdate."\n"  ;
$message =  $message ."รายการยา : ".$drugname."\n"; 
$message =  $message ."วันที่ใช้ยา : ".$drugdate."\n"  ;
 
/* 
   echo "MESSAGE == ".$message;  
   exit();
*/   
   $botApiToken = '7822155762:AAG1H0BFrLGpKNwfOQlq6fdqwPFe5Mmkb0U';
    $channelId ='-4728394640';  // $channelId ='-1004601965689';
 
 /*
    $botApiToken = '7951863040:AAEGXdpEHLpTAsYsPvfg9Gz92WP54o0dKFg';
    $channelId ='-4601965689';  // $channelId ='-1004601965689'; 
 */
    $text =  $message;  //'ข้อความแจ้งเตือน';
	
	$query = http_build_query([
		'chat_id' => $channelId,
		'text' => $text,
	]);
	$url = "https://api.telegram.org/bot{$botApiToken}/sendMessage?{$query}";

	$curl = curl_init();
	curl_setopt_array($curl, array(
		CURLOPT_URL => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CUSTOMREQUEST => 'GET',
		));
	$response = curl_exec($curl);
	$err = curl_error($curl);
	curl_close($curl);
	if($err){echo 'cURL Error #:'.$err;}
		else {echo $response;
  
   $sqlu = "update line_durg_lab_InrWar set st = 'y'   where lab_order_number = '".$_GET['lab_order_number']."'" ;   ///////////////////////////////////// chang
 mysqli_query($conn2,$sqlu);
 
 }
  


 /*************************************************************************************/
 
 
  
 
?>	
 <META HTTP-EQUIV="REFRESH" CONTENT="1;url=29drug_lab_InrWar.php"> 
<!-- -->
	
	
	
  