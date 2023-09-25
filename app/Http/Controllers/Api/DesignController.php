<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use Storage;
use App\Models\QUYKCVDesignSettings as DesignSettings;
use Illuminate\Support\Facades\Auth;
use File;

class DesignController extends Controller
{
    /* Store Design Settings Data */
    public function storeSettings(Request $request)
    {
        try{
            $rules = array(
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

            $logo_name = DesignSettings::where(['user_id'=>Auth::id(),'meta_key'=>"logo_name"])->first();

            if($logo_name){

                $data['logo_name'] = $logo_name->meta_value;
            }
             

            DesignSettings::where(['user_id'=>Auth::id()])->where('meta_key', '!=' , 'logo')->delete();
            foreach ($data as $key => $value) {

                DesignSettings::Create(
                    ['user_id' => Auth::id(),'meta_key' => $key,'meta_value' =>$value]
                ); 
                
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
    public function getSettings(Request $request)
    {
        try{
            $settings = DesignSettings::where(['user_id'=>Auth::id()])->get();
            $data = [];
            foreach ($settings as $key => $value) {
                if($value->meta_key=="logo" || $value->meta_key=="header_font" || $value->meta_key=="content_font"){
                    $data[$value->meta_key]= config('app.url').Storage::url($value->meta_value);
                }else{
                    $data[$value->meta_key]=$value->meta_value;
                }
                
            }

             // All went well
             if($request->hasHeader('x-usedby') && $request->hasHeader('x-usedby')=="mobile"){
                if(!empty($data)){
                    return response()->json([
                        "success"=>true,
                        'data' => $data
                    ]);
                }else{
                    return response()->json([
                        "success"=>true,
                        'data' => (object) $data
                    ]);
                }
               
            }else{
                 
                  // All went well
                 return $data;
            }
           
        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }
    }


    public function fontUpload(Request $request){

        if ($request->file('header_font')!=null){

            $file = $request->file('header_font');
            
            $fileName = Auth::id().time().".".$file->getClientOriginalExtension();
          
            Storage::disk('local')->put("public/fonts/".$fileName, \File::get($file));


           // $path = $file->store('public/fonts',$fileName);


            $storedSettings = DesignSettings::where(['user_id'=>Auth::id(),'meta_key'=>'header_font'])->first();
            if($storedSettings){
                Storage::disk('public')->delete($storedSettings->meta_value);
                DesignSettings::where(['user_id'=>Auth::id(),'meta_key'=>'header_font'])->delete();

            }   
            
            DesignSettings::create(array('user_id'=>Auth::id(),'meta_value'=>"fonts/".$fileName,'meta_key'=>'header_font')); 

            
                
            // All went well
            return response()->json([
                "status"=>1,
                'message' => 'logo Uploaded Successfully',
                'file' => Storage::disk('public')->url("fonts/".$fileName)
            ]);
       
        }

        if ($request->file('content_font')!=null){

            $file = $request->file('content_font');
            
            $fileName = Auth::id().time().".".$file->getClientOriginalExtension();
          
            Storage::disk('local')->put("public/fonts/".$fileName, \File::get($file));


           // $path = $file->store('public/fonts',$fileName);


            $storedSettings = DesignSettings::where(['user_id'=>Auth::id(),'meta_key'=>'content_font'])->first();
            if($storedSettings){
                Storage::disk('public')->delete($storedSettings->meta_value);
                DesignSettings::where(['user_id'=>Auth::id(),'meta_key'=>'content_font'])->delete();

            }   
            
            DesignSettings::create(array('user_id'=>Auth::id(),'meta_value'=>"fonts/".$fileName,'meta_key'=>'content_font')); 

            
                
            // All went well
            return response()->json([
                "status"=>1,
                'message' => 'logo Uploaded Successfully',
                'file' => Storage::disk('public')->url("fonts/".$fileName)
            ]);
       
        }

    } 


    /* function for upload logo in design from Mobile */

    public function insertMobileLogo(Request $request){

        try{
            $validator = Validator::make($request->all(), [
                'logo' => 'required',
            ]);

            if ($validator->fails()) {

                return response()->json(['error' => $validator->messages()->first()], 406);

            }


            $file = $request->file('logo');
            $filename = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();

            $imageName = Auth::id().time().'.'.$extension;  
            
             

             
             
            Storage::disk('local')->put("public/logos/".$imageName,file_get_contents($file));
           
            if ($request->has('file_name')) {
                $logo_name = $request->file_name ;
            }else{
                $logo_name = $imageName ;
            }
            
            $storedSettings = DesignSettings::where(['user_id'=>Auth::id(),'meta_key'=>'logo'])->first();
            if($storedSettings){
                Storage::disk('public')->delete($storedSettings->meta_value);
                DesignSettings::where(['user_id'=>Auth::id(),'meta_key'=>'logo'])->delete();
                DesignSettings::where(['user_id'=>Auth::id(),'meta_key'=>'logo_name'])->delete();

            }   
            
            DesignSettings::create(array('user_id'=>Auth::id(),'meta_value'=>"logos/".$imageName,'meta_key'=>'logo')); 
            DesignSettings::create(array('user_id'=>Auth::id(),'meta_value'=>$logo_name,'meta_key'=>'logo_name')); 

            $success['success'] = true;
            $success['data']['logo'] = Storage::disk('public')->url("logos/".$imageName);
            $success['data']['logo_name'] = $logo_name;
            return response()->json($success, 200);
            
             

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
            ]);

            if ($validator->fails()) {

                return response()->json(['error' => $validator->messages()->first()], 406);

            }
            
            $base64_image = $request->input('logo'); // your base64 encoded     
            @list($type, $file_data) = explode(';', $base64_image);
            @list(, $file_data) = explode(',', $file_data); 
            $imageName = rand(1,100).Auth::id().time().'.'.'png';  

            Storage::disk('local')->put("public/logos/".$imageName, base64_decode($file_data));

            if ($request->has('file_name')) {
                $logo_name = $request->file_name ;
            }else{
                $logo_name = $imageName ;
            }
            
            $storedSettings = DesignSettings::where(['user_id'=>Auth::id(),'meta_key'=>'logo'])->first();
            if($storedSettings){
                Storage::disk('public')->delete($storedSettings->meta_value);
                DesignSettings::where(['user_id'=>Auth::id(),'meta_key'=>'logo'])->delete();
                DesignSettings::where(['user_id'=>Auth::id(),'meta_key'=>'logo_name'])->delete();

            }   
            
            DesignSettings::create(array('user_id'=>Auth::id(),'meta_value'=>"logos/".$imageName,'meta_key'=>'logo')); 
            DesignSettings::create(array('user_id'=>Auth::id(),'meta_value'=>$logo_name,'meta_key'=>'logo_name')); 

            
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

    /* function for upload logo in design settings */

    public function removeLogo(Request $request){

        try{
            
            $storedSettings = DesignSettings::where(['user_id'=>Auth::id(),'meta_key'=>'logo'])->first();
            if($storedSettings){
                Storage::disk('public')->delete($storedSettings->meta_value);
                DesignSettings::where(['user_id'=>Auth::id(),'meta_key'=>'logo'])->delete();
                DesignSettings::where(['user_id'=>Auth::id(),'meta_key'=>'logo_name'])->delete();

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

    /* function for remove font in design settings */

    public function fontRemove(Request $request){

        try{
            
            $storedSettings = DesignSettings::where(['user_id'=>Auth::id(),'meta_key'=>$request->type])->first();
            if($storedSettings){
                Storage::disk('public')->delete($storedSettings->meta_value);
                DesignSettings::where(['user_id'=>Auth::id(),'meta_key'=>$request->type])->delete();

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

    

    
}
