<?php
class UsersController extends AppController {

	var $name = 'Users';
	var $components = array('Uploader','Email');
	var $uses = array('User','Usercontact','Linkedinoauth');
	
	public function beforeFilter() { 
		parent::beforeFilter();
		$this->Auth->allow('add', 'addUser','login1','login2','lilogin');
	}
	
	public function add($organization_id) {
		if($organization_id != null) {
			//do nothing
		} else {
			$this->Session->setFlash('You will need to fill up your organization first before proceeding to create an account.');
			$this->redirect(array('controller'=>'companyprofiles', 'action'=>'ifexists'));
		}
	}
	
	public function addUser() {
		if (!empty($this->data)) {
			$this->User->create();
			$name = strtok($this->data['User']['username'], '@');
			$this->data['User']['name'] = $name;
			$this->data['User']['profilepiclink'] = 'unknown-person.jpg';
			if ($this->User->save($this->data)) { 
				$this->Session->setFlash('User created! Please verify your account before logging in!'); 			
				$this->redirect(array('action'=>'login'));
				
			} else { 
				$this->Session->setFlash('This email has already been used. Please contact us if you think someone is trying to impersonate you.');	
				$this->redirect(array('controller'=>'users', 'action'=>'add', $this->data['User']['companyprofile_id']));
			}
		} else {
			$this->Session->setFlash('You will need to fill up your organization first before proceeding to create an account.');
			$this->redirect(array('controller'=>'companyprofiles', 'action'=>'ifexists'));
		}
	}

	function urlencode_oauth($str) {
  		return str_replace('+',' ',str_replace('%7E','~',rawurlencode($str)));
	}
	
	public function login1() {
	$LINKEDIN_KEY = 'N18Tcdi4Vgww_zUD4F4-kDebMAfZ1inLHOec-wOP2NHKbaAWOM1q8fz2ju_xL_C1';
	$LINKEDIN_SECRET = 'Z5g_ScAex-upAKRSmohF76492CCC68TEoTu-jUeP2hY9DOy7HYOAfPwo9eiTefZO'; 
	
	$links = array(
 		'request_token'=>'https://api.linkedin.com/uas/oauth/requestToken',
  		'authorize'=>'https://www.linkedin.com/uas/oauth/authorize',
  		'access_token'=>'https://api.linkedin.com/uas/oauth/accessToken'
	);
	
	$params = array(
  		'oauth_callback'=>"http://www.flypn.com/users/login2",
  		'oauth_consumer_key'=>$LINKEDIN_KEY,
  		'oauth_nonce'=>sha1(microtime()),
  		'oauth_signature_method'=>'HMAC-SHA1',
  		'oauth_timestamp'=>time(),
  		'oauth_version'=>'1.0'
	);

		// sort parameters according to ascending order of key
		ksort($params);

		// prepare URL-encoded query string
		$q = array();
		foreach ($params as $key=>$value) {
  			$q[] = $this->urlencode_oauth($key).'='.$this->urlencode_oauth($value);
		}
	
		$q = implode('&',$q);

		// generate the base string for signature
		$parts = array(
  			'POST',
  			$this->urlencode_oauth($links['request_token']),
  			$this->urlencode_oauth($q)
		);
		$base_string = implode('&',$parts);

		$key = $this->urlencode_oauth($LINKEDIN_SECRET) . '&';
		$signature = base64_encode(hash_hmac('sha1',$base_string,$key,true));

		$params['oauth_signature'] = $signature;
		$str = array();
		foreach ($params as $key=>$value) {
  			$str[] = $key . '="'.$this->urlencode_oauth($value).'"';
		}
	
		$str = implode(', ',$str);
		$headers = array(
	  		'POST /uas/oauth/requestToken HTTP/1.1',
  			'Host: api.linkedin.com',
  			'Authorization: OAuth '.$str,
  			'Content-Type: text/xml;charset=UTF-8',
  			'Content-Length: 0',
  			'Connection: close'
		);

		$fp = fsockopen("ssl://api.linkedin.com",443,$errno,$errstr,30);
		if (!$fp) { 
			echo 'Unable to connect to LinkedIn'; 
			exit(); 
		}
		$out = implode("\r\n",$headers) . "\r\n\r\n";
		fputs($fp,$out);

		// getting LinkedIn server response
		$res = '';
		while (!feof($fp)) $res .= fgets($fp,4096);
		fclose($fp);

		$parts = explode("\n\n",str_replace("\r",'',$res));
		$res_headers = explode("\n",$parts[0]);
		if ($res_headers[0] != 'HTTP/1.1 200 OK') {
  			echo 'Error getting OAuth token and secret.'; exit();
		}
		parse_str($parts[1],$data);
		if (empty($data['oauth_token'])) {
  			echo 'Failed to get LinkedIn request token.'; exit();
		}
	
		$this->Linkedinoauth->save(array('linkedin_oauth_token'=>$data['oauth_token'],'linkedin_oauth_token_secret'=>$data['oauth_token_secret']));
			
		header('Location: '.$links['authorize'].'?oauth_token='.urlencode($data['oauth_token']));
		exit();
	}
	
	public function login2() {
		$LINKEDIN_KEY = 'N18Tcdi4Vgww_zUD4F4-kDebMAfZ1inLHOec-wOP2NHKbaAWOM1q8fz2ju_xL_C1';
		$LINKEDIN_SECRET = 'Z5g_ScAex-upAKRSmohF76492CCC68TEoTu-jUeP2hY9DOy7HYOAfPwo9eiTefZO'; 
	
	$links = array(
 		'request_token'=>'https://api.linkedin.com/uas/oauth/requestToken',
  		'authorize'=>'https://www.linkedin.com/uas/oauth/authorize',
  		'access_token'=>'https://api.linkedin.com/uas/oauth/accessToken'
	);

	$currententry = $this->Linkedinoauth->find('first', array('conditions' => array('Linkedinoauth.linkedin_oauth_token'=>$_GET['oauth_token'])));
	
		if (empty($_GET['oauth_token']) || empty($_GET['oauth_verifier'])) {
  			echo 'If you see this error message, we suspect that there was an error in the authetication process. Please close this window, log-in to your LinkedIn account at www.linkedin.com directly, and then come back to www.flypn.com and log-in using the same process. Everything will be working smoothly then!'; exit();
		}
	
		$params = array(
  			'oauth_consumer_key'=>$LINKEDIN_KEY,
  			'oauth_nonce'=>sha1(microtime()),
  			'oauth_signature_method'=>'HMAC-SHA1',
  			'oauth_timestamp'=>time(),
  			'oauth_token'=>$_GET['oauth_token'],
  			'oauth_verifier'=>$_GET['oauth_verifier'],
  			'oauth_version'=>'1.0'
		);

		// sort parameters according to ascending order of key
		ksort($params);

		// prepare URL-encoded query string
		$q = array();
		foreach ($params as $key=>$value) {
  			$q[] = $this->urlencode_oauth($key).'='.$this->urlencode_oauth($value);
		}
		$q = implode('&',$q);

		// generate the base string for signature
		$parts = array(
  			'POST',
  			$this->urlencode_oauth($links['access_token']),
  			$this->urlencode_oauth($q)
		);
		$base_string = implode('&',$parts);

		$key = $this->urlencode_oauth($LINKEDIN_SECRET) . '&' . $this->urlencode_oauth($currententry['Linkedinoauth']['linkedin_oauth_token_secret']);
		$signature = base64_encode(hash_hmac('sha1',$base_string,$key,true));

		$params['oauth_signature'] = $signature;
		$str = array();
		foreach ($params as $key=>$value) {
  			$str[] = $key . '="'.$this->urlencode_oauth($value).'"';
		}
		
		$str = implode(', ',$str);
		$headers = array(
  			'POST /uas/oauth/accessToken HTTP/1.1',
  			'Host: api.linkedin.com',
  			'Authorization: OAuth '.$str,
  			'Content-Type: text/xml;charset=UTF-8',
  			'Content-Length: 0',
  			'Connection: close'
		);

		$fp = fsockopen("ssl://api.linkedin.com",443,$errno,$errstr,30);
		if (!$fp) { 
			echo 'Unable to connect to LinkedIn'; 
			exit(); 
		}
		$out = implode("\r\n",$headers) . "\r\n\r\n";
		fputs($fp,$out);

		// getting LinkedIn server response
		$res = '';
		while (!feof($fp)) $res .= fgets($fp,4096);
		fclose($fp);

		$parts = explode("\n\n",str_replace("\r",'',$res));
		$res_headers = explode("\n",$parts[0]);
		if ($res_headers[0] != 'HTTP/1.1 200 OK') {
  			echo 'Error getting OAuth token and secret.'; 
  			exit();
		}
		parse_str($parts[1],$data);
		if (empty($data['oauth_token'])) {
  			echo 'Failed to get LinkedIn request token.'; exit();
		}
		
		$this->Linkedinoauth->read(null,$currententry['Linkedinoauth']['id']);
		$this->Linkedinoauth->set(array('linkedin_access_token'=>$data['oauth_token'],'linkedin_access_token_secret'=>$data['oauth_token_secret']));
		$this->Linkedinoauth->save();
		
		$this->redirect(array('action'=>'lilogin',$currententry['Linkedinoauth']['id']));
	}
	
	function LI_api_call($id ,$method = 'GET', $appendurl = '/v1/people/~:(first-name,last-name,industry,id,picture-url,public-profile-url,headline)'){
	
	$LINKEDIN_KEY = 'N18Tcdi4Vgww_zUD4F4-kDebMAfZ1inLHOec-wOP2NHKbaAWOM1q8fz2ju_xL_C1';
	$LINKEDIN_SECRET = 'Z5g_ScAex-upAKRSmohF76492CCC68TEoTu-jUeP2hY9DOy7HYOAfPwo9eiTefZO'; 
	
	$links = array(
 		'request_token'=>'https://api.linkedin.com/uas/oauth/requestToken',
  		'authorize'=>'https://www.linkedin.com/uas/oauth/authorize',
  		'access_token'=>'https://api.linkedin.com/uas/oauth/accessToken'
	);
	
	$currententry = $this->Linkedinoauth->find('first', array('conditions' => array('Linkedinoauth.id'=>$id)));
	
		$params = array(
		  	'oauth_consumer_key'=>$LINKEDIN_KEY,
  			'oauth_nonce'=>sha1(microtime()),
	  		'oauth_signature_method'=>'HMAC-SHA1',
  			'oauth_timestamp'=>time(),
  			'oauth_token'=>$currententry['Linkedinoauth']['linkedin_access_token'],
  			'oauth_version'=>'1.0'
		);

		// sort parameters according to ascending order of key
		ksort($params);

		// prepare URL-encoded query string
		$q = array();
		foreach ($params as $key=>$value) {
  			$q[] = $this->urlencode_oauth($key).'='.$this->urlencode_oauth($value);
		}
		$q = implode('&',$q);

		// generate the base string for signature
		$parts = array(
 	 		$method,
  		$this->urlencode_oauth('https://api.linkedin.com'.$appendurl),
  		$this->urlencode_oauth($q)
		);
		$base_string = implode('&',$parts);		
		
		$key = $this->urlencode_oauth($LINKEDIN_SECRET) . '&' . $this->urlencode_oauth($currententry['Linkedinoauth']['linkedin_access_token_secret']);
		$signature = base64_encode(hash_hmac('sha1',$base_string,$key,true));

		$params['oauth_signature'] = $signature;
		$str = array();
		foreach ($params as $key=>$value) {
  			$str[] = $key . '="'.$this->urlencode_oauth($value).'"';
		}
		$str = implode(', ',$str);
		$headers = array(
  			$method.' '.$appendurl.' HTTP/1.1',
  			'Host: api.linkedin.com',
  			'Authorization: OAuth '.$str,
  			'Content-Type: text/html;charset=UTF8',
  			'Content-Length: 0',
	  		'x-li-format: json',
  			'Connection: close'
		);

		$fp = fsockopen("ssl://api.linkedin.com",443,$errno,$errstr,30);
		if (!$fp) { 
			echo 'Unable to connect to LinkedIn'; 
			exit(); 
		}
		$out = implode("\r\n",$headers)."\r\n\r\n";
		fputs($fp,$out);

		// getting LinkedIn server response
		$res = '';
		while (!feof($fp)) $res .= fgets($fp,4096);
		fclose($fp);

		$parts = explode("\n\n",str_replace("\r","",$res));
		$headers = explode("\n",$parts[0]);
		$headers2 = explode("\n",$parts[1]);
		if ($headers[0] != 'HTTP/1.1 200 OK') { echo 'Failed'; }
	
	$jsonvalue = substr($parts[1],3,strlen($parts[1])-5);
	$phparray = json_decode($jsonvalue);
	
	$this->Linkedinoauth->delete($id);
	
	return $phparray;
	
	}

	
	public function lilogin($linkedin_id) {
		$userinfo = $this->LI_api_call($linkedin_id); //not empty
		
		if(!empty($userinfo)){ //returns 'true'
			$fname = $userinfo->firstName;
			$lname = $userinfo->lastName;
			$username = $fname.' '.$lname;
			$id = $userinfo->id;
			$user = $this->User->find('first',array('conditions' => array(
																		'User.username' => $username,
																		'User.password' => $this->Auth->password($id),
																		'User.active' => '1'
																		),
													'recursive' => -1 ));
			$this->data['User']['username'] = $userinfo->firstName.' '.$userinfo->lastName;
			$this->data['User']['fname'] = $userinfo->firstName;
			$this->data['User']['lname'] = $userinfo->lastName;
			$this->data['User']['name'] = $userinfo->firstName.' '.$userinfo->lastName;
			$this->data['User']['password'] = $this->Auth->password($userinfo->id);
			if(isset($userinfo->pictureUrl)) {
				$this->data['User']['profilepiclink'] = $userinfo->pictureUrl; //could be null
			} else {
				$this->data['User']['profilepiclink'] = 'http://www.flypn.com/app/webroot/img/unknown-person.jpg';
			}
			$this->data['User']['publicprofilelink'] = $userinfo->publicProfileUrl;
			$this->data['User']['linkedinid'] = $userinfo->id;
			$this->data['User']['headline'] = $userinfo->headline;
			if(isset($userinfo->industry)) {
				$this->data['User']['industry'] = $userinfo->industry; //could be null
			} else {
				$this->data['User']['industry'] = 'Unknown';
			}
			
			//if user is not in database, add him and then log him in. If user is in database, straightaway log him in
			if(empty($user)) {
				$this->User->create();			
				if ($this->User->save($this->data)) {
					$user = $this->User->find('first',array('conditions' => array(
																		'User.username' => $username,
																		'User.password' => $this->Auth->password($id),
																		'User.active' => '1'
																		),
													'recursive' => -1 ));
					$this->Session->write('Auth.User',$user);
					$this->redirect($this->Auth->redirect());
				} else { 
					$this->redirect(array('action'=>'login1'));
				}
			} elseif(!empty($user)) {
				$this->Session->write('Auth.User',$user);
				$this->redirect($this->Auth->redirect());
			}			
		} else {
			$this->redirect(array('action'=>'login1'));
		}		
	}

	public function login() { 
		$this->redirect('http://www.flypn.com');
	}
	
	public function logout() {
		$this->redirect($this->Auth->logout());
	}
	
	public function profile(){
	
	}
	
	function view($id = null) {
		if (!$id) {
			$this->Session->setFlash(__('Invalid user', true));
			$this->redirect(array('action' => 'index'));
		}
		$this->set('viewuser', $this->User->read(null, $id));
		$this->set('isContact',$this->Usercontact->find('count',array('conditions'=>array('Usercontact.user_a'=>$this->Auth->user('id'),'Usercontact.user_b'=>$id))));
	}


	function edit($id = null) {
	if($id == $this->Auth->user('id')) {
		if (!$id && empty($this->data)) {
			$this->Session->setFlash(__('Invalid user', true));
			$this->redirect(array('action' => 'edit',$id));
		}
		if (!empty($this->data)) {
			if(!empty($this->data['User']['profilepic']['name'])) {
				$this->data['User']['profilepiclink'] = $this->data['User']['profilepic']['name']; 
			}
			if ($this->User->save($this->data)) {
				if(!empty($this->data['User']['profilepic'])) {
					$this->Uploader->uploader($this->data['User']['profilepic'],'profilepics/');
					$this->Uploader->createThumbs('../webroot/profilepics/','../webroot/profilepicsthumbnails/',100);
				}
				$this->Session->setFlash(__('The user has been saved', true));
				$this->redirect(array('action' => 'view',$id));
			} else {
				$this->Session->setFlash(__('The user could not be saved. Please, try again.', true));
			}
		}
		if (empty($this->data)) {
			$this->data = $this->User->read(null, $id);
		}
	} else {
		$this->Session->setFlash("Oops, you are trying to edit some other company's account information.");
		$this->redirect(array('action'=>'view', $id));
	}
	}

}
?>