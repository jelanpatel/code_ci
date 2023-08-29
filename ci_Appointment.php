<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Appointment extends MY_Controller {
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
            // echo "<pre>";print_r($this->data['serviceForPlanList']);die;
            
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
        $this->data['pageTitle'] = ['title' => 'Appointments', 'icon' => ''];
        $this->data['active']= "appointment_doctor";
        $this->template->content->view('doctor/appointment', $this->data);
        $this->template->publish();
    }

    public function rescheduleAppointment($userAppointmentId='', $timing="", $startTimestamp="", $endTimestamp=""){
        $resc_data['langType'] = '1';
        $resc_data['token'] = $this->data['cookieData']['doctorToken'];
        $resc_data['userAppointmentId'] = $userAppointmentId;
       // $resc_data['userAvailabilityId'] = $userAvailabilityId;
        $resc_data['timeRange'] = $timing;
        $resc_data['startDateTime'] = $startTimestamp;
        $resc_data['endDateTime'] = $endTimestamp;
        $result = $this->apicall->post('/doctor/rescheduleUserAppointmentNew', $resc_data, false);
        if(!empty($result)){
            if(isset($result->status) && $result->status == 1){
                $this->session->set_flashdata('success', $result->message);
                return redirect(APPOINTMENT_DOCTOR);
            } else {
                $this->session->set_flashdata('error', $result->message);
                return redirect(APPOINTMENT_DOCTOR);
            }
        }

    }

    public function calender(){
        $this->data['pageTitle'] = ['title' => 'Appointments', 'icon' => ''];
        $this->load->library('Calendar');
        $calendar = new Calendar();
 
        $this->data['calendar'] = $calendar->show();
        $this->data['active']= "appointment_doctor";
        $this->template->content->view('doctor/calender', $this->data);
        $this->template->publish();
    }
}
