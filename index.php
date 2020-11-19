<?php
	if( !(isset($_GET['d'])) || (empty($_GET['d'])) || (strlen($_GET['d']) != 32) || !(ctype_xdigit($_GET['d'])))
	{
		echo "<h1>Invalid Parameter#1</h1><br/>";
		echo "<h1>". $_GET['d'] ."</h1>";
		exit(0);
	}
	else
	{
		$rawData = strtoupper($_GET['d']);
		$uid = substr($rawData, 0, 14);
		$flagTamperTag = substr($rawData, 14, 2);
		$timeStampTag = (double)hexdec(substr($rawData, 16, 8)) ;
		$rollingCodeTag = substr($rawData, 24, 8);
	}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<title>SIC43NT Demonstration</title>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-status-bar-style" content="black">
	<meta name="format-detection" content="telephone=no">
	
	<style>
    table, th, td {
      border: 1px solid black;
      border-collapse: collapse;
    }
    th, td {
      padding: 5px;
      text-align: left;    
    }
  </style>
	
</head>
<body>
<?php
  require_once "keystream.php";
  /* Production Default Key is "FFFFFF" concatinate with UID[7] */
  $defaultKey = "FFFFFF".$uid;     
  
  /* Calculate Rolling code based on tag time stamp and Key. */
  $rollingCodeServer = keystream(hexbit($defaultKey), hexbit(substr($rawData, 16, 8)), 4); 
  
  /* No storage for latest time stamp and tamper flag on server side */
	$timeStampServer = "N/A";
  $flagTamperServer = "N/A";
  
	$timeStampDecision = "N/A";
  $flagTamperDecision = "N/A";
  
  /*---- Time Stamp Counting Decision ----*/
  if ($timeStampServer == "N/A") {
    $timeStampDecision = "N/A";
  } else {
    if ($timeStampServer < $timeStampTag) {
      $timeStampDecision = "Rolling code updated";
    }else {
      $timeStampDecision = "Rolling code reused";
    }
  }

  /*---- Rolling Code Counting Decision ----*/
  $rollingCodeDecision = "N/A";

  if ($rollingCodeServer == $rollingCodeTag) {
    $rollingCodeDecision = "Correct";
  } else {
    if ($flagTamperTag == "AA") {
		/* for tags that can setting secure tamper */
      $rlc = keystream(hexbit($defaultKey), hexbit(substr($rawData, 16, 8)), 12); 
      $rollingCodeServer = substr($rlc, 16, 8);

      if ($rollingCodeServer == $rollingCodeTag) {
        $rollingCodeDecision = "Correct";
      } else {
        $rollingCodeDecision = "Incorrect";
      }
    } else {
      $rollingCodeDecision = "Incorrect";
    }
  }
?>
<h1>SIC43NT Demonstration</h1>
<table style="width:75%">
  <tr>
    <th>UID[7]</th>
    <td colspan="3"><?php echo $uid; ?></td> 
  </tr>
  <tr>
    <th>Default Key</th> 
    <td colspan="3"><?php echo $defaultKey; ?></td> 
  </tr>
  <tr>
    <td> - </td>
    <td> From Tag </td> 
    <td> From Server</td> 
    <td> Result</td>     
  </tr>
  <tr>
    <th>Tamper Flag(HEX)</th>
    <td><?php echo $flagTamperTag; ?></td> 
    <td><?php echo $flagTamperServer; ?></td> 
    <td><?php echo $flagTamperDecision; ?></td>     
  </tr>
  <tr>
    <th>Time Stamp(DEC)</th>
    <td><?php echo $timeStampTag; ?></td> 
    <td><?php echo $timeStampServer; ?></td> 
    <td><?php echo $timeStampDecision; ?></td>     
  </tr>  
  <tr>
    <th>Rolling code(HEX)</th>
    <td><?php echo $rollingCodeTag; ?></td>
    <td><?php echo $rollingCodeServer; ?></td> 
    <td><?php echo $rollingCodeDecision; ?></td>     
  </tr>  

</table>

</body>
</html>