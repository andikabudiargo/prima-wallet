<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   // database/migrations/xxxx_add_google_id_to_users_table.php
public function up()
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('google_id')->nullable()->unique()->after('email');
        $table->string('password')->nullable()->change(); // user Google gak selalu punya password
    });
}

public function down()
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('google_id');
    });
}
};
