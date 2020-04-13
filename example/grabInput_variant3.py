#!/usr/bin/env python3

import functools
from select import select

import pyudev
import requests
from evdev import InputDevice, categorize, ecodes

# Replace with your BarcodeBuddy URL
SERVER_ADDRESS = 'http://10.0.20.2/barcodebuddy/api/'
# Replace with your Barcode Buddy API key
API_KEY = 'MyApiKey'
# Replace with an UDEV name string identifier for your scanner
SCANNER_STR = 'BarCode'

context = pyudev.Context()
devices = context.list_devices(subsystem='input')
monitor = pyudev.Monitor.from_netlink(context)
monitor.filter_by(subsystem='input')
monitor.start()

fds = {monitor.fileno(): monitor}
finalizers = []

scancodes = {
    1:  u'?1',
    2:  u'1',
    3:  u'2',
    4:  u'3',
    5:  u'4',
    6:  u'5',
    7:  u'6',
    8:  u'7',
    9:  u'8',
    10: u'9',
    11: u'0',
    12: u'-',
    13: u'?13',
    14: u'?14',
    15: u'?15',
    16: u'Q',
    17: u'W',
    18: u'E',
    19: u'R',
    20: u'T',
    21: u'Y',
    22: u'U',
    23: u'I',
    24: u'O',
    25: u'P',
    26: u'?26',
    27: u'?27',
    28: u'?28',
    29: u'?29',
    30: u'A',
    31: u'S',
    32: u'D',
    33: u'F',
    34: u'G',
    35: u'H',
    36: u'J',
    37: u'K',
    38: u'L',
    39: u'?39',
    40: u'?40',
    41: u'?41',
    42: u'',
    43: u'?43',
    44: u'Z',
    45: u'X',
    46: u'C',
    47: u'V',
    48: u'B',
    49: u'N',
    50: u'M'
}
NOT_RECOGNIZED_KEY = u'?'
barcode = ''


# lazily check to see if the device is already connected before the monitor starts.
for device in devices:
    for name in (i['NAME'] for i in device.ancestors if 'NAME' in i):
        if SCANNER_STR in name:
            dev = InputDevice(device.device_node)
            fds[dev.fd] = dev


while True:
    r, w, x = select(fds, [], [])

    if monitor.fileno() in r:
        r.remove(monitor.fileno())

        for udev in iter(functools.partial(monitor.poll, 0), None):
            # we're only interested in devices that have a device node
            # (e.g. /dev/input/eventX)
            if not udev.device_node:
                break

            # find the device we're interested in and add it to fds
            for name in (i['NAME'] for i in udev.ancestors if 'NAME' in i):
                if SCANNER_STR in name:
                    if udev.action == u'add':
                        print('Device added: %s' % udev)
                        dev = InputDevice(udev.device_node)
                        fds[dev.fd] = dev
                        break
                    if udev.action == u'remove':
                        print('Device removed: %s' % udev)

                        def helper():
                            global fds
                            fds = {monitor.fileno(): monitor}

                        finalizers.append(helper)
                        break

    for fd in r:
        try:
            dev = fds[fd]
            for event in dev.read():
                if event.type == ecodes.EV_KEY:
                    eventdata = categorize(event)
                    if eventdata.keystate == 1:
                        scancode = eventdata.scancode
                        if scancode == 28:
                            print('Sending: ' + barcode)
                            requests.get(SERVER_ADDRESS + 'action/scan?apikey=' + API_KEY + '&add=' + barcode)
                            barcode = ''
                        else:
                            key = scancodes.get(scancode, NOT_RECOGNIZED_KEY)
                            barcode = barcode + key
                            if key == NOT_RECOGNIZED_KEY:
                                print('unknown key, scancode=' + str(scancode))
        except OSError:
            print("Barcode Scanner Unavailable")

    for i in range(len(finalizers)):
        finalizers.pop()()
