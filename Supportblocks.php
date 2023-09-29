<?php
defined('BASEPATH') OR exit('No direct script access allowed');
//session_start();
class Supportblocks extends CI_Controller {

	function __construct()
	{
		parent::__construct();
		$this->load->database();
		$this->load->library('session');
	}

	public function index()
	{
		$data['title'] = 'Supportblocks';

		$this->load->library('session');

		$this->load->view('header',$data);
		$this->load->view('supportblocks');
		$this->load->view('footer');

	}

	public function submit_a_project()
	{
		$data['title'] = 'Submit a New Project';

		$this->load->helper(array('form', 'url'));
		$this->load->library('form_validation');
		$this->load->library('session');

		$this->load->view('header',$data);

		if (!$_POST)
		{
			$this->load->view('submit_a_project',$data);
		}else{

			$this->load->library('upload');

			$this->load->model('User');
			$domain = $_POST['domain'];
			$domain = strtolower($domain);
			$dom_last_char = substr($domain, -1);
			if($dom_last_char == "/") {
				$domain = substr($domain, 0, -1);
			}
			//print_r($_FILES);exit;
			$domain_name = $this->get_domain_name($domain);
			$username = str_replace(".","_",$domain_name);
			//$password = random_alpha_generator();
			$user_data = array('username'=>$username,'email'=>$_POST['email'],'created_at'=>date('Y-m-d H:i:s'));
			// print_r($user_data);
			// exit;
			//$user_id = $this->User->save_user($user_data);
			$user_id = $this->User->get_save_user($user_data);

			$this->load->model('Project');
			$project = $this->Project->get_project($user_id);

			if(empty($project)){
				$domain_data = array('project'=>$domain,'user_id'=>$user_id,'created_at'=>date('Y-m-d H:i:s'));
				$project_id = $this->Project->save_domain($domain_data);
				mkdir(target_directory().$username);
			}else{
				//$project = $this->Project->get_project($user_id);
				$project_id = $project[0]->id;
			}

			$order_id = random_alpha_generator();

			for($i=1;$i<=$_POST['total_pages'];$i++){

				$file = $_FILES['file-'.$i];
				$page = $_POST['url-'.$i];
				foreach($_POST['task-'.$i] as $key=>$value){
					$upload['name']		= $file['name'][$key];
					$upload['type']		= $file['type'][$key];
					$upload['tmp_name']	= $file['tmp_name'][$key];
					$upload['error']	= $file['error'][$key];
					$upload['size']		= $file['size'][$key];

					$upload_data = $this->upload_file($upload,$username);

					if($page[0]!="" || $value!=""){
						$task_data = array('project_id'=>$project_id,'order_id'=>$order_id,'page'=>$page[0],'task'=>$value,'file'=>$upload_data?$upload_data:'','file_name'=>$file['name'][$key]?$file['name'][$key]:'');
						$this->load->model('Task');
						$this->Task->save_task($task_data);
					}
				}
			}
			$this->send_email_quote($_POST,$project_id,$username,$order_id);
			redirect('supportblocks/thank_you/');
		}

		$this->load->view('footer');
	}

	public function mini_audit_request()
	{
		$data['title'] = 'Mini Audit Request Form';

		$this->load->helper(array('form', 'url'));
		$this->load->library('form_validation');
		$this->load->library('session');

		$this->load->view('header',$data);

		if (!$_POST)
		{
			$this->load->view('mini_audit_request',$data);
		}else{
			$accountrep = $_POST['accountrep'];
			$othername = $_POST['othername'];
			$email = $_POST['email'];
			$mobile = $_POST['mobile'];
			$market = (isset($_POST['market']))?$_POST['market']:'';
			$market = (is_array($market))?implode(',',$market):$market;
			// $market = implode(',',$market);
			$clientname = $_POST['clientname'];
			$webdomain = $_POST['webdomain'];
			$clientdet = (isset($_POST['clientdet']))?$_POST['clientdet']:'';
			$existing = $_POST['existing'];
			$budget = (isset($_POST['budget']))?$_POST['budget']:'';
			$budget = (is_array($budget))?implode(',',$budget):$budget;
			$audit = (isset($_POST['audit']))?$_POST['audit']:'';
			$developedby = (isset($_POST['developedby']))?$_POST['developedby']:'';
			$business_days = (isset($_POST['business_days']))?$_POST['business_days']:'';
			$additional = $_POST['additional'];
			$date = date('Y-m-d H:i:s');
			$ipaddr = $_SERVER['REMOTE_ADDR'];
			$insert_data = array('accountrep'=>$accountrep,'othername'=>$othername,'email'=>$email,'mobile'=>$mobile,'market'=>$market,'clientname'=>$clientname,'webdomain'=>$webdomain,'clientdet'=>$clientdet,'existing'=>$existing,'budget'=>$budget,'audit'=>$audit,'additional'=>$additional,'crcdt'=>$date,'ipaddr'=>$ipaddr,'developedby'=>$developedby,'business_days'=>$business_days);
			//print_r($insert_data); exit;
			$this->load->model('Task');
			$this->Task->save_audit($insert_data);
				
			$this->send_email_audit($_POST);
			redirect('supportblocks/mini_audit_request_thank_you/');
		}

		$this->load->view('footer');
	}

	//upload file
	private function upload_file($param,$folder){
		$ext = pathinfo($param["name"],PATHINFO_EXTENSION);echo '<br>';
		$file_name = basename($param["name"],'.'.$ext);
		$file_name2 = str_replace(" ","-",$file_name);
		$newfilename = $file_name2.'-'.round(microtime(true)).'-'.rand(10,1000).'.'.$ext;
		$target_file = target_directory().$folder.'/'.$newfilename;
		if (move_uploaded_file($param["tmp_name"], $target_file)) {
			return $newfilename;
		}
	}

	public function submit_a_project_step2()
	{
		$data['title'] = 'Submit a New Project';
		

		$this->load->view('footer');
	}

	//get the domain name from the domain, remove http:, https:, www.
	private function get_domain_name($domain){
		$input = trim($domain, '/');
		if (!preg_match('#^http(s)?://#', $input)) {
			$input = 'http://' . $input;
		}
		$urlParts = parse_url($input);
		return preg_replace('/^www\./', '', $urlParts['host']);
	}

	//send emails
	private function send_email_quote($data,$project_id,$user_data,$order_id){
		$this->load->model('Project');
		$result = $this->Project->get_project_task($project_id,$order_id);

		$content = '<div>Hi Team,<br><br>Here is a new request from the Mid-West Website Support portal.<br><br><table width="90%" style="border:1px solid #ccc" border="0" cellpadding="5" cellspacing="0" style="background-color: #FFFFFF;">';
		$content.='<tr><td colspan="3" style="font-size:14px; font-weight:bold; background-color:#EEE; border-bottom:1px solid #DFDFDF; padding:7px 7px"><a href="'.$result[0]->project.'" target="_blank">'.$result[0]->project."</a></td></tr>";

		if($data['email'])
		{
			$content.="<tr style='background-color: #EAF2FA;'><td colspan='3'><font style='font-family: sans-serif; font-size:12px;'><strong>Email</strong></td></tr>";
			$content.="<tr style='background-color: #FFFFFF;'><td colspan='3'><font style='font-family: sans-serif; font-size:12px;'>".$data['email']."</font></td></tr>";
		}
		/*if($data['phone'])
		{
			$content.="<tr style='background-color: #EAF2FA;'><td colspan='3'><font style='font-family: sans-serif; font-size:12px;'><strong>Phone</strong></td></tr>";
			$content.="<tr style='background-color: #FFFFFF;'><td colspan='3'><font style='font-family: sans-serif; font-size:12px;'>".$data['phone']."</font></td></tr>";
		}*/

		$array = $array2 = array();
		foreach($result as $key=>$value){
			//print_r($value->page);
			$array[$value->page][] = $value->task;
			$array2[$value->page.'-file'][] = $value->file;
			$array2[$value->page.'-file_name'][] = $value->file_name;
		}
		$page = 1;
		foreach($array as $key2=>$value2){
			$content.="<tr style='background-color: #EAF2FA;'><td colspan='3'><font style='font-family: sans-serif; font-size:12px;'><strong>Page ".$page."</strong></td></tr>";
			$content.="<tr style='background-color: #FFFFFF;'><td colspan='3'><font style='font-family: sans-serif; font-size:12px;'>".$key2."</font></td></tr>";
			$task = 1;
			foreach($value2 as $key3=>$value3){
				$content.="<tr style='background-color: #EAF2FA;'><td width='20'>&nbsp;</td><td colspan='2'><font style='font-family: sans-serif; font-size:12px;'><strong>Task ".$task."</strong></td></tr>";
				$content.="<tr style='background-color: #FFFFFF;'><td width='20'>&nbsp;</td><td width='20'>&nbsp;</td><td><font style='font-family: sans-serif; font-size:12px;'>".nl2br($value3)."</font></td></tr>";
				$file = target_directory().$user_data.'/'.$array2[$key2.'-file'][$key3];
				if(trim($array2[$key2.'-file'][$key3])!=""){
					$attachment = base_url().'assets/uploads/'.$user_data.'/'.$array2[$key2.'-file'][$key3];
					$content.="<tr style='background-color: #EAF2FA;'><td width='20'>&nbsp;</td><td colspan='2'><font style='font-family: sans-serif; font-size:12px;'><strong>Attachment</strong></td></tr>";
					$content.="<tr style='background-color: #FFFFFF;'><td width='20'>&nbsp;</td><td width='20'>&nbsp;</td><td><font style='font-family: sans-serif; font-size:12px;'><a href=".$attachment." target='_blank'>".$array2[$key2.'-file_name'][$key3]."</a></font></td></tr>";
				}
				//$content.="<tr><td></td></tr>";
				$task++;
			}
			//$content.="<tr><td><hr></td></tr>";
			$page++;
		}
		$content.='</table><br>Best regards,<br>-midwestwebteam.com</div>';
		$config_smpt = config_smpt('support@midwestwebteam.com');
		// $config_smpt = config_smpt('support@midwestwebteam.com, jenna.piche@midwestfamilymadison.com');
		//$config_smpt['smtp_user']

		$receipient = 'support@midwestwebteam.com, jenna.piche@midwestfamilymadison.com';
		// $receipient = 'pradeepa@webstix.com';

		$subject = 'New Maintenance Quote Request for '.$result[0]->project;
		// $subject = 'New Maintenance Quote from MidwestWebTeam.com'.' '.date("Y-m-d H:i:s");
		$my_library = new MY_Library();
		$send_mail = $my_library->send_email($data['email'],$receipient,'',$subject,$content);
		if($send_mail === TRUE){
			return true;
		}else{
			show_error($send_mail);
		}
	}


	private function send_email_audit($data){
		$accntrep = $data['accountrep'];
		$othername = $data['othername'];
		$email = $data['email'];
		$mobile = $data['mobile'];
		$clientname = $data['clientname'];
		$webdomain = $data['webdomain'];
		$clientdet = $data['clientdet'];
		$existing = $data['existing'];
		$audit = (isset($data['audit']))?$data['audit']:'';
		$developedby = (isset($data['developedby']))?$data['developedby']:'';
		$business_days = (isset($data['business_days']))?$data['business_days']:'';
		$additional = $data['additional'];
		$market = (!empty($data['market']))?$data['market']:'';
		if(in_array("Madison", $market)){
			$addcc = "destiny.luchtel@midwestfamilymadison.com";
		}else{
			$addcc = "";
		}
		$market = (is_array($market))?implode(',',$market):$market;
		$budget = (!empty($data['budget']))?$data['budget']:'';
		$budget = (is_array($budget))?implode(',',$budget):$budget;
		$content = '<div>Hi Team,<br><br>Here is a new request from the Mid-West Web Team.<br><br><table width="90%" style="border:1px solid #ccc" border="0" cellpadding="5" cellspacing="0" style="background-color: #FFFFFF;">';

		$content.='<tr style="background-color: #EAF2FA;"><td colspan="2"><font style="font-family: sans-serif; font-size:12px;"><strong>Account rep name</strong></td></tr>';
		$content.='<tr style="background-color: #FFFFFF;"><td colspan="2"><font style="font-family: sans-serif; font-size:12px;">'.$accntrep.'</font></td></tr>';
		if($othername){
			$content.='<tr style="background-color: #EAF2FA;"><td colspan="2"><font style="font-family: sans-serif; font-size:12px;"><strong>Other name </strong></td></tr>';
			$content.='<tr style="background-color: #FFFFFF;"><td colspan="2"><font style="font-family: sans-serif; font-size:12px;">'.$othername.'</font></td></tr>';
		}
		$content.='<tr style="background-color: #EAF2FA;"><td colspan="2"><font style="font-family: sans-serif; font-size:12px;"><strong>Email address</strong></td></tr>';
		$content.='<tr style="background-color: #FFFFFF;"><td colspan="2"><font style="font-family: sans-serif; font-size:12px;">'.$email.'</font></td></tr>';
		$content.='<tr style="background-color: #EAF2FA;"><td colspan="2"><font style="font-family: sans-serif; font-size:12px;"><strong>Mobile number</strong></td></tr>';
		$content.='<tr style="background-color: #FFFFFF;"><td colspan="2"><font style="font-family: sans-serif; font-size:12px;">'.$mobile.'</font></td></tr>';
		if(!empty($market)){
			$content.='<tr style="background-color: #EAF2FA;"><td colspan="2"><font style="font-family: sans-serif; font-size:12px;"><strong>Market</strong></td></tr>';
			$content.='<tr style="background-color: #FFFFFF;"><td colspan="2"><font style="font-family: sans-serif; font-size:12px;">'.$market.'</font></td></tr>';
		}	
		$content.='<tr style="background-color: #EAF2FA;"><td colspan="2"><font style="font-family: sans-serif; font-size:12px;"><strong>Client name</strong></td></tr>';
		$content.='<tr style="background-color: #FFFFFF;"><td colspan="2"><font style="font-family: sans-serif; font-size:12px;">'.$clientname.'</font></td></tr>';
		$content.='<tr style="background-color: #EAF2FA;"><td colspan="2"><font style="font-family: sans-serif; font-size:12px;"><strong>Web domain name</strong></td></tr>';
		$content.='<tr style="background-color: #FFFFFF;"><td colspan="2"><font style="font-family: sans-serif; font-size:12px;"><a style="color: #000000" href="'.$webdomain.'" target="_blank">'.$webdomain.'</a></font></td></tr>';
		if(!empty($clientdet)){
			$content.='<tr style="background-color: #EAF2FA;"><td colspan="2"><font style="font-family: sans-serif; font-size:12px;"><strong>Is this a new or existing client</strong></td></tr>';
			$content.='<tr style="background-color: #FFFFFF;"><td colspan="2"><font style="font-family: sans-serif; font-size:12px;">'.$clientdet.'</font></td></tr>';
		}
		if(!empty($existing)){
			$content.='<tr style="background-color: #EAF2FA;"><td colspan="2"><font style="font-family: sans-serif; font-size:12px;"><strong>If an existing client, what other services are you providing (brief listing)</strong></td></tr>';
			$content.='<tr style="background-color: #FFFFFF;"><td colspan="2"><font style="font-family: sans-serif; font-size:12px;">'.$existing.'</font></td></tr>';
		}
		if(!empty($budget)){
			$content.='<tr style="background-color: #EAF2FA;"><td colspan="2"><font style="font-family: sans-serif; font-size:12px;"><strong>Budget</strong></td></tr>';
			$content.='<tr style="background-color: #FFFFFF;"><td colspan="2"><font style="font-family: sans-serif; font-size:12px;">'.$budget.'</font></td></tr>';
		}
		if(!empty($audit)){
			$content.='<tr style="background-color: #EAF2FA;"><td colspan="2"><font style="font-family: sans-serif; font-size:12px;"><strong>Based on what you know, what do you think this client is most concerned about?</strong></td></tr>';
			$content.='<tr style="background-color: #FFFFFF;"><td colspan="2"><font style="font-family: sans-serif; font-size:12px;">'.$audit.'</font></td></tr>';
		}
		if(!empty($developedby)){
			$content.='<tr style="background-color: #EAF2FA;"><td colspan="2"><font style="font-family: sans-serif; font-size:12px;"><strong>Was this website developed and sold by the Mid-West Family?</strong></td></tr>';
			$content.='<tr style="background-color: #FFFFFF;"><td colspan="2"><font style="font-family: sans-serif; font-size:12px;">'.$developedby.'</font></td></tr>';
		}
		if(!empty($business_days)){
			$content.='<tr style="background-color: #EAF2FA;"><td colspan="2"><font style="font-family: sans-serif; font-size:12px;"><strong>When do you need this mini-audit?</strong></td></tr>';
			$content.='<tr style="background-color: #FFFFFF;"><td colspan="2"><font style="font-family: sans-serif; font-size:12px;">'.$business_days.'</font></td></tr>';
		}
		if(!empty($additional)){
			$content.='<tr style="background-color: #EAF2FA;"><td colspan="2"><font style="font-family: sans-serif; font-size:12px;"><strong>Are there any other issues we should focus on or any comments you like to add?</strong></td></tr>';
			$content.='<tr style="background-color: #FFFFFF;"><td colspan="2"><font style="font-family: sans-serif; font-size:12px;">'.$additional.'</font></td></tr>';
		}
		// $content.='</table><br>Best regards,<br>-midwestwebteam.com</div>';

		$receipient = 'vivek@webstix.com, james@webstix.com, tony@webstix.com, noel@webstix.com, lennart@webstix.com, joe@webstix.com';
		//$receipient = 'revathi@webstix.com';
		// echo $content; exit;
		// $subject = 'New Mini-Audit Request from MidwestWebTeam.com'.' '.date("Y-m-d H:i:s");
		$subject = 'New Mini-Audit Request for '.$webdomain;
		$my_library = new MY_Library();
		$send_mail = $my_library->send_email($data['email'],$receipient,$addcc,$subject,$content);
		//$send_mail = $my_library->send_email($data['email'],'sankar@webstix.com','',$subject,$content);
		if($send_mail === TRUE){
			return true;
		}else{
			show_error($send_mail);
		}
	}	

	//thank you page
	public function thank_you(){
		$data['title'] = 'Your Request Submitted - Thank You';

		$this->load->view('header',$data);
		$this->load->view('thank_you');
		$this->load->view('footer');
	}
	public function mini_audit_request_thank_you(){
		$data['title'] = 'Your Request Submitted - Thank You';

		$this->load->view('header',$data);
		$this->load->view('mini_audit_request_thank_you');
		$this->load->view('footer');
	}
}
