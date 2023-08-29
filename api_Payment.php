<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'libraries/REST_Controller.php';

class Payment extends REST_Controller {

    function __construct() {
        parent::__construct();
        $this->load->library('upload');
        $this->load->model('Common_Model','Common');
        $this->load->model('Background_Model');
        $this->load->model('Users_Model', 'User');            
        $this->load->model('StripeConnect_Model');
        $this->load->model('User_Transaction_Model','User_Transaction');
        $this->load->model('User_Wallet_Model');
        $this->load->model('User_Bank_Model');
        // type = 1 for chiry provider account
        $this->load->library('stripe',array('type'=>'1'));
    }    
    public function connectStripe_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        $getData = $this->StripeConnect_Model->get(['userId' => $user->id],TRUE);
        if(!empty($getData)){
            $connectedAccountId = $getData->accId;
        }else{
            $connectAccount = $this->stripe->createStripeConnect(['email' => $user->email]);
            if(isset($connectAccount->id) && !empty($connectAccount->id)){
                $set = $this->StripeConnect_Model->setData(['userId' => $user->id, 'accId' => $connectAccount->id]);
                $connectedAccountId = $connectAccount->id;
            }
        }
        if(!empty($connectedAccountId)){
            $loginLink = $this->stripe->createStripeConnectOnBoard(['accId' => $connectedAccountId]);
            if (isset($loginLink->url) && !empty($loginLink->url)) {
                $this->apiResponse['status'] = "1";
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("listsuccess", $apiData['data']['langType']);
                $this->apiResponse['accId'] = $connectedAccountId;
                $this->apiResponse['url'] = $loginLink->url;
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }else if(isset($loginLink['error']['message'])
            && !empty($loginLink['error']['message'])){
                $this->apiResponse['status'] = "0";
                $this->apiResponse['message'] = $loginLink['error']['message'];
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
        }
        $this->apiResponse['status'] = "0";
        $this->apiResponse['message'] = $this->Common_Model->GetNotification("failtoconnect", $apiData['data']['langType']);
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function saveBankDetailInStripe_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if(!isset($apiData['data']['account_holder_name']) || empty($apiData['data']['account_holder_name'])){
            $this->apiResponse['message'] = $this->Common->GetNotification("account_holder_name_required", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if(!isset($apiData['data']['account_holder_type']) || empty($apiData['data']['account_holder_type'])){
            $this->apiResponse['message'] = $this->Common->GetNotification("account_holder_type_required", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if(!isset($apiData['data']['routing_number']) || empty($apiData['data']['routing_number'])){
            $this->apiResponse['message'] = $this->Common->GetNotification("routing_number_required", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if(!isset($apiData['data']['account_number']) || empty($apiData['data']['account_number'])){
            $this->apiResponse['message'] = $this->Common->GetNotification("account_number_required", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $bankdatas = array();
        $bankdatas['account_holder_name'] = $apiData['data']['account_holder_name'];
        $bankdatas['account_holder_type'] = $apiData['data']['account_holder_type'];
        $bankdatas['routing_number'] = $apiData['data']['routing_number'];
        $bankdatas['account_number'] = $apiData['data']['account_number'];
        $bankdatas['country'] = "US";

        $bankToken = $this->stripe->createBankToken($bankdatas);
        if(isset($bankToken['error']) && !empty($bankToken['error'])){
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $bankToken['error']['message'];
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $connectAccount = $this->StripeConnect_Model->get(['userId' => $user->id],TRUE);
        if(!isset($connectAccount->accId) || empty($connectAccount->accId)){
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common->GetNotification("connectAccountDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        /*if(!isset($connectAccount->status) || $connectAccount->status != 1){
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common->GetNotification("yourConnectAccountPendingOrRejectedFromTheStripe", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }*/

        $bankData = $this->stripe->createBankAccountOfConnect($connectAccount->accId,$bankToken->id,true);
        if(isset($bankData['error']) && !empty($bankData['error'])){
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $bankData['error']['message'];
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if(isset($bankData['id']) && !empty($bankData['id'])){
            $bankdatas['userId'] = $user->id;
            $bankdatas['stripeBankId'] = $bankData['id'];
            $bankdatas['bankTokenJson'] = json_encode($bankData);
            $this->User_Bank_Model->setData(["userIds"=>$user->id,"status"=>0]);
            $this->User_Bank_Model->setData($bankdatas);
           
            $this->StripeConnect_Model->setData(['isBankDetail' => 1],$connectAccount->id);
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("bankAddedSuccessfully", $apiData['data']['langType']);
        }else{
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common->GetNotification("failToAddBank", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getBankDetail_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        $connectAccount = $this->StripeConnect_Model->get(['userId' => $user->id],TRUE);
        if(!isset($connectAccount->accId) || empty($connectAccount->accId)){
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common->GetNotification("connectAccountDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $bankAccount = $this->User_Bank_Model->get(['userId' => $user->id,"status"=>1],TRUE);
        if(empty($bankAccount)){
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common->GetNotification("bankAccountDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $bankData = $this->stripe->retriveBankAccountOfConnect($connectAccount->accId,$bankAccount->stripeBankId);
        if(isset($bankData['error']) && !empty($bankData['error'])){
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $bankData['error']['message'];
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        
        if(isset($bankData['id']) && !empty($bankData['id']) && isset($bankData['default_for_currency']) && $bankData['default_for_currency'] == "1"){
            $response = array();
            $response['id'] = $bankData['id'];
            $response['account_holder_name'] = $bankData['account_holder_name'];
            $response['account_holder_type'] = $bankData['account_holder_type'];
            $response['routing_number'] = $bankData['routing_number'];
            $response['country'] = $bankData['country'];
            $response['currency'] = $bankData['currency'];
            $response['account_number'] = $bankAccount->account_number;
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common->GetNotification("getBankAccountDataSuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
        }else{
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common->GetNotification("bankAccountDataNotFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function withdrawHistory_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        $getData = $this->StripeConnect_Model->get(['userId' => $user->id],TRUE);
        $totalIncome = $this->User_Transaction->get(['status'=>1,'userId'=>$user->id,'sumAmount'=>true],true);            
        $page_number = (isset($apiData['data']['page']) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : '';
        $limit = (isset($apiData['data']['limit']) && $apiData['data']['limit'] != '') ? $apiData['data']['limit'] : 10;
        if (isset($apiData['data']['page']) && $apiData['data']['page'] == 1) {
            $offset = 0;
        } else {
            if (isset($apiData['data']['page']) && $apiData['data']['page'] != '1') {
                $offset = ($page_number * $limit) - $limit;
            } else {
                $offset = 0;
            }
        }
        $data = array();
        $data['status'] = [1];
        $data['userId'] = $user->id;
        $data['type'] = 2;
        $data['tranType'] =1;
        $data['payType'] = [6,7];
        $totalData = $this->User_Transaction->get($data,false,true);
        $data['apiResponse'] = true;
        $data['getFormattedAmount'] = true;
        $data['userTranDateFormate'] = true;
        $data['limit'] = $limit;
        $data['offset'] = $offset;
        $data['doctorhistory'] = true;   
        $response = $this->User_Transaction->get($data);
        $data = array();
        $data['status'] = [1];
        $data['userId'] = $user->id;
        $data['type'] = 2;
        $data['tranType'] = 1;
        $data['payType'] = [6,7];
        $data['sumdoctorhistory'] = true;   
             
        $totalwithdrow = $this->User_Transaction->get($data,true);
        $this->apiResponse['status'] = "1";
        $this->apiResponse['totalPages'] = ceil($totalData / $limit) . "";
        $this->apiResponse['data'] = $response;
        $this->apiResponse['stripe_connect_status'] = isset($getData->status) && $getData->status == "1" ? "1" : "0";
        $this->apiResponse['totalIncome'] =  (isset($totalIncome->totalAmount) ? $totalIncome->totalAmount : "0.00");
        $this->apiResponse['noofwithdraw'] =$totalData;
        $this->apiResponse['totalwithdrawIncome'] =(isset($totalwithdrow->totalAmount) ? $totalwithdrow->totalAmount : "0.00");
        $this->apiResponse['message'] = $this->Common_Model->GetNotification("getTransactionListSuccess", $apiData['data']['langType']);                             
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        
    }

    public function getWalletData_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        if ($user->role != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        
        $response = array();
        $response['processingAmount'] = "0.00";
        $response['instantAmount'] = "0.00";
        $response['withdrawableAmount'] = "0.00";
        $processingAmount = $this->User_Wallet_Model->get(['userId'=>$user->id,'status'=>1,'getProcessingAmount'=>true],true);

        if(isset($processingAmount->processingAmount) && !empty($processingAmount->processingAmount)){
            $response['processingAmount'] = number_format($processingAmount->processingAmount,2);
        }
        
        $instantAmount = $this->User_Wallet_Model->get(['userId'=>$user->id,'status'=>1,'getInstantAmount'=>true],true);
        if(isset($instantAmount->instantAmount) && !empty($instantAmount->instantAmount)){
            $response['instantAmount'] = number_format($instantAmount->instantAmount,2);
        }
        
        $withdrawableAmount = $this->User_Wallet_Model->get(['userId'=>$user->id,'status'=>1,'getWithdrawableAmount'=>true],true);
        if(isset($withdrawableAmount->withdrawableAmount) && !empty($withdrawableAmount->withdrawableAmount)){
            $response['withdrawableAmount'] = number_format($withdrawableAmount->withdrawableAmount,2);
        }

        $this->apiResponse['status'] = "1";
        $this->apiResponse['message'] = $this->Common->GetNotification("getWalletDataSuccess", $apiData['data']['langType']);
        $this->apiResponse['data'] = $response;
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function withdraw_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        if(!isset($apiData['data']['amount'])
        || empty($apiData['data']['amount'])){
            $this->apiResponse['message'] = $this->Common->GetNotification("withdrawamount", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        
        $withdrawableAmount = $this->User_Wallet_Model->get(['userId'=>$user->id,'status'=>1,'getWithdrawableAmount'=>true],true);
        if(isset($withdrawableAmount->withdrawableAmount) && !empty($withdrawableAmount->withdrawableAmount)){
            if(round($withdrawableAmount->withdrawableAmount,2) < $apiData['data']['amount']){
                $this->apiResponse['status'] = "0";
                $this->apiResponse['message'] = $this->Common->GetNotification("insufficientBalanceInYourWallet", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
        }else{
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common->GetNotification("insufficientBalanceInYourWallet", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        
        $stripeconnect = $this->StripeConnect_Model->get(['userId' => $user->id],TRUE);

        if(empty($stripeconnect)){
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common->GetNotification("connectAccountDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if($stripeconnect->isPayment != 1 || $stripeconnect->isPayout != 1){
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common->GetNotification("yourConnectAccountPendingOrRejectedFromTheStripe", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        $withdrawableData = $this->User_Wallet_Model->get(['userId'=>$user->id,'status'=>1,'getWithdrawableData'=>true,'orderby'=>'id','orderstate'=>'ASC']);
        if($stripeconnect->status == "1" && !empty($withdrawableData)){           
            $connectTransfer = $this->stripe->stripeConnectTransfer($apiData['data']['amount'],$stripeconnect->accId);
            if(isset($connectTransfer['error']) && !empty($connectTransfer['error'])){
                $this->apiResponse['status'] = "0";
                $this->apiResponse['message'] = $connectTransfer['error']['message'];
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
            if(isset($connectTransfer['id']) && !empty($connectTransfer['id'])){
                $tranId = $this->User_Transaction->setData([
                    'userId'=>$user->id,                    
                    'type'=>'2',
                    'tranType'=>'1',
                    'amount'=> $apiData['data']['amount'],
                    'stripeTransactionId'=>$connectTransfer['id'],
                    'stripeTranJson'=>json_encode($connectTransfer),
                    'payType'=>'6'
                ]);
                $this->User->setData(['walletAmount'=>($user->walletAmount - $apiData['data']['amount'])],$user->id);
                
                $withdrawAmount = $apiData['data']['amount'];
                foreach($withdrawableData as $value){
                    if($value->availableAmount <= $withdrawAmount){
                        $this->User_Wallet_Model->setData(['availableAmount'=>0],$value->id);
                        $withdrawAmount = $withdrawAmount - $value->availableAmount;
                    }else{
                        $this->User_Wallet_Model->setData(['availableAmount'=>$value->availableAmount - $withdrawAmount],$value->id);
                        $withdrawAmount = 0;
                        break;
                    }
                }
                
                // Set notification withdraw request
                $notiData = [];
                $notiData['send_from'] = $user->id;
                $notiData['send_to'] = $user->id;
                $notiData['model_id'] = (int)$tranId;
                $notiData['amount'] = '$'.number_format($apiData['data']['amount'], 2);
                $this->Common_Model->backroundCall('withdrawWalletAmountRequest', $notiData);
                // ./ Set notification withdraw request

                $this->apiResponse['status'] = "1";
                $this->apiResponse['message'] = $this->Common->GetNotification("withdrawsuccess", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
        }
        $this->apiResponse['status'] = "0";
        $this->apiResponse['message'] = $this->Common->GetNotification("failtotransfer", $apiData['data']['langType']);
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function instantWithdraw_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        if(!isset($apiData['data']['amount'])
        || empty($apiData['data']['amount'])){
            $this->apiResponse['message'] = $this->Common->GetNotification("withdrawamount", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        
        $instantAmount = $this->User_Wallet_Model->get(['userId'=>$user->id,'status'=>1,'getInstantAmount'=>true],true);
        if(isset($instantAmount->instantAmount) && !empty($instantAmount->instantAmount)){
            if(round($instantAmount->instantAmount,2) < $apiData['data']['amount']){
                $this->apiResponse['status'] = "0";
                $this->apiResponse['message'] = $this->Common->GetNotification("insufficientBalanceInYourWallet", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
        }else{
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common->GetNotification("insufficientBalanceInYourWallet", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        
        $stripeconnect = $this->StripeConnect_Model->get(['userId' => $user->id],TRUE);

        if(empty($stripeconnect)){
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common->GetNotification("connectAccountDataNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if($stripeconnect->isPayment != 1 || $stripeconnect->isPayout != 1){
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common->GetNotification("yourConnectAccountPendingOrRejectedFromTheStripe", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        
        $withdrawableData = $this->User_Wallet_Model->get(['userId'=>$user->id,'status'=>1,'getInstantData'=>true,'orderby'=>'id','orderstate'=>'ASC']);
        if($stripeconnect->status == "1" && !empty($withdrawableData)){           
            $instantFees = number_format((($apiData['data']['amount'] * 1) / 100),2,".","");
            $connectTransfer = $this->stripe->stripeConnectTransfer(($apiData['data']['amount'] - $instantFees),$stripeconnect->accId);
            if(isset($connectTransfer['error']) && !empty($connectTransfer['error'])){
                $this->apiResponse['status'] = "0";
                $this->apiResponse['message'] = $connectTransfer['error']['message'];
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
            if(isset($connectTransfer['id']) && !empty($connectTransfer['id'])){
                $tranId = $this->User_Transaction->setData([
                    'userId'=>$user->id,                    
                    'type'=>'2',
                    'tranType'=>'1',
                    'amount'=> $apiData['data']['amount'] - $instantFees,
                    'stripeTransactionId'=>$connectTransfer['id'],
                    'stripeTranJson'=>json_encode($connectTransfer),
                    'payType'=>'7'
                ]);
                
                $tranInstantId = $this->User_Transaction->setData([
                    'userId'=>$user->id,                    
                    'type'=>'2',
                    'tranType'=>'1',
                    'amount'=> $instantFees,
                    'payType'=>'8'
                ]);
                $this->User->setData(['walletAmount'=>($user->walletAmount - $apiData['data']['amount'])],$user->id);
                
                $withdrawAmount = $apiData['data']['amount'];
                foreach($withdrawableData as $value){
                    if($value->availableAmount <= $withdrawAmount){
                        $this->User_Wallet_Model->setData(['availableAmount'=>0],$value->id);
                        $withdrawAmount = $withdrawAmount - $value->availableAmount;
                    }else{
                        $this->User_Wallet_Model->setData(['availableAmount'=>$value->availableAmount - $withdrawAmount],$value->id);
                        $withdrawAmount = 0;
                        break;
                    }
                }
        
                // Set notification withdraw request
                $notiData = [];
                $notiData['send_from'] = $user->id;
                $notiData['send_to'] = $user->id;
                $notiData['model_id'] = (int)$tranId;
                $notiData['amount'] = '$'.number_format($apiData['data']['amount'] - $instantFees, 2);
                $this->Common_Model->backroundCall('withdrawWalletAmountRequest', $notiData);
                // ./ Set notification withdraw request

                // Set notification instant withdraw request fees
                $notiData = [];
                $notiData['send_from'] = $user->id;
                $notiData['send_to'] = $user->id;
                $notiData['model_id'] = (int)$tranInstantId;
                $notiData['amount'] = '$'.number_format($instantFees, 2);
                $this->Common_Model->backroundCall('withdrawWalletInstantAmountFees', $notiData);
                // ./ Set notification instant withdraw request fees

                $this->apiResponse['status'] = "1";
                $this->apiResponse['message'] = $this->Common->GetNotification("withdrawsuccess", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
        }
        $this->apiResponse['status'] = "0";
        $this->apiResponse['message'] = $this->Common->GetNotification("failtotransfer", $apiData['data']['langType']);
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function subscriptionTransaction_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        $page_number = (isset($apiData['data']['page']) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : '';
        $limit = (isset($apiData['data']['limit']) && $apiData['data']['limit'] != '') ? $apiData['data']['limit'] : 10;
        if (isset($apiData['data']['page']) && $apiData['data']['page'] == 1) {
            $offset = 0;
        } else {
            if (isset($apiData['data']['page']) && $apiData['data']['page'] != '1') {
                $offset = ($page_number * $limit) - $limit;
            } else {
                $offset = 0;
            }
        }
        
        $response = $this->User_Transaction->get(['userId'=>$user->id,'status'=>[0,1],'payType'=>3,'type'=>2,'apiResponse'=>true,'getFormattedAmount'=>true,'userTranDateFormate'=>true,'limit'=>$limit,'offset'=>$offset]);
        $totalData = $this->User_Transaction->get(['userId'=>$user->id,'status'=>[0,1],'payType'=>3,'type'=>2],false,true);
        if (!empty($response)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("getTransactionHistorySuccess", $apiData['data']['langType']);
            $this->apiResponse['totalPages'] = ceil($totalData / $limit) . "";
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification(($offset > 0 ? 'allcatchedUp' : "transactionHistoryNotFound"), $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }


    
    public function loadWalletData_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }


        $response = array();
        $totalAmount = 0;
        $filterArr = [
            "userId" => $user->id,
            "status" => 1
        ];
        if(
            isset($apiData['data']['fdate']) && !empty($apiData['data']['fdate'])
            && isset($apiData['data']['tdate']) && !empty($apiData['data']['tdate'])        
        ) {
            $filterArr["fdate"] = strtotime($apiData['data']['fdate']);
            $filterArr["tdate"] = strtotime($apiData['data']['tdate']);
        }
        if(isset($apiData['data']['service']) && !empty($apiData['data']['service'])) {
            $filterArr["service"] = $apiData['data']['service'];
        }
        if(isset($apiData['data']['patientid']) && !empty($apiData['data']['patientid'])) {
            $filterArr["patientid"] = $apiData['data']['patientid'];
        }
                
        $getData = $this->User_Wallet_Model->get($filterArr);
        //$last = $this->db->last_query();
        if(!empty($getData)) {
            $chartData = [];
            foreach ($getData as $k => $v) {
                if(isset($v->chartAmount) && isset($v->chartDate)) {
                    $chartData[] = [
                        'chartAmount' => $v->chartAmount,
                        'chartDate' => $v->chartDate
                    ];
                    $totalAmount = $totalAmount + $v->chartAmount;
                }
            }
            sort($chartData);
            $response = $chartData;
        }
        //echo "<pre>"; print_r($getData); exit;

        /*
        $response = array();
        $totalAmount = 0;
        if(
            isset($apiData['data']['month']) && !empty($apiData['data']['month'])
            && isset($apiData['data']['year']) && !empty($apiData['data']['year'])        
        ) {
            $filterArr = [
                "userId" => $user->id,
                "month" => (int) $apiData['data']['month'],
                "year" => (int) $apiData['data']['year'],
                "status" => 1
            ];
            $getData = $this->User_Wallet_Model->get($filterArr);
            // $last = $this->db->last_query();
            if(!empty($getData)) {
                $chartData = [];
                foreach ($getData as $k => $v) {
                    $chartData[] = [
                        'chartAmount' => $v->chartAmount,
                        'chartDate' => $v->chartDate
                    ];
                    $totalAmount = $totalAmount + $v->chartAmount;
                }
                sort($chartData);
                $response = $chartData;
            }
        }
        */

        $this->apiResponse['status'] = "1";
        $this->apiResponse['message'] = $this->Common->GetNotification("getWalletChartDataSuccess", $apiData['data']['langType']);
        $this->apiResponse['data'] = $response;
        $this->apiResponse['totalAmount'] = "$".number_format($totalAmount, 2);
        // $this->apiResponse['last'] = $last;
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }


}
