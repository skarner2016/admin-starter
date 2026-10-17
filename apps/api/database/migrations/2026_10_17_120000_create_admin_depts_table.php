<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_depts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pid')->default(0)->index()->comment('父部门，0 为顶级');
            // 同级唯一在应用层校验：树结构下不同父级允许同名，单列唯一索引表达不了
            $table->string('name', 64)->comment('部门名称');
            $table->integer('sort')->default(0)->comment('同级排序');
            $table->tinyInteger('status')->default(1)->comment('1 启用 0 禁用');
            $table->string('remark')->nullable()->comment('备注');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_depts');
    }
};
