<?php
/**
 * @author Joas Schilling <nickvergessen@owncloud.com>
 * @author Lukas Reschke <lukas@owncloud.com>
 *
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Activity\Tests\Controller;

use OCA\Activity\Controller\SettingsController;
use OCA\Activity\Tests\TestCase;
use OCP\Activity\IExtension;

class SettingsTest extends TestCase {
	/** @var \PHPUnit_Framework_MockObject_MockObject */
	protected $config;

	/** @var \PHPUnit_Framework_MockObject_MockObject */
	protected $request;

	/** @var \PHPUnit_Framework_MockObject_MockObject */
	protected $urlGenerator;

	/** @var \PHPUnit_Framework_MockObject_MockObject */
	protected $data;

	/** @var \PHPUnit_Framework_MockObject_MockObject */
	protected $random;

	/** @var \PHPUnit_Framework_MockObject_MockObject */
	protected $userSettings;

	/** @var \OCP\IL10N */
	protected $l10n;

	/** @var SettingsController */
	protected $controller;

	protected function setUp() {
		parent::setUp();

		$this->data = $this->getMockBuilder('OCA\Activity\Data')
			->disableOriginalConstructor()
			->getMock();
		$this->userSettings = $this->getMockBuilder('OCA\Activity\UserSettings')
			->disableOriginalConstructor()
			->getMock();

		$this->config = $this->createMock('OCP\IConfig');
		$this->request = $this->createMock('OCP\IRequest');
		$this->urlGenerator = $this->createMock('OCP\IURLGenerator');
		$this->random = $this->createMock('OCP\Security\ISecureRandom');
		$this->l10n = \OCP\Util::getL10N('activity', 'en');

		$this->controller = new SettingsController(
			'activity',
			$this->request,
			$this->config,
			$this->random,
			$this->urlGenerator,
			$this->data,
			$this->userSettings,
			$this->l10n,
			'test'
		);
	}


	public function personalNonTypeSettingsData() {
		return [
			[3600, false, false, 0, false, false],
			[3600 * 24, true, false, 1, true, false],
			[3600 * 24 * 7, false, true, 2, false, true],
		];
	}

	/**
	 * @dataProvider personalNonTypeSettingsData
	 *
	 * @param int $expectedBatchTime
	 * @param bool $notifyEmail
	 * @param bool $notifyStream
	 * @param int $notifySettingBatchTime
	 * @param bool $notifySettingSelf
	 * @param bool $notifySettingSelfEmail
	 */
	public function testPersonalNonTypeSettings($expectedBatchTime, $notifyEmail, $notifyStream, $notifySettingBatchTime, $notifySettingSelf, $notifySettingSelfEmail) {
		$this->data->expects($this->any())
			->method('getNotificationTypes')
			->willReturn([
				'NotificationTestTypeShared' => 'Share description',
				'NotificationTestNoStream' => [
					'desc' => 'Email description',
					'methods' => [IExtension::METHOD_MAIL],
				],
				'NotificationTestNoEmail' => [
					'desc' => 'Stream description',
					'methods' => [IExtension::METHOD_STREAM],
				],
			]);

		$this->request->expects($this->any())
			->method('getParam')
			->willReturnMap([
				['NotificationTestTypeShared_email', false, $notifyEmail],
				['NotificationTestTypeShared_stream', false, $notifyStream],
				['NotificationTestNoStream_email', false, $notifyEmail],
				['NotificationTestNoEmail_stream', false, $notifyStream],
			]);

		$this->config->expects($this->exactly(7))
			->method('setUserValue');
		$this->config->expects($this->at(0))
			->method('setUserValue')
			->with(
				'test',
				'activity',
				'notify_email_NotificationTestTypeShared',
				$notifyEmail
			);
		$this->config->expects($this->at(1))
			->method('setUserValue')
			->with(
				'test',
				'activity',
				'notify_stream_NotificationTestTypeShared',
				$notifyStream
			);
		$this->config->expects($this->at(2))
			->method('setUserValue')
			->with(
				'test',
				'activity',
				'notify_email_NotificationTestNoStream',
				$notifyEmail
			);
		$this->config->expects($this->at(3))
			->method('setUserValue')
			->with(
				'test',
				'activity',
				'notify_stream_NotificationTestNoEmail',
				$notifyStream
			);
		$this->config->expects($this->at(4))
			->method('setUserValue')
			->with(
				'test',
				'activity',
				'notify_setting_batchtime',
				$expectedBatchTime
			);
		$this->config->expects($this->at(5))
			->method('setUserValue')
			->with(
				'test',
				'activity',
				'notify_setting_self',
				$notifySettingSelf
			);
		$this->config->expects($this->at(6))
			->method('setUserValue')
			->with(
				'test',
				'activity',
				'notify_setting_selfemail',
				$notifySettingSelfEmail
			);

		$response = $this->controller->personal(
			$notifySettingBatchTime,
			$notifySettingSelf,
			$notifySettingSelfEmail
		)->getData();
		$this->assertDataResponse($response);
	}

	/**
	 * @param array $response
	 */
	protected function assertDataResponse($response) {
		$this->assertEquals(1, sizeof($response));
		$this->assertArrayHasKey('data', $response);
		$data = $response['data'];
		$this->assertEquals(1, sizeof($data));
		$this->assertArrayHasKey('message', $data);
		$this->assertEquals('Your settings have been updated.', $data['message']);
	}

	public function testDisplayPanelTypeTable() {
		$this->data->expects($this->any())
			->method('getNotificationTypes')
			->willReturn([
				'NotificationTestTypeShared'	=> 'Share description',
				'NotificationTestTypeShared2'	=> [
					'desc' => 'Share description2',
					'methods' => [IExtension::METHOD_MAIL],
				],
			]);

		$renderedResponse = $this->controller->getPanel()->render();
		$this->assertContains('<form id="activity_notifications" class="section">', $renderedResponse);

		// Checkboxes for the type
		$this->assertContains('<label for="NotificationTestTypeShared_email">', $renderedResponse);
		$this->assertContains('<label for="NotificationTestTypeShared_stream">', $renderedResponse);

		$cleanedResponse = str_replace(["\n", "\t"], ' ', $renderedResponse);
		while (strpos($cleanedResponse, '  ') !== false) {
			$cleanedResponse = str_replace('  ', ' ', $cleanedResponse);
		}
		$this->assertContains('<input type="checkbox" id="NotificationTestTypeShared2_email" name="NotificationTestTypeShared2_email" value="1" class="NotificationTestTypeShared2 email checkbox" />', $cleanedResponse);
		$this->assertContains('<input type="checkbox" id="NotificationTestTypeShared2_stream" name="NotificationTestTypeShared2_stream" value="1" class="NotificationTestTypeShared2 stream checkbox" disabled="disabled" />', $cleanedResponse);

		// Description of the type
		$cleanedResponse = str_replace(["\n", "\t"], '', $renderedResponse);
		$this->assertContains('<td class="activity_select_group" data-select-group="NotificationTestTypeShared">Share description</td>', $cleanedResponse);
	}

	public function displayPanelEmailWarningData() {
		return [
			['', true],
			['test@localhost', false],
		];
	}

	/**
	 * @dataProvider displayPanelEmailWarningData
	 *
	 * @param string $email
	 * @param bool $containsWarning
	 */
	public function testDisplayPanelEmailWarning($email, $containsWarning) {
		$this->data->expects($this->any())
			->method('getNotificationTypes')
			->willReturn([]);
		$this->config->expects($this->any())
			->method('getUserValue')
			->willReturn($email);

		$renderedResponse = $this->controller->getPanel()->render();
		$this->assertContains('<form id="activity_notifications" class="section">', $renderedResponse);

		if ($containsWarning) {
			$this->assertContains('You need to set up your email address before you can receive notification emails.', $renderedResponse);
		} else {
			$this->assertNotContains('You need to set up your email address before you can receive notification emails.', $renderedResponse);
		}
	}

	public function displayPanelEmailSendBatchSettingData() {
		return [
			[0, 0, 'Hourly'],
			['foobar', 0, 'Hourly'],
			[3600, 0, 'Hourly'],
			[3600 * 24, 1, 'Daily'],
			[3600 * 24 * 7, 2, 'Weekly'],
		];
	}

	/**
	 * @dataProvider displayPanelEmailSendBatchSettingData
	 *
	 * @param string $setting
	 * @param int $selectedValue
	 * @param string $selectedLabel
	 */
	public function testDisplayPanelEmailSendBatchSetting($setting, $selectedValue, $selectedLabel) {
		$this->data->expects($this->any())
			->method('getNotificationTypes')
			->willReturn([]);
		$this->userSettings->expects($this->any())
			->method('getUserSetting')
			->willReturn($setting);

		$renderedResponse = $this->controller->getPanel()->render();
		$this->assertContains('<form id="activity_notifications" class="section">', $renderedResponse);

		$this->assertContains('<option value="' . $selectedValue . '" selected="selected">' . $selectedLabel . '</option>', $renderedResponse);
	}

	public function feedData() {
		return [
			['true', '012345678901234567890123456789', 'feedurl::'],
			['false', '', ''],
		];
	}

	/**
	 * @dataProvider feedData
	 *
	 * @param string $enabled
	 * @param string $token
	 * @param string $urlPrefix
	 */
	public function testFeed($enabled, $token, $urlPrefix) {
		$this->data->expects($this->any())
			->method('getNotificationTypes')
			->willReturn([]);
		$this->random->expects($this->any())
			->method('generate')
			->with(30)
			->willReturn('012345678901234567890123456789');
		$this->urlGenerator->expects($this->any())
			->method('linkToRouteAbsolute')
			->with('activity.Feed.show', ['token' => '012345678901234567890123456789'])
			->willReturn('feedurl::012345678901234567890123456789');
		$this->config->expects($this->once())
			->method('setUserValue')
			->with('test', 'activity', 'rsstoken', $token);

		$response = $this->controller->feed($enabled)->getData();
		$this->assertEquals(1, sizeof($response));
		$this->assertArrayHasKey('data', $response);
		$data = $response['data'];
		$this->assertEquals(2, sizeof($data));
		$this->assertArrayHasKey('message', $data);
		$this->assertArrayHasKey('rsslink', $data);

		$this->assertEquals($urlPrefix . $token, $data['rsslink']);
		$this->assertEquals('Your settings have been updated.', $data['message']);
	}
}
