<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Common_Model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->library('email');
        $this->load->model('Users_Model', 'User');
    }

    // Get Unique No
    public function get_Unique_No() {
        return uniqid();
    }

    // Get Random Number
    public function random_string($length) {
        $key = '';
        $keys = array_merge(range(0, 9));
        for ($i = 0; $i < $length; $i++) {
            $key .= $keys[array_rand($keys)];
            //$key .= '1';
        }
        return $key;
    }

    //for get file extantion
    public function getFileExtension($file_name) {
        return '.' . substr(strrchr($file_name, '.'), 1);
    }

    public function random_alphnum_string($length) {
        $key = '';
        $keys = array_merge(range('a', 'z'), range('A', 'Z'), range('0', '9'));
        for ($i = 0; $i < $length; $i++) {
            $key .= $keys[array_rand($keys)];
        }
        return $key;
    }

    public function crypto_rand_secure($min, $max) {
        $range = $max - $min;
        if ($range < 1)
            return $min; // not so random...
        $log = ceil(log($range, 2));
        $bytes = (int) ($log / 8) + 1; // length in bytes
        $bits = (int) $log + 1; // length in bits
        $filter = (int) (1 << $bits) - 1; // set all lower bits to 1
        do {
            $rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));
            $rnd = $rnd & $filter; // discard irrelevant bits
        } while ($rnd > $range);
        return $min + $rnd;
    }

    //for get date format
    public function getDateFormat($date, $time = false, $humanReadable = false) {
        $result = "";
        if ($date != '0000-00-00 00:00:00') {
            if ($humanReadable) {
                $result = date("d M Y", strtotime($date));
            } else {
                if ($time) {
                    $result = date("m-d-Y H:i:s", strtotime($date));
                } else {
                    $result = date("m-d-Y", strtotime($date));
                }
            }
        }
        return $result;
    }

    //for generate token
    public function getToken($length, $config = []) {
        $token = "";
        $codeAlphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $codeAlphabet .= isset($config['notSmall']) && $config['notSmall'] ? '' : "abcdefghijklmnopqrstuvwxyz";
        $codeAlphabet .= "0123456789";
        $max = strlen($codeAlphabet); // edited
        for ($i = 0; $i < $length; $i++) {
            $token .= $codeAlphabet[$this->crypto_rand_secure(0, $max - 1)];
        }
        return $token;
    }

    // Convert string(password) into hash
    public function convert_to_hash($password) {
        return hash_hmac('SHA512', $password, 1);
    }

    //Start of getNotification function
    public function GetNotification($key, $lang = '1') {
        $colName = "value_en";
        if ($lang == '1') {
            $colName = "value_en";
        }
        $this->db->select('*');
        $this->db->from('tbl_apiresponse');
        $this->db->where("key", $key);
        $this->db->where("status", "1");
        $result = $this->db->get()->row_array();
        if (empty($result)) {
            return $key;
            // return "Message not found";
        }
        return $result[$colName];
    }

    //for send email
    public function mailsend($recipient, $subject, $body, $from = NULL, $file = NULL, $bcc = NULL,$replyTo = NULL,$replyToName = NULL,$icalContent=NULL) {
        try {
            $this->load->library('email');
            $mail['charset'] = "utf-8";
            $mail['newline']  = '\r\n';
            $mail['wordwrap']  = TRUE;
            $mail['mailtype'] = 'html';
            $mail['protocol'] = "smtp";
            $mail['smtp_host'] = getenv('SMTP_HOST');
            $mail['smtp_port'] = getenv('SMTP_PORT');
            $mail['smtp_user'] = getenv('SMTP_USER');
            $mail['smtp_pass'] = getenv('SMTP_PASSWORD');
            $mail['newline'] = "\r\n";
            $this->email->initialize($mail);
            $from = empty($from) ? getenv('FROM_EMAIL') : $from;
            $this->email->from($from, getenv('WEBSITE_NAME'));
            $this->email->subject($subject);
            $this->email->message($body);

            if (!empty($recipient) && is_array($recipient)) {
                foreach ($recipient as $key => $value) {
                    $this->email->to($value);
                }
            } elseif (!empty($recipient)) {
                $this->email->to($recipient);
            }            
            if (!empty($file) && is_array($file)) {
                foreach ($file as $key => $value) {
                    $this->email->attach($value);
                }
            } elseif (!empty($file)) {
                $this->email->attach($file);
            }

            $systemBCC = getenv('EMAIL_BCC');
            if (!empty($systemBCC) && is_array($systemBCC)) {
                foreach ($systemBCC as $key => $value) {
                    $this->email->bcc($value);
                }
            } elseif (!empty($systemBCC)) {
                $this->email->bcc($systemBCC);
            }

            if (!empty($bcc) && is_array($bcc)) {
                foreach ($bcc as $key => $value) {
                    $this->email->bcc($value);
                }
            } elseif (!empty($bcc)) {
                $this->email->bcc($bcc);
            }

            if ( !empty($icalContent) ) {
                $this->email->attach($icalContent, 'ical.ics', 'base64', 'text/calendar');
            }

            if(!empty($replyTo)){
                //$this->email->ClearReplyTos();
                $this->email->reply_to($replyTo, $replyToName);
            }
            return $this->email->send();
            //var_dump($this->email->print_debugger ( array ('headers','subject') ));
        } catch (Stripe\Error\Card $e) {
            return $e->getJsonBody();
        }
    }

    public function distance($lat1, $lon1, $lat2, $lon2) {
        if(!empty($lon1) && !empty($lon2)) {
            $theta = $lon1 - $lon2;
            $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
            $dist = acos($dist);
            $dist = rad2deg($dist);
            $miles = $dist * 60 * 1.1515;
            return round($miles, 1) . "";
        }
        else {
            return "";
        }
    }

    public function is_jsonDecode($json) {
        if (empty($json)) {
            return $json;
        }

        $ob = json_decode($json);
        if ($ob === null) {
            return $json;
        } else {
            return $ob;
        }
    }

    public function get_time_ago($time) {
        $time_difference = time() - $time;
        if ($time_difference < 1) {
            return '1 second ago';
        }
        $condition = array(12 * 30 * 24 * 60 * 60 => 'year',
            30 * 24 * 60 * 60 => 'month',
            24 * 60 * 60 => 'day',
            60 * 60 => 'hour',
            60 => 'minute',
            1 => 'second'
        );
        foreach ($condition as $secs => $str) {
            $d = $time_difference / $secs;
            if ($d >= 1) {
                $t = round($d);
                return $t . ' ' . $str . ( $t > 1 ? 's' : '' ) . ' ago';
            }
        }
    }

    //Function definition

    public function timeAgo($time_ago) {
        $time_ago = strtotime($time_ago);
        $cur_time = time();
        $time_elapsed = $cur_time - $time_ago;
        $hours = round($time_elapsed / 3600);
        $days = round($time_elapsed / 86400);
        $weeks = round($time_elapsed / 604800);
        $months = round($time_elapsed / 2600640);
        $years = round($time_elapsed / 31207680);

        //Hours
        if ($hours <= 24) {
            return "Today";
        }
        //Days
        else if ($days <= 7) {
            if ($days == 1) {
                return "yesterday";
            } else {
                return "$days days ago";
            }
        }
        //Weeks
        else if ($weeks <= 4.3) {
            if ($weeks == 1) {
                return "a week ago";
            } else {
                return "$weeks weeks ago";
            }
        }
        //Months
        else if ($months <= 12) {
            if ($months == 1) {
                return "a month ago";
            } else {
                return "$months months ago";
            }
        }
        //Years
        else {
            if ($years == 1) {
                return "a year ago";
            } else {
                return "$years years ago";
            }
        }
    }

    public function backroundCall($function, $data) {

        /*if (class_exists('GearmanClient')) {
            $this->load->library('lib_gearman');
            $this->lib_gearman->gearman_client();
            $this->lib_gearman->do_job_background('dc_' . $function, serialize($data));
        } else {
            $this->Background_Model->$function($data);
        }*/
        $this->Background_Model->$function($data);
    }

    // Start From Hear

    function object_to_array($data)
    {
        if (is_array($data) || is_object($data))
        {
            $result = array();
            foreach ($data as $key => $value)
            {
                $result[$key] = $this->object_to_array($value);
            }
            return $result;
        }
        
        return $data;
    }

    function checkDateText($timestamp="",$userTimezone = ""){
        if(empty($timestamp)){
            return "";
        }
        $today_date = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
        $today_date->setTimezone(new DateTimeZone($userTimezone));

        $tomorrow_date = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
        $tomorrow_date->setTimezone(new DateTimeZone($userTimezone));
        $tomorrow_date->add(new DateInterval('P1D'));

        $yesterday_date = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
        $yesterday_date->setTimezone(new DateTimeZone($userTimezone));
        $yesterday_date->modify("-1 day");
        
        $match_date = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
        $match_date->setTimezone(new DateTimeZone($userTimezone));
        $match_date->setTimestamp($timestamp);
        
        if ( $today_date->format('y-m-d') == $match_date->format('y-m-d') ) {
            return strtolower($match_date->format('h:i a')).", today";
        } elseif ( $tomorrow_date->format('y-m-d') == $match_date->format('y-m-d') ) {
            return strtolower($match_date->format('h:i a')).", tomorrow";
        } elseif ( $yesterday_date->format('y-m-d') == $match_date->format('y-m-d') ) {
            return strtolower($match_date->format('h:i a')).", yesterday";
        } else {
            //return strtolower($match_date->format('h:i a, m/d/Y'));
            return strtolower($match_date->format('h:i a, m-d-Y'));
        }

        /*
        $interval = $today_date->diff($match_date);
        if($interval->days == 0) {
            return strtolower($match_date->format('h:i a')).", today";
        } elseif($interval->days == 1 && $interval->invert == 0) {
            return strtolower($match_date->format('h:i a')).", tomorrow";
        } elseif($interval->days == 1 && $interval->invert == 1) {
            return strtolower($match_date->format('h:i a')).", yesterday";
        } else {
            return strtolower($match_date->format('h:i a, m/d/Y'));
        }
        */
    }

    function getDayAndDateName($timestamp = "",$userTimezone = ""){
        if(empty($timestamp)){
            return "";
        }
        $today_date = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
        $today_date->setTimezone(new DateTimeZone($userTimezone));
        
        $tomorrow_date = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
        $tomorrow_date->setTimezone(new DateTimeZone($userTimezone));
        $tomorrow_date->add(new DateInterval('P1D'));

        $match_date = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
        $match_date->setTimezone(new DateTimeZone($userTimezone));
        $match_date->setTimestamp($timestamp);
        $interval = $today_date->diff($match_date);
        
        if ( $today_date->format('y-m-d') == $match_date->format('y-m-d') ) {
            return "Today";
        } elseif ( $tomorrow_date->format('y-m-d') == $match_date->format('y-m-d') ) {
            return "Tomorrow, ".$match_date->format('d M');
        } else {
            return $match_date->format('D, d M');
        }
        /*
        if($interval->days == 0) {
            return "Today";
        } elseif($interval->days == 1 && $interval->invert == 0) {
            return "Tomorrow, ".$match_date->format('d M');
        } else {
            return $match_date->format('D, d M');
        }*/
    }

    function checkAppointmentStatusText($starttimestamp = "", $endtimestamp = "",$status = "",$userTimezone = "",$showLocationIcon = 0,$doctorGender=1){
        $response = array("isAppointment"=>"","textColor"=>"","text"=>"","showOtp"=>"0","showMsgIcon"=>"0","showLocationIcon"=>"0");
        if(!empty($starttimestamp) && !empty($endtimestamp)){
            $showLocationIcon = (in_array($showLocationIcon,array(2,3)) ? 1 : 0);
            $currentDateTime = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
            $currentDateTime->setTimezone(new DateTimeZone($userTimezone));

            $startDateTime = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
            $startDateTime->setTimezone(new DateTimeZone($userTimezone));
            $startDateTime->setTimestamp($starttimestamp);

            $endDateTime = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
            $endDateTime->setTimezone(new DateTimeZone($userTimezone));
            $endDateTime->setTimestamp($endtimestamp);
            if($status == 2){
                // Cancelled Appointment
                $response = array("isAppointment"=>5, "textColor"=>"#D50000","text"=>"Cancelled","showOtp"=>"1","showMsgIcon"=>"1","showLocationIcon"=>$showLocationIcon);
            }elseif($status == 3){
                // Completed Appointment
                $response = array("isAppointment"=>3, "textColor"=>"#00D507","text"=>"Completed","showOtp"=>"1","showMsgIcon"=>"1","showLocationIcon"=>$showLocationIcon);
            }elseif($startDateTime->format('U') <= $currentDateTime->format('U') && $currentDateTime->format('U') <= $endDateTime->format('U')){   
                // Ongoing Appointment
                //"On ".($doctorGender==1 ? "his" :($doctorGender==2 ? "her" : ($doctorGender==3 || $doctorGender==4  ? "the" : "the")))." way"
                $response = array("isAppointment"=>2, "textColor"=>"#00D507","text"=>"","showOtp"=>"1","showMsgIcon"=>"1","showLocationIcon"=>$showLocationIcon);
            }elseif($endDateTime->format('U') < $currentDateTime->format('U')){
                // Recent Appointment
                $response = array("isAppointment"=>4, "textColor"=>"#D50000","text"=>"Recent Appointment","showOtp"=>"1","showMsgIcon"=>"1","showLocationIcon"=>$showLocationIcon);
            }elseif($endDateTime->format('U') > $currentDateTime->format('U')){
                // Upcoming Appointment
                $response = array("isAppointment"=>1, "textColor"=>"#00D507","text"=>"Upcoming Appointment","showOtp"=>"1","showMsgIcon"=>"1","showLocationIcon"=>$showLocationIcon);
            }
        }
        return $response;
    }

    function getPlanDayAndDateName($timestamp = "",$userTimezone = ""){
        if(empty($timestamp)){
            return "";
        }
        $today_date = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
        $today_date->setTimezone(new DateTimeZone($userTimezone));
        
        $tomorrow_date = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
        $tomorrow_date->setTimezone(new DateTimeZone($userTimezone));
        $tomorrow_date->add(new DateInterval('P1D'));

        $match_date = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
        $match_date->setTimezone(new DateTimeZone($userTimezone));
        $match_date->setTimestamp($timestamp);
        $interval = $today_date->diff($match_date);
        
        $data = array();
        
        $data['fullDate'] = $match_date->format('M d, Y');
        $data['dateTime'] = $match_date->format('h:i A');
        $data['date'] = $match_date->format('Y-m-d');
        return $data;
            // return $match_date->format('D, d M');
        /*
        if($interval->days == 0) {
            return "Today";
        } elseif($interval->days == 1 && $interval->invert == 0) {
            return "Tomorrow, ".$match_date->format('d M');
        } else {
            return $match_date->format('D, d M');
        }*/
    }

    public function checkUserAuth($type = '2', $isredirect = true) {
        if (empty($this->session->userdata('role')) || empty($this->session->userdata('adminId'))) {
            return redirect(base_url('admin'));
        }

        if ($type == '1') {
            if (($this->session->userdata('role') == 1 || $this->session->userdata('role') == 2) && !empty($this->session->userdata('adminId'))) {
                return true;
            } else {
                if (!$isredirect) {
                    return false;
                } else {
                    $this->session->set_flashdata('error', 'Your session is expire');
                    redirect(base_url('admin'));
                }
            }
        } else {
            $this->session->set_flashdata('error', 'Your session is expire');
            redirect(base_url('admin'));
        }
    }

    public function checkAuth($type = '4', $isredirect = true) {
        if ($type == '2') { //User
            // echo '<pre>'; print_R($this->session->all_userdata()); die;
            if ($this->session->userdata('userRole') == 2  && !empty($this->session->userdata('userId'))) {
                // if ($this->session->userdata('userRole') == 3  && !empty($this->session->userdata('userId'))) {
                return true;
            } else {
                if (!$isredirect) {
                    return false;
                } else {
                    $this->session->set_flashdata('error', 'Your session is expire');
                    redirect(base_url('login'));
                }
            }
        } else if ($type == '3') { //Doctor
            if ($this->session->userdata('doctorRole') == 3  && !empty($this->session->userdata('doctorId'))) {
                return true;
            } else {
                if (!$isredirect) {
                    return false;
                } else {
                    $this->session->set_flashdata('error', 'Your session is expire');
                    redirect(base_url('login'));
                }
            }
        } else {
            $this->session->set_flashdata('error', 'Your session is expire');
            redirect(base_url('/'));
        }
    }

    public function convert_htmltofrontimg($data=''){
        // print_r($data);die();
        if (empty($data)) {
            return false;
        }
        $ch = curl_init();
                
        curl_setopt($ch, CURLOPT_URL, "https://hcti.io/v1/image");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_USERPWD, getenv('SOCIAL_IMAGE_API_USER_ID') . ":" . getenv('SOCIAL_IMAGE_API_API_KEY'));
        
        $headers = array('Content-Type: application/x-www-form-urlencoded');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close ($ch);
        return $result;
    }

    public function convert_htmltobackimg($data=''){
        // print_r($data);die();
        if (empty($data)) {
            return false;
        }
        $ch = curl_init();
                
        curl_setopt($ch, CURLOPT_URL, "https://hcti.io/v1/image");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_USERPWD, getenv('SOCIAL_IMAGE_API_USER_ID') . ":" . getenv('SOCIAL_IMAGE_API_API_KEY'));
        
        $headers = array('Content-Type: application/x-www-form-urlencoded');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close ($ch);
        return $result;
        // die();
    }

    public function base64ToImage($image){
        if(!empty($image)){
            $image_parts = explode(";base64,", $image);
            $pos  = strpos($image, ';');
            $image_type = $type = explode(':', substr($image, 0, $pos))[1];
            $image_type = explode('/', $image_type)[1];
            $image_base64 = base64_decode($image_parts[1]);
            $fileName = time().''.rand("0000","9999"). '.'.$image_type;
            $file = getenv('UPLOADPATH')."".$fileName;
            if(file_put_contents($file, $image_base64)){
                // print_r($fileName);die;
                return $fileName;
            }
        }
        return '';
    }
    public function getusersystemtimezone(){                
        $ip = $_SERVER['REMOTE_ADDR'];  //$_SERVER['REMOTE_ADDR']
        $ipInfo = file_get_contents('http://ip-api.com/json/' . $ip);
        $ipInfo = json_decode($ipInfo);
        if(isset($ipInfo->timezone)){
            return $ipInfo->timezone;
        }
    }
}
