<?php

defined('BASEPATH') OR exit('No direct script access allowed');

ini_set('memory_limit','999999M');
ini_set('upload_max_filesize', '500M');
ini_set('max_input_time', '-1');
ini_set('max_execution_time', '-1');

class Cron extends MY_Controller {
    
    public function __construct() {
        parent::__construct();
        // $this->template->set_template('FrontTemplate');
        $this->load->model('Background_Model');
        // $this->load->model('Cms_Model');
        // $this->load->library('upload');
        // $this->load->model('Common_Model');
        $this->load->model('Users_Model');
    }
    
    public function index() {
        
    }

    /**
     * C001C
     * Chiry – Appointment push notification     
     * Chiry – When Appointment about to 1 Hour push notification will send.
     * Cron Timing - every 30 min
     */
    public function C001C_1hour_appointment_push_notification() {
        $cnt = $this->Background_Model->C001C_1hour_appointment_push_notification();
        echo $cnt;die;
    }

    /**
     * C002C >> stands for Chiry 002 Cron
     * Chiry – Appointment push notification     
     * Chiry – When Appointment about to 10 minutes push notification will send.
     * Cron Timing - every 5 min
     */
    public function C002C_10minutes_appointment_push_notification() {
        $this->Background_Model->C002C_10minutes_appointment_push_notification();
    }

    /**
     * C003C >> 
     * Chiry – Add Referral Coupon in Current Subdcription
     * Chiry – Provider coupon apply in current premium subscription
     * Cron Timing - every day 1 time
     */
    public function C001C_24hours_add_discount_couplon_subscription() {
        $this->load->model('WebAppProviderSubscription_Model');
        $this->load->model('User_Transaction_Model');
        $this->load->model('User_Referral_Earning_Model');
        $this->load->library('stripe');
        $existSubscriptionData = $this->WebAppProviderSubscription_Model->get(['current_plan'=>'2','status'=>'1','getFeature24HoursData'=>true]);
    
        if(!empty($existSubscriptionData)){
            foreach($existSubscriptionData as $value){
                $referraldata = $this->User_Referral_Earning_Model->get(['userId'=>$value->userId,'status'=>1,'orderstate'=>'ASC','orderby'=>'id','limit'=>3]);
                if(!empty($referraldata)){
                    $extraData = array();
                    foreach($referraldata as $rkey => $rvalue){
                        if($rvalue->amount == "100"){
                            if($rkey == 0){
                                $extraData['coupon'] = getenv('PROVIDER_COUPON100_ID');
                            }elseif($rkey == 1){
                                $extraData['coupon'] = getenv('PROVIDER_COUPON200_ID');
                            }elseif($rkey == 2){
                                $extraData['coupon'] = getenv('PROVIDER_COUPON300_ID');
                            }
                        }
                    }
                    
                    $tranData = $this->User_Transaction_Model->get(['id'=>$value->transactionId,'userId'=>$value->userId,'status'=>'1'],true);
                    if(!empty($tranData)){
                        $existSubscription = $this->stripe->retriveSingleSubscription($tranData->stripeSubscriptionId);
                        if(isset($existSubscription->status) && $existSubscription->status == "active"){
                            $updateSub = $this->stripe->updateSubscription($existSubscription->customer, $existSubscription->id, $extraData);
                            if(isset($existSubscription->status) && $existSubscription->status == "active"){
                                foreach($referraldata as $rkey => $rvalue){
                                    $this->User_Referral_Earning_Model->setData(['status'=>2],$rvalue->id);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * C004C >> 
     * Chiry – Create provider availability 
     * Chiry – Provider availability for upcoming 7 days
     * Cron Timing - every day 1 time
     */
    public function C004C_24hours_create_provider_availability() {
        $this->load->model('Users_Model', 'User');
        $existProviderData = $this->User->get(['role'=>'3','status'=>'1','checkAvailibilitySetting'=>true,'apiResponse'=>true]);
        if(!empty($existProviderData)){
            foreach($existProviderData as $value){
                $myUserTimeZone = (!empty($value->timeZone) ? $value->timeZone : getenv('SYSTEMTIMEZON'));
                $this->Background_Model->updateProviderAvailability($value->userId,$myUserTimeZone);
            }
        }

    }

    /**
     * C005C >> 
     * Chiry – Provider licens certificate expiration alert
     * Chiry – Notify provider when license(s)/certificate(s) will expire (3 months prior, 2 months prior, 1 month prior, and 1 week prior)
     * Cron Timing - every day 1 time
     * Cron URL - cron/C005C_24hours_license_certificate_expiration
     */
    public function C005C_24hours_license_certificate_expiration() {
        $this->load->model('User_Professional_Model', 'User_Professional');
        $userData = $this->User_Professional->get(['status'=>'1','checkLicenceExpiry'=>true]);
        if(!empty($userData)){
            foreach($userData as $value){
                $this->Background_Model->C005C_24hours_license_certificate_expiration($value);
            }
        }
    }

    /**
     * C006C >> 
     * Chiry – Provider licens certificate expiration account alert
     * Chiry – Hold provider account when license(s)/certificate(s) will expire
     * Cron Timing - every day 1 time
     * Cron URL - cron/C006C_24hours_license_certificate_expired_account_alert
     */
    public function C006C_24hours_license_certificate_expired_account_alert() {
        $this->load->model('User_Professional_Model', 'User_Professional');
        $userData = $this->User_Professional->get(['status'=>'1','getLicenceExpiredData'=>true]);
        if(!empty($userData)){
            foreach($userData as $value){
                $this->Background_Model->C006C_24hours_license_certificate_expired_account_alert($value);
            }
        }
    }

    /**
     * C007C >>
     * Chiry – Follow-up when user check any provider profile
     * Chiry – When user check provider profile follow-up after 1 hours
     * Cron Timing - every minute
     * Cron URL - cron/C007C_1minute_followup_after_visit_1hour_provider_profile
     */
    public function C007C_1minute_followup_after_visit_1hour_provider_profile() {
        $this->load->model('User_Profile_Visit_Model');
        $existVisitData = $this->User_Profile_Visit_Model->get(['status'=>'1','isFollowup'=>'0','getUnfollowupData'=>true]);
        
        //echo "<pre>"; print_r($existVisitData); die;
        if(!empty($existVisitData)){
            foreach($existVisitData as $value){
                $this->User_Profile_Visit_Model->setData(['isFollowup'=>'1'],$value->id);
                $this->Background_Model->C007C_1minute_followup_after_visit_1hour_provider_profile($value);
            }
        }
    }

    /**
     * C008C >> 
     * Chiry – Next visit not scheduled
     * Chiry – Next visit not scheduled after 1 hour and 24 hour after completed appointment
     * Cron Timing - every minute
     * Cron URL - cron/C008C_1minute_next_visit_not_scheduled_after_completed_appointment
     */
    public function C008C_1minute_next_visit_not_scheduled_after_completed_appointment() {
        $this->load->model('User_Appointment_Model');
        // Previous 1 hour data
        $startDate = strtotime("-59 minutes", time());
        $endDate = strtotime("-60 minutes", time());
        $bookData = $this->User_Appointment_Model->get(['status'=>'3','getCompletedAppointmentBetween'=>['startDate' => $startDate, 'endDate' => $endDate]]);
        if(!empty($bookData)){
            foreach($bookData as $value){
                $newBookingExist = $this->User_Appointment_Model->get(['getNewBookedToCreated'=>$value->endAppointmentDatetime,'status'=>'1','userId'=>$value->userId,'doctorId'=>$value->doctorId]);
                if(empty($newBookingExist)){
                    $this->Background_Model->C008C_1minute_next_visit_not_scheduled_after_completed_appointment($value);
                }
            }
        }

        // Previous 24 hour data
        $startDate = strtotime("-1439 minutes", time());
        $endDate = strtotime("-1440 minutes", time());
        $bookData = $this->User_Appointment_Model->get(['status'=>'3','getCompletedAppointmentBetween'=>['startDate' => $startDate, 'endDate' => $endDate]]);
        if(!empty($bookData)){
            foreach($bookData as $value){
                $newBookingExist = $this->User_Appointment_Model->get(['getNewBookedToCreated'=>$value->endAppointmentDatetime,'status'=>'1','userId'=>$value->userId,'doctorId'=>$value->doctorId]);
                if(empty($newBookingExist)){
                    $this->Background_Model->C008C_1minute_next_visit_not_scheduled_after_completed_appointment($value);
                }
            }
        } 
    }

    public function stripe_log() {
        $this->load->library('stripe',array('type'=>'1'));
        $fileData = file_get_contents('php://input');
        if(!isset($_SERVER['HTTP_STRIPE_SIGNATURE']) || empty($_SERVER['HTTP_STRIPE_SIGNATURE'])){
            $failauth = "\n\n--------------------------------------------------------------------------------------------------------\n";
            $failauth .= "Header Not found :".date("Y-m-d H:i:s")." \n";
            $failauth .= json_encode(json_decode(file_get_contents('php://input'), TRUE));
            $failauth .= "\n--------------------------------------------------------------------------------------------------------\n\n\n\n";
            file_put_contents(FCPATH.'worker/payment/failed_hook'.date('d_m_Y').'.txt',$failauth,FILE_APPEND);
            return false;
        }
        $fileData =  $this->stripe->validateWebhook($fileData,$_SERVER['HTTP_STRIPE_SIGNATURE']);
        if(empty($fileData)){
            $failauth = "\n\n--------------------------------------------------------------------------------------------------------\n";
            $failauth .= "Header authentication failed :".date("Y-m-d H:i:s")." \n";
            $failauth .= json_encode(json_decode(file_get_contents('php://input'), TRUE));
            $failauth .= "\n--------------------------------------------------------------------------------------------------------\n\n\n\n";
            file_put_contents(FCPATH.'worker/payment/failed_hook'.date('d_m_Y').'.txt',$failauth,FILE_APPEND);
            return false;
        }
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        $data = "--------------------------------------------".date('d-m-Y H:i')."--------------------------------------------\n\n";
        $data .= json_encode($apiData);
        $data .= "\n--------------------------------------------------------------------------------------------------------\n\n\n\n";
        file_put_contents(FCPATH.'worker/payment/stripe_log_'.date('d_m_Y').'.txt',$data,FILE_APPEND);
        if(isset($apiData['type'])){
            switch ($apiData['type']) {
                case 'account.updated':
                    $object = [];
                    if(isset($apiData['data']['object'])
                    && !empty($apiData['data']['object'])){
                        $object = $apiData['data']['object'];
                    }
                    if(!empty($object)){
                        $this->load->model('StripeConnect_Model');
                        $getData = $this->StripeConnect_Model->get(['accId'=>$object['id']],TRUE);
                        if(!empty($getData)){
                            if(isset($object['capabilities']['card_payments']) && isset($object['capabilities']['card_payments']) && 
                            $object['capabilities']['card_payments'] == 'active' && $object['capabilities']['transfers'] == 'active'){
                                $this->StripeConnect_Model->setData(['status'=>'1'],$getData->id);
                            }else if(isset($object['capabilities']['card_payments']) && isset($object['capabilities']['card_payments']) && 
                            $object['capabilities']['card_payments'] == 'pending' && $object['capabilities']['transfers'] == 'pending'){
                                $this->StripeConnect_Model->setData(['status'=>'2'],$getData->id);
                            }else{
                                $this->StripeConnect_Model->setData(['status'=>'0'],$getData->id);
                            }

                            if(isset($object['charges_enabled']) && $object['charges_enabled'] == true){
                                $this->StripeConnect_Model->setData(['isPayment'=>'1'],$getData->id);
                            }
                            if(isset($object['payouts_enabled']) && $object['payouts_enabled'] == true){
                                $this->StripeConnect_Model->setData(['isPayout'=>'1','isBankDetail'=>1],$getData->id);
                            }
                        }
                    }
                    break;
                default:
                    echo 'Received unknown event type ' . $apiData['type'];
            }
        }
    }

    public function C009C_appointment_payment_72hourse(){
        $this->Background_Model->C009C_appointment_payment_72hourse_model(); //Date : 2022-05-24
        /*
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
                            $this->Background_Model->appointmentNoPayment($value);
                        }elseif(!isset($response->id) || $response->id==""){ 
                            $this->Background_Model->appointmentNoPayment($value);
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
                        $this->Background_Model->appointmentNoPayment($value);
                    }
                } 
            }
        } 
       */
    }
    
    public function C0010C_cancel_appointment_payment_24hours(){
        $this->load->model('User_Appointment_Model');
        $this->load->model('User_Transaction_Model');
        $getData = $this->User_Appointment_Model->get(['status'=>'1', 'isFreeConsult'=>'0', 'paymentStatus'=>'0', 'getAppointmentPanding24HoursPaymentData' => true, 'getAvailabilityData'=>true]);
        error_log("\n\n -------------------------------------" . date('c'). " \n Request => ".json_encode($getData), 3, FCPATH.'worker/cancel24AppoimentNotPayment-'.date('d-m-Y').'.txt');
        if(!empty($getData)){
            foreach($getData as $value){
                $transcationData = $this->User_Transaction_Model->get(['appointmentId'=> $value->id, 'userId'=>$value->userId, 'userIdTo'=> $value->doctorId, 'status'=>[1, 4]]);
                if(empty($transactionData)){
                    $this->User_Appointment_Model->setData(['status'=>'2'], $value->id);//Cancelled Appointment
                    $this->Background_Model->cancelAppointment24Hours($value);
                }
            }
        }
    }

    /* Dev_2022-06-12 */    
    public function adminManualNotificationJobCron() {
        $this->load->model('NotificationJob_Model');
        $result = $this->NotificationJob_Model->get(['currentNotiJob' => TRUE, 'status' => 1]);
        //echo $this->db->last_query(); die;
        //echo "<pre>"; print_r($result);
        if(empty($result)) {
            return false;
        }

        //error_log(date('Y-m-d H:i:s') . "\n ", 3, FCPATH. 'worker/'.date('d-m-Y').'-NotificationJob.log');
        foreach($result as $row) {
            $this->Background_Model->manuaNotificationJob($row->id);
            error_log('==>'.date('d-m-Y').' \n =>'.$row->id.' \n \n ', 3, FCPATH. 'worker/'.date('d-m-Y').'-adminManualNotificationJobCron.log');
        }
        echo 1;
        //error_log("\n-------------------------------------\n", 3, FCPATH. 'worker/'.date('d-m-Y').'-NotificationJob.log');
    }
    
    /**
     * C0011C >> 
     * Chiry – cancel appointment if not attend
     * Chiry – If not start appointment on time then auto cancel bu system of mid-night 12:00 AM
     * Cron Timing - Every 5 mint
     * Cron URL - cron/C0011C_cancel_appointment_if_not_attend_5mint
     */
    public function C0011C_cancel_appointment_if_not_attend_5mint(){
        $this->load->model('User_Appointment_Model');
        $this->load->model('User_Transaction_Model');
        $getData = $this->User_Appointment_Model->get(['status'=>'1', 'getPastAppointmentNotStartedData' => true, 'getAvailabilityData'=>true]);
        error_log("\n\n -------------------------------------" . date('c'). " \n Request => ".json_encode($getData), 3, FCPATH.'worker/cancelAppoimentIfNotAttend-'.date('d-m-Y').'.txt');
        
        if(!empty($getData)){
            foreach($getData as $value){
                $this->User_Appointment_Model->setData(['status'=>'2'], $value->id);//Cancelled Appointment
                $transactionData = $this->User_Transaction_Model->get(['appointmentId'=> $value->id, 'userId'=>$value->userId, 'userIdTo'=> $value->doctorId,'type'=>'2','payType'=>['1','9'],'tranType'=>'2','status'=>[1, 4]],true);
                if( isset($transactionData->stripeTransactionId) && !empty($transactionData->stripeTransactionId)){
                    $this->load->library('stripe',array('type'=>'1'));
                    $response = $this->stripe->cancelCharge($transactionData->stripeTransactionId); 
                    error_log("\n\n -------------------------------------" . date('c'). " \n Request => ".json_encode($transactionData) . " \n Response => ".json_encode($response,true), 3, FCPATH.'worker/cancelAppoimentIfNotAttend_paymentcancel-'.date('d-m-Y').'.txt');
                }
                $this->Background_Model->cancelAppointmentIfNotStart($value);
            }
        }
    }




    /* 
     * Dev_2023-06-02
     * Cron Timing - Every 10 mint
     */
    public function user_appointment_subjective_reminder_24hours_notification() {
        $this->Background_Model->user_appointment_subjective_reminder_24hours_notification();
        #error_log('==>'.date('d-m-Y').' \n =>'.$row->id.' \n \n ', 3, FCPATH. 'worker/'.date('d-m-Y').'-adminManualNotificationJobCron.log');
        #error_log("\n-------------------------------------\n", 3, FCPATH. 'worker/'.date('d-m-Y').'-NotificationJob.log');
    }




}
