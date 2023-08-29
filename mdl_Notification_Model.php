<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Notification_Model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->table = "tbl_notification";
        $this->tbl_users = "tbl_users";
    }
    
    public function get($data = [], $single = false, $num_rows = false) {
        $this->db->flush_cache();
        if ($num_rows) {
            $this->db->select('COUNT(' . $this->table . '.id) as totalRecord');
        } else {
            $this->db->select($this->table . '.*');
        }

        $this->db->from($this->table);

        if (isset($data['checkNotification']) && !empty($data['checkNotification'])) {
            $this->db->where("(".$this->table . ".send_from = ".$data['checkNotification']['send_from']." OR ".$this->table . ".send_from = ".$data['checkNotification']['send_to'].")");
            $this->db->where("(".$this->table . ".send_to = ".$data['checkNotification']['send_from']." OR ".$this->table . ".send_to = ".$data['checkNotification']['send_to'].")");
        }
        if(isset($data['userData']) && $data['userData'] == TRUE){
            $this->db->select($this->tbl_users .'.name as senderName');
            $this->db->select($this->tbl_users .'.role as senderRole');
            
            #$this->db->select("CONCAT('".base_url(getenv('UPLOAD_URL'))."',".$this->tbl_users.".image) as senderImage", FALSE);
            #$this->db->select("IF(".$this->tbl_users.".image = 'default_user.jpg',CONCAT('https://ui-avatars.com/api/?name=',".$this->tbl_users.".name),CONCAT('".base_url(getenv('UPLOAD_URL'))."', ".$this->tbl_users.".image)) AS senderImage");
            $this->db->select("IF(".$this->tbl_users.".image = 'default_user.jpg',CONCAT('https://ui-avatars.com/api/?name=',REPLACE(".$this->tbl_users.".name, ' ', '%20'),'.jpg'),CONCAT('".base_url(getenv('UPLOAD_URL'))."', ".$this->tbl_users.".image)) AS senderImage");
            
            #$this->db->select("CONCAT('".base_url(getenv('THUMBURL'))."', ".$this->tbl_users.".image) as thumbSenderImage", FALSE);
            #$this->db->select("IF(".$this->tbl_users.".image = 'default_user.jpg',CONCAT('https://ui-avatars.com/api/?name=',".$this->tbl_users.".name),CONCAT('".base_url(getenv('THUMBURL'))."', ".$this->tbl_users.".image)) AS thumbSenderImage");
            $this->db->select("IF(".$this->tbl_users.".image = 'default_user.jpg',CONCAT('https://ui-avatars.com/api/?name=',REPLACE(".$this->tbl_users.".name, ' ', '%20'),'.jpg'),CONCAT('".base_url(getenv('THUMBURL'))."', ".$this->tbl_users.".image)) AS thumbSenderImage");
            
            $this->db->join($this->tbl_users, $this->table . ".send_from = " . $this->tbl_users . ".id", 'left');
            $this->db->where($this->tbl_users . '.status', 1);
        }

        if (isset($data['id']) && !empty($data['id'])) {
            if (is_array($data['id'])) {
                $this->db->where_in($this->table . '.id', $data['id']);
            } else {
                $this->db->where($this->table . '.id', $data['id']);
            }
        }
		
        if (isset($data['title'])) {
            $this->db->where($this->table . '.title', $data['title']);
        }
		
        if (isset($data['desc'])) {
            $this->db->where($this->table . '.desc', $data['desc']);
        }
		
        if (isset($data['send_to'])) {
            $this->db->where($this->table . '.send_to', $data['send_to']);
        }
		
        if (isset($data['send_from'])) {
            $this->db->where($this->table . '.send_from', $data['send_from']);
        }
		
        if (isset($data['model'])) {
            $this->db->where($this->table . '.model', $data['model']);
        }
		
        if (isset($data['model_id'])) {
            $this->db->where($this->table . '.model_id', $data['model_id']);
        }

        if (isset($data['updatedDate'])) {
            $this->db->where($this->table . '.updatedDate', $data['updatedDate']);
        }

        if (isset($data['createdDate'])) {
            $this->db->where($this->table . '.createdDate', $data['createdDate']);
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
		
        if (isset($data['title'])) {
            $modelData['title'] = $data['title'];
        }
		
        if (isset($data['desc'])) {
            $modelData['desc'] = $data['desc'];
        }
		
        if (isset($data['send_to'])) {
            $modelData['send_to'] = $data['send_to'];
        }
		
        if (isset($data['send_from'])) {
            $modelData['send_from'] = $data['send_from'];
        }
		
        if (isset($data['model'])) {
            $modelData['model'] = $data['model'];
        }
		
        if (isset($data['model_id'])) {
            $modelData['model_id'] = $data['model_id'];
        }
		
        if (isset($data['status'])) {
            $modelData['status'] = $data['status'];
        }
        
        if (isset($data['updatedDate'])) {
            $modelData['updatedDate'] = $data['updatedDate'];
        } elseif (!empty($id)) {
            $modelData['updatedDate'] = time();
        }
        if(empty($modelData)){
            return false;
        }
        
        if(!isset($data['markSeenAll'])){
            if(empty($id)) {
                $modelData['createdDate'] = !empty($data['createdDate']) ? $data['createdDate'] : time();
            }elseif(isset($data['createdDate']) && $data['createdDate'] == true) {
                $modelData['createdDate'] = time();
            }
        }
		
		
        $this->db->flush_cache();
        $this->db->trans_begin();
        if (!empty($id)) {
            $this->db->where('id', $id);
            $this->db->update($this->table, $modelData);
        } else {
            if (isset($data['markSeenAll'])) {
                $this->db->where('send_to', $data['seenUserId']);
                $this->db->update($this->table, $modelData);
            } else {
                $this->db->insert($this->table, $modelData);
                $id = $this->db->insert_id();
            }
        }

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return false;
        }

        $this->db->trans_commit();
        return $id;
    }

    public function makeread($userId){
        $this->db->where('send_to', $userId);
        $this->db->update($this->table, ['status'=>'0']);
        return true;
    }
}
