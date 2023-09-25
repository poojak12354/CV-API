<?php
namespace App\Http\Traits;
use App\Models\QUYKCVPicturesVideos;
use App\Models\QUYKCVTempPictures;

trait ImageVideoAssign {

    public static function index($request,$cv) {
       
        /* multiple cv images or videos to add */
        if($request->has('cv_images')){
            foreach($request->input('cv_images') as $k=>$images){

                $imageData = QUYKCVTempPictures::where('temp_id',$images['image_id'])->first();
                if($imageData){ // if image exists 
                    QUYKCVPicturesVideos::create(array( "cv_id"=>$cv->id,
                                                    "location"=>$imageData->location,
                                                    "thumb"=>$imageData->thumb,
                                                    "file_name"=>$imageData->file_name,
                                                    "type"=>$imageData->type,
                                                    'order'=>$k,
                                                    "active"=>1
                                                ));
    
                    $imageData->delete();
                }else{ // update order of image
                    QUYKCVPicturesVideos::where(['id'=>$images['image_id'],'cv_id'=>$cv->id])->update(['order'=>$k,'active'=>1]);
                }
                
            }
        }

        if($request->has('cv_videos')){
            foreach($request->input('cv_videos') as $k=>$videos){

                $videoData = QUYKCVTempPictures::where('temp_id',$videos['video_id'])->first();
                if($videoData){ // if image exists 
                    QUYKCVPicturesVideos::create(array( "cv_id"=>$cv->id,
                                                    "location"=>$videoData->location,
                                                    "thumb"=>$videoData->thumb,
                                                    "file_name"=>$videoData->file_name,
                                                    "type"=>$videoData->type,
                                                    'order'=>$k,
                                                    "active"=>1
                                                ));
    
                    $videoData->delete();
                }else{ // update order of image
                    QUYKCVPicturesVideos::where(['id'=>$videos['video_id'],'cv_id'=>$cv->id])->update(['order'=>$k,'active'=>1]);
                }
                
            }
        }
    }
}