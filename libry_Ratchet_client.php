<?php

defined('BASEPATH') OR exit('No direct script access allowed');
require FCPATH . 'vendor/autoload.php';

// Namespaces
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;


class Ratchet_client {
    private $CI;
    public $host = null;
    public $port = null;
    public $auth = false;
    public $debug = false;
    public $callback = array();
    protected $config = array();
    protected $callback_type = array('auth', 'event', 'saveMessage');
    
    public function __construct(array $config = array()) {
        $this->CI = & get_instance();
        $this->CI->load->helper('ratchet_client');
        $this->CI->load->model('Chat_Model');
        $this->CI->load->model('Users_Model');
        $this->CI->load->model('Background_Model');
        $this->CI->load->model('Ticket_Model');
        $this->CI->load->model('User_Appointment_Model');
        $this->CI->load->model('User_Free_Consult_Model');
        
        $this->config = (!empty($config)) ? $config : array();

        $this->host = getenv('RC_HOST');
        $this->port = getenv('RC_PORT');
        $this->auth = getenv('RC_AUTH');
        $this->debug = getenv('RC_DEBUG');
    }

    public function run() {
         //Secure:
         if ( getenv('RC_KEY') != '' && getenv('RC_CERT') != '') {
            $app = new HttpServer(new WsServer(new Server()));
            $loop = \React\EventLoop\Factory::create();
            $secure_websockets = new \React\Socket\Server($this->host.':'.$this->port, $loop);
            $secure_websockets = new \React\Socket\SecureServer($secure_websockets, $loop, [
                'local_cert' => getenv('RC_CERT'),
                'local_pk' => getenv('RC_KEY'),
                'verify_peer' => false,
                'allow_self_signed' => true,
            ]);
            $server = new \Ratchet\Server\IoServer($app, $secure_websockets, $loop);
        } else {
            $server = IoServer::factory( new HttpServer( new WsServer( new Server() ) ), $this->port, $this->host );
        }
        $server->run();
    }

    public function set_callback($type = null, array $callback = array()) {
        if (!empty($type) && in_array($type, $this->callback_type)) {
            if (is_callable($callback)) {
                $this->callback[$type] = $callback;
            } else {
                output('fatal', 'Method ' . $callback[1] . ' is not defined');
            }
        }
    }
}

class Server implements MessageComponentInterface {

    protected $clients;
    protected $subscribers = array();
    protected $myUsers = array();

    public function __construct() {
        $this->CI = & get_instance();
        $this->clients = new SplObjectStorage;
        if ($this->CI->ratchet_client->auth && empty($this->CI->ratchet_client->callback['auth'])) {
            output('fatal', 'Authentication callback is required, you must set it before run server, aborting..');
        }

        if ($this->CI->ratchet_client->debug) {
            output('success', 'Running server on host ' . $this->CI->ratchet_client->host . ':' . $this->CI->ratchet_client->port);
        }

        if (!empty($this->CI->ratchet_client->callback['auth']) && $this->CI->ratchet_client->debug) {
            output('success', 'Authentication activated');
        }
    }

    public function onOpen(ConnectionInterface $connection) {
        $this->clients->attach($connection);
        if ($this->CI->ratchet_client->debug) {
            output('info', 'New client connected as (' . $connection->resourceId . ')');
        }
    }

    public function onMessage(ConnectionInterface $client, $message) {
        var_dump($message);
        var_dump(valid_json($message));
        if (valid_json($message)) {
            $data = json_decode($message);
            if(!empty($data) && isset($data->nameValuePairs)){
                $data = $data->nameValuePairs;
            }
        }
        
        if(!isset($data) || empty($data) || !isset($data->hookMethod) || empty($data->hookMethod)){
            return false;
        }
        
        if($data->hookMethod != "registration" && (!isset($client->subscriber_id) || empty($client->subscriber_id))){
            if ($this->CI->ratchet_client->debug) {
                output('info', 'Client (' . $client->resourceId .") is not authenticated.");
            }
            return false;
        }
        
        $broadcast = (!empty($data->broadcast) and $data->broadcast == true) ? true : false;
        $clients = count($this->clients) - 1;

        switch ($data->hookMethod) {
            
            case 'registration':
                $this->CI->db->reconnect();
                //if (!empty($this->CI->ratchet_client->callback['auth']) && empty($client->subscriber_id)) {
                $auth = call_user_func_array($this->CI->ratchet_client->callback['auth'], array($data));
                if (empty($auth)) {
                    output('error', 'Client (' . $client->resourceId . " : " . $data->token . ') authentication failure');
                    $client->close(1006);
                    return false;
                }

                $client->subscriber_id = $auth->id;
                $client->timeZone = $auth->timeZone;
                $client->subscriber_auth_id = $auth->auth_id;
                $client->userData = $auth;
                $this->myUsers[$auth->id][$auth->auth_id] = $client;
                if ($this->CI->ratchet_client->debug) {
                    output('success', 'Client (' . $client->resourceId ." : ". $client->subscriber_id . ') authentication success');
                }
                break;

            case 'message':
                $this->setMessage($client, $data);
                break;
                
            case 'readmessage':
                $this->readMessage($client, $data);
                break;
            
            case 'chatinbox' :
                $this->chatInbox($client, $data);
                break;
            
            case 'chatmessagelist' :
                $this->chatMessageList($client, $data);
                break;
            
            case 'usermessagelist' :
                $this->userChatMessageList($client, $data);
                break;
            
            case 'removeuserchatmessages' :
                $this->removeuserchatmessages($client, $data);
                break;
            
            case 'removechatmessagelist' :
                $this->removeChatMessageList($client, $data);
                break;
            
            case 'userSupportTicketReply' :
                $this->userSupportTicketReplyss($client, $data);
                break;

            case 'userSupportTicketMessageList' :
                $this->userSupportTicketMessageList($client, $data);
                break;

            case 'userlist' :
                $list = [];
                foreach ($this->users as $resourceId => $value) {
                    $list[$resourceId] = $value['user'];
                }
                $new_package = [
                    'users' => $list,
                    'type' => 'userlist'
                ];
                $new_package = json_encode($new_package);
                $client->send($new_package);
                break;
            case 'userVideoCall' :
                $this->userVideoCall($client, $data);
                break;
            case 'videocallReviceCut' :
                $this->videocallReviceCut($client, $data);
                break;
            case 'checkConnection' :
                $this->checkConnection($client, $data);
                break;
        }
    }

    public function onClose(ConnectionInterface $connection) {
        if ($this->CI->ratchet_client->debug) {
            output('info', 'Client (' . $connection->resourceId ." : ". (isset($connection->subscriber_id) && !empty($connection->subscriber_id) ? $connection->subscriber_id : '') . ') disconnected');
        }
        if(isset($connection->subscriber_id) && !empty($connection->subscriber_id)){
            unset($this->myUsers[$connection->subscriber_id]);
        }
        $this->clients->detach($connection);
    }

    public function onError(ConnectionInterface $connection, \Exception $e) {
       
        if ($this->CI->ratchet_client->debug) {
            output('fatal', 'An error has occurred: ' . $e->getMessage());
        }
        if(isset($connection->subscriber_id) && !empty($connection->subscriber_id)){
            unset($this->myUsers[$connection->subscriber_id]);
        }
        $connection->close();
    }

    
    protected function setMessage($client, $data) {
        if (isset($client->subscriber_id) && !empty($client->subscriber_id) && isset($data->message) && isset($data->recipient_id) && $data->message != "" && !empty($data->recipient_id) && isset($data->type)) {
            if($data->type == "1" && isset($data->isWeb) && $data->isWeb == "1"){
                //$data->message = str_replace(['\"','\/'],['"','/'],trim(json_encode($data->message),'"'));
                $data->message = str_replace('\"','"',trim(json_encode($data->message,JSON_UNESCAPED_SLASHES),'"'));
            }
            $chat = $this->CI->Chat_Model->setData(["message" => $data->message, "type" => $data->type, "groupId" => $data->recipient_id, "freeConsultStatus" => ($data->type == 5 ? 1 : ""), "sender" => $client->subscriber_id, "status" => 1]);
            if ($chat) {
                $members = $this->CI->Chat_Model->getGroupMembers(["groupId" => $data->recipient_id, "status" => [1,2]]);
                $myMessage = $this->CI->Chat_Model->get(['id' => $chat], true);
                if(empty($myMessage)){
                    return false;
                }
                $myUserTimeZone = (!empty($client->timeZone) ? $client->timeZone : getenv('SYSTEMTIMEZON'));
                $msgDateTime = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
                $msgDateTime->setTimezone(new DateTimeZone($myUserTimeZone));
                $msgDateTime->setTimestamp($myMessage->createdDate);
                //$myMessage->time = $msgDateTime->format('d-M-Y h:i A');
                $myMessage->time = $msgDateTime->format('m-d-Y h:i A');
                $myMessage->onlytime = $msgDateTime->format('h:i A');

                $myMessage->readStatus = 1;

                $myMessage->referProviderData = "";
                $myMessage->freeConsultDoctorData = "";
                $myMessage->freeConsultUserData = "";
                $myUserTimeZone = (!empty($client->timeZone) ? $client->timeZone : getenv('SYSTEMTIMEZON'));
                if($data->type == "4" || $data->type == 4) {
                    $referProviderData = $this->CI->Users_Model->get(['id'=>$data->message,'status'=>1,'role'=>3,'apiResponse'=>true,'getFutureFirstAvailability'=>true,'getProfessionData'=>true,'getProfessionalData'=>true,'getRatingAverageData'=>true],true);
                    if(!empty($referProviderData)){
                        $referProviderData->nextAvailable = $this->CI->Common_Model->checkDateText($referProviderData->nextAvailable,$myUserTimeZone);
                        $myMessage->referProviderData = $referProviderData;
                    }
                }elseif($data->type == 5){
                    $this->CI->User_Free_Consult_Model->setData(['userId'=>$client->subscriber_id,'doctorId'=>$data->message]);
                    $scheduled = "";
                    if($myMessage->freeConsultStatus == 1){
                        $scheduled = "Pending";
                    }elseif($myMessage->freeConsultStatus == 2){
                        $appointmentData = $this->CI->User_Appointment_Model->get(['id'=>$myMessage->appointmentId,'getAvailabilityData'=>true,'status'=>[1,2,3]],true);
                        if(!empty($appointmentData)){
                            $scheduled = $this->CI->Common_Model->checkDateText($appointmentData->appointmentDateTime,$myUserTimeZone);
                        }
                    }elseif($myMessage->freeConsultStatus == 3){
                        $scheduled = "Rejected";
                    }
                    
                    $freeConsultDoctorData = $this->CI->Users_Model->get(['id'=>$data->message,'status'=>1,'role'=>3,'apiResponse'=>true,'getProfessionData'=>true,'getProfessionalData'=>true,'getRatingAverageData'=>true],true);
                    if(!empty($freeConsultDoctorData)){
                        $freeConsultDoctorData->scheduled = $scheduled;
                        $myMessage->freeConsultDoctorData = $freeConsultDoctorData;
                    }
                    $freeConsultUserData = $this->CI->Users_Model->get(['id'=>$myMessage->sender,'status'=>1,'role'=>2,'apiResponse'=>true],true);
                    if(!empty($freeConsultUserData)){
                        $freeConsultUserData->scheduled = $scheduled;
                        $myMessage->freeConsultUserData = $freeConsultUserData;
                    }
                }

                $response = array("hookMethod" => "message", "data" => $myMessage);
                if (!empty($members)) {
                    $offLineMemebers = [];
                    foreach ($members as $value) {
                        $status = 1;
                        if($value->userId == $client->subscriber_id){
                            $status = 3;
                        }
                        $this->CI->Chat_Model->setMessageStatus(["groupId"=>$data->recipient_id,"messageId" => $chat, "userId" => $value->userId, "status" => $status]);
                        
                        if (isset($this->myUsers[$value->userId]) && !empty($this->myUsers[$value->userId])) {
                            foreach($this->myUsers[$value->userId] as $sub){
                                $this->send_message($sub, $response, $client);
                            }
                        }
                        if($value->userId != $client->subscriber_id){
                            $offLineMemebers[] = $value->userId;
                        }
                        
                        /*if (isset($this->myUsers[$value->userId])) {
                            $this->send_message($this->myUsers[$value->userId], $response, $client);
                        }else{
                            $offLineMemebers[] = $value->userId;
                        }*/
                    }
                    $this->CI->Common_Model->backroundCall('sendChatNotification', [
                        'users' => $offLineMemebers, 'message' => $data,
                        // 'model_id' => $chat, 
                        'send_from' => $client->subscriber_id,
                    ]);
                    
                    unset($offLineMemebers);
                }
            }
        }
    }

    protected function readMessage($client, $data) {
        if (isset($client->subscriber_id) && !empty($client->subscriber_id) && isset($data->messageId) && !empty($data->messageId) ) {
            $messageState = $this->CI->Chat_Model->getMessageStatus(["messageId" => $data->messageId, "userId" => $client->subscriber_id], true);
            if(!empty($messageState)){
                $this->CI->Chat_Model->setMessageStatus(["status" => 3], $messageState->id);
            }
        }
    }

    protected function chatInbox($client, $data) {
        $search = (isset($data->search) ? $data->search : "");
        $timeZone = isset($client->userData->timeZone) && !empty($client->userData->timeZone) ? $client->userData->timeZone : getenv('SYSTEMTIMEZON');
        $query = array('status' => 1, "userId" => $client->subscriber_id,"search"=>$search,"withFormatedDate" => $timeZone);
        if (isset($data->nameLike) && !empty($data->nameLike)) {
            $query['nameLike'] = $data->nameLike;
        }
        if (isset($data->type) && !empty($data->type)) {
            $query['inboxType'] = $data->type;
        }
        $result = $this->CI->Chat_Model->chatInbox($query);
        $response = array("hookMethod" => "chatinbox", "data" => $result);
        $this->send_message($client, $response, $client);
    }
    
    protected function chatMessageList($client, $data) {
        if(!isset($data->id) || empty($data->id)){
            return false;
        }
        $query = array('status' => 1, "groupId" => $data->id, "orderby" => "tbl_chat_message.createdDate", "orderstate" => "DESC");
        $messageCount = $this->CI->Chat_Model->get($query, false, true);
        
        $query['limit'] = (isset($data->limit) && !empty($data->limit) != '') ? $data->limit : 20;
        $query['offset'] = (isset($data->page) && !empty($data->page) ? (($data->page - 1) * $query['limit']) : 0);

        $result = $this->CI->Chat_Model->get($query);
        if(!empty($result)){
            $myUserTimeZone = (!empty($client->timeZone) ? $client->timeZone : getenv('SYSTEMTIMEZON'));
            foreach($result as $value){
                $msgDateTime = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
                $msgDateTime->setTimezone(new DateTimeZone($myUserTimeZone));
                $msgDateTime->setTimestamp($value->createdDate);
                //$value->time = $msgDateTime->format('d-M-Y h:i A');
                $value->time = $msgDateTime->format('m-d-Y h:i A');
                $value->referProviderData = "";
                $value->freeConsultDoctorData = "";
                $value->freeConsultUserData = "";
                if($value->type == 4){
                    $referProviderData = $this->CI->Users_Model->get(['id'=>$value->message,'status'=>1,'role'=>3,'apiResponse'=>true,'getFutureFirstAvailability'=>true,'getProfessionData'=>true,'getProfessionalData'=>true,'getRatingAverageData'=>true],true);
                    if(!empty($referProviderData)){
                        $referProviderData->nextAvailable = $this->CI->Common_Model->checkDateText($referProviderData->nextAvailable,$myUserTimeZone);
                        $value->referProviderData = $referProviderData;
                    }
                }elseif($value->type == 5){
                    $scheduled = "";
                    if($value->freeConsultStatus == 1){
                        $scheduled = "Pending";
                    }elseif($value->freeConsultStatus == 2){
                        $appointmentData = $this->CI->User_Appointment_Model->get(['id'=>$value->appointmentId,'getAvailabilityData'=>true,'status'=>[1,2,3]],true);
                        if(!empty($appointmentData)){
                            $scheduled = $this->CI->Common_Model->checkDateText($appointmentData->appointmentDateTime,$myUserTimeZone);
                        }
                    }elseif($value->freeConsultStatus == 3){
                        $scheduled = "Rejected";
                    }

                    $freeConsultDoctorData = $this->CI->Users_Model->get(['id'=>$value->message,'status'=>1,'role'=>3,'apiResponse'=>true,'getProfessionData'=>true,'getProfessionalData'=>true,'getRatingAverageData'=>true],true);
                    if(!empty($freeConsultDoctorData)){
                        $freeConsultDoctorData->scheduled = $scheduled;
                        $value->freeConsultDoctorData = $freeConsultDoctorData;
                    }
                    $freeConsultUserData = $this->CI->Users_Model->get(['id'=>$value->sender,'status'=>1,'role'=>2,'apiResponse'=>true],true);
                    if(!empty($freeConsultUserData)){
                        $freeConsultUserData->scheduled = $scheduled;
                        $value->freeConsultUserData = $freeConsultUserData;
                    }
                }
            }
        }
        $result = array_reverse($result);
        $group = $this->CI->Chat_Model->chatInbox(['id' => $data->id, "userId" => $client->subscriber_id], true);
        $response = array("hookMethod" => "chatmessagelist", "data" => $result, "group" => $group, 'total_page' => ceil($messageCount / $query['limit']));
        $this->send_message($client, $response, $client);
        if(!empty($group) && !empty($result)){
            $this->CI->Chat_Model->setMessageRead(["groupId" => $group->id, "userId" => $client->subscriber_id, "messageId" => $result[0]->id]);
        }
    }
    
    protected function userChatMessageList($client, $data) {
        if(!isset($data->id) || empty($data->id)){
            return false;
        }
        $userExist = $this->CI->Users_Model->get(['id' => $data->id, 'status' => 1], true);
        if(empty($userExist)){
            return false;
        }
        
        $group = $this->CI->Chat_Model->checkCreateGroupFromUser(["users" => [$client->subscriber_id, $userExist->id], "setActive" => 1]);
        if(!$group){
            return false;
        }
        $data->id = $group;
        $this->chatMessageList($client, $data);
    }
    
    protected function removeuserchatmessages($client, $data) {
        if(!isset($data->id) || empty($data->id)){
            return false;
        }
        $this->CI->Chat_Model->removeUserChatMessagesData($data->id);
        $response = array("hookMethod" => "removeuserchatmessages", "data" => $data->id,'status'=>1);
        $this->send_message($client, $response, $client);
    }

    protected function removechatmessagelist($client, $data) {
        if(!isset($data->id) || empty($data->id)){
            return false;
        }
        $this->CI->Chat_Model->removeChatData($data->id);
        $response = array("hookMethod" => "removechatmessagelist", "data" => $data->id,'status'=>1);
        $this->send_message($client, $response, $client);
    }

    protected function userSupportTicketReplyss($client, $data) {
        if(!isset($data->ticket_id) || empty($data->ticket_id)){
            return false;
        }

        $ticketExist = $this->CI->Ticket_Model->get(['id'=>$data->ticket_id,'status'=>[0,1]], true);
        if(empty($ticketExist)){
            return false;
        }

        $userExist = $this->CI->Users_Model->get(['id' => $client->subscriber_id, 'status' => 1], true);
        if(empty($userExist)){
            return false;
        }

        $adminExist = $this->CI->Users_Model->get(['role' => 1, 'status' => 1], true);
        if(empty($adminExist)){
            return false;
        }

        if($userExist->role == 1){
            $forReply = 1;
        }else{
            $forReply = 2;
        }
        
        $id = $this->CI->Ticket_Model->setTicketReplyData(['ticketId'=>$data->ticket_id,'description'=>$data->description,'replyType'=>$data->replyType,'forReply'=>$forReply]);
        if(empty($id)){
            return false;
        }
        if($userExist->role == 1) {
            //$this->CI->Background_Model->adminTicketReplay($id);
        }
        #$result = $this->CI->Ticket_Model->getTicketReply(["id"=>$id,"formatedData"=>(!empty($userExist->timeZone) ? $userExist->timeZone : getenv('SYSTEMTIMEZON')),"status"=>1],true);
        #$response = array("hookMethod" => "userSupportTicketReply", "data" => $result);
        $users = [$ticketExist->userId,$adminExist->id];
        if(isset($users) && !empty($users)){
            foreach($users as $user){
                $udata = $this->CI->Users_Model->get(['id' => $user ], true);
                if(empty($udata)){
                    return false;
                }
                $result = $this->CI->Ticket_Model->getTicketReply(["id"=>$id,"formatedData"=>(!empty($udata->timeZone) ? $udata->timeZone : getenv('SYSTEMTIMEZON')),"status"=>1],true);
                $response = array("hookMethod" => "userSupportTicketReply", "data" => $result);
        
                if (isset($this->myUsers[$user]) && !empty($this->myUsers[$user])) {
                    foreach($this->myUsers[$user] as $sub){
                        $this->send_message($sub, $response, $client);
                    }
                }
            }
        }
    }

    protected function userSupportTicketMessageList($client, $data) {
        if(!isset($data->ticket_id) || empty($data->ticket_id)){
            return false;
        }

        $ticketExist = $this->CI->Ticket_Model->get(['id'=>$data->ticket_id,'status'=>[0,1]], true);
        if(empty($ticketExist)){
            return false;
        }
        
        $userExist = $this->CI->Users_Model->get(['id' => $client->subscriber_id, 'status' => 1], true);
        if(empty($userExist)){
            return false;
        }

        $adminData = $this->CI->Users_Model->get(['role' => 1, 'status' => 1], true);
        if(empty($adminData)){
            return false;
        }
        $adminResponse = array();
        $adminResponse['id'] = $adminData->id;
        $adminResponse['name'] = $adminData->name;
        $adminResponse['email'] = $adminData->email;
        $adminResponse['profileimage'] = $adminData->profileimage;
        $adminResponse['thumbprofileimage'] = $adminData->thumbprofileimage;
        $result = $this->CI->Ticket_Model->getTicketReply(["ticketId" => $data->ticket_id,"formatedData"=>(!empty($userExist->timeZone) ? $userExist->timeZone : getenv('SYSTEMTIMEZON')), "status" => 1,'orderby'=>'tbl_ticketreply.createdDate','orderstate'=>'ASC']);
        
        $response = array("hookMethod" => "userSupportTicketMessageList", "data" => $result,"adminData"=>$adminResponse);
        $this->send_message($client, $response, $client);
    }

    protected function send_message($user, $message, $client) {
        $message = json_decode(json_encode($message, true), true);
        array_walk_recursive($message, function(&$item) {
            $item = strval($item);
        });
        
        $message = json_encode($message, true);
        $user->send($message);
        /*
        if (!empty($this->CI->ratchet_client->callback['event'])) {
            call_user_func_array($this->CI->ratchet_client->callback['event'], array((valid_json($message) ? json_decode($message) : $message)));
            if ($this->CI->ratchet_client->debug) {
                output('info', 'Callback event "' . $this->CI->ratchet_client->callback['event'][1] . '" called');
            }
        }
        */
        if ($this->CI->ratchet_client->debug) {
            output('info', 'Client (' . $client->resourceId . ') send \'' . $message . '\' to (' . $user->resourceId . ')');
        }
    }

    protected function userVideoCall($client, $data) {
        $result = $this->CI->User_Appointment_Model->get(['id' => $data->appointmentId, 'userId' => $data->userId, 'doctorId' => $data->doctorId, 'status' => [0,1,2,3]], TRUE);
        if(!empty($result)){
            $userExit = $this->CI->Users_Model->get(['id' => $data->userId, 'status' => '1'], TRUE);
            $doctor_result = $this->CI->Users_Model->get(['id' => $data->doctorId, 'status' => '1'], TRUE);
            
            $response = array("hookMethod" => "userVideoCall", "data" => $result,"doctorData"=>$doctor_result);
            $users = [$result->userId, $userExit->id];
            if(isset($users) && !empty($users)){
                foreach($users as $user){
                    if (isset($this->myUsers[$user]) && !empty($this->myUsers[$user])) {
                        foreach($this->myUsers[$user] as $sub){
                            $this->send_message($sub, $response, $client);
                        }
                    }
                }
            }
        }
    }

    protected function videocallReviceCut($client, $data) {
        $result = $this->CI->User_Appointment_Model->get(['id' => $data->appointmentId, 'userId' => $data->userId, 'doctorId' => $data->doctorId, 'status' => [0,1,2,3]], TRUE);

        $userExit = $this->CI->Users_Model->get(['id' => $data->userId, 'status' => '1'], TRUE);
        $response = array("hookMethod" => "videocallReviceCut", "data" => $result);
        $users = [$result->userId, $userExit->id];
        if(isset($users) && !empty($users)){
            foreach($users as $user){
                if (isset($this->myUsers[$user]) && !empty($this->myUsers[$user])) {
                    foreach($this->myUsers[$user] as $sub){
                        $this->send_message($sub, $response, $client);
                    }
                }
            }
        }
    }

    protected function checkConnection($client, $data) {
        $response = array("hookMethod" => "checkConnection", "data" => "Activated");
        $this->send_message($client, $response, $client);
    }
}
