<?php
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
use App\Http\Controllers\Api\TeamAdmin\QUYKCVController as TeamAdmin;
use App\Http\Controllers\Api\TeamUser\QUYKCVController as TeamUser;
use App\Http\Controllers\Api\SuperAdminController as SuperAdmin;

use App\Http\Controllers\Api\AuthController as AUTH;

Route::group(['prefix' => 'v1/mobile','middleware' => 'localization'], function () {

  Route::post('user/register', 'Api\AuthController@mobileRegister')->middleware('throttle:5,1');
  Route::post('user/login', 'Api\AuthController@mobileLogin')->middleware('throttle:5,1');
  Route::post('login/verify', 'Api\AuthController@loginVerify')->middleware('throttle:5,1');

});

Route::group(['prefix' => 'v1','middleware' => 'localization'], function () {

      Route::post('user/login', 'Api\AuthController@login')->middleware('throttle:5,1');
      Route::post('getTranslation', 'Api\LanguageController@getTranslation');
      Route::post('getCountries', 'Api\LanguageController@getCountries');
      Route::post('user/auth', 'Api\AuthController@auth')->middleware('throttle:5,1');
      Route::post('user/register', 'Api\AuthController@register')->middleware('throttle:5,1');
      Route::post('user/resetpassword', 'Api\AuthController@resetpassword')->middleware('throttle:5,1');
      Route::post('user/updatePassword', 'Api\AuthController@updatePassword');
      Route::get('user/{username}', 'Api\AuthController@getUser')->middleware('throttle:5,1');;
      Route::get('billing', 'Api\AuthController@createBilling');
      
      Route::get('test', 'Api\QUYKCVController@thumbTest');
      
      Route::post('signupinvite', [AUTH::class,'signupInviteMember'])->middleware('throttle:5,1');;  
      Route::post('verify-new-email', [AUTH::class,'verifyNewEmail'])->middleware('throttle:5,1');;
      Route::post('usercv', 'Api\QUYKCVController@getUserCV'); 
      Route::post('vcf/download', 'Api\QUYKCVController@getUserVCF');

        
      Route::group(['middleware' => 'auth:api'], function () {

          Route::get('profile', 'Api\AuthController@profile');

          Route::post('saveLang', 'Api\LanguageController@saveLang');
      
          Route::get('getLang', 'Api\LanguageController@getLang');

          Route::get('listing', 'Api\QUYKCVController@listing');
          Route::get('list/{id}', 'Api\QUYKCVController@list');
          Route::post('insertcv', 'Api\QUYKCVController@store');
          Route::post('editcv', 'Api\QUYKCVController@edit');
          Route::post('videoUpload', 'Api\QUYKCVController@videoUpload');
          Route::post('videoDelete', 'Api\QUYKCVController@videoDelete');
          Route::post('imageUpload', 'Api\QUYKCVController@imageUpload');
         
          Route::post('mobile/imageUpload', 'Api\QUYKCVController@mobileImageUpload');
          Route::post('mobile/logoUpload', 'Api\DesignController@insertMobileLogo');
         
          Route::post('imageDelete', 'Api\QUYKCVController@imageDelete');
          Route::post('deletecv', 'Api\QUYKCVController@delete');
          Route::post('searchcv', 'Api\QUYKCVController@search');
          Route::post('duplicatecv', 'Api\QUYKCVController@duplicatecv');
          Route::post('updateSettings', 'Api\QUYKCVController@updateSettings');
          Route::get('getCompanies', 'Api\QUYKCVController@getCompanies');
          Route::get('getCompany/{id}', 'Api\QUYKCVController@getCompany');

          Route::post('update-email', 'Api\QUYKCVController@updateSingleUserEmail');

          Route::post('add-address', 'Api\QUYKCVController@addAddress');
          
          Route::get('get-address', 'Api\QUYKCVController@getAddress');
      
          Route::post('updatePassword', 'Api\QUYKCVController@updatePassword');
          
          Route::post('saveDesign', 'Api\DesignController@storeSettings');
          Route::post('getDesign/', 'Api\DesignController@getSettings');
          Route::post('insertLogo', 'Api\DesignController@insertLogo');
          
          Route::post('font-upload', 'Api\DesignController@fontUpload');
          
          Route::post('font-remove', 'Api\DesignController@fontRemove');

          Route::get('removeLogo', 'Api\DesignController@removeLogo');


          Route::get('delete/account', 'Api\QUYKCVController@deleteAccount');

          
          // Route::post('user/register', 'Api\AuthController@register');
        });
});
 
  
Route::group(['prefix' => 'v1/team-admin/','middleware' => ['json.response','auth:api','TeamAdmin','localization']], function () {

      Route::post('saveDesign', 'Api\DesignController@storeSettings');

      Route::post('saveLang', 'Api\LanguageController@saveLang');
      
      Route::get('getLang', 'Api\LanguageController@getLang');
      
      Route::get('billing', [TeamAdmin::class,'getBilling']);

      Route::post('getDesign', 'Api\DesignController@getSettings');

      Route::post('insertLogo', 'Api\DesignController@insertLogo');

      Route::post('font-upload', 'Api\DesignController@fontUpload');

      Route::post('font-remove', 'Api\DesignController@fontRemove');
      
      Route::get('removeLogo', 'Api\DesignController@removeLogo');

      Route::post('insertcv', [TeamAdmin::class,'AddCV']);  
      Route::post('imageUpload', [TeamAdmin::class,'imageUpload']);
      Route::post('imageDelete', [TeamAdmin::class,'imageDelete']);

      Route::post('videoUpload', [TeamAdmin::class,'videoUpload']);
      Route::post('videoDelete', [TeamAdmin::class,'videoDelete']);


      Route::post('invite', [TeamAdmin::class,'inviteTeamMember']);  
      Route::post('editcv', [TeamAdmin::class,'EditCV']);  
      Route::post('deletecv', [TeamAdmin::class,'delete']);
      Route::post('changeStatus', [TeamAdmin::class,'changeStatus']);  
      Route::post('searchUser', [TeamAdmin::class,'searchUser']);  
      Route::post('getUserCV', [TeamAdmin::class,'getUserCV']);  
      Route::get('getUsers', [TeamAdmin::class,'getMyUsers']);  
      Route::get('getUserCharacters', [TeamAdmin::class,'getUserCharacters']);  
      Route::post('getUsers', [TeamAdmin::class,'getUsersWithCharacter']);  
      Route::post('changePermission', [TeamAdmin::class,'changePermission']);  
      
      Route::get('get-address', [TeamAdmin::class,'getAddress']);  
     
      Route::post('add-address', [TeamAdmin::class,'addAddress']);  
      Route::post('edit-address', [TeamAdmin::class,'editAddress']);  
      Route::post('delete-address', [TeamAdmin::class,'deleteAddress']);


      Route::get('getCompanies', [TeamAdmin::class,'getCompanies']);
    
      Route::get('getCompany/{id}', [TeamAdmin::class,'getCompany']);

      Route::post('add-customer-address', [TeamAdmin::class,'addCustomerAddress']);
          
      Route::get('get-customer-address', [TeamAdmin::class,'getCustomerAddress']);

      Route::get('get-record', [TeamAdmin::class,'getRecord']);  
      Route::post('add-record', [TeamAdmin::class,'addRecord']);  
      Route::post('edit-record', [TeamAdmin::class,'editRecord']);  
      Route::post('delete-record', [TeamAdmin::class,'deleteRecord']);  

      Route::get('get-team-overview', [TeamAdmin::class,'getTeamOverview']);  
      Route::post('add-team-overview', [TeamAdmin::class,'addTeamOverview']);  
      
      Route::get('get-team-picture', [TeamAdmin::class,'getTeamPicture']);  
      Route::get('delete-team-picture', [TeamAdmin::class,'deleteTeamPicture']);  
      Route::post('add-team-picture', [TeamAdmin::class,'addTeamPicture']);  
      
      Route::post('updatePassword', [TeamAdmin::class,'updatePassword']);
      Route::get('get-team-url', [TeamAdmin::class,'getTeamUrl']);
      Route::post('update-url', [TeamAdmin::class,'updateTeamUrl']);
      Route::post('update-email', [TeamAdmin::class,'updateTeamEmail']);
      
      Route::get('delete/account', [TeamAdmin::class,'deleteAccount']);

    
});


 
  Route::group(['prefix' => 'v1/team-user/','middleware' => ['json.response','auth:api','TeamUser','localization']], function () {

    Route::post('insertcv', [TeamUser::class,'AddCV']);  
    Route::post('editcv', [TeamUser::class,'EditCV']);  
    Route::post('imageUpload',[TeamUser::class,'imageUpload']);
    Route::post('imageDelete',[TeamUser::class,'imageDelete']);

    Route::post('videoUpload', [TeamUser::class,'videoUpload']);
    Route::post('videoDelete', [TeamUser::class,'videoDelete']);

    Route::post('saveLang', 'Api\LanguageController@saveLang');
      
    Route::get('getLang', 'Api\LanguageController@getLang');
    
    Route::get('getCompanies', [TeamUser::class,'getCompanies']);
    
    Route::get('getCompany/{id}', [TeamUser::class,'getCompany']);


  });

Route::group(['prefix' => 'v1/super-admin/','middleware' => ['json.response','auth:api','SuperAdmin','localization']], function () {
    
    Route::get('getListUsers', [SuperAdmin::class,'getListUsers']); 
    Route::post('getListUsers', [SuperAdmin::class,'getUsersWithCharacter']);  

    Route::post('change/status', [SuperAdmin::class,'changeUserStatus']);  
    
    Route::post('team/editcv', [SuperAdmin::class,'teadAdminEditCV']);  

    Route::post('single/editcv', [SuperAdmin::class,'singleEditCV']);  
    
    Route::post('searchUser', [SuperAdmin::class,'searchUser']);  
    Route::post('getCompanies', [SuperAdmin::class,'getCompanies']);  
    Route::get('getCompany/{id}', [SuperAdmin::class,'getCompany']);
    Route::post('deleteUser', [SuperAdmin::class,'deleteUser']);  
    Route::get('editUser', [SuperAdmin::class,'editUser']);
    Route::post('getUsers', [SuperAdmin::class,'getUsers']);
    Route::post('getCVDetails', [SuperAdmin::class,'getCVDetails']);
   
    Route::post('deletecv', [SuperAdmin::class,'deleteCV']);

    Route::post('imageUpload', [SuperAdmin::class,'imageUpload']);
    Route::post('imageDelete', [SuperAdmin::class,'imageDelete']);

    Route::post('insertcv', [SuperAdmin::class,'AddCV']); 
    Route::post('single/insertcv', [SuperAdmin::class,'AddCVSingleUser']); 

    Route::post('getCVDetails', [SuperAdmin::class,'getCVDetails']);
    
    Route::get('getUserCharacters', [SuperAdmin::class,'getUserCharacters']);  


     
    Route::post('team/getUserCharacters', [SuperAdmin::class,'getTeamUserCharacters']);  
    Route::post('team/getUserLastCharacters', [SuperAdmin::class,'getTeamUsersWithCharacter']);  
    Route::post('team/changePermission', [SuperAdmin::class,'changePermission']); 
    Route::post('team/searchUser', [SuperAdmin::class,'teamSearchUser']);  

    Route::get('team/get-team-overview/{team_id}', [SuperAdmin::class,'getTeamOverview']);  
    Route::post('team/add-team-overview', [SuperAdmin::class,'addTeamOverview']);  
   
    Route::get('team/get-team-url/{team_id}', [SuperAdmin::class,'getTeamUrl']);
    

    Route::get('team/get-team-picture/{team_id}', [SuperAdmin::class,'getTeamPicture']);  
    Route::get('team/delete-team-picture/{team_id}', [SuperAdmin::class,'deleteTeamPicture']);  
    Route::post('team/add-team-picture', [SuperAdmin::class,'addTeamPicture']);


    Route::post('team/add-address/{team_id}', [SuperAdmin::class,'addTeamAddress']);  

    Route::get('team/get-address/{team_id}', [SuperAdmin::class,'getTeamAddress']);  

    Route::post('team/delete-address/{team_id}', [SuperAdmin::class,'deleteTeamAddress']);

    Route::post('saveDesign', [SuperAdmin::class,'saveDesign']);

    Route::get('getDesign/{user_id}', [SuperAdmin::class,'getDesign']);

    Route::post('insertLogo', [SuperAdmin::class,'insertLogo']);

    Route::post('font-upload', [SuperAdmin::class,'fontUpload']);

    Route::post('font-remove', [SuperAdmin::class,'fontRemove']);
    
    Route::get('removeLogo/{user_id}', [SuperAdmin::class,'removeLogo']);
    
    Route::post('add-customer-address/{user_id}', [SuperAdmin::class,'addCustomerAddress']);
          
    Route::get('get-customer-address/{user_id}', [SuperAdmin::class,'getCustomerAddress']);
    
    Route::post('account-create', [SuperAdmin::class,'accountCreate']);

    Route::get('team/get-record/{team_id}', [SuperAdmin::class,'getTeamRecord']);  
    Route::post('team/add-record', [SuperAdmin::class,'addTeamRecord']);  
    Route::post('team/delete-record', [SuperAdmin::class,'deleteTeamRecord']); 

    Route::post('updatePassword', [SuperAdmin::class,'updatePassword']);
  
    Route::post('team/update-url', [SuperAdmin::class,'updateTeamUrl']);
    
    Route::post('team/update-email', [SuperAdmin::class,'updateTeamEmail']);

    Route::post('user/update-email', [SuperAdmin::class,'updateSingleUserEmail']);
    
    Route::post('team/delete/account', [SuperAdmin::class,'deleteTeamAccount']);

    Route::post('videoUpload', 'Api\QUYKCVController@videoUpload');
    Route::post('videoDelete', 'Api\QUYKCVController@videoDelete');
});
