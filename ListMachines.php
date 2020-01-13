<?php

require "header.php";

// Displays an error message and stops scripts
function noTokenFound()
{
	echo "
	<div id='error-border'>
		<div class='error'>
			No valid token found.<br>
			<button onclick='".'window.location.href="index.php"'."'>Home</button>
		</div>
	</div>
	";
	exit(-1);
}

// Loads an OAuth token from file
// @param $oauth -- reference to OAuth object
function loadToken(&$oauth)
{
	$fd = file_exists("savedToken.txt") ? fopen("savedToken.txt", "r") : false;
	if($fd == false)
		noTokenFound();

	fgets($fd);
	$savedToken = trim(fgets($fd));
	$savedSecret = trim(fgets($fd));
	fclose($fd);

	if(empty($savedToken) || empty($savedSecret))
		noTokenFound();

	$oauth->setToken($savedToken, $savedSecret);
} // end of function


$oauth = new JDOAuth($settings["App_Key"], $settings["App_Secret"]);
loadToken($oauth);

?>
<div class="page-title">List Machines</div>
<div style="padding-left: 20px;">
<?php


// Only supports page 1 at present
$response = $oauth->get($settings["JDLINK_API_URL"].'Fleet/1');

// If http != 200, response is short and states error code
if(strlen($response) < 5){
	 $was_error = true;
	 echo 'ERROR - HTTP Response: '.$response;
} else {
	$was_error = false;
} // end else


// Saves data to xml file
$xml_data_store = "xml/JDLinkData.xml";

function saveXML($response){
	$fd = fopen($xml_data_store, "w");
	fwrite($fd, $response);
	fclose($fd);
} // end of function
saveXML($response);


function filterExtreme($data) {
	$data = trim(htmlentities(strip_tags($data)));
	  $data = preg_replace('/[^- .@_A-Za-z0-9]/', "", $data);
	  return $data;
} // end of function

if(isset($_GET['selected_PIN']) && $_GET['selected_PIN'] != ''){
	$selected_PIN = filterExtreme($_GET['selected_PIN']);
} else {
	$selected_PIN = '';
} // end else

echo '<div id="pin-lookup">';

echo '<form action="" method="GET" id="PIN_INPUT">
	<b>Enter PIN:</b>
	<br />
	<input type="text" name="selected_PIN" value="'.$selected_PIN.'" size="30" />
	<button type="submit">Go</button>
</form>';

// $selected_PIN = '1RW8230T903201';

if($selected_PIN != ''){
$selected_PIN = substr($selected_PIN, 1); // remove first character (since some people include a 1 at the front of a S/N, etc.)
$xml = simplexml_load_file($xml_data_store) or die("Error: Cannot create object from XML");
// Get name space from file, and use that
$namespaces = $xml->getDocNamespaces();
if(isset($namespaces[''])) {
    $defaultNamespaceUrl = $namespaces[''];
    $xml->registerXPathNamespace('default', $defaultNamespaceUrl);
    $nsprefix = 'default:';
} else {$nsprefix = '';}
$result = $xml->xpath('//'.$nsprefix.'Equipment');
foreach ($result as $Equipment) {
    $loop_PIN = $Equipment->EquipmentHeader->PIN;
    if(strpos($loop_PIN, $selected_PIN) !== false){
        $this_OEMName = $Equipment->EquipmentHeader->OEMName;
        $this_Model = $Equipment->EquipmentHeader->Model;
        $this_EquipmentID = $Equipment->EquipmentHeader->EquipmentID;
        $this_SerialNumber = $Equipment->EquipmentHeader->SerialNumber;
        $this_PIN = $Equipment->EquipmentHeader->PIN;
        
        $this_Latitude = $Equipment->Location->Latitude;
        $this_Longitude = $Equipment->Location->Longitude;
        $this_LocationTime = $Equipment->Location['datetime'];

        $this_HourReading = $Equipment->CumulativeOperatingHours->Hour;
        $this_HourReadingTime = $Equipment->CumulativeOperatingHours['datetime'];
    break; // if found, stop loop
    } // end if
} // end foreach

echo '<br />';
echo '<b>Details for '.$selected_PIN.'</b><br />';
echo '<br />';
echo '<b>OEM:</b> ';
echo $this_OEMName. '<br />';
echo '<b>Model:</b> ';
echo $this_Model. '<br />';
echo '<b>Equipment ID:</b> ';
echo $this_EquipmentID. '<br />';
echo '<b>Serial Number:</b> ';
echo $this_SerialNumber. '<br />';
echo '<b>PIN:</b> ';
echo $this_PIN. '<br />';
echo '<b>Location</b> - ';
echo '<b>Lat:</b> ';
echo $this_Latitude.' / ';
echo '<b>Long:</b> ';
echo $this_Longitude.' <span class="greysm">as of: '.$this_LocationTime.'</span>';
echo '<br />';
echo '<b>Hrs:</b> ';
echo $this_HourReading.' <span class="greysm">as of: '.$this_HourReadingTime.'</span>';
echo '<br />';
}

echo '</div>';
echo '<br />';
echo '<div class="page-title">Retrieved Raw XML JDLink Machine Data</div>';

echo '<textarea rows="40" style="width: 80%; height: 100%; background: inherit; border:none;" readonly>';
echo $response;
echo '</textarea>';




// Grabs a URL from a response
// @param $response -- decoded json response
// @param $rel -- the URL to grab
// @return URL, false if not found
function getURL($response, $rel)
{
	if(!isset($response->links))
		return false;

	foreach($response->links as $link)
	{
		if($link->rel == $rel)
			return $link->uri;
	}
	return false;
}

?>
</div>
<div class="footer"></div>
