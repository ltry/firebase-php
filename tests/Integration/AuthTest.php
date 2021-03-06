<?php

declare(strict_types=1);

namespace Kreait\Firebase\Tests\Integration;

use Kreait\Firebase\Auth;
use Kreait\Firebase\Exception\Auth\RevokedIdToken;
use Kreait\Firebase\Tests\IntegrationTestCase;
use Kreait\Firebase\Util\JSON;

class AuthTest extends IntegrationTestCase
{
    /**
     * @var Auth
     */
    private $auth;

    protected function setUp()
    {
        $this->auth = self::$firebase->getAuth();
    }

    public function testCreateAnonymousUser()
    {
        $user = $this->auth->createAnonymousUser();

        $this->assertNull($user->email);

        $this->auth->deleteUser($user->uid);
    }

    public function testCreateUserWithEmailAndPassword()
    {
        /** @noinspection NonSecureUniqidUsageInspection */
        $email = uniqid('').'@domain.tld';
        $password = 'foobar';

        $check = $this->auth->createUserWithEmailAndPassword($email, $password);

        $this->assertSame($email, $check->email);
        $this->assertFalse($check->emailVerified);

        $this->auth->deleteUser($check->uid);
    }

    public function testChangeUserPassword()
    {
        /** @noinspection NonSecureUniqidUsageInspection */
        $email = uniqid('').'@domain.tld';

        $user = $this->auth->createUserWithEmailAndPassword($email, 'old password');

        $this->auth->changeUserPassword($user->uid, 'new password');

        $this->auth->deleteUser($user->uid);

        $this->assertTrue($noExceptionHasBeenThrown = true);
    }

    public function testChangeUserEmail()
    {
        /** @noinspection NonSecureUniqidUsageInspection */
        $uniqid = uniqid('');
        $email = "${uniqid}@domain.tld";
        $newEmail = "${uniqid}-changed@domain.tld";
        $password = 'my password';

        $user = $this->auth->createUserWithEmailAndPassword($email, $password);

        $check = $this->auth->changeUserEmail($user->uid, $newEmail);
        $this->assertSame($newEmail, $check->email);

        $refetchedUser = $this->auth->getUserByEmail($newEmail);
        $this->assertSame($newEmail, $refetchedUser->email);

        $this->auth->deleteUser($user->uid);
    }

    public function testSendEmailVerification()
    {
        /** @noinspection NonSecureUniqidUsageInspection */
        $uniqid = uniqid();
        $email = "${uniqid}@domain.tld";
        $password = 'my password';

        $user = $this->auth->createUserWithEmailAndPassword($email, $password);

        $this->auth->sendEmailVerification($user->uid);

        $this->auth->deleteUser($user->uid);

        $this->assertTrue($noExceptionHasBeenThrown = true);
    }

    public function testSendPasswordResetEmail()
    {
        /** @noinspection NonSecureUniqidUsageInspection */
        $uniqid = uniqid();
        $email = "${uniqid}@domain.tld";
        $password = 'my password';

        $user = $this->auth->createUserWithEmailAndPassword($email, $password);

        $this->auth->sendPasswordResetEmail($user->email);

        $this->auth->deleteUser($user->uid);

        $this->assertTrue($noExceptionHasBeenThrown = true);
    }

    public function testListUsers()
    {
        // We already should have a list of users, but let's add another one,
        // just to be sure
        $createdUsers = [
            $this->auth->createAnonymousUser(),
            $this->auth->createAnonymousUser(),
        ];

        $userRecords = $this->auth->listUsers($maxResults = 2, $batchSize = 1);

        $count = 0;
        foreach ($userRecords as $userData) {
            $this->assertInstanceOf(Auth\UserRecord::class, $userData);
            ++$count;
        }

        $this->assertSame($maxResults, $count);

        foreach ($createdUsers as $createdUser) {
            $this->auth->deleteUser($createdUser->uid);
        }

        $this->assertTrue($noExceptionHasBeenThrown = true);
    }

    public function testVerifyIdToken()
    {
        $user = $this->auth->createAnonymousUser();

        $idTokenResponse = $this->auth->getApiClient()->exchangeCustomTokenForIdAndRefreshToken(
            $this->auth->createCustomToken($user->uid)
        );
        $idToken = JSON::decode($idTokenResponse->getBody()->getContents(), true)['idToken'];

        $this->auth->verifyIdToken($idToken);

        $this->auth->deleteUser($user->uid);

        $this->assertTrue($noExceptionHasBeenThrown = true);
    }

    public function testRevokeRefreshTokens()
    {
        $user = $this->auth->createAnonymousUser();

        $idTokenResponse = $this->auth->getApiClient()->exchangeCustomTokenForIdAndRefreshToken(
            $this->auth->createCustomToken($user->uid)
        );
        $idToken = JSON::decode($idTokenResponse->getBody()->getContents(), true)['idToken'];

        $this->auth->verifyIdToken($idToken, $checkIfRevoked = false); // Should not throw an exception

        $this->auth->revokeRefreshTokens($user->uid);

        $this->expectException(RevokedIdToken::class);

        $this->auth->verifyIdToken($idToken, $checkIfRevoked = true);

        $this->auth->deleteUser($user->uid);
    }

    public function testVerifyIdTokenString()
    {
        $user = $this->auth->createAnonymousUser();

        $idTokenResponse = $this->auth->getApiClient()->exchangeCustomTokenForIdAndRefreshToken(
            $this->auth->createCustomToken($user->uid)
        );
        $idToken = JSON::decode($idTokenResponse->getBody()->getContents(), true)['idToken'];

        $this->auth->verifyIdToken((string) $idToken);

        $this->auth->deleteUser($user->uid);

        $this->assertTrue($noExceptionHasBeenThrown = true);
    }

    public function testDisableAndEnableUser()
    {
        $user = $this->auth->createAnonymousUser();

        $check = $this->auth->disableUser($user->uid);
        $this->assertTrue($check->disabled);

        $check = $this->auth->enableUser($user->uid);
        $this->assertFalse($check->disabled);

        $this->auth->deleteUser($user->uid);
    }

    public function testGetUserRecord()
    {
        $user = $this->auth->createAnonymousUser();

        $check = $this->auth->getUser($user->uid);

        $this->assertSame($user->uid, $check->uid);

        $this->auth->deleteUser($user->uid);
    }
}
