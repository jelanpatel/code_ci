<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Message extends MY_Controller {
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

    public function index($id='') {
        $this->data['userId'] = $id;
        if(isset($id) && !empty($id)){
            $this->data['userId'] = $id;
        }
        $this->data['pageTitle'] = ['title' => 'Messages', 'icon' => ''];
        $this->data['active']= "message_doctor";
        $this->template->content->view('doctor/message', $this->data);
        $this->template->publish();
    }

}
