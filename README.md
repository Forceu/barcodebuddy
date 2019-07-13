# Barcode Buddy for Grocy

Pass barcodes to Barcode Buddy. If already in [Grocys](https://github.com/grocy/grocy) system, it will consume/add/open the product in Grocy. If an unknown barcode is passed, the product name will be looked up and a corresponding product can be chosen in the Web UI. Tags can be saved - if a new product contains the tag in the name, the product will be already preselected in the drop-down list.

## Getting Started

Copy the project files to a webserver. Insert your Grocy API details in config.php. Make sure to enable PHP and disallow the download of the database file (eg. with supplied .htaccess file)

### Prerequisites

Webserver that supports PHP (eg. NGINX, Apache), php-sqlite3, php-curl, ideally a barcode scanner


### Usage

Edit the config.php file and fill in your Grocy API details. You can find the api key in Grocy by clicking on "Settings/Manage API Keys". Then open the path in your browser. To add a barcode (in the following example "123456789"), either pass it to the script as a GET variable eg

```
https://your.webhost.com/barcodebuddy/index.php?showui&add=123456789
```

or pass it from the CLI

```
php index.php 123456789
```

If you pass the argument from the console, you will not see any output if no errors occurred. To achieve the same result with the GUI, do not pass the "showui" parameter, like so:


```
https://your.webhost.com/barcodebuddy/index.php?add=123456789
```

If the barcode is already associated with a Grocy product, one unit will now be consumed. If it was not associated, the name will be looked up through openfoodfacts.org. It is then listed on the main page, where you can select a corresponding product. Click on "Add" to add the units to the grocy inventory, "Consume" to remove then and "Delete" to remove the barcode from the list.

![Screenshot of the web ui](https://raw.githubusercontent.com/Forceu/barcodebuddy/master/screenshots/FullSite_small.png)

To the right there are also checkboxes for every word in the name of the product. If you tick them, Barcode Buddy will automatically preselect the chosen Grocy product in the list, if another barcode is added with the chosen word in it's name.


To tell Barcode Buddy to add a product to the inventory instead of consuming it, scan a barcode first with the value "BBUDDY_P" (values can be changed in the config.php). It will then add all following products to the stock.

To open a product or to consume a spoiled item, scan "BBUDDY_O" (open) or "BBUDDY_CS" (spoiled). After an item is scanned, Barcode Buddy will revert back to "Consuming" by default. A copy of the barcodes can be found in the folder "defaultBarcodes"

## Comments

This is a fairly new project, if you want to contribute, feel free to do so! 

## Authors

* **Marc Ole Bulling** - *Initial work*


## License

This project is licensed under the GNU GPL3 - see the [LICENSE.md](LICENSE.md) file for details

## Acknowledgments

* Thanks to Bernd Bestel for creating the amazing [Grocy](https://github.com/grocy/grocy) Project!
* openfoodfacts.org for providing the barcode lookup API
