<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBillingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('billings', function (Blueprint $table) {

            $table->id();
            $table->bigInteger('cv_id')->unsigned();
            $table->bigInteger('user_id')->unsigned(); 
            $table->date('billing_start_date')->nullable();
            $table->date('billing_end_date')->nullable();
            $table->smallInteger('billing_days');
            $table->smallInteger('billing_month');
            $table->float('billing_amount',8,2);
            $table->tinyInteger('cv_status')->comment('1 for active,0 for not active,2 for deleted');
            $table->foreign('cv_id')->references('id')->on('quyk_cv')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();

         
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('billings');
    }
}
