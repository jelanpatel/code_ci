<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Payment extends MY_Controller {
    public function __construct() {
        parent::__construct();
        $this->load->model('Background_Model');
        $this->load->model('Common_Model');
        $this->load->model('Cms_Model');
        $this->load->model('Faq_Model');
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
        }
        $this->template->set_template('DoctorTemplate',$this->data);
        $this->Common_Model->checkAuth('3');
    }

    public function index($id="") {
        $this->data['pageTitle'] = ['title' => 'Subscription', 'icon' => ''];
        $this->data['active'] = "payment";
        $this->data['priceId'] = $id;
        $request_data['token'] = $this->data['cookieData']['doctorToken'];
        $request_data['langType'] = '1';
        $this->data['getUserCard'] = $this->apicall->post('/users/getUserCardList', $request_data, false);
        if($this->input->server('REQUEST_METHOD') == 'POST'){
            $data = $this->input->post();
            $exp_date = explode('/',$data['exp_Date']);
            $insert_data['langType'] = '1';
            $insert_data['token'] = $this->data['cookieData']['doctorToken'];
            $insert_data['holderName'] = $data['holderName'];
            $insert_data['number'] = $data['number'];
            $insert_data['cvv'] = $data['cvv'];
            $insert_data['expMonth'] = $exp_date[0];
            $insert_data['expYear'] = $exp_date[1];
            $insert_data['isDefault'] = isset($data['isDefault']) ? $data['isDefault'] : '';
            $save_card = $this->apicall->post('/users/saveUserCard', $insert_data, false);
            if(isset($save_card->status) && $save_card->status == '1'){
                $this->session->set_flashdata('success', $save_card->message);
                return redirect(PAYMENT_DASHBOARD.'/'.$id);
            } else{
                $this->session->set_flashdata('error', $save_card->message);
                return redirect(PAYMENT_DASHBOARD.'/'.$id);
            }
        }

        $this->template->content->view('doctor/payment', $this->data);
        $this->template->publish();
    }

    public function remove_card($id = '', $priceId = ''){
        $remove_card['userCardId'] = $id;
        $remove_card['token'] = $this->data['cookieData']['doctorToken'];
        $remove_card['langType'] = '1';
        $removeCard = $this->apicall->post('/users/removeUserCard', $remove_card, false);
        if($removeCard->status == 1){
            $this->session->set_flashdata('success', $removeCard->message);
            return redirect(PAYMENT_DASHBOARD.'/'.$priceId);
        } else {
            $this->session->set_flashdata('error', $removeCard->message);
            return redirect(PAYMENT_DASHBOARD.'/'.$priceId);
        }
    }

    public function card_default($id = '', $priceId = ''){
        $card_def_val['token'] = $this->data['cookieData']['doctorToken'];
        $card_def_val['langType'] = '1';
        $card_def_val['userCardId'] = $id;
        $cardData = $this->apicall->post('/users/setCardDefault', $card_def_val, false);
        if($cardData->status == 1){
            $this->session->set_flashdata('success', $cardData->message);
            return redirect(PAYMENT_DASHBOARD.'/'.$priceId);
        } else {
            $this->session->set_flashdata('error', $cardData->message);
            return redirect(PAYMENT_DASHBOARD.'/'.$priceId);
        }
    }
}
?>
