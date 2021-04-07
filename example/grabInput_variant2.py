#!/usr/bin/python

from evdev import InputDevice, categorize, ecodes
from retrying import retry
import requests
import yaml
import sys

class ScanEventHandler:
  def onConnect(self):
    # Perform an action on (re-)connect of the scanner.
    pass

  def onScan(self, barcode):
    # Perform an action once a barcode was scanned (e.g. send via API)
    requests.get(f'{SERVER_ADDRESS}action/scan?apikey={API_KEY}&add={barcode}')

  def onException(self, exception):
    # Perform an action on any exception (can also be disconnect).
    pass

with open('config.yaml') as f:
    config = yaml.safe_load(f)

SERVER_ADDRESS = config['server_address']
API_KEY = config['api_key']
DEVICE_ID = config['device_id']

HEADERS = {
    'Authorization': f'Bearer {HA_TOKEN}',
    'content-type': 'application/json',
}

SCAN_CODES = {
    1: u'?1',
    2: u'1',
    3: u'2',
    4: u'3',
    5: u'4',
    6: u'5',
    7: u'6',
    8: u'7',
    9: u'8',
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

scanEventHandler = ScanEventHandler()

@retry(wait_fixed=2000)
def scan_barcodes_loop():
    try:
        device = InputDevice(f'/dev/input/event{DEVICE_ID}')
        barcode = ''

        print('Please begin scanning...')
        scanEventHandler.onConnect()

        for event in device.read_loop():
            if event.type == ecodes.EV_KEY:
                event_data = categorize(event)
                if event_data.keystate == 1:
                    scancode = event_data.scancode
                    if scancode == 28:
                        print(f'Sending: {barcode}')
                        scanEventHandler.onScan(barcode)
                        barcode = ''
                    else:
                        key = SCAN_CODES.get(scancode, NOT_RECOGNIZED_KEY)
                        barcode = barcode + key
                    if key == NOT_RECOGNIZED_KEY:
                        print(f'unknown key, scancode={scancode}')

    except Exception as e:
        print(f'Exception occurred, will retry. {e}')
        scanEventHandler.onException(e)
        raise


scan_barcodes_loop()