<?php
require_once ("secure_area.php");
require_once ("interfaces/idata_controller.php");

class Productions extends Secure_area implements iData_controller {

function __construct() { parent::__construct('production'); }
function get_form_width() { return 400; }
function index()   { /* Hayoooo... opo o koq kosong... */ }
function get_row() { /* Hayoooo... opo o koq kosong... */ }

public function paket_sofa ($item_kit_id=-1){
  $data['paket_info'] = $this->get_info($item_kit_id); 
  $this->load->view("productions/paket_sofa/manage", $data); 
}

public function prod_sofa ($item_kit_id=-1) {
  $data['prod_info'] = $this->get_info_produksi($item_kit_id); 
  $data['title']     = 'Produksi Sofa';
  $data['status']    = array (0 => 'Proses', 1 => 'Done');
  $data['gudang']    = $this->gudang();
  $this->load->view("productions/manage", $data);
}

public function prod_hpl ($item_kit_id=-1) {
  $data['prod_info'] = $this->get_info_produksi($item_kit_id); 
  $data['title']     = 'Produksi HPL';
  $data['status']    = array (0 => 'Proses', 1 => 'Done');
  $data['gudang']    = $this->gudang();
  $this->load->view("productions/manage", $data);
}

public function waste ($item_kit_id=-1) {
  $data['prod_info'] = $this->get_info_produksi($item_kit_id); 
  $data['title']     = 'Produk Rekondisi';
  $data['status']    = array (0 => 'Proses', 1 => 'Done');
  $data['gudang']    = $this->gudang();
  $this->load->view("productions/manage", $data);
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

function view($item_kit_id=-1) { 
  $data['paket_info'] = $this->get_info($item_kit_id); 
  $this->load->view("productions/paket_sofa/manage", $data); 
}

function save($item_kit_id=-1) {
  $item_kit_data = array(
    'name' => $this->input->post('name'),
    'description' => $this->input->post('description')
  );
  //echo $this->input->post('item_kit_item')."<br>";
  //var_dump($_POST['item_kit_item']);
  
  if ($this->saveK($item_kit_data, $item_kit_id)) {
    
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
    
    if ($this->input->post('item_kit_item')) {
    	$item_kit_items = array();
      foreach($this->input->post('item_kit_item') as $item_id => $quantity)	{
      $item_kit_items[] = array(
        'paket_id' => $item_kit_data['paket_id'],
        'item_id'  => $item_id,
        'quantity' => $quantity
      ); }
    	$this->saveK($item_kit_items, $item_kit_id);
    }
  
  // Failure condition
  } else echo "Error...!!!";
}

function save_produksi ($item_kit_id=-1) {
  $item_kit_data = array(
    'noSurat'   => $this->input->post('noSurat'),
    'nama'      => $this->input->post('nama'),
    'tanggal'   => $this->input->post('tanggal'),
    'gudang'    => $this->input->post('gudang'),
    'btsWaktu'  => $this->input->post('btsWaktu'),
    'status'    => $this->input->post('status')
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

	if ($this->Item_kit->delete_list($item_kits_to_delete))
	{
		echo json_encode(array('success'=>true,
							'message'=>$this->lang->line('item_kits_successful_deleted').' '.count($item_kits_to_delete).' '.$this->lang->line('item_kits_one_or_multiple')));
	}
	else
	{
		echo json_encode(array('success'=>false,
							'message'=>$this->lang->line('item_kits_cannot_be_deleted')));
	}
}

// Fungsi ambil dari model Production
function get_info($paket_id) {
	$this->db->from('paket_sofa');
	$this->db->where('paket_id', $paket_id);
	$query = $this->db->get();

	if($query->num_rows()==1) return $query->row();
	else {
    
    $item_obj = new stdClass();                                //Get empty base parent object, as $paket_id is NOT an item kit
    $fields = $this->db->list_fields('paket_sofa');            //Get all the fields from items table
    
    foreach ($fields as $field) $item_obj->$field = '';
    return $item_obj;
	}
} 

function saveK(&$paket_data, $paket_id=false) {
   if (!$paket_id or !$this->exists($paket_id)) {
  	if($this->db->insert('paket_sofa', $paket_data)) {
      $paket_data['paket_id'] = $this->db->insert_id();
      return true;
  	} return false;
  } 
  
  $this->db->where('paket_id', $paket_id);
  foreach ($paket_data as $paket) $this->db->insert('paket_sofa_items', $paket);
}

function exists($paket_id)  {
  $this->db->from('paket_sofa');
  $this->db->where('paket_id', $paket_id);
    
  return ($this->db->get()->num_rows()==1);
}

function get_info_produksi($noSurat) {
	$this->db->from('produksi');
	$this->db->where('noSurat', $noSurat);
	$query = $this->db->get();

	if($query->num_rows()==1) return $query->row();
	else {
    $item_obj = new stdClass();                                //Get empty base parent object, as $paket_id is NOT an item kit
    $fields = $this->db->list_fields('produksi');              //Get all the fields from items table
    
    foreach ($fields as $field) $item_obj->$field = '';
    return $item_obj;
	}
}

function noSurat() {
  $suggestions = array();
  $response    = array();
  $this->db->select('noFaktur, tglFaktur, ospos_people.first_name as first, ospos_people.last_name as last, ospos_people.person_id as person_id ');
  $this->db->from('pembelian_id');
  $this->db->join('ospos_people', 'ospos_pembelian_id.pelanggan =  ospos_people.person_id');
  $this->db->where('supplier', '17');
  $this->db->where('locked', '1');
  $suggestions = $this->db->get();
  
  /*
  $this->db->select('ospos_pembelian_id.noFaktur  as noFaktur, tglFaktur, ospos_people.first_name as first, ospos_people.last_name as last, ospos_people.person_id as person_id, ospos_pembelian.no as noBeli, ospos_pembelian.item_id as item_id, ospos_pembelian.jumlah as jumlah, ospos_pembelian.harga as harga, ospos_pembelian.diskon as diskon');
  $this->db->from('pembelian_id');
  $this->db->join('ospos_people', 'ospos_pembelian_id.pelanggan =  ospos_people.person_id');
  $this->db->join('ospos_pembelian', 'ospos_pembelian_id.noFaktur = ospos_pembelian.noFaktur');
  $this->db->where('supplier', '17');
  $this->db->where('locked', '1');
  $suggestions = $this->db->get(); */
  
  if($suggestions->num_rows() > 0) foreach($suggestions->result() as $suggest) {
  $response[] = array(
  'value'     => $suggest->noFaktur,
  'tanggal'   => $suggest->tglFaktur,
  'nama'      => $suggest->first." ".$suggest->last, 
  'person_id' => $suggest->person_id
  ); }
  echo json_encode($response);
}

function itemDetail($a) {
  $suggestions = array();
  $this->db->select('ospos_pembelian_id.noFaktur  as noFaktur, ospos_pembelian.no as noBeli, ospos_pembelian.item_id as item_id, ospos_pembelian.jumlah as jumlah, ospos_pembelian.harga as harga, ospos_pembelian.diskon as diskon');
  $this->db->from('pembelian_id');
  $this->db->join('ospos_pembelian', 'ospos_pembelian_id.noFaktur = ospos_pembelian.noFaktur');
  $this->db->where('ospos_pembelian_id.noFaktur', $a);
  $suggestions = $this->db->get();
  
  $x=0;
  echo "
  <table><thead><tr>
  <th style=\"width:30%\;\">Nama Barang</th>
  <th style=\"width:11%\;\">Harga</th>
  <th style=\"width:5%\;\">Jumlah</th>
  <th style=\"width:5%\;\">Satuan</th>
  <th style=\"width:11%\;\">Diskon</th>
  <th style=\"width:15%\;\">Total</th>
  </tr></thead>";
  if($suggestions->num_rows() > 0) foreach($suggestions->result() as $suggest) {
    $x += ($suggest->harga*$suggest->jumlah);
    echo "<tr><td>".$suggest->item_id."</td><td align=\"right\">".$suggest->harga."</td><td>".$suggest->jumlah."</td><td></td><td>".$suggest->diskon."</td><td align=\"right\">".$suggest->harga*$suggest->jumlah."</td></tr>";
  } echo "<tr><td colspan=4><b>Total</b><td><td>".$x."</td></tr>";
}

function gudang() {
  $option = array();
  $q = $this->Stock_location->get_all();
  foreach ($q->result_array() as $row) $option[$row['location_id']] = $row['location_name'];
  return $option;
}

}
?>