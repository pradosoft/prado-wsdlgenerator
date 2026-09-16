<?php

namespace Prado\Wsdl\Test\Unit;

use Prado\Wsdl\Wsdl;
use Prado\Wsdl\WsdlGenerator;
use ReflectionClass;
use ReflectionException;

class WsdlGeneratorTest extends WsdlTestCase
{
	public function testGetInstanceReturnsTheSameGenerator(): void
	{
		$this->assertSame(WsdlGenerator::getInstance(), WsdlGenerator::getInstance());
		$this->assertInstanceOf(WsdlGenerator::class, WsdlGenerator::getInstance());
	}

	public function testGetWsdlIsEmptyBeforeGenerating(): void
	{
		$generator = new WsdlGenerator();
		$this->assertSame('', $generator->getWsdl());
	}

	public function testGenerateReturnsTheDocument(): void
	{
		$wsdl = WsdlGenerator::generate('WsdlTestProvider', 'http://example.com/soap', 'UTF-8');
		$this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $wsdl);
		$this->assertStringContainsString('WsdlTestProviderService', $wsdl);
	}

	public function testOnlyPublicSoapMethodsBecomeOperations(): void
	{
		$dom = $this->generate('WsdlTestProvider');
		$this->assertSame(['add'], $this->attributes($dom, '//wsdl:portType/wsdl:operation', 'name'));
	}

	public function testOperationCarriesItsParametersAndReturn(): void
	{
		$dom = $this->generate('WsdlTestProvider');
		$this->assertSame(['a' => 'xsd:int', 'b' => 'xsd:int'], $this->parts($dom, 'addRequest'));
		$this->assertSame(['return' => 'xsd:int'], $this->parts($dom, 'addResponse'));
	}

	public function testOperationCarriesItsDocumentation(): void
	{
		$dom = $this->generate('WsdlTestProvider');
		$documentation = $this->query($dom, '//wsdl:portType/wsdl:operation/wsdl:documentation');
		$this->assertSame('Adds two numbers.', $documentation->item(0)->textContent);
	}

	public function testScalarTypesConvertToTheirXsdEquivalent(): void
	{
		$dom = $this->generate('WsdlTestScalarProvider');
		$this->assertSame([
			'a' => 'xsd:string',
			'b' => 'xsd:string',
			'c' => 'xsd:int',
			'd' => 'xsd:int',
			'e' => 'xsd:float',
			'f' => 'xsd:float',
			'g' => 'xsd:boolean',
			'h' => 'xsd:boolean',
		], $this->parts($dom, 'scalarsRequest'));
	}

	public function testRemainingTypesConvertToTheirXsdEquivalent(): void
	{
		$dom = $this->generate('WsdlTestScalarProvider');
		$this->assertSame([
			'a' => 'xsd:date',
			'b' => 'xsd:time',
			'c' => 'xsd:dateTime',
			'd' => 'soap-enc:Array',
			'e' => 'xsd:anyType',
			'f' => 'xsd:anyType',
		], $this->parts($dom, 'othersRequest'));
	}

	public function testSoapPropertiesBecomeComplexTypeElements(): void
	{
		$dom = $this->generate('WsdlTestNestedProvider');
		$elements = $this->attributes($dom, "//xsd:complexType[@name='WsdlTestAddress']/*/xsd:element", 'name');
		$this->assertSame(['street', 'zip'], $elements, 'a property without @soapproperty is left out');
	}

	public function testSoapPropertyAttributesReachTheElement(): void
	{
		$dom = $this->generate('WsdlTestNestedProvider');
		$zip = $this->element($dom, "//xsd:complexType[@name='WsdlTestAddress']/*/xsd:element[@name='zip']");
		$this->assertSame('xsd:int', $zip->getAttribute('type'));
		$this->assertSame('true', $zip->getAttribute('nillable'));
		$this->assertSame('0', $zip->getAttribute('minOccurs'));
		$this->assertSame('2', $zip->getAttribute('maxOccurs'));
	}

	public function testAComplexPropertyPullsInItsOwnType(): void
	{
		$dom = $this->generate('WsdlTestNestedProvider');
		$this->assertContains('WsdlTestPerson', $this->complexTypes($dom));
		$this->assertContains('WsdlTestAddress', $this->complexTypes($dom), 'the type of a property is declared too');
	}

	public function testATypeWithoutSoapPropertiesDeclaresNothing(): void
	{
		$dom = $this->generate('WsdlTestBlankTypeProvider');
		$this->assertNotContains('WsdlTestBlank', $this->complexTypes($dom));
	}

	public function testADocCommentWithoutTagsGeneratesAnEmptyMessage(): void
	{
		$dom = $this->generate('WsdlTestDocCommentProvider');
		$this->assertSame([], $this->parts($dom, 'bareRequest'));
		$this->assertSame([], $this->parts($dom, 'bareResponse'));
	}

	public function testDescriptionsContinueOntoTheFollowingLine(): void
	{
		$dom = $this->generate('WsdlTestDocCommentProvider');
		$this->assertSame(['a' => 'xsd:string'], $this->parts($dom, 'continuationRequest'));
		$this->assertSame(['return' => 'xsd:string'], $this->parts($dom, 'continuationResponse'));
	}

	/**
	 * A continuation line reaching the parser before any @param had no parameter
	 * to describe, and indexed the parameter list at -1.
	 */
	public function testAContinuationBeforeAnyParamRaisesNoWarning(): void
	{
		$raised = [];
		set_error_handler(function ($severity, $message) use (&$raised) {
			$raised[] = $message;
			return true;
		});

		try {
			$this->generate('WsdlTestDocCommentProvider');
		} finally {
			restore_error_handler();
		}

		$this->assertSame([], $raised);
	}

	/**
	 * convertType() maps void to an empty string, which is not a usable part.
	 */
	public function testAVoidReturnProducesNoPart(): void
	{
		$dom = $this->generate('WsdlTestVoidProvider');
		$this->assertSame([], $this->parts($dom, 'doThingResponse'));
		$this->assertCount(1, $this->query($dom, "//wsdl:message[@name='doThingResponse']"));
	}

	/**
	 * generate() hands back a singleton, which used to carry the types of the
	 * previous generation into the next document.
	 */
	public function testAGenerationDoesNotInheritTheTypesOfTheLast(): void
	{
		$generator = WsdlGenerator::getInstance();

		$generator->generateWsdl('WsdlTestArrayProvider', 'http://example.com/soap', 'UTF-8');
		$this->assertStringContainsString('stringArray', $generator->getWsdl());

		$generator->generateWsdl('WsdlTestVoidProvider', 'http://example.com/soap', 'UTF-8');
		$this->assertStringNotContainsString('stringArray', $generator->getWsdl());
	}

	public function testTheDocumentOfAGenerationReplacesTheLast(): void
	{
		$generator = new WsdlGenerator();
		$generator->generateWsdl('WsdlTestProvider', 'http://example.com/soap', 'UTF-8');
		$first = $generator->getWsdl();

		$generator->generateWsdl('WsdlTestVoidProvider', 'http://example.com/soap', 'UTF-8');
		$this->assertNotSame($first, $generator->getWsdl());
	}

	public function testSoapTypeDeclaresATypeNoSignatureNames(): void
	{
		$dom = $this->generate('WsdlTestTypeTagProvider');
		$types = $this->complexTypes($dom);
		$this->assertContains('WsdlTestPerson', $types);
		$this->assertContains('WsdlTestAddressArray', $types);
		$this->assertContains('WsdlTestAddress', $types, 'the array form declares its element type as well');
	}

	public function testSoapTypeIsReadFromAOneLineDocComment(): void
	{
		$dom = $this->generate('WsdlTestOneLineTagProvider');
		$this->assertContains('WsdlTestPerson', $this->complexTypes($dom));
	}

	public function testSoapTypeAcceptsSpacedBrackets(): void
	{
		$dom = $this->generate('WsdlTestProseProvider');
		$this->assertContains('WsdlTestAddressArray', $this->complexTypes($dom));
	}

	/**
	 * The tag has to be in tag position. Prose naming it is not a declaration.
	 */
	public function testProseNamingTheTagDeclaresNothing(): void
	{
		$dom = $this->generate('WsdlTestProseProvider');
		$this->assertSame(['WsdlTestAddressArray', 'WsdlTestAddress'], $this->complexTypes($dom));
	}

	public function testAProviderWithoutTheTagDeclaresNothing(): void
	{
		$dom = $this->generate('WsdlTestNoTagProvider');
		$this->assertSame([], $this->complexTypes($dom));
	}

	public function testSoapTypeNamingAMissingClassFails(): void
	{
		$this->expectException(ReflectionException::class);
		$this->generate('WsdlTestBadTagProvider');
	}

	/**
	 * An array of a primitive used to name tns:<type>, which the document never
	 * declares. A schema validator rejects a reference that does not resolve.
	 *
	 * @dataProvider providerNameProvider
	 * @param string $className The provider to generate for
	 */
	public function testEveryTypeAGeneratedDocumentNamesIsDeclared($className): void
	{
		$this->assertEveryLocalTypeResolves($this->generate($className));
	}

	/**
	 * @dataProvider providerNameProvider
	 * @param string $className The provider to generate for
	 */
	public function testTheSchemaOfAGeneratedDocumentCompiles($className): void
	{
		$this->assertSchemaCompiles($this->generate($className));
	}

	/**
	 * @dataProvider providerNameProvider
	 * @param string $className The provider to generate for
	 */
	public function testTheSchemaOfADocumentStyleDocumentCompiles($className): void
	{
		$dom = $this->generate($className, 'http://example.com/soap', Wsdl::STYLE_DOCUMENT);
		$this->assertSchemaCompiles($dom);
		$this->assertEveryLocalTypeResolves($dom);
	}

	/**
	 * The Basic Profile requires a literal body and one part naming an element
	 * (R2201, R2706), and prohibits an encodingStyle with it (R2716).
	 *
	 * @dataProvider providerNameProvider
	 * @param string $className The provider to generate for
	 */
	public function testADocumentStyleDocumentFollowsTheBasicProfile($className): void
	{
		$dom = $this->generate($className, 'http://example.com/soap', Wsdl::STYLE_DOCUMENT);

		$this->assertSame(['document'], $this->attributes($dom, '//soap:binding', 'style'));

		foreach ($this->query($dom, '//soap:body') as $body) {
			$this->assertInstanceOf(\DOMElement::class, $body);
			$this->assertSame('literal', $body->getAttribute('use'));
			$this->assertFalse($body->hasAttribute('encodingStyle'));
			$this->assertFalse($body->hasAttribute('namespace'));
		}

		$declared = $this->attributes($dom, '//xsd:schema/xsd:element', 'name');
		foreach ($this->query($dom, '//wsdl:message') as $message) {
			$this->assertInstanceOf(\DOMElement::class, $message);
			$parts = $message->getElementsByTagNameNS(self::WSDL_NS, 'part');
			$this->assertCount(1, $parts, $message->getAttribute('name') . ' carries one part');

			$part = $parts->item(0);
			$this->assertInstanceOf(\DOMElement::class, $part);
			$this->assertFalse($part->hasAttribute('type'), 'the part names an element, not a type');
			$this->assertContains(substr($part->getAttribute('element'), 4), $declared);
		}
	}

	public function testTheRequestElementCarriesTheParameters(): void
	{
		$dom = $this->generate('WsdlTestProvider', 'http://example.com/soap', Wsdl::STYLE_DOCUMENT);

		$parameters = $this->attributes($dom, "//xsd:element[@name='add']/xsd:complexType/xsd:sequence/xsd:element", 'name');
		$this->assertSame(['a', 'b'], $parameters);

		$returns = $this->attributes($dom, "//xsd:element[@name='addResponse']/xsd:complexType/xsd:sequence/xsd:element", 'type');
		$this->assertSame(['xsd:int'], $returns);
	}

	public function testAVoidReturnWrapsAnEmptyResponseElement(): void
	{
		$dom = $this->generate('WsdlTestVoidProvider', 'http://example.com/soap', Wsdl::STYLE_DOCUMENT);

		$this->assertCount(1, $this->query($dom, "//xsd:element[@name='doThingResponse']"));
		$this->assertCount(0, $this->query($dom, "//xsd:element[@name='doThingResponse']//xsd:element"));
	}

	/**
	 * SOAP encoding is prohibited in literal, so soap-enc:Array cannot be named.
	 */
	public function testAnUntypedArrayIsNotSoapEncodedInDocumentStyle(): void
	{
		$rpc = $this->generate('WsdlTestScalarProvider');
		$this->assertContains('soap-enc:Array', $this->attributes($rpc, '//wsdl:part', 'type'));

		$document = $this->generate('WsdlTestScalarProvider', 'http://example.com/soap', Wsdl::STYLE_DOCUMENT);
		$this->assertStringNotContainsString('soap-enc:Array', $document->saveXML());
	}

	public function testAnUnknownStyleIsRefused(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->generate('WsdlTestProvider', 'http://example.com/soap', 'literal');
	}

	public function testTheStyleDefaultsToRpcAndIsSettable(): void
	{
		$generator = new WsdlGenerator();
		$this->assertSame(Wsdl::STYLE_RPC, $generator->getStyle());

		$generator->setStyle(Wsdl::STYLE_DOCUMENT);
		$this->assertSame(Wsdl::STYLE_DOCUMENT, $generator->getStyle());
	}

	public function testGenerateTakesTheStyle(): void
	{
		$document = WsdlGenerator::generate('WsdlTestProvider', 'http://example.com/soap', 'UTF-8', Wsdl::STYLE_DOCUMENT);
		$this->assertStringContainsString('style="document"', $document);
		$this->assertStringContainsString('use="literal"', $document);

		$rpc = WsdlGenerator::generate('WsdlTestProvider', 'http://example.com/soap', 'UTF-8');
		$this->assertStringContainsString('style="rpc"', $rpc);
		$this->assertStringContainsString('use="encoded"', $rpc);
	}

	/**
	 * The style is carried rather than passed, so a subclass written against the
	 * signatures of 1.1 still loads.
	 */
	public function testTheGenerationSignaturesAreThoseOf11(): void
	{
		$generateWsdl = new \ReflectionMethod(WsdlGenerator::class, 'generateWsdl');
		$this->assertSame(3, $generateWsdl->getNumberOfParameters());

		$setMessageElements = new \ReflectionMethod(\Prado\Wsdl\WsdlOperation::class, 'setMessageElements');
		$this->assertSame(2, $setMessageElements->getNumberOfParameters());
	}

	/**
	 * @return array<int, array<int, string>> The provider to generate for
	 */
	public static function providerNameProvider(): array
	{
		return [
			['WsdlTestProvider'],
			['WsdlTestScalarProvider'],
			['WsdlTestArrayProvider'],
			['WsdlTestVoidProvider'],
			['WsdlTestTypeTagProvider'],
			['WsdlTestNestedProvider'],
			['WsdlTestDocCommentProvider'],
		];
	}

	/**
	 * A marker ends its line, so a comment that goes on to say something about
	 * the tag is discussing it rather than carrying it.
	 */
	public function testProseNamingTheMethodMarkerDeclaresNoOperation(): void
	{
		$dom = $this->generate('WsdlTestProseMarkerProvider');
		$this->assertSame(['oneLineMarker', 'compactMarker'], $this->attributes($dom, '//wsdl:portType/wsdl:operation', 'name'));
	}

	/**
	 * A marker written after a description on the same line is a marker. This is
	 * how a terse doc comment carries one.
	 */
	public function testTheMethodMarkerIsReadAfterADescription(): void
	{
		$dom = $this->generate('WsdlTestProseMarkerProvider');
		$this->assertSame(['return' => 'xsd:string'], $this->parts($dom, 'compactMarkerResponse'));
	}

	public function testTheMethodMarkerIsReadFromAOneLineDocComment(): void
	{
		$dom = $this->generate('WsdlTestProseMarkerProvider');
		$this->assertSame(['return' => 'xsd:string'], $this->parts($dom, 'oneLineMarkerResponse'));
	}

	public function testProseNamingThePropertyMarkerExportsNoElement(): void
	{
		$dom = $this->generate('WsdlTestNestedProvider');
		$elements = $this->attributes($dom, "//xsd:complexType[@name='WsdlTestAddress']/*/xsd:element", 'name');
		$this->assertSame(['street', 'zip'], $elements);
		$this->assertNotContains('internal', $elements);
	}

	public function testThePropertyMarkerIsReadFromAOneLineDocComment(): void
	{
		$dom = $this->generate('WsdlTestProseMarkerProvider');
		$elements = $this->attributes($dom, "//xsd:complexType[@name='WsdlTestOneLineType']/*/xsd:element", 'name');
		$this->assertSame(['a'], $elements);
	}

	/**
	 * @dataProvider markerCommentProvider
	 * @param string $comment The doc comment to read
	 * @param bool $expected Whether the comment carries the tag
	 */
	public function testHasTagReadsATagOnlyInTagPosition($comment, $expected): void
	{
		$method = new \ReflectionMethod(WsdlGenerator::class, 'hasTag');
		$method->setAccessible(true);
		$this->assertSame($expected, $method->invoke(null, $comment, 'soapmethod'));
	}

	/**
	 * @return array<string, array{0: false|string, 1: bool}> The comment and what it carries
	 */
	public static function markerCommentProvider(): array
	{
		return [
			'on its own line' => ["/**\n * @soapmethod\n */", true],
			'opening the comment' => ['/** @soapmethod */', true],
			'leading tabs' => ["/**\n\t * @soapmethod\n\t */", true],
			'with other tags' => ["/**\n * Does a thing.\n * @soapmethod\n * @return string x\n */", true],
			'after a description on one line' => ['/** Adds two numbers. @soapmethod */', true],
			'after a description on its line' => ["/**\n * Adds two numbers. @soapmethod\n */", true],
			'trailing space' => ["/**\n * @soapmethod  \n */", true],
			'named in prose' => ['/** Discusses the @soapmethod tag. */', false],
			'mid sentence' => ["/**\n * Use the @soapmethod tag here.\n */", false],
			'negated in prose' => ["/**\n * Carries no @soapmethod, so it is skipped.\n */", false],
			'a longer tag' => ["/**\n * @soapmethods\n */", false],
			'absent' => ["/**\n * Does a thing.\n */", false],
			'no doc comment' => [false, false],
		];
	}

	public function testProcessTypeTagsIgnoresAClassWithoutADocComment(): void
	{
		$generator = new WsdlGenerator();
		$generator->generateWsdl('WsdlTestProvider', 'http://example.com/soap', 'UTF-8');

		$this->callProtected($generator, 'processTypeTags', [new ReflectionClass(\WsdlTestUndocumented::class)]);

		$this->assertStringNotContainsString('complexType', $generator->getWsdl());
	}
}
