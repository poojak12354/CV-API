<?php
namespace App\Http\Traits;

trait GetPercentage {
    public static function index($request) {
       
        $percentage = 0 ;
        if($request->filled('cv_main_data.salutation')) {
            $percentage+=4;
        
        }
        if($request->filled('cv_main_data.first_name')) {
            $percentage+=8;
        }

        if($request->filled('cv_main_data.last_name')) {
            $percentage+=8;
        }

        
        if($request->filled('cv_images')) {
            
            $images = $request->cv_images;
            if(count($images)){
                $percentage+=20;
            }
            
        }
        
        if($request->filled('cv_videos')) {
            
            $percentage+=20;
            
        }

       

        if($request->filled('cv_company_address')) {
            
            $cv_company_address = $request->cv_company_address;
            
            if(count($cv_company_address)){
                
                 
                if(!empty($cv_company_address[0]['street'])){
                    $percentage+=4;
                }
                if(!empty($cv_company_address[0]['street_number'])){
                    $percentage+=4;
                }
                if(!empty($cv_company_address[0]['city'])){
                    $percentage+=4;
                }
                if(!empty($cv_company_address[0]['country'])){
                    $percentage+=4;
                }
                if(!empty($cv_company_address[0]['zip_code'])){
                    $percentage+=4;
                }
                
            }
            
        }

        
        if($request->filled('cv_contact_details')) {
            
            $cv_contact_details = $request->cv_contact_details;
            
            
            
            if(count($cv_contact_details)){

                $email_percentage_added = false;
                 
                
                foreach($cv_contact_details as $detail){

                     
                    if(isset($detail['network']) && $detail['network']=="email" && (!empty($detail['url'])) && $email_percentage_added==false){
                        
                        $percentage+=20;
                        $email_percentage_added=true;
                    }
                     
                }
                
                 
            }
            
        }
        
        if($request->filled('cv_curriculum_qualifications')) {
            
            $cv_curriculum_qualifications = $request->cv_curriculum_qualifications;
            
            
            
            if(count($cv_curriculum_qualifications)){

                $percentage+=20;
                 
            }
            
            
        }
        
       
        if($percentage>100){
            $percentage=100;
        }
        return $percentage;
    }
}