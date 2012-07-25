<?php
    #Enter Your Organization ID
    $organizationId = "";
    #Enter Your Organization Application Key
    $applicationKey = "";
    #Enter Your Organization Shared Secret
    $sharedSecret = "";
    
    $shortopts  = "";
    $longopts  = array(
	"nameMatch:",
	"powerState:"
    );
    $options = getopt($shortopts, $longopts);
    
    $vmNameRegex = "*" . $options['nameMatch'] . "*";
    $powerState = $options['powerState'];
    
    function scApiExecute($requestType,$apiCommand,$credentials) {
	$baseURL = "https://admin.securedcloud.com/cloud-external-api-rest";
	$ch = curl_init();
	if ($requestType == "PUT") {
	    curl_setopt_array($ch, array(
		CURLINFO_HEADER_OUT => true,
		CURLOPT_PUT => true,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_URL => $baseURL . $apiCommand,
		CURLOPT_HTTPHEADER => array("Accept: application/vnd.securedcloud.v3.0+json", "Authorization: SC {$credentials}")
	    ));
	} elseif ($requestType == "POST") {
	    curl_setopt_array($ch, array(
		CURLINFO_HEADER_OUT => true,
		CURLOPT_POST => true,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_URL => $baseURL . $apiCommand,
		CURLOPT_HTTPHEADER => array("Accept: application/vnd.securedcloud.v3.0+json", "Authorization: SC {$credentials}")
	    ));
	} else {
	    curl_setopt_array($ch, array(
		CURLINFO_HEADER_OUT => true,
		CURLOPT_HTTPGET => true,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_URL => $baseURL . $apiCommand,
		CURLOPT_HTTPHEADER => array("Accept: application/vnd.securedcloud.v3.0+json", "Authorization: SC {$credentials}")
	    ));
	}
	$response = curl_exec($ch);
	$info = curl_getinfo($ch);
	return $response;
    }
    
    $getVirtualMachineResourceURL = "/organization/" . $organizationId . "/virtualmachine";
    $requestString = "GET " . $getVirtualMachineResourceURL . " " . $applicationKey;
    $hash = base64_encode(hash_hmac('sha256', $requestString, $sharedSecret, TRUE));
    $credentials = base64_encode($applicationKey . ":" . $hash);
    $virtualMachineResources = json_decode(scApiExecute("GET",$getVirtualMachineResourceURL,$credentials));
    
    $virtualMachineIpArray = array();
    $virtualMachineNameArray = array();
    foreach($virtualMachineResources as $key=>$virtualMachineResourceArray) {
	$virtualMachineDetailsURL = $virtualMachineResourceArray->resourceURL;
	$requestString = "GET " . $virtualMachineDetailsURL . " " . $applicationKey;
	$hash = base64_encode(hash_hmac('sha256', $requestString, $sharedSecret, TRUE));
	$credentials = base64_encode($applicationKey . ":" . $hash);
	$jsonResponse = json_decode(scApiExecute("GET",$virtualMachineDetailsURL,$credentials));
	$virtualMachineIpArray[$virtualMachineDetailsURL] = $jsonResponse->privateIp;
	$virtualMachineNameArray[$virtualMachineDetailsURL] = $jsonResponse->name;
    };
    
    $vmsToPowerOn = preg_grep($vmNameRegex, $virtualMachineNameArray);
    
    foreach($vmsToPowerOn as $vmResourceURL=>$virtualMachineName) {
	$powerOnVmURL = $vmResourceURL . "/power?powerState=" . $powerState;
	$requestString = "PUT " . $powerOnVmURL . " " . $applicationKey;
	$hash = base64_encode(hash_hmac('sha256', $requestString, $sharedSecret, TRUE));
	$credentials = base64_encode($applicationKey . ":" . $hash);
	$powerOnResponse = scApiExecute("PUT",$powerOnVmURL,$credentials);
	$jsonPowerOnresponse = json_decode($powerOnResponse);
	print_r($jsonPowerOnresponse);
    }
?>
