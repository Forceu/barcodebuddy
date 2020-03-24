#!/usr/bin/python

#Currently this script needs to be run as root. Eventually a udev rule could
#solve this problem

import sys
import os
from evdev import InputDevice, list_devices, ecodes, categorize
#import requests 

SCRIPT_LOCATION = "/var/www/html/barcodebuddy/index.php"
#SERVER_ADDRESS = "https://your.bbuddy.url/index.php"

CODE_MAP_CHAR = {
    
    'KEY_0': "0",
    'KEY_1': "1",
    'KEY_2': "2",
    'KEY_3': "3",
    'KEY_4': "4",
    'KEY_5': "5",
    'KEY_6': "6",
    'KEY_7': "7",
    'KEY_8': "8",
    'KEY_9': "9",
    'KEY_KP0': "0",
    'KEY_KP1': "1",
    'KEY_KP2': "2",
    'KEY_KP3': "3",
    'KEY_KP4': "4",
    'KEY_KP5': "5",
    'KEY_KP6': "6",
    'KEY_KP7': "7",
    'KEY_KP8': "8",
    'KEY_KP9': "9",
    'KEY_NUMERIC_0': "0",
    'KEY_NUMERIC_1': "1",
    'KEY_NUMERIC_2': "2",
    'KEY_NUMERIC_3': "3",
    'KEY_NUMERIC_4': "4",
    'KEY_NUMERIC_5': "5",
    'KEY_NUMERIC_6': "6",
    'KEY_NUMERIC_7': "7",
    'KEY_NUMERIC_8': "8",
    'KEY_NUMERIC_9': "9",
    'KEY_A': "A",
    'KEY_B': "B",
    'KEY_C': "C",
    'KEY_D': "D",
    'KEY_E': "E",
    'KEY_F': "F",
    'KEY_G': "G",
    'KEY_H': "H",
    'KEY_I': "I",
    'KEY_J': "J",
    'KEY_K': "K",
    'KEY_L': "L",
    'KEY_M': "M",
    'KEY_N': "N",
    'KEY_O': "O",
    'KEY_P': "P",
    'KEY_Q': "Q",
    'KEY_R': "R",
    'KEY_S': "S",
    'KEY_T': "T",
    'KEY_U': "U",
    'KEY_V': "V",
    'KEY_W': "W",
    'KEY_X': "X",
    'KEY_Y': "Y",
    'KEY_Z': "Z",
    'KEY_DOT': ".",
    'KEY_MINUS': "-"

}

def parse_key_to_char(val):
    return CODE_MAP_CHAR[val] if val in CODE_MAP_CHAR else ""

if __name__ == "__main__":
    print "List of your devices :"
    devices = [InputDevice(fn) for fn in list_devices()]
    for device in devices:
        print "\t{}\t{}".format(device.fn, device.name)

    print "Choose event ID :",
    event_id = raw_input()

    device = InputDevice('/dev/input/event{}'.format(event_id))
    device.grab()

    data = ""
    for event in device.read_loop():
        if event.type == ecodes.EV_KEY:
            e = categorize(event)
            if e.keystate == e.key_up:
                if e.keycode == "KEY_ENTER":
                    print "Sending :" + data
                    os.system("sudo -H -u www-data /usr/bin/screen -dm /usr/bin/php " + SCRIPT_LOCATION + " " + data)
		    #If you want to send GET requests instead, uncomment the line below and the "import requests". The method above is preferred
		    #requests.get(SERVER_ADDRESS+'?add='+data)
                    data = ""
                else:
                    data += parse_key_to_char(e.keycode)
