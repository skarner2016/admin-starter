<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_roles', function (Blueprint $table) {
            $table->string('remark')->nullable()->comment('备注');
            $table->tinyInteger('status')->default(1)->comment('1 启用 0 禁用');
        });
    }

    public function down(): void
    {
        Schema::table('admin_roles', function (Blueprint $table) {
            $table->dropColumn(['remark', 'status']);
        });
    }
};
