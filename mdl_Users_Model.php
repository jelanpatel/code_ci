<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Users_Model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->table = "tbl_users";
        $this->tbl_user_profession = "tbl_user_profession";
        $this->tbl_profession = "tbl_profession";
        $this->tbl_user_professional = "tbl_user_professional";
        $this->tbl_user_rating = "tbl_user_rating";
        $this->tbl_user_availability = "tbl_user_availability";
        $this->tbl_user_favorite = "tbl_user_favorite";
        $this->tbl_user_subscription = "tbl_user_subscription";
        $this->tbl_stripe_connect = "tbl_stripe_connect";
        $this->tbl_user_availability_setting = "tbl_user_availability_setting";
        $this->tbl_user_services = "tbl_user_services";

        $this->tbl_specialties = "tbl_specialties";
        $this->tbl_user_specialties = "tbl_user_specialties";
        $this->tbl_user_location = "tbl_user_location";
    }

    public function get($data = [], $single = false, $num_rows = false) {
        $this->db->flush_cache();
        if ($num_rows) {
            $this->db->select('COUNT(' . $this->table . '.id) as totalRecord');
        } else {
            if(isset($data['apiResponse'])){
                $this->db->select($this->table . '.id as userId');
                $this->db->select($this->table . '.name');                
                $this->db->select($this->table . '.email');
                $this->db->select($this->table . '.phone');
                $this->db->select($this->table . '.bio');
                $this->db->select($this->table . '.isFounder');
                $this->db->select($this->table . '.virtualPrice');
                $this->db->select($this->table . '.mobilePrice');
                $this->db->select($this->table . '.onsitePrice');
                $this->db->select($this->table . '.gender');
                $this->db->select($this->table . '.latitude');
                $this->db->select($this->table . '.longitude');
                $this->db->select($this->table . '.address');
                $this->db->select($this->table . '.stateName');
                $this->db->select($this->table . '.cityName');
                $this->db->select($this->table . '.isfreeplan');
                $this->db->select($this->table . '.timeZone');
                $this->db->select($this->table . '.onboardingRatingStatus');
                $this->db->select($this->table . '.ispresenceforsearch');
            }else{
                $this->db->select($this->table . '.*');
            }
            $this->db->select('FROM_UNIXTIME(' . $this->table . '.createdDate, "%d-%m-%Y %H:%i") as createdDate');
            $this->db->select("IF(".$this->table.".acceptProfessionalAgreement < 1,0,FROM_UNIXTIME(" . $this->table . ".acceptProfessionalAgreement, '%d-%m-%Y %H:%i')) AS acceptProfessionalAgreement");

            $this->db->select('DATE_FORMAT(' . $this->table . '.birthdate, "%m-%d-%Y") as birthdate');
            $this->db->select($this->table . '.birthdate as birthdateOriginal');
            
            //$this->db->select("CONCAT('" . base_url(getenv('UPLOAD_URL')) . "', " . $this->table . ".image) as profileimage", FALSE);
            #$this->db->select("IF(".$this->table.".image = 'default_user.jpg',CONCAT('https://ui-avatars.com/api/?name=',".$this->table.".name),CONCAT('".base_url(getenv('UPLOAD_URL'))."', ".$this->table.".image)) AS profileimage");
            $this->db->select("IF(".$this->table.".image = 'default_user.jpg',CONCAT('https://ui-avatars.com/api/?name=',REPLACE(".$this->table.".name, ' ', '%20'),'.jpg'),CONCAT('".base_url(getenv('UPLOAD_URL'))."', ".$this->table.".image)) AS profileimage");

            #$this->db->select("CONCAT('" . base_url(getenv('THUMBURL')) . "', ".$this->table.".image) as thumbprofileimage", FALSE);
            #$this->db->select("IF(".$this->table.".image = 'default_user.jpg',CONCAT('https://ui-avatars.com/api/?name=',".$this->table.".name),CONCAT('".base_url(getenv('THUMBURL'))."', ".$this->table.".image)) AS thumbprofileimage");
            $this->db->select("IF(".$this->table.".image = 'default_user.jpg',CONCAT('https://ui-avatars.com/api/?name=',REPLACE(".$this->table.".name, ' ', '%20'),'.jpg'),CONCAT('".base_url(getenv('THUMBURL'))."', ".$this->table.".image)) AS thumbprofileimage");

            $this->db->select("IF(".$this->table.".referralCode = '' ,'' , CONCAT('" . base_url('app-link/referral/') . "', " . $this->table . ".referralCode)) as referralLink", FALSE);
            $this->db->select("CONCAT('" . base_url('app-link/provider-profile/') . "', " . $this->table . ".id) as providerShareLink", FALSE);
        }

        $this->db->from($this->table);
        
        if (isset($data['getRatingAverageData']) && $data['getRatingAverageData'] == true) {
            $this->db->select("(SELECT 
                IF(".$this->tbl_user_rating.".id > 0,ROUND(SUM(".$this->tbl_user_rating.".rating) / COUNT(".$this->tbl_user_rating.".id),1), 0)
                FROM ".$this->tbl_user_rating." 
                WHERE ".$this->tbl_user_rating.".send_to = ".$this->table.".id 
                AND ".$this->tbl_user_rating.".status = 1) as ratingAverage");
   
            if(isset($data['rating']) && !empty($data['rating'])) {
                $rating = $data['rating'];
                $this->db->having('ratingAverage >=', $rating);
                #$this->db->having('ratingAverage >=', $rating - 0.99);
                #$this->db->having('ratingAverage <=', $rating);
            }
        }

        if (isset($data['getSpecialtiesData']) && $data['getSpecialtiesData'] == true) {
            if(isset($data['specialties']) && !empty($data['specialties'])) {
                $this->db->join($this->tbl_user_specialties, $this->tbl_user_specialties.'.userId = '.$this->table.'.id', 'inner');
                $this->db->join($this->tbl_specialties, $this->tbl_specialties.'.id = '.$this->tbl_user_specialties.'.specialtiesId');

                if (is_array($data['specialties']) && count($data['specialties']) != 0) {
                    $this->db->where_in($this->tbl_user_specialties.'.specialtiesId', $data['specialties']);
                }
                else {
                    $this->db->where($this->tbl_user_specialties.'.specialtiesId', $data['specialties']);
                }                
                $this->db->where($this->tbl_user_specialties.'.status =', 1);
                $this->db->where($this->tbl_specialties.'.status =', 1);
            }
        }

        if (isset($data['appointmentType']) && !empty($data['appointmentType'])) {
            $this->db->join($this->tbl_user_services, $this->tbl_user_services.'.userId = '.$this->table.'.id AND '.$this->tbl_user_services.'.status = 1', 'right');
            if (is_array($data['appointmentType']) && count($data['appointmentType']) != 0) {
                $this->db->where_in($this->tbl_user_services.'.type', $data['appointmentType']);
            }
            else {
                $this->db->where($this->tbl_user_services.'.type', $data['appointmentType']);
            }
        }

        if (isset($data['availableSlot']) && !empty($data['availableSlot'])) {
            $this->db->join($this->tbl_user_availability, $this->tbl_user_availability.'.userId = '.$this->table.'.id AND '.$this->tbl_user_availability.'.isBooked = 0 AND '.$this->tbl_user_availability.'.status = 1', 'right');
            $this->db->where('FROM_UNIXTIME('.$this->tbl_user_availability.'.dateTime, "%d-%m-%Y") =', $data['availableSlot']);
        }

        if (isset($data['getFutureFirstAvailability']) && $data['getFutureFirstAvailability'] == true) {
            $this->db->select("(SELECT ".$this->tbl_user_availability.".dateTime
                FROM ".$this->tbl_user_availability."
                WHERE ".$this->tbl_user_availability.".userId = ".$this->table.".id 
                AND ".$this->tbl_user_availability.".isBooked = 0
                AND ".$this->tbl_user_availability.".dateTime > UNIX_TIMESTAMP()
                AND ".$this->tbl_user_availability.".status = 1 ORDER BY ".$this->tbl_user_availability.".dateTime ASC LIMIT 1) as nextAvailable");
                if (isset($data['getOnlyFavouriteData']) || isset($data['checkDoctorAddedInFavourite'])) {                           
                }else{
                    $this->db->having('nextAvailable is not NULL');                
                }
        }

        if (isset($data['getProfessionData']) && $data['getProfessionData'] == true) {
            $this->db->select("(SELECT 
                GROUP_CONCAT(".$this->tbl_profession.".name SEPARATOR', ') 
                FROM ".$this->tbl_user_profession." 
                INNER JOIN ".$this->tbl_profession." ON ".$this->tbl_profession.".id = ".$this->tbl_user_profession.".professionId AND ".$this->tbl_profession.".status = 1 
                WHERE ".$this->tbl_user_profession.".userId = ".$this->table.".id 
                AND ".$this->tbl_user_profession.".status = 1) as professionNames");
        }
        if (isset($data['getProfessionWiseData']) && !empty($data['getProfessionWiseData'])) {
            $this->db->join($this->tbl_user_profession, $this->tbl_user_profession.'.userId = '.$this->table.'.id AND '.$this->tbl_user_profession.'.professionId  = '.$data['getProfessionWiseData'].' AND '. $this->tbl_user_profession . '.status = 1','inner');
            $this->db->join($this->tbl_profession, $this->tbl_profession . '.id = ' . $this->tbl_user_profession . '.professionId AND '. $this->tbl_profession . '.status = 1','inner');
            $this->db->group_by($this->table.'.id');
        }
        if (isset($data['getProfessionalData']) && $data['getProfessionalData'] == true) {
            $this->db->select('CONCAT('.$this->tbl_user_professional.'.practiceYear," ","Years") as experienceYears');
            $this->db->select($this->tbl_user_professional.'.companyName');
            $this->db->join($this->tbl_user_professional, $this->tbl_user_professional.'.userId = '.$this->table.'.id AND '.$this->tbl_user_professional.'.status = 1','left');
        }

        if (isset($data['checkDoctorAddedInFavourite']) && !empty($data['checkDoctorAddedInFavourite'])) {
            $this->db->select('IF('.$this->tbl_user_favorite .'.id > 0,1,0) as isFavorite');
            $this->db->join($this->tbl_user_favorite, $this->table.'.id = '.$this->tbl_user_favorite.'.toUserId  AND '.$this->tbl_user_favorite.'.fromUserId = '.$data['checkDoctorAddedInFavourite'].' AND '.$this->tbl_user_favorite.'.status = 1','left');
        }

        if (isset($data['getSubscriptionData']) && $data['getSubscriptionData'] == true) {
            $this->db->select($this->tbl_user_subscription .'.current_plan');
            $this->db->select($this->tbl_user_subscription .'.last_plan');
            $this->db->select($this->tbl_user_subscription .'.status as subscription_status');
            $this->db->join($this->tbl_user_subscription, $this->table.'.id = '.$this->tbl_user_subscription.'.userId AND '.$this->tbl_user_subscription.'.status IN (0,1)','left');
        }

        if (isset($data['getOnlyFavouriteData']) && !empty($data['getOnlyFavouriteData'])) {
            $this->db->select('IF('.$this->tbl_user_favorite .'.id > 0,1,0) as isFavorite');
            $this->db->join($this->tbl_user_favorite, $this->table.'.id = '.$this->tbl_user_favorite.'.toUserId  AND '.$this->tbl_user_favorite.'.fromUserId = '.$data['getOnlyFavouriteData'].' AND '.$this->tbl_user_favorite.'.status = 1','inner');
            $this->db->group_by($this->table.'.id');
        }

        if (isset($data['checkAvailibilitySetting']) && $data['checkAvailibilitySetting'] == true) {
            $this->db->join($this->tbl_user_availability_setting, $this->table.'.id = '.$this->tbl_user_availability_setting.'.userId AND '.$this->tbl_user_availability_setting.'.status = 1','inner');
            $this->db->group_by($this->table.'.id');
        }

        if (isset($data['checkAvailibilitySettingLeftJoin']) && $data['checkAvailibilitySettingLeftJoin'] == true) {
            $this->db->join($this->tbl_user_availability_setting, $this->table.'.id = '.$this->tbl_user_availability_setting.'.userId AND '.$this->tbl_user_availability_setting.'.status = 1','left');
            $this->db->group_by($this->table.'.id');
        }

        if (isset($data['getStripeConnectedAccountData']) && $data['getStripeConnectedAccountData'] == true) {
            $this->db->select('IF('.$this->tbl_stripe_connect .'.id > 0,1,0) as isStripeConnect');
            $this->db->select($this->tbl_stripe_connect .'.isPayment');
            $this->db->select($this->tbl_stripe_connect .'.isPayout');
            $this->db->select($this->tbl_stripe_connect .'.isBankDetail');
            $this->db->join($this->tbl_stripe_connect, $this->table.'.id = '.$this->tbl_stripe_connect.'.userId AND '.$this->tbl_stripe_connect.'.status IN (1,2)','left');
        }
        
        if (isset($data['getInRadius']) && !empty($data['getInRadius'])) {
            $lat = $data['lat'];
            $lng = $data['long'];
            $miles = $data['miles'];
            $this->db->select("( 3959 * acos( cos( radians($lat) ) * cos( radians( ".$this->table.".latitude ) ) * cos( radians( ".$this->table.".longitude ) - radians($lng) ) + sin( radians($lat) ) * sin( radians( ".$this->table.".latitude ) ) ) ) AS distance");
            //$this->db->having('distance <= ' . $miles ."  OR  ".$this->table.".virtualPrice !=0 ");   
            if (isset($data['isvitual']) && !empty($data['isvitual']) && isset($data['vitualType']) && !empty($data['vitualType'])) {
                $this->db->having('distance <= IF(serviceType = 1, 90000000, '.$miles.') ');  
            }
            else {
                $this->db->having('distance <= ' . $miles);            
            }
            $this->db->where($this->table . '.profileStatus =', 1);
        }
        
        if (isset($data['getInRadiusNew']) && !empty($data['getInRadiusNew'])) {
            $lat = $data['lat'];
            $lng = $data['long'];
            $miles = $data['miles'];
        
            /*$this->db->select('(SELECT sub_user_location.id 
                FROM '.$this->tbl_user_location.' as sub_user_location
                WHERE sub_user_location.userId = '.$this->table.'.id 
                AND sub_user_location.status = 1 
                ORDER BY ( 3959 * acos( cos( radians('.$lat.') ) * cos( radians( sub_user_location.latitude ) ) * cos( radians( sub_user_location.longitude ) - radians('.$lng.') ) + sin( radians('.$lat.') ) * sin( radians( sub_user_location.latitude )))) ASC 
                LIMIT 1) as nearLocationId');
            */
            
            $this->db->select($this->tbl_user_location.".radius as userLocationRadius");
            $this->db->join(
                $this->tbl_user_location, 
                $this->tbl_user_location.'.userId = '.$this->table.'.id  
                AND '.$this->tbl_user_location.'.id = (SELECT sub_user_location.id FROM '.$this->tbl_user_location.' as sub_user_location  WHERE sub_user_location.userId = '.$this->table.'.id AND sub_user_location.status = 1 ORDER BY ( 3959 * acos( cos( radians('.$lat.') ) * cos( radians( sub_user_location.latitude ) ) * cos( radians( sub_user_location.longitude ) - radians('.$lng.') ) + sin( radians('.$lat.') ) * sin( radians( sub_user_location.latitude )))) ASC LIMIT 1)
                AND '.$this->tbl_user_location.'.status = 1', 
            'inner');

            $this->db->select("( 3959 * acos( cos( radians($lat) ) * cos( radians( ".$this->tbl_user_location.".latitude ) ) * cos( radians( ".$this->tbl_user_location.".longitude ) - radians($lng) ) + sin( radians($lat) ) * sin( radians( ".$this->tbl_user_location.".latitude )))) AS distance");
            if (isset($data['isvitual']) && !empty($data['isvitual']) && isset($data['vitualType']) && !empty($data['vitualType'])) {
                $this->db->having('distance <= IF(serviceType = 1, 90000000, '.$miles.') ');  
            }
            else {
                //$this->db->having('distance <= ' . $miles);            
                if (!isset($data['getOnlyMilesNumber'])) {
                    $this->db->having('distance <= userLocationRadius');
                }
            }
            $this->db->where($this->table . '.profileStatus =', 1);
        }
        
        if (isset($data['getNearestDoctor']) && !empty($data['getNearestDoctor'])) {
            $lat = $data['lat'];
            $lng = $data['long'];
            $this->db->select("( 3959 * acos( cos( radians($lat) ) * cos( radians( ".$this->table.".latitude ) ) * cos( radians( ".$this->table.".longitude ) - radians($lng) ) + sin( radians($lat) ) * sin( radians( ".$this->table.".latitude ) ) ) ) AS distance");
        }
        
        if (isset($data['isvitual']) && !empty($data['isvitual'])) {
            $this->db->select($this->tbl_user_services .'.type AS serviceType');
            $this->db->join($this->tbl_user_services, $this->tbl_user_services . '.userId = ' . $this->table . '.id  AND ' . $this->tbl_user_services . '.status = 1', 'right');
            //$this->db->join($this->tbl_user_services, $this->tbl_user_services . '.userId = ' . $this->table . '.id AND ' . $this->tbl_user_services . '.status = 1 AND ' . $this->tbl_user_services . '.type = 1', 'inner');
            if(isset($data['vitualType']) && !empty($data['vitualType'])) {
                $this->db->or_where($this->tbl_user_services . '.type = 1');
            }
            if(isset($data['virtualTypeGuest']) && !empty($data['virtualTypeGuest'])) {
                $this->db->where($this->tbl_user_services . '.type = 1');
            }
        }
       
        if (isset($data['getisvirtual']) && $data['getisvirtual'] == true) {
            $this->db->where($this->table . '.virtualPrice > 0 ');
        }

        if (isset($data['getismobile']) && $data['getismobile'] == true) {
            $this->db->where($this->table . '.mobilePrice > 0 ');
        }

        if (isset($data['getisonsite']) && $data['getisonsite'] == true) {
            $this->db->where($this->table . '.onsitePrice > 0 ');
        }
        
        if (isset($data['id']) && !empty($data['id'])) {
            if (is_array($data['id'])) {
                $this->db->where_in($this->table . '.id', $data['id']);
            } else {
                $this->db->where($this->table . '.id', $data['id']);
            }
        } elseif (isset($data['idNotInclude']) && !empty($data['idNotInclude'])) {
            if (is_array($data['idNotInclude'])) {
                $this->db->where_not_in($this->table . '.id', $data['idNotInclude']);
            } else {
                $this->db->where($this->table . '.id !=', $data['idNotInclude']);
            }
        }

        if (isset($data['search']) && !empty($data['search'])) {
            $search = trim($data['search']);
            $this->db->group_start();
                $this->db->like($this->table . '.name', $search);
                $this->db->or_like($this->table . '.email', $search);
            $this->db->group_end();
        }

        if(isset($data['subscriptionDoctorList']) && $data['subscriptionDoctorList'] == true){
            if((!isset($data['search']) && empty($data['search'])) || (!isset($data['allsearch']) && empty($data['allsearch']))){
                $this->db->join($this->tbl_user_subscription.' as subscrDoctor', $this->table.'.id = subscrDoctor.userId AND subscrDoctor.amount IN (300,99) AND subscrDoctor.status = 1', 'left');
                $this->db->order_by(" subscrDoctor.amount DESC");            

            }
        }

        if (isset($data['allsearch']) && !empty($data['allsearch'])) {
            $search = trim($data['allsearch']);
            $this->db->group_start();
                $this->db->like($this->table . '.name', $search);
                $this->db->or_like($this->table . '.email', $search);
                $this->db->or_like('tbl_p.name', $search);
                $this->db->or_like('tbl_up.practiceYear', $search);
            $this->db->group_end();
            
            $this->db->join($this->tbl_profession.' as tbl_p', 'tbl_p.status = 1','left');
            $this->db->join($this->tbl_user_profession.' as tbl_upl', 'tbl_upl.userId = '.$this->table.'.id AND tbl_upl.professionId  = tbl_p.id AND tbl_upl.status = 1','left');

            $this->db->join($this->tbl_user_professional.' as tbl_up', 'tbl_up.userId = '.$this->table.'.id AND tbl_up.status = 1','left');
            $this->db->group_by($this->table.'.id');
        }

        if (isset($data['like']) && isset($data['value'])) {
            $this->db->like($this->table . '.' . $data['like'], $data['value']);
        }

        if (isset($data['searchName']) && !empty($data["searchName"])) {
            $this->db->like($this->table . '.name', $data['searchName']);
        }

        if (isset($data['currentUserNot']) && !empty($data['currentUserNot'])) {
            $this->db->where($this->table . '.id !=', $data['currentUserNot']);
        }

        if (isset($data['name'])) {
            $this->db->where($this->table . '.name', $data['name']);
        }  

        if (isset($data['applec_status'])) {
            $this->db->where($this->table . '.applec_status', $data['applec_status']);
        }  

        if (isset($data['email']) && !empty($data['email'])) {
            $this->db->where($this->table . '.email', strtolower($data['email']));
        }

        if (isset($data['password']) && !empty($data['password'])) {
            $this->db->where($this->table . '.password', $data['password']);
        }

        if (isset($data['phone_code']) && !empty($data['phone_code'])) {
            $this->db->where($this->table . '.phone_code', $data['phone_code']);
        }

        if (isset($data['phone']) && !empty($data['phone'])) {
            $this->db->where($this->table . '.phone', $data['phone']);
        }

        if (isset($data['role']) && !empty($data['role'])) {
            if (is_array($data['role'])) {
                $this->db->where_in($this->table . '.role', $data['role']);
            } else {
                $this->db->where($this->table . '.role', $data['role']);
            }
        }

        if (isset($data['image']) && !empty($data['image'])) {
            $this->db->where($this->table . '.image', $data['image']);
        }

        if (isset($data['birthdate']) && !empty($data['birthdate'])) {
            $this->db->where($this->table . '.birthdate', $data['birthdate']);
        }

        if (isset($data['gender']) && !empty($data['gender'])) {
            $this->db->where($this->table . '.gender', $data['gender']);
        }

        if (isset($data['ispresenceforsearch'])) {
            $this->db->where($this->table . '.ispresenceforsearch', $data['ispresenceforsearch']);
        }

        if (isset($data['emergencyContact']) && !empty($data['emergencyContact'])) {
            $this->db->where($this->table . '.emergencyContact', $data['emergencyContact']);
        }
        if (isset($data['contactPersonName']) && !empty($data['contactPersonName'])) {
            $this->db->where($this->table . '.contactPersonName', $data['contactPersonName']);
        }

        if (isset($data['latitude']) && !empty($data['latitude'])) {
            $this->db->where($this->table . '.latitude', $data['latitude']);
        }
        if (isset($data['latNotBlank']) && !empty($data['latNotBlank'])) {
            $this->db->where($this->table . '.latitude !=""');
        }

        if (isset($data['longitude']) && !empty($data['longitude'])) {
            $this->db->where($this->table . '.longitude', $data['longitude']);
        }

        if (isset($data['address']) && !empty($data['address'])) {
            $this->db->where($this->table . '.address', $data['address']);
        }

        if (isset($data['unitNo']) && !empty($data['unitNo'])) {
            $this->db->where($this->table . '.unitNo', $data['unitNo']);
        }

        if (isset($data['cityName']) && !empty($data['cityName'])) {
            $this->db->where($this->table . '.cityName', $data['cityName']);
        }

        if (isset($data['stateName']) && !empty($data['stateName'])) {
            $this->db->where($this->table . '.stateName', $data['stateName']);
        }

        if (isset($data['zipcode']) && !empty($data['zipcode'])) {
            $this->db->where($this->table . '.zipcode', $data['zipcode']);
        }

        if (isset($data['bio']) && !empty($data['bio'])) {
            $this->db->where($this->table . '.bio', $data['bio']);
        }

        if (isset($data['timeZone']) && !empty($data['timeZone'])) {
            $this->db->where($this->table . '.timeZone', $data['timeZone']);
        }

        if (isset($data['virtualPrice']) && !empty($data['virtualPrice'])) {
            $this->db->where($this->table . '.virtualPrice', $data['virtualPrice']);
        }

        if (isset($data['mobilePrice']) && !empty($data['mobilePrice'])) {
            $this->db->where($this->table . '.mobilePrice', $data['mobilePrice']);
        }

        if (isset($data['onsitePrice']) && !empty($data['onsitePrice'])) {
            $this->db->where($this->table . '.onsitePrice', $data['onsitePrice']);
        }

        if (isset($data['forgotCode']) && !empty($data['forgotCode'])) {
            $this->db->where($this->table . '.forgotCode', $data['forgotCode']);
        }

        if (isset($data['verificationCode']) && !empty($data['verificationCode'])) {
            $this->db->where($this->table . '.verificationCode', $data['verificationCode']);
        }

        if (isset($data['profileStatus'])) {
            $this->db->where($this->table . '.profileStatus', $data['profileStatus']);
        }

        if (isset($data['referralCode']) && !empty($data['referralCode'])) {
            $this->db->where($this->table . '.referralCode', $data['referralCode']);
        }

        if (isset($data['isFounder'])) {
            $this->db->where($this->table . '.isFounder', $data['isFounder']);
        }

        if (isset($data['stripeCustomerId'])) {
            $this->db->where($this->table . '.stripeCustomerId', $data['stripeCustomerId']);
        }

        if (isset($data['stripeCustomerJson'])) {
            $this->db->where($this->table . '.stripeCustomerJson', $data['stripeCustomerJson']);
        }

        if (isset($data['walletAmount'])) {
            $this->db->where($this->table . '.walletAmount', $data['walletAmount']);
        }

        if (isset($data['createdDate'])) {
            $this->db->where($this->table . '.createdDate', $data['createdDate']);
        }

        if (isset($data['acceptProfessionalAgreement'])) {
            $this->db->where($this->table . '.acceptProfessionalAgreement', $data['acceptProfessionalAgreement']);
        }

        if (isset($data['providerWebStep'])) {
            $this->db->where($this->table . '.providerWebStep', $data['providerWebStep']);
        }

        if (isset($data['isfreeplan'])) {
            $this->db->where($this->table . '.isfreeplan', $data['isfreeplan']);
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

        if (isset($data['getInRadius']) && !empty($data['getInRadius'])) {           
            $this->db->order_by(" distance ASC");            
        }

        if (isset($data['getNearestDoctor']) && !empty($data['getNearestDoctor'])) {
            $this->db->order_by(" distance ASC");            
        }
        if (isset($data['orderby']) && !empty($data['orderby'])) {
            $this->db->order_by($this->table.'.'.$data['orderby'], (isset($data['orderstate']) && !empty($data['orderstate']) ? $data['orderstate'] : 'DESC'));
        } 

        $query = $this->db->get();
        //echo "<pre>";echo $this->db->last_query(); die;
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
        
        if (isset($data['isfreeplan'])) {
            $modelData['isfreeplan'] = $data['isfreeplan'];
        }
        
        if (isset($data['name'])) {
            $modelData['name'] = ucwords($data['name']);
        }

        if (isset($data['email'])) {
            $modelData['email'] = strtolower($data['email']);
        }

        if (isset($data['password'])) {
            $modelData['password'] = $data['password'];
        }

        if (isset($data['phone_code'])) {
            $modelData['phone_code'] = $data['phone_code'];
        }

        if (isset($data['phone'])) {
            $modelData['phone'] = $data['phone'];
        }

        if (isset($data['image']) && !empty($data['image'])) {
            $modelData['image'] = $data['image'];
        }

        if (isset($data['birthdate'])) {
            $modelData['birthdate'] = $data['birthdate'];
        }

        if (isset($data['gender'])) {
            $modelData['gender'] = $data['gender'];
        }

        if (isset($data['onboardingRatingStatus'])) {
            $modelData['onboardingRatingStatus'] = $data['onboardingRatingStatus'];
        }

        if (isset($data['ispresenceforsearch'])) {
            $modelData['ispresenceforsearch'] = $data['ispresenceforsearch'];
        }

        if (isset($data['emergencyContact'])) {
            $modelData['emergencyContact'] = $data['emergencyContact'];
        }
        if (isset($data['contactPersonName'])) {
            $modelData['contactPersonName'] = $data['contactPersonName'];
        }

        if (isset($data['latitude'])) {
            $modelData['latitude'] = $data['latitude'];
        }

        if (isset($data['longitude'])) {
            $modelData['longitude'] = $data['longitude'];
        }

        if (isset($data['address'])) {
            $modelData['address'] = $data['address'];
        }

        if (isset($data['unitNo'])) {
            $modelData['unitNo'] = $data['unitNo'];
        }

        if (isset($data['cityName'])) {
            $modelData['cityName'] = $data['cityName'];
        }

        if (isset($data['stateName'])) {
            $modelData['stateName'] = $data['stateName'];
        }

        if (isset($data['zipcode'])) {
            $modelData['zipcode'] = $data['zipcode'];
        }

        if (isset($data['bio'])) {
            $modelData['bio'] = $data['bio'];
        }

        if (isset($data['timeZone'])) {
            $modelData['timeZone'] = $data['timeZone'];
        }

        if (isset($data['virtualPrice'])) {
            $modelData['virtualPrice'] = $data['virtualPrice'];
        }

        if (isset($data['mobilePrice'])) {
            $modelData['mobilePrice'] = $data['mobilePrice'];
        }

        if (isset($data['onsitePrice'])) {
            $modelData['onsitePrice'] = $data['onsitePrice'];
        }

        if (isset($data['gc_accessToken'])) {
            $modelData['gc_accessToken'] = $data['gc_accessToken'];
        }

        if (isset($data['gc_json'])) {
            $modelData['gc_json'] = $data['gc_json'];
        }

        if (isset($data['gc_status'])) {
            $modelData['gc_status'] = $data['gc_status'];
        }
        
        if (isset($data['gc_updateTime'])) {
            $modelData['gc_updateTime'] = $data['gc_updateTime'];
        }
        
        if (isset($data['applec_status'])) {
            $modelData['applec_status'] = $data['applec_status'];
        }

        if (isset($data['role'])) {
            $modelData['role'] = $data['role'];
        }
    
        if (isset($data['forgotCode'])) {
            $modelData['forgotCode'] = $data['forgotCode'];
        }

        if (isset($data['verificationCode'])) {
            $modelData['verificationCode'] = $data['verificationCode'];
        }

        if (isset($data['profileStatus'])) {
            $modelData['profileStatus'] = $data['profileStatus'];
        }

        if (isset($data['referralCode'])) {
            $modelData['referralCode'] = $data['referralCode'];
        }

        if (isset($data['isFounder'])) {
            $modelData['isFounder'] = $data['isFounder'];
        }

        if (isset($data['stripeCustomerId'])) {
            $modelData['stripeCustomerId'] = $data['stripeCustomerId'];
        }

        if (isset($data['stripeCustomerJson'])) {
            $modelData['stripeCustomerJson'] = $data['stripeCustomerJson'];
        }

        if (isset($data['walletAmount'])) {
            $modelData['walletAmount'] = $data['walletAmount'];
        }

        if (isset($data['acceptProfessionalAgreement'])) {
            $modelData['acceptProfessionalAgreement'] = $data['acceptProfessionalAgreement'];
        }

        if (isset($data['providerWebStep'])) {
            $modelData['providerWebStep'] = $data['providerWebStep'];
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

    public function userData($id, $secure = FALSE,$authId = "") {
        if (empty($id)) {
            return false;
        }
        $user = $this->get(['id' => $id,'getStripeConnectedAccountData'=>TRUE]);

        if (empty($user)) {
            return false;
        }

        if (empty($user->password)) {
            $user->fillpassword = "0";
        } else {
            $user->fillpassword = "1";
        }

        if (empty($user)) {
            return false;
        }

        if ($secure == FALSE) {
            $user->token = "";
        }

        $user->password = "";
        $user->stripeCustomerJson = "";
        $user->forgotCode = "";
        $user->verificationCode = "";
        $user->token = "";
        
        $this->load->model('User_Language_Model','User_Language');
        $user->preferredLanguage = $this->User_Language->get(['userId'=>$user->id,'apiResponse'=>true,'status'=>1]);
        if($user->role == 3){
            $this->load->model('User_Rating_Model','User_Rating');
            $doctorRating = $this->User_Rating->get(['send_to'=>$user->id,'status'=>1,'getRatingAverage'=>true],true);
            $user->doctorRating = (isset($doctorRating->ratingAverage) ? $doctorRating->ratingAverage : "0.0") ;
        }
        if(!empty($authId)){
            $this->load->model('Auth_Model');
            $getAuthData = $this->Auth_Model->get(['id'=>$authId],TRUE);
            if(!empty($getAuthData)){
                $user->token = $getAuthData->token;
            }
        }

        return $user;
    }

}
