<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Home extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('Background_Model');
        $this->load->model('Cms_Model');
        $this->load->model('Profession_Model');
        $this->load->model('Common_Model');
        $this->load->model('User_Profession_Model');
        $this->load->model('Users_Model');
        $this->load->model('ContactUs_Model');
        $this->load->model('Resources_Model');
        $this->load->model('User_Card_Model');
        $this->load->library('apicall');
        $this->template->set_template('FrontMainTemplate');
        $this->data['showMenu'] = false;
    }

    public function index($slug="") {
        if($slug!=""){
            $blogData = $this->Resources_Model->get(['status'=>'1','slug'=> $slug],true);
                if($blogData){ 
                $this->data['pageInfo'] = [
                    'pageName' => "Blog", 
                    'metaTitle' => $blogData->metatitle ? $blogData->metatitle : $blogData->title , 
                    'metaDescription' => $blogData->metadescription ? $blogData->metadescription : "",
                    'metaKeyword' => $blogData->metakeyword ? $blogData->metakeyword : "",
                    'metaImage' => $blogData->imageUrl,
                    'canonical' => base_url().$blogData->slug."/",
                    'icon' => '', 
                    'menu' => 'blog',
                    'slug' => $blogData->slug
                ];        
                $this->data['relatedBlogData'] = $this->Resources_Model->get(['status'=>1,'limit'=>3,'categoryId'=>$blogData->categoryId,'notid'=>$blogData->id]);
                $this->data['blogData'] = $blogData;
                $this->data['active']="blog";
                $this->data['pageTitle'] = ['title'=> 'Chiry | '.$blogData->title ];
                $this->template->content->view('front/blog-detail', $this->data);
                $this->template->publish();
                return;
            }
            else{
                redirect();
            }
        }

        $this->data['breadcrumb'] = [
            ['title' => 'Home', 'url' => 'admin', 'icon' => ''],
            ['title' => 'Home', 'url' => '', 'icon' => ''],
        ];
        
        $this->data['professions'] = $this->Profession_Model->get(['apiResponse'=>true,'status'=>'1']);
        $this->data['pageTitle'] = ['title' => 'Welcome', 'icon' => ''];
        $this->data['active']="home";
        $this->template->content->view('front/home', $this->data);
        $this->template->publish();
    }

    public function mime_content_type($filename) {
        if ($filename) {
            $mime_types = array(
                'txt' => 'text/plain',
                'htm' => 'text/html',
                'html' => 'text/html',
                'php' => 'text/html',
                'css' => 'text/css',
                'js' => 'application/javascript',
                'json' => 'application/json',
                'xml' => 'application/xml',
                'swf' => 'application/x-shockwave-flash',
                'flv' => 'video/x-flv',
                // images
                'png' => 'image/png',
                'jpe' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'jpg' => 'image/jpeg',
                'gif' => 'image/gif',
                'bmp' => 'image/bmp',
                'ico' => 'image/vnd.microsoft.icon',
                'tiff' => 'image/tiff',
                'tif' => 'image/tiff',
                'svg' => 'image/svg+xml',
                'svgz' => 'image/svg+xml',
                // archives
                'zip' => 'application/zip',
                'rar' => 'application/x-rar-compressed',
                'exe' => 'application/x-msdownload',
                'msi' => 'application/x-msdownload',
                'cab' => 'application/vnd.ms-cab-compressed',
                // audio/video
                'mp3' => 'audio/mpeg',
                'mp4' => 'video/mp4',
                'qt' => 'video/quicktime',
                'mov' => 'video/quicktime',
                '3gp' => ' video/3gpp',
                // adobe
                'pdf' => 'application/pdf',
                'psd' => 'image/vnd.adobe.photoshop',
                'ai' => 'application/postscript',
                'eps' => 'application/postscript',
                'ps' => 'application/postscript',
                // ms office
                'doc' => 'application/msword',
                'rtf' => 'application/rtf',
                'xls' => 'application/vnd.ms-excel',
                'ppt' => 'application/vnd.ms-powerpoint',
                // open office
                'odt' => 'application/vnd.oasis.opendocument.text',
                'ods' => 'application/vnd.oasis.opendocument.spreadsheet'
            );

            $var_d = explode('.', $filename);
            $ext = strtolower(array_pop($var_d));
            if (array_key_exists($ext, $mime_types)) {
                return $mime_types [$ext];
            } elseif (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME);
                $mimetype = finfo_file($finfo, $filename);
                finfo_close($finfo);
                return $mimetype;
            }
            return 'application/octet-stream';
        }
    }
    public function image ($image, $max_height = 200, $max_width = 200) {
    	if (isset($image) && !empty($image)) {
            $src_file =  getenv('UPLOADPATH') . basename($image);
            if (file_exists($src_file)) {
            	$ext = pathinfo($image, PATHINFO_EXTENSION);
            	
            	if(in_array($ext,['jpg','jpeg','png','gif'])){
            		$dst_file = dirname($src_file) . DIRECTORY_SEPARATOR . 'thumbnail_' . (!empty($max_height) || $max_height ? $max_height .'_' : '') . basename($src_file);
					
	                if (!file_exists($dst_file)) {
						
	                    list ( $width, $height, $image_type ) = getimagesize($src_file);
						
	                    switch ($image_type) {
	                        case 1 :
	                            $src = imagecreatefromgif($src_file);
	                            break;
	                        case 2 :
	                            $src = imagecreatefromjpeg($src_file);
	                            break;
	                        case 3 :
	                            $src = imagecreatefrompng($src_file);
	                            break;
	                        default :
	                            return '';
	                            break;
	                    }
						
	                    $x_ratio = $max_width / $width;
	                    $y_ratio = $max_height / $height;
						
	                    if (($width <= $max_width) && ($height <= $max_height)) {
	                        $tn_width = $width;
	                        $tn_height = $height;
	                    } elseif (($x_ratio * $height) < $max_height) {
	                        $tn_height = ceil($x_ratio * $height);
	                        $tn_width = $max_width;
	                    } else {
	                        $tn_width = ceil($y_ratio * $width);
	                        $tn_height = $max_height;
	                    }

	                    $tmp = imagecreatetruecolor($tn_width, $tn_height);

	                    /* Check if this image is PNG or GIF to preserve its transparency */
	                    if (($image_type == 1) or ( $image_type == 3)) {
                            imagealphablending($tmp, false);
                            imagesavealpha($tmp, true);
                            $transparent = imagecolorallocatealpha($tmp, 255, 255, 255, 127);
                            imagefill($tmp, 0, 0, $transparent);
                            //imagefilledrectangle($tmp, 0, 0, $tn_width, $tn_height, $transparent);
                            imagecopyresized($tmp, $src, 0, 0, 0, 0, $tn_width, $tn_height, $width, $height);
                            imagepng($tmp, $dst_file);
	                    } else {
                            imagecopyresampled($tmp, $src, 0, 0, 0, 0, $tn_width, $tn_height, $width, $height);
                            imagejpeg($tmp, $dst_file, 85);
                        }
						
	                    /*
	                     * imageXXX() has only two options, save as a file, or send to the browser.
	                     * It does not provide you the oppurtunity to manipulate the final GIF/JPG/PNG file stream
	                     * So I start the output buffering, use imageXXX() to output the data stream to the browser,
	                     * get the contents of the stream, and use clean to silently discard the buffered contents.
	                     */
	                    # imagejpeg($tmp, $dst_file, 85);
	                }
				}else{
					$dst_file = dirname($src_file) . DIRECTORY_SEPARATOR . ''. basename($src_file);
				}
				
                if (file_exists($dst_file)) {
                    header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
                    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
                    header('Pragma: no-cache');
                    $type = $this->mime_content_type($dst_file);
                    // echo $type;die();
                    header("Content-Type: " . $type . "");
                    header("Content-Disposition: attachment; filename=\"" .  basename($dst_file) . "\";");
                    header("Content-Length: " . filesize($dst_file));
                    readfile($dst_file);
                    exit();
                }
            }
        }

        //throw new CHttpException(404, Yii::t('app', 'File not found'));
    }

    public function contactUs() {
        $this->data['active']="contact-us";
        $this->data['page'] = ['menu'=>'contact-us','submenu'=>''];
        $this->data['pageTitle'] = ['title' => ' Contact Us', 'icon' => ''];
        $this->data['meta'] = ['title' => '', 'desc' => ''];
       // $this->data['cms'] = $this->Cms_Model->get(['status' => 1, 'key' => 'contactus'], TRUE);
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $data = $this->input->post();
            $data['status'] = '1';
            $set = $this->ContactUs_Model->setData($data);
           
            if($set){
                $this->session->set_flashdata('success','Sent success.');
                $this->Background_Model->contactUsMail($set);
                // $this->Background_Model->contactUsAdminMail($set);
            }else{
                $this->session->set_flashdata('error','Fail to save, Please try after sometime.');
            }   
            //$this->session->set_flashdata('success', 'Sent success.');
            return redirect('contact-us');
           
        }
       
        $this->template->content->view('front/contactUs', $this->data);        
        $this->template->publish();
              
    }

    public function blog() {
        $this->data['active']="blog";
        $this->data['page'] = ['menu'=>'blog','submenu'=>''];
        $this->data['pageTitle'] = ['title' => ' Blog', 'icon' => ''];
        $this->data['meta'] = ['title' => '', 'desc' => ''];
        $this->template->content->view('front/blog', $this->data);        
        $this->template->publish();
    }
    
    public function getBlogListRequest(){
        if($this->input->server('REQUEST_METHOD') == 'POST'){
            $page_number = ($this->input->post('page') != '') ? $this->input->post('page') : 1;
            $limit = ( $this->input->post('limit') != '') ? $this->input->post('limit') : 10;
            if ( $this->input->post('page') == 1) {
                $offset = 0;
            } else {
                if ($this->input->post('page') != '1') {
                    $offset = ($page_number * $limit) - $limit;
                } else {
                    $offset = 0;
                }
            }


            $data['type'] = ""; //1-User, 2-Doctor            
            if($this->session->userdata('userRole')) {
                $user_role = $this->session->userdata('userRole');
                if($user_role == 2) {
                    $data['type'] = 1;
                }              
            }
            if($this->session->userdata('doctorRole')) {
                $user_role = $this->session->userdata('doctorRole');
                if($user_role == 3) {
                    $data['type'] = 2;
                }              
            }

            $data['status'] = 1;
            $blog = $this->Resources_Model->get(array_merge($data,array('limit'=> $limit,'offset'=>$offset)));
            $totalData =  $this->Resources_Model->get(['status'=>'1'], false, true); 
            $blogData['status'] =  $totalData==0 ? 0 : 1;
            $blogData['total_page'] =  ceil($totalData / $limit);
            $blogData['data'] =  $blog;
            echo json_encode($blogData); 
            return;
        }
    }

    public function subscribe() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $post = $this->input->post();
           
            $data = array(
                'email' => isset($post['email']) ? $post['email'] : ''
            );
            $existData = $this->MailSubscribe_Model->get($data,true);
            //print_r($existData);die;
            if(!empty($existData)){
                if($existData->status == 1)
                {
                    echo "2";
                }
                else
                {
                    $data['status'] = 1;
                    $response = $this->MailSubscribe_Model->setData($data,$existData->id);
                    $this->Background_Model->userNewSubscriber(['id' => $response]);
                    $this->Background_Model->userNewSubscriberMailAdmin(['id' => $response]);
                    echo "1";
                }
            }else{
                $response = $this->MailSubscribe_Model->setData($data);
                if (!empty($response)) {
                    $this->Background_Model->userNewSubscriber(['id' => $response]);
                    $this->Background_Model->userNewSubscriberMailAdmin(['id' => $response]);
                    echo "1";
                } else {
                    echo "0";
                }
            }
            die();
        }
       
    }
    
    public function stripeOnboardReturn(){
        echo json_encode(['close'=>true]);
        die();
        //return redirect(base_url('app-link/stripe_return/1')); 
    }

    public function stripeOnboardRefresh(){
        echo json_encode(['close'=>true]);
        die();
        //return redirect(base_url('app-link/stripe_refresh/1'));
    }

    public function demoVideo($userName, $roomName) {
        $this->load->library('TwilioVideo');
        $twillio = new TwilioVideo();
            
        $result = $twillio->getVideoRoomToken(["identity" => $userName, "roomId" => $roomName]);
        $result["identity"] = $userName;
        $result["roomId"] = $roomName;
        // echo "<pre>";print_r($result);die;
        $this->load->view('demoVideo', $result);
    }

    public function service_book($data="", $free=""){
        if(!empty($data)){
            if(isset($_COOKIE['userToken']) && !empty($_COOKIE['userToken'])){
                return redirect(base_url(DASHBOARDSERVICE.'/'.$data));
            } else if(isset($_COOKIE['doctorToken']) && !empty($_COOKIE['doctorToken'])) {
                return redirect(base_url(DASHBOARD_DOCTOR));
            } else {
                if(!empty($free)){
                    $this->data['free'] = $free;
                    $doctor_data['langType'] = '1';
                    $doctor_data['doctorId'] = $data;
                    $doctor_data['userTimezone'] = $this->Common_Model->getusersystemtimezone();
                    $this->data['serviceDetail'] = $this->apicall->post('/patient/getDoctorDetailNonRegister', $doctor_data, false);
                } else {
                    $get_service['langType'] = '1'; 
                    $get_service['serviceId'] = $data; 
                    $this->data['serviceDetail'] = $this->apicall->post('/patient/getServiceDoctorDetailNonRegister', $get_service, false);
                    error_log("\n\n -------------------------------------" . date('c'). " \n ServiceDetails => ".json_encode($this->data['serviceDetail']).", Success =>Success", 3, FCPATH.'worker/service_link-'.date('d-m-Y').'.txt');
    
                    if(isset($this->data['serviceDetail']->status) && $this->data['serviceDetail']->status == '1'){
                        if(!empty($this->data['serviceDetail']->doctorData)){
                            
                            $sr_avData['langType'] = '1';
                            $sr_avData['serviceId'] = $data;
                            $sr_avData['doctorId'] = $this->data['serviceDetail']->doctorData->userId;
                            $sr_avData['userTimezone'] = $this->Common_Model->getusersystemtimezone();

                            $this->data['service_ava'] = $this->apicall->post('/services/getDoctorServiceAvailability', $sr_avData, false);
                            error_log("\n\n -------------------------------------" . date('c'). " \n ServiceAvability => ".json_encode($this->data['service_ava']).", Success =>Success", 3, FCPATH.'worker/service_link-'.date('d-m-Y').'.txt');
                        }
                    }
                }
                if ($this->input->server('REQUEST_METHOD') == 'POST') {
                    $post_data = $this->input->post();

                    if(!empty($post_data)){
                        
                        if(isset($post_data['coupanCode']) && !empty($post_data['coupanCode'])) {
                            $this->load->model('Discount_Coupon_Model');
                            $datax = array();
                            $datax['promocode'] = $post_data['coupanCode'];
                            $datax['status'] = 1;
                            $datax['checkValidDate'] = true;
                            $codeData = $this->Discount_Coupon_Model->get($datax,true);
                            if (!empty($codeData)) {
                            }
                            else {
                                $this->session->set_flashdata('error', 'Invalid promo code.');
                                if(!empty($free)) {
                                    return redirect(FREESERVICEBOOKING.'/'.$data.'/'.$free);
                                } 
                                else {
                                    return redirect(SERVICEBOOKING.'/'.$data);
                                }
                            }
                        }
                        //echo "<pre>"; print_r($post_data); exit;

                        $user = $this->Users_Model->get(['email' => $this->input->post('email'), 'status' => [0, 1, 2, 4]], TRUE);
                        if(empty($user)){
                            $user_data['email'] = $post_data['email'];
                            $user_data['name'] = $post_data['name'];
                            $user_data['phone'] = $post_data['phone'];
                            $user_data['password'] = $this->Common_Model->convert_to_hash($post_data['password']);
                            $user_data['role'] = '2';
                            $user_data['status'] = '1';
                            $user_data['timeZone']  = $this->Common_Model->getusersystemtimezone();
                            $setUser = $this->Users_Model->setData($user_data);
                            if(!empty($setUser)){
                                $authData = [];
                                $authData['userId'] = $setUser;
                                $authData['deviceType'] = '3';
                                $authData['token'] = $this->Common_Model->getToken(120);
            
                                $getAuth = $this->Auth_Model->get(['userId' => $setUser], TRUE);
                                if(!empty($getAuth)) {
                                    $authid = $this->Auth_Model->setData($authData, $getAuth->id);
                                } else {
                                    $authid = $this->Auth_Model->setData($authData);
                                }
                            }

                        } else {
                            $authData = [];
                            $authData['userId'] = $user->id;
                            $authData['deviceType'] = '3';
                            $authData['token'] = $this->Common_Model->getToken(120); 
                            $getAuth = $this->Auth_Model->get(['userId' => $user->id], TRUE);
                            if(!empty($getAuth)) {
                                $authid = $this->Auth_Model->setData($authData, $getAuth->id);
                            } else {
                                $authid = $this->Auth_Model->setData($authData);
                            }
                        }
                        if(!empty($authid)){
                            if(empty($free)){
                                if(!empty($user)){
                                    if($user->role == 3){
                                        $this->session->set_flashdata('error', 'FAIL TO GET CARD TOKEN');
                                        return redirect(SERVICEBOOKING.'/'.$data);
                                    }else{
                                        $this->load->library('stripe',array('type'=>'1'));
                                    }
                                    if(empty($user->stripeCustomerId)){
                                        $customer['description'] = '#UserId:'.$user->id.", Name: ".$user->name.', Is registred from App';
                                        $customer['email'] = $user->email;
                                
                                        //Customer data
                                        $customerData = $this->stripe->addCustomer($customer);
                                        if (isset($customerData['error']) && !empty($customerData['error'])) {
                                            $this->session->set_flashdata('error', $customerData['error']);
                                            return redirect(SERVICEBOOKING.'/'.$data);
                                        }
                                        $stripeCustomerId = $customerData['id'];
                                        $this->User->setData(['stripeCustomerId' => $stripeCustomerId,'stripeCustomerJson'=>json_encode($customerData)], $user->id);
                                    }else{
                                        $stripeCustomerId = $user->stripeCustomerId;
                                    }
                                } else {
                                    $this->load->library('stripe',array('type'=>'1'));
                                    
                                    $customer['description'] = '#UserId:'.$setUser.", Name: ".$user_data['name'].', Is registred from App';
                                    $customer['email'] = $user_data['email'];
                            
    
                                    //Customer data
                                    $customerData = $this->stripe->addCustomer($customer);
                                    if (isset($customerData['error']) && !empty($customerData['error'])) {
                                        $this->session->set_flashdata('error', $customerData['error']);
                                        return redirect(SERVICEBOOKING.'/'.$data);
                                    }
                                    $stripeCustomerId = $customerData['id'];
                                    $this->User->setData(['stripeCustomerId' => $stripeCustomerId,'stripeCustomerJson'=>json_encode($customerData)], $setUser);
                                }
                                
                                //REGISTRING CARD IN STRIPE
                                $exp_date = explode('/',$post_data['ex_date']);
                                $stripeCardData['card']['number'] = str_replace(' ','',$post_data['card_number']);
                                $stripeCardData['card']['exp_month'] = $exp_date[0];
                                $stripeCardData['card']['exp_year'] = $exp_date[1];
                                $stripeCardData['card']['cvc'] = $post_data['cvv'];
                                $stripeCardData['card']['name'] = $post_data['card_name'];
                                $stripeToken = $this->stripe->createToken($stripeCardData);
                                //END OF REGISTRING CARD IN STRIPE
                        
                                if(empty($stripeToken)){ //
                                    $this->session->set_flashdata('error', 'FAIL TO GET CARD TOKEN');
                                    return redirect(SERVICEBOOKING.'/'.$data);
                                }elseif(isset($stripeToken['error'])){ //FAIL TO REGISTER CARD IN STRIPE
                                    $this->session->set_flashdata('error', 'FAIL TO REGISTER CARD IN STRIPE');
                                    return redirect(SERVICEBOOKING.'/'.$data);

                                }elseif(!isset($stripeToken["id"]) || $stripeToken["id"]==""){ //FAIL TO GET CARD TOKEN
                                    $this->session->set_flashdata('error', 'FAIL TO GET CARD TOKEN');
                                    return redirect(SERVICEBOOKING.'/'.$data);
                                }
                        
                                $responseCreateCard = $this->stripe->createCard(['customer_id' => $stripeCustomerId,'source' => $stripeToken['id'],]);
                        
                                if(empty($responseCreateCard)){ //FAIL TO GET CARD TOKEN
                                    $this->session->set_flashdata('error', 'FAIL TO GET CARD TOKEN');
                                    return redirect(SERVICEBOOKING.'/'.$data);
                                }elseif(isset($responseCreateCard['error'])){ //FAIL TO REGISTER CARD IN STRIPE
                                    $this->session->set_flashdata('error', 'FAIL TO REGISTER CARD IN STRIPE');
                                    return redirect(SERVICEBOOKING.'/'.$data);
                                }elseif(!isset($responseCreateCard["id"]) || $responseCreateCard["id"]==""){ //FAIL TO GET CARD TOKEN
                                    $this->session->set_flashdata('error', 'FAIL TO GET CARD TOKEN');
                                    return redirect(SERVICEBOOKING.'/'.$data);
                                }
                                
                                $cardData = array();
                                $cardData['userId'] = empty($user) ? $setUser : $user->id;
                                $cardData['customerId'] = $responseCreateCard['customer'];
                                $cardData['cardId'] = $responseCreateCard['id'];
                                $cardData['cardBrand'] = $responseCreateCard['brand'];
                                $cardData['last4'] = $responseCreateCard['last4'];
                                $cardData['month'] = $responseCreateCard['exp_month'];
                                $cardData['year'] = $responseCreateCard['exp_year'];
                                $cardData['holderName'] = $post_data['card_name'];
                                $cardData['cardJson'] = json_encode($responseCreateCard);
                                // if (isset($apiData['data']['isDefault']) && $apiData['data']['isDefault'] == 1) {
                                //     $cardData['isDefault'] = 1;
                                    $this->User_Card_Model->setData(['userIds'=>$cardData['userId'],'isDefault'=>0]);
                                // }
                                $cardId = $this->User_Card_Model->setData($cardData);
                            }
                            $discountCouponCode = "";
                            if(isset($post_data['coupanCode']) && !empty($post_data['coupanCode'])) { 
                                $discountCouponCode = $post_data['coupanCode'];
                            }
                            $service_book['langType'] = '1';
                            $service_book['token'] = $authData['token'];
                            $service_book['doctorId'] = $post_data['doctorId'];
                            $service_book['discountCouponCode'] = $discountCouponCode;
                            $service_book['startDateTime'] = $post_data['starttimeservice'];
                            $service_book['endDateTime'] = $post_data['endtimeservice'];
                            $service_book['appointmentType'] = $post_data['service_type'];
                            $service_book['timeRange'] = $post_data['timing'];
                            $service_book['serviceId'] = $data;
                            if(!empty($cardId) && empty($free)){
                                $service_book['location'] = $post_data['location'];
                                $service_book['latitude'] = $post_data['lati'];
                                $service_book['longitude'] = $post_data['long'];
                                $service_book['userCardId'] = $cardId;
                            } else {
                                $service_book['isfreeconsult'] = '1';
                            }
                            if(isset($free) && !empty($free)){
                                $sucess_booked = $this->apicall->post('/patient/bookAppointmentNew', $service_book, false);
                            } else {
                                $sucess_booked = $this->apicall->post('/services/bookServiceAppointment', $service_book, false);
                            }
                            if(!empty($sucess_booked) && $sucess_booked->status == 1){
                                $this->session->set_flashdata('success', $sucess_booked->message);
                                if(empty($free)){
                                    $doctor_id_sucess = isset($sr_avData['doctorId']) && !empty($sr_avData['doctorId']) ? $sr_avData['doctorId'] : '';
                                } else if(!empty($free)){
                                    $doctor_id_sucess = $data;
                                }
                                return redirect(SERVICEBOOKINGSUCESS.'/'.$doctor_id_sucess);
                            } else {
                                $this->session->set_flashdata('error', $sucess_booked->message);
                                if(!empty($free)){
                                    return redirect(FREESERVICEBOOKING.'/'.$data.'/'.$free);
                                } else {
                                    return redirect(SERVICEBOOKING.'/'.$data);
                                }
                            }
                        }
                        else{
                            $this->session->set_flashdata('error', 'Something want wrong');
                            if(!empty($free)){
                                return redirect(FREESERVICEBOOKING.'/'.$data.'/'.$free);
                            } else {
                                return redirect(SERVICEBOOKING.'/'.$data);
                            }
                        }
                    }
                    
                }
                #echo "<pre>"; print_r($this->data); exit;
                $this->load->view('front/service_description', $this->data);
            }
        }
    }

    public function service_sucess($data=""){
        if(!empty($data)){
            $doctor_data['langType'] = '1';
            $doctor_data['doctorId'] = $data;
            $this->data['doctorData'] = $this->apicall->post('/patient/getDoctorDetailNonRegister', $doctor_data, false);
            $this->load->view('front/service_success', $this->data);
        }
    }

    public function provider_profile($data=""){
        if(!empty($data)){
            if(isset($_COOKIE['userToken']) && !empty($_COOKIE['userToken'])){
                return redirect(base_url(DOCTOR_PROFILE_SHARE.'/'.$data));
            } else if(isset($_COOKIE['doctorToken']) && !empty($_COOKIE['doctorToken'])) {
                return redirect(base_url(DASHBOARD_DOCTOR));
            } else {
                $this->data['doctorId'] = $data;
                $this->load->view('front/provide-profile', $this->data);
            }
        } 
    }
}

