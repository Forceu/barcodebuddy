# Example Files

## Overview

This folder mostly contains the following files:

* Images of the default barcodes
* Screenshots used for the project page
* Nginx configuration file for your webserver
* Scripts for grabbing input of physical barcode scanners
* Systemd service files to run grabInput and the websocket server

## Barcode Scanner Scripts

All files that start the name with "grabInput" are scripts, that pass inputs from your physical barcode scanner to Barcode Buddy. Most of them require root access.

### grabInput.sh

This bash script is recommended for most scanners and is used for the docker image as well. To use it, run the programm "evtest" while your barcode is connected to your system. It will display a list of possible devices. Remeber the name of the one that your barcode uses (eg. event5). Then start the script with bash grabInput.sh /dev/input/eventX where X is your number.

Also make sure to adjust the path to your Barcode Buddy installation. ("SCRIPT_LOCATION" at the beginning of the file).


### grabInput.py

A Python script similar to the one above


### grabInput_variant3.py

Uses udev rules. If you have a barcode scanner that disconnects (eg. Bluetooth), you will need to use this script.



### grabInput_variant2.py

A Python script that sends the output to a server instead through PHP. This scripts configuration can be found in the file config.yaml

## Systemd support
To run grabInput.sh and the websocket server, copy the two systemd service scripts to /etc/systemd/system. Then edit them and adjust the path entries to fit your system. Afterwards run systemctl daemon-reload and then enable and start both of them using `systemctl enable --now bbuddy-grabInput.service` and `systemctl enable --now bbuddy-websocket.service`.