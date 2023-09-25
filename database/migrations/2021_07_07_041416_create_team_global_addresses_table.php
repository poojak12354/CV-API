<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTeamGlobalAddressesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('team_global_addresses', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('team_id')->unsigned();
            $table->string('company_name')->nullable();
            $table->string('address_designation')->nullable();
            $table->string('additive')->nullable();
            $table->string('road')->nullable();
            $table->string('road_no')->nullable();
            $table->string('postcode')->nullable();
            $table->string('place')->nullable();
            $table->string('country')->nullable();
            $table->foreign('team_id')->references('id')->on('users')->onDelete('cascade');
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
        Schema::dropIfExists('team_global_addresses');
    }
}
