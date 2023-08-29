<?php

defined('BASEPATH') OR exit('No direct script access allowed');
ob_start();

require APPPATH . 'libraries/REST_Controller.php';

class Doctor extends REST_Controller {

    function __construct() {
        parent::__construct();
        $this->load->library('upload');
        $this->load->model('Common_Model','Common');
        $this->load->model('Background_Model');
        $this->load->model('Users_Model', 'User');
        $this->load->model('User_Rating_Model', 'User_Rating');
        $this->load->model('User_Appointment_Model','User_Appointment');
        $this->load->model('User_Transaction_Model','User_Transaction');
        // $this->load->model('User_Rating_Model','User_Rating');
        $this->load->model('Resources_Model','Resources');
        $this->load->model('Medical_History_Personal_Model','Medical_History_Personal');
        $this->load->model('User_Medications_Model','User_Medications');
        $this->load->model('User_Allergies_Model', 'User_Allergies');
        $this->load->model('User_Health_Issues_Model', 'User_Health_Issues');
        $this->load->model('User_Injuries_Model', 'User_Injuries');
        $this->load->model('User_Surgeries_Model', 'User_Surgeries');
        $this->load->model('Medical_History_Social_Model', 'Medical_History_Social');
        $this->load->model('User_Family_Illness_Model', 'User_Family_Illness');
        $this->load->model('User_Availability_Model', 'User_Availability');
        $this->load->model('User_Appointment_Document_Model', 'User_Appointment_Document');
        $this->load->model('User_Appointment_Subjective_Model','User_Appointment_Subjective');
        $this->load->model('User_Appointment_Objective_Model','User_Appointment_Objective');
        $this->load->model('User_Appointment_Assessment_Model','User_Appointment_Assessment');
        $this->load->model('Chat_Model','Chat');
        $this->load->model('User_Wallet_Model');
        $this->load->model('StripeConnect_Model');
        $this->load->model('WebAppProviderSubscription_Model');
        $this->load->model('SiteSetting_Model');
        // $this->load->model('User_Transaction_Model','User_Transaction');
    }

    public function getDoctorDashboard_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role  != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        // Check referral code empty or not
        if(empty($user->referralCode)){
            $setData = array();
            $setData['referralCode'] = $user->id.$this->Common->random_string(4);
            $this->User->setData($setData,$user->id);
        }
        // ./ Check referral code empty or not

        if (isset($apiData['data']['timeZone']) && !empty($apiData['data']['timeZone'])) {
            $this->User->setData(['timeZone'=>$apiData['data']['timeZone']],$user->id);
            $myUserTimeZone = $apiData['data']['timeZone'];
        }else{
            $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
        }

        // Upcoming appoinment data
        $upcomingAppointment = $this->User_Appointment->get(['apiResponse'=>true,'getUserData'=>true,'status'=>'1','doctorId'=>$user->id,'getFutureAvailability'=>true,'getAvailabilityData'=>true,'limit'=>5]);
        if(!empty($upcomingAppointment)){
            foreach($upcomingAppointment as $value){
                $value->appointmentTimeText = $this->Common_Model->checkDateText($value->appointmentDateTime,$myUserTimeZone);
                $value->appointmentStatus = $this->Common_Model->checkAppointmentStatusText($value->appointmentDateTime, $value->appointmentEndDateTime , $value->status, $myUserTimeZone, $value->appointmentType);
            }
        }
        $response['upcomingAppointment'] = $upcomingAppointment;

        // Appoinment history data
        $appointmentHistory = $this->User_Appointment->get(['apiResponse'=>true,'getUserData'=>true,'ckeckReviewReceived'=>true,'status'=>[2,3],'doctorId'=>$user->id,'getAvailabilityData'=>true,'limit'=>5,'orderby'=>'id','orderstate'=>'DESC']);
        if(!empty($appointmentHistory)){
            foreach($appointmentHistory as $value){
                $value->appointmentTimeText = $this->Common_Model->checkDateText($value->appointmentDateTime,$myUserTimeZone);
                $value->appointmentStatus = $this->Common_Model->checkAppointmentStatusText($value->appointmentDateTime, $value->appointmentEndDateTime , $value->status,$myUserTimeZone, $value->appointmentType);
            }
        }
        $response['appointmentHistory'] = $appointmentHistory;

        // Total appoinment count
        #$response['totalAppointments'] = count($this->User_Appointment->get(['status'=>[1,3,2],'doctorId'=>$user->id,'getUserData'=>true,'getAvailabilityData'=>true]));
        
        $date = new DateTime('now');
        $date->modify('last day of this month');
        $tdate = $date->format('Y-m-d');

        $date->modify('first day of this month');
        $fdate = $date->format('Y-m-d');
        // Total appoinment count
        #$response['totalAppointments'] = count($this->User_Appointment->get(['status'=>[1,3,2],'doctorId'=>$user->id,'appointmentDateLimit'=>true, "fdate" => strtotime($fdate), "tdate" => strtotime($tdate) ]));
        $response['totalAppointments'] = count($this->User_Appointment->get([
            'status' => [1,3,2],
            'doctorId' => $user->id,
            'getAvailabilityData' => true,
            "getAvailabilityForCron" => [
                "startDate" => strtotime($fdate),
                "endDate" => strtotime($tdate)
            ]
        ]));
        
        
        // Total patient count
        #$response['totalPatients'] = count($this->User_Appointment->get(['status'=>[1,3,2],'doctorId'=>$user->id,'groupBy'=>'userId','getUserData'=>true,'getAvailabilityData'=>true]));

        // Total patient count
        $response['totalPatients'] = count($this->User_Appointment->get([
            'status' => [1,3,2],
            'doctorId' => $user->id,
            'getAvailabilityData' => true,
            "getAvailabilityForCron" => [
                "startDate" => strtotime($fdate),
                "endDate" => strtotime($tdate)
            ],
            'groupBy'=>'userId'
        ]));
        
        // Total income sum
        $totalIncome = $this->User_Transaction->get(['status'=>1,'userId'=>$user->id,'type'=>1,'payType'=>[2,5],'tranType'=>1,'sumAmount'=>true],true);
        $response['totalIncome'] = (isset($totalIncome->totalAmount) ? $totalIncome->totalAmount : "0.00");
        
        // Total income sum month wise
        $totalMonth = $this->User_Transaction->get(['status'=>1,'userId'=>$user->id,'type'=>1,'payType'=>[2,5],'tranType'=>1,'sumAmount'=>true,'getMonthYearData'=>date('Y-m')],true);
        $response['totalMonthIncome'] = (isset($totalMonth->totalAmount) ? $totalMonth->totalAmount : "0.00");
        
        // Review average
        $doctorRating = $this->User_Rating->get(['send_to'=>$user->id,'status'=>1,'getRatingAverage'=>true],true);
        $response['ratingAverage'] = (isset($doctorRating->ratingAverage) ? round($doctorRating->ratingAverage,1) : "0.0");
        $response['totalReview'] = $this->User_Rating->get(['send_to'=>$user->id,'getUserData'=>true,'status'=>1],false,true);

        $response['resourcesAndBlogs'] = $this->Resources->get(['apiResponse'=>true,'status'=>'1','type'=>'2','orderstate'=>'DESC','orderby'=>'id','limit'=>1]);
        $connectAccount = $this->StripeConnect_Model->get(['userId' => $user->id],TRUE);
        $response['connectAccount']['isStripeConnect'] = (!empty($connectAccount) ? 1 : 0);
        $response['connectAccount']['isBankDetail'] = (isset($connectAccount->isBankDetail) ? $connectAccount->isBankDetail : 0);
        $response['connectAccount']['isPayment'] = (isset($connectAccount->isPayment) ? $connectAccount->isPayment : 0);
        $response['connectAccount']['isPayout'] = (isset($connectAccount->isPayout) ? $connectAccount->isPayout : 0);
        if (!empty($response)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("getDashboardDataSuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common->GetNotification("dashboardDataNotFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getDoctorReviewList_post() {
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        if(isset($apiData['data']['doctorId']) && !empty($apiData['data']['doctorId'])){
            $this->checkGuestUserRequest();
            $user = $this->User->get(['id'=>$apiData['data']['doctorId'], 'status'=>'1'], true);
        } else {
            $user = $this->checkUserRequest();
        }
        
        if(!empty($user)){
            if ($user->role  != '3') {
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
        }
        $page_number = (isset($apiData['data']['page']) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : 1;
        $limit = (isset($apiData['data']['limit']) && $apiData['data']['limit'] != '') ? $apiData['data']['limit'] : 5;
        if (isset($apiData['data']['page']) && $apiData['data']['page'] == 1) {
            $offset = 0;
        } else {
            if (isset($apiData['data']['page']) && $apiData['data']['page'] != '1') {
                $offset = ($page_number * $limit) - $limit;
            } else {
                $offset = 0;
            }
        }

        $data = array();
        $data['send_to'] = $user->id;
        $data['apiResponse'] = true;
        $data['getUserData'] = true;
        $data['search'] = (isset($apiData['data']['search']) ? $apiData['data']['search'] : "");
        $data['status'] = 1;
        $totalData = $this->User_Rating->get($data,false,true);
        $data['limit'] = $limit;
        $data['offset'] = $offset;
        $response = $this->User_Rating->get($data);
        if (!empty($response)) {
            $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
            foreach($response as $value){
                $datetime = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
                $datetime->setTimezone(new DateTimeZone($myUserTimeZone));
                $datetime->setTimestamp($value->createdDate);
                //$value->createdDate = $datetime->format('d-m-Y h:i A');
                #$value->createdDate = $datetime->format('m-d-Y h:i A');
                $value->createdDate = $datetime->format('m-d-Y');
            }
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("getDoctorReviewListSuccess", $apiData['data']['langType']);
            $this->apiResponse['totalPages'] = ceil($totalData / $limit) . "";
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification(($offset > 0 ? 'allcatchedUp' : "doctorReviewListNotFound"), $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getAppointmentList_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role  != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $page_number = (isset($apiData['data']['page']) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : 1;
        $limit = (isset($apiData['data']['limit']) && $apiData['data']['limit'] != '') ? $apiData['data']['limit'] : 5;
        if (isset($apiData['data']['page']) && $apiData['data']['page'] == 1) {
            $offset = 0;
        }
        else {
            if (isset($apiData['data']['page']) && $apiData['data']['page'] != '1') {
                $offset = ($page_number * $limit) - $limit;
            } else {
                $offset = 0;
            }
        }

        $data = array();
        $data['doctorId'] = $user->id;
        $data['apiResponse'] = true;
        $data['getAvailabilityData'] = true; 
        $data['orderAppointmentStartDate'] = true;
        $data['getUserData'] = true;
        $data['ckeckReviewReceived'] = true;
        $data['orderby'] = "id";
        $data['orderstate'] = " DESC";
        if (isset($apiData['data']['sortType']) && !empty($apiData['data']['sortType'])) {
            $data['orderstate'] = $apiData['data']['sortType'];
            $data['orderAppointmentStartDate'] = false;
        }

        if (isset($apiData['data']['cancelStatus']) && !empty($apiData['data']['cancelStatus'])) {
            $data['cancelStatus'] = $apiData['data']['cancelStatus'];
        }

        if (
            isset($apiData['data']['fromDate']) && !empty($apiData['data']['fromDate'])
            && isset($apiData['data']['toDate']) && !empty($apiData['data']['toDate'])
        ) {
            $data['appointmentDateLimit'] = TRUE;
            $data['fdate'] = strtotime($apiData['data']['fromDate']);
            $data['tdate'] = strtotime($apiData['data']['toDate']);
        }
        
        // 0: Inactive 1: Active 2: Cancelled, 3: Completed, 4: Deleted STATUS
        if (isset($apiData['data']['appointmentStatus']) && $apiData['data']['appointmentStatus'] == 1){
            $data['status'] = 1;
            $data['getFutureAvailability'] = true;
        } else if (isset($apiData['data']['appointmentStatus']) && $apiData['data']['appointmentStatus'] == 2) {
            $data['status'] = 3;
        } else if (isset($apiData['data']['appointmentStatus']) && $apiData['data']['appointmentStatus'] == 3) {
            $data['availabilityStatusForCanel'] = true;
            $data['status'] = 2;
        } else {
            $data['status'] = [1,2,3];
        }
        $data['serviceStatus'] = ['1','0'];
        $data['acceptPlanData'] = true;
        #$data['ckeckMedicalHistoryAvailable'] = true;
        $data['healthConditionAvailable'] = true;
        $data['apisearch'] = (isset($apiData['data']['search']) ? $apiData['data']['search'] : "");
        $totalData = $this->User_Appointment->get($data,false,true);
        $data['limit'] = $limit;
        $data['offset'] = $offset;
        $response = $this->User_Appointment->get($data);
        #echo "<pre>"; print_r($response); die;
        if (!empty($response)) {
            $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
            foreach($response as $value){
                $currentDate = date('d-m-Y h:i');
                $value->bookingDateTxt = "";
                
                if($value->paymentStatus == 0){
                    if(strtotime($currentDate) < $value->appointmentDateTime){
                        $value->bookingDateTxt = "Money to be deducted on ".$value->createdDateShow;
                    } 
                }

                $startDateTime = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
                $startDateTime->setTimezone(new DateTimeZone($myUserTimeZone));
                $startDateTime->setTimestamp($value->appointmentDateTime);

                $value->appointmentTimeTextAppleCalender = $startDateTime->format("d-m-Y h:i A");
                $value->appointmentTimeText = $this->Common_Model->checkDateText($value->appointmentDateTime,$myUserTimeZone);
                $value->appointmentStatus = $this->Common_Model->checkAppointmentStatusText($value->appointmentDateTime, $value->appointmentEndDateTime , $value->status,$myUserTimeZone, $value->appointmentType);
                
                if($value->status == 2 && !empty($value->cancelreason) && $value->cancelreason != 0) {
                    $c_text = "Cancelled :";
                    if($value->cancelreason == 1) {
                        $c_text .= " by Client";
                    }
                    else if($value->cancelreason == 2) {
                        $c_text .= " by Provider";
                    }
                    else if($value->cancelreason == 3) {
                        $c_text .= " Funds Unavailable";
                    }
                    $value->appointmentStatus['text'] = $c_text;
                    $value->appointmentStatus['bgColor'] = "#D50000";
                }
                if($value->isFreeConsult == 1) {
                    $value->serviceDuration = 30;
                }
            }
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("getAppointmentsListSuccessDoctor", $apiData['data']['langType']);
            $this->apiResponse['totalPages'] = ceil($totalData / $limit) . "";
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification(($offset > 0 ? 'allcatchedUp' : "appointmentsListNotFoundDoctor"), $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getAppointmentDetail_post() {
        $this->load->model('HumanBodyParts_Model');
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
  
        if ($user->role  != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['userAppointmentId']) || empty($apiData['data']['userAppointmentId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userAppointmentIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $data = array();
        $data['doctorId'] = $user->id;
        $data['id'] = $apiData['data']['userAppointmentId'];
        $data['apiResponse'] = true;
        $data['getAvailabilityData'] = true;
        $data['getUserData'] = true;
        $data['ckeckReviewReceived'] = true;
        $data['status'] = [1,2,3];
        $data['availabilityStatusForCanel'] = true;
        $response = $this->User_Appointment->get($data,true);
        $frontbodyParts = $this->HumanBodyParts_Model->get([
            'userAppointmentId' => $apiData['data']['userAppointmentId'],
            'status' => 1,
            'groupBy' => 1,
            'frontBack' => 1,
            'orderby' => 'frontBack',
        ],true);
        
        $backbodyParts = $this->HumanBodyParts_Model->get([
            'userAppointmentId' => $apiData['data']['userAppointmentId'],
            'status' => 1,
            'groupBy' => 1,
            'frontBack' => 2,
            'orderby' => 'frontBack',
        ],true);
        #print_r($response);die;
        if(empty($response)) {
            return false;
        }

        
        $healthConditionAvailable = "0";
        #$medical_response = $this->Medical_History_Personal->get([ 'userId' => $response->userId, 'status' => 1, 'apiResponse' => true ], true);
        $medical_response = $this->User_Health_Issues->get(['userId'=>$response->userId, 'status'=>1, 'getOtherData'=>true, 'apiResponse'=>true]);
        if (!empty($medical_response)) {
            $healthConditionAvailable = "1";
        }
        
        if (!empty($response)) {
            $response->healthConditionAvailable = $healthConditionAvailable;

            $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
            $response->appointmentTimeText = $this->Common_Model->checkDateText($response->appointmentDateTime,$myUserTimeZone);
            $response->appointmentStatus = $this->Common_Model->checkAppointmentStatusText($response->appointmentDateTime, $response->appointmentEndDateTime , $response->status, $myUserTimeZone, $response->appointmentType);
            $response->frontImage = $response->thumbFrontImage = null;
            $response->backImage = $response->thumbBackImage = null;
            $response->frontImageName = '';
            $response->backImageName = '';
            if (isset($frontbodyParts) && !empty($frontbodyParts)) {
                $response->frontImage = $frontbodyParts->bodyImage;
                $response->thumbFrontImage = $frontbodyParts->thumbBodyImage;
                $response->frontImageName = $frontbodyParts->bodyImageName;
            }

            $response->price = number_format($response->price, 2);
            
            if (isset($backbodyParts) && !empty($backbodyParts)) {
                $response->backImage = $backbodyParts->bodyImage;
                $response->thumbBackImage = $backbodyParts->thumbBodyImage;
                $response->backImageName = $backbodyParts->bodyImageName;
            }
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("getAppointmentDetailSuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common->GetNotification("appointmentDetailNotFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function verifyAppointmentAuthenticationCode_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        if ($user->role  != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['userAppointmentId']) || empty($apiData['data']['userAppointmentId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userAppointmentIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['authenticationCode']) || empty($apiData['data']['authenticationCode'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("authenticationCodeRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        $appointmentData = $this->User_Appointment->get(['id'=>$apiData['data']['userAppointmentId'],'status'=>1,'doctorId'=>$user->id],true);
        if(empty($appointmentData)){
            
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        #echo "<pre>"; print_r($appointmentData); exit;
        
        if ($appointmentData->authenticationCode == $apiData['data']['authenticationCode']) {
            //$currentTraction=$this->User_Transaction->get(['userIdTo'=>$user->id,'appointmentId'=>$apiData['data']['userAppointmentId'],'status'=>4],true);
            $currentTraction=$this->User_Transaction->get(['userIdTo'=>$user->id,'appointmentId'=>$apiData['data']['userAppointmentId'],'status'=>[4,1]],true);
            if(!empty($currentTraction)){
                $this->load->library('stripe',array('type'=>'1'));                             
                $response = $this->stripe->confirmCharge($currentTraction->stripeTransactionId);        
                error_log("\n\n -------------------------------------" . date('c'). " \n Request => ".json_encode($currentTraction) . " \n Response => ".json_encode($response,true), 3, FCPATH.'worker/chargefromhold-'.date('d-m-Y').'.txt');
                $transactionData = array();
                $transactionData['status'] =1 ;
                $this->User_Transaction->setData($transactionData,$currentTraction->id);
                $this->apiResponse['status'] = "1";
                $this->apiResponse['data'] = $appointmentData;
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("authenticationCodeVerifySuccess", $apiData['data']['langType']);
                //$this->User_Appointment->setData(['paymentStatus' => '1'], $apiData['data']['userAppointmentId']);
            }
            else if(isset($appointmentData->isFreeConsult) && $appointmentData->isFreeConsult == 1) {
                $this->apiResponse['status'] = "1";
                $this->apiResponse['data'] = $appointmentData;
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("authenticationCodeVerifySuccess", $apiData['data']['langType']);
            }
            else {
                $this->apiResponse['status'] = "0";
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("appoinmentPaymentPending", $apiData['data']['langType']);
            }
        } else {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("invalidAuthenticationCode", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getUserMedicalHistoryPersonal_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role  != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['userId']) || empty($apiData['data']['userId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $response = $this->Medical_History_Personal->get(['userId'=>$apiData['data']['userId'],'status'=>1,'apiResponse'=>true],true);
        if (!empty($response)) {
            $response->ongoingMedication = $this->User_Medications->get(['userId'=>$apiData['data']['userId'],'status'=>1,'getOtherData'=>true,'type'=>1,'apiResponse'=>true]);
            $response->pastMedication = $this->User_Medications->get(['userId'=>$apiData['data']['userId'],'status'=>1,'getOtherData'=>true,'type'=>2,'apiResponse'=>true]);
            $response->allergies = $this->User_Allergies->get(['userId'=>$apiData['data']['userId'],'status'=>1,'getOtherData'=>true,'apiResponse'=>true]);
            $response->healthIssues = $this->User_Health_Issues->get(['userId'=>$apiData['data']['userId'],'status'=>1,'getOtherData'=>true,'apiResponse'=>true]);
            $response->injuries = $this->User_Injuries->get(['userId'=>$apiData['data']['userId'],'status'=>1,'getOtherData'=>true,'apiResponse'=>true]);
            $response->surgeries = $this->User_Surgeries->get(['userId'=>$apiData['data']['userId'],'status'=>1,'getOtherData'=>true,'apiResponse'=>true]);
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("getMedicalPersonalHistorySuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common->GetNotification("medicalPersonalHistoryNotFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getUserMedicalHistorySocial_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role  != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['userId']) || empty($apiData['data']['userId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $response = $this->Medical_History_Social->get(['userId'=>$apiData['data']['userId'],'status'=>1,'apiResponse'=>true],true);
        if (!empty($response)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("getMedicalSocialHistorySuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common->GetNotification("medicalSocialHistoryNotFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getUserMedicalHistoryFamilyIllness_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role  != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['userId']) || empty($apiData['data']['userId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $response = $this->User_Family_Illness->get(['userId'=>$apiData['data']['userId'],'status'=>1,'getOtherData'=>true,'apiResponse'=>true]);
        if (!empty($response)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("getMedicalFamilyIllnessHistorySuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common->GetNotification("medicalFamilyIllnessHistoryNotFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getMyAvailability_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role  != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
        $data = array();
        $data['userId'] = $user->id;
        $data['apiResponse'] = true;
        $data['status'] = 1;
        $data['groupByDate'] = $myUserTimeZone;
        //$data['getFutureAvailability'] = true;
        $data['orderby'] = 'dateTime';
        $data['orderstate'] = 'ASC';
        $availability = $this->User_Availability->get($data);
        
        if (!empty($availability)) {
            foreach($availability as $value){
                $datetime = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
                $datetime->setTimezone(new DateTimeZone($myUserTimeZone));
                $datetime->setTimestamp($value->dateTime);
                $value->dayAndDate = $this->Common_Model->getDayAndDateName($value->dateTime,$myUserTimeZone);
                $value->dateFormat =$datetime->format('Y-m-d');
                $value->slotsAvailable = $this->User_Availability->get(['apiResponse'=>true,'availabilityDateTimeFormat'=>$myUserTimeZone,'getByDate'=> ['date'=>$datetime->format('d-m-Y'),'timeZone'=>$myUserTimeZone],'userId'=>$value->userId,'status'=>1,'orderby'=>'dateTime','orderstate'=>'ASC']);
                $value->totalSlotsAvailable = count($value->slotsAvailable);
            }
            
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("getAvailabilitySuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $availability;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common->GetNotification("availabilityNotFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getMyAvailabilityNew_post() {
        $this->load->model('User_Services_Model');

        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role  != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        /*
        if (!isset($apiData['data']['serviceId']) || empty($apiData['data']['serviceId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("serviceIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        
        $serviceDuration = 30;
        $getServiceData = $this->User_Services_Model->get(['id'=>$apiData['data']['serviceId'], 'userId'=>$user->id, 'status'=> 1], true);
        if(!empty($getServiceData)){
            $serviceDuration = $getServiceData->duration;
        }
        */

        $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
        $availability = $this->Background_Model->updateProviderAvailabilityNew($user->id,$myUserTimeZone,$myUserTimeZone);
        #$availability = $this->Background_Model->updateProviderAvailabilityNew($user->id,$myUserTimeZone,$myUserTimeZone, $serviceDuration);
        if (!empty($availability)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("getAvailabilitySuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $availability;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common->GetNotification("availabilityNotFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }
    
    public function getMyAvailabilityNewSlot_post() {
        $this->load->model('User_Services_Model');

        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role  != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if (!isset($apiData['data']['serviceId']) || empty($apiData['data']['serviceId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("serviceIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        
        $serviceDuration = 30;
        $getServiceData = $this->User_Services_Model->get(['id'=>$apiData['data']['serviceId'], 'userId'=>$user->id, 'status'=> 1], true);
        if(!empty($getServiceData)){
            $serviceDuration = $getServiceData->duration;
        }

        $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
        $availability = $this->Background_Model->updateProviderAvailabilityNew($user->id,$myUserTimeZone,$myUserTimeZone);
        #$availability = $this->Background_Model->updateProviderAvailabilityNewSlot($user->id,$myUserTimeZone,$myUserTimeZone, $serviceDuration);
        if (!empty($availability)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("getAvailabilitySuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $availability;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common->GetNotification("availabilityNotFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function rescheduleUserAppointment_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role  != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['userAvailabilityId']) || empty($apiData['data']['userAvailabilityId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("availabilityIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['userAppointmentId']) || empty($apiData['data']['userAppointmentId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userAppointmentIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $availabilityData = $this->User_Availability->get(['userId'=>$user->id,'getFutureAvailability'=>true,'isBooked'=>0,'id'=>$apiData['data']['userAvailabilityId'],'status'=>1],true);
        if(empty($availabilityData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("availabilityDataNotAvailable", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $appointmentData = $this->User_Appointment->get(['doctorId'=>$user->id,'id'=>$apiData['data']['userAppointmentId'],'apiResponse'=>true,'status'=>1],true);
        if(empty($appointmentData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        $authenticationCode = $this->Common->random_string(4);
        $appointmentBookId = $this->User_Appointment->setData(['userAvailabilityId'=>$apiData['data']['userAvailabilityId'], 'authenticationCode' => $authenticationCode,],$appointmentData->userAppointmentId);
        if (!empty($appointmentBookId)) {
            $this->User_Availability->setData(['isBooked'=>0],$appointmentData->userAvailabilityId);
            $this->User_Availability->setData(['isBooked'=>1],$apiData['data']['userAvailabilityId']);

            // Send notification doctor to user for reschedule appointment
            // Set notification 
            $notiData = [];
            $notiData['send_from'] = $user->id;
            $notiData['send_to'] = $appointmentData->userId;
            $notiData['model_id'] = (int)$appointmentBookId;
            $this->Common_Model->backroundCall('rescheduleUserAppointmentByDoctor', $notiData);
            // ./ Set notification

            // Send notification doctor notify for reschedule appointment
            // Set notification 
            $notiData = [];
            $notiData['send_from'] = $user->id;
            $notiData['send_to'] = $user->id;
            $notiData['model_id'] = (int)$appointmentBookId;
            $notiData['userId'] = $appointmentData->userId;
            $this->Common_Model->backroundCall('rescheduleUserAppointmentAsDoctor', $notiData);
            // ./ Set notification

            // Send Mail and SMS in Authentication code
            $notiData = [];
            $notiData['userId'] = $user->id;
            $notiData['authenticationCode'] = $authenticationCode;
            $notiData['isReschedule'] = true;
            $this->Common_Model->backroundCall('sendMailAndSMSInAuthenticationCodeForUser', $notiData);
            // ./ Send Mail and SMS in Authentication code

            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("appointmentRescheduleSuccess", $apiData['data']['langType']);
        } else {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common->GetNotification("failToRescheduleAppointment", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function rescheduleUserAppointmentNew_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role  != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        /*if (!isset($apiData['data']['userAvailabilityId']) || empty($apiData['data']['userAvailabilityId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("availabilityIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }*/

        if (!isset($apiData['data']['userAppointmentId']) || empty($apiData['data']['userAppointmentId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userAppointmentIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        /*$availabilityData = $this->User_Availability->get(['userId'=>$user->id,'getFutureAvailability'=>true,'isBooked'=>0,'id'=>$apiData['data']['userAvailabilityId'],'status'=>1],true);
        if(empty($availabilityData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("availabilityDataNotAvailable", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }*/

        if (!isset($apiData['data']['startDateTime']) || empty($apiData['data']['startDateTime'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("startDateTimeRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
  
        if (!isset($apiData['data']['endDateTime']) || empty($apiData['data']['endDateTime'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("endDateTimeRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
  
        if (!isset($apiData['data']['timeRange']) || empty($apiData['data']['timeRange'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("timeRangeRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
        $startdatetime = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
        $startdatetime->setTimezone(new DateTimeZone($myUserTimeZone));
        $startdatetime->setTimestamp($apiData['data']['startDateTime']);

        $enddatetime = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
        $enddatetime->setTimezone(new DateTimeZone($myUserTimeZone));
        $enddatetime->setTimestamp($apiData['data']['endDateTime']);

        $doctorAvailabilityId = $this->User_Availability->setData(['userId'=>$user->id,'dateTime'=>$startdatetime->format('U'),'endDateTime'=>$enddatetime->format('U'),'timing'=>$apiData['data']['timeRange'],'status'=>1]);
        $availabilityData = $this->User_Availability->get(['userId'=>$user->id,'isBooked'=>0,'id'=>$doctorAvailabilityId,'status'=>1],true);
        if(empty($availabilityData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("availabilityDataNotAvailable", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $appointmentData = $this->User_Appointment->get(['doctorId'=>$user->id,'id'=>$apiData['data']['userAppointmentId'],'apiResponse'=>true,'status'=>1],true);
        if(empty($appointmentData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        $authenticationCode = $this->Common->random_string(4);
        $appointmentBookId = $this->User_Appointment->setData(['userAvailabilityId'=>$availabilityData->id, 'authenticationCode' => $authenticationCode,],$appointmentData->userAppointmentId);
        if (!empty($appointmentBookId)) {
            $this->User_Availability->setData(['isBooked'=>0,'status'=>2],$appointmentData->userAvailabilityId);
            $this->User_Availability->setData(['isBooked'=>1],$availabilityData->id);

            // Send notification doctor to user for reschedule appointment
            // Set notification 
            $notiData = [];
            $notiData['send_from'] = $user->id;
            $notiData['send_to'] = $appointmentData->userId;
            $notiData['model_id'] = (int)$appointmentBookId;
            $this->Common_Model->backroundCall('rescheduleUserAppointmentByDoctor', $notiData);
            // ./ Set notification

            // Send notification doctor notify for reschedule appointment
            // Set notification 
            $notiData = [];
            $notiData['send_from'] = $user->id;
            $notiData['send_to'] = $user->id;
            $notiData['model_id'] = (int)$appointmentBookId;
            $notiData['userId'] = $appointmentData->userId;
            $this->Common_Model->backroundCall('rescheduleUserAppointmentAsDoctor', $notiData);
            // ./ Set notification

            // Send Mail and SMS in Authentication code
            $notiData = [];
            $notiData['userId'] = $user->id;
            $notiData['authenticationCode'] = $authenticationCode;
            $notiData['isReschedule'] = true;
            $this->Common_Model->backroundCall('sendMailAndSMSInAuthenticationCodeForUser', $notiData);
            // ./ Send Mail and SMS in Authentication code

            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("appointmentRescheduleSuccess", $apiData['data']['langType']);
        } else {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common->GetNotification("failToRescheduleAppointment", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function uploadUserAppointmentDocument_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role  != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['userAppointmentId']) || empty($apiData['data']['userAppointmentId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userAppointmentIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['documentType']) || empty($apiData['data']['documentType'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("documentTypeRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['documentFileRealName']) || empty($apiData['data']['documentFileRealName'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("documentFileRealNameRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['documentFileName']) || empty($apiData['data']['documentFileName'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("documentFileNameRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }


        $appointmentData = $this->User_Appointment->get(['id'=>$apiData['data']['userAppointmentId'],'status'=>1,'doctorId'=>$user->id],true);
        if(empty($appointmentData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $data = [];
        $data['userId'] = $appointmentData->userId;
        $data['userAppointmentId'] = $apiData['data']['userAppointmentId'];
        $data['doctorId'] = $user->id;
        $data['documentType'] = $apiData['data']['documentType'];
        $data['documentFileRealName'] = $apiData['data']['documentFileRealName'];
        $data['documentFileName'] = $apiData['data']['documentFileName'];
        $data['uploadedBy'] = 1;
        $set = $this->User_Appointment_Document->setData($data);
        
        if (!empty($set)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("documentSavedSuccess", $apiData['data']['langType']);
        } else {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("failToSaveDocument", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }
    
    public function getUserAppointmentSubjective_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
  
        if ($user->role != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['userAppointmentId']) || empty($apiData['data']['userAppointmentId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userAppointmentIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $data = array();
        $data['userAppointmentId'] = $apiData['data']['userAppointmentId'];
        $data['apiResponse'] = true;
        $data['getGoalData'] = true;
        $data['status'] = 1;
        $response = $this->User_Appointment_Subjective->get($data,true);
        $this->load->model('HumanBodyParts_Model');
        $frontbodyParts = $this->HumanBodyParts_Model->get([
            'userAppointmentId' => $apiData['data']['userAppointmentId'],
            'status' => 1,
            'groupBy' => 1,
            'frontBack' => 1,
            'orderby' => 'frontBack',
        ],true);
        
        $backbodyParts = $this->HumanBodyParts_Model->get([
            'userAppointmentId' => $apiData['data']['userAppointmentId'],
            'status' => 1,
            'groupBy' => 1,
            'frontBack' => 2,
            'orderby' => 'frontBack',
        ],true);
        if (!empty($response)) {
            $response->backImage =$response->thumbBackImage=$response->frontImage = $response->thumbFrontImage="";           
            if (isset($frontbodyParts) && !empty($frontbodyParts)) {
                $response->frontImage = $frontbodyParts->bodyImage;
                $response->thumbFrontImage = $frontbodyParts->thumbBodyImage;
                $response->frontImageName = $frontbodyParts->bodyImageName;
            }
            if (isset($backbodyParts) && !empty($backbodyParts)) {
                $response->backImage = $backbodyParts->bodyImage;
                $response->thumbBackImage = $backbodyParts->thumbBodyImage;
                $response->backImageName = $backbodyParts->bodyImageName;
            }
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("getAppointmentSubjectiveSuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common->GetNotification("appointmentSubjectiveNotFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function saveUserAppointmentObjective_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['posture']) || empty($apiData['data']['posture'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("postureRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['palpation']) || empty($apiData['data']['palpation'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("palpationRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['rangeOfMotion']) || empty($apiData['data']['rangeOfMotion'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("rangeOfMotionRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['orthopedicTests']) || empty($apiData['data']['orthopedicTests'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("orthopedicTestsRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['neurologicTests']) || empty($apiData['data']['neurologicTests'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("neurologicTestsRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['userAppointmentId']) || empty($apiData['data']['userAppointmentId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userAppointmentIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $appointmentData = $this->User_Appointment->get(['id'=>$apiData['data']['userAppointmentId'],'status'=>[1,3],'doctorId'=>$user->id],true);
        if(empty($appointmentData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $data = [];
        $data['userId'] = $appointmentData->userId;
        $data['doctorId'] = $user->id;
        $data['userAppointmentId'] = $apiData['data']['userAppointmentId'];
        $data['posture'] = $apiData['data']['posture'];
        $data['palpation'] = $apiData['data']['palpation'];
        $data['rangeOfMotion'] = $apiData['data']['rangeOfMotion'];
        $data['orthopedicTests'] = $apiData['data']['orthopedicTests'];
        $data['neurologicTests'] = $apiData['data']['neurologicTests'];
        
        $objectiveExistData = $this->User_Appointment_Objective->get(['doctorId'=>$user->id,'userId'=>$appointmentData->userId,'userAppointmentId'=>$apiData['data']['userAppointmentId']],true);
        if(!empty($objectiveExistData)){
            $data['status'] = 1;
            $set = $this->User_Appointment_Objective->setData($data,$objectiveExistData->id);
        }else{
            $set = $this->User_Appointment_Objective->setData($data);
        }

        if (!empty($set)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentObjectiveSavedSuccess", $apiData['data']['langType']);
        } else {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("failToSaveAppointmentObjective", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getUserAppointmentObjective_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
  
        if ($user->role != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['userAppointmentId']) || empty($apiData['data']['userAppointmentId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userAppointmentIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $appointmentData = $this->User_Appointment->get(['id'=>$apiData['data']['userAppointmentId'],'status'=>[1,3],'doctorId'=>$user->id],true);
        if(empty($appointmentData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $data = array();
        $data['doctorId'] = $user->id;
        $data['userId'] = $appointmentData->userId;
        $data['userAppointmentId'] = $apiData['data']['userAppointmentId'];
        $data['apiResponse'] = true;
        $data['status'] = 1;
        $response = $this->User_Appointment_Objective->get($data,true);
        
        if (!empty($response)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("getAppointmentObjectiveSuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common->GetNotification("appointmentObjectiveNotFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function saveUserAppointmentAssessment_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['responseToTreatment']) || empty($apiData['data']['responseToTreatment'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("responseToTreatmentRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        /*
        if (!isset($apiData['data']['complicationsId']) || empty($apiData['data']['complicationsId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("complicationsIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['diagnosis']) || empty($apiData['data']['diagnosis'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("diagnosisRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['prognosis']) || empty($apiData['data']['prognosis'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("prognosisRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        */
        if (!isset($apiData['data']['userAppointmentId']) || empty($apiData['data']['userAppointmentId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userAppointmentIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $appointmentData = $this->User_Appointment->get(['id'=>$apiData['data']['userAppointmentId'],'status'=>[1,3],'doctorId'=>$user->id],true);
        if(empty($appointmentData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $data = [];
        $data['userId'] = $appointmentData->userId;
        $data['doctorId'] = $user->id;
        $data['userAppointmentId'] = $apiData['data']['userAppointmentId'];
        $data['responseToTreatment'] = $apiData['data']['responseToTreatment'];
        $data['complicationsId'] = $apiData['data']['complicationsId'];
        $data['diagnosis'] = $apiData['data']['diagnosis'];
        $data['prognosis'] = $apiData['data']['prognosis'];
        
        $assessmentExistData = $this->User_Appointment_Assessment->get(['doctorId'=>$user->id,'userId'=>$appointmentData->userId,'userAppointmentId'=>$apiData['data']['userAppointmentId']],true);
        if(!empty($assessmentExistData)){
            $data['status'] = 1;
            $set = $this->User_Appointment_Assessment->setData($data,$assessmentExistData->id);
        }else{
            $set = $this->User_Appointment_Assessment->setData($data);
        }

        if (!empty($set)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentAssessmentSavedSuccess", $apiData['data']['langType']);
        } else {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("failToSaveAppointmentAssessment", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }
    
    public function getUserAppointmentAssessment_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
  
        if ($user->role != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['userAppointmentId']) || empty($apiData['data']['userAppointmentId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userAppointmentIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $appointmentData = $this->User_Appointment->get(['id'=>$apiData['data']['userAppointmentId'],'status'=>[1,3],'doctorId'=>$user->id],true);
        if(empty($appointmentData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $data = array();
        $data['doctorId'] = $user->id;
        $data['userId'] = $appointmentData->userId;
        $data['userAppointmentId'] = $apiData['data']['userAppointmentId'];
        $data['apiResponse'] = true;
        //$data['getComplicationsData'] = true;
        $data['status'] = 1;
        $response = $this->User_Appointment_Assessment->get($data,true);
        
        if (!empty($response)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("getAppointmentAssessmentSuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common->GetNotification("appointmentAssessmentNotFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }
    
    public function cancelUserAppointment_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['userAppointmentId']) || empty($apiData['data']['userAppointmentId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userAppointmentIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['cancelStatus']) || empty($apiData['data']['cancelStatus'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("cancelStatusRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $appointmentData = $this->User_Appointment->get(['id'=>$apiData['data']['userAppointmentId'],'status'=>[1,3],'doctorId'=>$user->id],true);
        if(empty($appointmentData)) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $doctorData = $this->User->get(['id'=>$appointmentData->doctorId,'status'=>1,'role'=>3],true);
        if(empty($doctorData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("doctorDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if(
            !empty($appointmentData->userGToken) &&
            !empty($appointmentData->userGEventId) &&
            !empty($appointmentData->doctorGToken) &&
            !empty($appointmentData->doctorGEventId)
        ) {
            require_once('application/controllers/google-calendar-api.php');
            $site_url = current_url();
            $client_id = getenv('GOOGLE_KEY');
            $client_secret = getenv('GOOGLE_SECRET');
            $rurl = base_url()."google/calendar";

            $capi = new GoogleCalendarApi();
            $new_doctor_accessToken = $capi->RefreshAccessToken($client_id, $rurl, $client_secret, $appointmentData->doctorGToken);
            $new_user_accessToken = $capi->RefreshAccessToken($client_id, $rurl, $client_secret, $appointmentData->userGToken);
            
            $d_event = $capi->DeleteCalendarEvent($appointmentData->doctorGEventId, $new_doctor_accessToken);
            $u_event = $capi->DeleteCalendarEvent($appointmentData->userGEventId, $new_user_accessToken);
        }

        $tranData = $this->User_Transaction->get([
            'userId'=>$appointmentData->userId,
            'userIdTo'=>$appointmentData->doctorId,
            'payType'=>1,
            'status'=>[1,4],
            'appointmentId'=>$apiData['data']['userAppointmentId'],
        ],true);


        $key = $this->SiteSetting_Model->get(['key'=>"commissionamount"], true);
        $commissionamount = 0;
        if(isset($key->value) && !empty($key->value)) {
            $commissionamount = round($key->value,2);
        }

        $cancelStatus = $apiData['data']['cancelStatus'];        
        if(!empty($tranData)) {
            if(in_array($cancelStatus, ['1','3']) && !empty($tranData->amount)) {
                $refamount = $tranData->amount / 2;
                $refamount = $refamount * 100;
                // ---------------- Cancel order from Stripe ------------------ //
                $this->load->library('stripe',array('type'=>'1'));
                $stripeResponseData = $this->stripe->addRefundAmount([ "charge" => $tranData->stripeTransactionId, "amount" => $refamount]);
                if (isset($stripeResponseData['error'])) {
                    $this->apiResponse['status'] = "0";
                    $this->apiResponse['message'] = $stripeResponseData['error']['message'];
                    return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                }
                // ---------------- end Cancel order from Stripe --------------- //
                error_log("\n\n -------------------------------------" . date('c'). " \n Response => ".json_encode($stripeResponseData, true), 3, FCPATH.'worker/bookAppoinmentPayment-'.date('d-m-Y').'.txt');
                
                $userCard = $this->User_Card->get(['id' => $appointmentData->userCardId, 'userId' => $appointmentData->userId ], true);
                // For user transaction record
                $transactionData = array();
                $transactionData['userId'] = $appointmentData->userId;
                $transactionData['userIdTo'] = $appointmentData->doctorId;
                $transactionData['cardId'] = $userCard->id;
                $transactionData['appointmentId'] = $appointmentData->id;
                $transactionData['availabilityId'] = $appointmentData->userAvailabilityId;
                $transactionData['stripeTransactionId'] = $stripeResponseData['id'];
                $transactionData['stripeTranJson'] = json_encode($stripeResponseData);
                $transactionData['amount'] = $refamount;
                $transactionData['type'] = 2; // Debit amount
                $transactionData['payType'] = 5;
                $transactionData['tranType'] = 2; //Stripe Transaction
                $transactionData['status'] = 1; //4 
                $transactionData['createdDate'] = $stripeResponseData['created'];
                $this->User_Transaction->setData($transactionData);                        
                $this->User_Appointment->setData(['paymentStatus' => 1 ], $appointmentData->id);

                #$amount = $refamount;
                $amount = $refamount * $commissionamount /100;
                $amount = $refamount - $amount;
                if(isset($appointmentData->discountPrice) && !empty($appointmentData->discountPrice)) {
                    $amount = $amount - $appointmentData->discountPrice;
                }

                //For doctor wallet transaction record
                $transactionData = [];
                $transactionData['userId'] = $appointmentData->doctorId;
                $transactionData['appointmentId'] = $appointmentData->id;
                $transactionData['availabilityId'] = $appointmentData->userAvailabilityId;
                $transactionData['amount'] = $amount;
                $transactionData['type'] = 1; //Credit amount
                $transactionData['payType'] = 2; //Add money in wallet by user book appoinment
                $transactionData['tranType'] = 1; //Wallet Transaction
                $tranId = $this->User_Transaction->setData($transactionData);
                $this->User->setData([ 'walletAmount'=> $doctorData->walletAmount + $amount ], $doctorData->id);

                $this->User_Wallet_Model->setData([
                    'userId'=>$doctorData->id,
                    'transactionId'=>$tranId,
                    'amount'=>$amount,
                    'availableAmount'=>$amount,
                ]);

            }
            else {
                // ---------------- Cancel order from Stripe ------------------ //
                $this->load->library('stripe',array('type'=>'1'));         
                $stripeResponseData = $this->stripe->addRefund($tranData->stripeTransactionId);        
                if (isset($stripeResponseData['error'])) {
                    $this->apiResponse['status'] = "0";
                    $this->apiResponse['message'] = $stripeResponseData['error']['message'];
                    return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                }
                // ---------------- end Cancel order from Stripe --------------- //
            }
        }
        else {
            if(in_array($cancelStatus, ['1','3'])) {
                //Cancel appointment half payment
                $this->load->library('stripe',array('type'=>'1'));
                $this->load->model('User_Card_Model', 'User_Card');
                if(isset($appointmentData->userCardId) && !empty($appointmentData->userCardId)) {
                    $userCard = $this->User_Card->get(['id' => $appointmentData->userCardId, 'userId' => $appointmentData->userId ], true);
                    if(isset($appointmentData->price) && $appointmentData->price == 0) {
                    }
                    else if(isset($userCard) && !empty($userCard->cardId)) {
                        $refamount = $appointmentData->price / 2;
                        $in_amount = $refamount;
                        $refamount = $refamount * 100;
                        $stripeChargeData = [];
                        $stripeChargeData['customer'] = $userCard->customerId;
                        $stripeChargeData['source'] = $userCard->cardId;
                        $stripeChargeData['amount'] = $refamount;
                        $stripeChargeData['capture'] = true;        
                        $stripeChargeData['description'] ="Cancel Appointment Charge, userId: #".$appointmentData->userId.", doctorId: #".$appointmentData->doctorId.", userCardId: #".$userCard->id." , doctorAvailabilityId: #".$appointmentData->userAvailabilityId.", appointmentId: ".$appointmentData->id;
                        $response = $this->stripe->addCharge($stripeChargeData);
                        error_log("\n\n -------------------------------------" . date('c'). " \n Request => ".json_encode($stripeChargeData) . " \n Response => ".json_encode($response,true), 3, FCPATH.'worker/bookAppoinmentPayment-'.date('d-m-Y').'.txt');
                        if(!isset($response['error']) && !empty($response)) {
                            // For user transaction record
                            $transactionData = array();
                            $transactionData['userId'] = $appointmentData->userId;
                            $transactionData['userIdTo'] = $appointmentData->doctorId;
                            $transactionData['cardId'] = $userCard->id;
                            $transactionData['appointmentId'] = $appointmentData->id;
                            $transactionData['availabilityId'] = $appointmentData->userAvailabilityId;
                            $transactionData['stripeTransactionId'] = $response['id'];
                            $transactionData['stripeTranJson'] = json_encode($response);
                            $transactionData['amount'] = $in_amount;
                            $transactionData['type'] = 2; // Debit amount
                            $transactionData['payType'] = 5;
                            $transactionData['tranType'] = 2; //Stripe Transaction
                            $transactionData['status'] = 1; //4 ; 
                            $transactionData['createdDate'] = $response['created'];
                            $this->User_Transaction->setData($transactionData);                        
                            $this->User_Appointment->setData(['paymentStatus' => 1 ], $appointmentData->id);

                            #$amount = $in_amount;
                            $amount = $in_amount * $commissionamount /100;
                            $amount = $in_amount - $amount;
                            if(isset($appointmentData->discountPrice) && !empty($appointmentData->discountPrice)) {
                                $amount = $amount - $appointmentData->discountPrice;
                            }

                            //For doctor wallet transaction record
                            $transactionData = [];
                            $transactionData['userId'] = $appointmentData->doctorId;
                            $transactionData['appointmentId'] = $appointmentData->id;
                            $transactionData['availabilityId'] = $appointmentData->userAvailabilityId;
                            $transactionData['amount'] = $amount;
                            $transactionData['type'] = 1; // Credit amount
                            $transactionData['payType'] = 2; // Add money in wallet by user book appoinment
                            $transactionData['tranType'] = 1; //Wallet Transaction
                            $tranId = $this->User_Transaction->setData($transactionData);
                            $this->User->setData([ 'walletAmount'=> $doctorData->walletAmount + $amount ], $doctorData->id);

                            $this->User_Wallet_Model->setData([
                                'userId'=>$doctorData->id,
                                'transactionId'=>$tranId,
                                'amount'=>$amount,
                                'availableAmount'=>$amount,
                            ]);

                        }
                        else {
                            $msg = "Your Payment Failed. ";
                            /* if(isset($response['error'])) {
                                $msg .= $response['error'];
                            } */
                            $this->apiResponse['status'] = "0";
                            $this->apiResponse['response'] = $response;
                            $this->apiResponse['message'] = $msg;
                            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                        }
                    }
                    else {
                        $this->apiResponse['status'] = "0";
                        $this->apiResponse['message'] = $this->Common_Model->GetNotification("cardNotFound", $apiData['data']['langType']);
                        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                    }
                }
                else {
                    $this->apiResponse['status'] = "0";
                    $this->apiResponse['message'] = $this->Common_Model->GetNotification("cardNotFound", $apiData['data']['langType']);
                    return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                }
                $userId = $appointmentData->userCardId;
            }
        }

        /*
        #1-Cancelled - Fee Charged, 2-Cancel - No Fee Charged, 3-No Show - Fee Charged, 4-No Show - No Fee Charged, 5-Rescheduled
        if(!empty($tranData) && in_array($cancelStatus, ['1,','3'])) {
            // ---------------- Cancel order from Stripe ------------------ //
            $this->load->library('stripe',array('type'=>'1'));         
            $stripeResponseData = $this->stripe->addRefund($tranData->stripeTransactionId);        
            if (isset($stripeResponseData['error'])) {
                $this->apiResponse['status'] = "0";
                $this->apiResponse['message'] = $stripeResponseData['error']['message'];
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            } 
            // ---------------- End Cancel order from Stripe --------------- //
        }
        echo "<pre>"; print_r($tranData);
        echo "..<pre>"; print_r($appointmentData);
        exit;
        */
       
        $this->User_Appointment->setData([
            'status' => 2,
            'cancelreason' => 2,
            'cancelStatus' => $cancelStatus #1-Cancelled - Fee Charged, 2-Cancel - No Fee Charged, 3-No Show - Fee Charged, 4-No Show - No Fee Charged, 5-Rescheduled
        ], $appointmentData->id);
        $this->User_Availability->setData(['isBooked'=>0,'status'=>2], $appointmentData->userAvailabilityId);

        // Send notification doctor to user for Cancel appointment
        // Set notification 
        $notiData = [];
        $notiData['send_from']  = $appointmentData->doctorId; // doctor id
        $notiData['send_to']    = $appointmentData->userId; // user id
        $notiData['model_id']   = (int)$appointmentData->id;
        $this->Common_Model->backroundCall('cancelledUserAppointmentByDoctor', $notiData);        
        // Send notification doctor notify for Cancel appointment
        // Set notification 
        $notiData = [];
        $notiData['send_from'] = $user->id;
        $notiData['send_to'] = $user->id;
        $notiData['model_id'] = (int)$appointmentData->id;
        $notiData['userId'] = $appointmentData->userId;
        $this->Common_Model->backroundCall('cancelledUserAppointmentAsDoctor', $notiData);
        // ./ Set notification

        $this->apiResponse['status'] = "1";
        $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentCancelledSuccess", $apiData['data']['langType']);
    
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }
    
    public function rejectFreeConsultRequest_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
 
        if ($user->role != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['messageId']) || empty($apiData['data']['messageId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("messageIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $existMsgData = $this->Chat->get(['id'=>$apiData['data']['messageId'],'type'=>5,'freeConsultStatus'=>1,'status'=>1],true);
        if(empty($existMsgData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("messageDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
      
        $lastId = $this->Chat->setData(['freeConsultStatus'=>3],$existMsgData->id);
        
        if (!empty($lastId)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("freeConsultRequestDeclineSuccess", $apiData['data']['langType']);
        }else{
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("failToDeclineFreeConsultRequest", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }
    
    public function acceptFreeConsultRequest_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
 
        if ($user->role != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['messageId']) || empty($apiData['data']['messageId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("messageIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['availabilityId']) || empty($apiData['data']['availabilityId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("availabilityIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
       
        $existMsgData = $this->Chat->get(['id'=>$apiData['data']['messageId'],'type'=>5,'freeConsultStatus'=>1,'status'=>1],true);
        if(empty($existMsgData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("messageDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $availabilityData = $this->User_Availability->get(['userId'=>$user->id,'getFutureAvailability'=>true,'isBooked'=>0,'id'=>$apiData['data']['availabilityId'],'status'=>1],true);
        if(empty($availabilityData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("availabilityDataNotAvailable", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $appointmentData = array();
        $appointmentData['userId'] = $existMsgData->sender;
        $appointmentData['doctorId'] = $user->id;
        $appointmentData['userAvailabilityId'] = $availabilityData->id;
        $appointmentData['appointmentType'] = 2;
        $appointmentData['price'] = 0;
        $appointmentData['isFreeConsult'] = 1;
        $appointmentData['authenticationCode'] = $this->Common_Model->random_string(4);
        $appointmentBookId = $this->User_Appointment->setData($appointmentData);
        
        if (!empty($appointmentBookId)) {
            // Send notification user to doctor
            // Set notification 
            $notiData = [];
            $notiData['send_from'] = $existMsgData->sender;
            $notiData['send_to'] = $user->id;
            $notiData['model_id'] = (int)$appointmentBookId;
            $this->Common_Model->backroundCall('scheduleAppointmentByUser', $notiData);
            // ./ Set notification

        
            // Send notification in booked user
            // Set notification 
            $notiData = [];
            $notiData['send_from'] = $existMsgData->sender;
            $notiData['send_to'] = $existMsgData->sender;
            $notiData['model_id'] = (int)$appointmentBookId;
            $notiData['doctorName'] = $user->name;
            $this->Common_Model->backroundCall('scheduleAppointmentForUser', $notiData);
            // ./ Set notification

            // Send Mail and SMS in Authentication code
            $notiData = [];
            $notiData['userId'] = $existMsgData->sender;
            $notiData['authenticationCode'] = $appointmentData['authenticationCode'];
            $this->Common_Model->backroundCall('sendMailAndSMSInAuthenticationCodeForUser', $notiData);
            // ./ Send Mail and SMS in Authentication code

            $this->User_Availability->setData(['isBooked'=>1],$availabilityData->id);
            $lastId = $this->Chat->setData(['freeConsultStatus'=>2,'appointmentId'=>$appointmentBookId],$existMsgData->id);
            
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("freeConsultRequestAcceptSuccess", $apiData['data']['langType']);
        }else{
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("failToAcceptFreeConsultRequest", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function acceptFreeConsultRequestNew_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
 
        if ($user->role != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['messageId']) || empty($apiData['data']['messageId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("messageIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        /*if (!isset($apiData['data']['availabilityId']) || empty($apiData['data']['availabilityId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("availabilityIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }*/
       
        $existMsgData = $this->Chat->get(['id'=>$apiData['data']['messageId'],'type'=>5,'freeConsultStatus'=>1,'status'=>1],true);
        if(empty($existMsgData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("messageDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        /*$availabilityData = $this->User_Availability->get(['userId'=>$user->id,'getFutureAvailability'=>true,'isBooked'=>0,'id'=>$apiData['data']['availabilityId'],'status'=>1],true);
        if(empty($availabilityData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("availabilityDataNotAvailable", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }*/

        if (!isset($apiData['data']['startDateTime']) || empty($apiData['data']['startDateTime'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("startDateTimeRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
  
        if (!isset($apiData['data']['endDateTime']) || empty($apiData['data']['endDateTime'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("endDateTimeRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
  
        if (!isset($apiData['data']['timeRange']) || empty($apiData['data']['timeRange'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("timeRangeRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
        $startdatetime = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
        $startdatetime->setTimezone(new DateTimeZone($myUserTimeZone));
        $startdatetime->setTimestamp($apiData['data']['startDateTime']);

        $enddatetime = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
        $enddatetime->setTimezone(new DateTimeZone($myUserTimeZone));
        $enddatetime->setTimestamp($apiData['data']['endDateTime']);

        $doctorAvailabilityId = $this->User_Availability->setData(['userId'=>$user->id,'dateTime'=>$startdatetime->format('U'),'endDateTime'=>$enddatetime->format('U'),'timing'=>$apiData['data']['timeRange'],'status'=>1]);
        $availabilityData = $this->User_Availability->get(['userId'=>$user->id,'isBooked'=>0,'id'=>$doctorAvailabilityId,'status'=>1],true);
        if(empty($availabilityData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("availabilityDataNotAvailable", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $appointmentData = array();
        $appointmentData['userId'] = $existMsgData->sender;
        $appointmentData['doctorId'] = $user->id;
        $appointmentData['userAvailabilityId'] = $availabilityData->id;
        $appointmentData['appointmentType'] = 2;
        $appointmentData['price'] = 0;
        $appointmentData['isFreeConsult'] = 1;
        $appointmentData['authenticationCode'] = $this->Common_Model->random_string(4);
        $appointmentBookId = $this->User_Appointment->setData($appointmentData);
        
        if (!empty($appointmentBookId)) {
            // Send notification user to doctor
            // Set notification 
            $notiData = [];
            $notiData['send_from'] = $existMsgData->sender;
            $notiData['send_to'] = $user->id;
            $notiData['model_id'] = (int)$appointmentBookId;
            $this->Common_Model->backroundCall('scheduleAppointmentByUser', $notiData);
            // ./ Set notification

        
            // Send notification in booked user
            // Set notification 
            $notiData = [];
            $notiData['send_from'] = $existMsgData->sender;
            $notiData['send_to'] = $existMsgData->sender;
            $notiData['model_id'] = (int)$appointmentBookId;
            $notiData['doctorName'] = $user->name;
            $this->Common_Model->backroundCall('scheduleAppointmentForUser', $notiData);
            // ./ Set notification

            // Send Mail and SMS in Authentication code
            $notiData = [];
            $notiData['userId'] = $existMsgData->sender;
            $notiData['authenticationCode'] = $appointmentData['authenticationCode'];
            $this->Common_Model->backroundCall('sendMailAndSMSInAuthenticationCodeForUser', $notiData);
            // ./ Send Mail and SMS in Authentication code

            $this->User_Availability->setData(['isBooked'=>1],$availabilityData->id);
            $lastId = $this->Chat->setData(['freeConsultStatus'=>2,'appointmentId'=>$appointmentBookId],$existMsgData->id);
            
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("freeConsultRequestAcceptSuccess", $apiData['data']['langType']);
        }else{
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("failToAcceptFreeConsultRequest", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function setEndUserAppointment_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
  
        if ($user->role != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['userAppointmentId']) || empty($apiData['data']['userAppointmentId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentBookIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        $appointmentExists = $this->User_Appointment->get([
            'id' => $apiData['data']['userAppointmentId'],
            'doctorId' => $user->id,
            'status' => [1,2,3],
        ], true);
        
        if(empty($appointmentExists)) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if ($appointmentExists->status == 2) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("requestedAppointmentCancelled", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if ($appointmentExists->status == 3) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("requestedAppointmentCompleted", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $doctorData = $this->User->get(['id'=>$appointmentExists->doctorId,'status'=>1,'role'=>3],true);
        if(empty($doctorData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("doctorDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        
        $appointmentBookId = $this->User_Appointment->setData([
            'status' => 3,
        ], $appointmentExists->id);
        
        $key = $this->SiteSetting_Model->get(['key'=>"commissionamount"], true);
        $commissionamount = 0;
        if(isset($key->value) && !empty($key->value)) {
            $commissionamount = round($key->value,2);
        }

        //$amount = $appointmentExists->price * 4.9 /100;
        $amount = $appointmentExists->price * $commissionamount /100;
        $amount = $appointmentExists->price - $amount;
        if(!empty($appointmentExists->discountPrice)){
            $amount = $amount - $appointmentExists->discountPrice;
        }
        
        // For doctor wallet transaction record
        $transactionData = [];
        $transactionData['userId'] = $appointmentExists->doctorId;
        $transactionData['appointmentId'] = $appointmentBookId;
        $transactionData['availabilityId'] = $appointmentExists->userAvailabilityId;
        $transactionData['amount'] = $amount;
        $transactionData['type'] = 1; // Credit amount
        $transactionData['payType'] = 2; // Add money in wallet by user book appoinment
        $transactionData['tranType'] = 1; //Wallet Transaction
        $tranId = $this->User_Transaction->setData($transactionData);

        if($appointmentExists->isFreeConsult != 1){
            // Send notification doctor to add money in wallet
            // Set notification 
            $notiData = [];
            $notiData['send_from'] = $user->id;
            $notiData['send_to'] = $appointmentExists->doctorId;
            $notiData['model_id'] = (int)$appointmentBookId;
            $notiData['amount'] = '$'.number_format($amount, 2);
            $this->Common_Model->backroundCall('addMoneyInYourWalletForScheduleAppointment', $notiData);
            // ./ Set notification

            $this->User->setData([
                'walletAmount'=> $doctorData->walletAmount + $amount,
            ],$doctorData->id);

            $this->User_Wallet_Model->setData([
                'userId'=>$doctorData->id,
                'transactionId'=>$tranId,
                'amount'=>$amount,
                'availableAmount'=>$amount,
            ]);
        }
      
        $this->apiResponse['status'] = "1";
        $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentCompletedSuccess", $apiData['data']['langType']);
        $this->apiResponse['data']['appointmentBookId'] = $appointmentBookId;
      
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }
    public function getDocumentList_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        $page_number = (isset($apiData['data']['page']) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : 1;
        $limit = (isset($apiData['data']['limit']) && $apiData['data']['limit'] != '') ? $apiData['data']['limit'] : 5;
        if (isset($apiData['data']['page']) && $apiData['data']['page'] == 1) {
            $offset = 0;
        } else {
            if (isset($apiData['data']['page']) && $apiData['data']['page'] != '1') {
                $offset = ($page_number * $limit) - $limit;
            } else {
                $offset = 0;
            }
        }

        $data = array();
        $data['doctorId'] = $user->id;
        $data['apiResponse'] = true;
        $data['getDoctorWiseData'] = $user->id;
        if(isset($apiData['data']['userAppointmentId']) && !empty($apiData['data']['userAppointmentId'])){
            $data['userAppointmentId'] = $apiData['data']['userAppointmentId'];
        }            
        
        if(isset($apiData['data']['selectedDate']) && !empty($apiData['data']['selectedDate'])){
            $datetime = new DateTime($apiData['data']['selectedDate']);
            //$datetime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));
            $data['getSelectedDateWiseData'] = $datetime->format('d-m-Y');
        }
        $data['search'] = (isset($apiData['data']['search']) ? $apiData['data']['search'] : "");
        $data['status'] = 1;
        $totalData = $this->User_Appointment_Document->get($data,false,true);
        $data['limit'] = $limit;
        $data['offset'] = $offset;
        $response = $this->User_Appointment_Document->get($data);
        if (!empty($response)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("getDocumentListSuccess", $apiData['data']['langType']);
            $this->apiResponse['totalPages'] = ceil($totalData / $limit) . "";
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification(($offset > 0 ? 'allcatchedUp' : "documentListNotFound"), $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function uploadAppointmentDocument_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if (!isset($apiData['data']['userAppointmentId']) || empty($apiData['data']['userAppointmentId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userAppointmentIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['documentType']) || empty($apiData['data']['documentType'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("documentTypeRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['documentFileRealName']) || empty($apiData['data']['documentFileRealName'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("documentFileRealNameRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['documentFileName']) || empty($apiData['data']['documentFileName'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("documentFileNameRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }


        $appointmentData = $this->User_Appointment->get(['id'=>$apiData['data']['userAppointmentId'],'status'=>1,'doctorId'=>$user->id],true);
        if(empty($appointmentData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $data = [];
        $data['userId'] =$appointmentData->userId;
        $data['uploadedBy'] = 1;
        $data['userAppointmentId'] = $apiData['data']['userAppointmentId'];
        $data['doctorId'] =  $user->id;
        $data['documentType'] = $apiData['data']['documentType'];
        $data['documentFileRealName'] = $apiData['data']['documentFileRealName'];
        $data['documentFileName'] = $apiData['data']['documentFileName'];
        $set = $this->User_Appointment_Document->setData($data);
        
        if (!empty($set)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("documentSavedSuccess", $apiData['data']['langType']);
        } else {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("failToSaveDocument", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function removeDocument_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        /*if ($user->role != '2') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotAUser", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }*/

        if (!isset($apiData['data']['userAppointmentDocumentId']) || empty($apiData['data']['userAppointmentDocumentId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("documentIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        //$documentExistData = $this->User_Appointment_Document->get(['userId'=>$user->id,'id'=>$apiData['data']['userAppointmentDocumentId']],true);
        $documentExistData = $this->User_Appointment_Document->get(['id'=>$apiData['data']['userAppointmentDocumentId']],true);
        if(empty($documentExistData)){
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("documentDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $set = $this->User_Appointment_Document->setData(['status'=>2],$documentExistData->id);
        if (!empty($set)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("documentRemoveSuccess", $apiData['data']['langType']);
        } else {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("failToRemoveDocument", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function cancelSubscription_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        $existSubData = $this->WebAppProviderSubscription_Model->get(['userId' => $user->id],true);
        if(!empty($existSubData)){
            $lastId = $existSubData->id;
            $existPlan = $this->User_Transaction->get(['id'=>$existSubData->transactionId,'userId'=>$user->id,'payType'=>3,'tranType'=>2,'status'=>1],TRUE);
            if(!empty($existPlan)){
                $this->load->library('stripe');
                $cancelSub = $this->stripe->cancelSubscriptionInstant($existPlan->stripeTransactionId);
                if(isset($cancelSub['error']['message']) && !empty($cancelSub['error']['message'])){
                    $this->apiResponse['status'] = "0";
                    $this->apiResponse['message'] = $cancelSub['error']['message'];
                    return $this->response($this->apiResponse, REST_Controller::HTTP_OK); 
                }

                if(isset($cancelSub['status']) && $cancelSub['status'] == "canceled"){
                    $this->WebAppProviderSubscription_Model->setData(['status'=>0],$existSubData->id);
                    $this->apiResponse['status'] = "1";
                    $this->apiResponse['message'] = $this->Common_Model->GetNotification("cancelSubscriptionSuccess", $apiData['data']['langType']);
                    return $this->response($this->apiResponse, REST_Controller::HTTP_OK); 
                }
            }
        }
       
        $this->apiResponse['status'] = "0";
        $this->apiResponse['message'] = $this->Common_Model->GetNotification("failCancelSubscription", $apiData['data']['langType']);
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getCurrentSubscription_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        $existSubData = $this->WebAppProviderSubscription_Model->get(['userId' => $user->id,'status'=>1],true);
        if(!empty($existSubData)){
            $response = array();
            $response['name'] = "Premium";
            $response['period'] = "Monthly";
            $response['amount'] = $existSubData->amount;
            $response['expDate'] = $existSubData->expiredDateInword;
            $response['expText'] = "Expiring Date";
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("getCurrentSubscriptionSuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
        }else{
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("currentSubscriptionNotFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getSidebarAppointmentList_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        if ($user->role  != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $page_number = (isset($apiData['data']['page']) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : 1;
        $limit = (isset($apiData['data']['limit']) && $apiData['data']['limit'] != '') ? $apiData['data']['limit'] : 5;
        if (isset($apiData['data']['page']) && $apiData['data']['page'] == 1) {
            $offset = 0;
        } 
        else {
            if (isset($apiData['data']['page']) && $apiData['data']['page'] != '1') {
                $offset = ($page_number * $limit) - $limit;
            } 
            else {
                $offset = 0;
            }
        }

        $data = array();
        $data['doctorId'] = $user->id;
        $data['apiResponse'] = true;
        $data['getAvailabilityData'] = true; 
        #$data['getAvailabilityDataSitebar'] = true;
        $data['orderAppointmentStartDate'] = true;
        $data['getUserData'] = true;
        $data['ckeckReviewReceived'] = true;

        $data['orderby'] = "id";
        $data['orderstate'] = " DESC";
        if (isset($apiData['data']['sortType']) && !empty($apiData['data']['sortType'])) {
            $data['orderstate'] = $apiData['data']['sortType'];
            $data['orderAppointmentStartDate'] = false;
        }

        if (
            isset($apiData['data']['fromDate']) && !empty($apiData['data']['fromDate'])
            && isset($apiData['data']['toDate']) && !empty($apiData['data']['toDate'])
        ) {
            $data['appointmentDateLimit'] = TRUE;
            $data['fdate'] = strtotime($apiData['data']['fromDate']);
            $data['tdate'] = strtotime($apiData['data']['toDate']);
        }
        
        // 0: Inactive 1: Active 2: Cancelled, 3: Completed, 4: Deleted STATUS
        if (isset($apiData['data']['appointmentStatus']) && $apiData['data']['appointmentStatus'] == 1){
            $data['status'] = 1;
            $data['getFutureAvailability'] = true;
        } 
        else if (isset($apiData['data']['appointmentStatus']) && $apiData['data']['appointmentStatus'] == 2) {
            $data['status'] = 3;
        } 
        else if (isset($apiData['data']['appointmentStatus']) && $apiData['data']['appointmentStatus'] == 3) {
            $data['availabilityStatusForCanel'] = true;
            $data['status'] = 2;
        } 
        else {
            $data['status'] = [1,2,3];
        }
        $data['serviceStatus'] = ['1','0'];
        $data['acceptPlanData'] = true;
        #$data['ckeckMedicalHistoryAvailable'] = true;
        $data['healthConditionAvailable'] = true;
        $data['apisearch'] = (isset($apiData['data']['search']) ? $apiData['data']['search'] : "");
        $totalData = $this->User_Appointment->get($data,false,true);
        $data['limit'] = $limit;
        $data['offset'] = $offset;
        $response = $this->User_Appointment->get($data);
        
        #echo "<pre>"; print_r($response); die;

        if(!empty($response)) {
            $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
            $resArr = [];

            foreach($response as $value) {
                $startDateTime = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
                $startDateTime->setTimezone(new DateTimeZone($myUserTimeZone));
                $startDateTime->setTimestamp($value->appointmentDateTime);
                $keyDate = $startDateTime->format("Ymd");
                $starttime = $startDateTime->format("h:i A");

                $startDateTime = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
                $startDateTime->setTimezone(new DateTimeZone($myUserTimeZone));
                $startDateTime->setTimestamp($value->appointmentEndDateTime);
                $endtime = $startDateTime->format("h:i A");
                
                $appointmentTypeTxt = "";
                if($value->appointmentType == 1) {
                    $appointmentTypeTxt = "Virtual";
                }
                else if($value->appointmentType == 2) {
                    $appointmentTypeTxt = "My Place";
                }
                else if($value->appointmentType == 3) {
                    $appointmentTypeTxt = "GYM/office";
                }

                $appointmentStatus = [];
                if($value->status == 2 && !empty($value->cancelreason) && $value->cancelreason != 0) {
                    $c_text = "Cancelled :";
                    if($value->cancelreason == 1) {
                        $c_text .= " by Client";
                    }
                    else if($value->cancelreason == 2) {
                        $c_text .= " by Provider";
                    }
                    else if($value->cancelreason == 3) {
                        $c_text .= " Funds Unavailable";
                    }
                    $appointmentStatus['text'] = $c_text;
                    $appointmentStatus['bgColor'] = "#D50000";
                }
                $appointmentStatus = $this->Common_Model->checkAppointmentStatusText($value->appointmentDateTime, $value->appointmentEndDateTime , $value->status, $myUserTimeZone, $value->appointmentType);

                $resArr[$keyDate]['arrkey'] = $keyDate;
                $resArr[$keyDate]['dayText'] = $startDateTime->format("D");
                $resArr[$keyDate]['day'] = $startDateTime->format("d");
                $resArr[$keyDate]['monthText'] = $startDateTime->format("F d");
                $resArr[$keyDate]['list'][$value->userAppointmentId] = [
                    "userAppointmentId" => $value->userAppointmentId,
                    "userId" => $value->userId,
                    "appointmentAvailabilityDateTime" => $value->appointmentAvailabilityDateTime,
                    "userName" => $value->userName,
                    "useProfileImage" => $value->useProfileImage,
                    "thumbUserProfileImage" => $value->thumbUserProfileImage,
                    "appointmentType" => $value->appointmentType,
                    "appointmentTypeTxt" => $appointmentTypeTxt,
                    "appointmentStatus" => $appointmentStatus,
                    "serviceName" => $value->serviceName,
                    "starttime" => $starttime,
                    "endtime" => $endtime,
                    "serviceDuration" => $value->serviceDuration
                ];

                /*
                if($value->status == 2 && !empty($value->cancelreason) && $value->cancelreason != 0) {
                    $c_text = "Cancelled :";
                    if($value->cancelreason == 1) {
                        $c_text .= " by Client";
                    }
                    else if($value->cancelreason == 2) {
                        $c_text .= " by Provider";
                    }
                    else if($value->cancelreason == 3) {
                        $c_text .= " Funds Unavailable";
                    }
                    $value->appointmentStatus['text'] = $c_text;
                    $value->appointmentStatus['bgColor'] = "#D50000";
                }

                $value->appointmentTypeTxt = "";
                if($value->appointmentType == 1) {
                    $value->appointmentTypeTxt = "Virtual";
                }
                else if($value->appointmentType == 2) {
                    $value->appointmentTypeTxt = "My Place";
                }
                else if($value->appointmentType == 3) {
                    $value->appointmentTypeTxt = "GYM/office";
                }
                $currentDate = date('d-m-Y h:i');
                $value->bookingDateTxt = "";
                if($value->paymentStatus == 0){
                    if(strtotime($currentDate) < $value->appointmentDateTime){
                        $value->bookingDateTxt = "Money to be deducted on ".$value->createdDateShow;
                    } 
                }

                $startDateTime = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
                $startDateTime->setTimezone(new DateTimeZone($myUserTimeZone));
                $startDateTime->setTimestamp($value->appointmentDateTime);

                $value->appointmentTimeTextAppleCalender = $startDateTime->format("d-m-Y h:i A");
                $value->appointmentTimeText = $this->Common_Model->checkDateText($value->appointmentDateTime,$myUserTimeZone);
                $value->appointmentStatus = $this->Common_Model->checkAppointmentStatusText($value->appointmentDateTime, $value->appointmentEndDateTime , $value->status,$myUserTimeZone, $value->appointmentType);
                */
            }
            
            $resArr = array_values($resArr);
            $newResArr = [];
            foreach($resArr as $k => $v) {
                $q = [];
                $q['dayText'] = $v['dayText'];
                $q['day'] = $v['day'];
                $q['monthText'] = $v['monthText'];
                foreach($v['list'] AS $i) {
                    $q['list'][] = $i;
                }
                array_push($newResArr, $q);
            }
            
            $response = $newResArr;
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("getAppointmentsListSuccessDoctor", $apiData['data']['langType']);
            $this->apiResponse['totalPages'] = ceil($totalData / $limit) . "";
            $this->apiResponse['data'] = $response;
        }
        else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification(($offset > 0 ? 'allcatchedUp' : "appointmentsListNotFoundDoctor"), $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getAppointmentPatientList_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        if ($user->role  != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $data = array();
        $data['doctorId'] = $user->id;
        $data['apiResponse'] = true;
        $data['getPatientUniqueData'] = true;

        $response = $this->User_Appointment->get($data);
        #echo "<pre>"; print_r($response); die;
        if (!empty($response)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("getAppointmentsListSuccessDoctor", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification(($offset > 0 ? 'allcatchedUp' : "appointmentsListNotFoundDoctor"), $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }




    public function uploadUserDocumentWithoutAppointment_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role  != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }        
        if (!isset($apiData['data']['userId']) || empty($apiData['data']['userId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if (!isset($apiData['data']['documentType']) || empty($apiData['data']['documentType'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("documentTypeRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if (!isset($apiData['data']['documentFileRealName']) || empty($apiData['data']['documentFileRealName'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("documentFileRealNameRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if (!isset($apiData['data']['documentFileName']) || empty($apiData['data']['documentFileName'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("documentFileNameRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        /* $appointmentData = $this->User_Appointment->get(['id'=>$apiData['data']['userAppointmentId'],'status'=>1,'doctorId'=>$user->id],true);
        if(empty($appointmentData)) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        } */
        $appointmentId = "0";
        if (isset($apiData['data']['userAppointmentId']) && !empty($apiData['data']['userAppointmentId'])) {
            $appointmentData = $this->User_Appointment->get(['id'=>$apiData['data']['userAppointmentId'],'status'=>1,'doctorId'=>$user->id],true);
            if(empty($appointmentData)) {
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentDataNotFound", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
            else {
                $appointmentId = $apiData['data']['userAppointmentId'];
            }
        }

        $userId = $apiData['data']['userId'];
        if (isset($apiData['data']['userId']) && !empty($apiData['data']['userId'])) {
            $userData = $this->User->get(['id'=>$apiData['data']['userId'], 'status'=>'1'], true);
            if(empty($userData)) {
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("userIdRequired", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
            else {
                $userId = $apiData['data']['userId'];
            }
        }

        $data = [];
        $data['userId'] = $userId;
        $data['userAppointmentId'] = $appointmentId;
        $data['doctorId'] = $user->id;
        $data['documentType'] = $apiData['data']['documentType'];
        $data['documentFileRealName'] = $apiData['data']['documentFileRealName'];
        $data['documentFileName'] = $apiData['data']['documentFileName'];
        $data['uploadedBy'] = 1;

        $set = $this->User_Appointment_Document->setData($data);
        if (!empty($set)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("documentSavedSuccess", $apiData['data']['langType']);
        } 
        else {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("failToSaveDocument", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getDocumentUserList_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        $page_number = (isset($apiData['data']['page']) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : 1;
        $limit = (isset($apiData['data']['limit']) && $apiData['data']['limit'] != '') ? $apiData['data']['limit'] : 5;
        if (isset($apiData['data']['page']) && $apiData['data']['page'] == 1) {
            $offset = 0;
        } else {
            if (isset($apiData['data']['page']) && $apiData['data']['page'] != '1') {
                $offset = ($page_number * $limit) - $limit;
            } else {
                $offset = 0;
            }
        }

        $data = array();
        $data['doctorId'] = $user->id;
        $data['apiResponse'] = true;
        $data['getPatientData'] = true;
        $data['groupByUserId'] = true;
        #$data['getDoctorWiseData'] = $user->id;

        /*
        if(isset($apiData['data']['userAppointmentId']) && !empty($apiData['data']['userAppointmentId'])) {
            $data['userAppointmentId'] = $apiData['data']['userAppointmentId'];
        }            
        
        if(isset($apiData['data']['selectedDate']) && !empty($apiData['data']['selectedDate'])){
            $datetime = new DateTime($apiData['data']['selectedDate']);
            //$datetime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));
            $data['getSelectedDateWiseData'] = $datetime->format('d-m-Y');
        }
        */

        $data['search'] = (isset($apiData['data']['search']) ? $apiData['data']['search'] : "");
        $data['status'] = 1;
        $totalData = count($this->User_Appointment_Document->get($data));
        $data['limit'] = $limit;
        $data['offset'] = $offset;
        $response = $this->User_Appointment_Document->get($data);
        if (!empty($response)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("getDocumentListSuccess", $apiData['data']['langType']);
            $this->apiResponse['totalPages'] = ceil($totalData / $limit) . "";
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification(($offset > 0 ? 'allcatchedUp' : "documentListNotFound"), $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getUserAllDocumentList_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role  != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if (!isset($apiData['data']['userId']) || empty($apiData['data']['userId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $data = array();
        $data['doctorId'] = $user->id;
        $data['userId'] = $apiData['data']['userId'];
        $data['apiResponse'] = true;
        $data['getPatientData'] = true;
        $data['status'] = 1;

        $response = $this->User_Appointment_Document->get($data);
        if (!empty($response)) {
            $patient_qrr = [];
            $doctor_arr = [];
            foreach ($response as $k => $v) {
                if(isset($v->uploadedBy) && $v->uploadedBy == 1) {
                    $doctor_arr[] = $v;
                }
                else {
                    $patient_qrr[] = $v;
                }
            }
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("getDocumentListSuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = [
                "patient" => $patient_qrr,
                "doctor" => $doctor_arr
            ];
        }
        else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification(($offset > 0 ? 'allcatchedUp' : "documentListNotFound"), $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    
    public function appointmentsyncgoogle_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role  != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $data = array();
        //$data['id'] = $user->id;
        $data['doctorId'] = $user->id;
        $data['apiResponse'] = true;
        $data['getAvailabilityData'] = true; 
        $data['orderAppointmentStartDate'] = true;
        $data['getUserData'] = true;
        $data['ckeckReviewReceived'] = true;    
        $data['status'] = 1;
        $data['getFutureAvailability'] = true;
        $data['serviceStatus'] = ['1','0'];
        $data['acceptPlanData'] = true;
        $data['getUserGoolgeCalendarData'] = true;

        $response = $this->User_Appointment->get($data);
        $arr = [];
        if (!empty($response)) {
            $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
            foreach($response as $value) {
                if(isset($value->doctorGToken) && empty($value->doctorGToken)) {
                    if(
                        !empty($value->user_gc_accessToken) && $value->user_gc_status == 1 &&
                        !empty($user->gc_accessToken) && $user->gc_status == 1
                    ) {
                        $startdatetime = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
                        $startdatetime->setTimezone(new DateTimeZone($myUserTimeZone));
                        $startdatetime->setTimestamp($value->appointmentDateTime);

                        $enddatetime = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
                        $enddatetime->setTimezone(new DateTimeZone($myUserTimeZone));
                        $enddatetime->setTimestamp($value->appointmentEndDateTime);

                        $value->app_date = $startdatetime->format('Y-m-d');
                        $value->app_startdatetime = $startdatetime->format('h:i A');
                        $value->app_enddatetime = $enddatetime->format('h:i A');

                        require_once('application/controllers/google-calendar-api.php');
                        $site_url = current_url();
                        $client_id = getenv('GOOGLE_KEY');
                        $client_secret = getenv('GOOGLE_SECRET');
                        $rurl = base_url()."google/calendar";
                        $capi = new GoogleCalendarApi();
                        $new_accessToken = $capi->RefreshAccessToken($client_id, $rurl, $client_secret, $user->gc_accessToken);
                
                        $data_arr = [
                            "doctor" => [
                                "name" => $user->name,
                                "title" => "Appointment - ".$value->userName,
                                "accessToken" => $new_accessToken,
                                "refreshToken" => $user->gc_accessToken,
                                "date" => $startdatetime->format('Y-m-d'), //date("Y-m-d", $doctorAvailabilityData->dateTime),
                                "stime" => $startdatetime->format('h:i A'), //date("H:m A", $doctorAvailabilityData->dateTime),
                                "etime" => $enddatetime->format('h:i A'), //$startdatetime->format('U'),
                                "aid" => $value->userAppointmentId
                            ] /*,
                            "user" => [
                                "name" => $user->name,
                                "title" => "Appointment - ".$value->doctorName,
                                "accessToken" => $new_accessToken,
                                "refreshToken" => $user->gc_accessToken,
                                "date" => $startdatetime->format('Y-m-d'), //date("Y-m-d", $doctorAvailabilityData->dateTime),
                                "stime" => $startdatetime->format('h:i A'), //date("H:m A", $doctorAvailabilityData->dateTime),
                                "etime" => $enddatetime->format('h:i A'), //$startdatetime->format('U'),
                                "aid" => $value->userAppointmentId
                            ] */
                        ];
                        $this->Background_Model->createEventGoogleCalender($data_arr);
                        $arr[] = $data_arr;
                    }
                }
            }
            //echo "<pre>"; print_r($arr); die;
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("getAppointmentsListSuccessDoctor", $apiData['data']['langType']);
            $this->apiResponse['data'] = $arr;
        } 
        else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification(($offset > 0 ? 'allcatchedUp' : "appointmentsListNotFoundDoctor"), $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }


    public function getDoctorDetail_post() {
        $this->load->model('User_Language_Model','User_Language');
        $this->load->model('User_Referral_Model','User_Referral');
        $this->load->model('User_Favorite_Model','User_Favorite');
        $this->load->model('User_Free_Consult_Model','User_Free_Consult');
        $this->load->model('User_Services_Model','Services');
        $this->load->model('User_Profile_Visit_Model');
        $this->load->model('User_specialties_Model');

        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);  
        if (!isset($apiData['data']['doctorId']) || empty($apiData['data']['doctorId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("doctorIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $data = array();
        $data['id'] = $apiData['data']['doctorId'];
        $data['apiResponse'] = true;
        $data['status'] = 1;
        $data['role'] = 3;
        $data['getProfessionData'] = true;
        $data['getRatingAverageData'] = true;
        $data['getProfessionalData'] = true;
        //$data['getFutureFirstAvailability'] = true;
        $data['checkDoctorAddedInFavourite'] = $user->id;
        //$data['checkAvailibilitySetting'] = true;
        $data['checkAvailibilitySettingLeftJoin'] = true;
        $response = $this->User->get($data,true);
        // print_r($this->db->last_query());die;
        if (!empty($response)) {
            $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
            //$response->nextAvailable = $this->Common_Model->checkDateText($response->nextAvailable,$myUserTimeZone);
            $response->nextAvailable = "";
            $nextAvailableData = $this->Background_Model->updateProviderAvailabilityNew($response->userId,$myUserTimeZone,$response->timeZone);
            if(isset($nextAvailableData[0]["slotsAvailable"][0]["startTimestamp"]) && !empty($nextAvailableData[0]["slotsAvailable"][0]["startTimestamp"])){
                $response->nextAvailable = $this->Common_Model->checkDateText($nextAvailableData[0]["slotsAvailable"][0]["startTimestamp"],$myUserTimeZone);
            }

            $response->preferredLanguage = $this->User_Language->get(['apiResponse'=>true,'userId'=>$response->userId,'status'=>1]);
            $response->invitedBy = $this->User_Referral->get(['apiResponse'=>true,'fromUserId'=>$response->userId,'status'=>1],true);
            $response->appointments = $this->User_Appointment->get(['userId'=>$user->id,'doctorId'=>$apiData['data']['doctorId'],'apiResponse'=>true,'status'=>3,'getDoctorData'=>true,'getAvailabilityData'=>true]);
            $freeConsultData = $this->User_Free_Consult->get(['userId'=>$user->id,'doctorId'=>$apiData['data']['doctorId'],'status'=>1],true);
            $response->isFreeConsultAvailable = (empty($freeConsultData) ? 1 : 0);
            $response->availability =  $nextAvailableData;
            
            $response->isvitualmeeting=1;
            // $distance=$this->Common->distance($user->latitude,$user->longitude,$response->latitude,$response->longitude);
            $user_latitude = isset($apiData['data']['latitude']) && !empty($apiData['data']['latitude']) ? $apiData['data']['latitude'] : $user->latitude;
            $user_longitude = isset($apiData['data']['longitude']) && !empty($apiData['data']['longitude']) ? $apiData['data']['longitude'] : $user->longitude;

            $distance=$this->Common->distance($user_latitude,$user_longitude,$response->latitude,$response->longitude);
            $adminmile=empty(getenv('MILES')) ?6000:getenv('MILES');
            if(($response->virtualPrice==0  || empty($response->virtualPrice ))  && $adminmile < $distance ) {
                $response->isvitualmeeting=0;
            }
            if($distance >$adminmile) {
                $response->mobilePrice="";
                $response->onsitePrice="";
            }
            $response->specialties = $this->User_specialties_Model->get([
                'userId'        => $data['id'],
                'apiResponse'   => true,
                'status'        => 1,
                'getOtherData'  => true,
            ]);

            // Get services list data
            $serviceData['userId']= $apiData['data']['doctorId'];
            $serviceData['status']= 1;
            if ( $adminmile < $distance) {
                $serviceData['type'] =1;
            }
            $response->services = $this->Services->get($serviceData);

            $existVisitData = $this->User_Profile_Visit_Model->get(['userId'=>$user->id,'doctorId'=>$apiData['data']['doctorId']],true);
            if(!empty($existVisitData)){
                $this->User_Profile_Visit_Model->setData(['userId'=>$user->id,'doctorId'=>$apiData['data']['doctorId'],'status'=>1],$existVisitData->id);
            }
            else{
                $this->User_Profile_Visit_Model->setData(['userId'=>$user->id,'doctorId'=>$apiData['data']['doctorId']]);
            }
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("getHealthProfessionalDetailSuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
        }
        else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common->GetNotification("healthProfessionalDetailNotFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }






}