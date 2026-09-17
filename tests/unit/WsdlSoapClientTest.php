<?php

namespace Prado\Wsdl\Test\Unit;

use Prado\Wsdl\Test\Unit\Fixtures\QuoteProvider;
use Prado\Wsdl\Test\Unit\Fixtures\WrappedQuoteProvider;
use Prado\Wsdl\Wsdl;
use Prado\Wsdl\WsdlGenerator;
use SoapClient;
use SoapServer;

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
	 * @var array<int, string>
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
	 * Generates a provider's document and writes it where a client can read it.
	 * @param string $className The provider to generate for
	 * @param string $style The binding style to generate
	 * @return string The URI of the written document
	 */
	protected function wsdlFile($className, $style = Wsdl::STYLE_RPC)
	{
		$generator = new WsdlGenerator();
		$generator->setStyle($style);
		$generator->generateWsdl($className, 'http://example.com/soap', 'UTF-8');

		$file = tempnam(sys_get_temp_dir(), 'wsdl') . '.wsdl';
		$this->files[] = $file;
		file_put_contents($file, $generator->getWsdl());

		return 'file://' . $file;
	}

	/**
	 * Generates a provider's document and reads it back with a SOAP client.
	 * @param string $className The provider to generate for
	 * @param string $style The binding style to generate
	 * @return SoapClient The client reading the document
	 */
	protected function client($className, $style = Wsdl::STYLE_RPC)
	{
		return new SoapClient($this->wsdlFile($className, $style), ['exceptions' => true, 'cache_wsdl' => WSDL_CACHE_NONE]);
	}

	/**
	 * Generates a provider's document, serves a class from it, and returns a
	 * client calling that server in process.
	 * @param string $className The provider to generate for
	 * @param string $style The binding style to generate
	 * @param string $serverClass The class the server hands each request to
	 * @return InProcessSoapClient The client calling the server
	 */
	protected function roundTrip($className, $style, $serverClass)
	{
		$wsdl = $this->wsdlFile($className, $style);

		$server = new SoapServer($wsdl, ['cache_wsdl' => WSDL_CACHE_NONE]);
		$server->setClass($serverClass);

		return new InProcessSoapClient($wsdl, ['exceptions' => true, 'cache_wsdl' => WSDL_CACHE_NONE], $server);
	}

	public function testTheClientReadsAnOperationSignature(): void
	{
		$this->assertSame(['int add(int $a, int $b)'], $this->client('WsdlTestProvider')->__getFunctions());
	}

	/**
	 * An array of a primitive used to declare an element type that resolved to
	 * nothing, and the client reported the operation as returning void.
	 */
	public function testTheClientResolvesEveryArrayReturn(): void
	{
		$this->assertSame([
			'stringArray names()',
			'boolArray flags()',
			'doubleArray sizes()',
			'WsdlTestAddressArray addresses()',
		], $this->client('WsdlTestArrayProvider')->__getFunctions());
	}

	public function testTheClientReadsAVoidOperation(): void
	{
		$this->assertSame(['void doThing()'], $this->client('WsdlTestVoidProvider')->__getFunctions());
	}

	public function testTheClientSeesATypeNoSignatureNames(): void
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

	public function testTheClientReadsThePropertiesOfADeclaredType(): void
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

	/**
	 * A namespaced provider used to produce a document the client refused, its
	 * target namespace not being a URI. The client reads the dotted names, and
	 * in the document style reads each operation as taking its request wrapper,
	 * which is how the client of PHP presents a wrapped document.
	 */
	public function testTheClientReadsANamespacedProvider(): void
	{
		$client = $this->client(QuoteProvider::class);
		$this->assertSame([
			'Prado.Wsdl.Test.Unit.Fixtures.Quote quote(string $symbol, Prado.Wsdl.Test.Unit.Fixtures.Money $limit)',
			'Prado.Wsdl.Test.Unit.Fixtures.QuoteArray history()',
		], $client->__getFunctions());
		$this->assertNamespacedTypesAreRead($client);

		$client = $this->client(QuoteProvider::class, Wsdl::STYLE_DOCUMENT);
		$this->assertSame([
			'quoteResponse quote(quote $parameters)',
			'historyResponse history(history $parameters)',
		], $client->__getFunctions());
		$this->assertNamespacedTypesAreRead($client);
	}

	/**
	 * Asserts that a client reads the namespaced types under their dotted names.
	 * @param SoapClient $client The client reading the document
	 * @return void
	 */
	protected function assertNamespacedTypesAreRead(SoapClient $client)
	{
		$types = implode("\n", $client->__getTypes());
		$this->assertStringContainsString('struct Prado.Wsdl.Test.Unit.Fixtures.Quote {', $types);
		$this->assertStringContainsString('Prado.Wsdl.Test.Unit.Fixtures.Money price;', $types);
		$this->assertStringContainsString('struct Prado.Wsdl.Test.Unit.Fixtures.Money {', $types);
		$this->assertStringContainsString('struct Prado.Wsdl.Test.Unit.Fixtures.QuoteArray {', $types);
	}

	/**
	 * The server of PHP reads the same document to decode a request and encode
	 * its answer, so a call through both ends is the check that each accepts
	 * the document. The client hands an array type back as an object holding
	 * the elements under the element name.
	 */
	public function testAServerAndClientRoundTripANamespacedProviderInRpcStyle(): void
	{
		$client = $this->roundTrip(QuoteProvider::class, Wsdl::STYLE_RPC, QuoteProvider::class);

		$quote = $client->quote('ABC', ['amount' => 2.5, 'currency' => 'EUR']);
		$this->assertSame('ABC', $quote->symbol);
		$this->assertSame(1.5, $quote->price->amount);
		$this->assertSame('EUR', $quote->price->currency);

		$quotes = $client->history()->{'Prado.Wsdl.Test.Unit.Fixtures.Quote'};
		$this->assertSame(['ABC', 'XYZ'], array_map(fn ($quote) => $quote->symbol, $quotes));
	}

	/**
	 * In the document style the server of PHP hands a method the request
	 * wrapper as one object and encodes what it returns as the response
	 * wrapper, and the client calls and reads the same way, so the provider is
	 * served through an adapter following that convention.
	 */
	public function testAServerAndClientRoundTripANamespacedProviderInDocumentStyle(): void
	{
		$client = $this->roundTrip(QuoteProvider::class, Wsdl::STYLE_DOCUMENT, WrappedQuoteProvider::class);

		$quote = $client->quote(['symbol' => 'ABC', 'limit' => ['amount' => 2.5, 'currency' => 'EUR']])->return;
		$this->assertSame('ABC', $quote->symbol);
		$this->assertSame(1.5, $quote->price->amount);
		$this->assertSame('EUR', $quote->price->currency);

		$quotes = $client->history()->return->{'Prado.Wsdl.Test.Unit.Fixtures.Quote'};
		$this->assertSame(['ABC', 'XYZ'], array_map(fn ($quote) => $quote->symbol, $quotes));
	}
}
