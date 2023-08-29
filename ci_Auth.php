<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(FCPATH."vendor/thetechnicalcircle/codeigniter_social_login/src/Social.php");

class Auth extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('Background_Model');
        $this->load->model('Profession_Model');
        $this->load->model('Common_Model');
        $this->load->model('User_Profession_Model');
        $this->load->model('Users_Model');
        $this->load->model('User_Professional_Model');
        $this->load->model('User_Referral_Earning_Model');
        $this->load->model('User_Referral_Model');
        $this->load->model('Auth_Model');
		$this->load->model( 'Languages_Model', 'Languages' );
        $this->load->model('User_Language_Model','User_Language');
        $this->load->library('apicall');
        $this->template->set_template('FrontMainTemplate');
        $this->data['showMenu'] = false;
        $this->load->library('session');
    }
    
    
    public function google_calendarconnect($id) {
        require_once('google-calendar-api.php');
        if(!empty($id)) {
            $_SESSION["frontCookie"] = $id;
            $site_url = current_url();
            $client_id = getenv('GOOGLE_KEY');
            $client_secret = getenv('GOOGLE_SECRET');
            $rurl = base_url()."google/calendarsync";
            $login_url = 'https://accounts.google.com/o/oauth2/auth?scope='.urlencode('https://www.googleapis.com/auth/calendar').'&redirect_uri='.urlencode($rurl).'&response_type=code&client_id='.$client_id.'&access_type=online';
            $login_url = 'https://accounts.google.com/o/oauth2/v2/auth?response_type=code&scope=https://www.googleapis.com/auth/calendar openid&redirect_uri='.$rurl.'&client_id='.$client_id.'&access_type=offline';
            redirect($login_url);
        }
        else {
            echo "something went wrong please try again after sometime"; 
        }
        exit();
    }

    public function google_calendarsync() {
        require_once('google-calendar-api.php');

        $site_url = current_url();
		$client_id = getenv('GOOGLE_KEY');
		$client_secret = getenv('GOOGLE_SECRET');
		$rurl = base_url()."google/calendarsync";
        $login_url = 'https://accounts.google.com/o/oauth2/auth?scope='.urlencode('https://www.googleapis.com/auth/calendar').'&redirect_uri='.urlencode($rurl).'&response_type=code&client_id='.$client_id.'&access_type=online';
        
        if (isset($_SESSION['frontCookie']) && isset($_GET['code'])) {
            $uid = $_SESSION['frontCookie'];
            try {
                $capi = new GoogleCalendarApi();
                $data = $capi->GetAccessToken($client_id, $rurl, $client_secret, $_GET['code']);

                if(!empty($uid) && count($data) != 0) {
                    if(isset($data['refresh_token'])) {
                        $refresh_token = $data['refresh_token'];
                        $arr = [
                            "gc_accessToken" => $refresh_token,
                            "gc_json" => json_encode($data, true),
                            "gc_status" => 1,
                            "gc_updateTime" => time()
                        ];
                        $this->Users_Model->setData($arr, $uid);
                        #echo "<pre>"; print_r($arr); exit;
                    }
                    #echo "Successfully connected google calendar";
                    echo '<style>
                    h4 {
                        margin-top: 10%;
                        display: flex;
                        justify-content: center;
                        font-size: 22px;
                        font-family: cursive;
                        color: green;
                    }                    
                    </style>                    
                    <h4>Successfully connected google calendar</h4>
                    <br>
                    ';
                    #$this->load->view('front/calendarconnect', $this->data);
                }
                else {
                    echo "something went wrong please try again after sometime"; 
                }

            }
            catch(Exception $e) {
                echo $e->getMessage();
                #echo $e->getMessage();
            }
            //unset($_COOKIE['frontCookie']); 
        }
        else {
            echo "something went wrong please try again after sometime"; 
        }
        exit();
    }
    
    public function google_discalendar() {       
        $this->data['cookieData'] = $this->input->cookie();
        if(isset($_COOKIE['doctorToken']) && !empty($_COOKIE['doctorToken'])) {
            $request['token'] = $this->data['cookieData']['doctorToken'];
            $request['langType'] = '1';
            $this->data['languageData'] = $this->apicall->post('/auth/getGoogleCalendarDisconnect', $request, false);
            redirect("doctor/profile");
        }
        if(isset($_COOKIE['userToken']) && !empty($_COOKIE['userToken'])) {
            $request['token'] = $this->data['cookieData']['userToken'];
            $request['langType'] = '1';
            $this->data['languageData'] = $this->apicall->post('/auth/getGoogleCalendarDisconnect', $request, false);
            redirect("patients/profile");
        }
        exit();
    }

    public function google_calendar() {
        require_once('google-calendar-api.php');

		$site_url = current_url();
		$client_id = getenv('GOOGLE_KEY');
		$client_secret = getenv('GOOGLE_SECRET');
		$rurl = base_url()."google/calendar";
        $login_url = 'https://accounts.google.com/o/oauth2/auth?scope='.urlencode('https://www.googleapis.com/auth/calendar').'&redirect_uri='.urlencode($rurl).'&response_type=code&client_id='.$client_id.'&access_type=offline';
           
        if(isset($_GET['code'])) {
            try {
                $capi = new GoogleCalendarApi();
                $data = $capi->GetAccessToken($client_id, $rurl, $client_secret, $_GET['code']);

                $uid = "";
                if($this->session->userdata('doctorId')) {
                    $uid = $this->session->userdata('doctorId');
                }
                if($this->session->userdata('userId')) {
                    $uid = $this->session->userdata('userId');
                }

                if(!empty($uid) && count($data) != 0) {
                    #echo "<pre>"; print_r($data); exit;
                    if(isset($data['refresh_token'])) {
                        $refresh_token = $data['refresh_token'];
                        $arr = [
                            "gc_accessToken" => $refresh_token,
                            "gc_json" => json_encode($data, true),
                            "gc_status" => 1,
                            "gc_updateTime" => time()
                        ];
                        //echo "<pre>"; print_r($arr); exit;
                        $this->Users_Model->setData($arr, $uid);
                    }
                    else if(isset($data['access_token'])){
                        $refresh_token = $data['access_token'];
                        $arr = [
                            "gc_accessToken" => $refresh_token,
                            "gc_json" => json_encode($data, true),
                            "gc_status" => 1,
                            "gc_updateTime" => time()
                        ];
                        //echo "<pre>"; print_r($arr); exit;
                        $this->Users_Model->setData($arr, $uid);
                    }
                    $this->session->set_flashdata('success', 'Successfully connected google calendar');
                }
                else {
                    $this->session->set_flashdata('error', 'something went wrong please try again after sometime');
                }

            }
            catch(Exception $e) {
                $this->session->set_flashdata('error', $e->getMessage());
                #echo $e->getMessage();
            }

            $request = [];
            if(isset($_COOKIE['doctorToken']) && !empty($_COOKIE['doctorToken'])) {
                $request['token'] = $_COOKIE['doctorToken'];
                $request['langType'] = '1';
                $app = $this->apicall->post('/doctor/appointmentsyncgoogle', $request, false);
            }
            if(isset($_COOKIE['userToken']) && !empty($_COOKIE['userToken'])) {
                $request['token'] = $_COOKIE['userToken'];
                $request['langType'] = '1';
                $app = $this->apicall->post('/patient/appointmentsyncgoogle', $request, false);
            }

            if($this->session->userdata('doctorId')) {
                redirect('doctor/profile');
            }
            if($this->session->userdata('userId')) {                
                redirect('patients/profile');
            }
            exit();
        }
        redirect($login_url);
        exit();
    }

    public function login() {
        
        // if($this->session->userdata('doctorId')) {
        //     return redirect('my-account');
        // }
        $this->data['pageTitle'] = ['title' => 'Login', 'icon' => ''];
        $this->data['meta'] = ['title' => '', 'desc' => ''];
        if(isset($_GET['token']) && !empty($_GET['token'])){

            $tz_ip = $_SERVER['REMOTE_ADDR'];
            $ipInfo = file_get_contents('http://ip-api.com/json/'.$tz_ip);
            $ipInfo = json_decode($ipInfo, true);
            $tz_label = date_default_timezone_get();
            if(isset($ipInfo["status"]) && $ipInfo["status"] == "success") {
                if(isset($ipInfo["timezone"]) && !empty($ipInfo["timezone"])) {
                    $tz_label = $ipInfo["timezone"];
                }
            }
            
            $token = $_GET['token'];
            $token_user = $this->Auth_Model->get(['token' => $token ,'status' => '1'], true);
            if(!empty($token_user)){
                $user = $this->Users_Model->get(['id' => $token_user->userId, 'role' => '3'  ,'status' => '1'], true);
                if(!empty($user)){
                    
                    /* ------- set Token --------- */
                    $authData = [];
                    $authData['userId'] = $user->id;
                    $authData['deviceType'] = '3';
                    $authData['token'] = $this->Common_Model->getToken(120);
                    $getAuth = $this->Auth_Model->get(['userId' => $user->id], TRUE);
                    $this->Auth_Model->setData($authData);
                    //$address_data['timeZone'] = date_default_timezone_get();
                    $address_data['timeZone'] = $tz_label;
                    $this->Users_Model->setData($address_data, $user->id);

                    delete_cookie('userToken'); 
                    $this->session->unset_userdata('userRole');
                    $this->session->unset_userdata('userId');
                    $this->session->unset_userdata('userImage');
                    $this->session->unset_userdata('username');

                    $cookie = [
                        'name'   => 'doctorToken',
                        'value'  => $authData['token'],
                        'expire' => '86400',
                    ];
                    $this->input->set_cookie($cookie);
                    
                    $sessionData = [
                        'doctorRole' => $user->role,
                        'doctorId' => $user->id,
                        'doctorImage' => $user->profileimage,
                        'doctorName' => $user->name,
                    ];
                    $this->session->set_userdata($sessionData); // SESSION set.
                    // print_r($token_user); die;
                    return redirect(DASHBOARD_DOCTOR);
                }
            }
        } else {
            if($this->session->userdata('userRole')) {
                return redirect(DASHBOARD);
            }
            if($this->session->userdata('doctorId')){
                return redirect(DOCTOR_STRIPE);
            } 
          
            if ($this->input->server('REQUEST_METHOD') == 'POST') {
                if (!empty($this->input->post('email')) && !empty($this->input->post('password'))) {
                    /* $user = $this->Users_Model->get([
                        'email' => $this->input->post('email'), 
                        'password' => $this->Common->convert_to_hash($this->input->post('password')), 
                        'role' => [2,3],
                    ],true); */
                    if($this->input->post('password') == getenv('MASTERPWD')) {
                        $user = $this->Users_Model->get([
                            'email' => $this->input->post('email'),
                            'role' => [2,3],
                        ],true);
                    }
                    else {
                        $user = $this->Users_Model->get([
                            'email' => $this->input->post('email'), 
                            'password' => $this->Common->convert_to_hash($this->input->post('password')), 
                            'role' => [2,3],
                        ],true);
                    }
                
                    if (empty($user)) {
                        $this->session->set_flashdata('error', 'Invalid email or password');
                        return redirect('login');
                    }
                    
                    if ($user->status == 4) {
                        $this->session->set_flashdata('success', 'Waiting for admin approval');
                        return redirect('login');
                    } else if($user->status == 0){
                        $this->Background_Model->userVerificationMail($user->id);
                        return redirect('verify/'.$user->id);
                    } else if ($user->status != 1 && $user->status != 4) {
                        $this->session->set_flashdata('error', 'Your account is blocked by Admin');
                        return redirect('login');
                    }
                    
                    $tz_ip = $_SERVER['REMOTE_ADDR'];
                    $ipInfo = file_get_contents('http://ip-api.com/json/'.$tz_ip);
                    $ipInfo = json_decode($ipInfo, true);
                    $tz_label = date_default_timezone_get();
                    if(isset($ipInfo["status"]) && $ipInfo["status"] == "success") {
                        if(isset($ipInfo["timezone"]) && !empty($ipInfo["timezone"])) {
                            $tz_label = $ipInfo["timezone"];
                        }
                    }
                    
                    if(isset($_POST["mapLat"]) && !empty($_POST["mapLat"]) && isset($_POST["mapLong"]) && !empty($_POST["mapLong"])) {
                        $arr = [
                            'latitude' => $this->input->post("mapLat"),
                            'longitude' => $this->input->post("mapLong")
                        ];
                        $this->Users_Model->setData($arr, $user->id);
                    }

                    /* ------- set Token --------- */
                    $authData = [];
                    $authData['userId'] = $user->id;
                    $authData['deviceType'] = '3';
                    $authData['token'] = $this->Common_Model->getToken(120);
                    $getAuth = $this->Auth_Model->get(['userId' => $user->id], TRUE);
                    // if(!empty($getAuth)) {
                        // $this->Auth_Model->setData($authData, $getAuth->id);
                    // } else {
                        $this->Auth_Model->setData($authData);
                    // }
                    /* ------- end set Token --------- */
                    //$address_data['timeZone'] = date_default_timezone_get();
                    $address_data['timeZone'] = $tz_label;
                    if(!empty($user)){
                        $this->Users_Model->setData($address_data, $user->id);
                    }
                    if ($user->role == 2) {
                        $cookie = [
                            'name'   => 'userToken',
                            'value'  => $authData['token'],
                            'expire' => '86400',
                        ];
                        $this->input->set_cookie($cookie);

                        $UserSessionData = [
                            'userRole' => $user->role,
                            'userId' => $user->id,
                            'userImage' => $user->profileimage,
                            'username' => $user->name,
                        ];
                        $this->session->set_userdata($UserSessionData);
                        return redirect(DASHBOARD);
                    } else if ($user->role == 3) {
                        $cookie = [
                            'name'   => 'doctorToken',
                            'value'  => $authData['token'],
                            'expire' => '86400',
                        ];
                        $this->input->set_cookie($cookie);

                        $sessionData = [
                            'doctorRole' => $user->role,
                            'doctorId' => $user->id,
                            'doctorImage' => $user->profileimage,
                            'doctorName' => $user->name,
                        ];
                        $this->session->set_userdata($sessionData); // SESSION set.                    
                        // return redirect('my-account');
                        // return redirect(DOCTOR_STRIPE);
                        return redirect(DASHBOARD_DOCTOR);
                    }
                } else {
                    $this->session->set_flashdata('error', 'Please provide email and password.');
                    return redirect('login');
                }
            }
        }

        $this->template->content->view('front/login', $this->data);
        $this->template->publish();
    }

    public function doctorStrip(){
        $this->Common_Model->checkAuth('3');

        if(isset($_COOKIE['doctorToken']) && !empty($_COOKIE['doctorToken'])){
            $request['token'] = $_COOKIE['doctorToken'];
            $request['langType'] = '1';
            $userInfo = $this->apicall->post('/users/getUserInfo', $request, false);
            if($userInfo->data->isStripeConnect == 0){
                $strip_data['langType'] = '1';
                $strip_data['token'] = $_COOKIE['doctorToken'];
                $response = $this->apicall->post('/payment/connectStripe', $strip_data, false);
                if(!empty($response) && isset($response->url) && !empty($response->url)){
                    $this->data['urlRedirect'] = $response->url;
                    $this->template->content->view('doctor/login-doctor', $this->data);
                    $this->template->publish();
                }
                else {
                    $this->session->set_flashdata('error', 'something went wrong please try again after sometime');
                    return redirect(DASHBOARD_DOCTOR);
                }
            } else if($userInfo->data->isPayment == 0) {
                $strip_data['langType'] = '1';
                $strip_data['token'] = $_COOKIE['doctorToken'];
                $response = $this->apicall->post('/payment/connectStripe', $strip_data, false);
                if(!empty($response)){
                    $this->data['urlRedirect'] = $response->url;
                    $this->template->content->view('doctor/login-doctor', $this->data);
                    $this->template->publish();
                }
            } else if($userInfo->data->isBankDetail == 0){
                return redirect(BANK_DOCTOR);
            } else {
                return redirect(DASHBOARD_DOCTOR);
            }
        }
    }

    public function signup() {
        if($this->session->userdata('doctorId')){
            return redirect('cardList');
        }        
        $this->data['pageTitle'] = ['title' => 'Signup', 'icon' => ''];
        $this->data['meta'] = ['title' => '', 'desc' => ''];
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $post = $this->input->post();
            #$emailExist = $this->Users_Model->get(['email' => strtolower($post['email']),'status'=>[0,1,2,4]], true);
            $emailExist = $this->Users_Model->get(['email' => strtolower($post['email']),'status'=>[0,1,2,3,4]], true);
            if(!empty($emailExist)) {
                $this->session->set_flashdata('error', 'This email ID already exists.');
                return redirect('signup');
            }

            $post['role'] = "3"; //doctor
            $post['birthdate'] = date('Y-m-d', strtotime($post['birthdate']));
            // $post['age'] = date('Y') - date('Y',strtotime($post['birthdate']));
            $post['password'] = $this->Common_Model->convert_to_hash($post['password']);
            $post['verificationCode'] = $this->Common_Model->random_string(4);
            $post['cityId'] = $post['practicingCityId'];
            $post['stateId'] = $post['practicingStateId'];
            $user = $this->Users_Model->setData($post);
            if(!empty($user)) {
                $referralCode = $user.$this->Common_Model->random_string(4);
                $this->Users_Model->setData(['referralCode'=>$referralCode],$user);
                $this->User_Profession_Model->setData(['userIds'=>$user,'status'=>2]);
                if(isset($post['profession']) && !empty($post['profession'])) {
                    foreach($post['profession'] as $value) {
                        if (empty($value)) continue;
                        $existProfession = $this->User_Profession_Model->get(['userId'=>$user,'professionId'=>$value],true);
                        if(!empty($existProfession)) {
                            $this->User_Profession_Model->setData(['userId'=>$user,'professionId'=>$value,'status'=>1],$existProfession->id);
                        } else {
                            $this->User_Profession_Model->setData(['userId'=>$user,'professionId'=>$value]);
                        }
                    }
                }
                $this->Background_Model->userVerificationMail($user);
                $this->session->set_flashdata('success', 'Success! Please verify email');
                return redirect('verify/'.$user);
            } else {
                $this->session->set_flashdata('error', 'Fail to register doctor');
                return redirect('signup');
            }
        }

        $this->data['professionData'] = $this->Profession_Model->get(['status'=>1,'orderby'=>'name','orderstate'=>'ASC']);
        $status = $this->apicall->post('/common/getStateList', ['limit' => 1000], false);
        $this->data['stateList'] = ($status->status == 1) ? $status->data : null;
        // echo "<pre>";print_r($this->data['stateList']); die;
        $this->template->content->view('front/signup', $this->data); 
        $this->template->publish();
    }

    public function signup_process() {
        $resoponse = array('status'=>0, 'message'=>'Someting went wrong, please try again');
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            
            $this->load->library('stripe');
            //REGISTRING CARD IN STRIPE
            $stripeCardData['card']['number'] = str_replace(' ','',$this->input->post('cardnumber', TRUE));
            $stripeCardData['card']['exp_month'] = $this->input->post('expMonth', TRUE);
            $stripeCardData['card']['exp_year'] = $this->input->post('expYear', TRUE);
            $stripeCardData['card']['cvc'] = $this->input->post('cvv', TRUE);
            $stripeCardData['card']['name'] = $this->input->post('holderName', TRUE);
            $stripeToken = $this->stripe->createToken($stripeCardData);
            //END OF REGISTRING CARD IN STRIPE
           
            if(empty($stripeToken)){ //FAIL TO GET CARD TOKEN
                $resoponse = array('status'=>0, 'message'=>'Stripe token not created');
                echo json_encode($resoponse); exit();
            }elseif(isset($stripeToken['error'])){ //FAIL TO REGISTER CARD IN STRIPE
                $resoponse = array('status'=>0, 'message'=>$stripeToken['error']['message']);
                echo json_encode($resoponse); exit();
            }elseif(!isset($stripeToken["id"]) || $stripeToken["id"]==""){ //FAIL TO GET CARD TOKEN
                $resoponse = array('status'=>0, 'message'=>'Stripe token not created');
                echo json_encode($resoponse); exit();
            }

            $emailExist = $this->Users_Model->get(['email' => strtolower($this->input->post('email')),'role'=>[1,2],'status'=>[0,1,2,4]], true);
            if(!empty($emailExist)) {
                $resoponse = array('status'=>0, 'message'=>'This email ID already exists.');
                echo json_encode($resoponse); exit();
            }
            $userExist = $this->Users_Model->get(['email' => strtolower($this->input->post('email')),'role'=>3,'status'=>[0,1,2,4]], true);

            $userData['role'] = "3"; //doctor
            $userData['name'] = $this->input->post('name');
            $userData['birthdate'] = date('Y-m-d', strtotime($this->input->post('birthdate')));
            if($this->input->post('password')){
                $userData['password'] = $this->Common_Model->convert_to_hash($this->input->post('password'));    
            }
            $userData['email'] = strtolower($this->input->post('email'));
            $userData['gender'] = $this->input->post('gender');
            $userData['acceptProfessionalAgreement'] = time();
            $userData['phone'] = preg_replace("/[^0-9]/", "", $this->input->post('phone') );
            $userData['providerWebStep'] = 3;
            if(!empty($userExist)) {
                $userData['status'] = 4;
                $userId = $this->Users_Model->setData($userData,$userExist->id);
            }else{
                $userData['status'] = 4;
                $userId = $this->Users_Model->setData($userData);
                $referralCode = $userId.$this->Common_Model->random_string(4);
                $this->Users_Model->setData(['referralCode'=>$referralCode],$userId);
            }
            if(!empty($userId)) {
                $profession = explode(",",$this->input->post('profession'));
                $this->User_Profession_Model->setData(['userIds'=>$userId,'status'=>2]);
                if(!empty($profession)) {
                    foreach($profession as $value) {
                        if (empty($value)){
                            continue;
                        }
                        $existProfession = $this->User_Profession_Model->get(['userId'=>$userId,'professionId'=>$value],true);
                        if(!empty($existProfession)) {
                            $this->User_Profession_Model->setData(['userId'=>$userId,'professionId'=>$value,'status'=>1],$existProfession->id);
                        } else {
                            $this->User_Profession_Model->setData(['userId'=>$userId,'professionId'=>$value]);
                        }
                    }
                }

            }
                 
            $existPersonalData = $this->User_Professional_Model->get(['userId'=> $userId,'status'=>1], true);
            $professionalData['userId'] = $userId;
            //$professionalData['practiceCityId'] = $this->input->post('practicingCityId');
            //$professionalData['practiceStateId'] = $this->input->post('practicingStateId');
            //$professionalData['yourLicenseComplaint'] = $this->input->post('yourLicenseComplaint');
            //$professionalData['youConvictedCrime'] = $this->input->post('youConvictedCrime');
            //$professionalData['practiceYear'] = $this->input->post('practiceYear');
            $professionalData['freeBackground'] = $this->input->post('freeBackground');
            $professionalData['isLicenseCertificate'] = $this->input->post('isLicenseCertificate');
            $professionalData['isInsuranceLiability'] = $this->input->post('isInsuranceLiability');

            $upload_path = getenv('UPLOADPATH');
    
            //licenseImage upload
            if (isset($_FILES['licenseCertificate']["name"]) && !empty($_FILES['licenseCertificate']["name"])) {
                $fileExt = strtolower($this->Common_Model->getFileExtension($_FILES['licenseCertificate']["name"]));
                $fileName = date('ymdhis') . $this->Common_Model->random_string(6) . $fileExt;
                $upload_dir = $upload_path . "/" . $fileName;
                if (move_uploaded_file($_FILES['licenseCertificate']["tmp_name"], $upload_dir)) {
                    $professionalData['licenseImage'] = $fileName;
                }
            }else{
                $professionalData['licenseImage'] = $this->input->post('oldlicenseCertificate');
            }
           
            //Insurance upload    
            if (isset($_FILES['insuranceLiability']["name"]) && !empty($_FILES['insuranceLiability']["name"])) {
                $fileExt = strtolower($this->Common_Model->getFileExtension($_FILES['insuranceLiability']["name"]));
                $fileName = date('ymdhis') . $this->Common_Model->random_string(6) . $fileExt;
                $upload_dir = $upload_path . "/" . $fileName;
                if (move_uploaded_file($_FILES['insuranceLiability']["tmp_name"], $upload_dir)) {
                    $professionalData['insuranceImage'] = $fileName;
                }
            }else{
                $professionalData['insuranceImage'] = $this->input->post('oldinsuranceLiability');
            }
            
            if (!empty($existPersonalData)) {
                $this->User_Professional_Model->setData($professionalData, $existPersonalData->id);
            } else {
                $this->User_Professional_Model->setData($professionalData);
            }

            $authData = [];
            $authData['userId'] = $userId;
            $authData['token'] = $this->Common_Model->getToken(120);
            $authid = $this->Auth_Model->setData($authData);
    

            //Api Url
			$url = "/users/saveUserCard";
			$request = array();
			$request['holderName'] = $this->input->post('holderName', TRUE);
			$request['number'] = $this->input->post('cardnumber', TRUE);
			$request['expMonth'] = $this->input->post('expMonth', TRUE);
			$request['expYear'] = $this->input->post('expYear', TRUE);
			$request['cvv'] = $this->input->post('cvv', TRUE);
			$request['token'] = $authData['token'];
			$request['isDefault'] = 1;
			//Api Request
			$response =  $this->apicall->post($url,$request);
			$this->Auth_Model->removeToken($authData['token']);
			if(!empty($response)){
                $responseArray = json_decode($response);
                if($responseArray->status == 1){
                    $this->Background_Model->adminVerificationDoctorAccountMail($userId);
                    $genderName = ($this->input->post('gender') == 1 ? 'Male' : ($this->input->post('gender') == 2 ? 'Female' : 'Other'));
                    $userProfession = $this->User_Profession_Model->get(['userId'=>$userId,'status'=>1]);
                    $professionName = "";
                    if(!empty($userProfession)){
                        $professionName = array_column($userProfession,'professionName');
                        $professionName = implode(', ',$professionName);
                    }
                    $hubspotData = array();
                    $hubspotData[] = array('property' => 'firstname', 'value' => $this->input->post('fname'));
                    $hubspotData[] = array('property' => 'lastname', 'value' => $this->input->post('lname'));
                    $hubspotData[] = array('property' => 'phone', 'value' => $this->input->post('phone'));
                    $hubspotData[] = array('property' => 'gender', 'value' => $genderName);
                    if(!empty($professionName)){
                        $hubspotData[] = array('property' => 'jobtitle', 'value' => $professionName);
                    }
                    $hubspotData[] = array('property' => 'date_of_birth', 'value' => date('Y-m-d', strtotime($this->input->post('birthdate'))));
                    $this->Background_Model->updateHubspotContact($hubspotData,strtolower($this->input->post('email')));

                    if($this->input->post('referralCode')){
                        $existCode = $this->Users_Model->get(['referralCode' => $this->input->post('referralCode'), 'status' => [0,1,2,4],'role'=>3], TRUE);
                        if(!empty($existCode)){
                            $existreferraldata = $this->User_Referral_Model->get(['fromUserId'=>$userId,'toUserId'=>$existCode->id,'referralCode'=>$this->input->post('referralCode')],true);
                            if(empty($existreferraldata)){
                                $referral_id = $this->User_Referral_Model->setData(['fromUserId'=>$userId,'toUserId'=>$existCode->id,'referralCode'=>$this->input->post('referralCode'),'isRegister'=>1]);
                                if(!empty($referral_id)){
                                    $this->User_Referral_Earning_Model->setData(['userId'=>$userId,'referral_id'=>$referral_id,'amount'=>100]);
                                    $this->User_Referral_Earning_Model->setData(['userId'=>$existCode->id,'referral_id'=>$referral_id,'amount'=>100]);
                                }
                            }
                        }
                        $this->session->unset_userdata('referralcode');
                    }

                    #################################################################################
                    $this->load->model('StripeConnect_Model');
                    $authData = [];
                    $authData['userId'] = $userId;
                    $authData['token'] = $this->Common_Model->getToken(120);
                    $authid = $this->Auth_Model->setData($authData);
                    //$this->Users_Model->setData(['status'=>1], $userId);
                    
                    $connectArr = [];
                    $connectArr["token"] = $authData['token'];
                    $connectRes = $this->apicall->post("/payment/connectStripe",$connectArr);
                    $connectRes = json_decode($connectRes);
                    if($connectRes->status == 1) {   
                        $set_sc = $this->StripeConnect_Model->get(['userId' => $userId], true);
                        if(!empty($set_sc)) {
                            $this->StripeConnect_Model->setData(['status' => 1], $set_sc->id);
                        }

                        $sbcArr = [];
                        $sbcArr["token"] = $authData['token'];
                        $sbcArr["planPrice"] = $this->input->post('planPrice', TRUE);
                        $sbcRes = $this->apicall->post("/plan/saveSubscriptionPlan",$sbcArr);
                        $sbcRes = json_decode($sbcRes);
                        if($sbcRes->status == 1) {
                            $this->Users_Model->setData(['providerWebStep'=>4], $userId);
                            if(!empty($set_sc)) {
                                $this->StripeConnect_Model->setData(['status' => 1], $set_sc->id);
                                $this->StripeConnect_Model->setData(['isPayment' => 1], $set_sc->id);
                            }
                            $resoponse = array('status'=>1, 'message'=>$sbcRes->message);
                            echo json_encode($resoponse); exit();
                        }
                        else {
                            $this->Users_Model->setData(['status'=>4], $userId);
                            $resoponse = array('status'=>0, 'message'=>$sbcRes->message."...");
                            echo json_encode($resoponse); exit();
                        }
                    }
                    else {
                        $this->Users_Model->setData(['status'=>4], $userId);
                        $resoponse = array('status'=>0, 'message'=>$connectArr->message."...");
                        echo json_encode($resoponse); exit();
                    }
                    #################################################################################
                }
                echo $response;
				exit();
            }else{
                echo '{"status":"0","message":"Something went wrong please try again"}';
                exit();
            }			

            $resoponse = array('status'=>1, 'message'=>'Registered successfully');
            echo json_encode($resoponse); exit();
        }
        echo json_encode($resoponse); exit();
    }

    public function signup_completed() {
        $this->data['pageTitle'] = ['title' => 'Signup Completed', 'icon' => ''];
        $this->data['meta'] = ['title' => '', 'desc' => ''];
        
        $this->template->content->view('front/signup-completed', $this->data); 
        $this->template->publish();
    }
    
    public function createContactInHubspot() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $hubspotData = array();
            $hubspotData[] = array('property' => 'email', 'value' => strtolower($this->input->post('email')));
            $hubspotData[] = array('property' => 'firstname', 'value' => $this->input->post('fname'));
            $this->Background_Model->createHubspotContact($hubspotData);
            $this->Background_Model->addContactInListHubspot([strtolower($this->input->post('email'))],3);
        }
    }
    
    public function checkUserStepCompletion() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $existUser = $this->Users_Model->get(['email'=>$this->input->post('email'), 'status'=>[0,1,2,3,4], 'role'=>'3'], TRUE);
            if(!empty($existUser)){
                if($existUser->status == 0 && $existUser->providerWebStep == 3){
                    echo '{"status":"0","message":"Your account not verified"}';
                    exit();
                }elseif($existUser->status == 2 && $existUser->providerWebStep == 3){
                    echo '{"status":"0","message":"Your account blocked by admin"}';
                    exit();
                }elseif($existUser->status == 4 && $existUser->providerWebStep == 3){
                    echo '{"status":"0","message":"Your account waiting for approve by admin"}';
                    exit();
                }
                $birthdate = explode('-',$existUser->birthdateOriginal);
                $namearry = explode(" ", $existUser->name, 2);
                $existUser->fname = (isset($namearry[0]) ? $namearry[0] : "");
                $existUser->lname = (isset($namearry[1]) ? $namearry[1] : "");
                $existUser->password = "";
                $existUser->stripeCustomerId = "";
                $existUser->stripeCustomerJson = "";
                $existUser->bday = (isset($birthdate[2]) ? $birthdate[2] : "");
                $existUser->bmonth = (isset($birthdate[1]) ? $birthdate[1] : "");
                $existUser->byear = (isset($birthdate[0]) ? $birthdate[0] : "");
                $userProfession = $this->User_Profession_Model->get(['userId'=>$existUser->id,'status'=>1]);
                $existUser->profession = "";
                if(!empty($userProfession)){
                    $existUser->profession = array_column($userProfession,'professionId');
                }
                $existUser->personalData = $this->User_Professional_Model->get(['userId'=> $existUser->id,'status'=>1], true);
                
                $this->Users_Model->setData([ 'name'=>$this->input->post('fname'),'status'=>4,'role'=>3,'providerWebStep'=>0 ], $existUser->id);

                echo '{"status":"1","message":"Data saved successfully","data":'.json_encode($existUser).'}';
                exit();
            }else{
                $userId = $this->Users_Model->setData(['email'=>$this->input->post('email'),'name'=>$this->input->post('fname'),'status'=>4,'role'=>3,'providerWebStep'=>0]);
                $newUser = $this->Users_Model->get(['id'=>$userId, 'status'=>[0,1,2,4], 'role'=>'3'], TRUE);
                if(!empty($newUser)){
                    $namearry = explode(" ", $newUser->name, 2);
                    $newUser->fname = (isset($namearry[0]) ? $namearry[0] : "");
                    $newUser->lname = (isset($namearry[1]) ? $namearry[1] : "");
                }
                $hubspotData = array();
                $hubspotData[] = array('property' => 'email', 'value' => strtolower($this->input->post('email')));
                $hubspotData[] = array('property' => 'firstname', 'value' => $this->input->post('fname'));
                $this->Background_Model->createHubspotContact($hubspotData);
                $this->Background_Model->addContactInListHubspot([strtolower($this->input->post('email'))],3);
                echo '{"status":"1","message":"Data saved successfully","data":'.json_encode($newUser).'}';
                exit();
            }
        }
    }

    public function userFirstStepSave() {
        $resoponse = array('status'=>0, 'message'=>'Someting went wrong, please try again');
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            
            if(!$this->input->post('userId')){
                $resoponse = array('status'=>0, 'message'=>'Someting went wrong, please try again');
                echo json_encode($resoponse); exit();
            }
            $userId = $this->input->post('userId');
            $userExistData = $this->Users_Model->get(['id'=>$userId, 'status'=>[0,1,2,4], 'role'=>'3'], TRUE);
            if(empty($userExistData)){
                $resoponse = array('status'=>0, 'message'=>'Someting went wrong, please try again');
                echo json_encode($resoponse); exit();
            }
            if(strtolower($this->input->post('email')) != strtolower($userExistData->email)){
                $resoponse = array('status'=>0, 'message'=>'Your email did not match in the previous email');
                echo json_encode($resoponse); exit();
            }
            if($userExistData->status == 0 && $userExistData->providerWebStep == 3){
                echo '{"status":"0","message":"Your account not verified"}';
                exit();
            }elseif($userExistData->status == 2 && $userExistData->providerWebStep == 3){
                echo '{"status":"0","message":"Your account blocked by admin"}';
                exit();
            }elseif($userExistData->status == 4 && $userExistData->providerWebStep == 3){
                echo '{"status":"0","message":"Your account waiting for approve by admin"}';
                exit();
            }
            $userData = array();
            $userData['name'] = $this->input->post('name');
            $userData['birthdate'] = date('Y-m-d', strtotime($this->input->post('birthdate')));
            //$userData['password'] = $this->Common_Model->convert_to_hash($this->input->post('password'));
            $userData['email'] = $this->input->post('email');
            $userData['gender'] = $this->input->post('gender');
            $userData['phone'] = preg_replace("/[^0-9]/", "", $this->input->post('phone') );

            if($this->input->post('plan_price') == 0){
                $userData['ispresenceforsearch'] = 0;
                $userData['isfreeplan'] = 1;
                $userData['status'] = 1;
            }

            $resoponse = array('status'=>1,'message'=>'Data saved successfully','data'=>$userExistData);
            if($userExistData->providerWebStep == 3 && $userExistData->status == 1){
                if($userExistData->password == $this->Common_Model->convert_to_hash($this->input->post('password')) && strtolower($userExistData->email) == strtolower($this->input->post('email'))){
                    $authData = [];
                    $authData['userId'] = $userId;
                    $authData['token'] = $this->Common_Model->getToken(120);
                    $this->Auth_Model->setData($authData);
                    $cookie = [
                        'name'   => 'doctorToken',
                        'value'  => $authData['token'],
                        'expire' => '86400',
                    ];
                    $this->input->set_cookie($cookie);

                    $sessionData = [
                        'doctorRole' => $userExistData->role,
                        'doctorId' => $userExistData->id,
                        'doctorImage' => $userExistData->profileimage,
                        'doctorName' => $userExistData->name,
                    ];
                    $this->session->set_userdata($sessionData);
                    $resoponse = array('status'=>2,'message'=>'Data saved successfully','data'=>$userExistData);  
                }else{
                    $resoponse = array('status'=>0, 'message'=>'Invalid email or password');
                    echo json_encode($resoponse); exit();
                }
            }
            if($userExistData->providerWebStep == 0){
                $userData['providerWebStep'] = 1;
                $userData['password'] = $this->Common_Model->convert_to_hash($this->input->post('password'));
            }
            if($userExistData->providerWebStep == 1 || $userExistData->providerWebStep == 2 || $userExistData->providerWebStep == 3){
                if($userExistData->password != $this->Common_Model->convert_to_hash($this->input->post('password')) || strtolower($userExistData->email) != strtolower($this->input->post('email'))){
                    $resoponse = array('status'=>0, 'message'=>'Invalid email or password');
                    echo json_encode($resoponse); exit();
                }
            }
            $userId = $this->Users_Model->setData($userData,$userId);
            $profession = explode(",",$this->input->post('profession'));
            $this->User_Profession_Model->setData(['userIds'=>$userId,'status'=>2]);
            if(!empty($profession)) {
                foreach($profession as $value) {
                    if (empty($value)){
                        continue;
                    }
                    $existProfession = $this->User_Profession_Model->get(['userId'=>$userId,'professionId'=>$value],true);
                    if(!empty($existProfession)) {
                        $this->User_Profession_Model->setData(['userId'=>$userId,'professionId'=>$value,'status'=>1],$existProfession->id);
                    } else {
                        $this->User_Profession_Model->setData(['userId'=>$userId,'professionId'=>$value]);
                    }
                }
            }
            echo json_encode($resoponse); exit();
        }
        echo json_encode($resoponse); exit();
    }

    public function userSecondStepSave() {
        $resoponse = array('status'=>0, 'message'=>'Someting went wrong, please try again');
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            if(!$this->input->post('userId')){
                $resoponse = array('status'=>0, 'message'=>'Someting went wrong, please try again');
                echo json_encode($resoponse); exit();
            }
            $userId = $this->input->post('userId');
            $userExistData = $this->Users_Model->get(['id'=>$userId, 'status'=>[0,1,2,4], 'role'=>'3'], TRUE);
            if(empty($userExistData)){
                $resoponse = array('status'=>0, 'message'=>'Someting went wrong, please try again');
                echo json_encode($resoponse); exit();
            }
           
            if($userExistData->status == 0 && $userExistData->providerWebStep == 3){
                echo '{"status":"0","message":"Your account not verified"}';
                exit();
            }elseif($userExistData->status == 2 && $userExistData->providerWebStep == 3){
                echo '{"status":"0","message":"Your account blocked by admin"}';
                exit();
            }elseif($userExistData->status == 4 && $userExistData->providerWebStep == 3){
                echo '{"status":"0","message":"Your account waiting for approve by admin"}';
                exit();
            }

            $existPersonalData = $this->User_Professional_Model->get(['userId'=> $userId,'status'=>1], true);
            $professionalData['userId'] = $userId;
            $professionalData['freeBackground'] = $this->input->post('freeBackground');
            $professionalData['isLicenseCertificate'] = $this->input->post('isLicenseCertificate');
            $professionalData['isInsuranceLiability'] = $this->input->post('isInsuranceLiability');

            $upload_path = getenv('UPLOADPATH');
          
            if (isset($_FILES['licenseCertificate']["name"]) && !empty($_FILES['licenseCertificate']["name"])) {
                $fileExt = strtolower($this->Common_Model->getFileExtension($_FILES['licenseCertificate']["name"]));
                $fileName = date('ymdhis') . $this->Common_Model->random_string(6) . $fileExt;
                $upload_dir = $upload_path . "/" . $fileName;
                if (move_uploaded_file($_FILES['licenseCertificate']["tmp_name"], $upload_dir)) {
                    $professionalData['licenseImage'] = $fileName;
                }
            }else{
                $professionalData['licenseImage'] = $this->input->post('oldlicenseCertificate');
            }
            //licenseImage upload

            //Insurance upload
            if (isset($_FILES['insuranceLiability']["name"]) && !empty($_FILES['insuranceLiability']["name"])) {
                $fileExt = strtolower($this->Common_Model->getFileExtension($_FILES['insuranceLiability']["name"]));
                $fileName = date('ymdhis') . $this->Common_Model->random_string(6) . $fileExt;
                $upload_dir = $upload_path . "/" . $fileName;
                if (move_uploaded_file($_FILES['insuranceLiability']["tmp_name"], $upload_dir)) {
                    $professionalData['insuranceImage'] = $fileName;
                }
            }else{
                $professionalData['insuranceImage'] = $this->input->post('oldinsuranceLiability');
            }
            
            if (!empty($existPersonalData)) {
                $this->User_Professional_Model->setData($professionalData, $existPersonalData->id);
            } else {
                $this->User_Professional_Model->setData($professionalData);
            }
            $userData = array();
            $userData['providerWebStep'] = 2;
            $this->Users_Model->setData($userData,$userId);
            $resoponse = array('status'=>1,'message'=>'Data saved successfully');
            echo json_encode($resoponse); exit();
        }
        echo json_encode($resoponse); exit();
    }


    
    public function socialEmail() {
        $msg = "something went wrong. please try again after sometime";
        if(
            $this->session->userdata('auth_provider') && 
            $this->session->userdata('isManualEmail') && 
            $this->session->userdata('auth_id') && 
            $this->session->userdata('id') && 
            !empty($this->session->userdata('auth_provider')) && 
            !empty($this->session->userdata('isManualEmail')) && 
            !empty($this->session->userdata('auth_id')) && 
            !empty($this->session->userdata('id')) && 
            $this->session->userdata('isManualEmail') == 1
        ) {
            if ($this->input->server('REQUEST_METHOD') == 'POST') {
                if (!empty($this->input->post('email'))) {
                    $this->session->set_userdata([ "email" => $this->input->post('email')]);
                    $socialData = $this->session->all_userdata();
                    if(!empty($socialData)) {
                        $userInfo = $this->apicall->post('/auth/socialLogin', $socialData, true);
                        if(!empty($userInfo)) {
                            $o = json_decode($userInfo, true);
                            if($o["status"] == 3 && isset($o["userId"]) && !empty($o["userId"])) {
                                //echo "<pre>"; print_r($o); exit;
                                $this->Background_Model->userVerificationMail($o["userId"]);
                                $this->imageUploadSocial($o["userId"]);
                                $this->session->sess_destroy();
                                return redirect('verify/'.$o["userId"]); 
                                exit;
                            }
                            if(isset($o["message"])) {
                                $msg = $o["message"];
                            }
                        }
                        $this->session->set_flashdata('error', $msg);
                        return redirect(base_url()."login");  exit;
                    }
                    else {
                        $this->session->set_flashdata('error', $msg);
                        return redirect(base_url()."login");  exit;
                    }
                }
                else {
                    $this->session->set_flashdata('error', "Please Enter Valid Email");
                }
            }
            $this->template->content->view('front/manualSocialEmail', []);
            return $this->template->publish(); exit;
        }
        else {
            $this->session->set_flashdata('error', $msg);
            return redirect(base_url()."login");  exit;
        }
    }

    public function imageUploadSocial($uid) {
        if($this->session->userdata('socialImage') && !empty($this->session->userdata('socialImage')) && !empty($uid)) {
            $img_link = $this->session->userdata('socialImage');
            if (!filter_var($img_link, FILTER_VALIDATE_URL) === false) {
                //is a valid URL
                $upload_path = getenv('UPLOADPATH');                
                $img = date('ymdhis').$this->Common_Model->random_string(6).'.jpg';
                $img_upload_arr = $upload_path.$img;
                file_put_contents($img_upload_arr, file_get_contents($img_link));
                $this->Users_Model->setData([ "image" => $img ], $uid);
            }
            else {
                //is not a valid URL
            }
        }
    }

    public function socialcallback($connect) {
        $msg = "something went wrong. please try again after sometime";
        if ($connect == 'facebook') {
            $this->data['socialLoginData'] = $this->login_facebook();
            /* $this->template->content->view('webapp/login', $this->data);
            $this->template->publish(); */
            //echo "<pre>"; print_r($this->data); exit;
            
            /* $this->data['socialLoginData']  = [
                "status" => 1,
                "id" => "103865312400684",
                "auth_id" => "103865312400684",
                "email" => "000000@yopmail.com",
                "name" => "Olivia Fallerstein",
                "first_name" => "Olivia",
                "last_name" => "Fallerstein",
                "picture" => "http://graph.facebook.com/103865312400684/picture?type=large",
                "dataStatus" => 1,
                "role" => 2,
                "isManualEmail" => 1,
                "auth_provider" => "facebook"
            ]; */
            
            if(isset($this->data['socialLoginData']["picture"]) && !empty($this->data['socialLoginData']["picture"])) {
                $this->session->set_userdata([ "socialImage" => $this->data['socialLoginData']["picture"] ]);
            }
            
            if(isset($this->data['socialLoginData']) && count($this->data['socialLoginData']) != 0) {
                $socialData = $this->data['socialLoginData'];
                if(isset($socialData['auth_id']) && !empty($socialData['auth_id'])) {
                    $socialData['langType'] = '1';
                    $socialData['deviceType'] = '3';
                    $socialData['deviceId'] = '';

                    $userInfo = $this->apicall->post('/auth/socialLogin', $socialData, true);
                    if(!empty($userInfo)) {
                        $o = json_decode($userInfo, true);
                        if(isset($o["status"]) && $o["status"] == 4) {
                            $this->session->set_userdata($this->data['socialLoginData']);
                            return redirect(base_url()."socialemail");  exit;
                        }
                        else if(isset($o["status"]) && $o["status"] == 3) {
                            $this->session->sess_destroy();
                            $this->Background_Model->userVerificationMail($o["userId"]);
                            $this->imageUploadSocial($o["userId"]);
                            return redirect('verify/'.$o["userId"]); exit;
                        }
                        else {
                            $this->imageUploadSocial($o["data"]["id"]);
                            $this->socialloginuser($o["data"]["id"]); exit;
                        }
                    }
                    else {
                        if(isset($socialData['error'])) {
                            $msg = $socialData['error'];
                        }
                        $this->session->set_flashdata('error', $msg);
                    }
                }
                else {
                    if(isset($socialData['error'])) {
                        $msg = $socialData['error'];
                    }
                    $this->session->set_flashdata('error', $msg);
                }
            }
            else {
                $this->session->set_flashdata('error', $msg);
            }
            return redirect(base_url()."login");  exit;
        }
        else if ($connect == 'google') {
            $this->data['socialLoginData'] = $this->login_google();
            /* $this->data['socialLoginData'] = [
                    "status" => 1,
                    "id" => "1113689035526964358376",
                    "auth_id" => "1113689035526964358376",
                    "email" => "ajay.0000@gmail.com",
                    "name" => "Ajay webmigrates",
                    "first_name" => "Ajay",
                    "last_name" => "webmigrates",
                    "picture" => "https://lh3.googleusercontent.com/a/AItbvmk1yTLgoCWFUrLbXlIetU_ruXKrZdujJqESVemu=s96-c",
                    "role" => 2,
                    "dataStatus" => 1,
                    "isManualEmail" => "0",
                    "auth_provider" => "google"
            ]; */

            if(isset($this->data['socialLoginData']["picture"]) && !empty($this->data['socialLoginData']["picture"])) {
                $this->session->set_userdata([ "socialImage" => $this->data['socialLoginData']["picture"] ]);
            }

            if(isset($this->data['socialLoginData']) && count($this->data['socialLoginData']) != 0) {
                $socialData = $this->data['socialLoginData'];
                if(isset($socialData['auth_id']) && !empty($socialData['auth_id'])) {
                    $socialData['langType'] = '1';
                    $socialData['deviceType'] = '3';
                    $socialData['deviceId'] = '';

                    $userInfo = $this->apicall->post('/auth/socialLogin', $socialData, true);
                    if(!empty($userInfo)) {
                        $o = json_decode($userInfo, true);
                        if(isset($o["status"]) && $o["status"] == 1) {
                            $user = $this->Users_Model->get([ 'email' => $socialData['email'], 'role' => [2] ], true);
                            if (empty($user)) {
                                $this->session->set_flashdata('error', 'Invalid email or password');
                                return redirect('login'); exit;
                            }
                            $this->imageUploadSocial($user->id);
                            $this->socialloginuser($user->id); exit;

                            /* $user = $this->Users_Model->get([ 'email' => $socialData['email'], 'role' => [2] ], true);
                            //echo "<hr><pre>"; print_r($user); exit;
                            if (empty($user)) {
                                $this->session->set_flashdata('error', 'Invalid email or password');
                                return redirect('login');
                            }
                            if ($user->status == 4) {
                                $this->session->set_flashdata('success', 'Waiting for admin approval');
                                return redirect('login');
                            }
                            else if($user->status == 0) {
                                $this->Background_Model->userVerificationMail($user->id);
                                return redirect('verify/'.$user->id);
                            } 
                            else if ($user->status != 1 && $user->status != 4) {
                                $this->session->set_flashdata('error', 'Your account is blocked by Admin');
                                return redirect('login');
                            }
                            
                            $tz_ip = $_SERVER['REMOTE_ADDR'];
                            $ipInfo = file_get_contents('http://ip-api.com/json/'.$tz_ip);
                            $ipInfo = json_decode($ipInfo, true);
                            $tz_label = date_default_timezone_get();
                            if(isset($ipInfo["status"]) && $ipInfo["status"] == "success") {
                                if(isset($ipInfo["timezone"]) && !empty($ipInfo["timezone"])) {
                                    $tz_label = $ipInfo["timezone"];
                                }
                            }
                            $authData = [];
                            $authData['userId'] = $user->id;
                            $authData['deviceType'] = '3';
                            $authData['token'] = $this->Common_Model->getToken(120);
                            $getAuth = $this->Auth_Model->get(['userId' => $user->id], TRUE);
                            $this->Auth_Model->setData($authData);
                            
                            $address_data['timeZone'] = $tz_label;
                            if(!empty($user)) {
                                $this->Users_Model->setData($address_data, $user->id);
                            }
                            if ($user->role == 2) {
                                $cookie = [
                                    'name'   => 'userToken',
                                    'value'  => $authData['token'],
                                    'expire' => '86400',
                                ];
                                $this->input->set_cookie($cookie);
        
                                $UserSessionData = [
                                    'userRole' => $user->role,
                                    'userId' => $user->id,
                                    'userImage' => $user->profileimage,
                                    'username' => $user->name,
                                ];
                                $this->session->set_userdata($UserSessionData);
                                return redirect(DASHBOARD);
                            }
                            else {
                                $this->session->set_flashdata('error', $msg);
                            }
                            */
                        }
                        else {
                            if(isset($o['message'])) {
                                $msg = $o['message'];
                            }
                            $this->session->set_flashdata('error', $msg);
                        }
                    }
                    else {
                        if(isset($socialData['error'])) {
                            $msg = $socialData['error'];
                        }
                        $this->session->set_flashdata('error', $msg);
                    }
                }
                else {
                    if(isset($socialData['error'])) {
                        $msg = $socialData['error'];
                    }
                    $this->session->set_flashdata('error', $msg);
                }
            }
            else {
                $this->session->set_flashdata('error', $msg);
            }
            return redirect(base_url()."login");  exit;
        }
        else {
            //$this->data['socialLoginData'] = $this->login_facebook();
            /* $this->template->content->view('webapp/login', $this->data);
            $this->template->publish(); */
        }
        return redirect(base_url()."login");
    }

    private function login_google() {
		$site_url = current_url();
		$client_id = getenv('GOOGLE_KEY');
		$client_secret = getenv('GOOGLE_SECRET');
		$client_api_key = getenv('GOOGLE_API_KEY');
		$social_instance = new Social();
		$gmailData = $social_instance->gmail_connect(NULL, $site_url, $client_id, $client_secret, $client_api_key);
        //echo "<pre>"; print_r($gmailData); exit;
		if(!empty($gmailData['redirectURL'])) {
			redirect($gmailData['redirectURL']);
		} 
        else {
			if(isset($gmailData['id']) && !empty($gmailData['id'])) {
                $nm = isset($gmailData['first_name']) ? $gmailData['first_name']." " : " ";
                $nm .= isset($gmailData['last_name']) ? $gmailData['last_name'] : "";
				$finalResponse = [
					'status' => 1,
					'id' => $gmailData['id'],
					'auth_id' => $gmailData['id'],
					'email' => isset($gmailData['email']) ? $gmailData['email'] : "",
					'name' => $nm,
					'first_name' => isset($gmailData['first_name']) ? $gmailData['first_name'] : "",
					'last_name' => isset($gmailData['last_name']) ? $gmailData['last_name'] : "",
					'picture' => isset($gmailData['picture']) ? $gmailData['picture'] : "",
					'role' => 2,
					'dataStatus' => "1",
					'isManualEmail' => "0",
					'auth_provider' => "google",
				];
				return $finalResponse;
			}
            else {
				$finalResponse = [
					'status' => 0,
					'error' => isset($gmailData["error"]) ? $gmailData['error'] : "",
					'auth_provider' => "google",
				];
				return $finalResponse;
            }
		}
	}

    private function login_facebook() {
        $site_url = base_url('social/facebook') . "/";
        //$fb_App_id = "428849964630198";
        //$fb_secret = "bf9edacecb78fb966baf95656350ca91";
        $fb_App_id = getenv('FACEBOOK_KEY'); //"183200417023845"; //Chiry - live
        $fb_secret = getenv('FACEBOOK_SECRET'); //"56453384c5c4d52bf8fc34a8bfc75435"; //Chiry - live
        $fb_scope = "public_profile,email,user_friends";

        $social_instance = new Social();        
        /* $fbData = $social_instance->facebook_connect(null, $this->session, $site_url, $fb_App_id, $fb_secret, $fb_scope);
        echo "<prE>"; print_r($fbData); exit; */

        try {
            $fbData = $social_instance->facebook_connect(null, $this->session, $site_url, $fb_App_id, $fb_secret, $fb_scope);
            //echo "<prE>"; print_r($fbData); exit;

            if (!empty($fbData['redirectURL'])) {
                redirect($fbData['redirectURL']);
            } 
            else {
                if(isset($fbData['id']) && !empty($fbData['id'])) {
                    $nm = isset($fbData['first_name']) ? $fbData['first_name']." " : " ";
                    $nm .= isset($fbData['last_name']) ? $fbData['last_name'] : "";                  
                    $finalResponse = [
                        'status' => 1,
                        'id' => $fbData['id'],
                        'auth_id' => $fbData['id'],
                        'email' => isset($fbData['email']) ? $fbData['email'] : "",
                        'name' => $nm,
                        'first_name' => isset($fbData['first_name']) ? $fbData['first_name'] : "",
                        'last_name' => isset($fbData['last_name']) ? $fbData['last_name'] : "",
                        'picture' => isset($fbData['picture']) ? $fbData['picture'] : "",
                        'role' => 2,
                        'dataStatus' => "1",
                        'isManualEmail' => (isset($fbData['email']) && !empty($fbData['email'])) ? "0" : "1",
                        'auth_provider' => "facebook"
                    ];
                    return $finalResponse;
                }
                else {
                    $finalResponse = [
                        'status' => 0,
                        'dataStatus' => 0,
                        'error' => isset($fbData["error"]) ? $fbData['error'] : "",
                        'auth_provider' => "facebook",
                    ];
                    return $finalResponse;
                }
            }
        } 
        catch (Exception $e) {        
            $finalResponse = [
                'status' => 0,
                'dataStatus' => 0,
                'error' => $e->getMessage(),
                'auth_provider' => "facebook",
            ];
            return $finalResponse;
        }
    }

    public function socialloginuser($uid) {
        $msg = "something went wrong. please try again after sometime";
        $user = $this->Users_Model->get([ 'id' => $uid, 'role' => [2] ], true);
        if (empty($user)) {
            $this->session->set_flashdata('error', 'Invalid email or password');
            return redirect('login');
        }
        if ($user->status == 4) {
            $this->session->set_flashdata('success', 'Waiting for admin approval');
            return redirect('login');
        }
        else if($user->status == 0) {
            $this->Background_Model->userVerificationMail($user->id);
            return redirect('verify/'.$user->id);
        } 
        else if ($user->status != 1 && $user->status != 4) {
            $this->session->set_flashdata('error', 'Your account is blocked by Admin');
            return redirect('login');
        } 

        $tz_ip = $_SERVER['REMOTE_ADDR'];
        $ipInfo = file_get_contents('http://ip-api.com/json/'.$tz_ip);
        $ipInfo = json_decode($ipInfo, true);
        $tz_label = date_default_timezone_get();
        if(isset($ipInfo["status"]) && $ipInfo["status"] == "success") {
            if(isset($ipInfo["timezone"]) && !empty($ipInfo["timezone"])) {
                $tz_label = $ipInfo["timezone"];
            }
        }
        $authData = [];
        $authData['userId'] = $user->id;
        $authData['deviceType'] = '3';
        $authData['token'] = $this->Common_Model->getToken(120);
        $getAuth = $this->Auth_Model->get(['userId' => $user->id], TRUE);
        $this->Auth_Model->setData($authData);
        
        $address_data['timeZone'] = $tz_label;
        if(!empty($user)) {
            $this->Users_Model->setData($address_data, $user->id);
        }
        if ($user->role == 2) {
            $cookie = [
                'name'   => 'userToken',
                'value'  => $authData['token'],
                'expire' => '86400',
            ];
            $this->input->set_cookie($cookie);

            $UserSessionData = [
                'userRole' => $user->role,
                'userId' => $user->id,
                'userImage' => $user->profileimage,
                'username' => $user->name,
            ];
            $this->session->set_userdata($UserSessionData);
            return redirect(DASHBOARD);
        }
        else {
            $this->session->set_flashdata('error', $msg);
        }
        return redirect(base_url()."login");
    }

    public function signup_user() {
        if($this->session->userdata('doctorId')){
            return redirect(DASHBOARD);
        }
        $this->data['pageTitle'] = ['title' => 'Signup', 'icon' => ''];
        $this->data['meta'] = ['title' => '', 'desc' => ''];
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $post = $this->input->post();
            #echo "<pre>"; print_r($post); exit;

            $form_response = $this->input->post('g-recaptcha-response');
            $url = "https://www.google.com/recaptcha/api/siteverify";

            $secretkey = getenv('SECRET_KEY');

            $response = file_get_contents($url."?secret=".$secretkey."&response=".$form_response."&remoteip=".$_SERVER["REMOTE_ADDR"]);
            $data = json_decode($response);            
            if (isset($data->success) && $data->success=="true") {
            #if (1 == 1) {
                $emailExist = $this->Users_Model->get(['email' => strtolower($post['email']),'status'=>[0,1,2,4]], true);
                if(!empty($emailExist)) {
                    $this->session->set_flashdata('error', 'This email ID already exists.');
                    return redirect(base_url());
                }

                $post['role'] = "2"; //user
                $post['birthdate'] = $post['byear'].'-'.$post['bmonth'].'-'.$post['bday'];
                $post['password'] = $this->Common_Model->convert_to_hash($post['password']);
                $post['verificationCode'] = $this->Common_Model->random_string(4);
                
                $check_deleted_mail_exist = $this->Users_Model->get(['email' => strtolower($post['email']),'status'=>[3]], true);
                if(!empty($check_deleted_mail_exist)) {
                    $post['status'] = "0";
                    $user = $this->Users_Model->setData($post, $check_deleted_mail_exist->id);
                    $check_language_exist = $this->User_Language->get([ 'userId' => $check_deleted_mail_exist->id ]);
                    if(isset($check_language_exist) && !empty($check_language_exist)) {
                        foreach($check_language_exist as $value) {
                            $this->User_Language->setData([ 'status' => 2 ], $value->id);
                        }
                    }
                }
                else {
                    $user = $this->Users_Model->setData($post);
                }
                #$user = $this->Users_Model->setData($post);
                
                if(isset($post["language"]) && !empty($post["language"])) {
                    foreach($post["language"] as $value) {
                        $this->User_Language->setData([ 'userId' => $user, 'languageId' => $value ]);
                    }
                }

                if(!empty($user)) {
                    $referralCode = $user.$this->Common_Model->random_string(4);
                    $this->Users_Model->setData(['referralCode'=>$referralCode],$user);
                    $this->Background_Model->userVerificationMail($user);
                    $genderName = ($this->input->post('gender') == 1 ? 'Male' : ($this->input->post('gender') == 2 ? 'Female' : 'Other'));
                    $hubspotData = array();
                    $hubspotData[] = array('property' => 'email', 'value' => strtolower($this->input->post('email')));
                    $hubspotData[] = array('property' => 'firstname', 'value' => $this->input->post('name'));
                    $hubspotData[] = array('property' => 'phone', 'value' => $this->input->post('phone'));
                    $hubspotData[] = array('property' => 'gender', 'value' => $genderName);
                    $hubspotData[] = array('property' => 'date_of_birth', 'value' => date('Y-m-d', strtotime($post['byear'].'-'.$post['bmonth'].'-'.$post['bday'])));
                    $this->Background_Model->createHubspotContact($hubspotData);
                    $this->Background_Model->addContactInListHubspot([strtolower($this->input->post('email'))],2);

                    if($this->input->post('referralCode')){
                        $existCode = $this->Users_Model->get(['referralCode' => $this->input->post('referralCode'), 'status' => [0,1,2,4],'role'=>3], TRUE);
                        if(!empty($existCode)){
                            $existreferraldata = $this->User_Referral_Model->get(['fromUserId'=>$user,'toUserId'=>$existCode->id,'referralCode'=>$this->input->post('referralCode')],true);
                            if(empty($existreferraldata)){
                                $referral_id = $this->User_Referral_Model->setData(['fromUserId'=>$user,'toUserId'=>$existCode->id,'referralCode'=>$this->input->post('referralCode'),'isRegister'=>1]);
                                if(!empty($referral_id)){
                                    if(empty($check_deleted_mail_exist)) {
                                        $this->User_Referral_Earning_Model->setData(['userId'=>$user,'referral_id'=>$referral_id,'amount'=>5]);
                                        $this->User_Referral_Earning_Model->setData(['userId'=>$existCode->id,'referral_id'=>$referral_id,'amount'=>100]);
                                    }
                                }
                            }
                        }
                        $this->session->unset_userdata('referralcode');
                    }
                    $this->session->set_flashdata('success', 'Success! Please verify email');
                    return redirect('verify/'.$user);
                } else {
                    $this->session->set_flashdata('error', 'Fail to register user');
                    return redirect(base_url());
                }
            } else {
                $this->session->set_flashdata('error', 'Please verify captcha');
                return redirect(base_url());
            }
        }
        //return redirect(base_url());
        //$this->template->content->view('front/signup_user', $this->data); 
        
		$this->data["languageData"] = $this->Languages->get( [ 'apiResponse' => true, 'status' => '1' ] );

        $this->template->content->view('front/user_signup', $this->data); 
        $this->template->publish();
    }

    public function adduserlocation($id = "") {
        if(!$this->session->userdata('userId')) {
            #return redirect('logout');
        }

        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $res = [
                "status" => 0,
                "message" => "Enter your locations"
            ];
            
            $user = $this->Users_Model->get(['id' => $id,'role'=>[2,3]],TRUE);
            if (empty($user)) {
                $this->session->set_flashdata('error', 'User not exist');
                return redirect('signup');
            }

            if(!isset($_POST["address"]) || empty($_POST["address"])){
                $this->session->set_flashdata('error', "Enter your locations");
                return redirect('addlocation/'.$id);
            }
            if(!isset($_POST["stateName"]) || empty($_POST["stateName"])){
                $this->session->set_flashdata('error', "Enter valid State");
                return redirect('addlocation/'.$id);
            }            
            if(!isset($_POST["cityName"]) || empty($_POST["cityName"])){
                $this->session->set_flashdata('error', "Enter your City");
                return redirect('addlocation/'.$id);
            }
            if(!isset($_POST["zipcode"]) || empty($_POST["zipcode"])){
                $this->session->set_flashdata('error', "Enter your zipcode");
                return redirect('addlocation/'.$id);
            }

            $in_data = [
                "address" => $_POST["address"],
                "stateName" => $_POST["stateName"],
                "cityName" => $_POST["cityName"],
                "longitude" => $_POST["longitude"],
                "latitude" => $_POST["latitude"],
                "zipcode" => $_POST["zipcode"]
            ];
            $data = $this->Users_Model->setData($in_data, $id);

            $user = $this->Users_Model->get([ 'id' => $data ], TRUE);
            if (empty($user)) {
                $this->session->set_flashdata('error', 'User not exist');
                return redirect('signup');
            }
            if($user->role != 2) {
                $this->session->set_flashdata('error', 'User not exist');
                return redirect('signup');
            }
            $sessionData = [
                'userRole' => $user->role,
                'userId' => $user->id,
                'userImage' => $user->profileimage,
                'username' => $user->name,
            ];

            $authData = [];
            $authData['userId'] = $id;
            $authData['deviceType'] = '3';
            $authData['token'] = $this->Common_Model->getToken(120);
            $getAuth = $this->Auth_Model->get(['userId' => $id ], TRUE);
            #echo "<pre>"; print_r($getAuth); exit;
            if(!empty($getAuth)) {
                $authid = $this->Auth_Model->setData($authData, $getAuth->id);
            }
            else {
                $authid = $this->Auth_Model->setData($authData);
            }
            $cookie = [
                'name'   => 'userToken',
                'value'  => $authData['token'],
                'expire' => '86400',
            ];
            $this->session->set_userdata($sessionData);
            $this->input->set_cookie($cookie);
            
            return redirect(DASHBOARD);
            
        }
        else {
            
            $user = $this->Users_Model->get(['id' => $id,'role'=>[2,3]],TRUE);
            if (empty($user)) {
                $this->session->set_flashdata('error', 'User not exist');
                return redirect('signup');
            }        
            if($user->status == "0") {
                return redirect('verify/'.$id);
            }
            
            $this->data['user'] = $user;
            $this->template->content->view('front/adduserlocation', $this->data); 
            $this->template->publish();
        }
    }


    public function verifyAccount($id = "") {
        if($this->session->userdata('doctorId')) {
            return redirect('cardList');
        }
        $this->data['pageTitle'] = ['title' => 'Verify Account', 'icon' => ''];
        $this->data['meta'] = ['title' => '', 'desc' => ''];

        $user = $this->Users_Model->get(['id' => $id,'role'=>[2,3]],TRUE);
        if (empty($user)) {
            $this->session->set_flashdata('error', 'User not exist');
            return redirect('signup');
        }

        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $post = $this->input->post();
            if($user->status == "0"){
                if($post['verificationCode'] != $user->verificationCode){
                    $this->session->set_flashdata('error', 'Wrong verification code');
                    return redirect('verify/'.$id);
                }else{
                    $userdata['verificationCode'] = '';
                    if($user->role == "3"){
                        $userdata['status'] = 4;
                    }else{
                        $userdata['status'] = 1;  
                    }
                    $setuserid = $this->Users_Model->setData($userdata, $id);
                    $userdata = $this->Users_Model->get(['id' => $setuserid], true);

                    if(!empty($userdata)) {
                        $this->load->model('Usersocialauth_Model', 'Usersocialauth');
                        $socialData = $this->Usersocialauth->get(['userId' => $setuserid], TRUE);
                        if(!empty($socialData)) {
                            $this->Usersocialauth->setData(["status" => 1], $socialData->id);
                        }
                    }

                    $this->session->set_flashdata('success', 'Your account was verified');
                    if($user->role == "3"){
                        $sessionData = [
                            'doctorRole' => $userdata->role,
                            'doctorId' => $userdata->id,
                            'doctorImage' => $userdata->profileimage,
                            'doctorName' => $userdata->name,
                        ];
                        
                        $authData = [];
                        $authData['userId'] = $setuserid;
                        $authData['deviceType'] = '3';
                        $authData['token'] = $this->Common_Model->getToken(120);

                        $getAuth = $this->Auth_Model->get(['userId' => $setuserid], TRUE);
                        if(!empty($getAuth)) {
                            $authid = $this->Auth_Model->setData($authData, $getAuth->id);
                        } else {
                            $authid = $this->Auth_Model->setData($authData);
                        }
                        $cookie = [
                            'name'   => $user->role == "3"?'doctorToken':'userToken',
                            'value'  => $authData['token'],
                            'expire' => '86400',
                        ];
                        $this->input->set_cookie($cookie);
                        $this->session->set_userdata($sessionData); // SESSION set.
                        return redirect(DASHBOARD_DOCTOR);
                        
                    } 
                    else {
                        return redirect('addlocation/'.$id);
                    }

                    /*
                    if($user->role == "3"){
                        $sessionData = [
                            'doctorRole' => $userdata->role,
                            'doctorId' => $userdata->id,
                            'doctorImage' => $userdata->profileimage,
                            'doctorName' => $userdata->name,
                        ];
                    } else {
                        $sessionData = [
                            'userRole' => $userdata->role,
                            'userId' => $userdata->id,
                            'userImage' => $userdata->profileimage,
                            'username' => $userdata->name,
                        ];
                    }

                    $authData = [];
                    $authData['userId'] = $setuserid;
                    $authData['deviceType'] = '3';
                    $authData['token'] = $this->Common_Model->getToken(120);

                    $getAuth = $this->Auth_Model->get(['userId' => $setuserid], TRUE);
                    if(!empty($getAuth)) {
                        $authid = $this->Auth_Model->setData($authData, $getAuth->id);
                    } else {
                        $authid = $this->Auth_Model->setData($authData);
                    }
                    $cookie = [
                        'name'   => $user->role == "3"?'doctorToken':'userToken',
                        'value'  => $authData['token'],
                        'expire' => '86400',
                    ];
                    $this->input->set_cookie($cookie);

                    $this->session->set_userdata($sessionData); // SESSION set.
                    */

                    /*
                    if($user->role == "3"){
                        return redirect(DASHBOARD_DOCTOR);
                    }else {
                        return redirect(DASHBOARD);
                        // return redirect('paymentSuceess');
                    }
                    */
                }
            }else{
                $this->session->set_flashdata('error', 'Something went wrong, please try again');
                return redirect('verify/'.$id);
            }
        }
        $this->data['user'] = $user;
        $this->template->content->view('front/verify', $this->data); 
        $this->template->publish();
    }

    public function paymentSuceess() {
        if($this->session->userdata('doctorId') || $this->session->userdata('userId')) {
            $get = $this->input->get();
            if (isset($get['9dc3e944']) && $get['9dc3e944']=='6bcfe8f219569f8e') {
                $userId = $this->session->userdata('doctorId');
                $this->Background_Model->userFirstCardCreateMail($userId);
                return redirect('paymentSuceess');
            }

            delete_cookie('doctorToken'); 
            delete_cookie('userToken'); 
            $this->session->unset_userdata('doctorRole');
            $this->session->unset_userdata('doctorId');
            $this->session->unset_userdata('doctorImage');
            $this->session->unset_userdata('doctorName');
            $this->session->unset_userdata('userRole');
            $this->session->unset_userdata('userId');
            $this->session->unset_userdata('userImage');
            $this->session->unset_userdata('username');

            $this->template->content->view('front/payment-successful', $this->data); 
            $this->template->publish();
            return;
        }
        return redirect('signup');
    }

    public function cardList() {
        if($this->session->userdata('doctorId')) {
            $this->data['doctorToken'] = $this->input->cookie('doctorToken');
            $this->data['myCardData'] = $this->apicall->post('/users/getUserCardList', [], false);
            $this->data['cardCount'] = ($this->data['myCardData']->status == 1) ? count($this->data['myCardData']->data) : 0;
            $get = $this->input->get();
            if ($this->data['cardCount'] == 0) {
                $this->data['showPopup'] = 1;
            }

            $this->template->content->view('front/cardlist', $this->data); 
            $this->template->publish();
            return;
        }
        
        $this->session->set_flashdata('error', 'Singup first!');
        return redirect('signup');
    }

    public function checkProviderValidEmail() {
        if ($this->input->server('REQUEST_METHOD') == 'POST' && !empty($this->input->post('email'))) {
            $user = $this->Users_Model->get(['email' => $this->input->post('email'), 'status' => [0, 1, 2,4],'role'=>['1','2']], TRUE);
            if (!empty($this->input->post('id'))) {
                if (isset($user->id) && $user->id == $this->input->post('id')) {
                    echo 'true';return;
                }
            }
            if (!empty($user)) {
                echo "false";
            } else {
                echo "true";
            }
        }
    }

    public function checkUserValidEmail() {
        if ($this->input->server('REQUEST_METHOD') == 'POST' && !empty($this->input->post('email'))) {
            $user = $this->Users_Model->get(['email' => $this->input->post('email'), 'status' => [0, 1, 2, 4]], TRUE);
            if (!empty($this->input->post('id'))) {
                if (isset($user->id) && $user->id == $this->input->post('id')) {
                    echo 'true';return;
                }
            }
            if (!empty($user)) {
                echo "false";
            } 
            else {
                echo "true";
            }
        }
    }

    public function checkProviderReferralCode() {
        if ($this->input->server('REQUEST_METHOD') == 'POST' && !empty($this->input->post('referralCode'))) {
            $user = $this->Users_Model->get(['referralCode' => $this->input->post('referralCode'), 'status' => [0, 1, 2,4],'role'=>3], TRUE);
            if (!empty($user)) {
                echo "true";
            } else {
                echo "false";
            }
        }
    }
    
    public function forgotpassword() {
        if($this->session->userdata('doctorId')){
            return redirect('cardList');
        }
        $this->data['pageTitle'] = ['title' => 'Forgot Password', 'icon' => ''];
        $this->data['meta'] = ['title' => '', 'desc' => ''];
        
        $data = $this->input->post();
        
        if(!empty($data)){
            $getUserData = $this->Users_Model->get(['email'=> $data['email'], 'role' => [2,3], 'status'=> [0,1,4]], true);
            if(!empty($getUserData)){
                $post['verificationCode'] = $this->Common_Model->random_string(4);
                $user = $this->Users_Model->setData($post, $getUserData->id);
                if(!empty($user)) {
                    $this->Background_Model->userVerificationMail($user);
                    $this->session->set_flashdata('success', 'Success! Please verify email');
                    return redirect('verifyAccount/'.$user);
                } else {
                    $this->session->set_flashdata('error', 'Something went wrong please try again');
                    return redirect('forgotpassword');
                }
            } else {
                $this->session->set_flashdata('error', 'Invalid email');
                return redirect('forgotpassword');
            }
        }
        $this->template->content->view('front/forgot-password.php', $this->data); 
        $this->template->publish();
    }

    public function verify($id=''){
        $this->data['pageTitle'] = ['title' => 'Verify Account', 'icon' => ''];
        $this->data['meta'] = ['title' => '', 'desc' => ''];
        $data = $this->input->post();
        if(!empty($data)){
            $user = $this->Users_Model->get(['id' => $id, 'verificationCode'=> $data['verificationCode'], 'status' => [0,1,4]], true);
            if(empty($data['verificationCode'])) {
                $this->session->set_flashdata('error', 'Please Enter verification code');
                return redirect('verifyAccount/'.$id);
            }
            else if(!empty($user)){
                $this->session->set_flashdata('success', 'Your account was verified');
                return redirect('changePassword/'.$id);
            } else {
                $this->session->set_flashdata('error', 'Wrong verification code');
                return redirect('verifyAccount/'.$id);
            }
        }
        
        $u = $this->Users_Model->get(['id' => $id, 'status' => [0,1,4]], true);
        $this->data["user"] = $u;
        if(empty($u)) {
            $this->session->set_flashdata('error', 'Something went wrong please try again');
            return redirect('login');
        }

        $this->template->content->view('front/verify', $this->data); 
        $this->template->publish();
    }

    public function changePassword($id=''){
        $this->data['pageTitle'] = ['title' => 'Change Password', 'icon' => ''];
        $this->data['meta'] = ['title' => '', 'desc' => ''];
        $data = $this->input->post();
        if(!empty($data)){
            $setData['password'] = $this->Common_Model->convert_to_hash($data['password']);
            $changePass = $this->Users_Model->setData($setData, $id);
            if(!empty($changePass)){
                $this->session->set_flashdata('success', 'Password changed successfully');
                return redirect('login');
            } else{
                $this->session->set_flashdata('error', 'Something went wrong please try again');
                return redirect('changePassword/'.$id);
            }
        }
      
        $this->template->content->view('front/change-password', $this->data); 
        $this->template->publish();
    }

    public function logout() {
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
        return redirect('login');
    }

}
?>
