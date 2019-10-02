<?php
// SOAP_CLIENT_BASEDIR - folder that contains the PHP Toolkit and your WSDL
// $USERNAME - variable that contains your Salesforce.com username (must be in the form of an email)
// $PASSWORD - variable that contains your Salesforce.ocm password


use Trello\Client;
use Trello\Manager;

set_time_limit(0);
ini_set("memory_limit", "1024M");
defined('APPLICATION_PATH') || define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/'));
include APPLICATION_PATH . "/../vendor/autoload.php";
define("SOAP_CLIENT_BASEDIR", APPLICATION_PATH . "/../soapclient");
$USERNAME = 'sfforce@alquadri.co.uk';
$PASSWORD = 'alquadri' . 'N9zQ3gkSHnOWIqJWRYoALycX';
@$SOAP = 'partner.wsdl.xml';
$Trello_key = 'd28ceb052acd6b40e3a6eb77fdf9bee3';
$Trello_auth = '23b39119f523980bb02858b71bc463ef791b4f1c997eb7939bd657600e586b92';

require_once(SOAP_CLIENT_BASEDIR . '/SforcePartnerClient.php');
require_once(SOAP_CLIENT_BASEDIR . '/SforceHeaderOptions.php');

try {

    #### SalesForce Connection
    $mySforceConnection = new SforcePartnerClient();
    $mySoapClient = $mySforceConnection->createConnection(SOAP_CLIENT_BASEDIR . '/' . $SOAP);
    $mylogin = $mySforceConnection->login($USERNAME, $PASSWORD);
    #print_r($mySforceConnection->getUserInfo());

    #### Trello Connection
    $client = new Client();
    $client->authenticate($Trello_key, $Trello_auth, Client::AUTH_URL_CLIENT_ID);

    ############
    ### Get all the Salesforce Cases
    ############
    $caseDetails = array();
    $count = 1;

    try {


        #### NEW CASES WITHIN THE BUSINESS
        $query1 = "SELECT Id, CaseNumber, Account.name, Subject, Status,Case_Queue__c, 
              Technical_Category__c, Service__c, Service_Complexity__c, Case_Age_In_Business_Hours__c,
              SLA_Response_Given__c, SLA_Response_Time_Left__c, SLA_Resolution_Time_Left__c,
              LastModifiedDate, Project__c, Project_Name__c,
              Priority, Red_Flag__c,Product_Type__c, 
              OwnerId, Owner.name, Completed_By__c,CreatedById,CreatedDate
              , Latest_Comment__c,Description, SLA_Complete_Date__c, SLA_Complete_DateTime__c
              FROM Case WHERE  status = 'New' and (Case_Queue__c = 'NEW Enquiry' OR Case_Queue__c = 'Tech HUB' ) ";


        ###### Get the list of SF records based on the query
        foreach ($mySforceConnection->query($query1)->records as $record) {
            $sObject = new SObject($record);
            $caseDetails[$count] = array('CaseID' => $sObject->Id);
            $fields_keys = array_keys((array)$sObject->fields);

            foreach ($fields_keys as $field) {
                if (in_array($field, array('Owner', 'Account'))) {
                    $field_name = (isset($sObject->{$field}->fields->Name)) ? $sObject->{$field}->fields->Name : "";
                    $caseDetails[$count][$field] = $field_name;
                } else {
                    $caseDetails[$count][$field] = $sObject->{$field};
                }
            }

            #### Get the Email Messages for each case id
            $email_query = "SELECT FromAddress, FromName,HasAttachment,Id  FROM EmailMessage WHERE ParentId = '" . $sObject->Id . "' limit 1";
            foreach ($mySforceConnection->query($email_query)->records as $Erecord) {
                $EmailsObject = new SObject($Erecord);
                $Email_keys = array_keys((array)$EmailsObject->fields);
                foreach ($Email_keys as $field) {
                    $caseDetails[$count]["EMAIL_MESSAGE_" . $field] = $EmailsObject->{$field};
                }
            }
            $count++;

        }

    } catch (Exception $e) {
        echo "ERROR 01 ",print_r($e);
    }




    try {
        #### ALL cases which are not closed
        $query2 = "SELECT Id, CaseNumber, Account.name, Subject, Status,Case_Queue__c, 
              Technical_Category__c, Service__c, Service_Complexity__c, Case_Age_In_Business_Hours__c,
              SLA_Response_Given__c, SLA_Response_Time_Left__c, SLA_Resolution_Time_Left__c,
              LastModifiedDate, Project__c, Project_Name__c,
              Priority, Red_Flag__c,Product_Type__c, 
              OwnerId, Owner.name, Completed_By__c,CreatedById,CreatedDate
              , Latest_Comment__c,Description, SLA_Complete_Date__c, SLA_Complete_DateTime__c  
              FROM Case WHERE status <> 'Closed' AND Case_Queue__c = 'Tech HUB'";

        ###### Get the list of records based on the query
        foreach ($mySforceConnection->query($query2)->records as $record) {
            $sObject = new SObject($record);
            $caseDetails[$count] = array('CaseID' => $sObject->Id);
            $fields_keys = array_keys((array)$sObject->fields);

            foreach ($fields_keys as $field) {
                if (in_array($field, array('Owner', 'Account'))) {
                    $field_name = (isset($sObject->{$field}->fields->Name)) ? $sObject->{$field}->fields->Name : "";
                    $caseDetails[$count][$field] = $field_name;
                } else {
                    $caseDetails[$count][$field] = $sObject->{$field};
                }
            }

            #### Get the Email Messages for each case id
            $email_query = "SELECT FromAddress, FromName,HasAttachment,Id,HtmlBody  FROM EmailMessage WHERE ParentId = '" . $sObject->Id . "' limit 1";
            foreach ($mySforceConnection->query($email_query)->records as $Erecord) {
                $EmailsObject = new SObject($Erecord);
                $Email_keys = array_keys((array)$EmailsObject->fields);
                foreach ($Email_keys as $field) {
                    $caseDetails[$count]["EMAIL_MESSAGE_" . $field] = $EmailsObject->{$field};
                }
            }

            #### Get the Comments for each case id
            $caseDetails[$count]["AllComments"] = array();
            $comment_query = "SELECT CommentBody FROM CaseComment where parentId = '" . $sObject->Id . "'";
            foreach ($mySforceConnection->query($comment_query)->records as $record) {
                $Object = new SObject($record);
                $keys = array_keys((array)$Object->fields);
                foreach ($keys as $field) {
                    $caseDetails[$count]["AllComments"][] = $Object->{$field};
                }
            }
            $count++;

        }
    } catch (Exception $e) {
        print_r($e);
    }



    foreach ($caseDetails as $key => $caseDetail) {

        ####
        ## Trello Card Manager
        ####

        #if($caseDetail['CaseNumber'] !== '00079215' )continue;

        if((empty($caseDetail['SLA_Complete_DateTime__c']))){
            ### Requires triage
            $sf_date_time = new DateTime();
            $sf_date_time->modify('+ 3 hour');
        } else {
            ### Requires resolution
            $sf_date_time =  new DateTime($caseDetail['SLA_Complete_DateTime__c']);
        }


        $SFCardName             = $caseDetail['CaseNumber'] . " - " . $caseDetail['Subject'];
        $SFdescription          = strip_tags($caseDetail['Description']);
        $SF_CaseUrl             = "https://um2.salesforce.com/" . $caseDetail['CaseID'];
        $listID                 = "56bdba64c680e9f4b97ef95c"; #Support board backlog
        $clientComments         = array();
        $last_unread_comment    = false;

        if (in_array($caseDetail['Status'], array('Scheduled and/or Detailed Response Sent','Complete','Under Review', 'Assigned to Department', 'Further Action Required', 'Client Action Required', 'Closed Case Re-Opened'))) {

            ###############################
            ### UnArchive / create Card ###
            ###############################

            ##### IDENTIFY TRELLO CARD #####
            $cardID = find_trello_card($caseDetail['CaseNumber']);
            #echo "TRYING ".$caseDetail['CaseNumber']." and got ".$cardID." \n";

            if (!empty($cardID)) {
                ## Trello Card already exists.  No need to create another one.
                $card_id_ = $cardID;

                try {
                    $manager = new Manager($client);
                    $card = $manager->getCard($card_id_);
                    if ($caseDetail['Status'] == 'Closed Case Re-Opened') $card->addComment("Closed Case Re-Opened");
                    if ($caseDetail['Status'] !== 'Complete') $card->setClosed(0);
                    $card->save();
                } catch(Exception $e){
                    #echo "ERROR 01.01".$e;
                }

            } else {
                ## Create Trello card
                $manager = new Manager($client);
                $card = $manager->getCard();
                $card->setName($SFCardName)->setListId($listID)->setDescription($SFdescription)->addComment($SF_CaseUrl)->setClosed(0);
                if($caseDetail['Technical_Category__c'] !== 'Project') $card->setDueDate($sf_date_time);
                $card->save();
                $card_id_ = $card->getId();  # Created card id
            }

            #### Obtain all the Salesforce ATTACHMENTS ####
            $params = array();
            $attachments = $client->cards()->attachments()->all($card_id_, $params);
            $attach=true;
            foreach($attachments as $attachment){
                $all_card_attachments[] = trim(str_replace(array('https://eu1.', 'https://um2.'), "" , $attachment['url']));
            }

            $SF_CaseUrl_c = str_replace(array('https://eu1.', 'https://um2.'), "" , $SF_CaseUrl);
            if(in_array($SF_CaseUrl_c, $all_card_attachments) ||  in_array(substr($SF_CaseUrl_c, 0, -3), $all_card_attachments) ){
                #ignore adding sf url
            } else {
                $attachment = array('url'=>$SF_CaseUrl);
                $client->cards()->attachments()->create($card_id_, $attachment);
            }

            try {
                $timezone = new DateTimeZone('Europe/London');
                #Trello Date
                $DateTimeOnCard = $manager->getCard($card_id_)->getDueDate();
                $DateTimeOnCard->setTimezone($timezone);
                #Salesforce Date
                $SFCompleteDateTime = new DateTime($caseDetail['SLA_Complete_DateTime__c']);
                $SFCompleteDateTime->setTimezone($timezone);
                echo "qwqwewq\n".$DateTimeOnCard->format('Y-m-d h')." qweqw\n".$SFCompleteDateTime->format('Y-m-d h');
                //$DateTimeOnCard->format('Y-m-d h') !== $SFCompleteDateTime->format('Y-m-d h')
                if (!empty($caseDetail['SLA_Complete_DateTime__c']) && strcmp($DateTimeOnCard->format('Y-m-d h'), $SFCompleteDateTime->format('Y-m-d h')) !== 0 &&  $caseDetail['Technical_Category__c'] !== 'Project') {
                    echo "\n Came in to change \n";
                    $card->setDueDate($sf_date_time)->save();
                } else {
                    echo "\n Skipped";
                }
            }catch(Exception $e){
                print_r($e);
            }


            #############################
            ### Synchoronize Comments ###
            #############################

            ### Collect all the comments from Trello:
            $allTrelloComments = array();
            $manager = new Manager($client);
            $card = $manager->getCard($card_id_);
            $actions = $card->getActions();   ### Get all the actions that include the comment
            foreach ($actions as $action) {
                if ($action['type'] != 'commentCard') continue;
                $allTrelloComments[] = $action['data']['text'];
            }
            ### Collect all the comments from Salesforce;
            $allSalesforceComments = is_array($caseDetail['AllComments']) ? $caseDetail['AllComments'] : array();



            ###### Email From Client ######
            $efc = "SELECT CreatedDate,FromAddress,FromName,HasAttachment,MessageDate,Status,Subject,TextBody,ParentId FROM EmailMessage WHERE ParentId = '".$caseDetail['CaseID']."' and Status = '0' limit 1";
            foreach ($mySforceConnection->query($efc)->records as $record) {
                $Object = new SObject($record);
                $keys = array_keys((array)$Object->fields);
                foreach ($keys as $field) {
                    $clientComments[$field] = $Object->{$field};
                }
            }


            if(count($clientComments)>0){
                $allSalesforceComments[] = $clientsLatestComment = substr($clientComments['TextBody'], 0, strpos($clientComments['TextBody'], "Original Message")) ;
                $last_unread_comment = true;
            }

            ### UPDATE SALESFORCE CASE and Trello card ###
            if ($caseDetail['Status'] == 'Closed Case Re-Opened' || $last_unread_comment == true) {
                $updateFields = array('Id' => $caseDetail['CaseID'], 'Status' => 'Under Review', 'Case_Queue__c' => 'Tech HUB');
                $sObject1 = new SObject();
                $sObject1->fields = $updateFields;
                $sObject1->type = 'Case';
                $updateResponse = $mySforceConnection->update(array($sObject1));
               try{
                   if ($caseDetail['Status'] == 'Client Action Required') {
                       echo "TRYING to add client response \n";
                       $card->addComment("Client has responded");
                       $card->save();
                   }
               }catch(Exception $e){
                       echo "ERROR 01.02".$e;
               }
            }


            #print_r($allSalesforceComments); exit;
            //print_r($allTrelloComments);


            ### Salesforce to Trello
            if (count($allSalesforceComments)>0) {  ## Latest comment will tell that there are comments to consider
                ## Trello Connect
                foreach ($allSalesforceComments as $SComment) {
                    $addComment = true;
                    foreach ($allTrelloComments as $TComment) {
                        $TComment_ = trim(str_ireplace("#publish", "", $TComment));
                        $SComment_ = trim($SComment) ;
                        if ($TComment_ == $SComment_) {
                            $addComment = false;
                        }
                    }

                    if ($addComment && !preg_match('/@card/i', $SComment_)) {

                        try{
                            $card->addComment($SComment_)->save();
                        }catch(Exception $e){
                            echo "ERROR 02 ", print_r($e);
                        }

                    }

                }
            }





            ### Trello to Salesforce
            if (!empty($allTrelloComments)) {
                foreach ($allTrelloComments as $TComment) {
                    # check if comment contains #publish
                    if (preg_match('/#publish/i', $TComment)) {
                        # Check if it is already not on SF
                        $TCommentToPublish = trim(str_ireplace("#publish", "", $TComment));
                        if (!in_array($TCommentToPublish, $allSalesforceComments)) {
                            $updateFields = array();
                            $updateFields = array('parentId' => $caseDetail['CaseID'], 'CommentBody' => htmlentities($TCommentToPublish)  );
                            $sObject1 = new SObject();
                            $sObject1->fields = $updateFields;
                            $sObject1->type = 'CaseComment';
                            $updateResponse = $mySforceConnection->create(array($sObject1));
                        }

                    }
                }
            }


        }


        if ($caseDetail['Status'] == 'New') {

            #### Create a Trello Card for the case
            $manager = new Manager($client);
            $card = $manager->getCard();
            $date = new DateTime();
            $date->modify('+ 3 hour');

            $card->setName($SFCardName)->setListId($listID)->addComment($SF_CaseUrl)->setDueDate($date)->save();
            $card_id_ = $card->getId();  # Created card id
            try{
                $card->setDescription($SFdescription)->save();
            }catch(Exception $e){
                echo $e;
            }

            $attachment = array('url'=>$SF_CaseUrl);
            $client->cards()->attachments()->create($card_id_, $attachment);

            #### Accept the Salesforce case
            $updateFields = array('Id' => $caseDetail['CaseID'], 'Status' => 'Assigned to Department', 'Case_Queue__c' => 'Tech HUB', );
            $sObject1 = new SObject();
            $sObject1->fields = $updateFields;
            $sObject1->type = 'Case';
            $updateResponse = $mySforceConnection->update(array($sObject1));


        }


    }


} catch (Exception $e) {
    echo "ERROR 03 ", print_r($e);
    #print_r($caseDetail);
}


function search_trello_card($array, $pattern)
{
    $keys = array_keys($array);
    foreach ($keys as $key) {
        //If the key is found in your string, set $found to true
        if (preg_match("/" . $pattern . "/", $key)) {
            return $key;
        }
    }
}



function find_trello_card($case_number)
{

    $service_url = 'https://api.trello.com/1/';
    $service_url .= "search?query='$case_number'";
    $service_url .= "&closed=false&card_fields=name&partial=true&key=d28ceb052acd6b40e3a6eb77fdf9bee3&token=23b39119f523980bb02858b71bc463ef791b4f1c997eb7939bd657600e586b92&modelTypes=cards";

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $service_url);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_BINARYTRANSFER,1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST,false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER,false);
    curl_exec($curl);

    $curl_info = curl_getinfo($curl);
    $casedetails = json_decode(curl_exec($curl));


    return ($curl_info['http_code'] != '200') ? false : $casedetails->cards[0]->id;

}


?>
