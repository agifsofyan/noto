<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DbSystemFiles extends Migration
{
    public function up()
    {
        try {
            if(Schema::hasTable('system_files')) return;
            
            Schema::create('system_files', function (Blueprint $table) {
                $table->increments('id');
                $table->string('disk_name');
                $table->string('file_name');
                $table->integer('file_size');
                $table->string('content_type');
                $table->string('title')->nullable();
                $table->text('description')->nullable();
                $table->string('field')->nullable()->index();
                $table->integer('attachment_id')->nullable();
                $table->string('attachment_type')->nullable();
                $table->boolean('is_public')->default(true);
                $table->integer('sort_order')->nullable();
                $table->timestamps();

                $table->index(['attachment_id', 'attachment_type'], 'system_files_master_index');
            });
        }
        catch (Exception $ex) {
            $this->down();
            throw $ex;
        }
    }

    public function down()
    {
        Schema::dropIfExists('system_files');
    }
}
