<?php

require_once FCPATH . "vendor/autoload.php";

class Apicall {

    private $_ci;
    private $apiURL = "";

    public function __construct() {
        $this->_ci = & get_instance();
        $this->apiUrl = base_url('api');
        $this->client = new \GuzzleHttp\Client();
        $this->token = '';
        $this->_ci->load->helper('cookie');
        if(!empty($this->_ci->input->cookie('doctorToken',true))){
            $this->token = $this->_ci->input->cookie('doctorToken',true);
        }
        /* $this->_ci->load->library('session');
        if($this->_ci->session->userdata('user_session')){
            $this->user_data = $this->_ci->session->userdata('user_session');
            $this->token = $this->user_data->token;
        }*/
    }

    public function post($url,$req=[],$json='true') {
        $apiUrl = $this->apiUrl."".$url;
        $req['langType'] = "1";
        $req['deviceType'] = "3";
        if(!isset($req['token']) || empty($req['token'])){
            $req['token'] = $this->token;
        }
        
        if(!empty($this->_ci->input->cookie('referral',true))){
            $req['code'] = $this->_ci->input->cookie('referral',true);
        }
        
        $request['data'] = $req;
        // print_r($url);
        // print_r($request); die;
        try {
            $curl = curl_init();
            curl_setopt_array($curl, array(
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS =>json_encode($request),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json"
            ),
            ));
            $response = curl_exec($curl);
            curl_close($curl);
            if($json == 'true'){
                return $response;
            }else{
                return json_decode($response);
            }
        }catch (Exception $e) {
            return json_encode(["status" => '0','message'=>'Something went wrong from server']);
        }
    }
}
