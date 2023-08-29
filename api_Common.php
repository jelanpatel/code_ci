<?php

defined( 'BASEPATH' ) OR exit( 'No direct script access allowed' );

require APPPATH . 'libraries/REST_Controller.php';

class Common extends REST_Controller {

	function __construct() {
		parent::__construct();
		$this->load->library( 'upload' );
		$this->load->model( 'Common_Model' );
		$this->load->model( 'Cms_Model' );
		$this->load->model( 'Faq_Model' );
		$this->load->model( 'App_User_Feedback_Model' );
		$this->load->model( 'Ticket_Model' );
		$this->load->model( 'Background_Model' );
		$this->load->model( 'Languages_Model', 'Languages' );
		$this->load->model( 'Profession_Model', 'Profession' );
		$this->load->model( 'Medications_Model', 'Medications' );
		$this->load->model( 'Dosage_Model', 'Dosage' );
		$this->load->model( 'Frequency_Model', 'Frequency' );
		$this->load->model( 'Allergies_Type_Model', 'Allergies_Type' );
		$this->load->model( 'Health_Issues_Model', 'Health_Issues' );
		$this->load->model( 'Injuries_Model', 'Injuries' );
		$this->load->model( 'Specialties_Model', 'Specialties' );
		$this->load->model( 'Surgeries_Model', 'Surgeries' );
		$this->load->model( 'Illness_Model', 'Illness' );
		$this->load->model( 'Complications_Model', 'Complications' );
		$this->load->model( 'Goal_Model', 'Goal' );
		$this->load->model( 'User_Appointment_Model', 'User_Appointment' );
		$this->load->model( 'Country_Model' );
		$this->load->model( 'State_Model' );
		$this->load->model( 'City_Model' );
		$this->load->model( 'Auth_Model' );
		$this->load->model( 'HumanBodyParts_Model' );
		$this->load->model( 'Notification_Model' );
		$this->load->model( 'Chat_Model' );
        $this->load->model('Users_Model', 'User');
	}

	public function mediaVoiceUpload_post() {
		$this->checkGuestUserRequest();
		$apiData = json_decode( file_get_contents( 'php://input' ), TRUE );
		$pageURL = (isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] == 'on') ? "https://" : "http://";
		$pageURL .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
		error_log( "\n\n -------------------------------------" . date( 'c' ) . " \n" . $pageURL . " \n" . print_r( $_POST, TRUE ) . " \n" . print_r( $_FILES, TRUE ), 3, FCPATH . 'worker/api_fileuploadlog-' . date( 'd-m-Y' ) . '.log' );
		ini_set( 'max_execution_time', 999999 );
		ini_set( 'memory_limit', '999999M' );
		ini_set( 'upload_max_filesize', '500M' );
		ini_set( 'max_input_time', '-1' );
		ini_set( 'max_execution_time', '-1' );

		$imgData = array();
		if(isset($_FILES['files']) && !empty($_FILES['files']) && isset($_POST["isweb"]) && $_POST["isweb"] == 1) {
			$upload_path = getenv( 'UPLOADPATH' );
			$allowed_types = array(".mp3");
			foreach($_FILES as $key => $file) {
				if(is_array($_FILES[$key])) {
					if($_FILES[$key]["error"] == 0) {
						$fileExt = ".mp3";
						$fileName = date('ymdhis').$this->Common_Model->random_string(6).$fileExt;
						$upload_dir = $upload_path."/".$fileName;
						if(move_uploaded_file($_FILES[$key]["tmp_name"], $upload_dir)) {
							$tmp = array();
							$tmp['mediaName'] = $fileName;
							$tmp['mediaRealName'] = $fileName; //$_FILES[$key]['name'];
							$tmp['mediaBaseUrl'] = base_url(getenv('UPLOAD_URL'))."".$fileName;
							$tmp['medialThumUrl'] = base_url(getenv('UPLOAD_URL'))."".$fileName;
							$imgData = $tmp;
						}
					}
				}
			}			
		}
        else if(isset($_FILES['files']) && !empty($_FILES['files'])) {
			$upload_path = getenv( 'UPLOADPATH' );
			$allowed_types = array(".mp3");
			foreach($_FILES as $key => $file) {
				if(is_array($_FILES[$key])) {
					if($_FILES[$key]["error"] == 0) {
						$fileExt = ".mp3";
						$fileName = date('ymdhis').$this->Common_Model->random_string(6).$fileExt;
						$upload_dir = $upload_path."/".$fileName;
						if(move_uploaded_file($_FILES[$key]["tmp_name"], $upload_dir)) {
							$tmp = array();
							$tmp['mediaName'] = $fileName;
							$tmp['mediaRealName'] = $fileName; //$_FILES[$key]['name'];
							$tmp['mediaBaseUrl'] = base_url(getenv('UPLOAD_URL'))."".$fileName;
							$tmp['medialThumUrl'] = base_url(getenv('UPLOAD_URL'))."".$fileName;
							$imgData = $tmp;
						}
					}
				}
			}			
		}

		if(is_array($imgData) && count($imgData) != 0) {
			$this->apiResponse['status'] = "1";
			$this->apiResponse['data'] = $imgData;
			$this->apiResponse['base_url'] = base_url(getenv('UPLOAD_URL'));
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "imageUploaded", 1);
		}
		else {
			$this->apiResponse['status'] = "0";
			$this->apiResponse['message'] = $this->Common_Model->GetNotification( "noImageUpload", 1);
		}
		$this->response( $this->apiResponse, REST_Controller::HTTP_OK );
	}

	public function mediaUpload_post() {
		$this->checkGuestUserRequest();
		$apiData = json_decode( file_get_contents( 'php://input' ), TRUE );
		$pageURL = (isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] == 'on') ? "https://" : "http://";
		$pageURL .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
		error_log( "\n\n -------------------------------------" . date( 'c' ) . " \n" . $pageURL . " \n" . print_r( $_POST, TRUE ) . " \n" . print_r( $_FILES, TRUE ), 3, FCPATH . 'worker/api_fileuploadlog-' . date( 'd-m-Y' ) . '.log' );
		ini_set( 'max_execution_time', 999999 );
		ini_set( 'memory_limit', '999999M' );
		ini_set( 'upload_max_filesize', '500M' );
		ini_set( 'max_input_time', '-1' );
		ini_set( 'max_execution_time', '-1' );
		$imgData = array();
		if ( isset( $_FILES['files'] ) && ! empty( $_FILES['files'] ) ) {
			$image_type		 = $this->input->post( 'imageType' );
			$upload_path	 = getenv( 'UPLOADPATH' );
			$allowed_types = array("mp3", ".jpg", ".JPG", ".gif", ".png", ".PNG", ".jpeg", ".JPEG", ".mp4", ".m4a", ".MOV", ".MPEG-4", ".mpeg-4", ".mov", ".pdf", ".doc", ".docx", ".txt", ".PDF", ".DOC", ".DOCX", ".TXT");

			foreach ( $_FILES as $key => $file ) {
				if ( is_array( $_FILES[$key]["name"] ) ) {
					foreach ( $_FILES[$key]["name"] as $_key => $value ) {
						$_FILES['file']['name']		 = $_FILES[$key]['name'][$_key];
						$_FILES['file']['type']		 = $_FILES[$key]['type'][$_key];
						$_FILES['file']['tmp_name']	 = $_FILES[$key]['tmp_name'][$_key];
						$_FILES['file']['error']	 = $_FILES[$key]['error'][$_key];
						$_FILES['file']['size']		 = $_FILES[$key]['size'][$_key];

						$fileExt = $this->Common_Model->getFileExtension( $_FILES[$key]["name"][$_key] );
						if ( in_array( $fileExt, $allowed_types ) ) {
							$fileName	 = date( 'ymdhis' ) . $this->Common_Model->random_string( 6 ) . $fileExt;
							$upload_dir	 = $upload_path . "/" . $fileName;
							if ( move_uploaded_file( $_FILES[$key]["tmp_name"][$_key], $upload_dir ) ) {
								$tmp			 = array();
								$tmp['name']	 = $fileName;
								$tmp['realName'] = $_FILES[$key]['name'][$_key];
								$tmp['url']		 = base_url( UPLOAD_URL ) . "" . $fileName;
								$imgData[]		 = $tmp;
							}
						}
					}
				} else {

					$fileExt = $this->Common_Model->getFileExtension( $_FILES[$key]["name"] );
					if ( in_array( $fileExt, $allowed_types ) ) {
						$fileName	 = date( 'ymdhis' ) . $this->Common_Model->random_string( 6 ) . $fileExt;
						$upload_dir	 = $upload_path . "/" . $fileName;
						if ( move_uploaded_file( $_FILES[$key]["tmp_name"], $upload_dir ) ) {
							$tmp			 = array();
							$tmp['name']	 = $fileName;
							$tmp['realName'] = $_FILES[$key]["name"];
							$tmp['url']		 = base_url( UPLOAD_URL ) . "" . $fileName;
							$imgData[]		 = $tmp;
						}
					}
				}
			}
		}
		if ( ! empty( $imgData ) ) {
			$imgExtn	 = array( 'jpeg', 'gif', 'png', 'jpg', 'JPG', 'PNG', 'GIF', 'JPEG', 'mp3' );
			$finalData	 = array();
			foreach ( $imgData as $img ) {
				$tmp					 = [];
				$tmp['mediaRealName']	 = $img['realName'];
				$tmp['mediaName']		 = $img['name'];
				$tmp['mediaBaseUrl']	 = base_url( getenv( 'UPLOAD_URL' ) ) . $img['name'];
				$tmp['medialThumUrl']	 = base_url( getenv( 'UPLOAD_URL' ) ) . $img['name'];
				//Generate Video thumb image
				$extention				 = pathinfo( $img['name'], PATHINFO_EXTENSION );
				/*  if (!in_array($extention, $imgExtn)) {
				  $videoThumbImgName = date('ymdhis') . $this->Common_Model->random_string(6) . '.jpg';
				  exec('ffmpeg  -i ' . $upload_path .'/'. $img['name'] . ' -deinterlace -an -ss 2 -f mjpeg -t 1 -r 1 -y ' . $upload_path .'/'. $videoThumbImgName . ' 2>&1');
				  $tmp['videoThumbImgName'] = $videoThumbImgName;
				  $tmp['videoThumbImgUrl'] = base_url(getenv('UPLOAD_URL')) . $videoThumbImgName;
				  } else {
				  $tmp['videoThumbImgName'] = "";
				  $tmp['videoThumbImgUrl'] = "";
				  } */
				// ./Generate Video thumb image
				$finalData[]			 = $tmp;
			}
			$this->apiResponse['status']	 = "1";
			$this->apiResponse['data']		 = $finalData;
			$this->apiResponse['base_url']	 = base_url( getenv( 'UPLOAD_URL' ) );
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "imageUploaded", 1 );
		} else {
			$this->apiResponse['status']	 = "0";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "noImageUpload", 1 );
		}
		$this->response( $this->apiResponse, REST_Controller::HTTP_OK );
	}

	public function faq_post() {
		$user	 = $this->checkGuestUserRequest();
		$apiData = json_decode( file_get_contents( 'php://input' ), TRUE );

		if ( ! isset( $apiData['data']['type'] ) || empty( $apiData['data']['type'] ) ) {
			$this->apiResponse['message'] = $this->Common_Model->GetNotification( "typeRequired", $apiData['data']['langType'] );
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		}

		$page_number = (isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : 1;
		$limit		 = (isset( $apiData['data']['limit'] ) && $apiData['data']['limit'] != '') ? $apiData['data']['limit'] : 10;
		if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] == 1 ) {
			$offset = 0;
		} else {
			if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '1' ) {
				$offset = ($page_number * $limit) - $limit;
			} else {
				$offset = 0;
			}
		}

		$search		 = (isset( $apiData['data']['search'] ) ? $apiData['data']['search'] : "");
		$getData	 = $this->Faq_Model->get( [ 'type' => $apiData['data']['type'], 'search' => $search, 'status' => '1', 'limit' => $limit, 'offset' => $offset ] );		
		foreach($getData as $key=>$data){
			$getData[$key]->description =str_replace('%TOKEN%',$apiData['data']['token'],$data->description);
		} 
		$totalData	 = $this->Faq_Model->get( [ 'type' => $apiData['data']['type'], 'search' => $search, 'status' => '1' ], false, true );
		if ( ! empty( $getData ) ) {
			$this->apiResponse['status']	 = "1";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "faqlistSuccess", $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
			$this->apiResponse['data']		 = $getData;
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		} else {
			$this->apiResponse['status']	 = "6";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( ($offset > 0 ? 'allcatchedUp' : "faqNotFound" ), $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		}
	}

	public function faqDetails_post() {
		$user	 = $this->checkGuestUserRequest();
		$apiData = json_decode( file_get_contents( 'php://input' ), TRUE );

		if ( ! isset( $apiData['data']['faqId'] ) || empty( $apiData['data']['faqId'] ) ) {
			$this->apiResponse['message'] = $this->Common_Model->GetNotification( "faqIdRequired", $apiData['data']['langType'] );
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		}

		$getData = $this->Faq_Model->get( [ 'status' => '1', 'id' => $apiData['data']['faqId'] ], TRUE );
		if ( ! empty( $getData ) ) {
			/* if (isset($getData->description)) {
			  $getData->description = "<style>*,p{font-size:15px !important;}</style>" . $getData->description;
			  } */
			$this->apiResponse['status']	 = "1";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "getFaqDetailSuccess", $apiData['data']['langType'] );
			$this->apiResponse['data']		 = $getData;
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		} else {
			$this->apiResponse['status']	 = "6";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "faqDetailNotFound", $apiData['data']['langType'] );
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		}
	}

	public function setAppFeedback_post() {
		$user	 = $this->checkUserRequest();
		$apiData = json_decode( file_get_contents( 'php://input' ), TRUE );
		if ( ! isset( $apiData['data']['rating'] ) || empty( $apiData['data']['rating'] ) ) {
			$this->apiResponse['message'] = $this->Common_Model->GetNotification( "ratingRequired", $apiData['data']['langType'] );
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		}

		if ( ! isset( $apiData['data']['feedback'] ) || empty( $apiData['data']['feedback'] ) ) {
			$this->apiResponse['message'] = $this->Common_Model->GetNotification( "feedbackRequired", $apiData['data']['langType'] );
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		}
		$data				 = [];
		$data['userId']		 = $user->id;
		$data['rating']		 = $apiData['data']['rating'];
		$data['feedback']	 = $apiData['data']['feedback'];

		$appFeedbackId	 = "";
		$appFeedbackData = $this->App_User_Feedback_Model->get( [ 'userId' => $user->id ], true );
		if ( ! empty( $appFeedbackData ) ) {
			$data['status']	 = "1";
			$appFeedbackId	 = $this->App_User_Feedback_Model->setData( $data, $appFeedbackData->id );
		} else {
			$appFeedbackId = $this->App_User_Feedback_Model->setData( $data );
		}

		if ( ! empty( $appFeedbackId ) ) {
			$this->apiResponse['status']	 = "1";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "saveAppFeedbackSuccess", $apiData['data']['langType'] );
		} else {
			$this->apiResponse['status']	 = "0";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "failToSaveAppFeedback", $apiData['data']['langType'] );
		}
		return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
	}

	public function getMyAppFeedback_post() {
		$user	 = $this->checkUserRequest();
		$apiData = json_decode( file_get_contents( 'php://input' ), TRUE );

		$appFeedbackData = $this->App_User_Feedback_Model->get( [ 'userId' => $user->id, 'status' => 1 ], true );

		if ( ! empty( $appFeedbackData ) ) {
			$this->apiResponse['status']	 = "1";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "getMyAppFeedbackSuccess", $apiData['data']['langType'] );
			$this->apiResponse['data']		 = $appFeedbackData;
		} else {
			$this->apiResponse['status']	 = "6";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "failToGetMyAppFeedback", $apiData['data']['langType'] );
		}
		return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
	}

	public function getCMS_post() {
		$user	 = $this->checkGuestUserRequest();
		$apiData = json_decode( file_get_contents( 'php://input' ), TRUE );
		if ( ! isset( $apiData['data']['pageId'] ) || empty( $apiData['data']['pageId'] ) ) {
			$this->apiResponse['message'] = $this->Common_Model->GetNotification( "pageIdRequired", $apiData['data']['langType'] );
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		}

		$cms = $this->Cms_Model->get( [ 'status' => 1, 'key' => $apiData['data']['pageId'] ], TRUE );
		if ( ! empty( $cms ) ) {
			/* if (isset($cms->description)) {
			  $cms->description = "<style>*,p{font-size:15px !important;}</style>" . $cms->description;
			  } */
			$this->apiResponse['status']	 = "1";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "pageGetSuccess", $apiData['data']['langType'] );
			$this->apiResponse['data']		 = $cms;
		} else {
			$this->apiResponse['status']	 = "6";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "pageGetFail", $apiData['data']['langType'] );
		}
		return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
	}

	public function getActiveTicket_post() {
		$user	 = $this->checkUserRequest();
		$apiData = json_decode( file_get_contents( 'php://input' ), TRUE );

		$activeData = $this->Ticket_Model->get( [ 'status' => 1, 'userId' => $user->id ], false, true );

		$this->apiResponse['status']	 = "1";
		$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "ticketDataGetSuccess", $apiData['data']['langType'] );
		$this->apiResponse['data']		 = $activeData;
		return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
	}

	public function setTicket_post() {
		$user	 = $this->checkUserRequest();
		$apiData = json_decode( file_get_contents( 'php://input' ), TRUE );

		if ( ! isset( $apiData['data']['title'] ) || empty( $apiData['data']['title'] ) ) {
			$this->apiResponse['message'] = $this->Common_Model->GetNotification( "titleRequired", $apiData['data']['langType'] );
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		}

		if ( ! isset( $apiData['data']['description'] ) || empty( $apiData['data']['description'] ) ) {
			$this->apiResponse['message'] = $this->Common_Model->GetNotification( "descRequired", $apiData['data']['langType'] );
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		}

		$titcketData				 = array();
		$titcketData['userId']		 = $user->id;
		$titcketData['title']		 = $apiData['data']['title'];
		$titcketData['description']	 = $apiData['data']['description'];
		$titcketData['priority']	 = "0";

		$titcketId = $this->Ticket_Model->setData( $titcketData );

		if ( ! empty( $titcketId ) ) {
			$this->apiResponse['status']	 = "1";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "ticketSavedSuccess", $apiData['data']['langType'] );
		} else {
			$this->apiResponse['status']	 = "0";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "ticketSaveFail", $apiData['data']['langType'] );
		}

		$this->response( $this->apiResponse, REST_Controller::HTTP_OK );
	}

	public function getTicket_post() {
		$user	 = $this->checkUserRequest();
		$apiData = json_decode( file_get_contents( 'php://input' ), TRUE );

		$page_number = (isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : 1;
		$limit		 = (isset( $apiData['data']['limit'] ) && $apiData['data']['limit'] != '') ? $apiData['data']['limit'] : 10;
		if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] == 1 ) {
			$offset = 0;
		} else {
			if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '1' ) {
				$offset = ($page_number * $limit) - $limit;
			} else {
				$offset = 0;
			}
		}

		$titcketData = array();
		$titcketData = $this->Ticket_Model->get( [ 'userId' => $user->id, 'status' => [ 0, 1 ], "formatedData" => $user->timeZone, 'limit' => $limit, 'offset' => $offset ] );
		$totalData = $this->Ticket_Model->get( [ 'userId' => $user->id, 'status' => [ 0, 1 ] ], false, true );

		if ( ! empty( $titcketData ) ) {
			foreach ( $titcketData as $value ) {
				$value->lastReplay	 = "";
				$value->lastMsgTime	 = "";
				$lastreplay			 = $this->Ticket_Model->getTicketReply( [ 'ticketId' => $value->id, 'status' => 1 ], true );
				if ( ! empty( $lastreplay ) ) {
					if ( $lastreplay->replyType != 1 ) {
						$value->lastReplay = "Image";
					} else {
						$value->lastReplay = $lastreplay->description;
					}
					$value->lastMsgTime = $this->Common_Model->get_time_ago( $lastreplay->createdDate );
				}				
				$value->statusText = "Closed";
				if($value->status == 1) {
					$value->statusText = "Active";
				}
			}
			$this->apiResponse['status']	 = "1";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "ticketDataGetSuccess", $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
			$this->apiResponse['data']		 = $titcketData;
		} else {
			$this->apiResponse['status']	 = "6";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( ($offset > 0 ? 'allcatchedUp' : "ticketDataGetFail" ), $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
		}

		$this->response( $this->apiResponse, REST_Controller::HTTP_OK );
	}

	public function getTicketDetail_post() {
		$user	 = $this->checkUserRequest();
		$apiData = json_decode( file_get_contents( 'php://input' ), TRUE );

		if ( ! isset( $apiData['data']['ticketId'] ) || empty( $apiData['data']['ticketId'] ) ) {
			$this->apiResponse['message'] = $this->Common_Model->GetNotification( "ticketIdRequired", $apiData['data']['langType'] );
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		}

		$titcketData = $this->Ticket_Model->get( [ 'userId' => $user->id, 'id' => $apiData['data']['ticketId'], "formatedData" => $user->timeZone, 'status' => [ 0, 1 ] ], TRUE );
		if ( empty( $titcketData ) ) {
			$this->apiResponse['status']	 = "6";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "ticketNotFound", $apiData['data']['langType'] );
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		}

		$this->apiResponse['status']	 = "1";
		$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "ticketDataGetSuccess", $apiData['data']['langType'] );
		$this->apiResponse['data']		 = $titcketData;

		$this->response( $this->apiResponse, REST_Controller::HTTP_OK );
	}

	public function reopenTicket_post() {
		$user	 = $this->checkUserRequest();
		$apiData = json_decode( file_get_contents( 'php://input' ), TRUE );

		if ( ! isset( $apiData['data']['ticketId'] ) || empty( $apiData['data']['ticketId'] ) ) {
			$this->apiResponse['message'] = $this->Common_Model->GetNotification( "ticketIdRequired", $apiData['data']['langType'] );
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		}

		$titcketData = $this->Ticket_Model->get( [ 'userId' => $user->id, 'id' => $apiData['data']['ticketId'], 'status' => 0 ], TRUE );
		if ( empty( $titcketData ) ) {
			$this->apiResponse['status']	 = "6";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "ticketNotFound", $apiData['data']['langType'] );
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		}

		$titcketId = $this->Ticket_Model->setData( [ 'status' => 1, 'reopenDate' => time() ], $apiData['data']['ticketId'] );

		if ( ! empty( $titcketId ) ) {
			$titcketData = $this->Ticket_Model->get( [ 'userId' => $user->id, 'id' => $apiData['data']['ticketId'], 'status' => [ 0, 1 ] ], TRUE );

			$this->apiResponse['status']	 = "1";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "ticketReopenSuccess", $apiData['data']['langType'] );
			$this->apiResponse['data']		 = $titcketData;
		} else {
			$this->apiResponse['status']	 = "0";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "ticketReopenFail", $apiData['data']['langType'] );
		}
		$this->response( $this->apiResponse, REST_Controller::HTTP_OK );
	}

	public function getLanguagesList_post() {
		$user	 = $this->checkUserRequest();
		$apiData = json_decode( file_get_contents( 'php://input' ), TRUE );

		$page_number = (isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : 1;
		$limit		 = (isset( $apiData['data']['limit'] ) && $apiData['data']['limit'] != '') ? $apiData['data']['limit'] : 10;
		if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] == 1 ) {
			$offset = 0;
		} else {
			if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '1' ) {
				$offset = ($page_number * $limit) - $limit;
			} else {
				$offset = 0;
			}
		}

		$search		 = (isset( $apiData['data']['search'] ) ? $apiData['data']['search'] : "");
		$getData	 = $this->Languages->get( [ 'apiResponse' => true, 'search' => $search, 'status' => '1', 'limit' => $limit, 'offset' => $offset ] );
		$totalData	 = $this->Languages->get( [ 'search' => $search, 'status' => '1' ], false, true );

		if ( ! empty( $getData ) ) {
			$this->apiResponse['status']	 = "1";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "getLanguageSuccess", $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
			$this->apiResponse['data']		 = $getData;
		} else {
			$this->apiResponse['status']	 = "6";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( ($offset > 0 ? 'allcatchedUp' : "languageNotFound" ), $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
		}

		$this->response( $this->apiResponse, REST_Controller::HTTP_OK );
	}

	// Country list
	public function getCountryList_post() {
		$user		 = $this->checkUserRequest();
		$apiData	 = json_decode( file_get_contents( 'php://input' ), TRUE );
		$page_number = (isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : 1;
		$limit		 = (isset( $apiData['data']['limit'] ) && $apiData['data']['limit'] != '') ? $apiData['data']['limit'] : 10;
		if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] == 1 ) {
			$offset = 0;
		} else {
			if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '1' ) {
				$offset = ($page_number * $limit) - $limit;
			} else {
				$offset = 0;
			}
		}

		$getDataPara['search']	 = isset( $apiData['data']['search'] ) ? $apiData['data']['search'] : "";
		$getDataPara['list']	 = TRUE;
		$getDataPara['limit']	 = $limit;
		$getDataPara['offset']	 = $offset;
		$getDataPara['status']	 = "1";

		$getData	 = $this->Country_Model->get( $getDataPara );
		$totalData	 = $this->Country_Model->get( $getDataPara, false, true );

		if ( ! empty( $getData ) ) {
			$this->apiResponse['status']	 = "1";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "getCountrySuccess", $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
			$this->apiResponse['data']		 = $getData;
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		} else {
			$this->apiResponse['status']	 = "6";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "countryNotFound", $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
			$this->apiResponse['data']		 = [];
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		}
	}

	// State list
	public function getStateList_post() {
		$user		 = $this->checkGuestUserRequest();
		$apiData	 = json_decode( file_get_contents( 'php://input' ), TRUE );
		$page_number = (isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : 1;
		$limit		 = (isset( $apiData['data']['limit'] ) && $apiData['data']['limit'] != '') ? $apiData['data']['limit'] : 10;
		if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] == 1 ) {
			$offset = 0;
		} else {
			if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '1' ) {
				$offset = ($page_number * $limit) - $limit;
			} else {
				$offset = 0;
			}
		}
		$limit						 = 150;
		$getDataPara['search']		 = isset( $apiData['data']['search'] ) ? $apiData['data']['search'] : "";
		$getDataPara['countryId']	 = isset( $apiData['data']['countryId'] ) && ! empty( $apiData['data']['countryId'] ) ? $apiData['data']['countryId'] : 209;
		$getDataPara['list']		 = TRUE;
		$getDataPara['limit']		 = $limit;
		$getDataPara['offset']		 = $offset;
		$getDataPara['status']		 = "1";

		$getData	 = $this->State_Model->get( $getDataPara );
		$totalData	 = $this->State_Model->get( $getDataPara, false, true );

		if ( ! empty( $getData ) ) {
			$this->apiResponse['status']	 = "1";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "getStateSuccess", $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
			$this->apiResponse['data']		 = $getData;
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		} else {
			$this->apiResponse['status']	 = "6";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "stateNotFound", $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
			$this->apiResponse['data']		 = [];
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		}
	}

	// City list
	public function getCityList_post() {
		$user		 = $this->checkGuestUserRequest();
		$apiData	 = json_decode( file_get_contents( 'php://input' ), TRUE );
		$page_number = (isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : 1;
		$limit		 = (isset( $apiData['data']['limit'] ) && $apiData['data']['limit'] != '') ? $apiData['data']['limit'] : 10;
		if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] == 1 ) {
			$offset = 0;
		} else {
			if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '1' ) {
				$offset = ($page_number * $limit) - $limit;
			} else {
				$offset = 0;
			}
		}
		$limit						 = 20000;
		$getDataPara['search']		 = isset( $apiData['data']['search'] ) ? $apiData['data']['search'] : "";
		$getDataPara['countryId']	 = isset( $apiData['data']['countryId'] ) ? $apiData['data']['countryId'] : "";
		$getDataPara['stateId']		 = isset( $apiData['data']['stateId'] ) ? $apiData['data']['stateId'] : "";
		$getDataPara['list']		 = TRUE;
		$getDataPara['limit']		 = $limit;
		$getDataPara['offset']		 = $offset;
		$getDataPara['status']		 = "1";

		$getData	 = $this->City_Model->get( $getDataPara );
		$totalData	 = $this->City_Model->get( $getDataPara, false, true );

		if ( ! empty( $getData ) ) {
			$this->apiResponse['status']	 = "1";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "getCitySuccess", $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
			$this->apiResponse['data']		 = $getData;
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		} else {
			$this->apiResponse['status']	 = "6";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "cityNotFound", $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
			$this->apiResponse['data']		 = [];
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		}
	}

	public function getProfessionList_post() {
		$user	 = $this->checkUserRequest();
		$apiData = json_decode( file_get_contents( 'php://input' ), TRUE );

		$page_number = (isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : 1;
		$limit		 = (isset( $apiData['data']['limit'] ) && $apiData['data']['limit'] != '') ? $apiData['data']['limit'] : 10;
		if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] == 1 ) {
			$offset = 0;
		} else {
			if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '1' ) {
				$offset = ($page_number * $limit) - $limit;
			} else {
				$offset = 0;
			}
		}

		$search		 = (isset( $apiData['data']['search'] ) ? $apiData['data']['search'] : "");
		$getData	 = $this->Profession->get( [ 'countProfessionals' => true, 'apiResponse' => true, 'search' => $search, 'status' => '1', 'limit' => $limit, 'offset' => $offset ] );
		$totalData	 = $this->Profession->get( [ 'search' => $search, 'status' => '1' ], false, true );

		if ( ! empty( $getData ) ) {
			$this->apiResponse['status']	 = "1";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "getProfessionSuccess", $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
			$this->apiResponse['data']		 = $getData;
		} else {
			$this->apiResponse['status']	 = "6";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( ($offset > 0 ? 'allcatchedUp' : "professionNotFound" ), $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
		}

		$this->response( $this->apiResponse, REST_Controller::HTTP_OK );
	}

	public function getMedicationsList_post() {
		$user	 = $this->checkUserRequest();
		$apiData = json_decode( file_get_contents( 'php://input' ), TRUE );

		$page_number = (isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : 1;
		$limit		 = (isset( $apiData['data']['limit'] ) && $apiData['data']['limit'] != '') ? $apiData['data']['limit'] : 10;
		if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] == 1 ) {
			$offset = 0;
		} else {
			if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '1' ) {
				$offset = ($page_number * $limit) - $limit;
			} else {
				$offset = 0;
			}
		}

		$search		 = (isset( $apiData['data']['search'] ) ? $apiData['data']['search'] : "");
		$getData	 = $this->Medications->get( [ 'apiResponse' => true, 'search' => $search, 'status' => '1', 'limit' => $limit, 'offset' => $offset ] );
		$totalData	 = $this->Medications->get( [ 'search' => $search, 'status' => '1' ], false, true );

		if ( ! empty( $getData ) ) {
			$this->apiResponse['status']	 = "1";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "getMedicationsSuccess", $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
			$this->apiResponse['data']		 = $getData;
		} else {
			$this->apiResponse['status']	 = "6";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( ($offset > 0 ? 'allcatchedUp' : "medicationsNotFound" ), $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
		}

		$this->response( $this->apiResponse, REST_Controller::HTTP_OK );
	}

	public function getDosageList_post() {
		$user	 = $this->checkUserRequest();
		$apiData = json_decode( file_get_contents( 'php://input' ), TRUE );

		$page_number = (isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : 1;
		$limit		 = (isset( $apiData['data']['limit'] ) && $apiData['data']['limit'] != '') ? $apiData['data']['limit'] : 10;
		if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] == 1 ) {
			$offset = 0;
		} else {
			if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '1' ) {
				$offset = ($page_number * $limit) - $limit;
			} else {
				$offset = 0;
			}
		}

		$search		 = (isset( $apiData['data']['search'] ) ? $apiData['data']['search'] : "");
		$getData	 = $this->Dosage->get( [ 'apiResponse' => true, 'search' => $search, 'status' => '1', 'limit' => $limit, 'offset' => $offset ] );
		$totalData	 = $this->Dosage->get( [ 'search' => $search, 'status' => '1' ], false, true );

		if ( ! empty( $getData ) ) {
			$this->apiResponse['status']	 = "1";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "getDosageSuccess", $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
			$this->apiResponse['data']		 = $getData;
		} else {
			$this->apiResponse['status']	 = "6";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( ($offset > 0 ? 'allcatchedUp' : "dosageNotFound" ), $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
		}

		$this->response( $this->apiResponse, REST_Controller::HTTP_OK );
	}

	public function getFrequencyList_post() {
		$user	 = $this->checkUserRequest();
		$apiData = json_decode( file_get_contents( 'php://input' ), TRUE );

		$page_number = (isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : 1;
		$limit		 = (isset( $apiData['data']['limit'] ) && $apiData['data']['limit'] != '') ? $apiData['data']['limit'] : 10;
		if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] == 1 ) {
			$offset = 0;
		} else {
			if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '1' ) {
				$offset = ($page_number * $limit) - $limit;
			} else {
				$offset = 0;
			}
		}

		$search		 = (isset( $apiData['data']['search'] ) ? $apiData['data']['search'] : "");
		$getData	 = $this->Frequency->get( [ 'apiResponse' => true, 'search' => $search, 'status' => '1', 'limit' => $limit, 'offset' => $offset ] );
		$totalData	 = $this->Frequency->get( [ 'search' => $search, 'status' => '1' ], false, true );

		if ( ! empty( $getData ) ) {
			$this->apiResponse['status']	 = "1";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "getFrequencySuccess", $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
			$this->apiResponse['data']		 = $getData;
		} else {
			$this->apiResponse['status']	 = "6";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( ($offset > 0 ? 'allcatchedUp' : "frequencyNotFound" ), $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
		}

		$this->response( $this->apiResponse, REST_Controller::HTTP_OK );
	}

	public function getAllergiesTypeList_post() {
		$user	 = $this->checkUserRequest();
		$apiData = json_decode( file_get_contents( 'php://input' ), TRUE );

		$page_number = (isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : 1;
		$limit		 = (isset( $apiData['data']['limit'] ) && $apiData['data']['limit'] != '') ? $apiData['data']['limit'] : 10;
		if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] == 1 ) {
			$offset = 0;
		} else {
			if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '1' ) {
				$offset = ($page_number * $limit) - $limit;
			} else {
				$offset = 0;
			}
		}

		$search		 = (isset( $apiData['data']['search'] ) ? $apiData['data']['search'] : "");
		$getData	 = $this->Allergies_Type->get( [ 'apiResponse' => true, 'search' => $search, 'status' => '1', 'limit' => $limit, 'offset' => $offset ] );
		$totalData	 = $this->Allergies_Type->get( [ 'search' => $search, 'status' => '1' ], false, true );

		if ( ! empty( $getData ) ) {
			$this->apiResponse['status']	 = "1";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "getAllergiesTypeSuccess", $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
			$this->apiResponse['data']		 = $getData;
		} else {
			$this->apiResponse['status']	 = "6";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( ($offset > 0 ? 'allcatchedUp' : "allergiesTypeNotFound" ), $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
		}

		$this->response( $this->apiResponse, REST_Controller::HTTP_OK );
	}

	public function getHealthIssuesList_post() {
		$user	 = $this->checkUserRequest();
		$apiData = json_decode( file_get_contents( 'php://input' ), TRUE );

		$page_number = (isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : 1;
		$limit		 = (isset( $apiData['data']['limit'] ) && $apiData['data']['limit'] != '') ? $apiData['data']['limit'] : 10;
		if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] == 1 ) {
			$offset = 0;
		} else {
			if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '1' ) {
				$offset = ($page_number * $limit) - $limit;
			} else {
				$offset = 0;
			}
		}

		$search		 = (isset( $apiData['data']['search'] ) ? $apiData['data']['search'] : "");
		$getData	 = $this->Health_Issues->get( [ 'apiResponse' => true, 'search' => $search, 'status' => '1', 'limit' => $limit, 'offset' => $offset ] );
		$totalData	 = $this->Health_Issues->get( [ 'search' => $search, 'status' => '1' ], false, true );

		if ( ! empty( $getData ) ) {
			$this->apiResponse['status']	 = "1";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "getHealthIssuesSuccess", $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
			$this->apiResponse['data']		 = $getData;
		} else {
			$this->apiResponse['status']	 = "6";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( ($offset > 0 ? 'allcatchedUp' : "healthIssuesNotFound" ), $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
		}

		$this->response( $this->apiResponse, REST_Controller::HTTP_OK );
	}

	public function getInjuriesList_post() {
		$user	 = $this->checkUserRequest();
		$apiData = json_decode( file_get_contents( 'php://input' ), TRUE );

		$page_number = (isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : 1;
		$limit		 = (isset( $apiData['data']['limit'] ) && $apiData['data']['limit'] != '') ? $apiData['data']['limit'] : 10;
		if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] == 1 ) {
			$offset = 0;
		} else {
			if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '1' ) {
				$offset = ($page_number * $limit) - $limit;
			} else {
				$offset = 0;
			}
		}

		$search		 = (isset( $apiData['data']['search'] ) ? $apiData['data']['search'] : "");
		$getData	 = $this->Injuries->get( [ 'apiResponse' => true, 'search' => $search, 'status' => '1', 'limit' => $limit, 'offset' => $offset ] );
		$totalData	 = $this->Injuries->get( [ 'search' => $search, 'status' => '1' ], false, true );

		if ( ! empty( $getData ) ) {
			$this->apiResponse['status']	 = "1";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "getInjuriesSuccess", $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
			$this->apiResponse['data']		 = $getData;
		} else {
			$this->apiResponse['status']	 = "6";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( ($offset > 0 ? 'allcatchedUp' : "injuriesNotFound" ), $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
		}

		$this->response( $this->apiResponse, REST_Controller::HTTP_OK );
	}

	public function getSpecialtiesList_post() {
		$user	 = $this->checkUserRequest();
		$apiData = json_decode( file_get_contents( 'php://input' ), TRUE );

		$page_number = (isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : 1;
		$limit		 = (isset( $apiData['data']['limit'] ) && $apiData['data']['limit'] != '') ? $apiData['data']['limit'] : 10;
		if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] == 1 ) {
			$offset = 0;
		} else {
			if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '1' ) {
				$offset = ($page_number * $limit) - $limit;
			} else {
				$offset = 0;
			}
		}

		$search		 = (isset( $apiData['data']['search'] ) ? $apiData['data']['search'] : "");
		$getData	 = $this->Specialties->get( [ 'apiResponse' => true, 'search' => $search, 'status' => '1', 'limit' => $limit, 'offset' => $offset ] );
		$totalData	 = $this->Specialties->get( [ 'search' => $search, 'status' => '1' ], false, true );

		if ( ! empty( $getData ) ) {
			$this->apiResponse['status']	 = "1";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "getSpecialtiesSuccess", $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
			$this->apiResponse['data']		 = $getData;
		} else {
			$this->apiResponse['status']	 = "6";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( ($offset > 0 ? 'allcatchedUp' : "SpecialtiesNotFound" ), $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
		}

		$this->response( $this->apiResponse, REST_Controller::HTTP_OK );
	}

	public function getSurgeriesList_post() {
		$user	 = $this->checkUserRequest();
		$apiData = json_decode( file_get_contents( 'php://input' ), TRUE );

		$page_number = (isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : 1;
		$limit		 = (isset( $apiData['data']['limit'] ) && $apiData['data']['limit'] != '') ? $apiData['data']['limit'] : 10;
		if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] == 1 ) {
			$offset = 0;
		} else {
			if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '1' ) {
				$offset = ($page_number * $limit) - $limit;
			} else {
				$offset = 0;
			}
		}

		$search		 = (isset( $apiData['data']['search'] ) ? $apiData['data']['search'] : "");
		$getData	 = $this->Surgeries->get( [ 'apiResponse' => true, 'search' => $search, 'status' => '1', 'limit' => $limit, 'offset' => $offset ] );
		$totalData	 = $this->Surgeries->get( [ 'search' => $search, 'status' => '1' ], false, true );

		if ( ! empty( $getData ) ) {
			$this->apiResponse['status']	 = "1";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "getSurgeriesSuccess", $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
			$this->apiResponse['data']		 = $getData;
		} else {
			$this->apiResponse['status']	 = "6";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( ($offset > 0 ? 'allcatchedUp' : "surgeriesNotFound" ), $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
		}

		$this->response( $this->apiResponse, REST_Controller::HTTP_OK );
	}

	public function getIllnessList_post() {
		$user	 = $this->checkUserRequest();
		$apiData = json_decode( file_get_contents( 'php://input' ), TRUE );

		$page_number = (isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : 1;
		$limit		 = (isset( $apiData['data']['limit'] ) && $apiData['data']['limit'] != '') ? $apiData['data']['limit'] : 10;
		if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] == 1 ) {
			$offset = 0;
		} else {
			if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '1' ) {
				$offset = ($page_number * $limit) - $limit;
			} else {
				$offset = 0;
			}
		}

		$search		 = (isset( $apiData['data']['search'] ) ? $apiData['data']['search'] : "");
		$getData	 = $this->Illness->get( [ 'apiResponse' => true, 'search' => $search, 'status' => '1', 'limit' => $limit, 'offset' => $offset ] );
		$totalData	 = $this->Illness->get( [ 'search' => $search, 'status' => '1' ], false, true );

		if ( ! empty( $getData ) ) {
			$this->apiResponse['status']	 = "1";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "getIllnessSuccess", $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
			$this->apiResponse['data']		 = $getData;
		} else {
			$this->apiResponse['status']	 = "6";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( ($offset > 0 ? 'allcatchedUp' : "illnessNotFound" ), $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
		}

		$this->response( $this->apiResponse, REST_Controller::HTTP_OK );
	}

	public function getGoalList_post() {
		$user	 = $this->checkUserRequest();
		$apiData = json_decode( file_get_contents( 'php://input' ), TRUE );

		$page_number = (isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : 1;
		$limit		 = (isset( $apiData['data']['limit'] ) && $apiData['data']['limit'] != '') ? $apiData['data']['limit'] : 10;
		if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] == 1 ) {
			$offset = 0;
		} else {
			if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '1' ) {
				$offset = ($page_number * $limit) - $limit;
			} else {
				$offset = 0;
			}
		}

		$search		 = (isset( $apiData['data']['search'] ) ? $apiData['data']['search'] : "");
		$getData	 = $this->Goal->get( [ 'apiResponse' => true, 'search' => $search, 'status' => '1', 'limit' => $limit, 'offset' => $offset ] );
		$totalData	 = $this->Goal->get( [ 'search' => $search, 'status' => '1' ], false, true );

		if ( ! empty( $getData ) ) {
			$this->apiResponse['status']	 = "1";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "getGoalSuccess", $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
			$this->apiResponse['data']		 = $getData;
		} else {
			$this->apiResponse['status']	 = "6";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( ($offset > 0 ? 'allcatchedUp' : "goalNotFound" ), $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
		}

		$this->response( $this->apiResponse, REST_Controller::HTTP_OK );
	}

	public function getComplicationsList_post() {
		$user	 = $this->checkUserRequest();
		$apiData = json_decode( file_get_contents( 'php://input' ), TRUE );

		$page_number = (isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : 1;
		$limit		 = (isset( $apiData['data']['limit'] ) && $apiData['data']['limit'] != '') ? $apiData['data']['limit'] : 10;
		if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] == 1 ) {
			$offset = 0;
		} else {
			if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '1' ) {
				$offset = ($page_number * $limit) - $limit;
			} else {
				$offset = 0;
			}
		}

		$search		 = (isset( $apiData['data']['search'] ) ? $apiData['data']['search'] : "");
		$getData	 = $this->Complications->get( [ 'apiResponse' => true, 'search' => $search, 'status' => '1', 'limit' => $limit, 'offset' => $offset ] );
		$totalData	 = $this->Complications->get( [ 'search' => $search, 'status' => '1' ], false, true );

		if ( ! empty( $getData ) ) {
			$this->apiResponse['status']	 = "1";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "getComplicationSuccess", $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
			$this->apiResponse['data']		 = $getData;
		} else {
			$this->apiResponse['status']	 = "6";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( ($offset > 0 ? 'allcatchedUp' : "complicationNotFound" ), $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
		}

		$this->response( $this->apiResponse, REST_Controller::HTTP_OK );
	}

	public function setVoipToken_post() {
		$user	 = $this->checkUserRequest();
		$apiData = json_decode( file_get_contents( 'php://input' ), TRUE );
		if ( ! isset( $apiData['data']['voipToken'] ) || empty( $apiData['data']['voipToken'] ) ) {
			$this->apiResponse['message'] = $this->Common_Model->GetNotification( "voipTokenRequired", $apiData['data']['langType'] );
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		}

		$getAuth = $this->Auth_Model->get( [
			'token' => $apiData['data']['token'],
			'userId' => $user->id,
			'deviceId' => isset( $apiData['data']['deviceId'] ) && !empty($apiData['data']['deviceId']) ? $apiData['data']['deviceId'] : null,
		 ], TRUE );
		$setData = "";
		if ( ! empty( $getAuth ) ) {
			$setData = $this->Auth_Model->setData( [ 'voipToken' => $apiData['data']['voipToken'] ], $getAuth->id );
		}

		if ( ! empty( $setData ) ) {
			$this->apiResponse['status']	 = "1";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "voipTokenSaveSuccess", $apiData['data']['langType'] );
		} else {
			$this->apiResponse['status']	 = "0";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "failToSaveVoipToken", $apiData['data']['langType'] );
		}
		return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
	}

	public function generateAccessToken_post() {
		$user	 = $this->checkUserRequest();
		$apiData = json_decode( file_get_contents( 'php://input' ), TRUE );

		if ( ! isset( $apiData['data']['appointmentId'] ) || empty( $apiData['data']['appointmentId'] ) ) {
			$this->apiResponse['message'] = $this->Common_Model->GetNotification( "appointmentIdRequired", $apiData['data']['langType'] );
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		}

		$appointmentData = $this->User_Appointment->get( [ 'id' => $apiData['data']['appointmentId'], 'status' => [ 1, 3 ] ], true );
		if ( empty( $appointmentData ) ) {
			$this->apiResponse['message'] = $this->Common_Model->GetNotification( "appointmentDataNotFound", $apiData['data']['langType'] );
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		}
		//print_r($appointmentData); die;
		$room_name = "chat_room" . $apiData['data']['appointmentId'];

		$finalData['room_name']		 = $room_name;
		$senderData					 = array();
		$this->load->library( 'twillio_lib' );
		$senderData['access_token']	 = $this->twillio_lib->generateAccessToken( $user->email, $room_name );
		$senderData['image']		 = base_url( getenv( 'UPLOAD_URL' ) ) . $user->image;
		$senderData['name']			 = $user->name;
		$senderData['id']			 = $user->id;
		$finalData['senderData']	 = $senderData;

		$finalData['receiverData']	 = array();
		$receiverUserId				 = ($user->id == $appointmentData->userId ? $appointmentData->doctorId : $appointmentData->userId);
		$getUserData				 = $this->User->get( [ 'id' => $receiverUserId, 'status' => 1 ], TRUE );
		if ( ! empty( $getUserData ) ) {
			$receiverData					 = array();
			$receiverData['access_token']	 = $this->twillio_lib->generateAccessToken( $getUserData->email, $room_name );
			$receiverData['image']			 = $getUserData->profileimage;
			$receiverData['name']			 = $getUserData->name;
			$receiverData['id']				 = $getUserData->id;
			$finalData['receiverData']		 = $receiverData;
		}

		if ( ! empty( $finalData['senderData'] ) && ! empty( $finalData['receiverData'] ) ) {
			$this->Background_Model->sendVideoCallNotification( $receiverData, $finalData );
			$this->apiResponse['status']	 = "1";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "createTwilioAccessTokenSuccess", $apiData['data']['langType'] );
			$this->apiResponse['data']		 = $finalData;
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		} else {
			$this->apiResponse['status']	 = "0";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "failToCreateTwilioAccessToken", $apiData['data']['langType'] );
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		}
	}

	public function setBodypart_post() {
		$user	 = $this->checkUserRequest();
		$apiData = json_decode( file_get_contents( 'php://input' ), TRUE );

		if ( ! isset( $apiData['data']['userAppointmentId'] ) || empty( $apiData['data']['userAppointmentId'] ) ) {
			$this->apiResponse['message'] = $this->Common_Model->GetNotification( "userAppointmentIdRequired", $apiData['data']['langType'] );
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		}

		$appoinmentExist = $this->User_Appointment->get( [
			'id'	 => $apiData['data']['userAppointmentId'],
			'status' => [ 1, 2, 3 ],
			], true );

		if ( empty( $appoinmentExist ) ) {
			$this->apiResponse['message'] = $this->Common_Model->GetNotification( "userAppointmentIdInvalid", $apiData['data']['langType'] );
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		}

		$this->HumanBodyParts_Model->setData( [
			'userIds'			 => $user->id,
			'userAppointmentIds' => $apiData['data']['userAppointmentId'],
			'status'			 => 2,
			'updatedDate'		 => time(),
		] );

		if ( isset( $apiData['data']['frontParts'] ) && ! empty( $apiData['data']['frontParts'] ) ) {
			foreach ( $apiData['data']['frontParts'] as $parts ) {
				$this->HumanBodyParts_Model->setData( [
					'userAppointmentId'	 => $apiData['data']['userAppointmentId'],
					'userId'			 => $user->id,
					'frontBack'			 => 1, //1::front 2:back side
					'bodyParts'			 => $parts,
					'bodyImage'			 => (isset( $apiData['data']['frontImage'] ) || ! empty( $apiData['data']['frontImage'] )) ? $apiData['data']['frontImage'] : null,
				] );
			}
		}

		if ( isset( $apiData['data']['backParts'] ) && ! empty( $apiData['data']['backParts'] ) ) {
			foreach ( $apiData['data']['backParts'] as $parts ) {
				$this->HumanBodyParts_Model->setData( [
					'userAppointmentId'	 => $apiData['data']['userAppointmentId'],
					'userId'			 => $user->id,
					'frontBack'			 => 2, //1::front 2:back side
					'bodyParts'			 => $parts,
					'bodyImage'			 => (isset( $apiData['data']['backImage'] ) || ! empty( $apiData['data']['backImage'] )) ? $apiData['data']['backImage'] : null,
				] );
			}
		}

		$this->apiResponse['status']	 = "1";
		$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "saved", $apiData['data']['langType'] );

		return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
	}

	public function getBodypart_post() {
		$user	 = $this->checkUserRequest();
		$apiData = json_decode( file_get_contents( 'php://input' ), TRUE );

		if ( ! isset( $apiData['data']['userAppointmentId'] ) || empty( $apiData['data']['userAppointmentId'] ) ) {
			$this->apiResponse['message'] = $this->Common_Model->GetNotification( "userAppointmentIdRequired", $apiData['data']['langType'] );
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		}

		$appoinmentExist = $this->User_Appointment->get( [
			'id'	 => $apiData['data']['userAppointmentId'],
			'status' => [ 1, 2, 3 ],
			], true );

		if ( empty( $appoinmentExist ) ) {
			$this->apiResponse['message'] = $this->Common_Model->GetNotification( "userAppointmentIdInvalid", $apiData['data']['langType'] );
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		}

		$allData = $this->HumanBodyParts_Model->get( [
			'userAppointmentId'	 => $apiData['data']['userAppointmentId'],
			'status'			 => 1,
		] );
		$result	 = [];
		foreach ( $allData as $k => $value ) {
			$uaid								 = $value->userAppointmentId;
			$result[$uaid]['userAppointmentId']	 = $value->userAppointmentId;

			if ( $value->frontBack == 1 ) {
				$result[$uaid]['frontParts'][]		 = $value->bodyParts;
				$result[$uaid]['frontImage']		 = $value->bodyImage;
				$result[$uaid]['thumbFrontImage']	 = $value->thumbBodyImage;
			} else {
				$result[$uaid]['backParts'][]	 = $value->bodyParts;
				$result[$uaid]['backImage']		 = $value->bodyImage;
				$result[$uaid]['thumbBackImage'] = $value->thumbBodyImage;
			}
		}
		if ( isset( array_values( $result )[0] ) ) {
			$result = array_values( $result )[0];
		} else {
			$result = '';
		}
		if ( ! empty( $result ) ) {
			if ( ! isset( $result['frontParts'] ) ) {
				$result['frontParts'] = [];
			}
			if ( ! isset( $result['backParts'] ) ) {
				$result['backParts'] = [];
			}
			if ( ! isset( $result['frontImage'] ) ) {
				$result['frontImage']		 = '';
				$result['thumbFrontImage']	 = '';
			}
			if ( ! isset( $result['backImage'] ) ) {
				$result['backImage']		 = '';
				$result['thumbBackImage']	 = '';
			}
		}
		// print_r($result); die;
		$this->apiResponse['status']	 = "1";
		$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "get", $apiData['data']['langType'] );
		$this->apiResponse['data']		 = $result;

		return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
	}

	public function getnotificationlist_post() {
		$user		 = $this->checkUserRequest();
		$apiData	 = json_decode( file_get_contents( 'php://input' ), TRUE );
		$page_number = (isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '') ? $apiData['data']['page'] : '';
		$limit		 = (isset( $apiData['data']['limit'] ) && $apiData['data']['limit'] != '') ? $apiData['data']['limit'] : 10;
		if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] == 1 ) {
			$offset = 0;
		} else {
			if ( isset( $apiData['data']['page'] ) && $apiData['data']['page'] != '1' ) {
				$offset = ($page_number * $limit) - $limit;
			} else {
				$offset = 0;
			}
		}

		$getDataPara['send_to']	 = $user->id;
		$getDataPara['limit']	 = $limit;
		$getDataPara['offset']	 = $offset;
		$getData				 = $this->Notification_Model->get( $getDataPara );
		$totalData				 = $this->Notification_Model->get( $getDataPara, false, true );
		if ( ! empty( $getData ) ) {
			foreach ( $getData as $key => $value ) {
				$getData[$key]->time_ago = $this->Common_Model->get_time_ago( $value->createdDate );
				// if ( $value->status == 1 ) {
				// 	$this->Notification_Model->setData( [ 'status' => 0 ], $value->id );
				// }
			}
			$this->Notification_Model->setData( [ 'status' => 0, 'markSeenAll' => true, 'seenUserId'=>$user->id ]);
			$this->apiResponse['status']	 = "1";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "listSuccess", $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
			$this->apiResponse['data']		 = $getData;
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		} else {
			$this->apiResponse['status']	 = "6";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "nonotification", $apiData['data']['langType'] );
			$this->apiResponse['totalPages'] = ceil( $totalData / $limit ) . "";
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		}
	}

	public function getUnreadNotificationsCount_post() {
		$user	 = $this->checkUserRequest();
		$apiData = json_decode( file_get_contents( 'php://input' ), TRUE );

		$this->apiResponse['status']		 = "1";
		$this->apiResponse['unreadPushNoti'] = $this->Notification_Model->get( [ 'send_to' => $user->id, 'status' => 1 ], false, true );
		$this->apiResponse['unreadChatMsg']	 = $this->Chat_Model->getMessageStatus( [ 'userId' => $user->id, 'status' => 1 ], false, true );
		$this->apiResponse['message']		 = $this->Common_Model->GetNotification( "getUnreadNotificationCount", $apiData['data']['langType'] );
		return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
	}

	public function testNotification_post() {
		$this->checkGuestUserRequest();
		$apiData		 = json_decode( file_get_contents( 'php://input' ), TRUE );
		$notification	 = array(
			"title"	 => "THIS IS TITLE",
			"body"	 => "THIS IS NOTIFICATION BODY",
			"badge"	 => intval( 0 ),
			"sound"	 => "default"
		);
		if ( isset( $apiData['data']['title'] ) && ! empty( $apiData['data']['title'] ) ) {
			$notification["title"] = $apiData['data']['title'];
		}
		if ( isset( $apiData['data']['body'] ) && ! empty( $apiData['data']['body'] ) ) {
			$notification["body"] = $apiData['data']['body'];
		}
		if ( isset( $apiData['data']['deviceToken'] ) && ! empty( $apiData['data']['deviceToken'] ) ) {
			$extraData						 = array(
				"category"		 => "Test",
				"messageData"	 => array( 'testkey' => 'testdata' ),
				"unread"		 => (string) 0
			);
			$result							 = $this->Background_Model->pushNotification( $apiData['data']['deviceToken'], $notification, $extraData, 0 );
			$this->apiResponse['status']	 = "1";
			$this->apiResponse['message']	 = "Call success";
			$this->apiResponse['data']		 = json_decode( $result );
			$this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		}
		$this->apiResponse['status']	 = "0";
		$this->apiResponse['message']	 = "Fail to call";
		$this->response( $this->apiResponse, REST_Controller::HTTP_OK );
	}

	public function checkReferralCode_post() {
		$user	 = $this->checkGuestUserRequest();
		$apiData = json_decode( file_get_contents( 'php://input' ), TRUE );

		if ( ! isset( $apiData['data']['referralCode'] ) || empty( $apiData['data']['referralCode'] ) ) {
			$this->apiResponse['message'] = $this->Common_Model->GetNotification( "referralCodeRequired", $apiData['data']['langType'] );
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		}

		$getData	 = $existCode	 = $this->User->get( [ 'referralCode' => $apiData['data']['referralCode'], 'status' => [ 0, 1, 2, 4 ], 'role' => 3 ], TRUE );
		if ( ! empty( $getData ) ) {
			$this->apiResponse['status']	 = "1";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "referralCodeVerifySuccess", $apiData['data']['langType'] );
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		} else {
			$this->apiResponse['status']	 = "0";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "referralCodeInvalid", $apiData['data']['langType'] );
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		}
	}




	

	public function setProviderOnboardingRating_post() {
		$user = $this->checkUserRequest();
		$this->load->model( 'Provider_Onboarding_Rating_Model' );

		$apiData = json_decode( file_get_contents( 'php://input' ), TRUE );
		if ( ! isset( $apiData['data']['rating'] ) || empty( $apiData['data']['rating'] ) ) {
			$this->apiResponse['message'] = $this->Common_Model->GetNotification( "ratingStarRequired", $apiData['data']['langType'] );
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		}

		if ( ! isset( $apiData['data']['feedback'] ) || empty( $apiData['data']['feedback'] ) ) {
			$this->apiResponse['message'] = $this->Common_Model->GetNotification( "feedbackRequired", $apiData['data']['langType'] );
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		}

		$data = [];
		$data['userId'] = $user->id;
		$data['rating'] = $apiData['data']['rating'];
		$data['feedback'] = $apiData['data']['feedback'];

		$appFeedbackId = "";
		$appFeedbackData = $this->Provider_Onboarding_Rating_Model->get( [ 'userId' => $user->id ], true );
		if(!empty( $appFeedbackData)) {
			$data['status']	 = "1";
			$appFeedbackId = $this->Provider_Onboarding_Rating_Model->setData( $data, $appFeedbackData->id );
		} 
		else {
			$appFeedbackId = $this->Provider_Onboarding_Rating_Model->setData( $data );
		}

		if(!empty( $appFeedbackId)) {
			$set = $this->User->setData([ "onboardingRatingStatus" => 0 ], $user->id);
			$this->apiResponse['status']	 = "1";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "saveAppFeedbackSuccess", $apiData['data']['langType'] );
		}
		else {
			$this->apiResponse['status']	 = "0";
			$this->apiResponse['message']	 = $this->Common_Model->GetNotification( "failToSaveAppFeedback", $apiData['data']['langType'] );
		}
		return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
	}

	public function searchstatusupdate_post() {
		$user = $this->checkUserRequest();
		$apiData = json_decode( file_get_contents( 'php://input' ), TRUE );
		if(!isset($apiData['data']['searchstatus'])) {
			$this->apiResponse['message'] = $this->Common_Model->GetNotification( "searchStatusRequired", $apiData['data']['langType'] );
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		}
		if($user->role != 3) {
			$this->apiResponse['message'] = $this->Common_Model->GetNotification( "youAreNotADoctor", $apiData['data']['langType'] );
			return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
		}
		#secho "<pre>"; print_r($apiData['data']); exit;

		$userId = $this->User->setData( [ "ispresenceforsearch" => $apiData['data']['searchstatus'] ] , $user->id );
		if(!empty($userId)) {
			$this->apiResponse['status'] = "1";
			$this->apiResponse['message'] = $this->Common_Model->GetNotification( "successPresencesearchStatus", $apiData['data']['langType'] );
		}
		else {
			$this->apiResponse['status'] = "0";
			$this->apiResponse['message'] = $this->Common_Model->GetNotification( "failPresenceSearchStatus", $apiData['data']['langType'] );
		}
		return $this->response( $this->apiResponse, REST_Controller::HTTP_OK );
	}

	
    public function deletechatgroup_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if (!isset($apiData['data']['groupId']) || empty($apiData['data']['groupId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("groupIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $data = array('status' => [1,2], "groupId" => $apiData['data']['groupId'], "userId" => $user->id);
        $userGroup = $this->Chat_Model->getGroupMembers($data, true);
        if (empty($userGroup)) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("invalidGroupMember", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        $groupinfo=$this->Chat_Model->getGroups(['id'=>$apiData['data']['groupId'],'status'=>'1'],true);
        if (empty($groupinfo)) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("invalidGroup", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
		
		$saveId = $this->Chat_Model->setGroupMember(['status' => 0], $userGroup->id);
        if (empty($saveId)) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("chatFailDelete", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        $this->apiResponse['status'] = "1";
        $this->apiResponse['message'] = $this->Common_Model->GetNotification("chatSuccessDelete", $apiData['data']['langType']);
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);

	}

    public function archiveChatAddRemove_post() {
        $user = $this->checkUserRequest();
        $apiData = json_decode(file_get_contents('php://input'), TRUE);

        if (!isset($apiData['data']['groupId']) || empty($apiData['data']['groupId'])) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("groupIdRequired", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $data = array('status' => [1,2], "groupId" => $apiData['data']['groupId'], "userId" => $user->id);
        $userGroup = $this->Chat_Model->getGroupMembers($data, true);
        if (empty($userGroup)) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("invalidGroupMember", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }
        $groupinfo=$this->Chat_Model->getGroups(['id'=>$apiData['data']['groupId'],'status'=>'1'],true);
        if (empty($groupinfo)) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification("invalidGroup", $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $saveId = "";
        if($userGroup->status == '2') {
            $saveId = $this->Chat_Model->setGroupMember(['status' => 1], $userGroup->id);
            $successMsg = "removeArchiveSuccess";
            $failMsg = "failToRemoveArchive";
        }
		else {
            $saveId = $this->Chat_Model->setGroupMember(['status' => 2], $userGroup->id);
            $successMsg = "addArchiveSuccess";
            $failMsg = "failToAddArchive";
        }

        if (empty($saveId)) {
            $this->apiResponse['message'] = $this->Common_Model->GetNotification($failMsg, $apiData['data']['langType']);
            return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
        }

        $this->apiResponse['status'] = "1";
        $this->apiResponse['message'] = $this->Common_Model->GetNotification($successMsg, $apiData['data']['langType']);
        return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
    }

      public function getFilterDateSlotList_post() {
	$apiData = json_decode(file_get_contents('php://input'), TRUE);

	$dateFilterSlotArr = [];
	for ($i=0; $i < 7 ; $i++) {
	    $dateFilterSlotArr[] = [
	        "label" => $this->Common_Model->getDayAndDateName(strtotime("+".$i." days"), getenv('SYSTEMTIMEZON')),
	        "value" => date("d-m-Y", strtotime("+".$i." days"))
	    ];
	}

	$this->apiResponse['status'] = "1";
	$this->apiResponse['message'] = $this->Common_Model->GetNotification("getSuccessfullFilterDateSlot", $apiData['data']['langType']);
	$this->apiResponse['data'] = $dateFilterSlotArr;
	return $this->response($this->apiResponse, REST_Controller::HTTP_OK);
      }
  


}
