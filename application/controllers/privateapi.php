<?php

class Privateapi extends CI_Controller
{
   
   private $user_id = NULL;
   
   private $data = array();
   
   private $settings = array();
    
   public function __construct()
   {
        parent::__construct();
        
        header('Content-Type: application/json');
        
        //load settings
        $this->load->model('settings_m');
        $this->settings = $this->settings_m->get_fields();
        
        //load language files
        $this->load->model('language_m');
        $lang_code_uri = $this->uri->segment(3);
        $lang_name = $this->language_m->get_name($lang_code_uri);
        if($lang_name != NULL)
            $this->lang->load('frontend_template', $lang_name, FALSE, TRUE, FCPATH.'templates/'.$this->settings['template'].'/');
        
        // Check login and fetch user id
        $this->load->library('session');
        $this->load->model('user_m');
        if($this->user_m->loggedin() == TRUE)
        {
            $this->user_id = $this->session->userdata('id');
        }
        else
        {
            $this->data['message'] = lang_check('Login required!');
            $this->data['success'] = false;
            echo json_encode($this->data);
            exit();
        }
   }

	public function index()
	{
		$this->data['message'] = lang_check('Hello, Private API here!');
        echo json_encode($this->data);
        exit();
	}
    
    public function design_save($lang_code='')
    {
        $this->data['message'] = lang_check('No message returned!');
        $this->data['parameters'] = $_POST;
        // To fetch user_id use: $this->user_id

        $this->load->model('settings_m');
        
        $this->data['success'] = false;
        
        if($this->session->userdata('type') == 'ADMIN')
        {
            if(isset($this->data['parameters']['design_parameters']))
            {
                $post_data = array('design_parameters' => $this->data['parameters']['design_parameters'],
                                   'css_variant' => $this->data['parameters']['css_variant'],
                                   'color' => $this->data['parameters']['color']);
                $this->settings_m->save_settings($post_data);
                $this->session->set_userdata( array('color'=>$this->data['parameters']['color']));
                $this->data['message'] = lang_check('Changes saved!');
                $this->data['success'] = true;
            }
            else
            {
                $this->data['message'] = lang_check('Parameters not defined!');
                $this->data['success'] = true;
            }
        }
        
        echo json_encode($this->data);
        exit();
    }
    
    public function add_to_favorites($lang_code='')
    {
        $this->data['message'] = lang_check('No message returned!');
        $this->data['parameters'] = $_POST;
        $property_id = $this->input->post('property_id');
        // To fetch user_id use: $this->user_id

        $this->load->model('favorites_m');
        
        $this->data['success'] = false;
        // Check if property_id already saved, stop and write message
        if($this->favorites_m->check_if_exists($this->user_id, $property_id)>0)
        {
            $this->data['message'] = lang_check('Favorite already exists!');
            $this->data['success'] = true;
        }
        // Save favorites to database
        else
        {
            $data = $this->favorites_m->get_new_array();
            $data['user_id'] = $this->user_id;
            $data['property_id'] = $property_id;
            $data['lang_code'] = $lang_code;
            $data['date_last_informed'] = date('Y-m-d H:i:s');
            
            $this->favorites_m->save($data);
            
            $this->data['message'] = lang_check('Favorite added!');
            $this->data['success'] = true;
        }
        
        echo json_encode($this->data);
        exit();
    }
    
    public function remove_from_favorites($lang_code='')
    {
        $this->data['message'] = lang_check('No message returned!');
        $this->data['parameters'] = $_POST;
        $property_id = $this->input->post('property_id');
        // To fetch user_id use: $this->user_id

        $this->load->model('favorites_m');
        
        $this->data['success'] = false;
        // Check if property_id already saved, stop and write message
        if($this->favorites_m->check_if_exists($this->user_id, $property_id)>0)
        {
            $favorite_selected = $this->favorites_m->get_by(array('property_id'=>$property_id, 'user_id'=>$this->user_id), TRUE);
            $this->favorites_m->delete($favorite_selected->id);
            
            $this->data['message'] = lang_check('Favorite removed!');
            $this->data['success'] = true;
        }
        // Save favorites to database
        else
        {
            $this->data['message'] = lang_check('Favorite doesnt exists!');
            $this->data['success'] = true;
        }
        
        echo json_encode($this->data);
        exit();
    }
    
    public function dropdown($table)
    {
        $this->data['message'] = lang_check('No message returned!');
        $this->data['parameters'] = $_POST;
        // To fetch user_id use: $this->user_id

        $this->data['success'] = false;
        
        if(empty($this->data['parameters']['limit']))
            $this->data['parameters']['limit'] = 10;
            
        if(empty($this->data['parameters']['offset']))
            $this->data['parameters']['offset'] = 0;
            
        if(empty($this->data['parameters']['attribute_id']))
            $this->data['parameters']['attribute_id'] = 'id';
            
        if(empty($this->data['parameters']['show_empty']))
            $this->data['parameters']['show_empty'] = false;
            
        if(empty($this->data['parameters']['attribute_value']))
            $this->data['parameters']['attribute_value'] = 'address';
        
        if(substr($table,-2, 2) == '_m')
        {
            // it's model
            $attr_id = $this->data['parameters']['attribute_id'];
            $attr_val = $this->data['parameters']['attribute_value'];
            $attr_search = $this->data['parameters']['search_term'];
            
            $id_part="";
            if(is_numeric($attr_search))
                $id_part = "$attr_id=$attr_search OR ";
            
            $this->load->model($table);
            
            $where = array();
            if(!empty($this->data['parameters']['language_id']))
                $where["language_id"] = $this->data['parameters']['language_id'];
            
            if(!empty($attr_search))
                $where["($id_part $attr_val LIKE '%$attr_search%')"] = NULL;
            
            //get_by($where, $single = FALSE, $limit = NULL, $order_by = NULL, $offset = NULL, 
            //$search = array(), $where_in = NULL, $check_user = FALSE, $fetch_user_details=FALSE)
            if($table == 'estate_m')
            {
                $q_results = $this->$table->get_by($where, FALSE, $this->data['parameters']['limit'], 
                                                    "$attr_id DESC", $this->data['parameters']['offset'],
                                                    array(), NULL, TRUE);
            }
            else
            {
                $q_results = $this->$table->get_by($where, FALSE, $this->data['parameters']['limit'], 
                                                    "$attr_id DESC", $this->data['parameters']['offset']);
            }
            
            $results = array();
            
            if($this->data['parameters']['show_empty'] == true && $this->data['parameters']['offset'] == 0)
            {
                $results[-1]['key'] = '';
                $results[-1]['value'] = '-';
            }
            
            foreach ($q_results as $key=>$row)
            {
                $results[$key]['key'] = $row->id;
                $results[$key]['value'] = $row->{$this->data['parameters']['attribute_id']}.', '.
                                            _ch($row->{$this->data['parameters']['attribute_value']});
            }
            
            // get current value by ID
            $row = $this->$table->get($this->data['parameters']['curr_id']);
            if(is_object($row))
            {
                $this->data['curr_val'] = $row->{$this->data['parameters']['attribute_id']}.', '.
                                                _ch($row->{$this->data['parameters']['attribute_value']});
            }
            else
            {
                $this->data['curr_val'] = '-';
            }
            
            $this->data['success'] = true;
        }
        else
        {
            // it's table
            if($this->session->userdata('type') == 'ADMIN')
            {
                if(!empty($this->data['parameters']['search_term']))
                {
                    $attr_id = $this->data['parameters']['attribute_id'];
                    $attr_val = $this->data['parameters']['attribute_value'];
                    $attr_search = $this->data['parameters']['search_term'];
                    
                    $id_part="";
                    if(is_numeric($attr_search))
                        $id_part = "$attr_id=$attr_search OR ";
                    
                    $this->db->where("($id_part $attr_val LIKE '%$attr_search%')", NULL, FALSE);
                }
                
                $this->db->order_by("id desc"); 
                $query = $this->db->get($table, $this->data['parameters']['limit'], $this->data['parameters']['offset']);
                
                $results = array();
                foreach ($query->result() as $key=>$row)
                {
                    $results[$key]['key'] = $row->id;
                    $results[$key]['value'] = $row->{$this->data['parameters']['attribute_id']}.', '.
                                                _ch($row->{$this->data['parameters']['attribute_value']});
                }
                
                // get current value by ID
                $this->db->where("id", $this->data['parameters']['curr_id']); 
                $query = $this->db->get($table, 1);
                $row = $query->row();
                if(!empty($row))
                {
                    $this->data['curr_val'] = $row->{$this->data['parameters']['attribute_id']}.', '.
                                                    _ch($row->{$this->data['parameters']['attribute_value']});
                }
                else
                {
                    $this->data['curr_val'] = '-';
                }
                
                $this->data['success'] = true;
            }
            else
            {
                $this->data['success'] = false;
            }
        }
        
        $this->data['results'] = $results;
        
        echo json_encode($this->data);
        exit();
    }

    public function save_search($lang_code='')
    {
        $this->data['message'] = lang_check('No message returned!');
        
        if(count($_POST > 0))
        {
            // [START] Radius search
            $search_radius = $_POST['v_search_radius'];
            if(isset($search_radius) && isset($_POST['v_search_option_smart']) && $search_radius > 0)
            {
                $this->load->library('ghelper');
                $coordinates_center = $this->ghelper->getCoordinates($search_array['v_search_option_smart']);
                
                if(count($coordinates_center) >= 2 && $coordinates_center['lat'] != 0)
                {
                    // calculate rectangle
                    $rectangle_ne = $this->ghelper->getDueCoords($coordinates_center['lat'], $coordinates_center['lng'], 315, $search_radius);
                    $rectangle_sw = $this->ghelper->getDueCoords($coordinates_center['lat'], $coordinates_center['lng'], 135, $search_radius);
                    
                    $_POST['v_rectangle_ne'] = $rectangle_ne;
                    $_POST['v_rectangle_sw'] = $rectangle_sw;
                    unset($_POST['v_search_option_smart'], $_POST['v_undefined'], $_POST['v_search_radius']);
                }
            }
            // [END] Radius search
        }

        $this->data['parameters'] = $_POST;
        $parameters = json_encode($_POST);
        // To fetch user_id use: $this->user_id
        
        $this->load->model('savedsearch_m');
        
        // Check if parameters already saved, stop and write message
        if($this->savedsearch_m->check_if_exists($this->user_id, $parameters, $lang_code)>0)
        {
            $this->data['message'] = lang_check('Search already exists!');
        }
        // Save parameters to database
        else
        {
            $data = $this->savedsearch_m->get_new_array();
            $data['user_id'] = $this->user_id;
            $data['parameters'] = $parameters;
            $data['lang_code'] = $lang_code;
            
            // Check if there is some parameters
            $values_exists = false;
            foreach($this->data['parameters'] as $key=>$value){
                if(!empty($value) && $key != 'view' && $key != 'order' && 
                    $key != 'page_num' && $key != 'v_search-start')
                $values_exists = true;
            }
            
            if(!$values_exists)
            {
                $this->data['message'] = lang_check('No values selected!');
                echo json_encode($this->data);
                exit();
            }
            
            $this->savedsearch_m->save($data);
            
            $this->data['message'] = lang_check('Search saved!');
        }
        
        echo json_encode($this->data);
        exit();
    }
    
    public function get_level_values_select($lang_id, $field_id, $parent_id=0, $level=0)
    {
        $this->data['message'] = lang_check('No message returned!');
        $this->data['parameters'] = $_POST;
        $parameters = json_encode($_POST);
        // To fetch user_id use: $this->user_id
        
        $this->load->model('language_m');
        $this->load->model('treefield_m');

        $lang_name = $this->session->userdata('lang');
        if(!empty($lang_id))
            $lang_name = $this->language_m->get_name($lang_id);
            
        $this->lang->load('backend_base', $lang_name);
        
        $values_arr = $this->treefield_m->get_level_values ($lang_id, $field_id, $parent_id, $level);
        
        $generate_select = '';
        foreach($values_arr as $key=>$value)
        {
            $generate_select.= "<option value=\"$key\">$value</option>\n";
        }
        
        $this->data['generate_select'] = $generate_select;
        $this->data['values_arr'] = $values_arr;
        
        echo json_encode($this->data);
        exit();
    }
    
    public function load_reservations($property_id)
    {
        $this->data['message'] = lang_check('No message returned!');
        $this->data['parameters'] = $_POST;
        $parameters = json_encode($_POST);
        // To fetch user_id use: $this->user_id
        
        $this->load->model('reservations_m');
        
        $existing_dates = array();
        $query = $this->db->get_where('reservations', array('property_id' => $property_id, 'date_to >' => date('Y-m-d 00:00:00')));
        if ($query->num_rows() > 0)
        {
            foreach ($query->result() as $row)
            {
                /* [get days] */
                $days_between = $this->reservations_m->days_between($row->date_from, $row->date_to);
                
                $days = array();
                for($i=0; $i < $days_between;  $i++)
                {
                    $row_time = strtotime($row->date_from." + $i day");
                    $row_time_00 = date("Y-m-d", $row_time);
                    $existing_dates[$row_time_00] = $row_time_00;
                }
                /* [/get days] */
            }
        }
        
        $this->data['existing_dates'] = $existing_dates;     

        echo json_encode($this->data);
        exit();
    }
    
    public function property_exists($lang_code='')
    {
        $this->load->model('estate_m');
        $this->load->model('removedlistings_m');
        
        $this->data['message'] = lang_check('No message returned!');
        $this->data['parameters'] = $_POST;
        // To fetch user_id use: $this->user_id

        $this->load->model('settings_m');
        
        $this->data['success'] = false;
        $this->data['exists'] = true;
        $this->data['removed'] = true;
        $this->data['removed_list'] = array();
        
        if(empty($this->data['parameters']['address']) || empty($this->data['parameters']['gps']))
        {}
        else
        {
            $address = $this->data['parameters']['address'];
            $gps = explode(', ', $this->data['parameters']['gps']);
            $lat = floatval($gps[0]);
            $lng = floatval($gps[1]);
            
            $id=0;
            if(isset($this->data['parameters']['id']))
                $id = $this->data['parameters']['id'];
            
            $listings_similar = $this->estate_m->get_similar($address, $lat, $lng, array(), $id);
            //$this->data['similar_query'] = $this->db->last_query();
            
            $listings_removed = $this->removedlistings_m->get_similar($address, $lat, $lng, array());

            if($listings_similar === NULL)
            {
                $this->data['exists'] = false;
            }
            
            if($listings_removed === NULL)
            {
                $this->data['removed'] = false;
            }
            else
            {
                $this->data['removed_list'] = $listings_removed;
            }
            
            if($listings_similar === NULL && $listings_removed === NULL)
                $this->data['success']=TRUE;
            
        }

        echo json_encode($this->data);
        exit();
    }
    

    function parse_svg_map($file_name= NULL) {
        if($file_name == NULL) return false;
            
        // Fetch settings
        $this->load->model('settings_m');
        $settings= $this->settings_m->get_fields();
        
        $this->data = array();
        $this->data['success'] = false;
        $region_names = array();
        
        if(file_exists(FCPATH.'templates/'.$settings['template'].'/assets/svg_maps/'.$file_name)){
            $svg = file_get_contents(FCPATH.'templates/'.$settings['template'].'/assets/svg_maps/'.$file_name);
            $region_names = array();
            $match = '';
            preg_match_all('/(data-title-map)=("[^"]*")/i', $svg, $match);

            if(!empty($match[2])) {
                preg_match_all('/(data-name)=("[^"]*")/i', $svg, $matches);
                if(!empty($matches[2]))
                    foreach ($matches[2] as $value) {
                       $value = str_replace('"', '', $value);                       
                        $region_names[] = $value;
                    }
            } else if(stristr($svg, "http://amcharts.com/ammap") != FALSE ) {
                preg_match_all('/(title)=("[^"]*")/i', $svg, $matches);
                if(!empty($matches[2]))
                    foreach ($matches[2] as $value) {
                       $value = str_replace('"', '', $value);                       
                        $region_names[] = $value;
                    }
            }   
            
            $match = '';
            $this->data['title_map']='';
            preg_match_all('/(data-title-map)=("[^"]*")/i', $svg, $match);

            if(!empty($match[2])) {
               $this->data['title_map'] = str_replace('"', '', $match[2][0]);
            }
                
                
          $this->data['success'] = true; 
          $this->data['region_names'] = $region_names;
        }    
        
        
        echo json_encode($this->data);
        exit();        
    }
    
    /*
     * For Eventful lib
     * return count_pages
     */
    function eventful_get_count_pages($eventful_category = NULL){
        $this->data['success'] = false; 
        if($eventful_category == NULL)  {
            echo json_encode($this->data);
            exit();      
        }
        
        if(!file_exists(APPPATH.'libraries/Eventful.php') || $this->session->userdata('type')!='ADMIN') {
            echo json_encode($this->data);
            exit(); 
        }
        
        $this->load->library('eventful');
        $result = $this->eventful->get_count_pages($eventful_category);
        
        if($result != FALSE) {
            $this->data['success'] = true; 
            $this->data['eventful_get_count_pages'] = $result; 
            echo json_encode($this->data);
            exit();  
        } else {
            echo json_encode($this->data);
            exit();
        }
    }
    
    /*
     * 
     * Messenger
     * get_dialogs
     */
    
    function get_dialogs ($lang_code='en', $limit = NULL) {
        $this->data['success'] = false;
        /* check if user login */
        if(!file_exists(APPPATH.'libraries/Messenger.php') || !$this->session->userdata('id')) {
            $this->data['success'] = false;
            echo json_encode($this->data);
            exit();
        }
        $this->load->model('enquire_m');
        $this->load->library('messenger');
         
        $post = $_POST;
        $_all_dialogs = $this->messenger->refresh_dialogs($limit, $post['latest_id']);
        $this->data['all_dialogs'] = $this->messenger->_generate_dialogs($_all_dialogs,$lang_code, true);
        $this->data['unreaded_message']= '';
        if(!empty($_all_dialogs)){
            $this->data['latest_id'] = $this->data['all_dialogs'][current(array_keys($this->data['all_dialogs']))]['id'];
            $this->data['success'] = true;
            foreach($this->data['all_dialogs'] as $key => $dialog) {
                $this->data['unreaded_message'] += $dialog['unreaded'];
            }
        }
        
        //echo $this->db->last_query();
        //$this->data['latest_date'] = date('Y-m-d H:i:s');
        
        echo json_encode($this->data);
        exit();
    }
       
    /*
     * 
     * Messenger
     * get_messages
     */
    
    function get_dialog ($lang_code='en') {
        $this->data['success'] = false;
        /* check if user login */
        if(!file_exists(APPPATH.'libraries/Messenger.php') || !$this->session->userdata('id')) {
            $this->data['success'] = false;
            echo json_encode($this->data);
            exit();
        }
        
        $this->load->model('enquire_m');
        $this->load->library('messenger');
        
        $post = $_POST;
        $_dialog = $this->messenger->refresh_messages_by_dialog($post['sel_id'], $post['property_id'], $post['latest_id']);
        $this->data['dialog'] = $this->messenger->_generate_diolog($_dialog, $lang_code, true);
        
         if(!empty($this->data['dialog'])) {
            $this->data['speakers'] = $this->messenger->_generate_speakers($post['sel_id'], $lang_code, true);
            $this->data['latest_id'] = $this->data['dialog'][end(array_keys($this->data['dialog']))]->id;
         }
        
        if(!empty($this->data['dialog']))
            $this->data['success'] = true;
        
        echo json_encode($this->data);
        exit();
        
    }
    
    function save_message ($lang_code='en') {
        
        /* check if user login */
        if(!file_exists(APPPATH.'libraries/Messenger.php') || !$this->session->userdata('id')) {
            $this->data['success'] = false;
            echo json_encode($this->data);
            exit();
        }
        
        $user_id = $this->session->userdata('id');
        $user = $this->user_m->get($user_id);
        
        $this->load->model('enquire_m');
        $this->data['success'] = false;
        
        $post = $_POST;
        
        $data = array();
        $data['name_surname'] = $user->name_surname;
        $data['phone'] =$user->phone;
        $data['mail'] = $user->mail;
        $data['message'] = $post['message'];
        $data['property_id'] = $post['property_id'];
        $data['address'] = $post['address'];
        $data['readed'] = 0;
        $data['from_id'] = $this->session->userdata('id');
        $data['to_id'] = $post['to_id'];
        $data['date'] = date('Y-m-d H:i:s');
        
        $this->enquire_m->save($data);
        
        $this->data['success'] = true;
        echo json_encode($this->data);
        exit();
        
    }
    
    function readed ($lang_code='en') {
        /* check if user login */
        if(!file_exists(APPPATH.'libraries/Messenger.php') || !$this->session->userdata('id')) {
            $this->data['success'] = false;
            echo json_encode($this->data);
            exit();
        }
        
        $user_id = $this->session->userdata('id');
        
        $this->load->model('enquire_m');
        $this->data['success'] = false;
        
        $post = $_POST;
        
        $this->enquire_m->readed(null, $post['sel_id'], $post['property_id']);
        
        $this->data['success'] = true;
        echo json_encode($this->data);
        exit();
        
    }
    
    
}