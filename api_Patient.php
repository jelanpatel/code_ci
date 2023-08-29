<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'libraries/REST_Controller.php';

class Patient extends REST_Controller {

    function __construct() {
        parent::__construct();
        $this->load->library('upload');
        $this->load->model('Common_Model','Common');
        $this->load->model('Background_Model');
        $this->load->model('Users_Model', 'User');
        $this->load->model('Profession_Model', 'Profession');
        $this->load->model('Resources_Model','Resources');
        $this->load->model('User_Rating_Model','User_Rating');
        $this->load->model('User_Availability_Model','User_Availability');
        $this->load->model('User_Search_History_Model','User_Search_History');
        $this->load->model('User_Language_Model','User_Language');
        $this->load->model('User_Referral_Model','User_Referral');
        $this->load->model('User_Favorite_Model','User_Favorite');
        $this->load->model('User_Card_Model','User_Card');
        $this->load->model('User_Transaction_Model','User_Transaction');
        $this->load->model('User_Appointment_Model','User_Appointment');
        $this->load->model('User_Appointment_Subjective_Model','User_Appointment_Subjective');
        $this->load->model('User_Appointment_Document_Model','User_Appointment_Document');
        $this->load->model('User_Free_Consult_Model','User_Free_Consult');
        $this->load->model('User_specialties_Model');
        $this->load->model('User_Wallet_Model');
        $this->load->model('Discount_Coupon_Model');
        $this->load->model('User_Referral_Earning_Model');
        $this->load->model('User_Profile_Visit_Model');
        $this->load->model('User_Services_Model','Services');
    }

    public function getUserDashboard_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role != '2') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotAUser", $apiData['data']['langType']);
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

        $getProfeRequest = ['apiResponse'=>true,'status'=>'1','countProfessionals'=>true];
        if(!empty($apiData['data']['latitude']) && !empty($apiData['data']['longitude'])){
            $this->User->setData(['latitude'=>$apiData['data']['latitude'],'longitude'=>$apiData['data']['longitude']],$user->id);
        }
        $data['getInRadius'] = false;
        if (!empty($user->latitude) && !empty($user->longitude)) {
            $getProfeRequest['getInRadius'] = true;
            $getProfeRequest['lat'] = $user->latitude;
            $getProfeRequest['long'] = $user->longitude;
            $getProfeRequest['miles'] = empty(getenv('MILES')) ?6000:getenv('MILES');
        }
        $response['professions'] = $this->Profession->get($getProfeRequest);

        // Upcoming appoinment data
        $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
        $upcomingAppointment = $this->User_Appointment->get(['userId'=>$user->id,'apiResponse'=>true,'getAvailabilityData'=>true,'orderAppointmentStartDate'=>true,'getDoctorData'=>true,'status'=>1,'getFutureAvailability'=>true,'limit'=>5]);
        foreach($upcomingAppointment as $value){
            if($value->paymentStatus == 0){  
                $currentDate = date('d-m-Y h:i');
                $bookingDate = $value->appointmentDateTime;
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
                    $paymetData['text'] = $c_text;
                    $paymetData['bgColor'] = "#D50000";
                }
                else if($bookingDate <= strtotime($currentDate)){
                    $paymetData['text'] = "Declined";
                    $paymetData['bgColor'] = "#D50000";
                } else {
                    //$bookingBeforTwo = date('jS M Y', strtotime('+2 day', $bookingDate));
                    $bookingBeforTwo = date('jS M Y', strtotime('-3 day', $bookingDate));
                    //$paymetData['text'] = "Money to be deducted on ".$bookingBeforTwo;
                    $paymetData['text'] = "Payment Pending";
                    $paymetData['bgColor'] = "#FFA638";
                }

            } else {                
                if($value->paymentStatus == 1 && $value->status == 3) {
                    $paymetData['text'] = "Payment Success";
                    $paymetData['bgColor'] = "#00D435";
                }
                else {
                    if($value->status == 2 && !empty($value->cancelreason) && $value->cancelreason != 0) {
                        $currentDate = date('d-m-Y h:i');
                        $bookingDate = $value->appointmentDateTime;
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
                        $paymetData['text'] = $c_text;
                        $paymetData['bgColor'] = "#D50000";
                    }
                    else {
                        $paymetData['text'] = "Payment Scheduled";
                        $paymetData['bgColor'] = "#00D435";
                    }
                    #$paymetData['text'] = "Payment Scheduled";
                    #$paymetData['bgColor'] = "#00D435";
                }

            }
            if(isset($value->isFreeConsult) && $value->isFreeConsult == 1) {
                $value->serviceDuration = 30;
                $paymetData['text'] = "Free Appointment";
                $paymetData['bgColor'] = "#00D435";
            }
            $value->paymentStatus = $paymetData;
            $value->appointmentTimeText = $this->Common_Model->checkDateText($value->appointmentDateTime,$myUserTimeZone);
            $value->appointmentStatus = $this->Common_Model->checkAppointmentStatusText($value->appointmentDateTime, $value->appointmentEndDateTime , $value->status, $myUserTimeZone, $value->appointmentType,$value->doctorgender);
        }
        $response['upcomingAppointments'] = $upcomingAppointment;

        $response['resourcesAndBlogs'] = $this->Resources->get(['apiResponse'=>true,'status'=>'1','type'=>'1','orderstate'=>'DESC','orderby'=>'id'],true);
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

    public function getWebappUserDashboard_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role != '2') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotAUser", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        // Check referral code empty or not
        if(empty($user->referralCode)){
            $setData = array();
            $setData['referralCode'] = $user->id.$this->Common->random_string(4);
            $this->User->setData($setData,$user->id);
        }
        // ./ Check referral code empty or not

        $getProfeRequest = ['apiResponse'=>true,'status'=>'1','countProfessionals'=>true];
        if(!empty($apiData['data']['latitude']) && !empty($apiData['data']['longitude'])){
            $this->User->setData(['latitude'=>$apiData['data']['latitude'],'longitude'=>$apiData['data']['longitude']],$user->id);
        }
        $data['getInRadius'] = false;
        if (!empty($user->latitude) && !empty($user->longitude)) {
            $getProfeRequest['getInRadius'] = true;
            $getProfeRequest['lat'] = $user->latitude;
            $getProfeRequest['long'] = $user->longitude;
            $getProfeRequest['miles'] = empty(getenv('MILES')) ?6000:getenv('MILES');
        }
        $response['professions'] = $this->Profession->get($getProfeRequest);

        // Upcoming appoinment data
        $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
        $upcomingAppointment = $this->User_Appointment->get(['userId'=>$user->id,'apiResponse'=>true,'getAvailabilityData'=>true,'orderAppointmentStartDate'=>true,'getDoctorData'=>true,'status'=>1,'getFutureAvailability'=>true,'limit'=>3]);
        foreach($upcomingAppointment as $value){
            /* if($value->paymentStatus == 0){
                $currentDate = date('d-m-Y h:i');
                $bookingDate = $value->appointmentDateTime;
                if($bookingDate <= strtotime($currentDate)){
                    $paymetData['text'] = "Declined";
                    $paymetData['bgColor'] = "#D50000";
                } else {
                    //$bookingBeforTwo = date('jS M Y', strtotime('+2 day', $bookingDate));
                    $bookingBeforTwo = date('jS M Y', strtotime('-3 day', $bookingDate));
                    $paymetData['text'] = "Money to be deducted on ".$bookingBeforTwo;
                    $paymetData['bgColor'] = "#FFA638";
                }
            } else {
                $paymetData['text'] = "Payment Success";
                $paymetData['bgColor'] = "#00D435";
            } */
            
            if($value->paymentStatus == 0){  
                $currentDate = date('d-m-Y h:i');
                $bookingDate = $value->appointmentDateTime;
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
                    $paymetData['text'] = $c_text;
                    $paymetData['bgColor'] = "#D50000";
                }
                else if($bookingDate <= strtotime($currentDate)){
                    $paymetData['text'] = "Declined";
                    $paymetData['bgColor'] = "#D50000";
                } else {
                    //$bookingBeforTwo = date('jS M Y', strtotime('+2 day', $bookingDate));
                    $bookingBeforTwo = date('jS M Y', strtotime('-3 day', $bookingDate));
                    //$paymetData['text'] = "Money to be deducted on ".$bookingBeforTwo;
                    $paymetData['text'] = "Payment Pending";
                    $paymetData['bgColor'] = "#FFA638";
                }

            } else {                
                if($value->paymentStatus == 1 && $value->status == 3) {
                    $paymetData['text'] = "Payment Success";
                    $paymetData['bgColor'] = "#00D435";
                }
                else {
                    if($value->status == 2 && !empty($value->cancelreason) && $value->cancelreason != 0) {
                        $currentDate = date('d-m-Y h:i');
                        $bookingDate = $value->appointmentDateTime;
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
                        $paymetData['text'] = $c_text;
                        $paymetData['bgColor'] = "#D50000";
                    }
                    else {
                        $paymetData['text'] = "Payment Scheduled";
                        $paymetData['bgColor'] = "#00D435";
                    }
                    #$paymetData['text'] = "Payment Scheduled";
                    #$paymetData['bgColor'] = "#00D435";
                }

            }
            if(isset($value->isFreeConsult) && $value->isFreeConsult == 1) {
                $value->serviceDuration = 30;
                $paymetData['text'] = "Free Appointment";
                $paymetData['bgColor'] = "#00D435";
            }

            $value->paymentStatus = $paymetData;

            $value->appointmentTimeText = $this->Common_Model->checkDateText($value->appointmentDateTime,$myUserTimeZone);
            $value->appointmentStatus = $this->Common_Model->checkAppointmentStatusText($value->appointmentDateTime, $value->appointmentEndDateTime , $value->status, $myUserTimeZone, $value->appointmentType,$value->doctorgender);
        }
        $response['upcomingAppointments'] = $upcomingAppointment;

        // Appoinment history data
        $appointmentHistory = $this->User_Appointment->get(['apiResponse'=>true,'getDoctorData'=>true,'status'=>[2,3],'userId'=>$user->id,'getAvailabilityData'=>true,'limit'=>3,'orderby'=>'id','orderstate'=>'DESC']);
        
        if(!empty($appointmentHistory)){
            foreach($appointmentHistory as $value){
                /* if($value->paymentStatus == 0){
                    $currentDate = date('d-m-Y h:i');
                    $bookingDate = $value->appointmentDateTime;
                    if($bookingDate <= strtotime($currentDate)){
                        $paymetData['text'] = "Declined";
                        $paymetData['bgColor'] = "#D50000";
                    } else {
                        //$bookingBeforTwo = date('jS M Y', strtotime('+2 day', $bookingDate));
                        $bookingBeforTwo = date('jS M Y', strtotime('-3 day', $bookingDate));
                        $paymetData['text'] = "Money to be deducted on ".$bookingBeforTwo;
                        $paymetData['bgColor'] = "#FFA638";
                    }
                } else {
                    $paymetData['text'] = "Payment Success";
                    $paymetData['bgColor'] = "#00D435";
                } */
                if($value->paymentStatus == 0){  
                    $currentDate = date('d-m-Y h:i');
                    $bookingDate = $value->appointmentDateTime;
                    if(!empty($value->cancelreason) && $value->cancelreason != 0) {
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
                        else {
                            $c_text .= " Funds Unavailable";
                        }
                        $paymetData['text'] = $c_text;
                        $paymetData['bgColor'] = "#D50000";
                    }
                    else if($bookingDate <= strtotime($currentDate)){
                        $paymetData['text'] = "Declined";
                        $paymetData['bgColor'] = "#D50000";
                    } else {
                        //$bookingBeforTwo = date('jS M Y', strtotime('+2 day', $bookingDate));
                        $bookingBeforTwo = date('jS M Y', strtotime('-3 day', $bookingDate));
                        //$paymetData['text'] = "Money to be deducted on ".$bookingBeforTwo;
                        $paymetData['text'] = "Payment Pending";
                        $paymetData['bgColor'] = "#FFA638";
                    }

                } else {                
                    if($value->paymentStatus == 1 && $value->status == 3) {
                        $paymetData['text'] = "Payment Success";
                        $paymetData['bgColor'] = "#00D435";
                    }
                    else {
                        if($value->status == 2 && !empty($value->cancelreason) && $value->cancelreason != 0) {
                            $currentDate = date('d-m-Y h:i');
                            $bookingDate = $value->appointmentDateTime;
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
                            $paymetData['text'] = $c_text;
                            $paymetData['bgColor'] = "#D50000";
                        }
                        else {
                            $paymetData['text'] = "Payment Scheduled";
                            $paymetData['bgColor'] = "#00D435";
                        }
                        #$paymetData['text'] = "Payment Scheduled";
                        #$paymetData['bgColor'] = "#00D435";
                    }

                }
                if(isset($value->isFreeConsult) && $value->isFreeConsult == 1) {
                    $value->serviceDuration = 30;
                    $paymetData['text'] = "Free Appointment";
                    $paymetData['bgColor'] = "#00D435";
                }
                
                $value->paymentStatus = $paymetData;
                
                $value->appointmentTimeText = $this->Common_Model->checkDateText($value->appointmentDateTime,$myUserTimeZone);
                $value->appointmentStatus = $this->Common_Model->checkAppointmentStatusText($value->appointmentDateTime, $value->appointmentEndDateTime , $value->status,$myUserTimeZone, $value->appointmentType);
            }
        }
        $response['appointmentHistory'] = $appointmentHistory;

        $response['resourcesAndBlogs'] = $this->Resources->get(['apiResponse'=>true,'status'=>'1','type'=>'1','orderstate'=>'DESC','orderby'=>'id','limit'=>3]);
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

    public function saveDoctorRating_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role != '2') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotAUser", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['rating']) || !in_array($apiData['data']['rating'],array(1,2,3,4,5))) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("ratingRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['feedback']) || empty($apiData['data']['feedback'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("feedbackRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['doctorId']) || empty($apiData['data']['doctorId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("doctorIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['appointmentId']) || empty($apiData['data']['appointmentId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        $data = [];
        $data['send_from'] = $user->id;
        $data['send_to'] = $apiData['data']['doctorId'];
        $data['appointmentId'] = $apiData['data']['appointmentId'];
        $data['rating'] = $apiData['data']['rating'];
        $data['feedback'] = $apiData['data']['feedback'];
        
        $ratingExistData = $this->User_Rating->get(['send_from'=>$user->id,'send_to'=>$apiData['data']['doctorId'],'appointmentId'=>$apiData['data']['appointmentId']],true);
        if(!empty($ratingExistData)){
            $data['status'] = 1;
            $set = $this->User_Rating->setData($data,$ratingExistData->id);
        }else{
            $set = $this->User_Rating->setData($data);
        }

        if (!empty($set)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("doctorRatingSavedSuccess", $apiData['data']['langType']);
        } else {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("failToSaveDoctorRating", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getDoctors_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        $page_number = (isset($apiData['data']['page']) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : '';
        $limit = (isset($apiData['data']['limit']) && $apiData['data']['limit'] != '') ? $apiData['data']['limit'] : 10;
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

        $data['getRatingAverageData'] = true;
        if(isset($apiData['data']['rating']) && !empty($apiData['data']['rating'])) {
            $data['rating'] = $apiData['data']['rating'];
        }
        $data['getSpecialtiesData'] = true;
        if(isset($apiData['data']['specialties']) && !empty($apiData['data']['specialties'])) {
            $data['specialties'] = $apiData['data']['specialties'];
        }
        if(isset($apiData['data']['appointmentType']) && !empty($apiData['data']['appointmentType'])) {
            $data['appointmentType'] = $apiData['data']['appointmentType'];
        }
        if(isset($apiData['data']['availableSlot']) && !empty($apiData['data']['availableSlot'])) {
            $data['availableSlot'] = $apiData['data']['availableSlot'];
        }

        $data['apiResponse'] = true;    
        if(isset($apiData['data']['search']) && $apiData['data']['search'] != ""){
            $this->User_Search_History->setData(['userId'=>$user->id,'keyword'=>$apiData['data']['search']]);
            $data['allsearch'] = $apiData['data']['search'];
        }
        $data['getProfessionWiseData'] = (isset($apiData['data']['professionId']) && !empty($apiData['data']['professionId']) && $apiData['data']['professionId'] != "0" ? $apiData['data']['professionId'] : "");
        $data['status'] = 1;
        $data['role'] = 3;       
        $data['subscriptionDoctorList'] = true;
        $data['getInRadiusNew'] = true;
        $data['lat'] = (isset($apiData['data']['latitude']) && !empty($apiData['data']['latitude']) ? $apiData['data']['latitude'] : $user->latitude);
        $data['long'] =  (isset($apiData['data']['longitude']) && !empty($apiData['data']['longitude']) ? $apiData['data']['longitude'] : $user->longitude);
        $data['latNotBlank'] = true;
        $data['miles'] = empty(getenv('MILES')) ?6000:getenv('MILES');        
        //$data['getFutureFirstAvailability'] = true;
        $data['getProfessionData'] = true;
        $data['getProfessionalData'] = true;
        $data['getRatingAverageData'] = true;
        $data['checkAvailibilitySetting'] = true;
        $data['ispresenceforsearch'] = 1;
        $data['isfreeplan'] = 0;
        $totalData = count($this->User->get($data));
        $data['limit'] = $limit;
        $data['offset'] = $offset;
        $response = $this->User->get($data);
        // echo "<pre>";print_r($response);die;
        $this->apiResponse['noNearMessageDisplay'] ='';     
        if(isset($response) && empty($response)){
            $this->apiResponse['noNearMessageDisplay'] ="There are currently no available professionals in your area. We're working on it! \n\n However, feel free to schedule a virtual appointment with an out-of-area professional.";
            $data = array();
            if(isset($apiData['data']['search']) && $apiData['data']['search'] != ""){               
                $data['search'] = $apiData['data']['search'];
            }

            $data['getRatingAverageData'] = true;
            if(isset($apiData['data']['rating']) && !empty($apiData['data']['rating'])) {
                $data['rating'] = $apiData['data']['rating'];
            }
            $data['getSpecialtiesData'] = true;
            if(isset($apiData['data']['specialties']) && !empty($apiData['data']['specialties'])) {
                $data['specialties'] = $apiData['data']['specialties'];
            }
            if(isset($apiData['data']['appointmentType']) && !empty($apiData['data']['appointmentType'])) {
                #$data['appointmentType'] = $apiData['data']['appointmentType'];
            }
            if(isset($apiData['data']['availableSlot']) && !empty($apiData['data']['availableSlot'])) {
                $data['availableSlot'] = $apiData['data']['availableSlot'];
            }

            $data['apiResponse'] = true;  
            $data['getProfessionWiseData'] = (isset($apiData['data']['professionId']) && !empty($apiData['data']['professionId']) ? $apiData['data']['professionId'] : "");
            $data['status'] = 1;
            $data['role'] = 3;                       
            $data['latNotBlank'] = true; 
            //$data['getFutureFirstAvailability'] = true;
            $data['subscriptionDoctorList'] = true;
            $data['getProfessionData'] = true;
            $data['getProfessionalData'] = true;
            $data['getRatingAverageData'] = true;       
            $data['checkAvailibilitySetting'] = true;      
            $data['ispresenceforsearch'] = 1; 
            $data['isfreeplan'] = 0; 
            $data['isvitual'] = true;
            $data['getInRadiusNew'] = true;
            $data['getOnlyMilesNumber'] = true;
            $data['lat'] = (isset($apiData['data']['latitude']) && !empty($apiData['data']['latitude']) ? $apiData['data']['latitude'] : $user->latitude);
            $data['long'] =  (isset($apiData['data']['longitude']) && !empty($apiData['data']['longitude']) ? $apiData['data']['longitude'] : $user->longitude);
            $data['miles'] = empty(getenv('MILES')) ?6000:getenv('MILES');

            $totalData = count($this->User->get($data));
            $data['limit'] = $limit;
            $data['offset'] = $offset;            
            $response = $this->User->get($data);            
        }         
        if (!empty($response)) {
            $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
            foreach($response as $value){ 
                $value->diff_distance = round($value->distance,2)." Miles";               
                /*
                $value->diff_distance = "0.00 Miles"; 
                if(!empty($user->latitude) && !empty($user->longitude) && !empty($value->latitude) && !empty($value->longitude)) {
                    $disnc = $this->Common_Model->distance($user->latitude, $user->longitude, $value->latitude, $value->longitude);
                    $value->diff_distance = number_format($disnc,2)." Miles";
                }
                */
                //$nextAvailable =  $this->User_Availability->get(['getFutureAvailability'=>true,'isBooked'=>0,'userId'=>$value->userId,'status'=>1,'orderby'=>'dateTime','orderstate'=>'ASC'],true);
                //$value->nextAvailable = $this->Common_Model->checkDateText($nextAvailable->dateTime,$myUserTimeZone);
                //$value->nextAvailable = $this->Common_Model->checkDateText($value->nextAvailable,$myUserTimeZone);
                $value->nextAvailable = "";
                $nextAvailableData = $this->Background_Model->updateProviderAvailabilityNew($value->userId,$myUserTimeZone,$value->timeZone);
                if(isset($nextAvailableData[0]["slotsAvailable"][0]["startTimestamp"]) && !empty($nextAvailableData[0]["slotsAvailable"][0]["startTimestamp"])){
                    $value->nextAvailable = $this->Common_Model->checkDateText($nextAvailableData[0]["slotsAvailable"][0]["startTimestamp"],$myUserTimeZone);
                }
            }
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("getHealthProfessionalsSuccess", $apiData['data']['langType']);
            $this->apiResponse['totalPages'] = ceil($totalData / $limit) . "";
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['noNearMessageDisplay'] ="There are currently no available professionals in your area. We're working on it! \n\n However, feel free to schedule a virtual appointment with an out-of-area professional.";
            #$this->apiResponse['noNearMessageDisplay']='';
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification(($offset > 0 ? 'allcatchedUp' : "healthProfessionalsNotFound"), $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }
    
    public function getDoctorDetail_post() {
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
            /*$response->availability =  $this->User_Availability->get(['apiResponse'=>true,'groupByDate'=>$myUserTimeZone,'getFutureAvailability'=>true,'isBooked'=>0,'userId'=>$response->userId,'status'=>1,'orderby'=>'dateTime','orderstate'=>'ASC','limit'=>10]);
            if(!empty($response->availability)){
                foreach($response->availability as $value){
                    $datetime = new DateTime();
                    $datetime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));
                    $datetime->setTimestamp($value->dateTime);
                    $value->dayAndDate = $this->Common_Model->getDayAndDateName($value->dateTime,$myUserTimeZone);
                    $value->totalSlotsAvailable = $this->User_Availability->get(['getByDate'=>['date'=>$datetime->format('d-m-Y'),'timeZone'=>$myUserTimeZone],'isBooked'=>0,'userId'=>$response->userId,'status'=>1],false,true);
                    
                }
            }*/
            $response->isvitualmeeting=1;
            // $distance=$this->Common->distance($user->latitude,$user->longitude,$response->latitude,$response->longitude);
            $user_latitude = isset($apiData['data']['latitude']) && !empty($apiData['data']['latitude']) ? $apiData['data']['latitude'] : $user->latitude;
            
            $user_longitude = isset($apiData['data']['longitude']) && !empty($apiData['data']['longitude']) ? $apiData['data']['longitude'] : $user->longitude;
            
            $distance=$this->Common->distance($user_latitude,$user_longitude,$response->latitude,$response->longitude);
            $adminmile=empty(getenv('MILES')) ?6000:getenv('MILES');
            if(($response->virtualPrice==0  || empty($response->virtualPrice ))  && $adminmile < $distance ){
                $response->isvitualmeeting=0;
            }
            if($distance >$adminmile){
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
            }else{
                $this->User_Profile_Visit_Model->setData(['userId'=>$user->id,'doctorId'=>$apiData['data']['doctorId']]);
            }

            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("getHealthProfessionalDetailSuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common->GetNotification("healthProfessionalDetailNotFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }
    
    public function getDoctorDetailNonRegister_post() {
        $this->checkGuestUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
  
        $user =(object) array();  
        if(isset($apiData['data']['userTimezone']) && !empty($apiData['data']['userTimezone'])){
            $user->timeZone = $apiData['data']['userTimezone'];            
        }
        
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
        $data['getProfessionalData'] = true;
        $data['getRatingAverageData'] = true;
        //$data['getFutureFirstAvailability'] = true;
        // $data['checkDoctorAddedInFavourite'] = $user->id;
        $data['checkAvailibilitySetting'] = true;
        $response = $this->User->get($data,true);
        #echo "<pre>"; print_r($response); exit;
        if (!empty($response)) {
            $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
            $response->nextAvailable = "";
            $nextAvailableData = $this->Background_Model->updateProviderAvailabilityNew($response->userId,$myUserTimeZone,$response->timeZone);
            if(isset($nextAvailableData[0]["slotsAvailable"][0]["startTimestamp"]) && !empty($nextAvailableData[0]["slotsAvailable"][0]["startTimestamp"])){
                $response->nextAvailable = $this->Common_Model->checkDateText($nextAvailableData[0]["slotsAvailable"][0]["startTimestamp"],$myUserTimeZone);
            }
            $response->nextAvailable = "";

            $response->preferredLanguage = $this->User_Language->get(['apiResponse'=>true,'userId'=>$response->userId,'status'=>1]);
            $response->invitedBy = $this->User_Referral->get(['apiResponse'=>true,'fromUserId'=>$response->userId,'status'=>1],true);
            $response->appointments = $this->User_Appointment->get(['doctorId'=>$apiData['data']['doctorId'],'apiResponse'=>true,'status'=>3,'getDoctorData'=>true,'getAvailabilityData'=>true]);
            // $freeConsultData = $this->User_Free_Consult->get(['doctorId'=>$apiData['data']['doctorId'],'status'=>1],true);
            $response->isFreeConsultAvailable = 1;
            $response->availability = $nextAvailableData;
            $response->isvitualmeeting=1;
            
            $response->specialties = $this->User_specialties_Model->get([
                'userId'        => $data['id'],
                'apiResponse'   => true,
                'status'        => 1,
                'getOtherData'  => true,
            ]);
            
            // Get services list data
            $serviceData['userId']= $apiData['data']['doctorId'];
            $serviceData['status']= 1;
            if((isset($apiData['data']['latitude']) && !empty($apiData['data']['latitude'])) && (isset($apiData['data']['longitude']) && !empty($apiData['data']['longitude']))){
                $lat = (isset($apiData['data']['latitude']) && !empty($apiData['data']['latitude']) ? $apiData['data']['latitude'] : '');
                $long =  (isset($apiData['data']['longitude']) && !empty($apiData['data']['longitude']) ? $apiData['data']['longitude'] : '');
                $distance=$this->Common->distance($lat,$long,$response->latitude,$response->longitude);          
                $adminmile=empty(getenv('MILES')) ? 6000:getenv('MILES');
                if ($adminmile < $distance) {
                    $serviceData['type'] =1;
                }
            } else {
                $serviceData['type'] =1;
            }
            $response->services = $this->Services->get($serviceData);
            $existVisitData = $this->User_Profile_Visit_Model->get(['doctorId'=>$apiData['data']['doctorId']],true);
            if(!empty($existVisitData)){
                $this->User_Profile_Visit_Model->setData(['doctorId'=>$apiData['data']['doctorId'],'status'=>1],$existVisitData->id);
            }else{
                $this->User_Profile_Visit_Model->setData(['doctorId'=>$apiData['data']['doctorId']]);
            }

            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("getHealthProfessionalDetailSuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common->GetNotification("healthProfessionalDetailNotFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getServiceDoctorDetail_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
  
        if(!isset($apiData['data']['serviceId']) || empty($apiData['data']['serviceId'])){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("serviceIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $serviceData = $this->Services->get(['id'=>$apiData['data']['serviceId'],'status'=>1],true);
        if(empty($serviceData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("serviceDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $data = array();
        $data['id'] = $serviceData->userId;
        $data['apiResponse'] = true;
        $data['status'] = 1;
        $data['role'] = 3;
        $data['getProfessionData'] = true;
        $data['getRatingAverageData'] = true;
        //$data['getFutureFirstAvailability'] = true;
        $data['checkDoctorAddedInFavourite'] = $user->id;
        $data['checkAvailibilitySetting'] = true;
        $response = $this->User->get($data,true);
        
        if (!empty($response)) {
            $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
            $response->nextAvailable = "";
            $nextAvailableData = $this->Background_Model->updateProviderAvailabilityNew($response->userId,$myUserTimeZone,$response->timeZone);
            if(isset($nextAvailableData[0]["slotsAvailable"][0]["startTimestamp"]) && !empty($nextAvailableData[0]["slotsAvailable"][0]["startTimestamp"])){
                $response->nextAvailable = $this->Common_Model->checkDateText($nextAvailableData[0]["slotsAvailable"][0]["startTimestamp"],$myUserTimeZone);
            }

            $response->preferredLanguage = $this->User_Language->get(['apiResponse'=>true,'userId'=>$response->userId,'status'=>1]);
            $response->invitedBy = $this->User_Referral->get(['apiResponse'=>true,'fromUserId'=>$response->userId,'status'=>1],true);
            $response->appointments = $this->User_Appointment->get(['userId'=>$user->id,'doctorId'=>$serviceData->userId,'apiResponse'=>true,'status'=>3,'getDoctorData'=>true,'getAvailabilityData'=>true]);
            $freeConsultData = $this->User_Free_Consult->get(['userId'=>$user->id,'doctorId'=>$serviceData->userId,'status'=>1],true);
            $response->isFreeConsultAvailable = (empty($freeConsultData) ? 1 : 0);
            $response->availability =  $nextAvailableData;
            
            $response->isvitualmeeting=1;
            $distance=$this->Common->distance($user->latitude,$user->longitude,$response->latitude,$response->longitude);            
            $adminmile=empty(getenv('MILES')) ?6000:getenv('MILES');
            if(($response->virtualPrice==0  || empty($response->virtualPrice ))  && $adminmile < $distance ){
                $response->isvitualmeeting=0;
            }
            if($distance >$adminmile){
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
            $response->services = $this->Services->get(['userId'=>$serviceData->userId,'status'=>1]);

            $existVisitData = $this->User_Profile_Visit_Model->get(['userId'=>$user->id,'doctorId'=>$serviceData->userId],true);
            if(!empty($existVisitData)){
                $this->User_Profile_Visit_Model->setData(['userId'=>$user->id,'doctorId'=>$serviceData->userId,'status'=>1],$existVisitData->id);
            }else{
                $this->User_Profile_Visit_Model->setData(['userId'=>$user->id,'doctorId'=>$serviceData->userId]);
            }

            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("getHealthProfessionalDetailSuccess", $apiData['data']['langType']);
            $this->apiResponse['doctorData'] = $response;
            $this->apiResponse['serviceData'] = $serviceData;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common->GetNotification("healthProfessionalDetailNotFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getServiceDoctorDetailNonRegister_post() {
        $this->checkGuestUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
  
        if(!isset($apiData['data']['serviceId']) || empty($apiData['data']['serviceId'])){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("serviceIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $serviceData = $this->Services->get(['id'=>$apiData['data']['serviceId'],'status'=>1],true);
        if(empty($serviceData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("serviceDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $data = array();
        $data['id'] = $serviceData->userId;
        $data['apiResponse'] = true;
        $data['status'] = 1;
        $data['role'] = 3;
        $data['getProfessionData'] = true;
        $data['getRatingAverageData'] = true;
        //$data['getFutureFirstAvailability'] = true;
        $data['checkAvailibilitySetting'] = true;
        $response = $this->User->get($data,true);
        
        if (!empty($response)) {
            $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
            $response->nextAvailable = "";
            $nextAvailableData = $this->Background_Model->updateProviderAvailabilityNew($response->userId,$myUserTimeZone,$response->timeZone);
            if(isset($nextAvailableData[0]["slotsAvailable"][0]["startTimestamp"]) && !empty($nextAvailableData[0]["slotsAvailable"][0]["startTimestamp"])){
                $response->nextAvailable = $this->Common_Model->checkDateText($nextAvailableData[0]["slotsAvailable"][0]["startTimestamp"],$myUserTimeZone);
            }

            $response->preferredLanguage = $this->User_Language->get(['apiResponse'=>true,'userId'=>$response->userId,'status'=>1]);
            $response->invitedBy = $this->User_Referral->get(['apiResponse'=>true,'fromUserId'=>$response->userId,'status'=>1],true);
           
            $response->isFreeConsultAvailable = '1';
            $response->availability =  $nextAvailableData;
            
            $response->specialties = $this->User_specialties_Model->get([
                'userId'        => $data['id'],
                'apiResponse'   => true,
                'status'        => 1,
                'getOtherData'  => true,
            ]);

            // Get services list data
            $response->services = $this->Services->get(['userId'=>$serviceData->userId,'status'=>1]);

            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("getHealthProfessionalDetailSuccess", $apiData['data']['langType']);
            $this->apiResponse['doctorData'] = $response;
            $this->apiResponse['serviceData'] = $serviceData;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common->GetNotification("healthProfessionalDetailNotFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getUserSearchHistory_post() {
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
        $data['userId'] = $user->id;
        $data['apiResponse'] = true;
        $data['search'] = (isset($apiData['data']['search']) ? $apiData['data']['search'] : "");
        $data['status'] = 1;
        $totalData = $this->User_Search_History->get($data,false,true);
        $data['limit'] = $limit;
        $data['offset'] = $offset;
        $response = $this->User_Search_History->get($data);
        if (!empty($response)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("getUserSearchHistorySuccess", $apiData['data']['langType']);
            $this->apiResponse['totalPages'] = ceil($totalData / $limit) . "";
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification(($offset > 0 ? 'allcatchedUp' : "userSearchHistoryNotFound"), $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function clearUserSearchHistory_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        $response = $this->User_Search_History->delete($user->id);
        if (!empty($response)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("clearSearchHistorySuccess", $apiData['data']['langType']);
        } else {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("failClearSearchHistorySuccess", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function removeUserSearchHistory_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        if (!isset($apiData['data']['userSearchHistoryId']) || empty($apiData['data']['userSearchHistoryId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userSearchHistoryIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $response = $this->User_Search_History->delete($user->id,$apiData['data']['userSearchHistoryId']);
        if (!empty($response)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("removeSearchHistorySuccess", $apiData['data']['langType']);
        } else {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("failRemoveSearchHistorySuccess", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function addRemoveDoctorInFavorite_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role != '2') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotAUser", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['doctorId']) || empty($apiData['data']['doctorId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("doctorIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $getExistDoctor = $this->User->get(['apiResponse'=>true,'id'=>$apiData['data']['doctorId'],'status'=>1],true);
        if(empty($getExistDoctor)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("doctorDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $favoriteExistData = $this->User_Favorite->get(['fromUserId'=>$user->id,'toUserId'=>$apiData['data']['doctorId']],true);
        if(!empty($favoriteExistData)){
            if($favoriteExistData->status == 1){
                $set = $this->User_Favorite->setData(['status'=>2],$favoriteExistData->id);
                $successMsg = "removeToFavoriteSuccess";
                $failMsg = "failToRemoveFavorite";
            }else{
                $set = $this->User_Favorite->setData(['status'=>1],$favoriteExistData->id);
                $successMsg = "addToFavoriteSuccess";
                $failMsg = "failToAddFavorite";
            }
        }else{
            $set = $this->User_Favorite->setData(['fromUserId'=>$user->id,'toUserId'=>$apiData['data']['doctorId']]);
            $successMsg = "addToFavoriteSuccess";
            $failMsg = "failToAddFavorite";
        }

        if (!empty($set)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification($successMsg, $apiData['data']['langType']);
        } else {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification($failMsg, $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getFavoriteDoctors_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        $page_number = (isset($apiData['data']['page']) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : '';
        $limit = (isset($apiData['data']['limit']) && $apiData['data']['limit'] != '') ? $apiData['data']['limit'] : 10;
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
        $data['apiResponse'] = true;
        $data['search'] = (isset($apiData['data']['search']) && !empty($apiData['data']['search']) ? $apiData['data']['search'] : "");
        $data['status'] = 1;
        $data['role'] = 3;
        $data['getOnlyFavouriteData'] = $user->id;
        $totalData = count($this->User->get($data));
        //$data['getFutureFirstAvailability'] = true;
        $data['getProfessionData'] = true;
        $data['getProfessionalData'] = true;
        $data['getRatingAverageData'] = true;
        //$data['ispresenceforsearch'] = 1;
        $data['limit'] = $limit;
        $data['offset'] = $offset;
        $response = $this->User->get($data);
        if (!empty($response)) {
            $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
            foreach($response as $value){
                //$nextAvailable =  $this->User_Availability->get(['getFutureAvailability'=>true,'isBooked'=>0,'userId'=>$value->userId,'status'=>1,'orderby'=>'dateTime','orderstate'=>'ASC'],true);
                //$value->nextAvailable = $this->Common_Model->checkDateText($nextAvailable->dateTime,$myUserTimeZone);
                //$value->nextAvailable = $this->Common_Model->checkDateText($value->nextAvailable,$myUserTimeZone);
                $value->nextAvailable = "";
                $nextAvailableData = $this->Background_Model->updateProviderAvailabilityNew($value->userId,$myUserTimeZone,$value->timeZone);
                if(isset($nextAvailableData[0]["slotsAvailable"][0]["startTimestamp"]) && !empty($nextAvailableData[0]["slotsAvailable"][0]["startTimestamp"])){
                    $value->nextAvailable = $this->Common_Model->checkDateText($nextAvailableData[0]["slotsAvailable"][0]["startTimestamp"],$myUserTimeZone);
                }
            }
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("getFavoriteDoctorSuccess", $apiData['data']['langType']);
            $this->apiResponse['totalPages'] = ceil($totalData / $limit) . "";
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification(($offset > 0 ? 'allcatchedUp' : "favoriteDoctorNotFound"), $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }
     
    public function getDoctorAvailability_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
  
        if (!isset($apiData['data']['doctorId']) || empty($apiData['data']['doctorId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("doctorIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $getDoctor = $this->User->get(['apiResponse'=>true,'getProfessionData'=>true,'getRatingAverageData'=>true,'id'=>$apiData['data']['doctorId'],'status'=>1],true);
        if(empty($getDoctor)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("doctorDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
        $data = array();
        $data['userId'] = $apiData['data']['doctorId'];
        $data['apiResponse'] = true;
        $data['status'] = 1;
        $data['isBooked'] = 0;
        $data['groupByDate'] = $myUserTimeZone;
        $data['getFutureAvailability'] = true;
        $data['orderby'] = 'dateTime';
        $data['orderstate'] = 'ASC';
        $availability = $this->User_Availability->get($data);
        // echo $this->db->last_query(); die;
        if (!empty($availability)) {

            $distance=$this->Common->distance($user->latitude,$user->longitude,$getDoctor->latitude,$getDoctor->longitude);            
            $adminmile=empty(getenv('MILES')) ?6000:getenv('MILES');
            if($distance >$adminmile){
                $getDoctor->mobilePrice="";
                $getDoctor->onsitePrice="";
            }
            $response['doctorData'] = $getDoctor;
            foreach($availability as $value){
                $datetime = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
                $datetime->setTimezone(new DateTimeZone($myUserTimeZone));
                $datetime->setTimestamp($value->dateTime);
                $value->dayAndDate = $this->Common_Model->getDayAndDateName($value->dateTime,$myUserTimeZone);
                $value->slotsAvailable = $this->User_Availability->get(['apiResponse'=>true,'availabilityDateTimeFormat'=>$myUserTimeZone,'getByDate'=>['date'=>$datetime->format('d-m-Y'),'timeZone'=>$myUserTimeZone],'isBooked'=>0,'userId'=>$value->userId,'status'=>1,'orderby'=>'dateTime','orderstate'=>'ASC']);
                
                $value->totalSlotsAvailable = count($value->slotsAvailable);
            }
            $response['availabilityData'] = $availability;
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("getHealthProfessionalAvailabilitySuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common->GetNotification("healthProfessionalAvailabilityNotFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }
     
    public function getDoctorAvailabilityNew_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
  
        if (!isset($apiData['data']['doctorId']) || empty($apiData['data']['doctorId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("doctorIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $getDoctor = $this->User->get(['apiResponse'=>true,'getProfessionData'=>true,'getRatingAverageData'=>true,'id'=>$apiData['data']['doctorId'],'status'=>1],true);
        if(empty($getDoctor)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("doctorDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
        $doctorTimeZone = (!empty($getDoctor->timeZone) ? $getDoctor->timeZone : getenv('SYSTEMTIMEZON'));
        
        $availability = $this->Background_Model->updateProviderAvailabilityNew($getDoctor->userId,$myUserTimeZone,$doctorTimeZone);
        //print_r($availability); die;
        if (!empty($availability)) {
            $distance=$this->Common->distance($user->latitude,$user->longitude,$getDoctor->latitude,$getDoctor->longitude);            
            $adminmile=empty(getenv('MILES')) ?6000:getenv('MILES');
            if($distance >$adminmile){
                $getDoctor->mobilePrice="";
                $getDoctor->onsitePrice="";
            }
            $response['doctorData'] = $getDoctor;

            /*$finalAvailability = array();
            $currentdatetime = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
            $currentdatetime->setTimezone(new DateTimeZone($myUserTimeZone));
            foreach($availability as $value){
                $startdatetime = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
                $startdatetime->setTimezone(new DateTimeZone($myUserTimeZone));
                $startdatetime->setTimestamp($value["startTimestamp"]);
                
                if($currentdatetime->format('U') > $startdatetime->format('U')){
                    continue;
                }
                
                if(empty($finalAvailability[$value["date"]])){
                    $mainTmp = array();
                    $mainTmp["userId"] = $getDoctor->userId;
                    $mainTmp["dayAndDate"] = $this->Common_Model->getDayAndDateName($value["startTimestamp"],$myUserTimeZone);
                    $mainTmp["totalSlotsAvailable"] = 0;
                    $finalAvailability[$value["date"]] = $mainTmp;
                }

                // Create a slots array
                $subTmp = array();
                $subTmp["userId"] = $getDoctor->userId;
                $subTmp["startTimestamp"] = $value["startTimestamp"];
                $subTmp["endTimestamp"] = $value["endTimestamp"];
                $subTmp["dateTimeFormat"] = $startdatetime->format('d-m-Y h:i A');
                $subTmp["dateFormat"] = $startdatetime->format('d-m-Y');
                $subTmp["timeFormat"] = $startdatetime->format('h:i A');
                $subTmp["isBooked"] = 0;
                $subTmp["timing"] = $value["timing"];
                $finalAvailability[$value["date"]]["slotsAvailable"][] = $subTmp;
                $finalAvailability[$value["date"]]["totalSlotsAvailable"]++;
            }*/
            $response['availabilityData'] = $availability;
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("getHealthProfessionalAvailabilitySuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common->GetNotification("healthProfessionalAvailabilityNotFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }
     
    public function bookAppointment_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
  
        if (!isset($apiData['data']['doctorId']) || empty($apiData['data']['doctorId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("doctorIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['userAvailabilityId']) || empty($apiData['data']['userAvailabilityId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userAvailabilityIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['appointmentType']) || empty($apiData['data']['appointmentType'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentTypeRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if((isset($apiData['data']['isfreeconsult']) && $apiData['data']['isfreeconsult'] != '1') && (isset($apiData['data']['appointmentType']) && $apiData['data']['appointmentType'] != 1)){
            if (!isset($apiData['data']['location']) || empty($apiData['data']['location'])) {
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("locationRequired", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
    
            if (!isset($apiData['data']['latitude']) || empty($apiData['data']['latitude'])) {
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("latitudeRequired", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
    
            if (!isset($apiData['data']['longitude']) || empty($apiData['data']['longitude'])) {
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("longitudeRequired", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
    
            if (!isset($apiData['data']['userCardId']) || empty($apiData['data']['userCardId'])) {
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("userCardIdRequired", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
        }else{
            if (isset($apiData['data']['isfreeconsult']) && $apiData['data']['isfreeconsult'] == '1') {
                $freeConsultData = $this->User_Free_Consult->get(['userId'=>$user->id,'doctorId'=>$apiData['data']['doctorId'],'status'=>1], true);
                if (!empty($freeConsultData)) {
                    $this->apiResponse['message'] = $this->Common_Model->GetNotification("alreadybookfreeconsult", $apiData['data']['langType']);
                    return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                }
            }else{
                if (!isset($apiData['data']['userCardId']) || empty($apiData['data']['userCardId'])) {
                    $this->apiResponse['message'] = $this->Common_Model->GetNotification("userCardIdRequired", $apiData['data']['langType']);
                    return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                }
            }
        }
        $doctorData = $this->User->get(['id'=>$apiData['data']['doctorId'],'status'=>1,'role'=>3],true);
        if(empty($doctorData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("doctorDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $doctorAvailabilityData = $this->User_Availability->get(['userId'=>$doctorData->id,'getFutureAvailability'=>true,'isBooked'=>0,'id'=>$apiData['data']['userAvailabilityId'],'status'=>1],true);
        
        if(empty($doctorAvailabilityData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("doctorAvailabilityDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

      
        if(isset($apiData['data']['isfreeconsult']) && $apiData['data']['isfreeconsult']=='1'){
            $appointmentData = array();
                $appointmentData['userId'] = $user->id;
                $appointmentData['doctorId'] = $doctorData->id;
                $appointmentData['userAvailabilityId'] = $doctorAvailabilityData->id;                
                $appointmentData['appointmentType'] = $apiData['data']['appointmentType'];
                $appointmentData['price'] = 0;
                $appointmentData['isFreeConsult'] = 1;
                $appointmentData['authenticationCode'] = $this->Common->random_string(4);
                $appointmentBookId = $this->User_Appointment->setData($appointmentData);
                
                // Send Mail and SMS in Authentication code
                $notiData = [];
                $notiData['userId'] = $user->id;
                $notiData['authenticationCode'] = $appointmentData['authenticationCode'];
                $this->Common_Model->backroundCall('sendMailAndSMSInAuthenticationCodeForUser', $notiData);
                // ./ Send Mail and SMS in Authentication code
                // Send notification user to doctor
                // Set notification 
                $notiData = [];
                $notiData['send_from'] = $user->id;
                $notiData['send_to'] = $doctorData->id;
                $notiData['model_id'] = (int)$appointmentBookId;
                $notiData['doctorName'] = $doctorData->name;
                $notiData['doctorEmail'] = $doctorData->email;
                $notiData['startDateTime'] = $doctorAvailabilityData->dateTime;
                $notiData['endDateTime'] = $doctorAvailabilityData->endDateTime;
                $this->Common_Model->backroundCall('scheduleAppointmentByUser', $notiData);
                // ./ Set notification               
                // Send notification in booked user
                // Set notification 
                $notiData = [];
                $notiData['send_from'] = $user->id;
                $notiData['send_to'] = $user->id;
                $notiData['model_id'] = (int)$appointmentBookId;
                $notiData['doctorName'] = $doctorData->name;
                $notiData['doctorEmail'] = $doctorData->email;
                $notiData['startDateTime'] = $doctorAvailabilityData->dateTime;
                $notiData['endDateTime'] = $doctorAvailabilityData->endDateTime;
                $this->Common_Model->backroundCall('scheduleAppointmentForUser', $notiData);
                // ./ Set notification
                                
                $this->User_Availability->setData(['isBooked'=>1],$doctorAvailabilityData->id);
                $this->User_Free_Consult->setData(['userId'=> $user->id,'doctorId'=>$doctorData->id]);
                $this->apiResponse['status'] = "1";
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentBookedSuccess", $apiData['data']['langType']);
                $this->apiResponse['data']['appointmentBookId'] = $appointmentBookId;
            }else{
                $userCardData = $this->User_Card->get(['userId'=>$user->id,'id'=>$apiData['data']['userCardId'],'status'=>1],true);
                if(empty($userCardData)){
                    $this->apiResponse['message'] = $this->Common_Model->GetNotification("userCardDataNotFound", $apiData['data']['langType']);
                    return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                }
                $amount = 0;
                if($apiData['data']['appointmentType'] == 1){
                    $amount = $doctorData->virtualPrice;
                }elseif($apiData['data']['appointmentType'] == 2){
                    $amount = $doctorData->mobilePrice;
                }elseif($apiData['data']['appointmentType'] == 3){
                    $amount = $doctorData->onsitePrice;
                }
                $referraldata = array();
                $couponCode = "";
                $discountCouponId = "";
                $discountPrice = "";
                if(isset($apiData['data']['discountCouponCode']) && !empty($apiData['data']['discountCouponCode'])){
                    if($apiData['data']['discountCouponCode'] == "ReferralEarning5Off"){
                        $referraldata = $this->User_Referral_Earning_Model->get(['userId'=>$user->id,'status'=>1,'orderstate'=>'ASC','orderby'=>'id'],true);
                        if(!empty($referraldata)){
                            $amount = $amount - 5;
                            $couponCode = $apiData['data']['discountCouponCode'];
                            $discountPrice = 5;
                        }else{
                            $this->apiResponse['message'] = $this->Common_Model->GetNotification("couponCodeInvalid", $apiData['data']['langType']);
                            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                        }
                    }else{
                        $existCoupon = $this->Discount_Coupon_Model->get(['promocode'=> $apiData['data']['discountCouponCode'],'status'=>1],true);
                        if(!empty($existCoupon)){
                            if($existCoupon->type == 1){
                                $amount = $amount - $existCoupon->value;
                                $discountPrice = $existCoupon->value;
                            }elseif($existCoupon->type == 2){
                                $discountPrice = (($amount * $existCoupon->value) / 100);
                                $amount = $amount - (($amount * $existCoupon->value) / 100);
                            }
                            $couponCode = $existCoupon->promocode;
                            $discountCouponId = $existCoupon->id;
                        }else{
                            $this->apiResponse['message'] = $this->Common_Model->GetNotification("couponCodeInvalid", $apiData['data']['langType']);
                            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                        }
                    }
                }
                
                $this->load->library('stripe',array('type'=>'1'));
                $stripeChargeData['customer'] = $userCardData->customerId;
                $stripeChargeData['source'] = $userCardData->cardId;
                $stripeChargeData['amount'] = $amount * 100;
                $stripeChargeData['capture'] = false;        
                $stripeChargeData['description'] ="Book Appointment Payment, userId: #".$user->id.", doctorId: #".$doctorData->id.", userCardId: #".$userCardData->id." , doctorAvailabilityId: #".$doctorAvailabilityData->id.", appointmentType: ".$apiData['data']['appointmentType'];
                $response = $this->stripe->addCharge($stripeChargeData);

                error_log("\n\n -------------------------------------" . date('c'). " \n Request => ".json_encode($stripeChargeData) . " \n Response => ".json_encode($response,true), 3, FCPATH.'worker/bookAppoinmentPayment-'.date('d-m-Y').'.txt');
                if(isset($response) && !empty($response)){
                    if(isset($response['error'])){ 
                        $response['error']['status'] = '0';
                        $this->apiResponse = $response['error'];
                        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                    }elseif(!isset($response->id) || $response->id==""){ 
                        $this->apiResponse['status'] = "0";
                        $this->apiResponse['message'] = $this->Common_Model->GetNotification("failToBookAppointment", $apiData['data']['langType']);
                        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                    }else{
                        $appointmentData = array();
                        $appointmentData['userId'] = $user->id;
                        $appointmentData['doctorId'] = $doctorData->id;
                        $appointmentData['userAvailabilityId'] = $doctorAvailabilityData->id;
                        $appointmentData['userCardId'] = $userCardData->id;
                        $appointmentData['appointmentType'] = $apiData['data']['appointmentType'];
                        $appointmentData['couponCode'] = $couponCode;
                        $appointmentData['discountCouponId'] = $discountCouponId;
                        $appointmentData['discountPrice'] = $discountPrice;
                        if((!isset($apiData['data']['isfreeconsult']) || $apiData['data']['isfreeconsult'] != '1') && (isset($apiData['data']['appointmentType']) && $apiData['data']['appointmentType'] != 1)){
                            $appointmentData['location'] = $apiData['data']['location'];
                            $appointmentData['latitude'] = $apiData['data']['latitude'];
                            $appointmentData['longitude'] = $apiData['data']['longitude'];
                        }
                        $appointmentData['price'] = $amount;
                        $appointmentData['authenticationCode'] = $this->Common->random_string(4);
                        $appointmentBookId = $this->User_Appointment->setData($appointmentData);
                        
                        // Send Mail and SMS in Authentication code
                        $notiData = [];
                        $notiData['userId'] = $user->id;
                        $notiData['authenticationCode'] = $appointmentData['authenticationCode'];
                        $this->Common_Model->backroundCall('sendMailAndSMSInAuthenticationCodeForUser', $notiData);
                        // ./ Send Mail and SMS in Authentication code
        
                        // For user transaction record
                        $transactionData = array();
                        $transactionData['userId'] = $user->id;
                        $transactionData['userIdTo'] = $doctorData->id;
                        $transactionData['cardId'] = $userCardData->id;
                        $transactionData['appointmentId'] = $appointmentBookId;
                        $transactionData['availabilityId'] = $doctorAvailabilityData->id;
                        $transactionData['stripeTransactionId'] = $response['id'];
                        $transactionData['stripeTranJson'] = json_encode($response);
                        $transactionData['amount'] = $amount;
                        $transactionData['type'] = 2; // Debit amount
                        $transactionData['payType'] = 1; // Book Appointment Payment 
                        $transactionData['tranType'] = 2; //Stripe Transaction
                        $transactionData['status'] =4 ; 
                        $transactionData['createdDate'] = $response['created'];
                        $this->User_Transaction->setData($transactionData);
                        // // ./ Set notification

                        // Send notification user to doctor
                        // Set notification 
                        $notiData = [];
                        $notiData['send_from'] = $user->id;
                        $notiData['send_to'] = $doctorData->id;
                        $notiData['model_id'] = (int)$appointmentBookId;
                        $notiData['doctorName'] = $doctorData->name;
                        $notiData['doctorEmail'] = $doctorData->email;
                        $notiData['startDateTime'] = $doctorAvailabilityData->dateTime;
                        $notiData['endDateTime'] = $doctorAvailabilityData->endDateTime;
                        $this->Common_Model->backroundCall('scheduleAppointmentByUser', $notiData);
                        // ./ Set notification

                        // Send notification for transaction success
                        // Set notification 
                        $notiData = [];
                        $notiData['send_from'] = $user->id;
                        $notiData['send_to'] = $user->id;
                        $notiData['model_id'] = (int)$appointmentBookId;
                        $notiData['amount'] = '$'.number_format($amount,2);
                        $this->Common_Model->backroundCall('transactionSuccessForScheduleAppointment', $notiData);
                        // ./ Set notification

                        // Send notification in booked user
                        // Set notification 
                        $notiData = [];
                        $notiData['send_from'] = $user->id;
                        $notiData['send_to'] = $user->id;
                        $notiData['model_id'] = (int)$appointmentBookId;
                        $notiData['doctorName'] = $doctorData->name;
                        $notiData['doctorEmail'] = $doctorData->email;
                        $notiData['startDateTime'] = $doctorAvailabilityData->dateTime;
                        $notiData['endDateTime'] = $doctorAvailabilityData->endDateTime;
                        $this->Common_Model->backroundCall('scheduleAppointmentForUser', $notiData);
                        // ./ Set notification
                        
                        if(!empty($referraldata)){
                            $this->User_Referral_Earning_Model->setData(['status'=>2],$referraldata->id);
                        }

                        // $this->User->setData(['walletAmount'=>($doctorData->walletAmount + $amount)],$doctorData->id);
                        $this->User_Availability->setData(['isBooked'=>1],$doctorAvailabilityData->id);
                        $this->apiResponse['status'] = "1";
                        $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentBookedSuccess", $apiData['data']['langType']);
                        $this->apiResponse['data']['appointmentBookId'] = $appointmentBookId;
                    }
                }else{
                    $this->apiResponse['status'] = "0";
                    $this->apiResponse['message'] = $this->Common_Model->GetNotification("failToBookAppointment", $apiData['data']['langType']);
                    return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                }
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function bookAppointmentNew_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
  
        if (!isset($apiData['data']['doctorId']) || empty($apiData['data']['doctorId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("doctorIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
  
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

        /*if (!isset($apiData['data']['userAvailabilityId']) || empty($apiData['data']['userAvailabilityId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userAvailabilityIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }*/

        if (!isset($apiData['data']['appointmentType']) || empty($apiData['data']['appointmentType'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentTypeRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        
        if((!isset($apiData['data']['isfreeconsult']) || $apiData['data']['isfreeconsult'] != '1') && (isset($apiData['data']['appointmentType']) && $apiData['data']['appointmentType'] != 1)){
            if (!isset($apiData['data']['location']) || empty($apiData['data']['location'])) {
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("locationRequired", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
    
            if (!isset($apiData['data']['latitude']) || empty($apiData['data']['latitude'])) {
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("latitudeRequired", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
    
            if (!isset($apiData['data']['longitude']) || empty($apiData['data']['longitude'])) {
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("longitudeRequired", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
    
            if (!isset($apiData['data']['userCardId']) || empty($apiData['data']['userCardId'])) {
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("userCardIdRequired", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
        }else{
            if (isset($apiData['data']['isfreeconsult']) && $apiData['data']['isfreeconsult'] == '1') {
                $freeConsultData = $this->User_Free_Consult->get(['userId'=>$user->id,'doctorId'=>$apiData['data']['doctorId'],'status'=>1], true);
                if (!empty($freeConsultData)) {
                    $this->apiResponse['message'] = $this->Common_Model->GetNotification("alreadybookfreeconsult", $apiData['data']['langType']);
                    return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                }
            }else{
                if (!isset($apiData['data']['userCardId']) || empty($apiData['data']['userCardId'])) {
                    $this->apiResponse['message'] = $this->Common_Model->GetNotification("userCardIdRequired", $apiData['data']['langType']);
                    return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                }
            }
        }
        $doctorData = $this->User->get(['id'=>$apiData['data']['doctorId'],'status'=>1,'role'=>3],true);
        if(empty($doctorData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("doctorDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        /*$doctorAvailabilityData = $this->User_Availability->get(['userId'=>$doctorData->id,'getFutureAvailability'=>true,'isBooked'=>0,'id'=>$apiData['data']['userAvailabilityId'],'status'=>1],true);
        
        if(empty($doctorAvailabilityData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("doctorAvailabilityDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }*/
        $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
        $startdatetime = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
        $startdatetime->setTimezone(new DateTimeZone($myUserTimeZone));
        $startdatetime->setTimestamp($apiData['data']['startDateTime']);

        $enddatetime = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
        $enddatetime->setTimezone(new DateTimeZone($myUserTimeZone));
        $enddatetime->setTimestamp($apiData['data']['endDateTime']);

        $existAvailabilityData = $this->User_Availability->get(['userId'=>$doctorData->id,'checkBookedSlot'=>['startDateTime'=>$apiData['data']['startDateTime'],'endDateTime'=>$apiData['data']['endDateTime']],'isBooked'=>1,'status'=>1],true);
        if(!empty($existAvailabilityData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("selectedSlotAlreadyBooked", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        
        $doctorAvailabilityId = $this->User_Availability->setData(['userId'=>$doctorData->id,'dateTime'=>$startdatetime->format('U'),'endDateTime'=>$enddatetime->format('U'),'timing'=>$apiData['data']['timeRange'],'status'=>1]);
        $doctorAvailabilityData = $this->User_Availability->get(['userId'=>$doctorData->id,'isBooked'=>0,'id'=>$doctorAvailabilityId,'status'=>1],true);
        if(empty($doctorAvailabilityData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("doctorAvailabilityDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        
        if(isset($apiData['data']['isfreeconsult']) && $apiData['data']['isfreeconsult']=='1'){
            $appointmentData = array();
            $appointmentData['userId'] = $user->id;
            $appointmentData['doctorId'] = $doctorData->id;
            $appointmentData['userAvailabilityId'] = $doctorAvailabilityData->id;                
            $appointmentData['appointmentType'] = $apiData['data']['appointmentType'];
            $appointmentData['price'] = 0;
            $appointmentData['isFreeConsult'] = 1;
            $appointmentData['authenticationCode'] = $this->Common->random_string(4);
            $appointmentBookId = $this->User_Appointment->setData($appointmentData);
            
            // Send Mail and SMS in Authentication code
            $notiData = [];
            $notiData['userId'] = $user->id;
            $notiData['authenticationCode'] = $appointmentData['authenticationCode'];
            $this->Common_Model->backroundCall('sendMailAndSMSInAuthenticationCodeForUser', $notiData);
            // ./ Send Mail and SMS in Authentication code
            // Send notification user to doctor
            // Set notification 
            $notiData = [];
            $notiData['send_from'] = $user->id;
            $notiData['send_to'] = $doctorData->id;
            $notiData['model_id'] = (int)$appointmentBookId;
            $notiData['doctorName'] = $doctorData->name;
            $notiData['doctorEmail'] = $doctorData->email;
            $notiData['startDateTime'] = $doctorAvailabilityData->dateTime;
            $notiData['endDateTime'] = $doctorAvailabilityData->endDateTime;
            $this->Common_Model->backroundCall('scheduleAppointmentByUser', $notiData);
            // ./ Set notification               
            // Send notification in booked user
            // Set notification 
            $notiData = [];
            $notiData['send_from'] = $user->id;
            $notiData['send_to'] = $user->id;
            $notiData['model_id'] = (int)$appointmentBookId;
            $notiData['doctorName'] = $doctorData->name;
            $notiData['doctorEmail'] = $doctorData->email;
            $notiData['startDateTime'] = $doctorAvailabilityData->dateTime;
            $notiData['endDateTime'] = $doctorAvailabilityData->endDateTime;
            $this->Common_Model->backroundCall('scheduleAppointmentForUser', $notiData);
            // ./ Set notification
                            
            $this->User_Availability->setData(['isBooked'=>1],$doctorAvailabilityData->id);
            $this->User_Free_Consult->setData(['userId'=> $user->id,'doctorId'=>$doctorData->id]);
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentBookedSuccess", $apiData['data']['langType']);
            $this->apiResponse['data']['appointmentBookId'] = $appointmentBookId;
        }else{
            $userCardData = $this->User_Card->get(['userId'=>$user->id,'id'=>$apiData['data']['userCardId'],'status'=>1],true);
            if(empty($userCardData)){
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("userCardDataNotFound", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
            $amount = 0;
            if($apiData['data']['appointmentType'] == 1){
                $amount = $doctorData->virtualPrice;
            }elseif($apiData['data']['appointmentType'] == 2){
                $amount = $doctorData->mobilePrice;
            }elseif($apiData['data']['appointmentType'] == 3){
                $amount = $doctorData->onsitePrice;
            }
            $referraldata = array();
            $couponCode = "";
            $discountCouponId = "";
            $discountPrice = "";
            if(isset($apiData['data']['discountCouponCode']) && !empty($apiData['data']['discountCouponCode'])){
                if($apiData['data']['discountCouponCode'] == "ReferralEarning5Off"){
                    $referraldata = $this->User_Referral_Earning_Model->get(['userId'=>$user->id,'status'=>1,'orderstate'=>'ASC','orderby'=>'id'],true);
                    if(!empty($referraldata)){
                        $amount = $amount - 5;
                        $couponCode = $apiData['data']['discountCouponCode'];
                        $discountPrice = 5;
                    }else{
                        $this->apiResponse['message'] = $this->Common_Model->GetNotification("couponCodeInvalid", $apiData['data']['langType']);
                        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                    }
                }else{
                    $existCoupon = $this->Discount_Coupon_Model->get(['promocode'=> $apiData['data']['discountCouponCode'],'status'=>1],true);
                    if(!empty($existCoupon)){
                        if($existCoupon->type == 1){
                            $amount = $amount - $existCoupon->value;
                            $discountPrice = $existCoupon->value;
                        }elseif($existCoupon->type == 2){
                            $discountPrice = (($amount * $existCoupon->value) / 100);
                            $amount = $amount - (($amount * $existCoupon->value) / 100);
                        }
                        $couponCode = $existCoupon->promocode;
                        $discountCouponId = $existCoupon->id;
                    }else{
                        $this->apiResponse['message'] = $this->Common_Model->GetNotification("couponCodeInvalid", $apiData['data']['langType']);
                        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                    }
                }
            }
            
            // appoment code

            $appointmentData = array();
            $appointmentData['userId'] = $user->id;
            $appointmentData['doctorId'] = $doctorData->id;
            $appointmentData['userAvailabilityId'] = $doctorAvailabilityData->id;
            $appointmentData['userCardId'] = $userCardData->id;
            $appointmentData['appointmentType'] = $apiData['data']['appointmentType'];
            $appointmentData['couponCode'] = $couponCode;
            $appointmentData['discountCouponId'] = $discountCouponId;
            $appointmentData['discountPrice'] = $discountPrice;
            $appointmentData['paymentStatus'] = "0";
            if((!isset($apiData['data']['isfreeconsult']) || $apiData['data']['isfreeconsult'] != '1') && (isset($apiData['data']['appointmentType']) && $apiData['data']['appointmentType'] != 1)){
                $appointmentData['location'] = $apiData['data']['location'];
                $appointmentData['latitude'] = $apiData['data']['latitude'];
                $appointmentData['longitude'] = $apiData['data']['longitude'];
            }
            $appointmentData['price'] = $amount;
            $appointmentData['authenticationCode'] = $this->Common->random_string(4);
            $appointmentBookId = $this->User_Appointment->setData($appointmentData);

            //if  72  then olny stripe code and transcation 
            $currentDate = date('d-m-Y h:i');
            $hourdiff = round(($doctorAvailabilityData->dateTime - strtotime($currentDate))/3600, 1);
            if($hourdiff <= 72){ 
                $this->load->library('stripe',array('type'=>'1'));
                $stripeChargeData['customer'] = $userCardData->customerId;
                $stripeChargeData['source'] = $userCardData->cardId;
                $stripeChargeData['amount'] = $amount * 100;
                $stripeChargeData['capture'] = false;        
                $stripeChargeData['description'] ="Book Appointment Payment, userId: #".$user->id.", doctorId: #".$doctorData->id.", userCardId: #".$userCardData->id." , doctorAvailabilityId: #".$doctorAvailabilityData->id.", appointmentType: ".$apiData['data']['appointmentType'];
                $response = $this->stripe->addCharge($stripeChargeData);

                error_log("\n\n -------------------------------------" . date('c'). " \n Request => ".json_encode($stripeChargeData) . " \n Response => ".json_encode($response,true), 3, FCPATH.'worker/bookAppoinmentPayment-'.date('d-m-Y').'.txt');
                if(isset($response) && !empty($response)){
                    if(isset($response['error'])){ 
                        $response['error']['status'] = '0';
                        $this->apiResponse = $response['error'];
                        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                    }elseif(!isset($response->id) || $response->id==""){ 
                        $this->apiResponse['status'] = "0";
                        $this->apiResponse['message'] = $this->Common_Model->GetNotification("failToBookAppointment", $apiData['data']['langType']);
                        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                    }else{
                        
                        // Send Mail and SMS in Authentication code
                        $notiData = [];
                        $notiData['userId'] = $user->id;
                        $notiData['authenticationCode'] = $appointmentData['authenticationCode'];
                        $this->Common_Model->backroundCall('sendMailAndSMSInAuthenticationCodeForUser', $notiData);
                        // ./ Send Mail and SMS in Authentication code
        
                        // For user transaction record
                        $transactionData = array();
                        $transactionData['userId'] = $user->id;
                        $transactionData['userIdTo'] = $doctorData->id;
                        $transactionData['cardId'] = $userCardData->id;
                        $transactionData['appointmentId'] = $appointmentBookId;
                        $transactionData['availabilityId'] = $doctorAvailabilityData->id;
                        $transactionData['stripeTransactionId'] = $response['id'];
                        $transactionData['stripeTranJson'] = json_encode($response);
                        $transactionData['amount'] = $amount;
                        $transactionData['type'] = 2; // Debit amount
                        $transactionData['payType'] = 1; // Book Appointment Payment 
                        $transactionData['tranType'] = 2; //Stripe Transaction
                        $transactionData['status'] =4 ; 
                        $transactionData['createdDate'] = $response['created'];
                        $this->User_Transaction->setData($transactionData);                        
                        $appointmentBookId = $this->User_Appointment->setData(['paymentStatus'=>1],$appointmentBookId);
                        // Send notification for transaction success
                        // Set notification 
                        $notiData = [];
                        $notiData['send_from'] = $user->id;
                        $notiData['send_to'] = $user->id;
                        $notiData['model_id'] = (int)$appointmentBookId;
                        $notiData['amount'] = '$'.number_format($amount,2);
                        $this->Common_Model->backroundCall('transactionSuccessForScheduleAppointment', $notiData);
                        // ./ Set notification
                        // // ./ Set notification
                    }
                }else{
                    $this->apiResponse['status'] = "0";
                    $this->apiResponse['message'] = $this->Common_Model->GetNotification("failToBookAppointment", $apiData['data']['langType']);
                    return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                } 
            }
                    // Send notification user to doctor
                    // Set notification 
            $notiData = [];
            $notiData['send_from'] = $user->id;
            $notiData['send_to'] = $doctorData->id;
            $notiData['model_id'] = (int)$appointmentBookId;
            $notiData['doctorName'] = $doctorData->name;
            $notiData['doctorEmail'] = $doctorData->email;
            $notiData['startDateTime'] = $doctorAvailabilityData->dateTime;
            $notiData['endDateTime'] = $doctorAvailabilityData->endDateTime;
            $this->Common_Model->backroundCall('scheduleAppointmentByUser', $notiData);
            // ./ Set notification

            // Send notification in booked user
            // Set notification 
            $notiData = [];
            $notiData['send_from'] = $user->id;
            $notiData['send_to'] = $user->id;
            $notiData['model_id'] = (int)$appointmentBookId;
            $notiData['doctorName'] = $doctorData->name;
            $notiData['doctorEmail'] = $doctorData->email;
            $notiData['startDateTime'] = $doctorAvailabilityData->dateTime;
            $notiData['endDateTime'] = $doctorAvailabilityData->endDateTime;
            $this->Common_Model->backroundCall('scheduleAppointmentForUser', $notiData);
            // ./ Set notification
            
            if(!empty($referraldata)){
                $this->User_Referral_Earning_Model->setData(['status'=>2],$referraldata->id);
            }

            // $this->User->setData(['walletAmount'=>($doctorData->walletAmount + $amount)],$doctorData->id);
            $this->User_Availability->setData(['isBooked'=>1],$doctorAvailabilityData->id);
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentBookedSuccess", $apiData['data']['langType']);
            $this->apiResponse['data']['appointmentBookId'] = $appointmentBookId;
                
            
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function saveAppointmentSubjective_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role != '2') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotAUser", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['userAppointmentId']) || empty($apiData['data']['userAppointmentId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userAppointmentIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        /* if (!isset($apiData['data']['goalId']) || empty($apiData['data']['goalId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("goalIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        } */
        if (!isset($apiData['data']['goalText']) || empty($apiData['data']['goalText'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("goalIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

       /*  if (!isset($apiData['data']['painScale']) || !in_array($apiData['data']['painScale'],array(1,2,3,4,5,6,7,8,9,10))) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("painScaleRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        } */

        if (!isset($apiData['data']['functionScale']) || !in_array($apiData['data']['functionScale'],array('0',0,1,2,3,4,5,6,7,8,9,10))) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("functionScaleRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['complaintGetting']) || !in_array($apiData['data']['complaintGetting'],array(1,2,3,4))) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("complaintGettingRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $appointmentData = $this->User_Appointment->get(['id'=>$apiData['data']['userAppointmentId'],'status'=>1,'userId'=>$user->id],true);
        if(empty($appointmentData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $data = [];
        $data['userId'] = $user->id;
        $data['userAppointmentId'] = $apiData['data']['userAppointmentId'];
        // $data['goalId'] = $apiData['data']['goalId'];
        $data['goalText'] = $apiData['data']['goalText'];
        $data['painScale'] = $apiData['data']['painScale'];
        $data['functionScale'] = $apiData['data']['functionScale'];
        $data['complaintGetting'] = $apiData['data']['complaintGetting'];
        
        $subjectiveExistData = $this->User_Appointment_Subjective->get(['userId'=>$user->id,'userAppointmentId'=>$apiData['data']['userAppointmentId']],true);
        if(!empty($subjectiveExistData)){
            $data['status'] = 1;
            $set = $this->User_Appointment_Subjective->setData($data,$subjectiveExistData->id);
        }else{
            $set = $this->User_Appointment_Subjective->setData($data);
        }

        if (!empty($set)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentSubjectiveSavedSuccess", $apiData['data']['langType']);
        } else {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("failToSaveAppointmentSubjective", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }
    
    public function uploadAppointmentDocument_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role != '2') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotAUser", $apiData['data']['langType']);
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


        $appointmentData = $this->User_Appointment->get(['id'=>$apiData['data']['userAppointmentId'],'status'=>1,'userId'=>$user->id],true);
        if(empty($appointmentData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $data = [];
        $data['userId'] = $user->id;
        $data['userAppointmentId'] = $apiData['data']['userAppointmentId'];
        $data['doctorId'] = $appointmentData->doctorId;
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
        $data['userId'] = $user->id;
        $data['apiResponse'] = true;
        if(isset($apiData['data']['userAppointmentId']) && !empty($apiData['data']['userAppointmentId'])){
            $data['userAppointmentId'] = $apiData['data']['userAppointmentId'];
        }
        if(isset($apiData['data']['doctorId']) && !empty($apiData['data']['doctorId'])){
            $data['getDoctorWiseData'] = $apiData['data']['doctorId'];
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

    public function getUserBookedAppointment_post() {
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
        $data['userId'] = $user->id;
        $data['apiResponse'] = true;
        $data['getAvailabilityData'] = true;
        $data['orderAppointmentStartDate'] = true;
        $data['getDoctorData'] = true;
        // 0: Inactive 1: Active 2: Cancelled, 3: Completed, 4: Deleted STATUS
        if (isset($apiData['data']['appointmentStatus']) && $apiData['data']['appointmentStatus'] == 1) {
            $data['status'] = 1;
            $data['getFutureAvailability'] = true;
        } else if (isset($apiData['data']['appointmentStatus']) && $apiData['data']['appointmentStatus'] == 2) {
            $data['status'] = 3;
        } else if (isset($apiData['data']['appointmentStatus']) && $apiData['data']['appointmentStatus'] == 3) {
            $data['availabilityStatusForCanel'] = true;
            $data['status'] = 2;
        } else {
            $data['status'] = [1,3,2];
        }

        if (isset($apiData['data']['cancelStatus']) && !empty($apiData['data']['cancelStatus'])) {
            $data['cancelStatus'] = $apiData['data']['cancelStatus'];
        }
        
        $data['orderby'] = "id";
        $data['orderstate'] = " DESC";
        if (isset($apiData['data']['sortType']) && !empty($apiData['data']['sortType'])) {
            $data['orderstate'] = $apiData['data']['sortType'];
            $data['orderAppointmentStartDate'] = false;
        }

        /*
        if (
            isset($apiData['data']['fromDate']) && !empty($apiData['data']['fromDate'])
            && isset($apiData['data']['toDate']) && !empty($apiData['data']['toDate'])
        ) {
            $data['appointmentDateLimit'] = TRUE;
            $data['fdate'] = strtotime($apiData['data']['fromDate']);
            $data['tdate'] = strtotime($apiData['data']['toDate']);
        }
        */
        
        if (isset($apiData['data']['fromDate']) && !empty($apiData['data']['fromDate'])) {
            $data['appointmentDateLimit'] = TRUE;
            $data['fdate'] = strtotime($apiData['data']['fromDate']);

            if (isset($apiData['data']['toDate']) && !empty($apiData['data']['toDate'])) {
                $data['tdate'] = strtotime($apiData['data']['toDate']);
            }
            else {
                $data['tdate'] = time();
            }
        }

        $data['acceptPlanData'] = true;
        $data['apisearch'] = (isset($apiData['data']['search']) ? $apiData['data']['search'] : "");
        $totalData = $this->User_Appointment->get($data,false,true);
        $data['limit'] = $limit;
        $data['offset'] = $offset;
        $response = $this->User_Appointment->get($data);
        if (!empty($response)) {
            $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
            foreach($response as $value){
                if($value->paymentStatus == 0){
                    $currentDate = date('d-m-Y h:i');
                    $bookingDate = $value->appointmentDateTime;
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
                        $paymetData['text'] = $c_text;
                        $paymetData['bgColor'] = "#D50000";
                    }
                    else if($bookingDate <= strtotime($currentDate)){
                        $paymetData['text'] = "Declined";
                        $paymetData['bgColor'] = "#D50000";
                    } else {
                        //$bookingBeforTwo = date('jS M Y', strtotime('+2 day', $bookingDate));
                        $bookingBeforTwo = date('jS M Y', strtotime('-3 day', $bookingDate));
                        //$paymetData['text'] = "Money to be deducted on ".$bookingBeforTwo;
                        $paymetData['text'] = "Payment Pending";
                        $paymetData['bgColor'] = "#FFA638";
                    }
                } 
                else {
                    if($value->paymentStatus == 1 && $value->status == 3) {
                        $paymetData['text'] = "Payment Success";
                        $paymetData['bgColor'] = "#00D435";
                    }
                    else {
                        if($value->status == 2 && !empty($value->cancelreason) && $value->cancelreason != 0) {
                            $currentDate = date('d-m-Y h:i');
                            $bookingDate = $value->appointmentDateTime;
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
                            $paymetData['text'] = $c_text;
                            $paymetData['bgColor'] = "#D50000";
                        }
                        else {
                            $paymetData['text'] = "Payment Scheduled";
                            $paymetData['bgColor'] = "#00D435";
                        }
                        #$paymetData['text'] = "Payment Scheduled";
                        #$paymetData['bgColor'] = "#00D435";
                    }
                }
                if(isset($value->isFreeConsult) && $value->isFreeConsult == 1) {
                    $value->serviceDuration = 30;
                    $paymetData['text'] = "Free Appointment";
                    $paymetData['bgColor'] = "#00D435";
                }
                $value->appointmentTimeText = $this->Common_Model->checkDateText($value->appointmentDateTime,$myUserTimeZone);
                $value->paymentStatus = $paymetData;
                $value->appointmentStatus = $this->Common_Model->checkAppointmentStatusText($value->appointmentDateTime, $value->appointmentEndDateTime , $value->status, $myUserTimeZone, $value->appointmentType,$value->doctorgender);
            }
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("getAppointmentsListSuccess", $apiData['data']['langType']);
            $this->apiResponse['totalPages'] = ceil($totalData / $limit) . "";
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification(($offset > 0 ? 'allcatchedUp' : "appointmentsListNotFound"), $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getUserDocumentList_post() {
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
        $data['userId'] = $user->id;
        $data['apiResponse'] = true;
        $data['getDateAndDoctorGroupingData'] = true;
        $data['checkAppointmentAvaialble'] = true;
        $data['status'] = 1;
        $totalData = $this->User_Appointment_Document->get($data,false,true);
        $data['limit'] = $limit;
        $data['offset'] = $offset;
        $response = $this->User_Appointment_Document->get($data);
        if (!empty($response)) {
            foreach($response as $value){
                $value->totalFile = $this->User_Appointment_Document->get(['getSelectedDateWiseData'=>$value->uploadedDate,'doctorId'=>$value->doctorId,'userId'=>$user->id,'status'=>1],false,true);
            }
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
    
    public function getUserBookedAppointmentDetail_post() {
        $this->load->model('HumanBodyParts_Model');
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role != '2') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotAUser", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['userAppointmentId']) || empty($apiData['data']['userAppointmentId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userAppointmentIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $data = array();
        $data['userId'] = $user->id;
        $data['id'] = $apiData['data']['userAppointmentId'];
        $data['apiResponse'] = true;
        $data['getAvailabilityData'] = true;
        $data['getDoctorData'] = true;
        $data['status'] = [1,2,3]; //0: Inactive 1: Active 2: Cancelled, 3: Completed, 4: Deleted
        $data['availabilityStatusForCanel'] = true;
        $response = $this->User_Appointment->get($data,true);
        // print_r($response);die;
        if (!empty($response)) {
            $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
            $response->appointmentTimeText = $this->Common_Model->checkDateText($response->appointmentDateTime,$myUserTimeZone);
            $response->appointmentStatus = $this->Common_Model->checkAppointmentStatusText($response->appointmentDateTime, $response->appointmentEndDateTime , $response->status, $myUserTimeZone, $response->appointmentType,$response->doctorgender);
            $response->subjective = $this->User_Appointment_Subjective->get(['apiResponse'=>true,'getGoalData'=>true,'userId'=>$user->id,'userAppointmentId'=>$response->userAppointmentId,'status'=>1],true);
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
            $response->frontImage = $response->thumbFrontImage = null;
            $response->backImage = $response->thumbBackImage = null;
            if (isset($frontbodyParts) && !empty($frontbodyParts)) {
                $response->frontImage = $frontbodyParts->bodyImage;
                $response->thumbFrontImage = $frontbodyParts->thumbBodyImage;
            }
            $response->price = number_format($response->price, 2);
            if (isset($backbodyParts) && !empty($backbodyParts)) {
                $response->backImage = $backbodyParts->bodyImage;
                $response->thumbBackImage = $backbodyParts->thumbBodyImage;
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
    
    public function getUserTransaction_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role != '2') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotAUser", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $page_number = (isset($apiData['data']['page']) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : '';
        $limit = (isset($apiData['data']['limit']) && $apiData['data']['limit'] != '') ? $apiData['data']['limit'] : 10;
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
        $data['status'] = [1,4];
        $data['userId'] = $user->id;
        $data['type'] = 2;
        $data['tranType'] = 2;
        $data['getDoctorData'] = true;
        $totalData = $this->User_Transaction->get($data,false,true);
        $data['apiResponse'] = true;
        $data['getFormattedAmount'] = true;
        $data['userTranDateFormate'] = true;
        $data['limit'] = $limit;
        $data['offset'] = $offset;
        $response = $this->User_Transaction->get($data);
        if (!empty($response)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("getTransactionListSuccess", $apiData['data']['langType']);
            $this->apiResponse['totalPages'] = ceil($totalData / $limit) . "";
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification(($offset > 0 ? 'allcatchedUp' : "transactionListNotFound"), $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function cancelUserAppointment_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role != '2') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotPatient", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['userAppointmentId']) || empty($apiData['data']['userAppointmentId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userAppointmentIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $appointmentData = $this->User_Appointment->get(['id'=>$apiData['data']['userAppointmentId'],'status'=>[1,2],'userId'=>$user->id,'getAvailabilityData'=>true],true);

        if(empty($appointmentData)) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if ($appointmentData->status == 2) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("cancelledAlready", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $doctorData = $this->User->get(['id'=>$appointmentData->doctorId,'status'=>1,'role'=>3],true);
        if(empty($doctorData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("doctorDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
        $today_date = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
        $today_date->setTimezone(new DateTimeZone($myUserTimeZone));

        $match_date = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
        $match_date->setTimezone(new DateTimeZone($myUserTimeZone));
        $match_date->setTimestamp($appointmentData->appointmentDateTime);
        $interval = $today_date->diff($match_date);        
        if($interval->h<=24  && $interval->days=='0'){
            $currentTraction=$this->User_Transaction->get(['userId'=>$user->id,'appointmentId'=>$apiData['data']['userAppointmentId'],'status'=>4],true);
            if(!empty($currentTraction)){        
                $this->load->library('stripe',array('type'=>'1'));
                $response = $this->stripe->confirmCharge($currentTraction->stripeTransactionId,($appointmentData->price/2)*100);
                error_log("\n\n -------------------------------------" . date('c'). " \n Request => ".json_encode($currentTraction) . " \n Response => ".json_encode($response,true), 3, FCPATH.'worker/chargefromhold-'.date('d-m-Y').'.txt');
                $transactionData = array();
                $transactionData['status'] = 1;
                $transactionData['amount'] = ($appointmentData->price/2);               
                $transactionData['payType'] = 4;
                $this->User_Transaction->setData($transactionData,$currentTraction->id);
                
                
                $amount = (($appointmentData->price/2) * 6 /100);
                $amount = ($appointmentData->price/2) - $amount;
                
                // For doctor wallet transaction record
                $transactionData = [];
                $transactionData['userId'] = $appointmentData->doctorId;
                $transactionData['appointmentId'] = $currentTraction->appointmentId;
                $transactionData['availabilityId'] = $currentTraction->availabilityId;
                $transactionData['amount'] = $amount;
                $transactionData['type'] = 1; // Credit amount
                $transactionData['payType'] = 5; // Add 50% money in wallet by user cancel appoinment
                $transactionData['tranType'] = 1; //Wallet Transaction
                $tranId = $this->User_Transaction->setData($transactionData);
        
                // Send notification doctor to add money in wallet
                // Set notification 
                $notiData = [];
                $notiData['send_from'] = $user->id;
                $notiData['send_to'] = $appointmentData->doctorId;
                $notiData['model_id'] = (int)$currentTraction->appointmentId;
                $notiData['amount'] = '$'.number_format($amount, 2);
                $this->Common_Model->backroundCall('addMoneyInYourWalletForCancelAppointment', $notiData);
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
        }else{
            $currentTraction=$this->User_Transaction->get(['userId'=>$user->id,'appointmentId'=>$apiData['data']['userAppointmentId'],'status'=>4],true);
            if(!empty($currentTraction)){        
                $this->load->library('stripe',array('type'=>'1'));
                $response = $this->stripe->cancelCharge($currentTraction->stripeTransactionId);     
                error_log("\n\n -------------------------------------" . date('c'). " \n Request => ".json_encode($currentTraction) . " \n Response => ".json_encode($response,true), 3, FCPATH.'worker/chargefromhold-'.date('d-m-Y').'.txt');
                $transactionData = array();
                $transactionData['status'] = 2;
                $transactionData['payType'] = 4;
                $this->User_Transaction->setData($transactionData,$currentTraction->id);
            }
        }
        $this->User_Appointment->setData([
            'status' => 2,
            'cancelreason' => 1
        ], $appointmentData->id);
        $this->User_Availability->setData(['isBooked'=>0,'status' => 2],$appointmentData->userAvailabilityId);

        // Send notification user to doctor for Cancel appointment
        // Set notification 
        $notiData = [];
        $notiData['send_from']  = $appointmentData->userId; // user id
        $notiData['send_to']    = $appointmentData->doctorId; // doctor id
        $notiData['model_id']   = (int)$appointmentData->id;
        $this->Common_Model->backroundCall('cancelledUserAppointmentByUser', $notiData);
        // ./ Set notification

        // Send notification user[self] notify for Cancel appointment
        // Set notification 
        $notiData = [];
        $notiData['send_from'] = $user->id;
        $notiData['send_to'] = $user->id; //[self]
        $notiData['model_id'] = (int)$appointmentData->id;
        $notiData['userId'] = $appointmentData->doctorId;
        $this->Common_Model->backroundCall('cancelledUserAppointmentAsUser', $notiData);
        // ./ Set notification
        
        $this->apiResponse['status'] = "1";
        $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentCancelledSuccess", $apiData['data']['langType']);

        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function rescheduleUserAppointment_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role  != '2') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotPatient", $apiData['data']['langType']);
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

        $availabilityData = $this->User_Availability->get([
            // 'userId'                => $user->id,
            'getFutureAvailability' => true,
            'isBooked'              => 0,
            'id'                    => $apiData['data']['userAvailabilityId'],
            'status'                => 1,
        ], true);
        if (empty($availabilityData)) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("availabilityDataNotAvailable", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $appointmentData = $this->User_Appointment->get([
            'userId'        => $user->id,
            'id'            => $apiData['data']['userAppointmentId'],
            'apiResponse'   => true,
            'status'        => 1,
        ], true);
        if (empty($appointmentData)) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $authenticationCode = $this->Common->random_string(4);
        $appointmentBookId = $this->User_Appointment->setData([
            'userAvailabilityId'    => $apiData['data']['userAvailabilityId'],
            'authenticationCode'    => $authenticationCode,
        ], $appointmentData->userAppointmentId);
        if (!empty($appointmentBookId)) {
            $this->User_Availability->setData(['isBooked'=>0],$appointmentData->userAvailabilityId);
            $this->User_Availability->setData([
                'isBooked'  => 1,
            ], $apiData['data']['userAvailabilityId']);

            // Send notification user to doctor for reschedule appointment
            // Set notification 
            $notiData = [];
            $notiData['send_from']  = $user->id;
            $notiData['send_to']    = $appointmentData->doctorId;
            $notiData['model_id']   = (int)$appointmentBookId;
            $this->Common_Model->backroundCall('rescheduleUserAppointmentByUser', $notiData);
            // ./ Set notification

            // Send notification user[self] notify for reschedule appointment
            // Set notification 
            $notiData = [];
            $notiData['send_from']  = $user->id;
            $notiData['send_to']    = $user->id;
            $notiData['model_id']   = (int)$appointmentBookId;
            $notiData['userId']     = $appointmentData->doctorId; //self id.
            $this->Common_Model->backroundCall('rescheduleUserAppointmentAsUser', $notiData);
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

        if ($user->role  != '2') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotAUser", $apiData['data']['langType']);
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

        /*$availabilityData = $this->User_Availability->get([
            // 'userId'                => $user->id,
            'getFutureAvailability' => true,
            'isBooked'              => 0,
            'id'                    => $apiData['data']['userAvailabilityId'],
            'status'                => 1,
        ], true);

        if (empty($availabilityData)) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("availabilityDataNotAvailable", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }*/


        $appointmentData = $this->User_Appointment->get([
            'userId'        => $user->id,
            'id'            => $apiData['data']['userAppointmentId'],
            'apiResponse'   => true,
            'status'        => 1,
        ], true);

        if (empty($appointmentData)) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
        $startdatetime = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
        $startdatetime->setTimezone(new DateTimeZone($myUserTimeZone));
        $startdatetime->setTimestamp($apiData['data']['startDateTime']);

        $enddatetime = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
        $enddatetime->setTimezone(new DateTimeZone($myUserTimeZone));
        $enddatetime->setTimestamp($apiData['data']['endDateTime']);

        $doctorAvailabilityId = $this->User_Availability->setData(['userId'=>$appointmentData->doctorId,'dateTime'=>$startdatetime->format('U'),'endDateTime'=>$enddatetime->format('U'),'timing'=>$apiData['data']['timeRange'],'status'=>1]);
        $availabilityData = $this->User_Availability->get(['userId'=>$appointmentData->doctorId,'isBooked'=>0,'id'=>$doctorAvailabilityId,'status'=>1],true);
        if (empty($availabilityData)) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("availabilityDataNotAvailable", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $authenticationCode = $this->Common->random_string(4);
        $appointmentBookId = $this->User_Appointment->setData([
            'userAvailabilityId'    => $availabilityData->id,
            'authenticationCode'    => $authenticationCode,
        ], $appointmentData->userAppointmentId);
        if (!empty($appointmentBookId)) {
            $this->User_Availability->setData(['isBooked'=>0,'status'=>2],$appointmentData->userAvailabilityId);
            $this->User_Availability->setData([
                'isBooked'  => 1,
            ], $availabilityData->id);

            // Send notification user to doctor for reschedule appointment
            // Set notification 
            $notiData = [];
            $notiData['send_from']  = $user->id;
            $notiData['send_to']    = $appointmentData->doctorId;
            $notiData['model_id']   = (int)$appointmentBookId;
            $this->Common_Model->backroundCall('rescheduleUserAppointmentByUser', $notiData);
            // ./ Set notification

            // Send notification user[self] notify for reschedule appointment
            // Set notification 
            $notiData = [];
            $notiData['send_from']  = $user->id;
            $notiData['send_to']    = $user->id;
            $notiData['model_id']   = (int)$appointmentBookId;
            $notiData['userId']     = $appointmentData->doctorId; //self id.
            $this->Common_Model->backroundCall('rescheduleUserAppointmentAsUser', $notiData);
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

    public function getDiscountCouponList_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role != '2') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotAUser", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $page_number = (isset($apiData['data']['page']) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : '';
        $limit = (isset($apiData['data']['limit']) && $apiData['data']['limit'] != '') ? $apiData['data']['limit'] : 10;
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
        $data['status'] = 1;
        $data['checkValidDate'] = true;
        $totalData = $this->Discount_Coupon_Model->get($data,false,true);
        $data['apiResponse'] = true;
        $data['limit'] = $limit;
        $data['offset'] = $offset;
        $response = $this->Discount_Coupon_Model->get($data);
        $referralCoupon = [];
        $referraldata = $this->User_Referral_Earning_Model->get(['userId'=>$user->id,'status'=>1,'orderstate'=>'ASC','orderby'=>'id'],true);
        if(!empty($referraldata)){
            if($referraldata->amount == "5"){
                $referralCoupon['name'] = 'Referral Earning';
                $referralCoupon['promocode'] = 'ReferralEarning5Off';
                $referralCoupon['value'] = '5';
                $referralCoupon['type'] = '1';
                $referralCoupon['desc'] = '';
            }
        }

        if (!empty($response)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("getDiscountCouponSuccess", $apiData['data']['langType']);
            $this->apiResponse['totalPages'] = ceil($totalData / $limit) . "";
            $this->apiResponse['data'] = $response;
            $this->apiResponse['referralCoupon'] = (!empty($referralCoupon) ? $referralCoupon : "");
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification(($offset > 0 ? 'allcatchedUp' : "discountCouponNotFound"), $apiData['data']['langType']);
            $this->apiResponse['referralCoupon'] = (!empty($referralCoupon) ? $referralCoupon : "");
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function verifyDiscountCoupon_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role != '2') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotAUser", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['couponCode']) || empty($apiData['data']['couponCode'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("couponCodeRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if($apiData['data']['couponCode'] == "ReferralEarning5Off"){
            $codeData = $this->User_Referral_Earning_Model->get(['userId'=>$user->id,'status'=>1,'orderstate'=>'ASC','orderby'=>'id'],true);
        }else{
            $data = array();
            $data['promocode'] = $apiData['data']['couponCode'];
            $data['status'] = 1;
            $data['checkValidDate'] = true;
            $codeData = $this->Discount_Coupon_Model->get($data,true);
        }

        if (!empty($codeData)) {
            $response = array();
            if($apiData['data']['couponCode'] == "ReferralEarning5Off"){
                $response['type'] = 1;
                $response['value'] = 5;
            }else{
                $response['type'] = $codeData->type;
                $response['value'] = $codeData->value;
                if($codeData->useType == "2"){
                    $existUsedPromocode = $this->User_Appointment->get(['userId'=>$user->id,'couponCode'=>$codeData->promocode,'discountCouponId'=>$codeData->id],true);
                    if(!empty($existUsedPromocode)){
                        $this->apiResponse['status'] = "0";
                        $this->apiResponse['message'] = $this->Common->GetNotification("youAlreadyUsedThisCouponCode", $apiData['data']['langType']);
                        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                    }
                }
            }
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("couponCodeValid", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common->GetNotification("couponCodeInValid", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    
    public function verifyGestDiscountCoupon_post() {
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        if (!isset($apiData['data']['couponCode']) || empty($apiData['data']['couponCode'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("couponCodeRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $data = array();
        $data['promocode'] = $apiData['data']['couponCode'];
        $data['status'] = 1;
        $data['checkValidDate'] = true;
        $codeData = $this->Discount_Coupon_Model->get($data,true);

        if (!empty($codeData)) {
            $response =  [
                'type' => $codeData->type,
                'value' => $codeData->value
            ];
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("couponCodeValid", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
        }
        else {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common->GetNotification("couponCodeInValid", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }


    public function getQuickAppointment_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if (!isset($apiData['data']['professionId']) || empty($apiData['data']['professionId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("professionIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['appointmentType']) || empty($apiData['data']['appointmentType'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentTypeRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $data = array();
        $data['status'] = 1;
        $data['role'] = 3;
        $data['getProfessionWiseData'] = $apiData['data']['professionId'];
        $data['apiResponse'] = true;
        $data['checkAvailibilitySetting'] = true;
        $data['getNearestDoctor'] = true;
        $data['lat'] = (isset($apiData['data']['latitude']) && !empty($apiData['data']['latitude']) ? $apiData['data']['latitude'] : $user->latitude);
        $data['long'] = (isset($apiData['data']['longitude']) && !empty($apiData['data']['longitude']) ? $apiData['data']['longitude'] : $user->longitude);
        if($apiData['data']['appointmentType'] == '1'){
            $data['getisvirtual'] = true;
        }elseif($apiData['data']['appointmentType'] == '2'){
            $data['getismobile'] = true;
        }elseif($apiData['data']['appointmentType'] == '3'){
            $data['getisonsite'] = true;
        }
        $nearDoctor = $this->User->get($data,true);
       
        if(empty($nearDoctor)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("nearDoctorNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
        $availabilityData =  $this->User_Availability->get(['apiResponse'=>true,'availabilityDateTimeFormat'=>$myUserTimeZone,'getFutureAvailability'=>true,'isBooked'=>0,'userId'=>$nearDoctor->userId,'status'=>1,'orderby'=>'dateTime','orderstate'=>'ASC'],true);

        $data = array();
        $data['id'] = $nearDoctor->userId;
        $data['apiResponse'] = true;
        $data['status'] = 1;
        $data['role'] = 3;
        $data['getProfessionData'] = true;
        $data['getRatingAverageData'] = true;
        $data['getFutureFirstAvailability'] = true;
        $data['checkDoctorAddedInFavourite'] = $user->id;
        $response = $this->User->get($data,true);

        if(!empty($response) && !empty($availabilityData)){
            $availabilityData->dayAndDate = $this->Common_Model->getDayAndDateName($availabilityData->dateTime,$myUserTimeZone);
            $response->nextAvailable = $this->Common_Model->checkDateText($response->nextAvailable,$myUserTimeZone);
            $response->preferredLanguage = $this->User_Language->get(['apiResponse'=>true,'userId'=>$response->userId,'status'=>1]);
            $response->invitedBy = $this->User_Referral->get(['apiResponse'=>true,'fromUserId'=>$response->userId,'status'=>1],true);
            $response->appointments = $this->User_Appointment->get(['userId'=>$user->id,'doctorId'=>$nearDoctor->userId,'apiResponse'=>true,'status'=>3,'getDoctorData'=>true,'getAvailabilityData'=>true]);
            $freeConsultData = $this->User_Free_Consult->get(['userId'=>$user->id,'doctorId'=>$nearDoctor->userId,'status'=>1],true);
            $response->isFreeConsultAvailable = (empty($freeConsultData) ? 1 : 0);
            $response->availability =  $this->User_Availability->get(['apiResponse'=>true,'groupByDate'=>$myUserTimeZone,'getFutureAvailability'=>true,'isBooked'=>0,'userId'=>$response->userId,'status'=>1,'orderby'=>'dateTime','orderstate'=>'ASC','limit'=>10]);
            if(!empty($response->availability)){
                foreach($response->availability as $value){
                    $datetime = new DateTime();
                    $datetime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));
                    $datetime->setTimestamp($value->dateTime);
                    $value->dayAndDate = $this->Common_Model->getDayAndDateName($value->dateTime,$myUserTimeZone);
                    $value->totalSlotsAvailable = $this->User_Availability->get(['getByDate'=>['date'=>$datetime->format('d-m-Y'),'timeZone'=>$myUserTimeZone],'isBooked'=>0,'userId'=>$response->userId,'status'=>1],false,true);
                    
                }
            }
            $response->isvitualmeeting=1;
            $distance=$this->Common->distance($user->latitude,$user->longitude,$response->latitude,$response->longitude);            
            $adminmile=empty(getenv('MILES')) ?6000:getenv('MILES');
            if(($response->virtualPrice==0 || empty($response->virtualPrice )) && $adminmile < $distance ){
                $response->isvitualmeeting=0;
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("nearDoctorNotFound", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
            
            $response->specialties = $this->User_specialties_Model->get([
                'userId'        => $data['id'],
                'apiResponse'   => true,
                'status'        => 1,
                'getOtherData'  => true,
            ]);

            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("getQuickAppointmentDataSuccess", $apiData['data']['langType']);
            $this->apiResponse['data']['availabilityData'] = $availabilityData;
            $this->apiResponse['data']['doctorData'] = $response;
        }else{
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("quickAppointmentDataNotFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getQuickAppointmentNew_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if (!isset($apiData['data']['professionId']) || empty($apiData['data']['professionId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("professionIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['appointmentType']) || empty($apiData['data']['appointmentType'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentTypeRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $data = array();
        $data['status'] = 1;
        $data['role'] = 3;
        $data['getProfessionWiseData'] = $apiData['data']['professionId'];
        $data['apiResponse'] = true;
        $data['checkAvailibilitySetting'] = true;
        $data['getNearestDoctor'] = true;
        $data['lat'] = (isset($apiData['data']['latitude']) && !empty($apiData['data']['latitude']) ? $apiData['data']['latitude'] : $user->latitude);
        $data['long'] = (isset($apiData['data']['longitude']) && !empty($apiData['data']['longitude']) ? $apiData['data']['longitude'] : $user->longitude);
        if($apiData['data']['appointmentType'] == '1'){
            $data['getisvirtual'] = true;
        }elseif($apiData['data']['appointmentType'] == '2'){
            $data['getismobile'] = true;
        }elseif($apiData['data']['appointmentType'] == '3'){
            $data['getisonsite'] = true;
        }
        $nearDoctor = $this->User->get($data,true);
       
        if(empty($nearDoctor)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("nearDoctorNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
        $availabilityData = $this->Background_Model->updateProviderAvailabilityNew($nearDoctor->userId,$myUserTimeZone,$nearDoctor->timeZone);

        $data = array();
        $data['id'] = $nearDoctor->userId;
        $data['apiResponse'] = true;
        $data['status'] = 1;
        $data['role'] = 3;
        $data['getProfessionData'] = true;
        $data['getRatingAverageData'] = true;
        $data['checkDoctorAddedInFavourite'] = $user->id;
        $response = $this->User->get($data,true);

        if(!empty($response) && !empty($availabilityData)){
            $response->nextAvailable = "";
            if(isset($availabilityData[0]["slotsAvailable"][0]["startTimestamp"]) && !empty($availabilityData[0]["slotsAvailable"][0]["startTimestamp"])){
                $response->nextAvailable = $this->Common_Model->checkDateText($availabilityData[0]["slotsAvailable"][0]["startTimestamp"],$myUserTimeZone);
            }
            $response->preferredLanguage = $this->User_Language->get(['apiResponse'=>true,'userId'=>$response->userId,'status'=>1]);
            $response->invitedBy = $this->User_Referral->get(['apiResponse'=>true,'fromUserId'=>$response->userId,'status'=>1],true);
            $response->appointments = $this->User_Appointment->get(['userId'=>$user->id,'doctorId'=>$nearDoctor->userId,'apiResponse'=>true,'status'=>3,'getDoctorData'=>true,'getAvailabilityData'=>true]);
            $freeConsultData = $this->User_Free_Consult->get(['userId'=>$user->id,'doctorId'=>$nearDoctor->userId,'status'=>1],true);
            $response->isFreeConsultAvailable = (empty($freeConsultData) ? 1 : 0);
            $response->availability =  $availabilityData;
            $response->isvitualmeeting=1;
            $distance=$this->Common->distance($user->latitude,$user->longitude,$response->latitude,$response->longitude);            
            $adminmile=empty(getenv('MILES')) ?6000:getenv('MILES');
            if(($response->virtualPrice==0 || empty($response->virtualPrice )) && $adminmile < $distance ){
                $response->isvitualmeeting=0;
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("nearDoctorNotFound", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
            
            $response->specialties = $this->User_specialties_Model->get([
                'userId'        => $data['id'],
                'apiResponse'   => true,
                'status'        => 1,
                'getOtherData'  => true,
            ]);

            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("getQuickAppointmentDataSuccess", $apiData['data']['langType']);
            $this->apiResponse['data']['availabilityData'] = $availabilityData[0]["slotsAvailable"][0];
            $this->apiResponse['data']['availabilityData']['dayAndDate'] = $this->Common_Model->getDayAndDateName($this->apiResponse['data']['availabilityData']['startTimestamp'],$myUserTimeZone);
            $this->apiResponse['data']['doctorData'] = $response;
        }else{
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("nearDoctorNotFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function setAppointmentPayment_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        if ($user->role  != '2') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotAUser", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if(!isset($apiData['data']['appointmentId']) || empty($apiData['data']['appointmentId'])){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userAppointmentIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['holderName']) || empty($apiData['data']['holderName'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("holderNameRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if (!isset($apiData['data']['number']) || empty($apiData['data']['number'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("cardNumberRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if (!isset($apiData['data']['expMonth']) || empty($apiData['data']['expMonth'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("expMonthRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if (!isset($apiData['data']['expYear']) || empty($apiData['data']['expYear'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("expYearRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if (!isset($apiData['data']['cvv']) || empty($apiData['data']['cvv'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("cvvRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
       
        $this->load->library('stripe',array('type'=>'1'));

        if(empty($user->stripeCustomerId)){
            $customer['description'] = '#UserId:'.$user->id.", Name: ".$user->name.', Is registred from App';
            $customer['email'] = $user->email;
    
            //Customer data
            $customerData = $this->stripe->addCustomer($customer);
            if (isset($customerData['error']) && !empty($customerData['error'])) {
                $customerData['error']['status'] = '0';
                $this->apiResponse = $customerData['error'];
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
            $stripeCustomerId = $customerData['id'];
            $this->User->setData(['stripeCustomerId' => $stripeCustomerId,'stripeCustomerJson'=>json_encode($customerData)], $user->id);
        }else{
            $stripeCustomerId = $user->stripeCustomerId;
        }

        //REGISTRING CARD IN STRIPE
        $stripeCardData['card']['number'] = str_replace(' ','',$apiData['data']['number']);
        $stripeCardData['card']['exp_month'] = $apiData['data']['expMonth'];
        $stripeCardData['card']['exp_year'] = $apiData['data']['expYear'];
        $stripeCardData['card']['cvc'] = $apiData['data']['cvv'];
        $stripeCardData['card']['name'] = $apiData['data']['holderName'];
        $stripeToken = $this->stripe->createToken($stripeCardData);
        // print_r($stripeToken);die;
        //END OF REGISTRING CARD IN STRIPE

        if(empty($stripeToken)){ //FAIL TO GET CARD TOKEN
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("failToGetStripeCardToken", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }elseif(isset($stripeToken['error'])){ //FAIL TO REGISTER CARD IN STRIPE
            $stripeToken['error']['status'] = '0';
            $this->apiResponse = $stripeToken['error'];
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }elseif(!isset($stripeToken["id"]) || $stripeToken["id"]==""){ //FAIL TO GET CARD TOKEN
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("failToGetStripeCardToken", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $responseCreateCard = $this->stripe->createCard(['customer_id' => $stripeCustomerId,'source' => $stripeToken['id'],]);

        if(empty($responseCreateCard)){ //FAIL TO GET CARD TOKEN
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("failToCreateCardInStripe", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }elseif(isset($responseCreateCard['error'])){ //FAIL TO REGISTER CARD IN STRIPE
            $responseCreateCard['error']['status'] = '0';
            $this->apiResponse = $responseCreateCard['error'];
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }elseif(!isset($responseCreateCard["id"]) || $responseCreateCard["id"]==""){ //FAIL TO GET CARD TOKEN
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("failToCreateCardInStripe", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        
        $cardData = array();
        $cardData['userId'] = $user->id;
        $cardData['customerId'] = $responseCreateCard['customer'];
        $cardData['cardId'] = $responseCreateCard['id'];
        $cardData['cardBrand'] = $responseCreateCard['brand'];
        $cardData['last4'] = $responseCreateCard['last4'];
        $cardData['month'] = $responseCreateCard['exp_month'];
        $cardData['year'] = $responseCreateCard['exp_year'];
        $cardData['holderName'] = $apiData['data']['holderName'];
        $cardData['cardJson'] = json_encode($responseCreateCard);
        if (isset($apiData['data']['isDefault']) && $apiData['data']['isDefault'] == 1) {
            $cardData['isDefault'] = 1;
            $this->User_Card->setData(['userIds'=>$user->id,'isDefault'=>0]);
        }
        $cardId = $this->User_Card->setData($cardData);
        
        if(!empty($cardId)){
            $this->User_Appointment->setData(['userCardId'=>$cardId], $apiData['data']['appointmentId']);
            $appointmentData = $this->User_Appointment->get(['id'=>$apiData['data']['appointmentId'],'status'=>1,'userId'=>$user->id, 'getAvailabilityData'=>true, 'paymentStatus'=>'0'],true);
            if(!empty($appointmentData)){
                $userCardData = $this->User_Card->get(['id'=>$cardId], true);
                $this->load->library('stripe',array('type'=>'1'));
                $stripeChargeData['customer'] = $userCardData->customerId;
                $stripeChargeData['source'] = $userCardData->cardId;
                $stripeChargeData['amount'] = $appointmentData->price * 100;
                $stripeChargeData['capture'] = false;        
                $stripeChargeData['description'] ="Book Appointment Payment, userId: #".$user->id.", doctorId: #".$appointmentData->doctorId.", userCardId: #".$userCardData->id." , doctorAvailabilityId: #".$appointmentData->userAvailabilityId.", appointmentType: ".$appointmentData->appointmentType;
                $response = $this->stripe->addCharge($stripeChargeData);
                if(isset($response) && !empty($response)){
                    if(isset($response['error'])){ 
                        $response['error']['status'] = '0';
                        $this->apiResponse = $response['error'];
                        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                    } else if(!isset($response->id) || $response->id==""){ 
                        $this->apiResponse['status'] = "0";
                        $this->apiResponse['message'] = $this->Common_Model->GetNotification("failToBookAppointment", $apiData['data']['langType']);
                        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                    } else{
                        $transactionData = array();
                        $transactionData['userId'] = $appointmentData->userId;
                        $transactionData['userIdTo'] = $appointmentData->doctorId;
                        $transactionData['cardId'] = $cardId;
                        $transactionData['appointmentId'] = $apiData['data']['appointmentId'];
                        $transactionData['availabilityId'] = $appointmentData->userAvailabilityId;
                        $transactionData['stripeTransactionId'] = $response['id'];
                        $transactionData['stripeTranJson'] = json_encode($response);
                        $transactionData['amount'] = $appointmentData->price;
                        $transactionData['type'] = 2; // Debit amount
                        $transactionData['payType'] = 1; // Book Appointment Payment 
                        $transactionData['tranType'] = 2; //Stripe Transaction
                        $transactionData['status'] = 4; 
                        $transactionData['createdDate'] = $response['created'];
                        $transSetData = $this->User_Transaction->setData($transactionData);
                        if(!empty($transSetData)){
                            $transactionDataSet = array();
                            $transactionDataSet['status'] = 1;
                            $this->User_Transaction->setData($transactionDataSet,$transSetData);
                            $this->apiResponse['status'] = "1";
                            $this->apiResponse['message'] = $this->Common_Model->GetNotification("paymentSucess", $apiData['data']['langType']);
                            $this->User_Appointment->setData(['paymentStatus' => '1'], $apiData['data']['appointmentId']);
                        }
                    }
                }
            } else {
                $this->apiResponse['status'] = "0";
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("noAppointmentDataFound", $apiData['data']['langType']);
            }
        }else{
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("failToSaveCard", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);        
    }







    
    public function getDocumentDoctorList_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        if ($user->role  != '2') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotAUser", $apiData['data']['langType']);
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
        $data['userId'] = $user->id;
        $data['apiResponse'] = true;
        $data['getDoctorData'] = true;
        $data['groupByDoctorId'] = true;
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
        } 
        else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification(($offset > 0 ? 'allcatchedUp' : "documentListNotFound"), $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getDoctorAllDocumentList_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role  != '2') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotAUser", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if (!isset($apiData['data']['doctorId']) || empty($apiData['data']['doctorId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("doctorIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $data = array();
        $data['userId'] = $user->id;
        $data['doctorId'] = $apiData['data']['doctorId'];
        $data['apiResponse'] = true;
        $data['getDoctorData'] = true;
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

    
    public function uploadDoctorDocumentWithoutAppointment_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role  != '2') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotAUser", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }        
        if (!isset($apiData['data']['doctorId']) || empty($apiData['data']['doctorId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("doctorIdRequired", $apiData['data']['langType']);
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
            $appointmentData = $this->User_Appointment->get(['id'=>$apiData['data']['userAppointmentId'],'status'=>1,'userId'=>$user->id,'doctorId'=>$apiData['data']['doctorId']],true);
            if(empty($appointmentData)) {
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentDataNotFound", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
            else {
                $appointmentId = $apiData['data']['userAppointmentId'];
            }
        }

        $doctorId = $apiData['data']['doctorId'];
        if (isset($apiData['data']['doctorId']) && !empty($apiData['data']['doctorId'])) {
            #$userData = $this->User->get(['id'=>$apiData['data']['doctorId'], 'status'=>'1'], true);
            $userData = $this->User->get([ 'id'=>$apiData['data']['doctorId'] ], true);
            if(empty($userData)) {
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("doctorIdRequired", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
            else {
                if($userData->status == 1) {
                    $doctorId = $apiData['data']['doctorId'];
                }
                else {
                    $this->apiResponse['message'] = $this->Common_Model->GetNotification("doctorWatingRequest", $apiData['data']['langType']);
                    return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                }
            }
        }

        $data = [];
        $data['userId'] = $user->id;
        $data['userAppointmentId'] = $appointmentId;
        $data['doctorId'] = $apiData['data']['doctorId'];
        $data['documentType'] = $apiData['data']['documentType'];
        $data['documentFileRealName'] = $apiData['data']['documentFileRealName'];
        $data['documentFileName'] = $apiData['data']['documentFileName'];
        $data['uploadedBy'] = 0;

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

    public function getUserAllDocumentList_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role  != '2') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotAUser", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if (!isset($apiData['data']['doctorId']) || empty($apiData['data']['doctorId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("doctorIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $data = array();
        $data['userId'] = $user->id;
        $data['doctorId'] = $apiData['data']['userId'];
        $data['apiResponse'] = true;
        $data['getDoctorData'] = true;
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
        $this->load->model('User_Appointment_Model','User_Appointment');
        $user = $this->checkUserRequest();        
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        $data = array();
        $data['userId'] = $user->id;
        $data['apiResponse'] = true;
        $data['getAvailabilityData'] = true;
        $data['orderAppointmentStartDate'] = true;
        $data['getDoctorData'] = true;
        $data['status'] = 1;
        $data['getFutureAvailability'] = true;
        $data['acceptPlanData'] = true;
        $data['getDoctorGoolgeCalendarData'] = true;

        $response = $this->User_Appointment->get($data);
        $arr = [];
        if (!empty($response)) {
            $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
            foreach($response as $k => $value) {
                if(isset($value->userGToken) && empty($value->userGToken)) {
                    if(
                        !empty($value->doctor_gc_accessToken) && $value->doctor_gc_status == 1 &&
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
                            /* "doctor" => [
                                "name" => $doctorData->name,
                                "title" => "Appointment - ".$user->name,
                                "accessToken" => $new_doctor_accessToken,
                                "refreshToken" => $doctorData->gc_accessToken,
                                "date" => $startdatetime->format('Y-m-d'), //date("Y-m-d", $doctorAvailabilityData->dateTime),
                                "stime" => $startdatetime->format('h:i A'), //date("H:m A", $doctorAvailabilityData->dateTime),
                                "etime" => $enddatetime->format('h:i A'), //$startdatetime->format('U'),
                                "aid" => $value->userAppointmentId
                            ], */
                            "user" => [
                                "name" => $user->name,
                                "title" => "Appointment - ".$value->doctorName,
                                "accessToken" => $new_accessToken,
                                "refreshToken" => $user->gc_accessToken,
                                "date" => $startdatetime->format('Y-m-d'), //date("Y-m-d", $doctorAvailabilityData->dateTime),
                                "stime" => $startdatetime->format('h:i A'), //date("H:m A", $doctorAvailabilityData->dateTime),
                                "etime" => $enddatetime->format('h:i A'), //$startdatetime->format('U'),
                                "aid" => $value->userAppointmentId
                            ] 
                        ];
                        $this->Background_Model->createEventGoogleCalender($data_arr);
                        $arr[] = $data_arr;
                    }
                }
            }
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("getAppointmentsListSuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $arr;
        }
        else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification(($offset > 0 ? 'allcatchedUp' : "appointmentsListNotFound"), $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }




}
