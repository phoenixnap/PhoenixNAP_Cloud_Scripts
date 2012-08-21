<?php

$baseURL = "https://yourcloudadminurl/cloud-external-api-rest";
$applicationKey = "";
$sharedSecret = "";
$adminUser = "";

function hook_Get_Daily_Usage($vars) {
    global $applicationKey, $sharedSecret, $baseURL, $adminUser;
    $apiCommand = "getproducts";
    $apiValues = array(
        # ENTER YOUR PRODUCT GROUP ID FOR CLOUD SERVICES HERE
        'gid' => '1'
    );
    $results = localAPI($apiCommand,$apiValues,$adminUser);
    #if ($results['result']!="success") {
    #    syslog(LOG_WARNING, "An Error Occurred: ".$results['result']);
    #} else {
    #}
    $productName = $results['products']['product'][0]['name'];
    if ($productName == "Cloud Account") { 
        # Call to get all active clients and toss their ID's in an array
        $apiCommand = "getclients";
        $apiValues = array(
            'limitnum' => '3000'
        );
        $results = localAPI($apiCommand,$apiValues,$adminUser);
        foreach($results['clients']['client'] as $clients => $idArray) {
            $clientId = $idArray['id'];
            $clientStatus = $idArray['status'];
            if ($clientStatus == "Active") {
                $cloudAdminOrgId = NULL;
                $date = new DateTime(null, new DateTimeZone('America/Phoenix'));
                $result = mysql_query("SELECT cloudAdminOrgId,UNIX_TIMESTAMP(lastBillingUpdate)*1000,billingTotal FROM SC_Rated_Usage WHERE clientId=" . $clientId . " LIMIT 1");
                while ($row = mysql_fetch_row($result)) {
                   $currentBillingTotal = $row[2];
                   $lastBillingUpdateTime = $row[1];
                   $cloudAdminOrgId = $row[0];
                }
                #Add MySQL empty record error handling here
                if ($cloudAdminOrgId) {
                    $endTime   = $date->getTimestamp()*1000;
                    $startTime = $lastBillingUpdateTime;
                    $resourceURL = "/organization/" . $cloudAdminOrgId . "/ratedusage?detailed=false&endTime=" . $endTime . "&startTime=" . $startTime . "";
                    syslog(LOG_WARNING, "Resource URL: $resourceURL");
                    $requestString = "GET " . $resourceURL . " " . $applicationKey;
                    $hash = base64_encode(hash_hmac('sha256', $requestString, $sharedSecret, TRUE));
                    $credentials = base64_encode($applicationKey . ":" . $hash);
                    
                    $ch = curl_init();
                    curl_setopt_array($ch, array(
                        #CURLINFO_HEADER_OUT => 1,
                        #CURLOPT_HEADER => 1,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_URL => $baseURL . $resourceURL,
                        CURLOPT_HTTPHEADER => array("Accept: application/vnd.securedcloud.v4.0+json", "Authorization: SC {$credentials}", "Content-Type: application/vnd.securedcloud.v4.0+json")
                    ));
                    $response = curl_exec($ch);
                    $info = curl_getinfo($ch);
                    $jsonResponse = json_decode($response);
                    if ($jsonResponse != NULL) {
                        $newStartTime = $jsonResponse->{'endTime'};
                        $newBillingTotal = $jsonResponse->{'totalAmount'};
                        $sumBillingTotal = ($newBillingTotal + $currentBillingTotal);
                        $result = mysql_query("UPDATE SC_Rated_Usage SET billingTotal=" . $sumBillingTotal . ",lastBillingUpdate='". $newStartTime ."' WHERE clientId=" . $clientId . "");
                        #syslog(LOG_WARNING, "UPDATE SC_Rated_Usage SET billingTotal=" . $sumBillingTotal . ",lastBillingUpdate='". $newStartTime ."' WHERE clientId=" . $params['clientsdetails']['userid'] . "");
                    } else {
                        #syslog(LOG_WARNING, "No Updates at this time");
                    }
                }
            } 
        }
        
        
        
    }
}

function hook_Reset_Usage_On_Invoice($vars) {
    global $applicationKey, $sharedSecret, $baseURL, $adminUser;
    $invoiceId = $vars['invoiceid'];
    syslog(LOG_WARNING, "INVOICECREATEADMINAREA  Invoice ID: ". $invoiceId);
    $getInvoiceApiCommand = "getinvoice";
    $getInvoiceApiValues = array(
        'invoiceid' => $invoiceId
    );
    $results = localAPI($getInvoiceApiCommand,$getInvoiceApiValues,$adminUser);
    
    $clientId = $results['userid'];
    
    $result = mysql_query("SELECT cloudAdminOrgId,UNIX_TIMESTAMP(lastBillingUpdate)*1000,billingTotal FROM SC_Rated_Usage WHERE clientId=" . $clientId . " LIMIT 1");
    while ($row = mysql_fetch_row($result)) {
       $currentBillingTotal = $row[2];
       $lastBillingUpdateTime = $row[1];
       $cloudAdminOrgId = $row[0];
    }

    $updateInvoiceApiCommand = "updateinvoice";
    $updateInvoiceApiValues = array(
        'invoiceid' => $invoiceId,
        'newitemdescription' => array("0"=>"Cloud Usage"),
	'newitemamount' => array("0"=>$currentBillingTotal),
	'newitemtaxed' => array("0"=>"0")
    );
    $results = localAPI($updateInvoiceApiCommand,$updateInvoiceApiValues,$adminUser);
    $printUpdateInvoiceResults = implode(",", $results);
    
    $invoiceId = $vars['invoiceid'];
    $getInvoiceApiCommand = "getinvoice";
    $getInvoiceApiValues = array(
        'invoiceid' => $invoiceId
    );
    $results = localAPI($getInvoiceApiCommand,$getInvoiceApiValues,$adminUser);
    $printGetInvoiceResults = implode(",", $results);

    $result = mysql_query("UPDATE SC_Rated_Usage SET billingTotal = 0 WHERE clientId=" . $clientId);
    
    syslog(LOG_WARNING, "Client ID: ". $clientId);
    syslog(LOG_WARNING, "Current Billing Total: ". $currentBillingTotal);
    syslog(LOG_WARNING, "Invoice ID: ". $invoiceId);
    syslog(LOG_WARNING, "Update API Results: ". $printUpdateInvoiceResults);
    syslog(LOG_WARNING, "Get API Results: ". $printGetInvoiceResults);
}

function hook_Suspend_Cloud_Account($vars) {
    global $applicationKey, $sharedSecret, $baseURL, $adminUser;
    $serviceId = $vars['serviceid'];
    $getClientIdFromServiceIdApiCommand = "getclientsproducts";
    $getClientIdFromServiceIdApiValues = array(
	'serviceid' => $serviceId
    );
    $results = localAPI($getClientIdFromServiceIdApiCommand,$getClientIdFromServiceIdApiValues,$adminUser);
    $clientId = $results['products']['product'][0]['clientid'];
    $serviceStatus = $results['products']['product'][0]['status'];
    $result = mysql_query("SELECT cloudAdminOrgId FROM SC_Rated_Usage WHERE clientId=" . $clientId . " LIMIT 1");
    while ($row = mysql_fetch_row($result)) {
       $cloudAdminOrgId = $row[0];
    };
    syslog(LOG_WARNING, "Get ServiceId from ClientId API Results: ". $results['products']['product'][0]['clientid'] .", " . $cloudAdminOrgId . " ," . $serviceStatus);
    
    if ($cloudAdminOrgId && $serviceStatus == "Suspended") {
        $resourceURL = "/organization/" . $cloudAdminOrgId . "/hold?hold=true";
        syslog(LOG_WARNING, "Resource URL: $resourceURL");
        $requestString = "PUT " . $resourceURL . " " . $applicationKey;
        $hash = base64_encode(hash_hmac('sha256', $requestString, $sharedSecret, TRUE));
        $credentials = base64_encode($applicationKey . ":" . $hash);
        syslog(LOG_WARNING, "Request String " . $requestString);
        $sendEmail = array(
            'sendEmail' => false
        );
        $sendEmailString = json_encode($sendEmail);
        $length = strlen($sendEmailString);
        $fh = fopen('php://memory', 'rw');
            fwrite($fh, $sendEmailString);
            rewind($fh);
        $ch = curl_init();
        curl_setopt_array($ch, array(
            #CURLINFO_HEADER_OUT => 1,
            #CURLOPT_HEADER => 1,
            CURLOPT_PUT => 1,
            CURLOPT_INFILE => $fh,
            CURLOPT_INFILESIZE => $length,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => $baseURL . $resourceURL,
            CURLOPT_HTTPHEADER => array("Accept: application/vnd.securedcloud.v4.0+json", "Authorization: SC {$credentials}", "Content-Type: application/vnd.securedcloud.v4.0+json")
        ));
        $response = curl_exec($ch);
        $jsonResponse = json_decode($response);
    } elseif ($cloudAdminOrgId && $serviceStatus == "Active") {
        $resourceURL = "/organization/" . $cloudAdminOrgId . "/hold?hold=false";
        $requestString = "PUT " . $resourceURL . " " . $applicationKey;
        $hash = base64_encode(hash_hmac('sha256', $requestString, $sharedSecret, TRUE));
        $credentials = base64_encode($applicationKey . ":" . $hash);
        $sendEmail = array(
            'sendEmail' => false
        );
        $sendEmailString = json_encode($sendEmail);
        $length = strlen($sendEmailString);
        $fh = fopen('php://memory', 'rw');
            fwrite($fh, $sendEmailString);
            rewind($fh);
        $ch = curl_init();
        curl_setopt_array($ch, array(
            #CURLINFO_HEADER_OUT => 1,
            #CURLOPT_HEADER => 1,
            CURLOPT_PUT => 1,
            CURLOPT_INFILE => $fh,
            CURLOPT_INFILESIZE => $length,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => $baseURL . $resourceURL,
            CURLOPT_HTTPHEADER => array("Accept: application/vnd.securedcloud.v4.0+json", "Authorization: SC {$credentials}", "Content-Type: application/vnd.securedcloud.v4.0+json")
        ));
        $response = curl_exec($ch);
        $jsonResponse = json_decode($response);
    } elseif ($cloudAdminOrgId && $serviceStatus == "Cancelled") {
        $resourceURL = "/organization/" . $cloudAdminOrgId . "/cancel";
        $requestString = "PUT " . $resourceURL . " " . $applicationKey;
        $hash = base64_encode(hash_hmac('sha256', $requestString, $sharedSecret, TRUE));
        $credentials = base64_encode($applicationKey . ":" . $hash);
        $sendEmail = array(
            'sendEmail' => false
        );
        $sendEmailString = json_encode($sendEmail);
        $length = strlen($sendEmailString);
        $fh = fopen('php://memory', 'rw');
            fwrite($fh, $sendEmailString);
            rewind($fh);
        $ch = curl_init();
        curl_setopt_array($ch, array(
            #CURLINFO_HEADER_OUT => 1,
            #CURLOPT_HEADER => 1,
            CURLOPT_PUT => 1,
            CURLOPT_INFILE => $fh,
            CURLOPT_INFILESIZE => $length,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => $baseURL . $resourceURL,
            CURLOPT_HTTPHEADER => array("Accept: application/vnd.securedcloud.v4.0+json", "Authorization: SC {$credentials}", "Content-Type: application/vnd.securedcloud.v4.0+json")
        ));
        $response = curl_exec($ch);
        $jsonResponse = json_decode($response);
    } elseif ($cloudAdminOrgId && $serviceStatus == "Terminated") {
        $resourceURL = "/organization/" . $cloudAdminOrgId . "/terminate";
        $requestString = "PUT " . $resourceURL . " " . $applicationKey;
        $hash = base64_encode(hash_hmac('sha256', $requestString, $sharedSecret, TRUE));
        $credentials = base64_encode($applicationKey . ":" . $hash);
        $sendEmail = array(
            'sendEmail' => false
        );
        $sendEmailString = json_encode($sendEmail);
        $length = strlen($sendEmailString);
        $fh = fopen('php://memory', 'rw');
            fwrite($fh, $sendEmailString);
            rewind($fh);
        $ch = curl_init();
        curl_setopt_array($ch, array(
            #CURLINFO_HEADER_OUT => 1,
            #CURLOPT_HEADER => 1,
            CURLOPT_PUT => 1,
            CURLOPT_INFILE => $fh,
            CURLOPT_INFILESIZE => $length,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => $baseURL . $resourceURL,
            CURLOPT_HTTPHEADER => array("Accept: application/vnd.securedcloud.v4.0+json", "Authorization: SC {$credentials}", "Content-Type: application/vnd.securedcloud.v4.0+json")
        ));
        $response = curl_exec($ch);
        $jsonResponse = json_decode($response);
    } 
    
}

add_hook("InvoiceCreationAdminArea",1,"hook_Reset_Usage_On_Invoice");
add_hook("DailyCronJob",2,"hook_Get_Daily_Usage");
add_hook("AdminServiceEdit",3,"hook_Suspend_Cloud_Account");
?>
