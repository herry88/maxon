<?php if(!defined('BASEPATH')) exit('No direct script access allowd');

class Purchase_invoice extends CI_Controller {
    private $limit=10;
    private $sql="select purchase_order_number,i.terms,po_date,amount,i.posted, 
            i.supplier_number,c.supplier_name,c.city,i.warehouse_code
            from purchase_order i
            left join suppliers c on c.supplier_number=i.supplier_number
            where i.potype='I'";
    private $controller='purchase_invoice';
    private $primary_key='purchase_order_number';
    private $file_view='purchase/purchase_invoice';
    private $table_name='purchase_order';
	function __construct()
	{
		parent::__construct();
		if(!$this->access->is_login())redirect(base_url());
 		$this->load->helper(array('url','form','browse_select','mylib_helper'));
        $this->load->library('sysvar');
        $this->load->library('javascript');
        $this->load->library('template');
		$this->load->library('form_validation');
		$this->load->model('purchase_invoice_model');
		$this->load->model('supplier_model');
		$this->load->model('inventory_model');
		$this->load->model('type_of_payment_model');
		$this->load->model('syslog_model');
		 
	}
	function set_defaults($record=NULL){
            $data=data_table($this->table_name,$record);
            $data['mode']='';
            $data['message']='';
			$data['purchase_order_number']=$this->nomor_bukti();
            $data['po_date']= date("Y-m-d");
			$data['summary_info']='';
            return $data;
	}
	function index()
	{	
		if(!allow_mod2('_40130'))return false;
        $this->browse();
	}
	function get_posts(){
            $data=data_table_post($this->table_name);
            return $data;
	}
	function nomor_bukti($add=false)
	{
		$key="Purchase Invoice Numbering";
		if($add){
		  	$this->sysvar->autonumber_inc($key);
		} else {			
			$no=$this->sysvar->autonumber($key,0,'!PI~$00001');
			for($i=0;$i<100;$i++){			
				$no=$this->sysvar->autonumber($key,0,'!PI~$00001');
				$rst=$this->purchase_invoice_model->get_by_id($no)->row();
				if($rst){
				  	$this->sysvar->autonumber_inc($key);
				} else {
					break;					
				}
			}
			return $no;
		}
	}

	function add()
	{
		if(!allow_mod2('_40131'))return false;
	 	$data=$this->set_defaults();
		$this->_set_rules();
		$data['mode']='add';
		$data['message']='';
        $data['supplier_number']='';
        $data['po_date']= date("Y-m-d");
        $data['potype']='I';
        $data['amount']=0;
		$data['posted']=false;
		$data['closed']=false;
        $data['supplier_info']=$this->supplier_model->info($data['supplier_number']);
        $data['terms_list']=$this->type_of_payment_model->select_list();
		$this->template->display_form_input($this->file_view,$data,'');			                 
	}
	function save(){
		$mode=$this->input->post('mode');
        $data['potype']='I';
		if($mode=="add"){
	        $id=$this->nomor_bukti();
		} else {
			$id=$this->input->post('purchase_order_number');			
		}
		$data['purchase_order_number']=$id;
		$data['po_date']=$this->input->post('po_date');
        $data['supplier_number']=$this->input->post('supplier_number');
        $data['terms']=$this->input->post('terms');
        $data['due_date']=$this->input->post('due_date');
        $data['comments']=$this->input->post('comments');
		if($mode=="add"){
			$ok=$this->purchase_invoice_model->save($data);
			$this->syslog_model->add($id,"purchase_invoice","add");

		} else {
			$ok=$this->purchase_invoice_model->update($id,$data);			
			$this->syslog_model->add($id,"purchase_invoice","edit");
		}
		if ($ok){
			if($mode=="add") $this->nomor_bukti(true);
			echo json_encode(array('success'=>true,'purchase_order_number'=>$id,"msg"=>mysql_error()));
		} else {
			echo json_encode(array('msg'=>'Some errors occured.'.mysql_error()));
		}
	}
	function items($nomor,$type='')
	{
		$nomor=urldecode($nomor);
		$sql="select p.item_number,i.description,p.quantity 
		,p.unit,p.price,p.discount,p.total_price,p.line_number,
		p.disc_2,p.disc_3
		from purchase_order_lineitems p
		left join inventory i on i.item_number=p.item_number
		where purchase_order_number='$nomor'";
		 
		echo datasource($sql);
	}
	
	function update()
	{
		 $data=$this->set_defaults();
		 $this->_set_rules();
 		 $id=$this->input->post('purchase_order_number');
		 if ($this->form_validation->run()=== TRUE){
			$data=$this->get_posts();
			$this->purchase_invoice_model->update($id,$data);
			//simpan juga ke table payables
			//this->payables_model->update($id,$data);
            $message='Update Success';
			$this->syslog_model->add($id,"purchase_invoice","edit");

		} else {
			$message='Error Update';
		}
                
 		$this->view($id,$message);		
	}
	 
        
	function view($id,$message=null){
		if(!allow_mod2('_40130'))return false;
		$id=urldecode($id);
		 $data['id']=$id;
		 $this->purchase_invoice_model->recalc($id);
		 $model=$this->purchase_invoice_model->get_by_id($id)->row();
		 $data=$this->set_defaults($model);
		 $data['id']=$id;
		 $data['purchase_order_number']=$id;
		 $data['mode']='view';
         $data['message']=$message;
         $data['supplier_info']=$this->supplier_model->info($data['supplier_number']);
         $data['terms_list']=$this->type_of_payment_model->select_list();
		 $data['summary_info']=$this->summary_info($id);
		 $data['has_payment']=$this->purchase_invoice_model->has_payment($id);
		 $data['has_retur']=$this->purchase_invoice_model->has_retur($id);
		 $data['has_memo']=$this->purchase_invoice_model->has_memo($id);
		 if($model) {
			$data['posted']=$model->posted;
		} else {
			$data['posted']=false;
		}
		  
		 
		 $this->load->model('periode_model');
		 $data['closed']=$this->periode_model->closed($data['po_date']);
		 
		 
         $left='purchase/menu_purchase_invoice';
		 $this->session->set_userdata('_right_menu',$left);
         $this->session->set_userdata('purchase_order_number',$id);
		 
         $this->template->display('purchase/purchase_invoice',$data);
                 
	}
   
	function _set_rules(){	
		 $this->form_validation->set_rules('purchase_order_number','Nomor Faktur', 'required|trim');
		 $this->form_validation->set_rules('po_date','Tanggal','callback_valid_date');
	}
	 
	function valid_date($str)
	{
	 if(!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/',$str))
	 {
		 $this->form_validation->set_message('valid_date',
		 'Format tanggal salah, seharusnya yyyy-mm-dd');
		 return false;
	 } else {
	 	return true;
	 }
	}
    function browse($offset=0,$limit=50,$order_column='purchase_order_number',$order_type='asc'){
		$data['controller']=$this->controller;
		$data['fields_caption']=array('Nomor Faktur','Tanggal','Jumlah','Posted','Kode Supplier','Nama Supplier','Kota','Gudang');
		$data['fields']=array('purchase_order_number','po_date','amount','posted', 
                'supplier_number','supplier_name','city','warehouse_code');
		$data['field_key']='purchase_order_number';
		$data['caption']='DAFTAR FAKTUR PEMBELIAN';
		$data['posting_visible']=true;

		$this->load->library('search_criteria');
		
		$faa[]=criteria("Dari","sid_date_from","easyui-datetimebox");
		$faa[]=criteria("S/d","sid_date_to","easyui-datetimebox");
		$faa[]=criteria("Nomor PO","sid_po_number");
		$faa[]=criteria("Supplier","sid_supplier");
		$faa[]=criteria("Posted","sid_posted");

		$data['criteria']=$faa;
        $this->template->display_browse2($data);            
    }
    function browse_data($offset=0,$limit=10,$nama=''){
    	if($this->input->get('sid_po_number')!=''){
    		$sql=$this->sql." and purchase_order_number='".$this->input->get('sid_po_number')."'";
		} else {
			$d1= date( 'Y-m-d H:i:s', strtotime($this->input->get('sid_date_from')));
			$d2= date( 'Y-m-d H:i:s', strtotime($this->input->get('sid_date_to')));
			$sql=$this->sql." and po_date between '".$d1."' and '".$d2."'";
			if($this->input->get('sid_supplier')!='')$sql.=" and supplier_name like '".$this->input->get('sid_supplier')."%'";
			if($this->input->get('sid_posted')!=''){
				if($this->input->get('sid_posted')=='1'){
					$sql.=" and posted=true";
				} else {
					$sql.=" and (posted=false or posted is null)";				
				}
			}
		}
        echo datasource($sql);
    }	 
	function delete($id){
		if(!allow_mod2('_40133'))return false;
		$id=urldecode($id);
		$this->load->model('jurnal_model');
		$bill=$this->purchase_invoice_model->get_bill_id($id);
		$cnt_pay=$this->db->query("select count(1) as cnt from payables_payments where bill_id=".$bill)->row()->cnt;
		if($cnt_pay){
			echo json_encode(array("success"=>false,"msg"=>"Gagal hapus nomor ini. <br>Karena masih ada pembayaran."));
		} elseif ($this->amount_retur($id)>0){
			echo json_encode(array("success"=>false,"msg"=>"Gagal hapus nomor ini. <br>Karena masih ada nomor retur."));
		} elseif ($this->amount_crdb($id)>0){
			echo json_encode(array("success"=>false,"msg"=>"Gagal hapus nomor ini. <br>Karena masih ada nota kredit."));
		} elseif ($this->jurnal_model->get_by_id($id)){
			echo json_encode(array("success"=>false,"msg"=>"Gagal hapus nomor ini. <br>Karena sudah ada jurnal."));
		} else {
			$this->db->query("delete from payables_items where bill_id=".$bill);
			$this->db->query("delete from payables where bill_id=".$bill);
			$this->purchase_invoice_model->delete($id);
			echo json_encode(array("success"=>true,"msg"=>"Berhasil hapus nomor ini."));
		}
		$this->syslog_model->add($id,"purchase_invoice","delete");

	}
	function detail(){
		$data['purchase_order_number']=isset($_GET['purchase_order_number'])?$_GET['purchase_order_number']:'';
		$data['po_date']=isset($_GET['po_date'])?$_GET['po_date']:'';
		$data['supplier_number']=isset($_GET['supplier_number'])?$_GET['supplier_number']:'';
		$data['comments']=isset($_GET['comments'])?$_GET['comments']:'';
		$data['potype']='I';
		$data['terms']=isset($_GET['terms'])?$_GET['terms']:'';
		$this->purchase_invoice_model->save($data);
		$this->sysvar->autonumber_inc("Purchase Invoice Numbering");
		$data['supplier_info']=$this->supplier_model->info($data['supplier_number']);            
		$this->template->display('purchase/purchase_invoice_detail',$data);
	}
	function view_detail($nomor){
		$nomor=urldecode($nomor);
		$this->load->model('purchase_order_lineitems_model');
		echo $this->purchase_order_lineitems_model->browse($nomor);
    }
        function add_item(){            
            if(isset($_GET)){
                $data['purchase_order_number']=$_GET['purchase_order_number'];
            } else {
                $data['purchase_order_number']='';
            }
           $this->load->model('inventory_model');
           $data['item_lookup']=$this->inventory_model->item_list();
            $this->load->view('purchase/purchase_invoice_add_item',$data);
        }   
        function save_item(){ 
            $this->load->model('purchase_order_lineitems_model');
            $item_no=$this->input->post('item_number');
            $data['purchase_order_number']=$this->input->post('purchase_order_number');
            $data['item_number']=$item_no;
            $data['quantity']=$this->input->post('quantity');
            $data['description']=$this->inventory_model->get_by_id($data['item_number'])->row()->description;
            $data['unit']=$this->input->post('unit');
            $data['price']=$this->input->post('price');
            $data['total_price']=$data['quantity']*$data['price'];
            $this->purchase_order_lineitems_model->save($data);
        }        
        function delete_item($id){
			$id=urldecode($id);
            $this->load->model('purchase_order_lineitems_model');
            return $this->purchase_order_lineitems_model->delete($id);
        }        
        function print_faktur($nomor){
			$nomor=urldecode($nomor);
            $invoice=$this->purchase_invoice_model->get_by_id($nomor)->row();
			$saldo=$this->purchase_invoice_model->recalc($nomor);
			$data['po_number']=$invoice->purchase_order_number;
			$data['tanggal']=$invoice->po_date;
			$data['supplier']=$invoice->supplier_number;
			$data['terms']=$invoice->terms;
			$data['amount']=$invoice->amount;
			$data['sub_total']=$invoice->subtotal;
			$data['discount']=$invoice->discount;
			$data['disc_amount']=$invoice->subtotal*$invoice->discount;
			$data['freight']=$invoice->freight;
			$data['others']=$invoice->other;
			$data['tax']=$invoice->tax;
			$data['tax_amount']=$invoice->tax*($data['sub_total']-$data['disc_amount']);
			$data['comments']=$invoice->comments;
			$data['content']=load_view('purchase/print_faktur',$data);
			$this->load->view('pdf_print',$data);
        }
        function summary_info($nomor){
			$nomor=urldecode($nomor);
            $saldo=$this->purchase_invoice_model->recalc($nomor);
            return "<table class='table'><tr><td>Jumlah Faktur: Rp. ".  number_format($this->purchase_invoice_model->amount)
				."</td><tr><td>Jumlah Bayar : Rp. ".  number_format($this->purchase_invoice_model->amount_paid)
				."</td><tr><td>Jumlah Retur  : Rp. ".  number_format($this->purchase_invoice_model->retur_amount($nomor))
				."</td><tr><td>Jumlah CrDb Memo  : Rp. ".  number_format($this->purchase_invoice_model->crdb_amount($nomor))
				."</td><tr><td>Jumlah Sisa  : Rp. ".  number_format($saldo) . "</td>
				</table>
				";            
        }
        function payments($purchase_order_number)
        {
			$purchase_order_number=urldecode($purchase_order_number);
        	 $model=$this->purchase_invoice_model->get_by_id($purchase_order_number)->row();
			 $data=$this->set_defaults($model);
	         $data['supplier_info']=$this->supplier_model->info($data['supplier_number']);
	         $this->template->display('purchase/payments',$data);
        }
/*
		function add_payment($purchase_order_number)
		{
			$url='payables_payments/add?purchase_order_number='.$purchase_order_number;
			$result=file_get_contents(site_url($url));
			echo $result;
		}
		function save_payment()
		{
		 
			var_dump($_POST);
			 
			
		}
 *  * 
 */
		function delete_payment($purchase_order_number)
		{
			
		}
		function list_payment($purchase_order_number)
		{
//			$this->load->model('payables_payments_model');
//			echo $this->payables_payments_model->browse($purchase_order_number);	
			
			$purchase_order_number=urldecode($purchase_order_number);
			$bill=$this->purchase_invoice_model->get_bill_id($purchase_order_number);
			$sql="select * from payables_payments where bill_id=".$bill;
			 
			echo datasource($sql);

		}
		
		function add_retur($nomor)
		{
			
		}
		function delete_retur($nomor)
		{
			$nomor=urldecode($nomor);
			$this->db->query("delete from purchase_order_lineitems where purchase_order_number='$nomor'");
			$this->db->query("delete from purchase_order where purchase_order_number='$nomor'");
			
		}
		function list_retur($purchase_order_number)
		{
			$purchase_order_number=urldecode($purchase_order_number);
			$sql="select purchase_order_number as nomor,po_date as tanggal, amount, 
                i.supplier_number,c.supplier_name,c.city,i.warehouse_code
                from purchase_order i
                left join suppliers c on c.supplier_number=i.supplier_number
                where i.potype='R' and po_ref='$purchase_order_number'";
			echo datasource($sql);				
			
		}
		function save_retur($purchase_order_number)
		{
			
		}
		function add_crdb($nomor)
		{
			
		}
		function delete_crdb($nomor_bukti)
		{
			$purchase_order_number=urldecode($purchase_order_number);
			$this->db->query("delete from crdb_memo_dtl where kodecrdb='$nomor_bukti'");
			$this->db->query("delete from crdb_memo where kodecrdb='$nomor_bukti'");
			
		}
		function list_crdb($purchase_order_number)
		{
			$purchase_order_number=urldecode($purchase_order_number);
			$sql="select kodecrdb as nomor,tanggal, amount 
                from crdb_memo i
                where docnumber='$purchase_order_number'";
			echo datasource($sql);				
			
		}
		function save_crdb($purchase_order_number)
		{
			
		}
		function add_jurnal($purchase_order_number)
		{
			
		}
		function delete_jurnal($purchase_order_number)
		{
			
		}
		function list_jurnal($purchase_order_number)
		{
			
		}
		function save_jurnal($purchase_order_number)
		{
			
		}

	function daftar_saldo_faktur()
	{
		$sql="select p.purchase_order_number , p.po_date ,
		s.supplier_name,p.terms,p.amount,p.due_date
		from purchase_order p
		left join suppliers s on s.supplier_number=p.supplier_number
		where potype='I' and (p.due_date<=".date("Y-m-d")." or p.due_date is null) 
		order by p.po_date asc limit 5";
		echo datasource($sql);
	}
	function amount_paid($faktur){return $this->purchase_invoice_model->paid_amont($faktur);}
	function amount_retur($faktur){return $this->purchase_invoice_model->retur_amount($faktur);}
	function amount_crdb($faktur){return $this->purchase_invoice_model->crdb_amount($faktur);}
	
	function select_list_old(){
		
		$q=$this->input->get('q');
		$cst=$this->input->get('supp');
		if($q){
			if($q=='not_paid'){				
				$sql="select purchase_order_number,po_date,due_date,amount,terms 
				from purchase_order 
				where potype='I' and (paid=false or isnull(paid))
				and supplier_number='$cst'";
				 
				$query=$this->db->query($sql);
				$i=0;
				$this->load->model('purchase_invoice_model');
				$data='';
				foreach($query->result() as $row){
					$saldo=$this->purchase_invoice_model->recalc($row->purchase_order_number);
					if($saldo!=0){
						$data[$i][]=$row->purchase_order_number;
						$data[$i][]=$row->po_date;
						$data[$i][]=$row->due_date;
						$data[$i][]=$row->terms;
						$data[$i][]=number_format($row->amount);
						$data[$i][]=number_format($saldo);
						$data[$i][]=form_input('bayar[]');
						$data[$i][]=form_hidden('faktur[]',$row->purchase_order_number);
						$i++;
					}
				}
				
				$this->load->library('browse');
				$header=array('Faktur','Tanggal','Jth Tempo','Termin','Jumlah','Saldo','Bayar');
				$this->browse->set_header($header);
				$this->browse->data($data);
				echo $this->browse->refresh();
			}
		}
	}
	function find($nomor){
		$this->load->model('purchase_invoice_model');

		$sql="select purchase_order_number,po_date,due_date,amount,terms,paid,closed 
		from purchase_order 
		where purchase_order_number='$nomor'";
		
		$saldo=$this->purchase_invoice_model->recalc($nomor);
		$query=$this->purchase_invoice_model->get_by_id($nomor)->row();
		$data['po_date']=$query->po_date;
		$data['amount']=number_format($query->amount);
		$data['saldo']=number_format($saldo);
		
		echo json_encode($data);
		
	}
	function invoice_not_paid($supplier_number){
		$supplier_number=urldecode($supplier_number);

		$this->load->model('purchase_invoice_model');

		$sql="select purchase_order_number,po_date,due_date,amount,terms 
		from purchase_order 
		where potype='I' and (paid=false or isnull(paid))
		and supplier_number='$supplier_number'";
 
		$query=$this->db->query($sql);
		$i=0;
		$rows[0]='';
		if($query){ 
			foreach($query->result_array() as $row){
				$nomor=$row['purchase_order_number'];
				$saldo=$this->purchase_invoice_model->recalc($nomor);
				if($saldo!=0){
					$row['amount']=number_format($row['amount']);
					$row['saldo']=number_format($saldo);
					$row['bayar']=form_input("bayar[]","","style='width:100px;color:black;text-align:right'");
					$row['purchase_order_number']=$nomor.form_hidden("faktur[]",$nomor);
					$rows[$i++]=$row;
				}
			};
		}
		$data['total']=$i;
		$data['rows']=$rows;
					
		echo json_encode($data);
	}
	
	function select($supplier=''){
		$supplier=urldecode($supplier);
		$s="select purchase_order_number,po_date,terms from purchase_order 
		where potype='I'";
		if($supplier!="")$s.=" and supplier_number='".$supplier."'";
	 
		echo datasource($s);
	}
	function list_by_po($nomor_po){
		$nomor_po=urldecode($nomor_po);
		$s="select  distinct p.purchase_order_number,p.po_date,p.terms,p.amount 
			from purchase_order_lineitems pol
			left join purchase_order p on p.purchase_order_number=pol.purchase_order_number
			left join inventory_products ip on ip.id=pol.from_line_number
			where ip.purchase_order_number='$nomor_po'";
		echo datasource($s);
	}
	function unposting($nomor) {
		if(!allow_mod2('_40135'))return false;
		$nomor=urldecode($nomor);
		$this->purchase_invoice_model->recalc($nomor);
		$faktur=$this->purchase_invoice_model->get_by_id($nomor)->row();

		$this->load->model("periode_model");
		if($this->periode_model->closed($faktur->po_date)){
			echo "ERR_PERIOD";
			return false;
		}
		// validate jurnal
		$this->load->model('jurnal_model');
		if($this->jurnal_model->del_jurnal($nomor)) {
			$data['posted']=false;
		} else {
			$data['posted']=true;
		}
		$this->purchase_invoice_model->update($nomor,$data);
		
		$this->view($nomor);
	}
	function posting($nomor)
	{
		if(!allow_mod2('_40135'))return false;
		$nomor=urldecode($nomor);
		$this->load->model("purchase_invoice_model");
		$this->purchase_invoice_model->posting($nomor);		
		$this->view($nomor);
	}
	function posting_all() {
		if(!allow_mod2('_40135'))return false;
		$this->load->model("purchase_invoice_model");
		$d1= date( 'Y-m-d H:i:s', strtotime($this->input->get('sid_date_from')));
		$d2= date( 'Y-m-d H:i:s', strtotime($this->input->get('sid_date_to')));
		$sql="select distinct purchase_order_number from purchase_order"; 
		$sql.=" where potype='I'
		and (posted is null or posted=false) 
		and po_date  between '$d1' and '$d2'";
		
		if($q=$this->db->query($sql)){
			foreach($q->result() as $r){
				echo "<p>Posting..
				<a href=".base_url()."index.php/purchase_invoice/view/".$r->purchase_order_number."
				class='info_link'>".$r->purchase_order_number."</a> : ";
				$message=$this->purchase_invoice_model->posting($r->purchase_order_number);
				if($message!=''){
					echo ': '.$message;
				}
				echo "</p>";
			}
		}
		echo "<p>Finish.</p>";
	}	
	
}
