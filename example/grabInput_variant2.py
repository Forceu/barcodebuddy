#!/usr/bin/python

from evdev import InputDevice, categorize, ecodes
import requests
import yaml


with open('config.yaml') as f:
    config = yaml.safe_load(f)

SERVER_ADDRESS = config['server_address']
API_KEY = config['api_key']
device_id = config['device_id']
device = InputDevice(f'/dev/input/event{device_id}')

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

print('Please begin scanning...')

for event in device.read_loop():
    if event.type == ecodes.EV_KEY:
        eventdata = categorize(event)
        if eventdata.keystate == 1:
            scancode = eventdata.scancode
            if scancode == 28:
                print(f'Sending: {barcode}')
                requests.get(f'{SERVER_ADDRESS}action/scan?apikey={API_KEY}&add={barcode}')
                barcode = ''
            else:
                key = scancodes.get(scancode, NOT_RECOGNIZED_KEY)
                barcode = barcode + key
            if key == NOT_RECOGNIZED_KEY:
                print(f'unknown key, scancode={scancode}')
