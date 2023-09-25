<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Response;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });

         //This part will handle ThrottleRequestsException exceptions
         $this->renderable(function (ThrottleRequestsException $e, $request) {
            if ($request->is('api/*')) {
                $local = ($request->hasHeader('X-localization')) ? $request->header('X-localization') : 'de';
                // set laravel localization
                $lans = ['de','en'];
                if(!in_array($local,$lans)){$local="de";}
                app()->setLocale($local);
                return response()->json([
                    'error' => trans('messages.login.limit_reached') 
                ], 429);
            }
        });
    }


 
}
