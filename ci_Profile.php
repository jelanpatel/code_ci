<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Profile extends MY_Controller {
    public function __construct() {
        parent::__construct();
        $this->load->model('Background_Model');
        $this->load->model('Common_Model');
        $this->load->library('apicall');
        $this->data['cookieData'] = $this->input->cookie();
        if(!empty($this->data['cookieData']['doctorToken'])){
            $request['token'] = $this->data['cookieData']['doctorToken'];
            $request['langType'] = '1';
            $this->data['userInfo'] = $this->apicall->post('/users/getUserInfo', $request, false);
            if($this->data['userInfo']->data->isStripeConnect == 0){
                return redirect(DOCTOR_STRIPE);
            } else if($this->data['userInfo']->data->isPayment == 0){
                return redirect(DOCTOR_STRIPE);
            } else if($this->data['userInfo']->data->isBankDetail == 0){
                return redirect(BANK_DOCTOR);
            }
            $this->data['availability_data'] = $this->apicall->post('/users/getDoctorAvailabilitySetting', $request, false);
            $request['isPage'] = "1";
            $this->data['clientList'] = $this->apicall->post('/plan/patientsList', $request, false);
            $this->data['serviceForPlanList'] = $this->apicall->post('/plan/serviceList', $request, false);
            
            $get_medical = [];
            $get_medical['langType'] = '1';
            $get_medical['token'] = $this->data['cookieData']['doctorToken'];
            $get_medical['page'] = '1';
            $get_medical['limit'] = '100';
            $this->data['userLocationList'] = $this->apicall->post('/users/getUserLocationList', $get_medical, false);

        }
        $this->template->set_template('DoctorTemplate',$this->data);
        $this->Common_Model->checkAuth('3');
    }

    public function index() {
        if(!empty($this->data['cookieData']['doctorToken'])){
            $request['token'] = $this->data['cookieData']['doctorToken'];
            $request['langType'] = '1';
            $this->data['languageData'] = $this->apicall->post('/common/getLanguagesList', $request, false);
            $this->data['categoryData'] = $this->apicall->post('/common/getProfessionList', $request, false);
            $this->data['specialtiesData'] = $this->apicall->post('/common/getSpecialtiesList', $request, false);
            $this->data['professionalData'] = $this->apicall->post('/users/getProfessionalInfo', $request, false);
            //echo "<pre>"; print_r($this->data['professionalData']); exit;
            $this->data['stateData'] = $this->apicall->post('/common/getStateList', $request, false);

            if($this->input->server('REQUEST_METHOD') == 'POST'){
                $data = $this->input->post();
                $requestUser['langType'] = '1';
                $requestUser['token'] = $this->data['cookieData']['doctorToken'];

                if(isset($data['fromNamePersonal']) && $data['fromNamePersonal'] == 1){
                    $requestUser['birthdate'] = $data['byear'].'-'.$data['bmonth'].'-'.$data['bday'];
                    $requestUser['preferredLanguage'] = $data['language'];
                    $requestUser['name'] = $data['name'];
                    $requestUser['gender'] = $data['gender'];
                    $requestUser['phone'] = $data['phone'];
                    $requestUser['emergencyContact'] = $data['emergencyContact'];
                    $requestUser['profileStatus'] = '1';
                }

                // contact Info
                if(isset($data['fromNameContact']) && $data['fromNameContact'] == 1){
                    $requestUser['address'] = $data['address'];
                    $requestUser['stateName'] = $data['stateName'];
                    $requestUser['cityName'] = $data['cityName'];
                    $requestUser['zipcode'] = $data['zipcode'];
                    $requestUser['latitude'] = $data['latitude'];
                    $requestUser['longitude'] = $data['longitude'];
                    $requestUser['profileStatus'] = '1';
                    #echo "<pre>"; print_r($requestUser); exit;
                }

                // professional
                if(isset($data['fromNameProfession']) && $data['fromNameProfession'] == 1){
                    if(isset($data['companyName'])) {
                        $requestUser['companyName'] = $data['companyName'];
                    }
                    $requestUser['virtualPrice'] = $data['virtualPrice'];
                    $requestUser['mobilePrice'] = $data['mobilePrice'];
                    $requestUser['onsitePrice'] = $data['onsitePrice'];
                    $requestUser['practiceYear'] = $data['practiceYear'];
                    $requestUser['licenseNumber'] = $data['licenseNumber'];
                    $requestUser['licenseStartDate'] = $data['licenseStartDate'];
                    $requestUser['licenseEndDate'] = $data['licenseEndDate'];
                    $requestUser['npiNumber'] = $data['npiNumber'];
                    $requestUser['practiceStateId'] = $data['practiceStateId'];
                    $requestUser['bio'] = $data['bio'];
                    $requestUser['insuranceImage'] = $data['insuranceImage'];
                    $requestUser['licenseImage'] = $data['licenseImage'];
                    $requestUser['insuranceStartDate'] = $data['insuranceStartDate'];
                    $requestUser['insuranceEndDate'] = $data['insuranceEndDate'];
                    $requestUser['professions'] = $data['professions'];
                    $requestUser['specialties'] = $data['specialties'];
                    $requestUser['profileStatus'] = '1';
                    $saveUser = $this->apicall->post('/users/saveProfessionalInfo',$requestUser, false);

                }
                if((isset($data['fromNamePersonal']) && $data['fromNamePersonal'] == 1) || (isset($data['fromNameContact']) && $data['fromNameContact'] == 1)){
                    $saveUser = $this->apicall->post('/users/saveUserProfile',$requestUser, false);
                }
                
                if(isset($data['fromNameSecurity']) && $data['fromNameSecurity'] == 1){
                    $requestUser['oldPassword'] = $data['oldPassword'];
                    $requestUser['newPassword'] = $data['newPassword'];
                    $requestUser['profileStatus'] = '1';

                    $saveUser = $this->apicall->post('/auth/changePassword',$requestUser, false);
                }
                if($saveUser->status == '1'){
                    $this->session->set_flashdata('success',$saveUser->message);
                    return redirect(PROFILE_DOCTOR);
                } else {
                    $this->session->set_flashdata('error',$saveUser->message);
                    return redirect(PROFILE_DOCTOR);
                }
            }
        }

        $this->data['locationlist'] = [];
        if(!empty($this->data['cookieData']['doctorToken'])){
            $request['token'] = $this->data['cookieData']['doctorToken'];
            $request['langType'] = '1';
            $locationlist = $this->apicall->post('/users/getUserLocationList', $request, false);
            if(isset($locationlist->status) && $locationlist->status == 1 && !empty($locationlist->data) ) {
                $this->data['locationlist'] = $locationlist->data;
            }
        }
        #echo "<pre>"; print_r($this->data['locationlist']); exit;
        

        $this->data['pageTitle'] = ['title' => 'Profile', 'icon' => ''];
        $this->data['active']= "doctor_profile";
        $this->template->content->view('doctor/profile', $this->data);
        $this->template->publish();
    }
  
    public function changePassword(){
        if ($this->input->server('REQUEST_METHOD') == 'POST' && !empty($this->input->post('email'))) {
            $password = $this->Common_Model->convert_to_hash($this->input->post('oldPassword'));
            $user = $this->Users_Model->get(['email' => $this->input->post('email'), 'password' => $password, 'status' => [0, 1, 2, 4]], TRUE);
            
            if (!empty($user)) {
                echo "true";
            } else {
                echo "false";
            }
        }
    }
}
