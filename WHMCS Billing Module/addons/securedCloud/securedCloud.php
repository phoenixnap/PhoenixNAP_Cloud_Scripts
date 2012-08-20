<?php

function securedCloud_config() {
    $configarray = array(
    "name" => "PhoenixNAP SecuredCloud Reseller Module",
    "description" => "This is the reseller billing module for PhoenixNAP SecuredCloud",
    "version" => "1.0",
    "author" => "Phoenix NAP",
    "language" => "english"
    );
    return $configarray;
}

function securedCloud_activate() {

    # Create Custom DB Table
    $query = "CREATE TABLE `SC_Rated_Usage` (clientId INT(12),cloudAdminOrgId INT(12),lastBillingUpdate DATETIME,billingTotal VARCHAR(255),RID int(11) NOT NULL auto_increment,primary KEY (RID)) TYPE=innodb";
        $result = mysql_query($query);

    # Return Result
    return array('status'=>'success','description'=>'Thank you for activating the SecuredCloud WHMCS module');
    return array('status'=>'error','description'=>'You can use the error status return to
           indicate there was a problem activating the module');
    return array('status'=>'info','description'=>'You can use the info status return to display
           a message to the user');

}

function securedCloud_deactivate() {
 
    # Remove Custom DB Table
    $query = "DROP TABLE `SC_Rated_Usage`";
	$result = mysql_query($query);
 
    # Return Result
    return array('status'=>'success','description'=>'If successful, you can return a message
           to show the user here');
    return array('status'=>'error','description'=>'If an error occurs you can return an error
           message for display here');
    return array('status'=>'info','description'=>'If you want to give an info message to a user
           you can return it here');
 
}

?>
