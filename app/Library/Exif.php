<?php

namespace App\Library;

use Intervention\Image\Facades\Image;

class Exif
{
    protected $exif;
    protected $latitudeValue;
    protected $longitudeValue;

    public function __construct($file)
    {
        $exif = Image::make($file)->exif();

        $this->exif = $exif;
        $this->setCooridates();
    }

    public function latitude()
    {
        return $this->latitudeValue;
    }

    public function longitude()
    {
        return $this->longitudeValue;
    }

    public function setCooridates()
    {
        if (isset($this->exif['GPSLatitude']) && isset($this->exif['GPSLongitude']) &&
            isset($this->exif['GPSLatitudeRef']) && isset($this->exif['GPSLongitudeRef']) &&
            in_array($this->exif['GPSLatitudeRef'], array('E','W','N','S')) && in_array($this->exif['GPSLongitudeRef'], array('E','W','N','S'))) {

            $GPSLatitudeRef  = strtolower(trim($this->exif['GPSLatitudeRef']));
            $GPSLongitudeRef = strtolower(trim($this->exif['GPSLongitudeRef']));

            $lat_degrees_a = explode('/',$this->exif['GPSLatitude'][0]);
            $lat_minutes_a = explode('/',$this->exif['GPSLatitude'][1]);
            $lat_seconds_a = explode('/',$this->exif['GPSLatitude'][2]);
            $lng_degrees_a = explode('/',$this->exif['GPSLongitude'][0]);
            $lng_minutes_a = explode('/',$this->exif['GPSLongitude'][1]);
            $lng_seconds_a = explode('/',$this->exif['GPSLongitude'][2]);

            $lat_degrees = $lat_degrees_a[0] / $lat_degrees_a[1];
            $lat_minutes = $lat_minutes_a[0] / $lat_minutes_a[1];
            $lat_seconds = $lat_seconds_a[0] / $lat_seconds_a[1];
            $lng_degrees = $lng_degrees_a[0] / $lng_degrees_a[1];
            $lng_minutes = $lng_minutes_a[0] / $lng_minutes_a[1];
            $lng_seconds = $lng_seconds_a[0] / $lng_seconds_a[1];

            $lat = (float) $lat_degrees+((($lat_minutes*60)+($lat_seconds))/3600);
            $lng = (float) $lng_degrees+((($lng_minutes*60)+($lng_seconds))/3600);

            //If the latitude is South, make it negative.
            //If the longitude is west, make it negative
            $GPSLatitudeRef  == 's' ? $lat *= -1 : '';
            $GPSLongitudeRef == 'w' ? $lng *= -1 : '';

            $this->latitudeValue = $lat;
            $this->longitudeValue = $lng;
        } else {
            $this->latitudeValue = null;
            $this->longitudeValue = null;
        }
    }
}
