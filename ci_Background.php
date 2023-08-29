<?php

defined('BASEPATH') OR exit('No direct script access allowed');
/*
set_error_handler('exceptions_error_handler');

function exceptions_error_handler($severity, $message, $filename, $lineno) {
    $CI = &get_instance();
    $CI->load->model('SystemErrorLog_Model');
    $CI->SystemErrorLog_Model->addSystemErrorLog(['message' => $message, 'file' => $filename, 'line' => $lineno]);

    if (error_reporting() == 0) {
        return;
    }
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $filename, $lineno);
    }
}
*/
class Background extends CI_Controller {

    public function index() {
        
    }

    public function worker() {
        $CI = &get_instance();
        $CI->load->library('lib_gearman');
        $CI->load->model('Background_Model');
        $CI->load->model('Common_Model');

        $worker = $this->lib_gearman->gearman_worker();
        $this->lib_gearman->add_worker_function('scheduleAppointmentByUser', 'Background::scheduleAppointmentByUser');
        $this->lib_gearman->add_worker_function('scheduleServiceByUser', 'Background::scheduleServiceByUser');
        $this->lib_gearman->add_worker_function('scheduleAppointmentForUser', 'Background::scheduleAppointmentForUser');
        $this->lib_gearman->add_worker_function('scheduleServiceForUser', 'Background::scheduleServiceForUser');
        $this->lib_gearman->add_worker_function('addMoneyInYourWalletForScheduleAppointment', 'Background::addMoneyInYourWalletForScheduleAppointment');
        $this->lib_gearman->add_worker_function('transactionSuccessForScheduleAppointment', 'Background::transactionSuccessForScheduleAppointment');
        $this->lib_gearman->add_worker_function('transactionSuccessForScheduleService', 'Background::transactionSuccessForScheduleService');
        $this->lib_gearman->add_worker_function('rescheduleUserAppointmentByDoctor', 'Background::rescheduleUserAppointmentByDoctor');
        $this->lib_gearman->add_worker_function('rescheduleUserAppointmentAsDoctor', 'Background::rescheduleUserAppointmentAsDoctor');
        $this->lib_gearman->add_worker_function('sendMailAndSMSInAuthenticationCodeForUser', 'Background::sendMailAndSMSInAuthenticationCodeForUser');
        $this->lib_gearman->add_worker_function('sendMailAndSMSInServiceAuthenticationCodeForUser', 'Background::sendMailAndSMSInServiceAuthenticationCodeForUser');
        $this->lib_gearman->add_worker_function('cancelledUserAppointmentByAuto', 'Background::cancelledUserAppointmentByAuto');
        $this->lib_gearman->add_worker_function('cancelledDoctorAppointmentByAuto', 'Background::cancelledDoctorAppointmentByAuto');
        $this->lib_gearman->add_worker_function('addMoneyInYourWalletForCancelAppointment', 'Background::addMoneyInYourWalletForCancelAppointment');
        $this->lib_gearman->add_worker_function('withdrawWalletAmountRequest', 'Background::withdrawWalletAmountRequest');
        $this->lib_gearman->add_worker_function('withdrawWalletInstantAmountFees', 'Background::withdrawWalletInstantAmountFees');

        while ($this->lib_gearman->work()) {
            if (!$worker->returnCode()) {
                echo "\n----------- " . date('c') . " worker done successfully---------\n";
            }
            if ($worker->returnCode() != GEARMAN_SUCCESS) {

                echo "return_code: " . $this->lib_gearman->current('worker')->returnCode() . "\n";
                break;
            }
        }
    }

    public static function scheduleAppointmentByUser($job = null) {
        echo "\n---------- " . date('c') . "Start scheduleAppointmentByUser Notification -----------------\n";
        $data = unserialize($job->workload());
        $CI = &get_instance();

        try {
            if (empty($data)) {
                echo "\n Inavlida Data " . json_encode($data) . "  \n";
                return false;
            }
            $CI->db->reconnect();
            $CI->Background_Model->scheduleAppointmentByUser($data);
        } catch (Exception $e) {
            echo '\n error : ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
            //$CI->load->model('SystemErrorLog_Model');
            //$CI->SystemErrorLog_Model>addSystemErrorLog(['error' => json_encode($e, true)]);
        }

        echo "\n---------- " . date('c') . " End scheduleAppointmentByUser Notification -----------------\n";
    }

    public static function scheduleServiceByUser($job = null) {
        echo "\n---------- " . date('c') . "Start scheduleServiceByUser Notification -----------------\n";
        $data = unserialize($job->workload());
        $CI = &get_instance();

        try {
            if (empty($data)) {
                echo "\n Inavlida Data " . json_encode($data) . "  \n";
                return false;
            }
            $CI->db->reconnect();
            $CI->Background_Model->scheduleServiceByUser($data);
        } catch (Exception $e) {
            echo '\n error : ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
            //$CI->load->model('SystemErrorLog_Model');
            //$CI->SystemErrorLog_Model>addSystemErrorLog(['error' => json_encode($e, true)]);
        }

        echo "\n---------- " . date('c') . " End scheduleServiceByUser Notification -----------------\n";
    }

    public static function scheduleAppointmentForUser($job = null) {
        echo "\n---------- " . date('c') . "Start scheduleAppointmentForUser Notification -----------------\n";
        $data = unserialize($job->workload());
        $CI = &get_instance();

        try {
            if (empty($data)) {
                echo "\n Inavlida Data " . json_encode($data) . "  \n";
                return false;
            }
            $CI->db->reconnect();
            $CI->Background_Model->scheduleAppointmentForUser($data);
        } catch (Exception $e) {
            echo '\n error : ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
            //$CI->load->model('SystemErrorLog_Model');
            //$CI->SystemErrorLog_Model>addSystemErrorLog(['error' => json_encode($e, true)]);
        }

        echo "\n---------- " . date('c') . " End scheduleAppointmentForUser Notification -----------------\n";
    }

    public static function scheduleServiceForUser($job = null) {
        echo "\n---------- " . date('c') . "Start scheduleServiceForUser Notification -----------------\n";
        $data = unserialize($job->workload());
        $CI = &get_instance();

        try {
            if (empty($data)) {
                echo "\n Inavlida Data " . json_encode($data) . "  \n";
                return false;
            }
            $CI->db->reconnect();
            $CI->Background_Model->scheduleServiceForUser($data);
        } catch (Exception $e) {
            echo '\n error : ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
            //$CI->load->model('SystemErrorLog_Model');
            //$CI->SystemErrorLog_Model>addSystemErrorLog(['error' => json_encode($e, true)]);
        }

        echo "\n---------- " . date('c') . " End scheduleServiceForUser Notification -----------------\n";
    }

    public static function addMoneyInYourWalletForScheduleAppointment($job = null) {
        echo "\n---------- " . date('c') . "Start addMoneyInYourWalletForScheduleAppointment Notification -----------------\n";
        $data = unserialize($job->workload());
        $CI = &get_instance();

        try {
            if (empty($data)) {
                echo "\n Inavlida Data " . json_encode($data) . "  \n";
                return false;
            }
            $CI->db->reconnect();
            $CI->Background_Model->addMoneyInYourWalletForScheduleAppointment($data);
        } catch (Exception $e) {
            echo '\n error : ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
            //$CI->load->model('SystemErrorLog_Model');
            //$CI->SystemErrorLog_Model>addSystemErrorLog(['error' => json_encode($e, true)]);
        }

        echo "\n---------- " . date('c') . " End addMoneyInYourWalletForScheduleAppointment Notification -----------------\n";
    }

    public static function transactionSuccessForScheduleAppointment($job = null) {
        echo "\n---------- " . date('c') . "Start transactionSuccessForScheduleAppointment Notification -----------------\n";
        $data = unserialize($job->workload());
        $CI = &get_instance();

        try {
            if (empty($data)) {
                echo "\n Inavlida Data " . json_encode($data) . "  \n";
                return false;
            }
            $CI->db->reconnect();
            $CI->Background_Model->transactionSuccessForScheduleAppointment($data);
        } catch (Exception $e) {
            echo '\n error : ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
            //$CI->load->model('SystemErrorLog_Model');
            //$CI->SystemErrorLog_Model>addSystemErrorLog(['error' => json_encode($e, true)]);
        }

        echo "\n---------- " . date('c') . " End transactionSuccessForScheduleAppointment Notification -----------------\n";
    }

    public static function transactionSuccessForScheduleService($job = null) {
        echo "\n---------- " . date('c') . "Start transactionSuccessForScheduleService Notification -----------------\n";
        $data = unserialize($job->workload());
        $CI = &get_instance();

        try {
            if (empty($data)) {
                echo "\n Inavlida Data " . json_encode($data) . "  \n";
                return false;
            }
            $CI->db->reconnect();
            $CI->Background_Model->transactionSuccessForScheduleService($data);
        } catch (Exception $e) {
            echo '\n error : ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
            //$CI->load->model('SystemErrorLog_Model');
            //$CI->SystemErrorLog_Model>addSystemErrorLog(['error' => json_encode($e, true)]);
        }

        echo "\n---------- " . date('c') . " End transactionSuccessForScheduleService Notification -----------------\n";
    }

    public static function rescheduleUserAppointmentByDoctor($job = null) {
        echo "\n---------- " . date('c') . "Start rescheduleUserAppointmentByDoctor Notification -----------------\n";
        $data = unserialize($job->workload());
        $CI = &get_instance();

        try {
            if (empty($data)) {
                echo "\n Inavlida Data " . json_encode($data) . "  \n";
                return false;
            }
            $CI->db->reconnect();
            $CI->Background_Model->rescheduleUserAppointmentByDoctor($data);
        } catch (Exception $e) {
            echo '\n error : ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
            //$CI->load->model('SystemErrorLog_Model');
            //$CI->SystemErrorLog_Model>addSystemErrorLog(['error' => json_encode($e, true)]);
        }

        echo "\n---------- " . date('c') . " End rescheduleUserAppointmentByDoctor Notification -----------------\n";
    }

    public static function rescheduleUserAppointmentAsDoctor($job = null) {
        echo "\n---------- " . date('c') . "Start rescheduleUserAppointmentAsDoctor Notification -----------------\n";
        $data = unserialize($job->workload());
        $CI = &get_instance();

        try {
            if (empty($data)) {
                echo "\n Inavlida Data " . json_encode($data) . "  \n";
                return false;
            }
            $CI->db->reconnect();
            $CI->Background_Model->rescheduleUserAppointmentAsDoctor($data);
        } catch (Exception $e) {
            echo '\n error : ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
            //$CI->load->model('SystemErrorLog_Model');
            //$CI->SystemErrorLog_Model>addSystemErrorLog(['error' => json_encode($e, true)]);
        }

        echo "\n---------- " . date('c') . " End rescheduleUserAppointmentAsDoctor Notification -----------------\n";
    }

    public static function sendMailAndSMSInAuthenticationCodeForUser($job = null) {
        echo "\n---------- " . date('c') . "Start sendMailAndSMSInAuthenticationCodeForUser Notification -----------------\n";
        $data = unserialize($job->workload());
        $CI = &get_instance();

        try {
            if (empty($data)) {
                echo "\n Inavlida Data " . json_encode($data) . "  \n";
                return false;
            }
            $CI->db->reconnect();
            $CI->Background_Model->sendMailAndSMSInAuthenticationCodeForUser($data);
        } catch (Exception $e) {
            echo '\n error : ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
            //$CI->load->model('SystemErrorLog_Model');
            //$CI->SystemErrorLog_Model>addSystemErrorLog(['error' => json_encode($e, true)]);
        }

        echo "\n---------- " . date('c') . " End sendMailAndSMSInAuthenticationCodeForUser Notification -----------------\n";
    }

    public static function sendMailAndSMSInServiceAuthenticationCodeForUser($job = null) {
        echo "\n---------- " . date('c') . "Start sendMailAndSMSInServiceAuthenticationCodeForUser Notification -----------------\n";
        $data = unserialize($job->workload());
        $CI = &get_instance();

        try {
            if (empty($data)) {
                echo "\n Inavlida Data " . json_encode($data) . "  \n";
                return false;
            }
            $CI->db->reconnect();
            $CI->Background_Model->sendMailAndSMSInServiceAuthenticationCodeForUser($data);
        } catch (Exception $e) {
            echo '\n error : ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
            //$CI->load->model('SystemErrorLog_Model');
            //$CI->SystemErrorLog_Model>addSystemErrorLog(['error' => json_encode($e, true)]);
        }

        echo "\n---------- " . date('c') . " End sendMailAndSMSInServiceAuthenticationCodeForUser Notification -----------------\n";
    }

    public static function cancelledUserAppointmentByAuto($job = null) {
        echo "\n---------- " . date('c') . "Start cancelledUserAppointmentByAuto Notification -----------------\n";
        $data = unserialize($job->workload());
        $CI = &get_instance();

        try {
            if (empty($data)) {
                echo "\n Inavlida Data " . json_encode($data) . "  \n";
                return false;
            }
            $CI->db->reconnect();
            $CI->Background_Model->cancelledUserAppointmentByAuto($data);
        } catch (Exception $e) {
            echo '\n error : ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
            //$CI->load->model('SystemErrorLog_Model');
            //$CI->SystemErrorLog_Model>addSystemErrorLog(['error' => json_encode($e, true)]);
        }

        echo "\n---------- " . date('c') . " End cancelledUserAppointmentByAuto Notification -----------------\n";
    }

    public static function cancelledDoctorAppointmentByAuto($job = null) {
        echo "\n---------- " . date('c') . "Start cancelledDoctorAppointmentByAuto Notification -----------------\n";
        $data = unserialize($job->workload());
        $CI = &get_instance();

        try {
            if (empty($data)) {
                echo "\n Inavlida Data " . json_encode($data) . "  \n";
                return false;
            }
            $CI->db->reconnect();
            $CI->Background_Model->cancelledDoctorAppointmentByAuto($data);
        } catch (Exception $e) {
            echo '\n error : ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
            //$CI->load->model('SystemErrorLog_Model');
            //$CI->SystemErrorLog_Model>addSystemErrorLog(['error' => json_encode($e, true)]);
        }

        echo "\n---------- " . date('c') . " End cancelledDoctorAppointmentByAuto Notification -----------------\n";
    }

    public static function addMoneyInYourWalletForCancelAppointment($job = null) {
        echo "\n---------- " . date('c') . "Start addMoneyInYourWalletForCancelAppointment Notification -----------------\n";
        $data = unserialize($job->workload());
        $CI = &get_instance();

        try {
            if (empty($data)) {
                echo "\n Inavlida Data " . json_encode($data) . "  \n";
                return false;
            }
            $CI->db->reconnect();
            $CI->Background_Model->addMoneyInYourWalletForCancelAppointment($data);
        } catch (Exception $e) {
            echo '\n error : ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
            //$CI->load->model('SystemErrorLog_Model');
            //$CI->SystemErrorLog_Model>addSystemErrorLog(['error' => json_encode($e, true)]);
        }

        echo "\n---------- " . date('c') . " End addMoneyInYourWalletForCancelAppointment Notification -----------------\n";
    }

    public static function withdrawWalletAmountRequest($job = null) {
        echo "\n---------- " . date('c') . "Start withdrawWalletAmountRequest Notification -----------------\n";
        $data = unserialize($job->workload());
        $CI = &get_instance();

        try {
            if (empty($data)) {
                echo "\n Inavlida Data " . json_encode($data) . "  \n";
                return false;
            }
            $CI->db->reconnect();
            $CI->Background_Model->withdrawWalletAmountRequest($data);
        } catch (Exception $e) {
            echo '\n error : ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
            //$CI->load->model('SystemErrorLog_Model');
            //$CI->SystemErrorLog_Model>addSystemErrorLog(['error' => json_encode($e, true)]);
        }

        echo "\n---------- " . date('c') . " End withdrawWalletAmountRequest Notification -----------------\n";
    }

    public static function withdrawWalletInstantAmountFees($job = null) {
        echo "\n---------- " . date('c') . "Start withdrawWalletInstantAmountFees Notification -----------------\n";
        $data = unserialize($job->workload());
        $CI = &get_instance();

        try {
            if (empty($data)) {
                echo "\n Inavlida Data " . json_encode($data) . "  \n";
                return false;
            }
            $CI->db->reconnect();
            $CI->Background_Model->withdrawWalletInstantAmountFees($data);
        } catch (Exception $e) {
            echo '\n error : ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
            //$CI->load->model('SystemErrorLog_Model');
            //$CI->SystemErrorLog_Model>addSystemErrorLog(['error' => json_encode($e, true)]);
        }

        echo "\n---------- " . date('c') . " End withdrawWalletInstantAmountFees Notification -----------------\n";
    }

}
