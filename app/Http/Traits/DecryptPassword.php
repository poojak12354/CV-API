<?php
namespace App\Http\Traits;


trait DecryptPassword {

    public static function decrypt($password)
    {
        try{

            $string = json_decode(base64_decode($password),true);
            $encryption_key = "356d9abc7532ceb0945b615a622c3370"; 
            $ivkey = isset($string['iv']) ? $string['iv'] : '';
            $encrypted = isset($string['data']) ? $string['data'] : '';
            $encrypted = $encrypted . ':' . base64_encode($ivkey);
            $parts = explode(':', $encrypted);
            $decrypted = openssl_decrypt($parts[0], 'aes-256-cbc', $encryption_key, 0, base64_decode($parts[1]));
             
            return $decrypted;
    
             
        }catch(\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 406);
        }

    }

    /* generate short URL for cv */
    public static function generateUniqueCVSHORTURL($cv_url,$cv_id=null)
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
}