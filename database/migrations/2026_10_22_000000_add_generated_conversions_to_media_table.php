<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddGeneratedConversionsToMediaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Required by spatie/laravel-medialibrary v9+, which stores conversion state
     * in its own column instead of custom_properties.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasColumn('media', 'generated_conversions')) {
            Schema::table('media', function (Blueprint $table) {
                $table->json('generated_conversions')->nullable();
            });
        }

        DB::table('media')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $customProperties = json_decode($row->custom_properties ?? '[]', true) ?: [];
                $generatedConversions = $customProperties['generated_conversions'] ?? [];
                unset($customProperties['generated_conversions']);

                DB::table('media')->where('id', $row->id)->update([
                    'generated_conversions' => json_encode((object) $generatedConversions),
                    'custom_properties' => json_encode((object) $customProperties),
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn('generated_conversions');
        });
    }
}
