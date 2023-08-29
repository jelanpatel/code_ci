<?php

defined('BASEPATH') OR exit('No direct script access allowed');
ob_start();

require APPPATH . 'libraries/REST_Controller.php';

class Plan extends REST_Controller {

    function __construct() {
        parent::__construct();
        $this->load->library('upload');
        $this->load->model('Common_Model','Common');
        $this->load->model('Background_Model');
        $this->load->model('Users_Model', 'User');            
        $this->load->model('StripeConnect_Model');
        $this->load->model('User_Transaction_Model');
        $this->load->model('User_Wallet_Model');
        $this->load->model('User_Bank_Model');
        $this->load->model('User_Card_Model');
        $this->load->model('WebAppProviderSubscription_Model');
        $this->load->model('User_Services_Model');
        $this->load->model('User_Appointment_Model');
        $this->load->model('User_Availability_Model');
        $this->load->model('Users_Plan_Model');
        $this->load->model('Plan_Model');
    }

    public function saveSubscriptionPlan_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if ($user->role != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if(!isset($apiData['data']['planPrice']) || empty($apiData['data']['planPrice'])){
            $this->apiResponse['message'] = $this->Common->GetNotification("planPrice_required", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $mode = getenv('STRIPE_MODE');
        if($apiData['data']['planPrice'] == "49"){
            $stripePriceId = getenv('MONTHLY_PLAN_BASIC_TEST');
            if(strtoupper($mode) == 'LIVE') {
                $stripePriceId = getenv('MONTHLY_PLAN_BASIC_LIVE');
            }
        } 
        else if($apiData['data']['planPrice'] == "99"){
            $stripePriceId = getenv('MONTHLY_PLAN_PRO_TEST');
            if(strtoupper($mode) == 'LIVE') {
                $stripePriceId = getenv('MONTHLY_PLAN_PRO_LIVE');
            }
        } 
        else if($apiData['data']['planPrice'] == "300"){
            $stripePriceId = getenv('MONTHLY_PLAN_ELITE_TEST');
            if(strtoupper($mode) == 'LIVE') {
                $stripePriceId = getenv('MONTHLY_PLAN_ELITE_LIVE');
            }
        }
        else if($apiData['data']['planPrice'] == "x") {
            $stripePriceId = getenv('MONTHLY_PLAN_FREE_TEST');
            if(strtoupper($mode) == 'LIVE') {
                $stripePriceId = getenv('MONTHLY_PLAN_FREE_LIVE');
            }
        } 
        
        $userCard = $this->User_Card_Model->get(['status' => 1, 'userId' => $user->id]);
        $userCard = end($userCard);
        $cardId = 0;
        if (!empty($userCard)) {
            $cardId = $userCard->id;
        }
        $this->load->library('stripe');
        
        $existSubData = $this->WebAppProviderSubscription_Model->get(['userId' => $user->id],true);
        if(!empty($existSubData)) {
            $lastId = $existSubData->id;
        } else {
            $lastId = $this->WebAppProviderSubscription_Model->setData([
                'current_plan' => 2,
                'last_plan' => 1,
                'userId' => $user->id,
                'status' => 0,
            ]);
        }
        $metadata = [
            "userId" => $user->id,
            "email" => $user->email,
            'web_app_provider_subscription_id' => $lastId,
        ];  
        $subscriptionData = $this->stripe->addSubscription($user->stripeCustomerId, $stripePriceId, $metadata);
        error_log("\n\n -------------------------------------" . date('c') . "\n Request => " . json_encode($this->input->post()) . " \n Response => " . json_encode($subscriptionData), 3, FCPATH . 'worker/web_app_provider_subscription_log-' . date('d-m-Y') . '.txt');
        if(isset($subscriptionData) && !empty($subscriptionData)) {
            if(isset($subscriptionData['error'])) {
                $this->apiResponse['status'] = "0";
                $this->apiResponse['message'] = $subscriptionData['error']['message'];
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);

            } else if (!isset($subscriptionData->id) || $subscriptionData->id == "") {
                $this->apiResponse['status'] = "0";
                $this->apiResponse['message'] = "Create to failed subcription";
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            } else {                          
                // ------- transaction table --------//
                $tranId = $this->User_Transaction_Model->setData([
                    'userId' => $user->id,                                
                    'cardId' => $cardId, // user first card id
                    'userSubscriptionId' => $lastId,
                    'stripeTransactionId' => $subscriptionData['id'],
                    'stripeSubscriptionId' => $subscriptionData['id'],
                    'stripeTranJson' => json_encode($subscriptionData),
                    'amount' => ($subscriptionData['plan']['amount'] / 100),
                    'type' => 2, //1->Credit, 2->Debit
                    'payType' => 3, // web_app_provider_subscription
                    'tranType' => 2, //1-> Wallet Transaction, 2->Stripe Transaction
                    'subscriptionType' => 1, //1->New 2->Update 3->Recurring
                    ]);
                // ------- end transaction table --------//
                $this->WebAppProviderSubscription_Model->setData([
                    'transactionId' => $tranId,
                    'expiredDate' => $subscriptionData['current_period_end'],
                    'current_plan' => 2,
                    'last_plan' => 1,
                    'status' => 1,
                    'amount' => ($subscriptionData['plan']['amount'] / 100),
                ], $lastId);
                // successed 
                $this->apiResponse['status'] = "1";
                $this->apiResponse['message'] =  $this->Common->GetNotification("plansucessSubScription", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);

                // $this->Background_Model->adminVerifyDoctorAccountMail($usr->id);
            }
        } else {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = "Create to failed subcription";
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
    }

    public function saveDoctotrPlanOLD_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        if ($user->role != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if(!isset($apiData['data']['userId']) || empty($apiData['data']['userId'])){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if(!isset($apiData['data']['planName']) || empty($apiData['data']['planName'])){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("planNameRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if(!isset($apiData['data']['goals']) || empty($apiData['data']['goals'])){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("goalsRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if(!isset($apiData['data']['programOrHabits']) || empty($apiData['data']['programOrHabits'])){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("programOrHabitsRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
       
        if(!isset($apiData['data']['allDateTime']) || empty($apiData['data']['allDateTime'])){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("allDateTimeRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if(isset($apiData['data']['planId']) && !empty($apiData['data']['planId'])){
            $exitsPlan = $this->Plan_Model->get(['id'=>$apiData['data']['planId'] ,'status'=>'1'], true);
            if(empty($exitsPlan)){
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("planNotExits", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
        }

        $userData = $this->User->get(['id'=>$apiData['data']['userId'], 'status'=>'1'], true);
        if(empty($userData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);   
        }

        $userCardData = $this->User_Card_Model->get(['userId'=>$apiData['data']['userId'], 'status'=>'1'], true);
        if(empty($userCardData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userCardNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);   
        }

        foreach($apiData['data']['allDateTime'] as $value){
            if(!isset($value['serviceId']) || empty($value['serviceId'])){
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("serviceIdRequired", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
            if(!isset($value['dateAndTime']) || empty($value['dateAndTime'])){
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("dateRequired", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
            // if(!isset($value['dateTime']) || empty($value['dateTime'])){
            //     $this->apiResponse['message'] = $this->Common_Model->GetNotification("dateTimeRequired", $apiData['data']['langType']);
            //     return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            // }
            $getServiceData = $this->User_Services_Model->get(['id'=>$value['serviceId'], 'userId'=>$user->id, 'status'=> 1], true);
            // echo "<pre>";print_r($getServiceData);die;
            if(empty($getServiceData)){
                $this->apiResponse['status'] = "0";
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("serviceNotFound", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);

            }
            if($getServiceData->type != 1){
                if(!isset($apiData['data']['location']) || empty($apiData['data']['location'])){
                    $this->apiResponse['message'] = $this->Common_Model->GetNotification("locationRequired", $apiData['data']['langType']);
                    return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                }
                if(!isset($apiData['data']['latitude']) || empty($apiData['data']['latitude'])){
                    $this->apiResponse['message'] = $this->Common_Model->GetNotification("latitudeRequired", $apiData['data']['langType']);
                    return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                }
                if(!isset($apiData['data']['longitude']) || empty($apiData['data']['longitude'])){
                    $this->apiResponse['message'] = $this->Common_Model->GetNotification("longitudeRequired", $apiData['data']['langType']);
                    return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                }
            }
            // foreach($value['date'] as $fullDateValue){
            foreach($value['dateAndTime'] as $fullDateValue){
                if(!isset($fullDateValue['dateTime']) || empty($fullDateValue['dateTime'])){
                    $this->apiResponse['message'] = $this->Common_Model->GetNotification("dateTimeRequired", $apiData['data']['langType']);
                    return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                }
                foreach($fullDateValue['dateTime'] as $dateTimeValue){
                    if(!isset($dateTimeValue['startTime']) || empty($dateTimeValue['startTime'])){
                        $this->apiResponse['message'] = $this->Common_Model->GetNotification("startTimeRequired", $apiData['data']['langType']);
                        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                    }
                    if(!isset($dateTimeValue['endTime']) || empty($dateTimeValue['endTime'])){
                        $this->apiResponse['message'] = $this->Common_Model->GetNotification("endTimeRequired", $apiData['data']['langType']);
                        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                    }
                    $startDateTime = strtotime($fullDateValue['date'].' '.$dateTimeValue['startTime']);
                    $endDateTime = strtotime($fullDateValue['date'].' '.$dateTimeValue['endTime']);
                //     // echo "<pre>";print_r($fullDateValue);
                //     // echo "<pre>--------------";print_r($startDateTime);die;
                //     // echo "<pre>";print_r($startDateTime);die;
                   
                    $existAvailabilityBookedData = $this->User_Availability_Model->get(['userId'=>$user->id,'checkBookedSlot'=>['startDateTime'=>$startDateTime,'endDateTime'=>$endDateTime], 'isBooked'=>1, 'status'=>1],true);
                    if(isset($apiData['data']['planId']) && !empty($apiData['data']['planId'])){
                        $planDetailData = $this->Users_Plan_Model->get(['planId'=> $apiData['data']['planId'], 'status'=>'1']);
                    }
                    if(!empty($existAvailabilityBookedData)){
                        if(isset($planDetailData) && !empty($planDetailData)){
                            $inArr = array_column($planDetailData, 'userAvailabilityId');
                            if(!in_array($existAvailabilityBookedData->id, $inArr)){
                                $this->apiResponse['status'] = "0";
                                $message = $fullDateValue['date']." ".$dateTimeValue['startTime']." Selected slot already booked";
                                $this->apiResponse['message'] = $this->Common_Model->GetNotification($message, $apiData['data']['langType']);
                                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                            } else {
                                $exitData = $existAvailabilityBookedData->id;
                            }
                        }else {
                            $this->apiResponse['status'] = "0";
                            $message = $fullDateValue['date']." ".$dateTimeValue['startTime']." Selected slot already booked";
                            $this->apiResponse['message'] = $this->Common_Model->GetNotification($message, $apiData['data']['langType']);
                            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                        }
                    } else {
                        $exitData = '';
                    }
                    // print_r($inArr);die;
                    if(isset($exitData)){
                        if(empty($exitData)){
                            $existAvailabilityData = $this->User_Availability_Model->get(['userId'=>$user->id,'checkBookedSlot'=>['startDateTime'=>$startDateTime,'endDateTime'=>$endDateTime],'isBooked'=>0,'status'=>1], true);
                            // print_r($existAvailabilityData);die();
                            if(empty($existAvailabilityData)){
                                error_log("\n\n -------------------------------------" . date('c'). " \n Request => ".json_encode($existAvailabilityData) . " \n Response => ".json_encode($existAvailabilityData,true), 3, FCPATH.'worker/existAvailabilityData-'.date('d-m-Y').'.txt');
                                $availabilityData = array();
                                $availabilityData['userId'] = $user->id;
                                $availabilityData['timing'] = $getServiceData->duration;
                                $availabilityData['dateTime'] = $startDateTime;
                                $availabilityData['endDateTime'] = $endDateTime;
                                $availabilityData['status'] = 1;
                                $setUserAvailbility = $this->User_Availability_Model->setData($availabilityData);
                                error_log("\n\n -------------------------------------" . date('c'). " \n Request => ".json_encode($setUserAvailbility) . " \n Response => ".json_encode($setUserAvailbility,true), 3, FCPATH.'worker/existAvailabilityData-'.date('d-m-Y').'.txt');
                                // echo "<pre>";print_r($this->db->last_query());die;
                            }
                            $avaData = isset($setUserAvailbility) && !empty($setUserAvailbility) ? $setUserAvailbility : $existAvailabilityData->id;
                        } else {
                            $avaData = $exitData;
                        }
                        // die;
                        $planSetData = array();
                        $planSetData['doctorId'] = $user->id;
                        $planSetData['userId'] = $apiData['data']['userId'];
                        $planSetData['planName'] = $apiData['data']['planName']; 
                        $planSetData['goals'] = $apiData['data']['goals']; 
                        $planSetData['programOrHabits'] = $apiData['data']['programOrHabits'];
                        $planSetData['status'] = '1';
                        if(isset($apiData['data']['planId']) && !empty($apiData['data']['planId'])){
                            $getplanData = $this->Plan_Model->get(['id'=>$apiData['data']['planId']], true);
                            if(!empty($getplanData)){
                                $setplanData = $this->Plan_Model->setData($planSetData, $apiData['data']['planId']);
                            }
                        } else {
                            $getplanData = $this->Plan_Model->get($planSetData, true);
                            if(empty($getplanData)){
                                $setplanData = $this->Plan_Model->setData($planSetData);
                            }
                        }
                        $planData = isset($setplanData) && !empty($setplanData) ? $setplanData : $getplanData->id;
                        
                        if(isset($planData) && !empty($planData)){
                            if(isset($planDetailData) && !empty($planDetailData)){
                                foreach($planDetailData as $deleteAppointment){
                                    $deletAppointmentData = $this->User_Appointment_Model->get(['id'=> $deleteAppointment->appointmentId, 'status'=>[1,2,3]], true);
                                    if(!empty($deletAppointmentData)){
                                        $this->User_Appointment_Model->setData(['status'=>'4'],$deletAppointmentData->id);
                                    }
                                }
                            }
                            // print_r($planData);die;
                            $amount = round($getServiceData->price,2);
                            $couponCode = "";
                            $discountCouponId = "";
                            $discountPrice = "";
                            $appointmentData = array();
                            $appointmentData['userId'] = $apiData['data']['userId'];
                            $appointmentData['doctorId'] = $user->id;
                            $appointmentData['userServiceId'] = $getServiceData->id;
                            $appointmentData['userAvailabilityId'] = $avaData;
                            $appointmentData['userCardId'] = $userCardData->id;
                            $appointmentData['planId'] = $planData;
                            $appointmentData['appointmentType'] = $getServiceData->type;
                            $appointmentData['couponCode'] = $couponCode;
                            $appointmentData['discountPrice'] = $discountPrice;
                            $appointmentData['paymentStatus'] = "0";
                            $appointmentData['isServices'] = 1;
                            if($getServiceData->type != 1){
                                $appointmentData['location'] = $apiData['data']['location'];
                                $appointmentData['latitude'] = $apiData['data']['latitude'];
                                $appointmentData['longitude'] = $apiData['data']['longitude'];
                            }
                            $appointmentData['price'] = $amount;
                            $appointmentData['authenticationCode'] = $this->Common_Model->random_string(4);
                            
                            // print_r($appointmentData);die;
                            $appointmentBookId = $this->User_Appointment_Model->setData($appointmentData);
                            
                            if(!empty($appointmentBookId)){
                                if(isset($apiData['data']['planId']) && !empty($apiData['data']['planId'])){
                                    if(isset($planDetailData) && !empty($planDetailData)){
                                        foreach($planDetailData as $setPlanUser){
                                            $this->Users_Plan_Model->setData(['status'=>'2'], $setPlanUser->id);
                                        }
                                    }
                                    // $this->Users_Plan_Model->get(['status'=>'1', 'planId'=>$apiData['data']['planId']]);
                                }
                                $this->Users_Plan_Model->setData(['planId'=>$planData, 'appointmentId'=>$appointmentBookId, 'serviceId'=>$getServiceData->id, 'userAvailabilityId'=>$avaData, 'status'=>'1']);
                                
                                $currentDate = date('d-m-Y h:i');
                                if(isset($exitData) && !empty($exitData)){
                                    $doctorAvailabilityData = $this->User_Availability_Model->get(['userId'=>$user->id,'id'=>$avaData, 'isBooked'=>'1','status'=>1], true);
                                } else {
                                    $doctorAvailabilityData = $this->User_Availability_Model->get(['userId'=>$user->id,'id'=>$avaData, 'isBooked'=>0,'status'=>1], true);
                                }
                                // $hourdiff = round(($doctorAvailabilityData->dateTime - strtotime($currentDate))/3600, 1);
    
                                // if($hourdiff <= 72){ 
                                //     $this->load->library('stripe',array('type'=>'1'));
                                //     $stripeChargeData['customer'] = $userCardData->customerId;
                                //     $stripeChargeData['source'] = $userCardData->cardId;
                                //     $stripeChargeData['amount'] = $amount * 100;
                                //     $stripeChargeData['capture'] = false;        
                                //     $stripeChargeData['description'] ="Plan Services Booking Payment, userId: #".$user->id.", doctorId: #".$user->id.", userCardId: #".$userCardData->id." , serviceType: ".$getServiceData->type.", planId: ".$planData;
                                //     $response = $this->stripe->addCharge($stripeChargeData);
    
                                //     error_log("\n\n -------------------------------------" . date('c'). " \n Request => ".json_encode($stripeChargeData) . " \n Response => ".json_encode($response,true), 3, FCPATH.'worker/bookServicePayment-'.date('d-m-Y').'.txt');
                                //     if(isset($response) && !empty($response)){
                                //         if(isset($response['error'])){ 
                                //             $response['error']['status'] = '0';
                                //             $this->apiResponse = $response['error'];
                                //             return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                                //         }elseif(!isset($response->id) || $response->id==""){ 
                                //             $this->apiResponse['status'] = "0";
                                //             $this->apiResponse['message'] = $this->Common_Model->GetNotification("failToBookService", $apiData['data']['langType']);
                                //             return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                                //         }else{
                                //             // Send Mail and SMS in Authentication code
                                //             $notiData = [];
                                //             $notiData['userId'] = $apiData['data']['userId'];
                                //             $notiData['authenticationCode'] = $appointmentData['authenticationCode'];
                                //             $this->Common_Model->backroundCall('sendMailAndSMSInPlanAuthenticationCodeForUser', $notiData);
                                //             // ./ Send Mail and SMS in Authentication code
    
                                //             // For user transaction record
                                //             $transactionData = array();
                                //             $transactionData['userId'] = $apiData['data']['userId'];
                                //             $transactionData['userIdTo'] = $user->id;
                                //             $transactionData['cardId'] = $userCardData->id;
                                //             $transactionData['appointmentId'] = $appointmentBookId;
                                //             $transactionData['availabilityId'] = $doctorAvailabilityData->id;
                                //             $transactionData['stripeTransactionId'] = $response['id'];
                                //             $transactionData['stripeTranJson'] = json_encode($response);
                                //             $transactionData['amount'] = $amount;
                                //             $transactionData['type'] = 2; // Debit amount
                                //             $transactionData['payType'] = 9; // Service Booking Payment 
                                //             $transactionData['tranType'] = 2; //Stripe Transaction
                                //             $transactionData['status'] = 4 ; 
                                //             $transactionData['createdDate'] = $response['created'];
                                //             $this->User_Transaction_Model->setData($transactionData);
                                //             $appointmentBookId = $this->User_Appointment_Model->setData(['paymentStatus'=>1],$appointmentBookId);
    
                                //             // ./ Set notification
    
                                //             // Send notification for transaction success
                                //             // Set notification 
                                //             $notiData = [];
                                //             $notiData['send_from'] = $apiData['data']['userId'];
                                //             $notiData['send_to'] = $apiData['data']['userId'];
                                //             $notiData['model_id'] = (int)$appointmentBookId;
                                //             $notiData['amount'] = '$'.number_format($amount,2);
                                //             $this->Common_Model->backroundCall('transactionSuccessForSchedulePlan', $notiData);
                                        
                                //             // ./ Set notification
                                            
                                //             /*if(!empty($referraldata)){
                                //                 $this->User_Referral_Earning_Model->setData(['status'=>2],$referraldata->id);
                                //             }*/
    
                                //             // $this->User->setData(['walletAmount'=>($doctorData->walletAmount + $amount)],$doctorData->id);
                                        
                                //         }
                                //     }else{
                                //         $this->apiResponse['status'] = "0";
                                //         $this->apiResponse['message'] = $this->Common_Model->GetNotification("failToBookService", $apiData['data']['langType']);
                                //         return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                                //     }
                                // }
                                // ./ Set notification
    
                                // Send notification doctor to user
                                // Set notification 
                                $notiData = [];
                                $notiData['send_from'] = $user->id;
                                $notiData['send_to'] = $apiData['data']['userId'];
                                $notiData['model_id'] = (int)$appointmentBookId;
                                $notiData['userName'] = $userData->name;
                                $notiData['userEmail'] = $userData->email;
                                $notiData['startDateTime'] = $doctorAvailabilityData->dateTime;
                                $notiData['endDateTime'] = $doctorAvailabilityData->endDateTime;
                                $this->Common_Model->backroundCall('schedulePlanServiceByDoctor', $notiData);
    
                                // ./ Set notification
    
                                // Send notification in booked doctor
                                // Set notification 
                                $notiData = [];
                                $notiData['send_from'] = $user->id;
                                $notiData['send_to'] = $user->id;
                                $notiData['model_id'] = (int)$appointmentBookId;
                                $notiData['userName'] = $userData->name;
                                $notiData['userEmail'] = $userData->email;
                                $notiData['startDateTime'] = $doctorAvailabilityData->dateTime;
                                $notiData['endDateTime'] = $doctorAvailabilityData->endDateTime;
                                $this->Common_Model->backroundCall('schedulePlanServiceForDoctor', $notiData);
    
                                $this->User_Availability_Model->setData(['isBooked'=>1],$doctorAvailabilityData->id);
                            }
                        }
                    }
                }

            }
           
        }
        $this->apiResponse['status'] = "1";
        $this->apiResponse['message'] = $this->Common_Model->GetNotification("planServiceBookedSuccess", $apiData['data']['langType']);
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function saveDoctotrPlan_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        if ($user->role != '3') {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if(!isset($apiData['data']['userId']) || empty($apiData['data']['userId'])){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if(!isset($apiData['data']['planName']) || empty($apiData['data']['planName'])){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("planNameRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if(!isset($apiData['data']['goals']) || empty($apiData['data']['goals'])){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("goalsRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if(!isset($apiData['data']['programOrHabits']) || empty($apiData['data']['programOrHabits'])){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("programOrHabitsRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
       
        if(!isset($apiData['data']['serviceData']) || empty($apiData['data']['serviceData'])){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("allDateTimeRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if(isset($apiData['data']['planId']) && !empty($apiData['data']['planId'])){
            $exitsPlan = $this->Plan_Model->get(['id'=>$apiData['data']['planId'] ,'status'=> array('1','3')], true);
            if(empty($exitsPlan)){
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("planNotExits", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
            }
        }

        $userData = $this->User->get(['id'=>$apiData['data']['userId'], 'status'=>'1'], true);
        if(empty($userData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);   
        }

        $userCardData = $this->User_Card_Model->get(['userId'=>$apiData['data']['userId'], 'status'=>'1'], true);
        if(empty($userCardData)){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userCardNotFound", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);   
        }


        try {

        #############################################################################
        $new_doctor_accessToken = "";
        $new_user_accessToken = "";
        if(
            !empty($user->gc_accessToken) && $user->gc_status == "1" &&
            !empty($userData->gc_accessToken) && $userData->gc_status == "1"
        ) {
            require_once('application/controllers/google-calendar-api.php');
            $site_url = current_url();
            $client_id = getenv('GOOGLE_KEY');
            $client_secret = getenv('GOOGLE_SECRET');
            $rurl = base_url()."google/calendar";
            $capi = new GoogleCalendarApi();
            $new_doctor_accessToken = $capi->RefreshAccessToken($client_id, $rurl, $client_secret, $user->gc_accessToken);
            $new_user_accessToken = $capi->RefreshAccessToken($client_id, $rurl, $client_secret, $userData->gc_accessToken);
        }
        //echo $new_doctor_accessToken."<br><br><br>".$new_user_accessToken; exit;
        #############################################################################


        if(isset($apiData['data']['planId']) && !empty($apiData['data']['planId'])){
            $planDetailData = $this->Users_Plan_Model->get(['planId'=> $apiData['data']['planId'], 'status'=>'1']);
            #echo "<pre>"; print_r($planDetailData); exit;
            if(!empty($planDetailData)){
                foreach($planDetailData as $deleteAppointment){
                    if(!empty($deleteAppointment)){
                        $this->User_Appointment_Model->setData(['status'=>'4'],$deleteAppointment->appointmentId);
                    }
                }
            }
        }
        #echo "<pre>"; print_r($apiData['data']['serviceData']); exit;
        $addedPlanIds = array();
        $addedAvailIds = array();
        foreach($apiData['data']['serviceData'] as $value){
            if(!isset($value['serviceId']) || empty($value['serviceId'])){
                continue;
            }
            // if(!isset($value['serviceId']) || empty($value['serviceId'])){
            //     continue;
            // }
            // if(!isset($value['availabilityData']) || empty($value['availabilityData'])){
            //     continue;
            // }
            // if(!isset($value['dateTime']) || empty($value['dateTime'])){
            //     continue;
            // }
            $getServiceData = $this->User_Services_Model->get(['id'=>$value['serviceId'], 'userId'=>$user->id, 'status'=> 1], true);
            // echo "<pre>";print_r($getServiceData);die;
            if(empty($getServiceData)){
                $this->apiResponse['status'] = "0";
                $this->apiResponse['message'] = $this->Common_Model->GetNotification("serviceNotFound", $apiData['data']['langType']);
                return $this->response($this->apiResponse, REST_Controller::HTTP_OK);

            }
           
            $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));

            foreach($value['availabilityData'] as $fullDateValue){
                // if(!isset($fullDateValue['date']) || empty($fullDateValue['date'])){
                //     continue;
                // }
                // if(!isset($fullDateValue['startTime']) || empty($fullDateValue['startTime'])){
                //     continue;
                // }
                // if(!isset($fullDateValue['endTime']) || empty($fullDateValue['endTime'])){
                //     continue;
                // }
                // $startDateTime = strtotime($fullDateValue['date'].' '.$fullDateValue['startTime']);
                // $endDateTime = strtotime($fullDateValue['date'].' '.$fullDateValue['endTime']);
                
                $startDateTime = new DateTime($fullDateValue['date'].' '.$fullDateValue['startTime'], new DateTimeZone( $myUserTimeZone ));
                $startDateTime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));

                $endDateTime = new DateTime($fullDateValue['date'].' '.$fullDateValue['endTime'], new DateTimeZone( $myUserTimeZone ));
                $endDateTime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));

                $existAvailabilityBookedData = $this->User_Availability_Model->get(['userId'=>$user->id,'getNotInIdAdded'=>$addedAvailIds,'checkBookedSlot'=>['startDateTime'=>$startDateTime->format('U'),'endDateTime'=>$endDateTime->format('U')], 'isBooked'=>1, 'status'=>1],true);
                if(isset($apiData['data']['planId']) && !empty($apiData['data']['planId'])){
                    $planDetailData = $this->Users_Plan_Model->get(['planId'=> $apiData['data']['planId'],'getNotInIdAdded'=>$addedPlanIds, 'status'=>'1']);
                }
                if(!empty($existAvailabilityBookedData)){
                    if(isset($planDetailData) && !empty($planDetailData)){
                        foreach($planDetailData as $planDelete){
                            $this->User_Availability_Model->setData(['isBooked'=>'0'], $planDelete->userAvailabilityId);
                            $this->Users_Plan_Model->setData(['status'=>'2'], $planDelete->id);
                        }
                    }else {
                        $this->apiResponse['status'] = "0";
                        $message = $fullDateValue['date']." ".$fullDateValue['startTime']." Selected slot already booked";
                        $this->apiResponse['message'] = $this->Common_Model->GetNotification($message, $apiData['data']['langType']);
                        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                    }
                } 
                // print_r($inArr);die;
                $existAvailabilityData = $this->User_Availability_Model->get(['userId'=>$user->id,'checkBookedSlot'=>['startDateTime'=>$startDateTime->format('U'),'endDateTime'=>$endDateTime->format('U')],'isBooked'=>0,'status'=>1], true);
                // print_r($existAvailabilityData);die();
                $setUserAvailbility = "";
                if(empty($existAvailabilityData)){
                    error_log("\n\n -------------------------------------" . date('c'). " \n Request => ".json_encode($existAvailabilityData) . " \n Response => ".json_encode($existAvailabilityData,true), 3, FCPATH.'worker/existAvailabilityData-'.date('d-m-Y').'.txt');
                    $availabilityData = array();
                    $availabilityData['userId'] = $user->id;
                    $availabilityData['timing'] = $getServiceData->duration;
                    $availabilityData['dateTime'] = $startDateTime->format('U');
                    $availabilityData['endDateTime'] = $endDateTime->format('U');
                    $availabilityData['status'] = 1;
                    $setUserAvailbility = $this->User_Availability_Model->setData($availabilityData);
                    error_log("\n\n -------------------------------------" . date('c'). " \n Request => ".json_encode($setUserAvailbility) . " \n Response => ".json_encode($setUserAvailbility,true), 3, FCPATH.'worker/existAvailabilityData-'.date('d-m-Y').'.txt');
                    // echo "<pre>";print_r($this->db->last_query());die;
                }
                $avaData = (isset($setUserAvailbility) && !empty($setUserAvailbility) ? $setUserAvailbility : $existAvailabilityData->id);
                
                // die;
                $planSetData = array();
                $planSetData['doctorId'] = $user->id;
                $planSetData['userId'] = $apiData['data']['userId'];
                $planSetData['planName'] = $apiData['data']['planName']; 
                $planSetData['goals'] = $apiData['data']['goals']; 
                $planSetData['programOrHabits'] = $apiData['data']['programOrHabits'];
                //$planSetData['status'] = '1'; 
                $planSetData['status'] = '3'; //0=> InActive, 1=>Request, 2=>Delete 3=>Complate

                if(isset($apiData['data']['planId']) && !empty($apiData['data']['planId'])){
                    $getplanData = $this->Plan_Model->get(['id'=>$apiData['data']['planId']], true);
                    if(!empty($getplanData)){
                        $setplanData = $this->Plan_Model->setData($planSetData, $apiData['data']['planId']);
                    }
                } else {
                    $getplanData = $this->Plan_Model->get($planSetData, true);
                    if(empty($getplanData)){
                        $setplanData = $this->Plan_Model->setData($planSetData);
                    }
                }
                $planData = isset($setplanData) && !empty($setplanData) ? $setplanData : $getplanData->id;
                
                if(isset($planData) && !empty($planData)){
                    // if(isset($planDetailData) && !empty($planDetailData)){
                    //     foreach($planDetailData as $deleteAppointment){
                    //         $deletAppointmentData = $this->User_Appointment_Model->get(['id'=> $deleteAppointment->appointmentId, 'status'=>[1,2,3]], true);
                    //         if(!empty($deletAppointmentData)){
                    //             $this->User_Appointment_Model->setData(['status'=>'4'],$deletAppointmentData->id);
                    //         }
                    //     }
                    // }
                    // print_r($planData);die;
                    $amount = round($getServiceData->price,2);
                    $couponCode = "";
                    $discountCouponId = "";
                    $discountPrice = "";
                    $appointmentData = array();
                    $appointmentData['userId'] = $apiData['data']['userId'];
                    $appointmentData['doctorId'] = $user->id;
                    $appointmentData['userServiceId'] = $getServiceData->id;
                    $appointmentData['userAvailabilityId'] = $avaData;
                    $appointmentData['userCardId'] = $userCardData->id;
                    $appointmentData['planId'] = $planData;
                    $appointmentData['appointmentType'] = $getServiceData->type;
                    $appointmentData['couponCode'] = $couponCode;
                    $appointmentData['discountPrice'] = $discountPrice;
                    $appointmentData['paymentStatus'] = "0";
                    $appointmentData['isServices'] = 1;
                    if($getServiceData->type != 1){
                        $appointmentData['location'] = isset($value['location']) && !empty($value['location']) ? $value['location'] : "";
                        $appointmentData['latitude'] = isset($value['latitude']) && !empty($value['latitude']) ? $value['latitude'] : "";
                        $appointmentData['longitude'] = isset($value['longitude']) && !empty($value['longitude']) ? $value['longitude'] : "";
                    }
                    $appointmentData['price'] = $amount;
                    $appointmentData['authenticationCode'] = $this->Common_Model->random_string(4);
                    
                    // print_r($appointmentData);die;
                    $appointmentBookId = $this->User_Appointment_Model->setData($appointmentData);
              
                                
                    #############################################################################
                    $data_arr = [
                        "doctor" => [
                            "name" => $user->name,
                            "title" => "Appointment - ".$userData->name,
                            "accessToken" => $new_doctor_accessToken,
                            "refreshToken" => $user->gc_accessToken,
                            "date" => $fullDateValue['date'],
                            "stime" => $fullDateValue['startTime'],
                            "etime" => $fullDateValue['endTime'],
                            "aid" => $appointmentBookId
                        ],
                        "user" => [
                            "name" => $userData->name,
                            "title" => "Appointment - ".$user->name,
                            "accessToken" => $new_user_accessToken,
                            "refreshToken" => $userData->gc_accessToken,
                            "date" => $fullDateValue['date'],
                            "stime" => $fullDateValue['startTime'],
                            "etime" => $fullDateValue['endTime'],
                            "aid" => $appointmentBookId
                        ]
                    ];
                    if(
                        !empty($new_doctor_accessToken) && $user->gc_status == "1" &&
                        !empty($new_user_accessToken) && $userData->gc_status == "1"
                    ) {
                        $this->Background_Model->createEventGoogleCalender($data_arr);
                    }
                    #############################################################################


                    
                    if(!empty($appointmentBookId)){
                        // if(isset($apiData['data']['planId']) && !empty($apiData['data']['planId'])){
                        //     if(isset($planDetailData) && !empty($planDetailData)){
                        //         foreach($planDetailData as $setPlanUser){
                        //             $this->Users_Plan_Model->setData(['status'=>'2'], $setPlanUser->id);
                        //         }
                        //     }
                        //     // $this->Users_Plan_Model->get(['status'=>'1', 'planId'=>$apiData['data']['planId']]);
                        // }
                        $addedPlanIds[] = $this->Users_Plan_Model->setData(['planId'=>$planData, 'appointmentId'=>$appointmentBookId, 'serviceId'=>$getServiceData->id, 'userAvailabilityId'=>$avaData, 'status'=>'1']);
                        $addedAvailIds[] = $avaData;
                        $currentDate = date('d-m-Y h:i');
                        
                        $doctorAvailabilityData = $this->User_Availability_Model->get(['userId'=>$user->id,'id'=>$avaData, 'isBooked'=>0,'status'=>1], true);
                      
                        if(isset($doctorAvailabilityData->id) && !empty($doctorAvailabilityData->id)) {
                            $this->User_Availability_Model->setData(['isBooked'=>1],$doctorAvailabilityData->id);
                        }
                    }
                }
                // }

            }
            $this->Background_Model->C009C_appointment_payment_72hourse_model(); //cron job - payment deduction           
        }

        // Set notification 
        $notiData = [];
        $notiData['send_from'] = $user->id;
        $notiData['send_to'] = $apiData['data']['userId'];
        $notiData['model_id'] = isset($planData) && !empty($planData) ? (int)$planData : '';
        $notiData['userName'] = isset($userData) && !empty($userData) ? $userData->name : '';
        $notiData['userEmail'] = isset($userData) && !empty($userData) ? $userData->email : '';
        // $notiData['startDateTime'] = $doctorAvailabilityData->dateTime;
        // $notiData['endDateTime'] = $doctorAvailabilityData->endDateTime;
        $this->Common_Model->backroundCall('schedulePlanServiceByDoctor', $notiData);

        // ./ Set notification

        // Send notification in booked doctor
        // Set notification 
        // $notiData = [];
        // $notiData['send_from'] = $user->id;
        // $notiData['send_to'] = $user->id;
        // $notiData['model_id'] = isset($planData) && !empty($planData) ? (int)$planData : '';
        // $notiData['userName'] = isset($userData) && !empty($userData) ? $userData->name : '';
        // $notiData['userEmail'] = isset($userData) && !empty($userData) ? $userData->email : '';
        // $notiData['startDateTime'] = $doctorAvailabilityData->dateTime;
        // $notiData['endDateTime'] = $doctorAvailabilityData->endDateTime;
        // $this->Common_Model->backroundCall('schedulePlanServiceForDoctor', $notiData);
        $this->apiResponse['status'] = "1";
        $this->apiResponse['message'] = $this->Common_Model->GetNotification("planServiceBookedSuccess", $apiData['data']['langType']);
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);

        }
        catch (Exception $e) {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $e->errorMessage();
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
    }

    public function getDoctorPlanSlot_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        if($user->role != '3'){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        
        if(!isset($apiData['data']['serviceId']) || empty($apiData['data']['serviceId'])){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("serviceIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        
        $getServiceData = $this->User_Services_Model->get(['id'=>$apiData['data']['serviceId'], 'userId'=>$user->id, 'status'=> 1], true);
        if(!empty($getServiceData)){
            $response = array();
            $response = $this->Background_Model->planAvablitySlot($getServiceData->duration);
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("avabilitySloat", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("serviceNotFound", $apiData['data']['langType']);
        } 
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function checkAvailabelSlot_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        if($user->role != '3'){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        
        if(!isset($apiData['data']['date']) || empty($apiData['data']['date'])){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("dateRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if(!isset($apiData['data']['dateTime']) || empty($apiData['data']['dateTime'])){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("dateTimeRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        // foreach($apiData['data']['date'] as $date){
            $date = $apiData['data']['date'];
            $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));

            foreach($apiData['data']['dateTime'] as $dateTime){
                // $startDateTime = strtotime($date.' '.$dateTime['startTime']);
                // $endDateTime = strtotime($date.' '.$dateTime['endTime']);

                $startDateTime = new DateTime($date.' '.$dateTime['startTime'], new DateTimeZone( $myUserTimeZone ));
                $startDateTime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));

                $endDateTime = new DateTime($date.' '.$dateTime['endTime'], new DateTimeZone( $myUserTimeZone ));
                $endDateTime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));

                $existAvailabilityData = $this->User_Availability_Model->get(['userId'=>$user->id,'checkBookedSlot'=>['startDateTime'=>$startDateTime->format('U'),'endDateTime'=>$endDateTime->format('U')],'isBooked'=>1,'status'=>1],true);
                if(!empty($existAvailabilityData)){
                    $this->apiResponse['status'] = "0";
                    $message = $date." ".$dateTime['startTime']." Selected slot already booked";
                    $this->apiResponse['message'] = $this->Common_Model->GetNotification($message, $apiData['data']['langType']);
                    return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                } else {
                    $this->apiResponse['status'] = "1";
                    $this->apiResponse['message'] = $this->Common_Model->GetNotification("NoBookSloat", $apiData['data']['langType']);
                    return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
                }
            }
        // }
    }

    public function serviceList_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        if($user->role != '3'){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if(!isset($apiData['data']['isPage']) && empty($apiData['data']['isPage'])){
            $page_number = (isset($apiData['data']['page']) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : 1;
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
        }

        $data['userId'] = $user->id;
        $data['status'] = '1';
        $data['search'] = isset($apiData['data']['search']) && !empty($apiData['data']['search']) ? $apiData['data']['search'] : "";
        $totalData = $this->User_Services_Model->get($data,false,true);
        if(!isset($apiData['data']['isPage']) && empty($apiData['data']['isPage'])){
            $data['limit'] = $limit;
            $data['offset'] = $offset;
        }
        $response = $this->User_Services_Model->get($data);
        // echo "<pre>";print_r($response);die;
        if(!empty($response)){
            foreach($response as $value){
                $value->price = number_format($value->price,2);
                if($value->price > 0){
                    /* $value->price = number_format($value->price,2); */
                }
                $value->statusText = "";
                if($value->type == 1) { //1=>Virtual, 2=> Home, 3=> office/GYM
                    $value->statusText = "Virtual";
                }
                else if($value->type == 2) {
                    $value->statusText = "Home";
                }
                else if($value->type == 3) {
                    $value->statusText = "Office/GYM";
                }
            }
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("servicesListSucess", $apiData['data']['langType']);
            $this->apiResponse['totalPages'] = isset($apiData['data']['isPage']) && !empty($apiData['data']['isPage']) ? 1 : ceil($totalData / $limit) . "";
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("noUserServicesDataFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }
    
    public function patientsList_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        if($user->role != '3'){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if(!isset($apiData['data']['isPage']) && empty($apiData['data']['isPage'])){
            $page_number = (isset($apiData['data']['page']) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : 1;
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
        }
        $getAppointmentUserData = $this->User_Appointment_Model->get(['doctorId'=>$user->id]);
        if(!empty($getAppointmentUserData)){
            $appointmentDataUserIds = array_column($getAppointmentUserData, 'userId');
            $appointmentDataUserIds = array_unique($appointmentDataUserIds);
            $data['id'] = $appointmentDataUserIds;
        }
        
        $data['role'] = '2';
        $data['status'] = '1';
        $data['search'] = isset($apiData['data']['search']) && !empty($apiData['data']['search']) ? $apiData['data']['search'] : "";
        $totalData = $this->Users_Model->get($data,false,true);
        if(!isset($apiData['data']['isPage']) && empty($apiData['data']['isPage'])){
            $data['limit'] = $limit;
            $data['offset'] = $offset;
        }
        $response = $this->Users_Model->get($data);
        // print_r($data);die;
        if(!empty($response)){
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("patientListSucess", $apiData['data']['langType']);
            $this->apiResponse['totalPages'] = isset($apiData['data']['isPage']) && !empty($apiData['data']['isPage']) ? 1 : ceil($totalData / $limit) . "";
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("noUserDataFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function planDetail_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        // if($user->role != '3'){
        //     $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
        //     return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        // }
        if(!isset($apiData['data']['planId']) || empty($apiData['data']['planId'])){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("planIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        
        $response = $this->Plan_Model->get(['id'=>$apiData['data']['planId'], 'apiResponse'=>true,'onlyNameApi'=>true, 'status'=>[1, 3]], true);
        if(!empty($response)){
            $response->isAcceptRequest = '1';
            if($response->status == '1'){
                $response->isAcceptRequest = '0';
            }
            #$planData = $this->Users_Plan_Model->get(['planId'=>$response->id, 'getActiveAppointment'=> true, 'status'=>'1']);
            $planData = $this->Users_Plan_Model->get(['planId'=>$response->id, 'status'=>'1']);
            #echo "<pre>"; print_r($planData); exit;
            if(!empty($planData)){
                $srId = array_column($planData, 'serviceId');
                $srIds = array_unique($srId);
                $response->serviceData = $this->User_Services_Model->get(['id'=>$srIds, 'status'=>['1','0']]); //0: Inactive 1: Active, 2: Deleted
                $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));

                foreach($response->serviceData as $value){
                    $value->serviceId = $value->id;
                    #$getAvaData = $this->Users_Plan_Model->get(['planId'=>$response->id, 'serviceId'=>$value->id, 'getActiveAppointment'=> true, 'status'=>'1']);
                    $getAvaData = $this->Users_Plan_Model->get(['planId'=>$response->id, 'serviceId'=>$value->id, 'status'=>'1']);
                    $avaData = array_column($getAvaData, 'userAvailabilityId');

                    foreach($getAvaData as $appData){
                        $appData = $this->User_Appointment_Model->get(['id'=>$appData->appointmentId, 'status'=>[0,1,2,3]], true);
                        // print_r($appData);die;
                        if(!empty($appData)){
                            //echo "<pre>"; print_r($planData); 
                            $value->location = isset($appData->location) ? $appData->location : "";
                            $value->latitude = isset($appData->latitude) ? $appData->latitude : "";
                            $value->longitude = isset($appData->longitude) ? $appData->longitude : "";
                        }
                        else {
                            #continue;
                        }
                    }
                    $value->availabilityData = $this->User_Availability_Model->get(['id'=>$avaData, 'orderbytime' => true, 'status'=>'1']);
                    
                    foreach($value->availabilityData as $avaData){
                        $datetime = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
                        $datetime->setTimezone(new DateTimeZone($myUserTimeZone));
                        $datetime->setTimestamp($avaData->dateTime);
                        
                        // $avaData->dayAndDate = $this->Common_Model->getPlanDayAndDateName($avaData->dateTime,$myUserTimeZone);
                        $fullDate = $this->Common_Model->getPlanDayAndDateName($avaData->dateTime,$myUserTimeZone);
                        $avaData->dateFormate = $fullDate['fullDate'];
                        $avaData->dateAndTime = $fullDate['dateTime'];
                        $avaData->date = $fullDate['date'];
                        
                        // ----------
                        /* start time */
                        $match_date = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
                        $match_date->setTimezone(new DateTimeZone($myUserTimeZone));
                        $match_date->setTimestamp($avaData->dateTime);
                        $avaData->startTime = $match_date->format('h:i A');

                        /* end Time */
                        $match_date_1 = new DateTime(null,new DateTimeZone(getenv('SYSTEMTIMEZON')));
                        $match_date_1->setTimezone(new DateTimeZone($myUserTimeZone));
                        $match_date_1->setTimestamp($avaData->endDateTime);
                        $avaData->endTime = $match_date_1->format('h:i A');
                    }
                }
            }
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("planDetailSuceess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("planDetailNotFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);

    }

    public function getUserPlansAppointmentList_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        if($user->role != '3'){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        $page_number = (isset($apiData['data']['page']) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : 1;
        $limit = (isset($apiData['data']['limit']) && $apiData['data']['limit'] != '') ? $apiData['data']['limit'] : 5;
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
        // $data['doctorId'] = $user->id;
        if(isset($apiData['data']['planStatus']) && $apiData['data']['planStatus'] == '1'){
            $data['status'] = '1';
        } else if(isset($apiData['data']['planStatus']) && $apiData['data']['planStatus'] == '3'){
            $data['status'] = '3';
        } else {
            $data['status'] = [1,3];
        }
        $data['groupBy'] = "planId";
        // $data['getUserData'] = true;
        $data['apiResponse'] = true;
        $data['doctorDataCheck'] = $user->id;
        $data['apisearch'] = (isset($apiData['data']['search']) ? $apiData['data']['search'] : "");
        $totalData = $this->Users_Plan_Model->get($data, false, true);
        $data['limit'] = $limit;
        $data['offset'] = $offset;
        $response = $this->Users_Plan_Model->get($data);
        if(!empty($response)){
            foreach($response as $planData){
                $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
                //$appointmentData = $this->User_Appointment_Model->get(['apiResponse'=>true,'getAvailabilityData'=>true,'id'=>$planData->appointmentId], true);
                $appointmentData = $this->User_Appointment_Model->get(['availabilityStatusForCanel'=>true,'apiResponse'=>true,'getAvailabilityData'=>true,'id'=>$planData->appointmentId], true);
                if(isset($appointmentData) && !empty($appointmentData)){
                    $planData->planTimeText = $this->Common_Model->checkDateText($appointmentData->appointmentDateTime,$myUserTimeZone);
                    $planData->planStatus = $this->Common_Model->checkAppointmentStatusText($appointmentData->appointmentDateTime, $appointmentData->appointmentEndDateTime, $appointmentData->status, $myUserTimeZone, $appointmentData->appointmentType);
                } else {
                    $planData->planTimeText = "";

                    $planData->planStatus = array("isAppointment"=>"1", "textColor"=>"", "text"=> "", "showOtp"=>"", "showMsgIcon"=>0, "showLocationIcon"=>'0');  
                }

            }
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("getDoctoPlanAppointmentsListSuccess", $apiData['data']['langType']);
            $this->apiResponse['totalPages'] = ceil($totalData / $limit) . "";
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification(($offset > 0 ? 'allcatchedUp' : "appointmentsListNotFound"), $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);

    }

    /* Patient */
    public function getDoctorPlansAppointmentList_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        $page_number = (isset($apiData['data']['page']) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : 1;
        $limit = (isset($apiData['data']['limit']) && $apiData['data']['limit'] != '') ? $apiData['data']['limit'] : 5;
        if (isset($apiData['data']['page']) && $apiData['data']['page'] == 1) {
            $offset = 0;
        } else {
            if (isset($apiData['data']['page']) && $apiData['data']['page'] != '1') {
                $offset = ($page_number * $limit) - $limit;
            } else {
                $offset = 0;
            }
        }

        if($user->role != '2'){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotUser", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $data = array();
        $data['userId'] = $user->id;
        if(isset($apiData['data']['planStatus']) && $apiData['data']['planStatus'] == '1'){
            $data['getProgressAppointmentData'] = true;
        } else if(isset($apiData['data']['planStatus']) && $apiData['data']['planStatus'] == '3'){
            $data['getCompleteAppointmentData'] = true;
        }
        else {
            $data["getAllAppointmentData"] = true;
        }
        $data['status'] = '3';
        $data['getDoctorData'] = true;
        $totalData = $this->Plan_Model->get($data, false, true);
        $data['limit'] = $limit;
        $data['offset'] = $offset;
        $response = $this->Plan_Model->get($data);
        //echo $this->db->last_query(); exit;
        if(!empty($response)) {
            foreach($response as $planData) {
                $planData->planStatusText = "";  
                $planData->planStatusTextColor = "";
                if($planData->planAppoinmentStatus == 0) {
                    $planData->planStatusText = "Completed";  
                    $planData->planStatusTextColor = "#00D507";
                }
                else {
                    $planData->planStatusText = "In Progress";  
                    $planData->planStatusTextColor = "#FFA638";
                }
            }
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("getUserPlanAppointmentsListSuccess", $apiData['data']['langType']);
            $this->apiResponse['totalPages'] = ceil($totalData / $limit) . "";
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification(($offset > 0 ? 'allcatchedUp' : "appointmentsListNotFound"), $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);

    }

    public function requestPlanAccept_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        if($user->role != '2'){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        if(!isset($apiData['data']['planId']) || empty($apiData['data']['planId'])){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("planIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if(!isset($apiData['data']['planStatus']) || empty($apiData['data']['planStatus'])){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("planStatusRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $planData = $this->Plan_Model->get(['id'=>$apiData['data']['planId'], 'userId'=> $user->id], true);
        if(!empty($planData)){
            if($apiData['data']['planStatus'] == '1'){
                $message = "requestPlanAcceptSuccess";
                $planAcceptData = $this->Plan_Model->setData(['status'=> '3'], $planData->id);
                $this->Background_Model->C009C_appointment_payment_72hourse_model(); //cron job - payment deduction
            } else if($apiData['data']['planStatus'] == '2'){
                $message = "requestPlanCancelSuccess";
                $getUserPlanData = $this->Users_Plan_Model->get(['planId'=>$planData->id, 'status'=>'1']);
                if(!empty($getUserPlanData)){
                    $planAcceptData = $this->Plan_Model->setData(['status'=> '2'], $planData->id);
                    foreach($getUserPlanData as $userPlanDelete){
                        $this->Users_Plan_Model->setData(['status'=>'2'], $userPlanDelete->id);
                        $this->User_Appointment_Model->setData(['status'=>'4'], $userPlanDelete->appointmentId);
                        $this->User_Availability_Model->setData(['isBooked'=>'0'], $userPlanDelete->userAvailabilityId);
                    }
                    $this->Background_Model->cancelPlanAppoitmentNotification($planAcceptData);
                }
            }
        }

        if(isset($planData) && !empty($planData)){
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification($message, $apiData['data']['langType']);
        } else {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification('wrongPlanId', $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        
    }

    // s.o.a.p
    public function getUserAppointmentPlans_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        if($user->role != '3'){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotDoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        // if(!isset($apiData['data']['appointmentId']) || empty($apiData['data']['appointmentId'])){
        //     $this->apiResponse['message'] = $this->Common_Model->GetNotification("appointmentIdRequired", $apiData['data']['langType']);
        //     return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        // }
        if(!isset($apiData['data']['userId']) || empty($apiData['data']['userId'])){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("userIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if(!isset($apiData['data']['serviceId']) || empty($apiData['data']['serviceId'])){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("serviceIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        $response = array();
        $appointmentData = $this->User_Appointment_Model->get(['apiResponse'=>true,'getAvailabilityData'=>true,'serviceId'=> $apiData['data']['serviceId'], 'userId'=>$apiData['data']['userId'], 'doctorId'=> $user->id, 'status'=>[1,2,3], 'onlyPlanData'=> true]);
        $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
        if(!empty($appointmentData)){
            foreach($appointmentData as $value){
                $response[] = $this->Plan_Model->get(['id'=>$value->planId]);
                foreach($response as $responsePlan){
                    $responsePlan->planId = $responsePlan->id;
                    $responsePlan->planTimeText = $this->Common_Model->checkDateText($value->appointmentDateTime,$myUserTimeZone);
                    $responsePlan->planStatus = $this->Common_Model->checkAppointmentStatusText($value->appointmentDateTime, $value->appointmentEndDateTime, $value->status, $myUserTimeZone, $value->appointmentType);
                }
            }
        }


        // $data['doctorId'] = $user->id;
        // $planData = $this->Users_Plan_Model->get(['appointmentId'=>$apiData['data']['appointmentId']], true);
        // if(!empty($planData)){
        //     $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
        //     $data['id'] = $planData->planId;
        //     $appointmentData = $this->User_Appointment_Model->get(['apiResponse'=>true,'getAvailabilityData'=>true,'id'=>$planData->appointmentId], true);

        //     $response = $this->Plan_Model->get($data, true);
        //     $response->planTimeText = $this->Common_Model->checkDateText($appointmentData->appointmentDateTime,$myUserTimeZone);
        //     $response->planStatus = $this->Common_Model->checkAppointmentStatusText($appointmentData->appointmentDateTime, $appointmentData->appointmentEndDateTime, $appointmentData->status, $myUserTimeZone, $appointmentData->appointmentType);
            
        // }

        if(isset($response) && !empty($response)){
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("getUserPlanAppointmentsListSuccess", $apiData['data']['langType']);
            // $this->apiResponse['totalPages'] = ceil($totalData / $limit) . "";
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification('NoPlanAvailable', $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);

    }

    public function getDoctorPlanSlotNew_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if($user->role != '3'){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        
        if(!isset($apiData['data']['date']) || empty($apiData['data']['date'])){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("dateRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if(!isset($apiData['data']['serviceId']) || empty($apiData['data']['serviceId'])){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("serviceIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $getServiceData = $this->User_Services_Model->get(['id'=>$apiData['data']['serviceId'], 'userId'=>$user->id, 'status'=> 1], true);
        if(!empty($getServiceData)){
            $response = array();
            $response = $this->Background_Model->planAvablitySlotWithBooked($user->id, $apiData['data']['date'], $getServiceData->duration);
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("avabilitySloat", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("serviceNotFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);

    }


    public function checkSlotAvailabel_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if($user->role != '3'){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        
        if(!isset($apiData['data']['availabilityData']) || empty($apiData['data']['availabilityData'])){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("availabilityData", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
        $response = array();
        foreach($apiData['data']['availabilityData'] as $availabilityData){
            if(!isset($availabilityData['startTimesFormat']) || empty($availabilityData['startTimesFormat'])){
                continue;
            }
            if(!isset($availabilityData['endTimesFormat']) || empty($availabilityData['endTimesFormat'])){
                continue;
            }
            if(!isset($availabilityData['date']) || empty($availabilityData['date'])){
                continue;
            }

            $startDateTime = new DateTime($availabilityData['date'].' '.$availabilityData['startTimesFormat'], new DateTimeZone( $myUserTimeZone ));
            $startDateTime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));
            
            $endDateTime = new DateTime($availabilityData['date'].' '.$availabilityData['endTimesFormat'], new DateTimeZone( $myUserTimeZone ));
            $endDateTime->setTimezone(new DateTimeZone(getenv('SYSTEMTIMEZON')));
            
            $existAvailabilityBookedData = $this->User_Availability_Model->get(['userId'=>$user->id,'checkBookedSlot'=>['startDateTime'=>$startDateTime->format('U'),'endDateTime'=>$endDateTime->format('U')], 'isBooked'=>1, 'status'=>1],true);
            $returnData = array();
            if(!empty($existAvailabilityBookedData)){
                $returnData['date'] = $availabilityData['date'];
                $returnData['startTimesFormat'] = $availabilityData['startTimesFormat'];
                $returnData['endTimesFormat'] = $availabilityData['endTimesFormat'];
            }
            if(!empty($returnData)){
                $response[] = $returnData;
            }
        }
        if(!empty($response)){
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = "Your scheduled time is already booked for date here. Please book another slot time for date here"; 
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("noAlreadyBookSlot", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);

    }

    public function deletePlan_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if($user->role != '3'){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        if(!isset($apiData['data']['planId']) || empty($apiData['data']['planId'])){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("planIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $planData = $this->Plan_Model->get(['id'=>$apiData['data']['planId']], true);
        if(!empty($planData)){
            $this->Plan_Model->setData(['status'=>'2'], $planData->id);
            $planDataSub = $this->Users_Plan_Model->get(['planId' => $planData->id, 'status' => [0,1]]);
            if(!empty($planDataSub)){
                foreach($planDataSub as $planValue){
                    $this->User_Appointment_Model->setData(['status' => '4'], $planValue->appointmentId);
                    $this->User_Availability_Model->setData(['isBooked' => '0'], $planValue->userAvailabilityId);
                    $this->Users_Plan_Model->setData(['status' => '2'], $planValue->id);
                }
            }
            
        }
        if(isset($planData) && !empty($planData)){
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification('successDeletePlan', $apiData['data']['langType']);
        } else {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification('wrongPlanId', $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getUserPlansListDoctor_post(){
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        if($user->role != '3'){
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("youAreNotADoctor", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        $page_number = (isset($apiData['data']['page']) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : 1;
        $limit = (isset($apiData['data']['limit']) && $apiData['data']['limit'] != '') ? $apiData['data']['limit'] : 5;
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
        // $data['doctorId'] = $user->id;
        if(isset($apiData['data']['planStatus']) && $apiData['data']['planStatus'] == '1'){
            $data['status'] = '1';
        } else if(isset($apiData['data']['planStatus']) && $apiData['data']['planStatus'] == '3'){
            $data['status'] = '3';
        } else {
            $data['status'] = [1,3];
        }
        $data['groupBy'] = "planId";
        // $data['getUserData'] = true;
        $data['apiResponse'] = true;
        $data['doctorDataCheck'] = $user->id;
        $data['userDataCheck'] = $apiData['data']["userId"];
        $data['apisearch'] = (isset($apiData['data']['search']) ? $apiData['data']['search'] : "");
        $totalData = $this->Users_Plan_Model->get($data, false, true);
        $data['limit'] = $limit;
        $data['offset'] = $offset;
        $response = $this->Users_Plan_Model->get($data);
        if(!empty($response)){
            foreach($response as $planData){
                $myUserTimeZone = (!empty($user->timeZone) ? $user->timeZone : getenv('SYSTEMTIMEZON'));
                //$appointmentData = $this->User_Appointment_Model->get(['apiResponse'=>true,'getAvailabilityData'=>true,'id'=>$planData->appointmentId], true);
                $appointmentData = $this->User_Appointment_Model->get(['availabilityStatusForCanel'=>true,'apiResponse'=>true,'getAvailabilityData'=>true,'id'=>$planData->appointmentId], true);
                if(isset($appointmentData) && !empty($appointmentData)){
                    $planData->planTimeText = $this->Common_Model->checkDateText($appointmentData->appointmentDateTime,$myUserTimeZone);
                    $planData->planStatus = $this->Common_Model->checkAppointmentStatusText($appointmentData->appointmentDateTime, $appointmentData->appointmentEndDateTime, $appointmentData->status, $myUserTimeZone, $appointmentData->appointmentType);
                } else {
                    $planData->planTimeText = "";

                    $planData->planStatus = array("isAppointment"=>"1", "textColor"=>"", "text"=> "", "showOtp"=>"", "showMsgIcon"=>0, "showLocationIcon"=>'0');  
                }

            }
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("getDoctoPlanAppointmentsListSuccess", $apiData['data']['langType']);
            $this->apiResponse['totalPages'] = ceil($totalData / $limit) . "";
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification(($offset > 0 ? 'allcatchedUp' : "appointmentsListNotFound"), $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);

    }


}

?>