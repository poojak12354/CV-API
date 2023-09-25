<?php
namespace App\Http\Traits;
use App\Models\QUYKCV;
use App\Models\Teams;

trait CreateShortUrl {

    public static function generateUniqueCVURL($cv_url,$cv_id=null)
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