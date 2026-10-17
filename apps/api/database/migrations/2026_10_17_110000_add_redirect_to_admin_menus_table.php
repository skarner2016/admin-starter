<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_menus', function (Blueprint $table) {
            $table->string('redirect')->nullable()->comment('路由重定向');
            // 按钮类节点没有路由地址
            $table->string('path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('admin_menus', function (Blueprint $table) {
            $table->dropColumn('redirect');
        });
    }
};
