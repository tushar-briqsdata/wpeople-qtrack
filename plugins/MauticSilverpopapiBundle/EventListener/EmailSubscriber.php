<?php
namespace MauticPlugin\MauticSilverpopapiBundle\EventListener;

use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\EmailBuilderEvent;
use Mautic\EmailBundle\Event\EmailSendEvent;
use Mautic\DynamicContentBundle\Model\DynamicContentModel;
use Mautic\CampaignBundle\Model\EventModel;
use Mautic\CoreBundle\Helper\InputHelper;
//require_once '/opt/lampp/htdocs/wpeople/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use GuzzleHttp\Client as GuzzleClient;

class EmailSubscriber extends CommonSubscriber {

    /**
     * @var DynamicContentModel
     */
    protected $dynamicContentModel;
    public $client;
    public $silverpopconfig;
    public $database_list_id;
    public $aws_api_url;
    /**
     * EmailSubscriber constructor.
     *
     * @param DynamicContentModel   $dynamicContentModel
     * 
     */
    public function __construct() {
        //echo 'constructor';exit;
        $this->client = new GuzzleClient();
        $this->silverpopconfig = ['silverpopPod'=>3,'silverpopClientId'=>'15d2ff11-8023-4dc9-be4b-f57c967fac4b','silverpopClientSecret'=>'86714b70-d4c4-4323-b19b-5ea72a49b24b','silverpopRefreshToken'=>'r9hTGkInyn40Qf778pSUlGjfTBnnfo9edCk97-FV37C4S1'];
        $this->database_list_id = 9857903;
        $this->aws_api_url = 'https://9maq510jxb.execute-api.us-east-1.amazonaws.com/stage/';
    }

    static public function getSubscribedEvents() {
        return array(
            EmailEvents::EMAIL_BEFORE_SEND => array('onEmailGenerate', 0)
        );
    }

    public function onEmailGenerate(EmailSendEvent $event) {
        
        $campaignEventem = $this->em->getRepository('MauticCampaignBundle:Event');
        
        //$mail = new PHPMailer(true);                              // Passing `true` enables exceptions
        try {
            //Server settings
            /*$mail->SMTPDebug = 0;                                 // Enable verbose debug output
            $mail->isSMTP();                                      // Set mailer to use SMTP
            $mail->Host = 'smtp.gmail.com';  // Specify main and backup SMTP servers
            $mail->SMTPAuth = true;                               // Enable SMTP authentication
            $mail->Username = 'sameer.mehta@qdata.io';                 // SMTP username
            $mail->Password = 'briqs5464';                           // SMTP password
            // $mail->Username = 'jorgesalcedo_transact@qdata.io';                 // SMTP username
            // $mail->Password = 'India2017!';                           // SMTP password
            $mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
            $mail->Port = 587;*/                                    // TCP port to connect to
            //
//            $headers = $event->getHelper()->message->getHeaders();
//            $replyto = $headers->get('Reply-To')->getFieldBody();
//            echo $replyto;
//            echo "<br>";
            //print_r($headers->getAll());
            if (!$event->isInternalSend()) {
                
                $eventSource = $source = $event->getSource();
                $campaigneventData = $campaignEventem->find($source[1]);
                $lead_id = $event->getLead()["id"];
                $campaign_id = $campaigneventData->getCampaign()->getId();
                $contactlistName = $event->getEmail()->getId()."-".$eventSource[1]."-".$campaign_id;
                $toData = [];
                $fromData = [];
                $event->stopPropagation();
                $subject = $event->getSubject();
                $mailBody = $event->getHelper()->message->getBody();
                $headers = $event->getHelper()->message->getHeaders();
                $to = $headers->get('To')->getFieldBody();
                $from = $headers->get('From')->getFieldBody();
                if (preg_match('/(.*)<(.*)>/', $to, $toData)) {
                    $to_email = $toData[2];
                    $to_name = trim($toData[1]);
                } else {
                    $to_email = $to;
                    $to_name = "";
                }

                if (trim($from)) {
                    if (preg_match('/(.*)<(.*)>/', $from, $fromData)) {
                        $from_email = $fromData[2];
                        $from_name = $fromData[1];
                    } else {
                        $from_email = $from;
                        $from_name = "";
                    }
                }
                if ($headers->has('Reply-To')) {
                    $replyto = $headers->get('Reply-To')->getFieldBody();
                } else {
                    $replyto = $from_email;
                }
                /*$mail->addReplyTo($replyto);
                $mail->setFrom($from_email, $from_name);
                $mail->addAddress($to_email, $to_name);
                $mail->Subject = $subject . " :From plugin";
                $mail->Body = $mailBody;
                $mail->isHTML(true);*/
                $receipt_data = ['email'=>$to_email];
                //echo $to_email.'to_email'.$from_email.'from_email';exit;
                $resp_receipt_data = $this->lamdaApiUserReceiptData($receipt_data);
                //echo '<pre>';print_r($resp_receipt_data);
                //exit;
                $create_contact_data = ['email'=>$to_email];
                $resp_create_contact_data = $this->lamdaApiUserCreateContactData($create_contact_data);
                //echo 'resp_create_contact_data';echo '<pre>';print_r($resp_create_contact_data);
                $contactListId = $resp_create_contact_data['data']['data']['contactListId'];
                //echo $contactListId.'contactListId';exit;
                $add_contact_list_data = ['contact_list_id'=>$contactListId,'email'=>$to_email];
                $resp_add_contact_list_data = $this->lamdaApiUserAddContactListData($add_contact_list_data);
                //echo 'resp_add_contact_list_data';echo '<pre>';print_r($resp_add_contact_list_data);
                //exit;

                $create_mail_data = ['subject'=>$subject,
                                     'fromName'=>$from_name,   
                                     'email'=>$to_email,
                                     'fromAddress'=>$from_email,   
                                     'replyTo'=>$replyto,
                                     'folderPath'=>"QTRACK",   
                                     'listId'=>$contactListId,   
                                     'html'=>$mailBody,
                                    ];
                $resp_create_mail_data = $this->lamdaApiUserCreateMailData($create_mail_data);
                //echo '$resp_create_mail_data';echo '<pre>';print_r($resp_create_mail_data);

                $schedule_mail_data = ['templateId'=>$resp_create_mail_data['data']['data']['MailingID'],
                                     'listId'=>$contactListId,   
                                     'email'=>$to_email,
                                    ];
                //echo '<pre>';print_r($schedule_mail_data);exit;
                $resp_schedule_mail_data = $this->lamdaApiUserScheduleMailData($schedule_mail_data);
                //echo '<pre>';print_r($resp_schedule_mail_data);
                //exit;
                $current_date_time = date('m/d/Y h:i A');
                $lead_id = $event->getLead()['id'];
                $mail_id = $event->getIdHash();
                $insert_silverpop_data = ['mail_id'=>$mail_id,
                    'lead_id'=>$lead_id,
                    'campaign_id'=>$campaign_id,
                    'silverpop_recipientid'=>$resp_receipt_data['data']['data']['recipientId'],
                    'silverpop_contactlistid'=>$contactListId,
                    'silverpop_mailingid'=>$resp_create_mail_data['data']['data']['MailingID'],
                    'eventDateStart' => $current_date_time
                ];
                $db_insert_silverpop_data = $this->insertSilverPopData($insert_silverpop_data);

                $export_raw_data = ['templateId'=>$resp_create_mail_data['data']['data']['MailingID'],
                                     'listId'=>$contactListId,   
                                     'email'=>$to_email,
                                     'eventDateStart' => $current_date_time
                                    ];
                //echo '<pre>';print_r($export_raw_data);
                $resp_export_raw_data = $this->lamdaApiUserExportRawData($export_raw_data);
                //echo '<pre>';print_r($resp_export_raw_data);exit;
                //echo 'data inserted';exit;
                //$mail->send();
            }

            //Recipients
//            $mail->setFrom('noreply@wsuite.com', 'W-Suite');
//            $mail->addAddress('sameer.mehta@qdata.io', 'Sameer Mehta');     // Add a recipient
//
//            $mail->addReplyTo('noreply@wsuite.com', 'W-Suite');
            // Custom Header
//            $mail->addCustomHeader('X-SP-Transact-Id', '4d5d454sdf5g4s54g5s4sfg4s5g4');
//            $mail->addCustomHeader('From', 'noreply@wsuite.com');
//            $mail->addCustomHeader('To', 'sameer.mehta@qdata.io');
//            $mail->addCustomHeader('Subject', 'Here is the subject');
            //Content
//            $mail->isHTML(true);                                  // Set email format to HTML
//            $mail->Subject = 'Here is the subject';
//            $mail->Body = '<a download="" href="https://s3.amazonaws.com/static.wsuite.com/howtu/DESCARGABLE+Gu%C3%ADa+viajera+Los+15+lugares+m%C3%A1s+hermosos+de+Colombia.pdf" style="background: #009EE3; color: #ffffff; text-decoration: none; padding: 13px 55px; text-transform: uppercase; display: block; text-align: center; font-size: 20px; border-radius: 30px; font-weight: bold; letter-spacing: -0.38px; font-family: Nunito;">DESCARGAR</a>';
//            $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';
//            $mail->send();
//            echo 'Message has been sent';
        } catch (\Exception $e) {
//            echo 'Message could not be sent. Mailer Error: ', $mail->ErrorInfo;
        }
    }

    public function insertSilverPopData($insert_silverpop_data = []){
        
        $q = $this->em->getConnection()->createQueryBuilder();
        
        $q->insert(MAUTIC_TABLE_PREFIX.'silverpop_data')->values(['mail_id' => '?', 'lead_id' => '?', 'campaign_id' => '?','silverpop_recipientid' => '?','silverpop_contactlistid' => '?','silverpop_mailingid' => '?','eventDateStart' => '?'])->setParameter(0, $insert_silverpop_data['mail_id'])->setParameter(1, $insert_silverpop_data['lead_id'])->setParameter(2, $insert_silverpop_data['campaign_id'])->setParameter(3, $insert_silverpop_data['silverpop_recipientid'])->setParameter(4, $insert_silverpop_data['silverpop_contactlistid'])->setParameter(5, $insert_silverpop_data['silverpop_mailingid'])->setParameter(6, $insert_silverpop_data['eventDateStart']);

        $q->execute();

    }
    public function lamdaApiUserReceiptData($receipt_data = array()){

        $return_data = [];
        try {
            $user_email = $receipt_data['email'];
            //$user_email = 'harshshah1020120120@qdata.io';
            $user_receipt_data = ['silverpopConfig'=>
                $this->silverpopconfig,
                'listId' => $this->database_list_id,
                'columns' => ['email'=>$user_email]
            ];
            /*echo 'inputuser_receipt_data';
                print_r($user_receipt_data);                                    */
            $res = $this->client->request('POST', $this->aws_api_url.'database/addreceipient', [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode($user_receipt_data)
            ]);

            $res->getHeader('content-type');
            $ress_body = json_decode($res->getBody(),true);
            //echo '<pre>';print_r($ress_body);exit;
            $data = $ress_body;
            if(!is_array($ress_body)){
                $msg = 'error in response of api';
                throw new \Exception($msg,0);    
            }
            
            /*if(!is_array($ress_body['data'])){
                $msg = 'error in response data of api';
                throw new \Exception($msg,0);   
            }*/
            if(!isset($ress_body['data'])){
                $msg = 'error in response data of api';
                throw new \Exception($msg,0);   
            }
            
            //$user_email_id = 'harshshah@qdata.io';
            $receipt_id = $ress_body['data']['recipientId'];

            $q = $this->em->getConnection()->createQueryBuilder();
            /*$q->update(MAUTIC_TABLE_PREFIX.'users')
                ->set('silverpop_recipientid', $receipt_id)
                ->where('email = "'.$user_email.'"');

            $q->execute();*/
            
            $msg = 'succesfully data';
            throw new \Exception($msg,1);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            $isuccess=$e->getCode();
        }
        $return_data['message'] = $msg;
        $return_data['success'] = $isuccess;
        $return_data['data'] = $data;
        return $return_data;
    }

    public function lamdaApiUserCreateContactData($create_contact_data = array()){

        $return_data = [];
        try {
            $user_email = $create_contact_data['email'];
            //$user_email = 'harshshah0000336688995@qdata.io';
            $user_create_contact_data = ['silverpopConfig'=>
                $this->silverpopconfig,
                'databaseId' => $this->database_list_id,
                'contactListName' => $user_email.time(),
                'visibility' => 1
            ];
            /*echo 'inputuser_create_contact_data';
                print_r($user_create_contact_data);*/                                    
            $res = $this->client->request('POST', $this->aws_api_url.'database/contactlist/create', [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode($user_create_contact_data)
            ]);

            $res->getHeader('content-type');
            $ress_body = json_decode($res->getBody(),true);
            //echo '<pre>';print_r($ress_body);exit;
            $data = $ress_body;
            if(!is_array($ress_body)){
                $msg = 'error in response of api';
                throw new \Exception($msg,0);    
            }
            
            /*if(!is_array($ress_body['data'])){
                $msg = 'error in response data of api';
                throw new \Exception($msg,0);   
            }*/
            if(!isset($ress_body['data'])){
                $msg = 'error in response data of api';
                throw new \Exception($msg,0);   
            }
            
            //$user_email_id = 'harshshah@qdata.io';
            $contactListId = $ress_body['data']['contactListId'];

            /*$q = $this->em->getConnection()->createQueryBuilder();

            $q->update(MAUTIC_TABLE_PREFIX.'users')
                ->set('silverpop_contactlistid', $contactListId)
                ->where('email = "'.$user_email.'"');

            $q->execute();*/
            
            $msg = 'succesfully data';
            throw new \Exception($msg,1);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            $isuccess=$e->getCode();
        }
        $return_data['message'] = $msg;
        $return_data['success'] = $isuccess;
        $return_data['data'] = $data;
        return $return_data;
    }

    public function lamdaApiUserAddContactListData($add_contact_list_data = array()){

        $return_data = [];
        try {
            $user_email = $add_contact_list_data['email'];
            //$user_email = 'harshshah@qdata.io';
            $contact_list_id = $add_contact_list_data['contact_list_id'];
            $user_add_contact_list_data = ['silverpopConfig'=>
                $this->silverpopconfig,
                'contactListId' => $contact_list_id,
                'columns' => ['email'=>$user_email],
            ];
            /*echo 'inputuser_add_contact_list_data';
            print_r($user_add_contact_list_data);*/
            $res = $this->client->request('POST', $this->aws_api_url.'database/contactlist/addcontact', [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode($user_add_contact_list_data)
            ]);

            $res->getHeader('content-type');
            $ress_body = json_decode($res->getBody(),true);
            //echo '<pre>';print_r($ress_body);exit;
            $data = $ress_body;
            if(!is_array($ress_body)){
                $msg = 'error in response of api';
                throw new \Exception($msg,0);    
            }
            
            /*if(!is_array($ress_body['data'])){
                $msg = 'error in response data of api';
                throw new \Exception($msg,0);   
            }*/
            if(!isset($ress_body['data'])){
                $msg = 'error in response data of api';
                throw new \Exception($msg,0);   
            }
            
            $msg = 'succesfully data';
            throw new \Exception($msg,1);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            $isuccess=$e->getCode();
        }
        $return_data['message'] = $msg;
        $return_data['success'] = $isuccess;
        $return_data['data'] = $data;
        return $return_data;
    }

    public function lamdaApiUserCreateMailData($create_mail_data = array()){

        $return_data = [];
        try {
            $user_email = $create_mail_data['email'];
            //$user_email = 'harshshah@qdata.io';
            $user_create_mail_data = ['silverpopConfig'=>
                $this->silverpopconfig,
                'subject' => $create_mail_data['subject'],
                'mailingName' => $user_email.time(),
                'fromName' => $create_mail_data['fromName'],
                'fromAddress' => $create_mail_data['fromAddress'],
                'replyTo' => $create_mail_data['replyTo'],
                'folderPath' => $create_mail_data['folderPath'],
                'listId' => $create_mail_data['listId'],
                'html' => $create_mail_data['html'],
            ];
            /*echo 'user_create_mail_data';
            echo '<pre>';
            print_r($user_create_mail_data);
            exit;*/
            /*echo 'inputuser_create_mail_data';
            print_r($user_create_mail_data);*/
            $res = $this->client->request('POST', $this->aws_api_url.'mailing/create', [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode($user_create_mail_data)
            ]);

            $res->getHeader('content-type');
            $ress_body = json_decode($res->getBody(),true);
            //echo '<pre>';print_r($ress_body);exit;
            $data = $ress_body;
            if(!is_array($ress_body)){
                $msg = 'error in response of api';
                throw new \Exception($msg,0);    
            }
            
            /*if(!is_array($ress_body['data'])){
                $msg = 'error in response data of api';
                throw new \Exception($msg,0);   
            }*/
            if(!isset($ress_body['data'])){
                $msg = 'error in response data of api';
                throw new \Exception($msg,0);   
            }
            
            //$user_email_id = 'harshshah@qdata.io';
            //$user_email_id = $create_mail_data['email'];
            $MailingID = $ress_body['data']['MailingID'];

            /*$q = $this->em->getConnection()->createQueryBuilder();

            $q->update(MAUTIC_TABLE_PREFIX.'users')
                ->set('silverpop_mailingid', $MailingID)
                ->where('email = "'.$user_email.'"');

            $q->execute();*/

            $msg = 'succesfully data';
            throw new \Exception($msg,1);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            $isuccess=$e->getCode();
        }
        $return_data['message'] = $msg;
        $return_data['success'] = $isuccess;
        $return_data['data'] = $data;
        return $return_data;
    }

    public function lamdaApiUserScheduleMailData($schedule_mail_data = array()){

        $return_data = [];
        try {
            $user_email = $schedule_mail_data['email'];
            //$user_email = 'harshshah@qdata.io';
            $user_schedule_mail_data = [
                    'silverpopConfig'=>$this->silverpopconfig,
                    'templateId' => $schedule_mail_data['templateId'],
                    'listId' => $schedule_mail_data['listId'],
                    'mailingName' => $user_email.time(),
                    'parentFolder' => 'QTRACK/',
                    'sendHtml' => true,
                ];
            /*echo 'user_schedule_mail_data';
            echo '<pre>';
            print_r($user_schedule_mail_data);exit;*/
            /*echo 'inputuser_schedule_mail_data';
            print_r($user_schedule_mail_data);*/
            $res = $this->client->request('POST', $this->aws_api_url.'mailing/schedule', [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode($user_schedule_mail_data)
            ]);

            $res->getHeader('content-type');
            $ress_body = json_decode($res->getBody(),true);
            //echo '<pre>';print_r($ress_body);exit;
            $data = $ress_body;
            if(!is_array($ress_body)){
                $msg = 'error in response of api';
                throw new \Exception($msg,0);    
            }
            
            if(!isset($ress_body['data'])){
                $msg = 'error in response data of api';
                throw new \Exception($msg,0);   
            }
            /*if(!is_array($ress_body['data'])){
                $msg = 'error in response data of api';
                throw new \Exception($msg,0);   
            }*/
            
            //$user_email_id = 'harshshah@qdata.io';
            //$user_email_id = $create_mail_data['email'];
            $MailingID = $ress_body['data']['mailingId'];

            /*$q = $this->em->getConnection()->createQueryBuilder();

            $q->update(MAUTIC_TABLE_PREFIX.'users')
                ->set('silverpop_mailingid', $MailingID)
                ->where('email = "'.$user_email_id.'"');

            $q->execute();*/

            $msg = 'succesfully data';
            throw new \Exception($msg,1);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            $isuccess=$e->getCode();
        }
        $return_data['message'] = $msg;
        $return_data['success'] = $isuccess;
        $return_data['data'] = $data;
        return $return_data;
    }

    public function lamdaApiUserExportRawData($export_raw_data = array()){

        $return_data = [];
        try {
            $user_email = $export_raw_data['email'];
            //$user_email = 'harshshah@qdata.io';
            $user_export_raw_data = [
                    'silverpopConfig'=>$this->silverpopconfig,
                    'templateId' => $export_raw_data['templateId'],
                    'listId' => $export_raw_data['listId'],
                    'mailingName' => $user_email.time(),
                ];
            //echo '<pre>';print_r($user_export_raw_data);exit;
            $res = $this->client->request('POST', $this->aws_api_url.'reports/exportrawdata', [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode($user_export_raw_data)
            ]);

            $res->getHeader('content-type');
            $ress_body = json_decode($res->getBody(),true);
            //echo '<pre>';print_r($ress_body);exit;
            $data = $ress_body;
            if(!is_array($ress_body)){
                $msg = 'error in response of api';
                throw new \Exception($msg,0);    
            }
            
            if(!is_array($ress_body['data'])){
                $msg = 'error in response data of api';
                throw new \Exception($msg,0);   
            }
            
            //$user_email_id = 'harshshah@qdata.io';
            //$user_email_id = $create_mail_data['email'];
            //$MailingID = $ress_body['data']['MailingID'];

            /*$q = $this->em->getConnection()->createQueryBuilder();

            $q->update(MAUTIC_TABLE_PREFIX.'users')
                ->set('silverpop_mailingid', $MailingID)
                ->where('email = "'.$user_email_id.'"');

            $q->execute();*/

            $msg = 'succesfully data';
            throw new \Exception($msg,1);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            $isuccess=$e->getCode();
        }
        $return_data['message'] = $msg;
        $return_data['success'] = $isuccess;
        $return_data['data'] = $data;
        return $return_data;
    }

    public function lamdaApiUserReportData($report_data = array()){


    }
}
