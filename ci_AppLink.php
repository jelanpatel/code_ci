<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class AppLink extends MY_Controller {
    
    public function __construct() {
        parent::__construct();
       $this->load->model('Resources_Model','Resources');
       $this->load->model('Users_Model','Users');
       $this->load->model('User_Services_Model','Services');
    }

    public function index($host = "", $data = "") {
        $meta = [];
        if (!empty($data) && $host == "blog") {
            $blog = $this->Resources->get(['id' => $data], TRUE);
            
            if (!empty($blog)) {
                $meta['title'] = $blog->metatitle;
                $meta['desc'] = $blog->metadescription;
                $meta['metaKeyword'] = $blog->metakeyword;
                $meta['image'] = $blog->imageUrl;                
                $meta['slug'] = $blog->slug;                
            }
        }else if (!empty($data) && $host == "provider-profile") {
            $userData = $this->Users->get(['id' => $data,'role'=>3,'status'=>1,'providerWebStep'=>3], TRUE);
            if (!empty($userData)) {
                $meta['title'] = $userData->name;
                $meta['desc'] = $userData->bio;
                $meta['image'] = $userData->profileimage;               
            }
        }else if (!empty($data) && $host == "service") {
            $serviceData = $this->Services->get(['id'=>$data,'status'=>1], true);
            if (!empty($serviceData)) {
                $meta['title'] = $serviceData->name;
                $meta['desc'] = $serviceData->name;
            }
        }else if (!empty($data) && $host == "upcoming-appointment") {
            $userData = $this->Users->get(['id' => $data,'role'=>2,'status'=>1], TRUE);
            if (!empty($userData)) {
                $meta['title'] = $userData->name;
                $meta['image'] = $userData->profileimage;               
            }
        }else if (!empty($data) && $host == "referral") {
            $this->session->set_userdata(array('referralcode'=>$data));
        }
        return $this->load->view('deepView', ["host" => $host, 'data' => $data, "meta" => $meta]);
    }
    
    public function appleAppSiteAssociation () {
        header('Content-Type: application/json');
        echo '{"applinks": {"apps": [],"details": [{"appID": "525H5P968B.com.app.Chiry","paths": ["*"]}]}}';
        return;
    }
}
