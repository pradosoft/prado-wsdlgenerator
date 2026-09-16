<?php

namespace Prado\Wsdl\Test\Unit;

use Prado\Wsdl\WsdlGenerator;
use SoapClient;

/**
 * Reads generated documents back with the SOAP client of PHP itself, which is
 * what consumes them in practice. A document can be well formed and still
 * describe nothing usable, so the signatures the client reports are the check.
 *
 * @requires extension soap
 */
class WsdlSoapClientTest extends WsdlTestCase
{
	/**
	 * The documents written for the tests, removed afterwards.
	 * @var array
	 */
	private array $files = [];

	protected function tearDown(): void
	{
		foreach ($this->files as $file) {
			if (is_file($file)) {
				unlink($file);
			}
		}
		$this->files = [];
		parent::tearDown();
	}

	/**
	 * Generates a provider's document and reads it back with a SOAP client.
	 * @param string $className The provider to generate for
	 * @return SoapClient The client reading the document
	 */
	protected function client($className)
	{
		$generator = new WsdlGenerator();
		$generator->generateWsdl($className, 'http://example.com/soap', 'UTF-8');

		$file = tempnam(sys_get_temp_dir(), 'wsdl') . '.wsdl';
		$this->files[] = $file;
		file_put_contents($file, $generator->getWsdl());

		return new SoapClient('file://' . $file, ['exceptions' => true, 'cache_wsdl' => WSDL_CACHE_NONE]);
	}

	public function testTheClientReadsAnOperationSignature()
	{
		$this->assertSame(['int add(int $a, int $b)'], $this->client('WsdlTestProvider')->__getFunctions());
	}

	/**
	 * An array of a primitive used to declare an element type that resolved to
	 * nothing, and the client reported the operation as returning void.
	 */
	public function testTheClientResolvesEveryArrayReturn()
	{
		$this->assertSame([
			'stringArray names()',
			'boolArray flags()',
			'doubleArray sizes()',
			'WsdlTestAddressArray addresses()',
		], $this->client('WsdlTestArrayProvider')->__getFunctions());
	}

	public function testTheClientReadsAVoidOperation()
	{
		$this->assertSame(['void doThing()'], $this->client('WsdlTestVoidProvider')->__getFunctions());
	}

	public function testTheClientSeesATypeNoSignatureNames()
	{
		$types = $this->client('WsdlTestTypeTagProvider')->__getTypes();
		$names = [];
		foreach ($types as $type) {
			$this->assertSame(1, preg_match('/^struct (\w+)/', $type, $match), 'each type is a struct');
			$names[] = $match[1];
		}

		$this->assertContains('WsdlTestPerson', $names);
		$this->assertContains('WsdlTestAddressArray', $names);
		$this->assertContains('WsdlTestAddress', $names);
	}

	public function testTheClientReadsThePropertiesOfADeclaredType()
	{
		$types = $this->client('WsdlTestNestedProvider')->__getTypes();
		$person = '';
		foreach ($types as $type) {
			if (str_starts_with($type, 'struct WsdlTestPerson')) {
				$person = $type;
			}
		}

		$this->assertStringContainsString('string name;', $person);
		$this->assertStringContainsString('WsdlTestAddress address;', $person);
	}
}
