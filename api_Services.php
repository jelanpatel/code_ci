<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'libraries/REST_Controller.php';

class Services extends REST_Controller {

    function __construct() {
        parent::__construct();
        $this->load->model('Common_Model');
        $this->load->model('Background_Model');
        $this->load->model('User_Services_Model','Services');
        $this->load->model('User_Card_Model','User_Card');
        $this->load->model('User_Model','User');
        $this->load->model('User_Availability_Model','User_Availability');
        $this->load->model('User_Referral_Model','User_Referral');
        $this->load->model('Discount_Coupon_Model');
        $this->load->model('User_Referral_Earning_Model');
        $this->load->model('User_Appointment_Model','User_Appointment');
        $this->load->model('User_Transaction_Model','User_Transaction');
        $this->load->model('WebAppProviderSubscription_Model');
    }

    public function setUsersServices_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        if ($user->role != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        
        if(!isset($apiData['data']['name']) || empty($apiData['data']['name'])){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("serviceNameRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if(!isset($apiData['data']['type']) || empty($apiData['data']['type'])){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("serviceTypeRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if(!isset($apiData['data']['duration']) || empty($apiData['data']['duration'])){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("serviceDurationRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        //if(!isset($apiData['data']['price']) || $apiData['data']['price'] < 1){
        if(!isset($apiData['data']['price'])){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("servicePriceRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if(!isset($apiData['data']['description']) || empty($apiData['data']['description'])){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("serviceDescriptionRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if(!isset($apiData['data']['serviceId']) || empty($apiData['data']['serviceId'])){
            $get_sub_list = $this->WebAppProviderSubscription_Model->get(['userId'=> $user->id, 'amount' => '49', 'status' => '1']);
            if(!empty($get_sub_list)){
                $getServiceData = $this->Services->get(['userId'=> $user->id, 'status'=>'1'], false, true);
                if($getServiceData >= 2){
                    $this->apiResponse['status'] = "0";
                    $this->apiResponse['message'] = $this->Common_Model->GetNotification("limitSaveUserService", $apiData['data']['langType']);
                    return $this->response($this->apiResponse, REST_Controller::HTTP_OK);

                }
            }
        }
        
        $data = array();
        $data['userId'] = $user->id;
        $data['name'] = $apiData['data']['name'];
        //$data['type'] = $apiData['data']['type'];
        $data['duration'] = $apiData['data']['duration'];
        //$data['price'] = ($apiData['data']['price'] > 0 ? $apiData['data']['price'] : 0);
        $data['price'] = $apiData['data']['price'];
        $data['description'] = $apiData['data']['description'];
        if(isset($apiData['data']['bufferTimeAfter']) && $apiData['data']['bufferTimeAfter'] != ''){
            $data['bufferTimeAfter'] = $apiData['data']['bufferTimeAfter'];
        }
        if(isset($apiData['data']['bufferTimeBefore']) && $apiData['data']['bufferTimeBefore'] != ''){
            $data['bufferTimeBefore'] = $apiData['data']['bufferTimeBefore'];
        }
        if(isset($apiData['data']['serviceId']) && !empty($apiData['data']['serviceId'])){
            $getServiceData = $this->Services->get(['id'=>$apiData['data']['serviceId'], 'userId'=> $user->id, 'status'=>[0,1]], true);
            if(empty($getServiceData)){
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("wrongServiceId", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            } else {
                if(is_array($apiData['data']['type'])){
                    foreach($apiData['data']['type'] as $value){
                        $data['type'] = $value;
                    }
                }else{
                    $data['type'] = $apiData['data']['type'];
                }
                $set = $this->Services->setData($data, $apiData['data']['serviceId']);
            }
        } else {
            if(is_array($apiData['data']['type'])){
                foreach($apiData['data']['type'] as $value){
                    $data['type'] = $value;
                    $set = $this->Services->setData($data);
                }
            }else{
                $data['type'] = $apiData['data']['type'];
                $set = $this->Services->setData($data);
            }
        }

        if (!empty($set)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userServiceSavedSuccess", $apiData['data']['langType']);
        } else{
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("failToSaveUserService", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function serviceEnableDisable_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        if ($user->role != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if(!isset($apiData['data']['serviceId']) || empty($apiData['data']['serviceId'])){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("serviceIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $getServiceData = $this->Services->get(['id'=>$apiData['data']['serviceId'], 'userId'=>$user->id, 'status'=>[0,1]], true);
        if(!empty($getServiceData)){
            if($getServiceData->status == 1){
                $set = $this->Services->setData(['status'=>0],$apiData['data']['serviceId']);
                $successMsg = "addToEnableSuccess";
                $failMsg = "failToAddService";
            }else{
                $set = $this->Services->setData(['status'=>1],$apiData['data']['serviceId']);
                $successMsg = "removeToDisableSuccess";
                $failMsg = "failToRemoveService";
            }
        } else {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("wrongServiceId", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            $set = $this->Services->setData(['status'=>$apiData['data']['enabledDisable']], $apiData['data']['serviceId']);
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

    public function serviceList_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        $page_number = (isset($apiData['data']['page']) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : 1;
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
        if ($user->role != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        
        $data['apisearch'] = (isset($apiData['data']['search']) ? $apiData['data']['search'] : "");
        $totalData = $this->Services->get(['userId'=>$user->id, 'status'=>[0,1], 'apisearch' => $data['apisearch'] ],false,true);
        //$totalData = $this->Services->get(['userId'=>$user->id, 'status'=>[0,1]],false,true);
        $data['limit'] = $limit;
        $data['offset'] = $offset;
        $data['userId'] = $user->id;
        $data['status'] = [0,1];
        $response = $this->Services->get($data, false);
        if (!empty($response)) {
            foreach($response as $value){
                $value->price = number_format($value->price,2);
            }
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("servicesListSucess", $apiData['data']['langType']);
            $this->apiResponse['totalPages'] = ceil($totalData / $limit) . "";
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("noUserServicesDataFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function serviceDetail_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        if ($user->role != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if(!isset($apiData['data']['serviceId']) || empty($apiData['data']['serviceId'])){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("serviceIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        $response = $this->Services->get(['id'=>$apiData['data']['serviceId'], 'userId'=>$user->id, 'status'=>[0,1]], true);

        if (!empty($response)) {
            if($response->price > 0){
                $response->price = number_format($response->price,2);
            }
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("servicesListSucess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("noUserServicesDataFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function serviceDelete_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        if ($user->role != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if(!isset($apiData['data']['serviceId']) || empty($apiData['data']['serviceId'])){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("serviceIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        $getServiceData = $this->Services->get(['id'=>$apiData['data']['serviceId'], 'userId'=>$user->id, 'status'=>[0,1]], true);
        if(empty($getServiceData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("wrongServiceId", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        } else {
            $set = $this->Services->setData(['status'=> '2'], $apiData['data']['serviceId']);
        }
        if (!empty($set)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userServiceDeleteSuccess", $apiData['data']['langType']);
        } else {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("failToSaveUserService", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getDoctorServiceAvailability_post() {
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        if(isset($apiData['data']['token']) && !empty($apiData['data']['token'])){
            $user = $this->checkUserRequest();
        } else {
            $this->checkGuestUserRequest();               
            $user =(object) array();  
            if(isset($apiData['data']['userTimezone']) && !empty($apiData['data']['userTimezone'])){
                $user->timeZone = $apiData['data']['userTimezone'];            
            }
        }
  
        if (!isset($apiData['data']['doctorId']) || empty($apiData['data']['doctorId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("doctorIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
  
        if (!isset($apiData['data']['serviceId']) || empty($apiData['data']['serviceId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("serviceIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $getDoctor = $this->User->get(['apiResponse'=>true,'getProfessionData'=>true,'getRatingAverageData'=>true,'id'=>$apiData['data']['doctorId'],'status'=>1],true);
        if(empty($getDoctor)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("doctorDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if(isset($getDoctor->ispresenceforsearch) && $getDoctor->ispresenceforsearch != 1) {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("providerSearchNotAvailable", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $serviceData = $this->Services->get(['id'=>$apiData['data']['serviceId'],'status'=>1],true);
        if(empty($serviceData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("serviceDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
        $doctorTimeZone = (!empty($getDoctor->timeZone) ? $getDoctor->timeZone : getenv('SYSTEMTIMEZON'));
        
        #$availability = $this->Background_Model->updateProviderServicesAvailabilityNew($getDoctor->userId,$myUserTimeZone,$doctorTimeZone,$serviceData->id);
        $availability = $this->Background_Model->updateProviderServicesAvailabilityNewSlot($getDoctor->userId,$myUserTimeZone,$doctorTimeZone,$serviceData->id);
        if (!empty($availability)) {
            $response['doctorData'] = $getDoctor;
            $response['availabilityData'] = $availability;
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("getHealthProfessionalServiceAvailabilitySuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("healthProfessionalServiceAvailabilityNotFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function bookServiceAppointment_post() {
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

        if (!isset($apiData['data']['serviceId']) || empty($apiData['data']['serviceId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("serviceIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['userCardId']) || empty($apiData['data']['userCardId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userCardIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $doctorData = $this->User->get(['id'=>$apiData['data']['doctorId'],'status'=>1,'role'=>3],true);
        if(empty($doctorData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("doctorDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $serviceData = $this->Services->get(['id'=>$apiData['data']['serviceId'],'userId'=>$apiData['data']['doctorId'],'status'=>1],true);
        if(empty($serviceData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("serviceDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if($serviceData->type != 1){
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
        }

        $userCardData = $this->User_Card->get(['userId'=>$user->id,'id'=>$apiData['data']['userCardId'],'status'=>1],true);
        if(empty($userCardData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userCardDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        
        $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
        $startdatetime = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
        $startdatetime->setTimezone(new DateTimeZone($myUserTimeZone));
        $startdatetime->setTimestamp($apiData['data']['startDateTime']);

        $enddatetime = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
        $enddatetime->setTimezone(new DateTimeZone($myUserTimeZone));
        $enddatetime->setTimestamp($apiData['data']['endDateTime']);
        
        $existAvailabilityData = $this->User_Availability->get(['userId'=>$doctorData->id,'checkBookedSlot'=>['startDateTime'=>($apiData['data']['startDateTime'] - ($serviceData->bufferTimeBefore * 60)),'endDateTime'=>($apiData['data']['endDateTime'] + ($serviceData->bufferTimeAfter * 60))],'isBooked'=>1,'status'=>1],true);
        if(!empty($existAvailabilityData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("selectedSlotAlreadyBooked", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $doctorAvailabilityId = $this->User_Availability->setData(['userId'=>$doctorData->id,'dateTime'=>$startdatetime->format('U'),'endDateTime'=>$enddatetime->format('U'),'timing'=>$serviceData->duration,'status'=>1]);
        $doctorAvailabilityData = $this->User_Availability->get(['userId'=>$doctorData->id,'isBooked'=>0,'id'=>$doctorAvailabilityId,'status'=>1],true);
        if(empty($doctorAvailabilityData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("doctorAvailabilityDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        
        $amount = round($serviceData->price,2);
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

        #############################################################################
        $new_doctor_accessToken = "";
        $new_user_accessToken = "";
        if(
            !empty($user->gc_accessToken) && $user->gc_status == "1" &&
            !empty($doctorData->gc_accessToken) && $doctorData->gc_status == "1"
        ) {
            require_once('application/controllers/google-calendar-api.php');
            $site_url = current_url();
            $client_id = getenv('GOOGLE_KEY');
            $client_secret = getenv('GOOGLE_SECRET');
            $rurl = base_url()."google/calendar";
            $capi = new GoogleCalendarApi();
            $new_doctor_accessToken = $capi->RefreshAccessToken($client_id, $rurl, $client_secret, $doctorData->gc_accessToken);
            $new_user_accessToken = $capi->RefreshAccessToken($client_id, $rurl, $client_secret, $user->gc_accessToken);
        }
        #echo $new_doctor_accessToken."<br><br><br>".$new_user_accessToken; exit;
        #############################################################################
        
        $appointmentData = array();
        $appointmentData['userId'] = $user->id;
        $appointmentData['doctorId'] = $doctorData->id;
        $appointmentData['userAvailabilityId'] = $doctorAvailabilityData->id;
        $appointmentData['userServiceId'] = $serviceData->id;
        $appointmentData['userCardId'] = $userCardData->id;
        $appointmentData['appointmentType'] = $serviceData->type;
        $appointmentData['couponCode'] = $couponCode;
        $appointmentData['discountCouponId'] = $discountCouponId;
        $appointmentData['discountPrice'] = $discountPrice;
        $appointmentData['paymentStatus'] = "0";
        $appointmentData['isServices'] = 1;
        if($serviceData->type != 1){
            $appointmentData['location'] = $apiData['data']['location'];
            $appointmentData['latitude'] = $apiData['data']['latitude'];
            $appointmentData['longitude'] = $apiData['data']['longitude'];
        }
        $appointmentData['price'] = $amount;
        $appointmentData['authenticationCode'] = $this->Common_Model->random_string(4);
        $appointmentBookId = $this->User_Appointment->setData($appointmentData);

        //if  72  then olny stripe code and transcation 
        $currentDate = date('d-m-Y h:i');
        $hourdiff = round(($doctorAvailabilityData->dateTime - strtotime($currentDate))/3600, 1);
        if(empty($amount) || $amount ==0 || $amount != "") {
            // For user transaction record
            $transactionData = array();
            $transactionData['userId'] = $user->id;
            $transactionData['userIdTo'] = $doctorData->id;
            $transactionData['cardId'] = $userCardData->id;
            $transactionData['appointmentId'] = $appointmentBookId;
            $transactionData['availabilityId'] = $doctorAvailabilityData->id;
            $transactionData['stripeTransactionId'] = "";
            $transactionData['stripeTranJson'] = "";
            $transactionData['amount'] = $amount;
            $transactionData['type'] = 2; // Debit amount
            $transactionData['payType'] = 9; // Service Booking Payment 
            $transactionData['tranType'] = 2; //Stripe Transaction
            $transactionData['status'] = 1 ; 
            $transactionData['createdDate'] = time();
            $this->User_Transaction->setData($transactionData);
            $appointmentBookId = $this->User_Appointment->setData(['paymentStatus'=>1],$appointmentBookId);
        }
        else if($hourdiff <= 72){ 
            $this->load->library('stripe',array('type'=>'1'));
            $stripeChargeData['customer'] = $userCardData->customerId;
            $stripeChargeData['source'] = $userCardData->cardId;
            $stripeChargeData['amount'] = $amount * 100;
            $stripeChargeData['capture'] = false;        
            $stripeChargeData['description'] ="Services Booking Payment, userId: #".$user->id.", doctorId: #".$doctorData->id.", userCardId: #".$userCardData->id." , serviceType: ".$serviceData->type.", appointmentId:".$appointmentBookId;
            $response = $this->stripe->addCharge($stripeChargeData);

            error_log("\n\n -------------------------------------" . date('c'). " \n Request => ".json_encode($stripeChargeData) . " \n Response => ".json_encode($response,true), 3, FCPATH.'worker/bookServicePayment-'.date('d-m-Y').'.txt');
            if(isset($response) && !empty($response)){
                if(isset($response['error'])){ 
                    $response['error']['status'] = '0';
                    $this->apiResponse = $response['error'];
                    return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                }elseif(!isset($response->id) || $response->id==""){ 
                    $this->apiResponse['status'] = "0";
                    $this->apiResponse['message'] = $this->Common_Model->GetNotification("failToBookService", $apiData['data']['langType']);
                    return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                }else{
                    // Send Mail and SMS in Authentication code
                    $notiData = [];
                    $notiData['userId'] = $user->id;
                    $notiData['authenticationCode'] = $appointmentData['authenticationCode'];
                    $this->Common_Model->backroundCall('sendMailAndSMSInServiceAuthenticationCodeForUser', $notiData);
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
                    $transactionData['payType'] = 9; // Service Booking Payment 
                    $transactionData['tranType'] = 2; //Stripe Transaction
                    $transactionData['status'] = 4 ; 
                    $transactionData['createdDate'] = $response['created'];
                    $this->User_Transaction->setData($transactionData);
                    $appointmentBookId = $this->User_Appointment->setData(['paymentStatus'=>1],$appointmentBookId);

                 
                    // ./ Set notification

                    // Send notification for transaction success
                    // Set notification 
                    $notiData = [];
                    $notiData['send_from'] = $user->id;
                    $notiData['send_to'] = $user->id;
                    $notiData['model_id'] = (int)$appointmentBookId;
                    $notiData['amount'] = '$'.number_format($amount,2);
                    $this->Common_Model->backroundCall('transactionSuccessForScheduleService', $notiData);
                   
                    // ./ Set notification
                    
                    /*if(!empty($referraldata)){
                        $this->User_Referral_Earning_Model->setData(['status'=>2],$referraldata->id);
                    }*/

                    // $this->User->setData(['walletAmount'=>($doctorData->walletAmount + $amount)],$doctorData->id);
                  
                }
            }else{
                $this->apiResponse['status'] = "0";
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("failToBookService", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
        }
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
        $this->Common_Model->backroundCall('scheduleServiceByUser', $notiData);

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
        $this->Common_Model->backroundCall('scheduleServiceForUser', $notiData);

        $this->User_Availability->setData(['isBooked'=>1],$doctorAvailabilityData->id);
        
        #############################################################################
        $data_arr = [
            "doctor" => [
                "name" => $doctorData->name,
                "title" => "Appointment - ".$user->name,
                "accessToken" => $new_doctor_accessToken,
                "refreshToken" => $doctorData->gc_accessToken,
                "date" => $startdatetime->format('Y-m-d'), //date("Y-m-d", $doctorAvailabilityData->dateTime),
                "stime" => $startdatetime->format('h:i A'), //date("H:m A", $doctorAvailabilityData->dateTime),
                "etime" => $enddatetime->format('h:i A'), //$startdatetime->format('U'),
                "aid" => $appointmentBookId
            ],
            "user" => [
                "name" => $user->name,
                "title" => "Appointment - ".$doctorData->name,
                "accessToken" => $new_user_accessToken,
                "refreshToken" => $user->gc_accessToken,
                "date" => $startdatetime->format('Y-m-d'), //date("Y-m-d", $doctorAvailabilityData->dateTime),
                "stime" => $startdatetime->format('h:i A'), //date("H:m A", $doctorAvailabilityData->dateTime),
                "etime" => $enddatetime->format('h:i A'), //$startdatetime->format('U'),
                "aid" => $appointmentBookId
            ]
        ];
        //echo "<pre>"; print_r($data_arr); exit;
        if(
            !empty($user->gc_accessToken) && $user->gc_status == "1" &&
            !empty($doctorData->gc_accessToken) && $doctorData->gc_status == "1"
        ) {
            $this->Background_Model->createEventGoogleCalender($data_arr);
        }
        #############################################################################


        $this->apiResponse['status'] = "1";
        $this->apiResponse['message'] = $this->Common_Model->GetNotification("serviceBookedSuccess", $apiData['data']['langType']);
        $this->apiResponse['data']['appointmentBookId'] = $appointmentBookId;
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }
}
