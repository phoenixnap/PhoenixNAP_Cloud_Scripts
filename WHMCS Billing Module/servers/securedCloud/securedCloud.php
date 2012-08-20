<?php
date_default_timezone_set('UTC');
openlog("securedCloudAdmin", LOG_PID | LOG_PERROR, LOG_LOCAL0);

function securedCloud_ConfigOptions() {
    $configarray = array(
        "clientId" => array ("FriendlyName" => "Client ID", "Type" => "text", "Size" => "7", "Description" => "This is your Phoenix NAP Client ID", "Default" => "", ),
        "apiBaseURL" => array ("FriendlyName" => "API Base URL", "Type" => "text", "Size" => "80", "Description" => "Enter your reseller site URL here", "Default" => "https://admin.phoenixnap.com/cloud-external-api-rest", ),
        "applicationKey" => array ("FriendlyName" => "Application Key", "Type" => "text", "Size" => "40", "Description" => "", ),
        "sharedSecret" => array ("FriendlyName" => "Shared Secret", "Type" => "password", "Size" => "25", "Description" => "", ),
    );
    return $configarray;
}

function securedCloud_ClientArea() {
    return 1;
}


function securedCloud_CreateAccount($params) {
    $baseURL = $params['configoption2'];
    $resourceURL = "/organization/" . $params['configoption1'];
    $applicationKey = $params['configoption3'];
    $sharedSecret = $params['configoption4'];
    $requestString = "POST " . $resourceURL . " " . $applicationKey;
    $hash = base64_encode(hash_hmac('sha256', $requestString, $sharedSecret, TRUE));
    $credentials = base64_encode($applicationKey . ":" . $hash);
    $clientDetails = $params['clientsdetails'];
    $customFields = $params["customfields"];
    $clientDetailsArray = array(
    	"name"=>$clientDetails['companyname'],
   		"organizationType"=>"END_CLIENT",
   		"email"=>$clientDetails['email'],
   		"clientAssignedId"=>$clientDetails['userid'],
   		"userName"=>$customFields['Cloud Admin Username'],
   		"password"=>$customFields['Cloud Admin Password'],
   		"passPhrase"=>$customFields['Cloud Support Passphrase'],
   		"primaryContactName"=>$clientDetails['firstname'],
   		"primaryContactSurname"=>$clientDetails['lastname'],
   		"primaryContactEmail"=>$clientDetails['email'],
   		"primaryContactPhoneNumber"=>$clientDetails['phonenumber']
      );
    $jsonClientDetails = json_encode($clientDetailsArray);
    
    $ch = curl_init();
    curl_setopt_array($ch, array(
    	CURLINFO_HEADER_OUT => 1,
	CURLOPT_HEADER => 1,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => $jsonClientDetails,
        CURLOPT_URL => $baseURL . $resourceURL,
        CURLOPT_HTTPHEADER => array("Accept: application/vnd.securedcloud.v4.0+json", "Authorization: SC {$credentials}", "Content-Type: application/vnd.securedcloud.v4.0+json")
    ));
    $response = curl_exec($ch);
    $jsonClientAddResponse = json_decode($response);
    $cloudAdminOrgId = str_replace("/organization/", "", $jsonClientAddResponse->{'resourceURL'});

    
    
    $date = new DateTime(null, new DateTimeZone('America/Phoenix'));
    $now = $date->getTimestamp()*1000;
    $clientDetails = $params["clientsdetails"];
    $result = mysql_query("INSERT INTO SC_Rated_Usage (clientId,cloudAdminOrgId,lastBillingUpdate,billingTotal) VALUES ('" . $clientDetails['userid'] . "','" . $cloudAdminOrgId . "',from_unixtime(" . $now . "/1000), '0.00000')");
    #syslog(LOG_WARNING, "INSERT INTO SC_Rated_Usage (clientId,cloudAdminOrgId,lastBillingUpdate,billingTotal) VALUES ('" . $clientDetails['userid'] . "','" . $cloudAdminOrgId . "',from_unixtime(" . $now . "/1000), '0.00000')");
    
    
    if(curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200) {
    	$returnSuccess = "Successful";
    } else {
    }
    
    if ($returnSuccess) {
	    $result = "success";
    } else {
            $result = "We have encountered an error: ". $successful ."";
    }
    return $result;
}

function securedCloud_AdminServicesTabFields($params) {
    $result = mysql_query("SELECT billingTotal,lastBillingUpdate FROM SC_Rated_Usage WHERE clientId=" . $params['clientsdetails']['userid'] . " ORDER BY lastBillingUpdate DESC LIMIT 1");
    while ($row = mysql_fetch_row($result)) {
        $cloudUsage = $row[0];
        $lastBillingUpdateTime = $row[1];
    }
    
    $fieldsArray = array(
        "Current Monthly Cloud Usage" => "$" . number_format((float)$cloudUsage, 2, '.', ''),
        "Last Cloud Usage Update" => $lastBillingUpdateTime  
    );
    return $fieldsArray;
}

function securedCloud_getUpdatedBilling($params) {
    $date = new DateTime(null, new DateTimeZone('America/Phoenix'));
    $result = mysql_query("SELECT billingTotal,cloudAdminOrgId,UNIX_TIMESTAMP(lastBillingUpdate)*1000 FROM SC_Rated_Usage WHERE clientId=" . $params['clientsdetails']['userid'] . " LIMIT 1");
    syslog(LOG_WARNING, "SELECT billingTotal,cloudAdminOrgId,UNIX_TIMESTAMP(lastBillingUpdate)*1000 FROM SC_Rated_Usage WHERE clientId=" . $params['clientsdetails']['userid'] . " LIMIT 1");
    while ($row = mysql_fetch_row($result)) {
       $lastBillingUpdateTime = $row[2];
       $cloudAdminOrgId = $row[1];
       $currentBillingTotal = floatval($row[0]);
    }
    syslog(LOG_WARNING, "Cloud Billing ID: $cloudAdminOrgId");
    #Add MySQL empty record error handling here
    
    $endTime   = $date->getTimestamp()*1000;
    $startTime = $lastBillingUpdateTime;

    $baseURL = $params['configoption2'];
    $resourceURL = "/organization/" . $cloudAdminOrgId . "/ratedusage?detailed=false&endTime=" . $endTime . "&startTime=" . $startTime . "";
    $applicationKey = $params['configoption3'];
    $sharedSecret = $params['configoption4'];
    $requestString = "GET " . $resourceURL . " " . $applicationKey;
    $hash = base64_encode(hash_hmac('sha256', $requestString, $sharedSecret, TRUE));
    $credentials = base64_encode($applicationKey . ":" . $hash);
    
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_URL => $baseURL . $resourceURL,
        CURLOPT_HTTPHEADER => array("Accept: application/vnd.securedcloud.v4.0+json", "Authorization: SC {$credentials}", "Content-Type: application/vnd.securedcloud.v4.0+json")
    ));
    $response = curl_exec($ch);
    $jsonResponse = json_decode($response);
    #var_dump($jsonResponse);
    if ($jsonResponse != NULL) {
    	$newStartTime = $jsonResponse->{'endTime'};
    	$newBillingTotal = $jsonResponse->{'totalAmount'};
    	$sumBillingTotal = ($newBillingTotal + $currentBillingTotal);
    	$result = mysql_query("UPDATE SC_Rated_Usage SET billingTotal=" . $sumBillingTotal . ",lastBillingUpdate='". $newStartTime ."' WHERE clientId=" . $params['clientsdetails']['userid'] . "");
    	#syslog(LOG_WARNING, "UPDATE SC_Rated_Usage SET billingTotal=" . $sumBillingTotal . ",lastBillingUpdate='". $newStartTime ."' WHERE clientId=" . $params['clientsdetails']['userid'] . "");
    } else {
    	#syslog(LOG_WARNING, "No Updates at this time");
    }
    $returnSuccess = "Successful";
    if ($returnSuccess) {
	    $result = "success";
    } else {
            $result = "We have encountered an error: ". $successful ."";
    }
    return $result;
}

function securedCloud_ClientAreaCustomButtonArray() {
    $buttonArray = array(
        "Update Cloud Billing Total" => "getUpdatedBilling"
    );
    return $buttonArray;
}
function securedCloud_AdminCustomButtonArray() {
    $buttonArray = array(
        "Update Cloud Billing Total" => "getUpdatedBilling"
    );
    return $buttonArray;
}

closelog();
?>
