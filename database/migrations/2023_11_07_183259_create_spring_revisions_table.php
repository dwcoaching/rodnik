<?php

use App\Models\Report;
use App\Models\SpringRevision;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('spring_revisions', function (Blueprint $table) {
            $table->id();

            $table->decimal('old_latitude', 9, 6)->nullable();
            $table->decimal('old_longitude', 9, 6)->nullable();
            $table->string('old_name')->nullable();
            $table->string('old_type')->nullable();
            $table->string('old_intermittent')->nullable();
            $table->decimal('old_osm_latitude', 9, 6)->nullable();
            $table->decimal('old_osm_longitude', 9, 6)->nullable();
            $table->string('old_osm_name')->nullable();
            $table->string('old_osm_type')->nullable();
            $table->string('old_osm_intermittent')->nullable();

            $table->decimal('new_latitude', 9, 6)->nullable();
            $table->decimal('new_longitude', 9, 6)->nullable();
            $table->string('new_name')->nullable();
            $table->string('new_type')->nullable();
            $table->string('new_intermittent')->nullable();
            $table->decimal('new_osm_latitude', 9, 6)->nullable();
            $table->decimal('new_osm_longitude', 9, 6)->nullable();
            $table->string('new_osm_name')->nullable();
            $table->string('new_osm_type')->nullable();
            $table->string('new_osm_intermittent')->nullable();

            $table->string('revision_type')->nullable();
            // from_osm
            // user
            // bureau
            // merge?

            // config allowed spring_revisions_type
            // merge two osm

            $table->foreignId('user_id')->nullable();
            $table->foreignId('spring_id')->nullable();

            $table->timestamps();
        });

        $this->migrateReportsFromOSM();
        $this->migrateReportsSpringEdit();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spring_revisions');
    }

    protected function migrateReportsFromOSM()
    {
        DB::table('reports')->whereNotNull('from_osm')
            ->chunkById(100, function (Collection $reports) {
                foreach ($reports as $report) {
                    $springRevision = new SpringRevision();
                    $springRevision->revision_type = 'from_osm';
                    $springRevision->spring_id = $report->spring_id;
                    $springRevision->user_id = $report->user_id;

                    $springRevision->old_latitude = $report->old_latitude;
                    $springRevision->old_longitude = $report->old_longitude;
                    $springRevision->old_name = $report->old_name;
                    $springRevision->old_type = $report->old_type;
                    $springRevision->old_intermittent = $report->old_intermittent;

                    $springRevision->new_latitude = $report->new_latitude;
                    $springRevision->new_longitude = $report->new_longitude;
                    $springRevision->new_name = $report->new_name;
                    $springRevision->new_type = $report->new_type;
                    $springRevision->new_intermittent = $report->new_intermittent;

                    $springRevision->created_at = $report->created_at;
                    $springRevision->updated_at = $report->updated_at;

                    $springRevision->save();
                    $reportModel = Report::find($report->id)->delete();
                }
            });
    }

    protected function migrateReportsSpringEdit()
    {
        DB::table('reports')->whereNotNull('spring_edit')
            ->chunkById(100, function (Collection $reports) {
                foreach ($reports as $report) {
                    $springRevision = new SpringRevision();
                    $springRevision->revision_type = 'user';
                    $springRevision->spring_id = $report->spring_id;
                    $springRevision->user_id = $report->user_id;

                    $springRevision->old_latitude = $report->old_latitude;
                    $springRevision->old_longitude = $report->old_longitude;
                    $springRevision->old_name = $report->old_name;
                    $springRevision->old_type = $report->old_type;
                    $springRevision->old_intermittent = $report->old_intermittent;

                    $springRevision->new_latitude = $report->new_latitude;
                    $springRevision->new_longitude = $report->new_longitude;
                    $springRevision->new_name = $report->new_name;
                    $springRevision->new_type = $report->new_type;
                    $springRevision->new_intermittent = $report->new_intermittent;

                    $springRevision->created_at = $report->created_at;
                    $springRevision->updated_at = $report->updated_at;

                    $springRevision->save();
                    $reportModel = Report::find($report->id)->delete();
                }
            });
    }
};
