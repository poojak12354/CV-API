<?php

namespace App\Http\Controllers\Api;


use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\QUYKCV;
use App\Models\Team_users as TeamUser;
use App\Models\Teams;
use App\Models\Team_records as TeamRecords;
use App\Models\QUYKCVCompanyAddress;
use App\Models\QUYKCVContactDetails;
use App\Models\QUYKCVSocialNetworks;
use App\Models\QUYKCVGlobalAddress;
use App\Models\QUYKCVCurriculumQualifications;
use App\Models\QUYKCVPicturesVideos;
use App\Models\QUYKCVTempPictures;
use App\Models\QUYKCVCompanyAddressList;
use App\Models\User;
use App\Models\SingleUserAddress;
use App\Models\Views;
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
use File;
use Illuminate\Support\Facades\Hash;

use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Jobs\SendUpdateEmailVerify;

use App\Http\Traits\GetPercentage;
use App\Http\Traits\ImageVideoAssign;
use App\Rules\MatchOldPassword;
use VideoThumbnail;
use App\Http\Traits\DecryptPassword;


class QUYKCVController extends Controller
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

    /* Edit cv pages data */
    public function edit(Request $request)
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
                'cv_main_data.first_name' => 'required', // first name validation
                'cv_main_data.last_name' => 'required', // last name validation
                'media_type' => 'in:image,video', // media type validation
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

             

            $data = $request->all();

            $cv =  QUYKCV::where(array('id'=>$data['cv_id'],'user_id'=>Auth::id()))->first();
            
            $data['cv_main_data']['percentage']= GetPercentage::index($request);

            if($request->filled('media_type')){
                $data['cv_main_data']['media_type']= $request->media_type;
            }
            $data['cv_main_data']['cv_url']= $this->generateUniqueCVURL($data['cv_main_data']['first_name']."-".$data['cv_main_data']['last_name'],$request->cv_id);
            
            if(($cv->first_name!=$data['cv_main_data']['first_name']) || ($cv->last_name!=$data['cv_main_data']['last_name'])){
           
                 $data['cv_main_data']['cv_short_url']= $this->generateUniqueCVSHORTURL(mb_substr($data['cv_main_data']['first_name'],0,2).mb_substr($data['cv_main_data']['last_name'],0,2).rand(1,10000),$request->cv_id);

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

            if($request->hasHeader('x-usedby') && $request->hasHeader('x-usedby')=="mobile"){
                $success['success'] = true;
                $success['data'] = QUYKCV::with('pictures_videos','videos','temp_images','temp_videos')->find($cv->id);
                return response()->json($success, 200);
            }else{
                // All went well
                return response()->json($cv, 200);
            }
             

        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getLine()." ".$e->getMessage()
            ], 406);
        }
    }

    /* Store cv pages data */
    public function store(Request $request)
    {
        try{
             
            $rules = array(
                'cv_main_data.first_name' => 'required|max:50', // first name validation
                'cv_main_data.first_name' => 'required|max:50', // first name validation
                'cv_main_data.salutation' => 'max:50', // first name validation
                'cv_main_data.title' => 'max:50', // last name validation
                'media_type' => 'in:image,video', // media type validation
                'cv_contact_details.*.network'=>'required|in:email,fax,mobile,telephone,website',
                'cv_contact_details.*.url'=>'required',
                'cv_images.*.image_id'=>'exists:quyk_cv_temp_pictures,temp_id',// cv images id validation
                'cv_videos.*.video_id'=>'exists:quyk_cv_temp_pictures,temp_id',// cv video id validation
                'cv_social_networks.*.network'=>'sometimes|required|in:behance,dribbble,facebook,instagram,linkedin,pinterest,tiktok,twitter,vimeo,xing,youtube',
                'cv_social_networks.*.url'=>'sometimes|required',
                'cv_company_address.*.street_number'=>'max:10|alpha_spaces|nullable',
                'cv_company_address.*.company_name'=>'max:50',
                'cv_company_address.*.additional_information'=>'max:50',
                'cv_company_address.*.zip_code'=>'max:10',
                'cv_company_address.*.city'=>'max:25',
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
                    'cv_videos.*.video_id.exists' => trans('messages.cv.cv_images.video_id.exists'),
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
                return response()->json(['error' =>$error,'key'=>array_key_first($validator->errors()->messages())], 406);
            }
            
            
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

            if($request->hasHeader('x-usedby') && $request->hasHeader('x-usedby')=="mobile"){
                $success['success'] = true;
                $success['data'] = QUYKCV::with('pictures_videos','videos','temp_images','temp_videos')->find($cv->id);
                return response()->json($success, 200);
            }else{
                // All went well
                return response()->json($cv, 200);
            }
            
            

        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getLine()." ".$e->getMessage()
            ], 406);
        }
    }
 
    
    public function thumbTest(){

        $videoName="3101647936942.mp4";
        $thumbName="3101647936941.jpg";

         
        $v =  VideoThumbnail::createThumbnail(Storage::disk('public')->path("users/".$videoName), Storage::disk('public')->path("users"), $thumbName, 2, 74, 80);
        echo $string = "ffmpeg -i ".Storage::disk('public')->path("users/".$videoName)." -ss 00:00:02.000 -vframes 1 ".Storage::disk('public')->path("users/").$thumbName."";
        shell_exec($string);
        die;
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
            if($request->hasHeader('x-usedby') && $request->hasHeader('x-usedby')=="mobile"){
                
                return response()->json([
                    "success"=>true,
                    'message' => trans('messages.file.success'),
                    'data' => $uploadedImageResponse
                ]);
            }else{
                 
                return response()->json([
                    "status"=>1,
                    'message' => trans('messages.file.success'),
                    'data' => $uploadedImageResponse
                ]);
            }
            

        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }
 
    }

    public function mobileImageUpload(Request $request){

        try{

             
            $validator = Validator::make($request->all(), [
                'image' => 'required|mimes:jpg,jpeg,png'
            ]);

            if ($validator->fails()) {

                return response()->json(['error' => $validator->messages()->first()], 406);

            }
            $file = $request->file('image');
            $filename = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();

            $imageName = Auth::id().time().'.'.$extension;  
            
            $thumbImageName = "thumb_".Auth::id()."_".time().'.'.'png';  
             

             
             
            Storage::disk('local')->put("public/users/".$imageName,file_get_contents($file));
            Storage::disk('local')->put("public/users/".$thumbImageName, file_get_contents($file));
           
            
             
            $temp_id = Auth::id().strtotime("now");
            
           
            $save = QUYKCVTempPictures::create(array('user_id'=>Auth::id(),'location'=>"users/".$imageName,'thumb'=>"users/".$thumbImageName,'file_name'=>$filename,'temp_id'=>$temp_id,'type'=>1)); 

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
            if($request->hasHeader('x-usedby') && $request->hasHeader('x-usedby')=="mobile"){
                
                return response()->json([
                    "success"=>true,
                    'message' => trans('messages.file.success'),
                    'data' => $uploadedImageResponse
                ]);
            }else{
                 
                return response()->json([
                    "status"=>1,
                    'message' => trans('messages.file.success'),
                    'data' => $uploadedImageResponse
                ]);
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
            if($request->hasHeader('x-usedby') && $request->hasHeader('x-usedby')=="mobile"){
                
                // All went well
                return response()->json([
                    "success"=>true,
                    'message' => trans('messages.file.delete'),
                ]);

            }else{
                 
                // All went well
                return response()->json([
                    "status"=>1,
                    'message' => trans('messages.file.delete'),
                ]);
            }
            
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
            if($request->hasHeader('x-usedby') && $request->hasHeader('x-usedby')=="mobile"){
                
                // All went well
                return response()->json([
                    "success"=>true,
                    'message' => trans('messages.file.delete'),
                ]);
            }else{
                 
               // All went well
                return response()->json([
                    "status"=>1,
                    'message' => trans('messages.file.delete'),
                ]);
            }
            


        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }
    }


    /* get all cv pages listing */

    public function listing(Request $request){

        try{
            return QUYKCV::with('global_address','pictures_videos','videos','company_address','contact_details','social_networks','curriculum_qualifications')->paginate(10);
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

                if($request->hasHeader('x-usedby') && $request->hasHeader('x-usedby')=="mobile"){
                    $success['success'] = true;
                    $success['data'] = QUYKCV::with('global_address','pictures_videos','videos','company_address','contact_details','social_networks','curriculum_qualifications','temp_images','temp_videos')->find($request->id);
                    return response()->json($success, 200);
               
                }else{
    
                    return QUYKCV::with('global_address','pictures_videos','videos','company_address','contact_details','social_networks','curriculum_qualifications')->find($request->id);

                }
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
            return(QUYKCVCompanyAddressList::select('id as cv_id','company_name')->orderBy('company_name','asc')->get());
        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }
     }

     // get particular company data 
     public function getCompany(Request $request,$id){

        try{
            return(QUYKCVCompanyAddressList::where('id',$id)->get());
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
    public function getUserCV(Request $request){

        try {


             
            $rules = array('cv_url'=>'required');    
        
            $validator = Validator::make( $request->all(), $rules);  

            if ($validator->fails()) {
                
                return response()->json([
                    'error' => $validator->errors()->first(),
                    'key'     => array_key_first($validator->errors()->messages())
                ], 406);
            }

            
            if(Teams::where('team_url',$request->cv_url)->count() > 0){
 
                $team = Teams::with('team_users','team_address')->where('team_url',$request->cv_url)->first();
          
                 // Remove inactive users
                foreach($team->team_users as $key=>$user){

                    if($user->active==0){
                        $team->team_users->forget($key);
                        //$team->team_users->values();
                    }
                }
                $team_users =  $team->team_users;
                
                unset($team->team_users);

                $team->team_users = $team_users->values();

                $settings = DesignSettings::where(['user_id'=>$team->user_id])->get(); 

                $team->team_records = TeamRecords::select('heading_text','heading_description')->where('team_id',$team->user_id)->get();
                
                $settingsData = [];

                foreach ($settings as $key => $value) {
                    if($value->meta_key=="logo" || $value->meta_key=="header_font" || $value->meta_key=="content_font"){
                        $settingsData[$value->meta_key]= config('app.url').Storage::url($value->meta_value);
                    }else{
                        $settingsData[$value->meta_key]=$value->meta_value;
                    }
                    
                }


                if(empty($settingsData)){
                    $team->designSettings = (object) $settingsData;
                    
                }else{
                    $team->designSettings = $settingsData;
                    
                }
                
                $team->role = "team";

                if($request->hasHeader('x-usedby') && $request->hasHeader('x-usedby')=="mobile"){
                    $success['success'] = true;
                    $success['data'] = $team;
                    return response()->json($success, 200);
                }else{
                    return $team;
                }
               
            }
            
            
            $cvData = QUYKCV::with('global_address','pictures_videos','videos','company_address','contact_details','social_networks','curriculum_qualifications')->where('cv_url',$request->cv_url)->orWhere('cv_short_url',$request->cv_url)->first();
            
         
            
            if(!$cvData){
                return response()->json([
                    'error' => trans('messages.cv.not_exists')
                ], 406);
            }

            $cvData->viewIncrease = false ;
            if (Auth::guard('api')->check()) {

                $user = Auth::guard('api')->user();
                if($user->id != $cvData->user_id){
                    if(Views::where(['ip_address'=>$request->ip(),'cv_id'=>$cvData->id])->count()==0)
                    {
                        Views::create(['ip_address'=>$request->ip(),'cv_id'=>$cvData->id]);
                        $cvData->viewIncrease = true ;
                    }
                   
                }
            }else{

                if(Views::where(['ip_address'=>$request->ip(),'cv_id'=>$cvData->id])->count()==0){
                    
                    Views::create(['ip_address'=>$request->ip(),'cv_id'=>$cvData->id]);
                    $cvData->viewIncrease = true ;
                }
                

            }

            $teamUser = TeamUser::where(['team_user_id'=>$cvData->user_id])->first();

            if($teamUser){

               
                $cvData->team_url = config('app.frontend_url').Teams::where('user_id',$teamUser->team_id)->first()->team_url;
                $cvData->team_records = TeamRecords::select('heading_text','heading_description')->where('team_id',$teamUser->team_id)->get();
                
               // $teamList = TeamUser::where(['team_id'=>$teamUser->team_id])->whereNotIn('team_user_id', [$cvData->user_id])->get();
                $teamList = TeamUser::where(['team_id'=>$teamUser->team_id])->get();

                 
                $teamusersList = [];
                foreach($teamList as $k=>$teamUser){

                    if($teamUser->active==1){
                        $teamusersList[$k] = $teamUser->team_user_id;
                    }
                
                }

                $cvData->team_list = QUYKCV::select('user_id','first_name','middle_name','last_name','cv_url','cv_short_url')->whereIn('user_id',$teamusersList)->get();
                 
            }

            if($teamUser){
            $settings = DesignSettings::where(['user_id'=>$teamUser->team_id])->get();     
            $cvData->role = "team-user";
            }else{
            $settings = DesignSettings::where(['user_id'=>$cvData->user_id])->get();     
            $cvData->role = "basic";

            }
            $settingsData = [];

            foreach ($settings as $key => $value) {
                if($value->meta_key=="logo" || $value->meta_key=="header_font" || $value->meta_key=="content_font"){
                    $settingsData[$value->meta_key]= config('app.url').Storage::url($value->meta_value);
                }else{
                    $settingsData[$value->meta_key]=$value->meta_value;
                }
                
            }

            if(empty($settingsData)){
            $cvData->designSettings = (object) $settingsData;
                
            }else{
                $cvData->designSettings = $settingsData;

            }
            
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

                if($request->hasHeader('x-usedby') && $request->hasHeader('x-usedby')=="mobile"){
                    $success['success'] = true;
                    $success['data'] = $cvData;
                    return response()->json($success, 200);
                }else{
                    return $cvData;
                }
                
            }else{

                if (Auth::guard('api')->check()) {

                    if($request->hasHeader('x-usedby') && $request->hasHeader('x-usedby')=="mobile"){
                        $success['success'] = true;
                        $success['data'] = $cvData;
                        return response()->json($success, 200);
                    }else{
                        return $cvData;
                    }
                    
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

            $telephone="";
            $email="";
            $mobile="";
            $website="";
            $fax="";
            
            foreach($cv_data->contact_details as $contact_details){

                if(isset($contact_details->network) && $contact_details->network=="email"){
                    if($email==""){
                        $email = $contact_details->url;
                    }
                }
                if(isset($contact_details->network) && $contact_details->network=="telephone"){
                    if($telephone==""){
                        $telephone = $contact_details->url;
                    }
                    
                }
                if(isset($contact_details->network) && $contact_details->network=="mobile"){
                    if($mobile==""){
                        $mobile = $contact_details->url;
                    }
                    
                }
                if(isset($contact_details->network) && $contact_details->network=="website"){
                    if($website==""){
                        $website = $contact_details->url;
                    }
                    
                }
                if(isset($contact_details->network) && $contact_details->network=="fax"){
                    if($fax==""){
                        $fax = $contact_details->url;
                    }
                    
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
           // $file_name = uniqid();
            $file_name = $cv_data->cv_short_url; 

            $unwanted_array = array('Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
                            'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U',
                            'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c',
                            'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
                            'ö'=>'o', 'ø'=>'o', 'ü'=>'u', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y' );
            $file_name = strtr( $file_name, $unwanted_array );
             
            // add work data
            $vcard->addName($lastname, $firstname, $additional, $prefix, $suffix);
            // add work data
            if(isset($cv_data->company_address[0]->company_name)){
               $vcard->addCompany($cv_data->company_address[0]->company_name);
            }

           
            // add adress data
            if(isset($cv_data->company_address[0]->company_name)){
                $vcard->addAddress(null, null,$cv_data->company_address[0]->street." ".$cv_data->company_address[0]->street_number, $cv_data->company_address[0]->city, null,$cv_data->company_address[0]->zip_code, $cv_data->company_address[0]->country);
            }
            
            if(isset($cv_data->pictures_video[0]->location)){
               // $vcard->addPhoto(storage_path('app/public/').$cv_data->pictures_video[0]->location);
             }
            
            $vcard->addJobtitle($cv_data->position_in_company);
             
            $vcard->addEmail($email);
            $vcard->addPhoneNumber($telephone,'WORK;VOICE');
            $vcard->addPhoneNumber($mobile, 'CELL;VOICE');
            $vcard->addPhoneNumber($fax, 'TYPE=WORK,FAX');
            //$vcard->addPhoneNumber($mobile, 'MOBILE');
            
            if(!empty($website)){
                $vcard->addURL($website,'WORK');
            }
            
           
            if($cv_data->media_type=="image" && isset($cv_data->pictures_videos[0]->thumburl)){
                
                
                if (File::exists(storage_path('app/public/').$cv_data->pictures_videos[0]->thumb)) {
                     
                    $vcard->addPhoto(storage_path('app/public/').$cv_data->pictures_videos[0]->thumb);
                }
                
               
            }
            if($cv_data->media_type=="video" && isset($cv_data->videos[0]->thumburl)){

                if (File::exists(storage_path('app/public/').$cv_data->videos[0]->thumb)) {
                    $vcard->addPhoto(storage_path('app/public/').$cv_data->videos[0]->thumb);
                }
                 
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


    private function RemoveSpecialChar($str){
 
        // Using str_ireplace() function
        // to replace the word
        $res = str_ireplace( array( '\'', '"',
        ',' , ';', '<', '>' ), ' ', $str);
   
        // returning the result
        return $res;
    }

    /* Update team email from Team admin  */
    public function updateSingleUserEmail(Request $request){

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
        $details['lang'] = app()->getLocale();
        $details['name'] = auth()->user()->first_name." ".auth()->user()->last_name;
        $details['url'] = config('app.frontend_url');
        
        dispatch(new SendUpdateEmailVerify($details));

        if($request->hasHeader('x-usedby') && $request->hasHeader('x-usedby')=="mobile"){
            $success['success'] = true;
            $success['data'] = array();
            return response()->json($success, 200);
        }else{
            return response()->json(['success' => true]);
        }
		


    }

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

        if($request->hasHeader('x-usedby') && $request->hasHeader('x-usedby')=="mobile"){
            $success['success'] = true;
            $success['data'] = array();
            return response()->json($success, 200);
        }else{
            return response()->json(['success' => true]);
        }
		 
	}


    public function addAddress(Request $request){

        $messages = array(
                        'street_number.alpha_spaces' => trans('messages.cv.street_number'),
                        'company_name.required' =>trans('messages.account_address.company_name'),
                        'gender.required' =>trans('messages.account_address.gender'),
                        'first_name.required' =>trans('messages.account_address.first_name'),
                        'last_name.required' =>trans('messages.account_address.last_name'),
                        'street_address.required' =>trans('messages.account_address.street_address'),
                        'street_number.required' =>trans('messages.account_address.street_number'),
                        'pin_code.required' =>trans('messages.account_address.pin_code'),
                        'location.required' =>trans('messages.account_address.location'),
                        'country.required' =>trans('messages.account_address.country'),
                    );

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
        
        $address = SingleUserAddress::where('user_id',Auth::id())->first();
        $params = $request->input();
        
        if($address){ // if address exists 

            
            
            SingleUserAddress::where(['user_id'=>Auth::id()])->update($params);

            

            
        }else{ // add new address if not exists
            
            $params['user_id'] = Auth::id();
             
            SingleUserAddress::create($params);
        }


        $user = User::where('id',Auth::id())->first();
        $user->first_name = $params['first_name']; 
        $user->last_name = $params['last_name']; 
        $user->last_name_index = substr($params['last_name'], 0, 1); 
        $user->save();


        if($request->hasHeader('x-usedby') && $request->hasHeader('x-usedby')=="mobile"){
            $success['success'] = true;
            $success['data'] = SingleUserAddress::where('user_id',Auth::id())->first();
            return response()->json($success, 200);
        }else{
            return response()->json(['success' => true]);
        }

		

    }

     // get current user address
     public function getAddress(Request $request){

        try{

            if($request->hasHeader('x-usedby') && $request->hasHeader('x-usedby')=="mobile"){
                $success['success'] = true;
                $success['data'] = SingleUserAddress::where('user_id',Auth::id())->first();
                return response()->json($success, 200);
            }else{
                return(SingleUserAddress::where('user_id',Auth::id())->first());
            }
            
        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }
     }

     // delete my account. it will delete whole data of single user
     public function deleteAccount(Request $request){
        try{

            $user = Auth::user();

            if($user->hasRole('basic')==true){

                // code for deleting picture of cv's
                $pictures = QUYKCV::with('pictures_videos')->where('user_id',Auth::id())->get();
                
                foreach($pictures as $picture){

                    foreach($picture->pictures_videos as $imageData){

                        Storage::disk('public')->delete($imageData->location);
                        
                    }
                }
                 
                User::where('id',Auth::id())->forceDelete();
                
		        return response()->json(['success' => true]);
                
            }else{

                return response()->json([
                    'error' => "Please login from basic user"
                ], 406);

            }
           
        
        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }

    } 
}
