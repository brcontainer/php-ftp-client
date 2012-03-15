<?php

class Suin_FTPClient_FTPClientTest extends Suin_FTPClient_Test_TestCase
{
	/**
	 * Return new FTPClent object.
	 * @param bool $login
	 * @return Suin_FTPClient_FTPClient
	 * @throws RuntimeException
	 * @throws RuntimeException
	 */
	protected function getNewFTPClient($login = true)
	{
		$client = new Suin_FTPClient_FTPClient(FTP_CLIENT_TEST_FTP_HOST, FTP_CLIENT_TEST_FTP_PORT);

		if ( $login === false )
		{
			return $client;
		}

		$observer = new Suin_FTPClient_Test_FTPMessageObserver();
		$client->setObserver($observer);

		$success = $client->login(FTP_CLIENT_TEST_FTP_USER, FTP_CLIENT_TEST_FTP_PASS);

		if ( $success === false )
		{
			throw new RuntimeException("Failed to login to the FTP Server.\n".$observer->getMessagesAsString());
		}

		$success = $client->changeDirectory(FTP_CLIENT_TEST_REMOTE_SANDBOX_DIR);

		if ( $success === false )
		{
			throw new RuntimeException("Failed to change the current directory.\n".$observer->getMessagesAsString());
		}

		return $client;
	}

	public function testRealServerAvailable()
	{
		if ( defined('FTP_CLIENT_TEST_FTP_HOST') === false )
		{
			$this->markTestSkipped("This test was skipped. Please set up FTP config at FTPConfig.php");
		}

		if ( is_resource(@fsockopen(FTP_CLIENT_TEST_FTP_HOST, FTP_CLIENT_TEST_FTP_PORT)) === false )
		{
			$this->markTestSkipped("This test was skipped. Please confirm if FTP server is running.");
		}
	}

	/**
	 * @expectedException RuntimeException
	 */
	public function test__construct()
	{
		$this->disableErrorReporting();
		// Case: fsockopen() returns NOT resource.
		new Suin_FTPClient_FTPClient('foo', 0);
	}

	/**
	 * @depends testRealServerAvailable
	 * @expectedException InvalidArgumentException
	 * @expectedExceptionMessage Transfer mode is invalid.
	 */
	public function test__construct_With_invalid_transfer_mode()
	{
		new Suin_FTPClient_FTPClient(FTP_CLIENT_TEST_FTP_HOST, FTP_CLIENT_TEST_FTP_PORT, 'invalid');
	}

	/**
	 * @depends testRealServerAvailable
	 * @expectedException RuntimeException
	 * @expectedExceptionMessage Failed to connect to the FTP Server.
	 */
	public function test__construct_With_unexpected_response_code()
	{
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_getResponse'))
			->getMock();
		$client
			->expects($this->once())
			->method('_getResponse')
			->will($this->returnValue(0));
		$client->__construct(FTP_CLIENT_TEST_FTP_HOST, FTP_CLIENT_TEST_FTP_PORT);
	}

	public function testLogin()
	{
		// Case: Response code is not 331
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_request'))
			->getMock();
		$client
			->expects($this->once())
			->method('_request')
			->with('USER foo')
			->will($this->returnValue(array('code' => 0)));

		$actual = $client->login('foo', 'pass');
		$this->assertFalse($actual);
	}

	public function testLogin_With_invalid_password()
	{
		// Case: Response code is not 230
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_request'))
			->getMock();
		$client
			->expects($this->at(0))
			->method('_request')
			->with('USER foo')
			->will($this->returnValue(array('code' => 331)));
		$client
			->expects($this->at(1))
			->method('_request')
			->with('PASS pass')
			->will($this->returnValue(array('code' => 0)));
		$actual = $client->login('foo', 'pass');
		$this->assertFalse($actual);
	}

	public function testLogin_Success_to_login()
	{
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_request'))
			->getMock();
		$client
			->expects($this->at(0))
			->method('_request')
			->with('USER foo')
			->will($this->returnValue(array('code' => 331)));
		$client
			->expects($this->at(1))
			->method('_request')
			->with('PASS pass')
			->will($this->returnValue(array('code' => 230)));
		$actual = $client->login('foo', 'pass');
		$this->assertTrue($actual);
	}

	/**
	 * @depends testRealServerAvailable
	 */
	public function testLogin_With_real_server()
	{
		$client = $this->getNewFTPClient(false);
		$actual = $client->login(FTP_CLIENT_TEST_FTP_USER, FTP_CLIENT_TEST_FTP_PASS);
		$this->assertTrue($actual);
	}

	public function testDisconnect()
	{
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_request'))
			->getMock();
		$client
			->expects($this->once())
			->method('_request')
			->with('QUIT');

		$reflectionClass = new ReflectionClass($client);
		$reflectionProperty = $reflectionClass->getProperty('connection');
		$reflectionProperty->setAccessible(true);
		$reflectionProperty->setValue($client, 'this is not Null.');

		$client->disconnect();
		$this->assertAttributeSame(null, 'connection', $client);
	}

	public function testGetCurrentDirectory()
	{
		// Case: Response code is not 257
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_request'))
			->getMock();
		$client
			->expects($this->once())
			->method('_request')
			->with('PWD')
			->will($this->returnValue(array('code' => 0)));
		$actual = $client->getCurrentDirectory();
		$this->assertFalse($actual);
	}

	public function testGetCurrentDirectory_Success()
	{
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_request'))
			->getMock();
		$client
			->expects($this->once())
			->method('_request')
			->with('PWD')
			->will($this->returnValue(array(
				'code' => 257,
				'message' => '257 "/Users/suin" is the current directory.',
			)));
		$actual = $client->getCurrentDirectory();
		$this->assertSame('/Users/suin', $actual);
	}

	/**
	 * @depends testRealServerAvailable
	 */
	public function testGetCurrentDirectory_With_real_server()
	{
		$client = $this->getNewFTPClient(false);
		$client->login(FTP_CLIENT_TEST_FTP_USER, FTP_CLIENT_TEST_FTP_PASS);
		$actual = $client->getCurrentDirectory();
		$this->assertTrue(is_string($actual));
	}

	public function testChangeDirectory()
	{
		// Case: Response code is not 250
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_request'))
			->getMock();
		$client
			->expects($this->once())
			->method('_request')
			->with('CWD /foo/bar')
			->will($this->returnValue(array('code' => 0)));
		$actual = $client->changeDirectory('/foo/bar');
		$this->assertFalse($actual);
	}

	public function testChangeDirectory_Success()
	{
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_request'))
			->getMock();
		$client
			->expects($this->once())
			->method('_request')
			->with('CWD /foo/bar')
			->will($this->returnValue(array('code' => 250)));
		$actual = $client->changeDirectory('/foo/bar');
		$this->assertTrue($actual);
	}

	/**
	 * @depends testRealServerAvailable
	 */
	public function testChangeDirectory_With_real_server()
	{
		$client = $this->getNewFTPClient(false);
		$client->login(FTP_CLIENT_TEST_FTP_USER, FTP_CLIENT_TEST_FTP_PASS);
		$actual = $client->changeDirectory(FTP_CLIENT_TEST_REMOTE_SANDBOX_DIR);
		$this->assertTrue($actual);
	}

	public function testRemoveDirectory()
	{
		// Case: Response code is not 250
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_request'))
			->getMock();
		$client
			->expects($this->once())
			->method('_request')
			->with('RMD foo')
			->will($this->returnValue(array('code' => 0)));
		$actual = $client->removeDirectory('foo');
		$this->assertFalse($actual);
	}

	public function testRemoveDirectory_With_success()
	{
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_request'))
			->getMock();
		$client
			->expects($this->once())
			->method('_request')
			->with('RMD foo')
			->will($this->returnValue(array('code' => 250)));
		$actual = $client->removeDirectory('foo');
		$this->assertTrue($actual);
	}

	/**
	 * @depends testRealServerAvailable
	 */
	public function testRemoveDirectory_With_real_server()
	{
		$dirname = __FUNCTION__;
		$client = $this->getNewFTPClient();
		$client->createDirectory($dirname);
		$actual = $client->removeDirectory($dirname);
		$this->assertTrue($actual);
	}

	public function testCreateDirectory()
	{
		// Case: Response code is not 257
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_request'))
			->getMock();
		$client
			->expects($this->once())
			->method('_request')
			->with('MKD foo')
			->will($this->returnValue(array('code' => 0)));
		$actual = $client->createDirectory('foo');
		$this->assertFalse($actual);
	}

	public function testCreateDirectory_Success()
	{
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_request'))
			->getMock();
		$client
			->expects($this->once())
			->method('_request')
			->with('MKD foo')
			->will($this->returnValue(array('code' => 257)));
		$actual = $client->createDirectory('foo');
		$this->assertTrue($actual);
	}

	/**
	 * @depends testRealServerAvailable
	 */
	public function testCreateDirectory_With_real_server()
	{
		$client = $this->getNewFTPClient();
		$actual = $client->createDirectory(__FUNCTION__);
		$client->removeDirectory(__FUNCTION__);
		$this->assertTrue($actual);
	}

	public function testRename()
	{
		// Return code for RNFR is not 350
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_request'))
			->getMock();
		$client
			->expects($this->once())
			->method('_request')
			->with('RNFR foo')
			->will($this->returnValue(array('code' => 0)));
		$actual = $client->rename('foo', 'bar');
		$this->assertFalse($actual);
	}

	public function testRename_With_RNTO_fail()
	{
		// Return code for RNTO is not 250
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_request'))
			->getMock();
		$client
			->expects($this->at(0))
			->method('_request')
			->with('RNFR foo')
			->will($this->returnValue(array('code' => 350)));
		$client
			->expects($this->at(1))
			->method('_request')
			->with('RNTO bar')
			->will($this->returnValue(array('code' => 0)));
		$actual = $client->rename('foo', 'bar');
		$this->assertFalse($actual);
	}

	public function testRename_Success()
	{
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_request'))
			->getMock();
		$client
			->expects($this->at(0))
			->method('_request')
			->with('RNFR foo')
			->will($this->returnValue(array('code' => 350)));
		$client
			->expects($this->at(1))
			->method('_request')
			->with('RNTO bar')
			->will($this->returnValue(array('code' => 250)));
		$actual = $client->rename('foo', 'bar');
		$this->assertTrue($actual);
	}

	/**
	 * @depends testRealServerAvailable
	 */
	public function testRename_With_real_server()
	{
		$oldName = __FUNCTION__.'1';
		$newName = __FUNCTION__.'2';

		$client = $this->getNewFTPClient();
		$client->createDirectory($oldName);
		$actual = $client->rename($oldName, $newName);
		$client->removeDirectory($newName);
		$this->assertTrue($actual);
	}

	public function testRemoveFile()
	{
		// Return code for RNFR is not 250
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_request'))
			->getMock();
		$client
			->expects($this->once())
			->method('_request')
			->with('DELE foo')
			->will($this->returnValue(array('code' => 0)));
		$actual = $client->removeFile('foo');
		$this->assertFalse($actual);
	}

	public function testRemoveFile_Success()
	{
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_request'))
			->getMock();
		$client
			->expects($this->once())
			->method('_request')
			->with('DELE foo')
			->will($this->returnValue(array('code' => 250)));
		$actual = $client->removeFile('foo');
		$this->assertTrue($actual);
	}

	/**
	 * @param $mode
	 * @dataProvider data4testSetPermission
	 * @expectedException InvalidArgumentException
	 */
	public function testSetPermission($mode)
	{
		// Case: Invalid mode
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_request'))
			->getMock();
		$client
			->expects($this->never())
			->method('_request');
		$client->setPermission('foo', $mode);
	}

	public static function data4testSetPermission()
	{
		return array(
			array('1'), // Not int
			array(-1), // invalid range
			array(0777 + 1), //invalid range
		);
	}

	public function testSetPermission_With_unexpected_response_code()
	{
		// Case: Invalid mode
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_request'))
			->getMock();
		$client
			->expects($this->once())
			->method('_request')
			->with('SITE CHMOD 777 foo')
			->will($this->returnValue(array('code' => 0)));
		$actual = $client->setPermission('foo', 0777);
		$this->assertFalse($actual);
	}

	public function testSetPermission_Success()
	{
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_request'))
			->getMock();
		$client
			->expects($this->once())
			->method('_request')
			->with('SITE CHMOD 777 foo')
			->will($this->returnValue(array('code' => 200)));
		$actual = $client->setPermission('foo', 0777);
		$this->assertTrue($actual);
	}

	/**
	 * @depends testRealServerAvailable
	 */
	public function testSetPermission_With_real_server()
	{
		// TODO
	}

	public function testGetList()
	{
		// Case: Fails to open data connection.
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_openPassiveDataConnection', '_request'))
			->getMock();
		$client
			->expects($this->once())
			->method('_openPassiveDataConnection')
			->will($this->returnValue(false));
		$client
			->expects($this->never())
			->method('_request');

		$actual = $client->getList('dir');
		$this->assertFalse($actual);
	}

	public function testGetList_With_NLST_response_code_not_150()
	{
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_openPassiveDataConnection', '_request'))
			->getMock();
		$client
			->expects($this->once())
			->method('_openPassiveDataConnection')
			->will($this->returnValue(true));
		$client
			->expects($this->once())
			->method('_request')
			->with('NLST dir')
			->will($this->returnValue(array('code' => 0)));
		$actual = $client->getList('dir');
		$this->assertFalse($actual);
	}

	public function testGetList_Success()
	{
		$resource = fopen('php://memory', 'rw');
		fwrite($resource, "index.php\r\nfoo.gif\nbar.png\r");
		fseek($resource, 0);

		$expect = array('index.php', 'foo.gif', 'bar.png');

		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_openPassiveDataConnection', '_request'))
			->getMock();
		$client
			->expects($this->once())
			->method('_openPassiveDataConnection')
			->will($this->returnValue($resource));
		$client
			->expects($this->once())
			->method('_request')
			->with('NLST dir')
			->will($this->returnValue(array('code' => 150)));

		$actual = $client->getList('dir');

		$this->assertSame($expect, $actual);
	}

	/**
	 * @depends testRealServerAvailable
	 */
	public function testGetList_With_real_server()
	{
		// TODO
	}

	/**
	 * @expectedException InvalidArgumentException
	 * @expectedExceptionMessage Invalid mode "invalid mode" was given
	 */
	public function testDownload()
	{
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_openPassiveDataConnection', '_request'))
			->getMock();
		$client
			->expects($this->never())
			->method('_openPassiveDataConnection');
		$client
			->expects($this->never())
			->method('_request');

		$client->download('remote file', 'local file', 'invalid mode');
	}

	/**
	 * @expectedException RuntimeException
	 * @expectedExceptionMessage Failed to open local file ""
	 */
	public function testDownload_Fails_to_open_local_file()
	{
		$this->disableErrorReporting();

		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_openPassiveDataConnection', '_request'))
			->getMock();
		$client
			->expects($this->never())
			->method('_openPassiveDataConnection');
		$client
			->expects($this->never())
			->method('_request');

		$client->download('remote file', null, Suin_FTPClient_FTPClient::MODE_ASCII);
	}

	public function testDownload_Ascii_mode()
	{
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_openPassiveDataConnection', '_request'))
			->getMock();
		$client
			->expects($this->never())
			->method('_openPassiveDataConnection');
		$client
			->expects($this->once())
			->method('_request')
			->with('TYPE A')
			->will($this->returnValue(array('code' => 0)));

		$client->download('remote file', 'php://stdout', Suin_FTPClient_FTPClient::MODE_ASCII);
	}

	public function testDownload_Binary_mode()
	{
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_openPassiveDataConnection', '_request'))
			->getMock();
		$client
			->expects($this->never())
			->method('_openPassiveDataConnection');
		$client
			->expects($this->once())
			->method('_request')
			->with('TYPE I')
			->will($this->returnValue(array('code' => 0)));

		$client->download('remote file', 'php://stdout', Suin_FTPClient_FTPClient::MODE_BINARY);
	}

	public function testDownload_TYPE_not_200()
	{
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_openPassiveDataConnection', '_request'))
			->getMock();
		$client
			->expects($this->never())
			->method('_openPassiveDataConnection');
		$client
			->expects($this->once())
			->method('_request')
			->with('TYPE I')
			->will($this->returnValue(array('code' => 0)));

		$actual = $client->download('remote file', 'php://stdout', Suin_FTPClient_FTPClient::MODE_BINARY);
		$this->assertFalse($actual);
	}

	public function testDownload_Fails_to_open_data_connection()
	{
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_openPassiveDataConnection', '_request'))
			->getMock();
		$client
			->expects($this->once())
			->method('_request')
			->with('TYPE I')
			->will($this->returnValue(array('code' => 200)));
		$client
			->expects($this->once())
			->method('_openPassiveDataConnection')
			->will($this->returnValue(false));

		$actual = $client->download('remote file', 'php://stdout', Suin_FTPClient_FTPClient::MODE_BINARY);
		$this->assertFalse($actual);
	}

	public function testDownload_RETR_not_150()
	{
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_openPassiveDataConnection', '_request'))
			->getMock();
		$client
			->expects($this->at(0))
			->method('_request')
			->with('TYPE I')
			->will($this->returnValue(array('code' => 200)));
		$client
			->expects($this->at(1))
			->method('_openPassiveDataConnection')
			->will($this->returnValue(true));
		$client
			->expects($this->at(2))
			->method('_request')
			->with('RETR foo')
			->will($this->returnValue(array('code' => 0)));

		$actual = $client->download('foo', 'php://stdout', Suin_FTPClient_FTPClient::MODE_BINARY);
		$this->assertFalse($actual);
	}

	public function testDownload_Success()
	{
		$contents = 'data data data';

		$dataConnection = tmpfile();
		fwrite($dataConnection, $contents);
		fseek($dataConnection, 0);

		$localFile = tempnam(sys_get_temp_dir(), 'Tux');

		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_openPassiveDataConnection', '_request'))
			->getMock();
		$client
			->expects($this->at(0))
			->method('_request')
			->with('TYPE I')
			->will($this->returnValue(array('code' => 200)));
		$client
			->expects($this->at(1))
			->method('_openPassiveDataConnection')
			->will($this->returnValue($dataConnection));
		$client
			->expects($this->at(2))
			->method('_request')
			->with('RETR foo')
			->will($this->returnValue(array('code' => 150)));

		$actual = $client->download('foo', $localFile, Suin_FTPClient_FTPClient::MODE_BINARY);
		$this->assertTrue($actual);

		$this->assertSame($contents, file_get_contents($localFile));
	}

	/**
	 * @depends testRealServerAvailable
	 */
	public function testDownload_With_real_server_ASCII()
	{
		// TODO
	}

	/**
	 * @depends testRealServerAvailable
	 */
	public function testDownload_With_real_server_BINARY()
	{
		// TODO
	}

	/**
	 * @expectedException InvalidArgumentException
	 * @expectedExceptionMessage Invalid mode "invalid mode" was given
	 */
	public function testUpload()
	{
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_openPassiveDataConnection', '_request'))
			->getMock();
		$client
			->expects($this->never())
			->method('_openPassiveDataConnection');
		$client
			->expects($this->never())
			->method('_request');

		$client->upload('local file', 'remote file', 'invalid mode');
	}


	/**
	 * @expectedException RuntimeException
	 * @expectedExceptionMessage Failed to open local file ""
	 */
	public function testUpload_Fails_to_open_local_file()
	{
		$this->disableErrorReporting();

		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_openPassiveDataConnection', '_request'))
			->getMock();
		$client
			->expects($this->never())
			->method('_openPassiveDataConnection');
		$client
			->expects($this->never())
			->method('_request');

		$client->upload(null, 'remote file', Suin_FTPClient_FTPClient::MODE_ASCII);
	}

	public function testUpload_Ascii_mode()
	{
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_openPassiveDataConnection', '_request'))
			->getMock();
		$client
			->expects($this->never())
			->method('_openPassiveDataConnection');
		$client
			->expects($this->once())
			->method('_request')
			->with('TYPE A')
			->will($this->returnValue(array('code' => 0)));

		$client->upload('php://memory', 'remote file name', Suin_FTPClient_FTPClient::MODE_ASCII);
	}

	public function testUpload_Binary_mode()
	{
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_openPassiveDataConnection', '_request'))
			->getMock();
		$client
			->expects($this->never())
			->method('_openPassiveDataConnection');
		$client
			->expects($this->once())
			->method('_request')
			->with('TYPE I')
			->will($this->returnValue(array('code' => 0)));

		$client->upload('php://memory', 'remote file name', Suin_FTPClient_FTPClient::MODE_BINARY);
	}

	public function testUpload_TYPE_not_200()
	{
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_openPassiveDataConnection', '_request'))
			->getMock();
		$client
			->expects($this->never())
			->method('_openPassiveDataConnection');
		$client
			->expects($this->once())
			->method('_request')
			->with('TYPE I')
			->will($this->returnValue(array('code' => 0)));

		$actual = $client->upload('php://memory', 'remote file name', Suin_FTPClient_FTPClient::MODE_BINARY);
		$this->assertFalse($actual);
	}

	public function testUpload_Fails_to_open_data_connection()
	{
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_openPassiveDataConnection', '_request'))
			->getMock();
		$client
			->expects($this->once())
			->method('_request')
			->with('TYPE I')
			->will($this->returnValue(array('code' => 200)));
		$client
			->expects($this->once())
			->method('_openPassiveDataConnection')
			->will($this->returnValue(false));

		$actual = $client->upload('php://memory', 'remote file name', Suin_FTPClient_FTPClient::MODE_BINARY);
		$this->assertFalse($actual);
	}

	public function testUpload_STOR_not_150()
	{
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_openPassiveDataConnection', '_request'))
			->getMock();
		$client
			->expects($this->at(0))
			->method('_request')
			->with('TYPE I')
			->will($this->returnValue(array('code' => 200)));
		$client
			->expects($this->at(1))
			->method('_openPassiveDataConnection')
			->will($this->returnValue(true));
		$client
			->expects($this->at(2))
			->method('_request')
			->with('STOR remote file name')
			->will($this->returnValue(array('code' => 0)));

		$actual = $client->upload('php://memory', 'remote file name', Suin_FTPClient_FTPClient::MODE_BINARY);
		$this->assertFalse($actual);
	}

	public function testUpload_Success()
	{
		$contents = 'data data data';
		$dataConnection = tmpfile();

		$localFile = tempnam(sys_get_temp_dir(), 'Tux');
		file_put_contents($localFile, $contents);

		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_openPassiveDataConnection', '_request'))
			->getMock();
		$client
			->expects($this->at(0))
			->method('_request')
			->with('TYPE I')
			->will($this->returnValue(array('code' => 200)));
		$client
			->expects($this->at(1))
			->method('_openPassiveDataConnection')
			->will($this->returnValue($dataConnection));
		$client
			->expects($this->at(2))
			->method('_request')
			->with('STOR foo')
			->will($this->returnValue(array('code' => 150)));

		$actual = $client->upload($localFile, 'foo', Suin_FTPClient_FTPClient::MODE_BINARY);
		$this->assertTrue($actual);

		fseek($dataConnection, 0);

		$this->assertSame($contents, fgets($dataConnection));
	}

	/**
	 * @depends testRealServerAvailable
	 */
	public function testUpload_With_real_server()
	{
		// TODO
	}

	public function testSetObserver()
	{
		$client = $this->getNewFTPClient(false);

		$this->assertAttributeSame(null, 'observer', $client);

		$observer = new Suin_FTPClient_StdOutObserver();

		$client->setObserver($observer);

		$this->assertAttributeSame($observer, 'observer', $client);
	}

	public function test_openPassiveDataConnection()
	{
		// Response code is not 227
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_request', '_parsePassiveServerInfo'))
			->getMock();
		$client
			->expects($this->once())
			->method('_request')
			->with('PASV')
			->will($this->returnValue(array('code' => 0)));
		$client
			->expects($this->never())
			->method('_parsePassiveServerInfo');

		$reflectionClass = new ReflectionClass($client);
		$method = $reflectionClass->getMethod('_openPassiveDataConnection');
		$method->setAccessible(true);
		$actual = $method->invoke($client);

		$this->assertFalse($actual);
	}

	public function test_openPassiveDataConnection_Fails_to_parse_response_message()
	{
		$responseMessage = 'the response message';

		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_request', '_parsePassiveServerInfo'))
			->getMock();
		$client
			->expects($this->once())
			->method('_request')
			->with('PASV')
			->will($this->returnValue(array('code' => 227, 'message' => $responseMessage)));
		$client
			->expects($this->once())
			->method('_parsePassiveServerInfo')
			->with($responseMessage)
			->will($this->returnValue(false));

		$reflectionClass = new ReflectionClass($client);
		$method = $reflectionClass->getMethod('_openPassiveDataConnection');
		$method->setAccessible(true);
		$actual = $method->invoke($client);

		$this->assertFalse($actual);
	}

	public function test_openPassiveDataConnection_Fails_to_open_socket()
	{
		$this->disableErrorReporting();

		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_request', '_parsePassiveServerInfo'))
			->getMock();
		$client
			->expects($this->once())
			->method('_request')
			->with('PASV')
			->will($this->returnValue(array('code' => 227, 'message' => 'response message')));
		$client
			->expects($this->once())
			->method('_parsePassiveServerInfo')
			->will($this->returnValue(array('host' => null, 'port' => null)));

		$reflectionClass = new ReflectionClass($client);
		$method = $reflectionClass->getMethod('_openPassiveDataConnection');
		$method->setAccessible(true);
		$actual = $method->invoke($client);

		$this->assertFalse($actual);
	}

	/**
	 * @depends testRealServerAvailable
	 */
	public function test_openPassiveDataConnection_With_real_server()
	{
		// TODO
	}

	public function test_parsePassiveServerInfo()
	{
		// Fails to parse, as invalid format is given.

		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->getMock();

		$reflectionClass = new ReflectionClass($client);
		$method = $reflectionClass->getMethod('_parsePassiveServerInfo');
		$method->setAccessible(true);
		$actual = $method->invoke($client, 'invalid format');
		$this->assertFalse($actual);
	}

	public function test_parsePassiveServerInfo_Success()
	{
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->getMock();

		$reflectionClass = new ReflectionClass($client);
		$method = $reflectionClass->getMethod('_parsePassiveServerInfo');
		$method->setAccessible(true);
		$actual = $method->invoke($client, '227 Entering Passive Mode (127,0,0,1,192,3)');

		$expect = array(
			'host' => '127.0.0.1',
			'port' => 192 * 256 + 3,
		);
		$this->assertSame($expect, $actual);
	}

	public function test_request()
	{
		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_getResponse'))
			->getMock();

		$client
			->expects($this->once())
			->method('_getResponse')
			->will($this->returnValue('the result of _getResponse()'));

		$connection = tmpfile();

		$reflectionClass = new ReflectionClass($client);
		$property = $reflectionClass->getProperty('connection');
		$property->setAccessible(true);
		$property->setValue($client, $connection);

		$method = $reflectionClass->getMethod('_request');
		$method->setAccessible(true);
		$actual = $method->invoke($client, 'REQUEST');

		$this->assertSame('the result of _getResponse()', $actual);

		fseek($connection, 0);
		$this->assertSame("REQUEST\r\n", fgets($connection));
	}

	public function test_request_With_observer()
	{
		$expectedRequest = "REQUEST\r\n";

		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->setMethods(array('_getResponse'))
			->getMock();

		$client
			->expects($this->once())
			->method('_getResponse')
			->will($this->returnValue('the result of _getResponse()'));

		$connection = tmpfile();
		$observer = $this
			->getMockBuilder('stdclass')
			->setMethods(array('updateWithRequest'))
			->getMock();
		$observer
			->expects($this->once())
			->method('updateWithRequest')
			->with($expectedRequest);

		$reflectionClass = new ReflectionClass($client);
		$property = $reflectionClass->getProperty('connection');
		$property->setAccessible(true);
		$property->setValue($client, $connection);

		$property = $reflectionClass->getProperty('observer');
		$property->setAccessible(true);
		$property->setValue($client, $observer);

		$method = $reflectionClass->getMethod('_request');
		$method->setAccessible(true);
		$method->invoke($client, 'REQUEST');
	}

	public function test_getResponse()
	{
		$response = "123 Message Message\r\n";

		$connection = tmpfile();
		fwrite($connection, $response);
		fseek($connection, 0);

		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->getMock();

		$class = new ReflectionClass($client);
		$property = $class->getProperty('connection');
		$property->setAccessible(true);
		$property->setValue($client, $connection);

		$method = $class->getMethod('_getResponse');
		$method->setAccessible(true);
		$actual = $method->invoke($client);
		$expect = array(
			'code'    => 123,
			'message' => $response,
		);
		$this->assertSame($expect, $actual);
	}

	public function test_getResponse_With_observer()
	{
		$response = '123 message message';
		$code = 123;

		$observer = $this
			->getMockBuilder('stdclass')
			->setMethods(array('updateWithResponse'))
			->getMock();
		$observer
			->expects($this->once())
			->method('updateWithResponse')
			->with($response, $code);

		$connection = tmpfile();
		fwrite($connection, $response);
		fseek($connection, 0);

		$client = $this
			->getMockBuilder('Suin_FTPClient_FTPClient')
			->disableOriginalConstructor()
			->getMock();

		$class = new ReflectionClass($client);
		$property = $class->getProperty('connection');
		$property->setAccessible(true);
		$property->setValue($client, $connection);

		$property = $class->getProperty('observer');
		$property->setAccessible(true);
		$property->setValue($client, $observer);

		$method = $class->getMethod('_getResponse');
		$method->setAccessible(true);
		$method->invoke($client);
	}
}
