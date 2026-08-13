<META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=windows-874"> 
<head>
<title>29.Drug-Lab Inr Warfarin</title>  
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="refresh" content="1400"/>  
 
<?php 
date_default_timezone_set('asia/bangkok');
set_time_limit(0);
/* ย้อนไป xx วัน */
$date_x = date('Y-m-d', strtotime("-2 days")); // $date_x = '2019-10-05';
/*  1. ดูข้อมูล  ใน hosxp   ******************************* *************************************************/

 include("connecthos.php");
mysqli_query($conn,"SET CHARACTER SET 'tis620'");
mysqli_query($conn,"SET SESSION collation_connection ='tis620'");
 
 // check  INR > 3
  $sql1 = " select h.lab_order_number , h.hn , h.order_date , o.lab_order_result ,  k.department
from lab_head h
left outer join lab_order o on o.lab_order_number = h.lab_order_number
left outer join kskdepartment k on k.depcode = h.order_department
where o.lab_items_code in ( '324') and o.confirm = 'Y' and o.lab_order_result > 3
and h.order_date > '$date_x' and h.hn in (select hn from opitemrece where vstdate >= '$date_x'  and icode in ( '1001142' , '1001143' ) )
order by h.lab_order_number desc limit 100 ";
 $qry1 = mysqli_query($conn,$sql1);
 while($dat1 = mysqli_fetch_row($qry1)){
 
 $lab_order_number = $dat1[0]; 
 $hn = $dat1[1]; 
 $order_date = $dat1[2]; 
 $labresult = $dat1[3];
 $department = $dat1[4];
 
 
 
$numrow1 = mysqli_num_rows($qry1);  
if($numrow1 >0){

include("connecthos.php"); 
mysqli_query($conn,"SET CHARACTER SET 'tis620'");
mysqli_query($conn,"SET SESSION collation_connection ='tis620'");
 
 // ดูชื่อยา
  $sql2 = "select concat(p.fname ,'  ' , p.lname) as pname , k.department , d.icode , concat(d.name,' ',d.strength,' ',d.units ) as drugname , o.vstdate ,  w.name , o.hn , o.an
from opitemrece o
left outer join patient p on p.hn = o.hn
left outer join drugitems d on d.icode = o.icode
left outer join kskdepartment k on k.depcode = o.dep_code
left outer join ipt i on i.an = o.an
left outer join ward w on w.ward = i.ward  
where o.rxdate >= '$date_x'  and o.hn = '$hn'
and o.icode in ( '1001142' , '1001143', '1680036' ) 
order by o.vstdate desc limit 100 ";   
 $qry2 = mysqli_query($conn,$sql2); 
 $dat2 = mysqli_fetch_row($qry2); 
$pname = $dat2[0]; 
$drugname = $dat2[3];  
$drugdate = $dat2[4];   
$wardname = $dat2[5];  
$an = $dat2[7];  

 

 // check ในฐานข้อมูลในเครื่อง 
   include("connect243.php"); 
mysqli_query($conn2,"SET CHARACTER SET 'tis620'");
mysqli_query($conn2,"SET SESSION collation_connection ='tis620'");

  $sql4 = "select lab_order_number from line_durg_lab_InrWar where lab_order_number = '$lab_order_number'  limit 1";
 //echo "<br>". $sql; 
 $qry4 = mysqli_query($conn2,$sql4);
 $numrow4 = mysqli_num_rows($qry4);
 if($numrow4 == 0){  
/*  ถ้ายังไม่มี ให้ insert    */
 $sqlinsert = "insert into  line_durg_lab_InrWar ( lab_order_number , hn , pname , ward , department , labresult , labdate , drugname , drugdate , st , timeflg , an ) 
value ( '$lab_order_number' , '$hn' , '$pname'  , '$wardname' , '$department' , '$labresult' , '$order_date' , '$drugname' , '$drugdate' , 'n' , '".date('Y-m-d H:i:s')."' , '$an' )" ;  
 mysqli_query($conn2,$sqlinsert); 
// echo "<br>". $sqlinsert; 
  }//end if numrow4
 
  }//end if numrow1
} //end while




 

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
 
  
 

 
/* สถานะ  n  .ให้ไปส่ง line  */
  include("connect243.php"); 
mysqli_query($conn2,"SET CHARACTER SET 'tis620'");
mysqli_query($conn2,"SET SESSION collation_connection ='tis620'");

 $sql9 = "select lab_order_number  from line_durg_lab_InrWar  where st = 'n' order by lab_order_number asc limit 1 ";
 $qry9 = mysqli_query($conn2,$sql9);
 $dat9 = mysqli_fetch_row($qry9);   
 
$numrow9  = mysqli_num_rows($qry9);
if($numrow9 > 0){
?>
<META HTTP-EQUIV="REFRESH" CONTENT="0;url=29drug_lab_InrWarx.php?lab_order_number=<?php echo $dat9[0]?>" > 
<?php 
}else{  

 
 

// query แสดงข้อมูลที่ส่ง
echo "<table border=1>";
echo "<tr><td colspan=10><h2>29 drug_lab_InrWarin <br>".date('d-m-Y')." <br>".date('H:i:s')."<font color='#FFFFFF'>____________ </font>TIME REFRESH </h2><br>";   
  echo "<tr>";
 echo "<th>lab_order_number";
 echo "<th>HN";
 echo "<th>Pname";
 echo "<th>ward"; 
 echo "<th>labresult > 3";
 echo "<th>ชื่อยา";
 echo "<th>วันที่ใช้ยา";
 echo "<th>st";
 echo "<th>timeflg";
 
   include("connect243.php");  
 $sql10 = "select lab_order_number , hn , pname , ward , labresult , drugname , drugdate , st , timeflg  , department from line_durg_lab_InrWar  order by timeflg desc limit 100";
 $qry10 = mysqli_query($conn2,$sql10);
 while($dat10 = mysqli_fetch_row($qry10)){  
 echo "<tr>";
 echo "<td>".$dat10[0];
 echo "<td>".$dat10[1];
 echo "<td>".$dat10[2];
 echo "<td>".$dat10[3]." ".$dat10[9];
 echo "<td>".$dat10[4];
 echo "<td>".$dat10[5];
 echo "<td>".$dat10[6];
 echo "<td>".$dat10[7];
 echo "<td>".$dat10[8]; 
 }//end while 
 
}//end if 
 
 
 
?>



