<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends MY_Controller {
    public function __construct() {
        parent::__construct();
        $this->load->model('Background_Model');
        $this->load->model('Common_Model');
        $this->load->model('Cms_Model');
        $this->load->model('Faq_Model');
        $this->load->model('User_Location_Model');
        $this->load->library('apicall');
        $this->data['cookieData'] = $this->input->cookie();
        $this->data['doctorSeesionData'] = $this->session->userdata();

        if(!empty($this->data['cookieData']['doctorToken'])){
            $request['token'] = $this->data['cookieData']['doctorToken'];
            $request['langType'] = '1';
            $this->data['userInfo'] = $this->apicall->post('/users/getUserInfo', $request, false);
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

            // print_r($this->data['serviceForPlanList']);die;
            if($this->data['userInfo']->data->isStripeConnect == 0){
                return redirect(DOCTOR_STRIPE);
            } else if($this->data['userInfo']->data->isPayment == 0){
                return redirect(DOCTOR_STRIPE);
            } else if($this->data['userInfo']->data->isBankDetail == 0){
                return redirect(BANK_DOCTOR);
            }
        }
        $this->template->set_template('DoctorTemplate',$this->data);
        $this->Common_Model->checkAuth('3');

    }

    public function index() {
        $medical['langType'] = '1';
        $medical['token'] = $this->data['cookieData']['doctorToken'];

        $get_medical['langType'] = '1';
        $get_medical['token'] = $this->data['cookieData']['doctorToken'];
        $get_medical['page'] = '1';
        $get_medical['limit'] = '100';

        $this->data['medical_data'] = $this->apicall->post('/users/getMedicalHistoryPersonal', $medical, false);
        $this->data['medical_data_social'] = $this->apicall->post('/users/getMedicalHistorySocial', $medical, false);

        $this->data['medication_data'] = $this->apicall->post('/common/getMedicationsList', $get_medical, false);
        $this->data['dosage_data'] = $this->apicall->post('/common/getDosageList', $get_medical, false);
        $this->data['frequency_data'] = $this->apicall->post('/common/getFrequencyList', $get_medical, false);

        $this->data['allergies_data'] = $this->apicall->post('/common/getAllergiesTypeList', $get_medical, false);
        $this->data['health_data'] = $this->apicall->post('/common/getHealthIssuesList', $get_medical, false);
        $this->data['injuries_data'] = $this->apicall->post('/common/getInjuriesList', $get_medical, false);
        $this->data['surgeries_data'] = $this->apicall->post('/common/getSurgeriesList', $get_medical, false);
        $this->data['userLocationList'] = $this->apicall->post('/users/getUserLocationList', $get_medical, false);
        
        $this->data['pageTitle'] = ['title' => 'Home', 'icon' => ''];
        $this->data['active']= "dashboard_doctor";
        $requestData['token'] = $this->data['cookieData']['doctorToken'];
        $requestData['langType'] = '1';
        $this->data['dashboardInfo'] = $this->apicall->post('/doctor/getDoctorDashboard', $requestData, false);
        $this->template->content->view('doctor/dashboard', $this->data);
        $this->template->publish();
    }

    public function about_us() {
        $this->data['cms'] = $this->Cms_Model->get(['status' => 1, 'key' => 'aboutus'], TRUE);
        if(empty($this->data['cms'])) {
            return redirect();
        }
        if(!empty($this->data['cookieData']['doctorToken'])){
            $this->data['tokenDoctor'] = $this->data['cookieData']['doctorToken'];
            $request['langType'] = '1';
            $request['token'] = $this->data['cookieData']['doctorToken'];
            $this->data['feedData'] = $this->apicall->post('/common/getMyAppFeedback', $request, false);
        }

        $this->data['pageTitle'] = ['title' => 'About Chiry', 'icon' => ''];
        $this->data['active']= "about_doctor";
        $this->template->content->view('patients/aboutUs', $this->data);
        $this->template->publish();
    }

    public function term(){
        $this->data['cms'] = $this->Cms_Model->get(['status' => 1, 'key' => 'termscondition'], TRUE);
        if(empty($this->data['cms'])) {
            return redirect();
        }
        
        $this->data['pageTitle'] = ['title' => 'Terms & Condition', 'icon' => ''];
        $this->data['active']= "terms-condition";
        $this->template->content->view('patients/terms', $this->data);
        $this->template->publish();
    }

    public function privacy_policy(){
        $this->data['cms'] = $this->Cms_Model->get(['status' => 1, 'key' => 'privacypolicy'], TRUE);
        if(empty($this->data['cms'])) {
            return redirect();
        }
        
        $this->data['pageTitle'] = ['title' => 'Privacy Policy', 'icon' => ''];
        $this->data['active']= "privacy-policy";
        $this->template->content->view('patients/privacy_policy', $this->data);
        $this->template->publish();
    }

    public function appLicenceAgree(){
        $this->data['cms'] = $this->Cms_Model->get(['status' => 1, 'key' => 'appeula'], TRUE);
        if(empty($this->data['cms'])) {
            return redirect();
        }
       
        $this->data['pageTitle'] = ['title' => 'App license agreement', 'icon' => ''];
        $this->data['active']= "appLicenseAgree";
        $this->template->content->view('patients/privacy_policy', $this->data);
        $this->template->publish();
    }

    public function faq(){
        $this->data['faqData'] = $this->Faq_Model->get(['type' => 1 ,'status' => 1]);
        $this->data['tokenDoctor'] = $this->data['cookieData']['doctorToken'];
        $this->data['pageTitle'] = ['title' => "FAQs", 'icon' => ''];
        $this->data['active']= "faq";
        $this->template->content->view('patients/faq', $this->data);
        $this->template->publish();
    }

    public function notification(){
        $this->data['pageTitle'] = ['title' => 'Notification', 'icon' => ''];
        $this->data['active']= "notification";
        $this->template->content->view('patients/notification', $this->data);
        $this->template->publish();
    }

    public function setlatlong(){
        if($this->input->server('REQUEST_METHOD') == 'POST'){
            $data['latitude'] = $this->input->post('latitude');
            $data['longitude'] = $this->input->post('longitude');
            $id = $this->input->post('id');
            $response = $this->Users_Model->setData($data, $id);
            if(!empty($response)){
                $result = array('status'=>1, 'message'=>'Successfully Created', 'lat'=>$data['latitude'], 'log'=>$data['longitude']);
                echo json_encode($result);
                 exit();
            } else {
                $result = array('status'=>0, 'message'=>'Somethings wants wrong, please try again');
                echo json_encode($result); 
                exit();
            }
        } 
        exit();
    }
    public function shareInviteCode($type=''){
        $link = $this->data['userInfo']->data->referralLink;
        if($type == 1){
            echo 'https://www.facebook.com/sharer/sharer.php?u='.$link;
        }elseif($type == 2){
            echo 'https://wa.me/?text='.$link;
        }elseif($type == 3){
            echo 'http://twitter.com/share?url='.$link;
        }elseif($type == 4){
            echo 'fb-messenger://share/?link='.$link;
        }elseif($type == 5){
            echo 'https://www.linkedin.com/shareArticle?mini=true&url='.$link;
        }
        exit();        
    } 

    public function setUserLocation() {
        $req = $this->input->post();
        $req['token'] = $this->data['cookieData']['doctorToken'];
        $req['langType'] = '1';
        $save = $this->apicall->post('/users/setUserLocation', $req, false);
        if(!empty($save)){
            if(isset($save->status) && $save->status == 1){
                $this->session->set_flashdata('success', $save->message);
                return redirect($_SERVER['HTTP_REFERER']); 
                //return redirect(DASHBOARD_DOCTOR);
            } else {
                $this->session->set_flashdata('error', $save->message);
                return redirect($_SERVER['HTTP_REFERER']); 
                //return redirect(DASHBOARD_DOCTOR);
            }
        }
    }

}
