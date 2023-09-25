<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use Storage;
use File;
use App\Models\UserDefaultLang as Lang;
use Illuminate\Support\Facades\Auth;

class LanguageController extends Controller
{

    public function getCountries(Request $request){

        if ($request->has('lng')) {
            $lang = $request->lng;
        }else{
            $lang = "de";
        }
       
        $path = resource_path('lang/'.$lang.'/countries-'.$lang.'.json');

      
        if(File::exists($path)){

            $data = json_decode(file_get_contents($path), true);


            
            $countries = [];
            $countries_new = [];
            foreach($data as $k=>$count){

                $countries[$k]= $count['value'];
                $countries_new[$count['value']]['label'] = $count['label'];
                $countries_new[$count['value']]['value'] = $count['value'];
            }
            
            sort($countries);
            $filter = [];
            $main = [];             
            
            foreach($countries as $k=>$co){

                if($co=="Österreich" || $co=="Deutschland" || $co=="Schweiz")
                {   
                    $main[$k]['label']= $countries_new[$co]['label'];
                    $main[$k]['value']= $countries_new[$co]['value'];
                }else{

                    $filter[$k]['label']= $countries_new[$co]['label'];
                    $filter[$k]['value']= $countries_new[$co]['value'];
                }
                

            }

            $filter = array_values($filter);
            $main = array_values($main);

            if($request->hasHeader('x-usedby') && $request->hasHeader('x-usedby')=="mobile"){
                
                $success['success'] = true;
                $success['data'] = array_merge($main,$filter);
                return response()->json($success, 200);
           
            }else{
                
                $json = array_merge($main,$filter);  
                return $json;
                
            }
            
        }else{
            return [];
        }
    }
    public function getTranslation(Request $request){


        $path = resource_path('lang/'.$request->lng.'/'.$request->lng.'.json');

      
        if(File::exists($path)){
            $json = json_decode(file_get_contents($path), true); 

            return $json;
        }else{
            return [];
        }
       
    }
    /* Store User Language Data */
    public function saveLang(Request $request)
    {
        try{
            $rules = array(
                    'code' => 'required', 
                    'lng' => 'required', 
                    'name' => 'required', 
                );    
            $messages = array(
                        'code.required' => 'The code is required.',
                        'lng.required' => 'The lng is required.',
                        'name.required' => 'The name is required.'
                    );
            
            
            
            $validator = Validator::make( $request->all(), $rules, $messages );  
            if ($validator->fails()) {
                return response()->json(['error' =>$validator->errors()->first(),'key'=>array_key_first($validator->errors()->messages())], 406);
            }

            $exists = Lang::where('user_id',Auth::id())->first();

            if($exists){
                $exists->code = $request->code;
                $exists->lng = $request->lng;
                $exists->name = $request->name;
                $exists->save();

            }else{
            
                Lang::create(array('user_id'=>Auth::id(),'code'=>$request->code,'lng'=>$request->lng,'name'=>$request->name)); 

            }
             

            // All went well
            return response()->json([
                "status"=>1,
                'message' => 'Default Language Successfully.',
            ]);


        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }
    }

    /* Get User Language Data */
    public function getLang(Request $request)
    {
        try{
            
            if(Lang::where(['user_id'=>Auth::id()])->count()>0){
                return Lang::where(['user_id'=>Auth::id()])->first();
            }else{
                return response()->json(null);
            }
            
        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }
    }

 

    
}
