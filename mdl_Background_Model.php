<?php

defined('BASEPATH') OR exit('No direct script access allowed');
ob_start();

class Background_Model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->model('Users_Model');
        $this->load->model('Common_Model');        
        $this->load->model('Notification_Model','Notification');
        $this->load->model('ContactUs_Model');
        $this->load->model('Ticket_Model');
        $this->load->model('Auth_Model');
        $this->load->library('fcm');
        $this->load->library('voipappleuser');
        $this->load->library('voipappledoctor');
        $this->load->library('Twilio');
		$this->load->model('Chat_Model');
    }


    public function createEventGoogleCalender($data) {
        require_once('application/controllers/google-calendar-api.php');
        $this->load->model('User_Appointment_Model');

		$site_url = current_url();
		$client_id = getenv('GOOGLE_KEY');
		$client_secret = getenv('GOOGLE_SECRET');
		$rurl = base_url()."google/calendar";

        try {
            if(count($data) != 0) {
                foreach($data AS $k => $v) {
                    $event_time = [];
                    $event_time["start_time"] = $v["date"]."T".date("h:i:s", strtotime($v["stime"]));
                    $event_time["end_time"] = $v["date"]."T".date("h:i:s", strtotime($v["etime"]));
                    $capi = new GoogleCalendarApi();
                    $user_timezone = $capi->GetUserCalendarTimezone($v['accessToken']);
                    $event_id = $capi->CreateCalendarEvent('primary', $v['title'], 0, $event_time, $user_timezone, $v['accessToken']);

                    if(!empty($event_id)) {
                        $apid = $v['aid'];
                        if($k == "doctor") {
                            $arr = [
                                "doctorGToken" => $v['refreshToken'],
                                "doctorGEventId" => $event_id
                            ];
                            $this->User_Appointment_Model->setData($arr, $apid);
                        }
                        if($k == "user") {
                            $arr = [
                                "userGToken" => $v['refreshToken'],
                                "userGEventId" => $event_id
                            ];
                            $this->User_Appointment_Model->setData($arr, $apid);
                        }                    
                    }
                }
            }
            return true;
        }
        catch (Exception $e) {
            return false;
        }
    }


    public function C009C_appointment_payment_72hourse_model(){ //cron job - payment deduction
        $this->load->model('User_Appointment_Model');
        $this->load->model('User_Transaction_Model');
        $this->load->model('User_Card_Model');
        $getData = $this->User_Appointment_Model->get(['status'=>'1', 'isFreeConsult'=>'0', 'paymentStatus'=>'0', 'getAppointmentPanding72HoursPaymentData' => true, 'getAvailabilityData'=>true,'planData'=>true, 'checkPlanApprove'=>true]);
        error_log("\n\n -------------------------------------" . date('c'). " \n Request => ".json_encode($getData), 3, FCPATH.'worker/getAppointmentPanding72HoursPaymentData-'.date('d-m-Y').'.txt');
        if(!empty($getData)){
            foreach($getData as $value){
                if(!empty($value->userCardId)){
                    $userCardData = $this->User_Card_Model->get(['id'=>$value->userCardId], true);
                    if(!empty($userCardData)){
                        $this->load->library('stripe',array('type'=>'1'));
                        
                        $stripeChargeData['customer'] = $userCardData->customerId;
                        $stripeChargeData['source'] = $userCardData->cardId;
                        $stripeChargeData['amount'] = $value->price * 100;
                        $stripeChargeData['capture'] = false;        
                        $stripeChargeData['description'] ="Book Appointment Payment, userId: #".$value->userId.", doctorId: #".$value->doctorId.", userCardId: #".$userCardData->id." , doctorAvailabilityId: #".$value->userAvailabilityId.", appointmentId: ".$value->id;
                        $response = $this->stripe->addCharge($stripeChargeData);
                        // echo "<pre>";print_r($response);die;
                        
                        error_log("\n\n -------------------------------------" . date('c'). " \n Request => ".json_encode($stripeChargeData) . " \n Response => ".json_encode($response,true), 3, FCPATH.'worker/bookAppoinmentPayment-'.date('d-m-Y').'.txt');
                        if(isset($response['error'])){ 
                            $this->User_Appointment_Model->setData(['cancelreason'=>3],$value->id); //1-by Client, 2-by Provider, 3-Funds Unavailable
                            //$this->Background_Model->appointmentNoPayment($value);
                            $this->appointmentNoPayment($value);
                        }elseif(!isset($response->id) || $response->id==""){ 
                            $this->User_Appointment_Model->setData(['cancelreason'=>3],$value->id); //1-by Client, 2-by Provider, 3-Funds Unavailable
                            //$this->Background_Model->appointmentNoPayment($value);
                            $this->appointmentNoPayment($value);
                        }else{
                            // For user transaction record
                            $transactionData = array();
                            $transactionData['userId'] = $value->userId;
                            $transactionData['userIdTo'] = $value->doctorId;
                            $transactionData['cardId'] = $userCardData->id;
                            $transactionData['appointmentId'] = $value->id;
                            $transactionData['availabilityId'] = $value->userAvailabilityId;
                            $transactionData['stripeTransactionId'] = $response['id'];
                            $transactionData['stripeTranJson'] = json_encode($response);
                            $transactionData['amount'] = $value->price;
                            $transactionData['type'] = 2; // Debit amount
                            $transactionData['payType'] = 1; // Book Appointment Payment 
                            $transactionData['tranType'] = 2; //Stripe Transaction
                            $transactionData['status'] =4 ; 
                            $transactionData['createdDate'] = $response['created'];
                            $this->User_Transaction_Model->setData($transactionData);                        
                            $appointmentBookId = $this->User_Appointment_Model->setData(['paymentStatus'=>1],$value->id);
                        }
                    } else {
                        //$this->Background_Model->appointmentNoPayment($value);
                        $this->appointmentNoPayment($value);
                    }
                } 
            }
        }
    }

    public function userSignupMail($data) {
        if (empty($data)) {
            return false;
        }

        $user = $this->Users_Model->get(['id' => $data]);

        if (empty($user)) {
            return false;
        }
        
        if (!empty($user->email)) {
            $mailBody = $this->load->view('Mail/UserSignUpMail', ['user' => $user], TRUE);
            $this->Common_Model->mailsend($user->email, "Welcome to " . getenv('EMAIL_SUBJECT') . ".", $mailBody);
        }
    }

    public function userVerificationMail($data) {
        if (empty($data)) {
            return false;
        }
        
        $user = $this->Users_Model->get(['id' => $data]);
        
        if (empty($user)) {
            return false;
        }
        
        if (!empty($user->email)) {
            $mailBody = $this->load->view('Mail/UserVerificationMail', ['user' => $user], TRUE);
            $this->Common_Model->mailsend($user->email, getenv('EMAIL_SUBJECT') . " account verification code.", $mailBody);
        }
    }

    public function adminVerifyDoctorAccountMail($data) {
        if (empty($data)) {
            return false;
        }

        $user = $this->Users_Model->get(['id' => $data]);

        if (empty($user)) {
            return false;
        }

        if (!empty($user->email)) {
            $mailBody = $this->load->view('Mail/AdminVerifyDoctorAccountMail', ['user' => $user], TRUE);
            $this->Common_Model->mailsend($user->email, getenv('EMAIL_SUBJECT') . " Account Verification", $mailBody);
        }
    }

    public function adminVerificationDoctorAccountMail($data) {
        if (empty($data)) {
            return false;
        }

        $user = $this->Users_Model->get(['id' => $data]);

        if (empty($user)) {
            return false;
        }

        if (!empty(getenv('PROVIDER_EMAIL'))) {
            $mailBody = $this->load->view('Mail/AdminVerificationDoctorAccountMail', ['user' => $user], TRUE);
            $this->Common_Model->mailsend(getenv('PROVIDER_EMAIL'), getenv('EMAIL_SUBJECT') . " doctor account verify.", $mailBody);
        }
    }

    public function userForgotPasswordMail($data) {
        if (empty($data)) {
            return false;
        }

        $user = $this->Users_Model->get(['id' => $data]);

        if (empty($user)) {
            return false;
        }

        if (!empty($user->email)) {
            $mailBody = $this->load->view('Mail/UserForgotPasswordMail', ['user' => $user], TRUE);
            $this->Common_Model->mailsend($user->email, getenv('EMAIL_SUBJECT') . " Forgot Password.", $mailBody);
        }
    }
    
    public function scheduleAppointmentByUser($data){
        if(empty($data)){
            return false;
        }
        $savedId = "";
        $senderData = $this->Users_Model->get(['id'=>$data['send_from'],'status'=>1],true);
        $receiverData = $this->Users_Model->get(['id'=>$data['send_to'],'status'=>1],true);
        if(empty($senderData) || empty($receiverData)){
            return false;
        }
    
        /*if(!empty($receiverData->phone_code) && !empty($receiverData->phone)){
            $phone = preg_replace("/[^0-9]/", "", $receiverData->phone);
            $phone_code = str_replace("+", "", $receiverData->phone_code);
            $this->twilio->sendTextMessage('+'.$phone_code.''.$phone, $senderData->name." has scheduled a new appointment with you. ".base_url("app-link/upcoming-appointment/".$senderData->id));
        }*/

        if (!empty($receiverData->email)) {
            //$mailBody = $this->load->view('Mail/ScheduleAppointmentByUser', ['user' => $receiverData,'senderData'=>$senderData], TRUE);
            //$this->Common_Model->mailsend($receiverData->email, getenv('EMAIL_SUBJECT') . " - Scheduled a New Appointment With You.", $mailBody);

            $startDate = new DateTime;
            $startDate->setTimezone(new DateTimeZone("UTC"));
            $startDate->setTimestamp($data['startDateTime']);
            $endDate = new DateTime;
            $endDate->setTimezone(new DateTimeZone("UTC"));
            $endDate->setTimestamp($data['endDateTime']);

            $ical_content = "BEGIN:VCALENDAR\r\n
VERSION:2.0\r\n
METHOD:PUBLISH\r\n
X-WR-CALNAME: Appointment Booking\r\n
PRODID:-//Drupal iCal API//EN\r\n
BEGIN:VEVENT\r\n
UID:calendar:120:field_event_datetime:0:0\r\n
SUMMARY:Appointment - ".$data['doctorName']."\r\n
ORGANIZER;CN=".$data['doctorEmail'].":mailto:".$data['doctorEmail']."\r\n
DTSTART:".$startDate->format('Ymd\THis\Z')."\r\n
DTEND:".$endDate->format('Ymd\THis\Z')."\r\n
DTSTAMP:".$startDate->format('Ymd\THis\Z')."\r\n
URL;VALUE=URI:".base_url()."\r\n
END:VEVENT\r\n
END:VCALENDAR\r\n";

            $mailBody = $this->load->view('Mail/ScheduleAppointmentByUser', ['user' => $receiverData,'senderData'=>$senderData], TRUE);
            $this->Common_Model->mailsend($receiverData->email, getenv('EMAIL_SUBJECT') . " - Scheduled a New Appointment With You.", $mailBody,"","","","","",$ical_content);
        }

        // Send friend request
        $data['model'] = "scheduleAppointmentByUser";
        $data['title'] = "Schedule Appointment";
        $data['desc'] = $senderData->name." has scheduled a new appointment with you";
        $notification = array(
            "title" => $data['title'],
            "body" => $data['desc'],
            "badge" => intval(0),
            "sound" => "default"
        );
        $extData = array(
            "category" => "scheduleAppointmentByUser",
            "messageData" => $data,
            "unread" => (string) 0
        );

        $savedId = $this->Notification->setData($data);
        $receiverAuthData = $this->Auth_Model->get(['userId'=>$data['send_to'],'status'=>1]);
        if(!empty($receiverAuthData)){
            foreach($receiverAuthData as $value){
                $this->pushNotification($value->deviceToken, $notification, $extData, 0);
            }
        }
        return $savedId;
    }
    
    public function scheduleServiceByUser($data){
        if(empty($data)){
            return false;
        }
        $savedId = "";
        $senderData = $this->Users_Model->get(['id'=>$data['send_from'],'status'=>1],true);
        $receiverData = $this->Users_Model->get(['id'=>$data['send_to'],'status'=>1],true);
        if(empty($senderData) || empty($receiverData)){
            return false;
        }
    
        /*if(!empty($receiverData->phone_code) && !empty($receiverData->phone)){
            $phone = preg_replace("/[^0-9]/", "", $receiverData->phone);
            $phone_code = str_replace("+", "", $receiverData->phone_code);
            $this->twilio->sendTextMessage('+'.$phone_code.''.$phone, $senderData->name." has scheduled a new service with you. ");
        }*/

        if (!empty($receiverData->email)) {
            //$mailBody = $this->load->view('Mail/scheduleServiceByUser', ['user' => $receiverData,'senderData'=>$senderData], TRUE);
            //$this->Common_Model->mailsend($receiverData->email, getenv('EMAIL_SUBJECT') . " - Scheduled a New Service With You.", $mailBody);

            $startDate = new DateTime;
            $startDate->setTimezone(new DateTimeZone("UTC"));
            $startDate->setTimestamp($data['startDateTime']);
            $endDate = new DateTime;
            $endDate->setTimezone(new DateTimeZone("UTC"));
            $endDate->setTimestamp($data['endDateTime']);

            $ical_content = "BEGIN:VCALENDAR\r\n
VERSION:2.0\r\n
METHOD:PUBLISH\r\n
X-WR-CALNAME: Service Booking\r\n
PRODID:-//Drupal iCal API//EN\r\n
BEGIN:VEVENT\r\n
UID:calendar:120:field_event_datetime:0:0\r\n
SUMMARY:Service - ".$data['doctorName']."\r\n
ORGANIZER;CN=".$data['doctorEmail'].":mailto:".$data['doctorEmail']."\r\n
DTSTART:".$startDate->format('Ymd\THis\Z')."\r\n
DTEND:".$endDate->format('Ymd\THis\Z')."\r\n
DTSTAMP:".$startDate->format('Ymd\THis\Z')."\r\n
URL;VALUE=URI:".base_url()."\r\n
END:VEVENT\r\n
END:VCALENDAR\r\n";

            $mailBody = $this->load->view('Mail/ScheduleServiceByUser', ['user' => $receiverData,'senderData'=>$senderData], TRUE);
            $this->Common_Model->mailsend($receiverData->email, getenv('EMAIL_SUBJECT') . " - Scheduled a New Service With You.", $mailBody,"","","","","",$ical_content);
        }

        // Send friend request
        $data['model'] = "scheduleServiceByUser";
        $data['title'] = "Schedule Service";
        $data['desc'] = $senderData->name." has scheduled a new service with you";
        $notification = array(
            "title" => $data['title'],
            "body" => $data['desc'],
            "badge" => intval(0),
            "sound" => "default"
        );
        $extData = array(
            "category" => "scheduleServiceByUser",
            "messageData" => $data,
            "unread" => (string) 0
        );

        $savedId = $this->Notification->setData($data);
        $receiverAuthData = $this->Auth_Model->get(['userId'=>$data['send_to'],'status'=>1]);
        if(!empty($receiverAuthData)){
            foreach($receiverAuthData as $value){
                $this->pushNotification($value->deviceToken, $notification, $extData, 0);
            }
        }
        return $savedId;
    }
    
    public function scheduleAppointmentForUser($data){
        if(empty($data)){
            return false;
        }
        $savedId = "";
        $senderData = $this->Users_Model->get(['id'=>$data['send_from'],'status'=>1],true);
        $receiverData = $this->Users_Model->get(['id'=>$data['send_to'],'status'=>1],true);
        if(empty($senderData) || empty($receiverData)){
            return false;
        }
    
        if (!empty($receiverData->email)) {
            $mailBody = $this->load->view('Mail/ScheduleAppointmentForUser', ['user' => $receiverData,'senderData'=>$senderData,'doctorName'=>$data['doctorName']], TRUE);
            $this->Common_Model->mailsend($receiverData->email, getenv('EMAIL_SUBJECT') . " - You Are Scheduled a New Appointment With You.", $mailBody);
            /*  
            $startDate = new DateTime;
            $startDate->setTimezone(new DateTimeZone("UTC"));
            $startDate->setTimestamp($data['startDateTime']);
            $endDate = new DateTime;
            $endDate->setTimezone(new DateTimeZone("UTC"));
            $endDate->setTimestamp($data['endDateTime']);

$ical_content = "BEGIN:VCALENDAR\r\n
VERSION:2.0\r\n
METHOD:PUBLISH\r\n
X-WR-CALNAME: Appointment Booking\r\n
PRODID:-//Drupal iCal API//EN\r\n
BEGIN:VEVENT\r\n
UID:calendar:120:field_event_datetime:0:0\r\n
SUMMARY:".$data['doctorName']."\r\n
ORGANIZER;CN=".$data['doctorEmail'].":mailto:".$data['doctorEmail']."\r\n
DTSTART:".$startDate->format('Ymd\THis\Z')."\r\n
DTEND:".$endDate->format('Ymd\THis\Z')."\r\n
DTSTAMP:".$startDate->format('Ymd\THis\Z')."\r\n
URL;VALUE=URI:".base_url()."\r\n
END:VEVENT\r\n
END:VCALENDAR\r\n";

            $mailBody = $this->load->view('Mail/ScheduleAppointmentForUser', ['user' => $receiverData,'senderData'=>$senderData,'doctorName'=>$data['doctorName']], TRUE);
            $this->Common_Model->mailsend($receiverData->email, getenv('EMAIL_SUBJECT') . " - You Are Scheduled a New Appointment.", $mailBody,"","","","","",$ical_content);*/
        }

        // Send friend request
        $data['model'] = "scheduleAppointmentForUser";
        $data['title'] = "Schedule Appointment";
        $data['desc'] = "You have scheduled a new appointment with ".$data['doctorName'];
        $notification = array(
            "title" => $data['title'],
            "body" => $data['desc'],
            "badge" => intval(0),
            "sound" => "default"
        );
        $extData = array(
            "category" => "scheduleAppointmentForUser",
            "messageData" => $data,
            "unread" => (string) 0
        );

        $savedId = $this->Notification->setData($data);
        $receiverAuthData = $this->Auth_Model->get(['userId'=>$data['send_to'],'status'=>1]);
        if(!empty($receiverAuthData)){
            foreach($receiverAuthData as $value){
                $this->pushNotification($value->deviceToken, $notification, $extData, 0);
            }
        }
        return $savedId;
    }
    
    public function scheduleServiceForUser($data){
        if(empty($data)){
            return false;
        }
        $savedId = "";
        $senderData = $this->Users_Model->get(['id'=>$data['send_from'],'status'=>1],true);
        $receiverData = $this->Users_Model->get(['id'=>$data['send_to'],'status'=>1],true);
        if(empty($senderData) || empty($receiverData)){
            return false;
        }
    
        if (!empty($receiverData->email)) {
            $mailBody = $this->load->view('Mail/ScheduleServiceForUser', ['user' => $receiverData,'senderData'=>$senderData,'doctorName'=>$data['doctorName']], TRUE);
            $this->Common_Model->mailsend($receiverData->email, getenv('EMAIL_SUBJECT') . " - You Are Scheduled a New Service With You.", $mailBody);
            /*  
            $startDate = new DateTime;
            $startDate->setTimezone(new DateTimeZone("UTC"));
            $startDate->setTimestamp($data['startDateTime']);
            $endDate = new DateTime;
            $endDate->setTimezone(new DateTimeZone("UTC"));
            $endDate->setTimestamp($data['endDateTime']);

$ical_content = "BEGIN:VCALENDAR\r\n
VERSION:2.0\r\n
METHOD:PUBLISH\r\n
X-WR-CALNAME: Service Booking\r\n
PRODID:-//Drupal iCal API//EN\r\n
BEGIN:VEVENT\r\n
UID:calendar:120:field_event_datetime:0:0\r\n
SUMMARY:".$data['doctorName']."\r\n
ORGANIZER;CN=".$data['doctorEmail'].":mailto:".$data['doctorEmail']."\r\n
DTSTART:".$startDate->format('Ymd\THis\Z')."\r\n
DTEND:".$endDate->format('Ymd\THis\Z')."\r\n
DTSTAMP:".$startDate->format('Ymd\THis\Z')."\r\n
URL;VALUE=URI:".base_url()."\r\n
END:VEVENT\r\n
END:VCALENDAR\r\n";

            $mailBody = $this->load->view('Mail/ScheduleServiceForUser', ['user' => $receiverData,'senderData'=>$senderData,'doctorName'=>$data['doctorName']], TRUE);
            $this->Common_Model->mailsend($receiverData->email, getenv('EMAIL_SUBJECT') . " - You Are Scheduled a New Service.", $mailBody,"","","","","",$ical_content);*/
        }

        // Send friend request
        $data['model'] = "scheduleServiceForUser";
        $data['title'] = "Schedule Service";
        $data['desc'] = "You have scheduled a new service with ".$data['doctorName'];
        $notification = array(
            "title" => $data['title'],
            "body" => $data['desc'],
            "badge" => intval(0),
            "sound" => "default"
        );
        $extData = array(
            "category" => "scheduleServiceForUser",
            "messageData" => $data,
            "unread" => (string) 0
        );

        $savedId = $this->Notification->setData($data);
        $receiverAuthData = $this->Auth_Model->get(['userId'=>$data['send_to'],'status'=>1]);
        if(!empty($receiverAuthData)){
            foreach($receiverAuthData as $value){
                $this->pushNotification($value->deviceToken, $notification, $extData, 0);
            }
        }
        return $savedId;
    }

    public function schedulePlanServiceForDoctor($data){
        if(empty($data)){
            return false;
        }
        $savedId = "";
        $senderData = $this->Users_Model->get(['id'=>$data['send_from'],'status'=>1],true);
        $receiverData = $this->Users_Model->get(['id'=>$data['send_to'],'status'=>1],true);
        if(empty($senderData) || empty($receiverData)){
            return false;
        }
 
        // Send friend request
        $data['model'] = "schedulePlanServiceForDoctor";
        $data['title'] = "Schedule Plan Service";
        $data['desc'] = "You have scheduled a new plan service with ".$data['userName'];
        $notification = array(
            "title" => $data['title'],
            "body" => $data['desc'],
            "badge" => intval(0),
            "sound" => "default"
        );
        $extData = array(
            "category" => "schedulePlanServiceForDoctor",
            "messageData" => $data,
            "unread" => (string) 0
        );

        $savedId = $this->Notification->setData($data);
        $receiverAuthData = $this->Auth_Model->get(['userId'=>$data['send_to'],'status'=>1]);
        if(!empty($receiverAuthData)){
            foreach($receiverAuthData as $value){
                $this->pushNotification($value->deviceToken, $notification, $extData, 0);
            }
        }
        return $savedId;
    }
    
    public function schedulePlanServiceByDoctor($data){
        if(empty($data)){
            return false;
        }
        $savedId = "";
        $senderData = $this->Users_Model->get(['id'=>$data['send_from'],'status'=>1],true);
        $receiverData = $this->Users_Model->get(['id'=>$data['send_to'],'status'=>1],true);
        if(empty($senderData) || empty($receiverData)){
            return false;
        }
    
        // Send friend request
        $data['model'] = "schedulePlanServiceByDoctor";
        $data['title'] = "Schedule Plan Service";
        $data['desc'] = $senderData->name." has scheduled a new plan service with you";
        $notification = array(
            "title" => $data['title'],
            "body" => $data['desc'],
            "badge" => intval(0),
            "sound" => "default"
        );
        $extData = array(
            "category" => "schedulePlanServiceByDoctor",
            "messageData" => $data,
            "unread" => (string) 0
        );

        $savedId = $this->Notification->setData($data);
        $receiverAuthData = $this->Auth_Model->get(['userId'=>$data['send_to'],'status'=>1]);
        if(!empty($receiverAuthData)){
            foreach($receiverAuthData as $value){
                $this->pushNotification($value->deviceToken, $notification, $extData, 0);
            }
        }
        return $savedId;
    }
    
    public function transactionSuccessForSchedulePlan($data){
        if(empty($data)){
            return false;
        }
        $savedId = "";
        $senderData = $this->Users_Model->get(['id'=>$data['send_from'],'status'=>1],true);
        $receiverData = $this->Users_Model->get(['id'=>$data['send_to'],'status'=>1],true);
        if(empty($senderData) || empty($receiverData)){
            return false;
        }
    
        // Send friend request
        $data['model'] = "transactionSuccessForSchedulePlan";
        $data['title'] = "Your Payment Success";
        $data['desc'] = "Your ".$data["amount"]." payment was successful";
        $notification = array(
            "title" => $data['title'],
            "body" => $data['desc'],
            "badge" => intval(0),
            "sound" => "default"
        );
        $extData = array(
            "category" => "transactionSuccessForSchedulePlan",
            "messageData" => $data,
            "unread" => (string) 0
        );

        $savedId = $this->Notification->setData($data);
        $receiverAuthData = $this->Auth_Model->get(['userId'=>$data['send_to'],'status'=>1]);
        if(!empty($receiverAuthData)){
            foreach($receiverAuthData as $value){
                $this->pushNotification($value->deviceToken, $notification, $extData, 0);
            }
        }
        return $savedId;
    }

    public function sendMailAndSMSInPlanAuthenticationCodeForUser($data){
        if (empty($data)) {
            return false;
        }
        $user = $this->Users_Model->get(['id' => $data['userId']]);
        
        if (empty($user)) {
            return false;
        }
        $reschedule = '';
        if (isset($data['isReschedule'])) {
            $reschedule = 'Reschedule'; 
        }
        $user->authenticationCode = $data['authenticationCode'];
        if(!empty($user->phone_code) && !empty($user->phone)){
            $phone = preg_replace("/[^0-9]/", "", $user->phone );
            $phone_code = str_replace("+", "", $user->phone_code);
            $this->twilio->sendTextMessage('+'.$phone_code.''.$phone,'Your '. $reschedule .' Plan Authentication Code Is '.$user->authenticationCode);
        }

        if (!empty($user->email)) {
            $mailBody = $this->load->view('Mail/PlanAuthenticationCode', ['user' => $user], TRUE);
            $this->Common_Model->mailsend($user->email, $reschedule . " Plan Authentication Code", $mailBody);
        }
    }

    public function addMoneyInYourWalletForScheduleAppointment($data){
        if(empty($data)){
            return false;
        }
        $savedId = "";
        $senderData = $this->Users_Model->get(['id'=>$data['send_from'],'status'=>1],true);
        $receiverData = $this->Users_Model->get(['id'=>$data['send_to'],'status'=>1],true);
        if(empty($senderData) || empty($receiverData)){
            return false;
        }
    
        // Send friend request
        $data['model'] = "addMoneyInYourWalletForScheduleAppointment";
        $data['title'] = "Money Added In Your Wallet";
        $data['desc'] = $data["amount"]." added in your wallet for completing the appointment";
        $notification = array(
            "title" => $data['title'],
            "body" => $data['desc'],
            "badge" => intval(0),
            "sound" => "default"
        );
        $extData = array(
            "category" => "addMoneyInYourWalletForScheduleAppointment",
            "messageData" => $data,
            "unread" => (string) 0
        );

        $savedId = $this->Notification->setData($data);
        $receiverAuthData = $this->Auth_Model->get(['userId'=>$data['send_to'],'status'=>1]);
        if(!empty($receiverAuthData)){
            foreach($receiverAuthData as $value){
                $this->pushNotification($value->deviceToken, $notification, $extData, 0);
            }
        }
        return $savedId;
    }
    
    public function addMoneyInYourWalletForCancelAppointment($data){
        if(empty($data)){
            return false;
        }
        $savedId = "";
        $senderData = $this->Users_Model->get(['id'=>$data['send_from'],'status'=>1],true);
        $receiverData = $this->Users_Model->get(['id'=>$data['send_to'],'status'=>1],true);
        if(empty($senderData) || empty($receiverData)){
            return false;
        }
    
        // Send friend request
        $data['model'] = "addMoneyInYourWalletForCancelAppointment";
        $data['title'] = "Money Added In Your Wallet";
        $data['desc'] = $data["amount"]." added in your wallet for cancelled the appointment";
        $notification = array(
            "title" => $data['title'],
            "body" => $data['desc'],
            "badge" => intval(0),
            "sound" => "default"
        );
        $extData = array(
            "category" => "addMoneyInYourWalletForCancelAppointment",
            "messageData" => $data,
            "unread" => (string) 0
        );

        $savedId = $this->Notification->setData($data);
        $receiverAuthData = $this->Auth_Model->get(['userId'=>$data['send_to'],'status'=>1]);
        if(!empty($receiverAuthData)){
            foreach($receiverAuthData as $value){
                $this->pushNotification($value->deviceToken, $notification, $extData, 0);
            }
        }
        return $savedId;
    }
    
    public function transactionSuccessForScheduleAppointment($data){
        if(empty($data)){
            return false;
        }
        $savedId = "";
        $senderData = $this->Users_Model->get(['id'=>$data['send_from'],'status'=>1],true);
        $receiverData = $this->Users_Model->get(['id'=>$data['send_to'],'status'=>1],true);
        if(empty($senderData) || empty($receiverData)){
            return false;
        }
    
        // Send friend request
        $data['model'] = "transactionSuccessForScheduleAppointment";
        $data['title'] = "Your Payment Success";
        $data['desc'] = "Your ".$data["amount"]." payment was successful";
        $notification = array(
            "title" => $data['title'],
            "body" => $data['desc'],
            "badge" => intval(0),
            "sound" => "default"
        );
        $extData = array(
            "category" => "transactionSuccessForScheduleAppointment",
            "messageData" => $data,
            "unread" => (string) 0
        );

        $savedId = $this->Notification->setData($data);
        $receiverAuthData = $this->Auth_Model->get(['userId'=>$data['send_to'],'status'=>1]);
        if(!empty($receiverAuthData)){
            foreach($receiverAuthData as $value){
                $this->pushNotification($value->deviceToken, $notification, $extData, 0);
            }
        }
        return $savedId;
    }
    
    public function transactionSuccessForScheduleService($data){
        if(empty($data)){
            return false;
        }
        $savedId = "";
        $senderData = $this->Users_Model->get(['id'=>$data['send_from'],'status'=>1],true);
        $receiverData = $this->Users_Model->get(['id'=>$data['send_to'],'status'=>1],true);
        if(empty($senderData) || empty($receiverData)){
            return false;
        }
    
        // Send friend request
        $data['model'] = "transactionSuccessForScheduleService";
        $data['title'] = "Your Payment Success";
        $data['desc'] = "Your ".$data["amount"]." payment was successful";
        $notification = array(
            "title" => $data['title'],
            "body" => $data['desc'],
            "badge" => intval(0),
            "sound" => "default"
        );
        $extData = array(
            "category" => "transactionSuccessForScheduleService",
            "messageData" => $data,
            "unread" => (string) 0
        );

        $savedId = $this->Notification->setData($data);
        $receiverAuthData = $this->Auth_Model->get(['userId'=>$data['send_to'],'status'=>1]);
        if(!empty($receiverAuthData)){
            foreach($receiverAuthData as $value){
                $this->pushNotification($value->deviceToken, $notification, $extData, 0);
            }
        }
        return $savedId;
    }
    
    public function rescheduleUserAppointmentByDoctor($data){
        if(empty($data)){
            return false;
        }
        $savedId = "";
        $senderData = $this->Users_Model->get(['id'=>$data['send_from'],'status'=>1],true);
        $receiverData = $this->Users_Model->get(['id'=>$data['send_to'],'status'=>1],true);
        if(empty($senderData) || empty($receiverData)){
            return false;
        }
    
        // Send friend request
        $data['model'] = "rescheduleUserAppointmentByDoctor";
        $data['title'] = "Reschedule Appointment";
        $data['desc'] = $senderData->name." has rescheduled your appointment ";
        $notification = array(
            "title" => $data['title'],
            "body" => $data['desc'],
            "badge" => intval(0),
            "sound" => "default"
        );
        $extData = array(
            "category" => "rescheduleUserAppointmentByDoctor",
            "messageData" => $data,
            "unread" => (string) 0
        );

        $savedId = $this->Notification->setData($data);
        $receiverAuthData = $this->Auth_Model->get(['userId'=>$data['send_to'],'status'=>1]);
        if(!empty($receiverAuthData)){
            foreach($receiverAuthData as $value){
                $this->pushNotification($value->deviceToken, $notification, $extData, 0);
            }
        }
        return $savedId;
    }
    
    public function rescheduleUserAppointmentAsDoctor($data){
        if(empty($data)){
            return false;
        }
        $savedId = "";
        $senderData = $this->Users_Model->get(['id'=>$data['send_from'],'status'=>1],true);
        $receiverData = $this->Users_Model->get(['id'=>$data['send_to'],'status'=>1],true);
        $userData = $this->Users_Model->get(['id'=>$data['userId'],'status'=>1],true);
        if(empty($senderData) || empty($receiverData) || empty($userData)){
            return false;
        }
    
        $data['model'] = "rescheduleUserAppointmentAsDoctor";
        $data['title'] = "Reschedule Appointment";
        $data['desc'] = "You rescheduled ".$userData->name." appointment successfully";
        $notification = array(
            "title" => $data['title'],
            "body" => $data['desc'],
            "badge" => intval(0),
            "sound" => "default"
        );
        $extData = array(
            "category" => "rescheduleUserAppointmentAsDoctor",
            "messageData" => $data,
            "unread" => (string) 0
        );

        $savedId = $this->Notification->setData($data);
        $receiverAuthData = $this->Auth_Model->get(['userId'=>$data['send_to'],'status'=>1]);
        if(!empty($receiverAuthData)){
            foreach($receiverAuthData as $value){
                $this->pushNotification($value->deviceToken, $notification, $extData, 0);
            }
        }
        return $savedId;
    }

    public function pushNotification($deviceToken, $notification, $extData, $badgeCount) {
        $result = array();
        if(isset($extData["messageData"]['send_to'])) {
            $un_nf_chat = $this->Chat_Model->getMessageStatus( [ 'userId' => $extData["messageData"]['send_to'], 'status' => 1 ], false, true );
            $nf_count = $this->Notification->get(['send_to' => $extData["messageData"]['send_to'], 'status' => 1 ], false, true);
            $totl_nf = $un_nf_chat + $nf_count;
            $notification["badge"] = $totl_nf;
            $extData["messageData"]['badge'] = $totl_nf;
        }
        if (!empty($deviceToken)) {
            $result = $this->fcm->sendMessage($extData, $notification, $deviceToken);
        }
        //error_log("\n\n -------------------------------------" . date('c'). " \n". json_encode($notification)." \n". $deviceToken." \n". json_encode($extData)." \n". json_encode($result) , 3, FCPATH.'worker/notification.log');
        return $result; 
    }
    
    public function sendVideoCallNotification($data,$notficationData){
        if (empty($data['id'])) {
            return false;
        }

        $user = $this->Users_Model->get(['id' => $data['id']]);
        if(empty($user)){
            return false; 
        }

        $notification = array(
            "title" => "New Video Call",
            "body" => "New Video Call",
            "badge" => intval(0),
            "sound" => "default"
        );
        $extData = array(
            "category" => "videocall",
            "messageData" => $notficationData,
            "unread" => (string) 0
        );

        $receiverAuthData = $this->Auth_Model->get(['userId'=>$data['id'],'status'=>1]);
        if(!empty($receiverAuthData)){
            foreach($receiverAuthData as $value){
                if($value->deviceType == 2){
                    $this->pushNotification($value->deviceToken, $notification, $extData, 0);
                }else if($value->deviceType == 1){
                    if($user->role == 2){
                        $this->voipappleuser->sendNotification($value->voipToken,'New Video Call',$extData);
                    }else if($user->role == 3){
                        $this->voipappledoctor->sendNotification($value->voipToken,'New Video Call',$extData);
                    }
                }
            }
        }
    }

    function objectToArray($data) {
        if (is_array($data) || is_object($data)) {
            $result = array();
            foreach ($data as $key => $value) {
                $result[$key] = $this->objectToArray($value);
            }
            return $result;
        }
        return $data;
    }

    public function userFirstCardCreateMail($data) {
        if (empty($data)) {
            return false;
        }

        $user = $this->Users_Model->get(['id' => $data]);
        //$pdf = FCPATH . 'assets/img/Chiry-Provider-Registration-Checklist.pdf';
        $pdf = array(FCPATH . 'assets/img/Chiry-Provider-Registration-Checklist.pdf', FCPATH . 'assets/img/CHP_Next_Steps_Registration_Video.mp4');
        if (empty($user)) {
            return false;
        }
        
        if (!empty($user->email)) {
            $mailBody = $this->load->view('Mail/userFirstCardCreateMail', ['user' => $user], TRUE);
            $this->Common_Model->mailsend($user->email, getenv('EMAIL_SUBJECT') . " Subscription.", $mailBody, null, $pdf);
        }
    }

    public function userChangeNewPasswordMail($data) {
        if (empty($data['id'])) {
            return false;
        }

        $user = $this->Users_Model->get(['id' => $data['id']]);

        if (empty($user)) {
            return false;
        }

        if (!empty($user->email)) {
            $mailBody = $this->load->view('Mail/userChangeNewPasswordMail', ['user' => $user, 'password' => $data['password']], TRUE);
            $this->Common_Model->mailsend($user->email, "Your new " . getenv('WEBSITE_NAME') . " password has been modified.", $mailBody);
        }
    }

    public function AdminAddDoctorMail($data) {
        if (empty($data['id']) || empty($data['password'])) {
            return false;
        }

        $user = $this->Users_Model->get(['id' => $data['id']]);
        $user->freshPassword = $data['password'];

        if (empty($user)) {
            return false;
        }

        if (!empty($user->email)) {
            $mailBody = $this->load->view('Mail/AdminAddDoctorMail', ['user' => $user, 'password' => $data['password']], TRUE);
            $this->Common_Model->mailsend($user->email, "Your new " . getenv('WEBSITE_NAME') . " account was created.", $mailBody);
        }
    }

    public function contactUsMail($data) {
        if (empty($data)) {
            return false;
        }
        $user = $this->ContactUs_Model->get(['id' => $data]);
        // || empty($this->config->item('admin_email')
        if (empty($user)) {
            return false;
        }
        if (!empty($user->email)) {
            $mailBody = $this->load->view('Mail/ContactPageMail', ['data' => $user], TRUE);
            $this->Common_Model->mailsend($user->email, "Welcome to " . getenv('EMAIL_SUBJECT') . ".", $mailBody);
        }
    }
    
    public function contactUsAdminMail($data) {
        
        if (empty($data)) {
            return false;
        }
        $user = $this->ContactUs_Model->get(['id' => $data]);
        $contactUsAdmin = $this->config->item('contactUsEmail');
        if (empty($user) || empty($user->email)) {
            return false;
        }

        if (!empty($user->email)) {
            $mailBody = $this->load->view('Mail/ConatctUsMailAdmin', ['user' => $user], TRUE);
            
            $this->Common_Model->mailsend($contactUsAdmin, "Need to contact us ", $mailBody);
        }
    }

    public function sendMailAndSMSInAuthenticationCodeForUser($data){
        if (empty($data)) {
            return false;
        }
        $user = $this->Users_Model->get(['id' => $data['userId']]);
        
        if (empty($user)) {
            return false;
        }
        $reschedule = '';
        if (isset($data['isReschedule'])) {
            $reschedule = 'Reschedule '; 
        }
        $user->authenticationCode = $data['authenticationCode'];
        if(!empty($user->phone_code) && !empty($user->phone)){
            $phone = preg_replace("/[^0-9]/", "", $user->phone );
            $phone_code = str_replace("+", "", $user->phone_code);
            $this->twilio->sendTextMessage('+'.$phone_code.''.$phone,'Your '. $reschedule .' Appointment Authentication Code Is '.$user->authenticationCode);
        }

        if (!empty($user->email)) {
            $mailBody = $this->load->view('Mail/AppointmentAuthenticationCode', ['user' => $user], TRUE);
            $this->Common_Model->mailsend($user->email, $reschedule . " Appointment Authentication Code", $mailBody);
        }
    }

    public function sendMailAndSMSInServiceAuthenticationCodeForUser($data){
        if (empty($data)) {
            return false;
        }
        $user = $this->Users_Model->get(['id' => $data['userId']]);
        
        if (empty($user)) {
            return false;
        }
        $reschedule = '';
        if (isset($data['isReschedule'])) {
            $reschedule = 'Reschedule'; 
        }
        $user->authenticationCode = $data['authenticationCode'];
        if(!empty($user->phone_code) && !empty($user->phone)){
            $phone = preg_replace("/[^0-9]/", "", $user->phone );
            $phone_code = str_replace("+", "", $user->phone_code);
            $this->twilio->sendTextMessage('+'.$phone_code.''.$phone,'Your '. $reschedule .' Service Authentication Code Is '.$user->authenticationCode);
        }

        if (!empty($user->email)) {
            $mailBody = $this->load->view('Mail/ServiceAuthenticationCode', ['user' => $user], TRUE);
            $this->Common_Model->mailsend($user->email, $reschedule . " Service Authentication Code", $mailBody);
        }
    }

    /**
     *  CRON 1hour push notification.
     */
    public function C001C_1hour_appointment_push_notification() {
        $getSystemTimeZone = getenv('SYSTEMTIMEZON');
        $count = 0;
        $this->load->model('User_Appointment_Model');
        $startDate = strtotime("+60 minutes", time());
        $endDate = strtotime("+65 minutes", time());        
        $getAll = $this->User_Appointment_Model->get([
            'status' => 1,
            'getAvailabilityData' => 1,
            'getAvailabilityForCron' => ['startDate' => $startDate, 'endDate' => $endDate,],
        ]);
        if(empty($getAll)) {
            return $count . " time Sent...";
        }
        
        foreach ($getAll as $value) {
            // ------------------ For user(Patient)----------------//
            $data = [];
            $receiverData = $this->Users_Model->get(['id'=> $value->userId, 'status'=>1],true);
            if(!empty($receiverData) ) {
                $appointmentStartTime = $value->appointmentStartTime;
                if (!empty($receiverData->timeZone)) {
                    $dateObject = new DateTime(strtotime($value->appointmentDateTime), new DateTimeZone($getSystemTimeZone));
                    $dateObject->setTimezone(new DateTimeZone($receiverData->timeZone));
                    $appointmentStartTime = $dateObject->format('h:i A');
                }
                
                $data['send_to'] = $value->userId;
                $data['model_id'] = $value->id;
                $data['model'] = "appointmentWithin1HourAsUser";
                $data['title'] = "You have an appointment at " . $appointmentStartTime;
                $data['desc'] = "You have an appointment at " . $appointmentStartTime;
                $notification = [
                    "title" => $data['title'],
                    "body" => $data['desc'],
                    "badge" => intval(0),
                    "sound" => "default"
                ];
                $extData = [
                    "category" => "appointmentWithin1HourAsUser",
                    "messageData" => $data,
                    "unread" => (string) 0
                ];
    
                $savedId = $this->Notification->setData($data);
                $receiverAuthData = $this->Auth_Model->get(['userId' => $value->userId,'status'=>1 ]);
                if(!empty($receiverAuthData)) {
                    foreach($receiverAuthData as $val) {
                        $count++;
                        $this->pushNotification($val->deviceToken, $notification, $extData, 0);
                    }
                }
            }

            // ---------------- For doctor(health professional) Doctor----------------
            $data = [];
            $receiverData = $this->Users_Model->get(['id'=> $value->doctorId, 'status'=>1],true);
            if (!empty($receiverData)) {
                $appointmentStartTime = $value->appointmentStartTime;
                if (!empty($receiverData->timeZone)) {
                    $dateObject = new DateTime(strtotime($value->appointmentDateTime), new DateTimeZone($getSystemTimeZone));
                    $dateObject->setTimezone(new DateTimeZone($receiverData->timeZone));
                    $appointmentStartTime = $dateObject->format('h:i A');
                }
                $data['send_to'] = $value->doctorId;
                $data['model_id'] = $value->id;
                $data['model'] = "appointmentWithin1HourAsDoctor";
                $data['title'] = "You have an appointment at " . $appointmentStartTime;
                $data['desc'] = "You have an appointment at " . $appointmentStartTime;
                $notification = [
                    "title" => $data['title'],
                    "body" => $data['desc'],
                    "badge" => intval(0),
                    "sound" => "default"
                ];
                $extData = [
                    "category" => "appointmentWithin1HourAsDoctor",
                    "messageData" => $data,
                    "unread" => (string) 0
                ];
    
                $savedId = $this->Notification->setData($data);
                $receiverAuthData = $this->Auth_Model->get(['userId' => $value->doctorId,'status'=>1 ]);
                if(!empty($receiverAuthData)) {
                    foreach($receiverAuthData as $val1) {
                        $count++;
                        $this->pushNotification($val1->deviceToken, $notification, $extData, 0);
                    }
                }
            }


        }
        return $count . " time Sent...";
        
    }

    /**
     *  CRON 10 minutes push notification.
     */
    public function C002C_10minutes_appointment_push_notification() {
        $getSystemTimeZone = getenv('SYSTEMTIMEZON');  
        $count = 0;
        $this->load->model('User_Appointment_Model');
        $startDate = strtotime("+1438 minutes", time());
        $endDate = strtotime("+1443 minutes", time());        
        $getAll = $this->User_Appointment_Model->get([
            'status' => 1,
            'getAvailabilityData' => 1,
            'getAvailabilityForCron' => ['startDate' => $startDate, 'endDate' => $endDate,],
        ]);
        if(empty($getAll)) {
            return false;
            // return $count . " time Sent...";
        }
        
        foreach ($getAll as $value) {
            // ---------------- For user(patient) ----------------
            $data = [];
            $receiverData = $this->Users_Model->get(['id'=> $value->userId, 'status'=>1],true);
            if(!empty($receiverData) ) {
                $appointmentStartTime = $value->appointmentStartTime;
                if (!empty($receiverData->timeZone)) {
                    $dateObject = new DateTime(strtotime($value->appointmentDateTime), new DateTimeZone($getSystemTimeZone));
                    $dateObject->setTimezone(new DateTimeZone($receiverData->timeZone));
                    $appointmentStartTime = $dateObject->format('h:i A');
                }
                    
                $data['send_to'] = $value->userId;
                $data['model_id'] = $value->id;
                $data['model'] = "appointmentWithin10MinutesAsUser";
                $data['title'] = "You have an appointment at " . $appointmentStartTime;
                $data['desc'] = "You have an appointment at " . $appointmentStartTime;
                $notification = [
                    "title" => $data['title'],
                    "body" => $data['desc'],
                    "badge" => intval(0),
                    "sound" => "default"
                ];
                $extData = [
                    "category" => "appointmentWithin10MinutesAsUser",
                    "messageData" => $data,
                    "unread" => (string) 0
                ];

                $this->Notification->setData($data);
                $receiverAuthData = $this->Auth_Model->get(['userId' => $value->userId,'status'=>1 ]);
                if(!empty($receiverAuthData)) {
                    foreach($receiverAuthData as $val) {
                        $count++;
                        $this->pushNotification($val->deviceToken, $notification, $extData, 0);
                    }
                }
            }

            // ---------------- For doctor(health professional) Doctor----------------
            $receiverData = $this->Users_Model->get(['id'=> $value->doctorId, 'status'=>1],true);
            if (!empty($receiverData)) {
                $appointmentStartTime = $value->appointmentStartTime;
                if (!empty($receiverData->timeZone)) {
                    $dateObject = new DateTime(strtotime($value->appointmentDateTime), new DateTimeZone($getSystemTimeZone));
                    $dateObject->setTimezone(new DateTimeZone($receiverData->timeZone));
                    $appointmentStartTime = $dateObject->format('h:i A');
                }
                    
                $data = [];
                $data['send_to'] = $value->doctorId;
                $data['model_id'] = $value->id;
                $data['model'] = "appointmentWithin10MinutesAsDoctor";
                $data['title'] = "You have an appointment at " . $appointmentStartTime;
                $data['desc'] = "You have an appointment at " . $appointmentStartTime;
                $notification = [
                    "title" => $data['title'],
                    "body" => $data['desc'],
                    "badge" => intval(0),
                    "sound" => "default"
                ];
                $extData = [
                    "category" => "appointmentWithin10MinutesAsDoctor",
                    "messageData" => $data,
                    "unread" => (string) 0
                ];

                $this->Notification->setData($data);
                $receiverAuthData = $this->Auth_Model->get(['userId' => $value->doctorId,'status'=>1 ]);
                if(!empty($receiverAuthData)) {
                    foreach($receiverAuthData as $val1) {
                        $count++;
                        $this->pushNotification($val1->deviceToken, $notification, $extData, 0);
                    }
                }
            }


        }
        return true;
        // return $count . " time Sent...";
        
    }

    public function sendChatNotification($data) {
        if(empty($data['users'])) {
            return false;
        } 
        
        $savedId = "";
        $senderData = $this->Users_Model->get(['id'=>$data['send_from'],'status'=>1],true);
        foreach ($data['users'] as $userId) {
            $receiverData = $this->Users_Model->get(['id' => $userId, 'status' =>1], true);
            if(/* empty($senderData) || */ empty($receiverData)){
                return false;
            }

            // Send message
            $data['model'] = "doctorPatientChatPushNotification";
            $data['title'] = $senderData->name;
            $data['desc'] =($data['message']->type == 4 ? "Refer a provider" :($data['message']->type == 2 ? "Image" : ($data['message']->type == 3 ? "Video" : json_decode('"'.$data['message']->message.'"'))));
            $data['model_id'] = (int) $senderData->id;
            $data['receiverName'] = $senderData->name;
            $data['send_to'] = $userId;
            $notification = array(
                "title" => $data['title'],
                "body" => $data['desc'],
                "badge" => intval(0),
                "sound" => "default"
            );
            $extData = array(
                "category" => "doctorPatientChatPushNotification",
                "messageData" => $data,
                "unread" => (string) 0
            );
    
            //$savedId = $this->Notification->setData($data);
            $receiverAuthData = $this->Auth_Model->get(['userId' => $userId, 'status' => 1]);
            if (!empty($receiverAuthData)) {
                foreach($receiverAuthData as $value) {
                    $this->pushNotification($value->deviceToken, $notification, $extData, 0);
                }
            }
            // return $savedId;
        }
    }

    public function rescheduleUserAppointmentByUser($data) {
        if (empty($data)) {
            return false;
        }

        $savedId = "";
        $senderData = $this->Users_Model->get(['id'=>$data['send_from'],'status'=>1],true);
        $receiverData = $this->Users_Model->get(['id'=>$data['send_to'],'status'=>1],true);
        
        if (empty($senderData) || empty($receiverData)) {
            return false;
        }
    
        // Send notification.
        $data['model'] = "rescheduleUserAppointmentByUser";
        $data['title'] = "Reschedule Appointment";
        $data['desc'] = $senderData->name . " has rescheduled your appointment ";
        $notification = array(
            "title"     => $data['title'],
            "body"      => $data['desc'],
            "badge"     => intval(0),
            "sound"     => "default",
        );
        $extData = array(
            "category"      => "rescheduleUserAppointmentByUser",
            "messageData"   => $data,
            "unread"        => (string) 0
        );

        $savedId = $this->Notification->setData($data);
        $receiverAuthData = $this->Auth_Model->get(['userId'=>$data['send_to'],'status'=>1]);
        if(!empty($receiverAuthData)) {
            foreach($receiverAuthData as $value) {
                $this->pushNotification($value->deviceToken, $notification, $extData, 0);
            }
        }

        return $savedId;
    }

    public function rescheduleUserAppointmentAsUser($data) {
        if(empty($data)) {
            return false;
        }
        $savedId = "";
        $senderData = $this->Users_Model->get(['id'=>$data['send_from'],'status'=>1],true);
        $receiverData = $this->Users_Model->get(['id'=>$data['send_to'],'status'=>1],true);
        $userData = $this->Users_Model->get(['id'=>$data['userId'],'status'=>1],true);
        if(empty($senderData) || empty($receiverData) || empty($userData)) {
            return false;
        }
    
        $data['model']  = "rescheduleUserAppointmentAsUser";
        $data['title']  = "Reschedule Appointment";
        $data['desc']   = "You rescheduled ".$userData->name." appointment successfully";
        $notification   = [
            "title"     => $data['title'],
            "body"      => $data['desc'],
            "badge"     => intval(0),
            "sound"     => "default",
        ];
        $extData = [
            "category"      => "rescheduleUserAppointmentAsUser",
            "messageData"   => $data,
            "unread"        => (string) 0
        ];

        $savedId = $this->Notification->setData($data);
        $receiverAuthData = $this->Auth_Model->get([
            'userId'    => $data['send_to'],
            'status'    => 1,
        ]);
        if (!empty($receiverAuthData)) {
            foreach($receiverAuthData as $value) {
                $this->pushNotification($value->deviceToken, $notification, $extData, 0);
            }
        }

        return $savedId;
    }

    public function cancelledUserAppointmentByDoctor($data) {
        if (empty($data)) {
            return false;
        }
        $savedId = "";
        $senderData = $this->Users_Model->get(['id'=>$data['send_from'],'status'=>1],true);
        $receiverData = $this->Users_Model->get(['id'=>$data['send_to'],'status'=>1],true);
        if (empty($senderData) || empty($receiverData)) {
            return false;
        }
    
        // Send friend request
        $data['model'] = "cancelledUserAppointmentByDoctor";
        $data['title'] = "Cancelled Appointment";
        $data['desc'] = $senderData->name." has cancelled  your appointment ";
        $notification = [
            "title"     => $data['title'],
            "body"      => $data['desc'],
            "badge"     => intval(0),
            "sound"     => "default"
        ];
        $extData = [
            "category"      => "cancelledUserAppointmentByDoctor",
            "messageData"   => $data,
            "unread"        => (string) 0
        ];

        $savedId = $this->Notification->setData($data);
        $receiverAuthData = $this->Auth_Model->get(['userId' => $data['send_to'], 'status'=>1]);
        
        if(!empty($receiverAuthData)) {
            foreach($receiverAuthData as $value) {
                $this->pushNotification($value->deviceToken, $notification, $extData, 0);
            }
        }

        return $savedId;
    }

    public function cancelledUserAppointmentAsDoctor($data) {
        if(empty($data)) {
            return false;
        }

        $savedId = "";
        $senderData = $this->Users_Model->get(['id'=>$data['send_from'],'status'=>1],true);
        $receiverData = $this->Users_Model->get(['id'=>$data['send_to'],'status'=>1],true);
        $userData = $this->Users_Model->get(['id'=>$data['userId'],'status'=>1],true);
        
        if(empty($senderData) || empty($receiverData) || empty($userData)){
            return false;
        }
    
        $data['model'] = "cancelledUserAppointmentAsDoctor";
        $data['title'] = "Cancelled Appointment";
        $data['desc'] = "You cancelled " . $userData->name . " appointment successfully";
        $notification = [
            "title"     => $data['title'],
            "body"      => $data['desc'],
            "badge"     => intval(0),
            "sound"     => "default"
        ];
        $extData = [
            "category"      => "cancelledUserAppointmentAsDoctor",
            "messageData"   => $data,
            "unread"        => (string) 0
        ];

        $savedId = $this->Notification->setData($data);
        $receiverAuthData = $this->Auth_Model->get(['userId'=>$data['send_to'],'status'=>1]);
        if (!empty($receiverAuthData)) {
            foreach($receiverAuthData as $value) {
                $this->pushNotification($value->deviceToken, $notification, $extData, 0);
            }
        }

        return $savedId;
    }

    public function cancelledUserAppointmentByUser($data) {
        if (empty($data)) {
            return false;
        }
        $savedId = "";
        $senderData = $this->Users_Model->get(['id'=>$data['send_from'],'status'=>1],true);
        $receiverData = $this->Users_Model->get(['id'=>$data['send_to'],'status'=>1],true);
        if (empty($senderData) || empty($receiverData)) {
            return false;
        }
    
        // Send friend request
        $data['model'] = "cancelledUserAppointmentByUser";
        $data['title'] = "Cancelled Appointment";
        $data['desc'] = $senderData->name." has cancelled  your appointment ";
        $notification = [
            "title"     => $data['title'],
            "body"      => $data['desc'],
            "badge"     => intval(0),
            "sound"     => "default"
        ];
        $extData = [
            "category"      => "cancelledUserAppointmentByUser",
            "messageData"   => $data,
            "unread"        => (string) 0
        ];

        $savedId = $this->Notification->setData($data);
        $receiverAuthData = $this->Auth_Model->get(['userId' => $data['send_to'], 'status'=>1]);
        
        if(!empty($receiverAuthData)) {
            foreach($receiverAuthData as $value) {
                $this->pushNotification($value->deviceToken, $notification, $extData, 0);
            }
        }

        return $savedId;
    }

    public function cancelledUserAppointmentAsUser($data) {
        if(empty($data)) {
            return false;
        }

        $savedId = "";
        $senderData = $this->Users_Model->get(['id'=>$data['send_from'],'status'=>1],true);
        $receiverData = $this->Users_Model->get(['id'=>$data['send_to'],'status'=>1],true);
        $userData = $this->Users_Model->get(['id'=>$data['userId'],'status'=>1],true);
        
        if(empty($senderData) || empty($receiverData) || empty($userData)){
            return false;
        }
    
        $data['model'] = "cancelledUserAppointmentAsUser";
        $data['title'] = "Cancelled Appointment";
        $data['desc'] = "You cancelled " . $userData->name . " appointment successfully";
        $notification = [
            "title"     => $data['title'],
            "body"      => $data['desc'],
            "badge"     => intval(0),
            "sound"     => "default"
        ];
        $extData = [
            "category"      => "cancelledUserAppointmentAsUser",
            "messageData"   => $data,
            "unread"        => (string) 0
        ];

        $savedId = $this->Notification->setData($data);
        $receiverAuthData = $this->Auth_Model->get(['userId'=>$data['send_to'],'status'=>1]);
        if (!empty($receiverAuthData)) {
            foreach($receiverAuthData as $value) {
                $this->pushNotification($value->deviceToken, $notification, $extData, 0);
            }
        }

        return $savedId;
    }

    public function cancelledUserAppointmentByAuto($data) {
        if(empty($data)) {
            return false;
        }

        $savedId = "";
        $senderData = $this->Users_Model->get(['id'=>$data['send_from'],'status'=>1],true);
        $receiverData = $this->Users_Model->get(['id'=>$data['send_to'],'status'=>1],true);
        $userData = $this->Users_Model->get(['id'=>$data['userId'],'status'=>1],true);
        
        if(empty($senderData) || empty($receiverData) || empty($userData)){
            return false;
        }
    
        $data['model'] = "cancelledUserAppointmentByAuto";
        $data['title'] = "Cancelled Appointment";
        $data['desc'] = "Your appointment with ".$userData->name." has been auto cancelled";
        $notification = [
            "title"     => $data['title'],
            "body"      => $data['desc'],
            "badge"     => intval(0),
            "sound"     => "default"
        ];
        $extData = [
            "category"      => "cancelledUserAppointmentByAuto",
            "messageData"   => $data,
            "unread"        => (string) 0
        ];

        $savedId = $this->Notification->setData($data);
        $receiverAuthData = $this->Auth_Model->get(['userId'=>$data['send_to'],'status'=>1]);
        if (!empty($receiverAuthData)) {
            foreach($receiverAuthData as $value) {
                $this->pushNotification($value->deviceToken, $notification, $extData, 0);
            }
        }

        return $savedId;
    }

    public function cancelledDoctorAppointmentByAuto($data) {
        if(empty($data)) {
            return false;
        }

        $savedId = "";
        $senderData = $this->Users_Model->get(['id'=>$data['send_from'],'status'=>1],true);
        $receiverData = $this->Users_Model->get(['id'=>$data['send_to'],'status'=>1],true);
        $userData = $this->Users_Model->get(['id'=>$data['userId'],'status'=>1],true);
        
        if(empty($senderData) || empty($receiverData) || empty($userData)){
            return false;
        }
    
        $data['model'] = "cancelledDoctorAppointmentByAuto";
        $data['title'] = "Cancelled Appointment";
        $data['desc'] = "Your appointment with ".$userData->name." has been auto cancelled";
        $notification = [
            "title"     => $data['title'],
            "body"      => $data['desc'],
            "badge"     => intval(0),
            "sound"     => "default"
        ];
        $extData = [
            "category"      => "cancelledDoctorAppointmentByAuto",
            "messageData"   => $data,
            "unread"        => (string) 0
        ];

        $savedId = $this->Notification->setData($data);
        $receiverAuthData = $this->Auth_Model->get(['userId'=>$data['send_to'],'status'=>1]);
        if (!empty($receiverAuthData)) {
            foreach($receiverAuthData as $value) {
                $this->pushNotification($value->deviceToken, $notification, $extData, 0);
            }
        }

        return $savedId;
    }

    public function withdrawWalletAmountRequest($data) {
        if(empty($data)) {
            return false;
        }

        $savedId = "";
        $senderData = $this->Users_Model->get(['id'=>$data['send_from'],'status'=>1],true);
        $receiverData = $this->Users_Model->get(['id'=>$data['send_to'],'status'=>1],true);
        
        if(empty($senderData) || empty($receiverData)){
            return false;
        }
    
        $data['model'] = "withdrawWalletAmountRequest";
        $data['title'] = "Withdrawal Request";
        $data['desc'] = "Your withdrawal request ".$data['amount']." is under processing";
        $notification = [
            "title"     => $data['title'],
            "body"      => $data['desc'],
            "badge"     => intval(0),
            "sound"     => "default"
        ];
        $extData = [
            "category"      => "withdrawWalletAmountRequest",
            "messageData"   => $data,
            "unread"        => (string) 0
        ];

        $savedId = $this->Notification->setData($data);
        $receiverAuthData = $this->Auth_Model->get(['userId'=>$data['send_to'],'status'=>1]);
        if (!empty($receiverAuthData)) {
            foreach($receiverAuthData as $value) {
                $this->pushNotification($value->deviceToken, $notification, $extData, 0);
            }
        }

        return $savedId;
    }

    public function withdrawWalletInstantAmountFees($data) {
        if(empty($data)) {
            return false;
        }

        $savedId = "";
        $senderData = $this->Users_Model->get(['id'=>$data['send_from'],'status'=>1],true);
        $receiverData = $this->Users_Model->get(['id'=>$data['send_to'],'status'=>1],true);
        
        if(empty($senderData) || empty($receiverData)){
            return false;
        }
    
        $data['model'] = "withdrawWalletInstantAmountFees";
        $data['title'] = "Instant Withdrawal Fees";
        $data['desc'] = $data['amount']." deducted is your instant withdrawal request fees";
        $notification = [
            "title"     => $data['title'],
            "body"      => $data['desc'],
            "badge"     => intval(0),
            "sound"     => "default"
        ];
        $extData = [
            "category"      => "withdrawWalletInstantAmountFees",
            "messageData"   => $data,
            "unread"        => (string) 0
        ];

        $savedId = $this->Notification->setData($data);
        $receiverAuthData = $this->Auth_Model->get(['userId'=>$data['send_to'],'status'=>1]);
        if (!empty($receiverAuthData)) {
            foreach($receiverAuthData as $value) {
                $this->pushNotification($value->deviceToken, $notification, $extData, 0);
            }
        }

        return $savedId;
    }

    
    public function updateHubspotContact($data = array(),$email="") {
        if(empty($data) || empty($email)){
            return false;
        }
        /*
            //Sample Array
            $arr = array(
                'properties' => array(
                    array(
                        'property' => 'email',
                        'value' => 'apitest@hubspot.com'
                    ),
                    array(
                        'property' => 'firstname',
                        'value' => 'hubspot'
                    ),
                    array(
                        'property' => 'lastname',
                        'value' => 'user'
                    ),
                    array(
                        'property' => 'phone',
                        'value' => '555-1212'
                    )
                )
            );
        */

        $arr = array(
            'properties' => $data
        );
        $post_json = json_encode($arr);
        $hapikey = getenv('HUBSPOT_API_KEY');
        $endpoint = 'https://api.hubapi.com/contacts/v1/contact/email/'.$email.'/profile?hapikey=' . $hapikey;
        $ch = @curl_init();
        @curl_setopt($ch, CURLOPT_POST, true);
        @curl_setopt($ch, CURLOPT_POSTFIELDS, $post_json);
        @curl_setopt($ch, CURLOPT_URL, $endpoint);
        @curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = @curl_exec($ch);
        $status_code = @curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_errors = curl_error($ch);
        @curl_close($ch);
        //echo "curl Errors: " . $curl_errors;
        //echo "\nStatus code: " . $status_code;
        //echo "\nResponse: " . $response;
        return true;
    }
    
    public function createHubspotContact($data = array()) {
        if(empty($data)){
            return false;
        }
        /*
            //Sample Array
            $arr = array(
                'properties' => array(
                    array(
                        'property' => 'email',
                        'value' => 'apitest@hubspot.com'
                    ),
                    array(
                        'property' => 'firstname',
                        'value' => 'hubspot'
                    ),
                    array(
                        'property' => 'lastname',
                        'value' => 'user'
                    ),
                    array(
                        'property' => 'phone',
                        'value' => '555-1212'
                    )
                )
            );
        */

        $arr = array(
            'properties' => $data
        );
        $post_json = json_encode($arr);
        $hapikey = getenv('HUBSPOT_API_KEY');
        $endpoint = 'https://api.hubapi.com/contacts/v1/contact?hapikey=' . $hapikey;
        $ch = @curl_init();
        @curl_setopt($ch, CURLOPT_POST, true);
        @curl_setopt($ch, CURLOPT_POSTFIELDS, $post_json);
        @curl_setopt($ch, CURLOPT_URL, $endpoint);
        @curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = @curl_exec($ch);
        $status_code = @curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_errors = curl_error($ch);
        @curl_close($ch);
        //echo "curl Errors: " . $curl_errors;
        //echo "\nStatus code: " . $status_code;
        //echo "\nResponse: " . $response;
        return true;
    }
    
    public function addContactInListHubspot($data = array(),$role = "") {
        if(empty($data)){
            return false;
        }
        if(!in_array($role,array(2,3))){
            return false;
        }
        
        $hapikey = getenv('HUBSPOT_API_KEY');
        //Role = 2->User, 3->Provider(Doctor)
        if($role == 2){
            $listId = getenv('HUBSPOT_CONTACT_LIST_ID_USER');
        }else{
            $listId = getenv('HUBSPOT_CONTACT_LIST_ID_PROVIDER');
        }
        
        $arr = array(
            'emails' => $data
        );
        $post_json = json_encode($arr);
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.hubapi.com/contacts/v1/lists/'.$listId.'/add?hapikey='.$hapikey,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>$post_json,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));
        $response = curl_exec($curl);

        curl_close($curl);
        //echo $response;
        return true;
    }

    // Update provider availability entry
    public function updateProviderAvailability($userId = "", $myUserTimeZone = "") {
        if(empty($userId)){
            return false;
        }
        $this->load->model('User_Availability_Setting_Model');
        $this->load->model('User_Availability_Offtime_Model');
        $this->load->model('User_Availability_Model','User_Availability');
        $this->User_Availability->setData(['userIds'=>$userId,'notbooked'=>true,'status'=>2]);
        $availSetting = $this->User_Availability_Setting_Model->get(['userId'=>$userId,'status'=>1,'orderby'=>'type','orderstate'=>'ASC']);
        if(empty($availSetting)){
            return false;
        }
        $availOfftime = $this->User_Availability_Offtime_Model->get(['userId'=>$userId,'status'=>1]);
        $availOfftimeData = array();
        $currentYear = date('Y');
        $currentdate = new DateTime();

        if(!empty($availOfftime)){
            foreach($availOfftime as $value){
                //$startdatetime = new DateTime($currentYear.'-'.$value->month.'-'.$value->day.' '.$value->startTime);
                $startdatetime = new DateTime($currentYear.'-'.$value->month.'-'.$value->day.' '.$value->startTime, new DateTimeZone( $myUserTimeZone ));
                $startdatetime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));
                
                //$enddatetime = new DateTime($currentYear.'-'.$value->month.'-'.$value->day.' '.$value->endTime);
                $enddatetime = new DateTime($currentYear.'-'.$value->month.'-'.$value->day.' '.$value->endTime, new DateTimeZone( $myUserTimeZone ));
                $enddatetime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));
                $offcurrentdata = array();
                $offcurrentdata['startSkipTimestamp'] = $startdatetime->format('U');
                $offcurrentdata['starttimef'] = $startdatetime->format('Y-m-d H:i:s');
                $offcurrentdata['endSkipTimestamp'] = $enddatetime->format('U');
                $offcurrentdata['endtimef'] = $enddatetime->format('Y-m-d H:i:s');
                $availOfftimeData[$startdatetime->format('Ymd')][] = $offcurrentdata;
            }
        }
        //echo "<pre>"; print_r($availOfftimeData); die;

        $createdData = array();
        foreach($availSetting as $value){
            //$startdatetime = new DateTime($currentdate->format('Y-m-d').' '.$value->startTime);
            $startdatetime = new DateTime($currentdate->format('Y-m-d').' '.$value->startTime, new DateTimeZone( $myUserTimeZone ));
            $startdatetime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));
            //$enddatetime = new DateTime($currentdate->format('Y-m-d').' '.$value->endTime);
            $enddatetime = new DateTime($currentdate->format('Y-m-d').' '.$value->endTime, new DateTimeZone( $myUserTimeZone ));
            $enddatetime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));

            if($value->type == 1){
                for ($x = 1; $x <= 7; $x++) {
                    $temparray = array();
                    $temparray['start'] = $startdatetime->format('Y-m-d H:i:s');
                    $temparray['end'] = $enddatetime->format('Y-m-d H:i:s');
                    $temparray['slot'] = $this->getTimeSlots($startdatetime->format('U'), $enddatetime->format('U'), $value->timing);
                    
                    $createdData[$startdatetime->format('Ymd')] = $temparray;
                    $startdatetime->modify('+1 day');
                    $enddatetime->modify('+1 day');
                }
            }
            
            if($value->type == 2){
                for ($x = 1; $x <= 7; $x++) {
                    //if(!in_array($startdatetime->format('N'),array(6,7))){
                    if($startdatetime->format('N') != 7){
                        $temparray = array();
                        $temparray['start'] = $startdatetime->format('Y-m-d H:i:s');
                        $temparray['end'] = $enddatetime->format('Y-m-d H:i:s');
                        $temparray['slot'] = $this->getTimeSlots($startdatetime->format('U'), $enddatetime->format('U'), $value->timing);
                        $createdData[$startdatetime->format('Ymd')] = $temparray;
                    }
                    $startdatetime->modify('+1 day');
                    $enddatetime->modify('+1 day');
                }
            }
            
            if($value->type == 3){
                for ($x = 1; $x <= 7; $x++) {
                    if($startdatetime->format('N') == $value->day){
                        $temparray = array();
                        $temparray['start'] = $startdatetime->format('Y-m-d H:i:s');
                        $temparray['end'] = $enddatetime->format('Y-m-d H:i:s');
                        $temparray['slot'] = $this->getTimeSlots($startdatetime->format('U'), $enddatetime->format('U'), $value->timing);
                        $createdData[$startdatetime->format('Ymd')] = $temparray;
                    }
                    $startdatetime->modify('+1 day');
                    $enddatetime->modify('+1 day');
                }
            }
        }
        //echo "<pre>"; print_r($createdData); die;
        $finalslotarray = array();
        $skiptmparray = array();
        if(!empty($createdData)){
            foreach($createdData as $key => $value){
                if(isset($availOfftimeData[$key]) && !empty($availOfftimeData[$key])){
                    foreach($availOfftimeData[$key] as $skipvalue){
                        foreach($value['slot'] as $slotvalue){
                            if(($skipvalue['startSkipTimestamp'] < $slotvalue['startTimestamp']) && ($skipvalue['endSkipTimestamp'] > $slotvalue['startTimestamp']) 
                            || ($skipvalue['startSkipTimestamp'] < $slotvalue['endTimestamp']) && ($skipvalue['endSkipTimestamp'] > $slotvalue['endTimestamp'])){
                                $skiptmparray[] = $slotvalue['startTimestamp'];
                                continue;
                            }else{
                                if(!in_array($slotvalue['startTimestamp'],$skiptmparray)){
                                    $finalslotarray[$slotvalue['startTimestamp']] = $slotvalue;
                                }
                            }
                        }
                    }
                }else{
                    // Do entry for feature availability
                    foreach($value['slot'] as $slotvalue){
                        if(!in_array($slotvalue['startTimestamp'],$skiptmparray)){
                            $finalslotarray[$slotvalue['startTimestamp']] = $slotvalue;
                        }
                    }
                }

            }
        }
        if(!empty($skiptmparray)){
            foreach($skiptmparray as $value){
                if(isset($finalslotarray[$value])){
                    unset($finalslotarray[$value]);
                }
            }
        }

        if(!empty($finalslotarray)){
            foreach($finalslotarray as $value){
                $availabilityData = array();
                $availabilityData['userId'] = $userId;
                $availabilityData['timing'] = $value['timing'];
                $availabilityData['dateTime'] = $value['startTimestamp'];
                $availabilityData['endDateTime'] = $value['endTimestamp'];
                $availabilityData['status'] = 1;
        
                $existAvailabilityDataData = $this->User_Availability->get(['userId'=>$userId,'dateTime'=>$value['startTimestamp']],true);
                if(!empty($existAvailabilityDataData)){
                    $this->User_Availability->setData($availabilityData,$existAvailabilityDataData->id);
                }else{
                    $this->User_Availability->setData($availabilityData);
                }
            }
        }

        //echo "<pre>"; print_r($finalslotarray); die;
    }

    // Update provider availability entry NEW FLOW
    public function updateProviderAvailabilityNewSlot($userId = "", $myUserTimeZone = "", $doctorTimeZone = "", $serviceDuration = 30) {
        if(empty($userId)){
            return false;
        }
        $this->load->model('User_Availability_Setting_Model');
        $this->load->model('User_Availability_Offtime_Model');
        $this->load->model('User_Availability_Model','User_Availability');
        //$this->User_Availability->setData(['userIds'=>$userId,'notbooked'=>true,'status'=>2]);
        
        $availSetting = $this->User_Availability_Setting_Model->get(['userId'=>$userId,'status'=>1,'orderby'=>'type','orderstate'=>'ASC']);
        if(empty($availSetting)){
            return false;
        }
        
        $availOfftime = $this->User_Availability_Offtime_Model->get(['userId'=>$userId,'status'=>1]);
        $availOfftimeData = array();
        $currentYear = date('Y');
        $currentdate = new DateTime();
        if(!empty($availOfftime)) {
            foreach($availOfftime as $value) {
                //$startdatetime = new DateTime($currentYear.'-'.$value->month.'-'.$value->day.' '.$value->startTime);
                $startdatetime = new DateTime($currentYear.'-'.$value->month.'-'.$value->day.' '.$value->startTime, new DateTimeZone( $doctorTimeZone ));
                $startdatetime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));

                //$enddatetime = new DateTime($currentYear.'-'.$value->month.'-'.$value->day.' '.$value->endTime);
                $enddatetime = new DateTime($currentYear.'-'.$value->month.'-'.$value->day.' '.$value->endTime, new DateTimeZone( $doctorTimeZone ));
                $enddatetime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));
                $offcurrentdata = array();
                $offcurrentdata['startSkipTimestamp'] = $startdatetime->format('U');
                $offcurrentdata['starttimef'] = $startdatetime->format('Y-m-d H:i:s');
                $offcurrentdata['endSkipTimestamp'] = $enddatetime->format('U');
                $offcurrentdata['endtimef'] = $enddatetime->format('Y-m-d H:i:s');
                $availOfftimeData[$startdatetime->format('Ymd')][] = $offcurrentdata;
            }
        }
        //echo "<pre>"; print_r($availOfftimeData); die;

        $createdData = array();
        foreach($availSetting as $value){
            //$startdatetime = new DateTime($currentdate->format('Y-m-d').' '.$value->startTime);
            $startdatetime = new DateTime($currentdate->format('Y-m-d').' '.$value->startTime, new DateTimeZone( $doctorTimeZone ));
            $startdatetime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));
            //$enddatetime = new DateTime($currentdate->format('Y-m-d').' '.$value->endTime);
            $enddatetime = new DateTime($currentdate->format('Y-m-d').' '.$value->endTime, new DateTimeZone( $doctorTimeZone ));
            $enddatetime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));
            
            for ($x = 1; $x <= 30; $x++) {
                $temparray = array();
                $temparray['start'] = $startdatetime->format('Y-m-d H:i:s');
                $temparray['end'] = $enddatetime->format('Y-m-d H:i:s');

                $temparray["dayAndDate"] = $this->Common_Model->getDayAndDateName($startdatetime->getTimestamp(),$myUserTimeZone);
                $temparray['userId'] = $userId;

                $slotsAvailable = $this->planAvablitySlotWithBooked($userId, $startdatetime->format('Y-m-d'), $serviceDuration);

                $arr = [];
                if(!empty($slotsAvailable)) {
                    foreach ($slotsAvailable as $k => $v) {
                        $arr[] = [
                            "timeFormat" => $v["startTimesFormat"],
                            "dateTimeFormat" => date("d-m-Y H:i A", $v["startTimestamp"]),
                            "dateFormat" => $v["date"],
                            "isBooked" => $v["isBooked"],
                            "timing" => $serviceDuration,
                            "userId" => $userId,
                            "doctorStartTimestamp" => $v["startTimestamp"],
                            "doctorEndTimestamp" => $v["endTimestamp"],
                            "startTimestamp" => $v["startTimestamp"],
                            "endTimestamp" => $v["endTimestamp"]
                        ];
                    }
                }
                $temparray['slotsAvailable'] = $arr;
                $temparray['totalSlotsAvailable'] = count($slotsAvailable);
                
                $createdData[] = $temparray;
                $startdatetime->modify('+1 day');
                $enddatetime->modify('+1 day');
            }
            //echo "<pre>"; print_r($startdatetime);
        }
        return $createdData;
    }

    
    public function updateProviderAvailabilityNew($userId = "", $myUserTimeZone = "", $doctorTimeZone = "") {
        if(empty($userId)){
            return false;
        }
        $this->load->model('User_Availability_Setting_Model');
        $this->load->model('User_Availability_Offtime_Model');
        $this->load->model('User_Availability_Model','User_Availability');
        //$this->User_Availability->setData(['userIds'=>$userId,'notbooked'=>true,'status'=>2]);
        
        $availSetting = $this->User_Availability_Setting_Model->get(['userId'=>$userId,'status'=>1,'orderby'=>'type','orderstate'=>'ASC']);
        if(empty($availSetting)){
            return false;
        }
        
        $availOfftime = $this->User_Availability_Offtime_Model->get(['userId'=>$userId,'status'=>1]);
        $availOfftimeData = array();
        $currentYear = date('Y');
        $currentdate = new DateTime();

        if(!empty($availOfftime)){
            foreach($availOfftime as $value){
                //$startdatetime = new DateTime($currentYear.'-'.$value->month.'-'.$value->day.' '.$value->startTime);
                $startdatetime = new DateTime($currentYear.'-'.$value->month.'-'.$value->day.' '.$value->startTime, new DateTimeZone( $doctorTimeZone ));
                $startdatetime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));
                
                //$enddatetime = new DateTime($currentYear.'-'.$value->month.'-'.$value->day.' '.$value->endTime);
                $enddatetime = new DateTime($currentYear.'-'.$value->month.'-'.$value->day.' '.$value->endTime, new DateTimeZone( $doctorTimeZone ));
                $enddatetime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));
                $offcurrentdata = array();
                $offcurrentdata['startSkipTimestamp'] = $startdatetime->format('U');
                $offcurrentdata['starttimef'] = $startdatetime->format('Y-m-d H:i:s');
                $offcurrentdata['endSkipTimestamp'] = $enddatetime->format('U');
                $offcurrentdata['endtimef'] = $enddatetime->format('Y-m-d H:i:s');
                $availOfftimeData[$startdatetime->format('Ymd')][] = $offcurrentdata;
            }
        }
        //echo "<pre>"; print_r($availOfftimeData); die;

        $createdData = array();
        foreach($availSetting as $value){
            //$startdatetime = new DateTime($currentdate->format('Y-m-d').' '.$value->startTime);
            $startdatetime = new DateTime($currentdate->format('Y-m-d').' '.$value->startTime, new DateTimeZone( $doctorTimeZone ));
            $startdatetime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));
            //$enddatetime = new DateTime($currentdate->format('Y-m-d').' '.$value->endTime);
            $enddatetime = new DateTime($currentdate->format('Y-m-d').' '.$value->endTime, new DateTimeZone( $doctorTimeZone ));
            $enddatetime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));

            if($value->type == 1){
                for ($x = 1; $x <= 30; $x++) {
                    $temparray = array();
                    $temparray['start'] = $startdatetime->format('Y-m-d H:i:s');
                    $temparray['end'] = $enddatetime->format('Y-m-d H:i:s');
                    $temparray['slot'] = $this->getTimeSlots($startdatetime->format('U'), $enddatetime->format('U'), $value->timing);
                    
                    $createdData[$startdatetime->format('Ymd')] = $temparray;
                    $startdatetime->modify('+1 day');
                    $enddatetime->modify('+1 day');
                }
            }
            
            if($value->type == 2){
                for ($x = 1; $x <= 30; $x++) {
                    //if(!in_array($startdatetime->format('N'),array(6,7))){
                    if($startdatetime->format('N') != 7){
                        $temparray = array();
                        $temparray['start'] = $startdatetime->format('Y-m-d H:i:s');
                        $temparray['end'] = $enddatetime->format('Y-m-d H:i:s');
                        $temparray['slot'] = $this->getTimeSlots($startdatetime->format('U'), $enddatetime->format('U'), $value->timing);
                        $createdData[$startdatetime->format('Ymd')] = $temparray;
                    }
                    $startdatetime->modify('+1 day');
                    $enddatetime->modify('+1 day');
                }
            }
            
            if($value->type == 3){
                for ($x = 1; $x <= 30; $x++) {
                    if($startdatetime->format('N') == $value->day){
                        $temparray = array();
                        $temparray['start'] = $startdatetime->format('Y-m-d H:i:s');
                        $temparray['end'] = $enddatetime->format('Y-m-d H:i:s');
                        $temparray['slot'] = $this->getTimeSlots($startdatetime->format('U'), $enddatetime->format('U'), $value->timing);
                        $createdData[$startdatetime->format('Ymd')] = $temparray;
                    }
                    $startdatetime->modify('+1 day');
                    $enddatetime->modify('+1 day');
                }
            }
        }
        //echo "<pre>"; print_r($createdData); die;
        $finalslotarray = array();
        $skiptmparray = array();
        if(!empty($createdData)){
            foreach($createdData as $key => $value){
                if(isset($availOfftimeData[$key]) && !empty($availOfftimeData[$key])){
                    foreach($availOfftimeData[$key] as $skipvalue){
                        foreach($value['slot'] as $slotvalue){
                            if(($skipvalue['startSkipTimestamp'] < $slotvalue['startTimestamp']) && ($skipvalue['endSkipTimestamp'] > $slotvalue['startTimestamp']) 
                            || ($skipvalue['startSkipTimestamp'] < $slotvalue['endTimestamp']) && ($skipvalue['endSkipTimestamp'] > $slotvalue['endTimestamp'])){
                                $skiptmparray[] = $slotvalue['startTimestamp'];
                                continue;
                            }else{
                                if(!in_array($slotvalue['startTimestamp'],$skiptmparray)){
                                    $finalslotarray[$slotvalue['startTimestamp']] = $slotvalue;
                                }
                            }
                        }
                    }
                }else{
                    // Do entry for feature availability
                    foreach($value['slot'] as $slotvalue){
                        if(!in_array($slotvalue['startTimestamp'],$skiptmparray)){
                            $finalslotarray[$slotvalue['startTimestamp']] = $slotvalue;
                        }
                    }
                }

            }
        }
        if(!empty($skiptmparray)){
            foreach($skiptmparray as $value){
                if(isset($finalslotarray[$value])){
                    unset($finalslotarray[$value]);
                }
            }
        }

        //$bookedSlot = $this->User_Availability->get(['userId'=>$userId,'getFutureAvailability'=>true,'isBooked'=>1,'status'=>1]);
        //print_r($bookedSlot); die;
      
        $finalAvailability = array();
        $currentdatetime = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
        $currentdatetime->setTimezone(new DateTimeZone($myUserTimeZone));
        foreach($finalslotarray as $value){
            $startdatetime = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
            $startdatetime->setTimezone(new DateTimeZone($myUserTimeZone));
            $startdatetime->setTimestamp($value["startTimestamp"]);

            $enddatetime = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
            $enddatetime->setTimezone(new DateTimeZone($myUserTimeZone));
            $enddatetime->setTimestamp($value["endTimestamp"]);
            
            if($currentdatetime->format('U') > $startdatetime->format('U')){
                continue;
            }
            
            //$checkSlot = $this->User_Availability->get(['userId'=>$userId,'checkBookedSlot'=>['startDateTime'=>($apiData['data']['startDateTime'] - ($serviceData->bufferTimeBefore * 60)),'endDateTime'=>($apiData['data']['endDateTime'] + ($serviceData->bufferTimeAfter * 60))],'isBooked'=>1,'status'=>1],true);
            $checkSlot = $this->User_Availability->get(['userId'=>$userId,'checkBookedSlot'=>['startDateTime'=>$value["startTimestamp"],'endDateTime'=>$value["endTimestamp"]],'isBooked'=>1,'status'=>1],true);
            if(!empty($checkSlot)){
                continue;
            }
            if(empty($finalAvailability[$startdatetime->format('Y-m-d')])){
                $mainTmp = array();
                $mainTmp["userId"] = $userId;
                $mainTmp["dayAndDate"] = $this->Common_Model->getDayAndDateName($value["startTimestamp"],$myUserTimeZone);
                $mainTmp["totalSlotsAvailable"] = 0;
                $finalAvailability[$startdatetime->format('Y-m-d')] = $mainTmp;
            }

            // Create a slots array
            $subTmp = array();
            $subTmp["userId"] = $userId;
            $subTmp["doctorStartTimestamp"] = $startdatetime->format('U');
            $subTmp["doctorEndTimestamp"] = $enddatetime->format('U');
            $subTmp["startTimestamp"] = $value["startTimestamp"];
            $subTmp["endTimestamp"] = $value["endTimestamp"];
            $subTmp["dateTimeFormat"] = $startdatetime->format('d-m-Y h:i A');
            $subTmp["dateFormat"] = $startdatetime->format('d-m-Y');
            $subTmp["timeFormat"] = $startdatetime->format('h:i A');
            $subTmp["isBooked"] = 0;
            $subTmp["timing"] = $value["timing"];
            $finalAvailability[$startdatetime->format('Y-m-d')]["slotsAvailable"][] = $subTmp;
            $finalAvailability[$startdatetime->format('Y-m-d')]["totalSlotsAvailable"]++;
        }
        return array_values($finalAvailability);
    }
    

    // Update provider services availability entry NEW FLOW
    public function updateProviderServicesAvailabilityNew($userId = "", $myUserTimeZone = "", $doctorTimeZone = "", $serviceId = 0) {
        if(empty($userId) || empty($serviceId)){
            return false;
        }
        $this->load->model('User_Availability_Setting_Model');
        $this->load->model('User_Availability_Offtime_Model');
        $this->load->model('User_Services_Model','Services');
        
        $availSetting = $this->User_Availability_Setting_Model->get(['userId'=>$userId,'status'=>1,'orderby'=>'type','orderstate'=>'ASC']);
        if(empty($availSetting)){
            return false;
        }
        
        $serviceData = $this->Services->get(['id'=>$serviceId,'status'=>1],true);
        if(empty($serviceData)){
            return false;
        }

        $timing = $serviceData->duration;

        $availOfftime = $this->User_Availability_Offtime_Model->get(['userId'=>$userId,'status'=>1]);
        $availOfftimeData = array();
        $currentYear = date('Y');
        $currentdate = new DateTime();
        //$currentdate->setTimezone(new DateTimeZone($doctorTimeZone));

        if(!empty($availOfftime)){
            foreach($availOfftime as $value){
                //$startdatetime = new DateTime($currentYear.'-'.$value->month.'-'.$value->day.' '.$value->startTime);
                $startdatetime = new DateTime($currentYear.'-'.$value->month.'-'.$value->day.' '.$value->startTime, new DateTimeZone( $doctorTimeZone ));
                $startdatetime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));
                
                //$enddatetime = new DateTime($currentYear.'-'.$value->month.'-'.$value->day.' '.$value->endTime);
                $enddatetime = new DateTime($currentYear.'-'.$value->month.'-'.$value->day.' '.$value->endTime, new DateTimeZone( $doctorTimeZone ));
                $enddatetime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));
                $offcurrentdata = array();
                $offcurrentdata['startSkipTimestamp'] = $startdatetime->format('U');
                $offcurrentdata['starttimef'] = $startdatetime->format('Y-m-d H:i:s');
                $offcurrentdata['endSkipTimestamp'] = $enddatetime->format('U');
                $offcurrentdata['endtimef'] = $enddatetime->format('Y-m-d H:i:s');
                $availOfftimeData[$startdatetime->format('Ymd')][] = $offcurrentdata;
            }
        }
        //echo "<pre>"; print_r($availOfftimeData); die;
        $mainCreatedDate = array();
        foreach($availSetting as $value){
            $createdData = array();
            //$startdatetime = new DateTime($currentdate->format('Y-m-d').' '.$value->startTime);
            $startdatetime = new DateTime($currentdate->format('Y-m-d').' '.$value->startTime, new DateTimeZone( $doctorTimeZone ));
            $startdatetime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));
            //$enddatetime = new DateTime($currentdate->format('Y-m-d').' '.$value->endTime);
            $enddatetime = new DateTime($currentdate->format('Y-m-d').' '.$value->endTime, new DateTimeZone( $doctorTimeZone ));
            $enddatetime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));

            if($value->type == 1){
                for ($x = 1; $x <= 30; $x++) {
                    $temparray = array();
                    $temparray['start'] = $startdatetime->format('Y-m-d H:i:s');
                    $temparray['end'] = $enddatetime->format('Y-m-d H:i:s');
                    $temparray['slot'] = $this->getTimeSlots($startdatetime->format('U'), $enddatetime->format('U'), $timing, $serviceData->bufferTimeBefore, $serviceData->bufferTimeAfter);
                    
                    $createdData[$startdatetime->format('Ymd')] = $temparray;
                    $startdatetime->modify('+1 day');
                    $enddatetime->modify('+1 day');
                }
            }
            
            if($value->type == 2){
                for ($x = 1; $x <= 30; $x++) {
                    //if(!in_array($startdatetime->format('N'),array(6,7))){
                    if($startdatetime->format('N') != 7){
                        $temparray = array();
                        $temparray['start'] = $startdatetime->format('Y-m-d H:i:s');
                        $temparray['end'] = $enddatetime->format('Y-m-d H:i:s');
                        $temparray['slot'] = $this->getTimeSlots($startdatetime->format('U'), $enddatetime->format('U'), $timing, $serviceData->bufferTimeBefore, $serviceData->bufferTimeAfter);
                        $createdData[$startdatetime->format('Ymd')] = $temparray;
                    }
                    $startdatetime->modify('+1 day');
                    $enddatetime->modify('+1 day');
                }
            }
            
            if($value->type == 3){
                for ($x = 1; $x <= 30; $x++) {
                    if($startdatetime->format('N') == $value->day){
                        $temparray = array();
                        $temparray['start'] = $startdatetime->format('Y-m-d H:i:s');
                        $temparray['end'] = $enddatetime->format('Y-m-d H:i:s');
                        $temparray['slot'] = $this->getTimeSlots($startdatetime->format('U'), $enddatetime->format('U'), $timing, $serviceData->bufferTimeBefore, $serviceData->bufferTimeAfter);
                        $createdData[$startdatetime->format('Ymd')] = $temparray;
                    }
                    $startdatetime->modify('+1 day');
                    $enddatetime->modify('+1 day');
                }
            }
            $mainCreatedDate[] = $createdData;
        }
        $finalslotarray = array();
        $skiptmparray = array();
        if(!empty($mainCreatedDate)){
            foreach($mainCreatedDate as $key1 => $value1){
                foreach($value1 as $key => $value){
                    if(isset($availOfftimeData[$key]) && !empty($availOfftimeData[$key])){
                        foreach($availOfftimeData[$key] as $skipvalue){
                            foreach($value['slot'] as $slotvalue){
                                if(($skipvalue['startSkipTimestamp'] < $slotvalue['startTimestamp']) && ($skipvalue['endSkipTimestamp'] > $slotvalue['startTimestamp']) 
                                || ($skipvalue['startSkipTimestamp'] < $slotvalue['endTimestamp']) && ($skipvalue['endSkipTimestamp'] > $slotvalue['endTimestamp'])){
                                    $skiptmparray[] = $slotvalue['startTimestamp'];
                                    continue;
                                }else{
                                    if(!in_array($slotvalue['startTimestamp'],$skiptmparray)){
                                        $finalslotarray[$slotvalue['startTimestamp']] = $slotvalue;
                                    }
                                }
                            }
                        }
                    }else{
                        // Do entry for feature availability
                        foreach($value['slot'] as $slotvalue){
                            if(!in_array($slotvalue['startTimestamp'],$skiptmparray)){
                                $finalslotarray[$slotvalue['startTimestamp']] = $slotvalue;
                            }
                        }
                    }
                }
                // echo "<pre>"; print_r($value); die;
            }
        }
        if(!empty($skiptmparray)){
            foreach($skiptmparray as $value){
                if(isset($finalslotarray[$value])){
                    unset($finalslotarray[$value]);
                }
            }
        }

        // echo "<pre>"; print_r($finalslotarray); die;
        
        $finalAvailability = array();
        $currentdatetime = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
        $currentdatetime->setTimezone(new DateTimeZone($myUserTimeZone));
        foreach($finalslotarray as $value){
            $startdatetime = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
            $startdatetime->setTimezone(new DateTimeZone($myUserTimeZone));
            $startdatetime->setTimestamp($value["startTimestamp"]);
            
            $enddatetime = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
            $enddatetime->setTimezone(new DateTimeZone($myUserTimeZone));
            $enddatetime->setTimestamp($value["endTimestamp"]);

            if($currentdatetime->format('U') > $startdatetime->format('U')){
                continue;
            }

            $checkSlot = $this->User_Availability->get(['userId'=>$userId,'checkBookedSlot'=>['startDateTime'=>($value["startTimestamp"] - ($serviceData->bufferTimeBefore * 60)),'endDateTime'=>($value["endTimestamp"] + ($serviceData->bufferTimeAfter * 60))],'isBooked'=>1,'status'=>1],true);
            if(!empty($checkSlot)){
                continue;
            }
            
            if(empty($finalAvailability[$startdatetime->format('Y-m-d')])){
                $mainTmp = array();
                $mainTmp["userId"] = $userId;
                $mainTmp["dayAndDate"] = $this->Common_Model->getDayAndDateName($value["startTimestamp"],$myUserTimeZone);
                $mainTmp["totalSlotsAvailable"] = 0;
                $finalAvailability[$startdatetime->format('Y-m-d')] = $mainTmp;
            }

            // Create a slots array
            $subTmp = array();
            $subTmp["userId"] = $userId;
            $subTmp["doctorStartTimestamp"] = $startdatetime->format('U');
            $subTmp["doctorEndTimestamp"] = $enddatetime->format('U');
            $subTmp["startTimestamp"] = $value["startTimestamp"];
            $subTmp["endTimestamp"] = $value["endTimestamp"];
            $subTmp["dateTimeFormat"] = $startdatetime->format('d-m-Y h:i A');
            $subTmp["dateFormat"] = $startdatetime->format('d-m-Y');
            $subTmp["timeFormat"] = $startdatetime->format('h:i A');
            $subTmp["isBooked"] = 0;
            $subTmp["timing"] = $value["timing"];
            $finalAvailability[$startdatetime->format('Y-m-d')]["slotsAvailable"][] = $subTmp;
            $finalAvailability[$startdatetime->format('Y-m-d')]["totalSlotsAvailable"]++;
        }
        ksort($finalAvailability);
        return array_values($finalAvailability);
    }

    public function updateProviderServicesAvailabilityNewSlot($userId = "", $myUserTimeZone = "", $doctorTimeZone = "", $serviceId = 0) {
        if(empty($userId)){
            return false;
        }
        $this->load->model('User_Availability_Setting_Model');
        $this->load->model('User_Availability_Offtime_Model');
        $this->load->model('User_Availability_Model','User_Availability');
        //$this->User_Availability->setData(['userIds'=>$userId,'notbooked'=>true,'status'=>2]);

        $userData = $this->User->userData($userId, false);
        $user_latitude = $userData->latitude;
        $user_longitude = $userData->longitude;
        
        $serviceData = $this->Services->get(['id'=>$serviceId,'status'=>1],true);
        if(empty($serviceData)){
            return false;
        }
        $serviceDuration = $serviceData->duration;

        $arr_availSetting = ['userId'=>$userId,'status'=>1,'orderby'=>'type','orderstate'=>'ASC'];
        if(isset($serviceData->type) && $serviceData->type == 2) {
            $arr_availSetting["inHome"] = 1;
            $arr_availSetting["getInRadius"] = true;
        }
        else if(isset($serviceData->type) && $serviceData->type == 3) {
            $arr_availSetting["officeGym"] = 1;
            $arr_availSetting["getInRadius"] = true;
        }
        $arr_availSetting["latitude"] = $user_latitude;
        $arr_availSetting["longitude"] = $user_longitude;

        $availSetting = $this->User_Availability_Setting_Model->get($arr_availSetting);
        if(empty($availSetting)){
            return false;
        }
        #echo "<pre>"; print_r($userData); die;
        #echo "<pre>"; print_r($availSetting); die;

        $availOfftime = $this->User_Availability_Offtime_Model->get(['userId'=>$userId,'status'=>1]);
        $availOfftimeData = array();
        $currentYear = date('Y');
        $currentdate = new DateTime();
        if(!empty($availOfftime)) {
            foreach($availOfftime as $value) {
                //$startdatetime = new DateTime($currentYear.'-'.$value->month.'-'.$value->day.' '.$value->startTime);
                $startdatetime = new DateTime($currentYear.'-'.$value->month.'-'.$value->day.' '.$value->startTime, new DateTimeZone( $doctorTimeZone ));
                $startdatetime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));

                //$enddatetime = new DateTime($currentYear.'-'.$value->month.'-'.$value->day.' '.$value->endTime);
                $enddatetime = new DateTime($currentYear.'-'.$value->month.'-'.$value->day.' '.$value->endTime, new DateTimeZone( $doctorTimeZone ));
                $enddatetime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));
                $offcurrentdata = array();
                $offcurrentdata['startSkipTimestamp'] = $startdatetime->format('U');
                $offcurrentdata['starttimef'] = $startdatetime->format('Y-m-d H:i:s');
                $offcurrentdata['endSkipTimestamp'] = $enddatetime->format('U');
                $offcurrentdata['endtimef'] = $enddatetime->format('Y-m-d H:i:s');
                $availOfftimeData[$startdatetime->format('Ymd')][] = $offcurrentdata;
            }
        }

        $createdData = array();
        foreach($availSetting as $value){
            //$startdatetime = new DateTime($currentdate->format('Y-m-d').' '.$value->startTime);
            $startdatetime = new DateTime($currentdate->format('Y-m-d').' '.$value->startTime, new DateTimeZone( $doctorTimeZone ));
            $startdatetime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));
            //$enddatetime = new DateTime($currentdate->format('Y-m-d').' '.$value->endTime);
            $enddatetime = new DateTime($currentdate->format('Y-m-d').' '.$value->endTime, new DateTimeZone( $doctorTimeZone ));
            $enddatetime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));
            
            for ($x = 1; $x <= 30; $x++) {
                $temparray = array();
                #$temparray['start'] = $startdatetime->format('Y-m-d H:i:s');
                #$temparray['end'] = $enddatetime->format('Y-m-d H:i:s');

                $temparray["dayAndDate"] = $this->Common_Model->getDayAndDateName($startdatetime->getTimestamp(),$myUserTimeZone);
                $temparray['userId'] = $userId;
                $slotsAvailable = $this->planAvablitySlotWithBooked_New($userId, $startdatetime->format('Y-m-d'), $serviceDuration,$myUserTimeZone,$value->startTime, $value->endTime);
                #echo $startdatetime->format('Y-m-d')."=". $enddatetime->format('Y-m-d'); echo "<pre>"; print_r($value); die;
                $arr = [];
                if(!empty($slotsAvailable)) {
                    foreach ($slotsAvailable as $k => $v) {
                        $fstartdatetime = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
                        $fstartdatetime->setTimestamp($v["startTimestamp"]);
                        $fstartdatetime->setTimezone(new DateTimeZone($myUserTimeZone));
                        
                        $fendTimestamp = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
                        $fendTimestamp->setTimestamp($v["endTimestamp"]);
                        $fendTimestamp->setTimezone(new DateTimeZone($myUserTimeZone));
                        
                        
                        $arr[] = [
                            "timeFormat" => $fstartdatetime->format("h:i A"),
                            "dateTimeFormat" => $fstartdatetime->format("d-m-Y h:i A"),
                            "dateFormat" => $fstartdatetime->format("Y-m-d"),
                            "isBooked" => $v["isBooked"],
                            "timing" => $serviceDuration,
                            "userId" => $userId,
                            "doctorStartTimestamp" => $fstartdatetime->format("U"),
                            "doctorEndTimestamp" => $fendTimestamp->format("U"),
                            "startTimestamp" => $v["startTimestamp"],
                            "endTimestamp" => $v["endTimestamp"]
                        ];
                    }
                }
                $temparray['slotsAvailable'] = $arr;
                $temparray['totalSlotsAvailable'] = count($slotsAvailable);
                $createdData[] = $temparray;

                /* if($value->type == 3) { //1->Every Day, 2->Monday to Friday, 3->Day of week(Monday, Tuesday, Wednesday, Thursday, Friday, Saturday, Sunday)
                    $startdatetime->modify('+7 day');
                    $enddatetime->modify('+7 day');
                }
                else {
                    $startdatetime->modify('+1 day');
                    $enddatetime->modify('+1 day');
                } */
                $startdatetime->modify('+1 day');
                $enddatetime->modify('+1 day');
            }
            //echo "<pre>"; print_r($startdatetime);
        }
        return $createdData;
    }

    function getTimeSlots($StartTime, $EndTime, $Duration="30",$bufferTimeBefore = 0, $bufferTimeAfter = 0){
       
        $ReturnArray = array();// Define output
        $StartTime = $StartTime; //Get Timestamp
        $EndTime = $EndTime; //Get Timestamp
        
        $AddMins = $Duration * 60;
        $startBufferTime = $bufferTimeBefore * 60;
        $endBufferTime = $bufferTimeAfter * 60;
        
        $skiparrays = array();
        while ($StartTime <= $EndTime) //Run loop
        {
            $StartTime = $StartTime + $startBufferTime;
            if(($StartTime+$AddMins) <= $EndTime){
                $ReturnTempArray = array();
                $ReturnTempArray['startDateTime'] = date("Y-m-d H:i:s", $StartTime);
                $ReturnTempArray['endDateTime'] = date("Y-m-d H:i:s", ($StartTime+$AddMins));
                $ReturnTempArray['startTimestamp'] = $StartTime;
                $ReturnTempArray['endTimestamp'] = ($StartTime+$AddMins);
                $ReturnTempArray['startDateTimeWithBuffer'] = date("Y-m-d H:i:s", $StartTime-$startBufferTime);
                $ReturnTempArray['endDateTimeWithBuffer'] = date("Y-m-d H:i:s", ($StartTime+$AddMins+$endBufferTime));
                $ReturnTempArray['startTimestampWithBuffer'] = $StartTime-$startBufferTime;
                $ReturnTempArray['endTimestampWithBuffer'] = ($StartTime+$AddMins+$endBufferTime);
                $ReturnTempArray['startBufferTime'] = $bufferTimeBefore;
                $ReturnTempArray['endBufferTime'] = $bufferTimeAfter;
                $ReturnTempArray['date'] = date("Y-m-d", $StartTime);
                $ReturnTempArray['timing'] = $Duration;
                $ReturnArray[] = $ReturnTempArray;
            }
            $StartTime += $AddMins + $endBufferTime; //Endtime check
        }
        return $ReturnArray;
    }

    // Update provider availability entry
    public function updateProviderAvailability_OLD($userId = "", $myUserTimeZone = "") {
        if(empty($userId)){
            return false;
        }
        $this->load->model('User_Availability_Setting_Model');
        $this->load->model('User_Availability_Offtime_Model');
        $availSetting = $this->User_Availability_Setting_Model->get(['userId'=>$userId,'status'=>1,'orderby'=>'type','orderstate'=>'ASC']);
        if(empty($availSetting)){
            return false;
        }
        $availOfftime = $this->User_Availability_Offtime_Model->get(['userId'=>$userId,'status'=>1]);
        $availOfftimeData = array();
        $currentYear = date('Y');
        $currentdate = new DateTime();

        if(!empty($availOfftime)){
            foreach($availOfftime as $value){
                $startdatetime = new DateTime($currentYear.'-'.$value->month.'-'.$value->day.' '.$value->startTime);
                //$startdatetime = new DateTime($currentYear.'-'.$value->month.'-'.$value->day.' '.$value->startTime, new DateTimeZone( $myUserTimeZone ));
                //$startdatetime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));
                
                $enddatetime = new DateTime($currentYear.'-'.$value->month.'-'.$value->day.' '.$value->endTime);
                //$enddatetime = new DateTime($currentYear.'-'.$value->month.'-'.$value->day.' '.$value->endTime, new DateTimeZone( $myUserTimeZone ));
                //$enddatetime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));
                $offcurrentdata = array();
                $offcurrentdata['starttime'] = $startdatetime->format('U');
                $offcurrentdata['starttimef'] = $startdatetime->format('Y-m-d H:i:s');
                $offcurrentdata['endtime'] = $enddatetime->format('U');
                $offcurrentdata['endtimef'] = $enddatetime->format('Y-m-d H:i:s');
                $availOfftimeData[$startdatetime->format('Y-m-d')][] = $offcurrentdata;
            }
        }
        //echo "<pre>"; print_r($availOfftimeData); die;

        $createdData = array();
        foreach($availSetting as $value){
            if($value->type == 1){
                $startdatetime = new DateTime($currentdate->format('Y-m-d').' '.$value->startTime);
                $enddatetime = new DateTime($currentdate->format('Y-m-d').' '.$value->endTime);

                for ($x = 1; $x <= 7; $x++) {
                    $temparray = array();
                    $temparray['start'] = $startdatetime->format('Y-m-d H:i:s');
                    $temparray['end'] = $enddatetime->format('Y-m-d H:i:s');
                    if(!empty($availOfftimeData)){
                        if(isset($availOfftimeData[$startdatetime->format('Y-m-d')]) && !empty($availOfftimeData[$startdatetime->format('Y-m-d')])){
                            $temparray['slot'] = $this->getTimeSlots($startdatetime->format('U'), $enddatetime->format('U'), $value->timing, $availOfftimeData[$startdatetime->format('Y-m-d')]);
                        }else{
                            $temparray['slot'] = $this->getTimeSlots($startdatetime->format('U'), $enddatetime->format('U'), $value->timing);
                        }
                    }else{
                        $temparray['slot'] = $this->getTimeSlots($startdatetime->format('U'), $enddatetime->format('U'), $value->timing);
                    }
                    
                    $createdData[] = $temparray;
                    $startdatetime->modify('+1 day');
                    $enddatetime->modify('+1 day');
                }
            }
        }
        echo "<pre>"; print_r($createdData); die;
        //$this->getTimeSlots($StartTime, $EndTime, $value->timing);

    }

    function getTimeSlots_OLD($StartTime, $EndTime, $Duration="30",$skipSlot=array()){
       
        $ReturnArray = array();// Define output
        $StartTime = $StartTime; //Get Timestamp
        $EndTime = $EndTime; //Get Timestamp
        
        $AddMins = $Duration * 60;
        
        $skiparrays = array();
        while ($StartTime <= $EndTime) //Run loop
        {
            
            if(!empty($skipSlot)){
                foreach($skipSlot as $k => $skvalue){
                    $skiparray['StartTime'] = date("Y-m-d H:i:s", $StartTime);
                    $skiparray['StartTimeAdd'] = date("Y-m-d H:i:s",($StartTime+$AddMins));
                    $skiparray['starttimeskip'] = date("Y-m-d H:i:s", $skvalue['starttime']);
                    $skiparray['endtimeskip'] = date("Y-m-d H:i:s",$skvalue['endtime']);
                    $skiparrays[] = $skiparray;
                    //if(($StartTime+$AddMins) <= $skvalue['starttime'] && ($StartTime+$AddMins) >= $skvalue['endtime']){
                        //11 >= 12 && 17 >= 11
                    if($StartTime >= $skvalue['starttime'] && $skvalue['endtime'] >= ($StartTime+$AddMins)){
                        /*echo $StartTime+$AddMins.'</br>';
                        echo $skvalue['starttime'].'</br>';
                        echo $skvalue['endtime']; die;
                        echo date("Y-m-d H:i:s", $StartTime).'</br>';*/
                        $StartTime += $AddMins;
                    }else{
                        if(($StartTime+$AddMins) <= $EndTime){
                            $ReturnArray[] = date("Y-m-d H:i:s", $StartTime);
                        }
                        $StartTime += $AddMins;
                    }
                }




                /*if(($StartTime+$AddMins) <= $EndTime){
                    $ReturnArray[] = date("Y-m-d H:i:s", $StartTime);
                }
                $StartTime += $AddMins; //Endtime check*/
            }else{
                if(($StartTime+$AddMins) <= $EndTime){
                    $ReturnArray[] = date("Y-m-d H:i:s", $StartTime);
                }
                $StartTime += $AddMins; //Endtime check
            }
        }
        echo "<pre>"; print_r($skiparrays);
        return $ReturnArray;
    }

    /**
     *  CRON 24 hours push notification, mail, sms alert.
     */
    public function C005C_24hours_license_certificate_expiration($professionalData = "") {
        if(empty($professionalData)) {
            return false;
        }

        $userData = $this->Users_Model->get(['id'=>$professionalData->userId, 'status'=>1],true);

        if(empty($userData)) {
            return false;
        }
        
        /*if(!empty($userData->phone_code) && !empty($userData->phone)){
            $phone = preg_replace("/[^0-9]/", "", $userData->phone);
            $phone_code = str_replace("+", "", $userData->phone_code);
            $this->twilio->sendTextMessage('+'.$phone_code.''.$phone, 'Your license certificate expire at '.date('d-m-Y',strtotime($professionalData->insuranceEndDate)));
        }

        if (!empty($userData->email)) {
            $mailBody = $this->load->view('Mail/LicenseCertificateExpirationAlertMail', ['user' => $userData,'professionalData'=>$professionalData], TRUE);
            $this->Common_Model->mailsend($userData->email, getenv('EMAIL_SUBJECT') . " License Certificate Expiration.", $mailBody);
        }*/

        $data['send_to'] = $userData->id;
        $data['model_id'] = (int)$userData->id;
        $data['model'] = "licenseCertificateExpirationAlert";
        $data['title'] = "Your License Certificate Expiration";
        $data['desc'] = "Your license certificate expire at ".date('d-m-Y',strtotime($professionalData->insuranceEndDate));
        $notification = [
            "title" => $data['title'],
            "body" => $data['desc'],
            "badge" => intval(0),
            "sound" => "default"
        ];
        $extData = [
            "category" => "licenseCertificateExpirationAlert",
            "messageData" => $data,
            "unread" => (string) 0
        ];

        //$this->Notification->setData($data);
        $receiverAuthData = $this->Auth_Model->get(['userId' => $userData->id,'status'=>1 ]);
        if(!empty($receiverAuthData)) {
            foreach($receiverAuthData as $val) {
                $this->pushNotification($val->deviceToken, $notification, $extData, 0);
            }
        }
        return true;
    }

    /**
     *  CRON 24 hours push notification, mail, sms alert.
     */
    public function C006C_24hours_license_certificate_expired_account_alert($professionalData = "") {
        if(empty($professionalData)) {
            return false;
        }

        $userData = $this->Users_Model->get(['id'=>$professionalData->userId, 'status'=>1],true);

        if(empty($userData)) {
            return false;
        }
        
        /*if(!empty($userData->phone_code) && !empty($userData->phone)){
            $phone = preg_replace("/[^0-9]/", "", $userData->phone);
            $phone_code = str_replace("+", "", $userData->phone_code);
            $this->twilio->sendTextMessage('+'.$phone_code.''.$phone, 'Your license certificate is expired on '.date('d-m-Y',strtotime($professionalData->insuranceEndDate)));
        }

        if (!empty($userData->email)) {
            $mailBody = $this->load->view('Mail/LicenseCertificateExpiredAlertMail', ['user' => $userData,'professionalData'=>$professionalData], TRUE);
            $this->Common_Model->mailsend($userData->email, getenv('EMAIL_SUBJECT') . " License Certificate Expired.", $mailBody);
        }*/

        $data['send_to'] = $userData->id;
        $data['model_id'] = (int)$userData->id;
        $data['model'] = "licenseCertificateExpiredAlert";
        $data['title'] = "Your License Certificate Expired";
        $data['desc'] = "Your license certificate is expired on ".date('d-m-Y',strtotime($professionalData->insuranceEndDate));
        $notification = [
            "title" => $data['title'],
            "body" => $data['desc'],
            "badge" => intval(0),
            "sound" => "default"
        ];
        $extData = [
            "category" => "licenseCertificateExpiredAlert",
            "messageData" => $data,
            "unread" => (string) 0
        ];

        //$this->Notification->setData($data);
        $receiverAuthData = $this->Auth_Model->get(['userId' => $userData->id,'status'=>1 ]);
        if(!empty($receiverAuthData)) {
            foreach($receiverAuthData as $val) {
                $this->pushNotification($val->deviceToken, $notification, $extData, 0);
            }
        }
        return true;
    }

    /**
     *  CRON Every 1 minute push notification, mail, sms alert.
     */
    public function C007C_1minute_followup_after_visit_1hour_provider_profile($followupData = "") {
        if(empty($followupData)) {
            return false;
        }

        $userData = $this->Users_Model->get(['id'=>$followupData->userId, 'status'=>1],true);

        if(empty($userData)) {
            return false;
        }

        $doctorData = $this->Users_Model->get(['id'=>$followupData->doctorId, 'status'=>1],true);

        if(empty($doctorData)) {
            return false;
        }
        
        /*if(!empty($userData->phone_code) && !empty($userData->phone)){
            $phone = preg_replace("/[^0-9]/", "", $userData->phone);
            $phone_code = str_replace("+", "", $userData->phone_code);
            $this->twilio->sendTextMessage('+'.$phone_code.''.$phone, 'You recently visited on '.$doctorData->name.' provider profile. open profile '.base_url('app-link/provider-profile/'.$doctorData->id));
        }

        if (!empty($userData->email)) {
            $mailBody = $this->load->view('Mail/FollowupAfterVisit1hourProviderProfile', ['user' => $userData,'doctorData'=>$doctorData], TRUE);
            $this->Common_Model->mailsend($userData->email, getenv('EMAIL_SUBJECT') . " You Recently Visited Provider Profile.", $mailBody);
        }*/

        $data['send_to'] = $userData->id;
        $data['model_id'] = (int)$doctorData->id;
        $data['model'] = "followupAfterVisit1hourProviderProfile";
        $data['title'] = "You Recently Visit Provider Profile";
        $data['desc'] = "You recently visited on ".$doctorData->name." provider profile";
        $notification = [
            "title" => $data['title'],
            "body" => $data['desc'],
            "badge" => intval(0),
            "sound" => "default"
        ];
        $extData = [
            "category" => "followupAfterVisit1hourProviderProfile",
            "messageData" => $data,
            "unread" => (string) 0
        ];

        //$this->Notification->setData($data);
        $receiverAuthData = $this->Auth_Model->get(['userId' => $userData->id,'status'=>1 ]);
        if(!empty($receiverAuthData)) {
            foreach($receiverAuthData as $val) {
                $this->pushNotification($val->deviceToken, $notification, $extData, 0);
            }
        }
        return true;
    }

    /**
     *  CRON Every 1 minute push notification.
     */
    public function C008C_1minute_next_visit_not_scheduled_after_completed_appointment($followupData = "") {
        if(empty($followupData)) {
            return false;
        }

        $userData = $this->Users_Model->get(['id'=>$followupData->userId, 'status'=>1],true);

        if(empty($userData)) {
            return false;
        }

        $doctorData = $this->Users_Model->get(['id'=>$followupData->doctorId, 'status'=>1],true);

        if(empty($doctorData)) {
            return false;
        }

        $data['send_to'] = $userData->id;
        $data['model_id'] = (int)$doctorData->id;
        $data['model'] = "nextVisitNotScheduledAfterCompletedAppointment";
        $data['title'] = "You Recently Scheduled Appointment";
        $data['desc'] = "You recently schedule an appointment with ".$doctorData->name.". If you need to schedule the next appointment with ".$doctorData->name."?";
        $notification = [
            "title" => $data['title'],
            "body" => $data['desc'],
            "badge" => intval(0),
            "sound" => "default"
        ];
        $extData = [
            "category" => "nextVisitNotScheduledAfterCompletedAppointment",
            "messageData" => $data,
            "unread" => (string) 0
        ];

        $receiverAuthData = $this->Auth_Model->get(['userId' => $userData->id,'status'=>1 ]);
        if(!empty($receiverAuthData)) {
            foreach($receiverAuthData as $val) {
                $this->pushNotification($val->deviceToken, $notification, $extData, 0);
            }
        }
        return true;
    }

    public function appointmentNoPayment($appoData = ""){
        $this->load->model('User_Appointment_Model');

        if(empty($appoData)){
            return false;
        }

        $userData = $this->Users_Model->get(['id'=>$appoData->userId, 'status'=>1],true);

        if(empty($userData)) {
            return false;
        }

        $doctorData = $this->Users_Model->get(['id'=>$appoData->doctorId, 'status'=>1],true);

        if(empty($doctorData)) {
            return false;
        }

        $data['send_to'] = $userData->id;
        $data['model_id'] = (int)$appoData->id;
        $data['model'] = "paymentfailed";
        $data['title'] = "Your Payment Failed";
        $data['desc'] = "Your payment for ".$doctorData->name." service failed.";
        $notification = [
            "title" => $data['title'],
            "body" => $data['desc'],
            "badge" => intval(0),
            "sound" => "default"
        ];
        $extData = [
            "category" => "paymentfailed",
            "messageData" => $data,
            "unread" => (string) 0
        ];
        $this->Notification->setData($data);

        $receiverAuthData = $this->Auth_Model->get(['userId' => $userData->id,'status'=>1]);
        if(!empty($receiverAuthData)) {
            foreach($receiverAuthData as $val) {
                $this->pushNotification($val->deviceToken, $notification, $extData, 0);
            }
        }
        return true;
    }

    public function cancelAppointment24Hours($appoData = ""){
        $this->load->model('User_Appointment_Model');

        if(empty($appoData)){
            return false;
        }

        $userData = $this->Users_Model->get(['id'=>$appoData->userId, 'status'=>1],true);

        if(empty($userData)) {
            return false;
        }

        $doctorData = $this->Users_Model->get(['id'=>$appoData->doctorId, 'status'=>1],true);

        if(empty($doctorData)) {
            return false;
        }
        /* user notification */
        $data['send_to'] = $userData->id;
        $data['model_id'] = (int)$appoData->id;
        $data['model'] = "cancelUserAppointment24Hours";
        $data['title'] = "Your Appointment Cancelled";
        $data['desc'] = "Your appointment was cancelled due to insufficient funds in your account.";
        $notification = [
            "title" => $data['title'],
            "body" => $data['desc'],
            "badge" => intval(0),
            "sound" => "default"
        ];
        $extData = [
            "category" => "cancelUserAppointment24Hours",
            "messageData" => $data,
            "unread" => (string) 0
        ];
        $this->Notification->setData($data);

        $receiverAuthData = $this->Auth_Model->get(['userId' => $userData->id,'status'=>1]);
        if(!empty($receiverAuthData)) {
            foreach($receiverAuthData as $val) {
                $this->pushNotification($val->deviceToken, $notification, $extData, 0);
            }
        }

        /* doctor notification */
        $data['send_to'] = $doctorData->id;
        $data['model_id'] = (int)$appoData->id;
        $data['model'] = "cancelDoctorAppontment24Hours";
        $data['title'] = "Cancelled Appointment";
        $data['desc'] = "Your appointment with ".$userData->name." has been auto cancelled";
        $notification = [
            "title" => $data['title'],
            "body" => $data['desc'],
            "badge" => intval(0),
            "sound" => "default"
        ];
        $extData = [
            "category" => "cancelDoctorAppontment24Hours",
            "messageData" => $data,
            "unread" => (string) 0
        ];
        $this->Notification->setData($data);

       $doctorAuthData = $this->Auth_Model->get(['userId' => $doctorData->id,'status'=>1]);
        if(!empty($doctorAuthData)) {
            foreach($doctorAuthData as $val) {
                $this->pushNotification($val->deviceToken, $notification, $extData, 0);
            }
        }
        return true;
    }

    public function cancelAppointmentIfNotStart($appoData = ""){
        $this->load->model('User_Appointment_Model');

        if(empty($appoData)){
            return false;
        }

        $userData = $this->Users_Model->get(['id'=>$appoData->userId, 'status'=>1],true);

        if(empty($userData)) {
            return false;
        }

        $doctorData = $this->Users_Model->get(['id'=>$appoData->doctorId, 'status'=>1],true);

        if(empty($doctorData)) {
            return false;
        }
        /* user notification */
        $data['send_to'] = $userData->id;
        $data['model_id'] = (int)$appoData->id;
        $data['model'] = "cancelUserAppointmentIfNotStart";
        $data['title'] = "Your Appointment Cancelled";
        $data['desc'] = "Your appointment is cancelled for not attending.";
        $notification = [
            "title" => $data['title'],
            "body" => $data['desc'],
            "badge" => intval(0),
            "sound" => "default"
        ];
        $extData = [
            "category" => "cancelUserAppointmentIfNotStart",
            "messageData" => $data,
            "unread" => (string) 0
        ];
        $this->Notification->setData($data);

        $receiverAuthData = $this->Auth_Model->get(['userId' => $userData->id,'status'=>1]);
        if(!empty($receiverAuthData)) {
            foreach($receiverAuthData as $val) {
                $this->pushNotification($val->deviceToken, $notification, $extData, 0);
            }
        }

        /* doctor notification */
        $data['send_to'] = $doctorData->id;
        $data['model_id'] = (int)$appoData->id;
        $data['model'] = "cancelDoctorAppontmentIfNotStart";
        $data['title'] = "Cancelled Appointment";
        $data['desc'] = "Your appointment with ".$userData->name." has been auto cancelled";
        $notification = [
            "title" => $data['title'],
            "body" => $data['desc'],
            "badge" => intval(0),
            "sound" => "default"
        ];
        $extData = [
            "category" => "cancelDoctorAppontmentIfNotStart",
            "messageData" => $data,
            "unread" => (string) 0
        ];
        $this->Notification->setData($data);

       $doctorAuthData = $this->Auth_Model->get(['userId' => $doctorData->id,'status'=>1]);
        if(!empty($doctorAuthData)) {
            foreach($doctorAuthData as $val) {
                $this->pushNotification($val->deviceToken, $notification, $extData, 0);
            }
        }
        return true;
    }

    public function planAvablitySlot($duration){
        $this->load->model('User_Appointment_Model');
        $currentdate = new DateTime();
        
        $startTime = '12:00 AM';
        $endTime = '11:59 PM';

        $startdatetime = new DateTime($currentdate->format('Y-m-d').' '.$startTime, new DateTimeZone(getenv('SYSTEMTIMEZON') ));
        $startdatetime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));

        $enddatetime = new DateTime($currentdate->format('Y-m-d').' '.$endTime, new DateTimeZone(getenv('SYSTEMTIMEZON')));
        $enddatetime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));

        $ReturnArray = array();// Define output
        $startTime = $startdatetime->format('U'); //Get Timestamp
        $endTime = $enddatetime->format('U'); //Get Timestamp
        $AddMins = $duration * 60;
        $bufferTimeBefore = 0;
        $bufferTimeAfter = 0;
        $startBufferTime = $bufferTimeBefore * 60;
        $endBufferTime = $bufferTimeAfter * 60;
        while ($startTime <= $endTime) //Run loop
        {
            $startTime = $startTime + $startBufferTime;
            if(($startTime+$AddMins) <= $endTime){
                $ReturnTempArray = array();
                $ReturnTempArray['startTimesFormat'] = date('h:i a', $startTime);
                $ReturnTempArray['endTimesFormat'] = date('h:i a', $startTime+$AddMins);
                $ReturnTempArray['startTimestamp'] = $startTime;
                $ReturnTempArray['endTimestamp'] = $startTime+$AddMins;
                $ReturnArray[] = $ReturnTempArray;
            }
            $startTime += $AddMins + $endBufferTime; //Endtime check
        }
        return $ReturnArray;
    }

    public function planAvablitySlotWithBooked($userId, $date, $duration){
        $this->load->model('User_Appointment_Model');
        $this->load->model('User_Availability_Model');
        $userData = $this->Users_Model->get(['id'=>$userId, 'status'=>'1'], true);
        if(empty($userData)){
            return false;
        }
        $myUserTimeZone = (!empty($userData->timeZone) ? $userData->timeZone : getenv('SYSTEMTIMEZON'));
        
        $getDate = new DateTime($date);
        
        // $getDate = new DateTime();
        $currentdate = date("Y-m-d");

        $datetime = new DateTime(date('Y-m-d H:i:s'));
        $la_time = new DateTimeZone($myUserTimeZone);
        $datetime->setTimezone($la_time);
        $currentTime = strtotime($datetime->format('Y-m-d h:i A'));
        $checkCurrentDate = date('Y-m-d', strtotime($date));

        $startTime = '12:00 AM';
        $endTime = '11:59 PM';

        $startdatetime = new DateTime($getDate->format('Y-m-d').' '.$startTime, new DateTimeZone(getenv('SYSTEMTIMEZON') ));
        $startdatetime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));

        $enddatetime = new DateTime($getDate->format('Y-m-d').' '.$endTime, new DateTimeZone(getenv('SYSTEMTIMEZON')));
        $enddatetime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));

        $ReturnArray = array();// Define output
        $startTime = $startdatetime->format('U'); //Get Timestamp
        $endTime = $enddatetime->format('U'); //Get Timestamp
        $AddMins = $duration * 60;
        $bufferTimeBefore = 0;
        $bufferTimeAfter = 0;
        $startBufferTime = $bufferTimeBefore * 60;
        $endBufferTime = $bufferTimeAfter * 60;
        while ($startTime <= $endTime) //Run loop
        {
            $startTime = $startTime + $startBufferTime;
            if(($startTime+$AddMins) <= $endTime){
                $ReturnTempArray = array();
                if($currentdate == $date){
                    if($startTime > $currentTime){
                        $checkIsBook = $this->User_Availability_Model->get(['userId'=> $userId,'checkBookedSlot'=>['startDateTime'=>$startTime,'endDateTime'=>$startTime+$AddMins], 'status'=>'1'], true);
                        $ReturnTempArray['isBooked'] = "0";
                        if(!empty($checkIsBook)){
                            $ReturnTempArray['isBooked'] = $checkIsBook->isBooked;
                        }
                        // if($startTime > strtotime(date('h:i a'))){
                        $ReturnTempArray['date'] = $getDate->format('Y-m-d');
                        $ReturnTempArray['startTimesFormat'] = date('h:i A', $startTime);
                        $ReturnTempArray['endTimesFormat'] = date('h:i A', $startTime+$AddMins);
                        $ReturnTempArray['startTimestamp'] = $startTime;
                        $ReturnTempArray['endTimestamp'] = $startTime+$AddMins;
                    }
                } else {
                    $checkIsBook = $this->User_Availability_Model->get(['userId'=> $userId,'checkBookedSlot'=>['startDateTime'=>$startTime,'endDateTime'=>$startTime+$AddMins], 'status'=>'1'], true);
                    $ReturnTempArray['isBooked'] = "0";
                    if(!empty($checkIsBook)){
                        $ReturnTempArray['isBooked'] = $checkIsBook->isBooked;
                    }
                    $ReturnTempArray['date'] = $getDate->format('Y-m-d');
                    $ReturnTempArray['startTimesFormat'] = date('h:i A', $startTime);
                    $ReturnTempArray['endTimesFormat'] = date('h:i A', $startTime+$AddMins);
                    $ReturnTempArray['startTimestamp'] = $startTime;
                    $ReturnTempArray['endTimestamp'] = $startTime+$AddMins;
                }
                if(!empty($ReturnTempArray)){
                    $ReturnArray[] = $ReturnTempArray;
                }
            }
            $startTime += $AddMins + $endBufferTime; //Endtime check
        }
        // print_r($ReturnArray);
        return $ReturnArray;
    }

    public function planAvablitySlotWithBooked_New($userId, $date, $duration,$myUserTimeZone="",$startTime='12:00 AM', $endTime='11:59 PM'){
        $this->load->model('User_Appointment_Model');
        $this->load->model('User_Availability_Model');
        $userData = $this->Users_Model->get(['id'=>$userId, 'status'=>'1'], true);
        if(empty($userData)){
            return false;
        }
        $doctorTimeZone = (!empty($userData->timeZone) ? $userData->timeZone : getenv('SYSTEMTIMEZON'));
        
        $getDate = new DateTime($date);
        
        // $getDate = new DateTime();
        $currentdate = date("Y-m-d");

        $datetime = new DateTime(null);
        $la_time = new DateTimeZone($doctorTimeZone);
        $datetime->setTimezone($la_time);
        $currentTime = $datetime->format('U');
        //$checkCurrentDate = date('Y-m-d', strtotime($date));
        $checkCurrentDate = $date;
        
        //$startTime = '12:00 AM';
        //$endTime = '11:59 PM';

        $startdatetime = new DateTime($checkCurrentDate.' '.$startTime, new DateTimeZone($doctorTimeZone ));
        $startdatetime->setTimezone(new DateTimeZone($myUserTimeZone));

        $enddatetime = new DateTime($checkCurrentDate.' '.$endTime, new DateTimeZone($doctorTimeZone));
        $enddatetime->setTimezone(new DateTimeZone($myUserTimeZone));

        $ReturnArray = array();// Define output
        $startTime = $startdatetime->format('U'); //Get Timestamp
        $endTime = $enddatetime->format('U'); //Get Timestamp
        $AddMins = $duration * 60;
        $bufferTimeBefore = 0;
        $bufferTimeAfter = 0;
        $startBufferTime = $bufferTimeBefore * 60;
        $endBufferTime = $bufferTimeAfter * 60;
                        
        while ($startTime <= $endTime) //Run loop
        {
            $startTime = $startTime + $startBufferTime;
            
            $sysstartdatetime = new DateTime(null,new DateTimeZone($myUserTimeZone));
            $sysstartdatetime->setTimestamp($startTime);
            $sysstartdatetime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));
            
            $sysendTimestamp = new DateTime(null,new DateTimeZone($myUserTimeZone));
            $sysendTimestamp->setTimestamp($startTime+$AddMins);
            $sysendTimestamp->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));
                        
            if(($startTime+$AddMins) <= $endTime){
                $ReturnTempArray = array();
                if($currentdate == $date){
                    if($startTime > $currentTime){
                        $checkIsBook = $this->User_Availability_Model->get(['userId'=> $userId,'checkBookedSlot'=>['startDateTime'=>$startTime,'endDateTime'=>$startTime+$AddMins], 'status'=>'1'], true);
                        $ReturnTempArray['isBooked'] = "0";
                        if(!empty($checkIsBook)){
                            $ReturnTempArray['isBooked'] = $checkIsBook->isBooked;
                        }
                        // if($startTime > strtotime(date('h:i a'))){
                        $ReturnTempArray['date'] = $checkCurrentDate;
                        $ReturnTempArray['startTimestamp'] = $sysstartdatetime->format("U");
                        $ReturnTempArray['endTimestamp'] = $sysendTimestamp->format("U");
                    }
                } else {
                    $checkIsBook = $this->User_Availability_Model->get(['userId'=> $userId,'checkBookedSlot'=>['startDateTime'=>$startTime,'endDateTime'=>$startTime+$AddMins], 'status'=>'1'], true);
                    $ReturnTempArray['isBooked'] = "0";
                    if(!empty($checkIsBook)){
                        $ReturnTempArray['isBooked'] = $checkIsBook->isBooked;
                    }
                    $ReturnTempArray['date'] = $checkCurrentDate;
                    $ReturnTempArray['startTimestamp'] = $sysstartdatetime->format("U");
                    $ReturnTempArray['endTimestamp'] = $sysendTimestamp->format("U");
                }
                if(!empty($ReturnTempArray)){
                    $ReturnArray[] = $ReturnTempArray;
                }
            }
            $startTime += $AddMins + $endBufferTime; //Endtime check
        }
        // print_r($ReturnArray);
        return $ReturnArray;
    }
    
    public function cancelPlanAppoitmentNotification($planData){
        $this->load->model('Plan_Model');

        if(empty($planData)){
            return false;
        }

        $appoData = $this->Plan_Model->get(['id'=>$planData], true);
        if(empty($appoData)){
            return false;
        }
        $senderUserData = $this->Users_Model->get(['id'=> $appoData->userId, 'status'=>'1'], true);
        $reciverDoctorData = $this->Users_Model->get(['id'=> $appoData->doctorId, 'status'=>'1'], true);
        
        $data['send_to'] = $reciverDoctorData->id;
        $data['send_from'] = $senderUserData->id;
        $data['model_id'] = (int)$appoData->id;
        $data['model'] = "planCancelByUser";
        $data['title'] = "Cancelled Plan Appointment";
        $data['desc'] = $senderUserData->name." has cancelled your plan appointment";
        $notification = [
            "title" => $data['title'],
            "body" => $data['desc'],
            "badge" => intval(0),
            "sound" => "default"
        ];
        $extData = [
            "category" => "planCancelByUser",
            "messageData" => $data,
            "unread" => (string) 0
        ];
        $this->Notification->setData($data);

        $receiverAuthData = $this->Auth_Model->get(['userId' => $reciverDoctorData->id,'status'=>1]);
        if(!empty($receiverAuthData)) {
            foreach($receiverAuthData as $val) {
                $this->pushNotification($val->deviceToken, $notification, $extData, 0);
            }
        }
        return true;

    }

    
    /* Dev_2022-06-12 */    
    public function manuaNotificationJob($data) {
        if(empty($data)) {
            return;
        }
        $this->load->model('NotificationJob_Model');
        $result = $this->NotificationJob_Model->get(['id' => $data], true);
        if(empty($result)) {
            return false;
        }

        $notificationSentCount = $result->notificationSent;
        $emailSentCount = $result->emailSent;
        $result->sendTo = explode(',', $result->sendTo);
        $result->type = explode(',', $result->type);
        if(!empty($result->userId)) {
            $result->userId = explode(',', $result->userId);
        }
        
        if(in_array(1, $result->sendTo)) {
            $userRoleIn[] = 2;
        }
        if(in_array(2, $result->sendTo)) {
            $userRoleIn[] = 3;
        }
        
        $userData = [];
        if(!empty($result->userId)) {
            $userData = $this->Users_Model->get(['id'=>$result->userId, 'role'=>$userRoleIn, 'status'=> 1]);
        }
        else {
            $userData = $this->Users_Model->get(['role'=>$userRoleIn, 'status'=> 1]);
        }

        if(!empty($userData)) {
            foreach($userData as $value) {
                if(in_array(2, $result->type)) {
                    $notificationSentCount++;
                    $data = [];
                    $data['send_to'] = $value->id; 
                    $data['send_from'] = 0;
                    $data['model_id'] = 0;
                    $data['model'] = "adminManualNotification";
                    $data['title'] = $result->notificationTitle;
                    $data['desc'] = $result->notificationText;

                    $notification = [
                        "title" => $result->notificationTitle,
                        "body" => $result->notificationText,
                        "badge" => intval(0),
                        "sound" => "default"
                    ];
                    $extData = [
                        "category" => "adminManualNotification",
                        "messageData" => $data,
                        "unread" => (string) 0
                    ];

                    $savedId = $this->Notification->setData($data);
                    $receiverAuthData = $this->Auth_Model->get(['userId' => $value->id,'status'=>1]);
                    if(!empty($receiverAuthData)) {
                        foreach($receiverAuthData as $val) {
                            $this->pushNotification($val->deviceToken, $notification, $extData, 0);
                        }
                    }
                }
                if(in_array(1, $result->type)) {
                    if(!empty($value->email)) {
                        $emailSentCount++;
                        if(!empty($value->email)) {
                            $mailBody = $this->load->view('Mail/NotificationJobMail', ['user' => $value, 'email' => $result], TRUE);
                            $this->Common_Model->mailsend($value->email, $result->mailSubject, $mailBody);
                        }
                    }
                }
            }
        }
        $this->NotificationJob_Model->setData(['status' => 3, 'notificationSent'=>$notificationSentCount, 'emailSent'=>$emailSentCount], $result->id);
        return true;
    }

    


    public function user_appointment_subjective_reminder_24hours_notification() {
        $count = 0;
        $getSystemTimeZone = getenv('SYSTEMTIMEZON');
        $this->load->model('User_Appointment_Model');

        $startDate = strtotime("+1438 minutes", time());
        #$startDate = strtotime("+18 minutes", time());
        $endDate = strtotime("+1443 minutes", time());
        $getAll = $this->User_Appointment_Model->get([
            'status' => 1,
            'getAvailabilityData' => 1,
            'getAvailabilityForCron' => [ 'startDate' => $startDate, 'endDate' => $endDate ]
        ]);
        if(empty($getAll)) {
            return false;
            // return $count . " time Sent...";
        }
        
        foreach ($getAll as $value) {
            $data = [];
            $receiverData = $this->Users_Model->get(['id' => $value->userId, 'status' => 1 ], true);
            if(!empty($receiverData) ) {
                $appointmentStartTime = $value->appointmentStartTime;
                if (!empty($receiverData->timeZone)) {
                    $dateObject = new DateTime(strtotime($value->appointmentDateTime), new DateTimeZone($getSystemTimeZone));
                    $dateObject->setTimezone(new DateTimeZone($receiverData->timeZone));
                    $appointmentStartTime = $dateObject->format('h:i A');
                }
                    
                $data['send_to'] = $value->userId;
                $data['model_id'] = $value->id;
                $data['model'] = "appointmentWithin10MinutesAsUser";
                $data['title'] = "You have an appointment at " . $appointmentStartTime;
                $data['desc'] = "Your appointment is only 24 hours left please update your subjective notification";
                $notification = [
                    "title" => $data['title'],
                    "body" => $data['desc'],
                    "badge" => intval(0),
                    "sound" => "default"
                ];
                $extData = [
                    "category" => "appointmentWithin10MinutesAsUser",
                    "messageData" => $data,
                    "unread" => (string) 0
                ];

                #$this->Notification->setData($data);
                $receiverAuthData = $this->Auth_Model->get([ 'userId' => $value->userId, 'status' => 1 ]);
                if(!empty($receiverAuthData)) {
                    foreach($receiverAuthData as $val) {
                        $count++;
                        $this->pushNotification($val->deviceToken, $notification, $extData, 0);
                    }
                }
            }

        }
        return true;
        // return $count . " time Sent...";
    }





}
