<?php
/**
 * This file is part of openvj project.
 *
 * Copyright 2013-2015 openvj dev team.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace VJ\Test\User;

use VJ\Core\Application;
use VJ\Core\Exception\UserException;
use VJ\User\RememberMeEncoder;
use VJ\User\UserCredential;
use Zumba\PHPUnit\Extensions\Mongo\Client\Connector;
use Zumba\PHPUnit\Extensions\Mongo\DataSet\DataSet;
use Zumba\PHPUnit\Extensions\Mongo\TestTrait;

class UserCredentialTest extends \PHPUnit_Framework_TestCase
{
    use TestTrait;

    private $fixture = [
        'User' => [
            [ // password: test_password
                '_id' => 0,
                'user' => 'test_User',
                'luser' => 'test_user',
                'mail' => 'test@example.com',
                'lmail' => 'test@example.com',
                'salt' => '5b26d1542f68297831044e4cfe10052344e20fea',
                'hash' => 'openvj|$2y$10$5b26d1542f68297831044eOCPuejIMxU6peNfQQUw.HUz8CoxOZ1.',
            ],
            [ // password: test_password
                '_id' => 1,
                'user' => 'test_User2',
                'luser' => 'test_user2',
                'mail' => 'test2@example.com',
                'lmail' => 'test2@example.com',
                'salt' => '5b26d1542f68297831044e4cfe10052344e20fea',
                'hash' => 'openvj|$2y$10$5b26d1542f68297831044eOCPuejIMxU6peNfQQUw.HUz8CoxOZ1.',
                'banned' => true,
            ]
        ],
        'RememberMeToken' => []
    ];
    private $rememberMeClientTokens = [];

    public function __construct()
    {
        // generate client token
        // valid
        $expire = time() + 24 * 60 * 60;
        $clientToken = RememberMeEncoder::generateClientToken(0, (int)$expire);
        $token = RememberMeEncoder::parseClientToken($clientToken);
        $token['expireat'] = new \MongoDate($token['expire']);
        unset($token['expire']);
        $this->rememberMeClientTokens[] = $clientToken;
        $this->fixture['RememberMeToken'][] = $token;

        // expired
        $expire = time() - 24 * 60 * 60;
        $clientToken = RememberMeEncoder::generateClientToken(0, (int)$expire);
        $token = RememberMeEncoder::parseClientToken($clientToken);
        $token['expireat'] = new \MongoDate($token['expire']);
        unset($token['expire']);
        $this->rememberMeClientTokens[] = $clientToken;
        $this->fixture['RememberMeToken'][] = $token;

        // user not valid
        $expire = time() + 24 * 60 * 60;
        $clientToken = RememberMeEncoder::generateClientToken(1, (int)$expire);
        $token = RememberMeEncoder::parseClientToken($clientToken);
        $token['expireat'] = new \MongoDate($token['expire']);
        unset($token['expire']);
        $this->rememberMeClientTokens[] = $clientToken;
        $this->fixture['RememberMeToken'][] = $token;
    }

    public function getMongoConnection()
    {
        $connection = new Connector(Application::get('mongo_client'));
        $connection->setDb(Application::get('config')['mongodb']['db'] . (MODE_TEST ? '-test' : ''));
        return $connection;
    }

    public function getMongoDataSet()
    {
        $dataset = new DataSet($this->getMongoConnection());
        $dataset->setFixture($this->fixture);
        return $dataset;
    }

    public function testCheckPasswordCredentialUserNotExist()
    {
        $throw = false;
        try {
            UserCredential::checkPasswordCredential('test', '', true);
        } catch (UserException $e) {
            $throw = true;
            $this->assertEquals('error.checkCredential.user_not_valid', $e->getUserErrorCode());
        }
        $this->assertTrue($throw, 'Expect thrown exception');

        $throw = false;
        try {
            UserCredential::checkPasswordCredential('test_nonexist@example.com', '', true);
        } catch (UserException $e) {
            $throw = true;
            $this->assertEquals('error.checkCredential.user_not_valid', $e->getUserErrorCode());
        }
        $this->assertTrue($throw, 'Expect thrown exception');
    }

    public function testCheckPasswordCredentialWrongPassword()
    {
        $throw = false;
        try {
            UserCredential::checkPasswordCredential('Test_user', 'test_wrong_password', true);
        } catch (UserException $e) {
            $throw = true;
            $this->assertEquals('error.checkCredential.wrong_password', $e->getUserErrorCode());
        }
        $this->assertTrue($throw, 'Expect thrown exception');

        $throw = false;
        try {
            UserCredential::checkPasswordCredential('TEST@example.com', 'test_wrong_password', true);
        } catch (UserException $e) {
            $throw = true;
            $this->assertEquals('error.checkCredential.wrong_password', $e->getUserErrorCode());
        }
        $this->assertTrue($throw, 'Expect thrown exception');
    }

    public function testCheckPasswordCredentialUserInvalid()
    {
        $throw = false;
        try {
            UserCredential::checkPasswordCredential('Test_user2', 'test_password', true);
        } catch (UserException $e) {
            $throw = true;
            $this->assertEquals('error.checkCredential.user_not_valid', $e->getUserErrorCode());
        }
        $this->assertTrue($throw, 'Expect thrown exception');
    }

    public function testCheckPasswordCredentialPassed()
    {
        $user = UserCredential::checkPasswordCredential('Test_User', 'test_password', true);
        $this->assertEquals($this->fixture['User'][0], $user);
    }

    public function testCheckCookieTokenCredentialInvalid()
    {
        // invalid format
        $throw = false;
        try {
            UserCredential::checkCookieTokenCredential('1|2|a', true);
        } catch (UserException $e) {
            $throw = true;
            $this->assertEquals('error.checkCredential.invalid_rememberme_token', $e->getUserErrorCode());
        }
        $this->assertTrue($throw, 'Expect thrown exception');

        // null
        $throw = false;
        try {
            UserCredential::checkCookieTokenCredential(null, true);
        } catch (UserException $e) {
            $throw = true;
            $this->assertEquals('error.checkCredential.invalid_rememberme_token', $e->getUserErrorCode());
        }
        $this->assertTrue($throw, 'Expect thrown exception');

        // not exist
        $throw = false;
        try {
            UserCredential::checkCookieTokenCredential('1|100|12345678123456781234567812345678', true);
        } catch (UserException $e) {
            $throw = true;
            $this->assertEquals('error.checkCredential.invalid_rememberme_token', $e->getUserErrorCode());
        }
        $this->assertTrue($throw, 'Expect thrown exception');

        // expired
        $throw = false;
        try {
            UserCredential::checkCookieTokenCredential($this->rememberMeClientTokens[1], true);
        } catch (UserException $e) {
            $throw = true;
            $this->assertEquals('error.checkCredential.invalid_rememberme_token', $e->getUserErrorCode());
        }
        $this->assertTrue($throw, 'Expect thrown exception');

        // user banned
        $throw = false;
        try {
            UserCredential::checkCookieTokenCredential($this->rememberMeClientTokens[2], true);
        } catch (UserException $e) {
            $throw = true;
            $this->assertEquals('error.checkCredential.user_not_valid', $e->getUserErrorCode());
        }
        $this->assertTrue($throw, 'Expect thrown exception');
    }

    public function testCheckCookieTokenCredentialPassed()
    {
        $user = UserCredential::checkCookieTokenCredential($this->rememberMeClientTokens[0], true);
        $this->assertEquals($this->fixture['User'][0], $user);
    }

    public function testCreateRememberMeClientToken()
    {
        $clientToken = UserCredential::createRememberMeClientToken(0, '1.2.3.4', null, time() + 60);
        $token = RememberMeEncoder::parseClientToken($clientToken);

        // assert valid
        $user = UserCredential::checkCookieTokenCredential($clientToken, true);
        $this->assertEquals($this->fixture['User'][0], $user);

        // assert record
        $record = Application::coll('RememberMeToken')->findOne([
            'uid' => $token['uid'],
            'token' => $token['token'],
        ]);
        $this->assertEquals('1.2.3.4', $record['ip']);
        $this->assertEquals(null, $record['ua']);
        $this->assertEquals($token['expire'], $record['expireat']->sec);
    }

    public function testInvalidateRememberMeClientToken()
    {
        $clientToken = UserCredential::createRememberMeClientToken(0, '1.2.3.4', null, time() + 60);
        $token = RememberMeEncoder::parseClientToken($clientToken);

        UserCredential::invalidateRememberMeClientToken($clientToken);

        $throw = false;
        try {
            UserCredential::checkCookieTokenCredential($clientToken, true);
        } catch (UserException $e) {
            $throw = true;
            $this->assertEquals('error.checkCredential.invalid_rememberme_token', $e->getUserErrorCode());
        }
        $this->assertTrue($throw, 'Expect thrown exception');

        $record = Application::coll('RememberMeToken')->findOne([
            'uid' => $token['uid'],
            'token' => $token['token'],
        ]);
        $this->assertNull($record);
    }

    public function testSetCredential()
    {
        $ret = UserCredential::setCredential(0, 'new_password');
        $this->assertEquals(1, $ret);

        $user = UserCredential::checkPasswordCredential('test_user', 'new_password', true);
        $this->assertNotNull($user);
        $this->assertEquals('test_user', $user['luser']);
    }

}