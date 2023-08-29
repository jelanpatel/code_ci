<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'libraries/REST_Controller.php';

class Resources extends REST_Controller {

    function __construct() {
        parent::__construct();
        $this->load->library('upload');
        $this->load->model('Common_Model','Common');
        $this->load->model('Background_Model');
        $this->load->model('Users_Model', 'User');
        $this->load->model('Resources_Model','Resources');
        $this->load->model('Resources_Category_Model','Resources_Category');
        $this->load->model('Resources_Bookmark_Model','Resources_Bookmark');
    }

    public function getResourceCategory_post() {
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

        $search = (isset($apiData['data']['search']) ? $apiData['data']['search'] : "");
        $response = $this->Resources_Category->get(['apiResponse'=>true,'search'=>$search,'status'=>'1','limit'=>$limit,'offset'=>$offset]);
        $totalData = $this->Resources_Category->get(['status'=>'1','search'=>$search], false, true);
        if (!empty($response)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("getResourcesCategorySuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
            $this->apiResponse['totalPages'] = ceil($totalData / $limit) . "";
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification(($offset > 0 ? 'allcatchedUp' : "resourcesCategoryNotFound"), $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getResource_post() {
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

        $search = (isset($apiData['data']['search']) ? $apiData['data']['search'] : "");
        $categoryId = (isset($apiData['data']['categoryId']) ? $apiData['data']['categoryId'] : "");
        $type = (isset($user->role) && $user->role == 3 ? 2 : 1);
        $isBookmark = (isset($apiData['data']['isBookmark']) && $apiData['data']['isBookmark'] == "1" ? $user->id : "");
        $response = $this->Resources->get(['apiResponse'=>true,'getBookmarkedResource'=>$isBookmark,'search'=>$search,'type'=>$type,'categoryId'=>$categoryId,'status'=>'1','limit'=>$limit,'offset'=>$offset]);
        $totalData = $this->Resources->get(['status'=>'1','getBookmarkedResource'=>$isBookmark,'categoryId'=>$categoryId,'type'=>$type,'search'=>$search], false, true);
        if (!empty($response)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("getResourcesSuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
            $this->apiResponse['totalPages'] = ceil($totalData / $limit) . "";
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification(($offset > 0 ? 'allcatchedUp' : "resourcesNotFound"), $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getResourceDetail_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);
        
        if (!isset($apiData['data']['resourceId']) || empty($apiData['data']['resourceId'])) {
            $this->apiResponse['message'] = $this->Common->GetNotification("resourceIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $response = $this->Resources->get(['apiResponse'=>true,'checkAddedInBookmark'=>$user->id,'status'=>'1','id'=>$apiData['data']['resourceId']],true);
        if (!empty($response)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("getResourceDetailSuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("resourceDetailNotFound", $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function addRemoveResourceBookmark_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if (!isset($apiData['data']['resourceId']) || empty($apiData['data']['resourceId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("resourceIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $bookmarkExistData = $this->Resources_Bookmark->get(['userId'=>$user->id,'resourceId'=>$apiData['data']['resourceId']],true);
        if(!empty($bookmarkExistData)){
            if($bookmarkExistData->status == 1){
                $set = $this->Resources_Bookmark->setData(['status'=>2],$bookmarkExistData->id);
                $successMsg = "removeToBookmarkSuccess";
                $failMsg = "failToRemoveBookmark";
            }else{
                $set = $this->Resources_Bookmark->setData(['status'=>1],$bookmarkExistData->id);
                $successMsg = "addToBookmarkSuccess";
                $failMsg = "failToAddBookmark";
            }
        }else{
            $set = $this->Resources_Bookmark->setData(['userId'=>$user->id,'resourceId'=>$apiData['data']['resourceId']]);
            $successMsg = "addToBookmarkSuccess";
            $failMsg = "failToAddBookmark";
        }

        if (!empty($set)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification($successMsg, $apiData['data']['langType']);
        } else {
            $this->apiResponse['status'] = "0";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification($failMsg, $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

    public function getBookmarkResource_post() {
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

        $search = (isset($apiData['data']['search']) ? $apiData['data']['search'] : "");
        $categoryId = (isset($apiData['data']['categoryId']) ? $apiData['data']['categoryId'] : "");
        $response = $this->Resources->get(['apiResponse'=>true,'getBookmarkedResource'=>$user->id,'search'=>$search,'categoryId'=>$categoryId,'status'=>'1','limit'=>$limit,'offset'=>$offset]);
        $totalData = $this->Resources->get(['getBookmarkedResource'=>$user->id,'status'=>'1','categoryId'=>$categoryId,'search'=>$search], false, true);
        if (!empty($response)) {
            $this->apiResponse['status'] = "1";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("getBookmarkResourcesSuccess", $apiData['data']['langType']);
            $this->apiResponse['data'] = $response;
            $this->apiResponse['totalPages'] = ceil($totalData / $limit) . "";
        } else {
            $this->apiResponse['status'] = "6";
            $this->apiResponse['message'] = $this->Common_Model->GetNotification(($offset > 0 ? 'allcatchedUp' : "bookmarkResourcesNotFound"), $apiData['data']['langType']);
        }
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }
}
