<?php

namespace App\Http\Controllers\Api;
use Illuminate\Support\Facades\DB;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\QUYKCV;
use App\Models\Team_users as TeamUser;
use App\Models\Team_addresses as TeamAddresses;
use App\Models\Teams;
use App\Models\Team_records as TeamRecords;
use App\Models\QUYKCVCompanyAddress;
use App\Models\QUYKCVContactDetails;
use App\Models\QUYKCVSocialNetworks;
use App\Models\QUYKCVCurriculumQualifications;
use App\Models\QUYKCVPicturesVideos;
use App\Models\QUYKCVTempPictures;
use App\Models\QUYKCVCompanyAddressList;
use App\Models\QUYKCVGlobalAddress;
use App\Models\User;
use App\Models\Role;
use App\Models\SingleUserAddress;
use Spatie\Searchable\Search;
use App\Models\QUYKCVDesignSettings as DesignSettings;
use App\Models\UpdateEmail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use JeroenDesloovere\VCard\VCard;
use Validator;
use Storage;
use Image;
use Illuminate\Support\Facades\Hash;
use App\Http\Traits\CreateShortUrl;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Jobs\SendUpdateEmailVerify;
use App\Models\UsersSettings;


use App\Rules\MatchOldPassword;

use App\Jobs\SendAuthUserEmailJob;
use App\Jobs\SendEmailChangeJob;
use App\Jobs\SendInviteUserEmailJob;
use App\Http\Traits\GetPercentage;
use App\Http\Traits\ImageVideoAssign;
use App\Http\Traits\DecryptPassword;


class SuperAdminController extends Controller
{

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
                    $new_cv_url .= "-".(string) $variations;
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

    /* get the companies according to team admin 
     * @author Parvinder 04 JAN 2022*/
    public function getCompanies(Request $request){

        try{
            
            $messages = array(
                'team_id.required' => trans('messages.team_id.required')
            );
            $validator = Validator::make($request->all(), [
                'team_id'=>'required'
            ],$messages);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->messages()->first()], 406);
            }
            return TeamAddresses::where('team_id',$request->team_id)->orderBy('company_name','asc')->get();
             
        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }
    }


     
    /* get particular company data 
     * @author Parvinder 04 JAN 2022*/
    public function getCompany(Request $request,$id){

        try{
            return(TeamAddresses::where('id',$id)->first());
        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }

    }
   
    public function searchUser(Request $request)
    {

        $rules = array(
            'char' => 'required', // query validation
            );    
        $validator = Validator::make( $request->all(), $rules );  
        if ($validator->fails()) {
            return response()->json(['data' =>array(),'current_page'=>1,"per_page"=>10,"total"=>0]);
        }


        // add array pagination
        if ($request->has('limit')) { // if limit available in request
            $paginate =$request->limit; 
        }else{
            $paginate = 10;
        }

        $q = $request->char;
        return User::with('roles')->where(function($query) use ($q) {
            $query->where('first_name', 'LIKE', '%'.$q.'%')
                ->orWhere('last_name', 'LIKE', '%'.$q.'%')
                ->orWhere('email', 'LIKE', '%'.$q.'%');
        })->whereHas(
            'roles', function($q){
                $q->whereIn('name',['team','basic']);
            }
        )->paginate($paginate);

    }


    public function changeUserStatus(Request $request){


        try{


            $user_id = $request->user_id;
            $rules = array(
                'cv_id' => [                                                                  
                    'required',                                                            
                    Rule::exists('quyk_cv', 'id')                     
                    ->where(function ($query) use ($user_id) {                      
                        $query->where('user_id', $user_id);                  
                    }),                                                                    
                ], // check id exists with login use
                'action' => 'required|in:CVSTATUS,EDITPERMISSION',
                'value' => 'required|numeric|between:0,1',
            );
            
            $validator = Validator::make($request->all(),$rules);

            if ($validator->fails()) {

                return response()->json(['error' => $validator->messages()->first()], 406);

            }

            if($request->action=="CVSTATUS"){

                $cvData = QUYKCV::where(['id'=>$request->cv_id,'user_id'=>$request->user_id])->first();
            
                $cvData->active = $request->value;
                $cvData->save();

            }

            if($request->action=="EDITPERMISSION"){

                $settingData = UsersSettings::where(['meta_key'=>'cv_edit_by_user','user_id'=>$request->user_id])->first();
            
                $settingData->meta_value = $request->value;
                
                $settingData->save();

               
            }
            
            return response()->json([
                'status' => 1
            ]);

             

                 
            }catch(\Exception $e) {
                return response()->json([
                    'error' => $e->getMessage()
                ], 406);
            }
    }
    /* get the records for all users 
     * @author Mangita 22 Dec 2021*/
    public function getListUsers(Request $request)
    {
        
        try{
            
            // add array pagination
            if ($request->has('limit')) { // if limit available in request
                $paginate =$request->limit; 
            }else{
                $paginate = 10;
            }

            return User::with('roles')->whereHas(
                'roles', function($q){
                    $q->whereIn('name',['team','basic']);
                }
            )->latest()->paginate($paginate);

          //   print_r($users->toArray());die;

            $roles=['team','basic'];

            $userlist= DB::table('users')
            ->join('users_roles', 'users.id', '=', 'users_roles.user_id')
            ->join('roles', 'roles.id', '=', 'users_roles.role_id')
            ->select('users.id','users.first_name', 'users.last_name','roles.slug')
            ->whereIn('roles.slug', $roles)
            ->get();
           
            $result = json_decode($userlist, true);
            
            $page = $request->input('page',1);
            $offSet = ($page * $paginate) - $paginate;  
            foreach ($result as $key => $value) {
                $cvPage  =QUYKCV::where('user_id',$result[$key]['id'])->first();
                $status=0;
                if($cvPage) $status=$cvPage->active;
                if($result[$key]['slug']=='basic') $result[$key]['slug']='Single';
                $cvPageCount =  TeamUser::where(array('team_id'=>$result[$key]['id']))->count();
                $result[$key]['cvpages'] = $cvPageCount;
                $result[$key]['status']= $status;
                $result[$key]['gesamthits'] = 133;
                $result[$key]['price'] = '0,00 €';
            }

            $itemsForCurrentPage = array_slice($result, $offSet, $paginate, true);  
            $results = new \Illuminate\Pagination\LengthAwarePaginator($itemsForCurrentPage, count($result), $paginate, $page);
            $results = $results->toArray();
            $results['data'] = array_values($results['data']);
            return $results;
        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }

    }

    /* Delete userdataby super Admin

     * @author Mangita 22 Dec 2021 */ 
     public function deleteUser(Request $request){

        try{
            $id = $request->id;
            $validator = Validator::make($request->all(), [
                'id' => [                                                                  
                    'required',                                                            
                    Rule::exists('users', 'id')                     
                    ->where(function ($query) use ($id) {                      
                        $query->where('id', $id);                  
                    }),                                                                    
                ]
            ]);

            if ($validator->fails()) {

                return response()->json(['error' => $validator->messages()->first()], 406);

            }
            User::where(['id'=>$request->id])->forceDelete();;
            
            return response()->json(['status' => 1]);

        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getLine()." ".$e->getMessage()
            ], 406);
        }

    }


    /* Edit user data by super-admin 
     * @author Mangita 23 Dec 2021 */
    public function editUser(Request $request)
    {
        try{
            return app('App\Http\Controllers\Api\TeamAdmin\QUYKCVController')->getMyUsers($request);
        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }
        
    }


    /* Edit user data by super-admin 
     * @author Mangita 23 Dec 2021 */
    public function getUsers(Request $request)
    {
        try{

        $messages = array(
            'id.required' => trans('messages.team_id.required'),
            'slug.required' =>trans('messages.team_id.required')
        );
        $validator = Validator::make($request->all(), [
            'id'=>'required',
            'slug'=>'required|in:Single,Team'
        ],$messages);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->messages()->first()], 406);
        }
        if ($request->has('limit')) { // if limit available in request
            $per_page =$request->limit; 
        }else{
            $per_page = 20;
        }
        if($request->slug=="Team"){ // if slug will team then we will send all team users
            
            return TeamUser::where('team_id',$request->id)->with('user')->paginate($per_page);

        }
        if($request->slug=="Single"){ // if slug will Single then we will CV details
            
            return QUYKCV::with('global_address','pictures_videos','videos','company_address','contact_details','social_networks','curriculum_qualifications')->where('user_id',$request->id)->first();

        }


  

             
        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }
        
    }


    public function getCVDetails(Request $request)
    {
        try{

            $messages = array(
                'id.required' => trans('messages.cv.required'),
                 
            );
            $validator = Validator::make($request->all(), [
                'id'=>'required|exists:quyk_cv,id'
            ],$messages);
    
            if ($validator->fails()) {
                return response()->json(['error' => $validator->messages()->first()], 406);
            }
             
            
                $cvData =  QUYKCV::with('global_address','pictures_videos','videos','company_address','contact_details','social_networks','curriculum_qualifications')->where('id',$request->id)->first();
                $cvData->edit_by_user = (int) UsersSettings::where(['user_id'=>$cvData->user_id,'meta_key'=>'cv_edit_by_user'])->first()->meta_value;
                $cvData->cv_page_active = $cvData->active;
                $cvData->send_login_details = 0;
                return $cvData;
                 
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



   

    

   

    

     


    public function getUserCharacters(Request $request){

        try{
            
             
            $users =  User::with('roles')->whereHas(
                'roles', function($q){
                    $q->whereIn('name',['team','basic']);
                }
            )->get();
          
            $character = [];
            foreach($users as $k=>$user){
                $character[$k] = strtoupper($user->last_name_index);
            }

            $character = array_values(array_unique($character));

            return response()->json($character);
            

        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getLine()." ".$e->getMessage()
            ], 406);
        }
    }

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
                $per_page = 10;
            }

            if ($request->has('page')) { // if page param is available in request
                $page =$request->page; 
            }else{
                $page = 1;
            }

            $char = $request->char;

           return  User::where(['last_name_index'=>$request->char])->whereHas(
                'roles', function($q){
                    $q->whereIn('name',['team','basic']);
                }
            )->paginate($per_page);

             

        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getLine()." ".$e->getMessage()
            ], 406);
        }
    }


     
    /* Edit CV of team user by Super admin  
     * @author Parvninder 05 jan 2021 */
    public function teadAdminEditCV(Request $request)
    {
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
                'cv_page_active' => 'required|boolean',
			    'edit_by_user' => 'required|boolean',
			    'send_login_details' => 'boolean',
                'team_id'=>'required',
                'email'=>'required|email:filter|unique:users,email,'.$user_id, // contact details email validation
                'cv_main_data.first_name' => 'required', // first name validation
                'cv_main_data.last_name' => 'required', // last name validation
                'media_type' => 'in:image,video', // media type validation
                'address_type' => 'required|in:global,individual', // address type validation
                'company_id' => 'exists:team_global_addresses,id',
                'cv_contact_details.*.network'=>'required|in:email,fax,mobile,telephone,website',
                'cv_contact_details.*.url'=>'required', 
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
                'team_id.required' => trans('messages.team_id.required'),
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

            $team_id = $request->team_id;
            $data = $request->all();

            if(TeamUser::where(['team_id'=>$team_id,'team_user_id'=>$user_id])->count()==0){
                return response()->json(['error' =>trans('messages.user.not_in_team')], 406);
            }

             
            $user = User::where('id',$user_id)->first();
            $password = rand(100000000,999999999);
            if($user->email!=$request->email){ 
                $previous_email = $user->email;
                $user->email = $request->email;
                $user->save();

                $details['name'] = $user->first_name." ".$user->last_name;
                $details['new_email'] = $user->email;
                $details['previous_email'] = $previous_email;
                $details['email'] = $previous_email;
                $details['password'] = $password;
                $details['lang'] = app()->getLocale();
                $details['url'] = config('app.frontend_url');
                $details['company_name'] = Teams::where('user_id',$team_id)->first()->company_name;
                dispatch(new SendEmailChangeJob($details));
            }

            
             
           
            $cv =  QUYKCV::where(array('id'=>$data['cv_id'],'user_id'=>$user_id))->first();
            
            UsersSettings::where(['user_id'=>$user_id,'meta_key'=>'cv_edit_by_user'])->forceDelete();

            UsersSettings::create(['user_id'=>$user_id,'meta_key'=>'cv_edit_by_user','meta_value'=>(int) $request->edit_by_user]);
            
            $data['cv_main_data']['cv_url']= $this->generateUniqueCVURL($data['cv_main_data']['first_name']."-".$data['cv_main_data']['last_name'],$request->cv_id);
            if($request->filled('media_type')){
                $data['cv_main_data']['media_type']= $request->media_type;
            }
            if(($cv->first_name!=$data['cv_main_data']['first_name']) || ($cv->last_name!=$data['cv_main_data']['last_name'])){
                $data['cv_main_data']['cv_short_url']= $this->generateUniqueCVSHORTURL(mb_substr($data['cv_main_data']['first_name'],0,2).mb_substr($data['cv_main_data']['last_name'],0,2).rand(1,10000),$request->cv_id);
            }
            
            $data['cv_main_data']['percentage']= GetPercentage::index($request);
            if($request->filled('media_type')){
                $data['cv_main_data']['media_type']= $request->media_type;
            }
            $data['cv_main_data']['active'] = (int) $data['cv_page_active'];
            
            $data['cv_main_data']['address_type']= $request->address_type;
 
            $cv->update($data['cv_main_data']);
    
            /* user first name last name update according to cv */

            $user->first_name = $data['cv_main_data']['first_name']; 
            $user->last_name = $data['cv_main_data']['last_name']; 
            $user->last_name_index = substr($data['cv_main_data']['last_name'], 0, 1); 

            
            $user->save();

            /* multiple company addresses to add */

            QUYKCVCompanyAddress::where('cv_id',$cv->id)->delete(); // delete previous added addresses
            
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
            /* multiple contact details to add */

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

            /* multiple curriculum qualifications to add */
            if($request->filled('cv_curriculum_qualifications')){
                QUYKCVCurriculumQualifications::where('cv_id',$cv->id)->delete();
                foreach($request->input('cv_curriculum_qualifications') as $curriculum_qualifications){
                        $curriculum_qualifications['cv_id'] = $cv->id;
                        QUYKCVCurriculumQualifications::create($curriculum_qualifications);
                     
                }
            }

            ImageVideoAssign::index($request,$cv);

            
            if($request->edit_by_user==true && $request->send_login_details==true){

                $user->password = bcrypt($password);
                $user->save();
                $details['name'] = $user->first_name." ".$user->last_name;
                $details['email'] = $user->email;
                $details['password'] = $password;
                $details['lang'] = app()->getLocale();
                $details['url'] = config('app.frontend_url');
                $details['company_name'] = Teams::where('user_id',$team_id)->first()->company_name;
                dispatch(new SendAuthUserEmailJob($details));
            }
            
            // All went well
            return response()->json($cv, 200);

        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }
    }


    /* Edit CV of Single User by Super admin  
     * @author Parvninder 05 jan 2021 */
    public function singleEditCV(Request $request)
    {
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
                'cv_main_data.first_name' => 'required', // first name validation
                'cv_main_data.last_name' => 'required', // last name validation
                'media_type' => 'in:image,video', // media type validation
                'cv_social_networks.*.network'=>'sometimes|required',
                'cv_social_networks.*.url'=>'sometimes|required',
                'cv_contact_details.*.network'=>'required|in:email,fax,mobile,telephone,website',
                'cv_contact_details.*.url'=>'required'
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

            $cv =  QUYKCV::where(array('id'=>$data['cv_id'],'user_id'=>$user_id))->first();
            
            
            $data['cv_main_data']['cv_url']= $this->generateUniqueCVURL($data['cv_main_data']['first_name']."-".$data['cv_main_data']['last_name'],$request->cv_id);
            
            if(($cv->first_name!=$data['cv_main_data']['first_name']) || ($cv->last_name!=$data['cv_main_data']['last_name'])){
           
                 $data['cv_main_data']['cv_short_url']= $this->generateUniqueCVSHORTURL(mb_substr($data['cv_main_data']['first_name'],0,2).mb_substr($data['cv_main_data']['last_name'],0,2).rand(1,10000),$request->cv_id);

            }
            
            $data['cv_main_data']['percentage']= GetPercentage::index($request);
            if($request->filled('media_type')){
                $data['cv_main_data']['media_type']= $request->media_type;
            }
            
            $cv->update($data['cv_main_data']);
    
             
            

            /* multiple company addresses to add */

            QUYKCVCompanyAddress::where('cv_id',$data['cv_id'])->delete(); // delete previous added addresses

            
            if($request->filled('cv_company_address')){
                

                foreach($request->input('cv_company_address') as $company_address){

                
                    $company_address['cv_id'] = $data['cv_id'];
                    
                    QUYKCVCompanyAddress::create($company_address);
                    
                }
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
                'error' => $e->getLine()." ".$e->getMessage()
            ], 406);
        }
    }


    /* Soft Delete CV   
     * @author Parvninder 06 jan 2021 */
     public function deleteCV(Request $request){

        try{
            $user_id = $request->user_id; // fetch logged in user id
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

            if(TeamUser::where(['team_user_id'=>$user_id])->count() > 0){
                
                $cv = User::where('id',$user_id)->forceDelete();
                
            }else{
            
                $cv = QUYKCV::destroy($request->id); // destroy the cv page

            }

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


    /* Add CV data from team admin for user */
    public function AddCV(Request $request)
    {
 
        
        try{

            if($request->has('user_id')){

                $email_validation = 'required|email:filter|exists:users';
    
            }else{
                $email_validation = 'required|email:filter|unique:users';
            }

           
            $rules = array(
                'cv_page_active' => 'required|boolean',
			    'edit_by_user' => 'required|boolean',
			    'send_login_details' => 'boolean',
                'team_id'=>'required',
                'email'=>$email_validation, // contact details email validation
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

            $team_id = $request->team_id;

            if ($request->has('user_id')) {

                if(User::where(['id'=>$request->user_id,'email'=>$request->email])->count()==0){
                    return response()->json(['error' =>"This user is not in your team"], 406);
                }

                $user = User::where(['id'=>$request->user_id,'email'=>$request->email])->first();
               
                
                if(TeamUser::where(['team_id'=>$team_id,'team_user_id'=>$request->user_id])->count()==0){
                    return response()->json(['error' =>"This user is not in your team"], 406);
                }

                if(QUYKCV::where('user_id',$request->user_id)->count() > 0){
                    return response()->json(['error' =>"This User CV already added"], 406);
                }

                UsersSettings::create(['user_id'=>$request->user_id,'meta_key'=>'cv_edit_by_user','meta_value'=>$request->edit_by_user]);

                $data['cv_main_data']['user_id']= $request->user_id;
                if($request->filled('media_type')){
                    $data['cv_main_data']['media_type']= $request->media_type;
                }
                $data['cv_main_data']['cv_url']= $this->generateUniqueCVURL($data['cv_main_data']['first_name']."-".$data['cv_main_data']['last_name'],null);

                $data['cv_main_data']['cv_short_url']= $this->generateUniqueCVSHORTURL(mb_substr($data['cv_main_data']['first_name'],0,2).mb_substr($data['cv_main_data']['last_name'],0,2).rand(1,10000),null);
               
                if($request->edit_by_user==true && $request->send_login_details==true){

                    $password = rand(100000000,999999999);

                    $user->password = bcrypt($password);

                    $user->save();

                    $details['name'] = $user->first_name." ".$user->last_name;
                    $details['email'] = $user->email;
                    $details['password'] = $password;
                    $details['lang'] = app()->getLocale();
                    $details['url'] = config('app.frontend_url');
                    $details['company_name'] = Teams::where('user_id',$team_id)->first()->company_name;
                    dispatch(new SendAuthUserEmailJob($details));
                }
                
            }else{

                $user_create['email'] =$request->email;
                $user_create['first_name'] = $data['cv_main_data']['first_name'];
                $user_create['last_name'] = $data['cv_main_data']['last_name'];

                $password = rand(100000000,999999999);
                $user_create['password'] = bcrypt($password);

                $user_create['last_name_index'] = substr($user_create['last_name'], 0, 1);

                $user = User::create($user_create);

                $user->roles()->attach(Role::where('slug','team-user')->first());
                
                
                UsersSettings::create(['user_id'=>$user->id,'meta_key'=>'cv_edit_by_user','meta_value'=>$request->edit_by_user]);

                $data['cv_main_data']['user_id']= $user->id;
                
                $data['cv_main_data']['cv_url']= $this->generateUniqueCVURL($data['cv_main_data']['first_name']."-".$data['cv_main_data']['last_name'],null);

                $data['cv_main_data']['cv_short_url']= $this->generateUniqueCVSHORTURL(mb_substr($data['cv_main_data']['first_name'],0,2).mb_substr($data['cv_main_data']['last_name'],0,2).rand(1,10000),null);
                
                TeamUser::create(['team_id'=>$team_id,'team_user_id'=>$user->id]);

                if($request->edit_by_user==true && $request->send_login_details==true){

                    $details['name'] = $user->first_name." ".$user->last_name;
                    $details['email'] = $user->email;
                    $details['password'] = $password;
                    $details['lang'] = app()->getLocale();
                    $details['url'] = config('app.frontend_url');
                    $details['company_name'] = Teams::where('user_id',$team_id)->first()->company_name;
                    dispatch(new SendAuthUserEmailJob($details));
                }
            }
            
            
            

            $data['cv_main_data']['active']= $request->cv_page_active;
            $data['cv_main_data']['percentage']= GetPercentage::index($request);
            if($request->filled('media_type')){
                $data['cv_main_data']['media_type']= $request->media_type;
            }
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
            
            /* multiple contact details to add */

            if($request->filled('cv_contact_details')){
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

            /* multiple curriculum qualifications to add */

            if($request->filled('cv_curriculum_qualifications')){
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

    /**
     * Get Listing of Team User
     *
     * @throws Some_Exception_Class If something interesting cannot happen
     * @author Parvinder Singh 29 june 2021 <parvinder.singh@maracana.in>
     * @return Status
     */ 
    public function getMyUsers(Request $request){

        try{
            $user = Auth::user();
            if($user->hasRole('super-admin')==true){
                $team_id=$request->id;
            }
            else{
                $team_id=Auth::id();
            }
            if ($request->has('limit')) { // if limit available in request
                $per_page =$request->limit; 
            }else{
                $per_page = 20;
            }
            return TeamUser::where('team_id',$team_id)->with('user')->paginate($per_page);
      
        }catch(\Exception $e){
            return response()->json([
                'error' => $e->getLine()." ".$e->getMessage()
            ], 406);
        }
    }

    /**
     * Get Listing of last index all users
     *
     * @throws Some_Exception_Class If something interesting cannot happen
     * @author Parvinder Singh 11 Nov 2021 <parvinder.singh@maracana.in>
     * @return response
     */ 
    public function getTeamUserCharacters(Request $request){

        try{
            $validator = Validator::make($request->all(), [
                 
                'team_id'=> 'required'
            ]);
            if ($validator->fails()) {

                return response()->json(['error' => $validator->messages()->first()], 406);

            }
             
            $users =  TeamUser::where('team_id',$request->team_id)->with('user')->get();
          
            $character = [];
            foreach($users as $k=>$user){
                $character[$k] = strtoupper($user->user->last_name_index);
            }

            $character = array_values(array_unique($character));

            return response()->json($character);
            

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
    public function getTeamUsersWithCharacter(Request $request){

        try{
            
            $validator = Validator::make($request->all(), [
                'char' => 'required',
                'team_id'=> 'required'
            ]);
            if ($validator->fails()) {

                return response()->json(['error' => $validator->messages()->first()], 406);

            }

            if ($request->has('limit')) { // if limit available in request
                $per_page =$request->limit; 
            }else{
                $per_page = 20;
            }

            if ($request->has('page')) { // if page param is available in request
                $page =$request->page; 
            }else{
                $page = 1;
            }

            $char = $request->char;

            $collection =   TeamUser::where('team_id',$request->team_id)->with(['user'=>function($q) use($char) {
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


    /* if Team admin wants to change permission for user like active deactive CV and edit permission */
    public function changePermission(Request $request){
        
        try{
            return app('App\Http\Controllers\Api\TeamAdmin\QUYKCVController')->changeStatus($request);
        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }
    }


    public function teamSearchUser(Request $request)
    {

        $rules = array(
            'char' => 'required', // query validation
            'team_id'=>'required'
            );    
        $validator = Validator::make( $request->all(), $rules );  
        if ($validator->fails()) {
            return response()->json(['error' => $validator->messages()->first()],406);
        }


        if ($request->has('limit')) { // if limit available in request
            $per_page =$request->limit; 
        }else{
            $per_page = 20;
        }

        if ($request->has('page')) { // if page param is available in request
            $page =$request->page; 
        }else{
            $page = 1;
        }

        $q = $request->char;

        $collection = TeamUser::where('team_id',$request->team_id)->with(['user'=>function($query) use($q) {
            $query->where('first_name', 'LIKE', '%'.$q.'%')
                ->orWhere('last_name', 'LIKE', '%'.$q.'%')
                ->orWhere('email', 'LIKE', '%'.$q.'%');
        }])->get();

            
        $filtered = $collection->filter(function ($value, $key) {
            return $value->user !== null;
        });

        $filtered = $filtered->values();
        
        $filtered = $this->paginateCollection($filtered,$per_page,$page);
        
        return $filtered;

        

    }

    

    /* Store cv pages for single user from super admin */
    public function AddCVSingleUser(Request $request)
    {
        try{

          
            $rules = array(
                'user_id'=>'required|exists:users,id',
                'cv_main_data.first_name' => 'required', // first name validation
                'cv_main_data.last_name' => 'required', // last name validation
                'media_type' => 'in:image,video', // media type validation
                'cv_contact_details.*.network'=>'required|in:email,fax,mobile,telephone,website',
                'cv_contact_details.*.url'=>'required', 
                'cv_images.*.image_id'=>'exists:quyk_cv_temp_pictures,temp_id',// cv images id validation
                'cv_images.*.active'=>'numeric|min:0|max:1',// cv images active validation
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

             
            $user_id = $request->user_id;

            $data = $request->all();

           
            $data['cv_main_data']['user_id']= $user_id;
            $data['cv_main_data']['percentage']= GetPercentage::index($request);
            if($request->filled('media_type')){
                $data['cv_main_data']['media_type']= $request->media_type;
            }
            $data['cv_main_data']['cv_url']= $this->generateUniqueCVURL($data['cv_main_data']['first_name']."-".$data['cv_main_data']['last_name'],null);

            $data['cv_main_data']['cv_short_url']= $this->generateUniqueCVSHORTURL(mb_substr($data['cv_main_data']['first_name'],0,2).mb_substr($data['cv_main_data']['last_name'],0,2).rand(1,10000),null);

             
            $cv = QUYKCV::create($data['cv_main_data']);

            /* multiple company addresses to add */
            if($request->filled('cv_company_address')){

                foreach($request->input('cv_company_address') as $company_address){

                    
                    $company_address['cv_id'] = $cv->id;
                    
                    QUYKCVCompanyAddress::create($company_address);
                    
                }
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



    // get team admin get team overview 
    public function getTeamOverview(Request $request,$team_id){
        
        
        $team = Teams::where('user_id',$team_id)->first();

        return response()->json(['company_name'=>$team->company_name,'team_overview' =>$team->team_overview,'company_url'=>$team->company_url]);

       
    }

    
    /* Add team overview from super admin text*/
    public function addTeamOverview(Request $request){

        try{
            
            $validator = Validator::make($request->all(), [
                'overview'=>'nullable',
                'team_id' =>'required'
            ]);

            if ($validator->fails()) {

                return response()->json(['error' => $validator->messages()->first()], 406);

            }
            
            $team_id = $request->team_id;

            $team = Teams::where('user_id',$team_id)->first();

            

            $team->company_url = isset($request->company_url)? $request->company_url : null;;
            
            $team->company_name = isset($request->company_name)? $request->company_name : null;;

            
            $team->team_overview = $request->overview;

            $team->save();
            
            return response()->json(['status' => 1]);

        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getLine()." ".$e->getMessage()
            ], 406);
        }
    }


    // Team admin get team Picture 
    public function getTeamPicture(Request $request,$team_id){
        
        $team_picture = Teams::where('user_id',$team_id)->first()->team_picture;
        if($team_picture){
            $team_picture =  Storage::disk('public')->url($team_picture);
        }else{
            $team_picture = "";
        }
        return response()->json(['team_picture' =>$team_picture]);
    }

    // Team admin delete team Picture 
    public function deleteTeamPicture(Request $request,$team_id){

        $team_overview = Teams::where('user_id',$team_id)->first();
 
        if(Storage::disk('public')->delete($team_overview->team_picture)) {
            $team_overview->team_picture="";
            $team_overview->save();
        }else{
            return response()->json(['error' => trans('messages.wrong')], 406);
        }
        
        return response()->json(['status' =>1]);
       
    }

    /* Add team overview text*/
    public function addTeamPicture(Request $request){

        try{
            
            $validator = Validator::make($request->all(), [
                'image' => 'required',
                'team_id'=>'required'
            ]);

            if ($validator->fails()) {

                return response()->json(['error' => $validator->messages()->first()], 406);

            }

            $team_id = $request->team_id;
            
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


    /* Add Global addresses by Team Admin */
    public function addTeamAddress(Request $request,$team_id){

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

                    TeamAddresses::where(['id'=>$address['id'],'team_id'=>$team_id])->update($addresses[$k]);
                }else{
                    $addresses[$k]['team_id'] = $team_id;
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
    // get team admin global address
    public function getTeamAddress(Request $request,$team_id){

        return TeamAddresses::where('team_id',$team_id)->orderBy('id','asc')->get();
    }

    /* Delete Global addresses by Team Admin */
    public function deleteTeamAddress(Request $request,$team_id){

        try{
            
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
            TeamAddresses::where(['team_id' => $team_id,'id'=>$request->id])->forceDelete();;
            
            return response()->json(['status' => 1]);

        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getLine()." ".$e->getMessage()
            ], 406);
        }

    }


    /* Store Design Settings Data */
    public function saveDesign(Request $request)
    {
        try{
            $rules = array(
                    'user_id'=>'required|exists:users,id',
                    'primary_color' => 'required', // Primary color validation
                    'secondary_color' => 'required', // Secondary color validation
                    'font_color_contact' => 'required', // Font Color contact Details
                    'font_color_cv'=>'required', // Font Color CV Details
                    'font_headline'=>'required', // Font headline
                    'font_normal'=>'required', // Font normal
                );    
            $messages = array(
                        'primary_color.required' => 'The primary color is required.',
                        'secondary_color.required' => 'The secondary color is required.',
                        'font_color_contact.required' => 'The font color contact is required.',
                        'font_color_cv.required' => 'The font color cv is required.',
                        'font_headline.required' => 'The font headline is required.',
                        'font_normal.required' => 'The font normal is required.',
                    );
            
            
            
            $validator = Validator::make( $request->all(), $rules, $messages );  
            if ($validator->fails()) {
                return response()->json(['error' =>$validator->errors()->first(),'key'=>array_key_first($validator->errors()->messages())], 406);
            }

             
            $data = $request->all();

            $logo_name = DesignSettings::where(['user_id'=>$request->user_id,'meta_key'=>"logo_name"])->first();

            if($logo_name){

                $data['logo_name'] = $logo_name->meta_value;
            }

            DesignSettings::where(['user_id'=>$request->user_id])->where('meta_key', '!=' , 'logo')->delete();
            foreach ($data as $key => $value) {
                if($key!="user_id"){
                    DesignSettings::Create(['user_id' => $request->user_id,'meta_key' => $key,'meta_value' =>$value]); 
                }
            }

            // All went well
            return response()->json([
                "status"=>1,
                'message' => 'Design settings saved Successfully.',
            ]);


        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }
    }


    /* Get Design Settings Data */
    public function getDesign(Request $request,$user_id)
    {
        try{
            $settings = DesignSettings::where(['user_id'=>$user_id])->get();
            $data = [];
            foreach ($settings as $key => $value) {
                if($value->meta_key=="logo" || $value->meta_key=="header_font" || $value->meta_key=="content_font"){
                    $data[$value->meta_key]= config('app.url').Storage::url($value->meta_value);
                }else{
                    $data[$value->meta_key]=$value->meta_value;
                }
                
            }
            // All went well
            return $data;
        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }
    }

    /* function for upload logo in design settings */

    public function insertLogo(Request $request){

        try{
            $validator = Validator::make($request->all(), [
                'logo' => 'required',
                'user_id'=>'required:exists:users,id'
            ]);

            if ($validator->fails()) {

                return response()->json(['error' => $validator->messages()->first()], 406);

            }
            
            $user_id = $request->user_id;
            $base64_image = $request->input('logo'); // your base64 encoded     
            @list($type, $file_data) = explode(';', $base64_image);
            @list(, $file_data) = explode(',', $file_data); 
            $imageName = rand(1,100).$user_id.time().'.'.'png';  

            if ($request->has('file_name')) {
                $logo_name = $request->file_name ;
            }else{
                $logo_name = $imageName ;
            }

            Storage::disk('local')->put("public/logos/".$imageName, base64_decode($file_data));

            $storedSettings = DesignSettings::where(['user_id'=>$user_id,'meta_key'=>'logo'])->first();
            if($storedSettings){
                Storage::disk('public')->delete($storedSettings->meta_value);
                DesignSettings::where(['user_id'=>$user_id,'meta_key'=>'logo'])->delete();
                DesignSettings::where(['user_id'=>$user_id,'meta_key'=>'logo_name'])->delete();

            }   
            
            DesignSettings::create(array('user_id'=>$user_id,'meta_value'=>"logos/".$imageName,'meta_key'=>'logo')); 
            DesignSettings::create(array('user_id'=>$user_id,'meta_value'=>$logo_name,'meta_key'=>'logo_name')); 

            
            // All went well
            return response()->json([
                "status"=>1,
                'message' => 'logo Uploaded Successfully',
                'logo' => Storage::disk('public')->url("logos/".$imageName),
                'logo_name'=>$logo_name

            ]);

        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }
 
    }


    public function fontUpload(Request $request){

        $validator = Validator::make($request->all(), [
            'user_id'=>'required:exists:users,id'
        ]);

        if ($validator->fails()) {

            return response()->json(['error' => $validator->messages()->first()], 406);

        }

        $user_id = $request->user_id;

        if ($request->file('header_font')!=null){

            $file = $request->file('header_font');
            
            $fileName = $user_id.time().".".$file->getClientOriginalExtension();
          
            Storage::disk('local')->put("public/fonts/".$fileName, \File::get($file));


           // $path = $file->store('public/fonts',$fileName);


            $storedSettings = DesignSettings::where(['user_id'=>$user_id,'meta_key'=>'header_font'])->first();
            if($storedSettings){
                Storage::disk('public')->delete($storedSettings->meta_value);
                DesignSettings::where(['user_id'=>$user_id,'meta_key'=>'header_font'])->delete();

            }   
            
            DesignSettings::create(array('user_id'=>$user_id,'meta_value'=>"fonts/".$fileName,'meta_key'=>'header_font')); 

            
                
            // All went well
            return response()->json([
                "status"=>1,
                'message' => 'logo Uploaded Successfully',
                'file' => Storage::disk('public')->url("fonts/".$fileName)
            ]);
       
        }

        if ($request->file('content_font')!=null){

            $file = $request->file('content_font');
            
            $fileName = $user_id.time().".".$file->getClientOriginalExtension();
          
            Storage::disk('local')->put("public/fonts/".$fileName, \File::get($file));


           // $path = $file->store('public/fonts',$fileName);


            $storedSettings = DesignSettings::where(['user_id'=>$user_id,'meta_key'=>'content_font'])->first();
            if($storedSettings){
                Storage::disk('public')->delete($storedSettings->meta_value);
                DesignSettings::where(['user_id'=>$user_id,'meta_key'=>'content_font'])->delete();

            }   
            
            DesignSettings::create(array('user_id'=>$user_id,'meta_value'=>"fonts/".$fileName,'meta_key'=>'content_font')); 

            
                
            // All went well
            return response()->json([
                "status"=>1,
                'message' => 'logo Uploaded Successfully',
                'file' => Storage::disk('public')->url("fonts/".$fileName)
            ]);
       
        }

    } 


    /* function for remove font in design settings */

    public function fontRemove(Request $request){

        try{
            
            $validator = Validator::make($request->all(), [
                'user_id'=>'required:exists:users,id'
            ]);
    
            if ($validator->fails()) {
    
                return response()->json(['error' => $validator->messages()->first()], 406);
    
            }
            
            $user_id = $request->user_id;
            

            $storedSettings = DesignSettings::where(['user_id'=>$user_id,'meta_key'=>$request->type])->first();
            if($storedSettings){
                Storage::disk('public')->delete($storedSettings->meta_value);
                DesignSettings::where(['user_id'=>$user_id,'meta_key'=>$request->type])->delete();

            }   
            
            // All went well
            return response()->json([
                "status"=>1,
                'message' => 'font deleted Successfully'
            ]);

        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }
 
    }


    /* function for upload logo in design settings */

    public function removeLogo(Request $request,$user_id){

        try{
            
             

            $storedSettings = DesignSettings::where(['user_id'=>$user_id,'meta_key'=>'logo'])->first();
            if($storedSettings){
                Storage::disk('public')->delete($storedSettings->meta_value);
                DesignSettings::where(['user_id'=>$user_id,'meta_key'=>'logo'])->delete();
                DesignSettings::where(['user_id'=>$user_id,'meta_key'=>'logo_name'])->delete();

            }   
            
            // All went well
            return response()->json([
                "status"=>1,
                'message' => 'logo delete Successfully'
            ]);

        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }
 
    }

    // team admin add customer address 
    public function addCustomerAddress(Request $request,$user_id){

        $messages = array('street_number.alpha_spaces' => trans('messages.cv.street_number'));

        $validator = Validator::make($request->all(),
		[
			'company_name' => 'nullable',
			'gender' => 'nullable',
			'first_name' => 'required',
			'last_name' => 'required',
			'street_address' => 'nullable',
			'street_number' => 'alpha_spaces|nullable',
			'pin_code' => 'nullable',
			'location' => 'nullable',
			'country' => 'nullable',
		],$messages);
		 
		//if validation failes, then  error would return
		if ($validator->fails()) {
			return response()->json([
				'error' => $validator->errors()->first(),
				'key'=>array_key_first($validator->errors()->messages())
			], 406);
		}
        
        $address = SingleUserAddress::where('user_id',$user_id)->first();
        $params = $request->input();
        
        if($address){ // if address exists 

            SingleUserAddress::where(['user_id'=>$user_id])->update($params);
    
        }else{ // add new address if not exists
            
            $params['user_id'] = $user_id;
             
            SingleUserAddress::create($params);
        }

        $user = User::where('id',$user_id)->first();
        $user->first_name = $params['first_name']; 
        $user->last_name = $params['last_name']; 
        $user->last_name_index = substr($params['last_name'], 0, 1); 
        $user->save();
		return response()->json(['success' => true]);

    }

    // get current user address
    public function getCustomerAddress(Request $request,$user_id){

        try{
            return(SingleUserAddress::where('user_id',$user_id)->first());
        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }
    }


    public function accountCreate(Request $request){

        $validator = Validator::make($request->all(),
		[
			'first_name' => 'required|min:2',
			'last_name'=>'required|min:2',
            'company_name'=>'required|min:2',
			'email' => 'required|email:filter|unique:users',
			'role'=> 'required|in:basic,team',
            'password' =>'required|min:8',
            'send_login_details'=>'required|boolean'
		]);



		//if validation failes, then  error would return and status code 406
		if ($validator->fails()) {
			return response()->json([
				'error' => $validator->errors()->first(),
				'key'=>array_key_first($validator->errors()->messages())
			], 406);
		}
		$input = $request->all();
 
        $password = $input['password'];

       
		$user_data['first_name'] = $input['first_name'];
		$user_data['last_name'] = $input['last_name'];
		$user_data['email'] = $input['email'];
		$user_data['password'] = bcrypt(DecryptPassword::decrypt($password));
		$user_data['last_name_index'] = substr(request('last_name'), 0, 1);

		
		$user = User::create($user_data);

		 
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

        $params['user_id'] = $user->id;
        $params['company_name'] = $request->company_name;
        $params['first_name'] = $request->first_name;
        $params['last_name'] = $request->last_name;
        $params['gender'] = isset($input['gender'])? $input['gender'] : null;
        $params['street_address'] = isset($input['street_address'])? $input['street_address'] : null;
        $params['street_number'] = isset($input['street_number'])? $input['street_number'] : null;
        $params['pin_code'] = isset($input['pin_code'])? $input['pin_code'] : null;
        $params['location'] = isset($input['location'])? $input['location'] : null;
        $params['country'] = isset($input['country'])? $input['country'] : null;

             
        SingleUserAddress::create($params); // user_address update
       
        if($request->send_login_details==true){
            
            $details['name'] = $user->first_name." ".$user->last_name;
            $details['email'] = $user->email;
            $details['password'] = DecryptPassword::decrypt($password);
            $details['company_name'] = "CVPAGES";
            $details['url'] = config('app.frontend_url');
            $details['lang'] = app()->getLocale();
            dispatch(new SendAuthUserEmailJob($details));
    
        }
       
        return response()->json(['success' => true]);

    }

    private function paginateCollection($items, $perPage = 50000000, $page = null, $options = [])
    {
        $page = $page ?: (\Illuminate\Pagination\Paginator::resolveCurrentPage() ?: 1);
        
        $items = $items instanceof \Illuminate\Support\Collection ? $items : \Illuminate\Support\Collection::make($items);
        return new \Illuminate\Pagination\LengthAwarePaginator($items->forPage($page, $perPage)->values(), $items->count(), $perPage, $page,['path' => url('api/v1/team-admin/getUsers')]);
    }

    /* Get team url from Team admin  */
    public function getTeamUrl(Request $request,$team_id){

        if(Teams::where('user_id',$team_id)->count()>0){

            $team_url = Teams::where('user_id',$team_id)->first()->team_url;
		    return response()->json(['team_url' => config('app.frontend_url').$team_url]);

        }
        
    }

    // get team records from Super admin
    public function getTeamRecord(Request $request,$team_id){

        return TeamRecords::where('team_id',$team_id)->get();

    }

    /* Add Global Records from super Admin for team */
    public function addTeamRecord(Request $request){

        try{
            
            $messages = array(
                'heading_text.*.required' => trans('messages.record.heading_text'),
                'heading_description.*.required' =>trans('messages.record.heading_description'),
                
            );

            $validator = Validator::make($request->all(), [
                'heading_text.*'=>'required',
                'heading_description.*'=>'required',
                'team_id.*'=>'required'
            ],$messages);

            if ($validator->fails()) {

                return response()->json(['error' => $validator->messages()->first()], 406);

            }

          

            $records = $request->all();
            
            foreach($records as $k=>$record){
                if($k==0){
                    TeamRecords::where('team_id',$record['team_id'])->forceDelete();
                }
                TeamRecords::create($records[$k]);
            }
            return response()->json(['status' => 1]);

        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getLine()." ".$e->getMessage()
            ], 406);
        }

    }

    /* Delete Global addresses by Team Admin */
    public function deleteTeamRecord(Request $request){

        try{

            $team_id = $request->team_id;
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
            TeamRecords::where(['team_id' => $team_id,'id'=>$request->id])->forceDelete();;
            
            return response()->json(['status' => 1]);

        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getLine()." ".$e->getMessage()
            ], 406);
        }

    }

    /* Update team url from Team admin  */
    public function updateTeamUrl(Request $request,$team_id){

        try{

           
            $team_id = $request->team_id;

            $validator = Validator::make($request->all(),
            [
                'url' => 'required|min:6|unique:quyk_cv_teams_detail,team_url,' . $team_id. ",user_id",
                
            ]);
            
            //if validation failes, then  error would return
            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors()->first(),
                    'key'=>array_key_first($validator->errors()->messages())
                ], 406);
            }

            $team = Teams::where('user_id',$team_id)->first();
            $team->team_url = $request->url;
            $team->save();
            
            return response()->json(['success' => true]);


        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getLine()." ".$e->getMessage()
            ], 406);
        }
        

    }

    /* Update team email from Team admin  */
    public function updateTeamEmail(Request $request){

          
        try{

            $messages = array(
                'email.unique'=>trans('messages.email.unique'),
                'email.email'=>trans('messages.email.email'),
                'email.required'=>trans('messages.email.required'),
            );
           
            $team_id = $request->team_id;
            $validator = Validator::make($request->all(),
            [
                'email' => 'required|email:filter|unique:users,email,' . $team_id,
                
            ],$messages);
            
            //if validation failes, then  error would return
            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors()->first(),
                    'key'=>array_key_first($validator->errors()->messages())
                ], 406);
            }
            $token = Str::random(80).$team_id;

            UpdateEmail::where('user_id',$team_id)->forceDelete();

            UpdateEmail::create(['user_id'=>$team_id,'email'=>$request->email,'confirm_code'=>$token]);
            
            
            $details['email'] = User::where('id',$team_id)->first()->email;
            $details['code'] = $token;
            $details['lang'] = app()->getLocale();
            $details['url'] = config('app.frontend_url');
            $details['name'] = Teams::where('user_id',$team_id)->first()->company_name;
            dispatch(new SendUpdateEmailVerify($details));
            return response()->json(['success' => true]);


        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getLine()." ".$e->getMessage()
            ], 406);
        }
        


    }


    /* Update single user email from Super admin  */
    public function updateSingleUserEmail(Request $request){

        $messages = array(
            'email.unique'=>trans('messages.email.unique'),
            'email.email'=>trans('messages.email.email'),
            'email.required'=>trans('messages.email.required'),
        );

        $user_id = $request->user_id;


        $validator = Validator::make($request->all(),
		[
			'email' => 'required|email:filter|unique:users,email,' . $user_id,
            
		],$messages);
		 
		//if validation failes, then  error would return
		if ($validator->fails()) {
			return response()->json([
				'error' => $validator->errors()->first(),
				'key'=>array_key_first($validator->errors()->messages())
			], 406);
		}
 
        $token = Str::random(80).$user_id;

        UpdateEmail::where('user_id',$user_id)->forceDelete();

        UpdateEmail::create(['user_id'=>$user_id,'email'=>$request->email,'confirm_code'=>$token]);
        
        $user = User::where('id',$user_id)->first();

        $details['email'] = $user->email;

        $details['code'] = $token;
        $details['lang'] = app()->getLocale();
        $details['url'] = config('app.frontend_url');
        $details['name'] = $user->first_name." ".$user->last_name;

        dispatch(new SendUpdateEmailVerify($details));

		return response()->json(['success' => true]);


    }

    public function updatePassword(Request $request) {

        try{

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
        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getLine()." ".$e->getMessage()
            ], 406);
        }
		
	}

    // delete my account. it will delete whole data of single user
    public function deleteTeamAccount(Request $request){
        try{

            $validator = Validator::make($request->all(),
            [
               'team_id'=>'required'
            ]);
             
            //if validation failes, then  error would return
            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors()->first(),
                    'key'=>array_key_first($validator->errors()->messages())
                ], 406);
            }

           $team_id = $request->team_id;
            $teams = TeamUser::where('team_id',$team_id)->with('user')->get();

            
            foreach($teams as $team){

                // code for deleting picture of cv's
                $pictures = QUYKCV::with('pictures_videos')->where('user_id',$team->team_user_id)->get();
                
                foreach($pictures as $picture){

                    foreach($picture->pictures_videos as $imageData){

                        Storage::disk('public')->delete($imageData->location);
                        
                    }
                }
                
                User::where('id',$team->team_user_id)->forceDelete(); // delete team user
            }

            User::where('id',$team_id)->forceDelete(); // delete team admin 

            
            
            return response()->json(['success' => true]);
                
             
           
        
        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }

    }
}

