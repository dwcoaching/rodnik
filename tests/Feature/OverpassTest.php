<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Library\Overpass;
use App\Models\SpringRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OverpassTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_spring_revision_is_created()
    {
        $json = file_get_contents(base_path('tests/stubs/overpass.json'));

        Overpass::parse(json_decode($json));

        $springRevision = SpringRevision::where('spring_id', 1)
            ->orderBy('id', 'desc')->first();

        $this->assertNotNull($springRevision);
        $this->assertEquals($springRevision->old_latitude, 55.655136);
        $this->assertEquals($springRevision->old_longitude, 36.709845);
        $this->assertEquals($springRevision->new_latitude, 55.655135);
        $this->assertEquals($springRevision->new_longitude, 36.709844);
        $this->assertEquals($springRevision->new_name, 'Родник святого Дионисия');
    }
}
