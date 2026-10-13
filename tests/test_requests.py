"""Run the real PHP refresh methods and webhook over local HTTP with Jeedom/API doubles.

Usage: PHP_BINARY=php python3 tests/test_requests.py
"""
import json
import os
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import time
import unittest
from urllib.request import Request, urlopen

SOURCE = Path(__file__).resolve().parents[1]
PHP = os.environ.get('PHP_BINARY', 'php')


def home(home_id, **extra):
    return dict(id=home_id, modules=[], **extra)


class RequestsTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temporary = tempfile.TemporaryDirectory(prefix='netatmo-test-')
        cls.root = Path(cls.temporary.name)
        plugin = cls.root / 'plugins/netatmo'
        shutil.copytree(SOURCE / 'core', plugin / 'core')
        core = cls.root / 'core/php'
        core.mkdir(parents=True)
        shutil.copyfile(SOURCE / 'tests/fixtures/core.inc.php', core / 'core.inc.php')
        (cls.root / 'case.php').write_text('''<?php
require_once __DIR__ . '/core/php/core.inc.php';
switch ($_GET['action']) {
  case 'cron': netatmo::cron10(); netatmo::cronHourly(); break;
  case 'energy': netatmo::refreshClassNetatmo(); break;
  case 'energy-data': netatmo_energy::refresh($fixture['responses']['/homesdata']); break;
  case 'empty-data':
    netatmo_weather::refresh(array()); netatmo_aircare::refresh(array());
    netatmo_security::refresh(array()); netatmo_energy::refresh(array()); break;
  case 'sync-weather': netatmo_weather::sync(); break;
  case 'sync-aircare': netatmo_aircare::sync(); break;
  case 'sync-security': netatmo_security::sync(); break;
  case 'sync-energy': netatmo_energy::sync(); break;
}
''')
        (cls.root / 'case.json').write_text('{"equipment": []}')
        with socket.socket() as listener:
            listener.bind(('127.0.0.1', 0))
            port = listener.getsockname()[1]
        cls.url = f'http://127.0.0.1:{port}'
        cls.log = tempfile.TemporaryFile()
        cls.server = subprocess.Popen(
            [PHP, '-n', '-S', f'127.0.0.1:{port}', '-t', str(cls.root)],
            stdout=cls.log, stderr=cls.log,
        )
        for _ in range(100):
            try:
                with urlopen(cls.url + '/case.php?action=cron', timeout=1):
                    return
            except OSError:
                if cls.server.poll() is not None:
                    cls.log.seek(0)
                    raise RuntimeError(cls.log.read().decode())
                time.sleep(0.05)
        cls.server.terminate()
        raise RuntimeError('PHP test server did not start')

    @classmethod
    def tearDownClass(cls):
        cls.server.terminate()
        cls.server.wait(timeout=5)
        cls.log.close()
        cls.temporary.cleanup()

    def run_case(self, equipment=(), responses=None, action='cron', webhook=None, key='test-key'):
        (self.root / 'case.json').write_text(json.dumps({
            'equipment': equipment, 'responses': responses or {},
        }))
        if webhook is None:
            request = self.url + '/case.php?action=' + action
        else:
            request = Request(
                self.url + '/plugins/netatmo/core/php/jeeNetatmo.php',
                data=json.dumps({'apikey': key, 'data': webhook}).encode(),
                headers={'Content-Type': 'application/json'},
            )
        with urlopen(request, timeout=5) as response:
            result = json.load(response)
        self.assertEqual(result['errors'], [])
        return result

    def assert_requests(self, result, *expected):
        self.assertEqual([(item[0], item[1]) for item in result['requests']], list(expected))

    def test_no_equipment_or_all_disabled(self):
        for equipment in ([], [dict(id=t, type=t, enabled=0) for t in ('weather', 'aircare', 'energy', 'security')]):
            with self.subTest(equipment=equipment):
                self.assert_requests(self.run_case(equipment))

    def test_weather_only(self):
        self.assert_requests(self.run_case([dict(id='station', type='weather')]), ('/getstationsdata', None))

    def test_aircare_only(self):
        self.assert_requests(self.run_case([dict(id='coach', type='aircare')]), ('/gethomecoachsdata', None))

    def test_energy_only(self):
        result = self.run_case([dict(id='room', type='energy', home_id='active')], {
            '/homesdata': {'homes': [home('active'), home('unused')]},
        })
        self.assert_requests(result, ('/homesdata', None), ('/homestatus', {'home_id': 'active'}))

    def test_security_keeps_homestatus_and_hourly_refresh(self):
        result = self.run_case([dict(id='camera', type='security', home_id='active', commands=['state'])], {
            '/homesdata': {'homes': [home('active'), home('unused')]},
            '/homestatus:active': {'home': {'modules': [dict(id='camera', type='NOC', status='on')]}},
        })
        self.assert_requests(result, ('/homesdata', None), ('/homestatus', {'home_id': 'active'}), ('/gethomedata', None))
        self.assertIn(['camera', 'state', 'on'], result['updates'])

    def test_disabled_home_does_not_disable_active_room(self):
        result = self.run_case([
            dict(id='active', type='energy', enabled=0),
            dict(id='room', type='energy', home_id='active'),
            dict(id='unused-room', type='energy', home_id='unused', enabled=0),
        ], {'/homesdata': {'homes': [home('active'), home('unused')]}})
        self.assert_requests(result, ('/homesdata', None), ('/homestatus', {'home_id': 'active'}))

    def test_legacy_equipment_without_home_id(self):
        for key, kind in [('rooms', 'energy'), ('modules', 'security')]:
            with self.subTest(key=key):
                active = dict(id='active', modules=[], rooms=[])
                active[key] = [dict(id='legacy', type='NRV')]
                result = self.run_case([dict(id='legacy', type=kind)], {
                    '/homesdata': {'homes': [active, home('unused')]},
                }, action='energy')
                self.assert_requests(result, ('/homesdata', None), ('/homestatus', {'home_id': 'active'}))

    def test_home_equipment_matches_its_logical_id(self):
        result = self.run_case([dict(id='active', type='energy')], {
            '/homesdata': {'homes': [home('active'), home('unused')]},
        }, action='energy')
        self.assert_requests(result, ('/homesdata', None), ('/homestatus', {'home_id': 'active'}))

    def test_multiple_active_homes_and_duplicate_home(self):
        result = self.run_case([
            dict(id='room-a', type='energy', home_id='a'),
            dict(id='room-b', type='energy', home_id='b'),
        ], {'/homesdata': {'homes': [home('a'), home('b'), home('a'), home('unused')]}}, action='energy')
        self.assert_requests(result, ('/homesdata', None), ('/homestatus', {'home_id': 'a'}), ('/homestatus', {'home_id': 'b'}))

    def test_supplied_energy_data_is_reused(self):
        result = self.run_case([dict(id='room', type='energy', home_id='active')], {
            '/homesdata': {'homes': [home('active')]},
        }, action='energy-data')
        self.assert_requests(result, ('/homestatus', {'home_id': 'active'}))

    def test_empty_supplied_data_does_not_trigger_network(self):
        equipment = [dict(id=t, type=t) for t in ('weather', 'aircare', 'energy', 'security')]
        self.assert_requests(self.run_case(equipment, action='empty-data'))

    def test_sync_still_discovers_without_equipment(self):
        for kind, path in [('weather', '/getstationsdata'), ('aircare', '/gethomecoachsdata'), ('security', '/gethomedata'), ('energy', '/homesdata')]:
            with self.subTest(kind=kind):
                self.assert_requests(self.run_case(action='sync-' + kind), (path, None))

    def test_disabled_thermostat_room_is_not_updated(self):
        result = self.run_case([
            dict(id='active', type='energy'),
            dict(id='disabled-room', type='energy', enabled=0, commands=['temperature']),
        ], {
            '/homesdata': {'homes': [dict(id='active', modules=[dict(id='thermostat', room_id='disabled-room')])]},
            '/homestatus:active': {'home': {'modules': [dict(id='thermostat', type='NATherm1', temperature=20)]}},
        }, action='energy')
        self.assertEqual(result['updates'], [])

    def test_active_heating_values_are_updated(self):
        result = self.run_case([
            dict(id='active', type='energy'),
            dict(id='room', type='energy', home_id='active', commands=['temperature', 'therm_setpoint_temperature']),
        ], {
            '/homesdata': {'homes': [dict(id='active', therm_mode='away', modules=[dict(id='thermostat', room_id='room')])]},
            '/homestatus:active': {'home': {
                'modules': [dict(id='thermostat', type='NATherm1', temperature=20)],
                'rooms': [dict(id='room', therm_setpoint_temperature=21)],
            }},
        }, action='energy')
        self.assertIn(['active', 'mode', 'away'], result['updates'])
        self.assertIn(['room', 'temperature', 20], result['updates'])
        self.assertIn(['room', 'therm_setpoint_temperature', 21], result['updates'])

    def test_relevant_webhooks_refresh(self):
        for payload in [dict(push_type='topology_changed'), dict(push_type='home_event_changed', event_type='schedule')]:
            with self.subTest(payload=payload):
                result = self.run_case([dict(id='active', type='energy')], {
                    '/homesdata': {'homes': [home('active'), home('unused')]},
                }, webhook=payload)
                self.assert_requests(result, ('/homesdata', None), ('/homestatus', {'home_id': 'active'}))

    def test_unrelated_or_incomplete_webhooks_do_not_refresh(self):
        for payload in [dict(push_type='unrelated'), dict(push_type='home_event_changed', event_type='other'), dict(push_type='home_event_changed'), {}]:
            with self.subTest(payload=payload):
                self.assert_requests(self.run_case([dict(id='active', type='energy')], webhook=payload))

    def test_topology_without_active_home_does_not_refresh(self):
        self.assert_requests(self.run_case([dict(id='station', type='weather')], webhook=dict(push_type='topology_changed')))

    def test_device_webhook_keeps_local_update(self):
        result = self.run_case([dict(id='camera', type='security')], webhook=dict(device_id='camera', push_type='NOC', event_type='on'))
        self.assert_requests(result)
        self.assertEqual(result['updates'], [['camera', 'monitoring', 1]])

    def test_unauthorized_webhook_does_nothing(self):
        result = self.run_case([dict(id='active', type='energy')], webhook=dict(push_type='topology_changed'), key='wrong-key')
        self.assert_requests(result)
        self.assertEqual(result['updates'], [])


if __name__ == '__main__':
    unittest.main(verbosity=2)
