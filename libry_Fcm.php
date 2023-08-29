<?php

class Fcm {

    public $sender_id = "";
    public $api_key = "";
    public $data;
    public $device;
    public $notification;

    public function __construct() {
        $this->CI = & get_instance();
        $this->sender_id = getenv('FCMSENDERID');
        $this->api_key = getenv('FCMAPIKEY');
    }

    public function sendMessage($payload, $notification, $device) {
        $notification["vibrate"] = 1;
        $notification["sound"] = 1;
        $notification["largeIcon"] = 'large_icon';
        $notification["smallIcon"] = 'small_icon';
        
        $data = $notification;
        $data['payload']['messages'] = $payload;
        $data['is_background'] = FALSE;
        $data['timestamp'] = date('Y-m-d G:i:s');
        return $this->sendPushNotification(['to' => $device,'notification'=> $notification,'data' => $data]);
    }

    // Sending message to a topic by topic name
    public function sendToTopic($to, $message) {
        $fields = array(
            'to' => '/topics/' . $to,
            'data' => $message,
        );
        return $this->sendPushNotification($fields);
    }

    // sending push message to multiple users by firebase registration ids
    public function sendMultiple($registration_ids, $message) {
        $fields = array(
            'to' => $registration_ids,
            'data' => $message,
        );

        return $this->sendPushNotification($fields);
    }

    // function makes curl request to firebase servers
    private function sendPushNotification($fields) {
        $headers = array(
            'Authorization: key=' . $this->api_key,
            'Content-Type: application/json'
        );
        // Open connection
        $ch = curl_init();

        // Set the url, number of POST vars, POST data
        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
        //curl_setopt($ch, CURLOPT_URL, 'http://157.245.128.205/fcm_notification/index.php');

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Disabling SSL Certificate support temporarly
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));

        // Execute post
        $result = curl_exec($ch);
        if ($result === FALSE) {
            die('Curl failed: ' . curl_error($ch));
        }

        // Close connection
        curl_close($ch);

        return $result;
    }

}
