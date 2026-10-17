<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            // 可空：super / 系统账号可能不属于任何部门，存量用户升级后也为空
            $table->unsignedBigInteger('dept_id')->nullable()->index()->comment('所属部门');
        });
    }

    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropColumn('dept_id');
        });
    }
};
