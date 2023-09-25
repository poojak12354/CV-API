<?php

namespace App\Http\Controllers\Api\TeamUser; 
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\QUYKCV;
use App\Models\Role;
use Illuminate\Support\Str;
use App\Models\Team_users as TeamUser;
use App\Models\Team_users_invite as TeamUserInvite;
use App\Models\Team_addresses as TeamAddresses;
use App\Models\Team_records as TeamRecords;
use App\Models\Teams;
use App\Models\UpdateEmail;
use App\Models\QUYKCVCompanyAddress;
use App\Models\QUYKCVContactDetails;
use App\Models\QUYKCVSocialNetworks;
use App\Models\QUYKCVCurriculumQualifications; 
use App\Models\QUYKCVPicturesVideos;
use App\Models\QUYKCVTempPictures;
use App\Models\QUYKCVCompanyAddressList;
use App\Models\QUYKCVGlobalAddress;
use App\Models\User;
use Spatie\Searchable\Search;
use App\Models\QUYKCVDesignSettings as DesignSettings;
use App\Models\UsersSettings;

use App\Rules\MatchOldPassword;
use Illuminate\Support\Facades\Hash;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use JeroenDesloovere\VCard\VCard;
use Validator;
use Storage;
use Image;
use App\Jobs\SendAuthUserEmailJob;
use App\Jobs\SendEmailChangeJob;
use App\Jobs\SendInviteUserEmailJob;
use App\Jobs\SendUpdateEmailVerify;
use App\Http\Traits\GetPercentage;
use App\Http\Traits\ImageVideoAssign;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Http\Traits\DecryptPassword;

 

class QUYKCVController extends Controller
{

    public function __construct(){

         
    }
    private function generateUniqueCVURL($cv_url,$cv_id=null)
    {
        try{
            $cv_url = mb_strtolower($cv_url);
            $variations = 0;

            while (true) {
                $new_cv_url = $cv_url;
                if ($variations > 0) {
                    $new_cv_url .= "-".(string) $variations;
                }
                if($cv_id){
                    $cv_url_Exist = QUYKCV::where('cv_url', $new_cv_url)->withTrashed()->where('id','!=',$cv_id)->exists();
                }else{
                    $cv_url_Exist = QUYKCV::where('cv_url', $new_cv_url)->withTrashed()->exists();
                }
                
                $team_url_exists = Teams::where('team_url',$new_cv_url)->exists();

                if ($cv_url_Exist || $team_url_exists) {
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

    /* generate short URL for cv */
    private function generateUniqueCVSHORTURL($cv_url,$cv_id=null)
    {
        try{
            $cv_url = mb_strtolower($cv_url);
            $variations = 0;

            while (true) {
                $new_cv_url = $cv_url;
                if ($variations > 0) {
                    $new_cv_url .= (string) $variations;
                }
                if($cv_id){
                    $cv_url_Exist = QUYKCV::where('cv_short_url', $new_cv_url)->withTrashed()->where('id','!=',$cv_id)->exists();
                }else{
                    $cv_url_Exist = QUYKCV::where('cv_short_url', $new_cv_url)->withTrashed()->exists();
                }

                $team_url_exists = Teams::where('team_url',$new_cv_url)->exists();
                
                if ($cv_url_Exist || $team_url_exists) {
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

    /* Edit cv pages data */
    public function EditCV(Request $request)
    {
        try{

            $user_id = Auth::id();
            $rules = array(
                'cv_id' => [                                                                  
                    'required',                                                            
                    Rule::exists('quyk_cv', 'id')                     
                    ->where(function ($query) use ($user_id) {                      
                        $query->where('user_id', $user_id);                  
                    }),                                                                    
                ], // check id exists with login user
                'email'=>'required|email:filter|exists:users,email', // contact details email validation
                'cv_main_data.first_name' => 'required', // first name validation
                'cv_main_data.last_name' => 'required', // last name validation
                'media_type' => 'in:image,video', // media type validation
                'address_type' => 'required|in:global,individual', // address type validation
                'company_id' => 'exists:team_global_addresses,id',
                'cv_contact_details.*.network'=>'required|in:email,fax,mobile,telephone,website',
                'cv_contact_details.*.url'=>'required', 
                'cv_social_networks.*.network'=>'sometimes|required',
                'cv_social_networks.*.url'=>'sometimes|required'
                );    
            $messages = array(
                'cv_main_data.first_name.required' => trans('messages.cv.first_name.required'),
                'cv_social_networks.*.network.required' =>trans('messages.cv.network.required'),
                'cv_social_networks.*.url.required' => trans('messages.cv.url.required'),
                'cv_main_data.last_name.required' => trans('messages.cv.last_name.required'),
                'cv_contact_details.*.network.required' =>trans('messages.cv.network.required'),
                'cv_contact_details.*.url.required' => trans('messages.cv.url.required',[ 'key' => ':network' ]),
                'cv_contact_details.*.url.distinct' => trans('messages.cv.url.distinct'),
                
            );
            
            $validator = Validator::make( $request->all(), $rules, $messages );  
            if ($validator->fails()) {
                
                $error = $validator->errors()->first();
                $key = array_key_first($validator->errors()->messages());
                if(strpos($key, "cv_contact_details") !== false){
                     
                    $keys = explode(".",$key);
                    $data = $request->all();
                    $error = str_replace(":network",$data['cv_contact_details'][$keys[1]]['network'],$error); 
                }

                return response()->json([
                    'error' => $error,
                    'key'=>array_key_first($validator->errors()->messages())
                ], 406);
            }

            // if($request->has('cv_images')){ // only one image should be active
            //     $active = 0;
            //     foreach($request->input('cv_images') as $images){
        
            //         if($images['active']==1){
            //             $active++;
            //         }
                    
            //     }
            //     if($active > 1 || $active==0){
            //         return response()->json(['error' =>"one image should be active"], 406);
            //     }

            // }

            
            $data = $request->all();

            if(!UsersSettings::where(['user_id'=>$user_id,'meta_key'=>'cv_edit_by_user'])->first()){
                
                UsersSettings::create(['user_id'=>$user_id,'meta_key'=>'cv_edit_by_user','meta_value'=>1]);
            
            }

             
            if(UsersSettings::where(['user_id'=>$user_id,'meta_key'=>'cv_edit_by_user'])->first()->meta_value==0){
                return response()->json(['error' =>trans('messages.cv.not_rights')], 406);
            }

            $cv =  QUYKCV::where(array('id'=>$data['cv_id'],'user_id'=>$user_id))->first();
            
             
           
            $data['cv_main_data']['cv_url']= $this->generateUniqueCVURL($data['cv_main_data']['first_name']."-".$data['cv_main_data']['last_name'],$request->cv_id);
            
            if(($cv->first_name!=$data['cv_main_data']['first_name']) || ($cv->last_name!=$data['cv_main_data']['last_name'])){
           
                $data['cv_main_data']['cv_short_url']= $this->generateUniqueCVSHORTURL(mb_substr($data['cv_main_data']['first_name'],0,2).mb_substr($data['cv_main_data']['last_name'],0,2).rand(1,10000),$request->cv_id);

           }
           $data['cv_main_data']['percentage']= GetPercentage::index($request);
           if($request->filled('media_type')){
            $data['cv_main_data']['media_type']= $request->media_type;
            }
            
            $data['cv_main_data']['address_type']= $request->address_type;


            $cv->update($data['cv_main_data']);

            QUYKCVCompanyAddress::where('cv_id',$data['cv_id'])->delete(); // delete previous added addresses
    
            /* multiple company addresses to add */

            if($request->filled('cv_company_address')){
                

                foreach($request->input('cv_company_address') as $company_address){

                
                    $company_address['cv_id'] = $cv->id;
                    
                    QUYKCVCompanyAddress::create($company_address);
                    
                }
            }

            if($request->address_type=="global" && $request->filled('company_id')){
                QUYKCVGlobalAddress::where('cv_id',$cv->id)->delete(); // delete previous added global address
                QUYKCVGlobalAddress::create(['cv_id'=>$cv->id,'company_id'=>$request->company_id]);
            }
            
            if($request->filled('cv_contact_details')){

                QUYKCVContactDetails::where('cv_id',$cv->id)->delete(); // delete previous added contact details

                /* multiple contact details to add */

                foreach($request->input('cv_contact_details') as $contact_details){

                    $contact_details['cv_id'] = $cv->id;
                    
                    QUYKCVContactDetails::create($contact_details);
                    
                }
            }

            /* multiple social networks to add */

            if($request->filled('cv_social_networks')){
                QUYKCVSocialNetworks::where('cv_id',$cv->id)->delete(); // delete previous added contact details

                foreach($request->input('cv_social_networks') as $social_networks){
                    
                    $social_networks['cv_id'] = $cv->id;
                    QUYKCVSocialNetworks::create($social_networks);
                     
                    
                }
            }

            if($request->filled('cv_curriculum_qualifications')){

                QUYKCVCurriculumQualifications::where('cv_id',$cv->id)->delete(); // delete previous added curriculum details


                /* multiple curriculum qualifications to add */

                foreach($request->input('cv_curriculum_qualifications') as $curriculum_qualifications){

                    $curriculum_qualifications['cv_id'] = $cv->id;

                     
                    
                    QUYKCVCurriculumQualifications::create($curriculum_qualifications);
                    
                }
            }

            ImageVideoAssign::index($request,$cv);

            
            
            
            // All went well
            return response()->json($cv, 200);

        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }
    }

    /**
     * Get Listing of Team User
     *
     * @throws Some_Exception_Class If something interesting cannot happen
     * @author Parvinder Singh 29 june 2021 <parvinder.singh@maracana.in>
     * @return Status
     */ 
    public function getMyUsers(Request $request){

        try{
            
            if ($request->has('limit')) { // if limit available in request
                $per_page =$request->limit; 
            }else{
                $per_page = 1;
            }
           return TeamUser::where('team_id',Auth::id())->with('user')->paginate($per_page);
          

        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getLine()." ".$e->getMessage()
            ], 406);
        }
    }

     /**
     * Get Listing of Team User with Last name
     *
     * @throws Some_Exception_Class If something interesting cannot happen
     * @author Parvinder Singh 29 june 2021 <parvinder.singh@maracana.in>
     * @return Status
     */ 
    public function getUsersWithCharacter(Request $request){

        try{
            
            $validator = Validator::make($request->all(), [
                'char' => 'required',
            ]);

            if ($validator->fails()) {

                return response()->json(['error' => $validator->messages()->first()], 406);

            }

            if ($request->has('limit')) { // if limit available in request
                $per_page =$request->limit; 
            }else{
                $per_page = 1;
            }

            if ($request->has('page')) { // if page param is available in request
                $page =$request->page; 
            }else{
                $page = 1;
            }

            $char = $request->char;

            $collection =  TeamUser::where('team_id',Auth::id())->with(['user'=>function($q) use($char) {
                $q->where('last_name_index', '=', $char);
            }])->get();

            
            $filtered = $collection->filter(function ($value, $key) {
                return $value->user !== null;
            });

            $filtered = $filtered->values();
            
            $filtered = $this->paginateCollection($filtered,$per_page,$page);
            
            return $filtered;

        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getLine()." ".$e->getMessage()
            ], 406);
        }
    }


    /* Add Global addresses by Team Admin */
    public function addAddress(Request $request){

        try{
            $messages = array(
            '*.company_name.required' => trans('messages.address.company_name'),
            '*.address_designation.required' =>trans('messages.address.address_designation'),
            '*.road.required' => trans('messages.address.road'),
            '*.road_no.required' => trans('messages.address.road_no'),
            '*.road_no.alpha_spaces' => trans('messages.cv.street_number'),
            '*.postcode.required' => trans('messages.address.postcode'),
            '*.place.required' => trans('messages.address.place'),
            '*.country.required' => trans('messages.address.country'),
            );
            
            $validator = Validator::make($request->all(), [
                '*.company_name'=>'required',
                '*.address_designation'=>'required',
                '*.additive'=>'nullable',
                '*.road'=>'required',
                '*.road_no'=>'required|alpha_spaces|nullable',
                '*.postcode'=>'required',
                '*.place'=>'required',
                '*.country'=>'required',
            ],$messages);

            if ($validator->fails()) {

                return response()->json(['error' => $validator->messages()->first()], 406);

            }
           
            $addresses = $request->all();
            foreach($addresses as $k=>$address){
                if(isset($address['id'])){

                    TeamAddresses::where(['id'=>$address['id'],'team_id'=>Auth::id()])->update($addresses[$k]);
                }else{
                    $addresses[$k]['team_id'] = Auth::id();
                    TeamAddresses::create($addresses[$k]);
                }
                
            }
            
            
            
            return response()->json(['status' => 1]);

        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getLine()." ".$e->getMessage()
            ], 406);
        }

    }


     /* Delete Global addresses by Team Admin */
     public function deleteAddress(Request $request){

        try{
            $team_id = Auth::id();
            $validator = Validator::make($request->all(), [
                'id' => [                                                                  
                    'required',                                                            
                    Rule::exists('team_global_addresses', 'id')                     
                    ->where(function ($query) use ($team_id) {                      
                        $query->where('team_id', $team_id);                  
                    }),                                                                    
                ]
            ]);

            if ($validator->fails()) {

                return response()->json(['error' => $validator->messages()->first()], 406);

            }
            TeamAddresses::where(['team_id' => Auth::id(),'id'=>$request->id])->forceDelete();;
            
            return response()->json(['status' => 1]);

        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getLine()." ".$e->getMessage()
            ], 406);
        }

    }

    

    /* Add Global Records by Team Admin */
    public function addRecord(Request $request){

        try{
            
            $validator = Validator::make($request->all(), [
                'heading_text.*'=>'required'
            ]);

            if ($validator->fails()) {

                return response()->json(['error' => $validator->messages()->first()], 406);

            }
             

            $records = $request->all();
            foreach($records as $k=>$record){
                if(isset($record['id'])){

                    TeamRecords::where(['id'=>$record['id'],'team_id'=>Auth::id()])->update($records[$k]);
                }else{
                    $records[$k]['team_id'] = Auth::id();
                    TeamRecords::create($records[$k]);
                }
                
            }
            
            return response()->json(['status' => 1]);

        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getLine()." ".$e->getMessage()
            ], 406);
        }

    }
    /* Add team overview text*/
    public function addTeamOverview(Request $request){

        try{
            $team_id = Auth::id();
            $validator = Validator::make($request->all(), [
                'overview'=>'required',
            ]);

            if ($validator->fails()) {

                return response()->json(['error' => $validator->messages()->first()], 406);

            }
            
            $team = Teams::where('user_id',$team_id)->first();

            $team->team_overview = $request->overview;
            $team->company_url = isset($request->company_url)? $request->company_url : null;;

            $team->save();
            
            return response()->json(['status' => 1]);

        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getLine()." ".$e->getMessage()
            ], 406);
        }
    }

    /**
	 * Reset password link needs to send through post
	 * @param  \Illuminate\Http\Request
	 * @return [json] token object, through an error if user credentials are not valid
	 */
	public function updatePassword(Request $request) {

        $messages = array(
                'current_password.required'=>trans('messages.current_password.required'),
                'new_password.required' => trans('messages.new_password.required'),
                'new_password.different' => trans('messages.new_password.different'),
                'new_password.min'=>trans('messages.new_password.min', ['min' => 8]),
                'confirm_password.required' => trans('messages.confirm_password.required'),
                'confirm_password.same' => trans('messages.confirm_password.same'),
    
            );
    
            $validator = Validator::make($request->all(),
            [
                'current_password' => 'required',
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
            $current_password = DecryptPassword::decrypt($request->current_password);
            $new_password     = DecryptPassword::decrypt($request->new_password);
            $confirm_password = DecryptPassword::decrypt($request->confirm_password);
            if (!Hash::check($current_password, auth()->user()->password)) {
                
                return response()->json([
                    'error' => trans('messages.current_password.match'),
                    'key'=> "confirm_password"
                ], 406);
            }
             
             
    
            if($new_password!=$confirm_password){
    
                return response()->json([
                    'error' => trans('messages.confirm_password.same'),
                    'key'=> "confirm_password"
                ], 406);
                
            }
            
            User::find(auth()->user()->id)->update(['password'=> Hash::make($new_password)]);
        
		return response()->json(['success' => true]);
	}

    

    /* Update team email from Team admin  */
    public function updateTeamEmail(Request $request){

        $messages = array(
            'email.unique'=>trans('messages.email.unique'),
            'email.email'=>trans('messages.email.email'),
            'email.required'=>trans('messages.email.required'),
        );
        
        $validator = Validator::make($request->all(),
		[
			'email' => 'required|email:filter|unique:users,email,' . auth()->user()->id,
            
		],$messages);
		 
		//if validation failes, then  error would return
		if ($validator->fails()) {
			return response()->json([
				'error' => $validator->errors()->first(),
				'key'=>array_key_first($validator->errors()->messages())
			], 406);
		}
        $token = Str::random(80).auth()->user()->id;

        UpdateEmail::where('user_id',auth()->user()->id)->forceDelete();

        UpdateEmail::create(['user_id'=>auth()->user()->id,'email'=>$request->email,'confirm_code'=>$token]);
        
		 
        $details['email'] = auth()->user()->email;
        $details['code'] = $token;
        $details['name'] = Teams::where('user_id',Auth::id())->first()->company_name;
        $details['lang'] = app()->getLocale();
        $details['url'] = config('app.frontend_url');
        dispatch(new SendUpdateEmailVerify($details));
		return response()->json(['success' => true]);


    }
    
    /* Update team url from Team admin  */
    public function updateTeamUrl(Request $request){

        $team_id = Auth::id();

        $team = Teams::where('user_id',$team_id)->first();
        
         
        $validator = Validator::make($request->all(),
		[
			'url' => 'required|min:6|unique:quyk_cv_teams_detail,team_url,' . $team_id . ",user_id",
            
		]);
		 
		//if validation failes, then  error would return
		if ($validator->fails()) {
			return response()->json([
				'error' => $validator->errors()->first(),
				'key'=>array_key_first($validator->errors()->messages())
			], 406);
		}

        $team->team_url = $request->url;
        $team->save();
		 
		return response()->json(['success' => true]);


    }
    /* Add team overview text*/
    public function addTeamPicture(Request $request){

        try{
            $team_id = Auth::id();
            $validator = Validator::make($request->all(), [
                'image' => 'required',
            ]);

            if ($validator->fails()) {

                return response()->json(['error' => $validator->messages()->first()], 406);

            }

            $base64_image = $request->input('image'); // your base64 encoded     
            @list($type, $file_data) = explode(';', $base64_image);
            @list(, $file_data) = explode(',', $file_data); 
            $imageName = Auth::id()."_".time().'.'.'png';  

            Storage::disk('local')->put("public/teams/".$imageName, base64_decode($file_data));
             
            $team = Teams::where('user_id',$team_id)->first();

            Storage::disk('public')->delete($team->team_picture);
        
            $team->team_picture = "teams/".$imageName;

            $team->save();

            $url=  Storage::disk('public')->url("teams/".$imageName);
            // All went well
            return response()->json([
                "status"=>1,
                'message' => trans('messages.file.success'),
                'data' => $url
            ]);
            
            
            
            

        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getLine()." ".$e->getMessage()
            ], 406);
        }
    }

    /* Edit Global Records by Team Admin */
    public function editRecord(Request $request){

        try{
            $team_id = Auth::id();
            $validator = Validator::make($request->all(), [
                'heading_text'=>'required',
                'id' => [                                                                  
                    'required',  
                    Rule::exists('team_global_records', 'id')                     
                    ->where(function ($query) use ($team_id) {                      
                        $query->where('team_id', $team_id);                  
                    }),                                                                    
                ]
            ]);

            if ($validator->fails()) {

                return response()->json(['error' => $validator->messages()->first()], 406);

            }
            TeamRecords::where(['team_id' => Auth::id(),'id'=>$request->id])->update($request->all());
            
            return response()->json(['status' => 1]);

        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getLine()." ".$e->getMessage()
            ], 406);
        }

    }

    /* Delete Global addresses by Team Admin */
    public function deleteRecord(Request $request){

        try{
            $team_id = Auth::id();
            $validator = Validator::make($request->all(), [
                'id' => [                                                                  
                    'required',                                                            
                    Rule::exists('team_global_records', 'id')                     
                    ->where(function ($query) use ($team_id) {                      
                        $query->where('team_id', $team_id);                  
                    }),                                                                    
                ]
            ]);

            if ($validator->fails()) {

                return response()->json(['error' => $validator->messages()->first()], 406);

            }
            TeamRecords::where(['team_id' => Auth::id(),'id'=>$request->id])->forceDelete();;
            
            return response()->json(['status' => 1]);

        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getLine()." ".$e->getMessage()
            ], 406);
        }

    }
    /* get team user CV */
    public function getUserCV(Request $request){

        try{
            
            $user_id = $request->user_id;
            $rules = array(
                'cv_id' => [                                                                  
                    'required',                                                            
                    Rule::exists('quyk_cv', 'id')                     
                    ->where(function ($query) use ($user_id) {                      
                        $query->where('user_id', $user_id);                  
                    }),                                                                    
                ], // check id exists with login user
                );    
            
            
            $validator = Validator::make( $request->all(), $rules );  
            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()->first()], 406);
            }

            if(TeamUser::where(['team_id'=>Auth::id(),'team_user_id'=>$user_id])->count()==0){
                return response()->json(['error' =>trans('messages.user.not_in_team')], 406);
            }

            

            $cvData = QUYKCV::with('pictures_videos','videos','company_address','contact_details','social_networks','curriculum_qualifications')->where(['id'=>$request->cv_id,'user_id'=>$user_id])->first();
            
            if(!$cvData){
                return response()->json([
                    'error' => trans('messages.wrong')
                ], 406);
            }

            $cvData->cv_edit_by_user = (int) UsersSettings::where(['user_id'=>$user_id,'meta_key'=>'cv_edit_by_user'])->first()->meta_value;
             
            return $cvData;

        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getLine()." ".$e->getMessage()
            ], 406);
        }
    }

    public function searchUser(Request $request){

        try{
            
            $validator = Validator::make($request->all(), [
                'char' => 'required',
            ]);

            if ($validator->fails()) {

                return response()->json(['error' => $validator->messages()->first()], 406);

            }

            if ($request->has('limit')) { // if limit available in request
                $per_page =$request->limit; 
            }else{
                $per_page = 1;
            }

            if ($request->has('page')) { // if page param is available in request
                $page =$request->page; 
            }else{
                $page = 1;
            }

            $char = $request->char;

            $collection =  TeamUser::where('team_id',Auth::id())->with(['user'=>function($q) use($char) {
                $q->where('first_name', 'like', '%' . $char . '%');
                $q->orWhere('last_name', 'like', '%' . $char . '%');
                $q->orWhere('email', 'like', '%' . $char . '%');
            }])->get();

            
            $filtered = $collection->filter(function ($value, $key) {
                return $value->user !== null;
            });

            $filtered = $filtered->values();
            
            $filtered = $this->paginateCollection($filtered,$per_page,$page);
            
            return $filtered;

        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getLine()." ".$e->getMessage()
            ], 406);
        }
    }


    
    public function inviteTeamMember(Request $request){

        try{
            
            $validator = Validator::make($request->all(), [
                'email'=>'required|email:filter|unique:users,email'
            ]);

            if ($validator->fails()) {

                return response()->json(['error' => $validator->messages()->first()], 406);

            }

            if(TeamUserInvite::where(['email'=>$request->email])->count()==0){

                $inviteCode = $this->generateRandomString(40);

                TeamUserInvite::create(['team_id'=>Auth::id(),'email'=>$request->email,'invite_code'=>$inviteCode]);
 
                $details['email'] = $request->email;
                $details['invite_code'] = $inviteCode;
                $details['lang'] = app()->getLocale();
                $details['url'] = config('app.frontend_url');
                $details['company_name'] = Teams::where('user_id',Auth::id())->first()->company_name;
                dispatch(new SendInviteUserEmailJob($details));

                return response()->json(['status' => 1,'message'=>trans('messages.invitation.sent')]);

            }else{
                $data = TeamUserInvite::where('email',$request->email)->first();
                if($data->invite_status==0){
                    $details['email'] = $request->email;
                    $details['invite_code'] = $data->invite_code;
                    $details['lang'] = app()->getLocale();
                    $details['url'] = config('app.frontend_url');
                    $details['company_name'] = Teams::where('user_id',$data->team_id)->first()->company_name;
                    dispatch(new SendInviteUserEmailJob($details));

                    return response()->json(['status' => 1,'message'=>trans('messages.invitation.sent')]);
                }else{
                    return response()->json(['error' => trans('messages.user.exists')], 406);
                }
                
            }
            

        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getLine()." ".$e->getMessage()
            ], 406);
        }
    }
    
    /* if Team admin wants to change permission for user like active deactive CV and edit permission */
    public function changePermission(Request $request){
        
        try{
            $user_id = $request->user_id;
             
            $validator = Validator::make($request->all(), [
                'cv_id' => [                                                                  
                    'required',                                                            
                    Rule::exists('quyk_cv', 'id')                     
                    ->where(function ($query) use ($user_id) {                      
                        $query->where('user_id', $user_id);                  
                    }),                                                                    
                ],
                'cv_page_active' => 'required|boolean',
			    'edit_by_user' => 'required|boolean',
            ]);

            if ($validator->fails()) {

                return response()->json(['error' => $validator->messages()->first()], 406);

            }


            
            if(TeamUser::where(['team_id'=>Auth::id(),'team_user_id'=>$user_id])->count()==0){
                return response()->json(['error' =>trans('messages.user.not_in_team')], 406);
            }

            

            $cv = QUYKCV::where(['id'=>$request->cv_id,'user_id'=>$request->user_id])->first();
            $cv->active = $request->cv_page_active;
            $cv->save();

            UsersSettings::where(['user_id'=>$user_id,'meta_key'=>'cv_edit_by_user'])->forceDelete();

            UsersSettings::create(['user_id'=>$user_id,'meta_key'=>'cv_edit_by_user','meta_value'=>$request->edit_by_user]);

            return response()->json(['status' => 1]);
            

        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getLine()." ".$e->getMessage()
            ], 406);
        }
    }
   
    public  function generateRandomString($length = 20) {
        $characters = strtotime("now").'0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    private function paginateCollection($items, $perPage = 50000000, $page = null, $options = [])
    {
        $page = $page ?: (\Illuminate\Pagination\Paginator::resolveCurrentPage() ?: 1);
        
        $items = $items instanceof \Illuminate\Support\Collection ? $items : \Illuminate\Support\Collection::make($items);
        return new \Illuminate\Pagination\LengthAwarePaginator($items->forPage($page, $perPage)->values(), $items->count(), $perPage, $page,['path' => url('api/v1/team-admin/getUsers')]);
    }

    /* Add CV data from team admin for user */
    public function AddCV(Request $request)
    {
       
        try{

          
            $rules = array(
                'cv_main_data.first_name' => 'required', // first name validation
                'cv_main_data.last_name' => 'required', // last name validation
                'media_type' => 'in:image,video', // media type validation
                'address_type' => 'required|in:global,individual', // address type validation
                'company_id' => 'exists:team_global_addresses,id',
                'cv_contact_details.*.network'=>'required|in:email,fax,mobile,telephone,website',
                'cv_contact_details.*.url'=>'required',
                'cv_images.*.image_id'=>'exists:quyk_cv_temp_pictures,temp_id',// cv images id validation
                'cv_social_networks.*.network'=>'sometimes|required',
                'cv_social_networks.*.url'=>'sometimes|required',
                'cv_company_address.*.street_number'=>'alpha_spaces|nullable'
                );    
            $messages = array(
                'cv_main_data.first_name.required' => trans('messages.cv.first_name.required'),
                'cv_social_networks.*.network.required' =>trans('messages.cv.network.required'),
                'cv_social_networks.*.url.required' => trans('messages.cv.url.required'),
                'cv_main_data.last_name.required' => trans('messages.cv.last_name.required'),
                'cv_contact_details.*.network.required' =>trans('messages.cv.network.required'),
                'cv_contact_details.*.url.required' => trans('messages.cv.url.required',[ 'key' => ':network' ]),
                'cv_contact_details.*.url.distinct' => trans('messages.cv.url.distinct'),
                'cv_images.*.image_id.exists' => trans('messages.cv.cv_images.image_id.exists'),
                'cv_company_address.*.street_number.alpha_spaces' => trans('messages.cv.street_number')
                );
            
            $validator = Validator::make( $request->all(), $rules, $messages );  
            if ($validator->fails()) {
                $error = $validator->errors()->first();
                $key = array_key_first($validator->errors()->messages());
                if(strpos($key, "cv_contact_details") !== false){
                     
                    $keys = explode(".",$key);
                    $data = $request->all();
                    $error = str_replace(":network",$data['cv_contact_details'][$keys[1]]['network'],$error); 
                }

                return response()->json([
                    'error' => $error,
                    'key'=>array_key_first($validator->errors()->messages())
                ], 406);
            }

            // if($request->has('cv_images')){ // only one image should be active
            //     $active = 0;
            //     foreach($request->input('cv_images') as $images){
        
            //         if($images['active']==1){
            //             $active++;
            //         }
                    
            //     }
            //     if($active > 1 || $active==0){
            //         return response()->json(['error' =>"one image should be active"], 406);
            //     }

            // }
             

            $data = $request->all();

            if(QUYKCV::where('user_id',Auth::id())->count() > 0){
                return response()->json(['error' =>trans('messages.cv.already')], 406);
            }

            $data['cv_main_data']['user_id']= Auth::id();
            $data['cv_main_data']['percentage']= GetPercentage::index($request);
            if($request->filled('media_type')){
                $data['cv_main_data']['media_type']= $request->media_type;
            }
            $data['cv_main_data']['cv_url']= $this->generateUniqueCVURL($data['cv_main_data']['first_name']."-".$data['cv_main_data']['last_name'],null);

            $data['cv_main_data']['cv_short_url']= $this->generateUniqueCVSHORTURL(mb_substr($data['cv_main_data']['first_name'],0,2).mb_substr($data['cv_main_data']['last_name'],0,2).rand(1,10000),null);

            $data['cv_main_data']['address_type']= $request->address_type;

            $cv = QUYKCV::create($data['cv_main_data']);

            /* multiple company addresses to add */
            if($request->filled('cv_company_address')){

                foreach($request->input('cv_company_address') as $company_address){

                    
                    $company_address['cv_id'] = $cv->id;
                    
                    QUYKCVCompanyAddress::create($company_address);
                    
                }
            }

            if($request->address_type=="global" && $request->filled('company_id')){

                QUYKCVGlobalAddress::create(['cv_id'=>$cv->id,'company_id'=>$request->company_id]);
            }

            if($request->filled('cv_contact_details')){

                /* multiple contact details to add */

                foreach($request->input('cv_contact_details') as $contact_details){

                    $contact_details['cv_id'] = $cv->id;
                    
                    QUYKCVContactDetails::create($contact_details);
                    
                }
            }

            /* multiple social networks to add */

            if($request->filled('cv_social_networks')){
                foreach($request->input('cv_social_networks') as $social_networks){

                    $social_networks['cv_id'] = $cv->id;
                    
                    QUYKCVSocialNetworks::create($social_networks);
                    
                }
            }

            if($request->filled('cv_curriculum_qualifications')){

                /* multiple curriculum qualifications to add */

                foreach($request->input('cv_curriculum_qualifications') as $curriculum_qualifications){

                    $curriculum_qualifications['cv_id'] = $cv->id;

                     
                    
                    QUYKCVCurriculumQualifications::create($curriculum_qualifications);
                    
                }
            }

            ImageVideoAssign::index($request,$cv);


             
            
            // All went well
            return response()->json($cv, 200);

        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getLine()." ".$e->getMessage()
            ], 406);
        }
    }
 

    /* function for upload image or video. this image will be publish when user will save cv page */

    public function imageUpload(Request $request){

        try{
            $validator = Validator::make($request->all(), [
                'image' => 'required',
                'type'=>'integer|between:1,2'
            ]);

            if ($validator->fails()) {

                return response()->json(['error' => $validator->messages()->first()], 406);

            }

            $this->setServerIniValue();
            
            $base64_image = $request->input('image'); // your base64 encoded     
            @list($type, $file_data) = explode(';', $base64_image);
            @list(, $file_data) = explode(',', $file_data); 
            $imageName = Auth::id()."_".time().'.'.'png';  
            $thumbImageName = "thumb_".Auth::id()."_".time().'.'.'png';  

            Storage::disk('local')->put("public/users/".$imageName, base64_decode($file_data));
            Storage::disk('local')->put("public/users/".$thumbImageName, base64_decode($file_data));
            $temp_id = Auth::id().strtotime("now");
            $save = QUYKCVTempPictures::create(array('user_id'=>Auth::id(),'location'=>"users/".$imageName,'thumb'=>"users/".$thumbImageName,'temp_id'=>$temp_id,'type'=>1)); 

            $uploadedImageResponse['image_id'] = $temp_id;
            $uploadedImageResponse['url'] =  Storage::disk('public')->url("users/".$imageName);
            $uploadedImageResponse['thumburl'] =  Storage::disk('public')->url("users/".$thumbImageName);
          
            $thumbnailpath = Storage::disk('public')->path("users/".$thumbImageName); 
            //Resize image here
            ini_set('memory_limit', '256M');
            $img = Image::make($thumbnailpath)->resize(200, 200, function($constraint) {
                $constraint->aspectRatio();
            });
            $img->save($thumbnailpath);

            $img->destroy();

            unset($thumbnailpath);
            
            // All went well
            return response()->json([
                "status"=>1,
                'message' => trans('messages.file.success'),
                'data' => $uploadedImageResponse
            ]);

        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }
 
    }

    private function setServerIniValue() {

		ini_set('memory_limit', '-1');
		ini_set('upload_max_filesize', '80M');
		ini_set('post_max_size', '80M');
		ini_set('max_input_time', '18000');
		ini_set('max_allowed_packet', '500M');
		ini_set('max_execution_time', '18000');
		ini_set('mysql.connect_timeout', 300);
		ini_set('default_socket_timeout', 300);

	}
    /* function for delete temp image when user uploading images on time of creating cv pages */ 
    public function imageDelete(Request $request){

        try{
            if(!request('cv_id')){
                $validator = Validator::make($request->all(), [
                    'image_id' => 'required|integer|exists:quyk_cv_temp_pictures,temp_id'
                ]);
            }else{

                $cv_id = request('cv_id');

                $rules = array( 
                                'image_id' => [                                                                  
                                        'required',                                                            
                                        Rule::exists('quyk_cv_profile_pictures_videos', 'id')                     
                                        ->where(function ($query) use ($cv_id) {                      
                                            $query->where('cv_id', $cv_id);                  
                                        }),                                                                    
                                    ], 
                                );  
                            
                $validator = Validator::make( $request->all(), $rules );
                
            }
            

            if ($validator->fails()) {
                return response()->json(['error' =>$validator->errors()->first()], 406);
            }

            if(request('cv_id')){

                $imageData = QUYKCVPicturesVideos::find($request->image_id);         
                if(Storage::disk('public')->delete($imageData->location)) {
                    $imageData->delete();
                }else{
                    return response()->json(['error' => trans('messages.wrong')], 406);
                }

            }else{
                $imageData = QUYKCVTempPictures::where('temp_id',$request->image_id)->first();         
                if(Storage::disk('public')->delete($imageData->location)) {
                    $imageData->delete();
                }else{
                    return response()->json(['error' => trans('messages.wrong')], 406);
                }
            }
            
            // All went well
            return response()->json([
                "status"=>1,
                'message' => trans('messages.file.delete'),
            ]);
        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }
    }


    /* get all cv pages listing */

    public function listing(Request $request){

        try{
            return QUYKCV::with('pictures_videos','videos','company_address','contact_details','social_networks','curriculum_qualifications')->paginate(10);
        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }
    }

    /* get cv page list according to id */
    public function list(Request $request,$id){

        try{
            if(!QUYKCV::find($request->id)){

                return response()->json(['error' => trans('messages.wrong')], 406);

            }else{
                return QUYKCV::with('pictures_videos','company_address','contact_details','social_networks','curriculum_qualifications')->find($request->id);
            }

        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }
    }

    // Duplicate record creation of cv page
    public function duplicatecv(Request $request){

        try{
            $user_id = Auth::id();
            $rules = array(
                'cv_id' => [                                                                  
                    'required',                                                            
                    Rule::exists('quyk_cv', 'id')                     
                    ->where(function ($query) use ($user_id) {                      
                        $query->where('user_id', $user_id);                  
                    }),                                                                    
                ], // check id exists with login user
                );    
            
            
            $validator = Validator::make( $request->all(), $rules );  
            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()->first()], 406);
            }
        
            $cv = QUYKCV::with('pictures_videos','videos','company_address','contact_details','social_networks','curriculum_qualifications')->find($request->cv_id);

            
            if($cv){
                
                $cv->cv_url= $this->generateUniqueCVURL($cv->first_name."-".$cv->last_name,null);
                $cv->cv_short_url= $this->generateUniqueCVSHORTURL(mb_substr($cv->first_name,0,2).mb_substr($cv->last_name,0,2).rand(1,10000),null);
        
                $newcv = $cv->replicate(); // replicate new records
                $newcv->push();
                foreach ($newcv->getRelations() as $relation => $entries) // creating duplicate according to relations
                {
                    foreach($entries as $entry)
                    {
                        $e = $entry->replicate();
                        if ($e->push())
                        {
                            $newcv->{$relation}()->save($e);
                        }
                    }
                }    
                // All went well
                return response()->json($cv, 200);
            }else{
                return response()->json(['error' => trans('messages.cv.not_exists')],406);
            }
        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }
    }

    /* search cv page list  */
    public function search(Request $request){

        try{
            $rules = array(
                'query' => 'required', // query validation
                );    
            $validator = Validator::make( $request->all(), $rules );  
            if ($validator->fails()) {
                return response()->json(['data' =>array(),'current_page'=>1,"per_page"=>10,"total"=>0]);
            }

            $searchResults = (new Search())
                ->registerModel(QUYKCV::class, 'salutation','title','cv_short_url','first_name','middle_name','last_name','position_in_company','cv_url')
                ->registerModel(QUYKCVCompanyAddress::class, 'company_name','additional_information','street','zip_code','city','country')
                ->registerModel(QUYKCVContactDetails::class, 'phone','mobile','email','website')
                ->registerModel(QUYKCVSocialNetworks::class, 'xing','linkedin','instagram','twitter')
                ->registerModel(QUYKCVCurriculumQualifications::class, 'headline','record_type','your_text')
                ->perform($request->input('query'));

                
            // get all cv id with results
            $cv_id = array();    
            foreach($searchResults as $searchResult){
                if(isset($searchResult->searchable->cv_id)){
                    $cv_id[]=$searchResult->searchable->cv_id;
                }else{
                    $cv_id[]=$searchResult->searchable->id;
                }
                
            }

            $cv_id = array_values(array_unique($cv_id));
    
            
            $cvs = array();
            foreach ($cv_id as $key => $value) {

                $valueData = QUYKCV::with('pictures_videos','videos','company_address','contact_details','social_networks','curriculum_qualifications')->find($value);
                if($valueData){
                    $cvs[$key] = $valueData;
                }
            
                
            }

            // add array pagination
            $paginate = 10;
            $page = $request->input('page',1);
            

            $offSet = ($page * $paginate) - $paginate;  
            $itemsForCurrentPage = array_slice($cvs, $offSet, $paginate, true);  
            $result = new \Illuminate\Pagination\LengthAwarePaginator($itemsForCurrentPage, count($cvs), $paginate, $page);
            $result = $result->toArray();
            $result['data'] = array_values($result['data']);
            return $result;
        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }
     }

     /* update settings of cv  */
     public function updateSettings(Request $request){

        try{
            $user_id = Auth::id();
            $rules = array(
                'cv_id' => [                                                                  
                    'required',                                                            
                    Rule::exists('quyk_cv', 'id')                     
                    ->where(function ($query) use ($user_id) {                      
                        $query->where('user_id', $user_id);                  
                    }),                                                                    
                ], // check id exists with login user
                'active' => 'required|boolean', // active status will be 1 or 0
                'external' => 'required|boolean', // external will be 1 or 0
                ); 
            $validator = Validator::make( $request->all(), $rules );  
            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()->first()], 406);
            } 


            $cv =  QUYKCV::find($request->cv_id);   
            $cv->active = $request->active;    
            $cv->external = $request->external;    
            $cv->save();

            return response()->json($cv, 200);
        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }
     }

     // get all companies list 
     public function getCompanies(Request $request){

        try{
            
            $teamUser = TeamUser::where(['team_user_id'=>Auth::id()])->first();
            if($teamUser){
            return TeamAddresses::where('team_id',$teamUser->team_id)->orderBy('id','asc')->get();

            }else{
                return [];
            }
            
        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }
     }

     // get particular company data 
     public function getCompany(Request $request,$id){

        try{
            return(TeamAddresses::where('id',$id)->first());
        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }

    }

    /* softdelete cv page from db */
    public function delete(Request $request){

        try{
            $user_id = Auth::id(); // fetch logged in user id
            $rules = array( 
                    'id' => [                                                                  
                        'required',                                                            
                        Rule::exists('quyk_cv', 'id')                     
                        ->where(function ($query) use ($user_id) {                      
                            $query->where('user_id', $user_id);                  
                        }),                                                                    
                    ], 
                );  
            
            $validator = Validator::make( $request->all(), $rules );  
            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors()->first()
                ], 406);
            }    

            $cv=QUYKCV::destroy($request->id); // destroy the cv page
            if($cv){ // if destroyed
                return response()->json([
                    "status"=>1,
                    'message' => trans('messages.cv.delete'),
                ]);
            }else{ // if already destroyed
                return response()->json([
                    'error' => trans('messages.cv.deleted'),
                ], 406);

            }
        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }
        

        

    }

    /* get cv page list according to id */
    public function getUserCVold(Request $request){

        try {
            $rules = array('cv_url'=>'required');    
        
            $validator = Validator::make( $request->all(), $rules);  

            if ($validator->fails()) {
                
                return response()->json([
                    'error' => $validator->errors()->first(),
                    'key'     => array_key_first($validator->errors()->messages())
                ], 406);
            }

            
            
            
            $cvData = QUYKCV::with('pictures_videos','videos','company_address','contact_details','social_networks','curriculum_qualifications')->where('cv_url',$request->cv_url)->orWhere('cv_short_url',$request->cv_url)->first();
            
            if(!$cvData){
                return response()->json([
                    'error' => trans('messages.token.invalid')
                ], 406);
            }
            $settings = DesignSettings::where(['user_id'=>$cvData->user_id])->get();

             
            $settingsData = [];
            foreach ($settings as $key => $value) {
                if($value->meta_key=="logo"){
                    $settingsData[$value->meta_key]= config('app.url').Storage::url($value->meta_value);
                }else{
                    $settingsData[$value->meta_key]=$value->meta_value;
                }
                
            }

            $cvData->designSettings = $settingsData;
            
            if($cvData && $cvData->external==0){
                if (Auth::guard('api')->check()) {
                    $user = Auth::guard('api')->user();
                }else{
                    return response()->json([
                        'error' => trans('messages.token.invalid')
                    ], 406);
                }
            }
            
            if($cvData && $cvData->active==1){
                if($cvData->middle_name==null){$cvData->middle_name="";}
                return $cvData;
            }else{

                if (Auth::guard('api')->check()) {
                    return $cvData;
                }else{
                    return response()->json([
                        'error' => trans('messages.token.invalid')
                    ], 406);
                }
                 
            }

        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }
        
    }

    

    /* get cv vcf list according to id */
    public function getUserVCF(Request $request){

        try {
            
            $rules = array('cv_url'=>'required');    
        
            $validator = Validator::make( $request->all(), $rules);  
            if ($validator->fails()) {
                
                return response()->json([
                    'error' => $validator->errors()->first(),
                    'key'     => array_key_first($validator->errors()->messages())
                ], 406);
            }
             
            $cvData = QUYKCV::with('pictures_videos','videos','company_address','contact_details','social_networks','curriculum_qualifications')->where('cv_url',$request->cv_url)->orWhere('cv_short_url',$request->cv_url)->first();
            
            if(!$cvData){
                return response()->json([
                'url' => ''
                ]);
            }
             

            if($cvData && $cvData->external==0){
                if (Auth::guard('api')->check()) {
                    $cv_data = $cvData;
                }else{
                    return response()->json([
                        'url' => ''
                        ]);
                }
            } 
            
           
            if($cvData && $cvData->active==1){
                $cv_data = $cvData;
            }else{

                if (Auth::guard('api')->check()) {
                    $cv_data = $cvData;
                }else{
                    return response()->json([
                        'url' => ''
                        ]);
                }
                 
            }

            
            
            // define vcard
            $vcard = new VCard();

            // define variables
            $lastname = $cv_data->last_name;
            $firstname = $cv_data->first_name;
            $additional = '';
            $prefix = '';
            $suffix = '';
            $file_name = uniqid();
            // add work data
            $vcard->addName($lastname, $firstname, $additional, $prefix, $suffix);
            // add work data
            $vcard->addCompany($cv_data->company_address[0]->company_name);
            $vcard->addJobtitle($cv_data->position_in_company);
           
            $vcard->addEmail($cv_data->contact_details[0]->email);
            $vcard->addPhoneNumber($cv_data->contact_details[0]->phone, 'PREF;WORK');
            $vcard->addPhoneNumber($cv_data->contact_details[0]->mobile, 'MOBILE');

            if(isset($cv_data->pictures_video[0]->location)){
                $vcard->addPhoto(storage_path('app/public/').$cv_data->pictures_videos[0]->location);
            }

            $vcard->setFilename($file_name);
            $vcard->setSavePath(storage_path('app/public/vcf'));

            
            $saveDa= $vcard->save();
             
            return response()->json([
                'url' => Storage::disk('public')->url("vcf/".$file_name.".vcf")
            ]);

           // Storage::disk('public')->url("users/".$imageName);
        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getLine()." ".$e->getMessage()
            ], 406);
        }
        
    }

    public function videoUpload(Request $request){

        try{

             
            $validator = Validator::make($request->all(), [
                'video' => 'required|mimes:mp4,mov'
            ]);

            if ($validator->fails()) {

                return response()->json(['error' => $validator->messages()->first()], 406);

            }
            $file = $request->file('video');
            $filename = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();

            $videoName = Auth::id().time().'.'.$extension;  
            
            $thumbName = Auth::id().time().'.jpg';  

             
             
            Storage::disk('local')->put("public/users/".$videoName,file_get_contents($file));
           
            $string = "ffmpeg -i ".Storage::disk('public')->path("users/".$videoName)." -ss 00:00:02.000 -vframes 1 ".Storage::disk('public')->path("users/").$thumbName."";
            shell_exec($string);
            //$v =  VideoThumbnail::createThumbnail(Storage::disk('public')->path("users/".$videoName), Storage::disk('public')->path("users"), $thumbName, 2, 74, 80);

             
            $temp_id = Auth::id().strtotime("now");
            
           
            $save = QUYKCVTempPictures::create(array('user_id'=>Auth::id(),'location'=>"users/".$videoName,'file_name'=>$filename,'temp_id'=>$temp_id,'type'=>2,'thumb'=>"users/".$thumbName)); 

            $uploadedImageResponse['video_id'] = $temp_id;
            $uploadedImageResponse['url'] =  Storage::disk('public')->url("users/".$videoName);
            $uploadedImageResponse['thumb'] =  Storage::disk('public')->url("users/".$thumbName);
            // All went well
            return response()->json([
                "status"=>1,
                'message' => trans('messages.file.success'),
                'data' => $uploadedImageResponse
            ]);

        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }
 
    }

    /* function for delete temp video when user uploading video on time of creating cv pages */ 
    public function videoDelete(Request $request){

        try{
            if(!request('cv_id')){
                $validator = Validator::make($request->all(), [
                    'video_id' => 'required|integer|exists:quyk_cv_temp_pictures,temp_id'
                ]);
            }else{

                $cv_id = request('cv_id');

                $rules = array( 
                                'video_id' => [                                                                  
                                        'required',                                                            
                                        Rule::exists('quyk_cv_profile_pictures_videos', 'id')                     
                                        ->where(function ($query) use ($cv_id) {                      
                                            $query->where('cv_id', $cv_id);                  
                                        }),                                                                    
                                    ], 
                                );  
                            
                $validator = Validator::make( $request->all(), $rules );
                
            }
            

            if ($validator->fails()) {
                return response()->json(['error' =>$validator->errors()->first()], 406);
            }

            if(request('cv_id')){

                $videoData = QUYKCVPicturesVideos::find($request->video_id);         
                if(Storage::disk('public')->delete($videoData->location)) {
                    Storage::disk('public')->delete($videoData->thumb);
                    $videoData->delete();
                }else{
                    return response()->json(['error' => trans('messages.wrong')], 406);
                }

            }else{
                $videoData = QUYKCVTempPictures::where('temp_id',$request->video_id)->first();         
                if(Storage::disk('public')->delete($videoData->location)) {
                    Storage::disk('public')->delete($videoData->thumb);
                    $videoData->delete();
                }else{
                    return response()->json(['error' => trans('messages.wrong')], 406);
                }
            }
            
            // All went well
            return response()->json([
                "status"=>1,
                'message' => trans('messages.file.delete'),
            ]);
        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }
    }
}
