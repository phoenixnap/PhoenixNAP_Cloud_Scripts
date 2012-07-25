<?php
    $baseURL = "https://admin.securedcloud.com/cloud-external-api-rest";
    #Enter Your Organization ID
    $organizationId = "";
    #Enter Your Organization Application Key
    $applicationKey = "";
    #Enter Your Organization Shared Secret
    $sharedSecret = "";
    
    
    $getVirtualMachineResourceURL = "/organization/" . $organizationId . "/virtualmachine";
    $requestString = "GET " . $getVirtualMachineResourceURL . " " . $applicationKey;
    $hash = base64_encode(hash_hmac('sha256', $requestString, $sharedSecret, TRUE));
    $credentials = base64_encode($applicationKey . ":" . $hash);


    $ch = curl_init();
    curl_setopt_array($ch, array(
	    CURLINFO_HEADER_OUT => true,
	    CURLOPT_HTTPGET => true,
	    CURLOPT_RETURNTRANSFER => true,
	    CURLOPT_URL => $baseURL . $getVirtualMachineResourceURL,
	    CURLOPT_HTTPHEADER => array("Accept: application/vnd.securedcloud.v3.0+json", "Authorization: SC {$credentials}")
     ));
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    $jsonResponse = json_decode($response);
    
    $virtualMachineIpArray = array();
    $virtualMachineNameArray = array();
    foreach($jsonResponse as $key=>$virtualMachineResourceArray) {
	$virtualMachineDetailsURL = $virtualMachineResourceArray->resourceURL;
	$requestString = "GET " . $virtualMachineDetailsURL . " " . $applicationKey;
	$hash = base64_encode(hash_hmac('sha256', $requestString, $sharedSecret, TRUE));
	$credentials = base64_encode($applicationKey . ":" . $hash);
	$ch = curl_init();
	curl_setopt_array($ch, array(
	    CURLINFO_HEADER_OUT => true,
	    CURLOPT_HTTPGET => true,
	    CURLOPT_RETURNTRANSFER => true,
	    CURLOPT_URL => $baseURL . $virtualMachineDetailsURL,
	    CURLOPT_HTTPHEADER => array("Accept: application/vnd.securedcloud.v3.0+json", "Authorization: SC {$credentials}")
	 ));
	$response = curl_exec($ch);
	$info = curl_getinfo($ch);
	$jsonResponse = json_decode($response);
	array_push($virtualMachineIpArray, $jsonResponse->privateIp);
	array_push($virtualMachineNameArray, $jsonResponse->name);
    };
    print_r($virtualMachineIpArray);
    print_r($virtualMachineNameArray);
?>
