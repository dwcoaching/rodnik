<?php

namespace App\Console\Commands;

use SimpleXMLElement;
use App\Models\OSMTag;
use App\Models\Spring;
use App\Models\OverpassImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OverpassImportParse extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'overpass:parse {id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Parse an XML import with specified id';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $overpassImport = OverpassImport::findOrFail($this->argument('id'));

        $xml = new SimpleXMLElement($overpassImport->response);

        $existing = 0;
        $new = 0;

        $count = 1;
        foreach ($xml->node as $node) {
            $spring = Spring::where('osm_node_id', $node['id'])->first();

            if (! $spring) {
                $new = $new + 1;
                $spring = new Spring();
                $spring->osm_node_id = $node['id'];
            } else {
                $existing = $existing + 1;
            }

            $spring->latitude = $node['lat'];
            $spring->longitude = $node['lon'];
            $spring->save();

            DB::table('osm_tags')->where('spring_id', '=', $spring->id)->delete();

            foreach ($node->tag as $tag) {
                $osmTag = new OSMTag();
                $osmTag->key = $tag['k'];
                $osmTag->value = $tag['v'];
                $osmTag->spring_id = $spring->id;
                $osmTag->save();
            };
        }

        echo 'new: ' . $new . "\n";
        echo 'existing: ' . $existing . "\n";
    }
}
