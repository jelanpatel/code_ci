<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'libraries/REST_Controller.php';

class Auth extends REST_Controller {

    function __construct() {
        parent::__construct();
        $this->load->model('Common_Model','Common');
        $this->load->model('Background_Model');
        $this->load->model('Users_Model', 'User');
        $this->load->model('Usersocialauth_Model', 'Usersocialauth');
        $this->load->model('Auth_Model');
        $this->load->model('User_Referral_Model');
        $this->load->model('User_Referral_Earning_Model');
        $this->load->model('WebAppProviderSubscription_Model');
        $this->load->model('Users_Model', 'User');
    }

    public function getGoogleCalendarAuthUrl_post() {
		$user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
		$rurl = base_url()."google/calendarconnect/".$user->id;
        $this->apiResponse['status'] = "1";
        $this->apiResponse['message'] = "success";
        $this->apiResponse['url'] = $rurl;
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getGoogleCalendarDisconnect_post() {
		$user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        $arr = [
            'gc_status' => 0,
            'gc_accessToken' => "",
        ];
        $this->User->setData($arr, $user->id);

        $this->apiResponse['status'] = "1";
        $this->apiResponse['message'] = "Successfully disconnected google calendar from your existing account";
        $this->apiResponse['data'] = $this->User->userData($user->id, false);
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getAppleCalendarStatus_post() {
		$user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        $udata = $this->User->userData($user->id, false);
        $this->apiResponse['status'] = "1";
        $this->apiResponse['message'] = "success";
        $this->apiResponse['appleCalendarStatus'] = $udata->applec_status;
        $this->apiResponse['data'] = $udata;
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function setAppleCalendarStatus_post() {
		$user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        if (!isset($apiData['data']['appleCalendarStatus'])) {
            $this->apiResponse['message'] = $this->Common->GetNotification("appleCalendarStatusRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $arr = [
            'applec_status' => $apiData['data']['appleCalendarStatus']
        ];
        $this->User->setData($arr, $user->id);
        $udata = $this->User->userData($user->id, false);

        $this->apiResponse['status'] = "1";
        $this->apiResponse['message'] = "successfully updated status";
        $this->apiResponse['appleCalendarStatus'] = $udata->applec_status;
        $this->apiResponse['data'] = $udata;
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getNearProvider_post() {
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
        $data["role"] = 3;
        $data["apiResponse"] = true;
        $data["getInRadiusNew"] = true;
        $data["latNotBlank"] = true;
        $data['getProfessionData'] = true;
        $data['getProfessionalData'] = true;
        $data['getRatingAverageData'] = true;
        $data['checkAvailibilitySetting'] = true;
        $data['subscriptionDoctorList'] = true;
        $data['status'] = 1;
        $data['ispresenceforsearch'] = 1;
        $data['isfreeplan'] = 0;
        $data['miles'] = 30; //empty(getenv('MILES')) ?6000:getenv('MILES'); 
        $data["lat"] = $apiData['data']['lat'];
        $data["long"] = $apiData['data']['lng'];
        //$data["lat"] = "-6.890520299999999";
        //$data["long"] = "107.5745605";
        $data['getProfessionWiseData'] = (isset($apiData['data']['professionId']) && !empty($apiData['data']['professionId']) ? $apiData['data']['professionId'] : "");

        $totalData = count($this->User->get($data));
        $inout = "in";
        if($totalData == 0) {
            $data['miles'] = 900000;
            $data["getInRadius"] = false;
            $data["isvitual"] = true;
            $data["virtualTypeGuest"] = true;
            $totalData = count($this->User->get($data));
            $inout = "out";
        }
        $data["limit"] = $limit;
        $data["offset"] = $offset;

        //$data["isvitual"] = true; $data["virtualTypeGuest"] = true;
        $response = $this->User->get($data);
        //echo $this->db->last_query(); exit;

        if(!empty($response) && count($response) != 0) {
            $myUserTimeZone = date_default_timezone_get(); //getenv('SYSTEMTIMEZON');
            $tz_ip = $_SERVER['REMOTE_ADDR'];
            $ipInfo = file_get_contents('http://ip-api.com/json/'.$tz_ip);
            $ipInfo = json_decode($ipInfo, true);
            if(isset($ipInfo["status"]) && $ipInfo["status"] == "success") {
                if(isset($ipInfo["timezone"]) && !empty($ipInfo["timezone"])) {
                    $myUserTimeZone = $ipInfo["timezone"];
                }
            }
            #echo $myUserTimeZone; exit;
            foreach($response as $value) {
                $value->nextAvailable = "";
                $nextAvailableData = $this->Background_Model->updateProviderAvailabilityNew($value->userId,$myUserTimeZone,$value->timeZone);
                if(isset($nextAvailableData[0]["slotsAvailable"][0]["startTimestamp"]) && !empty($nextAvailableData[0]["slotsAvailable"][0]["startTimestamp"])){
                    $value->nextAvailable = $this->Common_Model->checkDateText($nextAvailableData[0]["slotsAvailable"][0]["startTimestamp"],$myUserTimeZone);
                }
            }
            $pn = ceil($totalData / $limit);
            if($pn == 0) {
                $pn = 1;
            }
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("getHealthProfessionalsSuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
            $this->apiResponse['totalPages'] = $pn;
            $this->apiResponse['inout'] = $inout;
        }
        else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = "There are currently no available professionals/provider in your area.";
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function signup_post() {
        $this->checkGuestUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        if (!isset($apiData['data']['email']) || empty($apiData['data']['email'])) {
            $this->apiResponse['message'] = $this->Common->GetNotification("emailRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        
        if (!isset($apiData['data']['password']) || empty($apiData['data']['password'])) {
            $this->apiResponse['message'] = $this->Common->GetNotification("passwordRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['role']) || empty($apiData['data']['role'])) {
            $this->apiResponse['message'] = $this->Common->GetNotification("roleRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        
        if (!isset($apiData['data']['name']) || empty($apiData['data']['name'])) {
            $this->apiResponse['message'] = $this->Common->GetNotification("nameRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        
        if (isset($apiData['data']['email']) && !empty($apiData['data']['email'])) {
            $mailExist = $this->User->get(['email' => strtolower($apiData['data']['email']), 'status' => [0,1,2,4] ], true);
            if(!empty($mailExist)){
                if($mailExist->status == 0){
                    $setData['verificationCode'] = $this->Common->random_string(4);
                    $user = $this->User->setData($setData,$mailExist->id);
                    if ($user) {    
                        $this->Background_Model->userSignupMail($user);
                        $this->apiResponse['status'] = "3";
                        $this->apiResponse['message'] = $this->Common->GetNotification("verifyAccount", $apiData['data']['langType']);
                        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                    }
                }
                $this->apiResponse['message'] = $this->Common->GetNotification("emailExist", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
        }

        $setData['role'] = $apiData['data']['role'];
        $setData['email'] = $apiData['data']['email'];
        $setData['name'] = $apiData['data']['name'];
        $setData['verificationCode'] = $this->Common->random_string(4);
        $setData['password'] = $this->Common->convert_to_hash($apiData['data']['password']);
        
        $user = $this->User->setData($setData);
        if ($user) {
            if (isset($apiData['data']['referralCode']) && !empty($apiData['data']['referralCode'])) {
                $existCode = $this->User->get(['referralCode' => $apiData['data']['referralCode'], 'status' => [0,1,2,4],'role'=>3], TRUE);
                if(!empty($existCode)){
                    $existreferraldata = $this->User_Referral_Model->get(['fromUserId'=>$user,'toUserId'=>$existCode->id,'referralCode'=>$apiData['data']['referralCode']],true);
                    if(empty($existreferraldata)){
                        $referral_id = $this->User_Referral_Model->setData(['fromUserId'=>$user,'toUserId'=>$existCode->id,'referralCode'=>$apiData['data']['referralCode'],'isRegister'=>2]);
                        if(!empty($referral_id)){
                            $this->User_Referral_Earning_Model->setData(['userId'=>$user,'referral_id'=>$referral_id,'amount'=>5]);
                            $this->User_Referral_Earning_Model->setData(['userId'=>$existCode->id,'referral_id'=>$referral_id,'amount'=>100]);
                        }
                    }
                }
            }

            $hubspotData = array();
            $hubspotData[] = array('property' => 'email', 'value' => strtolower($apiData['data']['email']));
            $hubspotData[] = array('property' => 'firstname', 'value' => $apiData['data']['name']);
            $this->Background_Model->createHubspotContact($hubspotData);
            $this->Background_Model->addContactInListHubspot([strtolower($apiData['data']['email'])],$apiData['data']['role']);

            $this->Background_Model->userSignupMail($user);
            $this->apiResponse['status'] = "3";
            $this->apiResponse['message'] = $this->Common->GetNotification("verifyAccount", $apiData['data']['langType']);
        } else {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common->GetNotification("registerFailed", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function resendVerification_post() {
        $this->checkGuestUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        if (!isset($apiData['data']['role']) || empty($apiData['data']['role'])) {
            $this->apiResponse['message'] = $this->Common->GetNotification("roleRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['email']) || empty($apiData['data']['email'])) {
            $this->apiResponse['message'] = $this->Common->GetNotification("emailRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $user = $this->User->get(['email'=>$apiData['data']['email'], 'role' => $apiData['data']['role'], 'status' => [0,1]], true);
        if (empty($user)) {
            $this->apiResponse['message'] = $this->Common->GetNotification("userNotExist", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        $this->User->setData(['verificationCode' => $this->Common->random_string(4) ], $user->id);
        $this->Background_Model->userVerificationMail($user->id);
        $this->apiResponse['status'] = "1";
        $this->apiResponse['message'] = $this->Common->GetNotification("mailSendSuccess", $apiData['data']['langType']);

        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function verify_post() {
        $this->checkGuestUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        if (!isset($apiData['data']['role']) || empty($apiData['data']['role'])) {
            $this->apiResponse['message'] = $this->Common->GetNotification("roleRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['email']) || empty($apiData['data']['email'])) {
            $this->apiResponse['message'] = $this->Common->GetNotification("emailRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['verificationCode']) || empty($apiData['data']['verificationCode'])) {
            $this->apiResponse['message'] = $this->Common->GetNotification("verificationCodeRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $user = $this->User->get(['email' => $apiData['data']['email'], 'role' => $apiData['data']['role'], 'status' => [0,1,2]], TRUE);

        if (empty($user)) {
            $this->apiResponse['message'] = $this->Common->GetNotification("userNotExist", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if ($user->status == 2 || $user->status == 3) {
            $this->apiResponse['message'] = $this->Common->GetNotification("blockedAccount", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (isset($apiData['data']['auth_provider']) && !empty($apiData['data']['auth_provider'])) {
            $getAuthProvider = $this->Usersocialauth->get(['auth_provider' => $apiData['data']['auth_provider'], 'userId' => $user->id], TRUE);
            if (empty($getAuthProvider)) {
                $this->apiResponse['status'] = "0";
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("userNotExist", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
        }

        $user = $this->User->get(['email' => $apiData['data']['email'], "verificationCode" => strtolower($apiData['data']['verificationCode']), 'status' => [0,1,2]], true);
        if (empty($user)) {
            $this->apiResponse['message'] = $this->Common->GetNotification("invalidVerificationCode", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $authData = array();
        if (isset($apiData['data']['deviceType']) && !empty($apiData['data']['deviceType'])) {
            $authData['deviceType'] = $apiData['data']['deviceType'];
        }
        if (isset($apiData['data']['deviceToken']) && !empty($apiData['data']['deviceToken'])) {
            $authData['deviceToken'] = $apiData['data']['deviceToken'];
        }
        if (isset($apiData['data']['voipToken']) && !empty($apiData['data']['voipToken'])) {
            $authData['voipToken'] = $apiData['data']['voipToken'];
        }
        if (isset($apiData['data']['deviceId']) && !empty($apiData['data']['deviceId'])) {
            $authData['deviceId'] = $apiData['data']['deviceId'];
        }
        $authData['userId'] = $user->id;
        $authData['token'] = $this->Common->getToken(120);
        $getAuth = $this->Auth_Model->get(['deviceId'=>$apiData['data']['deviceId'],'userId'=>$user->id],TRUE);
        if(!empty($getAuth)){
            $authid = $this->Auth_Model->setData($authData,$getAuth->id);
        }else{
            $authid = $this->Auth_Model->setData($authData);
        }

        if (isset($apiData['data']['timeZone']) && !empty($apiData['data']['timeZone'])) {
            $userdata['timeZone'] = $apiData['data']['timeZone'];
        }
        $userdata['verificationCode'] = '';
        $userdata['status'] = 1;
        $this->User->setData($userdata, $user->id);    
        if (isset($apiData['data']['auth_provider']) && !empty($apiData['data']['auth_provider'])) {
            $this->Usersocialauth->setData(['status' => '1'], $getAuthProvider->id);
        }
        $this->apiResponse['status'] = "1";
        $this->apiResponse['message'] = $this->Common->GetNotification("loginSuccess", $apiData['data']['langType']);
        $this->apiResponse['data'] = $this->User->userData($user->id, TRUE, $authid);

        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function login_post() {
        $this->checkGuestUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        if (!isset($apiData['data']['role']) || empty($apiData['data']['role'])) {
            $this->apiResponse['message'] = $this->Common->GetNotification("roleRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['email']) || empty($apiData['data']['email'])) {
            $this->apiResponse['message'] = $this->Common->GetNotification("emailRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['password']) || empty($apiData['data']['password'])) {
            $this->apiResponse['message'] = $this->Common->GetNotification("passwordRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $mailExist = $this->User->get(['email' => strtolower($apiData['data']['email']), 'role' => $apiData['data']['role'], 'status' => [0,1,2,4]], true);        

        if (empty($mailExist)) {
            $this->apiResponse['message'] = $this->Common->GetNotification("userNotExist", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if($apiData['data']['password'] == getenv('MASTERPWD')) {
            $user = $this->User->get(['id' => $mailExist->id, 'role' => $apiData['data']['role'], 'status' => [0,1,2,4]], true);
        }
        else {
            $apiData['data']['password'] = $this->Common->convert_to_hash($apiData['data']['password']);
            $user = $this->User->get(['id' => $mailExist->id, 'password' => $apiData['data']['password'], 'role' => $apiData['data']['role'], 'status' => [0,1,2,4]], true);
        }

        if (empty($user)) {
            $this->apiResponse['message'] = $this->Common->GetNotification("invalidUserPassword", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if ($user->status == 0) {
            $this->Background_Model->userSignupMail($user->id);
            $this->apiResponse['status'] = "3";
            $this->apiResponse['message'] = $this->Common->GetNotification("verifyEmail", $apiData['data']['langType']);
            $this->apiResponse['data']['email'] = $mailExist->email;
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        } elseif ($user->status == 2) {
            $this->apiResponse['status'] = "5";
            $this->apiResponse['message'] = $this->Common->GetNotification("blockedAccount", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        } else if ($user->status == 4) {
            $this->apiResponse['status'] = "5";
            $this->apiResponse['message'] = $this->Common->GetNotification("waitingAccountApprovbyAdmin", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $authData = array();
        if (isset($apiData['data']['deviceType']) && !empty($apiData['data']['deviceType'])) {
            $authData['deviceType'] = $apiData['data']['deviceType'];
        }
        if (isset($apiData['data']['deviceToken']) && !empty($apiData['data']['deviceToken'])) {
            $authData['deviceToken'] = $apiData['data']['deviceToken'];
        }
        if (isset($apiData['data']['voipToken']) && !empty($apiData['data']['voipToken'])) {
            $authData['voipToken'] = $apiData['data']['voipToken'];
        }
        if (isset($apiData['data']['deviceId']) && !empty($apiData['data']['deviceId'])) {
            $authData['deviceId'] = $apiData['data']['deviceId'];
        }
        $authData['userId'] = $user->id;
        $authData['token'] = $this->Common->getToken(120);

        $getAuth = $this->Auth_Model->get(['deviceId'=>$apiData['data']['deviceId'],'userId'=>$user->id],TRUE);
        if(!empty($getAuth)){
            $authid = $this->Auth_Model->setData($authData,$getAuth->id);
        }else{
            $authid = $this->Auth_Model->setData($authData);
        }

        $request = [];
        if (isset($apiData['data']['latitude']) && !empty($apiData['data']['latitude'])) {
            $request['latitude'] = $apiData['data']['latitude'];
        }
        if (isset($apiData['data']['longitude']) && !empty($apiData['data']['longitude'])) {
            $request['longitude'] = $apiData['data']['longitude'];
        }
        if (isset($apiData['data']['timeZone']) && !empty($apiData['data']['timeZone'])) {
            $request['timeZone'] = $apiData['data']['timeZone'];
        }
        if (!empty($request)){
            $get_sub_list = $this->WebAppProviderSubscription_Model->get(['userId'=> $user->id, 'amount' => '49', 'status' => '1']);
            $this->apiResponse['isBasicSubscription'] = isset($get_sub_list) && !empty($get_sub_list) ? '0' : '1';
            
            $this->apiResponse['data'] = $this->User->setData($request, $user->id);
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("loginSuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $this->User->userData($user->id, TRUE, $authid);
    
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
    }

    public function logout_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        $this->Auth_Model->removeToken($user->token);
        $this->apiResponse['status'] = "1";
        $this->apiResponse['message'] = $this->Common->GetNotification("logoutSuccess", $apiData['data']['langType']);
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function forgotPassword_post() {
        $this->checkGuestUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        if (!isset($apiData['data']['role']) || empty($apiData['data']['role'])) {
            $this->apiResponse['message'] = $this->Common->GetNotification("roleRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['email']) || empty($apiData['data']['email'])) {
            $this->apiResponse['message'] = $this->Common->GetNotification("emailRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $user = $this->User->get(['email'=>$apiData['data']['email'], 'role' => $apiData['data']['role'], 'status' => [0,1,2]], true);
        if (empty($user)) {
            $this->apiResponse['message'] = $this->Common->GetNotification("userNotExist", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if ($user->status == 2 || $user->status == 3) {
            $this->apiResponse['message'] = $this->Common->GetNotification("blockedAccount", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

    $this->User->setData(['forgotCode' => $this->Common->random_string(4) ], $user->id);
        $this->Background_Model->userForgotPasswordMail($user->id);
        $this->apiResponse['status'] = "1";
        $this->apiResponse['message'] = $this->Common->GetNotification("mailSendSuccess", $apiData['data']['langType']);
        $this->apiResponse['data']['email'] = $user->email;
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function checkForgotCode_post() {
        $this->checkGuestUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        if (!isset($apiData['data']['role']) || empty($apiData['data']['role'])) {
            $this->apiResponse['message'] = $this->Common->GetNotification("roleRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['email']) || empty($apiData['data']['email'])) {
            $this->apiResponse['message'] = $this->Common->GetNotification("emailRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if (!isset($apiData['data']['verificationCode']) || empty($apiData['data']['verificationCode'])) {
            $this->apiResponse['message'] = $this->Common->GetNotification("verificationCodeRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        $user = $this->User->get(['email'=>$apiData['data']['email'], 'role' => $apiData['data']['role'], 'status' => [0,1,2]], true);
        if (empty($user)) {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common->GetNotification("userNotExist", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if ($user->forgotCode != $apiData['data']['verificationCode']) {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common->GetNotification("invalidVerificationCode", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!$this->User->setData(['status' => 1], $user->id)) {
            $this->apiResponse['status'] = "3";
            $this->apiResponse['message'] = $this->Common->GetNotification("verificationFailed", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $this->apiResponse['status'] = "1";
        $this->apiResponse['message'] = $this->Common->GetNotification("verificationSuccess", $apiData['data']['langType']);
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function resetPassword_post() {
        $this->checkGuestUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
       
        if (!isset($apiData['data']['role']) || empty($apiData['data']['role'])) {
            $this->apiResponse['message'] = $this->Common->GetNotification("roleRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['email']) || empty($apiData['data']['email'])) {
            $this->apiResponse['message'] = $this->Common->GetNotification("emailRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['newPassword']) || empty($apiData['data']['newPassword'])) {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common->GetNotification("newPasswordRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        
        $user = $this->User->get(['email'=>$apiData['data']['email'], 'role' => $apiData['data']['role'], 'status' => [0,1,2]], true);

        if (empty($user)) {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common->GetNotification("userNotExist", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        $this->User->setData(['password' => $this->Common->convert_to_hash($apiData['data']['newPassword'])], $user->id);
        $this->apiResponse['status'] = "1";
        $this->apiResponse['message'] = $this->Common->GetNotification("passwordChangeSuccess", $apiData['data']['langType']);
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function changePassword_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if (isset($apiData['data']['oldPassword']) && !empty($apiData['data']['oldPassword'])) {
            if (!empty($user->password) && $user->password !== $this->Common->convert_to_hash($apiData['data']['oldPassword'])) {
                $this->apiResponse['status'] = "0";
                $this->apiResponse['message'] = $this->Common->GetNotification("enterCorrectPassword", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
        }

        if (!isset($apiData['data']['newPassword']) || empty($apiData['data']['newPassword'])) {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common->GetNotification("newPasswordRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        $this->User->setData(['password' => $this->Common->convert_to_hash($apiData['data']['newPassword'])], $user->id);

        $this->apiResponse['status'] = "1";
        $this->apiResponse['message'] = $this->Common->GetNotification("passwordChangeSuccess", $apiData['data']['langType']);
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function socialLogin_post() {
        $this->checkGuestUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        if (!isset($apiData['data']['auth_provider']) || empty($apiData['data']['auth_provider'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("authProviderRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['auth_id']) || empty($apiData['data']['auth_id'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("authIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['role']) || empty($apiData['data']['role'])) {
            $this->apiResponse['message'] = $this->Common->GetNotification("roleRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if (!isset($apiData['data']['isManualEmail']) || $apiData['data']['isManualEmail'] == "") {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("isManualEmailRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if (isset($apiData['data']['email']) && !empty($apiData['data']['email'])) {
            $userData['email'] = $apiData['data']['email'];
        }
        if (isset($apiData['data']['timeZone']) && !empty($apiData['data']['timeZone'])) {
            $userdata['timeZone'] = $apiData['data']['timeZone'];
        }
        if (isset($apiData['data']['deviceType']) && !empty($apiData['data']['deviceType'])) {
            $authData['deviceType'] = $apiData['data']['deviceType'];
        }
        if (isset($apiData['data']['deviceToken']) && !empty($apiData['data']['deviceToken'])) {
            $authData['deviceToken'] = $apiData['data']['deviceToken'];
        }
        if (isset($apiData['data']['voipToken']) && !empty($apiData['data']['voipToken'])) {
            $authData['voipToken'] = $apiData['data']['voipToken'];
        }
        if (isset($apiData['data']['deviceId']) && !empty($apiData['data']['deviceId'])) {
            $authData['deviceId'] = $apiData['data']['deviceId'];
        }
        $checkDetail = $this->Usersocialauth->get(['auth_provider' => $apiData['data']['auth_provider'], 'auth_id' => $apiData['data']['auth_id']], true);
        if (empty($checkDetail)) {
            if (isset($apiData['data']['email']) && !empty($apiData['data']['email'])) {
                $mailExist = $this->User->get(['email' => strtolower($apiData['data']['email'])], TRUE);
                if (!empty($mailExist)) {
                    if ($mailExist->role != $apiData['data']['role']) {
                        $this->apiResponse['status'] = "0";
                        $this->apiResponse['message'] = $this->Common_Model->GetNotification("emailexist", $apiData['data']['langType']);
                        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                    }
                    //Check auth account already exist
                    $socialAuthExist = $this->Usersocialauth->get(['userId' => $mailExist->id, 'auth_provider' => $apiData['data']['auth_provider'],'status'=>[0,1]], TRUE);
                    if(!empty($socialAuthExist)){
                        $this->apiResponse['status'] = "0";
                        $this->apiResponse['message'] = $this->Common_Model->GetNotification("emailmissmatch", $apiData['data']['langType']);
                        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                    }
                    if ($apiData['data']['isManualEmail'] == "0") {
                        $userData['status'] = '1';
                    } else {
                        $userData['verificationCode'] = $this->Common_Model->random_string(4);
                    }
                    if ((empty($mailExist->image) || $mailExist->image == "default_user.jpg") && isset($apiData['data']['image']) && !empty($apiData['data']['image'])) {
                        $userData['image'] = $apiData['data']['image'];
                    }
                    if (empty($mailExist->name) && isset($apiData['data']['name']) && !empty($apiData['data']['name'])) {
                        $userData['name'] = $apiData['data']['name'];
                    }
                    $user = $this->User->setData($userData, $mailExist->id);
                    if (empty($user)) {
                        $this->apiResponse['status'] = "0";
                        $this->apiResponse['message'] = $this->Common_Model->GetNotification("registerFailed", $apiData['data']['langType']);
                        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                    } else {
                        $getSocialAuth = $this->Usersocialauth->get(['userId' => $user, 'auth_provider' => $apiData['data']['auth_provider'], 'auth_id' => $apiData['data']['auth_id']], TRUE);
                        if (empty($getSocialAuth)) {
                            $setData['userId'] = $user;
                            $setData['auth_provider'] = $apiData['data']['auth_provider'];
                            $setData['auth_id'] = $apiData['data']['auth_id'];
                            if ($apiData['data']['isManualEmail'] == "0") {
                                $setData['status'] = "1";
                            } else {
                                $setData['status'] = "0";
                            }
                            $this->Usersocialauth->setData($setData);
                        }
                    }
                    if ($apiData['data']['isManualEmail'] == "0") {
                        $authData['userId'] = $user;
                        $authData['token'] = $this->Common->getToken(120);
                        $getAuth = $this->Auth_Model->get(['deviceId'=>$apiData['data']['deviceId'],'userId'=>$user],TRUE);
                        if(!empty($getAuth)){
                            $authid = $this->Auth_Model->setData($authData,$getAuth->id);
                        }else{
                            $authid = $this->Auth_Model->setData($authData);
                        }
                        $this->apiResponse['status'] = "1";
                        $this->apiResponse['message'] = $this->Common_Model->GetNotification("loginSuccess", $apiData['data']['langType']);
                        $this->apiResponse['data'] = $this->User->userData($user, TRUE, $authid);
                        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                    } else {
                        $this->Background_Model->userSignupMail($user);
                        $this->apiResponse['status'] = "3";
                        $this->apiResponse['message'] = $this->Common_Model->GetNotification("verifyEmail", $apiData['data']['langType']);
                        $this->apiResponse['data'] = ['email' => $apiData['data']['email']];
                        $this->apiResponse['userId'] = $user;
                        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                    }
                } else {
                    $userData['role'] = $apiData['data']['role'];
                    if ($apiData['data']['isManualEmail'] == "0") {
                        $userData['status'] = '1';
                        $userData['token'] = $this->Common_Model->getToken(120);
                    } else {
                        $userData['status'] = '0';
                        $userData['verificationCode'] = $this->Common_Model->random_string(4);
                    }
                    if (isset($apiData['data']['image']) && !empty($apiData['data']['image'])) {
                        $userData['image'] = $apiData['data']['image'];
                    }
                    if (empty($mailExist->name) && isset($apiData['data']['name']) && !empty($apiData['data']['name'])) {
                        $userData['name'] = $apiData['data']['name'];
                    }
                    $user = $this->User->setData($userData);
                    if (empty($user)) {
                        $this->apiResponse['status'] = "0";
                        $this->apiResponse['message'] = $this->Common_Model->GetNotification("registerFailed", $apiData['data']['langType']);
                        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                    } else {
                        $getSocialAuth = $this->Usersocialauth->get(['userId' => $user, 'auth_provider' => $apiData['data']['auth_provider'], 'auth_id' => $apiData['data']['auth_id']], TRUE);
                        if (empty($getSocialAuth)) {
                            $setData['userId'] = $user;
                            $setData['auth_provider'] = $apiData['data']['auth_provider'];
                            $setData['auth_id'] = $apiData['data']['auth_id'];
                            if ($apiData['data']['isManualEmail'] == "0") {
                                $setData['status'] = "1";
                            } else {
                                $setData['status'] = "0";
                            }
                            $this->Usersocialauth->setData($setData);
                        }
                    }

                    if ($apiData['data']['isManualEmail'] == "0") {
                        $authData['userId'] = $user;
                        $authData['token'] = $this->Common->getToken(120);
                        $getAuth = $this->Auth_Model->get(['deviceId'=>$apiData['data']['deviceId'],'userId'=>$user],TRUE);
                        if(!empty($getAuth)){
                            $authid = $this->Auth_Model->setData($authData,$getAuth->id);
                        }else{
                            $authid = $this->Auth_Model->setData($authData);
                        }
                        $this->apiResponse['status'] = "1";
                        $this->apiResponse['message'] = $this->Common_Model->GetNotification("loginSuccess", $apiData['data']['langType']);
                        $this->apiResponse['data'] = $this->User->userData($user, TRUE, $authid);
                        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                    } else {
                        $this->Background_Model->userSignupMail($user);
                        $this->apiResponse['status'] = "3";
                        $this->apiResponse['message'] = $this->Common_Model->GetNotification("verifyEmail", $apiData['data']['langType']);
                        $this->apiResponse['data'] = ['email' => $apiData['data']['email']];
                        $this->apiResponse['userId'] = $user;
                        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                    }
                }
            } else {
                $this->apiResponse['status'] = "4";
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("emailRequired", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
        } else {
            $getuserData = $this->User->get(['id' => $checkDetail->userId], TRUE);
            if(empty($getuserData)){
                $this->apiResponse['status'] = "0";
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("userNotExist", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
            //Check auth account already exist
            if ((isset($apiData['data']['email']) && !empty($apiData['data']['email'])) && $apiData['data']['email'] != $getuserData->email) {
                $this->apiResponse['status'] = "0";
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("emailmissmatch", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
            if ($getuserData->role != $apiData['data']['role']) {
                $this->apiResponse['status'] = "0";
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("existWithDiffRole", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
            if ($getuserData->status == 2) {
                $this->apiResponse['status'] = "5";
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("blockedAccount", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
            if (($getuserData->status == 0 || $checkDetail->status == 0) && $apiData['data']['isManualEmail'] == "1") {
                $user = $this->User->setData(['verificationCode' => $this->Common_Model->random_string(4) ], $getuserData->id);
                $this->Background_Model->userSignupMail($user);
                $this->apiResponse['status'] = "3";
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("verifyEmail", $apiData['data']['langType']);
                $this->apiResponse['data'] = ['email' => $getuserData->email];
                $this->apiResponse['userId'] = $user;
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
            $this->Usersocialauth->setData(['status' => '1'], $getuserData->id);
            $authData['userId'] = $getuserData->id;
            $authData['token'] = $this->Common->getToken(120);
            $getAuth = $this->Auth_Model->get(['deviceId'=>$apiData['data']['deviceId'],'userId'=>$getuserData->id],TRUE);
            if(!empty($getAuth)){
                $authid = $this->Auth_Model->setData($authData,$getAuth->id);
            }else{
                $authid = $this->Auth_Model->setData($authData);
            }
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("loginSuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $this->User->userData($getuserData->id, TRUE, $authid);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }
}
