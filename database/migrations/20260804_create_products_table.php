<?php

use NovaSysCore\Database\Migration;
use NovaSysCore\Database\Schema;


return new class extends Migration
{


    public function up(): void
    {

        Schema::create(
            'products',
            function($table){

                $table->id();

                $table->string('name');

                $table->decimal(
                    'price'
                );

                $table->integer(
                    'stock'
                );

                $table->timestamps();

            }
        );

    }



    public function down(): void
    {

        Schema::drop('products');

    }


};