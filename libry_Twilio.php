<?php
require_once FCPATH . "vendor/autoload.php";
use Twilio\Rest\Client;

class Twilio {

    private $_ci;
    

    public function __construct() {
        $this->_ci = & get_instance();
        $this->sid = "AC0d6fd7e447c00d6c3f40684989f2b88b";
        $this->token = "201eaefca47ee1940d4330fb8a874d33";
        $this->messagingServiceSid = 'MG096f9d3a5a68e02f4e6c2d9a635797fa';
        $this->client = new Client($this->sid, $this->token);
    }

    public function sendTextMessage($to,$message) {
        try{
            // Use the client to do fun stuff like send text messages!
            $message = $this->client->messages->create(
                // the number you'd like to send the message to
                $to,
                [
                    // A Twilio phone number you purchased at twilio.com/console
                    'messagingServiceSid' => $this->messagingServiceSid,
                    // the body of the text message you'd like to send
                    'body' => $message
                ]
            );
            return $message->sid;
            error_log("\n\n -------------------------------------" . date('c'). " \n" .$to. " \n" . $message->sid, 3, FCPATH.'worker/twilloSMS-'.date('d-m-Y').'.log');
        }catch(Exception $e){
            error_log("\n\n -------------------------------------" . date('c'). " \n" .$to. " \n" .  $e->getCode()." \n ".$e->getMessage(), 3, FCPATH.'worker/twilloSMS-'.date('d-m-Y').'.log');
            return $e->getCode() . ' : ' . $e->getMessage()."<br>";
        }
    }

}
