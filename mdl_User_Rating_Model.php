<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class User_Rating_Model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->table = "tbl_user_rating";
        $this->tbl_users = "tbl_users";
    }
    public function get($data = [], $single = false, $num_rows = false) {
        $this->db->flush_cache();
        if ($num_rows) {
            $this->db->select('COUNT(' . $this->table . '.id) as totalRecord');
        } else {
            if(isset($data['apiResponse'])){
                $this->db->select($this->table . '.id as illnessId');
                $this->db->select($this->table . '.send_from as userId');
                $this->db->select($this->table . '.send_to as doctorId');
                $this->db->select($this->table . '.rating');
                $this->db->select($this->table . '.feedback');
                $this->db->select($this->table . '.createdDate');
                //$this->db->select('FROM_UNIXTIME(' . $this->table . '.createdDate, "%d.%m.%Y at %h:%i") as createdDate');
            }else{
                $this->db->select($this->table . '.*');
            }
        }
        
        if(isset($data['getRatingAverage']) && $data['getRatingAverage']==true){
            $this->db->select('ROUND(SUM('.$this->table.'.rating)/COUNT('.$this->table.'.id),2) as ratingAverage');
        }

        if (isset($data['getUserData']) && $data['getUserData'] == true) {
            $this->db->select($this->tbl_users . '.name as userName');
            $this->db->select("CONCAT('" . base_url(getenv('UPLOAD_URL')) . "', " . $this->tbl_users . ".image) as userProfileImage", FALSE);            
            $this->db->select("CONCAT('" . base_url(getenv('THUMBURL')) . "', ".$this->tbl_users.".image) as thumbUserProfileImage", FALSE);
            $this->db->join($this->tbl_users, $this->tbl_users.'.id = '.$this->table.'.send_from AND '.$this->tbl_users.'.status = 1','inner');
        }

        $this->db->from($this->table);

        if (isset($data['search']) && !empty($data['search'])) {
            $search = trim($data['search']);
            $this->db->group_start();
                $this->db->like($this->table .'.feedback ',$search);
            $this->db->group_end();
        }

        if (isset($data['id']) && !empty($data['id'])) {
            if (is_array($data['id'])) {
                $this->db->where_in($this->table . '.id', $data['id']);
            } else {
                $this->db->where($this->table . '.id', $data['id']);
            }
        }

        if (isset($data['send_from'])) {
            $this->db->where($this->table . '.send_from', $data['send_from']);
        }

        if (isset($data['send_to'])) {
            $this->db->where($this->table . '.send_to', $data['send_to']);
        }

        if (isset($data['rating'])) {
            $this->db->where($this->table . '.rating', $data['rating']);
        }

        if (isset($data['appointmentId'])) {
            $this->db->where($this->table . '.appointmentId', $data['appointmentId']);
        }

        if (isset($data['feedback'])) {
            $this->db->where($this->table . '.feedback', $data['feedback']);
        }

        if (isset($data['createdDate'])) {
            $this->db->where($this->table . '.createdDate', $data['createdDate']);
        }

        if (isset($data['updatedDate'])) {
            $this->db->where($this->table . '.updatedDate', $data['updatedDate']);
        }

        if (isset($data['status'])) {
            if (is_array($data['status'])) {
                $this->db->where_in($this->table . '.status', $data['status']);
            } else {
                $this->db->where($this->table . '.status', $data['status']);
            }
        }

        if (!$num_rows) {
            if (isset($data['limit']) && isset($data['offset'])) {
                $this->db->limit($data['limit'], $data['offset']);
            } elseif (isset($data['limit']) && !empty($data['limit'])) {
                $this->db->limit($data['limit']);
            } else {
                //$this->db->limit(10);
            }
        }

        if (isset($data['orderby']) && !empty($data['orderby'])) {
            $this->db->order_by($data['orderby'], (isset($data['orderstate']) && !empty($data['orderstate']) ? $data['orderstate'] : 'DESC'));
        } else {
            $this->db->order_by($this->table . '.createdDate', 'DESC');
        }

        $query = $this->db->get();

        if ($num_rows) {
            $row = $query->row();
            return (isset($row->totalRecord) && !empty($row->totalRecord) ? $row->totalRecord : "0");
        }


        if ($single) {
            return $query->row();
        } elseif (isset($data['id']) && !empty($data['id']) && !is_array($data['id'])) {
            return $query->row();
        }

        return $query->result();
    }

    public function setData($data, $id = 0) {
        if (empty($data)) {
            return false;
        }
        $modelData = array();

        if (isset($data['send_from'])) {
            $modelData['send_from'] = $data['send_from'];
        }

        if (isset($data['send_to'])) {
            $modelData['send_to'] = $data['send_to'];
        }

        if (isset($data['appointmentId'])) {
            $modelData['appointmentId'] = $data['appointmentId'];
        }

        if (isset($data['rating'])) {
            $modelData['rating'] = $data['rating'];
        }

        if (isset($data['feedback'])) {
            $modelData['feedback'] = $data['feedback'];
        }

        if (isset($data['status'])) {
            $modelData['status'] = $data['status'];
        }

        if (isset($data['updatedDate'])) {
            $modelData['updatedDate'] = $data['updatedDate'];
        } elseif (!empty($id)) {
            $modelData['updatedDate'] = time();
        }

        if (empty($modelData)) {
            return false;
        }
        if (empty($id)) {
            $modelData['createdDate'] = !empty($data['createdDate']) ? $data['createdDate'] : time();
        }
        $this->db->flush_cache();
        $this->db->trans_begin();

        if (!empty($id)) {
            $this->db->where('id', $id);
            $this->db->update($this->table, $modelData);
        } else {
            $this->db->insert($this->table, $modelData);
            $id = $this->db->insert_id();
        }

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return false;
        }

        $this->db->trans_commit();
        return $id;
    }
    
}