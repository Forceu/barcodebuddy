# Barcode Buddy for Grocy

[![Documentation Status](https://readthedocs.org/projects/barcodebuddy-documentation/badge/?version=latest)](https://barcodebuddy-documentation.readthedocs.io/en/latest/?badge=latest) 

### Available for:

- Bare Metal
- [Docker](https://github.com/Forceu/barcodebuddy-docker)
- [Android](https://play.google.com/store/apps/details?id=de.bulling.barcodebuddyscanner)

## About

Pass barcodes to Barcode Buddy. If already in [Grocys](https://github.com/grocy/grocy) system, it will consume/add/open the product in Grocy. If an unknown barcode is passed, the product name will be looked up and a corresponding product can be chosen in the Web UI. Tags can be saved - if a new product contains the tag in the name, the product will be already preselected in the drop-down list.

## Prerequisites

Webserver that supports PHP (eg. NGINX, Apache) or Docker, a [Grocy](https://github.com/grocy/grocy) API key and ideally a barcode scanner or Android phone

## Getting Started

Refer to the [documentation](https://barcodebuddy-documentation.readthedocs.io/en/latest/), on how to install the program. Installation can be done in a couple of minutes.

### Usage

Barcodes can either manually be added to the programm or the process can be automated by grabbing the input from the scanner.

Refer to the [documentation](https://barcodebuddy-documentation.readthedocs.io/en/latest/), on how to use the program.

##### Reverse Proxies

If you are using a reverse proxy, please make sure to disable caching, see [documentation](https://barcodebuddy-documentation.readthedocs.io/en/latest/setup.html#reverse-proxy)

### Screenshots
![Screenshot of the web ui](https://raw.githubusercontent.com/Forceu/barcodebuddy/master/example/screenshots/FullSite_small.png)     
![Screenshot of the screen module](https://github.com/Forceu/barcodebuddy/raw/master/example/screenshots/Screen_small.gif)


## Contributors
<a href="https://github.com/forceu/barcodebuddy/graphs/contributors">
  <img src="https://contributors-img.web.app/image?repo=forceu/barcodebuddy" />
</a>

## Acknowledgments

* Thanks to Bernd Bestel for creating the amazing [Grocy](https://github.com/grocy/grocy) Project!
* openfoodfacts.org for providing the barcode lookup API
* [PHP-Websocket](https://github.com/bloatless/php-websocket)
* [NoSleep.js](https://github.com/richtr/NoSleep.js)
* [PHP-Auth](https://github.com/delight-im/PHP-Auth)

## License

This project is licensed under the GNU GPL3 - see the [LICENSE.md](LICENSE.md) file for details


## Donations

As with all Free software, the power is less in the finances and more in the collective efforts. I really appreciate every pull request and bug report offered up by BarcodeBuddy's users, so please keep that stuff coming. If however, you're not one for coding/design/documentation, and would like to contribute financially, you can do so with the link below. Every help is very much appreciated!

[![paypal](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=donate@bulling.mobi&lc=US&item_name=BarcodeBuddy&no_note=0&cn=&currency_code=EUR&bn=PP-DonationsBF:btn_donateCC_LG.gif:NonHosted) [![LiberaPay](https://img.shields.io/badge/Donate-LiberaPay-green.svg)](https://liberapay.com/MBulling/donate)

