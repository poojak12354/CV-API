<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAddressTypeToQuykCvTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('quyk_cv', function (Blueprint $table) {
            $table->enum('address_type', ['global', 'individual'])->default('individual')->after('media_type');
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('quyk_cv', function (Blueprint $table) {
            $table->dropColumn('address_type');
            
        });
    }
}
