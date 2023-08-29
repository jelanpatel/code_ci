<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Auth_Model extends CI_Model {

    public function __construct() {
        parent::__construct(); 
        $this->table = "tbl_auth";
    }

    public function get($data = [], $single = false, $num_rows = false) {
        $this->db->flush_cache();
        if ($num_rows) {
            $this->db->select('COUNT(' . $this->table . '.id) as totalRecord');
        } else {
            $this->db->select($this->table . '.*');
        }

        $this->db->from($this->table);

        if (isset($data['id']) && !empty($data['id'])) {
            if (is_array($data['id'])) {
                $this->db->where_in($this->table . '.id', $data['id']);
            } else {
                $this->db->where($this->table . '.id', $data['id']);
            }
        }

        if (isset($data['userId'])) {
            $this->db->where($this->table . '.userId', $data['userId']);
        }
        
        if (isset($data['token'])) {
            $this->db->where($this->table . '.token', $data['token']);
        }

        if (isset($data['auth_provider'])) {
            $this->db->where($this->table . '.auth_provider', $data['auth_provider']);
        }

        if (isset($data['auth_id'])) {
            $this->db->where($this->table . '.auth_id', $data['auth_id']);
        }

        if (isset($data['deviceToken'])) {
            $this->db->where($this->table . '.deviceToken', $data['deviceToken']);
        }

        if (isset($data['voipToken'])) {
            $this->db->where($this->table . '.voipToken', $data['voipToken']);
        }

        if (isset($data['deviceId'])) {
            $this->db->where($this->table . '.deviceId', $data['deviceId']);
        }

        if (isset($data['deviceType'])) {
            $this->db->where($this->table . '.deviceType', $data['deviceType']);
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
            $this->db->order_by($this->table . '.id', 'DESC');
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

        if (isset($data['userId'])) {
            $modelData['userId'] = $data['userId'];
        }
        
        if (isset($data['token'])) {
            $modelData['token'] = $data['token'];
        }

        if (isset($data['auth_provider'])) {
            $modelData['auth_provider'] = $data['auth_provider'];
        }

        if (isset($data['auth_id'])) {
            $modelData['auth_id'] = $data['auth_id'];
        }

        if (isset($data['deviceToken'])) {
            $modelData['deviceToken'] = $data['deviceToken'];
        }

        if (isset($data['voipToken'])) {
            $modelData['voipToken'] = $data['voipToken'];
        }

        if (isset($data['deviceId'])) {
            $modelData['deviceId'] = $data['deviceId'];
        }

        if (isset($data['deviceType'])) {
            $modelData['deviceType'] = $data['deviceType'];
        }

        if (isset($data['status'])) {
            $modelData['status'] = $data['status'];
        }

        if (empty($modelData)) {
            return false;
        }
        
        if(empty($id)){
            $modelData['createdDate'] = isset($data['createdDate']) && !empty($data['createdDate']) ? $data['createdDate'] : time();
        }

        $modelData['updatedDate'] = time();
        
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

    public function removeToken($token){
        $this->db->where('token', $token);
        $this->db->delete($this->table);
    }
}
