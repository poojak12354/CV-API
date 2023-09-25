<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCvGlobalAddressTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cv_global_address', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('cv_id')->unsigned();
            $table->bigInteger('company_id')->unsigned();
            $table->foreign('cv_id')->references('id')->on('quyk_cv')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('team_global_addresses')->onDelete('cascade');
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
        Schema::dropIfExists('cv_global_address');
    }
}
