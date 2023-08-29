<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class App_User_Feedback_Model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->table = "tbl_app_feedback";
        $this->tbl_users = "tbl_users";
    }

    public function get($data = [], $single = false, $num_rows = false) {
        $this->db->flush_cache();
        if ($num_rows) {
            $this->db->select('COUNT(' . $this->table . '.id) as totalRecord');
        } else {
            $this->db->select($this->table . '.*');
            $this->db->select('FROM_UNIXTIME(' . $this->table . '.updatedDate, "%d-%m-%Y %H:%i") as updatedDate');
            $this->db->select('FROM_UNIXTIME(' . $this->table . '.createdDate, "%d-%m-%Y %H:%i") as createdDate');
            $this->db->select($this->tbl_users . '.name as userName');
        }

        $this->db->from($this->table);
        $this->db->join($this->tbl_users, $this->table . ".userId = " . $this->tbl_users . ".id AND ". $this->tbl_users .".status = 1");

        if (isset($data['id']) && !empty($data['id'])) {
            if (is_array($data['id'])) {
                $this->db->where_in($this->table . '.id', $data['id']);
            } else {
                $this->db->where($this->table . '.id', $data['id']);
            }
        }

        if (isset($data['search']) && !empty($data['search'])) {
            $search = trim($data['search']);
            $this->db->group_start();
                $this->db->like($this->tbl_users . '.name', $search);
                $this->db->or_like($this->table .'.feedback', $search);
            $this->db->group_end();
        }

        if (isset($data['userId'])) {
            $this->db->where($this->table . '.userId', $data['userId']);
        }

        if (isset($data['rating'])) {
            $this->db->where($this->table . '.rating', $data['rating']);
        }

        if (isset($data['feedback'])) {
            $this->db->where($this->table . '.feedback', $data['feedback']);
        }

        if (isset($data['status'])) {
            if (is_array($data['status'])) {
                $this->db->where_in($this->table . '.status', $data['status']);
            } else {
                $this->db->where($this->table . '.status', $data['status']);
            }
        }

        if (isset($data['createdDate'])) {
            $this->db->where($this->tbl_user_gallery . '.createdDate', $data['createdDate']);
        }

        if (isset($data['updatedDate'])) {
            $this->db->where($this->tbl_user_gallery . '.updatedDate', $data['updatedDate']);
        }

        if (!$num_rows) {
            if (isset($data['length']) && isset($data['start'])) {
                $this->db->limit($data['length'], $data['start']);
            } elseif (isset($data['length']) && !empty($data['length'])) {
                $this->db->limit($data['length']);
            } else {
                // $this->db->limit(10);
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

        if (isset($data['userId']) && !empty($data['userId'])) {
            $modelData['userId'] = $data['userId'];
        }

        if (isset($data['rating'])) {
            $modelData['rating'] = $data['rating'];
        }

        if (isset($data['feedback']) && !empty($data['feedback'])) {
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
            $modelData['createdDate'] = isset($data['createdDate']) && !empty($data['createdDate']) ? $data['createdDate'] : time();
        }

        $this->db->flush_cache();
        $this->db->trans_begin();

        if (!empty($id)) {
            if (is_array($id)) {
                $this->db->where_in('id', $id);
            } else {
                $this->db->where('id', $id);
            }
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
