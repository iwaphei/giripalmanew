<?php
require_once ("secure_area.php");
require_once ("interfaces/idata_controller.php");

class Pembelian extends Secure_area implements iData_controller {

function get_form_width() { return 400; }

function __construct() { 
  parent::__construct('pembelian');
  $this->load->library('sale_lib');
}

function index($item_kit_id=-1, $id=0) {
  $today = getdate();
  //$supplier = $this->get_supplier();
  $data['pembelian_info']          = $this->get_info($item_kit_id); 
  $data['title']                   = 'Info Pembelian';
  $data['faktur']                  = $this->autoGenerate();
  $data['tanggal']                 = $today['year'].'/'.$this->generateFaktur($today['mon']).'/'.$this->generateFaktur($today['mday']);
  $data['supplier']                = $this->get_supplier();
  $data['locked']                  = '1';
  switch ($id) {
    case 1: $data['pembelian'] = 'Sukses masukkan data faktur'; break;
    case 2: $data['pembelian'] = 'Error...'; break;
  } $this->load->view("pembelian/manage", $data);
  
}

function search()	{
  $search         = $this->input->post('search');
  $limit_from     = $this->input->post('limit_from');
  $lines_per_page = $this->Appconfig->get('lines_per_page');
  $item_kits      = $this->Item_kit->search($search, $lines_per_page, $limit_from);
  $total_rows     = $this->Item_kit->get_found_rows($search);
  $links          = $this->_initialize_pagination($this->Item_kit, $lines_per_page, $limit_from, $total_rows, 'search');
  
  // calculate the total cost and retail price of the Kit so it can be printed out in the manage table
  foreach($item_kits->result() as $item_kit) $item_kit = $this->add_totals_to_item_kit($item_kit);
  
  $data_rows = get_item_kits_manage_table_data_rows($item_kits, $this);
  $this->_remove_duplicate_cookies();
  
  echo json_encode(array(
    'total_rows' => $total_rows, 
    'rows'       => $data_rows, 
    'pagination' => $links));
}

/*  Gives search suggestions based on what is being searched for  */
function suggest() {
  $suggestions = $this->Item_kit->get_search_suggestions($this->input->post('q'), $this->input->post('limit'));
  echo implode("\n", $suggestions);
}

function get_row(){
  $item_kit_id = $this->input->post('row_id');
  
  // calculate the total cost and retail price of the Kit so it can be added to the table refresh
  $item_kit = $this->add_totals_to_item_kit($this->Item_kit->get_info($item_kit_id));
  
  echo (get_item_kit_data_row($item_kit, $this));
  $this->_remove_duplicate_cookies();
}

function view($item_kit_id=-1) { 
  $data['paket_info'] = $this->get_info($item_kit_id); 
  $this->load->view("productions/paket_sofa/manage", $data); 
}

function save($item_kit_id=-1) {
  $item_kit_data = array(
    'name' => $this->input->post('name'),
    'description' => $this->input->post('description')
  );
  
  if ($this->Item_kit->save($item_kit_data, $item_kit_id)) {
    
    //New item kit
    if ($item_kit_id==-1) {
      $item_kit_id = $item_kit_data['paket_id'];
      
      echo json_encode(array('success'=>true,
        'message'=>$this->lang->line('item_kits_successful_adding').' '.$item_kit_data['name'],
        'paket_id'=>$item_kit_id));
    
    // Previous item
    } else {
    	echo json_encode(array('success'=>true, 
        'message'=>$this->lang->line('item_kits_successful_updating').' '.$item_kit_data['name'],
        'paket_id'=>$item_kit_id));
    }
    
    if ($this->input->post('paket_sofa_item')) {
    	$item_kit_items = array();
      foreach($this->input->post('paket_sofa_item') as $item_id => $quantity)	{
        $item_kit_items[] = array(
          'item_id' => $item_id,
          'quantity' => $quantity
        );
      }
    
    	$this->Paket_sofa->save($item_kit_items, $item_kit_id);
    }
  
  // Failure condition
  } else {
    echo json_encode(array('success'=>false, 
      'message'=>$this->lang->line('item_kits_error_adding_updating').' '.$item_kit_data['name'],
      'paket_id'=>-1));
  }
}

function delete() {
	$item_kits_to_delete = $this->input->post('ids');

	if ($this->Item_kit->delete_list($item_kits_to_delete)) {
		echo json_encode(array('success'=>true,
							'message'=>$this->lang->line('item_kits_successful_deleted').' '.count($item_kits_to_delete).' '.$this->lang->line('item_kits_one_or_multiple')));
	}	else {
		echo json_encode(array('success'=>false,
							'message'=>$this->lang->line('item_kits_cannot_be_deleted')));
	}
}

function customer_search() {
  $suggestions = array();
  $response    = array();
  $this->db->select('first_name, last_name, ospos_people.person_id as person_id');
  $this->db->from('customers');
  $this->db->join('people', 'ospos_people.person_id = ospos_customers.person_id');
  $this->db->order_by('person_id', 'asc');
  $suggestions = $this->db->get();
  
  if($suggestions->num_rows() > 0) foreach($suggestions->result() as $suggest){
  $response[] = array(
    'value' => $suggest->first_name.' '.$suggest->last_name);
  } echo json_encode($response);
}

function item_search() {
  $suggestions = array();
  
  if ($this->sale_lib->get_mode() == 'return') $this->sale_lib->is_valid_receipt($this->input->get('term')) && $suggestions[] = $this->input->get('term');
  
  // first, search item
  $suggestions = $this->Item->get_item_search_suggestions($this->input->get('term'),$this->input->get('limit'));
  
  $response = array();
  if($suggestions->num_rows() > 0) foreach($suggestions->result() as $suggest){
      $response[] = array(
        'value'     => $suggest->item_number.', '.$suggest->name,
        'id'        => $suggest->item_id,
        'name'      => $suggest->name,
        'category'  => $suggest->category,
        'price'     => $suggest->cost_price,
        'satuan'    => $suggest->satuan );
  }
    
  // second, search item kits
  $suggestions = $this->Item_kit->get_item_kit_search_suggestions($this->input->get('term'),$this->input->get('limit'));
  if($suggestions->num_rows() > 0) foreach($suggestions->result() as $suggest){
    $response[] = array(
    'value' => 'KIT '.$suggest->name,
    'id'    => 'KIT '.$suggest->item_kit_id,
    'name'  => $suggest->name );
  }
  
  echo json_encode($response);
}

function get_info($paket_id) {
	$this->db->from('pembelian');
	$this->db->where('noFaktur', $paket_id);
	$query = $this->db->get();

	if($query->num_rows()==1) return $query->row();
	else {
    $item_obj = new stdClass();                         // Get empty base parent object, as $paket_id is NOT an item kit
    $fields = $this->db->list_fields('pembelian');      // Get all the fields from items table
    
    foreach ($fields as $field) $item_obj->$field = '';
    return $item_obj;
	}
} 

function get_supplier() {
  $this->db->from('suppliers');	
  $this->db->where('deleted', 0);
  $query = $this->db->get(); 
  $option = array(0=>'stock');
  
  foreach($query->result_array() as $a) $option[$a['person_id']] = $a['company_name'];
  return $option;  
}

function add() {
  $dataFaktur = array();
  $dataFaktur['noFaktur']   = $this -> input -> post('noFakturH');
  $dataFaktur['tglFaktur']  = $this -> input -> post('tglFakturH');
  $dataFaktur['supplier']   = $this -> input -> post('supplier');
  $dataFaktur['keterangan'] = $this -> input -> post('keterangan');
  $dataFaktur['pelanggan']  = $this -> input -> post('pelangganH');
  $dataFaktur['locked']     = $this -> input -> post('locked');
  /*
  if ($this->db->insert('pembelian_id', $dataFaktur)) {
    $this->db->from('counter');
    $query = $this->db->get();
    $this->counter($query->row('counter')+1, 0);
    $this->index(-1,1);
  } else $this->index(-1,2);*/
  
  $data = array();
  foreach ($this -> input -> post('jumlah') as $id => $x) { $data[1][] = array( 'jumlah' => $x ); }
  foreach ($this -> input -> post('harga')  as $id => $x) { $data[2][] = array( 'harga' => $x ); }
  foreach ($this -> input -> post('diskon') as $id => $x) { 
    if ($x == NULL || $x == 0) $data[3][] = array( 'diskon' => '0' ); 
    else $data[3][] = array( 'diskon' => $x ); 
    $data[4][] = array( 'id' => $id ); }

  $a=0;
  while ($a < count($data[1])) {
  $data[0][$a] = array(
    'noFaktur' => $dataFaktur['noFaktur'],
    'item_id'  => $data[4][$a]['id'], 
    'jumlah'   => $data[1][$a]['jumlah'],
    'harga'    => $data[2][$a]['harga'],
    'diskon'   => $data[3][$a]['diskon']);
  $a++;
  }
  var_dump($_POST);
  //print_r($dataFaktur);
  //foreach($data[0] as $detail)  $this->db->insert('pembelian', $detail);
}

function autoGenerate() {
  $today = getdate();
  $noFaktur = $today['year'];
  if ($today['mon'] < 10)  $noFaktur .= '0'.$today['mon'];  else $noFaktur .= $today['mon'];
  if ($today['mday'] < 10) $noFaktur .= '0'.$today['mday']; else $noFaktur .= $today['mday'];
  
  $this->db->from('counter');
  $query = $this->db->get();
  
  if ($query->row('tanggal') == $today['mday']) $noFaktur .= $this->generateFaktur($query->row('counter'));
  else {
    $noFaktur .= "01";
    $this->counter(0, $today['mday']);
  } return $noFaktur;
}

function generateFaktur($a) {
  $num_length = strlen((string)$a);
  switch ($num_length) {
    case 1 : $x = '0'.$a; break;
    case 2 : $x = $a; break;
  } return $x;
}

function counter($counter, $tanggal) {
  $this->db->from('counter');
  $this->db->where('no', 0);
  if ($tanggal == 0) $this->db->set('counter', $counter);
  else {
  $this->db->set('tanggal', $tanggal);
  $this->db->set('counter', 1);
  } $this->db->update('counter');
}

}
?>