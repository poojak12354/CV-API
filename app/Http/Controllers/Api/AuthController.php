<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Teams;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\UpdateEmail;
use Validator;
use App\Jobs\SendAuthEmailJob;
use App\Jobs\SendResetPasswordEmailJob;
use Illuminate\Support\Str;
use App\Models\QUYKCV;
use App\Models\Team_users_invite as TeamUserInvite;
use App\Models\Team_users as TeamUser;
use App\Models\UsersSettings;
use App\Models\AccessTokens;
use App\Models\Billing;
use App\Http\Traits\CreateShortUrl;
use App\Http\Traits\DecryptPassword;
use App\Models\SingleUserAddress;
use Illuminate\Support\Facades\Crypt;

/**
 *This controller has functionalify for register user, Update
 *user data, delete user, and users listing.
 */

class AuthController extends Controller {
	public $successStatus = 200;

	/**
	 * Registeration users, In this name, users, password, confirm password are required field
	 * @param  array  $request (name, email, password)
	 * @return [json] token object, if required field not entered, it will throw an error
	 */

	public function verifyNewEmail(Request $request){

		try{

			$validator = Validator::make($request->all(),
			[
				'code' => 'required|exists:team_update_email_address,confirm_code'
			]);

			//if validation failes, then  error would return
			if ($validator->fails()) {
				return response()->json([
					'error' => $validator->errors()->first(),
					'key'=>array_key_first($validator->errors()->messages())
				], 406);
			}

			$data= UpdateEmail::where('confirm_code',$request->code)->first();

			User::find($data->user_id)->update(['email'=> $data->email]);
			
			UpdateEmail::where('confirm_code',$request->code)->forceDelete();
			AccessTokens::where('user_id',$data->user_id)->forceDelete();
			return response()->json(['success' => true]);

			
		}catch(\Exception $e) {
			return response()->json([
				'error' => $e->getLine()." ".$e->getMessage()
			], 406);
		}
	}
	private function generateUniqueCVURL($cv_url,$cv_id=null)
    {
        try{
            $cv_url = strtolower($cv_url);
            $variations = 0;

            while (true) {
                $new_cv_url = $cv_url;
                if ($variations > 0) {
                    $new_cv_url .= "-".(string) $variations;
                }
                if($cv_id){
                    $cv_url_Exist = Teams::where('team_url', $new_cv_url)->where('id','!=',$cv_id)->exists();
                }else{
                    $cv_url_Exist = Teams::where('team_url', $new_cv_url)->exists();
                }
                

                if ($cv_url_Exist) {
                    $variations++;
                } else {
                    $cv_url = $new_cv_url;
                    break;
                }
            }
    
            return $cv_url;
        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }

    }


	public function mobileLogin(Request $request){

		$messages = array(
			'email.required' => trans('messages.email.required'),
			'email.exists' => trans('messages.email.exists'),
			'password.required' => trans('messages.password.required'),
			'password.min' => trans('messages.password.min', ['min' => 8]),
		);

		
		 
		$validator = Validator::make($request->all(),
		[
			'email' => 'required|email:filter|exists:users',
			'password' =>'required|min:8',
		],$messages);	

		//if validation failes, then  error would return
		if ($validator->fails()) {
			return response()->json([
				'error' => $validator->errors()->first(),
				'key'=>array_key_first($validator->errors()->messages())
			], 406);
		}

		if (Auth::attempt(['email' => $request->email, 'password' => DecryptPassword::decrypt($request->password)])) {

			 
			$user = Auth::user();

			if($user->two_factor==true){

				$details['email'] = $user->email;
				if(config('app.server_type')=="prod"){

					$details['code'] = mt_rand(100000,999999);
				}else{
					
					$details['code'] = 123456;

				}
				$details['name'] = $user->first_name." ".$user->last_name;
				$details['lang'] = app()->getLocale();
				$details['url'] = config('app.frontend_url');
				$user->auth_code = $details['code'];
				$user->auth_code_expire = strtotime("now")+300;
				$user->save();
				
				dispatch(new SendAuthEmailJob($details));
				
				return response()->json(['success' => true,'IsTwofactorAuth'=>$user->two_factor], $this->successStatus);

			}

			if($user->two_factor==false){

				$user = Auth::user();
				$user->auth_code = null;
				$user->save();

				
				if($user->hasRole('basic')==true){
					$user->role = "basic";
					$user->cv = QUYKCV::where('user_id',$user->id)->with('pictures_videos','videos','company_address','contact_details','social_networks','curriculum_qualifications')->first();
				}else if($user->hasRole('team-user')==true){
					$user->role = "team-user";
					$user->cv = QUYKCV::where('user_id',$user->id)->with('global_address','pictures_videos','videos','company_address','contact_details','social_networks','curriculum_qualifications')->first();
				}else if($user->hasRole('team')==true){
					$user->team = Teams::where('user_id',$user->id)->first();
					$user->role = "team";
				}else if($user->hasRole('super-admin')==true){
					$user->role = "super-admin";
				}
				
				$success['success'] = true;
				$success['IsTwofactorAuth'] = $user->two_factor;
				$success['data'] = $user;
				$success['token'] = $user->createToken('basf-token')->accessToken;
				return response()->json($success , $this->successStatus);
			}
		
		} else {
			return response()->json(['error' =>trans('messages.unauthorize')], 406);
		}
	}
	/* register from Mobile Login */

	public function mobileRegister(Request $request) {

		 
		$messages = array(
			'first_name.required' => trans('messages.first_name.required'),
			'first_name.min' => trans('messages.first_name.min'),
			'last_name.required' => trans('messages.last_name.required'),
			'last_name.min' => trans('messages.last_name.min'),
			'email.required' => trans('messages.email.required'),
			'email.unique' => trans('messages.email.unique'),
			'email.email' => trans('messages.email.email'),
			'password.required' => trans('messages.password.required'),
			'password.min' => trans('messages.password.min', ['min' => 8]),
		);

		$validator = Validator::make($request->all(),
		[
			'first_name' => 'required|min:2',
			'last_name'=>'required|min:2',
			'gender'=>'required',
			'email' => 'required|email:filter|unique:users',
			'password' =>'required|min:8',
			'IsTwofactorAuth'=>'required|boolean'
		],$messages);


	 
		//if validation failes, then  error would return and status code 406
		if ($validator->fails()) {
			return response()->json([
				'error' => $validator->errors()->first(),
				'key'=>array_key_first($validator->errors()->messages())
			], 406);
		}
		$input = $request->all();
 
		 
		$input['password'] = bcrypt(DecryptPassword::decrypt($input['password']));
		$input['last_name_index'] = substr(request('last_name'), 0, 1);
		$input['two_factor'] = $request->IsTwofactorAuth;

		 
		$user = User::create($input);

		$input['role'] = "basic";
	 
		$user->roles()->attach(Role::where('slug',$input['role'])->first());

		//success message
		return response()->json(['success' => true], $this->successStatus);
	}
	/* create billing function */
	public function createBilling(Request $request){ 

		$monthly_rates =  config('monthlybill');
		$current_month = date('m');

		$users = User::with('roles')->whereHas(
			'roles', function($q){
				$q->whereIn('name',['team','basic']);
			}
		)->get();

		foreach($users as $user){

			$team_id = $user->id;
			$team_users = TeamUser::where('team_id',$team_id)->with('user')->get();
			
			foreach($team_users as $team_user){

				$cv = QUYKCV::withTrashed()->where('user_id',$team_user->team_user_id)->first();
				if(isset($cv)){

					 
					if (empty($cv->deleted_at)){
						//echo $cv->id;die;
						$cv_created_at = $cv->created_at->toDateString();
						$month_start_date = date('Y-m-01');
						$month_end_date = date('Y-m-t');
						if ($cv_created_at < $month_start_date) { 

							$bill_days =  1 + (strtotime($month_end_date) - strtotime($month_start_date)) / (60 * 60 * 24);
							
							$total_bill = round($monthly_rates[$current_month] * $bill_days,2) ; 
							
							$total_bill = sprintf('%0.2f', round($total_bill, 2));

							Billing::create(['user_id'=>$team_id,'cv_id'=>$cv->id,'billing_month'=>$current_month,'billing_start_date'=>$month_start_date,'billing_end_date'=>$month_end_date,'billing_days'=>$bill_days,'billing_amount'=>$total_bill,'cv_status'=>$cv->active]);
						
						}else{

							$bill_days =  1 + (strtotime($month_end_date) - strtotime($cv_created_at)) / (60 * 60 * 24);
							$total_bill = round($monthly_rates[$current_month] * $bill_days,2) ; 
							$total_bill = sprintf('%0.2f', round($total_bill, 2));

							Billing::create(['user_id'=>$team_id,'cv_id'=>$cv->id,'billing_month'=>$current_month,'billing_start_date'=>$cv_created_at,'billing_end_date'=>$month_end_date,'billing_days'=>$bill_days,'billing_amount'=>$total_bill,'cv_status'=>$cv->active]);

							 
						}
					}else{
						$cv_created_at = $cv->created_at->toDateString();
						 
						$cv_deleted_at = $cv->deleted_at->toDateString();

						$month_start_date = date('Y-m-01');
						 
						if($cv_created_at > $month_start_date){

							$bill_days =  1 + (strtotime($cv_deleted_at) - strtotime($cv_created_at)) / (60 * 60 * 24);
							$total_bill = round($monthly_rates[$current_month] * $bill_days,2) ; 
							$total_bill = sprintf('%0.2f', round($total_bill, 2));
							Billing::create(['user_id'=>$team_id,'cv_id'=>$cv->id,'billing_month'=>$current_month,'billing_start_date'=>$cv_created_at,'billing_end_date'=>$cv_deleted_at,'billing_days'=>$bill_days,'billing_amount'=>$total_bill,'cv_status'=>2]);
							 
						}

						 

						
					}
				}
			}
			print_r($team_users->toArray());die;

		}
	}
	public function register(Request $request) {

		 
		$messages = array(
			'first_name.required' => trans('messages.first_name.required'),
			'first_name.min' => trans('messages.first_name.min'),
			'last_name.required' => trans('messages.last_name.required'),
			'last_name.min' => trans('messages.last_name.min'),
			'email.required' => trans('messages.email.required'),
			'email.unique' => trans('messages.email.unique'),
			'email.email' => trans('messages.email.email'),
			'password.required' => trans('messages.password.required'),
			'password.min' => trans('messages.password.min', ['min' => 8]),
			'company_name.required_if'=>trans('messages.company_name.required_if'),
		);

		$validator = Validator::make($request->all(),
		[
			'first_name' => 'required|min:2',
			'last_name'=>'required|min:2',
			'email' => 'required|email:filter|unique:users',
			'role'=> 'required|in:basic,team',
			'password' =>'required|min:8',
			'company_name' => 'required_if:role,team',
			'privacy_policy' => 'required|boolean',
			'terms' => 'required|boolean',
			
		],$messages);



		//if validation failes, then  error would return and status code 406
		if ($validator->fails()) {
			return response()->json([
				'error' => $validator->errors()->first(),
				'key'=>array_key_first($validator->errors()->messages())
			], 406);
		}
		$input = $request->all();
 
		 
		$input['password'] = bcrypt(DecryptPassword::decrypt($input['password']));
		$input['last_name_index'] = substr(request('last_name'), 0, 1);

		 
		$user = User::create($input);

		SingleUserAddress::create(['user_id'=>$user->id,'first_name'=>$input['first_name'],'last_name'=>$input['last_name']]);
		 
		if($input['role']=="team"){

			$company_name = str_replace(' ', '-', $input['company_name']);
			$company_name = preg_replace('/[^A-Za-z0-9\-]/', '', $company_name);
			$teamUrl = $this->generateUniqueCVURL($company_name,null);
			Teams::create(['user_id'=>$user->id,'team_url'=>$teamUrl,'company_name'=>$request->company_name]);
			
			 
			

			$cv_url = CreateShortUrl::generateUniqueCVURL($request->first_name."-".$request->last_name,null);

            $cv_short_url = CreateShortUrl::generateUniqueCVSHORTURL(mb_substr($request->first_name,0,2).mb_substr($request->last_name,0,2).rand(1,10000),null);

			QUYKCV::create(['percentage'=>16,'cv_url'=>$cv_url,'cv_short_url'=>$cv_short_url,'user_id'=>$user->id,'first_name'=>$request->first_name,'last_name'=>$request->last_name]);
			
			TeamUser::create(['team_id'=>$user->id,'team_user_id'=>$user->id]);
			
			UsersSettings::create(['user_id'=>$user->id,'meta_key'=>'cv_edit_by_user','meta_value'=>1]);

		}
		$user->roles()->attach(Role::where('slug',$input['role'])->first());

		 
		//success message
		return response()->json(['success' => true], $this->successStatus);
	}

	/**
	 * Login user and create token, email and password needs to send through post
	 * @param  \Illuminate\Http\Request
	 * @return [json] token object, through an error if user credentials are not valid
	 */
	public function auth(Request $request) {

		
		 
		$messages = array(
			'email.required' => trans('messages.email.required'),
			'email.exists' => trans('messages.email.exists'),
			'email.email' => trans('messages.email.email'),
			'password.required' => trans('messages.password.required'),
			'password.min' => trans('messages.password.min', ['min' => 8]),
		);

		
		 
		$validator = Validator::make($request->all(),
		[
			'email' => 'required|email:filter|exists:users',
			'password' =>'required|min:8',
		],$messages);	

		//if validation failes, then  error would return
		if ($validator->fails()) {
			return response()->json([
				'error' => $validator->errors()->first(),
				'key'=>array_key_first($validator->errors()->messages())
			], 406);
		}

		if (Auth::attempt(['email' => $request->email, 'password' => DecryptPassword::decrypt($request->password)])) {

			 
			$user = Auth::user();
			$details['email'] = $user->email;
			if(config('app.server_type')=="prod"){

				$details['code'] = mt_rand(100000,999999);
			}else{
				
				$details['code'] = 123456;

			}
			$details['name'] = $user->first_name." ".$user->last_name;
			$details['lang'] = app()->getLocale();
			$details['url'] = config('app.frontend_url');
			$user->auth_code = $details['code'];
			$user->auth_code_expire = strtotime("now")+300;
			$user->save();
			 
			dispatch(new SendAuthEmailJob($details));
			return response()->json(['success' => true], $this->successStatus);

		} else {
			return response()->json(['error' =>trans('messages.unauthorize')], 406);
		}
	}

	 
	 
	private function decrypt($password){

		$string = json_decode(base64_decode($password),true);
		$encryption_key = "356d9abc7532ceb0945b615a622c3370"; 
		$ivkey = isset($string['iv']) ? $string['iv'] : '';
		$encrypted = isset($string['data']) ? $string['data'] : '';
		$encrypted = $encrypted . ':' . base64_encode($ivkey);
		$parts = explode(':', $encrypted);
		$decrypted = openssl_decrypt($parts[0], 'aes-256-cbc', $encryption_key, 0, base64_decode($parts[1]));
		return $decrypted;
	}

	/**
	 * Login user and create token, email and password needs to send through post
	 * @param  \Illuminate\Http\Request
	 * @return [json] token object, through an error if user credentials are not valid
	 */
	public function loginVerify(Request $request) {

		$messages = array(
			'email.required' => trans('messages.email.required'),
			'email.exists' => trans('messages.email.exists'),
			'email.email' => trans('messages.email.email'),
			'password.required' => trans('messages.password.required'),
			'password.min' => trans('messages.password.min', ['min' => 8]),
			'auth_code.required'=>trans('messages.auth_code.required')
		);

		$validator = Validator::make($request->all(),
		[
			'email' => 'required|email:filter|exists:users',
			'password' =>'required|min:8',
			'auth_code'=>'required'
		],$messages);

		//if validation failes, then  error would return
		if ($validator->fails()) {
			return response()->json([
				'error' => $validator->errors()->first(),
				'key'=>array_key_first($validator->errors()->messages())
			], 406);
		}

		if (Auth::attempt(['auth_code'=>$request->auth_code,'email' => $request->email, 'password' => DecryptPassword::decrypt($request->password)])) {

			$user = Auth::user();

			if(strtotime("now") > $user->auth_code_expire){
				
				return response()->json(['error' => trans('messages.auth_code_expire')], 406);
			}

			$user->auth_code = null;
			$user->auth_code_expire = null;
			$user->save();

			 
			if($user->hasRole('basic')==true){
				$user->role = "basic";
				$user->cv = QUYKCV::where('user_id',$user->id)->with('pictures_videos','videos','company_address','contact_details','social_networks','curriculum_qualifications')->first();
			}else if($user->hasRole('team-user')==true){
				$user->role = "team-user";
				$user->cv = QUYKCV::where('user_id',$user->id)->with('pictures_videos','videos','company_address','contact_details','social_networks','curriculum_qualifications')->first();
			}else if($user->hasRole('team')==true){
				$user->team = Teams::where('user_id',$user->id)->first();
				$user->role = "team";
			}else if($user->hasRole('super-admin')==true){
				$user->role = "super-admin";
			}
			
			$success['success'] = true;
			$success['data'] = $user;
			$success['token'] = $user->createToken('basf-token')->accessToken;
			return response()->json($success, $this->successStatus);
		} else {
			return response()->json(['error' => trans('messages.unauthorize')], 406);
		}
	}


	public function profile(Request $request){

		 
		$user = Auth::user();
		 
		if($user->hasRole('basic')==true){
			$user->role = "basic";
			$user->cv = QUYKCV::where('user_id',$user->id)->with('pictures_videos','videos','company_address','contact_details','social_networks','curriculum_qualifications')->first();
		}else if($user->hasRole('team-user')==true){
			$user->role = "team-user";
			$user->cv = QUYKCV::where('user_id',$user->id)->with('pictures_videos','videos','company_address','contact_details','social_networks','curriculum_qualifications')->first();
		}else if($user->hasRole('team')==true){
			$user->team = Teams::where('user_id',$user->id)->first();
			$user->role = "team";
		}else if($user->hasRole('super-admin')==true){
			$user->role = "super-admin";
		}
		
		 
		 
		return response()->json($user, $this->successStatus);
	}
	/**
	 * Login user and create token, email and password needs to send through post
	 * @param  \Illuminate\Http\Request
	 * @return [json] token object, through an error if user credentials are not valid
	 */
	public function login(Request $request) {

		$messages = array(
			'email.required' => trans('messages.email.required'),
			'email.exists' => trans('messages.email.exists'),
			'password.required' => trans('messages.password.required'),
			'password.min' => trans('messages.password.min', ['min' => 8]),
			'auth_code.required'=>trans('messages.auth_code.required')
		);

		$validator = Validator::make($request->all(),
		[
			'email' => 'required|email:filter|exists:users',
			'password' =>'required|min:8',
			'auth_code'=>'required'
		],$messages);

		//if validation failes, then  error would return
		if ($validator->fails()) {
			return response()->json([
				'error' => $validator->errors()->first(),
				'key'=>array_key_first($validator->errors()->messages())
			], 406);
		}

		if (Auth::attempt(['auth_code'=>$request->auth_code,'email' => $request->email, 'password' => DecryptPassword::decrypt($request->password)])) {

			$user = Auth::user();

			if(strtotime("now") > $user->auth_code_expire){
				
				return response()->json(['error' => trans('messages.auth_code_expire')], 406);
			}
			
			$user->auth_code = null;
			$user->save();

			 
			if($user->hasRole('basic')==true){
				$user->role = "basic";
				$user->cv = QUYKCV::where('user_id',$user->id)->with('pictures_videos','videos','company_address','contact_details','social_networks','curriculum_qualifications')->first();
			}else if($user->hasRole('team-user')==true){
				$user->role = "team-user";
				$user->cv = QUYKCV::where('user_id',$user->id)->with('pictures_videos','videos','company_address','contact_details','social_networks','curriculum_qualifications')->first();
			}else if($user->hasRole('team')==true){
				$user->team = Teams::where('user_id',$user->id)->first();
				$user->role = "team";
			}else if($user->hasRole('super-admin')==true){
				$user->role = "super-admin";
			}
			
			$success['data'] = $user;
			$success['token'] = $user->createToken('basf-token')->accessToken;
			return response()->json(['success' => $success], $this->successStatus);
		} else {
			return response()->json(['error' => trans('messages.unauthorize')], 406);
		}
	}

	/**
	 * Reset password link needs to send through post
	 * @param  \Illuminate\Http\Request
	 * @return [json] token object, through an error if user credentials are not valid
	 */
	public function resetpassword(Request $request) {

		$messages = array(
			'email.required' => trans('messages.email.required'),
			'email.exists' => trans('messages.email.exists'),
			'email.email' => trans('messages.email.email')
		);

		$validator = Validator::make($request->all(),
		[
			'email' => 'required|email:filter|exists:users,email,deleted_at,NULL'
		],$messages);
		 
		//if validation failes, then  error would return
		if ($validator->fails()) {
			return response()->json([
				'error' => $validator->errors()->first(),
				'key'=>array_key_first($validator->errors()->messages())
			], 406);
		}

		$user=User::where('email',$request->email)->first();
		$token = Str::random(60).$user->id;
		$user->setRememberToken($token);
		$user->save();

		$details['email'] = $user->email;
		$details['name'] = $user->first_name." ".$user->last_name;
		$details['token'] = $token;
		$details['lang'] = app()->getLocale();
		$details['url'] = config('app.frontend_url');
		dispatch(new SendResetPasswordEmailJob($details));
		return response()->json(['success' => true], $this->successStatus);
	}


	 /**
	 * Reset password link needs to send through post
	 * @param  \Illuminate\Http\Request
	 * @return [json] token object, through an error if user credentials are not valid
	 */
	public function updatePassword(Request $request) {

		$messages = array(
			'token.required' => trans('messages.token.required'),
			'token.exists' => trans('messages.token.exists'),
			'new_password.required' => trans('messages.new_password.required'),
			'new_password.min'=>trans('messages.new_password.min', ['min' => 8]),
			'confirm_password.required' => trans('messages.confirm_password.required'),
			'confirm_password.same' => trans('messages.confirm_password.same'),

		);

		$validator = Validator::make($request->all(),
		[
			'token' => 'required|exists:users,remember_token',
			'new_password' => 'required',
			'confirm_password' => 'required'
		],$messages);
		 
		//if validation failes, then  error would return
		if ($validator->fails()) {
			return response()->json([
				'error' => $validator->errors()->first(),
				'key'=>array_key_first($validator->errors()->messages())
			], 406);
		}
		$new_password = DecryptPassword::decrypt($request->new_password);
		$confirm_password = DecryptPassword::decrypt($request->confirm_password);

		if($new_password!=$confirm_password){

			return response()->json([
				'error' => trans('messages.confirm_password.same'),
				'key'=> "confirm_password"
			], 406);
			
		}

		$user=User::where('remember_token',$request->token)->first(); // get user according to token
		$user->password = bcrypt(DecryptPassword::decrypt($request->new_password)); // set new password
		$user->remember_token=null; // ser remember token null
		$user->save();   //user save
		return response()->json(['success' => true], $this->successStatus);
	}

	
	

	/**
	 * Get User Details, this will fetch all the information of authentive user
	 * @param  $username
	 * @return [json] user object
	 */
	public function getUser($username) {

		//auth user information
		$user=User::where('name',$username) -> first();
		return response()->json(['success' => $user], $this->successStatus);
	}


	/**
	 * Sign up invite member
	 * required params : first_name:string,last_name:string,password:
	 */
	public function signupInviteMember(Request $request){
		try{
			
			$messages = array(
				'first_name.required' => trans('messages.first_name.required'),
				'first_name.min' => trans('messages.first_name.min'),
				'last_name.required' => trans('messages.last_name.required'),
				'last_name.min' => trans('messages.last_name.min'),
				'last_name.min' => trans('messages.last_name.min'),
				'invite_code.exists' => trans('messages.invite_code.exists'),
				'password.min' => trans('messages.password.min', ['min' => 8]),
			);
			
			$validator = Validator::make($request->all(), [
				'first_name' => 'required|min:2',
				'last_name'=>'required|min:2',
				'password' =>'required|min:8',
				'privacy_policy' => 'required|boolean',
				'invite_code'=>'required|exists:team_users_invite,invite_code',
				'terms' => 'required|boolean',
			],$messages);
	
			if ($validator->fails()) {
	
				return response()->json(['error' => $validator->messages()->first()], 406);
	
			}
	
			$data = TeamUserInvite::where(['invite_code'=>$request->invite_code])->first();
			if($data){
				if($data->invite_status==1){
					return response()->json(['error' => trans('messages.invite_code.exists')], 406);
				}
	
				
				$input['email'] = $data->email;
				$input['password'] = bcrypt(DecryptPassword::decrypt(request('password')));
				$input['first_name'] = request('first_name');
				$input['last_name'] = request('last_name');
				$input['last_name_index'] = substr(request('last_name'), 0, 1);
				$user = User::create($input); 
				$user->roles()->attach(Role::where('slug','team-user')->first());
	
				TeamUser::create(['team_id'=>$data->team_id,'team_user_id'=>$user->id]);
	
				UsersSettings::create(['user_id'=>$user->id,'meta_key'=>'cv_edit_by_user','meta_value'=>1]);
				
				$data->invite_status=1;
				$data->save();
				//success message
				return response()->json(['success' => true], $this->successStatus);
				
			}else{
				return response()->json(['error' => "Invalid Invite Code"], 406);
			}

		}catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }
        
    }
}
