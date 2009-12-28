# Map Location Field
 
Version: 3.0  
Author: Nick Dunn, Nils Werner, Brendan Abbott, Symphony Team
Build Date: 28 December 2009  
Requirements: Symphony 2.0.6+

## Installation
 
1. Upload the 'maplocationfield' folder in this archive to your Symphony 'extensions' folder.
2. Enable it by selecting the "Field: Map Location", choose Enable from the with-selected menu, then click Apply.
3. The field will be available in the list when creating a Section.


## Configuration

When adding this field to a section, the following options are available to you:

* **Default Marker Location** is the address of a default marker. Enter any address/ZIP to be geocoded
* **Default Zoom Level** is the initial zoom level of the map

## Usage

When creating a new entry, drag the red marker on the map to change location. To tweak the latitude/longitude click "Edit Location". This panel also allows you to enter an address to be geocoded and placed on the map.

## Data Source Filtering

The field provides a single syntax for radius-based searches. Use the following as a DS filter:

	within DISTANCE UNIT of ORIGIN

* `DISTANCE` is an integer
* `UNIT` is the distance unit: `km`, `mile` or `miles`
* `ORIGIN` is the centre of the radius. Accepts either a latitude/longitude or an address

Examples:

	within 20 km of 10.545, -103.1

	within 1km of 1 West Street, Burleigh Heads, Australia

	within 500 miles of London