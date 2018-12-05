<?php

class DegreeDistanceFinder {
    private $radius;
    private $diameter;
    private $distanceKM;
    public $lat;
    public $lng;

    public function __construct($diameter) {
        // Save diameter
        $this->diameter = $diameter;

        // Set distance diameter to radius (Half diameter)
        $this->radius = $this->diameter/2;
    
        // Get diagonal distance as a +north, +east distance 
        // (Diagonal is radius, +north & +east must be equal so use square diagonal rule)
        $this->distanceKM = $this->radius / sqrt(2);
    }

    public function latRange() {
        // Require position set first
        if(!isset($this->lat)) return false;

        // Get distance as lat degree (Distance wanted / distance between one degree of latitude)
        $distanceDeg = $this->distanceKM / 111;

        // Set min and max for lat degree (lat + length in decimal degrees, vice versa)
        return [
            "max" => ($this->lat + $distanceDeg),
            "min" => ($this->lat - $distanceDeg)
        ];
    }

    public function lngRange() {
        // Require position set first
        if(!isset($this->lng)) return false;

        // Get distance as 1 longitude degree 
        // (cosine of the degree of latitude * distance between latitutde)
        // Latitude are evenly spaces, longitude changes distance from equator to poles
        $distanceLng = cos( deg2rad($this->lat) ) * 111;

        // Get distance as lng degree 
        // (Distance wanted / distance between one degree of longitude at this latitude)
        $distanceDeg = abs($this->distanceKM / $distanceLng);

        // Set min and max for lng degree (lng + length in decimal degrees, vice versa)
        return [
            "max" => ($this->lng + $distanceDeg),
            "min" => ($this->lng - $distanceDeg)
        ];
    }
}

?>