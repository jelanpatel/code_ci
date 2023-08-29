<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'libraries/REST_Controller.php';

class Users extends REST_Controller {

    function __construct() {
        parent::__construct();
        $this->load->library('upload');
        $this->load->model('Common_Model','Common');
        $this->load->model('Background_Model');
        $this->load->model('Users_Model', 'User');
        $this->load->model('User_Language_Model','User_Language');
        $this->load->model('User_Availability_Model','User_Availability');
        $this->load->model('User_Professional_Model','User_Professional');
        $this->load->model('User_Profession_Model','User_Profession');
        $this->load->model('Medical_History_Personal_Model','Medical_History_Personal');
        $this->load->model('User_Medications_Model','User_Medications');
        $this->load->model('Medications_Model', 'Medications');
        $this->load->model('User_Allergies_Model', 'User_Allergies');
        $this->load->model('Allergies_Type_Model', 'Allergies_Type');
        $this->load->model('User_Health_Issues_Model', 'User_Health_Issues');
        $this->load->model('Health_Issues_Model', 'Health_Issues');
        $this->load->model('User_Injuries_Model', 'User_Injuries');
        $this->load->model('Injuries_Model', 'Injuries');
        $this->load->model('User_Surgeries_Model', 'User_Surgeries');
        $this->load->model('Surgeries_Model', 'Surgeries');
        $this->load->model('Medical_History_Social_Model', 'Medical_History_Social');
        $this->load->model('Illness_Model', 'Illness');
        $this->load->model('User_Family_Illness_Model', 'User_Family_Illness');
        $this->load->model('User_Card_Model','User_Card');
        $this->load->model('User_specialties_Model');
        $this->load->model('Specialties_Model');
        $this->load->model('User_Availability_Setting_Model');
        $this->load->model('User_Availability_Offtime_Model');
        $this->load->model('User_Location_Model');
        $this->load->model('WebAppProviderSubscription_Model');
        $this->load->model('User_Appointment_Model','User_Appointment');
    }

    public function getUserInfo_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        $data = $this->User->userData($user->id, false);
        if (!empty($data)) {
            // $data->birthdate1 = date("d-m-Y", strtotime($data->birthdateOriginal));
            $data->birthdate =  ($data->birthdateOriginal);
            $existSubData = $this->WebAppProviderSubscription_Model->get(['userId' => $user->id,'status'=>1],true);
            $data->isSubsciption = (!empty($existSubData) ? 1 : 0);
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("getUserinfoSuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $data;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common->GetNotification("userInfoNotFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function saveUserProfile_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        $setData = array();
        if(isset($apiData['data']['name']) && !empty($apiData['data']['name'])){
            $setData['name'] = $apiData['data']['name'];
        }
        if(isset($apiData['data']['image']) && !empty($apiData['data']['image'])){
            $setData['image'] = $apiData['data']['image'];
        }

        if(isset($apiData['data']['birthdate']) && !empty($apiData['data']['birthdate'])){
            $setData['birthdate'] = date('Y-m-d', strtotime($apiData['data']['birthdate']));
        }
        if(isset($apiData['data']['gender']) && in_array($apiData['data']['gender'],array(1,2,3,4))){
            $setData['gender'] = $apiData['data']['gender'];
        }
        if(isset($apiData['data']['emergencyContact']) && !empty($apiData['data']['emergencyContact'])){
            $setData['emergencyContact'] = $apiData['data']['emergencyContact'];
        }        
        if(isset($apiData['data']['contactPersonName']) && !empty($apiData['data']['contactPersonName'])){
            $setData['contactPersonName'] = $apiData['data']['contactPersonName'];
        }
        if(isset($apiData['data']['bio']) && !empty($apiData['data']['bio'])){
            $setData['bio'] = $apiData['data']['bio'];
        }

        if(isset($apiData['data']['preferredLanguage']) && !empty($apiData['data']['preferredLanguage']) && is_array($apiData['data']['preferredLanguage'])){
            $this->User_Language->setData(['userIds'=>$user->id,'status'=>2]);
            foreach($apiData['data']['preferredLanguage'] as $value){
                $existUserLangData = $this->User_Language->get(['userId'=>$user->id,'languageId'=>$value],true);
                if(!empty($existUserLangData)){
                    $this->User_Language->setData(['userId'=>$user->id,'languageId'=>$value,'status'=>1],$existUserLangData->id);
                }else{
                    $this->User_Language->setData(['userId'=>$user->id,'languageId'=>$value]);
                }
            }
        }

        if(isset($apiData['data']['latitude']) && !empty($apiData['data']['latitude'])){
            $setData['latitude'] = $apiData['data']['latitude'];
        }
        if(isset($apiData['data']['longitude']) && !empty($apiData['data']['longitude'])){
            $setData['longitude'] = $apiData['data']['longitude'];
        }
        if(isset($apiData['data']['address']) && !empty($apiData['data']['address'])){
            $setData['address'] = $apiData['data']['address'];
        }
        if(isset($apiData['data']['unitNo']) ) {
            $setData['unitNo'] = $apiData['data']['unitNo'];
        }
        if(isset($apiData['data']['cityName']) && !empty($apiData['data']['cityName'])){
            $setData['cityName'] = $apiData['data']['cityName'];
        }
        if(isset($apiData['data']['stateName']) && !empty($apiData['data']['stateName'])){
            $setData['stateName'] = $apiData['data']['stateName'];
        }
        if(isset($apiData['data']['zipcode']) && !empty($apiData['data']['zipcode'])){
            $setData['zipcode'] = $apiData['data']['zipcode'];
        }

        if (isset($apiData['data']['email']) && !empty($apiData['data']['email'])){
            $emailExist = $this->User->get(['email' => $apiData['data']['email']], true);
            if (!empty($emailExist) && $emailExist->id != $user->id) {
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("emailExist", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
            $setData['email'] = $apiData['data']['email'];
        }

        if (isset($apiData['data']['phone']) && !empty($apiData['data']['phone'])){
        //     $phoneExist = $this->User->get(['phone' => $apiData['data']['phone']], true);
        //     if (!empty($phoneExist) && $phoneExist->id != $user->id) {
        //         $this->apiResponse['message'] = $this->Common_Model->GetNotification("phoneExist", $apiData['data']['langType']);
        //         return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        //     }
            $setData['phone'] = $apiData['data']['phone'];
        }

        if($user->role == 3){
            if(isset($apiData['data']['virtualPrice']) && !empty($apiData['data']['virtualPrice'])){
                $setData['virtualPrice'] = $apiData['data']['virtualPrice'];
            }
            if(isset($apiData['data']['mobilePrice']) && !empty($apiData['data']['mobilePrice'])){
                $setData['mobilePrice'] = $apiData['data']['mobilePrice'];
            }
            if(isset($apiData['data']['onsitePrice']) && !empty($apiData['data']['onsitePrice'])){
                $setData['onsitePrice'] = $apiData['data']['onsitePrice'];
            }
        }
        if(isset($apiData['data']['profileStatus']) && in_array($apiData['data']['profileStatus'],array(1,2))){
            $setData['profileStatus'] = $apiData['data']['profileStatus'];
        }
        if(empty($user->referralCode)){
            $setData['referralCode'] = $user->id.$this->Common->random_string(4);
        }

        $set = $this->User->setData($setData,$user->id);
        if (!empty($set)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("profileSavedSuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $this->User->userData($user->id, false);
        } else {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common->GetNotification("failToSaveProfile", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function saveDoctorAvailability_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        if ($user->role  != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        /* if (!isset($apiData['data']['availability']) || empty($apiData['data']['availability'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("availabilityRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        } */

        $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
        $lastId = "";
        $this->User_Availability->setData(['userIds'=>$user->id,'notbooked'=>true,'status'=>2]);
        if(isset($apiData['data']['availability']) && !empty($apiData['data']['availability'])){
            foreach($apiData['data']['availability'] as $value){
                if(!isset($value['timing']) || !isset($value['date']) || !isset($value['slots'])){
                    continue;
                }
                foreach($value['slots'] as $slot){
                    $startdatetime = new DateTime($value['date'].' '.$slot, new DateTimeZone( $myUserTimeZone ));
                    $startdatetime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));
                    
                    $enddatetime = new DateTime($value['date'].' '.$slot, new DateTimeZone( $myUserTimeZone ));
                    $enddatetime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));
                    $enddatetime->add(new DateInterval('PT'.$value['timing'].'M'));

                    $availabilityData = array();
                    $availabilityData['userId'] = $user->id;
                    $availabilityData['timing'] = $value['timing'];
                    $availabilityData['dateTime'] = $startdatetime->format('U');
                    $availabilityData['endDateTime'] = $enddatetime->format('U');
                    $availabilityData['status'] = 1;
            
                    $existAvailabilityDataData = $this->User_Availability->get(['userId'=>$user->id,'dateTime'=>$startdatetime->format('U')],true);
                    if(!empty($existAvailabilityDataData)){
                        $lastId = $this->User_Availability->setData($availabilityData,$existAvailabilityDataData->id);
                    }else{
                        $lastId = $this->User_Availability->setData($availabilityData);
                    }
                }
            }
            if (!empty($lastId)) {
                if($user->profileStatus == 0){
                    $this->User->setData(['profileStatus'=>1],$user->id);
                }
                $this->apiResponse['status'] = "1";
                $this->apiResponse['message'] = $this->Common->GetNotification("saveAvailabilitySuccess", $apiData['data']['langType']);
            } else {
                $this->apiResponse['status'] = "0";
                $this->apiResponse['message'] = $this->Common->GetNotification("failToSaveAvailability", $apiData['data']['langType']);
            }
        }else{
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("saveAvailabilitySuccess", $apiData['data']['langType']);
        }
        
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function saveDoctorAvailabilitySetting_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        /* if (!isset($apiData['data']['availability']) || empty($apiData['data']['availability'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("availabilityRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        } */

        if(isset($apiData['data']['timing']) && !empty($apiData['data']['timing'])){
            $timing = $apiData['data']['timing'];
        }else{
            $timing = 30;
        }

        $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
        
        $lastId = "";
        
        $this->User_Availability_Offtime_Model->removeData($user->id);
        if(isset($apiData['data']['offDateTime']) && !empty($apiData['data']['offDateTime'])){
            foreach($apiData['data']['offDateTime'] as $value){
                if(!isset($value['day']) || !isset($value['month']) || !isset($value['startTime']) || !isset($value['endTime'])){
                    continue;
                }
                $availabilityOffData = array();
                $availabilityOffData['userId'] = $user->id;
                $availabilityOffData['day'] = $value['day'];
                $availabilityOffData['month'] = $value['month'];
                $availabilityOffData['startTime'] = $value['startTime'];
                $availabilityOffData['endTime'] = $value['endTime'];
                $availabilityOffData['status'] = 1;
                $this->User_Availability_Offtime_Model->setData($availabilityOffData);
            }
        }

        $this->User_Availability_Setting_Model->removeData($user->id);
        if(isset($apiData['data']['availability']) && !empty($apiData['data']['availability'])){
            foreach($apiData['data']['availability'] as $value){
                if(!isset($value['type']) || !isset($value['startTime']) || !isset($value['endTime'])){
                    continue;
                }
                
                $availabilityData = array();
                $availabilityData['userId'] = $user->id;
                $availabilityData['type'] = $value['type'];
                $availabilityData['timing'] = $timing;
                $availabilityData['startTime'] = $value['startTime'];
                $availabilityData['endTime'] = $value['endTime'];
                $availabilityData['userLocationId'] = isset($value['userLocationId']) && !empty($value['userLocationId']) ? $value['userLocationId'] : '' ;
                $availabilityData['inHome'] = isset($value['inHome']) && $value['inHome'] >= 0 ? $value['inHome'] : '' ; // service type
                $availabilityData['officeGym'] = isset($value['officeGym']) && $value['officeGym'] >= 0 ? $value['officeGym'] : '' ; // service type
                $availabilityData['virtual'] = isset($value['virtual']) && $value['virtual'] >= 0 ? $value['virtual'] : '' ; // service type
                $availabilityData['status'] = 1;
                if($value['type'] == 3){
                    if(!isset($value['day'])){
                        continue;
                    }
                    $availabilityData['day'] = $value['day'];
                }
        
                $lastId = $this->User_Availability_Setting_Model->setData($availabilityData);
            }
            if (!empty($lastId)) {
                if($user->profileStatus == 0){
                    $this->User->setData(['profileStatus'=>1],$user->id);
                }
                $this->Background_Model->updateProviderAvailability($user->id,$myUserTimeZone);
                $this->apiResponse['status'] = "1";
                $this->apiResponse['message'] = $this->Common->GetNotification("saveAvailabilitySuccess", $apiData['data']['langType']);
            } else {
                $this->apiResponse['status'] = "0";
                $this->apiResponse['message'] = $this->Common->GetNotification("failToSaveAvailability", $apiData['data']['langType']);
            }
        }else{
            $this->Background_Model->updateProviderAvailability($user->id,$myUserTimeZone);
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("saveAvailabilitySuccess", $apiData['data']['langType']);
        }
        
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getDoctorAvailabilitySetting_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        if ($user->role  != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        $response = array();
        $response['availabilitySetting'] = $this->User_Availability_Setting_Model->get(['apiResponse'=>true,'userId'=>$user->id,'status'=>1,'orderby'=>'userAvailabilitySettingId','orderstate'=>'ASC']);
        $response['timeOff'] = $this->User_Availability_Offtime_Model->get(['apiResponse'=>true,'userId'=>$user->id,'status'=>1,'orderby'=>'id','orderstate'=>'ASC']);
        if (!empty($response['availabilitySetting']) || !empty($response['timeOff'])) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("getAvailabilitySettingSuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common->GetNotification("availabilitySettingNotFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getProfessionalInfo_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        if ($user->role  != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $response = $this->User_Professional->get(['userId'=>$user->id,'status'=>1],true);
        if (!empty($response)) {
            $response->virtualPrice = $user->virtualPrice;
            $response->mobilePrice = $user->mobilePrice;
            $response->onsitePrice = $user->onsitePrice;
            $response->professions =  $this->User_Profession->get(['userId'=>$user->id,'status'=>1,'apiResponse'=>true]);
            $response->specialties = $this->User_specialties_Model->get(['userId'=>$user->id,'apiResponse'=>true,'status'=>1, 'getOtherData'=>true,]);
            $user = $this->User->get(['id'=>$user->id,'apiResponse'=>true,'status'=>1, ]);
            $response->bio = isset($user->bio) ? $user->bio:'';
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("getProfessionalInfoSuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common->GetNotification("professionalInfoNotFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function saveProfessionalInfo_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        if ($user->role  != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $setData = [];
        // if ( 
        // (!isset($apiData['data']['mobilePrice']) || empty($apiData['data']['mobilePrice'])) && 
        // (!isset($apiData['data']['virtualPrice']) || empty($apiData['data']['virtualPrice'])) &&
        // (!isset($apiData['data']['onsitePrice']) || empty($apiData['data']['onsitePrice'])) 
        // ) {
        //     $this->apiResponse['message'] = $this->Common_Model->GetNotification("atLeastOnePriceRequired", $apiData['data']['langType']);
        //     return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        // }

        if (!isset($apiData['data']['professions']) || empty($apiData['data']['professions'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("professionsRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
     
        if (!isset($apiData['data']['practiceYear']) || empty($apiData['data']['practiceYear'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("practiceYearRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
     
        if (!isset($apiData['data']['practiceStateId']) || empty($apiData['data']['practiceStateId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("practiceStateIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
     
        /*if (!isset($apiData['data']['practiceCityId']) || empty($apiData['data']['practiceCityId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("practiceCityIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }*/
     
        /*if (!isset($apiData['data']['licenseNumber']) || empty($apiData['data']['licenseNumber'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("licenseNumberRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
     
        if (!isset($apiData['data']['licenseStartDate']) || empty($apiData['data']['licenseStartDate'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("licenseStartDateRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
     
        if (!isset($apiData['data']['licenseEndDate']) || empty($apiData['data']['licenseEndDate'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("licenseEndDateRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
     
        if (!isset($apiData['data']['licenseImage']) || empty($apiData['data']['licenseImage'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("licenseImageRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }*/
     
        /* if (!isset($apiData['data']['npiNumber']) || empty($apiData['data']['npiNumber'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("npiNumberRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        } */
     
        /* if (!isset($apiData['data']['insuranceLiability']) || empty($apiData['data']['insuranceLiability'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("insuranceLiabilityRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        } */
     
        if (!isset($apiData['data']['insuranceStartDate']) || empty($apiData['data']['insuranceStartDate'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("insuranceStartDateRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
     
        if (!isset($apiData['data']['insuranceEndDate']) || empty($apiData['data']['insuranceEndDate'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("insuranceEndDateRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        
        if (!isset($apiData['data']['insuranceImage']) || empty($apiData['data']['insuranceImage'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("insuranceImageRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if(isset($apiData['data']['onsitePrice']) && !empty($apiData['data']['onsitePrice'])) {
            $setData['onsitePrice'] = $apiData['data']['onsitePrice'];
        }
        if(isset($apiData['data']['virtualPrice']) && !empty($apiData['data']['virtualPrice']) ) {
            $setData['virtualPrice'] = $apiData['data']['virtualPrice'];
        }
        if(isset($apiData['data']['mobilePrice']) && !empty($apiData['data']['mobilePrice']) ) {
            $setData['mobilePrice'] = $apiData['data']['mobilePrice'];
        }
        if(isset($apiData['data']['bio']) ) {
            $setData['bio'] = $apiData['data']['bio'];
        }

        /* ------- set user_specialties ------ */
        if(isset($apiData['data']['specialties']) ) {
            $this->User_specialties_Model->setData(['userIds'=>$user->id,'status'=>2]);
            foreach($apiData['data']['specialties'] as $specialtiesId) {
                if(empty($specialtiesId)) {
                    continue;
                }

                $existUserSpecialtiesData = $this->User_specialties_Model->get([
                    'userId'=> $user->id,
                    'specialtiesId'=> $specialtiesId,
                ], true);

                if (empty($existUserSpecialtiesData)) {
                    $this->User_specialties_Model->setData([
                        'userId'=>$user->id,
                        'specialtiesId'=>$specialtiesId]);
                } else {
                    $this->User_specialties_Model->setData([
                        'userId'=>$user->id,
                        'specialtiesId'=>$specialtiesId,
                        'status'=>1
                    ], $existUserSpecialtiesData->id);
                }
            }
        }
        /* ------- end set user_specialties ------ */

        $set = $this->User->setData($setData,$user->id);

        $setData = array();
        $setData['practiceYear'] = $apiData['data']['practiceYear'];
        $setData['practiceStateId'] = $apiData['data']['practiceStateId'];
        $setData['practiceCityId'] = (isset($apiData['data']['practiceCityId']) ? $apiData['data']['practiceCityId'] : "");
        $setData['licenseNumber'] = (isset($apiData['data']['licenseNumber']) ? $apiData['data']['licenseNumber'] : "");
        $setData['licenseStartDate'] = (isset($apiData['data']['licenseStartDate']) && !empty($apiData['data']['licenseStartDate']) ? date('Y-m-d',strtotime($apiData['data']['licenseStartDate'])) : "");
        $setData['licenseEndDate'] = (isset($apiData['data']['licenseEndDate']) && !empty($apiData['data']['licenseEndDate']) ? date('Y-m-d',strtotime($apiData['data']['licenseEndDate'])) : "");
        $setData['licenseImage'] = (isset($apiData['data']['licenseImage']) ? $apiData['data']['licenseImage'] : "");
        $setData['npiNumber'] = isset($apiData['data']['npiNumber']) ? $apiData['data']['npiNumber'] : null;
        $setData['insuranceLiability'] = isset($apiData['data']['insuranceLiability']) ? $apiData['data']['insuranceLiability'] : null;
        $setData['insuranceStartDate'] = date('Y-m-d',strtotime($apiData['data']['insuranceStartDate']));
        $setData['insuranceEndDate'] = date('Y-m-d',strtotime($apiData['data']['insuranceEndDate']));
        $setData['insuranceImage'] = $apiData['data']['insuranceImage'];
        $setData['companyName'] = (isset($apiData['data']['companyName']) ? $apiData['data']['companyName'] : "");

        $existData = $this->User_Professional->get(['userId'=>$user->id],true);
        if(!empty($existData)){
            $setData['status'] = 1;
            $set = $this->User_Professional->setData($setData,$existData->id);
        }else{
            $setData['userId'] = $user->id;
            $set = $this->User_Professional->setData($setData);
        }
        if (!empty($set)) {
            $this->User_Profession->setData(['userIds'=>$user->id,'status'=>2]);
            foreach($apiData['data']['professions'] as $value){
                if(empty($value)){
                    continue;
                }
                
                $existprofessionData = $this->User_Profession->get(['userId'=>$user->id,'professionId'=>trim($value)],true);
                
                if(!empty($existprofessionData)){
                    $this->User_Profession->setData(['userId'=>$user->id,'professionId'=>trim($value),'status'=>1],$existprofessionData->id);
                }else{
                    $this->User_Profession->setData(['userId'=>$user->id,'professionId'=>trim($value)]);
                }
            }
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("professionalInfoSavedSuccess", $apiData['data']['langType']);
        } else {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common->GetNotification("failToSaveProfessionalInfo", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getMedicalHistoryPersonal_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role  != '2') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotAUser", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $response = $this->Medical_History_Personal->get(['userId'=>$user->id,'status'=>1,'apiResponse'=>true],true);
        if(empty($response)){
            $response = (object)array();
            $response->medicalHistoryPersonalId = 0;
            $response->userId = 0;
            $response->height_ft = 0;
            $response->height_in = 0;
            $response->weight = 0;
        }
        $response->ongoingMedication = $this->User_Medications->get(['userId'=>$user->id,'status'=>1,'getOtherData'=>true,'type'=>1,'apiResponse'=>true]);
        $response->pastMedication = $this->User_Medications->get(['userId'=>$user->id,'status'=>1,'getOtherData'=>true,'type'=>2,'apiResponse'=>true]);
        $response->allergies = $this->User_Allergies->get(['userId'=>$user->id,'status'=>1,'getOtherData'=>true,'apiResponse'=>true]);
        $response->healthIssues = $this->User_Health_Issues->get(['userId'=>$user->id,'status'=>1,'getOtherData'=>true,'apiResponse'=>true]);
        $response->injuries = $this->User_Injuries->get(['userId'=>$user->id,'status'=>1,'getOtherData'=>true,'apiResponse'=>true]);
        $response->surgeries = $this->User_Surgeries->get(['userId'=>$user->id,'status'=>1,'getOtherData'=>true,'apiResponse'=>true]);
        if (!empty($response)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("getMedicalPersonalHistorySuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common->GetNotification("medicalPersonalHistoryNotFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function saveMedicalHistoryPersonal_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        if ($user->role  != '2') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotAUser", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $set = "";
        if(isset($apiData['data']['ongoingMedication']) ){
            $this->User_Medications->setData(['userIds'=>$user->id,'types'=>1,'status'=>2]);
            if( is_array($apiData['data']['ongoingMedication']) ){
                foreach($apiData['data']['ongoingMedication'] as $value){
                    if(!isset($value['medicationName']) || empty($value['medicationName'])){
                        continue;
                    }
                    $checkMediData = $this->Medications->get(['name'=>trim($value['medicationName'])],true);
                    if(!empty($checkMediData)){
                        $medicationId = $this->Medications->setData(['status'=>1],$checkMediData->id);
                    }else{
                        $medicationId = $this->Medications->setData(['name'=>trim($value['medicationName'])]);
                    }

                    $existUserMediData = $this->User_Medications->get(['userId'=>$user->id,'type'=>1,'medicationId'=>$medicationId],true);
                    $userMediData = array();
                    $userMediData['userId'] = $user->id;
                    $userMediData['medicationId'] = $medicationId;
                    $userMediData['status'] = 1;
                    $userMediData['dosageId'] = (isset($value['dosageId']) ? $value['dosageId'] : '');
                    $userMediData['frequencyId'] = (isset($value['frequencyId']) ? $value['frequencyId'] : '');
                    if(!empty($existUserMediData)){
                        $set = $this->User_Medications->setData($userMediData,$existUserMediData->id);
                    }else{
                        $set = $this->User_Medications->setData($userMediData);
                    }
                }
            }
        }
        if(isset($apiData['data']['pastMedication']) ){
            $this->User_Medications->setData(['userIds'=>$user->id,'types'=>2,'status'=>2]);
            if( is_array($apiData['data']['pastMedication']) ){
                foreach($apiData['data']['pastMedication'] as $value){
                    if(!isset($value['medicationName']) || empty($value['medicationName'])){
                        continue;
                    }
                    $checkMediData = $this->Medications->get(['name'=>trim($value['medicationName'])],true);
                    if(!empty($checkMediData)){
                        $medicationId = $this->Medications->setData(['status'=>1],$checkMediData->id);
                    }else{
                        $medicationId = $this->Medications->setData(['name'=>trim($value['medicationName'])]);
                    }

                    $existUserMediData = $this->User_Medications->get(['userId'=>$user->id,'type'=>2,'medicationId'=>$medicationId],true);
                    $userMediData = array();
                    $userMediData['userId'] = $user->id;
                    $userMediData['medicationId'] = $medicationId; 
                    $userMediData['status'] = 1;
                    $userMediData['type'] = 2;
                    $userMediData['dosageId'] = (isset($value['dosageId']) ? $value['dosageId'] : '');
                    $userMediData['frequencyId'] = (isset($value['frequencyId']) ? $value['frequencyId'] : '');
                    if(!empty($existUserMediData)){
                        $set = $this->User_Medications->setData($userMediData,$existUserMediData->id);
                    }else{
                        $set = $this->User_Medications->setData($userMediData);
                    }
                }
            }
        }
        if(isset($apiData['data']['allergies']) ) {
            $this->User_Allergies->setData(['userIds'=>$user->id,'status'=>2]);
            if( is_array($apiData['data']['allergies']) ){
                foreach($apiData['data']['allergies'] as $value){
                    if(!isset($value['name']) || empty($value['name'])){
                        continue;
                    }
                    if(!isset($value['keyword']) || empty($value['keyword'])){
                        continue;
                    }
                    $checkAlrgData = $this->Allergies_Type->get(['name'=>trim($value['name'])],true);
                    if(!empty($checkAlrgData)){
                        $allergiesTypeId = $this->Allergies_Type->setData(['status'=>1],$checkAlrgData->id);
                    }else{
                        $allergiesTypeId = $this->Allergies_Type->setData(['name'=>trim($value['name'])]);
                    }

                    $userAlrgData = ['userId'=>$user->id,'allergiesTypeId'=>$allergiesTypeId,'keyword'=>trim($value['keyword'])];
                    $existUserAlrgData = $this->User_Allergies->get($userAlrgData,true);
                    if(!empty($existUserAlrgData)){
                        $userAlrgData['status'] = 1;
                        $set = $this->User_Allergies->setData($userAlrgData,$existUserAlrgData->id);
                    }else{
                        $set = $this->User_Allergies->setData($userAlrgData);
                    }
                }
            }
        }
        if(isset($apiData['data']['healthIssues']) ) {
            $this->User_Health_Issues->setData(['userIds'=>$user->id,'status'=>2]);
            if( is_array($apiData['data']['healthIssues']) ){
                foreach($apiData['data']['healthIssues'] as $value){
                    if(empty($value)){
                        continue;
                    }
                
                    $checkHealthIssuesData = $this->Health_Issues->get(['name'=>trim($value)],true);
                    if(!empty($checkHealthIssuesData)){
                        $healthIssuesId = $this->Health_Issues->setData(['status'=>1],$checkHealthIssuesData->id);
                    }else{
                        $healthIssuesId = $this->Health_Issues->setData(['name'=>trim($value)]);
                    }

                    $existUserAlrgData = $this->User_Health_Issues->get(['userId'=>$user->id,'healthIssuesId'=>$healthIssuesId],true);
                    if(!empty($existUserAlrgData)){
                        $set = $this->User_Health_Issues->setData(['userId'=>$user->id,'healthIssuesId'=>$healthIssuesId,'status'=>1],$existUserAlrgData->id);
                    }else{
                        $set = $this->User_Health_Issues->setData(['userId'=>$user->id,'healthIssuesId'=>$healthIssuesId]);
                    }
                }
            }
        }
        if(isset($apiData['data']['injuries']) ){
            $this->User_Injuries->setData(['userIds'=>$user->id,'status'=>2]);
            if( is_array($apiData['data']['injuries']) ){
                foreach($apiData['data']['injuries'] as $value){
                    if(empty($value)){
                        continue;
                    }
                
                    $checkInjuriesData = $this->Injuries->get(['name'=>trim($value)],true);
                    if(!empty($checkInjuriesData)){
                        $injuriesId = $this->Injuries->setData(['status'=>1],$checkInjuriesData->id);
                    }else{
                        $injuriesId = $this->Injuries->setData(['name'=>trim($value)]);
                    }

                    $existUserInjuriesData = $this->User_Injuries->get(['userId'=>$user->id,'injuriesId'=>$injuriesId],true);
                    if(!empty($existUserInjuriesData)){
                        $set = $this->User_Injuries->setData(['userId'=>$user->id,'injuriesId'=>$injuriesId,'status'=>1],$existUserInjuriesData->id);
                    }else{
                        $set = $this->User_Injuries->setData(['userId'=>$user->id,'injuriesId'=>$injuriesId]);
                    }
                }
            }
        }
        if(isset($apiData['data']['surgeries']) ){
            $this->User_Surgeries->setData(['userIds'=>$user->id,'status'=>2]);
            if( is_array($apiData['data']['surgeries']) ){
                foreach($apiData['data']['surgeries'] as $value){
                    if(empty($value)){
                        continue;
                    }
                
                    $checkSurgeriesData = $this->Surgeries->get(['name'=>trim($value)],true);
                    if(!empty($checkSurgeriesData)){
                        $surgeriesId = $this->Surgeries->setData(['status'=>1],$checkSurgeriesData->id);
                    }else{
                        $surgeriesId = $this->Surgeries->setData(['name'=>trim($value)]);
                    }

                    $existUserSurgeriesData = $this->User_Surgeries->get(['userId'=>$user->id,'surgeriesId'=>$surgeriesId],true);
                    if(!empty($existUserSurgeriesData)){
                        $set = $this->User_Surgeries->setData(['userId'=>$user->id,'surgeriesId'=>$surgeriesId,'status'=>1],$existUserSurgeriesData->id);
                    }else{
                        $set = $this->User_Surgeries->setData(['userId'=>$user->id,'surgeriesId'=>$surgeriesId]);
                    }
                }
            }
        }

        $setData = array();
        if(isset($apiData['data']['height_ft']) && $apiData['data']['height_ft'] != ""){
            $setData['height_ft'] = $apiData['data']['height_ft'];
        }

        if(isset($apiData['data']['height_in']) && $apiData['data']['height_in'] != ""){
            $setData['height_in'] = $apiData['data']['height_in'];
        }

        if(isset($apiData['data']['weight']) && $apiData['data']['weight'] != ""){
            $setData['weight'] = $apiData['data']['weight'];
        }
       
        if(!empty($setData)){
            $existData = $this->Medical_History_Personal->get(['userId'=>$user->id],true);
            if(!empty($existData)){
                $setData['status'] = 1;
                $set = $this->Medical_History_Personal->setData($setData,$existData->id);
            }else{
                $setData['userId'] = $user->id;
                $set = $this->Medical_History_Personal->setData($setData);
            }
        }
        
        // if (!empty($set)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("medicalPersonalHistorySavedSuccess", $apiData['data']['langType']);
        // } else {
        //     $this->apiResponse['status'] = "0";
        //     $this->apiResponse['message'] = $this->Common->GetNotification("failToSaveMedicalPersonalHistory", $apiData['data']['langType']);
        // }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getMedicalHistorySocial_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role  != '2') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotAUser", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $response = $this->Medical_History_Social->get(['userId'=>$user->id,'status'=>1,'apiResponse'=>true],true);
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

    public function saveMedicalHistorySocial_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        if ($user->role  != '2') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotAUser", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $setData = array();
        $tenArray = array(0,1,2,3,4,5,6,7,8,9,10);
        $twoArray = array(1,2);
        if(isset($apiData['data']['hourOfSleep']) && in_array($apiData['data']['hourOfSleep'], $tenArray)){
            $setData['hourOfSleep'] = $apiData['data']['hourOfSleep'];
        }

        if(isset($apiData['data']['weekTypicallyExercise']) && in_array($apiData['data']['weekTypicallyExercise'], $tenArray)){
            $setData['weekTypicallyExercise'] = $apiData['data']['weekTypicallyExercise'];
        }

        if(isset($apiData['data']['smokeOrTobacco']) && in_array($apiData['data']['smokeOrTobacco'], $twoArray)){
            $setData['smokeOrTobacco'] = $apiData['data']['smokeOrTobacco'];
        }

        if(isset($apiData['data']['alcoholicBeveragesWeek']) && in_array($apiData['data']['alcoholicBeveragesWeek'], $tenArray)){
            $setData['alcoholicBeveragesWeek'] = $apiData['data']['alcoholicBeveragesWeek'];
        }

        if(isset($apiData['data']['caffeinatedBeveragesWeek']) && in_array($apiData['data']['caffeinatedBeveragesWeek'], $tenArray)){
            $setData['caffeinatedBeveragesWeek'] = $apiData['data']['caffeinatedBeveragesWeek'];
        }

        if(isset($apiData['data']['recreationalDrugs']) && in_array($apiData['data']['recreationalDrugs'], $twoArray)){
            $setData['recreationalDrugs'] = $apiData['data']['recreationalDrugs'];
        }

        if(isset($apiData['data']['workHazardousOrToxicChemicals']) && in_array($apiData['data']['workHazardousOrToxicChemicals'], $twoArray)){
            $setData['workHazardousOrToxicChemicals'] = $apiData['data']['workHazardousOrToxicChemicals'];
        }

        $existData = $this->Medical_History_Social->get(['userId'=>$user->id],true);
        if(!empty($existData)){
            $setData['status'] = 1;
            $set = $this->Medical_History_Social->setData($setData,$existData->id);
        }else{
            $setData['userId'] = $user->id;
            $set = $this->Medical_History_Social->setData($setData);
        }
        if (!empty($set)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("medicalPersonalSocialSavedSuccess", $apiData['data']['langType']);
        } else {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common->GetNotification("failToSaveMedicalSocialHistory", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getMedicalHistoryFamilyIllness_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role  != '2') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotAUser", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $response = $this->User_Family_Illness->get(['userId'=>$user->id,'status'=>1,'getOtherData'=>true,'apiResponse'=>true]);
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

    public function saveMedicalHistoryFamilyIllness_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        if ($user->role  != '2') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotAUser", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        /* if (!isset($apiData['data']['familyIllness']) || empty($apiData['data']['familyIllness'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("familyIllnessRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        } */
        $set = "";
        $this->User_Family_Illness->setData(['userIds'=>$user->id,'status'=>2]);
        if (isset($apiData['data']['familyIllness'])) {
            if( is_array($apiData['data']['familyIllness']) ){
                foreach($apiData['data']['familyIllness'] as $value){
                    if(!isset($value['illnessName']) || empty($value['illnessName'])) {
                        continue;
                    }
                    if(!isset($value['relation']) || empty($value['relation'])) {
                        continue;
                    }
                    $checkIllnessData = $this->Illness->get(['name'=>trim($value['illnessName'])],true);
                    if(!empty($checkIllnessData)) {
                        $illnessId = $this->Illness->setData(['status'=>1],$checkIllnessData->id);
                    } else {
                        $illnessId = $this->Illness->setData(['name'=>trim($value['illnessName'])]);
                    }

                    $userFamilyIllnessData = ['userId'=>$user->id,'illnessId'=>$illnessId,'relation'=>trim($value['relation'])];
                    $existUserFamilyIllnessData = $this->User_Family_Illness->get($userFamilyIllnessData,true);
                    if(!empty($existUserFamilyIllnessData)) {
                        $userFamilyIllnessData['status'] = 1;
                        $set = $this->User_Family_Illness->setData($userFamilyIllnessData,$existUserFamilyIllnessData->id);
                    } else {
                        $set = $this->User_Family_Illness->setData($userFamilyIllnessData);
                    }
                }
            }
        }
        // if (!empty($set)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("medicalFamilyIllnessHistorySavedSuccess", $apiData['data']['langType']);
        // } else {
        //     $this->apiResponse['status'] = "0";
        //     $this->apiResponse['message'] = $this->Common->GetNotification("failToSaveMedicalFamilyIllnessHistory", $apiData['data']['langType']);
        // }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function saveUserCard_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
 
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

        if($user->role == 3){
            $this->load->library('stripe');
        }else{
            $this->load->library('stripe',array('type'=>'1'));
        }
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
        
        if (!empty($cardId)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("saveCardSuccess", $apiData['data']['langType']);
        }else{
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("failToSaveCard", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);        
    }

    public function setCardDefault_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        if (!isset($apiData['data']['userCardId']) || empty($apiData['data']['userCardId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userCardIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $existCardData = $this->User_Card->get(['id'=>$apiData['data']['userCardId'],'userId'=>$user->id,'status'=>1],true);
        if(empty($existCardData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("cardNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $this->User_Card->setData(['userIds'=>$user->id,'isDefault'=>0]);
        $response = $this->User_Card->setData(['isDefault'=>1], $existCardData->id);
        if (!empty($response)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("setDefaultCardSuccess", $apiData['data']['langType']);
        } else {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("failToSetDefaultCard", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getUserCardList_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        $data = array();
        $data['status'] = 1;
        $data['userId'] = $user->id;
        $data['apiResponse'] = true;
        $response = $this->User_Card->get($data);
        if (!empty($response)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("getCardListSuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("cardListNotFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }
    
    public function removeUserCard_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
 
        if (!isset($apiData['data']['userCardId']) || empty($apiData['data']['userCardId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userCardIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $existCardData = $this->User_Card->get(['id'=>$apiData['data']['userCardId'],'userId'=>$user->id,'status'=>1],true);
        if(empty($existCardData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("cardNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $totalCard = $this->User_Card->get(['userId'=>$user->id,'status'=>1]);
        if(count($totalCard) <= 1){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("mustBeAtLeastOneCardNeeded", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if($user->role == 3){
            $this->load->library('stripe');
        }else{
            $this->load->library('stripe',array('type'=>'1'));
        }
        $removeCardResponse = $this->stripe->deleteCard(['customer_id'=> $existCardData->customerId,'card_id'=> $existCardData->cardId]);
        
        if(empty($removeCardResponse)){ //FAIL TO GET CARD TOKEN
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("failToRemoveCardFromStripe", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }elseif(isset($removeCardResponse['error'])){ //FAIL TO REGISTER CARD IN STRIPE
            $removeCardResponse['error']['status'] = '0';
            $this->apiResponse = $removeCardResponse['error'];
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }elseif((!isset($removeCardResponse['deleted']) || $removeCardResponse['deleted'] != 1)){ //FAIL TO GET CARD TOKEN
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("failToRemoveCardFromStripe", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        $cardId = $this->User_Card->setData(['status'=>2],$existCardData->id);
        
        if (!empty($cardId)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("removeCardSuccess", $apiData['data']['langType']);
        }else{
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("failToRemoveCard", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getDoctorList_post() {
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
     
        if(isset($apiData['data']['search']) && $apiData['data']['search'] != ""){
            $data['search'] = $apiData['data']['search'];
        }
        $data['status'] = 1;
        $data['role'] = 3;
        $data['currentUserNot'] = $user->id;
        $totalData = count($this->User->get($data));
        $data['getProfessionData'] = true;
        $data['limit'] = $limit;
        $data['offset'] = $offset;
        $response = $this->User->get($data);
        if (!empty($response)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("getHealthProfessionalsSuccess", $apiData['data']['langType']);
            $this->apiResponse['totalPages'] = ceil($totalData / $limit) . "";
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification(($offset > 0 ? 'allcatchedUp' : "healthProfessionalsNotFound"), $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function deleteUserData_post() {
        $user = $this->checkUserRequest();
        $this->load->model('User_Transaction_Model','User_Transaction');
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        //echo "<hr>";echo "<pre>";
        if(!empty($user) && $user->role != 1) {
            $data = [
                "status" => 1
            ];
            $cancelreason = "1"; //1-by Client, 2-by Provider, 3-Funds Unavailable
            if($user->role == 2) {
                $data["userId"] = $user->id;
                $cancelreason = "1";
            }
            else {
                $data["doctorId"] = $user->id;
                $cancelreason = "2";
            }
            $getAppointment = $this->User_Appointment->get($data);
            if(!empty($getAppointment)) {
                foreach($getAppointment AS $v) {
                    if(!empty($v)) {
                        //echo $v->id."-".$v->userId."-".$v->doctorId."<br>"; print_r($v);
                        $tranData = $this->User_Transaction->get([
                            'userId' => $v->userId,
                            'userIdTo' => $v->doctorId,
                            'payType' => 1,
                            'status' => [1],
                            'appointmentId' => $v->id,
                        ],true);
                    
                        if(!empty($tranData)) {
                            // ---------------- Cancel order from Stripe ------------------ //
                            $this->load->library('stripe',array('type'=>'1'));         
                            //$stripeResponseData = $this->stripe->addRefund($tranData->stripeTransactionId);        
                            /* if (isset($stripeResponseData['error'])) {
                                $this->apiResponse['status'] = "0";
                                $this->apiResponse['message'] = $stripeResponseData['error']['message'];
                                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                            }  */
                            // ---------------- end Cancel order from Stripe --------------- // availabilityId
                        }
                        //$this->User_Appointment->setData(['status' => 2, 'cancelreason' => $cancelreason], $v->id);
                        //$this->User_Availability->setData(['isBooked'=> 0, 'status'=>2], $v->userAvailabilityId);

                        //Send notification doctor/user to user for Cancel appointment
                        $notiData = [];
                        $notiData['send_from'] = $v->doctorId;
                        $notiData['send_to'] = $v->userId;
                        $notiData['model_id'] = (int)$v->id;
                        //$this->Common->backroundCall('cancelledUserAppointmentByDoctor', $notiData);        
                        $notiData = [];
                        $notiData['send_from'] = $v->userId;
                        $notiData['send_to'] = $v->doctorId;
                        $notiData['model_id'] = (int)$v->id;
                        $notiData['userId'] = (int)$v->userId;
                        //$this->Common->backroundCall('cancelledUserAppointmentAsDoctor', $notiData);
                    }
                }
            }

            //$this->User->setData(['status' => 3], $user->id); //3=>Deleted
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("userDeleteSuccess", $apiData['data']['langType']);
            //$this->apiResponse['data'] = $user;
            $this->destroysession();
        }
        else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common->GetNotification("userInfoNotFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function setUserLocation_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        $setData = [];

        if(isset($apiData['data']['name']) && !empty($apiData['data']['name'])) {
            $setData['name'] = $apiData['data']['name'];
        }
        if(isset($apiData['data']['address']) && !empty($apiData['data']['address']) ) {
            $setData['address'] = $apiData['data']['address'];
        }
        if(isset($apiData['data']['apartmentNumber']) && !empty($apiData['data']['apartmentNumber']) ) {
            $setData['apartmentNumber'] = $apiData['data']['apartmentNumber'];
        }
        if(isset($apiData['data']['city']) && !empty($apiData['data']['city']) ) {
            $setData['city'] = $apiData['data']['city'];
        }
        if(isset($apiData['data']['state']) && !empty($apiData['data']['state']) ) {
            $setData['state'] = $apiData['data']['state'];
        }
        if(isset($apiData['data']['zipcode']) && !empty($apiData['data']['zipcode']) ) {
            $setData['zipcode'] = $apiData['data']['zipcode'];
        }
        if(isset($apiData['data']['latitude']) && !empty($apiData['data']['latitude']) ) {
            $setData['latitude'] = $apiData['data']['latitude'];
        }
        if(isset($apiData['data']['longitude']) && !empty($apiData['data']['longitude']) ) {
            $setData['longitude'] = $apiData['data']['longitude'];
        }
        if(isset($apiData['data']['radius']) && !empty($apiData['data']['radius']) ) {
            $setData['radius'] = $apiData['data']['radius'];
        }
        if(isset($apiData['data']['userLocationId']) && !empty($apiData['data']['userLocationId'])){
            $exist = $this->User_Location_Model->get(['id'=>$apiData['data']['userLocationId'],'userId'=>$user->id,'status'=>1]);
            if(empty($exist)){
                $this->apiResponse['status'] = "0";
                $this->apiResponse['message'] = $this->Common->GetNotification("userLocationNotFound", $apiData['data']['langType']);    
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
        }
        $setData['userId'] = $user->id;
        $set = $this->User_Location_Model->setData($setData,isset($apiData['data']['userLocationId']) && !empty($apiData['data']['userLocationId']) ? $apiData['data']['userLocationId'] : '' );
        if (!empty($set)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("userLocationSavedSuccess", $apiData['data']['langType']);
        } else {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common->GetNotification("failToSaveUserLocation", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getUserLocationList_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        $response = $this->User_Location_Model->get(['userId'=>$user->id,'status'=>1,'apiResponse'=>true]);
        if (!empty($response)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("getUserLocationListSuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common->GetNotification("userLocationListNotFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getUserLocationDetail_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if (!isset($apiData['data']['userLocationId']) || empty($apiData['data']['userLocationId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userLocationIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        $userLocationId = $apiData['data']['userLocationId'];
        $response = $this->User_Location_Model->get([ 'userId' => $user->id, 'status' => 1 ,'id' => $userLocationId, 'apiResponse'=>true],true);
        if (!empty($response)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("getUserLocationSuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common->GetNotification("userLocationNotFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function destroysession() {
        $this->session->sess_destroy();
        delete_cookie('doctorToken');
        delete_cookie('userToken');
        delete_cookie('patient_address');
        delete_cookie('patient_latitude');
        delete_cookie('patient_longitude');
        delete_cookie('doctor_latitude');
        delete_cookie('doctor_longitude');
        delete_cookie('header_address_user');
        
        $this->session->unset_userdata('doctorRole');
        $this->session->unset_userdata('doctorId');
        $this->session->unset_userdata('doctorImage');
        $this->session->unset_userdata('doctorName');
        $this->session->unset_userdata('userRole');
        $this->session->unset_userdata('userId');
        $this->session->unset_userdata('userImage');
        $this->session->unset_userdata('username');
        return true;
    }


}
