<?php
/**
 * @author Joas Schilling <nickvergessen@owncloud.com>
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

namespace OCA\Activity\Tests;

use Doctrine\DBAL\Driver\Statement;
use OCA\Activity\Data;
use OCP\Activity\IExtension;

/**
 * Class DataDeleteActivitiesTest
 *
 * @group DB
 * @package OCA\Activity\Tests
 */
class DataDeleteActivitiesTest extends TestCase {
	/** @var \OCA\Activity\Data */
	protected $data;

	protected function setUp() {
		parent::setUp();

		$activities = array(
			array('affectedUser' => 'delete', 'subject' => 'subject', 'time' => 0),
			array('affectedUser' => 'delete', 'subject' => 'subject2', 'time' => time() - 2 * 365 * 24 * 3600),
			array('affectedUser' => 'otherUser', 'subject' => 'subject', 'time' => time()),
			array('affectedUser' => 'otherUser', 'subject' => 'subject2', 'time' => time()),
		);

		$queryActivity = \OC::$server->getDatabaseConnection()->prepare('INSERT INTO `*PREFIX*activity`(`app`, `subject`, `subjectparams`, `message`, `messageparams`, `file`, `link`, `user`, `affecteduser`, `timestamp`, `priority`, `type`)' . ' VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )');
		foreach ($activities as $activity) {
			$queryActivity->execute(array(
				'app',
				$activity['subject'],
				json_encode([]),
				'',
				json_encode([]),
				'file',
				'link',
				'user',
				$activity['affectedUser'],
				$activity['time'],
				IExtension::PRIORITY_MEDIUM,
				'test',
			));
		}
		$this->data = new Data(
			$this->createMock('\OCP\Activity\IManager'),
			\OC::$server->getDatabaseConnection(),
			$this->createMock('\OCP\IUserSession')
		);
	}

	protected function tearDown() {
		$this->data->deleteActivities(array(
			'type' => 'test',
		));

		parent::tearDown();
	}

	public function deleteActivitiesData() {
		return array(
			array(array('affecteduser' => 'delete'), array('otherUser')),
			array(array('affecteduser' => array('delete', '=')), array('otherUser')),
			array(array('timestamp' => array(time() - 10, '<')), array('otherUser')),
			array(array('timestamp' => array(time() - 10, '>')), array('delete')),
		);
	}

	/**
	 * @dataProvider deleteActivitiesData
	 */
	public function testDeleteActivities($condition, $expected) {
		$this->assertUserActivities(array('delete', 'otherUser'));
		$this->data->deleteActivities($condition);
		$this->assertUserActivities($expected);
	}

	public function testExpireActivities() {
		$backgroundjob = new \OCA\Activity\BackgroundJob\ExpireActivities();
		$this->assertUserActivities(array('delete', 'otherUser'));
		$jobList = $this->createMock('\OCP\BackgroundJob\IJobList');
		$backgroundjob->execute($jobList);
		$this->assertUserActivities(array('otherUser'));
	}

	protected function assertUserActivities($expected) {
		$query = \OC::$server->getDatabaseConnection()->prepare("SELECT `affecteduser` FROM `*PREFIX*activity` WHERE `type` = 'test'");
		$this->assertTableKeys($expected, $query, 'affecteduser');
	}

	protected function assertTableKeys($expected, Statement $query, $keyName) {
		$query->execute();

		$users = array();
		while ($row = $query->fetch()) {
			$users[] = $row[$keyName];
		}
		$query->closeCursor();
		$users = array_unique($users);
		sort($users);
		sort($expected);

		$this->assertEquals($expected, $users);
	}
}
